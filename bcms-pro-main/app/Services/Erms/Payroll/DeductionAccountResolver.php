<?php

namespace App\Services\Erms\Payroll;

use InvalidArgumentException;

/**
 * Maps payroll deduction rows to ERMS credit account + GFS by explicit deduction kind only.
 *
 * Every deduction must match a configured kind in erms.payroll_deduction_accounts with a
 * corresponding {kind}_account_code / {kind}_gfs_code in erms.payable_settings.
 */
class DeductionAccountResolver
{
    public function accountCode(object $deductionRow): string
    {
        return $this->resolve($deductionRow)['account_code'];
    }

    public function gfsCode(object $deductionRow): string
    {
        return $this->resolve($deductionRow)['gfs_code'];
    }

    public function kind(object $deductionRow): string
    {
        return $this->classify($deductionRow);
    }

    /**
     * @return array{kind: string, account_code: string, gfs_code: string}
     */
    public function resolve(object $deductionRow): array
    {
        $kind = $this->classify($deductionRow);
        $map = $this->accountMap();

        if (! array_key_exists($kind, $map)) {
            throw new InvalidArgumentException(
                "No ERMS credit account configured for deduction kind \"{$kind}\". "
                ."Set erms.payable_settings.{$kind}_account_code."
            );
        }

        $entry = $map[$kind];
        $accountCode = trim((string) ($entry['account_code'] ?? ''));
        $gfsCode = trim((string) ($entry['gfs_code'] ?? ''));

        if ($accountCode === '') {
            throw new InvalidArgumentException(
                "Missing ERMS account code for deduction kind \"{$kind}\". Check erms.payable_settings."
            );
        }

        return [
            'kind' => $kind,
            'account_code' => $accountCode,
            'gfs_code' => $gfsCode !== '' ? $gfsCode : '0',
        ];
    }

    public function classify(object $deductionRow): string
    {
        $kind = $this->tryClassify($deductionRow);
        if ($kind !== null) {
            return $kind;
        }

        throw new InvalidArgumentException($this->unmappedDeductionMessage($deductionRow));
    }

    public function tryClassify(object $deductionRow): ?string
    {
        $code = $this->normalize((string) ($deductionRow->deduction_code ?? ''));
        $name = $this->normalize((string) ($deductionRow->display_name ?? ''));

        foreach ($this->matchers() as $kind => $patterns) {
            foreach ($patterns as $pattern) {
                $pattern = $this->normalize((string) $pattern);
                if ($pattern === '') {
                    continue;
                }
                if ($code === $pattern || str_contains($code, $pattern) || str_contains($name, $pattern)) {
                    return $kind;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function configuredKinds(): array
    {
        return array_keys($this->matchers());
    }

    private function unmappedDeductionMessage(object $deductionRow): string
    {
        $label = trim((string) ($deductionRow->display_name ?? ''));
        if ($label === '') {
            $label = trim((string) ($deductionRow->deduction_code ?? ''));
        }
        if ($label === '') {
            $label = 'unknown deduction';
        }

        return "Deduction \"{$label}\" is not mapped to an ERMS account kind. "
            .'Add erms.payroll_deduction_accounts.{kind}.match and erms.payable_settings.{kind}_account_code.';
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @return array<string, list<string>>
     */
    private function matchers(): array
    {
        $configured = config('erms.payroll_deduction_accounts', []);
        $configured = is_array($configured) ? $configured : [];

        $payables = config('erms.payroll_deduction_payables', []);
        $payables = is_array($payables) ? $payables : [];

        $institutions = config('erms.payroll_institution_payees', []);
        $institutions = is_array($institutions) ? $institutions : [];

        $kinds = array_unique(array_merge(
            array_keys($configured),
            array_keys($payables),
            array_keys($institutions),
        ));

        $merged = [];
        foreach ($kinds as $kind) {
            $kind = strtolower(trim((string) $kind));
            if ($kind === '') {
                continue;
            }

            $row = is_array($configured[$kind] ?? null) ? $configured[$kind] : [];
            $match = $row['match'] ?? $row['matchers'] ?? [];
            $match = is_array($match) ? $match : [];

            if ($match === []) {
                continue;
            }

            $merged[$kind] = array_values(array_map('strval', $match));
        }

        return $merged;
    }

    /**
     * @return array<string, array{account_code: string, gfs_code: string}>
     */
    private function accountMap(): array
    {
        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];

        $map = [];

        foreach (array_keys($this->matchers()) as $kind) {
            $accountCode = trim((string) ($payable["{$kind}_account_code"] ?? ''));
            if ($accountCode === '') {
                continue;
            }

            $map[$kind] = [
                'account_code' => $accountCode,
                'gfs_code' => (string) ($payable["{$kind}_gfs_code"] ?? '0'),
            ];
        }

        return $map;
    }
}
