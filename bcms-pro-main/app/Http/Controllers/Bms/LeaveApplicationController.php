<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\AuthUser;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\Bms\LeaveApplication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LeaveApplicationController extends BasicController
{
    private const ROLE_ADMINISTRATOR = 'employee administrator';
    private const ROLE_APPROVER = 'employee approver';
    private const DOCUMENT_DIRECTORY = 'leave-documents';
    private const MAX_DOCUMENT_BYTES = 10485760;

    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        $isAdministrator = $this->userHasBmsRole(self::ROLE_ADMINISTRATOR, $userId);
        $isApprover = $this->userHasBmsRole(self::ROLE_APPROVER, $userId);

        $query = LeaveApplication::query()->orderByDesc('created_at');

        if (!$isAdministrator && !$isApprover) {
            $query->where('applicant_id', $userId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->get('leave_type'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('start_date', '>=', $request->get('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('end_date', '<=', $request->get('to_date'));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->sendResponse([
            'items' => collect($paginator->items())->map(fn ($row) => $this->serialize($row))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Leave applications retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
            'supportive_document' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $start = Carbon::parse($request->input('start_date'))->startOfDay();
        $end = Carbon::parse($request->input('end_date'))->startOfDay();
        $days = $start->diffInDays($end) + 1;
        $user = AuthUser::find($userId);

        $documentPath = null;
        $documentName = null;
        if ($request->hasFile('supportive_document')) {
            $file = $request->file('supportive_document');
            if ($file->getSize() > self::MAX_DOCUMENT_BYTES) {
                return $this->sendError('Supportive document must be 10MB or smaller', [], 0, 422);
            }
            $documentPath = $file->store(self::DOCUMENT_DIRECTORY, 'local');
            $documentName = $file->getClientOriginalName();
        }

        $application = LeaveApplication::create([
            'applicant_id' => $userId,
            'applicant_name' => $this->displayName($user),
            'pf_number' => $user?->pf_number,
            'leave_type' => $request->input('leave_type'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => trim((string) $request->input('reason')),
            'supportive_document_path' => $documentPath,
            'supportive_document_name' => $documentName,
            'status' => LeaveApplication::STATUS_APPLIED,
        ]);

        return $this->sendResponse($this->serialize($application), 'Leave application submitted');
    }

    public function verify(Request $request, $id): JsonResponse
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }
        if (!$this->userHasBmsRole(self::ROLE_ADMINISTRATOR, $userId)) {
            return $this->sendError('Only an Employee Administrator can verify leave applications', [], 0, 403);
        }

        $application = LeaveApplication::find($id);
        if (!$application) {
            return $this->sendError('Leave application not found', [], 0, 404);
        }
        if ($application->status !== LeaveApplication::STATUS_APPLIED) {
            return $this->sendError('Only applied leave can be verified', [], 0, 422);
        }

        $application->status = LeaveApplication::STATUS_VERIFIED;
        $application->verified_by = $userId;
        $application->verified_at = now();
        $application->verification_comment = $request->input('comment');
        $application->save();

        return $this->sendResponse($this->serialize($application), 'Leave application verified');
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }
        if (!$this->userHasBmsRole(self::ROLE_APPROVER, $userId)) {
            return $this->sendError('Only an Employee Approver can approve leave applications', [], 0, 403);
        }

        $application = LeaveApplication::find($id);
        if (!$application) {
            return $this->sendError('Leave application not found', [], 0, 404);
        }
        if ($application->status !== LeaveApplication::STATUS_VERIFIED) {
            return $this->sendError('Leave must be verified before it can be approved', [], 0, 422);
        }

        $application->status = LeaveApplication::STATUS_APPROVED;
        $application->approved_by = $userId;
        $application->approved_at = now();
        $application->approval_comment = $request->input('comment');
        $application->save();

        return $this->sendResponse($this->serialize($application), 'Leave application approved');
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $application = LeaveApplication::find($id);
        if (!$application) {
            return $this->sendError('Leave application not found', [], 0, 404);
        }

        $isAdministrator = $this->userHasBmsRole(self::ROLE_ADMINISTRATOR, $userId);
        $isApprover = $this->userHasBmsRole(self::ROLE_APPROVER, $userId);

        if ($application->status === LeaveApplication::STATUS_APPLIED && $isAdministrator) {
            $stage = LeaveApplication::STATUS_APPLIED;
        } elseif ($application->status === LeaveApplication::STATUS_VERIFIED && $isApprover) {
            $stage = LeaveApplication::STATUS_VERIFIED;
        } else {
            return $this->sendError('You cannot reject this leave application at its current stage', [], 0, 403);
        }

        $application->status = LeaveApplication::STATUS_REJECTED;
        $application->rejected_by = $userId;
        $application->rejected_at = now();
        $application->rejected_at_stage = $stage;
        $application->rejection_reason = trim((string) $request->input('reason'));
        $application->save();

        return $this->sendResponse($this->serialize($application), 'Leave application rejected');
    }

    public function downloadDocument($id)
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $application = LeaveApplication::find($id);
        if (!$application || !$application->supportive_document_path) {
            return $this->sendError('Supportive document not found', [], 0, 404);
        }

        $isAdministrator = $this->userHasBmsRole(self::ROLE_ADMINISTRATOR, $userId);
        $isApprover = $this->userHasBmsRole(self::ROLE_APPROVER, $userId);
        if ((int) $application->applicant_id !== (int) $userId && !$isAdministrator && !$isApprover) {
            return $this->sendError('You do not have permission to download this document', [], 0, 403);
        }

        if (!Storage::disk('local')->exists($application->supportive_document_path)) {
            return $this->sendError('Supportive document file is missing', [], 0, 404);
        }

        return Storage::disk('local')->download(
            $application->supportive_document_path,
            $application->supportive_document_name ?: 'supportive-document'
        );
    }

    private function serialize(LeaveApplication $application): array
    {
        return [
            'id' => $application->id,
            'applicantName' => $application->applicant_name,
            'pfNumber' => $application->pf_number,
            'leaveType' => $application->leave_type,
            'startDate' => optional($application->start_date)->toDateString(),
            'endDate' => optional($application->end_date)->toDateString(),
            'days' => $application->days,
            'reason' => $application->reason,
            'supportiveDocumentName' => $application->supportive_document_name,
            'hasDocument' => (bool) $application->supportive_document_path,
            'status' => $application->status,
            'appliedOn' => optional($application->created_at)->toDateString(),
            'verifiedAt' => optional($application->verified_at)->toDateTimeString(),
            'approvedAt' => optional($application->approved_at)->toDateTimeString(),
            'rejectedAt' => optional($application->rejected_at)->toDateTimeString(),
            'rejectionReason' => $application->rejection_reason,
        ];
    }

    private function displayName(?AuthUser $user): string
    {
        if (!$user) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $user->first_name,
            $user->middle_name,
            $user->surname,
        ])));
    }

    private function userHasBmsRole(string $roleName, $userId = null): bool
    {
        $currentUserId = $userId ?? auth()->id();
        if (!$currentUserId) {
            return false;
        }

        try {
            $user = AuthUser::find($currentUserId);
            $pfNumber = $user?->pf_number;
            $nationalId = $user?->nida;

            if (!$nationalId && $pfNumber) {
                $nationalId = BridgeEmployee::where('pfno', $pfNumber)->value('national_id');
            }
            if (!$nationalId) {
                return false;
            }

            $roles = BridgeEmployeeRole::where('national_id', $nationalId)
                ->where('is_active', true)
                ->with('role')
                ->get();

            $target = strtolower($roleName);
            foreach ($roles as $assignment) {
                $current = strtolower((string) ($assignment->role->role_name ?? ''));
                if ($current === $target) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check leave BMS role', [
                'user_id' => $currentUserId,
                'role' => $roleName,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
