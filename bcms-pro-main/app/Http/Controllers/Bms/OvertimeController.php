<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\OvertimeRequestHistory;
use App\Models\Bms\OvertimeBatch;
use App\Models\AuthUser;
use App\Services\Overtime\OvertimeRequestService;
use App\Services\Overtime\OvertimeQueryService;
use App\Services\Overtime\OvertimeWorkflowService;
use App\Services\Overtime\OvertimeBatchService;
use App\Services\Overtime\OvertimeInvoiceService;
use App\Services\Overtime\OvertimeEOfficeWebhookService;
use App\Services\Overtime\OvertimeErmsSubmissionService;
use App\Services\Overtime\OvertimeNotificationService;
use App\Services\Overtime\OvertimeSubmissionDocumentService;
use App\Models\Bms\OvertimeSubmissionDocument;
use App\Traits\EOfficeTrait;
use App\Traits\FMSTrait;
use App\Helpers\DBHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OvertimeController extends BasicController
{
    use EOfficeTrait;
    use FMSTrait;

    private OvertimeRequestService $overtimeRequestService;
    private OvertimeQueryService $overtimeQueryService;
    private OvertimeWorkflowService $overtimeWorkflowService;
    private OvertimeBatchService $overtimeBatchService;
    private OvertimeInvoiceService $overtimeInvoiceService;
    private OvertimeEOfficeWebhookService $overtimeEOfficeWebhookService;
    private OvertimeErmsSubmissionService $overtimeErmsSubmissionService;
    private OvertimeNotificationService $overtimeNotificationService;
    private OvertimeSubmissionDocumentService $overtimeSubmissionDocumentService;

    public function __construct(
        OvertimeRequestService $overtimeRequestService,
        OvertimeQueryService $overtimeQueryService,
        OvertimeWorkflowService $overtimeWorkflowService,
        OvertimeBatchService $overtimeBatchService,
        OvertimeInvoiceService $overtimeInvoiceService,
        OvertimeEOfficeWebhookService $overtimeEOfficeWebhookService,
        OvertimeErmsSubmissionService $overtimeErmsSubmissionService,
        OvertimeNotificationService $overtimeNotificationService,
        OvertimeSubmissionDocumentService $overtimeSubmissionDocumentService
    ) {
        $this->overtimeRequestService = $overtimeRequestService;
        $this->overtimeQueryService = $overtimeQueryService;
        $this->overtimeWorkflowService = $overtimeWorkflowService;
        $this->overtimeBatchService = $overtimeBatchService;
        $this->overtimeInvoiceService = $overtimeInvoiceService;
        $this->overtimeEOfficeWebhookService = $overtimeEOfficeWebhookService;
        $this->overtimeErmsSubmissionService = $overtimeErmsSubmissionService;
        $this->overtimeNotificationService = $overtimeNotificationService;
        $this->overtimeSubmissionDocumentService = $overtimeSubmissionDocumentService;
    }

    public function store(Request $request): JsonResponse
    {
        $employeeId = auth()->id();
        if (!$employeeId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $result = $this->overtimeRequestService->createRequest($request->all(), (int) $employeeId);

        if (!$result['success']) {
            return $this->sendError(
                $result['error'],
                $result['errors'] ?? [],
                0,
                $result['http']
            );
        }

        $this->overtimeNotificationService->sendRequestSubmittedNotifications(
            $result['overtimeRequest'],
            $result['notification'],
            $this->getOvertimeValidators()
        );

        $message = !empty($result['isResubmit'])
            ? 'Overtime request resubmitted successfully'
            : 'Overtime request created successfully';

        return $this->sendResponse($result['responseData'], $message);
    }

    public function validateOvertimeAmount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estimated_days' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $employeeId = auth()->id();
        if (!$employeeId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $result = $this->overtimeRequestService->validateOvertimeAmount(
            (int) $employeeId,
            (int) $request->estimated_days
        );

        if (!$result['success']) {
            return $this->sendError(
                $result['error'],
                $result['errors'] ?? [],
                0,
                $result['http']
            );
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    private function isSupervisor($userId = null): bool
    {
        return $this->overtimeWorkflowService->isSupervisor($userId);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $authUserId = auth()->id();

            if (!$authUserId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            $isSupervisor = $this->isSupervisor($authUserId);
            $isValidator = $this->isOvertimeValidator($authUserId);
            $isReviewer = $this->isOvertimeReviewer($authUserId);
            $currentUserPfNumber = $this->overtimeRequestService->getPfNumberFromUserId($authUserId);

            $result = $this->overtimeQueryService->paginateOvertimeRequests(
                [
                    'stage' => $request->get('stage'),
                    'status' => $request->input('status'),
                    'month' => $request->input('month'),
                    'search' => $request->input('search'),
                    'pf_number' => $request->input('pf_number'),
                    'employee_id' => $request->input('employee_id'),
                    'per_page' => $request->get('per_page', 10),
                ],
                [
                    'auth_user_id' => (int) $authUserId,
                    'is_supervisor' => $isSupervisor,
                    'is_validator' => $isValidator,
                    'is_reviewer' => $isReviewer,
                    'current_pf' => $currentUserPfNumber,
                ]
            );

            if (!$result['success']) {
                return $this->sendError($result['error'], [], 0, $result['http']);
            }

            return $this->sendResponse([
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ], 'Overtime records retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve overtime requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to retrieve overtime requests: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function show($id): JsonResponse
    {
        $result = $this->overtimeQueryService->getOvertimeRequestDetail((int) $id, auth()->id());

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        return $this->sendResponse($result['data'], 'Overtime request retrieved successfully');
    }

    public function checkOvertimeForDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        $employeeId = auth()->id();
        if (!$employeeId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $result = $this->overtimeRequestService->checkOvertimeForDate(
            (int) $employeeId,
            (string) $request->date
        );

        if (!$result['success']) {
            return $this->sendError(
                $result['error'],
                $result['errors'] ?? [],
                0,
                $result['http']
            );
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    private function getEmployeeRoleFromPfNumber($pfNumber): string
    {
        return $this->overtimeWorkflowService->getEmployeeRoleFromPfNumber($pfNumber);
    }

    private function getOvertimeValidators(): array
    {
        return $this->overtimeWorkflowService->getOvertimeValidators();
    }

    private function hasOvertimeRole(string $roleName, $userId = null): bool
    {
        return $this->overtimeWorkflowService->hasOvertimeRole($roleName, $userId);
    }

    private function isOvertimeValidator($userId = null): bool
    {
        return $this->hasOvertimeRole('overtime validator', $userId);
    }

    private function isOvertimeReviewer($userId = null): bool
    {
        return $this->hasOvertimeRole('overtime reviewer', $userId);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $result = $this->overtimeWorkflowService->updateStatus((int) $id, $request->all(), auth()->id());

        if (!$result['success']) {
            return $this->sendError(
                $result['error'],
                $result['errors'] ?? [],
                0,
                $result['http']
            );
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    public function validatorApprove(Request $request, $id): JsonResponse
    {
        $request->merge([
            'status' => 'Validator Approved',
            'action' => 'Validator Approved',
        ]);

        return $this->updateStatus($request, $id);
    }

    public function reviewerApprove(Request $request, $id): JsonResponse
    {
        $request->merge([
            'status' => 'Reviewer Approved',
            'action' => 'Reviewer Approved',
        ]);

        return $this->updateStatus($request, $id);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->merge([
            'status' => 'Rejected',
            'action' => 'Rejected',
        ]);

        return $this->updateStatus($request, $id);
    }

    public function returnOvertimeRequest(Request $request, $id): JsonResponse
    {
        $result = $this->overtimeWorkflowService->returnOvertimeRequest(
            (int) $id,
            $request->all(),
            auth()->id()
        );

        if (!$result['success']) {
            return $this->sendError(
                $result['error'],
                $result['errors'] ?? [],
                0,
                $result['http']
            );
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    public function getEmployeeOvertime(Request $request, $pfNumber = null): JsonResponse
    {
        try {
            $authUserId = auth()->id();

            if (!$authUserId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            $currentUserPfNumber = $this->overtimeRequestService->getPfNumberFromUserId($authUserId);

            if (!$pfNumber) {
                if (!$currentUserPfNumber) {
                    return $this->sendError('PF number not found for user. Please ensure your account is linked to an employee record.', [], 0, 400);
                }
                $pfNumber = $currentUserPfNumber;
            } else {
                $isSupervisor = $this->isSupervisor($authUserId);
                $isValidator = $this->isOvertimeValidator($authUserId);
                $isReviewer = $this->isOvertimeReviewer($authUserId);

                if (!$isSupervisor && !$isValidator && !$isReviewer && $pfNumber !== $currentUserPfNumber) {
                    return $this->sendError('You do not have permission to view other employees\' overtime records.', [], 0, 403);
                }
            }

            $request->merge(['pf_number' => $pfNumber]);

            return $this->index($request);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve employee overtime', [
                'pf_number' => $pfNumber ?? 'current_user',
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to retrieve employee overtime: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function getReadyForBatch(Request $request): JsonResponse
    {
        $result = $this->overtimeBatchService->getReadyForBatch([
            'pf_number' => $request->input('pf_number'),
            'month' => $request->input('month'),
        ]);

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        return $this->sendResponse($result['data'], 'Overtime requests ready for batch retrieved successfully');
    }

    public function getBatch($batchId): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError('You do not have permission to view batches. Only users with "overtime reviewer" role can access this.', [], 0, 403);
        }

        $result = $this->overtimeBatchService->getBatch((int) $batchId);

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        return $this->sendResponse($result['data'], 'Batch retrieved successfully');
    }

    public function getBatchSubmissionReadiness(int $batchId): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to view batch submission readiness. Only users with "overtime reviewer" role can access this.',
                [],
                0,
                403
            );
        }

        $batch = OvertimeBatch::query()->find($batchId);
        if ($batch === null) {
            return $this->sendError('Batch not found', [], 0, 404);
        }

        return $this->sendResponse(
            $this->overtimeSubmissionDocumentService->assessBatchReadiness($batch),
            'Batch submission readiness retrieved successfully'
        );
    }

    public function repostFailedErmsSubmission(int $batchId): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError('You do not have permission to repost batches. Only users with "overtime reviewer" role can perform this action.', [], 0, 403);
        }

        try {
            $batch = OvertimeBatch::query()->find($batchId);

            if (!$batch) {
                return $this->sendError('Batch not found', [], 0, 404);
            }

            $result = $this->overtimeErmsSubmissionService->repostFailedSubmission($batch);

            if ($result['ok']) {
                return $this->sendResponse($result, 'Overtime batch reposted to ERMS successfully');
            }

            return $this->sendError(
                'ERMS repost completed with failure.',
                $result,
                0,
                422
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 409);
        } catch (\Exception $e) {
            Log::error('Failed to repost overtime batch to ERMS', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to repost overtime batch to ERMS: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function submitBatchToErms(int $batchId): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError('You do not have permission to submit batches. Only users with "overtime reviewer" role can perform this action.', [], 0, 403);
        }

        try {
            $batch = OvertimeBatch::query()->find($batchId);

            if (!$batch) {
                return $this->sendError('Batch not found', [], 0, 404);
            }

            $result = $this->overtimeErmsSubmissionService->submitOvertimeBatch($batch);

            if (($result['submission'] ?? null) === null && ($result['skipped'] ?? null) !== null) {
                return $this->sendResponse($result, 'Overtime batch ERMS submission skipped');
            }

            $ok = is_array($result['submission'] ?? null) ? (bool) ($result['submission']['ok'] ?? false) : false;
            if ($ok) {
                return $this->sendResponse($result, 'Overtime batch submitted to ERMS successfully');
            }

            return $this->sendError(
                'ERMS submission completed with failure.',
                $result,
                0,
                422
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 409);
        } catch (\Exception $e) {
            Log::error('Failed to submit overtime batch to ERMS', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to submit overtime batch to ERMS: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function listBatches(Request $request): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError('You do not have permission to view batches. Only users with "overtime reviewer" role can access this.', [], 0, 403);
        }

        $result = $this->overtimeBatchService->listBatches([
            'status' => $request->input('status'),
            'per_page' => $request->get('per_page', 10),
        ]);

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        return $this->sendResponse([
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ], 'Batches retrieved successfully');
    }

    public function createBatch(Request $request): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError('You do not have permission to create batches. Only users with "overtime reviewer" role can perform this action.', [], 0, 403);
        }

        $result = $this->overtimeBatchService->createBatch($request->all(), auth()->id());

        if (!$result['success']) {
            return $this->sendError(
                $result['error'],
                $result['errors'] ?? [],
                0,
                $result['http']
            );
        }

        return $this->sendResponse($result['data'], 'Batch created successfully');
    }

    public function saveAndSubmitBatch(Request $request, $batchId): JsonResponse
    {
        try {
            if (!$this->isOvertimeReviewer()) {
                return $this->sendError('You do not have permission to submit batches. Only users with "overtime reviewer" role can perform this action.', [], 0, 403);
            }

            $validator = Validator::make($request->all(), [
                'batch_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $batch = OvertimeBatch::with('overtimeRequests')->find($batchId);

            if (!$batch) {
                return $this->sendError('Batch not found', [], 0, 404);
            }

            if ($batch->overtimeRequests->isEmpty()) {
                return $this->sendError('Batch is empty. Cannot submit empty batch.', [], 0, 422);
            }

            if ($batch->status !== 'draft') {
                return $this->sendError(
                    'Only draft batches can be submitted to eOffice.',
                    ['status' => $batch->status],
                    0,
                    422
                );
            }

            $readiness = $this->overtimeSubmissionDocumentService->assessBatchReadiness($batch);
            if (!$readiness['can_submit_to_eoffice']) {
                return $this->sendError(
                    'Cannot submit batch to eOffice. Required submission documents are missing or expired.',
                    ['blocking_reasons' => $readiness['blocking_reasons']],
                    0,
                    422
                );
            }

            DB::beginTransaction();

            if ($request->filled('batch_name')) {
                $batch->batch_name = $request->batch_name;
            }
            if ($request->filled('notes')) {
                $batch->notes = $request->notes;
            }

            $tokenResponse = $this->requestToken();

            Log::info('OAuth token response', ['response' => $tokenResponse]);

            if ($tokenResponse instanceof JsonResponse) {
                $tokenData = json_decode($tokenResponse->getContent(), true);
                if (!$tokenData || !isset($tokenData['data']) || !isset($tokenData['data']['access_token'])) {
                    DB::rollBack();
                    Log::error('Failed to get eOffice access token', [
                        'response' => $tokenData,
                    ]);

                    return $this->sendError('Failed to authenticate with eOffice system. Please try again later.', [], 0, 500);
                }
                $accessToken = $tokenData['data']['access_token'];
            } else {
                if (isset($tokenResponse['error']) || !isset($tokenResponse['access_token'])) {
                    DB::rollBack();
                    Log::error('Failed to get eOffice access token', [
                        'response' => $tokenResponse,
                    ]);

                    return $this->sendError('Failed to authenticate with eOffice system. Please try again later.', [], 0, 500);
                }
                $accessToken = $tokenResponse['access_token'];
            }

            $user = AuthUser::find(auth()->id());
            if (!$user) {
                DB::rollBack();
                return $this->sendError('User not found', [], 0, 404);
            }

            $userDetails = [
                'email' => $user->email,
                'fullname' => $this->overtimeQueryService->formatUserName($user),
            ];

            $attachments = [];

            $pdfBase64 = $this->overtimeInvoiceService->generateInvoiceDocument($batch->batch_number, 'overtime');
            if (!$pdfBase64) {
                DB::rollBack();
                return $this->sendError('Failed to generate invoice document.', [], 0, 422);
            }

            $attachments[] = [
                'attachmentTitle' => 'Invoice_' . $batch->batch_number . '.pdf',
                'attachmentData' => $pdfBase64,
                'documentFlag' => 'MAIN',
            ];

            Log::info('Invoice document generated for batch attachment', [
                'batch_number' => $batch->batch_number,
                'file_name' => 'Invoice_' . $batch->batch_number . '.pdf',
            ]);

            foreach (config('overtime.eoffice_attachments', []) as $spec) {
                if (!($spec['required'] ?? false)) {
                    continue;
                }

                $documentType = (string) ($spec['document_type'] ?? '');
                $doc = $this->overtimeSubmissionDocumentService->resolveForBatch($batch, $documentType);

                if ($doc === null) {
                    DB::rollBack();
                    return $this->sendError(
                        'No valid document found for type: ' . $documentType,
                        ['blocking_reasons' => $readiness['blocking_reasons'] ?? []],
                        0,
                        422
                    );
                }

                try {
                    $attachmentData = $this->overtimeSubmissionDocumentService->readAsBase64($doc);
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::error('Failed to read submission document for eOffice attachment', [
                        'batch_id' => $batch->id,
                        'document_id' => $doc->id,
                        'document_type' => $documentType,
                        'error' => $e->getMessage(),
                    ]);

                    return $this->sendError(
                        'Failed to read submission document for type: ' . $documentType,
                        [],
                        0,
                        422
                    );
                }

                $attachments[] = [
                    'attachmentTitle' => $doc->file_name ?: (
                        ($spec['file_name_prefix'] ?? ucfirst($documentType) . '_') . $batch->batch_number . '.pdf'
                    ),
                    'attachmentData' => $attachmentData,
                    'documentFlag' => $spec['attachment_flag'],
                ];
            }

            $eOfficeBaseUrl = DBHelper::getEOfficeLink();
            $eOfficeUrl = $eOfficeBaseUrl . 'api/eoffice/requests-add';

            $responseUrl = DBHelper::getBmsApi() . '/overtime/feedback/status-update';

            Log::info('eOffice URL', ['eOfficeUrl' => $eOfficeUrl]);
            Log::info('eOffice callback URL', ['responseUrl' => $responseUrl]);

            $eOfficeResponse = $this->postEofficeOffice(
                $accessToken,
                $eOfficeUrl,
                'OVERTIME_MANAGEMENT',
                $batch->batch_number,
                (float) $batch->total_amount,
                $userDetails,
                $attachments,
                'APPROVAL',
                $responseUrl
            );

            Log::info('eOffice response', ['response' => $eOfficeResponse]);

            if ($eOfficeResponse instanceof JsonResponse) {
                $responseData = json_decode($eOfficeResponse->getContent(), true);
            } else {
                $responseData = $eOfficeResponse;
            }

            if (isset($responseData['status']) && $responseData['status'] === true) {
                $externalBatchId = $responseData['data']['reference_id'] ?? $batch->batch_number;
                $externalPaymentRequestId = $responseData['data']['request_id'] ?? $responseData['data']['document_id'] ?? null;

                Log::info('Overtime batch submitted to eOffice successfully', [
                    'batch_id' => $batch->id,
                    'external_batch_id' => $externalBatchId,
                    'external_payment_request_id' => $externalPaymentRequestId,
                ]);

                $batch->status = 'submitted';
                $batch->external_batch_id = $externalBatchId;
                $batch->external_payment_request_id = $externalPaymentRequestId;
                $batch->external_response = $responseData;
                $batch->submitted_at = now();
                $batch->updated_by = auth()->id();
                $batch->save();

                foreach ($batch->overtimeRequests as $overtimeRequest) {
                    $overtimeRequest->external_payment_request_id = $batch->external_payment_request_id;
                    $overtimeRequest->save();

                    $submitterPfNumber = $this->overtimeRequestService->getPfNumberFromUserId(auth()->id());
                    OvertimeRequestHistory::create([
                        'overtime_request_id' => $overtimeRequest->id,
                        'action' => 'Submitted to eOffice',
                        'status' => $overtimeRequest->status,
                        'workflow_status' => $this->overtimeQueryService->mapToWorkflowStatus($overtimeRequest->status),
                        'performed_by' => $submitterPfNumber,
                        'performed_by_role' => $this->getEmployeeRoleFromPfNumber($submitterPfNumber),
                        'comment' => "Batch {$batch->batch_number} submitted to eOffice system",
                    ]);
                }

                DB::commit();

                return $this->sendResponse([
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'external_batch_id' => $batch->external_batch_id,
                    'external_payment_request_id' => $batch->external_payment_request_id,
                    'status' => $batch->status,
                ], 'Batch submitted to EOffice system successfully');
            } else {
                DB::rollBack();
                $errorMessage = $responseData['message'] ?? 'Unknown error occurred while submitting to eOffice';
                Log::error('Failed to submit batch to eOffice', [
                    'batch_id' => $batch->id,
                    'response' => $responseData,
                ]);

                return $this->sendError('Failed to submit batch to eOffice system: ' . $errorMessage, [], 0, 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save and submit batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to save and submit batch: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function eOfficeFeedbackStatusUpdate(Request $request): JsonResponse
    {
        $result = $this->overtimeEOfficeWebhookService->handleStatusUpdate($request->all());

        if ($result['success']) {
            return $this->sendResponse([
                'code' => 9000,
                'data' => 'Successful',
                'message' => 'Received Successful',
            ], 'Successful');
        }

        return $this->sendError('9005', [
            'code' => 9005,
            'data' => 'Failed',
            'message' => 'Not Received Successful',
        ]);
    }

    public function getMyActions(Request $request): JsonResponse
    {
        try {
            $authUserId = auth()->id();

            if (!$authUserId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            $currentUserPfNumber = $this->overtimeRequestService->getPfNumberFromUserId($authUserId);

            $result = $this->overtimeQueryService->paginateMyActions(
                (int) $authUserId,
                $currentUserPfNumber,
                [
                    'status' => $request->input('status'),
                    'month' => $request->input('month'),
                    'search' => $request->input('search'),
                    'per_page' => $request->get('per_page', 10),
                ]
            );

            if (!$result['success']) {
                return $this->sendError($result['error'], [], 0, $result['http'] ?? 500);
            }

            return $this->sendResponse([
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ], 'Overtime requests acted upon by you retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve my actions overtime requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to retrieve overtime requests: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function getInvoiceDocument($batch_number, $flag = 'overtime')
    {
        return $this->overtimeInvoiceService->generateInvoiceDocument((string) $batch_number, (string) $flag);
    }

    public function getInvoiceDocumentEndpoint(Request $request, $batch_number)
    {
        $result = $this->overtimeInvoiceService->getInvoiceDocumentEndpoint(
            (string) $batch_number,
            (string) $request->input('flag', 'overtime'),
            $request->input('format'),
            $request->wantsJson(),
            $request->expectsJson()
        );

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        if (isset($result['response'])) {
            return $result['response'];
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    public function getOvertimeDocumentEndpoint(Request $request, $batch_number)
    {
        $result = $this->overtimeInvoiceService->getOvertimeDocumentEndpoint(
            (string) $batch_number,
            $request->input('format'),
            $request->wantsJson(),
            $request->expectsJson()
        );

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        if (isset($result['response'])) {
            return $result['response'];
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    public function listSubmissionDocumentTypes(): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to view submission document types. Only users with "overtime reviewer" role can access this.',
                [],
                0,
                403
            );
        }

        return $this->sendResponse([
            'document_types' => config('overtime.submission_documents.types', []),
            'eoffice_attachments' => config('overtime.eoffice_attachments', []),
        ], 'Submission document types retrieved successfully');
    }

    public function uploadSubmissionDocument(Request $request): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to upload submission documents. Only users with "overtime reviewer" role can perform this action.',
                [],
                0,
                403
            );
        }

        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $maxKb = (int) config('overtime.submission_documents.max_file_size_kb', 10240);

        $validator = Validator::make($request->all(), [
            'document_type' => [
                'required',
                'string',
                Rule::in(config('overtime.submission_documents.types', [])),
            ],
            'document_name' => 'nullable|string|max:255',
            'period_start' => 'required|date_format:Y-m-d',
            'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start',
            'file' => 'required|file|mimes:pdf|max:' . $maxKb,
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        try {
            $document = $this->overtimeSubmissionDocumentService->uploadDocument(
                $request->only(['document_type', 'document_name', 'period_start', 'period_end']),
                $request->file('file'),
                (int) $userId
            );

            return $this->sendResponse(
                $this->overtimeSubmissionDocumentService->serializeDocument($document),
                'Submission document uploaded successfully'
            )->setStatusCode(201);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 0, 422);
        } catch (\Throwable $e) {
            Log::error('Failed to upload overtime submission document', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return $this->sendError('Failed to upload submission document: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function listSubmissionDocuments(Request $request): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to list submission documents. Only users with "overtime reviewer" role can access this.',
                [],
                0,
                403
            );
        }

        $result = $this->overtimeSubmissionDocumentService->listOvertimeSubmissionDocuments([
            'document_type' => $request->input('document_type'),
            'document_name' => $request->input('document_name'),
            'status' => $request->input('status'),
            'per_page' => $request->get('per_page', 10),
        ]);

        if (!$result['success']) {
            return $this->sendError($result['error'], [], 0, $result['http']);
        }

        return $this->sendResponse([
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ], 'Submission documents retrieved successfully');
    }

    public function showSubmissionDocument(int $id): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to view submission documents. Only users with "overtime reviewer" role can access this.',
                [],
                0,
                403
            );
        }

        $document = OvertimeSubmissionDocument::query()->find($id);
        if ($document === null) {
            return $this->sendError('Submission document not found', [], 0, 404);
        }

        return $this->sendResponse(
            $this->overtimeSubmissionDocumentService->serializeDocument($document),
            'Submission document retrieved successfully'
        );
    }

    public function updateSubmissionDocument(Request $request, int $id): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to update submission documents. Only users with "overtime reviewer" role can perform this action.',
                [],
                0,
                403
            );
        }

        $userId = auth()->id();
        if (!$userId) {
            return $this->sendError('User not authenticated', [], 0, 401);
        }

        $document = OvertimeSubmissionDocument::query()->find($id);
        if ($document === null) {
            return $this->sendError('Submission document not found', [], 0, 404);
        }

        $maxKb = (int) config('overtime.submission_documents.max_file_size_kb', 10240);

        $validator = Validator::make($request->all(), [
            'document_name' => 'nullable|string|max:255',
            'period_start' => 'nullable|required_with:period_end|date_format:Y-m-d',
            'period_end' => 'nullable|required_with:period_start|date_format:Y-m-d|after_or_equal:period_start',
            'file' => 'nullable|file|mimes:pdf|max:' . $maxKb,
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
        }

        try {
            $document = $this->overtimeSubmissionDocumentService->updateDocument(
                $document,
                $request->only(['document_name', 'period_start', 'period_end']),
                $request->file('file'),
                (int) $userId
            );

            return $this->sendResponse(
                $this->overtimeSubmissionDocumentService->serializeDocument($document),
                'Submission document updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 0, 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update overtime submission document', [
                'document_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return $this->sendError('Failed to update submission document: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function toggleSubmissionDocumentStatus(int $id): JsonResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to update submission document status. Only users with "overtime reviewer" role can perform this action.',
                [],
                0,
                403
            );
        }

        $document = OvertimeSubmissionDocument::query()->find($id);
        if ($document === null) {
            return $this->sendError('Submission document not found', [], 0, 404);
        }

        try {
            $document = $this->overtimeSubmissionDocumentService->toggleDocumentStatus($document);

            return $this->sendResponse(
                $this->overtimeSubmissionDocumentService->serializeDocument($document),
                'Submission document status updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 0, 422);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle overtime submission document status', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to update submission document status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    public function downloadSubmissionDocument(int $id): JsonResponse|StreamedResponse
    {
        if (!$this->isOvertimeReviewer()) {
            return $this->sendError(
                'You do not have permission to download submission documents. Only users with "overtime reviewer" role can access this.',
                [],
                0,
                403
            );
        }

        $document = OvertimeSubmissionDocument::query()->find($id);
        if ($document === null) {
            return $this->sendError('Submission document not found', [], 0, 404);
        }

        $disk = (string) config('overtime.submission_documents.disk', 'local');
        if (!Storage::disk($disk)->exists($document->file_path)) {
            return $this->sendError('Submission document file not found', [], 0, 404);
        }

        return Storage::disk($disk)->download(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->mime_type ?: 'application/pdf']
        );
    }
}
