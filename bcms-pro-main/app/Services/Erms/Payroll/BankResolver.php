<?php

namespace App\Services\Erms\Payroll;

use App\Models\Bms\Bank;

/**
 * Resolves an employee salary bank to a payroll net-pay ERMS batch bucket.
 *
 * Buckets: CRDB, NBC, NMB, or other_banks (non-primary banks, disbursed via CRDB).
 */
class BankResolver
{
    /** @var array<string, array{label: string, source_ref_suffix: string, matchers: list<string>, pay_bank_account_number: string}>|null */
    private ?array $buckets = null;

    public function bucketKeys(): array
    {
        return array_keys($this->bucketDefinitions());
    }

    public function resolve(Bank $bank): string
    {
        $haystacks = $this->bankMatchHaystacks($bank);

        foreach ($this->bucketDefinitions() as $key => $definition) {
            if ($key === 'other_banks') {
                continue;
            }
            if ($this->matchesAny($haystacks, $definition['matchers'])) {
                return $key;
            }
        }

        return 'other_banks';
    }

    public function label(string $bucketKey): string
    {
        return (string) ($this->bucketDefinitions()[$this->normalizeBucketKey($bucketKey)]['label'] ?? strtoupper($bucketKey));
    }

    public function sourceRefSuffix(string $bucketKey): string
    {
        $suffix = (string) ($this->bucketDefinitions()[$this->normalizeBucketKey($bucketKey)]['source_ref_suffix'] ?? '');

        return $suffix !== '' ? $suffix : 'NET-'.strtoupper(str_replace('_', '-', $bucketKey));
    }

    /**
     * NSSF / bridge paying bank account used when submitting this net-pay batch to ERMS.
     */
    public function payBankAccountNumber(string $bucketKey): string
    {
        $key = $this->normalizeBucketKey($bucketKey);
        $defs = $this->bucketDefinitions();

        if (! array_key_exists($key, $defs)) {
            throw new \InvalidArgumentException("Unknown net-pay bank bucket: {$bucketKey}.");
        }

        $account = trim((string) ($defs[$key]['pay_bank_account_number'] ?? ''));
        if ($account !== '') {
            return $account;
        }

        throw new \InvalidArgumentException(
            "Missing pay_bank_account_number for net-pay bucket \"{$key}\". "
            ."Configure erms.payroll_net_pay_bank_buckets.{$key}.pay_bank_account_number."
        );
    }

    public function isKnownBucket(string $bucketKey): bool
    {
        return array_key_exists($this->normalizeBucketKey($bucketKey), $this->bucketDefinitions());
    }

    public function normalizeBucketKey(string $bucketKey): string
    {
        $key = strtolower(trim($bucketKey));
        $aliases = [
            'net-other-banks' => 'other_banks',
            'net-crdb' => 'crdb',
            'net-nbc' => 'nbc',
            'net-nmb' => 'nmb',
        ];

        return $aliases[$key] ?? $key;
    }

    /**
     * @return list<string>
     */
    private function bankMatchHaystacks(Bank $bank): array
    {
        $parts = [
            (string) ($bank->short_name ?? ''),
            (string) ($bank->bank_name ?? ''),
            (string) ($bank->swift_code ?? ''),
            (string) ($bank->bank_code ?? ''),
            (string) ($bank->bi_code ?? ''),
        ];

        return array_values(array_filter(array_map(
            static fn (string $v) => strtoupper(trim($v)),
            $parts
        )));
    }

    /**
     * @param  list<string>  $haystacks
     * @param  list<string>  $matchers
     */
    private function matchesAny(array $haystacks, array $matchers): bool
    {
        foreach ($matchers as $needle) {
            $needle = strtoupper(trim((string) $needle));
            if ($needle === '') {
                continue;
            }
            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, array{label: string, source_ref_suffix: string, matchers: list<string>, pay_bank_account_number: string}>
     */
    private function bucketDefinitions(): array
    {
        if ($this->buckets !== null) {
            return $this->buckets;
        }

        $configured = config('erms.payroll_net_pay_bank_buckets', []);
        if ($configured === [] || $configured === null) {
            $legacy = config('erms.payroll_net_pay', []);
            $configured = is_array($legacy) ? $legacy : [];
        }
        $configured = is_array($configured) ? $configured : [];

        $defaults = [
            'crdb' => [
                'label' => 'CRDB',
                'source_ref_suffix' => 'NET-CRDB',
                'matchers' => ['CRDB'],
                'pay_bank_account_number' => '',
            ],
            'nbc' => [
                'label' => 'NBC',
                'source_ref_suffix' => 'NET-NBC',
                'matchers' => ['NBC'],
                'pay_bank_account_number' => '',
            ],
            'nmb' => [
                'label' => 'NMB',
                'source_ref_suffix' => 'NET-NMB',
                'matchers' => ['NMB'],
                'pay_bank_account_number' => '',
            ],
            'other_banks' => [
                'label' => 'Other Banks',
                'source_ref_suffix' => 'NET-OTHER-BANKS',
                'matchers' => [],
                'pay_bank_account_number' => '',
            ],
        ];

        $merged = [];
        foreach ($defaults as $key => $default) {
            $row = is_array($configured[$key] ?? null) ? $configured[$key] : [];
            $match = $row['match'] ?? $row['matchers'] ?? $default['matchers'];
            $match = is_array($match) ? $match : $default['matchers'];

            $account = trim((string) (
                $row['pay_bank_account_number']
                ?? $row['payer_bank_account_number']
                ?? ''
            ));
            if ($account === '' && $key === 'other_banks') {
                $account = trim((string) ($merged['crdb']['pay_bank_account_number'] ?? ''));
            }

            $merged[$key] = [
                'label' => (string) ($row['label'] ?? $default['label']),
                'source_ref_suffix' => (string) ($row['source_ref_suffix'] ?? $default['source_ref_suffix']),
                'matchers' => array_values(array_map('strval', $match)),
                'pay_bank_account_number' => $account,
            ];
        }

        $this->buckets = $merged;

        return $this->buckets;
    }
}
