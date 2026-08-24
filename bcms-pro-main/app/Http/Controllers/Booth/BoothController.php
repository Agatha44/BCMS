<?php

namespace App\Http\Controllers\Booth;
use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Account;
use App\Models\CancelledDetection;
use App\Models\Counter;
use App\Models\Detection;
use App\Models\OpenGate;
use App\Models\Receipt;
use App\Models\ReprintReceipt;
use App\Models\TollTransaction;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BoothController extends ConfigurationController
{

    public function openCounter(Request $request): array
    {
        $shift_id = $request->shift_id;
        $user_id = $request->user_id;
        $lane_id = $request->lane_id;
        $payment_method = $request->payment_method;

        // Get today's date to check against `open_counter`
        $today = now()->startOfDay()->toDateTimeString();

        // Check if the user has logged into any counter today
        $check_counter = Counter::where('user_id', $user_id)
            ->whereDate('open_counter', '>=', $today)
            ->whereNull('close_counter')
            ->first();
        // If the user has logged into any counter today, check if it’s the same counter
        if ($check_counter != null) {
            if ($check_counter->lane_id == $lane_id && $check_counter->shift_id == $shift_id) {
                // User is already logged into the same counter
                $check_counter['shift'] = DB::table('shift')->where('id', $check_counter->shift_id)->first();
                $check_counter['lane'] = DB::table('lane')->where('id', $check_counter->lane_id)->first();
                return [
                    'status' => 1,
                    'message' => 'Counter Already logged',
                    'counter' => $check_counter
                ];
            } else {
                // User logged into a different counter today
                return [
                    'status' => 0,
                    'message' => 'Failed to create counter: User already logged into a different booth today.'
                ];
            }
        }
        // Proceed to create a new counter if no open counters exist for today
        $counter = new Counter();
        $counter->lane_id = $lane_id;
        $counter->shift_id = $shift_id;
        $counter->user_id = $user_id;
        $counter->payment_method = $payment_method;
        $counter->open_counter = now()->toDateTimeString();

        if ($counter->save()) {
            $counter['shift'] = DB::table('shift')->where('id', $counter->shift_id)->first();
            $counter['lane'] = DB::table('lane')->where('id', $counter->lane_id)->first();

            return [
                'status' => 1,
                'counter' => $counter
            ];
        }

        return ['status' => 0, 'message' => 'Failed to create counter'];
    }

    public function closeCounter(Request $request): array
    {
        $check_counter = Counter::where('user_id', $request->user_id)
            ->where('lane_id', $request->lane_id)
            ->where('payment_method', $request->payment_method)
            ->where('shift_id', $request->shift_id)
            ->where('open_counter', $request->opendate)
            ->where('close_counter', null)
            ->first();

        if ($check_counter != null) {
            $check_counter->update(['close_counter' => now()->toDateTimeString()]);

            // todo: ask more on log shift record
            DB::table('shift_record')
                ->insert([
                    'shift_id' => $request->shift_id,
                    'user_id' => $request->user_id,
                    'open_counter' => $check_counter->open_counter,
                    'close_counter' => $check_counter->close_counter
                ]);

            return ['message' => 'Successfully Closed Counter and Logged Shift Report', 'status' => 1];
        }

        return ['message' => 'Failed to Close Counter', 'status' => 0];
    }

    public function rePrintReceipt(Request $request)
    {
        $lane_id = $request->lane_id;
        $user_id = $request->user_id;
        $reason = $request->reason;

        $toll_trans = TollTransaction::where('lane_id', $lane_id)
            ->orderBy('id', 'DESC')
            ->first();

        if ($toll_trans != null) {
            ReprintReceipt::create([
                'reason' => $reason,
                'user_id' => $user_id,
                'lane_id' => $lane_id,
                'receipt_no' => $toll_trans->receipt_num,
                'created_at' =>now()->toDateTimeString()
            ]);

            return [
                'status' => 1,
                'trans_id' => $toll_trans->id
            ];
        }

        return [
            'status' => 0,
            'No transaction found'
        ];
    }

    public function cancelledDetection(Request $request)
    {
        $lane_id = $request->lane_id;
        $shift_id = $request->shift_id;
        $user_id = $request->user_id;
        $plate_no = $request->plate_no;
        $amount = $request->amount;
        $body_type_id = $request->body_type_id;

        CancelledDetection::create([
            'plate_no' => $plate_no,
            'user_id' => $user_id,
            'lane_id' => $lane_id,
            'shift_id' => $shift_id,
            'amount' => $amount,
            'body_type_id' => $body_type_id,
            'created_at' =>now()->toDateTimeString()
        ]);

        return ['message' => 'Detection logged', 'status' => 1];
    }

    public function detection(Request $request)
    {
        $lane_id = $request->lane_id;
        $shift_id = $request->shift_id;
        $user_id = $request->user_id;
        $plate_no = $request->plate_no;
        $amount = $request->amount;
        $body_type_id = $request->body_type_id;

        Detection::create([
            'plate_no' => $plate_no,
            'user_id' => $user_id,
            'lane_id' => $lane_id,
            'shift_id' => $shift_id,
            'amount' => $amount,
            'body_type_id' => $body_type_id,
            'created_at' =>now()->toDateTimeString()
        ]);

        return ['message' => 'Detection logged', 'status' => 1];
    }

    public function openGate(Request $request)
    {
        $lane_id = $request->lane_id;
        $user_id = $request->user_id;
        $reason = $request->reason;

        OpenGate::create([
            'reason' => $reason,
            'user_id' => $user_id,
            'lane_id' => $lane_id,
            'created_at' =>now()->toDateTimeString()
        ]);

        return ['message' => 'Record logged', 'status' => 1];
    }

    public function cardPassage(Request $request): array
    {
        $account = Account::where('nfc_card', $request->card_no)->first();
        $price = DB::table('price_list')->where('body_type_id', $request->body_type_id)->first();

        if ($account != null && $price != null) {
            if ($account->account_balance >= $price->amount) {
                $new_account_balance = ($account->account_balance - $price->amount);
                Account::where(['nfc_card'=> $request->card_no])->update([
                    'account_balance' => $new_account_balance,
                    'updated_at' => now()->toDateTimeString(),
                    'updated_by' => $request->user_id
                ]);


                // record transaction
                $transaction = TollTransaction::recordTollTransaction(false, 'CASHLESS',
                    $request,
                    null, $account->id);


                // record IDS SMS
                $sms_body = 'Makato ya Tozo ya kupita darajani' . "\n" .
                    'Akaunti: ' . $account->account_no . "\n" .
                    'Jina: ' . $account->first_name . ' ' . $account->surname . "\n" .
                    'Gari: ' . $account['plate_no'] . "\n" .
                    'Tarehe: ' . date('d-M-Y h:i:s A') . "\n" .
                    'Kiasi: ' . str_replace("¤", "", $price->amount) . " TZS\n" .
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

                return [
                    'status' => 1,
                    'nfc_card' => $account->nfc_card,
                    'amount' => $request->amount,
                    'account' => $account,
                    'body_type' => $request->body_type_id,
                    'message' => 'Toll Pass Request Granted Successfully and Amount deducted'
                ];

            }

            else {
                try {
                    $response = Http::post('http://localhost:3004/passage-denied/'.$request->lane_id, [
                        'card_no' => $request->card_no,
                        'lane_id' => $request->lane_id,
                        'amount' => $price->amount,
                        'balance' => $account->account_balance,
                        'message' => 'Insufficient funds'
                    ]);
                    if ($response->ok()) {
                        // Handle successful response
                    } else {
                        // Handle non-200 status codes
                    }
                } finally {
                    return ['status' => 2, 'account' => $account, 'price' => $price, 'message' => 'Insufficient balance'];
                }
            }
        }

        return ['status' => 0, 'account' => $account, 'price' => $price];

    }

    public function testURL(): array
    {
        $receipt = Receipt::create(['prefix' => 'B',]);

        return ['status' => true, 'receipt' => $receipt];
    }

//    public function detectVehicleFromCardNumber(Request $request)
//    {
//        $account = Account::where('account_no', $request->account_no)->first();
//        if ($account != null) {
//            if ($account->account_balamce >= $request->amount) {
//                $new_account_balance = ($account->account_balance - $request->amount);
//
//                // record transaction
//                $transaction = Transaction::recordTransaction(false, 'CASHLESS', $request, $vehicle->id);
//
//                if($transaction == null){
//                    return ['status' => false];
//                }
//
//                Account::where('account_no', $request->account_no)->update([
//                    'account_balance' => $new_account_balance,
//                    'updated_at' => now()->toDateTimeString(),
//                    'updated_by' => $request->user_id // todo: check user from the current shift
//                ]);
//
//            }
//            // check amount
//        }
//        return ['status' => true];
//    }
}
