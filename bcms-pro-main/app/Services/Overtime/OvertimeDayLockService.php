<?php

namespace App\Services\Overtime;

use App\Models\Bms\OvertimeRequestDay;

class OvertimeDayLockService
{
    public const BLOCKING_STATUSES = [
        'Pending',
        'Validator Approved',
        'Reviewer Approved',
        'In Batch',
        'Submitted to Payment',
        'Payment Approved',
        'Payment Processing',
        'Payment Completed',
    ];

    public function isDayBlocked(string $pfNumber, string $date, ?int $excludeRequestId = null): bool
    {
        $query = OvertimeRequestDay::whereHas('overtimeRequest', function ($q) use ($pfNumber) {
            $q->where('pf_number', $pfNumber)
                ->whereIn('status', self::BLOCKING_STATUSES);
        })->whereDate('day_date', $date);

        if ($excludeRequestId !== null) {
            $query->where('overtime_request_id', '!=', $excludeRequestId);
        }

        return $query->exists();
    }
}
