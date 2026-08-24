<?php

namespace App\Http\Controllers\Booth;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Account;
use App\Models\TollTransaction;
use App\Models\Transaction;

// ERMS services
use App\Services\Erms\ErmsMiscellaneousSubmissionService;
use App\Services\Erms\Mappers\CashlessTollMiscellaneousMapper;
// End of ERMS services
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// logging
use Illuminate\Support\Facades\Log;


class PayAndGoController extends ConfigurationController
{
    public static array $RES_STATUS = [
        'FAILED' => 0,
        'SUCCESS' => 1,
        'EXEMPTED' => 2,
        'CASH_PAID' => 3
    ];

    public function payAndGo(Request $request)
    {

        if ($request->mcv != 0) { // this block deals with manual capture vehicles (mcv)

            if ($request->exempted == 1) { // If the vehicle is exempted manually from the request
                $tran = TollTransaction::recordTollTransaction(true, 'CASHLESS', $request, null, null); // todo: exemption reason
                if ($tran != null) {
                    return ['status' => self::$RES_STATUS['SUCCESS'], 'plate_no' => $request->plate_no, 'message' => 'Vehicle is successfully Exempted'];
                }
                return ['status' => self::$RES_STATUS['FAILED'], 'message' => 'Failed To Record Transaction'];
            }
        }

        $vehicle = DB::table('vehicle')->where('plate_no', $request->plate_no)->first();

        if ($vehicle != null) {

            // Handle exempted vehicle passage
            if ($vehicle->exempted == '1') {
                $trans = TollTransaction::recordTollTransaction(true, 'CASHLESS', $request, null, null);
                if ($trans != null) {
                    return ['status' => self::$RES_STATUS['EXEMPTED'], 'plate_no' => $request->plate_no, 'message' => 'Vehicle  is Exempted, Proceed'];
                }
                return ['status' => self::$RES_STATUS['FAILED'], 'message' => 'Failed To Record Transaction'];
            }

            // Process bundle subscription passage, only if the vehicle has bundle
            $processedVehicleBundlePassage = $this->processBundleSubscriptionPassage($request, $vehicle);
//            return $processedVehicleBundlePassage;
            if ($processedVehicleBundlePassage['status']) {
                return $processedVehicleBundlePassage['response'];
            }

            // Process credit balance passage
            $processedCreditBalancePassage = $this->processCreditBalancePassage($request, $vehicle);

            if ($processedCreditBalancePassage['status']) {
                return $processedCreditBalancePassage['response'];
            }

        }

        // handle cash payment
        $toll_transaction = TollTransaction::recordTollTransaction(false,'CASH', $request, null, null);

        if ($toll_transaction != null) {
            return ['status' => 3, 'message'=> 'Vehicle Charged successfully', 'data' => ['trans_id' => $toll_transaction['toll']->id]];
        }

        return ['status' => true];
    }

    private function processCreditBalancePassage(Request $request, $vehicle): array
    {
        $account = Account::where('account_no', $vehicle->account_no)->first();
        if ($account == null) {
            return ['status' => false, 'message' => 'Account not found'];
        }

        if ($account->account_balance >= $request->amount) {
            $new_account_balance = ($account->account_balance - $request->amount);
            Account::where(['account_no'=>$vehicle->account_no])->update([
                    'account_balance' => $new_account_balance,
                    'updated_at' => now()->toDateTimeString(),
                    'updated_by' => $request->user_id
                ]);

//            $account->account_balance = $new_account_balance;
//            $account->save();


            // record transaction
            $transaction = TollTransaction::recordTollTransaction(false, 'CASHLESS', $request, $vehicle->id, $account->id);

            if($transaction == null){
                return ['status' => false];
            }

            // record IDS SMS
            $sms_body = 'Makato ya Tozo ya kupita darajani' . "\n" .
                'Akaunti: ' . $vehicle->account_no . "\n" .
                'Jina: ' . $account->first_name . ' ' . $account->surname . "\n" .
                'Gari: ' . $account['plate_no'] . "\n" .
                'Tarehe: ' . date('d-M-Y h:i:s A') . "\n" .
                'Kiasi: ' . str_replace("¤", "", $request->amount) . " TZS\n" .
                'Salio: ' . str_replace("¤", "", $new_account_balance) . " TZS\n" .
                'Risiti: ' . $transaction['toll']->receipt_num . "\n" .
                'NSSF - Nyerere Bridge';

            DB::table('ids_messages')
                ->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $account->phone,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => 'Toll Deductions',
                ]);

            // submit cashless miscellaneous entry to ERMS
            $toll = $transaction['toll'];
            try {
                if ((float) $toll->charged_amount > 0) {
                    $miscAttrs = app(CashlessTollMiscellaneousMapper::class)->map($toll, $account);
                    $erms = app(ErmsMiscellaneousSubmissionService::class)->submit($miscAttrs);

                    // update the toll transaction erms status to submitted
                    if ($erms['ok']) {
                        TollTransaction::where('id', $toll->id)->update(['erms_status' => 1]);
                    } else if (! $erms['ok']) {
                        TollTransaction::where('id', $toll->id)->update(['erms_status' => 2]);
                        Log::warning('ERMS miscellaneous (credit balance cashless) failed: ' . $erms['error'], [
                            'toll_transaction_id' => $toll->id,
                            'http_status' => $erms['http_status'],
                            'error' => $erms['error'],
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('ERMS miscellaneous (credit balance cashless) exception; passage not blocked', [
                    'toll_transaction_id' => $toll->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // End of submit cashless miscellaneous entry to ERMS

            return [
                'status' => true,
                'response' => [
                    'status' => 4,
                    'tag_reference' => $vehicle->rfid_tag_no,
                    'amount' => $request->amount,
                    'account' => $account,
                    'body_type' => $vehicle->body_type_id,
                    'message' => 'Toll Pass Request Granted Successfully and Amount deducted'
                ]];

        }
        return ['status' => false, 'account' => $account];

    }

    private function processBundleSubscriptionPassage(Request $request, $vehicle): array
    {
        $activeBundle = DB::table('bundle_subscriptions')
            ->select('id')
            ->where('status', 1)
            ->where('vehicle_id', $vehicle->id)->first();
        if ($activeBundle != null) {
            $bundleSubPassageID = DB::table('bundle_subscription_passage')
                ->insertGetId([
                    'card_number' => $request->card_number,  // todo: card number or account number?
                    'lane_id' => $request->lane_id,
                    'arrival_time' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(), // todo: difference with arrival time?
                    'shift_id' => $request->shift_id,
                    'created_by' => $request->user_id
                ]);

            return [
                'status' => true,
                'response' => [
                    'status' => 7,
                    'passage_id' => $bundleSubPassageID,
                    'message' => 'Vehicle with Plate number ' . $vehicle->plate_no . ' can pass']
            ];
        }

        return ['status' => false,];

    }

}
