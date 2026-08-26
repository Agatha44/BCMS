<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Models\Account;
use App\Models\AccountCardHistory;
use App\Models\AccountBalanceHistory;
use App\Models\PosTerminal;
use App\Models\Vehicle;
use App\Models\BodyType;
use App\Models\TollBundle;
use App\Models\PriceList;
use App\Models\IdsMessages;
use App\Models\Notifications\Notifications;
use App\Models\BridgeBill;
use App\Models\TollTransaction;
use App\Models\Receipt;
use App\Services\Pos\PosPaymentFlowLogger;
use App\Services\Vehicle\CardNumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AccountController extends BasicController
{
    /**
     * Portal registration endpoint
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function accountRegistration(Request $request): JsonResponse
    {
        Log::info('Account registration request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'plate_no' => 'nullable|string',
            'bundle_id' => 'nullable|integer',
            'nida' => 'nullable|string',
            'first_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'surname' => 'required|string',
            'email' => 'required|email',
            'password_hash' => 'required|string',
            'created_by' => 'required|integer'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in account registration', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors());
        }

        $userDetails = $request->all();
        $mobilePhone = $userDetails['phone'];
        $plateNumber = $userDetails['plate_no'] ?? null;
        $bundleId = $userDetails['bundle_id'] ?? null;

        // Check if account exists
        $checkAccount = Account::where('phone', $mobilePhone)->first();
        
        // Only check for vehicle if plate_no is provided
        $checkVehicle = null;
        if ($plateNumber) {
            $checkVehicle = Vehicle::where('plate_no', $plateNumber)->first();
        }

        // Get Body Type and bundle details only if vehicle and bundle are provided
        $checkBody = null;
        $tollBundles = null;
        $bundlePrice = null;
        $bundleAmount = null;

        if ($checkVehicle && $bundleId) {
            $checkBody = BodyType::find($checkVehicle->body_type_id);
            if (!$checkBody) {
                Log::error('Body type not found in account registration', [
                    'body_type_id' => $checkVehicle->body_type_id,
                    'vehicle_id' => $checkVehicle->id,
                    'request_data' => $request->all()
                ]);
                return $this->sendError('Body type not found');
            }

            // Get bundle details
            $tollBundles = TollBundle::find($bundleId);
            if (!$tollBundles) {
                Log::error('Bundle not found in account registration', [
                    'bundle_id' => $bundleId,
                    'request_data' => $request->all()
                ]);
                return $this->sendError('Bundle not found');
            }

            // Get bundle price
            $bundlePrice = PriceList::where('body_type_id', $checkVehicle->body_type_id)->first();
            if (!$bundlePrice) {
                Log::error('Price list not found in account registration', [
                    'body_type_id' => $checkVehicle->body_type_id,
                    'vehicle_id' => $checkVehicle->id,
                    'request_data' => $request->all()
                ]);
                return $this->sendError('Price list not found for this body type');
            }

            // Determine bundle amount
            $bundleAmount = match($bundleId) {
                1 => $bundlePrice->daily_bundle_amount,
                2 => $bundlePrice->weekly_bundle_amount,
                3 => $bundlePrice->monthly_bundle_amount,
                default => 'unknown amount'
            };
        }

        // Check if user has an account
        if ($checkAccount == null) {
            Log::info('Creating new account for user', [
                'phone' => $mobilePhone,
                'plate_no' => $plateNumber,
                'bundle_id' => $bundleId
            ]);
            
            // Create new account
            try {
                DB::beginTransaction();

                $model = new Account();
                $model->nida = $userDetails['nida'];
                $model->first_name = $userDetails['first_name'];
                $model->middle_name = $userDetails['middle_name'] ?? null;
                $model->surname = $userDetails['surname'];
                $model->email = $userDetails['email'];
                $model->phone = $mobilePhone;
                $model->created_at = now();
                $model->created_by = $userDetails['created_by'];
                $model->password_hash = Hash::make($userDetails['password_hash']);

                if ($model->save()) {
                    Log::info('Account created successfully', [
                        'account_id' => $model->id,
                        'phone' => $model->phone
                    ]);
                    
                    // Get account number
                    $account = DB::table('account')
                        ->select('account_no')
                        ->where('id', $model->id)
                        ->first();

                    // Send SMS
                    $smsRecipient = $model->phone;
                    $smsBody = 'Usajili wa Malipo ya Tozo ya kupita Darajani' . "\n" .
                        'Akaunti: ' . $account->account_no . "\n" .
                        'Jina: ' . $model->first_name . ' ' . $model->surname . "\n" .
                        'Nywila/Neno la Siri: 12345' . "\n" .
                        'Kwa Huduma zaidi Tembela Tovuti' . "\n" . 'https://bridge-portal.nssf.go.tz';

                    $smsProcess = 'Prepayment Account Creation';
                    $logSms = new IdsMessages();
                    $logSms->sms_body = $smsBody;
                    $logSms->sms_recipient = strval($smsRecipient);
                    $logSms->sms_source = config('app.sms_source');
                    $logSms->sms_process = $smsProcess;
                    $logSms->created_at = now();

                    if ($logSms->save()) {
                        Log::info('SMS logged successfully', [
                            'recipient' => $smsRecipient,
                            'account_id' => $model->id
                        ]);
                        
                        // Link Vehicle only if vehicle and bundle are provided
                        if ($checkVehicle && $bundleId) {
                            if (in_array($checkVehicle->body_type_id, [178, 183, 184, 185, 186, 189, 190, 191, 192, 193, 194])) {
                                Log::error('Body type not allowed for service', [
                                    'body_type_id' => $checkVehicle->body_type_id,
                                    'vehicle_id' => $checkVehicle->id
                                ]);
                                DB::rollBack();
                                return $this->sendError('Body type not allowed to this service');
                            }

                            if ($checkVehicle->account_no != null) {
                                Log::error('Vehicle already associated with account', [
                                    'vehicle_id' => $checkVehicle->id,
                                    'existing_account_no' => $checkVehicle->account_no
                                ]);
                                DB::rollBack();
                                return $this->sendError('Vehicle Already associated with an Account number', [
                                    'account_number' => $checkVehicle->account_no
                                ]);
                            }

                            // Generate unique card number
                            $cardNumber = $this->generateUniqueCardNumber();
                            if (!$cardNumber) {
                                Log::error('Failed to generate unique card number', [
                                    'vehicle_id' => $checkVehicle->id,
                                    'account_id' => $model->id
                                ]);
                                DB::rollBack();
                                return $this->sendError('Failed to generate unique card number');
                            }
                            
                            Log::info('Card number generated successfully', [
                                'card_number' => $cardNumber,
                                'vehicle_id' => $checkVehicle->id
                            ]);
                                
                            $checkVehicle->account_no = $account->account_no;
                            $checkVehicle->updated_by = $model->id;
                            $checkVehicle->card_number = $cardNumber;
                            $checkVehicle->card_number_status = 1;
                            $checkVehicle->rfid_tag_status = 0;
                            $checkVehicle->updated_at = now();

                            if ($checkVehicle->save()) {
                                Log::info('Vehicle linked successfully to new account', [
                                    'vehicle_id' => $checkVehicle->id,
                                    'account_no' => $account->account_no,
                                    'card_number' => $cardNumber
                                ]);
                                
                                DB::commit();

                                // Prepare response data with vehicle and bundle info
                                $responseData = [
                                    'vehicle' => [
                                        'id' => $checkVehicle->id,
                                        'plate_no' => $checkVehicle->plate_no,
                                        'body_type' => [
                                            'id' => $checkBody->id,
                                            'name' => $checkBody->name,
                                            'description' => $checkBody->description,
                                        ],
                                        'account_no' => $account->account_no,
                                        'image' => $checkVehicle->image,
                                        'created_at' => $checkVehicle->created_at,
                                        'updated_at' => $checkVehicle->updated_at,
                                    ],
                                    'owner' => [
                                        'account_no' => $account->account_no,
                                        'first_name' => $model->first_name,
                                        'surname' => $model->surname,
                                        'full_name' => $model->first_name . ' ' . $model->surname,
                                        'phone' => $model->phone,
                                        'email' => $model->email,
                                        'created_at' => $model->created_at,
                                        'updated_at' => $model->updated_at,
                                    ],
                                    'bundles' => [
                                        [
                                            'bundle_id' => 1,
                                            'bundle_name' => 'Daily Bundle',
                                            'bundle_description' => 'Daily Bundle Subscription',
                                            'amount' => $bundlePrice->daily_bundle_amount,
                                            'currency' => 'TZS'
                                        ],
                                        [
                                            'bundle_id' => 2,
                                            'bundle_name' => 'Weekly Bundle',
                                            'bundle_description' => 'Weekly Bundle Subscription',
                                            'amount' => $bundlePrice->weekly_bundle_amount,
                                            'currency' => 'TZS'
                                        ],
                                        [
                                            'bundle_id' => 3,
                                            'bundle_name' => 'Monthly Bundle',
                                            'bundle_description' => 'Monthly Bundle Subscription',
                                            'amount' => $bundlePrice->monthly_bundle_amount,
                                            'currency' => 'TZS'
                                        ]
                                    ],
                                    'price_list' => [
                                        'id' => $bundlePrice->id,
                                        'body_type_id' => $bundlePrice->body_type_id,
                                        'regular_amount' => $bundlePrice->amount,
                                        'daily_bundle_amount' => $bundlePrice->daily_bundle_amount,
                                        'weekly_bundle_amount' => $bundlePrice->weekly_bundle_amount,
                                        'monthly_bundle_amount' => $bundlePrice->monthly_bundle_amount,
                                        'status' => $bundlePrice->status,
                                    ]
                                ];

                                return $this->sendResponse($responseData, 'Vehicle successfully associated with a new account');
                            } else {
                                Log::error('Failed to save vehicle association', [
                                    'vehicle_id' => $checkVehicle->id,
                                    'account_no' => $account->account_no,
                                    'errors' => $checkVehicle->getErrors() ?? 'Unknown error'
                                ]);
                                DB::rollBack();
                                return $this->sendError('Failed to associate vehicle with an account');
                            }
                        } else {
                            // Just create the account without vehicle association
                            DB::commit();

                            $responseData = [
                                'owner' => [
                                    'account_no' => $account->account_no,
                                    'first_name' => $model->first_name,
                                    'surname' => $model->surname,
                                    'full_name' => $model->first_name . ' ' . $model->surname,
                                    'phone' => $model->phone,
                                    'email' => $model->email,
                                    'created_at' => $model->created_at,
                                    'updated_at' => $model->updated_at,
                                ],
                                'message' => 'Account created successfully. Vehicle can be associated later.'
                            ];

                            return $this->sendResponse($responseData, 'Account created successfully');
                        }

                    } else {
                        Log::error('Failed to save SMS log', [
                            'account_id' => $model->id,
                            'sms_recipient' => $smsRecipient
                        ]);
                        DB::rollBack();
                        return $this->sendError('Failed to send sms', [
                            'user' => $model
                        ]);
                    }
                } else {
                    Log::error('Failed to save new account', [
                        'phone' => $mobilePhone,
                        'email' => $userDetails['email'],
                        'errors' => $model->getErrors() ?? 'Unknown error'
                    ]);
                    DB::rollBack();
                    return $this->sendError('Failed to Register New Account');
                }
            } catch (\Exception $e) {
                Log::error('Exception occurred during account creation', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->all()
                ]);
                DB::rollBack();
                return $this->sendError('Error occurred during account registration', $e->getMessage());
            }
        } else {
            // Account already exists
            if ($checkVehicle && $bundleId) {
                Log::info('Linking vehicle to existing account', [
                    'phone' => $mobilePhone,
                    'plate_no' => $plateNumber,
                    'existing_account_id' => $checkAccount->id
                ]);
                
                // Link vehicle with existing account
                try {
                    DB::beginTransaction();

                    if (in_array($checkVehicle->body_type_id, [178, 183, 184, 185, 186, 189, 190, 191, 192, 193, 194])) {
                        Log::error('Body type not allowed for service (existing account)', [
                            'body_type_id' => $checkVehicle->body_type_id,
                            'vehicle_id' => $checkVehicle->id
                        ]);
                        return $this->sendError('Body type not allowed to this service');
                    }

                    // Check if vehicle is already associated with an account
                    if ($checkVehicle->account_no != null) {
                        // Check if the vehicle is already associated with the same account
                        if ($checkVehicle->account_no === $checkAccount->account_no) {
                            Log::info('Vehicle already associated with the same account', [
                                'vehicle_id' => $checkVehicle->id,
                                'account_no' => $checkVehicle->account_no,
                                'phone' => $mobilePhone
                            ]);
                            
                            // Return success response since vehicle is already linked to this account
                            $responseData = [
                                'vehicle' => [
                                    'id' => $checkVehicle->id,
                                    'plate_no' => $checkVehicle->plate_no,
                                    'body_type' => [
                                        'id' => $checkBody->id,
                                        'name' => $checkBody->name,
                                        'description' => $checkBody->description,
                                    ],
                                    'account_no' => $checkAccount->account_no,
                                    'image' => $checkVehicle->image,
                                    'created_at' => $checkVehicle->created_at,
                                    'updated_at' => $checkVehicle->updated_at,
                                ],
                                'owner' => [
                                    'account_no' => $checkAccount->account_no,
                                    'first_name' => $checkAccount->first_name,
                                    'surname' => $checkAccount->surname,
                                    'full_name' => $checkAccount->first_name . ' ' . $checkAccount->surname,
                                    'phone' => $checkAccount->phone,
                                    'email' => $checkAccount->email,
                                    'created_at' => $checkAccount->created_at,
                                    'updated_at' => $checkAccount->updated_at,
                                ],
                                'bundles' => [
                                    [
                                        'bundle_id' => 1,
                                        'bundle_name' => 'Daily Bundle',
                                        'bundle_description' => 'Daily Bundle Subscription',
                                        'amount' => $bundlePrice->daily_bundle_amount,
                                        'currency' => 'TZS'
                                    ],
                                    [
                                        'bundle_id' => 2,
                                        'bundle_name' => 'Weekly Bundle',
                                        'bundle_description' => 'Weekly Bundle Subscription',
                                        'amount' => $bundlePrice->weekly_bundle_amount,
                                        'currency' => 'TZS'
                                    ],
                                    [
                                        'bundle_id' => 3,
                                        'bundle_name' => 'Monthly Bundle',
                                        'bundle_description' => 'Monthly Bundle Subscription',
                                        'amount' => $bundlePrice->monthly_bundle_amount,
                                        'currency' => 'TZS'
                                    ]
                                ],
                                'price_list' => [
                                    'id' => $bundlePrice->id,
                                    'body_type_id' => $bundlePrice->body_type_id,
                                    'regular_amount' => $bundlePrice->amount,
                                    'daily_bundle_amount' => $bundlePrice->daily_bundle_amount,
                                    'weekly_bundle_amount' => $bundlePrice->weekly_bundle_amount,
                                    'monthly_bundle_amount' => $bundlePrice->monthly_bundle_amount,
                                    'status' => $bundlePrice->status,
                                ]
                            ];

                            return $this->sendResponse($responseData, 'Vehicle is already associated with this account');
                        } else {
                            // Vehicle is associated with a different account
                            Log::error('Vehicle already associated with different account', [
                                'vehicle_id' => $checkVehicle->id,
                                'existing_account_no' => $checkVehicle->account_no,
                                'requested_account_no' => $checkAccount->account_no,
                                'phone' => $mobilePhone
                            ]);
                            return $this->sendError('Vehicle is already associated with a different account', [
                                'account_number' => $checkVehicle->account_no
                            ]);
                        }
                    }

                    // Generate unique card number
                    $cardNumber = $this->generateUniqueCardNumber();
                    if (!$cardNumber) {
                        Log::error('Failed to generate unique card number (existing account)', [
                            'vehicle_id' => $checkVehicle->id,
                            'account_id' => $checkAccount->id
                        ]);
                        DB::rollBack();
                        return $this->sendError('Failed to generate unique card number');
                    }
                    
                    Log::info('Card number generated successfully (existing account)', [
                        'card_number' => $cardNumber,
                        'vehicle_id' => $checkVehicle->id
                    ]);
                        
                    $checkVehicle->account_no = $checkAccount->account_no;
                    $checkVehicle->updated_by = $checkAccount->id;
                    $checkVehicle->card_number = $cardNumber;
                    $checkVehicle->card_number_status = 1;
                    $checkVehicle->rfid_tag_status = 0;
                    $checkVehicle->updated_at = now();

                    if ($checkVehicle->save()) {
                        Log::info('Vehicle linked successfully to existing account', [
                            'vehicle_id' => $checkVehicle->id,
                            'account_no' => $checkAccount->account_no,
                            'card_number' => $cardNumber
                        ]);
                        
                        DB::commit();

                        // Prepare response data similar to getVehicleBundleInfo
                        $responseData = [
                            'vehicle' => [
                                'id' => $checkVehicle->id,
                                'plate_no' => $checkVehicle->plate_no,
                                'body_type' => [
                                    'id' => $checkBody->id,
                                    'name' => $checkBody->name,
                                    'description' => $checkBody->description,
                                ],
                                'account_no' => $checkAccount->account_no,
                                'image' => $checkVehicle->image,
                                'created_at' => $checkVehicle->created_at,
                                'updated_at' => $checkVehicle->updated_at,
                            ],
                            'owner' => [
                                'account_no' => $checkAccount->account_no,
                                'first_name' => $checkAccount->first_name,
                                'surname' => $checkAccount->surname,
                                'full_name' => $checkAccount->first_name . ' ' . $checkAccount->surname,
                                'phone' => $checkAccount->phone,
                                'email' => $checkAccount->email,
                                'created_at' => $checkAccount->created_at,
                                'updated_at' => $checkAccount->updated_at,
                            ],
                            'bundles' => [
                                [
                                    'bundle_id' => 1,
                                    'bundle_name' => 'Daily Bundle',
                                    'bundle_description' => 'Daily Bundle Subscription',
                                    'amount' => $bundlePrice->daily_bundle_amount,
                                    'currency' => 'TZS'
                                ],
                                [
                                    'bundle_id' => 2,
                                    'bundle_name' => 'Weekly Bundle',
                                    'bundle_description' => 'Weekly Bundle Subscription',
                                    'amount' => $bundlePrice->weekly_bundle_amount,
                                    'currency' => 'TZS'
                                ],
                                [
                                    'bundle_id' => 3,
                                    'bundle_name' => 'Monthly Bundle',
                                    'bundle_description' => 'Monthly Bundle Subscription',
                                    'amount' => $bundlePrice->monthly_bundle_amount,
                                    'currency' => 'TZS'
                                ]
                            ],
                            'price_list' => [
                                'id' => $bundlePrice->id,
                                'body_type_id' => $bundlePrice->body_type_id,
                                'regular_amount' => $bundlePrice->amount,
                                'daily_bundle_amount' => $bundlePrice->daily_bundle_amount,
                                'weekly_bundle_amount' => $bundlePrice->weekly_bundle_amount,
                                'monthly_bundle_amount' => $bundlePrice->monthly_bundle_amount,
                                'status' => $bundlePrice->status,
                            ]
                        ];

                        return $this->sendResponse($responseData, 'Vehicle successfully associated with an existing Account');
                    } else {
                        Log::error('Failed to save vehicle association (existing account)', [
                            'vehicle_id' => $checkVehicle->id,
                            'account_no' => $checkAccount->account_no,
                            'errors' => $checkVehicle->getErrors() ?? 'Unknown error'
                        ]);
                        DB::rollBack();
                        return $this->sendError('Failed to associate vehicle with an account');
                    }

                } catch (\Exception $e) {
                    Log::error('Exception occurred during vehicle linking', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'request_data' => $request->all()
                    ]);
                    DB::rollBack();
                    return $this->sendError('Error occurred during account registration', $e->getMessage());
                }
            } else {
                // Account exists but no vehicle/bundle provided
                Log::info('Account already exists', [
                    'phone' => $mobilePhone,
                    'account_id' => $checkAccount->id
                ]);
                
                $responseData = [
                    'owner' => [
                        'account_no' => $checkAccount->account_no,
                        'first_name' => $checkAccount->first_name,
                        'surname' => $checkAccount->surname,
                        'full_name' => $checkAccount->first_name . ' ' . $checkAccount->surname,
                        'phone' => $checkAccount->phone,
                        'email' => $checkAccount->email,
                        'created_at' => $checkAccount->created_at,
                        'updated_at' => $checkAccount->updated_at,
                    ],
                    'message' => 'Account already exists. Vehicle can be associated later.'
                ];

                return $this->sendResponse($responseData, 'Account already exists');
            }
        }
    }

    /**
     * Search account details by account number or phone
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchAccountDetails(Request $request): JsonResponse
    {
        Log::info('Account search request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'account_no' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in account search', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        // Check that either account_no or phone is provided
        if (empty($request->account_no) && empty($request->phone)) {
            return $this->sendError('Either account number or phone number is required');
        }

        try {
            // Get account details using either account number or phone
            $accountQuery = DB::table('account');
            
            if (!empty($request->account_no)) {
                $accountQuery->where('account_no', $request->account_no);
            } else {
                $accountQuery->where('phone', $request->phone);
            }
            
            $account = $accountQuery->select([
                'id',
                'account_no',
                'nida',
                'control_no',
                'account_balance',
                'amount_received',
                'first_name',
                'middle_name',
                'surname',
                'phone',
                'email',
                'otp_cdate',
                'one_time_password',
                'last_login',
                'status',
                'created_by',
                'created_at',
                'updated_at',
                'updated_by',
                'otp_status'
            ])->first();

            if (!$account) {
                $searchField = !empty($request->account_no) ? 'account number' : 'phone number';
                $searchValue = !empty($request->account_no) ? $request->account_no : $request->phone;
                
                Log::warning('Account not found', [
                    'search_field' => $searchField,
                    'search_value' => $searchValue
                ]);
                return $this->sendError('Account not found', [
                    'search_field' => $searchField,
                    'search_value' => $searchValue,
                    'message' => "No account found with the provided {$searchField}"
                ]);
            }

            // Get the actual account number for further processing
            $accountNo = $account->account_no;

            Log::info('Account found', [
                'account_no' => $accountNo,
                'account_id' => $account->id
            ]);

            // Get associated vehicles with basic info
            $vehicles = DB::table('vehicle')
                ->leftJoin('body_type', 'body_type.id', '=', 'vehicle.body_type_id')
                ->where('vehicle.account_no', $accountNo)
                ->select([
                    'vehicle.id',
                    'vehicle.plate_no',
                    'vehicle.status',
                    'vehicle.exempted',
                    'body_type.name as body_type_name'
                ])
                ->get();

            // Get active bundle subscriptions
            $activeBundles = DB::table('bundle_subscriptions as bs')
                ->join('vehicle as v', 'bs.vehicle_id', '=', 'v.id')
                ->join('toll_bundles as tb', 'bs.bundle_id', '=', 'tb.id')
                ->where('bs.account_id', $accountNo)
                ->where('bs.status', 1) // Active bundles only
                ->where('bs.expire_date', '>', now()) // Not expired
                ->select([
                    'bs.id',
                    'bs.vehicle_id',
                    'bs.bundle_id',
                    'bs.start_date',
                    'bs.expire_date',
                    'v.plate_no',
                    'tb.bundle_description',
                    'tb.duration'
                ])
                ->get();

            // Prepare simple response
            $response = [
                'account_no' => $account->account_no,
                'account_name' => $account->first_name . ' ' . $account->surname,
                'account_balance' => $account->account_balance,
                'phone' => $account->phone,
                'email' => $account->email,
                'status' => $account->status,
                'vehicles' => $vehicles->map(function ($vehicle) {
                    return [
                        'id' => $vehicle->id,
                        'plate_no' => $vehicle->plate_no,
                        'body_type' => $vehicle->body_type_name,
                        'status' => $vehicle->status,
                        'exempted' => $vehicle->exempted
                    ];
                }),
                'active_bundles' => $activeBundles->map(function ($bundle) {
                    return [
                        'id' => $bundle->id,
                        'plate_no' => $bundle->plate_no,
                        'bundle_description' => $bundle->bundle_description,
                        'start_date' => $bundle->start_date,
                        'expire_date' => $bundle->expire_date,
                        'duration' => $bundle->duration
                    ];
                })
            ];

            Log::info('Account details retrieved successfully', [
                'account_no' => $accountNo,
                'vehicles_count' => $vehicles->count(),
                'active_bundles_count' => $activeBundles->count()
            ]);

            return $this->sendResponse($response, 'Account details retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Exception during account search', [
                'account_no' => $accountNo,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Error occurred during account search', [
                'account_no' => $accountNo,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate a unique card number
     */
    private function generateUniqueCardNumber(): ?string
    {
        return app(CardNumberGeneratorService::class)->generate();
    }

    /**
     * List all accounts with pagination and filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listAccounts(Request $request): JsonResponse
    {
        try {
            $query = Account::select([
                'id',
                'account_no',
                'nida',
                'control_no',
                'account_balance',
                'amount_received',
                'first_name',
                'middle_name',
                'surname',
                'phone',
                'email',
                'status',
                'created_by',
                'created_at',
                'updated_by',
                'updated_at',
                'otp_status',
                'nfc_card',
                'card_reference'
            ]);

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = trim($request->search);
                $like = "%{$search}%";
                $query->where(function ($q) use ($search, $like) {
                    $q->where('account_no', 'like', $like)
                      ->orWhere('first_name', 'like', $like)
                      ->orWhere('middle_name', 'like', $like)
                      ->orWhere('surname', 'like', $like)
                      ->orWhere('phone', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('nida', 'like', $like)
                      // Match against the full name so multi-word queries
                      // (e.g. "John Smith") resolve across name columns.
                      ->orWhereRaw(
                          "CONCAT_WS(' ', first_name, middle_name, surname) LIKE ?",
                          [$like]
                      );
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== null) {
                $query->where('status', $request->status);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $accounts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Accounts retrieved successfully',
                'data' => [
                    'accounts' => $accounts->items(),
                    'pagination' => [
                        'current_page' => $accounts->currentPage(),
                        'last_page' => $accounts->lastPage(),
                        'per_page' => $accounts->perPage(),
                        'total' => $accounts->total(),
                        'from' => $accounts->firstItem(),
                        'to' => $accounts->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve accounts', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accounts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific account
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getAccount($id): JsonResponse
    {
        try {
            $account = Account::find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Account retrieved successfully',
                'data' => $account
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve account', [
                'account_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a one-time code to the phone before creating an account.
     */
    public function sendAccountCreationOtp(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), $this->newAccountValidationRules(false));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $phone = $request->input('phone');
            $otp = (string) mt_rand(100000, 999999);
            $cacheKey = $this->accountCreationOtpCacheKey($phone);

            Cache::put($cacheKey, $otp, now()->addMinutes(5));

            $smsBody = 'OTP: ' . $otp . "\n";
            $this->trySendAccountCreationOtpSms($phone, $smsBody);

            $email = $request->input('email');
            if ($email) {
                try {
                    Notifications::pushEmailNotification(
                        $email,
                        'BMS account verification code',
                        $smsBody,
                        'Bridge_OTP'
                    );
                } catch (\Throwable $emailException) {
                    Log::warning('Account creation OTP email failed', [
                        'email' => $email,
                        'message' => $emailException->getMessage(),
                    ]);
                }
            }

            Log::info('Account creation OTP generated', [
                'phone' => $this->maskPhone($phone),
            ]);

            $data = [
                'phone' => $this->maskPhone($phone),
                'expires_in' => 300,
            ];

            if (config('app.debug')) {
                $data['otp'] = $otp;
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to the phone number',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send account creation OTP', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createAccount(Request $request): JsonResponse
    {
        try {
            if (!$request->filled('created_by') && $request->user()) {
                $request->merge(['created_by' => $request->user()->id]);
            }

            $validator = Validator::make($request->all(), $this->newAccountValidationRules(true));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cacheKey = $this->accountCreationOtpCacheKey($request->input('phone'));
            $cachedOtp = Cache::get($cacheKey);

            if (!$cachedOtp || (string) $cachedOtp !== (string) $request->input('otp')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 422);
            }

            DB::beginTransaction();

            $account = new Account();
            $account->nida = $request->nida;
            $account->first_name = $request->first_name;
            $account->middle_name = $request->middle_name;
            $account->surname = $request->surname;
            $account->email = $request->email;
            $account->phone = $request->phone;
            $account->created_at = now();
            $account->created_by = $request->created_by;
            $account->password_hash = Hash::make('12345'); // Default password
            $account->status = 1; // Active by default

            if ($account->save()) {
                $account->refresh();

                if ($account->account_no) {
                    $this->sendAccountCreationSMS($account, $account->account_no);
                }

                Cache::forget($cacheKey);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Account created successfully',
                    'data' => [
                        'account' => $account,
                        'account_no' => $account->account_no,
                    ]
                ], 201);

            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create account'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create account', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing account
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateAccount(Request $request, $id): JsonResponse
    {
        try {
            $account = Account::find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nida' => 'nullable|string|max:255',
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'surname' => 'required|string|max:255',
                'email' => 'required|email|unique:account,email,' . $id,
                'phone' => 'required|string|max:20|unique:account,phone,' . $id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $account->nida = $request->nida;
            $account->first_name = $request->first_name;
            $account->middle_name = $request->middle_name;
            $account->surname = $request->surname;
            $account->email = $request->email;
            $account->phone = $request->phone;
            $account->updated_at = now();
            $account->updated_by = auth()->user()->id;

            if ($account->save()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account updated successfully',
                    'data' => $account
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update account'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update account', [
                'account_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate an account
     *
     * @param int $id
     * @return JsonResponse
     */
    public function activateAccount($id): JsonResponse
    {
        try {
            $account = Account::find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            $account->status = 1;
            $account->updated_at = now();

            if ($account->save()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account activated successfully',
                    'data' => $account
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to activate account'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to activate account', [
                'account_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate an account
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deactivateAccount($id): JsonResponse
    {
        try {
            $account = Account::find($id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            $account->status = 0;
            $account->updated_at = now();

            if ($account->save()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account deactivated successfully',
                    'data' => $account
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to deactivate account'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to deactivate account', [
                'account_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create top-up bill
     *
     * @param string $accountNo
     * @param int $userId
     * @param string $description
     * @param float $amount
     * @return bool
     */
    private function createTopUpBill($accountNo, $userId, $description, $amount): bool
    {
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post(config('app.api_base_url') . '/transaction/post-top-up-bill', [
                'json' => [
                    'bill_amount' => $amount,
                    'bill_desc' => $description,
                    'account_no' => $accountNo,
                    'user_id' => $userId,
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error('Failed to create top-up bill', [
                'account_no' => $accountNo,
                'amount' => $amount,
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send account creation SMS
     *
     * @param Account $account
     * @param string $accountNo
     * @return void
     */
    private function newAccountValidationRules(bool $requireOtp): array
    {
        $rules = [
            'nida' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|email|unique:account,email',
            'phone' => 'required|string|max:20|unique:account,phone',
        ];

        if ($requireOtp) {
            $rules['created_by'] = 'required|integer';
            $rules['otp'] = 'required|digits:6';
        }

        return $rules;
    }

    private function normalizePhoneNumber(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone));

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) >= 12 && str_starts_with($digits, '255')) {
            $rest = substr($digits, 3);
            $subscriber = strlen($rest) > 9 ? ltrim($rest, '0') : $rest;
            $subscriber = substr(preg_replace('/\D/', '', $subscriber), -9);

            return '255' . str_pad($subscriber ?: '0', 9, '0', STR_PAD_LEFT);
        }

        if (strlen($digits) === 9) {
            return '255' . $digits;
        }

        if (strlen($digits) >= 10) {
            $subscriber = ltrim(substr($digits, -10), '0');

            return '255' . str_pad($subscriber ?: '0', 9, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    private function accountCreationOtpCacheKey(?string $phone): string
    {
        return 'account_create_otp:' . $this->normalizePhoneNumber($phone);
    }

    private function maskPhone(?string $phone): string
    {
        $normalized = $this->normalizePhoneNumber($phone);
        $length = strlen($normalized);

        if ($length < 6) {
            return $normalized;
        }

        return substr($normalized, 0, 3) . str_repeat('*', $length - 6) . substr($normalized, -3);
    }

    private function trySendAccountCreationOtpSms(?string $phone, string $smsBody): void
    {
        $recipient = $this->normalizePhoneNumber($phone);
        if ($recipient === '') {
            Log::warning('Account creation OTP SMS skipped: empty phone');
            return;
        }

        $smsSource = config('app.sms_source');
        if (!$smsSource || strcasecmp(trim((string) $smsSource), 'WRONG FORM') === 0) {
            $smsSource = 'BMS';
        }

        try {
            $logSms = new IdsMessages();
            $logSms->sms_body = $smsBody;
            $logSms->sms_recipient = $recipient;
            $logSms->sms_source = $smsSource;
            $logSms->sms_process = 'Bridge_OTP';
            $logSms->status = 0;
            $logSms->created_at = now();
            $logSms->save();
        } catch (\Throwable $queueException) {
            Log::warning('Failed to queue account creation OTP SMS', [
                'phone' => $this->maskPhone($recipient),
                'message' => $queueException->getMessage(),
            ]);
        }

        try {
            Notifications::pushSmsNotification($recipient, $smsBody, 'Bridge_OTP', 5);
        } catch (\Throwable $smsException) {
            Log::warning('Account creation OTP SMS failed', [
                'phone' => $this->maskPhone($recipient),
                'message' => $smsException->getMessage(),
            ]);
        }
    }

    private function sendAccountCreationSMS($account, $accountNo): void
    {
        try {
            $smsBody = 'Usajili wa Malipo ya Tozo ya kupita Darajani' . "\n" .
                'Akaunti: ' . $accountNo . "\n" .
                'Jina: ' . $account->first_name . ' ' . $account->surname . "\n" .
                'Nywila/Neno la Siri: 12345' . "\n" .
                'Kwa Huduma zaidi Tembela Tovuti' . "\n" . 'https://bridge-portal.nssf.go.tz';

            $logSms = new IdsMessages();
            $logSms->sms_body = $smsBody;
            $logSms->sms_recipient = $account->phone;
            $logSms->sms_source = config('app.sms_source');
            $logSms->sms_process = 'Prepayment Account Creation';
            $logSms->created_at = now();
            $logSms->save();

            Log::info('Account creation SMS logged', [
                'account_no' => $accountNo,
                'phone' => $account->phone
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log account creation SMS', [
                'account_no' => $accountNo,
                'phone' => $account->phone,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Link a card reference to an account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function linkCardReference(Request $request): JsonResponse
    {
        Log::info('Card reference link request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'account_id' => 'required|integer|exists:account,id',
            'card_reference' => 'required|string|max:100',
            'reason' => 'nullable|string|max:255',
            'performed_by' => 'required|integer'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in card reference link', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $accountId = $request->account_id;
            $cardReference = $request->card_reference;
            $reason = $request->reason ?? 'Card linked to account';
            $performedBy = $request->performed_by;

            // Check if account exists
            $account = Account::find($accountId);
            if (!$account) {
                return $this->sendError('Account not found', [
                    'account_id' => $accountId
                ]);
            }

            // Check if card reference is already linked to another account
            $existingAccount = Account::where('card_reference', $cardReference)
                ->where('id', '!=', $accountId)
                ->first();

            if ($existingAccount) {
                return $this->sendError('Card reference already linked', [
                    'card_reference' => $cardReference,
                    'linked_to_account' => $existingAccount->account_no,
                    'linked_to_name' => $existingAccount->full_name
                ]);
            }

            // Store old card reference for history
            $oldCardReference = $account->card_reference;

            // Update account with new card reference
            $account->card_reference = $cardReference;
            $account->updated_by = $performedBy;
            $account->save();

            // Create history record
            $historyData = [
                'account_id' => $accountId,
                'card_reference' => $cardReference,
                'action' => $oldCardReference ? AccountCardHistory::ACTION_UPDATED : AccountCardHistory::ACTION_LINKED,
                'old_card_reference' => $oldCardReference,
                'new_card_reference' => $cardReference,
                'reason' => $reason,
                'performed_by' => $performedBy
            ];

            AccountCardHistory::create($historyData);

            DB::commit();

            Log::info('Card reference linked successfully', [
                'account_id' => $accountId,
                'account_no' => $account->account_no,
                'card_reference' => $cardReference,
                'performed_by' => $performedBy
            ]);

            return $this->sendResponse([
                'account' => $account->fresh(),
                'history' => $historyData
            ], 'Card reference linked successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to link card reference', [
                'account_id' => $request->account_id,
                'card_reference' => $request->card_reference,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to link card reference: ' . $e->getMessage());
        }
    }

    /**
     * Unlink a card reference from an account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unlinkCardReference(Request $request): JsonResponse
    {
        Log::info('Card reference unlink request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'account_id' => 'required|integer|exists:account,id',
            'reason' => 'nullable|string|max:255',
            'performed_by' => 'required|integer'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in card reference unlink', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $accountId = $request->account_id;
            $reason = $request->reason ?? 'Card unlinked from account';
            $performedBy = $request->performed_by;

            // Check if account exists
            $account = Account::find($accountId);
            if (!$account) {
                return $this->sendError('Account not found', [
                    'account_id' => $accountId
                ]);
            }

            if (!$account->card_reference) {
                return $this->sendError('No card reference linked to this account', [
                    'account_id' => $accountId,
                    'account_no' => $account->account_no
                ]);
            }

            // Store old card reference for history
            $oldCardReference = $account->card_reference;

            // Remove card reference from account
            $account->card_reference = null;
            $account->updated_by = $performedBy;
            $account->save();

            // Create history record
            $historyData = [
                'account_id' => $accountId,
                'card_reference' => $oldCardReference,
                'action' => AccountCardHistory::ACTION_UNLINKED,
                'old_card_reference' => $oldCardReference,
                'new_card_reference' => null,
                'reason' => $reason,
                'performed_by' => $performedBy
            ];

            AccountCardHistory::create($historyData);

            DB::commit();

            Log::info('Card reference unlinked successfully', [
                'account_id' => $accountId,
                'account_no' => $account->account_no,
                'old_card_reference' => $oldCardReference,
                'performed_by' => $performedBy
            ]);

            return $this->sendResponse([
                'account' => $account->fresh(),
                'history' => $historyData
            ], 'Card reference unlinked successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unlink card reference', [
                'account_id' => $request->account_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to unlink card reference: ' . $e->getMessage());
        }
    }

    /**
     * Get card reference history for an account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCardHistory($id, Request $request): JsonResponse
    {
        Log::info('Card history request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in card history request', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            $accountId = $id;
            $perPage = $request->per_page ?? 15;

            // Use the same approach as getAccountHistoryStats - find account by ID
            $account = Account::find($accountId);
            if (!$account) {
                return $this->sendError('Account not found', [
                    'account_id' => $accountId
                ]);
            }

            // Get card history with relationships
            $history = AccountCardHistory::with(['account:id,account_no,first_name,surname', 'performedBy:id,username'])
                ->forAccount($accountId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            Log::info('Card history retrieved successfully', [
                'account_id' => $accountId,
                'account_no' => $account->account_no,
                'history_count' => $history->total()
            ]);

            return $this->sendResponse([
                'account' => [
                    'id' => $account->id,
                    'account_no' => $account->account_no,
                    'full_name' => $account->full_name,
                    'current_card_reference' => $account->card_reference
                ],
                'history' => $history->items(),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ]
            ], 'Card history retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve card history', [
                'account_id' => $request->account_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve card history: ' . $e->getMessage());
        }
    }

    /**
     * Search account by card reference
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchByCardReference(Request $request): JsonResponse
    {
        Log::info('Account search by card reference request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'card_reference' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in card reference search', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            $cardReference = $request->card_reference;

            // Search for account with this card reference
            $account = Account::where('card_reference', $cardReference)->first();

            if (!$account) {
                Log::warning('No account found for card reference', [
                    'card_reference' => $cardReference
                ]);
                return $this->sendError('No account found for this card reference', [
                    'card_reference' => $cardReference
                ]);
            }

            // Get latest card history
            $latestHistory = AccountCardHistory::forAccount($account->id)
                ->with('performedBy:id,username')
                ->latest()
                ->first();

            Log::info('Account found by card reference', [
                'card_reference' => $cardReference,
                'account_id' => $account->id,
                'account_no' => $account->account_no
            ]);

            return $this->sendResponse([
                'account' => [
                    'id' => $account->id,
                    'account_no' => $account->account_no,
                    'nida' => $account->nida,
                    'full_name' => $account->full_name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'account_balance' => $account->account_balance,
                    'card_reference' => $account->card_reference,
                    'status' => $account->status,
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at
                ],
                'latest_card_history' => $latestHistory
            ], 'Account found successfully');

        } catch (\Exception $e) {
            Log::error('Failed to search account by card reference', [
                'card_reference' => $request->card_reference,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to search account: ' . $e->getMessage());
        }
    }

    /**
     * Process balance deduction from POS terminal
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processBalanceDeduction(Request $request): JsonResponse
    {
        $api = 'POST /api/pos/balance-deduction';
        $flowLogger = app(PosPaymentFlowLogger::class);
        $flowId = $flowLogger->start($request, $api);

        $validator = Validator::make($request->all(), [
            'card_reference' => 'required_without:account_no|string|max:100',
            'account_no' => 'required_without:card_reference|string|max:30',
            'deduction_amount' => 'required|numeric|min:0.01',
            'lane_number' => 'required|string|max:20',
            'mac_address' => 'required|string|max:17',
            'terminal_id' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $flowLogger->respond(
                $request,
                $api,
                $flowId,
                $this->sendError('Validation failed', $validator->errors()->toArray()),
                '2. Validation failed'
            );
        }

        try {
            DB::beginTransaction();

            $deductionAmount = (float) $request->deduction_amount;
            $laneNumber = $request->lane_number;
            $macAddress = strtoupper($request->mac_address);
            $terminalId = $request->terminal_id ?? 'POS_' . $laneNumber;
            // Generate reference number based on payment type
            $refPrefix = $request->has('account_no') ? 'QR' : 'CARD';
            $referenceNumber = $request->reference_number ?? $refPrefix . '_' . time() . '_' . $laneNumber;
            // Determine payment type for description
            $paymentType = $request->has('account_no') ? 'QR Code Payment' : 'Card Payment';
            $description = $request->description ?? $paymentType . ' - Lane ' . $laneNumber;

            // Validate POS terminal
            $posTerminal = PosTerminal::where('mac_address', $macAddress)
                ->where('lane_number', $laneNumber)
                ->first();

            if (!$posTerminal) {
                return $flowLogger->respond(
                    $request,
                    $api,
                    $flowId,
                    $this->sendError('POS terminal not registered', [
                        'mac_address' => $macAddress,
                        'lane_number' => $laneNumber,
                    ]),
                    '3. POS terminal not registered'
                );
            }

            // Check if terminal is active
            if ($posTerminal->status !== PosTerminal::STATUS_ACTIVE) {
                return $flowLogger->respond(
                    $request,
                    $api,
                    $flowId,
                    $this->sendError('POS terminal is not active', [
                        'terminal_name' => $posTerminal->name,
                        'status' => $posTerminal->status,
                    ]),
                    '4. POS terminal inactive',
                    ['terminal_id' => $posTerminal->id]
                );
            }

            // Update terminal heartbeat
            $posTerminal->updateHeartbeat();

            $flowLogger->step($flowId, $api, '5. POS terminal validated', [
                'terminal_id' => $posTerminal->id,
                'terminal_name' => $posTerminal->name,
                'mac_address' => $macAddress,
                'lane_number' => $laneNumber,
            ]);

            // Find account by card reference or account number
            $account = null;
            $lookupMethod = '';
            
            if ($request->has('card_reference') && !empty($request->card_reference)) {
                $account = Account::where('nfc_card', $request->card_reference)->first();
                $lookupMethod = 'card_reference';
                $lookupValue = $request->card_reference;
            } elseif ($request->has('account_no') && !empty($request->account_no)) {
                $account = Account::where('account_no', $request->account_no)->first();
                $lookupMethod = 'account_no';
                $lookupValue = $request->account_no;
            }

            if (!$account) {
                $flowLogger->logDeductionFailed($flowId, $api, 'account_not_found', [
                    'lookup_method' => $lookupMethod,
                    'lookup_value' => $lookupValue ?? 'N/A',
                    'lane_number' => $laneNumber,
                    'deduction_amount' => $deductionAmount,
                ]);

                return $flowLogger->respond(
                    $request,
                    $api,
                    $flowId,
                    $this->sendError('Account not found', [
                        'lookup_method' => $lookupMethod,
                        'lookup_value' => $lookupValue ?? 'N/A',
                        'message' => "No account found for this {$lookupMethod}",
                    ]),
                    '6. Account not found',
                    ['lookup_method' => $lookupMethod, 'lookup_value' => $lookupValue ?? 'N/A']
                );
            }

            $flowLogger->step($flowId, $api, '7. Account found', [
                'account_no' => $account->account_no,
                'lookup_method' => $lookupMethod,
                'lookup_value' => $lookupValue ?? 'N/A',
                'current_balance' => (float) $account->account_balance,
            ]);

            // Check if account is active
            if ($account->status != '1') {
                $flowLogger->logDeductionFailed($flowId, $api, 'account_inactive', [
                    'account_no' => $account->account_no,
                    'account_status' => $account->status,
                    'lane_number' => $laneNumber,
                    'deduction_amount' => $deductionAmount,
                ]);

                return $flowLogger->respond(
                    $request,
                    $api,
                    $flowId,
                    $this->sendError('Account is not active', [
                        'account_no' => $account->account_no,
                        'status' => $account->status,
                    ]),
                    '8. Account inactive'
                );
            }

            // Check if account has sufficient balance
            $currentBalance = (float) $account->account_balance;
            if ($currentBalance < $deductionAmount) {
                $flowLogger->logDeductionFailed($flowId, $api, 'insufficient_balance', [
                    'account_no' => $account->account_no,
                    'customer_name' => $account->full_name,
                    'current_balance' => $currentBalance,
                    'deduction_amount' => $deductionAmount,
                    'shortfall' => $deductionAmount - $currentBalance,
                    'lane_number' => $laneNumber,
                    'payment_method' => $request->has('account_no') ? 'qr_payment' : 'card_deduction',
                ]);

                return $flowLogger->respond(
                    $request,
                    $api,
                    $flowId,
                    $this->sendError('Insufficient balance', [
                        'account_no' => $account->account_no,
                        'current_balance' => $currentBalance,
                        'deduction_amount' => $deductionAmount,
                        'shortfall' => $deductionAmount - $currentBalance,
                    ]),
                    '9. Insufficient balance'
                );
            }

            // Calculate new balance
            $newBalance = $currentBalance - $deductionAmount;

            // Update account balance
            $account->account_balance = $newBalance;
            $account->updated_at = now();
            $account->save();

            // Create toll transaction record first
            $receiptNumber = $this->generateReceiptNumber();
            $tollTransaction = new TollTransaction();
            $tollTransaction->receipt_num = $receiptNumber;
            $tollTransaction->vehicle_id = null; // POS transactions don't have vehicle_id
            $tollTransaction->lane_id = $laneNumber;
            $tollTransaction->exemption = 0;
            $tollTransaction->plate_no = 'MCV'; // POS transactions don't have plate_no
            $tollTransaction->account_no = $account->account_no;
            $tollTransaction->charged_amount = $deductionAmount;
            $tollTransaction->trans_type = 'CASHLESS';
            $tollTransaction->created_at = now()->toDateTimeString();
            $tollTransaction->created_by = null; // POS terminal transaction
            $tollTransaction->body_type_id = null; // POS transactions don't have body_type_id
            $tollTransaction->status = 1; // Active transaction
            $tollTransaction->save();

            // Create balance history record with link to toll transaction
            $historyData = [
                'account_id' => $account->id,
                'transaction_id' => $tollTransaction->id, // Link to toll transaction
                'card_reference' => $account->card_reference, // Use account's card reference
                'transaction_type' => AccountBalanceHistory::TYPE_DEDUCTION,
                'previous_balance' => $currentBalance,
                'transaction_amount' => $deductionAmount,
                'new_balance' => $newBalance,
                'lane_number' => $laneNumber,
                'terminal_id' => $posTerminal->id,
                'reference_number' => $referenceNumber,
                'description' => $description,
                'metadata' => [
                    'pos_terminal' => [
                        'id' => $posTerminal->id,
                        'name' => $posTerminal->name,
                        'mac_address' => $posTerminal->mac_address,
                        'ip_address' => $posTerminal->ip_address
                    ],
                    'pos_request' => $request->all(),
                    'lookup_method' => $lookupMethod,
                    'lookup_value' => $lookupValue,
                    'timestamp' => now()->toISOString(),
                    'ip_address' => $request->ip(),
                    'toll_transaction_id' => $tollTransaction->id,
                    'receipt_number' => $receiptNumber
                ],
                'processed_by' => null // POS terminal transaction
            ];

            $balanceHistory = AccountBalanceHistory::create($historyData);

            DB::commit();

            $flowLogger->logDeduction($flowId, $api, [
                'account_no' => $account->account_no,
                'customer_name' => $account->full_name,
                'payment_method' => $request->has('account_no') ? 'qr_payment' : 'card_deduction',
                'lookup_method' => $lookupMethod,
                'lookup_value' => $lookupValue ?? 'N/A',
                'deduction_amount' => $deductionAmount,
                'previous_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'lane_number' => $laneNumber,
                'terminal_id' => $posTerminal->id,
                'terminal_name' => $posTerminal->name,
                'reference_number' => $referenceNumber,
                'receipt_number' => $receiptNumber,
                'toll_transaction_id' => $tollTransaction->id,
                'description' => $description,
            ]);

            $flowLogger->step($flowId, $api, '10. Payment committed', [
                'account_no' => $account->account_no,
                'deduction_amount' => $deductionAmount,
                'new_balance' => $newBalance,
                'receipt_number' => $receiptNumber,
                'toll_transaction_id' => $tollTransaction->id,
            ]);

            return $flowLogger->respond(
                $request,
                $api,
                $flowId,
                $this->sendResponse([
                'transaction' => [
                    'reference_number' => $referenceNumber,
                    'receipt_number' => $receiptNumber,
                    'toll_transaction_id' => $tollTransaction->id,
                    'balance_history_id' => $balanceHistory->id,
                    'account_no' => $account->account_no,
                    'customer_name' => $account->full_name,
                    'card_reference' => $account->card_reference,
                    'lookup_method' => $lookupMethod,
                    'lookup_value' => $lookupValue,
                    'previous_balance' => $currentBalance,
                    'deduction_amount' => $deductionAmount,
                    'new_balance' => $newBalance,
                    'lane_number' => $laneNumber,
                    'terminal_id' => $terminalId,
                    'transaction_time' => now()->toISOString()
                ],
                'account' => [
                    'id' => $account->id,
                    'account_no' => $account->account_no,
                    'full_name' => $account->full_name,
                    'current_balance' => $newBalance,
                    'status' => $account->status,
                    'card_reference' => $account->card_reference
                ]
            ], 'Balance deduction processed successfully'),
                '11. Payment successful'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            $flowLogger->exception($flowId, $api, $request, $e);

            return $flowLogger->respond(
                $request,
                $api,
                $flowId,
                $this->sendError('Failed to process balance deduction: ' . $e->getMessage()),
                'Exception during payment'
            );
        }
    }

    /**
     * Generate receipt number for transactions
     *
     * @return string
     */
    private function generateReceiptNumber(): string
    {
        $receipt = Receipt::create(['prefix' => 'B']);
        $receiptNumber = 'B' . strval($receipt->number);
        
        // Update the receipt_num field with the complete receipt number
        $receipt->receipt_num = $receiptNumber;
        $receipt->save();
        
        return $receiptNumber;
    }

    /**
     * Get balance history for an account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBalanceHistory($id, Request $request): JsonResponse
    {
        Log::info('Balance history request received', [
            'request_data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'transaction_type' => 'nullable|string|in:deduction,top_up,adjustment,refund',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed in balance history request', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            $accountId = $id;
            $perPage = $request->per_page ?? 15;

            Log::info('Looking for account with account_id', [
                'account_id' => $accountId
            ]);

            // Use the same approach as getAccountHistoryStats - find account by ID
            $account = Account::find($accountId);
            
            if (!$account) {
                Log::warning('Account not found by ID', [
                    'account_id' => $accountId
                ]);
                
                return $this->sendError('Account not found', [
                    'account_id' => $accountId
                ]);
            }

            // Build query with filters
            $query = AccountBalanceHistory::with([
                'account:id,account_no,first_name,surname', 
                'processedBy:id,username',
                'tollTransaction:id,receipt_num,charged_amount,trans_type,created_at'
            ])
                ->forAccount($accountId);

            // Apply transaction type filter if provided
            if ($request->transaction_type) {
                $query->transactionType($request->transaction_type);
            }

            // Apply date range filter if provided
            if ($request->start_date && $request->end_date) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            $history = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Calculate summary statistics
            $summary = [
                'total_transactions' => $history->total(),
                'total_deductions' => AccountBalanceHistory::forAccount($accountId)
                    ->transactionType(AccountBalanceHistory::TYPE_DEDUCTION)
                    ->sum('transaction_amount'),
                'total_top_ups' => AccountBalanceHistory::forAccount($accountId)
                    ->transactionType(AccountBalanceHistory::TYPE_TOP_UP)
                    ->sum('transaction_amount'),
                'current_balance' => $account->account_balance
            ];

            Log::info('Balance history retrieved successfully', [
                'account_id' => $accountId,
                'account_no' => $account->account_no,
                'history_count' => $history->total()
            ]);

            return $this->sendResponse([
                'account' => [
                    'id' => $account->id,
                    'account_no' => $account->account_no,
                    'full_name' => $account->full_name,
                    'current_balance' => $account->account_balance,
                    'card_reference' => $account->card_reference
                ],
                'summary' => $summary,
                'history' => $history->items(),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ]
            ], 'Balance history retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve balance history', [
                'account_id' => $request->account_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve balance history: ' . $e->getMessage());
        }
    }

    /**
     * Get account history statistics
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getAccountHistoryStats($id): JsonResponse
    {
        try {
            $account = Account::find($id);

            if (!$account) {
                return $this->sendError('Account not found', [
                    'account_id' => $id
                ]);
            }

            // Get balance history statistics
            $balanceStats = AccountBalanceHistory::forAccount($id);
            
            $stats = [
                'total_transactions' => $balanceStats->count(),
                'total_deductions' => $balanceStats->transactionType(AccountBalanceHistory::TYPE_DEDUCTION)->count(),
                'total_topups' => $balanceStats->transactionType(AccountBalanceHistory::TYPE_TOP_UP)->count(),
                'total_amount_deducted' => $balanceStats->transactionType(AccountBalanceHistory::TYPE_DEDUCTION)->sum('transaction_amount'),
                'total_amount_topped_up' => $balanceStats->transactionType(AccountBalanceHistory::TYPE_TOP_UP)->sum('transaction_amount'),
                'last_transaction' => $balanceStats->orderBy('created_at', 'desc')->with(['terminal', 'processedBy:id,username'])->first(),
                'card_changes' => AccountCardHistory::forAccount($id)->count()
            ];

            return $this->sendResponse($stats, 'Account history statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve account history statistics', [
                'account_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve account history statistics: ' . $e->getMessage());
        }
    }

    /**
     * Register a card reference to an account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerCardToAccount(Request $request): JsonResponse
    {
        Log::info('Card registration request received', [
            'request_data' => $request->all(),
            'user_id' => auth()->id(),
            'ip' => $request->ip()
        ]);

        $validator = Validator::make($request->all(), [
            'account_id' => 'required|integer|exists:account,id',
            'card_reference' => 'required|string|max:100|unique:account,card_reference',
            'reason' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            Log::error('Card registration validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $accountId = $request->account_id;
            $cardReference = $request->card_reference;
            $userId = auth()->id();

            // Get the account
            $account = Account::find($accountId);
            if (!$account) {
                return $this->sendError('Account not found');
            }

            // Check if account is active
            if ($account->status !== '1') {
                return $this->sendError('Cannot register card to inactive account');
            }

            // Store the old card reference for history
            $oldCardReference = $account->card_reference;
            
            // Get reason from request (now required)
            $reason = $request->reason;

            // Update the account with new card reference
            $account->card_reference = $cardReference;
            $account->updated_by = $userId;
            $account->save();

            // Create account card history record
            AccountCardHistory::create([
                'account_id' => $accountId,
                'card_reference' => $cardReference,
                'old_card_reference' => $oldCardReference,
                'new_card_reference' => $cardReference,
                'action' => $oldCardReference ? 'updated' : 'registered',
                'performed_by' => $userId,
                'reason' => $reason
            ]);

            DB::commit();

            Log::info('Card registration successful', [
                'account_id' => $accountId,
                'account_no' => $account->account_no,
                'card_reference' => $cardReference,
                'old_card_reference' => $oldCardReference,
                'user_id' => $userId
            ]);

            return $this->sendResponse([
                'account' => [
                    'id' => $account->id,
                    'account_no' => $account->account_no,
                    'first_name' => $account->first_name,
                    'surname' => $account->surname,
                    'card_reference' => $account->card_reference,
                    'status' => $account->status
                ],
                'action' => $oldCardReference ? 'updated' : 'registered',
                'old_card_reference' => $oldCardReference
            ], $oldCardReference 
                ? 'Card reference updated successfully' 
                : 'Card reference registered successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Card registration failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);
            
            return $this->sendError('Failed to register card: ' . $e->getMessage());
        }
    }

    /**
     * Debug method to check account lookup
     */
    public function debugAccountLookup(Request $request): JsonResponse
    {
        $accountNo = $request->get('account_no');
        
        if (!$accountNo) {
            return $this->sendError('account_no parameter is required');
        }

        try {
            // Test database connection first
            $totalAccounts = Account::count();
            
            // Try exact match
            $exactMatch = Account::where('account_no', $accountNo)->first();
            
            // Try LIKE match (same as listAccounts)
            $likeMatch = Account::where('account_no', 'like', "%{$accountNo}%")->first();
            
            // Try trimmed match
            $trimmedMatch = Account::where('account_no', trim($accountNo))->first();
            
            // Try case-insensitive match
            $caseInsensitiveMatch = Account::whereRaw('LOWER(account_no) = ?', [strtolower($accountNo)])->first();
            
            // Get some sample accounts
            $sampleAccounts = Account::select('id', 'account_no', 'first_name', 'surname')->limit(10)->get();
            
            // Test raw query
            $rawQuery = DB::select("SELECT id, account_no, first_name, surname FROM account WHERE account_no = ?", [$accountNo]);
            
            return $this->sendResponse([
                'requested_account_no' => $accountNo,
                'total_accounts_in_db' => $totalAccounts,
                'exact_match' => $exactMatch ? [
                    'id' => $exactMatch->id,
                    'account_no' => $exactMatch->account_no,
                    'name' => $exactMatch->first_name . ' ' . $exactMatch->surname
                ] : null,
                'like_match' => $likeMatch ? [
                    'id' => $likeMatch->id,
                    'account_no' => $likeMatch->account_no,
                    'name' => $likeMatch->first_name . ' ' . $likeMatch->surname
                ] : null,
                'trimmed_match' => $trimmedMatch ? [
                    'id' => $trimmedMatch->id,
                    'account_no' => $trimmedMatch->account_no,
                    'name' => $trimmedMatch->first_name . ' ' . $trimmedMatch->surname
                ] : null,
                'case_insensitive_match' => $caseInsensitiveMatch ? [
                    'id' => $caseInsensitiveMatch->id,
                    'account_no' => $caseInsensitiveMatch->account_no,
                    'name' => $caseInsensitiveMatch->first_name . ' ' . $caseInsensitiveMatch->surname
                ] : null,
                'raw_query_result' => $rawQuery,
                'sample_accounts' => $sampleAccounts->toArray()
            ], 'Account lookup debug information');

        } catch (\Exception $e) {
            return $this->sendError('Debug failed: ' . $e->getMessage());
        }
    }
}
