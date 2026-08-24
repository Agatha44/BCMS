<?php

namespace App\Services\Erms\Payroll;

use App\Models\Bms\Payroll\PayrollRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges deduction matchers, payable behaviour, and institution payee config per kind.
 */
class PayrollDeductionPayableConfig
{
    /**
     * Registered payable kinds (config order). Includes aliases as separate lookup keys.
     *
     * @return list<string>
     */
    public function kinds(): array
    {
        $kinds = [];
        foreach ($this->payableDefinitions() as $kind => $definition) {
            if (! is_array($definition) || ! ($definition['enabled'] ?? true)) {
                continue;
            }
            $kinds[] = strtolower(trim((string) $kind));
        }

        return array_values(array_unique($kinds));
    }

    public function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        foreach ($this->payableDefinitions() as $canonical => $definition) {
            if ($canonical === $kind) {
                return $canonical;
            }
            $aliases = $definition['aliases'] ?? [];
            if (! is_array($aliases)) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (strtolower(trim((string) $alias)) === $kind) {
                    return $canonical;
                }
            }
        }

        return $kind;
    }

    public function isKnownKind(string $kind): bool
    {
        $kind = $this->normalizeKind($kind);

        return array_key_exists($kind, $this->payableDefinitions())
            && $this->isEnabled($kind);
    }

    public function isEnabled(string $kind): bool
    {
        $kind = $this->normalizeKind($kind);
        $payables = $this->payableDefinitions();

        if (! array_key_exists($kind, $payables)) {
            return false;
        }

        $definition = $payables[$kind];

        return (bool) (is_array($definition) ? ($definition['enabled'] ?? true) : true);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $kind): array
    {
        $kind = $this->normalizeKind($kind);
        $payables = $this->payableDefinitions();
        if (! array_key_exists($kind, $payables)) {
            throw new InvalidArgumentException("Unknown payroll deduction payable kind: {$kind}.");
        }

        $payable = $payables[$kind];
        $payable = is_array($payable) ? $payable : [];

        $institution = config("erms.payroll_institution_payees.{$kind}", []);
        $institution = is_array($institution) ? $institution : [];

        $accounts = config('erms.payroll_deduction_accounts.'.$kind, []);
        $accounts = is_array($accounts) ? $accounts : [];

        return array_merge($payable, [
            'kind' => $kind,
            'match' => $accounts['match'] ?? $payable['match'] ?? [],
            'institution' => array_merge($institution, is_array($payable['institution'] ?? null) ? $payable['institution'] : []),
            'label' => (string) (
                $payable['label']
                ?? $institution['label']
                ?? strtoupper($kind)
            ),
            'source_ref_suffix' => (string) (
                $payable['source_ref_suffix']
                ?? $institution['source_ref_suffix']
                ?? strtoupper($kind)
            ),
            'amount_source' => (string) ($payable['amount_source'] ?? 'deduction_items'),
            'debit_strategy' => (string) ($payable['debit_strategy'] ?? 'auto'),
            'transaction_column' => (string) ($payable['transaction_column'] ?? ''),
            'credit_account_key' => (string) ($payable['credit_account_key'] ?? $kind),
        ]);
    }

    /**
     * Whether matched deduction_type rows define a non-null, non-zero employer contribution.
     */
    public function kindHasEmployerContribution(string $kind): bool
    {
        $typeIds = $this->deductionTypeIdsForKind($kind);
        if ($typeIds === []) {
            return false;
        }

        return DB::connection('bcmis2')
            ->table('deduction_type')
            ->whereIn('deduction_type_id', $typeIds)
            ->whereNotNull('employer_contribution_percentage')
            ->where('employer_contribution_percentage', '>', 0)
            ->exists();
    }

    /**
     * Resolves debit journal layout for a payable kind.
     *
     * - auto (default): employee_employer when deduction_type has employer contribution, else single
     * - employee_employer / single: forced
     */
    public function resolveDebitStrategy(PayrollRun $run, string $kind): string
    {
        unset($run);

        $kind = $this->normalizeKind($kind);
        $configured = strtolower(trim((string) ($this->definition($kind)['debit_strategy'] ?? 'auto')));

        if (in_array($configured, ['single', 'employee_employer'], true)) {
            return $configured;
        }

        return $this->kindHasEmployerContribution($kind) ? 'employee_employer' : 'single';
    }

    /**
     * @return list<int>
     */
    public function deductionTypeIdsForKind(string $kind): array
    {
        $kind = $this->normalizeKind($kind);

        try {
            $matchers = $this->definition($kind)['match'] ?? [];
        } catch (InvalidArgumentException) {
            $matchers = [];
        }

        $matchers = is_array($matchers) ? $matchers : [];
        $matchers = array_values(array_filter(array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            $matchers
        )));

        if ($matchers === []) {
            $matchers = [$kind];
        }

        $query = DB::connection('bcmis2')->table('deduction_type');
        $query->where(function ($q) use ($matchers) {
            foreach ($matchers as $pattern) {
                $q->orWhereRaw('LOWER(deduction_code) = ?', [$pattern])
                    ->orWhereRaw('LOWER(deduction_name) LIKE ?', ['%'.$pattern.'%']);
            }
        });

        return $query
            ->pluck('deduction_type_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    /**
     * Shared credit GL for all payroll deduction payables (PAYE, PSSSF, HESLB, …).
     * Institution / amount still differ per kind; only the credit account is common.
     *
     * @return array{account_code: string, gfs_code: string}
     */
    public function creditAccount(string $kind): array
    {
        $kind = $this->normalizeKind($kind);

        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];

        $accountCode = trim((string) (
            $payable['payable_supplier_account_code']
        ));
        $gfsCode = trim((string) ($payable['payable_supplier_gfs_code']));

        if ($accountCode === '') {
            throw new InvalidArgumentException(
                'Missing shared credit account for payroll deduction payables. '
                .'Set erms.payable_settings.payable_supplier_account_code (ERMS_PAYABLE_SUPPLIER_ACCOUNT_CODE).'
            );
        }

        return [
            'account_code' => $accountCode,
            'gfs_code' => $gfsCode !== '' ? $gfsCode : '0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function institution(string $kind): array
    {
        $institution = $this->definition($kind)['institution'] ?? [];
        $institution = is_array($institution) ? $institution : [];

        if (trim((string) ($institution['name'] ?? '')) === '') {
            throw new InvalidArgumentException(
                "Missing institution payee for deduction payable \"{$kind}\" (erms.payroll_institution_payees.{$kind})."
            );
        }

        return $institution;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function payableDefinitions(): array
    {
        $configured = config('erms.payroll_deduction_payables', []);
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return $this->defaultPayableDefinitions();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultPayableDefinitions(): array
    {
        $institutionKinds = config('erms.payroll_institution_payees', []);
        $institutionKinds = is_array($institutionKinds) ? $institutionKinds : [];

        $definitions = [];
        foreach (array_keys($institutionKinds) as $kind) {
            $definitions[$kind] = [
                'enabled' => true,
                'amount_source' => match ($kind) {
                    'paye' => 'transaction_column',
                    'psssf' => 'psssf_split',
                    default => 'deduction_items',
                },
                'debit_strategy' => 'auto',
                'transaction_column' => $kind === 'paye' ? 'paye' : '',
                'credit_account_key' => $kind,
            ];
        }

        if (! isset($definitions['paye'])) {
            $definitions['paye'] = [
                'enabled' => true,
                'aliases' => ['payee'],
                'amount_source' => 'transaction_column',
                'transaction_column' => 'paye',
                'debit_strategy' => 'auto',
                'credit_account_key' => 'paye',
            ];
        } else {
            $definitions['paye']['aliases'] = array_values(array_unique(array_merge(
                is_array($definitions['paye']['aliases'] ?? null) ? $definitions['paye']['aliases'] : [],
                ['payee']
            )));
        }

        return $definitions;
    }
}
