<?php

namespace App\Services\Erms;

final class CreditedAccountResolver
{
    /**
     * Resolve coding details for the given credited account number.
     *
     * @return array{account_code: string, activity_code: string, gfs_code: string, currency_code: string}|null
     */
    public function resolve(?string $creditedAccNum): ?array
    {
        $creditedAccNum = $this->normalize($creditedAccNum);
        if ($creditedAccNum === '') {
            return null;
        }

        $map = config('credited_accounts', []);
        if (! is_array($map) || $map === []) {
            return null;
        }

        $row = $map[$creditedAccNum] ?? null;
        if (! is_array($row) || $row === []) {
            return null;
        }

        $account = (string) ($row['account_code'] ?? '');
        $activity = (string) ($row['activity_code'] ?? '');
        $gfs = (string) ($row['gfs_code'] ?? '');
        $currency = strtoupper((string) ($row['currency_code'] ?? ''));

        if ($account === '' && $activity === '' && $gfs === '' && $currency === '') {
            return null;
        }

        return [
            'account_code' => $account,
            'activity_code' => $activity,
            'gfs_code' => $gfs,
            'currency_code' => $currency,
        ];
    }

    private function normalize(?string $value): string
    {
        $value = (string) ($value ?? '');
        $value = preg_replace('/\s+/', '', $value) ?? '';

        return trim($value);
    }
}

