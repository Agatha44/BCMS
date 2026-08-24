<?php

namespace App\Http\Controllers\Ussd;

use App\Http\Controllers\BasicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\BridgeBill;
use App\Models\Vehicle;
use App\Models\Account;
use App\Models\BundleSubscription;
use App\Models\TollBundle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\BundlesController\BundleController;
use App\Http\Controllers\Billing\BillingController;
use Carbon\Carbon;
use App\Helpers\GePG;

class UssdController extends BasicController
{
    /**
     * Main USSD request handler
     * Routes requests based on bridge_services and subcategories
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleRequest(Request $request): JsonResponse
    {
        $bridgeServices = $request->input('bridge_services');
        $msisdn = $request->input('msisdn');
        $transactionId = $request->input('transaction_id', 'N/A');
        
        Log::info('USSD Request Received', [
            'bridge_services' => $bridgeServices,
            'prepayment_service' => $request->input('prepayment_service'),
            'bundle_service' => $request->input('bundle_service'),
            'msisdn' => $msisdn,
            'transaction_id' => $transactionId,
            'mno' => $request->input('mno'),
            'source' => $request->input('source'),
            'full_payload' => $request->all()
        ]);

        // Route based on bridge_services (main category)
        switch ($bridgeServices) {
            case '1': // Bundle Services
                $bundleService = $request->input('bundle_service');
                
                switch ($bundleService) {
                    case '1': // Buy/Request Bundle
                        $bundleController = new BundleController();
                        $request->merge(['source' => 'ussd']);
                        return $bundleController->postBill($request);
                        // return $this->handleBundleRequest($request);
                    
                    case '2': // Bundle Balance
                        $request->merge(['source' => 'ussd']);
                        return $this->handleBundleBalanceRequest($request);
                    
                    default:
                        return $this->sendError('Unknown bundle service type. bundle_service must be 1 (Buy Bundle) or 2 (Bundle Balance)', [
                            'bundle_service' => $bundleService,
                            'available_services' => [
                                '1' => 'Buy Bundle',
                                '2' => 'Bundle Balance'
                            ]
                        ]);
                }
            
            case '2': // Prepayment Services
                $prepaymentService = $request->input('prepayment_service');
                
                switch ($prepaymentService) {
                    case '1': // Top-up Account
                        $request->merge(['source' => 'ussd']);
                        return $this->handleTopUpRequest($request);
                    
                    case '2': // Account Balance
                        $request->merge(['source' => 'ussd']);
                        return $this->handleAccountBalanceRequest($request);
                    
                    default:
                        return $this->sendError('Unknown prepayment service type. prepayment_service must be 1 (Top-up) or 2 (Balance)', [
                            'prepayment_service' => $prepaymentService,
                            'available_services' => [
                                '1' => 'Top-up Account',
                                '2' => 'Account Balance'
                            ]
                        ]);
                }
            
            default:
                return $this->sendError('Unknown service type. bridge_services must be 1 (Bundle) or 2 (Prepayment)', [
                    'bridge_services' => $bridgeServices,
                    'available_services' => [
                        '1' => 'Bundle Services',
                        '2' => 'Prepayment Services'
                    ]
                ]);
        }
    }


    /**
     * Handle bundle request from USSD gateway
     * Processes bundle subscription request with USSD-specific payload format
     * 
     * @param Request $request
     * @return JsonResponse
     */
    private function handleBundleRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bundle_id' => 'required|string|exists:toll_bundles,id',
            'plate_no' => 'required|string',
            'msisdn' => 'required|string',
        ]);



        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $validatedData = $validator->validated();
        $msisdn = $validatedData['msisdn'];
        
        // Extract phone number from msisdn (remove country code if present)
        $phone = $this->normalizePhoneNumber($msisdn);

        // Find vehicle by plate number
        $vehicle = Vehicle::where('plate_no', $validatedData['plate_no'])->first();
        if (!$vehicle) {
            return $this->sendError('Vehicle not found for plate number: ' . $validatedData['plate_no']);
        }

        // Get account associated with the vehicle
        $account = Account::where('account_no', $vehicle->account_no)->first();
        if (!$account) {
            // return $this->sendError('Account not found for vehicle');
            return response()->json(['ussd_response' => 'Chombo hakijasajiliwa kutumia huduma ya bando']);
        }

        // Verify phone number matches account (optional security check)
        if ($account->phone && $this->normalizePhoneNumber($account->phone) !== $phone) {
            Log::warning('USSD phone mismatch', [
                'requested_phone' => $phone,
                'account_phone' => $account->phone,
                'account_no' => $account->account_no,
                'msisdn' => $msisdn
            ]);
            // return response()->json(['ussd_response' => 'Namba']);
        }

        try {
            DB::beginTransaction();

            $bundle = TollBundle::find($validatedData['bundle_id']);
            if (!$bundle) {
                return $this->sendError('Bundle not found');
            }

            $vehicle_id = $vehicle->id;
            $activeSubscription = BundleSubscription::activeSubscription($account->id, $vehicle_id);

            $startDate = now();
            if ($activeSubscription) {
                // Update start date with the expiration date of the current active subscription if any
                $startDate = $activeSubscription->expire_date;
            }

            // For USSD requests, we don't have an authenticated user
            $createdBy = null;

            // Create a new bundle subscription
            $bundleSubscription = BundleSubscription::create([
                'account_id' => $account->account_no,
                'vehicle_id' => $vehicle_id,
                'bundle_id' => $validatedData['bundle_id'],
                'status' => BundleSubscription::STATUS_PENDING,
                'start_date' => $startDate,
                'expire_date' => $bundle->calculateExpireDate($startDate, $bundle->duration),
                'created_by' => $createdBy,
            ]);

            Log::info('USSD Bundle subscription created', [
                'bundle_subscription_id' => $bundleSubscription->id,
                'account_no' => $account->account_no,
                'plate_no' => $validatedData['plate_no'],
                'phone' => $phone,
                'msisdn' => $msisdn,
                'bundle_id' => $validatedData['bundle_id'],
                'transaction_id' => $request->input('transaction_id')
            ]);

            // Proceed to create control number
            $bill = BridgeBill::requestBundleSubscriptionControlNo($bundleSubscription);

            if ($bill['success']) {
                DB::commit();
                return $this->sendResponse('Bundle subscription created successfully', [
                    'bundle_subscription' => $bundleSubscription,
                    'control_number' => $bill['control_number'] ?? null,
                    'message' => 'Please pay using the control number.',
                    'transaction_id' => $request->input('transaction_id')
                ]);
            }

            DB::rollBack();
            return $this->sendError($bill['message'] ?? 'Failed to generate control number', $bill);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('USSD bundle request error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $validatedData,
                'transaction_id' => $request->input('transaction_id')
            ]);
            return $this->sendError('Error processing request: ' . $e->getMessage());
        }
    }

    /**
     * Handle account balance request from USSD gateway
     * Returns account balance and subscription status
     * 
     * @param Request $request
     * @return JsonResponse
     */
    private function handleAccountBalanceRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'msisdn' => 'required|string',
            'phone_no' => 'nullable|string', // Optional, can use msisdn instead
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $msisdn = $request->input('msisdn');
        $phoneNo = $request->input('phone_no', $msisdn);
        
        // Normalize phone number
        $phone = $this->normalizePhoneNumber($phoneNo);

        try {
            $account = Account::where('phone', $phone)->first();
            if (!$account) {
                // Try with msisdn format
                $account = Account::where('phone', $this->normalizePhoneNumber($msisdn))->first();
            }

            if (!$account) {
                return $this->sendError('Account not found for phone number: ' . $phone);
            }

            // Get active subscriptions
            $activeSubscriptions = BundleSubscription::where('account_id', $account->id)
                ->where('status', BundleSubscription::STATUS_ACTIVE)
                ->where('expire_date', '>', now())
                ->with(['tollBundle', 'vehicle'])
                ->get();

            // Get account balance (if available)
            $balance = DB::table('account')
                ->where('id', $account->id)
                ->value('balance') ?? 0;

            Log::info('USSD Account balance requested', [
                'account_no' => $account->account_no,
                'phone' => $phone,
                'msisdn' => $msisdn,
                'transaction_id' => $request->input('transaction_id')
            ]);

            // Send SMS to the account
            $sms_body = 'Salio lako la kadi ni TZS ' . "\n" .
                           'Kiasi: ' . number_format($balance, 2) . " TZS\n";
                           
            // Only include active bundles section if there are active bundles
            if ($activeSubscriptions->count() > 0) {
                $sms_body .= 'Bando zilizobaki: ' . $activeSubscriptions->count() . "\n\n";
                
                foreach ($activeSubscriptions as $index => $subscription) {
                    $vehiclePlate = $subscription->vehicle->plate_no ?? 'N/A';
                    $bundleName = $subscription->tollBundle->bundle_description ?? $subscription->tollBundle->name ?? 'N/A';
                    $expireDate = date('d/m/Y', strtotime($subscription->expire_date));
                    $remainingDays = now()->diffInDays($subscription->expire_date, false);
                    
                    $sms_body .= ($index + 1) . '. ' . $vehiclePlate . "\n" .
                    'Aina ya bando: ' . $bundleName . "\n" .
                    'Tarehe ya mwisho: ' . $expireDate . "\n" .
                    'Siku zilizobaki: ' . $remainingDays . "\n\n";
                }
            }
            
            $sms_body .= 'NSSF-Nyerere Bridge';
            $sms_recipient = $account->phone;
            $sms_process = 'Account Balance';

            // Log SMS
            DB::table('ids_messages')->insert([
                'sms_body' => $sms_body,
                'sms_recipient' => $sms_recipient,
                'sms_source' => config('app.sms_source'),
                'sms_process' => $sms_process,
                'created_at' => now()  ]);


            return $this->sendResponse('Account status retrieved successfully', [
                'account_no' => $account->account_no,
                'account_name' => trim($account->first_name . ' ' . $account->middle_name . ' ' . $account->surname),
                'phone' => $account->phone,
                'balance' => $balance,
                'active_subscriptions' => $activeSubscriptions->map(function ($sub) {
                    return [
                        'vehicle_plate' => $sub->vehicle->plate_no ?? 'N/A',
                        'bundle_name' => $sub->tollBundle->name ?? 'N/A',
                        'expire_date' => $sub->expire_date,
                        'remaining_days' => now()->diffInDays($sub->expire_date, false)
                    ];
                }),
                'transaction_id' => $request->input('transaction_id')
            ]);

        } catch (\Exception $e) {
            Log::error('USSD account balance request error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone,
                'msisdn' => $msisdn,
                'transaction_id' => $request->input('transaction_id')
            ]);
            return $this->sendError('Error checking account status: ' . $e->getMessage());
        }
    }

    /**
     * Handle top-up request from USSD gateway
     * Creates a top-up bill and generates control number
     * Logic similar to BillingController::postTopUpBill
     * 
     * @param Request $request
     * @return JsonResponse
     */
    private function handleTopUpRequest(Request $request): JsonResponse
    {
        // Validate required fields - account_card and top_up from new USSD payload
        $validator = Validator::make($request->all(), [
            'account_card' => 'required|string',
            'top_up' => 'required|numeric|min:1',
            'msisdn' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first());
        }

        $validatedData = $validator->validated();
        $msisdn = $validatedData['msisdn'];
        $accountCard = $validatedData['account_card'];
        $amount = (float) $validatedData['top_up'];
        
        // Also normalize msisdn for account lookup
        $msisdnNormalized = $this->normalizePhoneNumber($msisdn);

        // Get account details using phone number
        $accountQuery = DB::table('account');
        $accountQuery->where('account_no', '=', $accountCard)
        ->orWhere('phone', '=', $msisdnNormalized)
        ->orWhere('nfc_card', '=', $accountCard);
        $account = $accountQuery->first();

        if (!$account) {
            return response()->json(['ussd_response' => 'Namba ya akaunti sio sahihi']);
        }

        // Get the actual account number for further processing
        $accountNo = $account->account_no;
        
        $request->merge(['account_no' => $accountNo, 
            'phone' => $account->phone, 'bill_amount' => $amount, 'tin' => $account->tin ?? null]);

        new BillingController();
        $billingController = new BillingController();
        return $billingController->postTopUpBill($request);

    }

    /**
     * Handle bundle balance request from USSD gateway
     * Returns bundle subscription status for a specific vehicle
     * 
     * @param Request $request
     * @return JsonResponse
     */
    private function handleBundleBalanceRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'required|string',
            'msisdn' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $validatedData = $validator->validated();
        $plateNo = $validatedData['plate_no'];
        $msisdn = $validatedData['msisdn'];
        $transactionId = $request->input('transaction_id', 'N/A');

        try {
            // Find vehicle by plate number
            $vehicle = Vehicle::where('plate_no', $plateNo)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found for plate number: ' . $plateNo, [
                    'transaction_id' => $transactionId
                ]);
            }

            // Get account associated with the vehicle
            $account = Account::where('account_no', $vehicle->account_no)->first();
            if (!$account) {
                return $this->sendError('Account not found for vehicle', [
                    'transaction_id' => $transactionId
                ]);
            }

            // Get active bundle subscriptions for this vehicle
            $activeSubscriptions = BundleSubscription::where('vehicle_id', $vehicle->id)
                ->where('status', BundleSubscription::STATUS_ACTIVE)
                ->where('expire_date', '>', now())
                ->with(['tollBundle'])
                ->get();

            Log::info('USSD Bundle balance requested', [
                'plate_no' => $plateNo,
                'vehicle_id' => $vehicle->id,
                'account_no' => $account->account_no,
                'msisdn' => $msisdn,
                'active_subscriptions_count' => $activeSubscriptions->count(),
                'transaction_id' => $transactionId
            ]);

            // Prepare SMS message
            $sms_body = 'Salio la bando la: ' . $plateNo . "\n\n";

            if ($activeSubscriptions->count() > 0) {
                // $sms_body .= 'Bando zilizobaki: ' . $activeSubscriptions->count() . "\n\n";
                
                foreach ($activeSubscriptions as $index => $subscription) {
                    $bundleName = $subscription->tollBundle->bundle_description ?? $subscription->tollBundle->name ?? 'N/A';
                    $expireDate = date('j M Y H:i', strtotime($subscription->expire_date));
                    $remainingDays = now()->diffInDays($subscription->expire_date, false);
                    $sms_body .= ($index + 1) . '. Aina ya bando: ' . $bundleName . "\n" .
                                'Tarehe ya mwisho: ' . $expireDate . "\n" .
                                'Tembelea: https://portal.nssf.go.tz/'. "\n";
                }
            } else {
                $sms_body .= 'Hakuna bando kwenye gari hili'. "\n\n";
            }
            
            $sms_body .= 'Daraja la Nyerere';

            // Send SMS to the account phone
            $sms_recipient = $account->phone;
            $sms_process = 'Bundle Balance';

            // Log SMS
            DB::table('ids_messages')->insert([
                'sms_body' => $sms_body,
                'sms_recipient' => $sms_recipient,
                'sms_source' => config('app.sms_source'),
                'sms_process' => $sms_process,
                'created_at' => now()
            ]);

            return $this->sendResponse('Bundle balance retrieved successfully', [
                'vehicle_plate' => $plateNo,
                'account_no' => $account->account_no,
                'account_name' => trim($account->first_name . ' ' . $account->middle_name . ' ' . $account->surname),
                'active_subscriptions' => $activeSubscriptions->map(function ($sub) {
                    return [
                        'bundle_description' => $sub->tollBundle->bundle_description ?? 'N/A',
                        'start_date' => $sub->start_date,
                        'expire_date' => $sub->expire_date,
                        'remaining_days' => max(0, now()->diffInDays($sub->expire_date, false))
                    ];
                }),
                'transaction_id' => $transactionId
            ]);

        } catch (\Exception $e) {
            Log::error('USSD bundle balance request error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'plate_no' => $plateNo,
                'msisdn' => $msisdn,
                'transaction_id' => $transactionId
            ]);
            return $this->sendError('Error checking bundle balance: ' . $e->getMessage(), [
                'transaction_id' => $transactionId
            ]);
        }
    }

    /**
     * Normalize phone number to standard format
     * Removes country code prefix and formats consistently
     * 
     * @param string $phone
     * @return string
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Remove country code if present (255 for Tanzania)
        if (strpos($phone, '255') === 0 && strlen($phone) > 9) {
            $phone = substr($phone, 3);
        }
        
        // Remove leading 0 if present
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }
        
        return $phone;
    }

    /**
     * Check account balance and subscription status
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAccountStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['error' => $validator->errors()]);
        }

        try {
            $account = Account::where('phone', $request->phone)->first();
            if (!$account) {
                return $this->sendError('Account not found for phone number: ' . $request->phone);
            }

            // Get active subscriptions
            $activeSubscriptions = BundleSubscription::where('account_id', $account->id)
                ->where('status', BundleSubscription::STATUS_ACTIVE)
                ->where('expire_date', '>', now())
                ->with(['tollBundle', 'vehicle'])
                ->get();

            // Get account balance (if available)
            $balance = DB::table('account')
                ->where('id', $account->id)
                ->value('balance') ?? 0;

            return $this->sendResponse('Account status retrieved successfully', [
                'account_no' => $account->account_no,
                'account_name' => trim($account->first_name . ' ' . $account->middle_name . ' ' . $account->surname),
                'phone' => $account->phone,
                'balance' => $balance,
                'active_subscriptions' => $activeSubscriptions->map(function ($sub) {
                    return [
                        'vehicle_plate' => $sub->vehicle->plate_no ?? 'N/A',
                        'bundle_name' => $sub->tollBundle->name ?? 'N/A',
                        'expire_date' => $sub->expire_date,
                        'remaining_days' => now()->diffInDays($sub->expire_date, false)
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('USSD check account status error', [
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Error checking account status: ' . $e->getMessage());
        }
    }
}

