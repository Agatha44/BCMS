<?php

namespace App\Services\Notifications;

use App\Helpers\DBHelper;
use Illuminate\Support\Facades\View;

class BmsEmailTemplateService
{
    /**
     * Render overtime notification HTML for the mail dispatcher API.
     *
     * @param  array<string, mixed>  $options
     */
    public function renderOvertime(array $options): string
    {
        $status = (string) ($options['status'] ?? '');

        return View::make('emails.overtime.notification', [
            'subject' => $options['subject'] ?? 'Overtime Notification',
            'moduleTitle' => 'Overtime Management',
            'headline' => $options['headline'] ?? 'Overtime Update',
            'greeting' => $options['greeting'] ?? null,
            'intro' => $options['intro'] ?? null,
            'statusLabel' => ($options['show_status_badge'] ?? true) ? ($options['status_label'] ?? $status) : null,
            'statusVariant' => $options['status_variant'] ?? $this->statusVariant($status),
            'details' => $options['details'] ?? [],
            'note' => $options['note'] ?? null,
            'warning' => $options['warning'] ?? null,
            'actionUrl' => $options['action_url'] ?? $this->defaultPortalUrl(),
            'actionLabel' => $options['action_label'] ?? 'Open BMS',
            'referenceCode' => $options['reference_code'] ?? null,
            'compact' => $options['compact'] ?? true,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    public function renderAttendanceViolation(array $violation, string $type): string
    {
        $isLate = $type === 'late_arrival';
        $violationLabel = $isLate ? 'Late Arrival' : 'Early Departure';
        $timeType = $isLate ? 'Arrival' : 'Departure';
        $minutesLabel = $isLate ? 'Minutes Late' : 'Minutes Early';
        $minutesValue = $isLate
            ? ($violation['minutes_late'] ?? '—')
            : ($violation['minutes_early'] ?? '—');

        $expectedTime = !empty($violation['expected_time'])
            ? date('d M Y, H:i', strtotime((string) $violation['expected_time']))
            : '—';
        $actualTime = !empty($violation['actual_time'])
            ? date('d M Y, H:i', strtotime((string) $violation['actual_time']))
            : '—';

        $employeeName = (string) ($violation['employee_name'] ?? 'Employee');

        return View::make('emails.attendance.violation', [
            'subject' => "{$violationLabel} — {$employeeName}",
            'moduleTitle' => 'Attendance',
            'compact' => true,
            'headline' => $violationLabel,
            'greeting' => "Dear {$employeeName},",
            'intro' => $isLate
                ? 'Our records show that you arrived later than your scheduled shift start time.'
                : 'Our records show that you left before your scheduled shift end time.',
            'statusLabel' => $violationLabel,
            'statusVariant' => 'warning',
            'details' => [
                ['label' => 'Employee', 'value' => $employeeName],
                ['label' => 'PF Number', 'value' => (string) ($violation['pf_number'] ?? '—')],
                ['label' => 'Date', 'value' => (string) ($violation['date'] ?? '—')],
                ['label' => 'Shift', 'value' => (string) ($violation['shift_name'] ?? '—')],
                ['label' => "Expected {$timeType}", 'value' => $expectedTime],
                ['label' => "Actual {$timeType}", 'value' => $actualTime],
                ['label' => $minutesLabel, 'value' => "{$minutesValue} minutes"],
            ],
            'note' => $isLate
                ? 'Please ensure you arrive on time for your scheduled shifts.'
                : 'Please ensure you complete your full scheduled shift duration.',
            'actionUrl' => $this->defaultPortalUrl(),
            'actionLabel' => 'View Attendance in BMS',
            'referenceCode' => isset($violation['pf_number'], $violation['date'])
                ? 'ATT-' . $violation['pf_number'] . '-' . $violation['date']
                : null,
        ])->render();
    }

    /**
     * @param  array<int, array{label: string, value: string|int|float}>  $details
     */
    public function overtimeSubmitted(
        string $employeeName,
        int $requestId,
        string $monthDisplay,
        $totalHours,
        $totalDays,
        array $extraDetails = []
    ): string {
        $details = array_merge([
            ['label' => 'Period', 'value' => $monthDisplay],
            ['label' => 'Overtime', 'value' => $this->formatDays($totalDays)],
        ], $extraDetails);

        return $this->renderOvertime([
            'subject' => "Overtime submitted — {$monthDisplay}",
            'headline' => 'Request submitted',
            'greeting' => "Hello {$employeeName},",
            'intro' => 'Your overtime request for the period of ' . $monthDisplay . ' was sent and is waiting for validation.',
            'status' => 'Submitted',
            'status_label' => 'Submitted',
            'status_variant' => 'success',
            'details' => $details,
            'note' => 'We will email you again after review.',
            'action_label' => 'Open BMS',
            'reference_code' => "OT-{$requestId}",
            'compact' => true,
        ]);
    }

    /**
     * @param  array<int, array{label: string, value: string|int|float}>  $details
     */
    public function overtimeApproverAction(
        string $recipientRole,
        string $employeeName,
        int $requestId,
        string $monthDisplay,
        $totalHours,
        $totalDays,
        string $status,
        array $extraDetails = []
    ): string {
        $details = array_merge([
            ['label' => 'Employee', 'value' => $employeeName],
            ['label' => 'Period', 'value' => $monthDisplay],
            ['label' => 'Overtime', 'value' => $this->formatDays($totalDays)],
        ], $extraDetails);

        return $this->renderOvertime([
            'subject' => 'Action Required: Overtime Request for Review',
            'headline' => 'Action Required',
            'greeting' => "Dear {$recipientRole},",
            'intro' => 'An overtime request requires your review and approval in BMS.',
            'status' => $status,
            'show_status_badge' => false,
            'details' => $details,
            'note' => 'Open BMS to approve or reject.',
            'action_label' => 'Review in BMS',
            'reference_code' => "OT-{$requestId}",
            'compact' => true,
        ]);
    }

    /**
     * @param  array<int, array{label: string, value: string|int|float}>  $details
     */
    public function overtimeStatusUpdate(
        string $employeeName,
        int $requestId,
        string $monthDisplay,
        string $status,
        string $headline,
        string $intro,
        array $details = [],
        ?string $comment = null
    ): string {
        $rows = array_merge([
            ['label' => 'Period', 'value' => $monthDisplay],
            ['label' => 'Status', 'value' => $status],
        ], $details);

        if ($comment !== null && $comment !== '') {
            $rows[] = ['label' => 'Comment / Reason', 'value' => $comment];
        }

        $options = [
            'subject' => "Overtime — {$status}",
            'headline' => $headline,
            'greeting' => "Hi {$employeeName},",
            'intro' => $intro,
            'status' => $status,
            'status_label' => $status,
            'status_variant' => $this->statusVariant($status),
            'details' => $rows,
            'reference_code' => "OT-{$requestId}",
            'compact' => true,
        ];

        if (str_contains(strtolower($status), 'reject')) {
            $options['warning'] = 'If you have questions, please contact your supervisor or HR department.';
        }

        return $this->renderOvertime($options);
    }

    private function statusVariant(string $status): string
    {
        $normalized = strtolower($status);

        if (str_contains($normalized, 'reject') || str_contains($normalized, 'failed')) {
            return 'danger';
        }

        if (str_contains($normalized, 'approv') || str_contains($normalized, 'complet')) {
            return 'success';
        }

        if (str_contains($normalized, 'pending') || str_contains($normalized, 'validat') || str_contains($normalized, 'process')) {
            return 'warning';
        }

        return 'info';
    }

    private function defaultPortalUrl(): ?string
    {
        $url = DBHelper::getBmsPortalUrl();

        return $url !== '' ? $url : null;
    }

    private function formatHours($hours): string
    {
        $value = is_numeric($hours) ? (float) $hours : 0.0;
        $label = abs($value - 1.0) < 0.001 ? 'hr' : 'hrs';

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $label;
    }

    private function formatDays($days): string
    {
        $value = is_numeric($days) ? (int) $days : 0;

        return $value . ($value === 1 ? ' day' : ' days');
    }
}
