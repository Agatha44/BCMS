<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\BasicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\BridgeBill;
use App\Models\Receipt;
use App\Models\EventPayment;
use App\Models\Vehicle;
use App\Models\Account;
use App\Models\BundleSubscription;
use App\Models\TollBundle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Helpers\EnvironmentHelper;
use Illuminate\Http\JsonResponse;
use App\Helpers\GePG;
use App\Models\IncidentFine;
use App\Models\OverloadFine;
use App\Services\Erms\ErmsReceiptSubmissionService;
use App\Services\Erms\Mappers\BridgeBillReceiptMapper;
use App\Services\Erms\Mappers\EventPaymentReceiptMapper;
use App\Services\Erms\Mappers\IncidentFineReceiptMapper;
use App\Services\Erms\Mappers\OverloadFineReceiptMapper;
use App\Services\Erms\Mappers\PrepaymentMapper;
use App\Services\Audit\OwenItAuditWriter;
use Carbon\Carbon;

class BillingController extends BasicController
{
    const STATUS_CODE_PRICE_LIST_NOT_FOUND = 'BILLING_001';
    const STATUS_CODE_GEPG_INVALID_PARAMS = 'BILLING_002';
    const STATUS_CODE_GEPG_INVALID_REQUEST = 'BILLING_003';
    const PAID = 'PAID';
    const CANCELLED = 'CANCELLED';


    public function requestBundle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bundle_type' => 'required|string|exists:toll_bundles,id',
            'account_id' => 'required|string|exists:account,account_no',
            'vehicle_id' => 'required|string|exists:vehicle,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $validatedData = $validator->validated();

        // todo: checkf if account has unpaid control number

        // todo: check if the account and vehicle are linked

        try {
            DB::beginTransaction();

            $bundle = TollBundle::find($validatedData['bundle_type']);

            $activeSubscription = BundleSubscription::activeSubscription($request->account_id, $request->vehicle_id);

            $startDate = now();
            if ($activeSubscription) {
                // Update start date with the expiration date of the current active subscription if any
                $startDate = $activeSubscription->expire_date;
            }

            $user = Auth::user();
            Log::info('User', ['user' => $user]);

            // Create a new bundle subscription
            $bundleSubscription = BundleSubscription::create([
                'account_id' => $request->account_id,
                'vehicle_id' => $request->vehicle_id,
                'bundle_id' => $bundle->id,
                'status' => BundleSubscription::STATUS_PENDING,
                'start_date' => $startDate,
                'expire_date' => $bundle->calculateExpireDate($startDate, $bundle->duration),
                'created_by' => $user->id,
            ]);

            Log::info('Bundle subscription created successfully', ['bundle_subscription' => $bundleSubscription]);


            // Proceed to create control number
            $bill = BridgeBill::requestBundleSubscriptionControlNo($bundleSubscription, $user);

            if ($bill['success']) {
                DB::commit();
                return $this->sendResponse('Bundle subscription created successfully', ['bundle_subscription' => $bundleSubscription]);
            }
            DB::rollBack();
            return $this->sendError($bill['message'], $bill);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bundle subscription error', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('An error occurred. Please try again or contact support.');
        }
    }

    public function requestBundleUssd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bundle_id' => 'required|string|exists:toll_bundles,id',
            'plate_no' => 'required|string',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $validatedData = $validator->validated();


        $vehicle = Vehicle::where('plate_no', $validatedData['plate_no'])->first();
        if (!$vehicle) {
            return $this->sendError('Vehicle not found');
        }

        // i want  to get vehicle_id  and account_id
        $vehicle_id = $vehicle->id;
        $account = Account::where('account_no', $vehicle->account_no)->first();

        try {
            DB::beginTransaction();

            $bundle = TollBundle::find($validatedData['bundle_id']);

            $activeSubscription = BundleSubscription::activeSubscription($account->id, $vehicle_id);

            $startDate = now();
            if ($activeSubscription) {
                // Update start date with the expiration date of the current active subscription if any
                $startDate = $activeSubscription->expire_date;
            }

            $user = Auth::user();
            Log::info('User', ['user' => $user]);

            // Create a new bundle subscription
            $bundleSubscription = BundleSubscription::create([
                'account_id' => $account->id,
                'vehicle_id' => $vehicle_id,
                'bundle_id' =>$validatedData['bundle_id'],
                'status' => BundleSubscription::STATUS_PENDING,
                'start_date' => $startDate,
                'expire_date' => $bundle->calculateExpireDate($startDate, $bundle->duration),
                'created_by' => $user->id,
            ]);

            Log::info('Bundle subscription created successfully', ['bundle_subscription' => $bundleSubscription]);


            // Proceed to create control number
            $bill = BridgeBill::requestBundleSubscriptionControlNo($bundleSubscription, $user);

            if ($bill['success']) {
                DB::commit();
                return $this->sendResponse('Bundle subscription created successfully', ['bundle_subscription' => $bundleSubscription]);
            }
            DB::rollBack();
            return $this->sendError($bill['message'], $bill);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bundle USSD request error', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('An error occurred. Please try again or contact support.');
        }

    }

    public function receiveControlNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'control_no' => 'required|string|max:255',
            'bill_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $control_no = request()->control_no;
        $bill_id = request()->bill_id;

        // implement the logic to receive the control number

        return $this->sendResponse('Control number received successfully', ['control_no' => $control_no, 'bill_id' => $bill_id]);
    }

    public function receiveControlNumberFromDMZ(Request $request)
    {
        $control_number = $request->all();

        Log::info('Control number received from DMZ', [
            'control_number_data' => $control_number,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Handle nested data structure
        $data = $control_number['data'] ?? $control_number;

        $bill_id = $data['bill_id'] ?? null;
        if (!$bill_id) {
            Log::error('bill_id not found in control number data', ['control_number' => $control_number]);
            return response()->json(['status' => 7201, 'error' => 'bill_id not found']);
        }

        $bill = substr($bill_id, 0, 3);

        Log::info('Processing control number', [
            'bill_id' => $bill_id,
            'bill_type' => $bill
        ]);

        if ($bill == "OLF") {
            Log::info('Processing OLF (Overload Fine) control number', [
                'bill_id' => $bill_id,
                'id' => str_replace("OLF", "", $bill_id)
            ]);

            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("OLF", "", $bill_id);

            if (!$contr_num) {
                Log::error('Control number not found in data', ['data' => $data]);
                return response()->json(['status' => 7201, 'error' => 'Control number not found']);
            }

            $model = DB::table('overload_fine')->where('id', $id)->first();
            if (!$model) {
                Log::error('Overload fine not found', ['id' => $id]);
                return response()->json(['status' => 7201, 'error' => 'Overload fine not found']);
            }

            $updated = DB::table('overload_fine')
                ->where('id', $id)
                ->update([
                    'contr_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('OLF control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);

                $ticket_num = $model->ticket_num;
                // Send to Tload system
                $tloadResult = $this->sendToTload($ticket_num, $contr_num);
                Log::info('Tload API call result', [
                    'ticket_num' => $ticket_num,
                    'contr_num' => $contr_num,
                    'result' => $tloadResult
                ]);

                $sms_recipient = $model->pyr_cell_num;
                $bill_amount = $model->bill_amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Lipa faini ya Kuzidisha Uzito ' . "\n" .
                           'Mlipaji: ' . $model->first_name . '  ' . $model->surname . "\n" .
                           'Kiasi:' . $amount . " TZS\n" .
                           'Kumb.Na: ' . $contr_num . "\n" .
                           'Lipa kwa Simu Malipo ya Serikali, Benki NMB,NBC,CRDB au Wakala wa Benki Tajwa';
                $sms_process = 'Overload Payment Request';

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                return response()->json(['status' => 7101]);
            } else {
                return response()->json(['status' => 7201]);
            }
        }
        elseif ($bill == "SHB") {

            Log::info('Processing SHB (Bridge Bill) control number', [
                'bill_id' => $bill_id,
                'id' => str_replace("SHB", "", $bill_id)
            ]);

            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("SHB", "", $bill_id);

            $updated = DB::table('bridge_bills')
                ->where('id', $id)
                ->update([
                    'contr_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('SHB control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                return response()->json(['status' => 7101]);
            } else {
                Log::error('Failed to update SHB control number', ['id' => $id]);
                return response()->json(['status' => 7201, 'error' => 'Failed to update bridge bill']);
            }
        }
        elseif ($bill == "ADV") {
            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("ADV", "", $bill_id);

            $updated = DB::table('bridge_bills')
                ->where('id', $id)
                ->update([
                    'contr_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('ADV control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                return response()->json(['status' => 7101]);
            } else {
                Log::error('Failed to update ADV control number', ['id' => $id]);
                return response()->json(['status' => 7201, 'error' => 'Failed to update bridge bill']);
            }
        }
        elseif ($bill == "TBS") {
            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("TBS", "", $bill_id);

            $updated = DB::table('bridge_bills')
                ->where('id', $id)
                ->update([
                    'contr_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('TBS control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                $bill = DB::table('bridge_bills')->where('id', $id)->first();
                $accountData = DB::table('account')->where('account_no', $bill->bill_gen_by)->first();

                $sms_recipient = $bill->phone_number;
                $bill_amount = $bill->bill_amount;
                $bundle_type = DB::table('toll_bundles')->where('id', $bill->bundle_id)->first();
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Lipa kifurushi cha ' . $bundle_type->sw_desc . ' cha tozo' . "\n" .
                           'Mlipaji: ' . $accountData->first_name . ' ' . $accountData->surname . '  ' . "\n" .
                           'Kiasi: TZS ' . $amount . " \n" .
                           'Kumb. Na: ' . $contr_num . "\n" .
                           'Lipa malipo ya serikali kupitia mitandao ya simu, benki au wakala' . "\n" .
                           'Tembelea: https://portal.nssf.go.tz/' . "\n" .
                           'Daraja la Nyerere';
                $sms_process = 'Bundle Subscription';

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => strval($sms_recipient),
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                return response()->json(['status' => 7101]);
            } else {
                Log::error('Failed to update TBS control number', ['id' => $id]);
                return response()->json(['status' => 7201, 'error' => 'Failed to update bridge bill']);
            }
        }
        elseif ($bill == "INF") {
            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("INF", "", $bill_id);

            $updated = DB::table('incident_fine')
                ->where('id', $id)
                ->update([
                    'control_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('INF control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                $model = DB::table('incident_fine')->where('id', $id)->first();

                $sms_recipient = $model->phone_number;
                $bill_amount = $model->amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Lipa faini ya Uharibifu wa Miundombinu' . "\n" .
                           'Mlipaji: ' . $model->payer_name . '  ' . "\n" .
                           'Kiasi:' . $amount . " TZS\n" .
                           'Kumb.Na: ' . $contr_num . "\n" .
                           'Lipa kwa Simu Malipo ya Serikali, Benki NMB,NBC,CRDB au Wakala wa Benki Tajwa' . "\n" .
                           'NSSF-Nyerere Bridge';
                $sms_process = 'Incidence Payment Reciept';

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                return response()->json(['status' => 7101]);
            } else {
                Log::error('Failed to update INF control number', ['id' => $id]);
                return response()->json(['status' => 7201]);
            }
        }
        elseif ($bill == "ECP") {
            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("ECP", "", $bill_id);

            $updated = DB::table('event_payment')
                ->where('id', $id)
                ->update([
                    'control_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('ECP control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                $model = DB::table('event_payment')->where('id', $id)->first();

                $sms_recipient = $model->phone_number;
                $bill_amount = $model->amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Lipa Kibali cha Kupiga Picha ' . "\n" .
                           'Mlipaji: ' . $model->payer_name . "\n" .
                           'Kiasi:' . $amount . " TZS\n" .
                           'Kumb.Na: ' . $contr_num . "\n" .
                           'Lipa kwa Simu Malipo ya Serikali, Benki NMB,NBC,CRDB au Wakala wa Benki Tajwa' . "\n" .
                           'NSSF-Nyerere Bridge';
                $sms_process = 'Event Payment Request';

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                return response()->json(['status' => 7101]);
            } else {
                Log::error('Failed to update ECP control number', ['id' => $id]);
                return response()->json(['status' => 7201]);
            }
        }
        elseif ($bill == "FCB") {
            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("FCB", "", $bill_id);

            $updated = DB::table('fine_charge')
                ->where('id', $id)
                ->update([
                    'control_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('FCB control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                $model = DB::table('fine_charge')->where('id', $id)->first();

                $sms_recipient = $model->phone_number;
                $bill_amount = $model->amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Lipa Tozo ya Faini ' . "\n" .
                           'Mlipaji: ' . $model->payer_name . "\n" .
                           'Gari: ' . ($model->plate_number ?? '-') . "\n" .
                           'Kiasi:' . $amount . " TZS\n" .
                           'Kumb.Na: ' . $contr_num . "\n" .
                           'Lipa kwa Simu Malipo ya Serikali, Benki NMB,NBC,CRDB au Wakala wa Benki Tajwa' . "\n" .
                           'NSSF-Nyerere Bridge';
                $sms_process = 'Fine Charge Billing Request';

                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                return response()->json(['status' => 7101]);
            }

            Log::error('Failed to update FCB control number', ['id' => $id]);
            return response()->json(['status' => 7201]);
        }
        else {
            // Default case for TUP (Top Up)
            $contr_num = $data['contr_num'] ?? $data['control_no'] ?? null;
            $t_status = $data['t_status'] ?? 'SUCCESS';
            $t_code = $data['t_code'] ?? '0000';
            $id = str_replace("TUP", "", $bill_id);

            // Get account details
            $topUp = DB::table('top_up')->where('id', $id)->first();
            if (!$topUp) {
                return response()->json(['status' => 7201, 'error' => 'Top up record not found']);
            }

            $account = DB::table('account')->where('account_no', $topUp->account_no)->first();
            if (!$account) {
                return response()->json(['status' => 7201, 'error' => 'Account not found']);
            }

            $phone = $account->phone;
            $name = $account->first_name . ' ' . $account->middle_name . ' ' . $account->surname;

            $updated = DB::table('top_up')
                ->where('id', $id)
                ->update([
                    'contr_num' => $contr_num,
                    'error_code' => $t_code,
                    't_status' => $t_status,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('TUP control number updated successfully', [
                    'id' => $id,
                    'contr_num' => $contr_num,
                    't_status' => $t_status,
                    't_code' => $t_code
                ]);
                $sms_recipient = $phone;
                $sms_payer_cel = $topUp->pyr_cell_num;
                $bill_amount = $topUp->bill_amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Lipa Tozo ya Daraja la Nyerere (NSSF)' . "\n" .
                           'Akaunti: ' . $topUp->account_no . "\n" .
                           'Jina: ' . $name . "\n" .
                           'Kiasi: ' . $amount . " TZS\n" .
                           'Kumbukumbu: ' . $contr_num . "\n" .
                           'Lipa kwa Simu Malipo ya Serikali, Benki NMB,NBC,CRDB au Wakala wa Benki tajwa' . "\n";
                $sms_process = 'Top Up Payment Request';

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_payer_cel == null ? $sms_recipient : $sms_payer_cel,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                return response()->json(['status' => 7101]);
            } else {
                Log::error('Failed to update TUP control number', ['id' => $id]);
                return response()->json(['status' => 7201]);
            }
        }
    }

    private function sendToTload($ticket_num, $contr_num)
    {
//        try {
//            $response = Http::withHeaders([
//                'Content-Type' => 'application/json',
//            ])->post('http://10.10.104.202:4500/api/v1/post-control-number', [
//                'ticket_num' => $ticket_num,
//                'control_num' => $contr_num,
//            ]);
//
//            if ($response->successful()) {
//                return $response->json();
//            }
//
//            return false;
//        } catch (\Exception $e) {
//            Log::error('Tload API call failed', [
//                'ticket_num' => $ticket_num,
//                'control_num' => $contr_num,
//                'error' => $e->getMessage()
//            ]);
//            return false;
//        }
    }

    public function receivePayment(Request $request)
    {
        $payment = $request->all();

        Log::info('Payment received from GePG', [
            'payment_data' => $payment,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Determine receipt method based on PSP name
        $receipt_method = 'Azania Masdo GEPG';
        $receipt_method_id = 5192;

        if (str_contains($payment['psp_name'], 'National Microfinance Bank')) {
            $receipt_method = 'NMB Bank House GEPG';
            $receipt_method_id = 1132;
        } elseif (str_contains($payment['psp_name'], 'CRDB Bank')) {
            $receipt_method = 'CRDB Azikiwe GEPG';
            $receipt_method_id = 1127;
        } elseif (str_contains($payment['psp_name'], 'NBC')) {
            $receipt_method = 'NBC Corporate GEPG';
            $receipt_method_id = 1130;
        } elseif (str_contains($payment['psp_name'], 'Azania')) {
            $receipt_method = 'Azania Nyerere Bridge Direct';
            $receipt_method_id = 5192;
        }

        // Create receipt number
        $receipt = DB::table('receipt')->insertGetId(['prefix' => 'B']);
        $receipt_number = 'B' . $receipt;

        $receipt_date = now()->format('Y-m-d H:i:s');
        $trx_id = $payment['transaction_id'] ?? $payment['trx_id'];
        $payrefid = $payment['pay_ref_id'];
        $pyr_cell_num = $payment['cell_number'] ?? null;

        // Parse and convert GePG datetime format to MySQL datetime
        $gepg_datetime = $payment['datetime'];
        $trx_dt_tm = $this->parseGePGDateTime($gepg_datetime);

        $usd_pay_chn = $payment['payment_channel'];
        $psp_receipt_num = $payment['psp_receipt_no'];
        $psp_name = $payment['psp_name'];
        $ctr_acc_num = $payment['credited_acc_num'];
        $bill_status = '1';
        $bill_id = $payment['bill_id'];
        $bank_amount = $payment['paid_amount'];
        $currency = $payment['currency'];
        $gepg_receipt_no = $payment['pay_ref_id'];
        $gepg_control_no = $payment['control_no'];
        $created_by = 'GePG';
        $payer_name = is_array($payment['payer_name']) ? "null" : $payment['payer_name'];
        $customer_name = 'Sundries';
        $activity = 'Nyerere Bridge Toll Collection';
        $format = 'YYYY-MM-DD"T"HH24:MI:SS';
        $bill = substr($bill_id, 0, 3);

        Log::info('Processing payment', [
            'bill_id' => $bill_id,
            'bill_type' => $bill,
            'receipt_number' => $receipt_number,
            'amount' => $bank_amount
        ]);

        try {
            app(OwenItAuditWriter::class)->record(
                'created',
                Receipt::class,
                (int) $receipt,
                null,
                [
                    'source' => 'GePG',
                    'receipt_number' => $receipt_number,
                    'bill_id' => $bill_id,
                    'bill_type' => $bill,
                    'transaction_id' => $trx_id,
                    'pay_ref_id' => $payrefid,
                    'control_no' => $gepg_control_no,
                    'paid_amount' => $bank_amount,
                    'currency' => $currency,
                    'psp_name' => $psp_name,
                    'payment_channel' => $usd_pay_chn,
                    'psp_receipt_no' => $psp_receipt_num,
                    'credited_acc_num' => $ctr_acc_num,
                    'payer_name' => $payer_name,
                    'cell_number' => $pyr_cell_num,
                    'gepg_datetime' => $gepg_datetime,
                ],
                'billing.receivePayment'
            );
        } catch (\Throwable $e) {
            Log::warning('billing.receivePayment: audit skipped', [
                'message' => $e->getMessage(),
                'receipt_number' => $receipt_number,
            ]);
        }

        if ($bill == "OLF") {
            return $this->processOverloadFinePayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        elseif ($bill == "INF") {
            return $this->processIncidentFinePayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        elseif ($bill == "SHB") {
            return $this->processShortBridgeBillPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        elseif ($bill == "TBS") {
            return $this->processTollBundleSubscriptionPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        elseif ($bill == "ADV") {
            return $this->processAdvertisementPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        elseif ($bill == "ECP") {
            return $this->processEventPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        elseif ($bill == "FCB") {
            return $this->processFineChargePayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
        else {
            // Default case for TUP (Top Up)
            return $this->processTopUpPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id);
        }
    }

    private function processOverloadFinePayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("OLF", "", $bill_id);

        $model = DB::table('overload_fine')->where('id', $id)->first();
        if (!$model) {
            Log::error('Overload fine not found for payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Overload fine not found']);
        }

        // TODO: Implement ERP posting
        // $over_load->postGePGERP($id, $payername, $bill_desc, $format, $created_by, $receipt_method, $receipt_method_id, $receipt_number, $gepg_receipt_no, $bank_amount, $gepg_control_no, $payrefid, $trx_dt_tm, $psp_receipt_num);

        $updated = DB::table('overload_fine')
            ->where('id', $id)
            ->update([
                'trx_id' => $trx_id,
                'bill_status' => $bill_status,
                'trx_dt_tm' => $trx_dt_tm,
                'usd_pay_chn' => $usd_pay_chn,
                'psp_receipt_num' => $psp_receipt_num,
                'psp_name' => $psp_name,
                'payment_date' => now(),
                'ctr_acc_num' => $ctr_acc_num,
                'updated_at' => $receipt_date,
                'receipt_number' => $receipt_number,
                'pay_ref_id' => $payrefid,
                'paid_amt' => $bank_amount,
                'pyr_name' => $payer_name
            ]);

        if ($updated) {
            // Submit overload-fine receipt to ERMS.
            try {
                $overloadFine = OverloadFine::query()->find($id);
                if ($overloadFine !== null) {
                    if (empty($overloadFine->psp_receipt_num)) {
                        Log::info('Skipping ERMS submit; payment not confirmed', [
                            'id' => $id,
                            'receipt_number' => $overloadFine->receipt_number,
                            'pay_ref_id' => $overloadFine->pay_ref_id,
                        ]);
                    } elseif ((int) ($overloadFine->erp_status ?? 0) === 1) {
                        Log::info('Skipping ERMS submit; already posted', [
                            'id' => $id,
                            'receipt_number' => $overloadFine->receipt_number,
                            'pay_ref_id' => $overloadFine->pay_ref_id,
                        ]);
                    } else {
                        $mapper = app(OverloadFineReceiptMapper::class);
                        $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($overloadFine));

                        DB::table('overload_fine')
                            ->where('id', $id)
                            ->update([
                                'erp_status' => $submission['ok'] ? 1 : 2,
                                'http_status' => $submission['http_status'],
                                'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                            ]);
                    }
                } else {
                    Log::warning('Overload fine not found for ERMS submission', ['id' => $id]);
                }
            } catch (\Throwable $ermsError) {
                Log::error('Overload fine ERMS receipt submission failed', [
                    'id' => $id,
                    'error' => $ermsError->getMessage(),
                ]);
                DB::table('overload_fine')
                    ->where('id', $id)
                    ->update([
                        'erp_status' => 2,
                    ]);
            }

            $ticket_num = $model->ticket_num;
            $this->sendPaymentToTload($ticket_num, $receipt_number);

            $sms_recipient = $model->pyr_cell_num;
            $bill_amount = $model->bill_amount;
            $amount = number_format($bill_amount, 0, '.', ',');
            $sms_body = 'Malipo ya Overload Fine yamepokelewa NSSF-Daraja la Nyerere' . "\n" .
                'Ankara Na: ' . $model->contr_num . "\n" .
                'Kiasi:' . $amount . " TZS\n" .
                'Risiti: ' . $receipt_number . "\n" .
                'Tarehe:' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                'Kupitia:' . $psp_receipt_num . '.' . "\n";
            $sms_process = 'Overload Payment Receipt';

            // Log SMS
            DB::table('ids_messages')->insert([
                'sms_body' => $sms_body,
                'sms_recipient' => $sms_recipient,
                'sms_source' => config('app.sms_source'),
                'sms_process' => $sms_process,
                'created_at' => now()
            ]);

            Log::info('Overload fine payment processed successfully', ['id' => $id]);
            return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
        } else {
            Log::error('Failed to update overload fine payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'General Error']);
        }
    }

    private function processIncidentFinePayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("INF", "", $bill_id);

        $model = DB::table('incident_fine')->where('id', $id)->first();
        if (!$model) {
            Log::error('Incident fine not found for payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Incident fine not found']);
        }

        // TODO: Implement ERP posting
        // $incident_fine->postGePGERP($id, $payername, $bill_desc, $format, $activity, $created_by, $customer_name, $receipt_method, $receipt_method_id, $receipt_number, $gepg_receipt_no, $currency, $bank_amount, $gepg_control_no, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_id);

        $updated = DB::table('incident_fine')
            ->where('id', $id)
            ->update([
                'trx_id' => $trx_id,
                'bill_status' => $bill_status,
                'trx_dt_tm' => $trx_dt_tm,
                'usd_pay_chn' => $usd_pay_chn,
                'psp_receipt_num' => $psp_receipt_num,
                'psp_name' => $psp_name,
                'ctr_acc_num' => $ctr_acc_num,
                'updated_at' => $receipt_date,
                'payment_date' => now(),
                'receipt_number' => $receipt_number,
                'pay_ref_id' => $payrefid,
                'paid_amt' => $bank_amount,
                'pyr_name' => $payer_name
            ]);

        if ($updated) {
            // Submit incident-fine receipt to ERMS.
            try {
                $incidentFine = IncidentFine::query()->find($id);
                if ($incidentFine !== null) {
                    if (empty($incidentFine->psp_receipt_num)) {
                        Log::info('Skipping ERMS submit; payment not confirmed', [
                            'id' => $id,
                            'receipt_number' => $incidentFine->receipt_number,
                            'pay_ref_id' => $incidentFine->pay_ref_id,
                        ]);
                    } elseif ((int) ($incidentFine->erp_status ?? 0) === 1) {
                        Log::info('Skipping ERMS submit; already posted', [
                            'id' => $id,
                            'receipt_number' => $incidentFine->receipt_number,
                            'pay_ref_id' => $incidentFine->pay_ref_id,
                        ]);
                    } else {
                        $mapper = app(IncidentFineReceiptMapper::class);
                        $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($incidentFine));

                        DB::table('incident_fine')
                            ->where('id', $id)
                            ->update([
                                'erp_status' => $submission['ok'] ? 1 : 2,
                                'http_status' => $submission['http_status'],
                                'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                            ]);
                    }
                } else {
                    Log::warning('Incident fine not found for ERMS submission', ['id' => $id]);
                }
            } catch (\Throwable $ermsError) {
                Log::error('Incident fine ERMS receipt submission failed', [
                    'id' => $id,
                    'error' => $ermsError->getMessage(),
                ]);
                DB::table('incident_fine')
                    ->where('id', $id)
                    ->update([
                        'erp_status' => 2,
                    ]);
            }

            $sms_recipient = $model->phone_number;
            $bill_amount = $model->amount;
            $amount = number_format($bill_amount, 0, '.', ',');
            $sms_body = 'Malipo ya Incident Fine yamepokelewa NSSF-Daraja la Nyerere' . "\n" .
                'Ankara: ' . $model->control_num . "\n" .
                'Kiasi:' . $amount . " TZS\n" .
                'Risiti: ' . $receipt_number . "\n" .
                'Tarehe:' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                'Kupitia:' . $psp_receipt_num . '.' . "\n";
            $sms_process = 'Incidence Payment Request';

            // Log SMS
            DB::table('ids_messages')->insert([
                'sms_body' => $sms_body,
                'sms_recipient' => $sms_recipient,
                'sms_source' => config('app.sms_source'),
                'sms_process' => $sms_process,
                'created_at' => now()
            ]);

            Log::info('Incident fine payment processed successfully', ['id' => $id]);
            return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
        } else {
            Log::error('Failed to update incident fine payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'General Error']);
        }
    }

    private function processShortBridgeBillPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("SHB", "", $bill_id);

        $model = DB::table('bridge_bills')->where('id', $id)->first();
        if (!$model) {
            Log::error('Bridge bill not found for payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Bridge bill not found']);
        }

        $updated = DB::table('bridge_bills')
            ->where('id', $id)
            ->update([
                'trx_id' => $trx_id,
                'bill_status' => $bill_status,
                'trx_dt_tm' => $trx_dt_tm,
                'usd_pay_chn' => $usd_pay_chn,
                'psp_receipt_num' => $psp_receipt_num,
                'psp_name' => $psp_name,
                'ctr_acc_num' => $ctr_acc_num,
                'updated_at' => $receipt_date,
                'payment_date' => now(),
                'receipt_number' => $receipt_number,
                'pay_ref_id' => $payrefid,
                'paid_amt' => $bank_amount,
                'payer_name' => $payer_name
            ]);

        if ($updated) {
            // Submit short-bridge (bridge_bills) receipt to ERMS.
            try {
                $bridgeBill = BridgeBill::query()->find($id);
                if ($bridgeBill !== null) {
                    if (empty($bridgeBill->psp_receipt_num)) {
                        Log::info('Skipping ERMS submit; payment not confirmed', [
                            'id' => $id,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id,
                        ]);
                    } elseif ((int) ($bridgeBill->erp_status ?? 0) === 1) {
                        Log::info('Skipping ERMS submit; already posted', [
                            'id' => $id,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id,
                        ]);
                    } else {
                        $mapper = app(BridgeBillReceiptMapper::class);
                        $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($bridgeBill));

                        DB::table('bridge_bills')
                            ->where('id', $id)
                            ->update([
                                'erp_status' => $submission['ok'] ? 1 : 2,
                                'http_status' => $submission['http_status'],
                                'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                            ]);
                    }
                } else {
                    Log::warning('Bridge bill not found for ERMS submission', ['id' => $id]);
                }
            } catch (\Throwable $ermsError) {
                Log::error('Bridge bill ERMS receipt submission failed', [
                    'id' => $id,
                    'error' => $ermsError->getMessage(),
                ]);
                DB::table('bridge_bills')
                    ->where('id', $id)
                    ->update([
                        'erp_status' => 2,
                    ]);
            }

            // TODO: Implement ERP posting
            // $response = $client->createRequest()->setFormat(Client::FORMAT_JSON)->setMethod('POST')->setUrl(Yii::$app->params['apiBaseUrl'] . '/receipt/post-shift-erp-receipt')->setData([...])->send();

            Log::info('Short bridge bill payment processed successfully', ['id' => $id]);
            return response()->json(['status' => 7101, 'message' => 'Successful and uploaded in ERP', 'payment' => $model]);
        } else {
            Log::error('Failed to update short bridge bill payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'General Error']);
        }
    }

    private function processTollBundleSubscriptionPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("TBS", "", $bill_id);

        $model = DB::table('bridge_bills')->where('id', $id)->first();
        if (!$model) {
            Log::error('Bridge bill not found for TBS payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Bridge bill not found']);
        }

        // Check if already paid
        if ($model->usd_pay_chn != null) {
            return response()->json(['status' => 7101, 'message' => 'Successful']);
        }

        $updated = DB::table('bridge_bills')
            ->where('id', $id)
            ->update([
                'trx_id' => $trx_id,
                'bill_status' => $bill_status,
                'trx_dt_tm' => $trx_dt_tm,
                'usd_pay_chn' => $usd_pay_chn,
                'psp_receipt_num' => $psp_receipt_num,
                'psp_name' => $psp_name,
                'ctr_acc_num' => $ctr_acc_num,
                'updated_at' => $receipt_date,
                'payment_date' => now(),
                'receipt_number' => $receipt_number,
                'pay_ref_id' => $payrefid,
                'paid_amt' => $bank_amount,
                'payer_name' => $payer_name
            ]);

            Log::info('TBS payment updated after GePG payment', ['id' => $id, 'updated' => $updated]);

        if ($updated) {
            // Submit toll-bundle (bridge_bills) receipt to ERMS.
            Log::info('Submitting toll-bundle (bridge_bills) receipt to ERMS if updated is true');
            try {
                $bridgeBill = BridgeBill::query()->find($id);
                Log::info('Bridge bill found for TBS payment', ['id' => $id]);
                if ($bridgeBill !== null) {
                    if (empty($bridgeBill->psp_receipt_num)) {
                        Log::info('Skipping ERMS submit; payment not confirmed (psp_receipt_num empty)', [
                            'id' => $id,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id,
                        ]);
                    } elseif ((int) ($bridgeBill->erp_status ?? 0) === 1) {
                        Log::info('Skipping ERMS submit; already posted', [
                            'id' => $id,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id,
                        ]);
                    } else {
                        Log::info('Submitting ERMS receipt for TBS payment', ['id' => $id]);
                        $mapper = app(BridgeBillReceiptMapper::class);
                        $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($bridgeBill));
                        Log::info('ERMS receipt submitted for TBS payment', ['id' => $id, 'submission' => $submission]);
                        DB::table('bridge_bills')
                            ->where('id', $id)
                            ->update([
                                'erp_status' => $submission['ok'] ? 1 : 2,
                                'http_status' => $submission['http_status'],
                                'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                            ]);
                        Log::info('Bridge bill updated for TBS payment', ['id' => $id]);
                    }
                } else {
                    Log::warning('Bridge bill not found for ERMS submission', ['id' => $id]);
                }
            } catch (\Throwable $ermsError) {
                Log::error('Bridge bill ERMS receipt submission failed', [
                    'id' => $id,
                    'error' => $ermsError->getMessage(),
                ]);
                DB::table('bridge_bills')
                    ->where('id', $id)
                    ->update([
                        'erp_status' => 2,
                    ]);
            }

            // Create bundle subscription
            $vehicleData = DB::table('vehicle')->where('plate_no', $model->dist_param)->first();
            $tollBundle = DB::table('toll_bundles')->where('id', $model->bundle_id)->first();

            if ($vehicleData && $tollBundle) {
                // Check for existing bundle - get the most recent active bundle
                $checkBundle = DB::table('bundle_subscriptions')
                    ->where('vehicle_id', $vehicleData->id)
                    ->where('status', 1)
                    ->orderBy('expire_date', 'desc')
                    ->first();

                if ($checkBundle != null) {
                    // Start the new bundle from the expiration date of the existing bundle
                    $start_date = Carbon::parse($checkBundle->expire_date);
                } else {
                    // No existing bundle, start from now
                    $start_date = now();
                }

                // Calculate expire_date from start_date (not from now())
                // This ensures the new bundle duration is added to the start_date
                $expire_date = Carbon::parse($start_date)->addDays($tollBundle->duration);

                // Create bundle subscription
                DB::table('bundle_subscriptions')->insert([
                    'created_by' => $model->bill_gen_by,
                    'account_id' => $model->bill_gen_by,
                    'vehicle_id' => $vehicleData->id,
                    'bill_id' => $id,
                    'bundle_id' => $model->bundle_id,
                    'status' => 1,
                    'start_date' => $start_date,
                    'expire_date' => $expire_date
                ]);

                // Create transaction record
                $dc = $this->getDailyCounter();
                DB::table('transactions')->insert([
                    'receipt_num' => $receipt_number,
                    'dc' => $dc,
                    'payment_type' => 'EMONEY',
                    'trans_desc' => 'TOLL BUNDLE FEE',
                    'trans_source' => 'TBF',
                    'account_no' => $model->bill_gen_by,
                    'charged_amount' => $model->bill_amount,
                    'created_at' => now(),
                    'receipt_type' => 8,
                    'collection_office' => 8,
                    'payment_method' => 8
                ]);

                $trans_log = DB::table('transactions')->where('receipt_num', $receipt_number)->first();
                $rctvnum = 'B' . $trans_log->gc . '_' . date('His', strtotime($trans_log->created_at));

                $sms_recipient = $pyr_cell_num ?? $model->phone_number;
                $bill_amount = $model->bill_amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Malipo ya kifurushi cha ' . $tollBundle->sw_desc . ' cha tozo yamepokelewa' . "\n" .
                    'Ankara: ' . $model->contr_num . "\n" .
                    'Kiasi: TZS ' . $amount . "\n" .
                    'Risiti: ' . $receipt_number . "\n" .
                    'Tarehe: ' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                    'Kupitia: ' . $psp_receipt_num . '.' . "\n" .
                    'Kuanzia: ' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                    'Kuishia: ' . date('d-M-Y H:i:s', strtotime($expire_date)) . "\n" .
                    'Namba ya usajili: ' . $vehicleData->plate_no . "\n" .
                    'Hakiki: ' . 'https://verify.tra.go.tz/' . "\n" .
                    'Tembelea: https://portal.nssf.go.tz/' . "\n" .
                    'Daraja la Nyerere';
                $sms_process = 'Bundle payment receipt';

                // TODO: Implement ERP posting
//                 $model->postGePGERP($id, $bill_desc, $payername, $payer_name, $format, $activity, $created_by, $customer_name, $receipt_method, $receipt_method_id, $receipt_number, $gepg_receipt_no, $currency, $bank_amount, $gepg_control_no, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_id);

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);
            }

            Log::info('Toll bundle subscription payment processed successfully', ['id' => $id]);
            return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
        } else {
            Log::error('Failed to update toll bundle subscription payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'General Error']);
        }
    }

    private function processAdvertisementPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("ADV", "", $bill_id);

        $model = DB::table('bridge_bills')->where('id', $id)->first();
        if (!$model) {
            Log::error('Bridge bill not found for ADV payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Bridge bill not found']);
        }

        // Check if already paid
        if ($model->usd_pay_chn != null) {
            return response()->json(['status' => 7101, 'message' => 'Successful']);
        }

        $updated = DB::table('bridge_bills')
            ->where('id', $id)
            ->update([
                'trx_id' => $trx_id,
                'bill_status' => $bill_status,
                'trx_dt_tm' => $trx_dt_tm,
                'usd_pay_chn' => $usd_pay_chn,
                'psp_receipt_num' => $psp_receipt_num,
                'psp_name' => $psp_name,
                'ctr_acc_num' => $ctr_acc_num,
                'updated_at' => $receipt_date,
                'payment_date' => now(),
                'receipt_number' => $receipt_number,
                'pay_ref_id' => $payrefid,
                'paid_amt' => $bank_amount,
                'payer_name' => $payer_name
            ]);

        if ($updated) {
            // Submit advertisement (bridge_bills) receipt to ERMS.
            try {
                $bridgeBill = BridgeBill::query()->find($id);
                if ($bridgeBill !== null) {
                    if (empty($bridgeBill->psp_receipt_num)) {
                        Log::info('Skipping ERMS submit; payment not confirmed', [
                            'id' => $id,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id,
                        ]);
                    } elseif ((int) ($bridgeBill->erp_status ?? 0) === 1) {
                        Log::info('Skipping ERMS submit; already posted', [
                            'id' => $id,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id,
                        ]);
                    } else {
                        $mapper = app(BridgeBillReceiptMapper::class);
                        $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($bridgeBill));

                        DB::table('bridge_bills')
                            ->where('id', $id)
                            ->update([
                                'erp_status' => $submission['ok'] ? 1 : 2,
                                'http_status' => $submission['http_status'],
                                'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                            ]);
                    }
                } else {
                    Log::warning('Bridge bill not found for ERMS submission', ['id' => $id]);
                }
            } catch (\Throwable $ermsError) {
                Log::error('Bridge bill ERMS receipt submission failed', [
                    'id' => $id,
                    'error' => $ermsError->getMessage(),
                ]);
                DB::table('bridge_bills')
                    ->where('id', $id)
                    ->update([
                        'erp_status' => 2,
                    ]);
            }

            // Create transaction record
            $dc = $this->getDailyCounter();
            DB::table('transactions')->insert([
                'receipt_num' => $receipt_number,
                'dc' => $dc,
                'payment_type' => 'EMONEY',
                'trans_desc' => 'ADVERTISEMENT FEE',
                'trans_source' => 'TBF',
                'account_no' => $model->bill_gen_by,
                'charged_amount' => $model->bill_amount,
                'created_at' => now(),
                'receipt_type' => 8,
                'collection_office' => 8,
                'payment_method' => 8
            ]);

            $trans_log = DB::table('transactions')->where('receipt_num', $receipt_number)->first();
            $rctvnum = 'B' . $trans_log->gc . '_' . date('His', strtotime($trans_log->created_at));

            $sms_recipient = $pyr_cell_num ?? $model->phone_number;
            $bill_amount = $model->bill_amount;
            $amount = number_format($bill_amount, 0, '.', ',');
            $sms_body = 'Malipo kwajili ya Matangazo yamepokelewa daraja la nyerere (NSSF)' . "\n" .
                'Ankara: ' . $model->contr_num . "\n" .
                'Kiasi: ' . $amount . " TZS\n" .
                'Risiti: ' . $receipt_number . "\n" .
                'Tarehe: ' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                'Kupitia: ' . $psp_receipt_num . '.' . "\n" .
                'Hakiki: ' . 'https://verify.tra.go.tz/' . $rctvnum . "\n";
            $sms_process = 'Advertisement payment receipt';

            // TODO: Implement ERP posting
            // $model->postGePGERP($id, $bill_desc, $payername, $payer_name, $format, $activity, $created_by, $customer_name, $receipt_method, $receipt_method_id, $receipt_number, $gepg_receipt_no, $currency, $bank_amount, $gepg_control_no, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_id);

            // Log SMS
            DB::table('ids_messages')->insert([
                'sms_body' => $sms_body,
                'sms_recipient' => $sms_recipient,
                'sms_source' => config('app.sms_source'),
                'sms_process' => $sms_process,
                'created_at' => now()
            ]);

            Log::info('Advertisement payment processed successfully', ['id' => $id]);
            return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
        } else {
            Log::error('Failed to update advertisement payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'General Error']);
        }
    }

    private function processEventPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("ECP", "", $bill_id);

        $model = DB::table('event_payment')->where('id', $id)->first();
        if (!$model) {
            Log::error('Event payment not found', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Event payment not found']);
        }

        if ($model->psp_receipt_num == null) {
            // TODO: Implement ERP posting
            // $event_payment->postGePGERP($id, $bill_desc, $payername, $format, $activity, $created_by, $customer_name, $receipt_method, $receipt_method_id, $receipt_number, $gepg_receipt_no, $currency, $bank_amount, $gepg_control_no, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_id);

            $updated = DB::table('event_payment')
                ->where('id', $id)
                ->update([
                    'trx_id' => $trx_id,
                    'bill_status' => $bill_status,
                    'trx_dt_tm' => $trx_dt_tm,
                    'usd_pay_chn' => $usd_pay_chn,
                    'psp_receipt_num' => $psp_receipt_num,
                    'psp_name' => $psp_name,
                    'payment_date' => now(),
                    'ctr_acc_num' => $ctr_acc_num,
                    'updated_at' => $receipt_date,
                    'receipt_number' => $receipt_number,
                    'pyr_cell_num' => $pyr_cell_num,
                    'pay_ref_id' => $payrefid,
                    'paid_amt' => $bank_amount,
                    'pyr_name' => $payer_name
                ]);

            if ($updated) {
                // Submit event-payment receipt to ERMS.
                try {
                    $eventPayment = EventPayment::query()->find($id);
                    if ($eventPayment !== null) {
                        if (empty($eventPayment->psp_receipt_num)) {
                            Log::info('Skipping ERMS submit; payment not confirmed (psp_receipt_num empty)', [
                                'id' => $id,
                                'receipt_number' => $eventPayment->receipt_number,
                                'pay_ref_id' => $eventPayment->pay_ref_id,
                            ]);
                        } elseif ((int) ($eventPayment->erp_status ?? 0) === 1) {
                            Log::info('Skipping ERMS submit; already posted', [
                                'id' => $id,
                                'receipt_number' => $eventPayment->receipt_number,
                                'pay_ref_id' => $eventPayment->pay_ref_id,
                            ]);
                        } else {
                            $mapper = app(EventPaymentReceiptMapper::class);
                            $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($eventPayment));

                            DB::table('event_payment')
                                ->where('id', $id)
                                ->update([
                                    'erp_status' => $submission['ok'] ? 1 : 2,
                                    'http_status' => $submission['http_status'],
                                    'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                                ]);
                        }
                    } else {
                        Log::warning('Event payment not found for ERMS submission', ['id' => $id]);
                    }
                } catch (\Throwable $ermsError) {
                    Log::error('Event payment ERMS receipt submission failed', [
                        'id' => $id,
                        'error' => $ermsError->getMessage(),
                    ]);
                    DB::table('event_payment')
                        ->where('id', $id)
                        ->update([
                            'erp_status' => 2,
                        ]);
                }

                // Create transaction record
                $dc = $this->getDailyCounter();
                DB::table('transactions')->insert([
                    'receipt_num' => $receipt_number,
                    'dc' => $dc,
                    'payment_type' => 'EMONEY',
                    'trans_desc' => 'EVENT FINE',
                    'trans_source' => 'EF',
                    'receipt_type' => 1,
                    'payment_method' => 3,
                    'collection_office' => 1,
                    'charged_amount' => $model->amount,
                    'created_at' => now()
                ]);

                $trans_log = DB::table('transactions')->where('receipt_num', $receipt_number)->first();
                $rctvnum = 'B' . $trans_log->gc . '_' . date('His', strtotime($trans_log->created_at));

                $sms_recipient = $model->pyr_cell_num;
                $bill_amount = $model->amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Malipo ya Event yamepokelewa NSSF-Daraja la Nyerere.' . "\n" .
                    'Ankara: ' . $model->control_num . "\n" .
                    'Kiasi:' . $amount . " TZS\n" .
                    'Risiti: ' . $receipt_number . "\n" .
                    'Tarehe: ' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                    'Kupitia:' . $psp_receipt_num . '.' . "\n" .
                    'Hakiki: ' . 'https://verify.tra.go.tz/' . $rctvnum;
                $sms_process = 'Event Payment Receipt';

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                Log::info('Event payment processed successfully', ['id' => $id]);
                return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
            } else {
                Log::error('Failed to update event payment', ['id' => $id]);
                return response()->json(['status' => 7201, 'message' => 'General Error']);
            }
        }

        return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
    }

    private function processFineChargePayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("FCB", "", $bill_id);

        $model = DB::table('fine_charge')->where('id', $id)->first();
        if (!$model) {
            Log::error('Fine charge not found for payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Fine charge not found']);
        }

        if ($model->psp_receipt_num == null) {
            $updated = DB::table('fine_charge')
                ->where('id', $id)
                ->update([
                    'trx_id' => $trx_id,
                    'bill_status' => $bill_status,
                    'trx_dt_tm' => $trx_dt_tm,
                    'usd_pay_chn' => $usd_pay_chn,
                    'psp_receipt_num' => $psp_receipt_num,
                    'psp_name' => $psp_name,
                    'payment_date' => now(),
                    'ctr_acc_num' => $ctr_acc_num,
                    'updated_at' => $receipt_date,
                    'receipt_number' => $receipt_number,
                    'pyr_cell_num' => $pyr_cell_num,
                    'pay_ref_id' => $payrefid,
                    'paid_amt' => $bank_amount,
                    'pyr_name' => $payer_name
                ]);

            if ($updated) {
                $dc = $this->getDailyCounter();
                DB::table('transactions')->insert([
                    'receipt_num' => $receipt_number,
                    'dc' => $dc,
                    'payment_type' => 'EMONEY',
                    'trans_desc' => 'FINE CHARGE',
                    'trans_source' => 'FC',
                    'receipt_type' => 1,
                    'payment_method' => 3,
                    'collection_office' => 1,
                    'charged_amount' => $model->amount,
                    'created_at' => now()
                ]);

                $trans_log = DB::table('transactions')->where('receipt_num', $receipt_number)->first();
                $rctvnum = 'B' . $trans_log->gc . '_' . date('His', strtotime($trans_log->created_at));

                $sms_recipient = $model->pyr_cell_num ?: $model->phone_number;
                $bill_amount = $model->amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Malipo ya Tozo ya Faini yamepokelewa NSSF-Daraja la Nyerere.' . "\n" .
                    'Ankara: ' . $model->control_num . "\n" .
                    'Kiasi:' . $amount . " TZS\n" .
                    'Risiti: ' . $receipt_number . "\n" .
                    'Tarehe: ' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                    'Kupitia:' . $psp_receipt_num . '.' . "\n" .
                    'Hakiki: ' . 'https://verify.tra.go.tz/' . $rctvnum;
                $sms_process = 'Fine Charge Receipt';

                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                Log::info('Fine charge payment processed successfully', ['id' => $id]);
                return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
            }

            Log::error('Failed to update fine charge payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'General Error']);
        }

        return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
    }

    private function processTopUpPayment($payment, $bill_id, $receipt_number, $receipt_date, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_status, $bank_amount, $currency, $gepg_receipt_no, $gepg_control_no, $created_by, $payer_name, $customer_name, $activity, $format, $receipt_method, $receipt_method_id)
    {
        $id = str_replace("TUP", "", $bill_id);

        $model = DB::table('top_up')->where('id', $id)->first();
        if (!$model) {
            Log::error('Top up not found for payment', ['id' => $id]);
            return response()->json(['status' => 7201, 'message' => 'Top up not found']);
        }

        if ($model->psp_receipt_num == null) {
            $account_details = DB::table('account')->where('account_no', $model->account_no)->first();
            $bill_desc = $model->bill_desc;
            $payername = $account_details->first_name . ' ' . $account_details->middle_name . ' ' . $account_details->surname;

            $updated = DB::table('top_up')
                ->where('id', $id)
                ->update([
                    'trx_id' => $trx_id,
                    'trx_dt_tm' => $trx_dt_tm,
                    'bill_status' => $bill_status,
                    'usd_pay_chn' => $usd_pay_chn,
                    'psp_receipt_num' => $psp_receipt_num,
                    'psp_name' => $psp_name,
                    'payment_date' => now(),
                    'ctr_acc_num' => $ctr_acc_num,
                    'receipt_number' => $receipt_number,
                    'pay_ref_id' => $payrefid,
                    'paid_amt' => $bank_amount,
                    'payer_name' => $payer_name
                ]);

            if ($updated) {
                // Create transaction record
                $dc = $this->getDailyCounter();
                DB::table('transactions')->insert([
                    'dc' => $dc,
                    'receipt_num' => $receipt_number,
                    'payment_type' => 'EMONEY',
                    'trans_desc' => 'TOLL FEE',
                    'trans_source' => 'TF',
                    'account_no' => $model->account_no,
                    'tin' => $model->tin,
                    'charged_amount' => $model->bill_amount,
                    'created_at' => now(),
                    'receipt_type' => 4,
                    'collection_office' => 2,
                    'payment_method' => 3
                ]);

                $trans_log = DB::table('transactions')->where('receipt_num', $receipt_number)->first();
                $rctvnum = 'B' . $trans_log->gc . '_' . date('His', strtotime($trans_log->created_at));

                $account_no = $model->account_no;
                $sms_recipient = $pyr_cell_num;
                $bill_amount = $model->bill_amount;
                $amount = number_format($bill_amount, 0, '.', ',');
                $sms_body = 'Malipo ya Toll-Fee yamepokelewa Daraja la Nyerere(NSSF)' . "\n" .
                    'Ankara: ' . $model->contr_num . "\n" .
                    'Kiasi: ' . $amount . " TZS\n" .
                    'Risiti: ' . $receipt_number . "\n" .
                    'Tarehe: ' . date('d-M-Y H:i:s', strtotime($receipt_date)) . "\n" .
                    'Kupitia: ' . $psp_receipt_num . '.' . "\n" .
                    'Hakiki: ' . 'https://verify.tra.go.tz/' . $rctvnum;
                $sms_process = 'Top Up Payment Request';

                // Update account balance
                DB::table('account')
                    ->where('account_no', $account_no)
                    ->increment('account_balance', $bill_amount);

                // Submit top-up receipt to ERMS.
                try {
                    $topUpRow = DB::table('top_up')->where('id', $id)->first();
                    if ($topUpRow !== null) {
                        if ((int) ($topUpRow->erp_status ?? 0) === 1) {
                            Log::info('Skipping ERMS submit; already posted', [
                                'id' => $id,
                                'receipt_number' => $topUpRow->receipt_number,
                                'pay_ref_id' => $topUpRow->pay_ref_id,
                            ]);
                        } else {
                            $mapper = app(PrepaymentMapper::class);
                            $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($topUpRow));

                            DB::table('top_up')
                                ->where('id', $id)
                                ->update([
                                    'erp_status' => $submission['ok'] ? 1 : 2,
                                    'http_status' => $submission['http_status'],
                                    'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
                                ]);
                        }
                    } else {
                        Log::warning('Top up not found for ERMS submission', ['id' => $id]);
                    }
                } catch (\Throwable $ermsError) {
                    Log::error('Top up ERMS receipt submission failed', [
                        'id' => $id,
                        'error' => $ermsError->getMessage(),
                    ]);
                    DB::table('top_up')
                        ->where('id', $id)
                        ->update([
                            'erp_status' => 2,
                        ]);
                }

                // TODO: Implement ERP posting
                // $top_up->postGePGERP($id, $bill_desc, $payername, $payer_name, $format, $activity, $created_by, $customer_name, $receipt_method, $receipt_method_id, $receipt_number, $gepg_receipt_no, $currency, $bank_amount, $gepg_control_no, $trx_id, $payrefid, $pyr_cell_num, $trx_dt_tm, $usd_pay_chn, $psp_receipt_num, $psp_name, $ctr_acc_num, $bill_id);

                // Log SMS
                DB::table('ids_messages')->insert([
                    'sms_body' => $sms_body,
                    'sms_recipient' => $sms_recipient,
                    'sms_source' => config('app.sms_source'),
                    'sms_process' => $sms_process,
                    'created_at' => now()
                ]);

                Log::info('Top up payment processed successfully', ['id' => $id]);
                return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
            } else {
                Log::error('Failed to update top up payment', ['id' => $id]);
                return response()->json(['status' => 7201, 'message' => 'General Error']);
            }
        }

        return response()->json(['status' => 7101, 'message' => 'Successful', 'payment' => $model]);
    }

    private function getDailyCounter()
    {
        // TODO: Implement daily counter logic
        return 1;
    }

    private function parseGePGDateTime($gepg_datetime)
    {
        try {
            // Handle GePG datetime format: "2025-07-13UTC04:32:16"
            if (strpos($gepg_datetime, 'UTC') !== false) {
                // Remove UTC and parse the datetime
                $datetime_str = str_replace('UTC', ' ', $gepg_datetime);
                $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime_str);

                if ($datetime) {
                    // Convert to UTC timezone and format for MySQL
                    $datetime->setTimezone(new \DateTimeZone('UTC'));
                    return $datetime->format('Y-m-d H:i:s');
                }
            }

            // Fallback: try to parse as ISO 8601 format
            $datetime = \DateTime::createFromFormat(\DateTime::ISO8601, $gepg_datetime);
            if ($datetime) {
                return $datetime->format('Y-m-d H:i:s');
            }

            // If all parsing fails, return current datetime
            Log::warning('Failed to parse GePG datetime', ['datetime' => $gepg_datetime]);
            return now()->format('Y-m-d H:i:s');

        } catch (\Exception $e) {
            Log::error('Error parsing GePG datetime', [
                'datetime' => $gepg_datetime,
                'error' => $e->getMessage()
            ]);
            return now()->format('Y-m-d H:i:s');
        }
    }

    private function sendPaymentToTload($ticket_num, $receipt_number)
    {
//        try {
//            $response = Http::withHeaders([
//                'Content-Type' => 'application/json',
//            ])->post('http://10.10.104.202:4500/api/v1/post-payment', [
//                'ticket_num' => $ticket_num,
//                'receipt_number' => $receipt_number,
//            ]);
//
//            if ($response->successful()) {
//                return $response->json();
//            }
//
//            return false;
//        } catch (\Exception $e) {
//            Log::error('Tload payment API call failed', [
//                'ticket_num' => $ticket_num,
//                'receipt_number' => $receipt_number,
//                'error' => $e->getMessage()
//            ]);
//            return false;
//        }
    }

    public function billCancellation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|string|max:255',
            'bill_canc_date' => 'required|string|max:255',
            'bill_canc_by' => 'required|string|max:255',
            'cancel_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $bill_details = $request->all();

        Log::info('Bill cancellation request received', [
            'bill_details' => $bill_details,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Call GePG API to cancel the bill
        $billId = $bill_details['bill_id'];

        if (GePG::shouldStubLocally()) {
            Log::warning('GePG URL is not configured; cancelling bill locally', ['bill_id' => $billId]);
            $cancelBill = ['message' => 'Successful'];
        } else {
            $url_path = EnvironmentHelper::GePGBaseUrl() . '/bills/' . $billId;

            Log::info('Calling GePG API for bill cancellation', [
                'url' => $url_path,
                'bill_id' => $billId
            ]);

            try {
                $response = Http::delete($url_path);

                Log::info('GePG cancellation response', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json()
                ]);

                if ($response->successful()) {
                    $cancelBill = $response->json();
                } else {
                    Log::error('GePG cancellation failed', [
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return response()->json(['status' => 2, 'message' => 'GePG API call failed. Status: ' . $response->status()]);
                }
            } catch (\Exception $e) {
                Log::error('GePG cancellation exception', [
                    'error' => $e->getMessage(),
                    'bill_id' => $billId,
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['status' => 2, 'message' => 'Payment gateway request failed. Please try again or contact support.']);
            }
        }

        if (isset($cancelBill['message']) && ($cancelBill['message'] == 7204 || $cancelBill['message'] == 'Successful')) {
            $status = 1;
            $bill_id = $bill_details['bill_id'];
            $bill_canc_date = $bill_details['bill_canc_date'];
            $bill_canc_by = $bill_details['bill_canc_by'];
            $canc_reas = $bill_details['cancel_reason'];
            $init = "CANC";
            $psp_receipt_num = $init . date('Y-m-d H:i:s');
            $bill = substr($bill_id, 0, 3);

            Log::info('Processing bill cancellation', [
                'bill_id' => $bill_id,
                'bill_type' => $bill,
                'cancel_reason' => $canc_reas
            ]);

            if ($bill == "OLF") {
                return $this->cancelOverloadFine($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            elseif ($bill == "ECP") {
                return $this->cancelEventPayment($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            elseif ($bill == "FCB") {
                return $this->cancelFineCharge($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            elseif ($bill == "ADV") {
                return $this->cancelAdvertisement($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            elseif ($bill == "SHB") {
                return $this->cancelShortBridgeBill($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            elseif ($bill == "TBS") {
                return $this->cancelTollBundleSubscription($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            elseif ($bill == "INF") {
                return $this->cancelIncidentFine($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
            else {
                // Default case for TUP (Top Up)
                return $this->cancelTopUp($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num);
            }
        } else {
            Log::error('GePG cancellation failed', [
                'bill_id' => $bill_details['bill_id'],
                'message' => $cancelBill['message'] ?? 'Unknown',
                'response_data' => $cancelBill
            ]);
            return response()->json(['status' => 2, 'message' => 'Unsuccessfully, Please Contact the Admin. Code: ' . ($cancelBill['message'] ?? 'Unknown')]);
        }
    }

    private function cancelOverloadFine($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("OLF", "", $bill_id);

        $updated = DB::table('overload_fine')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->update([
                'is_cancelled' => $status,
                'cancel_reason' => $canc_reas,
                'bill_cancel_date' => $bill_canc_date,
                'bill_cancel_by' => $bill_canc_by,
                'psp_receipt_num' => $psp_receipt_num,
                'updated_at' => now()
            ]);

        if ($updated) {
            Log::info('Overload fine cancelled successfully', ['id' => $id]);
            return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
        } else {
            Log::error('Failed to cancel overload fine', ['id' => $id]);
            return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
        }
    }

    private function cancelFineCharge($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("FCB", "", $bill_id);

        $checkTrx = DB::table('fine_charge')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->count();

        if ($checkTrx > 0) {
            $updated = DB::table('fine_charge')
                ->where('id', $id)
                ->whereNull('trx_id')
                ->update([
                    'is_cancelled' => $status,
                    'cancel_reason' => $canc_reas,
                    'bill_cancel_date' => $bill_canc_date,
                    'bill_cancel_by' => $bill_canc_by,
                    'psp_receipt_num' => $psp_receipt_num,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('Fine charge cancelled successfully', ['id' => $id]);
                return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
            }

            Log::error('Failed to cancel fine charge', ['id' => $id]);
            return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
        }

        return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
    }

    private function cancelEventPayment($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("ECP", "", $bill_id);

        // Check if transaction exists
        $checkTrx = DB::table('event_payment')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->count();

        if ($checkTrx > 0) {
            $updated = DB::table('event_payment')
                ->where('id', $id)
                ->whereNull('trx_id')
                ->update([
                    'is_cancelled' => $status,
                    'cancel_reason' => $canc_reas,
                    'bill_cancel_date' => $bill_canc_date,
                    'bill_cancel_by' => $bill_canc_by,
                    'psp_receipt_num' => $psp_receipt_num,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('Event payment cancelled successfully', ['id' => $id]);
                return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
            } else {
                Log::error('Failed to cancel event payment', ['id' => $id]);
                return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
            }
        }

        Log::error('Event payment not found or already has transaction', ['id' => $id]);
        return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
    }

    private function cancelAdvertisement($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("ADV", "", $bill_id);

        // Check if transaction exists
        $checkTrx = DB::table('bridge_bills')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->count();

        if ($checkTrx > 0) {
            $updated = DB::table('bridge_bills')
                ->where('id', $id)
                ->whereNull('trx_id')
                ->update([
                    'is_cancelled' => $status,
                    'bill_status' => 2,
                    'cancel_reason' => $canc_reas,
                    'bill_cancel_date' => $bill_canc_date,
                    'bill_cancel_by' => $bill_canc_by,
                    'psp_receipt_num' => $psp_receipt_num,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('Advertisement bill cancelled successfully', ['id' => $id]);
                return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
            } else {
                Log::error('Failed to cancel advertisement bill', ['id' => $id]);
                return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
            }
        }

        Log::error('Advertisement bill not found or already has transaction', ['id' => $id]);
        return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
    }

    private function cancelShortBridgeBill($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("SHB", "", $bill_id);

        // Check if transaction exists
        $checkTrx = DB::table('bridge_bills')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->count();

        if ($checkTrx > 0) {
            $updated = DB::table('bridge_bills')
                ->where('id', $id)
                ->whereNull('trx_id')
                ->update([
                    'is_cancelled' => $status,
                    'bill_status' => 2,
                    'cancel_reason' => $canc_reas,
                    'bill_cancel_date' => $bill_canc_date,
                    'bill_cancel_by' => $bill_canc_by,
                    'psp_receipt_num' => $psp_receipt_num,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('Short bridge bill cancelled successfully', ['id' => $id]);
                return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
            } else {
                Log::error('Failed to cancel short bridge bill', ['id' => $id]);
                return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
            }
        }

        Log::error('Short bridge bill not found or already has transaction', ['id' => $id]);
        return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
    }

    private function cancelTollBundleSubscription($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("TBS", "", $bill_id);

        // Check if transaction exists
        $checkTrx = DB::table('bridge_bills')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->count();

        if ($checkTrx > 0) {
            $updated = DB::table('bridge_bills')
                ->where('id', $id)
                ->whereNull('trx_id')
                ->update([
                    'is_cancelled' => $status,
                    'bill_status' => 2,
                    'cancel_reason' => $canc_reas,
                    'bill_cancel_date' => $bill_canc_date,
                    'bill_cancel_by' => $bill_canc_by,
                    'psp_receipt_num' => $psp_receipt_num,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('Toll bundle subscription cancelled successfully', ['id' => $id]);
                return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
            } else {
                Log::error('Failed to cancel toll bundle subscription', ['id' => $id]);
                return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
            }
        }

        Log::error('Toll bundle subscription not found or already has transaction', ['id' => $id]);
        return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
    }

    private function cancelIncidentFine($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("INF", "", $bill_id);

        // Check if transaction exists
        $checkTrx = DB::table('incident_fine')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->count();

        if ($checkTrx > 0) {
            $updated = DB::table('incident_fine')
                ->where('id', $id)
                ->whereNull('trx_id')
                ->update([
                    'is_cancelled' => $status,
                    'cancel_reason' => $canc_reas,
                    'bill_cancel_date' => $bill_canc_date,
                    'bill_cancel_by' => $bill_canc_by,
                    'psp_receipt_num' => $psp_receipt_num,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info('Incident fine cancelled successfully', ['id' => $id]);
                return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
            } else {
                Log::error('Failed to cancel incident fine', ['id' => $id]);
                return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
            }
        }

        Log::error('Incident fine not found or already has transaction', ['id' => $id]);
        return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
    }

    private function cancelTopUp($bill_id, $status, $canc_reas, $bill_canc_date, $bill_canc_by, $psp_receipt_num)
    {
        $id = str_replace("TUP", "", $bill_id);

        $updated = DB::table('top_up')
            ->where('id', $id)
            ->whereNull('trx_id')
            ->update([
                'is_cancelled' => $status,
                'cancel_reason' => $canc_reas,
                'bill_cancel_date' => $bill_canc_date,
                'bill_cancel_by' => $bill_canc_by,
                'psp_receipt_num' => $psp_receipt_num,
                'updated_at' => now()
            ]);

        if ($updated) {
            Log::info('Top up cancelled successfully', ['id' => $id]);
            return response()->json(['status' => 1, 'message' => 'Bill Have successfully being Cancelled']);
        } else {
            Log::error('Failed to cancel top up', ['id' => $id]);
            return response()->json(['status' => 0, 'message' => 'Bill Cancellation Failed to be Cancelled']);
        }
    }

    public function cancelControlNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $validatedData = $validator->validated();

        return $this->sendError('Control number cancelled successfully', $validatedData);
    }

    public function billPushToPay(Request $request): JsonResponse
    {
        try {
            $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
            $productionIP = config('params.server_ips.production');

            if ($serverIP == $productionIP) {
                return $this->sendError('This action is not allowed on this server', []);
            }

        $bill = $request->bill;

        if ($bill === null) {
            return $this->sendError('Bill not found', []);
        }

            Log::info('Bill push to pay request', [
                'bill' => $bill,
                'server_ip' => $serverIP
            ]);

        // Generate unique IDs
        $trxId = 'TRX' . uniqid(); // TrxId
        $payRefId = 'REF' . uniqid(); // PayRefId

        // Escape values for XML
        $payerName = htmlspecialchars($bill["payer_name"]);
        $payerEmail = htmlspecialchars($bill["payer_email"]);
        $payerCell = htmlspecialchars($bill["payer_cell"]);

        $xml = '<?xml version="1.0" encoding="UTF-16"?>
        <Gepg>
            <gepgPmtSpInfo>
                <PymtTrxInf>
                    <TrxId>' . $trxId . '</TrxId>
                    <SpCode>SP565</SpCode>
                    <PayRefId>' . $payRefId . '</PayRefId>
                    <BillId>' . $bill["bill_id"] . '</BillId>
                    <PayCtrNum>' . $bill["control_number"] . '</PayCtrNum>
                    <BillAmt>' . $bill["bill_amount"] . '</BillAmt>
                    <PaidAmt>' . $bill["bill_amount"] . '</PaidAmt>
                    <BillPayOpt>3</BillPayOpt>
                    <CCy>TZS</CCy>
                    <TrxDtTm>' . htmlspecialchars(date('Y-m-d' . 'T' . 'h:i:s')) . '</TrxDtTm>
                    <UsdPayChnl>IB</UsdPayChnl>
                    <PyrCellNum>' . $payerCell . '</PyrCellNum>
                    <PyrEmail>' . strtolower($payerEmail) . '</PyrEmail>
                    <PyrName>' . $payerName . '</PyrName>
                    <PspReceiptNumber>' . $payRefId . '</PspReceiptNumber>
                    <PspName>National Bank of Commerce</PspName>
                    <CtrAccNum>011103000689</CtrAccNum>
                </PymtTrxInf>
            </gepgPmtSpInfo>
            <gepgSignature>PuKyvf8KlhYLmMmw5iDW6MPLn/rbeAKa4HJT51bvWgEH5XygVwgeLNM9Voa5NPwwazfvG0TQbNEbJaQngRj0+cPuLc1hbipvennWyr4k8bIJpuVCO0APtuv1xiSBvEjAOuEcKNT6rQnFa/27wBYbbPsL3Nsjku/KKghYL7vOnr5sQsButu43snJDSyOEBxmmD3HnUMiiT9d4QMWpHOM881WGX3GfB1w2YIpNKst9wNV+RtFfUAhNEmvxh72KWGr3n9D9GLzKUNNDCtr4ABe3udiDWsKRQFofkd+2/pGXJnA3NPlTZHvXVkhNZWtbOeAZ9dw+nmcv2RlFccev2qqbRA==</gepgSignature>
        </Gepg>';

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://idsdev.nssf.go.tz/gepg/receipt',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/xml'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        Log::info('GePG API response', [
            'raw_response' => $response,
            'curl_error' => curl_error($curl) ?: 'No error'
        ]);

        $xml = simplexml_load_string($response);
        $json_response = json_encode($xml, JSON_PRETTY_PRINT);
        $res_arr = json_decode($json_response, true);

        Log::info('Parsed GePG response', [
            'xml_object' => $xml,
            'json_response' => $json_response,
            'parsed_array' => $res_arr
        ]);

        if (isset($res_arr['gepgPmtSpInfoAck']['TrxStsCode'])) {
            if ($res_arr['gepgPmtSpInfoAck']['TrxStsCode'] == 7101) {
                return $this->sendResponse([], 'Payment Received Successfully');
            }
        }

        // Ensure $res_arr is always an array
        $errorData = is_array($res_arr) ? $res_arr : ['response' => $res_arr];
        return $this->sendError('Failed to pay', $errorData);

        } catch (\Exception $e) {
            Log::error('Bill push to pay error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('An error occurred while processing payment. Please try again or contact support.', []);
        }
    }

    public function postTopUpBill(Request $request): JsonResponse
    {
        // Validate required fields - either account_no or phone is required
        $rules = [
            'bill_amount' => 'required|numeric|min:1',
            'account_no' => 'nullable|string',
            'phone' => 'nullable|string',
            'tin' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first());
        }

        Log::info('Top up bill request', ['request' => $request->all()]);

        $validatedData = $validator->validated();

        Log::info('Top up bill validated data', ['validated_data' => $validatedData]);

        // Check that either account_no or phone is provided
        if (empty($validatedData['account_no']) && empty($validatedData['phone'])) {
            return $this->sendError('Either account number or phone number is required');
        }

        // Get account details using either account number or phone
        $accountQuery = DB::table('account');

        Log::info('Top up bill account query', ['account_query' => $accountQuery]);

        if (!empty($validatedData['account_no'])) {
            $accountQuery->where('account_no', $validatedData['account_no']);
        } else {
            $accountQuery->where('phone', $validatedData['phone']);
        }

        $account = $accountQuery->first();

        Log::info('Top up bill account', ['account' => $account]);

        if (!$account) {
            $searchField = !empty($validatedData['account_no']) ? 'account number' : 'phone number';
            return $this->sendError("Account not found for this {$searchField}");
        }

        // Get the actual account number for further processing
        $accountNo = $account->account_no;

        Log::info('Top up bill account number', ['account_no' => $accountNo]);

        try {
            DB::beginTransaction();

            // Check for outstanding bill (excluding expired bills and invalid control numbers)
            $outstandingBill = DB::table('top_up')
                ->where('account_no', $accountNo)
                ->whereNull('trx_dt_tm')
                ->whereNull('bill_cancel_date')
                ->whereNotNull('contr_num')
                ->where('contr_num', '!=', '0') // Exclude invalid control numbers
                ->where('contr_num', '!=', '') // Exclude empty control numbers
                ->where('bill_exp_dt', '>=', now()) // Exclude expired bills
                ->first();

            if ($outstandingBill) {
                DB::rollBack();

                return $this->sendError('You have an outstanding bill with Control Number ' . $outstandingBill->contr_num, ['outstanding_bill' => $outstandingBill]);
            }

            // Mark stale bills before creating a new one (psp_receipt_num must be unique per account).
            $staleFailedBills = DB::table('top_up')
                ->where('account_no', $accountNo)
                ->whereNull('psp_receipt_num')
                ->whereNull('contr_num')
                ->get();

            foreach ($staleFailedBills as $staleBill) {
                DB::table('top_up')
                    ->where('id', $staleBill->id)
                    ->update([
                        'psp_receipt_num' => 'BILL FAILED ' . now()->format('Y-m-d H:i:s') . ' #' . $staleBill->id,
                        'updated_at' => now(),
                        'bill_cancel_date' => now(),
                        'updated_by' => 'SYSTEM',
                    ]);
            }

            $expiredBills = DB::table('top_up')
                ->where('account_no', $accountNo)
                ->whereNull('trx_dt_tm')
                ->whereNotNull('bill_exp_dt')
                ->where('bill_exp_dt', '<', now())
                ->where(function ($query) {
                    $query->whereNull('psp_receipt_num')
                        ->orWhere(function ($q) {
                            $q->where('psp_receipt_num', 'not like', 'BILL EXPIRED%')
                                ->where('psp_receipt_num', 'not like', 'BILL FAILED%');
                        });
                })
                ->get();

            Log::info('Top up bill expired bills', ['expired_bills' => $expiredBills]);

            foreach ($expiredBills as $expiredBill) {
                DB::table('top_up')
                    ->where('id', $expiredBill->id)
                    ->update([
                        'psp_receipt_num' => 'BILL EXPIRED ' . $expiredBill->bill_exp_dt . ' #' . $expiredBill->id,
                        'trx_dt_tm' => now(),
                        'updated_at' => now(),
                        'updated_by' => 'SYSTEM',
                    ]);
            }

            // Create top-up bill
            $topUp = DB::table('top_up')->insertGetId([
                'account_no' => $accountNo,
                'bill_gen_by' => $account->id,
                'bill_desc' => 'Toll Fee',
                'bill_amount' => $validatedData['bill_amount'],
                'tin' => $validatedData['tin'] ?? null,
                'bill_gen_at' => Carbon::now()->tz('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s'),
                'bill_exp_dt' => Carbon::now()->tz('Africa/Dar_es_Salaam')->addDay(1)->format('Y-m-d H:i:s')
            ]);

            Log::info('Top up bill created', ['top_up' => $topUp]);

            $billReqId = $topUp;
            $top_up_ref = "TUP";
            $bill_id = $top_up_ref . $billReqId;

            Log::info('Top up bill bill id', ['bill_id' => $bill_id]);

            // GePG Accepted Date Format
            $bill_gen_at = Carbon::now()->tz('Africa/Dar_es_Salaam')->format("Y-m-d\TH:i:s");
            Log::info('Top up bill bill gen at', ['bill_gen_at' => $bill_gen_at]);
            $bill_exp_dt = Carbon::now()->tz('Africa/Dar_es_Salaam')->addDay(1)->format("Y-m-d\TH:i:s");
            Log::info('Top up bill bill exp dt', ['bill_exp_dt' => $bill_exp_dt]);

            // Prepare parameters for GePG
            $gepgParams = [
                'payment_ref' => $bill_id,
                'amount' => (string)$validatedData['bill_amount'],
                'equiv_amount' => (string)$validatedData['bill_amount'],
                'bill_desc' => 'Toll Fee',
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $accountNo,
                'payer_name' => $account->first_name . ' ' . $account->middle_name . ' ' . $account->surname,
                'payer_cell' => '255' . intval($account->phone),
                'generated_by' => (string)$accountNo,
                'days_expires_after' => 1,
                'payer_email' => $account->email ?? 'noreply@example.com',
                'bill_gen_date' => $bill_gen_at,
                'bill_exp_date' => $bill_exp_dt
            ];

            Log::info('Top up bill gepg params', ['gepg_params' => $gepgParams]);

            // Send to GePG
            $gepgResponse = GePG::postBill($gepgParams);

            Log::info('Top up bill gepg response', ['gepg_response' => $gepgResponse]);

            if ($gepgResponse['status'] == 'invalid_params' || $gepgResponse['status'] == 'invalid_request') {
                DB::rollBack();
                Log::error('Top up bill gepg error', ['gepg_error' => $gepgResponse['message']]);
                return $this->sendError('GePG Error: ' . $gepgResponse['message']);
            }

            // Handle GePG response based on the actual response structure
            if (isset($gepgResponse['data']) && is_array($gepgResponse['data'])) {
                $controlNum = $gepgResponse['control_num']
                    ?? ($gepgResponse['data']['control_num'] ?? $gepgResponse['data']['contr_num'] ?? null);

                $updateData = ['t_status' => 'SP', 'updated_at' => now()];
                if (!empty($controlNum)) {
                    $updateData['contr_num'] = $controlNum;
                }

                DB::table('top_up')->where('id', $topUp)->update($updateData);

                $getBilldata = DB::table('top_up')->where('id', $topUp)->first();
                DB::commit();

                Log::info('Top up bill get bill data', ['get_bill_data' => $getBilldata]);

                return $this->sendResponse([
                    'bill_id' => $topUp,
                    'bill_amount' => $validatedData['bill_amount'],
                    'control_number' => $getBilldata->contr_num ?? $controlNum ?? 0,
                    'gepg_response' => $gepgResponse['data']
                ], 'Control Number Request Successfully Sent');
            } else {
                // Failed - update bill with error
                DB::table('top_up')
                    ->where('id', $topUp)
                    ->update([
                        't_status' => 'GF',
                        'error_code' => $gepgResponse['message'] ?? 'Unknown Error'
                    ]);

                Log::info('Top up bill updated', ['updated' => $updated]);

                DB::rollBack();
                Log::error('Top up bill failed to process bill', ['gepg_error' => $gepgResponse['message']]);
                return $this->sendError('Failed to process bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bill', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('Failed to create bill. Please try again or contact support.');
        }
    }

    /**
     * Heartbeat check for control number status
     * Checks if control number is being returned using bill_id or plate_no
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function heartbeatControlNumber(Request $request): JsonResponse
    {
        Log::info('Heartbeat control number check request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'bill_id' => 'nullable|string|max:255',
            'plate_no' => 'nullable|string|max:255',
            'account_no' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in heartbeat control number check', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        // Check if at least one search parameter is provided
        if (!$request->bill_id && !$request->plate_no && !$request->account_no) {
            return $this->sendError('At least one search parameter is required', [
                'message' => 'Please provide either bill_id, plate_no, or account_no parameter'
            ]);
        }

        try {
            if ($request->account_no) {
                // Search by account number in top_up table only
                $accountNo = $request->account_no;

                Log::info('Searching for bills by account number', [
                    'account_no' => $accountNo
                ]);

                $topUp = DB::table('top_up')
                    ->where('account_no', $accountNo)
                    ->orderBy('bill_gen_at', 'desc') // Get the latest bill
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'account_no'
                    ])
                    ->first();

                if ($topUp) {
                    Log::info('Top up bill found for account number', [
                        'account_no' => $accountNo,
                        'bill_id' => $topUp->id,
                        'contr_num' => $topUp->contr_num,
                        'bill_status' => $topUp->bill_status,
                        't_status' => $topUp->t_status,
                        'bill_gen_at' => $topUp->bill_gen_at
                    ]);

                    $response = [
                        'search_type' => 'account_no',
                        'account_no' => $accountNo,
                        'bill_type' => 'top_up',
                        'bill_id' => $topUp->id,
                        'bill_desc' => $topUp->bill_desc,
                        'bill_amount' => $topUp->bill_amount,
                        'control_number' => $topUp->contr_num,
                        'bill_status' => $topUp->bill_status,
                        't_status' => $topUp->t_status,
                        'error_code' => $topUp->error_code,
                        'bill_gen_at' => $topUp->bill_gen_at,
                        'bill_exp_dt' => $topUp->bill_exp_dt,
                        'updated_at' => $topUp->updated_at,
                        'source' => $topUp->source,
                        'has_control_number' => !empty($topUp->contr_num),
                        'is_paid' => $topUp->bill_status == self::PAID,
                        'is_cancelled' => $topUp->bill_status == self::CANCELLED
                    ];

                    return $this->sendResponse($response, 'Top up bill control number status retrieved successfully by account number');
                }

                // No bill found for account number
                Log::warning('No top up bill found for account number', [
                    'account_no' => $accountNo
                ]);

                return $this->sendError('No bill found for account number', [
                    'account_no' => $accountNo,
                    'message' => 'No top up bill found for the provided account number'
                ]);

            } elseif ($request->plate_no) {
                // Search by plate number in bridge_bills table only
                $plateNo = $request->plate_no;

                Log::info('Searching for bills by plate number', [
                    'plate_no' => $plateNo
                ]);

                $bridgeBill = DB::table('bridge_bills')
                    ->where('dist_param', $plateNo)
                    ->orderBy('bill_gen_at', 'desc') // Get the latest bill
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'dist_param'
                    ])
                    ->first();

                if ($bridgeBill) {
                    Log::info('Bridge bill found for plate number', [
                        'plate_no' => $plateNo,
                        'bill_id' => $bridgeBill->id,
                        'contr_num' => $bridgeBill->contr_num,
                        'bill_status' => $bridgeBill->bill_status,
                        't_status' => $bridgeBill->t_status,
                        'bill_gen_at' => $bridgeBill->bill_gen_at
                    ]);

                    $response = [
                        'search_type' => 'plate_no',
                        'plate_no' => $plateNo,
                        'bill_type' => 'bridge_bill',
                        'bill_id' => $bridgeBill->id,
                        'bill_desc' => $bridgeBill->bill_desc,
                        'bill_amount' => $bridgeBill->bill_amount,
                        'control_number' => $bridgeBill->contr_num,
                        'bill_status' => $bridgeBill->bill_status,
                        't_status' => $bridgeBill->t_status,
                        'error_code' => $bridgeBill->error_code,
                        'bill_gen_at' => $bridgeBill->bill_gen_at,
                        'bill_exp_dt' => $bridgeBill->bill_exp_dt,
                        'updated_at' => $bridgeBill->updated_at,
                        'source' => $bridgeBill->source,
                        'has_control_number' => !empty($bridgeBill->contr_num),
                        'is_paid' => $bridgeBill->bill_status == self::PAID,
                        'is_cancelled' => $bridgeBill->bill_status == self::CANCELLED
                    ];

                    return $this->sendResponse($response, 'Bridge bill control number status retrieved successfully by plate number');
                }

                // No bill found for plate number
                Log::warning('No bridge bill found for plate number', [
                    'plate_no' => $plateNo
                ]);

                return $this->sendError('No bill found for plate number', [
                    'plate_no' => $plateNo,
                    'message' => 'No bridge bill found for the provided plate number'
                ]);

            } else {
                // Search by bill_id in both tables
                $billId = $request->bill_id;

                Log::info('Searching for bills by bill_id', [
                    'bill_id' => $billId
                ]);

                // Check bridge_bills table first
                $bridgeBill = DB::table('bridge_bills')
                    ->where('id', $billId)
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'dist_param'
                    ])
                    ->first();

                if ($bridgeBill) {
                    Log::info('Bridge bill found for heartbeat check', [
                        'bill_id' => $billId,
                        'contr_num' => $bridgeBill->contr_num,
                        'bill_status' => $bridgeBill->bill_status,
                        't_status' => $bridgeBill->t_status
                    ]);

                    $response = [
                        'search_type' => 'bill_id',
                        'bill_type' => 'bridge_bill',
                        'bill_id' => $bridgeBill->id,
                        'plate_no' => $bridgeBill->dist_param,
                        'bill_desc' => $bridgeBill->bill_desc,
                        'bill_amount' => $bridgeBill->bill_amount,
                        'control_number' => $bridgeBill->contr_num,
                        'bill_status' => $bridgeBill->bill_status,
                        't_status' => $bridgeBill->t_status,
                        'error_code' => $bridgeBill->error_code,
                        'bill_gen_at' => $bridgeBill->bill_gen_at,
                        'bill_exp_dt' => $bridgeBill->bill_exp_dt,
                        'updated_at' => $bridgeBill->updated_at,
                        'source' => $bridgeBill->source,
                        'has_control_number' => !empty($bridgeBill->contr_num),
                        'is_paid' => $bridgeBill->bill_status == self::PAID,
                        'is_cancelled' => $bridgeBill->bill_status == self::CANCELLED
                    ];

                    return $this->sendResponse($response, 'Bridge bill control number status retrieved successfully');
                }

                // Check top_up table if not found in bridge_bills
                $topUp = DB::table('top_up')
                    ->where('id', $billId)
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source'
                    ])
                    ->first();

                if ($topUp) {
                    Log::info('Top up bill found for heartbeat check', [
                        'bill_id' => $billId,
                        'contr_num' => $topUp->contr_num,
                        'bill_status' => $topUp->bill_status,
                        't_status' => $topUp->t_status
                    ]);

                    $response = [
                        'search_type' => 'bill_id',
                        'bill_type' => 'top_up',
                        'bill_id' => $topUp->id,
                        'bill_desc' => $topUp->bill_desc,
                        'bill_amount' => $topUp->bill_amount,
                        'control_number' => $topUp->contr_num,
                        'bill_status' => $topUp->bill_status,
                        't_status' => $topUp->t_status,
                        'error_code' => $topUp->error_code,
                        'bill_gen_at' => $topUp->bill_gen_at,
                        'bill_exp_dt' => $topUp->bill_exp_dt,
                        'updated_at' => $topUp->updated_at,
                        'source' => $topUp->source,
                        'has_control_number' => !empty($topUp->contr_num),
                        'is_paid' => $topUp->bill_status == self::PAID,
                        'is_cancelled' => $topUp->bill_status == self::CANCELLED
                    ];

                    return $this->sendResponse($response, 'Top up bill control number status retrieved successfully');
                }

                // Bill not found in either table
                Log::warning('Bill not found in either bridge_bills or top_up tables', [
                    'bill_id' => $billId
                ]);

                return $this->sendError('Bill not found', [
                    'bill_id' => $billId,
                    'message' => 'Bill ID not found in bridge_bills or top_up tables'
                ]);
            }

        } catch (\Exception $e) {
            $searchParam = $request->account_no ? 'account_no: ' . $request->account_no :
                          ($request->plate_no ? 'plate_no: ' . $request->plate_no : 'bill_id: ' . $request->bill_id);

            Log::error('Exception during heartbeat control number check', [
                'search_param' => $searchParam,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sendError('Error occurred during heartbeat check. Please try again or contact support.');
        }
    }

    /**
     * Heartbeat check for payment status
     * Checks if payment has been made using bill_id or plate_no
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function heartbeatPayment(Request $request): JsonResponse
    {
        Log::info('Heartbeat payment check request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'bill_id' => 'nullable|string|max:255',
            'plate_no' => 'nullable|string|max:255',
            'account_no' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in heartbeat payment check', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        // Check if at least one search parameter is provided
        if (!$request->bill_id && !$request->plate_no && !$request->account_no) {
            return $this->sendError('At least one search parameter is required', [
                'message' => 'Please provide either bill_id, plate_no, or account_no parameter'
            ]);
        }

        try {
            if ($request->account_no) {
                // Search by account number in top_up table only
                $accountNo = $request->account_no;

                Log::info('Searching for payments by account number', [
                    'account_no' => $accountNo
                ]);

                $topUp = DB::table('top_up')
                    ->where('account_no', $accountNo)
                    ->orderBy('bill_gen_at', 'desc') // Get the latest bill
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'account_no',
                        'trx_dt_tm',
                        'trx_id',
                        'usd_pay_chn',
                        'psp_receipt_num',
                        'psp_name',
                        'ctr_acc_num',
                        'payment_date',
                        'paid_amt',
                        'payer_name',
                        'receipt_number',
                        'pay_ref_id'
                    ])
                    ->first();

                if ($topUp) {
                    $isPaid = !empty($topUp->trx_dt_tm);

                    Log::info('Top up bill payment status found for account number', [
                        'account_no' => $accountNo,
                        'bill_id' => $topUp->id,
                        'is_paid' => $isPaid,
                        'trx_dt_tm' => $topUp->trx_dt_tm,
                        'bill_status' => $topUp->bill_status
                    ]);

                    $response = [
                        'search_type' => 'account_no',
                        'account_no' => $accountNo,
                        'bill_type' => 'top_up',
                        'bill_id' => $topUp->id,
                        'bill_desc' => $topUp->bill_desc,
                        'bill_amount' => $topUp->bill_amount,
                        'control_number' => $topUp->contr_num,
                        'bill_status' => $topUp->bill_status,
                        't_status' => $topUp->t_status,
                        'error_code' => $topUp->error_code,
                        'bill_gen_at' => $topUp->bill_gen_at,
                        'bill_exp_dt' => $topUp->bill_exp_dt,
                        'updated_at' => $topUp->updated_at,
                        'source' => $topUp->source,
                        'has_control_number' => !empty($topUp->contr_num),
                        'is_paid' => $isPaid,
                        'is_cancelled' => $topUp->bill_status == self::CANCELLED,
                        'payment_details' => [
                            'transaction_datetime' => $topUp->trx_dt_tm,
                            'transaction_id' => $topUp->trx_id,
                            'payment_channel' => $topUp->usd_pay_chn,
                            'psp_receipt_number' => $topUp->psp_receipt_num,
                            'psp_name' => $topUp->psp_name,
                            'credit_account_number' => $topUp->ctr_acc_num,
                            'payment_date' => $topUp->payment_date,
                            'paid_amount' => $topUp->paid_amt,
                            'payer_name' => $topUp->payer_name,
                            'receipt_number' => $topUp->receipt_number,
                            'pay_ref_id' => $topUp->pay_ref_id
                        ]
                    ];

                    $message = $isPaid
                        ? 'Top up bill payment status retrieved successfully by account number - Payment completed'
                        : 'Top up bill payment status retrieved successfully by account number - Payment pending';

                    return $this->sendResponse($response, $message);
                }

                // No bill found for account number
                Log::warning('No top up bill found for account number', [
                    'account_no' => $accountNo
                ]);

                return $this->sendError('No bill found for account number', [
                    'account_no' => $accountNo,
                    'message' => 'No top up bill found for the provided account number'
                ]);

            } elseif ($request->plate_no) {
                // Search by plate number in bridge_bills table only
                $plateNo = $request->plate_no;

                Log::info('Searching for payments by plate number', [
                    'plate_no' => $plateNo
                ]);

                $bridgeBill = DB::table('bridge_bills')
                    ->where('dist_param', $plateNo)
                    ->orderBy('bill_gen_at', 'desc') // Get the latest bill
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'dist_param',
                        'trx_dt_tm',
                        'trx_id',
                        'usd_pay_chn',
                        'psp_receipt_num',
                        'psp_name',
                        'ctr_acc_num',
                        'payment_date',
                        'paid_amt',
                        'payer_name',
                        'receipt_number',
                        'pay_ref_id'
                    ])
                    ->first();

                if ($bridgeBill) {
                    $isPaid = !empty($bridgeBill->trx_dt_tm);

                    Log::info('Bridge bill payment status found for plate number', [
                        'plate_no' => $plateNo,
                        'bill_id' => $bridgeBill->id,
                        'is_paid' => $isPaid,
                        'trx_dt_tm' => $bridgeBill->trx_dt_tm,
                        'bill_status' => $bridgeBill->bill_status
                    ]);

                    $response = [
                        'search_type' => 'plate_no',
                        'plate_no' => $plateNo,
                        'bill_type' => 'bridge_bill',
                        'bill_id' => $bridgeBill->id,
                        'bill_desc' => $bridgeBill->bill_desc,
                        'bill_amount' => $bridgeBill->bill_amount,
                        'control_number' => $bridgeBill->contr_num,
                        'bill_status' => $bridgeBill->bill_status,
                        't_status' => $bridgeBill->t_status,
                        'error_code' => $bridgeBill->error_code,
                        'bill_gen_at' => $bridgeBill->bill_gen_at,
                        'bill_exp_dt' => $bridgeBill->bill_exp_dt,
                        'updated_at' => $bridgeBill->updated_at,
                        'source' => $bridgeBill->source,
                        'has_control_number' => !empty($bridgeBill->contr_num),
                        'is_paid' => $isPaid,
                        'is_cancelled' => $bridgeBill->bill_status == self::CANCELLED,
                        'payment_details' => [
                            'transaction_datetime' => $bridgeBill->trx_dt_tm,
                            'transaction_id' => $bridgeBill->trx_id,
                            'payment_channel' => $bridgeBill->usd_pay_chn,
                            'psp_receipt_number' => $bridgeBill->psp_receipt_num,
                            'psp_name' => $bridgeBill->psp_name,
                            'credit_account_number' => $bridgeBill->ctr_acc_num,
                            'payment_date' => $bridgeBill->payment_date,
                            'paid_amount' => $bridgeBill->paid_amt,
                            'payer_name' => $bridgeBill->payer_name,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id
                        ]
                    ];

                    $message = $isPaid
                        ? 'Bridge bill payment status retrieved successfully by plate number - Payment completed'
                        : 'Bridge bill payment status retrieved successfully by plate number - Payment pending';

                    return $this->sendResponse($response, $message);
                }

                // No bill found for plate number
                Log::warning('No bridge bill found for plate number', [
                    'plate_no' => $plateNo
                ]);

                return $this->sendError('No bill found for plate number', [
                    'plate_no' => $plateNo,
                    'message' => 'No bridge bill found for the provided plate number'
                ]);

            } else {
                // Search by bill_id in both tables
                $billId = $request->bill_id;

                Log::info('Searching for payments by bill_id', [
                    'bill_id' => $billId
                ]);

                // Check bridge_bills table first
                $bridgeBill = DB::table('bridge_bills')
                    ->where('id', $billId)
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'dist_param',
                        'trx_dt_tm',
                        'trx_id',
                        'usd_pay_chn',
                        'psp_receipt_num',
                        'psp_name',
                        'ctr_acc_num',
                        'payment_date',
                        'paid_amt',
                        'payer_name',
                        'receipt_number',
                        'pay_ref_id'
                    ])
                    ->first();

                if ($bridgeBill) {
                    $isPaid = !empty($bridgeBill->trx_dt_tm);

                    Log::info('Bridge bill payment status found for bill_id', [
                        'bill_id' => $billId,
                        'is_paid' => $isPaid,
                        'trx_dt_tm' => $bridgeBill->trx_dt_tm,
                        'bill_status' => $bridgeBill->bill_status
                    ]);

                    $response = [
                        'search_type' => 'bill_id',
                        'bill_type' => 'bridge_bill',
                        'bill_id' => $bridgeBill->id,
                        'plate_no' => $bridgeBill->dist_param,
                        'bill_desc' => $bridgeBill->bill_desc,
                        'bill_amount' => $bridgeBill->bill_amount,
                        'control_number' => $bridgeBill->contr_num,
                        'bill_status' => $bridgeBill->bill_status,
                        't_status' => $bridgeBill->t_status,
                        'error_code' => $bridgeBill->error_code,
                        'bill_gen_at' => $bridgeBill->bill_gen_at,
                        'bill_exp_dt' => $bridgeBill->bill_exp_dt,
                        'updated_at' => $bridgeBill->updated_at,
                        'source' => $bridgeBill->source,
                        'has_control_number' => !empty($bridgeBill->contr_num),
                        'is_paid' => $isPaid,
                        'is_cancelled' => $bridgeBill->bill_status == self::CANCELLED,
                        'payment_details' => [
                            'transaction_datetime' => $bridgeBill->trx_dt_tm,
                            'transaction_id' => $bridgeBill->trx_id,
                            'payment_channel' => $bridgeBill->usd_pay_chn,
                            'psp_receipt_number' => $bridgeBill->psp_receipt_num,
                            'psp_name' => $bridgeBill->psp_name,
                            'credit_account_number' => $bridgeBill->ctr_acc_num,
                            'payment_date' => $bridgeBill->payment_date,
                            'paid_amount' => $bridgeBill->paid_amt,
                            'payer_name' => $bridgeBill->payer_name,
                            'receipt_number' => $bridgeBill->receipt_number,
                            'pay_ref_id' => $bridgeBill->pay_ref_id
                        ]
                    ];

                    $message = $isPaid
                        ? 'Bridge bill payment status retrieved successfully - Payment completed'
                        : 'Bridge bill payment status retrieved successfully - Payment pending';

                    return $this->sendResponse($response, $message);
                }

                // Check top_up table if not found in bridge_bills
                $topUp = DB::table('top_up')
                    ->where('id', $billId)
                    ->select([
                        'id',
                        'bill_desc',
                        'bill_amount',
                        'contr_num',
                        'bill_status',
                        't_status',
                        'error_code',
                        'bill_gen_at',
                        'bill_exp_dt',
                        'updated_at',
                        'source',
                        'trx_dt_tm',
                        'trx_id',
                        'usd_pay_chn',
                        'psp_receipt_num',
                        'psp_name',
                        'ctr_acc_num',
                        'payment_date',
                        'paid_amt',
                        'payer_name',
                        'receipt_number',
                        'pay_ref_id'
                    ])
                    ->first();

                if ($topUp) {
                    $isPaid = !empty($topUp->trx_dt_tm);

                    Log::info('Top up bill payment status found for bill_id', [
                        'bill_id' => $billId,
                        'is_paid' => $isPaid,
                        'trx_dt_tm' => $topUp->trx_dt_tm,
                        'bill_status' => $topUp->bill_status
                    ]);

                    $response = [
                        'search_type' => 'bill_id',
                        'bill_type' => 'top_up',
                        'bill_id' => $topUp->id,
                        'bill_desc' => $topUp->bill_desc,
                        'bill_amount' => $topUp->bill_amount,
                        'control_number' => $topUp->contr_num,
                        'bill_status' => $topUp->bill_status,
                        't_status' => $topUp->t_status,
                        'error_code' => $topUp->error_code,
                        'bill_gen_at' => $topUp->bill_gen_at,
                        'bill_exp_dt' => $topUp->bill_exp_dt,
                        'updated_at' => $topUp->updated_at,
                        'source' => $topUp->source,
                        'has_control_number' => !empty($topUp->contr_num),
                        'is_paid' => $isPaid,
                        'is_cancelled' => $topUp->bill_status == self::CANCELLED,
                        'payment_details' => [
                            'transaction_datetime' => $topUp->trx_dt_tm,
                            'transaction_id' => $topUp->trx_id,
                            'payment_channel' => $topUp->usd_pay_chn,
                            'psp_receipt_number' => $topUp->psp_receipt_num,
                            'psp_name' => $topUp->psp_name,
                            'credit_account_number' => $topUp->ctr_acc_num,
                            'payment_date' => $topUp->payment_date,
                            'paid_amount' => $topUp->paid_amt,
                            'payer_name' => $topUp->payer_name,
                            'receipt_number' => $topUp->receipt_number,
                            'pay_ref_id' => $topUp->pay_ref_id
                        ]
                    ];

                    $message = $isPaid
                        ? 'Top up bill payment status retrieved successfully - Payment completed'
                        : 'Top up bill payment status retrieved successfully - Payment pending';

                    return $this->sendResponse($response, $message);
                }

                // Bill not found in either table
                Log::warning('Bill not found in either bridge_bills or top_up tables', [
                    'bill_id' => $billId
                ]);

                return $this->sendError('Bill not found', [
                    'bill_id' => $billId,
                    'message' => 'Bill ID not found in bridge_bills or top_up tables'
                ]);
            }

        } catch (\Exception $e) {
            $searchParam = $request->account_no ? 'account_no: ' . $request->account_no :
                          ($request->plate_no ? 'plate_no: ' . $request->plate_no : 'bill_id: ' . $request->bill_id);

            Log::error('Exception during heartbeat payment check', [
                'search_param' => $searchParam,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sendError('Error occurred during payment check', [
                'search_param' => $searchParam,
                'error' => $e->getMessage()
            ]);
        }
    }
}
