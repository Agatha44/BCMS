<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\BasicController;
use App\Services\Reports\CollectionReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CollectionReportController extends BasicController
{
    protected CollectionReportService $reports;

    public function __construct(CollectionReportService $reports)
    {
        $this->reports = $reports;
    }

    public function dailyCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->dailyCollection($request->from_date, $request->to_date);

        return $this->reportResponse($rows, 'Daily toll collection report');
    }

    public function dailyShiftCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'shift_id' => 'required|integer|in:1,2,3',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->dailyShiftCollection(
            $request->from_date,
            $request->to_date,
            (int) $request->shift_id
        );

        return $this->reportResponse($rows, 'Daily shift collection report');
    }

    public function bodyTypeCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'body_type' => 'nullable|integer',
            'operator' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->bodyTypeCollection(
            $request->from_date,
            $request->to_date,
            $request->filled('body_type') ? (int) $request->body_type : null,
            $request->filled('operator') ? (int) $request->operator : null
        );

        return $this->reportResponse($rows, 'Body type collection report');
    }

    public function boothCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'lane' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->boothCollection(
            $request->from_date,
            $request->to_date,
            $request->filled('lane') ? (int) $request->lane : null
        );

        return $this->reportResponse($rows, 'Booth collection report');
    }

    public function bodyTypeAudit(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'operator' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->bodyTypeAudit(
            $request->from_date,
            $request->to_date,
            $request->filled('operator') ? (int) $request->operator : null,
            $request->all()
        );

        return $this->paginatedReportResponse($result, 'Body type audit report');
    }

    public function exemptedVehicles(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'operator' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->exemptedVehicles(
            $request->from_date,
            $request->to_date,
            $request->filled('operator') ? (int) $request->operator : null,
            $request->all()
        );

        return $this->paginatedReportResponse($result, 'Exempted vehicles report');
    }

    public function dailyCashlessCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->dailyCashlessCollection($request->from_date, $request->to_date);

        return $this->reportResponse($rows, 'Daily cashless collection report');
    }

    public function bodyCashlessCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->bodyCashlessCollection($request->from_date, $request->to_date);

        return $this->reportResponse($rows, 'Cashless collection by body type');
    }

    public function cancelledTransactions(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'operator' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->cancelledTransactions(
            $request->from_date,
            $request->to_date,
            $request->filled('operator') ? (int) $request->operator : null,
            $request->all()
        );

        return $this->paginatedReportResponse($result, 'Cancelled transactions report', 'records');
    }

    public function bundleCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'body_type_id' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->bundleCollectionReport(
            $request->from_date,
            $request->to_date,
            $request->filled('body_type_id') ? (int) $request->body_type_id : null
        );

        return $this->reportResponse($rows, 'Bundle collection report');
    }

    public function bundleRegistration(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'options' => 'required|integer|in:1,2',
            'body_type' => 'nullable|integer',
            'operator' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->bundleRegistrationReport(
            $request->from_date,
            $request->to_date,
            (int) $request->options,
            $request->filled('body_type') ? (int) $request->body_type : null,
            $request->filled('operator') ? (int) $request->operator : null
        );

        return $this->reportResponse($rows, 'Bundle registration report', 'records');
    }

    public function bundleSubscription(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'options' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->bundleSubscriptionReport(
            $request->from_date,
            $request->to_date,
            $request->filled('options') ? (int) $request->options : null
        );

        return $this->reportResponse($rows, 'Bundle subscription report', 'records');
    }

    public function vehiclePassage(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'operator' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->vehiclePassageReport(
            $request->from_date,
            $request->to_date,
            $request->filled('operator') ? (int) $request->operator : null,
            $request->all()
        );

        return $this->paginatedReportResponse($result, 'Vehicle passage report');
    }

    public function vehiclePassagePaginated(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->queryVehiclePassage($request->all());

        return $this->sendResponse($result, 'Vehicle passage retrieved');
    }

    public function tollCollectionDetail(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'shift_id' => 'nullable|integer',
            'body_type_id' => 'nullable|integer',
            'lane' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->tollCollectionDetail($request->all());

        return $this->paginatedReportResponse($result, 'Toll collection detail report');
    }

    public function paymentReconciliation(Request $request): JsonResponse
    {
        $rows = $this->reports->paymentReconciliationList();

        return $this->reportResponse($rows, 'Payment reconciliation list');
    }

    public function endOfShiftOverall(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'shift_id' => 'required|integer',
            'shift_date' => 'required_without:counter_date|nullable|date_format:Y-m-d',
            'counter_date' => 'nullable|date_format:Y-m-d',
            'lane_id' => 'nullable|integer',
            'open_counter' => 'nullable|date',
            'close_counter' => 'nullable|date|after:open_counter',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $shiftDate = $request->input('shift_date') ?? $request->input('counter_date');

        $window = $this->reports->resolveReportWindow(
            $request->open_counter,
            $request->close_counter,
            (int) $request->shift_id,
            $shiftDate
        );

        if (!$window) {
            return $this->sendError('Unable to resolve shift window. Provide shift date and shift.', [], 0, 422);
        }

        [$open, $close] = $window;
        $laneId = $request->filled('lane_id') ? (int) $request->lane_id : null;
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;
        $rows = $this->reports->endOfShiftOverall(
            $open,
            $close,
            $userId,
            (int) $request->shift_id,
            $laneId
        );

        return $this->sendResponse([
            'rows' => $rows,
            'meta' => [
                'open_counter' => $open,
                'close_counter' => $close,
                'shift_date' => $shiftDate,
                'operator_name' => $this->reports->resolveOperatorMeta($userId) ?? 'All operators',
            ],
        ], 'End of shift overall report');
    }

    public function shiftSummary(Request $request): JsonResponse
    {
        $mode = $request->input('report_mode', 'summary');
        if ($request->boolean('audit')) {
            $mode = 'audit';
        }

        return $this->shiftWindowReport($request, $mode === 'audit' ? 'audit' : 'summary');
    }

    public function shiftSummaryAudit(Request $request): JsonResponse
    {
        $request->merge(['report_mode' => 'audit']);

        return $this->shiftWindowReport($request, 'audit');
    }

    public function tollCollectionSummary(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'collection_type' => 'required|in:bundle,cash,topUp',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->tollCollectionSummary(
            $request->from_date,
            $request->to_date,
            $request->collection_type
        );

        return $this->reportResponse($rows, 'Toll collection summary');
    }

    public function incidentCollectionSummary(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->incidentCollectionSummary($request->from_date, $request->to_date);

        return $this->reportResponse($rows, 'Incident collection summary');
    }

    public function overloadCollectionSummary(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->overloadCollectionSummary($request->from_date, $request->to_date);

        return $this->reportResponse($rows, 'Overload collection summary');
    }

    public function eventCollectionSummary(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->eventCollectionSummary($request->from_date, $request->to_date);

        return $this->reportResponse($rows, 'Event collection summary');
    }

    public function monthlyCollectionSummary(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2100',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $rows = $this->reports->monthlyCollectionSummary((int) $request->year);

        return $this->reportResponse($rows, 'Monthly collection summary');
    }

    public function shiftCollection(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'shift_id' => 'required|integer|in:1,2,3',
            'shift_date' => 'required|date_format:Y-m-d',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $result = $this->reports->shiftCollectionPerOperator(
            (int) $request->shift_id,
            $request->shift_date
        );

        $collection = collect($result['rows']);
        $page = max((int) request()->input('page', 1), 1);
        $perPage = min(max((int) request()->input('per_page', 15), 1), 200);
        $total = $collection->count();
        $slice = $collection->forPage($page, $perPage)->values();

        return $this->sendResponse([
            'rows' => $slice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, (int) ceil($total / max($perPage, 1))),
            ],
            'meta' => $result['meta'],
            'total' => $total,
        ], $collection->isEmpty() ? 'No records found' : 'Shift collection per operator');
    }

    private function shiftWindowReport(Request $request, string $type): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'shift_id' => 'required|integer',
            'shift_date' => 'required_without:counter_date|nullable|date_format:Y-m-d',
            'counter_date' => 'nullable|date_format:Y-m-d',
            'lane_id' => 'nullable|integer',
            'report_mode' => 'nullable|in:summary,audit',
            'open_counter' => 'nullable|date',
            'close_counter' => 'nullable|date|after:open_counter',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $shiftDate = $request->input('shift_date') ?? $request->input('counter_date');

        $window = $this->reports->resolveReportWindow(
            $request->open_counter,
            $request->close_counter,
            (int) $request->shift_id,
            $shiftDate
        );

        if (!$window) {
            return $this->sendError('Unable to resolve shift window. Provide shift date and shift.', [], 0, 422);
        }

        [$open, $close] = $window;
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;
        $shiftId = (int) $request->shift_id;
        $laneId = $request->filled('lane_id') ? (int) $request->lane_id : null;

        $payload = $type === 'audit'
            ? $this->reports->shiftSummaryAuditReport($open, $close, $userId, $shiftId, $laneId)
            : $this->reports->shiftSummaryReport($open, $close, $userId, $shiftId, $laneId);

        $payload['meta'] = [
            'open_counter' => $open,
            'close_counter' => $close,
            'shift_date' => $shiftDate,
            'operator_name' => $payload['operator_name'] ?? $this->reports->resolveOperatorMeta($userId) ?? 'All operators',
            'report_mode' => $type,
        ];

        if ($payload['data']->isEmpty() && ($payload['cancelled_receipts'] ?? collect())->isEmpty()) {
            return $this->sendResponse($payload, 'No records found for this counter session');
        }

        return $this->sendResponse($payload, 'Shift summary report');
    }

    private function reportResponse($rows, string $message, string $key = 'rows'): JsonResponse
    {
        $collection = collect($rows);
        $page = max((int) request()->input('page', 1), 1);
        $perPage = min(max((int) request()->input('per_page', 15), 1), 200);
        $total = $collection->count();
        $slice = $collection->forPage($page, $perPage)->values();

        return $this->sendResponse([
            $key => $slice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, (int) ceil($total / max($perPage, 1))),
            ],
            'from_date' => request('from_date'),
            'to_date' => request('to_date'),
            'total' => $total,
        ], $collection->isEmpty() ? 'No records found' : $message);
    }

    private function paginatedReportResponse(array $result, string $message, string $key = 'rows'): JsonResponse
    {
        $rows = collect($result['rows'] ?? []);
        $pagination = $result['pagination'] ?? [];

        return $this->sendResponse([
            $key => $rows,
            'pagination' => $pagination,
            'from_date' => request('from_date'),
            'to_date' => request('to_date'),
            'total' => $pagination['total'] ?? $rows->count(),
        ], $rows->isEmpty() ? 'No records found' : $message);
    }
}
