<?php

namespace App\Http\Controllers;

use App\Constants\AccountTransferRequest;
use App\Constants\AccountTransferStatus;
use App\Models\AccountTransfer;
use App\Services\Account\AccountTransferDocumentService;
use App\Services\Account\AccountTransferWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountTransferController extends BasicController
{
    public function __construct(
        private AccountTransferWorkflowService $workflowService,
        private AccountTransferDocumentService $documentService
    ) {}

    /**
     * Initiate a transfer (no balance movement until Toll Approver approves).
     */
    public function transfer(Request $request): JsonResponse
    {
        Log::info('Account transfer submission received', [
            'request_data' => $request->except(['approval_document']),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|string|max:32|different:to_account_id',
            'to_account_id' => 'required|string|max:32',
            'amount' => 'required|numeric|min:0.01',
            'narration' => 'nullable|string|max:255',
            'request_type' => ['required', 'string', Rule::in(AccountTransferRequest::types())],
            'action' => ['required', 'string', Rule::in(AccountTransferRequest::actions())],
            'request_date' => 'required|date',
            'client_reference' => 'nullable|string|max:64',
            'approval_document' => 'required|file',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->submit(
            $validator->validated(),
            $request->file('approval_document'),
            (int) auth()->id()
        );

        return $this->workflowResult($result);
    }

    /**
     * Resubmit a returned transfer.
     */
    public function resubmit(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0.01',
            'narration' => 'sometimes|nullable|string|max:255',
            'request_type' => ['sometimes', 'string', Rule::in(AccountTransferRequest::types())],
            'action' => ['sometimes', 'string', Rule::in(AccountTransferRequest::actions())],
            'request_date' => 'sometimes|date',
            'approval_document' => 'sometimes|file',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->resubmit(
            $id,
            $validator->validated(),
            $request->file('approval_document'),
            (int) auth()->id()
        );

        return $this->workflowResult($result);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->review($id, $validator->validated()['comment'], (int) auth()->id());

        return $this->workflowResult($result);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->verify($id, $validator->validated()['comment'], (int) auth()->id());

        return $this->workflowResult($result);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->approve($id, $validator->validated()['comment'], (int) auth()->id());

        return $this->workflowResult($result);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->reject($id, $validator->validated()['comment'], (int) auth()->id());

        return $this->workflowResult($result);
    }

    public function returnTransfer(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $result = $this->workflowService->returnTransfer($id, $validator->validated()['comment'], (int) auth()->id());

        return $this->workflowResult($result);
    }

    /**
     * Checker queue: transfers awaiting the current role's action.
     */
    public function listPendingTransfers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'role' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $queueStatuses = $this->workflowService->queueStatusesForUser(
            $request->input('role'),
            (int) auth()->id()
        );

        if ($queueStatuses === []) {
            return $this->sendError(
                'You do not have permission to view pending account transfers.',
                [],
                0,
                403
            );
        }

        try {
            $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
            $page = max(1, (int) $request->input('page', 1));

            $paginator = $this->buildTransferListQuery($queueStatuses)
                ->orderBy('t.created_at')
                ->orderBy('t.id')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->sendResponse([
                'transfers' => $this->mapTransferListItems($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], 'Pending transfers retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to list pending transfers', [
                'message' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to retrieve pending transfers: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * List account transfers with their ledger entries when posted.
     */
    public function listTransferHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => ['nullable', 'string', Rule::in(array_merge(AccountTransferStatus::all(), ['all']))],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        try {
            $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
            $page = max(1, (int) $request->input('page', 1));
            $status = $request->input('status', 'all');

            $query = $this->buildTransferListQuery($status === 'all' ? null : [$status])
                ->orderByDesc('t.created_at')
                ->orderByDesc('t.id');

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return $this->sendResponse([
                'transfers' => $this->mapTransferListItems($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], 'Transfer history retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to list transfer history', [
                'request' => $request->all(),
                'message' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to retrieve transfer history: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function getTransferHistory(int $id): JsonResponse
    {
        try {
            $transfer = $this->buildTransferListQuery(null)
                ->where('t.id', $id)
                ->first();

            if (!$transfer) {
                return $this->sendError('Transfer not found', ['id' => $id], 0, 404);
            }

            $mapped = $this->mapTransferListItems([$transfer]);

            return $this->sendResponse([
                'transfer' => $mapped[0],
            ], 'Transfer details retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to get transfer history detail', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to retrieve transfer details: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function downloadApprovalDocument(int $id): JsonResponse|StreamedResponse
    {
        $transfer = AccountTransfer::query()->find($id);
        if (!$transfer) {
            return $this->sendError('Transfer not found', ['id' => $id], 0, 404);
        }

        $userId = (int) auth()->id();
        $isSubmitter = (int) $transfer->created_by === $userId;
        $isParticipant = $this->workflowService->isWorkflowParticipant($userId);

        if (!$isSubmitter && !$isParticipant) {
            return $this->sendError(
                'You do not have permission to download this approval document.',
                [],
                0,
                403
            );
        }

        try {
            return $this->documentService->download($transfer);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Approval document not found', $e->errors(), 0, 404);
        }
    }

    /**
     * @param string[]|null $statuses
     */
    private function buildTransferListQuery(?array $statuses)
    {
        $query = DB::table('account_transfer as t')
            ->leftJoin('account as from_acc', 'from_acc.account_no', '=', 't.from_account_id')
            ->leftJoin('account as to_acc', 'to_acc.account_no', '=', 't.to_account_id')
            ->leftJoin('auth_user as creator', 'creator.id', '=', 't.created_by')
            ->leftJoin('auth_user as reviewer', 'reviewer.id', '=', 't.reviewed_by')
            ->leftJoin('auth_user as verifier', 'verifier.id', '=', 't.verified_by')
            ->leftJoin('auth_user as approver', 'approver.id', '=', 't.approved_by')
            ->leftJoin('auth_user as rejector', 'rejector.id', '=', 't.rejected_by')
            ->leftJoin('auth_user as returner', 'returner.id', '=', 't.returned_by')
            ->select([
                't.*',
                'from_acc.account_no as from_account_no',
                DB::raw("TRIM(CONCAT_WS(' ', from_acc.first_name, from_acc.middle_name, from_acc.surname)) as from_account_name"),
                'to_acc.account_no as to_account_no',
                DB::raw("TRIM(CONCAT_WS(' ', to_acc.first_name, to_acc.middle_name, to_acc.surname)) as to_account_name"),
                DB::raw($this->authUserNameSelect('creator', 'created_by')),
                DB::raw($this->authUserNameSelect('creator', 'submitted_by')),
                DB::raw($this->authUserNameSelect('reviewer', 'reviewed_by')),
                DB::raw($this->authUserNameSelect('verifier', 'verified_by')),
                DB::raw($this->authUserNameSelect('approver', 'approved_by')),
                DB::raw($this->authUserNameSelect('rejector', 'rejected_by')),
                DB::raw($this->authUserNameSelect('returner', 'returned_by')),
            ]);

        if ($statuses !== null) {
            $query->whereIn('t.status', $statuses);
        }

        return $query;
    }

    private function mapTransferListItems(array $items): array
    {
        $transferIds = collect($items)->pluck('id')->filter()->values();

        $entriesByTransfer = $transferIds->isEmpty()
            ? collect()
            : $this->buildTransferDetailsQuery()
                ->whereIn('at.account_transfer_id', $transferIds)
                ->orderBy('at.id')
                ->get()
                ->groupBy('account_transfer_id');

        return collect($items)->map(function ($transfer) use ($entriesByTransfer) {
            $sanitized = $this->workflowService->sanitizeTransferForResponse((array) $transfer);

            return array_merge($sanitized, [
                'from_account_no' => $transfer->from_account_no ?? null,
                'from_account_name' => $transfer->from_account_name ?? null,
                'to_account_no' => $transfer->to_account_no ?? null,
                'to_account_name' => $transfer->to_account_name ?? null,
                'created_by' => $transfer->created_by ?? null,
                'submitted_by' => $transfer->submitted_by ?? null,
                'reviewed_by' => $transfer->reviewed_by ?? null,
                'verified_by' => $transfer->verified_by ?? null,
                'approved_by' => $transfer->approved_by ?? null,
                'rejected_by' => $transfer->rejected_by ?? null,
                'returned_by' => $transfer->returned_by ?? null,
                'details' => ($entriesByTransfer->get($transfer->id) ?? collect())->values()->all(),
            ]);
        })->values()->all();
    }

    private function buildTransferDetailsQuery()
    {
        return DB::table('account_transaction as at')
            ->leftJoin('account as a', function ($join) {
                $join->on('a.account_no', '=', 'at.account_id')
                    ->orOn('a.id', '=', 'at.account_id');
            })
            ->leftJoin('auth_user as entry_creator', 'entry_creator.id', '=', 'at.created_by')
            ->select([
                'at.*',
                'a.account_no',
                DB::raw("TRIM(CONCAT_WS(' ', a.first_name, a.middle_name, a.surname)) as account_name"),
                DB::raw($this->authUserNameSelect('entry_creator', 'created_by')),
            ]);
    }

    private function authUserNameSelect(string $alias, string $column): string
    {
        return "NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.middle_name, {$alias}.surname)), '') as {$column}";
    }

    private function workflowResult(array $result): JsonResponse
    {
        if (!$result['success']) {
            return $this->sendError(
                $result['error'] ?? 'Request failed',
                $result['errors'] ?? [],
                0,
                $result['http'] ?? 500
            );
        }

        $httpStatus = $result['http'] ?? 200;
        if ($httpStatus === 201) {
            return response()->json([
                'success' => true,
                'status_code' => 1,
                'data' => $result['data'],
                'message' => $result['message'],
            ], 201);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }
}
