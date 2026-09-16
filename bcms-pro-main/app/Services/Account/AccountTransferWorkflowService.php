<?php

namespace App\Services\Account;

use App\Constants\AccountTransferStatus;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\AuthUser;
use App\Models\Bms\BridgeEmployeeRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountTransferWorkflowService
{
    private const ROLE_REGISTRAR = 'Toll Registrar';

    private const ROLE_SUPERVISOR = 'Toll Supervisor';

    private const ROLE_ACCOUNTANT = 'Toll Accountant';

    private const ROLE_APPROVER = 'Toll Approver';

    public function __construct(
        private AccountTransferDocumentService $documentService
    ) {}

    public function isInitiator(?int $userId = null): bool
    {
        return $this->hasBridgeEmployeeRole(self::ROLE_REGISTRAR, $userId);
    }

    public function isSupervisor(?int $userId = null): bool
    {
        return $this->hasBridgeEmployeeRole(self::ROLE_SUPERVISOR, $userId);
    }

    public function isAccountant(?int $userId = null): bool
    {
        return $this->hasBridgeEmployeeRole(self::ROLE_ACCOUNTANT, $userId);
    }

    public function isApprover(?int $userId = null): bool
    {
        return $this->hasBridgeEmployeeRole(self::ROLE_APPROVER, $userId);
    }

    public function isChecker(?int $userId = null): bool
    {
        return $this->isSupervisor($userId) || $this->isAccountant($userId) || $this->isApprover($userId);
    }

    public function isWorkflowParticipant(?int $userId = null): bool
    {
        return $this->isInitiator($userId) || $this->isChecker($userId);
    }

    /**
     * Statuses the user may act on, optionally scoped to the selected role.
     *
     * @return string[]
     */
    public function queueStatusesForUser(?string $selectedRole = null, ?int $userId = null): array
    {
        $role = strtolower(trim((string) $selectedRole));
        $map = [
            'toll supervisor' => AccountTransferStatus::PENDING,
            'toll accountant' => AccountTransferStatus::REVIEWED,
            'toll approver' => AccountTransferStatus::VERIFIED,
        ];

        if ($role !== '' && isset($map[$role])) {
            $hasRole = match ($role) {
                'toll supervisor' => $this->isSupervisor($userId),
                'toll accountant' => $this->isAccountant($userId),
                'toll approver' => $this->isApprover($userId),
                default => false,
            };

            return $hasRole ? [$map[$role]] : [];
        }

        $statuses = [];
        if ($this->isSupervisor($userId)) {
            $statuses[] = AccountTransferStatus::PENDING;
        }
        if ($this->isAccountant($userId)) {
            $statuses[] = AccountTransferStatus::REVIEWED;
        }
        if ($this->isApprover($userId)) {
            $statuses[] = AccountTransferStatus::VERIFIED;
        }

        return $statuses;
    }

    public function canActOnTransfer(AccountTransfer $transfer, int $userId): bool
    {
        if ((int) $transfer->created_by === $userId) {
            return false;
        }

        return match ($transfer->status) {
            AccountTransferStatus::PENDING => $this->isSupervisor($userId),
            AccountTransferStatus::REVIEWED => $this->isAccountant($userId),
            AccountTransferStatus::VERIFIED => $this->isApprover($userId),
            default => false,
        };
    }

    private function hasBridgeEmployeeRole(string $roleName, ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return false;
        }

        try {
            $user = AuthUser::find($userId);
            if (!$user || empty($user->nida)) {
                return false;
            }

            $nationalId = $user->nida;
            $targetRoleName = strtolower($roleName);
            $bridgeEmployeeRoles = BridgeEmployeeRole::where('national_id', $nationalId)
                ->active()
                ->with('role')
                ->get();

            foreach ($bridgeEmployeeRoles as $bridgeEmployeeRole) {
                if ($bridgeEmployeeRole->role) {
                    $currentRoleName = strtolower($bridgeEmployeeRole->role->role_name ?? '');
                    if ($currentRoleName === $targetRoleName) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check bridge employee role for account transfer approval', [
                'user_id' => $userId,
                'role' => $roleName,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }


    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, errors?: array, http?: int}
     */
    public function submit(array $input, UploadedFile $file, int $userId): array
    {
        if (!$this->isInitiator($userId)) {
            return [
                'success' => false,
                'error' => 'Only a Toll Registrar can initiate Update Receipts.',
                'http' => 403,
            ];
        }

        $fromAccountNo = $this->normalizeAccountReference($input['from_account_id'] ?? null);
        $toAccountNo = $this->normalizeAccountReference($input['to_account_id'] ?? null);
        $amount = (float) $input['amount'];
        $narration = (string) ($input['narration'] ?? '');
        $requestType = (string) $input['request_type'];
        $action = (string) $input['action'];
        $requestDate = $input['request_date'] ?? null;
        $clientReference = $input['client_reference'] ?? null;

        if ($fromAccountNo === '' || $toAccountNo === '') {
            return [
                'success' => false,
                'error' => 'Account number is required',
                'errors' => [
                    'from_account_id' => $fromAccountNo === '' ? ['From account is required.'] : [],
                    'to_account_id' => $toAccountNo === '' ? ['To account is required.'] : [],
                ],
                'http' => 422,
            ];
        }

        if ($fromAccountNo === $toAccountNo) {
            return [
                'success' => false,
                'error' => 'Source and destination accounts must be different',
                'errors' => ['to_account_id' => ['Source and destination accounts must be different.']],
                'http' => 422,
            ];
        }

        if (!empty($clientReference)) {
            $existing = AccountTransfer::query()
                ->where('from_account_id', $fromAccountNo)
                ->where('client_reference', $clientReference)
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'data' => $this->buildTransferResponse($existing),
                    'message' => 'Transfer already submitted',
                    'http' => 200,
                ];
            }
        }

        $accountCheck = $this->validateAccounts($fromAccountNo, $toAccountNo, $amount, checkBalance: true);
        if (!$accountCheck['success']) {
            return $accountCheck;
        }

        $transferUuid = (string) Str::uuid();
        $documentMeta = null;

        try {
            $documentMeta = $this->documentService->store($transferUuid, $file);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e instanceof ValidationException
                    ? 'Validation failed'
                    : 'Failed to store approval document',
                'errors' => $e instanceof ValidationException ? $e->errors() : [],
                'http' => $e instanceof ValidationException ? 422 : 500,
            ];
        }

        try {
            $transfer = AccountTransfer::query()->create([
                'transfer_uuid' => $transferUuid,
                'client_reference' => $clientReference,
                'from_account_id' => $fromAccountNo,
                'to_account_id' => $toAccountNo,
                'amount' => $amount,
                'status' => AccountTransferStatus::PENDING,
                'posted_at' => null,
                'narration' => $narration,
                'request_type' => $requestType,
                'action' => $action,
                'request_date' => $requestDate,
                'approval_document_path' => $documentMeta['path'],
                'approval_document_name' => $documentMeta['name'],
                'approval_document_mime' => $documentMeta['mime'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            Log::info('Account transfer initiated for review', [
                'transfer_id' => $transfer->id,
                'transfer_uuid' => $transferUuid,
                'created_by' => $userId,
            ]);

            return [
                'success' => true,
                'data' => $this->buildTransferResponse($transfer),
                'message' => 'Update Receipt initiated. Awaiting Toll Supervisor review.',
                'http' => 201,
            ];
        } catch (\Throwable $e) {
            $this->documentService->delete($documentMeta['path'] ?? null);

            Log::error('Account transfer submission failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to submit transfer: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, errors?: array, http?: int}
     */
    public function resubmit(int $transferId, array $input, ?UploadedFile $file, int $userId): array
    {
        $transfer = AccountTransfer::query()->find($transferId);
        if (!$transfer) {
            return ['success' => false, 'error' => 'Transfer not found', 'http' => 404];
        }

        if (!$transfer->isReturned()) {
            return ['success' => false, 'error' => 'Only returned transfers can be resubmitted', 'http' => 409];
        }

        if ((int) $transfer->created_by !== $userId) {
            return ['success' => false, 'error' => 'Only the original submitter can resubmit this transfer', 'http' => 403];
        }

        if (!$this->isInitiator($userId)) {
            return ['success' => false, 'error' => 'Only a Toll Registrar can resubmit Update Receipts.', 'http' => 403];
        }

        $fromAccountNo = $this->normalizeAccountReference($transfer->from_account_id);
        $toAccountNo = $this->normalizeAccountReference($transfer->to_account_id);
        $amount = isset($input['amount']) ? (float) $input['amount'] : (float) $transfer->amount;
        $narration = isset($input['narration']) ? (string) $input['narration'] : (string) $transfer->narration;
        $requestType = isset($input['request_type']) ? (string) $input['request_type'] : (string) $transfer->request_type;
        $action = isset($input['action']) ? (string) $input['action'] : (string) $transfer->action;
        $requestDate = $input['request_date'] ?? $transfer->request_date;

        $accountCheck = $this->validateAccounts($fromAccountNo, $toAccountNo, $amount, checkBalance: true);
        if (!$accountCheck['success']) {
            return $accountCheck;
        }

        $oldPath = $transfer->approval_document_path;
        $documentMeta = null;

        if ($file !== null) {
            try {
                $documentMeta = $this->documentService->replace($transfer->transfer_uuid, $file, $oldPath);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'error' => $e instanceof ValidationException
                        ? 'Validation failed'
                        : 'Failed to store approval document',
                    'errors' => $e instanceof ValidationException ? $e->errors() : [],
                    'http' => $e instanceof ValidationException ? 422 : 500,
                ];
            }
        } elseif (!$this->documentService->exists($transfer)) {
            return [
                'success' => false,
                'error' => 'Approval document is missing. Please upload a new document.',
                'http' => 422,
            ];
        }

        $transfer->amount = $amount;
        $transfer->narration = $narration;
        $transfer->request_type = $requestType;
        $transfer->action = $action;
        $transfer->request_date = $requestDate;
        $transfer->status = AccountTransferStatus::PENDING;
        $transfer->reviewed_by = null;
        $transfer->reviewed_at = null;
        $transfer->review_comment = null;
        $transfer->verified_by = null;
        $transfer->verified_at = null;
        $transfer->verification_comment = null;
        $transfer->approved_by = null;
        $transfer->approved_at = null;
        $transfer->approval_comment = null;
        $transfer->returned_by = null;
        $transfer->returned_at = null;
        $transfer->return_comment = null;
        $transfer->updated_by = $userId;

        if ($documentMeta !== null) {
            $transfer->approval_document_path = $documentMeta['path'];
            $transfer->approval_document_name = $documentMeta['name'];
            $transfer->approval_document_mime = $documentMeta['mime'];
        }

        $transfer->save();

        return [
            'success' => true,
            'data' => $this->buildTransferResponse($transfer->fresh()),
            'message' => 'Update Receipt resubmitted for review',
            'http' => 200,
        ];
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, http?: int}
     */
    public function review(int $transferId, string $comment, int $userId): array
    {
        if (!$this->isSupervisor($userId)) {
            return [
                'success' => false,
                'error' => 'Only a Toll Supervisor can review Update Receipts.',
                'http' => 403,
            ];
        }

        try {
            return DB::transaction(function () use ($transferId, $comment, $userId) {
                $transfer = AccountTransfer::query()->lockForUpdate()->find($transferId);
                if (!$transfer) {
                    return ['success' => false, 'error' => 'Transfer not found', 'http' => 404];
                }

                if (!$transfer->isPending()) {
                    return ['success' => false, 'error' => 'Only initiated transfers can be reviewed', 'http' => 409];
                }

                if ((int) $transfer->created_by === $userId) {
                    return ['success' => false, 'error' => 'You cannot review your own transfer request', 'http' => 403];
                }

                $transfer->status = AccountTransferStatus::REVIEWED;
                $transfer->reviewed_by = $userId;
                $transfer->reviewed_at = now();
                $transfer->review_comment = $comment;
                $transfer->updated_by = $userId;
                $transfer->save();

                Log::info('Account transfer reviewed', [
                    'transfer_id' => $transfer->id,
                    'reviewed_by' => $userId,
                ]);

                return [
                    'success' => true,
                    'data' => $this->buildTransferResponse($transfer->fresh()),
                    'message' => 'Update Receipt reviewed. Awaiting Toll Accountant verification.',
                    'http' => 200,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Account transfer review failed', [
                'transfer_id' => $transferId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to review transfer: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, http?: int}
     */
    public function verify(int $transferId, string $comment, int $userId): array
    {
        if (!$this->isAccountant($userId)) {
            return [
                'success' => false,
                'error' => 'Only a Toll Accountant can verify Update Receipts.',
                'http' => 403,
            ];
        }

        try {
            return DB::transaction(function () use ($transferId, $comment, $userId) {
                $transfer = AccountTransfer::query()->lockForUpdate()->find($transferId);
                if (!$transfer) {
                    return ['success' => false, 'error' => 'Transfer not found', 'http' => 404];
                }

                if (!$transfer->isReviewed()) {
                    return ['success' => false, 'error' => 'Only reviewed transfers can be verified', 'http' => 409];
                }

                if ((int) $transfer->created_by === $userId) {
                    return ['success' => false, 'error' => 'You cannot verify your own transfer request', 'http' => 403];
                }

                $transfer->status = AccountTransferStatus::VERIFIED;
                $transfer->verified_by = $userId;
                $transfer->verified_at = now();
                $transfer->verification_comment = $comment;
                $transfer->updated_by = $userId;
                $transfer->save();

                Log::info('Account transfer verified', [
                    'transfer_id' => $transfer->id,
                    'verified_by' => $userId,
                ]);

                return [
                    'success' => true,
                    'data' => $this->buildTransferResponse($transfer->fresh()),
                    'message' => 'Update Receipt verified. Awaiting Toll Approver approval.',
                    'http' => 200,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Account transfer verification failed', [
                'transfer_id' => $transferId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to verify transfer: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, http?: int}
     */
    public function approve(int $transferId, string $comment, int $userId): array
    {
        if (!$this->isApprover($userId)) {
            return [
                'success' => false,
                'error' => 'Only a Toll Approver can approve Update Receipts.',
                'http' => 403,
            ];
        }

        try {
            return DB::transaction(function () use ($transferId, $comment, $userId) {
                $transfer = AccountTransfer::query()->lockForUpdate()->find($transferId);
                if (!$transfer) {
                    return ['success' => false, 'error' => 'Transfer not found', 'http' => 404];
                }

                if (!$transfer->isVerified()) {
                    return ['success' => false, 'error' => 'Only verified transfers can be approved', 'http' => 409];
                }

                if ((int) $transfer->created_by === $userId) {
                    return ['success' => false, 'error' => 'You cannot approve your own transfer request', 'http' => 403];
                }

                if (!$this->documentService->exists($transfer)) {
                    return [
                        'success' => false,
                        'error' => 'Approval document is missing. Cannot approve this transfer.',
                        'http' => 422,
                    ];
                }

                $posted = $this->postTransfer($transfer, $userId);

                $transfer->status = AccountTransferStatus::POSTED;
                $transfer->posted_at = now();
                $transfer->approved_by = $userId;
                $transfer->approved_at = now();
                $transfer->approval_comment = $comment;
                $transfer->updated_by = $userId;
                $transfer->save();

                Log::info('Account transfer approved and posted', [
                    'transfer_id' => $transfer->id,
                    'approved_by' => $userId,
                ]);

                return [
                    'success' => true,
                    'data' => array_merge($this->buildTransferResponse($transfer->fresh()), $posted),
                    'message' => 'Update Receipt approved and posted successfully',
                    'http' => 200,
                ];
            });
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
                'http' => 422,
            ];
        } catch (\Throwable $e) {
            Log::error('Account transfer approval failed', [
                'transfer_id' => $transferId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to approve transfer: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, http?: int}
     */
    public function reject(int $transferId, string $comment, int $userId): array
    {
        return $this->processCheckerAction(
            $transferId,
            $comment,
            $userId,
            AccountTransferStatus::REJECTED,
            'reject',
            function (AccountTransfer $transfer) use ($comment, $userId) {
                $transfer->rejected_by = $userId;
                $transfer->rejected_at = now();
                $transfer->rejection_reason = $comment;
            },
            'Update Receipt rejected successfully'
        );
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, http?: int}
     */
    public function returnTransfer(int $transferId, string $comment, int $userId): array
    {
        return $this->processCheckerAction(
            $transferId,
            $comment,
            $userId,
            AccountTransferStatus::RETURNED,
            'return',
            function (AccountTransfer $transfer) use ($comment, $userId) {
                $transfer->returned_by = $userId;
                $transfer->returned_at = now();
                $transfer->return_comment = $comment;
            },
            'Update Receipt returned to submitter successfully'
        );
    }

    /**
     * @return array{transfer: AccountTransfer, entries: \Illuminate\Support\Collection, accounts: array, reference_number: string}
     */
    private function postTransfer(AccountTransfer $transfer, int $userId): array
    {
        $fromAccountNo = $this->normalizeAccountReference($transfer->from_account_id);
        $toAccountNo = $this->normalizeAccountReference($transfer->to_account_id);
        $amount = (float) $transfer->amount;
        $narration = (string) $transfer->narration;
        $transferUuid = (string) $transfer->transfer_uuid;

        $accounts = Account::query()
            ->whereIn('account_no', [$fromAccountNo, $toAccountNo])
            ->lockForUpdate()
            ->get()
            ->keyBy('account_no');

        /** @var Account|null $from */
        $from = $accounts->get($fromAccountNo);
        /** @var Account|null $to */
        $to = $accounts->get($toAccountNo);

        if (!$from) {
            throw ValidationException::withMessages([
                'from_account_id' => ['From account not found.'],
            ]);
        }
        if (!$to) {
            throw ValidationException::withMessages([
                'to_account_id' => ['To account not found.'],
            ]);
        }

        if ($from->status != '1') {
            throw ValidationException::withMessages([
                'from_account_id' => ['Sender account is not active.'],
            ]);
        }
        if ($to->status != '1') {
            throw ValidationException::withMessages([
                'to_account_id' => ['Receiver account is not active.'],
            ]);
        }

        $fromPrevBalance = (float) ($from->account_balance ?? 0);
        $toPrevBalance = (float) ($to->account_balance ?? 0);

        if ($fromPrevBalance < $amount) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance in sender account.'],
            ]);
        }

        $fromNewBalance = $fromPrevBalance - $amount;
        $toNewBalance = $toPrevBalance + $amount;

        $from->account_balance = $fromNewBalance;
        $from->updated_at = now();
        $from->updated_by = $userId;
        $from->save();

        $to->account_balance = $toNewBalance;
        $to->updated_at = now();
        $to->updated_by = $userId;
        $to->save();

        $referenceNumber = 'TRF_' . now()->format('Ymd_His') . '_' . $fromAccountNo . '_' . $toAccountNo;

        DB::table('account_transaction')->insert([
            [
                'account_transfer_id' => $transfer->id,
                'account_id' => $from->id,
                'entry_type' => 'debit',
                'amount' => -1 * $amount,
                'previous_balance' => $fromPrevBalance,
                'new_balance' => $fromNewBalance,
                'reference_number' => $referenceNumber,
                'description' => $narration ?: 'Account transfer (debit)',
                'metadata' => json_encode([
                    'transfer_uuid' => $transferUuid,
                    'from_account_id' => $fromAccountNo,
                    'to_account_id' => $toAccountNo,
                ]),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
                'updated_by' => $userId,
            ],
            [
                'account_transfer_id' => $transfer->id,
                'account_id' => $to->id,
                'entry_type' => 'credit',
                'amount' => $amount,
                'previous_balance' => $toPrevBalance,
                'new_balance' => $toNewBalance,
                'reference_number' => $referenceNumber,
                'description' => $narration ?: 'Account transfer (credit)',
                'metadata' => json_encode([
                    'transfer_uuid' => $transferUuid,
                    'from_account_id' => $fromAccountNo,
                    'to_account_id' => $toAccountNo,
                ]),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
                'updated_by' => $userId,
            ],
        ]);

        $entries = DB::table('account_transaction')
            ->where('account_transfer_id', $transfer->id)
            ->orderBy('id')
            ->get();

        return [
            'entries' => $entries,
            'accounts' => [
                'from' => [
                    'id' => $from->id,
                    'account_no' => $from->account_no,
                    'previous_balance' => $fromPrevBalance,
                    'new_balance' => $fromNewBalance,
                ],
                'to' => [
                    'id' => $to->id,
                    'account_no' => $to->account_no,
                    'previous_balance' => $toPrevBalance,
                    'new_balance' => $toNewBalance,
                ],
            ],
            'reference_number' => $referenceNumber,
        ];
    }

    /**
     * @return array{success: bool, data?: array, message?: string, error?: string, http?: int}
     */
    private function processCheckerAction(
        int $transferId,
        string $comment,
        int $userId,
        string $newStatus,
        string $actionLabel,
        callable $applyFields,
        string $successMessage
    ): array {
        if (!$this->isChecker($userId)) {
            return [
                'success' => false,
                'error' => "You do not have permission to {$actionLabel} account transfers.",
                'http' => 403,
            ];
        }

        try {
            return DB::transaction(function () use ($transferId, $comment, $userId, $newStatus, $actionLabel, $applyFields, $successMessage) {
                $transfer = AccountTransfer::query()->lockForUpdate()->find($transferId);
                if (!$transfer) {
                    return ['success' => false, 'error' => 'Transfer not found', 'http' => 404];
                }

                if (!$transfer->isInProgress()) {
                    return ['success' => false, 'error' => "This transfer cannot be {$actionLabel}ed at its current stage", 'http' => 409];
                }

                if ((int) $transfer->created_by === $userId) {
                    return ['success' => false, 'error' => "You cannot {$actionLabel} your own transfer request", 'http' => 403];
                }

                if (!$this->canActOnTransfer($transfer, $userId)) {
                    return [
                        'success' => false,
                        'error' => "You cannot {$actionLabel} this transfer at its current stage",
                        'http' => 403,
                    ];
                }

                $transfer->status = $newStatus;
                $applyFields($transfer);
                $transfer->updated_by = $userId;
                $transfer->save();

                Log::info("Account transfer {$actionLabel}ed", [
                    'transfer_id' => $transfer->id,
                    'acted_by' => $userId,
                ]);

                return [
                    'success' => true,
                    'data' => $this->buildTransferResponse($transfer->fresh()),
                    'message' => $successMessage,
                    'http' => 200,
                ];
            });
        } catch (\Throwable $e) {
            Log::error("Account transfer {$actionLabel} failed", [
                'transfer_id' => $transferId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => "Failed to {$actionLabel} transfer: " . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string, errors?: array, http?: int}
     */
    private function validateAccounts(string $fromAccountNo, string $toAccountNo, float $amount, bool $checkBalance): array
    {
        $from = $this->findAccountByReference($fromAccountNo);
        $to = $this->findAccountByReference($toAccountNo);

        if (!$from) {
            return ['success' => false, 'error' => 'From account not found', 'errors' => ['from_account_id' => ['From account not found.']], 'http' => 404];
        }
        if (!$to) {
            return ['success' => false, 'error' => 'To account not found', 'errors' => ['to_account_id' => ['To account not found.']], 'http' => 404];
        }

        if ($from->status != '1') {
            return [
                'success' => false,
                'error' => 'Sender account is not active',
                'errors' => ['from_account_id' => ['Sender account is not active.']],
                'http' => 422,
            ];
        }
        if ($to->status != '1') {
            return [
                'success' => false,
                'error' => 'Receiver account is not active',
                'errors' => ['to_account_id' => ['Receiver account is not active.']],
                'http' => 422,
            ];
        }

        if ($checkBalance) {
            $fromBalance = (float) ($from->account_balance ?? 0);
            if ($fromBalance < $amount) {
                return [
                    'success' => false,
                    'error' => 'Insufficient balance',
                    'errors' => [
                        'amount' => ['Insufficient balance in sender account.'],
                        'current_balance' => [$fromBalance],
                        'transfer_amount' => [$amount],
                        'shortfall' => [$amount - $fromBalance],
                    ],
                    'http' => 422,
                ];
            }
        }

        return ['success' => true];
    }

    public function buildTransferResponse(AccountTransfer $transfer): array
    {
        $entries = DB::table('account_transaction')
            ->where('account_transfer_id', $transfer->id)
            ->orderBy('id')
            ->get();

        return [
            'transfer' => $this->sanitizeTransferForResponse($transfer),
            'entries' => $entries,
        ];
    }

    public function sanitizeTransferForResponse(array|AccountTransfer $transfer): array
    {
        $data = is_array($transfer) ? $transfer : $transfer->toArray();
        unset($data['approval_document_path']);

        $path = is_array($transfer)
            ? ($transfer['approval_document_path'] ?? null)
            : $transfer->approval_document_path;

        $data['has_approval_document'] = !empty($path);

        return $data;
    }

    private function normalizeAccountReference($value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function findAccountByReference(string $reference): ?Account
    {
        if ($reference === '') {
            return null;
        }

        $query = Account::query()->where('account_no', $reference);
        if (ctype_digit($reference)) {
            $query->orWhere('id', (int) $reference);
        }

        return $query->first();
    }
}
