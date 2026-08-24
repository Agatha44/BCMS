<?php

namespace App\Http\Controllers\Shift;

use App\Helpers\EnvironmentHelper;
use App\Helpers\GePG;
use App\Http\Controllers\BasicController;
use App\Models\BridgeBill;
use App\Models\Counter;
use App\Models\Receipt;
use App\Models\TollTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Validator;
use Mpdf\Mpdf;

class ShiftController extends BasicController
{
    /**
     * Get wrong-shift total (preview).
     * Returns total amount and count of valid toll transactions for the counter session
     * so the operator can preview before applying the correction.
     *
     * POST /shift/get-wrong-shift-amount
     * Body: user_id (number), counter_date (Y-m-d), lane_id (number), shift_id (number)
     */
    public function getWrongShiftAmount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id'      => 'required|integer',
            'counter_date' => 'required|date_format:Y-m-d',
            'lane_id'      => 'required|integer',
            'shift_id'     => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId      = (int) $request->user_id;
        $counterDate = $request->counter_date;
        $laneId      = (int) $request->lane_id;
        $shiftId     = (int) $request->shift_id;

        $counter = Counter::where('user_id', $userId)
            ->where('lane_id', $laneId)
            ->whereDate('created_at', $counterDate)
            ->where('shift_id', $shiftId)
            ->orderByDesc('created_at')
            ->first();

        if (!$counter) {
            return response()->json([
                'status'  => 0,
                'message' => 'Counter not found',
            ], 404);
        }

        if ($counter->open_counter === null || $counter->close_counter === null) {
            return response()->json([
                'status'  => 0,
                'message' => 'Counter has no open or close time; cannot compute wrong-shift total.',
            ], 422);
        }

        $currentShiftId = $counter->shift_id;
        $openCounter   = $counter->open_counter;
        $closeCounter   = $counter->close_counter;

        $aggregate = TollTransaction::where('created_by', $userId)
            ->where('created_at', '>=', $openCounter)
            ->where('created_at', '<=', $closeCounter)
            ->where('shift_id', $currentShiftId)
            ->whereNull('status')
            ->selectRaw('COALESCE(SUM(charged_amount), 0) AS total_amount, COUNT(*) AS transaction_count')
            ->first();

        $totalAmount        = (int) ($aggregate->total_amount ?? 0);
        $transactionCount   = (int) ($aggregate->transaction_count ?? 0);

        return response()->json([
            'status'               => 1,
            'counter_id'           => $counter->id,
            'user_id'              => $userId,
            'lane_id'              => $laneId,
            'counter_date'         => $counterDate,
            'requested_shift_id'   => $shiftId,
            'current_shift_id'     => $currentShiftId,
            'open_counter'         => $openCounter,
            'close_counter'        => $closeCounter,
            'total_amount'         => $totalAmount,
            'transaction_count'    => $transactionCount,
        ]);
    }

    /**
     * Update wrong shift (apply correction).
     * Updates the counter and all its toll transactions to the correct shift_id.
     *
     * POST /shift/update-wrong-shift
     * Body: user_id (number), counter_date (Y-m-d), lane_id (number), shift_id (number), new_shift_id (number)
     */
    public function updateWrongShift(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id'       => 'required|integer',
            'counter_date'  => 'required|date_format:Y-m-d',
            'lane_id'       => 'required|integer',
            'shift_id'      => 'required|integer',
            'new_shift_id'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId      = (int) $request->user_id;
        $counterDate = $request->counter_date;
        $laneId      = (int) $request->lane_id;
        $shiftId     = (int) $request->shift_id;
        $newShiftId  = (int) $request->new_shift_id;

        $counter = Counter::where('user_id', $userId)
            ->where('lane_id', $laneId)
            ->whereDate('created_at', $counterDate)
            ->where('shift_id', $shiftId)
            ->orderByDesc('created_at')
            ->first();

        if (!$counter) {
            return response()->json([
                'status'  => 0,
                'message' => 'Counter not found',
            ], 404);
        }

        if ($counter->open_counter === null || $counter->close_counter === null) {
            return response()->json([
                'status'  => 0,
                'message' => 'Counter has no open or close time; cannot apply correction.',
            ], 422);
        }

        $oldShiftId   = $counter->shift_id;
        $openCounter  = $counter->open_counter;
        $closeCounter = $counter->close_counter;

        try {
            DB::beginTransaction();

            $counter->shift_id = $newShiftId;
            $counter->save();

            $numberUpdated = TollTransaction::where('created_by', $userId)
                ->where('created_at', '>=', $openCounter)
                ->where('created_at', '<=', $closeCounter)
                ->where('shift_id', $oldShiftId)
                ->update(['shift_id' => $newShiftId]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 0,
                'message' => 'Failed to apply shift correction: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'                      => 1,
            'message'                     => 'Shift corrected successfully',
            'counter_id'                  => $counter->id,
            'old_shift_id'                => $oldShiftId,
            'new_shift_id'                => $newShiftId,
            'open_counter'                => $openCounter,
            'close_counter'               => $closeCounter,
            'number_of_transactions_updated' => $numberUpdated,
            'toll_transactions_updated'   => $numberUpdated,
        ]);
    }

    public function shifts(): JsonResponse
    {
        try {
            $columns = ['id', 'name'];
            $hasTimes = Schema::hasColumn('shift', 'shift_start_time')
                && Schema::hasColumn('shift', 'shift_end_time');

            if ($hasTimes) {
                $columns[] = 'shift_start_time';
                $columns[] = 'shift_end_time';
            }

            $formatTime = static function ($time): ?string {
                if ($time === null || $time === '') {
                    return null;
                }

                if (is_string($time)) {
                    $normalized = strlen($time) === 5 ? $time . ':00' : $time;

                    return substr($normalized, 0, 5);
                }

                if ($time instanceof \DateTimeInterface) {
                    return $time->format('H:i');
                }

                return null;
            };

            $rows = DB::table('shift')
                ->select($columns)
                ->orderBy('id')
                ->get()
                ->map(static function ($row) use ($hasTimes, $formatTime) {
                    $item = [
                        'id' => $row->id,
                        'name' => $row->name,
                    ];

                    if ($hasTimes) {
                        $item['shift_start_time'] = $formatTime($row->shift_start_time ?? null);
                        $item['shift_end_time'] = $formatTime($row->shift_end_time ?? null);
                    }

                    return $item;
                })
                ->values()
                ->all();

            return $this->sendResponse($rows, 'Shifts retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve shifts', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to retrieve shifts', [], 0, 500);
        }
    }

    public function shiftAmount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shift_id' => 'required|integer',
            'shift_date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $shiftId = (int) $request->input('shift_id');
        $shiftDate = $request->input('shift_date');

        try {
            $variance = DB::table('variance')
                ->where('shift_date', $shiftDate)
                ->where('shift', $shiftId)
                ->where('status', 1)
                ->value('amount');

            if ($shiftId === 3) {
                $fromDate = $shiftDate . ' 18:00:00';
                $toDate = Carbon::parse($shiftDate)->addDay()->format('Y-m-d') . ' 09:00:00';
                $amount = DB::table('toll_transaction')
                    ->where('shift_id', $shiftId)
                    ->whereNull('status')
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->sum('charged_amount');
            } else {
                $amount = DB::table('toll_transaction')
                    ->where('shift_id', $shiftId)
                    ->whereNull('status')
                    ->whereDate('created_at', $shiftDate)
                    ->sum('charged_amount');
            }

            $amount = (float) ($amount ?? 0);
            if ($amount <= 0) {
                return $this->sendError('This shift has zero amount for the selected date');
            }

            $shiftName = DB::table('shift')->where('id', $shiftId)->value('name');

            return $this->sendResponse([
                'shift_id' => $shiftId,
                'shift_date' => $shiftDate,
                'shift_name' => $shiftName,
                'billing_amount' => $amount,
                'billingAmount' => $amount,
                'sum_per_shift' => $amount,
                'variance' => $variance !== null ? (float) $variance : 0,
            ], 'Shift amount retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve shift amount', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to retrieve shift amount', [], 0, 500);
        }
    }

    public function billsIndex(Request $request): JsonResponse
    {
        try {
            $post = $request->input('post_value', $request->all());
            $page = max(1, (int) data_get($post, 'page', 1));
            $perPage = max(1, (int) data_get($post, 'per_page', data_get($post, 'length', 10)));
            $start = data_get($post, 'start');
            if ($start !== null) {
                $page = ((int) $start / $perPage) + 1;
            }

            $baseQuery = DB::table('bridge_bills as bb')
                ->leftJoin('shift as s', 's.id', '=', 'bb.shift_id')
                ->whereNull('bb.dist_param')
                ->where('bb.bill_status', BridgeBill::REQUESTED);

            $search = trim((string) (data_get($post, 'search.value') ?? data_get($post, 'search', '')));
            if ($search !== '') {
                $baseQuery->where(function ($query) use ($search) {
                    $query->where('bb.receipt_number', 'like', "%{$search}%")
                        ->orWhere('bb.contr_num', 'like', "%{$search}%")
                        ->orWhere('bb.psp_receipt_num', 'like', "%{$search}%")
                        ->orWhere('bb.shift_date', 'like', "%{$search}%")
                        ->orWhere('s.name', 'like', "%{$search}%")
                        ->orWhere('bb.payer_name', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();
            $recordsTotal = DB::table('bridge_bills')
                ->whereNull('dist_param')
                ->where('bill_status', BridgeBill::REQUESTED)
                ->count();

            $rows = (clone $baseQuery)
                ->select([
                    'bb.id',
                    'bb.shift_id',
                    'bb.shift_date',
                    'bb.contr_num',
                    'bb.receipt_number',
                    'bb.psp_receipt_num',
                    'bb.bill_amount',
                    'bb.bill_desc',
                    'bb.bill_status',
                    'bb.is_cancelled',
                    'bb.bill_gen_at',
                    'bb.payer_name',
                    'bb.t_status',
                    'bb.trx_id',
                    's.name as shift_name',
                ])
                ->orderByDesc('bb.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(fn ($row) => $this->formatShiftBillRow($row));

            return $this->sendResponse([
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $recordsFiltered,
                'last_page' => max(1, (int) ceil($recordsFiltered / $perPage)),
                'data' => $rows,
            ], 'End of shift bills retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve end of shift bills', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to retrieve end of shift bills', [], 0, 500);
        }
    }

    public function createShiftBill(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shift_id' => 'required|integer',
            'shift_date' => 'required|date_format:Y-m-d',
            'bill_amount' => 'required|numeric|min:1',
            'bill_desc' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $data = $validator->validated();
        $userId = Auth::id() ?? ($data['user_id'] ?? null);
        $user = $userId ? DB::table('auth_user')->where('id', $userId)->first() : null;
        $payerName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->surname ?? '')) : 'Shift Billing';
        $phone = $user->phone ?? '0000000000';

        try {
            DB::beginTransaction();

            $now = Carbon::now('Africa/Dar_es_Salaam');
            $expiresAt = $now->copy()->addDays(31);
            $shiftName = DB::table('shift')->where('id', $data['shift_id'])->value('name');
            $description = $data['bill_desc'] ?? ('End of shift bill for ' . ($shiftName ?? 'shift') . ' on ' . $data['shift_date']);

            $bill = BridgeBill::create([
                'bill_amount' => $data['bill_amount'],
                'bill_desc' => $description,
                'phone_number' => preg_replace('/\D/', '', $phone) ?: null,
                'bill_gen_by' => $userId,
                'bill_status' => BridgeBill::REQUESTED,
                'shift_id' => $data['shift_id'],
                'shift_date' => $data['shift_date'],
                'payer_name' => $payerName,
                'pyr_cell_num' => $phone,
                'bill_gen_at' => $now->format('Y-m-d H:i:s'),
                'bill_exp_dt' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $billRef = 'SHB' . $bill->id;
            $gepgParams = [
                'payment_ref' => $billRef,
                'amount' => (string) $data['bill_amount'],
                'equiv_amount' => (string) $data['bill_amount'],
                'bill_desc' => $description,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => preg_replace('/\D/', '', $phone) ?: $billRef,
                'payer_name' => $payerName,
                'payer_cell' => GePG::normalizePayerCell($phone),
                'generated_by' => (string) ($userId ?? 'SYSTEM'),
                'days_expires_after' => 31,
                'payer_email' => $user->email ?? 'billing@nssf.go.tz',
                'bill_gen_date' => Carbon::parse($bill->bill_gen_at)->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($bill->bill_exp_dt)->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);
            if (($gepgResponse['status'] ?? null) !== 'success') {
                $bill->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['message'] ?? ($gepgResponse['error_code'] ?? 'Unknown Error'),
                ]);
                DB::rollBack();

                return $this->sendError('Failed to process shift bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

            if (!empty($gepgResponse['control_num'])) {
                $bill->update([
                    'contr_num' => $gepgResponse['control_num'],
                    't_status' => 'SP',
                ]);
            }

            $bill->refresh();
            DB::commit();

            return $this->sendResponse([
                'bill_id' => $bill->id,
                'payment_ref' => $billRef,
                'bill_amount' => (float) $bill->bill_amount,
                'control_number' => $bill->contr_num ?? 0,
            ], 'Control Number Request Successfully Sent');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create shift bill', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to create shift bill. Please try again or contact support.');
        }
    }

    public function cancelBill(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $bill = BridgeBill::query()
            ->whereNull('dist_param')
            ->find((int) $request->input('id'));

        if (!$bill) {
            return $this->sendError('Shift bill not found');
        }

        if ($bill->trx_id !== null) {
            return $this->sendError('Paid shift bills cannot be cancelled');
        }

        if (!empty($bill->psp_receipt_num)) {
            return $this->sendError('Bills with payment receipt cannot be cancelled from this screen');
        }

        $paymentRef = 'SHB' . $bill->id;
        if (!empty($bill->contr_num) && $bill->contr_num !== '0') {
            $cancelResponse = $this->cancelWithGateway($paymentRef);
            if (!$cancelResponse['success']) {
                return $this->sendError($cancelResponse['message']);
            }
        }

        $now = Carbon::now('Africa/Dar_es_Salaam');
        $bill->update([
            'is_cancelled' => 1,
            'bill_status' => BridgeBill::CANCELLED,
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled from BMS'),
            'bill_cancel_date' => $now->format('Y-m-d H:i:s'),
            'bill_cancel_by' => Auth::id(),
            'psp_receipt_num' => 'CANC' . $now->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);

        return $this->sendResponse($this->formatShiftBillRow($bill->fresh()), 'Shift bill cancelled successfully');
    }

    public function reuseBill(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'reuse_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $bill = BridgeBill::query()
            ->whereNull('dist_param')
            ->find((int) $request->input('id'));

        if (!$bill) {
            return $this->sendError('Shift bill not found');
        }

        if (empty($bill->psp_receipt_num)) {
            return $this->sendError('Only paid shift bills can be reused');
        }

        try {
            $billRef = 'SHB' . $bill->id;
            $gepgParams = [
                'payment_ref' => $billRef,
                'amount' => (string) $bill->bill_amount,
                'equiv_amount' => (string) $bill->bill_amount,
                'bill_desc' => $request->input('reuse_reason', $bill->bill_desc ?? 'Shift bill reuse'),
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $bill->pyr_cell_num ?? $billRef,
                'payer_name' => $bill->payer_name ?? 'Shift Billing',
                'payer_cell' => GePG::normalizePayerCell((string) ($bill->pyr_cell_num ?? $billRef)),
                'generated_by' => (string) (Auth::id() ?? 'SYSTEM'),
                'days_expires_after' => 31,
                'payer_email' => 'billing@nssf.go.tz',
                'bill_gen_date' => Carbon::parse($bill->bill_gen_at ?? now())->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($bill->bill_exp_dt ?? now()->addDays(31))->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);
            if (($gepgResponse['status'] ?? null) !== 'success') {
                $bill->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['message'] ?? 'Unknown Error',
                ]);

                return $this->sendError('Failed to reuse shift bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

            $bill->update([
                't_status' => 'SP',
                'contr_num' => $gepgResponse['control_num'] ?? $bill->contr_num,
                'psp_receipt_num' => null,
                'trx_id' => null,
                'updated_at' => now(),
            ]);

            return $this->sendResponse($this->formatShiftBillRow($bill->fresh()), 'Shift bill reuse request sent successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to reuse shift bill', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to reuse shift bill');
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
            ->leftJoin('shift as s', 's.id', '=', 'bb.shift_id')
            ->where('bb.id', (int) $request->input('id'))
            ->whereNull('bb.dist_param')
            ->select([
                'bb.id',
                'bb.payer_name',
                'bb.pyr_cell_num',
                'bb.bill_exp_dt',
                'bb.bill_amount',
                'bb.contr_num',
                'bb.bill_desc',
                'bb.shift_date',
                'bb.shift_id',
                's.name as shift_name',
            ])
            ->first();

        if (!$bill) {
            return $this->sendError('Shift bill not found');
        }

        return $this->sendResponse($this->formatShiftBillRow($bill), 'Shift order form retrieved successfully');
    }

    public function receiptsIndex(Request $request): JsonResponse
    {
        try {
            $post = $request->input('post_value', $request->all());
            $page = max(1, (int) data_get($post, 'page', 1));
            $perPage = max(1, (int) data_get($post, 'per_page', data_get($post, 'length', 10)));

            $baseQuery = DB::table('end_of_shift as es')
                ->leftJoin('shift as s', 's.id', '=', 'es.shift_id')
                ->where('es.status', 1);

            $search = trim((string) (data_get($post, 'search.value') ?? data_get($post, 'search', '')));
            if ($search !== '') {
                $baseQuery->where(function ($query) use ($search) {
                    $query->where('es.receipt_number', 'like', "%{$search}%")
                        ->orWhere('es.bank_receipt', 'like', "%{$search}%")
                        ->orWhere('es.accountant', 'like', "%{$search}%")
                        ->orWhere('es.shift_date', 'like', "%{$search}%")
                        ->orWhere('s.name', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();
            $recordsTotal = DB::table('end_of_shift')->where('status', 1)->count();

            $rows = (clone $baseQuery)
                ->select([
                    'es.id',
                    'es.receipt_number',
                    'es.receipt_date',
                    'es.bank_date',
                    'es.bank_receipt',
                    'es.amount',
                    'es.accountant',
                    'es.shift_date',
                    'es.shift_id',
                    's.name as shift_name',
                    'es.cancel_reason',
                ])
                ->orderByDesc('es.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(fn ($row) => $this->formatReceiptRow($row));

            return $this->sendResponse([
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $recordsFiltered,
                'last_page' => max(1, (int) ceil($recordsFiltered / $perPage)),
                'data' => $rows,
            ], 'End of shift receipts retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve end of shift receipts', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to retrieve end of shift receipts', [], 0, 500);
        }
    }

    public function postErpReceipt(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shift_id' => 'required|integer',
            'shift_date' => 'required|date_format:Y-m-d',
            'bank_date' => 'required|date',
            'bank_receipt' => 'required|string|max:100',
            'bank_amount' => 'required|numeric|min:1',
            'created_by' => 'nullable',
            'customer_name' => 'nullable|string|max:50',
            'activity' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:500',
            'receipt_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $data = $validator->validated();
        $shiftName = DB::table('shift')->where('id', $data['shift_id'])->value('name') ?? 'Shift';
        $createdBy = (string) (Auth::id() ?? ($data['created_by'] ?? 'SYSTEM'));
        $customerName = substr($data['customer_name'] ?? 'NSSF', 0, 30);

        try {
            DB::beginTransaction();

            $receipt = Receipt::create(['prefix' => 'B']);
            $receiptNumber = 'B' . $receipt->number;
            $now = Carbon::now('Africa/Dar_es_Salaam');

            if (!empty($data['receipt_id'])) {
                DB::table('end_of_shift')
                    ->where('id', $data['receipt_id'])
                    ->update([
                        'receipt_number' => $receiptNumber,
                        'receipt_date' => $now->format('Y-m-d H:i:s'),
                        'shift_id' => $data['shift_id'],
                        'shift_date' => $data['shift_date'],
                        'bank_date' => Carbon::parse($data['bank_date'])->format('Y-m-d H:i:s'),
                        'updated_at' => $now,
                        'accountant' => $createdBy,
                        'updated_by' => $createdBy,
                        'cancel_reason' => $data['reason'] ?? null,
                    ]);
                $endOfShiftId = (int) $data['receipt_id'];
            } else {
                $endOfShiftId = DB::table('end_of_shift')->insertGetId([
                    'receipt_number' => $receiptNumber,
                    'receipt_date' => $now->format('Y-m-d H:i:s'),
                    'shift_id' => $data['shift_id'],
                    'shift_date' => $data['shift_date'],
                    'bank_date' => Carbon::parse($data['bank_date'])->format('Y-m-d H:i:s'),
                    'created_at' => $now,
                    'accountant' => $createdBy,
                ]);
            }

            $erpPosted = $this->postShiftToErp([
                'end_of_shift_id' => $endOfShiftId,
                'shift_name' => $shiftName,
                'shift_date' => $data['shift_date'],
                'bank_date' => Carbon::parse($data['bank_date'])->format('Y-m-d H:i:s'),
                'bank_amount' => $data['bank_amount'],
                'bank_receipt' => $data['bank_receipt'],
                'receipt_no' => $receiptNumber,
                'created_by' => $createdBy,
                'customer_name' => $customerName,
                'activity' => $data['activity'] ?? 'End of shift',
            ]);

            if (!$erpPosted['success']) {
                DB::rollBack();

                return $this->sendError($erpPosted['message'] ?? 'Failed to post shift receipt to ERP');
            }

            DB::table('end_of_shift')
                ->where('id', $endOfShiftId)
                ->update([
                    'status' => 1,
                    'amount' => (int) $data['bank_amount'],
                    'bank_receipt' => $data['bank_receipt'],
                    'updated_at' => $now,
                ]);

            DB::commit();

            return $this->sendResponse([
                'end_of_shift_id' => $endOfShiftId,
                'receipt_number' => $receiptNumber,
                'bank_amount' => (float) $data['bank_amount'],
                'bank_receipt' => $data['bank_receipt'],
            ], 'Successfully posted end of shift to ERP');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to post end of shift ERP receipt', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to process end of shift. Please try again or contact support.');
        }
    }

    public function receiptDetails($id): JsonResponse
    {
        $row = DB::table('end_of_shift')->where('id', $id)->first();
        if (!$row) {
            return $this->sendError('End of shift receipt not found');
        }

        return $this->sendResponse($this->formatReceiptRow($row), 'End of shift receipt retrieved successfully');
    }

    /**
     * Print end-of-shift ERP receipt (Miscellaneous Receipt) — same layout as bridge-app.
     */
    public function printReceipt($id): Response
    {
        try {
            $row = DB::table('end_of_shift as es')
                ->leftJoin('shift as s', 's.id', '=', 'es.shift_id')
                ->where('es.id', (int) $id)
                ->where('es.status', 1)
                ->select([
                    'es.*',
                    's.name as shift_name',
                ])
                ->first();

            if (!$row) {
                return response('End of shift receipt not found or not yet processed.', 404)
                    ->header('Content-Type', 'text/html');
            }

            if (empty($row->receipt_number)) {
                return response('Receipt number is not available for this record.', 400)
                    ->header('Content-Type', 'text/html');
            }

            $amount = round((float) ($row->amount ?? 0), 2);
            if ($amount <= 0) {
                return response('Receipt amount is not available.', 400)
                    ->header('Content-Type', 'text/html');
            }

            $receiptData = (array) $row;
            $amountWord = ucwords($this->numberToWords((int) round($amount)));
            $shiftName = $row->shift_name ?: $this->resolveShiftName((int) ($row->shift_id ?? 0));

            $receiptDateFormatted = $row->receipt_date
                ? Carbon::parse($row->receipt_date)->format('d-M-Y')
                : '';
            $bankDateFormatted = $row->bank_date
                ? Carbon::parse($row->bank_date)->format('d-M-Y')
                : '';

            $logoPath = public_path('images/nssf-log1.png');
            if (!file_exists($logoPath)) {
                $logoPath = public_path('images/logo.png');
            }
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }

            $html = View::make('receipts.end_of_shift_receipt', [
                'receipt_data' => $receiptData,
                'amount' => $amount,
                'amount_word' => $amountWord,
                'shift_name' => $shiftName,
                'receipt_date_formatted' => $receiptDateFormatted,
                'bank_date_formatted' => $bankDateFormatted,
                'logoBase64' => $logoBase64,
            ])->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 20,
                'margin_header' => 10,
                'margin_footer' => 10,
            ]);

            $mpdf->showImageErrors = true;

            if (file_exists($logoPath)) {
                $mpdf->SetWatermarkImage($logoPath, 0.05);
                $mpdf->showWatermarkImage = true;
            }

            $printedBy = 'System';
            $user = Auth::user();
            if ($user) {
                $printedBy = trim(
                    ($user->first_name ?? '') . ' ' . ($user->middle_name ?? '') . ' ' . ($user->surname ?? '')
                ) ?: (string) ($user->name ?? $user->email ?? 'System');
            }

            $mpdf->SetProtection(['print']);
            $mpdf->SetTitle(($row->receipt_number ?? 'Receipt') . ' - Receipt');
            $mpdf->SetAuthor('NSSF');
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->SetHTMLFooter('
            <table width="100%">
                <tr>
                    <td width="50%">Printed by: ' . htmlspecialchars($printedBy) . '</td>
                    <td width="50%" style="text-align: right;">Printed on: {DATE j-m-Y}</td>
                </tr>
            </table>');

            $headerPdf = public_path('receipt-header.pdf');
            if (file_exists($headerPdf)) {
                $pageCount = $mpdf->SetSourceFile($headerPdf);
                $tplId = $mpdf->ImportPage($pageCount);
                $mpdf->UseTemplate($tplId);
            }

            $mpdf->WriteHTML($html);

            $filename = ($row->receipt_number ?? 'eos_receipt') . '-' . random_int(1000, 9999) . '.pdf';

            return response($mpdf->Output($filename, 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error generating end of shift receipt PDF', [
                'receipt_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Failed to generate receipt: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/html');
        }
    }

    private function resolveShiftName(int $shiftId): string
    {
        return match ($shiftId) {
            1 => 'Morning Shift',
            2 => 'Afternoon Shift',
            3 => 'Evening Shift',
            default => 'Undefined Shift Name',
        };
    }

    private function numberToWords(int $number): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

        if ($number === 0) {
            return 'zero';
        }
        if ($number < 10) {
            return strtolower($ones[$number]);
        }
        if ($number < 20) {
            return strtolower($teens[$number - 10]);
        }
        if ($number < 100) {
            $tensPart = $tens[intdiv($number, 10)];
            $onesPart = $ones[$number % 10];

            return strtolower($tensPart . ($onesPart ? ' ' . $onesPart : ''));
        }
        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $remainder = $number % 100;

            return strtolower($ones[$hundreds] . ' hundred' . ($remainder ? ' ' . $this->numberToWords($remainder) : ''));
        }
        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;

            return strtolower($this->numberToWords($thousands) . ' thousand' . ($remainder ? ' ' . $this->numberToWords($remainder) : ''));
        }
        if ($number < 1000000000) {
            $millions = intdiv($number, 1000000);
            $remainder = $number % 1000000;

            return strtolower($this->numberToWords($millions) . ' million' . ($remainder ? ' ' . $this->numberToWords($remainder) : ''));
        }

        return 'number too large';
    }

    private function postShiftToErp(array $payload): array
    {
        try {
            if (!config('database.connections.oracle')) {
                return ['success' => true, 'message' => 'ERP connection not configured; end of shift saved locally'];
            }

            $comment = 'End of ' . $payload['shift_name'] . ' Shift of ' . $payload['shift_date'];
            $format = 'YYYY-MM-DD HH24:MI:SS';

            $sql = "INSERT INTO INTERFACE.NSSF_NEW_AR_PAY_INT_MISC
                (DEPOSIT_DATE, REMITTANCE_AMOUNT, CHECK_NUMBER, CURRENCY_CODE,
                 ATTRIBUTE1, ATTRIBUTE2, ATTRIBUTE3, RECEIPT_METHOD, RECEIPT_METHOD_ID,
                 CREATED_BY, CUSTOMER_NAME, ACTIVITY, COMMENTS)
                VALUES (
                    to_date(:deposit_date, :format),
                    :amount,
                    substr(:check_number, 1, 30),
                    'TZS',
                    :attr1, :attr2, :attr3,
                    'Azania Masdo GEPG', 11118,
                    :created_by, :customer_name, :activity, :comments
                )";

            DB::connection('oracle')->insert($sql, [
                'deposit_date' => $payload['bank_date'],
                'format' => $format,
                'amount' => (string) $payload['bank_amount'],
                'check_number' => $payload['bank_receipt'],
                'attr1' => $payload['receipt_no'],
                'attr2' => $payload['receipt_no'],
                'attr3' => $payload['receipt_no'],
                'created_by' => $payload['created_by'],
                'customer_name' => $payload['customer_name'],
                'activity' => $payload['activity'],
                'comments' => $comment,
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::warning('ERP post skipped or failed for end of shift', ['error' => $e->getMessage()]);

            return [
                'success' => true,
                'message' => 'End of shift saved; ERP post skipped: ' . $e->getMessage(),
            ];
        }
    }

    private function cancelWithGateway(string $paymentRef): array
    {
        try {
            $response = Http::delete(EnvironmentHelper::GePGBaseUrl() . '/bills/' . $paymentRef);
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway cancellation failed. Status: ' . $response->status(),
                ];
            }

            $payload = $response->json();
            $message = $payload['message'] ?? null;
            if ($message === 7204 || $message === '7204' || $message === 'Successful') {
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => 'Payment gateway cancellation failed. Code: ' . ($message ?? 'Unknown'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Payment gateway request failed. Please try again or contact support.',
            ];
        }
    }

    private function formatShiftBillRow(object $bill): array
    {
        $isCancelled = (int) ($bill->is_cancelled ?? 0) === 1;
        $isPaid = !empty($bill->trx_id) || !empty($bill->psp_receipt_num);

        return [
            'id' => $bill->id,
            'shift_id' => $bill->shift_id ?? null,
            'shift_date' => $bill->shift_date ?? null,
            'shift_name' => $bill->shift_name ?? null,
            'control_number' => $bill->contr_num ?? null,
            'control_num' => $bill->contr_num ?? null,
            'contr_num' => $bill->contr_num ?? null,
            'receipt_number' => $bill->receipt_number ?? null,
            'receipt_no' => $bill->receipt_number ?? null,
            'psp_receipt_num' => $bill->psp_receipt_num ?? null,
            'bank_receipt' => $bill->psp_receipt_num ?? null,
            'bill_amount' => $bill->bill_amount !== null ? (float) $bill->bill_amount : null,
            'amount' => $bill->bill_amount !== null ? (float) $bill->bill_amount : null,
            'bill_desc' => $bill->bill_desc ?? null,
            'bill_status' => $isCancelled ? 'CANCELLED' : ($isPaid ? 'PAID' : 'PENDING'),
            'is_cancelled' => $isCancelled,
            'bill_generated_at' => $bill->bill_gen_at ?? null,
            'bill_exp_dt' => $bill->bill_exp_dt ?? null,
            'bill_expiry_at' => $bill->bill_exp_dt ?? null,
            'payer_name' => $bill->payer_name ?? null,
            'pyr_cell_num' => $bill->pyr_cell_num ?? null,
            'phone_number' => $bill->pyr_cell_num ?? ($bill->phone_number ?? null),
            't_status' => $bill->t_status ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_reuse' => !$isCancelled && $isPaid,
            'can_print' => true,
        ];
    }

    private function formatReceiptRow(object $row): array
    {
        return [
            'id' => $row->id,
            'receipt_number' => $row->receipt_number ?? null,
            'receipt_date' => $row->receipt_date ?? null,
            'bank_date' => $row->bank_date ?? null,
            'bank_receipt' => $row->bank_receipt ?? null,
            'amount' => $row->amount !== null ? (float) $row->amount : null,
            'bill_amount' => $row->amount !== null ? (float) $row->amount : null,
            'accountant' => $row->accountant ?? null,
            'shift_date' => $row->shift_date ?? null,
            'shift_id' => $row->shift_id ?? null,
            'shift_name' => $row->shift_name ?? null,
            'cancel_reason' => $row->cancel_reason ?? null,
        ];
    }
}
