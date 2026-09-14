<?php

namespace App\Constants;

class AccountTransferStatus
{
    public const PENDING = 'pending';

    public const REVIEWED = 'reviewed';

    public const VERIFIED = 'verified';

    public const RETURNED = 'returned';

    public const POSTED = 'posted';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    public const REVERSED = 'reversed';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::REVIEWED,
            self::VERIFIED,
            self::RETURNED,
            self::POSTED,
            self::REJECTED,
            self::FAILED,
            self::REVERSED,
        ];
    }

    public static function inProgress(): array
    {
        return [
            self::PENDING,
            self::REVIEWED,
            self::VERIFIED,
        ];
    }

    public static function isPending(string $status): bool
    {
        return $status === self::PENDING;
    }

    public static function isReviewed(string $status): bool
    {
        return $status === self::REVIEWED;
    }

    public static function isVerified(string $status): bool
    {
        return $status === self::VERIFIED;
    }

    public static function isReturned(string $status): bool
    {
        return $status === self::RETURNED;
    }

    public static function isInProgress(string $status): bool
    {
        return in_array($status, self::inProgress(), true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::POSTED, self::REJECTED, self::FAILED, self::REVERSED], true);
    }
}
