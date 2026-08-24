<?php

namespace App\Http\Controllers\Portal;

use App\Helpers\DBHelper;
use App\Http\Controllers\Configurations\ConfigurationController;
use App\Http\Controllers\Registration\RegistrationApprovalController;
use App\Models\Account;
use App\Models\Approvals\RegistrationApproval;
use App\Models\AuthUser;
use App\Models\BundleSubscription;
use App\Models\Portal\PortalAccountSubscription;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\AccountVehicle;

class PortalController extends ConfigurationController
{
    //
    public function commuter(Request $request): JsonResponse
    {
        $phone = $request->phone_number;
        if ($phone != null) {

            #receive phone number and validate if user is valid
            $account_details = DB::table('account')
                ->where('phone', '=', "$phone")
                ->select('phone', 'account_no', 'account_balance', 'first_name', 'middle_name', 'surname', 'phone', 'email')
                ->get();

            if ($account_details->count() > 0) {
                $response = [
                    'name' => $account_details[0]->first_name . ' ' . $account_details[0]->middle_name . ' ' . $account_details[0]->surname,
                    'phone' => $phone,
                    'accountNo' => $account_details[0]->account_no,
                    'accountBalance' => $account_details[0]->account_balance,
                ];
                return $this->sendResponse($response, 'Data successfully retrieve');
            } else {
                return $this->sendError('The phone number is not related to any account');
            }

        }
        return $this->sendError('The phone number is not related to any account');
    }

    public function commuterVehicles(Request $request): JsonResponse
    {
        $account = $request->account_no;
        if ($account != null) {
            // First, verify the account exists
            $accountExists = DB::table('account')->where('account_no', $account)->exists();
            if (!$accountExists) {
                return $this->sendError('Account no does not exist');
            }

            // Get vehicles associated with the account through both methods:
            // 1. Direct association via vehicle.account_no
            // 2. Association via account_vehicle table
            $vehicles = DB::table('vehicle')
                ->join('body_type', 'body_type.id', '=', 'vehicle.body_type_id')
                ->leftJoin('price_list as pl', 'body_type.id', '=', 'pl.body_type_id')
                ->select(
                    'vehicle.id',
                    'vehicle.plate_no','vehicle.created_at',
                    DB::raw('pl.amount as price'),
                    DB::raw("IF(vehicle.status = 1, 'VEHICLE ACTIVE', 'VEHICLE INACTIVE') as vehicle_status"),
                    'vehicle.card_number',
                    'body_type.name as body',
                    DB::raw("CASE WHEN vehicle.exempted = 1 THEN 'EXEMPTED' ELSE 'NOT EXEMPTED' END as exemptedStatus")
                )
                ->where(function ($query) use ($account) {
                    $query->where('vehicle.account_no', $account)
                          ->orWhereExists(function ($subQuery) use ($account) {
                              $subQuery->select(DB::raw(1))
                                      ->from('account_vehicle')
                                      ->whereColumn('account_vehicle.vehicle_id', 'vehicle.id')
                                      ->where('account_vehicle.account_id', $account)
                                      ->where('account_vehicle.status', AccountVehicle::STATUS_ACTIVE);
                          });
                })
                ->get();

            // Get bundle information separately to avoid duplication
            $vehicleIds = $vehicles->pluck('id')->toArray();
            $bundleInfo = [];

            if (!empty($vehicleIds)) {
                $bundleInfo = DB::table('bundle_subscriptions as bs')
                    ->join('vehicle as v', 'bs.vehicle_id', '=', 'v.id')
                    ->leftJoin('toll_bundles as b', 'bs.bundle_id', '=', 'b.id')
                    ->select(
                        'v.id as vehicle_id',
                        'v.plate_no',
                        'bs.account_id',
                        'b.bundle_description',
                        'bs.start_date',
                        'bs.expire_date',
                        'bs.status'
                    )
                    ->whereIn('v.id', $vehicleIds)
                    ->where('bs.status', 1)
                    ->get()
                    ->groupBy('vehicle_id');
            }

            // Merge bundle information with vehicles
            $vehicles = $vehicles->map(function ($vehicle) use ($bundleInfo) {
                $vehicleData = (array) $vehicle;

                if (isset($bundleInfo[$vehicle->id])) {
                    $bundles = $bundleInfo[$vehicle->id];
                    $vehicleData['bundle_status'] = 'BUNDLE ACTIVE';
                    $vehicleData['bundle_plate_no'] = $bundles->first()->plate_no;
                    $vehicleData['account_id'] = $bundles->first()->account_id;
                    $vehicleData['bundle_description'] = $bundles->first()->bundle_description;
                    $vehicleData['start_date'] = $bundles->first()->start_date;
                    $vehicleData['expire_date'] = $bundles->first()->expire_date;

                    // Add all bundles as a separate array if needed
                    $vehicleData['all_bundles'] = $bundles->map(function ($bundle) {
                        return [
                            'bundle_description' => $bundle->bundle_description,
                            'start_date' => $bundle->start_date,
                            'expire_date' => $bundle->expire_date,
                            'status' => $bundle->status
                        ];
                    });
                } else {
                    $vehicleData['bundle_status'] = 'NO BUNDLE';
                    $vehicleData['bundle_plate_no'] = null;
                    $vehicleData['account_id'] = null;
                    $vehicleData['bundle_description'] = null;
                    $vehicleData['start_date'] = null;
                    $vehicleData['expire_date'] = null;
                    $vehicleData['all_bundles'] = [];
                }

                return $vehicleData;
            });

            if ($vehicles->count() > 0) {
                return $this->sendResponse($vehicles, 'Data successfully retrieve');
            }
            return $this->sendError('No vehicles related to this account');
        }
        return $this->sendError('Account no does not exist');
    }

    public function commuterVehiclePassage(Request $request): JsonResponse
    {
        $plateNo = $request->plate_no;

        if ($plateNo != null) {
            $queryA = DB::table('toll_transaction as tt')
                ->select('v.plate_no', 'l.lane_no', 'tt.created_at as passed_date')
                ->leftJoin('vehicle as v', 'tt.vehicle_id', '=', 'v.id')
                ->leftJoin('lane as l', 'tt.lane_id', '=', 'l.id')
                ->where('v.plate_no', $plateNo);

            $queryB = DB::table('bundle_subscription_passage as bsp')
                ->select('v.plate_no', 'l.lane_no', 'bsp.arrival_time as passed_date')
                ->leftJoin('vehicle as v', 'bsp.card_number', '=', 'v.card_number')
                ->leftJoin('lane as l', 'bsp.lane_id', '=', 'l.id')
                ->where('v.plate_no', $plateNo);

            $results = $queryA->unionAll($queryB)->orderByDesc('passed_date')->limit(10)->get();
            if ($results->count() > 0) {
                return $this->sendResponse($results, 'Data successfully retrieve');
            }
            return $this->sendError('No passage record for this plate number');
        }

        return $this->sendError('No passage record for this plate number');
    }


    public function commuterTopUps(Request $request)
    {
        $account_no = $request->account_no;
        if ($account_no != null) {
            #receive account no and validate if is valid
            $top_ups = DB::table('top_up as t')
                ->select(
                    't.bill_gen_at',
                    't.contr_num',
                    't.bill_amount',
                    't.bill_status',
                    't.psp_receipt_num',
                    't.pay_ref_id',
                    't.trx_dt_tm',
                    't.trx_id',
                    't.usd_pay_chn'
                )
                ->where('t.account_no', $account_no)
                ->whereNull('t.is_cancelled')
                ->orderByDesc('t.trx_dt_tm')
                ->get();
            if ($top_ups->count() > 0) {
                return $this->sendResponse($top_ups, 'Data successfully retrieve');
            } else {
                return $this->sendError('The account no is does not have any transactions');
            }

        }
        return $this->sendError('Account no does not exist');
    }

    public function commuterBundles(Request $request)
    {
        $account_no = $request->account_no;
        if ($account_no != null) {
            #receive account no and validate if is valid
            $bundles = DB::table('bundle_subscriptions as bs')
                ->join('toll_bundles as tb', 'bs.bundle_id', '=', 'tb.id')
                ->join('vehicle as v', 'bs.vehicle_id', '=', 'v.id')
                ->select(
                    'bs.account_id',
                    'v.plate_no',
                    'tb.bundle_description',
                    'bs.start_date',
                    'bs.expire_date',
                    DB::raw("CASE WHEN bs.status = 1 THEN 'ACTIVE' ELSE 'IN-ACTIVE' END AS STATUS")
                )
                ->where('bs.account_id', $account_no)
                ->get();
            if ($bundles->count() > 0) {
                return $this->sendResponse($bundles, 'Data successfully retrieve');
            } else {
                return $this->sendError('The account no is does not have any transactions');
            }

        }
        return $this->sendError('Account no does not exist');
    }

    public function commuterBundleTransactions(Request $request)
    {
        $account_no = $request->account_no;
        if ($account_no != null) {
            #receive account no and validate if is valid
            $bundles = DB::table('bridge_bills as bb')
                ->select(
                    'bb.bill_gen_at',
                    'bb.bill_desc',
                    'bb.dist_param',
                    'bb.bill_amount',
                    'bb.contr_num',
                    'bb.receipt_number'
                )
                ->where('bb.bill_gen_by', $account_no)
                ->whereNull('bb.bill_cancel_by')
                ->whereNull('bb.is_cancelled')
                ->orderByDesc('bb.bill_gen_at')
                ->get();
            if ($bundles->count() > 0) {
                return $this->sendResponse($bundles, 'Data successfully retrieve');
            } else {
                return $this->sendError('The account no is does not have any transactions');
            }

        }
        return $this->sendError('Account no does not exist');
    }

    public function commuterActiveBundle(Request $request)
    {
        $account_no = $request->account_no;
        if ($account_no != null) {
            #receive account no and validate if is valid
            $result = DB::table('bundle_subscriptions as bs')
                ->join('toll_bundles as tb', 'bs.bundle_id', '=', 'tb.id')
                ->join('vehicle as v', 'bs.vehicle_id', '=', 'v.id')
                ->select(
                    'bs.account_id',
                    'v.plate_no',
                    'tb.bundle_description',
                    'bs.start_date',
                    'bs.expire_date',
                    DB::raw("CASE WHEN bs.status = 1 THEN 'ACTIVE' ELSE 'IN-ACTIVE' END AS STATUS")
                )
                ->where('bs.account_id', $account_no)
                ->where('bs.status', 1)
                ->get();

            if ($result->count() > 0) {
                return $this->sendResponse($result, 'Data successfully retrieve');
            } else {
                return $this->sendError('The account no is does not have any active bundle');
            }

        }
        return $this->sendError('Account no does not exist');
    }

    public function commuterBuyBundle(Request $request)
    {
        $plate_no = $request->plate_no;
        $bundle_id = $request->bundle_id;
//        if ($account_no != null) {
//            #receive account no and validate if is valid
//            $result = DB::table('bundle_subscriptions as bs')
//                ->join('toll_bundles as tb', 'bs.bundle_id', '=', 'tb.id')
//                ->join('vehicle as v', 'bs.vehicle_id', '=', 'v.id')
//                ->select(
//                    'bs.account_id',
//                    'v.plate_no',
//                    'tb.bundle_description',
//                    'bs.start_date',
//                    'bs.expire_date',
//                    DB::raw("CASE WHEN bs.status = 1 THEN 'ACTIVE' ELSE 'IN-ACTIVE' END AS STATUS")
//                )
//                ->where('bs.account_id', $account_no)
//                ->where('bs.status', 1)
//                ->get();
//
//            if ($result->count() > 0) {
//                return $this->sendResponse($result, 'Data successfully retrieve');
//            }else {
//                return $this->sendError('The account no is does not have any active bundle');
//            }
//
//        }
//        return $this->sendError('Account no does not exist');
    }

    public function commuterGetPaidVehiclePassage(Request $request): void
    {
        $account_no = $request->account_no;
        if ($account_no != null) {

        }

    }

    public function commuterGetBundleVehiclePassage(Request $request): void
    {
        $account_no = $request->account_no;
        if ($account_no != null) {

        }

    }

    public function commuterCheckControlNumber(Request $request): JsonResponse
    {
        //youuuu
        $bill_id = $request->bill_id;
        $bill_type = $request->bill_type;
        if ($bill_id != null) {
            if ($bill_type == "T") {
                $top_up = DB::table('top_up')
                    ->where('id', '=', "$bill_id")
                    ->select(
                        "account_no",
                        "bill_desc",
                        "bill_amount",
                        "bill_gen_at",
                        "bill_exp_dt",
                        "contr_num",
                        "usd_pay_chn",
                        "pyr_cell_num",
                        "psp_name",
                        "ctr_acc_num",
                        "bill_status",
                        "receipt_number",
                        "pay_ref_id",
                        "receipt_date",
                        "payment_date",
                    )
                    ->first();
                return $this->checkControlNo($top_up);

            } elseif ($bill_type == "B") {

                $bundle = DB::table('bridge_bills')
                    ->where('id', '=', "$bill_id")
                    ->select(
                        "account_no",
                        "bill_desc",
                        "bill_amount",
                        "contr_num",
                        "psp_name",
                        "bill_status",
                        "receipt_number",
                        "pay_ref_id",
                        "receipt_date",
                        "payment_date",
                    )
                    ->first();

                return $this->checkControlNo($bundle);
            }
            return $this->sendError('Invalid bill');
        }
        return $this->sendError('Invalid bill');
    }

    /**
     * @param $top_up
     * @return JsonResponse
     */
    private function checkControlNo($bill_type): JsonResponse
    {
        if ($bill_type) {
            $receipt_number = $bill_type['receipt_number'];
            if ($receipt_number != null) {
                $getReceiptUrl = DB::table('transactions')
                    ->where('receipt_num', '=', "$receipt_number")
                    ->select(
                        "verification_url",
                    )
                    ->first();
                $response = [
                    'control_number' => $bill_type['contr_num'],
                    'receipt_number' => $bill_type['receipt_number'],
                    'receipt_date' => $bill_type['receipt_date'],
                    'verification_url' => $getReceiptUrl['verification_url'],
                ];
                return $this->sendResponse($response, 'Data successfully retrieve');
            }
            $response = [
                'control_number' => $bill_type['contr_num'],
                'receipt_number' => $bill_type['receipt_number'],
                'receipt_date' => $bill_type['receipt_date'],
                'verification_url' => 'Not Paid',
            ];
            return $this->sendResponse($response, 'Data successfully retrieve');
        }
        return $this->sendError('Invalid bill does not exist');
    }

    public function getBridgeAccountDetails(Request $request): JsonResponse
    {

        $rules = [
            'token' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        $url = DBHelper::getCFMSProUrl() . 'portal/external-system-validate-token?extra=subscribe';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->token, // Replace $yourToken with your actual token
        ])->post($url, [
            'service' => 'bridge'
        ]);

        if ($response->successful()) {
            // Handle successful response
            $data = $response->json(); // Get the response data

            if ($data['success']) {
                $portal_user = $data['data']['portal_user'];
                $portal_service_subscription = $data['data']['portal_service_subscription'];

                if (!is_null($portal_service_subscription)) {
                    $portal_account_subscription = PortalAccountSubscription::where('portal_service_subscription_id', $portal_service_subscription['id'])->first();
                    if (is_null($portal_account_subscription)) {


                       $account = DB::table('account')->where('phone', $portal_user['phone_number'])->first();

                       if (is_null($account)) {
                           $account_id = DB::table('account')->insertGetId([
                               'first_name' => $portal_user['first_name'],
                               'middle_name' => $portal_user['middle_name'],
                               'surname' => $portal_user['last_name'],
                               'phone' => $portal_user['phone_number'],
                               'email' => $portal_user['email'],
                               'created_by' => 'self_registration',
                               'created_at' => now(), // Add timestamps if needed
                               'updated_at' => now()  // Add timestamps if needed
                           ]);
                           $account = DB::table('account')->where('id', $account_id)->first();
                       }
                       // todo set email and names to that of portal user

                       $account_no = $account->account_no;

                       $portal_account_subscription = PortalAccountSubscription::firstOrCreate(
                           [
                               'portal_service_subscription_id' => $portal_service_subscription['id'],
                               'portal_user_id' => $portal_user['id'],
                           ],
                           [
                               'account_number' => $account_no
                           ]
                       );
                       $portal_account_subscription->save();
                    }

                    $account = Account::where('account_no', $portal_account_subscription->account_number)->first();

                    // Get vehicles count associated with the account through account_vehicle table
                    $vehiclesCount = DB::table('account_vehicle as av')
                        ->join('vehicle as v', 'av.vehicle_id', '=', 'v.id')
                        ->where('av.account_id', $account->account_no)
                        ->where('av.status', AccountVehicle::STATUS_ACTIVE)
                        ->count();

                    // Get total active bundles for the account
                    $activeBundlesCount = DB::table('bundle_subscriptions as bs')
                        ->join('account_vehicle as av', 'bs.vehicle_id', '=', 'av.vehicle_id')
                        ->where('av.account_id', $account->account_no)
                        ->where('av.status', AccountVehicle::STATUS_ACTIVE)
                        ->where('bs.status', BundleSubscription::STATUS_ACTIVE)
                        ->count();

                    $user = Auth::guard('portal')->getProvider()->retrieveById($account->id);
                    Auth::guard('portal')->setUser($user);

                    $token = $user->createToken('Personal Access Token')->plainTextToken;
                    return $this->sendResponse([
                        'account' => $account,
                        'vehicles' => [
                            'total_vehicles' => $vehiclesCount
                        ],
                        'bundles' => [
                            'total_active_bundles' => $activeBundlesCount
                        ],
                        'token' => $token,
                    ], 'Response');
                }
                return $this->sendError('Not Subscribed', ['url' => $url, 'data' => $data], 5);
            }

            return $this->sendError($data['message'], $data, $data['error_code'], $data['status_code']);
        } else {
            // Handle error response
            $statusCode = $response->status(); // Get the status code
            $error = $response->body(); // Get the raw response
            return $this->sendError('Failed to verify token', ['error' => $error], $statusCode);
        }

    }

    public function getBridgeAccountPassagesCount(Request $request): JsonResponse
    {
        $rules = [
            'token' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        $url = DBHelper::getCFMSProUrl() . 'portal/external-system-validate-token?extra=subscribe';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->token,
        ])->post($url, [
            'service' => 'bridge'
        ]);

        if ($response->successful()) {
            $data = $response->json();

            if ($data['success']) {
                $portal_user = $data['data']['portal_user'];
                $portal_service_subscription = $data['data']['portal_service_subscription'];

                if (!is_null($portal_service_subscription)) {
                    $portal_account_subscription = PortalAccountSubscription::where('portal_service_subscription_id', $portal_service_subscription['id'])->first();

                    if (!is_null($portal_account_subscription)) {
                        $account = Account::where('account_no', $portal_account_subscription->account_number)->first();

                        if (!is_null($account)) {
                            // Get total passages count for vehicles of the account from toll_transaction table
                            $passagesCount = DB::table('toll_transaction as tt')
                                ->join('account_vehicle as av', 'tt.vehicle_id', '=', 'av.vehicle_id')
                                ->where('av.account_id', $account->account_no)
//                                ->where('av.status', AccountVehicle::STATUS_ACTIVE)
                                ->count();

                            return $this->sendResponse([
                                'passages' => [
                                    'total_passages' => $passagesCount
                                ]
                            ], 'Passages count retrieved successfully');
                        }
                    }
                }
                return $this->sendError('Not Subscribed', ['url' => $url, 'data' => $data], 5);
            }

            return $this->sendError($data['message'], $data, $data['error_code'], $data['status_code']);
        } else {
            $statusCode = $response->status();
            $error = $response->body();
            return $this->sendError('Failed to verify token', ['error' => $error], $statusCode);
        }
    }

    public function subscribeToBridgeService(Request $request): JsonResponse
    {
        $rules = [
            'token' => 'required|string',
            'account_no' => [
                'nullable',  // Makes account_no optional
                'string',    // Ensures the value is a string if provided
                'exists:account,account_no' // Checks if account_no exists in the accounts table, only if provided
            ],
        ];


        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        $created_by = AuthUser::where('username', '=', 'portal_user')->first();
        if (is_null($created_by)) {
            return $this->sendError('Failed to subscribe user', [], 10);
        }

        /**
         * Validate and create the subscription using 'extra' params set to subscribe
         */
        $url = DBHelper::getCFMSProUrl() . 'portal/external-system-validate-token?extra=subscribe';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->token, // Replace $yourToken with your actual token
        ])->post($url, [
            'service' => 'bridge'
        ]);

        if ($response->successful()) {
            // Handle successful response
            $data = $response->json(); // Get the response data

            if ($data['success']) {
                DB::beginTransaction();
                $portal_user = $data['data']['portal_user'];
                $portal_service_subscription = $data['data']['portal_service_subscription'];

                $account_no = $request->account_no;
                if (!isset($request->account_no)) {
                    $account_id = DB::table('account')->insertGetId([
                        'first_name' => $portal_user['first_name'],
                        'middle_name' => $portal_user['middle_name'],
                        'surname' => $portal_user['last_name'],
                        'phone' => $portal_user['phone_number'],
                        'email' => $portal_user['email'],
                        'created_by' => $created_by->id,
                        'created_at' => now(), // Add timestamps if needed
                        'updated_at' => now()  // Add timestamps if needed
                    ]);
                    $account = DB::table('account')->where('id', $account_id)->first();
                    $account_no = $account->account_no;
                } else {
                    $account = DB::table('account')->where('account_no', $account_no)->first();
                    if (is_null($account)) {
                        return $this->sendError('Account Number not found', [], 15);
                    }

                    if (
//                        strtolower($portal_user['first_name']) !== strtolower($account->first_name) ||
//                        strtolower($portal_user['middle_name']) !== strtolower($account->middle_name) ||
//                        strtolower($portal_user['last_name']) !== strtolower($account->surname) ||
                        strtolower($portal_user['email']) !== strtolower($account->email)
//                        strtolower($portal_user['phone_number']) !== strtolower($account->phone)
                    ) {
                        return $this->sendError('Account details are not compatible for subscription', ['portal_user' => $portal_user, 'account' => $account], 20);
                    }
                }

                $portal_acc_subscription = PortalAccountSubscription::firstOrCreate(
                    [
                        'portal_service_subscription_id' => $portal_service_subscription['id'],
                        'portal_user_id' => $portal_user['id'],
                    ],
                    [
                        'account_number' => $account_no
                    ]
                );
                $portal_acc_subscription->save();

                DB::commit();
                return $this->sendResponse(['account' => $account_no], 'User Subscribed to Bridge Service Successfully');
//                return $this->sendError('Not Subscribed', [], 5);
            }
            return $this->sendError($data['message'], $data, $data['error_code'], $data['status_code']);
        } else {
            // Handle error response
            $statusCode = $response->status(); // Get the status code
            $error = $response->body(); // Get the raw response
            return $this->sendError('Failed to verify token', ['error' => $error], $statusCode);
        }
    }

    public function fetchBundles(): JsonResponse
    {
        $user = Auth::user();

        $account = Account::where('id', $user->id)->first();

        // Get existing bundle subscriptions
        $bundleSubscriptions = DB::table('bundle_subscriptions as bs')
            ->select([
                'bs.bill_id',
                'bs.bundle_id',
                'bb.contr_num',
                'bb.is_cancelled',
                'bb.bill_status',
                'tb.bundle_description',
                'bs.start_date',
                'bs.expire_date',
                'v.id as vehicle_id',
                'v.plate_no',
                'bt.name as body_type',
                'tb.status as bundle_status',
                'bb.bill_amount',
                'bb.ctr_acc_num'
            ])
            ->join('bcmis.vehicle as v', 'v.id', '=', 'bs.vehicle_id')
            ->join('bcmis.body_type as bt', 'bt.id', '=', 'v.body_type_id')
            ->join('bcmis.toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
            ->Join('bcmis.bridge_bills as bb', 'bb.id', '=', 'bs.bill_id')
            ->where('bs.account_id', '=', $account->account_no)
            ->get();

        $accountNo = $account->account_no;

        // Get bridge bills that have bundle_id but are not yet in bundle_subscriptions
        $bridgeBills = DB::table('bridge_bills as bb')
            ->select([
                'bb.id as bill_id',
                'bb.bundle_id',
                'bb.contr_num',
                DB::raw('CAST(bb.is_cancelled AS CHAR) as is_cancelled'),
                DB::raw('CAST(bb.bill_status AS CHAR) as bill_status'),
                'tb.bundle_description',
                DB::raw("'N/A' as start_date"),
                DB::raw("'N/A' as expire_date"),
                'v.id as vehicle_id',
                'bb.dist_param as plate_no',
                'bt.name as body_type',
                DB::raw('CAST(tb.status AS CHAR) as bundle_status'),
                'bb.bill_amount',
                'bb.ctr_acc_num'
            ])
            ->join('bcmis.toll_bundles as tb', 'tb.id', '=', 'bb.bundle_id')
            ->join('bcmis.vehicle as v', 'v.plate_no', '=', 'bb.dist_param')
            ->join('bcmis.body_type as bt', 'bt.id', '=', 'v.body_type_id')
            ->where(function ($q) use ($accountNo) {
                $q->whereIn('bb.dist_param', function ($sub) use ($accountNo) {
                    $sub->select('v2.plate_no')
                        ->from('bcmis.account_vehicle as av')
                        ->join('bcmis.vehicle as v2', 'v2.id', '=', 'av.vehicle_id')
                        ->where('av.account_id', $accountNo);
                })
                    ->orWhere('v.account_no', $accountNo);
            })
            ->whereNotNull('bb.bundle_id')
            ->whereNotIn('bb.id', function($query) {
                $query->select('bill_id')
                    ->from('bundle_subscriptions')
                    ->whereNotNull('bill_id');
            })
            ->get();

        // Combine both results
        $allBundles = $bundleSubscriptions->concat($bridgeBills);

        $allBundles->map(/**
         * @throws Exception
         */
        function ($subscription) {
             if ($subscription->is_cancelled) {
                $subscription->status = 'Cancelled';
             } else {
                if ($subscription->bill_status == '0') {
                    $subscription->status = 'Pending';
                } else if ($subscription->bill_status == '1') {
                    // Check if control number exists for payment verification
                    if (isset($subscription->ctr_acc_num) && !empty($subscription->ctr_acc_num)) {
                        $subscription->status = 'Paid';
                    } else {
                        $subscription->status = 'Unpaid';
                    }
                } else {
                    $subscription->status = 'Paid';
                }
             }
            // Return the modified object
            return $subscription;
        });

        return $this->sendResponse($allBundles, 'Bundles subscriptions fetched successfully');
    }

    public function fetchVehicles(): JsonResponse
    {
        $user = Auth::user();

        $account = Account::where('id', $user->id)->first();

        $vehicles = DB::table('account_vehicle as av')
            ->select([
                'av.vehicle_id as id',
                'v.plate_no',
                'v.created_at',
                'b.name as body_type',
                DB::raw("CASE WHEN v.exempted = 1 THEN 'YES' ELSE 'NO' END as exempted"),
            ])
            ->join('vehicle as v', 'v.id', '=', 'av.vehicle_id')
            ->join('body_type as b', 'b.id', '=', 'v.body_type_id')
            ->where('account_id', '=', $account->account_no)
            ->where('av.status', '=', AccountVehicle::STATUS_ACTIVE)
            ->get();


        return $this->sendResponse($vehicles, 'Vehicles fetched successfully');
    }

    public function fetchTopUps(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'account_no' => 'required|string|exists:account,account_no',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $accountNo = trim((string) $request->query('account_no'));
        $authed = Auth::user();

        if ($authed instanceof Account) {
            if (trim((string) $authed->account_no) !== $accountNo) {
                return $this->sendError('You may only list top-ups for your own account.', [], 0, 403);
            }
        } elseif ($authed instanceof AuthUser) {
            // Staff: may list any valid account_no (back office).
        } else {
            $linkedAccount = Account::where('id', $authed->getAuthIdentifier())->first();
            if (!$linkedAccount || trim((string) $linkedAccount->account_no) !== $accountNo) {
                return $this->sendError('You may only list top-ups for your own account.', [], 0, 403);
            }
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        $query = $this->topUpBaseQueryForAccount($accountNo);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($row) {
            return $this->enrichTopUpRow($row);
        });

        return $this->sendResponse([
            'top_ups' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'account_no' => $accountNo,
        ], 'Top-ups fetched successfully');
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function topUpBaseQueryForAccount(string $accountNo)
    {
        return DB::table('top_up as t')
            ->select([
                't.id',
                't.account_no',
                't.bill_desc',
                't.receipt_number',
                't.bill_amount',
                't.bill_exp_dt',
                't.contr_num',
                't.pay_ref_id',
                't.ctr_acc_num',
                't.bill_gen_at',
                't.bill_cancel_by',
                't.bill_cancel_date',
                't.cancel_reason',
                't.is_cancelled',
                't.payment_date',
                't.paid_amt',
                't.receipt_date',
                't.trx_id',
                't.trx_dt_tm',
                't.usd_pay_chn',
                't.pyr_cell_num',
                't.psp_receipt_num',
                't.psp_name',
                't.tin',
                't.source',
                't.erp_status',
                't.bill_status as bill_status_raw',
                't.t_status',
                't.error_code',
                't.payer_name',
            ])
            ->where('t.account_no', '=', $accountNo)
            ->orderByDesc('t.id');
    }

    /**
     * @param  object  $topUp  row from top_up query
     * @return object
     */
    private function enrichTopUpRow(object $topUp): object
    {
        if ((int) ($topUp->is_cancelled ?? 0) === 1) {
            $topUp->bill_status = 'CANCELLED';
        } elseif (!is_null($topUp->trx_dt_tm)) {
            $topUp->bill_status = 'PAID';
        } elseif (!is_null($topUp->bill_exp_dt) && now()->greaterThan($topUp->bill_exp_dt)) {
            $topUp->bill_status = 'EXPIRED';
        } else {
            $topUp->bill_status = 'UNPAID';
        }

        $topUp->is_unpaid = $topUp->bill_status === 'UNPAID';
        $topUp->gepg_control_number = $topUp->contr_num;
        $topUp->api_control_number = $topUp->contr_num;

        $isCancelled = (int) ($topUp->is_cancelled ?? 0) === 1;
        $isPaid = !is_null($topUp->trx_dt_tm) || !empty($topUp->psp_receipt_num);
        $topUp->can_cancel = !$isCancelled && !$isPaid;
        $topUp->can_repost = !$isCancelled && !$isPaid;
        $topUp->can_print = $isPaid;

        return $topUp;
    }

    public function fetchBundlePassages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'nullable|string|exists:vehicle,plate_no',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $user = Auth::user();

        $account = Account::where('id', $user->id)->first();

        $passages = DB::table('bundle_subscription_passage as bsp')
            ->select([
                'bsp.id',
                'l.lane_no',
                'v.plate_no',
                'bt.name as body_type_name',
                'bsp.arrival_time',
                'bsp.clearance_time'
            ])
            ->leftJoin('lane as l', 'l.id', '=', 'bsp.lane_id')
            ->leftJoin('vehicle as v', 'v.card_number', '=', 'bsp.card_number')
            ->leftJoin('account_vehicle as av', 'av.vehicle_id', '=', 'v.id')
            ->leftJoin('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->where('v.account_no', '=', $account->account_no);

        if ($request->plate_no != null) {
            $passages->where('v.plate_no', $request->plate_no);
        }

        $passages = $passages->get();
        return $this->sendResponse($passages, 'Bundles passages fetched successfully');
    }

    public function fetchNormaPassages(): JsonResponse
    {
        $user = Auth::user();
        $account = Account::where('id', $user->id)->first();

        $passages = DB::table('toll_transaction as tt')
            ->select([
                'tt.id',
                'tt.charged_amount',
                'tt.plate_no',
                'tt.receipt_num',
                'tt.trans_type',
                'tt.created_at',
                'bt.name as body_type_name'
            ])
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->leftJoin('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->where('tt.account_no', '=', $account->account_no)
            ->get();

        return $this->sendResponse($passages, 'passages fetched successfully');
    }

    public function fetchCommuterBills(Request $request): JsonResponse
    {
        $user = Auth::user();

        $account = Account::where('id', $user->id)->first();

        $bills = DB::table('commuter_bills as cb')
            ->where('cb.account_no', '=', $account->account_no)
            ->get();

        return $this->sendResponse($bills, 'Commute bills fetched successfully');
    }

    public function fetchAccountBills(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_no' => 'required|string|exists:account,account_no'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', ['errors' => $validator->errors()], 400, 422);
            }

            $accountNo = $request->account_no;

            // Get bills from bridge_bills table (bundle subscriptions)
            $bridgeBills = DB::table('bridge_bills as bb')
                ->select(
                    'bb.contr_num as control_no',
                    'bb.bill_gen_at as bill_date',
                    'bb.bill_amount',
                    'bb.bill_status',
                    'bb.trx_dt_tm',
                    DB::raw("'BUNDLE' as bill_type")
                )
                ->where('bb.bill_gen_by', $accountNo)
                ->whereNull('bb.bill_cancel_by')
                ->where('bb.bill_status', '!=', '0')
                ->get();

            // Get bills from top_up table (top-up transactions)
            $topUpBills = DB::table('top_up as t')
                ->select(
                    't.contr_num as control_no',
                    't.bill_gen_at as bill_date',
                    't.bill_amount',
                    't.bill_status',
                    't.trx_dt_tm',
                    DB::raw("'TOP_UP' as bill_type")
                )
                ->where('t.account_no', $accountNo)
                ->whereNull('t.is_cancelled')
                ->where('t.bill_status', '!=', '0')
                ->get();

            // Combine and format all bills
            $allBills = collect([$bridgeBills, $topUpBills])
                ->flatten(1)
                ->map(function ($bill) {
                    return [
                        'control_no' => $bill->control_no ?? 'N/A',
                        'bill_date' => $bill->bill_date ?? 'N/A',
                        'bill_amount' => $bill->bill_amount ?? 'N/A',
                        'bill_status' => $this->formatBillStatus($bill->bill_status, $bill->trx_dt_tm),
                        'bill_type' => $bill->bill_type ?? 'N/A'
                    ];
                })
                ->sortByDesc('bill_date')
                ->values();

            if ($allBills->count() > 0) {
                return $this->sendResponse($allBills, 'Account bills fetched successfully');
            }

            return $this->sendError('No bills found for this account');

        } catch (\Exception $e) {
            return $this->sendError('An error occurred while fetching bills', [
                'error' => $e->getMessage()
            ], 500, 500);
        }
    }

    /**
     * Format bill status for better readability
     * When control number is returned (status = 1), check trx_dt_tm to determine if paid
     */
    private function formatBillStatus($status, $trxDtTm = null): string
    {
        if (is_null($status)) {
            return 'N/A';
        }

        // If status is 1 (control number returned), check transaction date/time
        if ($status == '1') {
            if (!is_null($trxDtTm)) {
                return 'PAID'; // Transaction date/time exists, bill is paid
            } else {
                return 'PENDING_PAYMENT'; // Control number returned but no payment yet
            }
        }

        $statusMap = [
            '0' => 'PENDING',
            '2' => 'CANCELLED'
        ];

        return $statusMap[$status] ?? $status;
    }

    public function fetchCommuterPassages(Request $request): JsonResponse
    {
        $user = Auth::user();

        $account = Account::where('id', $user->id)->first();

        $passages = DB::table('passages as p')
            ->where('p.vehicle_id', '=', $request->vehicle_id)
            ->get();

        return $this->sendResponse($passages, 'Commuter passages fetched successfully');
    }


//    public function actionBillCancellation()
//    {
//        Yii::$app->response->format = Response::FORMAT_JSON;
//        $model = new TopUp();
//        $bill_details = Yii::$app->request->post();
//        $cancelBill = $model->postGepg($bill_details);
//
//        if ($cancelBill['TrxStsCode'] == 7283) {
//
//            $status = 1;
//            $bill_id = $bill_details['bill_id'];
//            $bill_canc_date = $bill_details['bill_canc_date'];
//            $bill_canc_by = $bill_details['bill_canc_by'];
//            $canc_reas = $bill_details['cancel_reason'];
//            $init = "CANC ";
//            $psp_receipt_num = $init . date('Y-m-d H:i:s');
//
//            Yii::$app->db->createCommand("UPDATE top_up SET is_cancelled=:status,cancel_reason=:cancel_reason,
//                  bill_cancel_date=:bill_cancel_date,bill_cancel_by=:bill_cancel_by,psp_receipt_num=:psp_receipt_num WHERE id=:bill_id")
//                ->bindValue(':cancel_reason', $canc_reas)
//                ->bindValue(':bill_cancel_date', $bill_canc_date)
//                ->bindValue(':bill_cancel_by', $bill_canc_by)
//                ->bindValue(':psp_receipt_num', $psp_receipt_num)
//                ->bindValue(':bill_id', $bill_id)
//                ->bindValue(':status', $status)
//                ->execute();
//
//            return array('status' => 1, 'message' => 'Bill Have successfully being Cancelled');
//
//        } elseif ($cancelBill['TrxStsCode'] == 7204) {
//
//            return array('status' => 0, 'message' => 'Bill Already Cancelled');
//        } else {
//            return array('status' => 2, 'message' => 'Unsuccessfully, Please Contact the Admin');
//        }
//    }

}
