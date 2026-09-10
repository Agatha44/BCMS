<?php

namespace App\Constants;

class AccountTransferRequest
{
    public const TYPE_FUND_TRANSFER = 'fund_transfer';

    public const ACTION_CASHLESS_TO_CASHLESS = 'cashless_to_cashless';
    public const ACTION_CASHLESS_TO_PENSION = 'cashless_to_pension';
    public const ACTION_PENSION_TO_CASHLESS = 'pension_to_cashless';
    public const ACTION_BUNDLE_TO_CASHLESS = 'bundle_to_cashless';
    public const ACTION_CASHLESS_TO_BUNDLE = 'cashless_to_bundle';

    public static function types(): array
    {
        return [
            self::TYPE_FUND_TRANSFER,
        ];
    }

    public static function actions(): array
    {
        return [
            self::ACTION_CASHLESS_TO_CASHLESS,
            self::ACTION_CASHLESS_TO_PENSION,
            self::ACTION_PENSION_TO_CASHLESS,
            self::ACTION_BUNDLE_TO_CASHLESS,
            self::ACTION_CASHLESS_TO_BUNDLE,
        ];
    }
}
