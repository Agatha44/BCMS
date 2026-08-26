<?php

namespace App\Http\Controllers\BundlesController;
use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Account;
use App\Models\BridgeBill;
use App\Models\Counter;
use App\Models\PriceList;
use App\Models\Receipt;
use App\Models\ReprintReceipt;
use App\Models\TollBundle;
use App\Models\TollTransaction;
use App\Models\BundleSubscription;
use App\Models\Vehicle;
use App\Services\Bundle\BundleSubscriptionTransferService;
use App\Helpers\GePG;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class BundleController extends ConfigurationController
{
    const OUTSTANDING_BILL = 'BILL_001';

    public function getTollBundleTypes(): JsonResponse
    {
        return $this->sendResponse(TollBundle::where('status', 1)->get(), 'Toll Bundles fetched successfully');
    }

    public function fetchVehiclePrice(Request $request): JsonResponse
    {

        $rules = [
            'vehicle_id' => 'required|string|exists:vehicle,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        $vehicle = DB::table('vehicle as v')
            ->select(['v.id', 'v.body_type_id'])
            ->where('v.id', '=', $request->vehicle_id)
            ->join('body_type as bt', 'bt.id', 'v.body_type_id')
            ->first();

        if (is_null($vehicle)) {
            return $this->sendError('Price for this body type not found');
        }

        $price = DB::table('price_list as pl')
            ->where('pl.body_type_id', '=', $vehicle->body_type_id)
            ->first();

        if (is_null($price)) {
            return $this->sendError('Price for this body type not found');
        }

        return $this->sendResponse($price, 'Price fetched successfully');
    }

    public function postBill(Request $request): JsonResponse
    {
          // Validate required fields
        $rules = [
            'plate_no' => 'required|string',
            'bundle_id' => 'required|numeric|in:1,2,3',
            'source' => 'nullable|string|in:portal,ussd',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first());
        }

        $validatedData = $validator->validated();
        
        // Set default source to 'portal' if not provided
        if (!isset($validatedData['source']) || empty($validatedData['source'])) {
            $validatedData['source'] = 'portal';
        }

        // Check if vehicle exists and plate number matches
        $vehicle = Vehicle::where('plate_no', $validatedData['plate_no'])
            ->first();

        if (!$vehicle) {
            return $this->sendError('Vehicle not found or plate number does not match');
        }

        // Get account number from vehicle
        if (!$vehicle->account_no) {
            return $this->sendError('Vehicle is not associated with any account');
        }

        // Get account details using account number from vehicle
        $account = Account::where('account_no', $vehicle->account_no)->first();
        if (!$account) {
            return $this->sendError('Account not found for this vehicle');
        }

        // Get price list for the vehicle's body type - ensure it's active
        $priceList = PriceList::where('body_type_id', $vehicle->body_type_id)
            ->where('status', PriceList::STATUS_ACTIVE)
            ->first();

        if (!$priceList) {
            return $this->sendError('Vehicle body type not allowed for bundle');
        }

        // Calculate the bill amount based on bundle type and body type
        $billAmount = 0;
        switch ($validatedData['bundle_id']) {
            case 1: // Daily bundle
                $billAmount = $priceList->daily_bundle_amount;
                break;
            case 2: // Weekly bundle
                $billAmount = $priceList->weekly_bundle_amount;
                break;
            case 3: // Monthly bundle
                $billAmount = $priceList->monthly_bundle_amount;
                break;
            default:
                return $this->sendError('Please select a valid bundle');
        }

        if ($billAmount <= 0) {
            return $this->sendError('Invalid bundle amount. Please check price list.');
        }

        // Check if account has an outstanding bill (excluding expired bills and invalid control numbers)
        $outstandingBill = BridgeBill::where('dist_param', $validatedData['plate_no'])
            ->whereNull('trx_dt_tm')
            ->where('bill_status', '!=', BridgeBill::CANCELLED)
            ->whereNotNull('contr_num')
            ->where('contr_num', '!=', '0') // Exclude invalid control numbers
            ->where('contr_num', '!=', '') // Exclude empty control numbers
            ->where('bill_exp_dt', '>=', now()) // Exclude expired bills
            ->first();

        if ($outstandingBill) {
            if ($validatedData['source'] == 'ussd') {
                 return response()->json(['ussd_response' => 'Una ankara yenye kumbukumbu no  ' . $outstandingBill->contr_num . 'ambayo haijalipiwa', 'outstanding_bill' => $outstandingBill]);
            }
            return $this->sendError('You have an outstanding bill with Control Number ' . $outstandingBill->contr_num, ['outstanding_bill' => $outstandingBill], self::OUTSTANDING_BILL);
        }

        try {
            DB::beginTransaction();

            // Normalize phone to digits-only; bridge_bills.phone_number is integer
            $phoneNumber = (int) preg_replace('/\D/', '', (string) ($account->phone ?? ''));

            // Create new bridge bill
            $bridgeBill = BridgeBill::create([
                'bill_amount' => $billAmount,
                'bill_desc' => match($validatedData['bundle_id']) {
                    1 => 'Daily Bundle Subscription',
                    2 => 'Weekly Bundle Subscription',
                    3 => 'Monthly Bundle Subscription',
                    default => 'Bundle Subscription'
                },
                'phone_number' => $phoneNumber ?: null,
                'bill_gen_by' => $vehicle->account_no,
                'bill_source' => 1,
                'bill_status' => BridgeBill::REQUESTED,
                'source' => 'TBS',
                'bundle_id' => $validatedData['bundle_id'],
                'dist_param' => $validatedData['plate_no'],
                'bill_gen_at' => Carbon::now()->tz('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s'),
                'bill_exp_dt' => Carbon::now()->tz('Africa/Dar_es_Salaam')->addDay(1)->format('Y-m-d H:i:s'),
            ]);


            $billReqId = $bridgeBill->id;
            $shift_ref = "TBS";
            $bill_id = $shift_ref . $billReqId;


            // GePG Accepted Date Format
            $bill_gen_at = Carbon::parse($bridgeBill->bill_gen_at)->format("Y-m-d\TH:i:s");
            $bill_exp_dt = Carbon::parse($bridgeBill->bill_exp_dt)->format("Y-m-d\TH:i:s");

            // Prepare parameters for GePG
            $gepgParams = [
                'payment_ref' =>$bill_id,
                'amount' => (string)$billAmount,
                'equiv_amount' => (string)$billAmount,
                'bill_desc' => match($validatedData['bundle_id']) {
                    1 => 'Daily Bundle Subscription',
                    2 => 'Weekly Bundle Subscription',
                    3 => 'Monthly Bundle Subscription',
                    default => 'Bundle Subscription'
                },
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $vehicle->account_no,
                'payer_name' => $account->full_name,
                'payer_cell' => '255' . preg_replace('/\D/', '', (string) ($account->phone ?? '')),
                'generated_by' => (string)$vehicle->account_no,
                'days_expires_after' => 1,
                'payer_email' => $account->email ?? 'noreply@example.com',
                'bill_gen_date' => $bill_gen_at,
                'bill_exp_date' => $bill_exp_dt

            ];

            // Send to GePG
            $gepgResponse = GePG::postBill($gepgParams);

            if ($gepgResponse['status'] == 'invalid_params' || $gepgResponse['status'] == 'invalid_request') {
                DB::rollBack();
                return $this->sendError('GePG Error: ' . $gepgResponse['message']);
            }

            // Handle GePG response based on the actual response structure
            if (isset($gepgResponse['data']) && is_array($gepgResponse['data'])) {
                // Success case - GePG returned data
                $getBilldata = BridgeBill::find($bridgeBill->id);
                DB::commit();
                return $this->sendResponse([
                    'bill_id' => $bridgeBill->id,
                    'bill_amount' => $billAmount,
                    'control_number' => $getBilldata->contr_num ?? 0,
                    'gepg_response' => $gepgResponse['data']
                ], 'Control Number Request Successfully Sent');
            } else {
                // Failed - update bill with error
                $bridgeBill->update(
                    [
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['message'] ?? 'Unknown Error'
                    ]
            );

                DB::rollBack();
                return $this->sendError('Failed to process bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bill', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('Failed to create bill. Please try again or contact support.');
        }
    }

    public function getVehicleBundleInfo(Request $request): JsonResponse
    {
        // Validate required fields
        $rules = [
            'plate_no' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first());
        }

        $validatedData = $validator->validated();

        // Get vehicle with body type information
        $vehicle = Vehicle::with('bodyType')
            ->where('plate_no', $validatedData['plate_no'])
            ->first();

        if (!$vehicle) {
            return $this->sendError('Vehicle not found with the provided plate number');
        }

        // Get account details using account number from vehicle
        $account = null;
        if ($vehicle->account_no) {
            $account = Account::where('account_no', $vehicle->account_no)->first();
        }

        if (!$account) {
            return $this->sendError('Vehicle not registered,Please Visit Nyerere Bridge office for registration');
        }

        // Get price list for the vehicle's body type
        $priceList = PriceList::where('body_type_id', $vehicle->body_type_id)
            ->where('status', PriceList::STATUS_ACTIVE)
            ->first();

        if (!$priceList) {
            return $this->sendError('Vehicle body type not allowed for bundle');
        }

        // Prepare bundle information
        $bundles = [
            [
                'bundle_id' => 1,
                'bundle_name' => 'Daily Bundle',
                'bundle_description' => 'Day Bundle Subscription',
                'amount' => $priceList->daily_bundle_amount,
                'currency' => 'TZS'
            ],
            [
                'bundle_id' => 2,
                'bundle_name' => 'Weekly Bundle',
                'bundle_description' => 'Week Bundle Subscription',
                'amount' => $priceList->weekly_bundle_amount,
                'currency' => 'TZS'
            ],
            [
                'bundle_id' => 3,
                'bundle_name' => 'Monthly Bundle',
                'bundle_description' => 'Month Bundle Subscription',
                'amount' => $priceList->monthly_bundle_amount,
                'currency' => 'TZS'
            ]
        ];

        // Prepare response data
        $responseData = [
            'vehicle' => [
                'id' => $vehicle->id,
                'plate_no' => $vehicle->plate_no,
                'body_type' => [
                    'id' => $vehicle->bodyType->id ?? null,
                    'name' => $vehicle->bodyType->name ?? null,
                    'description' => $vehicle->bodyType->description ?? null,
                ],
                'account_no' => $vehicle->account_no,
                'image' => $vehicle->image,
                'created_at' => $vehicle->created_at,
                'updated_at' => $vehicle->updated_at,
            ],
            'owner' => [
                'account_no' => $account->account_no,
                'first_name' => $account->first_name,
                'surname' => $account->surname,
                'full_name' => $account->full_name,
                'phone' => $account->phone,
                'email' => $account->email,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at,
            ],
            'bundles' => $bundles,
            'price_list' => [
                'id' => $priceList->id,
                'body_type_id' => $priceList->body_type_id,
                'regular_amount' => $priceList->amount,
                'daily_bundle_amount' => $priceList->daily_bundle_amount,
                'weekly_bundle_amount' => $priceList->weekly_bundle_amount,
                'monthly_bundle_amount' => $priceList->monthly_bundle_amount,
                'status' => $priceList->status,
            ]
        ];

        return $this->sendResponse($responseData, 'Vehicle bundle information fetched successfully');
    }

    public function getTollBundleBills(Request $request): JsonResponse
    {
        try {
            // Supports both payload styles:
            // 1) direct: draw,start,length,search.value,order[0][column],order[0][dir]
            // 2) wrapped: { post_value: { ... } }
            $post = $request->input('post_value', $request->all());
    
            $columns = [
                0 => 'esb.id',
                1 => 'esb.receipt_number',
                2 => 'esb.contr_num',
                3 => 'esb.bill_amount',
                4 => 'esb.bill_desc',
                5 => 'customer_name',
                6 => 'esb.psp_receipt_num',
                7 => 'a.account_no',
            ];
    
            $baseQuery = DB::table('bridge_bills as esb')
                ->leftJoin('account as a', 'esb.bill_gen_by', '=', 'a.account_no')
                ->where('esb.bill_source', 1)
                ->where('esb.bill_status', 1);
    
            // Search by account_no, customer name, plate_no, control number
            $search = $this->resolveRequestSearch($post);
            if ($search !== '') {
                $like = '%' . $search . '%';
                $lowerLike = '%' . mb_strtolower($search) . '%';
                $baseQuery->where(function ($q) use ($like, $lowerLike) {
                    $q->where('a.account_no', 'like', $like)
                        ->orWhere('esb.dist_param', 'like', $like)
                        ->orWhere('esb.contr_num', 'like', $like)
                        ->orWhere('esb.psp_receipt_num', 'like', $like)
                        ->orWhere('a.first_name', 'like', $like)
                        ->orWhere('a.middle_name', 'like', $like)
                        ->orWhere('a.surname', 'like', $like)
                        ->orWhereRaw(
                            "LOWER(TRIM(CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.middle_name,''), ' ', COALESCE(a.surname,'')))) LIKE ?",
                            [$lowerLike]
                        );
                });
            }
    
            $recordsFiltered = (clone $baseQuery)->count();
    
            $orderCol = (int) data_get($post, 'order.0.column', -1);
            $orderDir = strtolower((string) data_get($post, 'order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    
            $query = (clone $baseQuery)
                ->select([
                    'esb.id',
                    'esb.receipt_number',
                    'esb.contr_num',
                    'esb.bill_amount',
                    'esb.bill_desc',
                    'esb.psp_receipt_num',
                    'esb.dist_param as plate_no',
                    'esb.bill_status',
                    'esb.bill_gen_at',
                    'esb.bill_exp_dt',
                    'esb.source',
                    'a.account_no',
                    DB::raw("TRIM(CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.surname,''))) as customer_name"),
                ]);
    
            if (isset($columns[$orderCol])) {
                $query->orderBy($columns[$orderCol], $orderDir);
            } else {
                $query->orderByDesc('esb.id');
            }
    
            $paging = $this->resolveDataTablePaging($post);

            if ($paging['length'] !== -1) {
                $query->offset($paging['start'])->limit($paging['length']);
            } else {
                $query->offset(0)->limit(100);
            }

            $rows = $query->get();

            // Pure API rows (no HTML/actions)
            $data = $rows->values()->map(function ($row) {
                return [
                    'id' => $row->id,
                    'account_no' => $row->account_no,
                    'customer_name' => $row->customer_name,
                    'plate_no' => $row->plate_no,
                    'control_number' => $row->contr_num,
                    'psp_receipt_num' => $row->psp_receipt_num,
                    'bill_amount' => (float) $row->bill_amount,
                    'bill_description' => $row->bill_desc,
                    'bill_status' => $row->bill_status,
                    'bill_generated_at' => $row->bill_gen_at,
                    'bill_expiry_at' => $row->bill_exp_dt,
                    'created_at' => $row->bill_gen_at,
                    'source' => $row->source,
                    'can_cancel' => is_null($row->psp_receipt_num),
                    'can_repost' => is_null($row->psp_receipt_num),
                    'can_print' => true,
                ];
            });
    
            $recordsTotal = DB::table('bridge_bills')
                ->where('bill_source', 1)
                ->where('bill_status', 1)
                ->count();

            $perPage = $paging['per_page'];
            $currentPage = $paging['page'];
            $lastPage = $perPage > 0 ? max(1, (int) ceil($recordsFiltered / $perPage)) : 1;

            return $this->sendResponse([
                'draw' => (int) data_get($post, 'draw', 0),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'pagination' => [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $recordsFiltered,
                    'from' => $recordsFiltered > 0 ? $paging['start'] + 1 : null,
                    'to' => $recordsFiltered > 0 ? min($paging['start'] + $data->count(), $recordsFiltered) : null,
                ],
            ], 'Toll bundle bills retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve toll bundle bills', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return $this->sendError('Failed to retrieve toll bundle bills: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function orderForm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $bill = DB::table('bridge_bills as bb')
            ->leftJoin('account as a', 'bb.bill_gen_by', '=', 'a.account_no')
            ->leftJoin('toll_bundles as tb', 'tb.id', '=', 'bb.bundle_id')
            ->where('bb.id', (int) $request->input('id'))
            ->where('bb.bill_source', 1)
            ->select([
                'bb.id',
                'bb.bill_amount',
                'bb.bill_desc',
                'bb.contr_num',
                'bb.receipt_number',
                'bb.psp_receipt_num',
                'bb.bill_status',
                'bb.is_cancelled',
                'bb.bill_gen_at',
                'bb.bill_exp_dt',
                'bb.created_at',
                'bb.dist_param as plate_no',
                'bb.bundle_id',
                'bb.phone_number',
                'bb.pyr_cell_num',
                'bb.payer_name',
                'bb.trx_id',
                'bb.trx_dt_tm',
                'bb.payment_date',
                'bb.paid_amt',
                'bb.t_status',
                'bb.error_code',
                'bb.cancel_reason',
                'bb.bill_cancel_date',
                'bb.updated_at',
                'a.account_no',
                DB::raw("TRIM(CONCAT(COALESCE(a.first_name,''), ' ', COALESCE(a.surname,''))) as customer_name"),
                'tb.bundle_description',
                'tb.sw_desc',
            ])
            ->first();

        if (!$bill) {
            return $this->sendError('Toll bundle bill not found');
        }

        return $this->sendResponse($this->formatTollBundleBillRow($bill), 'Toll bundle order form retrieved successfully');
    }

    public function repost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $bill = BridgeBill::query()
            ->where('bill_source', 1)
            ->find((int) $request->input('id'));

        if (!$bill) {
            return $this->sendError('Toll bundle bill not found', [], 0, 404);
        }

        if (!empty($bill->psp_receipt_num) || !empty($bill->trx_dt_tm)) {
            return $this->sendError('Cannot repost paid bill', [], 0, 400);
        }

        if ((int) ($bill->is_cancelled ?? 0) === 1) {
            return $this->sendError('Cannot repost cancelled bill', [], 0, 400);
        }

        $account = Account::where('account_no', $bill->bill_gen_by)->first();
        if (!$account) {
            return $this->sendError('Account not found for this bill');
        }

        try {
            $now = Carbon::now('Africa/Dar_es_Salaam');
            $billGenAt = $now->format('Y-m-d H:i:s');
            $billExpDt = $now->copy()->addDay()->format('Y-m-d H:i:s');

            $bill->update([
                'bill_gen_at' => $billGenAt,
                'bill_exp_dt' => $billExpDt,
                'updated_at' => now(),
            ]);

            $billRef = 'TBS' . $bill->id;
            $phone = (string) ($account->phone_number ?? '');

            $gepgParams = [
                'payment_ref' => $billRef,
                'amount' => (string) $bill->bill_amount,
                'equiv_amount' => (string) $bill->bill_amount,
                'bill_desc' => $bill->bill_desc ?? 'Bundle Subscription',
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => (string) $bill->bill_gen_by,
                'payer_name' => $account->full_name ?? trim(($account->first_name ?? '') . ' ' . ($account->surname ?? '')),
                'payer_cell' => GePG::normalizePayerCell($phone),
                'generated_by' => (string) $bill->bill_gen_by,
                'days_expires_after' => 1,
                'payer_email' => $account->email,
                'bill_gen_date' => Carbon::parse($billGenAt)->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($billExpDt)->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);

            if (in_array($gepgResponse['status'] ?? null, ['invalid_params', 'invalid_request'], true)) {
                return $this->sendError('GePG Error: ' . ($gepgResponse['message'] ?? 'Invalid request'));
            }

            if (($gepgResponse['status'] ?? null) === 'success' || (isset($gepgResponse['data']) && is_array($gepgResponse['data']))) {
                $responseData = $gepgResponse['data'] ?? [];
                $controlNum = is_array($responseData)
                    ? ($responseData['contr_num'] ?? $responseData['control_no'] ?? null)
                    : null;

                $updateData = [
                    't_status' => 'SP',
                    'error_code' => null,
                    'updated_at' => now(),
                ];
                if ($controlNum) {
                    $updateData['contr_num'] = $controlNum;
                }
                $bill->update($updateData);
                $bill->refresh();

                Log::info('Toll bundle bill reposted to GePG successfully', [
                    'bill_id' => $bill->id,
                    'control_number' => $bill->contr_num,
                ]);

                return $this->sendResponse([
                    'bill_id' => $bill->id,
                    'bill_amount' => (float) $bill->bill_amount,
                    'control_number' => $bill->contr_num ?? 0,
                    'gepg_response' => $gepgResponse['data'] ?? null,
                ], 'Bill reposted successfully');
            }

            $bill->update([
                't_status' => 'GF',
                'error_code' => $gepgResponse['message'] ?? 'Unknown Error',
                'updated_at' => now(),
            ]);

            Log::warning('Toll bundle bill repost to GePG failed', [
                'bill_id' => $bill->id,
                'gepg_response' => $gepgResponse,
            ]);

            return $this->sendError('Failed to repost bill to GePG', $gepgResponse, 0, 200);
        } catch (\Throwable $e) {
            Log::error('Failed to repost toll bundle bill', [
                'bill_id' => $request->input('id'),
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to repost bill', [], 0, 500);
        }
    }

    public function tollBundleSubscriptions(Request $request): JsonResponse
    {
        $post = $request->input('post_value', $request->all());

        $columns = [
            "id",
            "bill_amount",
            "plate_no",
            "customer",
            "start_date",
            "expire_date",
            "account_no",
            "bundle",
            "status",
            "bundle_description",
            "bundle_status",
        ];

        $baseQuery = DB::table('bundle_subscriptions as bs')
            ->leftJoin('account as a', 'bs.account_id', '=', 'a.account_no')
            ->leftJoin('vehicle as v', 'v.id', '=', 'bs.vehicle_id')
            ->join('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id');

        $searchValue = $this->resolveRequestSearch($post);
        if ($searchValue !== '') {
            $like = '%' . $searchValue . '%';
            $lowerLike = '%' . mb_strtolower($searchValue) . '%';
            $baseQuery->where(function ($q) use ($like, $lowerLike) {
                $q->where('v.plate_no', 'like', $like)
                    ->orWhere('a.first_name', 'like', $like)
                    ->orWhere('a.middle_name', 'like', $like)
                    ->orWhere('a.surname', 'like', $like)
                    ->orWhere('a.account_no', 'like', $like)
                    ->orWhere('tb.bundle_description', 'like', $like)
                    ->orWhereRaw(
                        "LOWER(CONCAT_WS(' ', a.first_name, a.middle_name, a.surname)) LIKE ?",
                        [$lowerLike]
                    );
            });
        }

        $filteredCount = (clone $baseQuery)->distinct()->count('bs.id');

        $baseQuery->selectRaw("
                bs.id,
                bs.start_date,
                bs.expire_date,
                bs.status as bundle_status,
                CONCAT(a.first_name, ' ', a.surname) as customer,
                a.account_no,
                tb.bundle_description,
                v.plate_no
            ");

        $orderColumnIndex = data_get($post, 'order.0.column');
        $orderDir = strtolower((string) data_get($post, 'order.0.dir', 'desc'));
        $orderDir = in_array($orderDir, ['asc', 'desc']) ? $orderDir : 'desc';

        if ($orderColumnIndex !== null && isset($columns[(int) $orderColumnIndex])) {
            $orderColumn = $columns[(int) $orderColumnIndex];

            if ($orderColumn === 'customer') {
                $baseQuery->orderByRaw("CONCAT(a.first_name, ' ', a.surname) {$orderDir}");
            } elseif ($orderColumn === 'bundle_description') {
                $baseQuery->orderBy('tb.bundle_description', $orderDir);
            } elseif ($orderColumn === 'plate_no') {
                $baseQuery->orderBy('v.plate_no', $orderDir);
            } elseif ($orderColumn === 'account_no') {
                $baseQuery->orderBy('a.account_no', $orderDir);
            } elseif ($orderColumn === 'bundle_status' || $orderColumn === 'status') {
                $baseQuery->orderBy('bs.status', $orderDir);
            } else {
                $baseQuery->orderBy("bs.{$orderColumn}", $orderDir);
            }
        } else {
            $baseQuery->orderByDesc('bs.id');
        }

        $paging = $this->resolveDataTablePaging($post);

        if ($paging['length'] !== -1) {
            $baseQuery->offset($paging['start'])->limit($paging['length']);
        }

        $rows = $baseQuery->get();

        $data = collect($rows)->values()->map(function ($row, $index) {
            return [
                'id' => (int) $row->id,
                'customer' => $row->customer,
                'account_no' => $row->account_no,
                'plate_no' => $row->plate_no,
                'start_date' => $row->start_date,
                'expire_date' => $row->expire_date,
                'bundle_description' => $row->bundle_description,
                'bundle_status' => ((int) $row->bundle_status === 1) ? 'ACTIVE' : 'IN ACTIVE',
                'bundle_status_code' => (int) $row->bundle_status,
            ];
        });

        $recordsTotal = DB::table('bundle_subscriptions as bs')
            ->join('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
            ->count('bs.id');

        $perPage = $paging['per_page'];
        $currentPage = $paging['page'];
        $lastPage = $perPage > 0 ? max(1, (int) ceil($filteredCount / $perPage)) : 1;
        $from = $filteredCount > 0 ? $paging['start'] + 1 : null;
        $to = $filteredCount > 0 ? min($paging['start'] + $data->count(), $filteredCount) : null;

        return response()->json([
            'draw' => (int) data_get($post, 'draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $filteredCount,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
    /**
     * Purchasable bundle tiers for a plate (same rules as post-bill: vehicle, account, active price list, amount &gt; 0).
     * Includes outstanding bundle-bill check so the client can disable "Pay" until resolved.
     */
    public function eligibleBundlesByPlate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'plate_no' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $plateNo = trim((string) $validator->validated()['plate_no']);

        $vehicle = Vehicle::with('bodyType')->where('plate_no', $plateNo)->first();
        if (!$vehicle) {
            return $this->sendError('Vehicle not found with the provided plate number', [], 0, 404);
        }

        if (!$vehicle->account_no) {
            return $this->sendError('Vehicle is not associated with any account');
        }

        $account = Account::where('account_no', $vehicle->account_no)->first();
        if (!$account) {
            return $this->sendError('Account not found for this vehicle');
        }

        $priceList = PriceList::where('body_type_id', $vehicle->body_type_id)
            ->where('status', PriceList::STATUS_ACTIVE)
            ->first();

        if (!$priceList) {
            return $this->sendError('Vehicle body type not allowed for bundle');
        }

        $outstandingBill = BridgeBill::where('dist_param', $plateNo)
            ->whereNull('trx_dt_tm')
            ->where('bill_status', '!=', BridgeBill::CANCELLED)
            ->whereNotNull('contr_num')
            ->where('contr_num', '!=', '0')
            ->where('contr_num', '!=', '')
            ->where('bill_exp_dt', '>=', now())
            ->first();

        $definitions = [
            1 => ['key' => 'daily', 'name' => 'Daily Bundle', 'description' => 'Daily Bundle Subscription', 'amount' => (float) $priceList->daily_bundle_amount],
            2 => ['key' => 'weekly', 'name' => 'Weekly Bundle', 'description' => 'Weekly Bundle Subscription', 'amount' => (float) $priceList->weekly_bundle_amount],
            3 => ['key' => 'monthly', 'name' => 'Monthly Bundle', 'description' => 'Monthly Bundle Subscription', 'amount' => (float) $priceList->monthly_bundle_amount],
        ];

        $bundles = [];
        foreach ($definitions as $bundleId => $meta) {
            $amount = $meta['amount'];
            $eligible = $amount > 0;
            $bundles[] = [
                'bundle_id' => $bundleId,
                'key' => $meta['key'],
                'bundle_name' => $meta['name'],
                'bundle_description' => $meta['description'],
                'amount' => $amount,
                'currency' => 'TZS',
                'eligible' => $eligible,
                'ineligible_reason' => $eligible ? null : 'Bundle price is zero or not configured for this body type',
            ];
        }

        return $this->sendResponse([
            'plate_no' => $vehicle->plate_no,
            'vehicle_id' => $vehicle->id,
            'account_no' => $vehicle->account_no,
            'body_type' => [
                'id' => $vehicle->bodyType->id ?? null,
                'name' => $vehicle->bodyType->name ?? null,
                'description' => $vehicle->bodyType->description ?? null,
            ],
            'has_outstanding_bundle_bill' => $outstandingBill !== null,
            'outstanding_bill' => $outstandingBill,
            'bundles' => $bundles,
            'price_list_id' => $priceList->id,
        ], 'Eligible bundle types retrieved successfully');
    }

    /**
     * Paginated list of all bundle subscriptions (purchases) with vehicle, bundle, and optional bill data.
     */
    public function listBundlePurchases(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'nullable|string|max:50',
            'plate_no' => 'nullable|string|max:50',
            'vehicle_id' => 'nullable|integer|min:1',
            'bundle_id' => 'nullable|integer|min:1',
            'status' => 'nullable|integer|in:0,1,2,3',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:id,start_date,expire_date,created_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()], 422, 422);
        }

        $perPage = (int) $request->get('per_page', 25);
        $page = (int) $request->get('page', 1);

        $sortMap = [
            'id' => 'bs.id',
            'start_date' => 'bs.start_date',
            'expire_date' => 'bs.expire_date',
            'created_at' => 'bs.created_at',
        ];
        $sortByKey = $request->get('sort_by', 'created_at');
        $orderColumn = $sortMap[$sortByKey] ?? 'bs.created_at';
        $sortOrder = strtolower((string) $request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $hasBridgeBills = Schema::hasTable('bridge_bills');
        $hasSubscriptionAmount = Schema::hasColumn('bundle_subscriptions', 'amount');

        $query = DB::table('bundle_subscriptions as bs')
            ->join('vehicle as v', 'v.id', '=', 'bs.vehicle_id')
            ->leftJoin('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id');

        $select = [
            'bs.id as bundle_subscription_id',
            'bs.account_id',
            'bs.bill_id',
            'bs.vehicle_id',
            'bs.bundle_id',
            'bs.start_date',
            'bs.expire_date',
            'bs.status as subscription_status',
            'bs.created_at',
            'bs.updated_at',
            'v.plate_no',
            'tb.bundle_description',
        ];

        if ($hasBridgeBills) {
            $query->leftJoin('bridge_bills as bb', 'bb.id', '=', 'bs.bill_id');
            $select[] = 'bb.bill_amount';
            $select[] = 'bb.bill_status';
            $select[] = 'bb.contr_num';
            $select[] = 'bb.is_cancelled';
            $select[] = 'bb.trx_id';
            $select[] = 'bb.trx_dt_tm';
        }

        if ($hasSubscriptionAmount) {
            $select[] = 'bs.amount as subscription_amount';
        }

        $query->select($select);

        if ($request->filled('account_id')) {
            $query->where('bs.account_id', $request->account_id);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('bs.vehicle_id', (int) $request->vehicle_id);
        }

        if ($request->filled('plate_no')) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->plate_no)) . '%';
            $query->where('v.plate_no', 'like', $term);
        }

        if ($request->filled('bundle_id')) {
            $query->where('bs.bundle_id', (int) $request->bundle_id);
        }

        if ($request->has('status') && $request->status !== null && $request->status !== '') {
            $query->where('bs.status', (int) $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('bs.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('bs.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $query->orderBy($orderColumn, $sortOrder);

        $total = (clone $query)->count();
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $purchases = $rows->map(function ($row) use ($hasBridgeBills, $hasSubscriptionAmount) {
            $paymentStatus = $this->resolveBundlePurchasePaymentStatus($row, $hasBridgeBills);

            $amount = null;
            if ($hasBridgeBills && isset($row->bill_amount) && $row->bill_amount !== null) {
                $amount = (float) $row->bill_amount;
            } elseif ($hasSubscriptionAmount && isset($row->subscription_amount) && $row->subscription_amount !== null) {
                $amount = (float) $row->subscription_amount;
            }

            return [
                'bundle_subscription_id' => (int) $row->bundle_subscription_id,
                'account_id' => $row->account_id,
                'bill_id' => $row->bill_id !== null ? (int) $row->bill_id : null,
                'vehicle_id' => (int) $row->vehicle_id,
                'plate_no' => $row->plate_no,
                'bundle_id' => $row->bundle_id !== null ? (int) $row->bundle_id : null,
                'bundle_description' => $row->bundle_description,
                'start_date' => $row->start_date,
                'expire_date' => $row->expire_date,
                'subscription_status' => $row->subscription_status !== null ? (int) $row->subscription_status : null,
                'subscription_status_label' => $this->bundleSubscriptionStatusLabel($row->subscription_status),
                'payment_status' => $paymentStatus,
                'bill_status' => $hasBridgeBills && isset($row->bill_status) ? $row->bill_status : null,
                'amount' => $amount,
                'control_number' => $hasBridgeBills && isset($row->contr_num) ? $row->contr_num : null,
                'trx_id' => $hasBridgeBills && isset($row->trx_id) ? $row->trx_id : null,
                'trx_dt_tm' => $hasBridgeBills && isset($row->trx_dt_tm) ? $row->trx_dt_tm : null,
                'is_cancelled' => $hasBridgeBills && isset($row->is_cancelled) ? (bool) $row->is_cancelled : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        });

        $lastPage = max(1, (int) ceil($total / $perPage));

        return $this->sendResponse([
            'purchases' => $purchases->values()->all(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? ($page - 1) * $perPage + 1 : null,
                'to' => $total > 0 ? min($page * $perPage, $total) : null,
            ],
        ], 'Bundle purchases retrieved successfully');
    }

    private function bundleSubscriptionStatusLabel($status): string
    {
        $s = (string) $status;

        return [
            (string) BundleSubscription::STATUS_PENDING => 'pending',
            (string) BundleSubscription::STATUS_ACTIVE => 'active',
            (string) BundleSubscription::STATUS_INACTIVE => 'inactive',
            (string) BundleSubscription::STATUS_CANCELLED => 'cancelled',
        ][$s] ?? 'unknown';
    }

    /**
     * Paid = bridge bill has a recorded payment transaction (GePG / bank postback).
     *
     * @param  object  $row  query row
     */
    private function bridgeBillHasTransactionRecord(object $row): bool
    {
        $trxId = isset($row->trx_id) ? trim((string) $row->trx_id) : '';
        if ($trxId === '') {
            return false;
        }

        if (!isset($row->trx_dt_tm) || $row->trx_dt_tm === null) {
            return false;
        }

        return trim((string) $row->trx_dt_tm) !== '';
    }

    /**
     * @param  object  $row  query row
     */
    private function resolveBundlePurchasePaymentStatus($row, bool $hasBridgeBills): string
    {
        if (!$hasBridgeBills) {
            return 'unknown';
        }

        if ($row->bill_id === null || $row->bill_id === '') {
            return 'no_bill';
        }

        if (isset($row->is_cancelled) && $row->is_cancelled) {
            return 'cancelled';
        }

        if ($this->bridgeBillHasTransactionRecord($row)) {
            return 'paid';
        }

        return 'pending';
    }

    /**
     * Load bundle subscription for edit/transfer (legacy bridge-subscriptions/edit-bundle).
     */
    public function editBundle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:bundle_subscriptions,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first());
        }

        try {
            $data = app(BundleSubscriptionTransferService::class)
                ->getSubscriptionForEdit((int) $request->input('id'));

            return $this->sendResponse($data, 'Bundle subscription fetched successfully');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('editBundle failed', ['message' => $e->getMessage()]);

            return $this->sendError('Failed to fetch bundle subscription');
        }
    }

    /**
     * Transfer bundle subscription to a different vehicle plate (legacy bundle-sub-editing).
     * Fully audited via OwenItAuditWriter (audits table).
     */
    public function transferBundleToVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:bundle_subscriptions,id',
            'plate_no' => 'nullable|string|max:50|exists:vehicle,plate_no',
            'new_plate_no' => 'nullable|string|max:50|exists:vehicle,plate_no',
            'reason' => 'required|string|max:200',
            'user' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first());
        }

        $targetPlateNo = (string) $request->input('new_plate_no');

        if ($targetPlateNo === '') {
            return $this->sendError('Validation Error: new_plate_no is required');
        }

        $updatedBy = $request->input('user') ?? auth()->id();
        if ($updatedBy === null) {
            return $this->sendError('Authenticated user or user id is required');
        }

        try {
            $result = app(BundleSubscriptionTransferService::class)->transferToVehicle(
                (int) $request->input('id'),
                $targetPlateNo,
                (int) $updatedBy,
                (string) $request->input('reason')
            );

            return $this->sendResponse($result, 'Bundle transferred to new vehicle successfully');
        } catch (\RuntimeException $e) {
            return $this->sendError($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('transferBundleToVehicle failed', ['message' => $e->getMessage()]);

            return $this->sendError('Failed to transfer bundle subscription');
        }
    }

    /**
     * Repair overlapping bundle subscriptions for a vehicle by shifting each overlap
     * to start from the previous subscription's expiry.
     */
    public function repairOverlappingBundle(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'plate_no' => 'required|string|max:50|exists:vehicle,plate_no',
            'dry_run' => 'nullable|boolean',
        ])->validate();

        $plateNo = strtoupper(trim((string) $validated['plate_no']));
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $vehicle = Vehicle::firstWhere('plate_no', $plateNo);
        if (!$vehicle) {
            return $this->sendError('Vehicle not found with the provided plate number', [], 0, 404);
        }

        try {
            $moved = [];

            $repair = function () use ($vehicle, &$moved, $dryRun) {
                $query = DB::table('bundle_subscriptions as bs')
                    ->leftJoin('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
                    ->where('bs.vehicle_id', $vehicle->id)
                    ->where('bs.status', '!=', BundleSubscription::STATUS_CANCELLED)
                    ->where('bs.status', '!=', BundleSubscription::STATUS_INACTIVE)
                    ->orderBy('bs.start_date')
                    ->orderBy('bs.id')
                    ->select('bs.id', 'bs.bundle_id', 'bs.start_date', 'bs.expire_date', 'tb.duration', 'tb.bundle_description');

                $subscriptions = $dryRun ? $query->get() : $query->lockForUpdate()->get();

                $prevExpiry = null;

                foreach ($subscriptions as $subscription) {
                    if (empty($subscription->start_date) || empty($subscription->expire_date)) {
                        continue;
                    }

                    $start = Carbon::parse($subscription->start_date);
                    $expiry = Carbon::parse($subscription->expire_date);
                    $days = $subscription->duration !== null
                        ? max(1, (int) $subscription->duration)
                        : max(1, $start->diffInDays($expiry));

                    if (!$prevExpiry || !$start->lt($prevExpiry)) {
                        $prevExpiry = $expiry;
                        continue;
                    }

                    $newStart = $prevExpiry->copy();
                    $newExpiry = $newStart->copy()->addDays($days);

                    if (!$dryRun) {
                        DB::table('bundle_subscriptions')
                            ->where('id', $subscription->id)
                            ->update([
                                'start_date' => $newStart->toDateTimeString(),
                                'expire_date' => $newExpiry->toDateTimeString(),
                                'updated_at' => now(),
                            ]);
                    }

                    $moved[] = [
                        'id' => (int) $subscription->id,
                        'bundle_id' => $subscription->bundle_id !== null ? (int) $subscription->bundle_id : null,
                        'bundle_description' => $subscription->bundle_description,
                        'old_start_date' => $start->toDateTimeString(),
                        'old_expire_date' => $expiry->toDateTimeString(),
                        'new_start_date' => $newStart->toDateTimeString(),
                        'new_expire_date' => $newExpiry->toDateTimeString(),
                        'duration_days' => $days,
                    ];

                    $prevExpiry = $newExpiry;
                }
            };

            if ($dryRun) {
                $repair();
            } else {
                DB::transaction($repair);
            }

            $hasOverlaps = !empty($moved);
            if ($dryRun && $hasOverlaps) {
                $message = 'Dry run: overlapping bundle subscriptions preview';
            } elseif ($dryRun) {
                $message = 'Dry run: no overlapping bundle subscriptions found';
            } elseif ($hasOverlaps) {
                $message = 'Overlapping bundle subscriptions moved successfully';
            } else {
                $message = 'No overlapping bundle subscriptions found';
            }

            return $this->sendResponse([
                'plate_no' => $plateNo,
                'vehicle_id' => (int) $vehicle->id,
                'dry_run' => $dryRun,
                'moved_count' => count($moved),
                'moved_subscriptions' => $moved,
            ], $message);
        } catch (\Throwable $e) {
            Log::error('repairOverlappingBundle failed', [
                'plate_no' => $plateNo,
                'vehicle_id' => $vehicle->id,
                'dry_run' => $dryRun,
                'message' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to repair overlapping bundle subscriptions', [], 0, 500);
        }
    }

    private function resolveRequestSearch(array $post): string
    {
        $nested = data_get($post, 'search.value');
        if (is_string($nested) && trim($nested) !== '') {
            return trim($nested);
        }

        foreach (['search', 'search_term', 'q'] as $key) {
            $value = data_get($post, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * Resolve offset/limit from REST (page, per_page) or DataTables (start, length).
     * When both are sent, page/per_page wins so page 2 is not stuck at start=0.
     *
     * @return array{start: int, length: int, page: int, per_page: int}
     */
    private function resolveDataTablePaging(array $post): array
    {
        $page = data_get($post, 'page');
        if ($page !== null && $page !== '') {
            $currentPage = max(1, (int) $page);
            $perPage = max(1, (int) data_get($post, 'per_page', data_get($post, 'length', 15)));

            return [
                'start' => ($currentPage - 1) * $perPage,
                'length' => $perPage,
                'page' => $currentPage,
                'per_page' => $perPage,
            ];
        }

        $start = max(0, (int) data_get($post, 'start', 0));
        $length = data_get($post, 'length');
        if ($length === null) {
            $length = max(1, (int) data_get($post, 'per_page', 15));
        } else {
            $length = (int) $length;
        }

        $perPage = $length === -1 ? max(1, (int) data_get($post, 'per_page', 15)) : max(1, $length);

        return [
            'start' => $start,
            'length' => $length,
            'page' => $length > 0 ? (int) floor($start / $length) + 1 : 1,
            'per_page' => $perPage,
        ];
    }

    private function formatTollBundleBillRow(object $bill): array
    {
        $status = (string) ($bill->bill_status ?? '');
        $isCancelled = (int) ($bill->is_cancelled ?? 0) === 1 || $status === (string) BridgeBill::CANCELLED;
        $isPaid = !empty($bill->trx_id) || !empty($bill->trx_dt_tm) || !empty($bill->psp_receipt_num);

        return [
            'id' => $bill->id,
            'account_no' => $bill->account_no,
            'customer_name' => $bill->customer_name,
            'plate_no' => $bill->plate_no,
            'bundle_id' => $bill->bundle_id,
            'bundle_description' => $bill->bundle_description,
            'bundle_sw_desc' => $bill->sw_desc,
            'payer_name' => $bill->payer_name ?: $bill->customer_name,
            'phone_number' => $bill->pyr_cell_num ?? $bill->phone_number,
            'control_number' => $bill->contr_num,
            'contr_num' => $bill->contr_num,
            'receipt_number' => $bill->receipt_number,
            'psp_receipt_num' => $bill->psp_receipt_num,
            'bill_amount' => $bill->bill_amount !== null ? (float) $bill->bill_amount : null,
            'bill_desc' => $bill->bill_desc,
            'bill_description' => $bill->bill_desc,
            'bill_status' => $isCancelled ? 'CANCELLED' : ($isPaid ? 'PAID' : ($status === BridgeBill::REQUESTED ? 'PENDING' : $status)),
            'is_cancelled' => $isCancelled,
            'bill_generated_at' => $bill->bill_gen_at,
            'bill_expiry_at' => $bill->bill_exp_dt,
            'bill_cancel_date' => $bill->bill_cancel_date ?? null,
            'cancel_reason' => $bill->cancel_reason ?? null,
            'payment_date' => $bill->payment_date ?? null,
            'paid_amount' => $bill->paid_amt ?? null,
            't_status' => $bill->t_status ?? null,
            'error_code' => $bill->error_code ?? null,
            'created_at' => $bill->created_at ?? null,
            'updated_at' => $bill->updated_at ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_repost' => !$isCancelled && !$isPaid,
            'can_print' => true,
        ];
    }
}
