<?php

namespace App\Constants;

class EmployeeStatus
{
    // Pending approval statuses
    const PENDING_APPROVAL = 'PENDING_APPROVAL';
    const PENDING_CREATION = 'pending_creation';
    const PENDING_TERMINATION = 'pending_termination';
    const PENDING_DELETION = 'pending_deletion';
    const PENDING_UPDATE = 'pending_update';

    // Approved/Active statuses
    const APPROVED = 'approved';
    /** Canonical employment code stored on bridge_employee.employee_status */
    const ACTIVE = 'A';

    // Terminated/Deleted statuses
    /** Canonical employment code stored on bridge_employee.employee_status */
    const TERMINATED = 'T';
    const DELETED = 'deleted';

    // Rejected status
    const REJECTED = 'rejected';

    /**
     * Employment-state aliases stored on bridge_employee.employee_status
     * (legacy source codes plus app-normalized values).
     */
    public static function activeValues(): array
    {
        return ['A', 'a', 'Active', 'active'];
    }

    public static function terminatedValues(): array
    {
        return ['T', 't', 'Terminated', 'terminated'];
    }

    public static function isActive(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['a', 'active'], true);
    }

    public static function isTerminated(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['t', 'terminated'], true);
    }

    /**
     * Get all statuses
     */
    public static function all(): array
    {
        return [
            self::PENDING_APPROVAL,
            self::PENDING_CREATION,
            self::PENDING_TERMINATION,
            self::PENDING_DELETION,
            self::PENDING_UPDATE,
            self::APPROVED,
            self::ACTIVE,
            self::TERMINATED,
            self::DELETED,
            self::REJECTED,
        ];
    }

    /**
     * Get pending statuses
     */
    public static function pending(): array
    {
        return [
            self::PENDING_APPROVAL,
            self::PENDING_CREATION,
            self::PENDING_TERMINATION,
            self::PENDING_DELETION,
            self::PENDING_UPDATE,
        ];
    }

    /**
     * Check if status is pending
     */
    public static function isPending(string $status): bool
    {
        return in_array($status, self::pending());
    }

    /**
     * Check if status requires approval
     */
    public static function requiresApproval(string $status): bool
    {
        return self::isPending($status);
    }

    /**
     * Get status display name
     */
    public static function displayName(string $status): string
    {
        if (self::isActive($status)) {
            return 'Active';
        }

        if (self::isTerminated($status)) {
            return 'Terminated';
        }

        return match ($status) {
            self::PENDING_APPROVAL => 'Pending Approval',
            self::PENDING_CREATION => 'Pending Creation Approval',
            self::PENDING_TERMINATION => 'Pending Termination Approval',
            self::PENDING_DELETION => 'Pending Deletion Approval',
            self::PENDING_UPDATE => 'Pending Update Approval',
            self::APPROVED => 'Approved',
            self::DELETED => 'Deleted',
            self::REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
