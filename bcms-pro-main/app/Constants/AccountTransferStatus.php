<?php

namespace App\Constants;

class AccountTransferStatus
{
    public const PENDING = 'pending';

    public const RETURNED = 'returned';

    public const POSTED = 'posted';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    public const REVERSED = 'reversed';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::RETURNED,
            self::POSTED,
            self::REJECTED,
            self::FAILED,
            self::REVERSED,
        ];
    }

    public static function isPending(string $status): bool
    {
        return $status === self::PENDING;
    }

    public static function isReturned(string $status): bool
    {
        return $status === self::RETURNED;
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::POSTED, self::REJECTED, self::FAILED, self::REVERSED], true);
    }
}
