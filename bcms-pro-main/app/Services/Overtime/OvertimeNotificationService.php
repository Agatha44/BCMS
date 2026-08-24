<?php

namespace App\Services\Overtime;

use App\Models\AuthUser;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\OvertimeRequest;
use App\Models\Notifications\Notifications;
use App\Services\Notifications\BmsEmailTemplateService;
use Illuminate\Support\Facades\Log;

class OvertimeNotificationService
{
    private OvertimeQueryService $overtimeQueryService;
    private BmsEmailTemplateService $emailTemplates;

    public function __construct(
        OvertimeQueryService $overtimeQueryService,
        BmsEmailTemplateService $emailTemplates
    ) {
        $this->overtimeQueryService = $overtimeQueryService;
        $this->emailTemplates = $emailTemplates;
    }

    public function sendRequestSubmittedNotifications(OvertimeRequest $overtimeRequest, array $notificationData, array $validatorUserIds): void
    {
        $monthDisplay = $notificationData['monthDisplay'];
        $totalOvertimeHours = $notificationData['totalOvertimeHours'];
        $totalDays = $notificationData['totalDays'];
        $pfNumber = $overtimeRequest->pf_number;

        $employee = $this->overtimeQueryService->getEmployeeByPfNumber($pfNumber);
        $employeeName = $this->overtimeQueryService->formatEmployeeName($employee) ?? 'Employee';

        $data = [
            'request_id' => $overtimeRequest->id,
            'month' => $monthDisplay,
            'total_hours' => $totalOvertimeHours,
            'total_days' => $totalDays,
            'status' => 'Pending',
            'employee_name' => $employeeName,
        ];

        $subject = "Overtime Request Submitted — {$monthDisplay}";
        $html = $this->emailTemplates->overtimeSubmitted(
            $employeeName,
            $overtimeRequest->id,
            $monthDisplay,
            $totalOvertimeHours,
            $totalDays
        );

        $this->sendNotificationToEmployee(
            $pfNumber,
            $subject,
            strip_tags($html),
            'Overtime Request Submission',
            array_merge($data, ['html_body' => $html])
        );

        if (!empty($validatorUserIds)) {
            $approverHtml = $this->emailTemplates->overtimeApproverAction(
                'Validator',
                $employeeName,
                $overtimeRequest->id,
                $monthDisplay,
                $totalOvertimeHours,
                $totalDays,
                'Pending Validation'
            );

            $this->sendNotificationToApprovers(
                $validatorUserIds,
                'Action Required: New Overtime Request for Review',
                strip_tags($approverHtml),
                'Overtime Approval Required',
                array_merge($data, ['html_body' => $approverHtml])
            );
        }
    }

    public function sendWorkflowNotifications(OvertimeRequest $overtimeRequest, string $newStatus, string $comment, array $reviewerUserIds = []): void
    {
        $employee = $this->overtimeQueryService->getEmployeeByPfNumber($overtimeRequest->pf_number);
        $employeeName = $this->overtimeQueryService->formatEmployeeName($employee) ?? 'Employee';
        $monthDisplay = $overtimeRequest->month->format('F Y');

        $statusConfig = $this->workflowStatusEmailConfig($newStatus, $employeeName, $monthDisplay, $comment);

        if ($statusConfig !== null) {
            $html = $this->emailTemplates->overtimeStatusUpdate(
                $employeeName,
                $overtimeRequest->id,
                $monthDisplay,
                $newStatus,
                $statusConfig['headline'],
                $statusConfig['intro'],
                [
                    ['label' => 'Overtime', 'value' => (string) (string) $overtimeRequest->total_days . ' days'],
                ],
                $statusConfig['comment'] ?? null
            );

            $this->sendNotificationToEmployee(
                $overtimeRequest->pf_number,
                $statusConfig['subject'],
                strip_tags($html),
                'Overtime Request Update',
                [
                    'request_id' => $overtimeRequest->id,
                    'month' => $monthDisplay,
                    'total_hours' => $overtimeRequest->total_overtime_hours,
                    'total_days' => $overtimeRequest->total_days,
                    'status' => $newStatus,
                    'employee_name' => $employeeName,
                    'html_body' => $html,
                ]
            );
        }

        if ($newStatus === 'Validator Approved' && !empty($reviewerUserIds)) {
            $approverHtml = $this->emailTemplates->overtimeApproverAction(
                'Reviewer',
                $employeeName,
                $overtimeRequest->id,
                $monthDisplay,
                $overtimeRequest->total_overtime_hours,
                $overtimeRequest->total_days,
                'Validator Approved'
            );

            $this->sendNotificationToApprovers(
                $reviewerUserIds,
                'Action Required: Overtime Request Pending Review',
                strip_tags($approverHtml),
                'Overtime Review Required',
                [
                    'request_id' => $overtimeRequest->id,
                    'month' => $monthDisplay,
                    'total_days' => $overtimeRequest->total_days,
                    'status' => 'Validator Approved',
                    'employee_name' => $employeeName,
                    'html_body' => $approverHtml,
                ]
            );
        }
    }

    /**
     * @return array{subject: string, headline: string, intro: string, comment?: string}|null
     */
    private function workflowStatusEmailConfig(
        string $newStatus,
        string $employeeName,
        string $monthDisplay,
        string $comment
    ): ?array {
        $configs = [
            'Validator Approved' => [
                'subject' => "Overtime Request Approved by Validator - {$monthDisplay}",
                'headline' => 'Approved by Validator',
                'intro' => "Your overtime request for <strong>{$monthDisplay}</strong> has been approved by the validator and is pending reviewer approval.",
            ],
            'Reviewer Approved' => [
                'subject' => "Overtime Request Approved by Reviewer - {$monthDisplay}",
                'headline' => 'Approved by Reviewer',
                'intro' => "Your overtime request for <strong>{$monthDisplay}</strong> has been approved by the reviewer and will proceed to payment processing.",
            ],
            'Rejected' => [
                'subject' => "Overtime Request Rejected - {$monthDisplay}",
                'headline' => 'Request Rejected',
                'intro' => "Your overtime request for <strong>{$monthDisplay}</strong> has been rejected.",
                'comment' => $comment !== '' ? $comment : null,
            ],
            'Returned' => [
                'subject' => "Overtime Request Returned for Correction - {$monthDisplay}",
                'headline' => 'Returned for Correction',
                'intro' => "Your overtime request for <strong>{$monthDisplay}</strong> has been returned for correction. Please review the comment below, update your request, and resubmit.",
                'comment' => $comment !== '' ? $comment : null,
            ],
            'In Batch' => [
                'subject' => "Overtime Request Added to Payment Batch - {$monthDisplay}",
                'headline' => 'Added to Payment Batch',
                'intro' => "Your overtime request for <strong>{$monthDisplay}</strong> has been added to a payment batch.",
            ],
            'Submitted to Payment' => [
                'subject' => "Overtime Request Submitted to Payment - {$monthDisplay}",
                'headline' => 'Submitted for Payment',
                'intro' => "Your overtime request for <strong>{$monthDisplay}</strong> has been submitted to the payment system.",
            ],
            'Payment Approved' => [
                'subject' => "Overtime Payment Approved - {$monthDisplay}",
                'headline' => 'Payment Approved',
                'intro' => "Your overtime payment for <strong>{$monthDisplay}</strong> has been approved and will be processed shortly.",
            ],
            'Payment Processing' => [
                'subject' => "Overtime Payment Processing - {$monthDisplay}",
                'headline' => 'Payment Processing',
                'intro' => "Your overtime payment for <strong>{$monthDisplay}</strong> is currently being processed.",
            ],
            'Payment Completed' => [
                'subject' => "Overtime Payment Completed - {$monthDisplay}",
                'headline' => 'Payment Completed',
                'intro' => "Your overtime payment for <strong>{$monthDisplay}</strong> has been completed successfully.",
            ],
            'Payment Rejected' => [
                'subject' => "Overtime Payment Rejected - {$monthDisplay}",
                'headline' => 'Payment Rejected',
                'intro' => "Your overtime payment for <strong>{$monthDisplay}</strong> has been rejected.",
                'comment' => $comment !== '' ? $comment : null,
            ],
        ];

        return $configs[$newStatus] ?? null;
    }

    public function sendNotificationToEmployee($pfNumber, $subject, $message, $process = 'Overtime Request', $data = []): void
    {
        try {
            $email = $this->getEmailFromPfNumber($pfNumber);
            if ($email) {
                Notifications::pushEmailNotification($email, $subject, $message, $process, $data);
                Log::info('Email notification sent to employee', [
                    'pf_number' => $pfNumber,
                    'email' => $email,
                    'process' => $process,
                ]);
            } else {
                Log::warning('Email address not found for employee', [
                    'pf_number' => $pfNumber,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send email notification to employee', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendNotificationToApprovers(array $userIds, $subject, $message, $process = 'Overtime Approval', $data = []): void
    {
        try {
            foreach ($userIds as $userId) {
                $email = $this->getEmailFromUserId($userId);
                if ($email) {
                    Notifications::pushEmailNotification($email, $subject, $message, $process, $data);
                    Log::info('Email notification sent to approver', [
                        'user_id' => $userId,
                        'email' => $email,
                        'process' => $process,
                    ]);
                } else {
                    Log::warning('Email address not found for approver', [
                        'user_id' => $userId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send email notification to approvers', [
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getEmailFromPfNumber($pfNumber): ?string
    {
        if (!$pfNumber) {
            return null;
        }

        try {
            $user = AuthUser::where('pf_number', $pfNumber)->first();
            if ($user && !empty($user->email)) {
                return $user->email;
            }

            $employee = BridgeEmployee::byPfno($pfNumber)->first();

            return $employee->email ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to get email from PF number', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getEmailFromUserId($userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = AuthUser::find($userId);

        return $user->email ?? null;
    }
}
