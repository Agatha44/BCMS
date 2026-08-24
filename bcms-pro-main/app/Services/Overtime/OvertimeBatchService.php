<?php

namespace App\Services\Overtime;

use App\Models\Bms\OvertimeBatch;
use App\Models\Bms\OvertimeRequest;
use App\Models\Bms\OvertimeRequestHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OvertimeBatchService
{
    private OvertimeRequestService $overtimeRequestService;
    private OvertimeQueryService $overtimeQueryService;
    private OvertimeWorkflowService $overtimeWorkflowService;
    private OvertimeErmsSubmissionService $overtimeErmsSubmissionService;
    private OvertimeSubmissionDocumentService $overtimeSubmissionDocumentService;

    public function __construct(
        OvertimeRequestService $overtimeRequestService,
        OvertimeQueryService $overtimeQueryService,
        OvertimeWorkflowService $overtimeWorkflowService,
        OvertimeErmsSubmissionService $overtimeErmsSubmissionService,
        OvertimeSubmissionDocumentService $overtimeSubmissionDocumentService
    ) {
        $this->overtimeRequestService = $overtimeRequestService;
        $this->overtimeQueryService = $overtimeQueryService;
        $this->overtimeWorkflowService = $overtimeWorkflowService;
        $this->overtimeErmsSubmissionService = $overtimeErmsSubmissionService;
        $this->overtimeSubmissionDocumentService = $overtimeSubmissionDocumentService;
    }

    /**
     * @return array{success: true, data: array}|array{success: false, error: string, http: int}
     */
    public function getReadyForBatch(array $filters): array
    {
        try {
            $query = OvertimeRequest::select(
                'overtime_requests.id',
                'overtime_requests.pf_number',
                'overtime_requests.month',
                'overtime_requests.total_overtime_hours',
                'overtime_requests.total_days',
                'overtime_requests.total_amount',
                'overtime_requests.workflow_status',
                'overtime_requests.status',
                'overtime_requests.created_at',
                'bridge_employee.fname',
                'bridge_employee.mname',
                'bridge_employee.sname'
            )
                ->leftJoin('bridge_employee', function ($join) {
                    $join->on(DB::raw('overtime_requests.pf_number'), '=', DB::raw('bridge_employee.pfno'));
                })
                ->where('overtime_requests.status', 'Reviewer Approved')
                ->whereNull('overtime_requests.batch_id')
                ->with(['days']);

            if (!empty($filters['pf_number'])) {
                $query->where('overtime_requests.pf_number', $filters['pf_number']);
            }

            if (!empty($filters['month'])) {
                $query->where('overtime_requests.month', $filters['month'] . '-01');
            }

            $overtimeRequests = $query->orderBy('overtime_requests.created_at', 'desc')->get();

            $data = $overtimeRequests->map(function ($request) {
                $employeeName = trim(($request->fname ?? '') . ' ' . ($request->mname ?? '') . ' ' . ($request->sname ?? ''));
                $monthDate = Carbon::parse($request->month);
                $workflowStatus = $this->overtimeQueryService->mapToWorkflowStatus($request->status);

                return [
                    'id' => $request->id,
                    'pfNumber' => $request->pf_number,
                    'employeeName' => $employeeName,
                    'month' => $monthDate->format('Y-m'),
                    'totalOvertimeHours' => (float) $request->total_overtime_hours,
                    'totalDays' => $request->total_days,
                    'totalAmount' => (float) $request->total_amount,
                    'status' => $request->status,
                    'workflowStatus' => $workflowStatus,
                ];
            });

            return [
                'success' => true,
                'data' => $data->values()->all(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve overtime requests ready for batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve overtime requests ready for batch: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data: array}|array{success: false, error: string, http: int}
     */
    public function getBatch(int $batchId): array
    {
        try {
            $batch = OvertimeBatch::with(['overtimeRequests.days'])->find($batchId);

            if (!$batch) {
                return [
                    'success' => false,
                    'error' => 'Batch not found',
                    'http' => 404,
                ];
            }

            $employees = $this->overtimeQueryService->employeesKeyedByOvertimeRequests($batch->overtimeRequests);
            $ermsSubmission = $this->overtimeErmsSubmissionService->submissionStatusForBatch($batch);

            return [
                'success' => true,
                'data' => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'batch_name' => $batch->batch_name,
                    'status' => $batch->status,
                    'total_amount' => (float) $batch->total_amount,
                    'total_requests' => $batch->total_requests,
                    'external_batch_id' => $batch->external_batch_id,
                    'external_payment_request_id' => $batch->external_payment_request_id,
                    'submitted_at' => $batch->submitted_at,
                    'completed_at' => $batch->completed_at,
                    'created_at' => $batch->created_at,
                    'erms_status' => $ermsSubmission['erms_status'],
                    'payment_reference' => $ermsSubmission['payment_reference'],
                    'payment_status' => $ermsSubmission['payment_status'],
                    'can_repost' => $ermsSubmission['can_repost'],
                    'submission_readiness' => $this->overtimeSubmissionDocumentService->assessBatchReadiness($batch),
                    'overtime_requests' => $batch->overtimeRequests->map(function ($request) use ($employees) {
                        $employee = $employees->get($request->pf_number);
                        $workflowStatus = $this->overtimeQueryService->mapToWorkflowStatus($request->status);

                        return [
                            'id' => $request->id,
                            'pfNumber' => $request->pf_number,
                            'employeeName' => $this->overtimeQueryService->formatEmployeeName($employee),
                            'month' => $request->month->format('Y-m'),
                            'totalOvertimeHours' => (float) $request->total_overtime_hours,
                            'totalDays' => $request->total_days,
                            'totalAmount' => (float) $request->total_amount,
                            'status' => $request->status,
                            'workflowStatus' => $workflowStatus,
                        ];
                    }),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve batch: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data: array, pagination: array}|array{success: false, error: string, http: int}
     */
    public function listBatches(array $filters): array
    {
        try {
            $query = OvertimeBatch::withCount('overtimeRequests');

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $perPage = (int) ($filters['per_page'] ?? 10);
            if ($perPage < 1) {
                $perPage = 10;
            }

            $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);
            $data = collect($batches->items())->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'batch_name' => $batch->batch_name,
                    'status' => $batch->status,
                    'total_amount' => (float) $batch->total_amount,
                    'total_requests' => $batch->overtime_requests_count,
                    'external_batch_id' => $batch->external_batch_id,
                    'submitted_at' => $batch->submitted_at,
                    'created_at' => $batch->created_at,
                ];
            });

            return [
                'success' => true,
                'data' => $data->values()->all(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page' => $batches->lastPage(),
                    'per_page' => $batches->perPage(),
                    'total' => $batches->total(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve batches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve batches: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data: array}|array{success: false, error: string, errors?: array, http: int}
     */
    public function createBatch(array $input, ?int $userId): array
    {
        try {
            if (isset($input['overtime_ids']) && !isset($input['overtime_request_ids'])) {
                $input['overtime_request_ids'] = $input['overtime_ids'];
            }

            $validator = Validator::make($input, [
                'batch_name' => 'nullable|string|max:255',
                'overtime_request_ids' => 'required|array|min:1',
                'overtime_request_ids.*' => 'required|integer',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'http' => 422,
                ];
            }

            $overtimeRequestIds = $input['overtime_request_ids'];
            $overtimeRequests = OvertimeRequest::whereIn('id', $overtimeRequestIds)->get();

            if ($overtimeRequests->count() !== count($overtimeRequestIds)) {
                return [
                    'success' => false,
                    'error' => 'One or more overtime requests not found',
                    'http' => 404,
                ];
            }

            foreach ($overtimeRequests as $overtimeRequest) {
                if ($overtimeRequest->status !== 'Reviewer Approved') {
                    return [
                        'success' => false,
                        'error' => "Overtime request #{$overtimeRequest->id} is not in 'Reviewer Approved' status. Current status: {$overtimeRequest->status}",
                        'http' => 422,
                    ];
                }

                if ($overtimeRequest->batch_id !== null) {
                    return [
                        'success' => false,
                        'error' => "Overtime request #{$overtimeRequest->id} is already assigned to batch #{$overtimeRequest->batch_id}",
                        'http' => 422,
                    ];
                }
            }

            DB::beginTransaction();

            $batchNumber = OvertimeBatch::generateBatchNumber();
            $totalAmount = $overtimeRequests->sum('total_amount');
            $totalRequests = $overtimeRequests->count();

            $batch = OvertimeBatch::create([
                'batch_number' => $batchNumber,
                'batch_name' => $input['batch_name'] ?? "Batch {$batchNumber}",
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'total_requests' => $totalRequests,
                'notes' => $input['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $pfNumber = $this->overtimeRequestService->getPfNumberFromUserId($userId);
            $employeeRole = $this->overtimeWorkflowService->getEmployeeRoleFromPfNumber($pfNumber ?? '');
            $inBatchWorkflow = $this->overtimeQueryService->mapToWorkflowStatus('In Batch');

            foreach ($overtimeRequests as $overtimeRequest) {
                $overtimeRequest->batch_id = $batch->id;
                $overtimeRequest->status = 'In Batch';
                $overtimeRequest->workflow_status = $inBatchWorkflow;
                $overtimeRequest->updated_by = $userId;
                $overtimeRequest->save();

                OvertimeRequestHistory::create([
                    'overtime_request_id' => $overtimeRequest->id,
                    'action' => 'Added to Batch',
                    'status' => 'In Batch',
                    'workflow_status' => $inBatchWorkflow,
                    'performed_by' => $pfNumber ?? 'System',
                    'performed_by_role' => $employeeRole,
                    'comment' => "Added to batch {$batchNumber}",
                ]);
            }

            DB::commit();

            $batch->load('overtimeRequests');
            $employees = $this->overtimeQueryService->employeesKeyedByOvertimeRequests($batch->overtimeRequests);

            return [
                'success' => true,
                'data' => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'batch_name' => $batch->batch_name,
                    'status' => $batch->status,
                    'total_amount' => (float) ($batch->total_amount ?? 0),
                    'total_requests' => (int) ($batch->total_requests ?? 0),
                    'external_batch_id' => $batch->external_batch_id,
                    'submitted_at' => $batch->submitted_at,
                    'created_at' => $batch->created_at,
                    'overtime_requests' => $batch->overtimeRequests->map(function ($req) use ($employees) {
                        $employee = $employees->get($req->pf_number);

                        return [
                            'id' => $req->id,
                            'pfNumber' => $req->pf_number,
                            'employeeName' => $this->overtimeQueryService->formatEmployeeName($employee),
                            'month' => $req->month->format('Y-m'),
                            'totalOvertimeHours' => (float) ($req->total_overtime_hours ?? 0),
                            'totalDays' => (int) ($req->total_days ?? 0),
                            'totalAmount' => (float) ($req->total_amount ?? 0),
                            'status' => $req->status,
                        ];
                    }),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $input,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create batch: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }
}
