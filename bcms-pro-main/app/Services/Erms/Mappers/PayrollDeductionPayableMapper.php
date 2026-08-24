<?php

namespace App\Services\Erms\Mappers;

use App\Models\Bms\Payroll\PayrollErmsExecution;
use App\Models\Bms\Payroll\PayrollRun;
use App\Services\Erms\Payroll\DeductionAmountResolverService;
use App\Services\Erms\Payroll\PayrollDeductionPayableConfig;
use App\Services\Payroll\PayrollDocumentService;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Config-driven ERMS payables for payroll deductions and statutory remittances
 * (PAYE/payee, PSSSF, HESLB, and future kinds in erms.payroll_deduction_payables).
 */
class PayrollDeductionPayableMapper
{
    public function __construct(
        private PayrollDeductionPayableConfig $payableConfig,
        private DeductionAmountResolverService $amountResolver,
        private PayrollInstitutionPayeeMapper $payeeMapper,
    ) {}

    /**
     * @return list<string>
     */
    public function kinds(): array
    {
        return $this->payableConfig->kinds();
    }

    /**
     * @return list<string>
     */
    public function kindsWithAmounts(PayrollRun $run): array
    {
        $this->ensureValidRun($run);

        return $this->amountResolver->kindsWithAmounts($run);
    }

    public static function executionType(string $kind): string
    {
        return PayrollErmsExecution::TYPE_DEDUCTION_PAYABLE.':'.strtolower(trim($kind));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function summarizeAll(PayrollRun $run, array $context = []): array
    {
        $this->ensureValidRun($run);
        $payrollNumber = $this->payrollNumber($run);
        $summary = [];

        foreach ($this->amountResolver->kindsWithAmounts($run) as $kind) {
            $amounts = $this->amountResolver->resolve($run, $kind);
            $definition = $this->payableConfig->definition($kind);

            $row = [
                'kind' => $kind,
                'label' => (string) ($definition['label'] ?? strtoupper($kind)),
                'source_ref' => $this->buildSourceRef($payrollNumber, $kind, $context),
                'amount' => $amounts['total'],
                'amount_source' => (string) ($definition['amount_source'] ?? ''),
            ];

            if (isset($amounts['employee'], $amounts['employer'])) {
                $row['employee_contribution'] = $amounts['employee'];
                $row['employer_contribution'] = $amounts['employer'];
            }

            $row['debit_strategy'] = $this->payableConfig->resolveDebitStrategy($run, $kind);
            $row['has_employer_on_deduction_type'] = $this->payableConfig->kindHasEmployerContribution($kind);

            $summary[] = $row;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(PayrollRun $run, string $kind, array $context = []): array
    {
        $this->ensureValidRun($run);
        $kind = $this->payableConfig->normalizeKind($kind);
        if (! $this->payableConfig->isKnownKind($kind)) {
            throw new InvalidArgumentException("Unknown deduction payable kind: {$kind}.");
        }

        $amounts = $this->amountResolver->resolve($run, $kind);
        $definition = $this->payableConfig->definition($kind);

        $row = [
            'kind' => $kind,
            'label' => (string) ($definition['label'] ?? strtoupper($kind)),
            'source_ref' => $this->buildSourceRef($this->payrollNumber($run), $kind, $context),
            'amount' => $amounts['total'],
            'amount_source' => (string) ($definition['amount_source'] ?? ''),
            'payer_bank_account_number' => $this->resolvePayerBankAccountNumber($context),
        ];

        if (isset($amounts['employee'], $amounts['employer'])) {
            $row['employee_contribution'] = $amounts['employee'];
            $row['employer_contribution'] = $amounts['employer'];
        }

        $row['debit_strategy'] = $this->payableConfig->resolveDebitStrategy($run, $kind);
        $row['has_employer_on_deduction_type'] = $this->payableConfig->kindHasEmployerContribution($kind);

        return $row;
    }

    /**
     * @return array<string, array<string, mixed>> keyed by kind
     */
    public function mapAll(PayrollRun $run, array $context = []): array
    {
        $payloads = [];

        foreach ($this->amountResolver->kindsWithAmounts($run) as $kind) {
            try {
                $payloads[$kind] = $this->map($run, $kind, $context);
            } catch (InvalidArgumentException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'no ')) {
                    throw $e;
                }
            }
        }

        return $payloads;
    }

    /**
     * @return array<string, mixed>
     */
    public function map(PayrollRun $run, string $kind, array $context = []): array
    {
        $this->ensureValidRun($run);
        $kind = $this->payableConfig->normalizeKind($kind);

        if (! $this->payableConfig->isKnownKind($kind)) {
            throw new InvalidArgumentException("Unknown deduction payable kind: {$kind}.");
        }

        $definition = $this->payableConfig->definition($kind);
        $amounts = $this->amountResolver->resolve($run, $kind);
        $total = round((float) ($amounts['total'] ?? 0), 2);

        if ($total <= 0) {
            throw new InvalidArgumentException(
                'No '.((string) ($definition['label'] ?? $kind)).' amount to submit for this payroll run.'
            );
        }

        $cfg = $this->settings();
        $institution = $this->payableConfig->institution($kind);
        $payrollNumber = $this->payrollNumber($run);
        $credit = $this->payableConfig->creditAccount($kind);

        $sourceRef = (string) ($context['source_ref'] ?? $this->buildSourceRef($payrollNumber, $kind, $context));
        $requestedDate = (string) ($context['requested_date'] ?? now()->format('Y-m-d'));
        $referenceNumber = (string) ($context['reference_number'] ?? $sourceRef);
        $entries = $this->buildEntries($run, $cfg, $credit, $kind, $amounts);

        return [
            'currencyCode' => (string) $cfg['currency'],
            'exchangeRate' => 1,
            'departmentCode' => (string) $cfg['department_code'],
            'branchCode' => (string) ($context['branch_code'] ?? $cfg['branch_code']),
            'businessLineCode' => (string) ($context['business_line_code'] ?? $cfg['business_line_code']),
            'payerBankAccountNumber' => $this->resolvePayerBankAccountNumber($context),
            'paymentProcessingMethod' => (string) $cfg['payment_processing_method'],
            'useBudget' => (bool) ($cfg['use_budget']),
            'hasBudgetReservation' => (bool) ($cfg['has_budget_reservation']),
            'budgetReservationRef' => $cfg['budget_reservation_ref'],
            'subActivityCode' => (string) $cfg['sub_activity_code'],
            'paymentType' => (string) $cfg['payment_type'],
            'requestedDate' => $requestedDate,
            'sourceRef' => $sourceRef,
            'netPayAmount' => $total,
            'amount' => $total,
            'description' => $this->description($run, $definition),
            'payee' => $cfg['payee'],
            'externalResources' => $this->externalResources($run, $kind, $context),
            'requestItems' => $this->requestItemsFromDebitEntries($entries),
            'entries' => $entries,
            'payeeList' => [
                $this->payeeMapper->map($total, $institution, $cfg, $referenceNumber),
            ],
            'deduction_kind' => $kind,
            'deduction_label' => (string) ($definition['label'] ?? strtoupper($kind)),
            'deduction_amounts' => $amounts,
            'debit_strategy' => $this->payableConfig->resolveDebitStrategy($run, $kind),
            'has_employer_on_deduction_type' => $this->payableConfig->kindHasEmployerContribution($kind),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array{amount: float, gfsCode: string}>
     */
    private function requestItemsFromDebitEntries(array $entries): array
    {
        $items = [];

        foreach ($entries as $entry) {
            if (strtoupper((string) ($entry['bookSide'] ?? '')) !== 'DEBIT') {
                continue;
            }

            $amount = round((float) ($entry['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $items[] = [
                'amount' => $amount,
                'gfsCode' => (string) ($entry['gfsCode'] ?? ''),
            ];
        }

        if ($items === []) {
            throw new InvalidArgumentException('No request items could be built from debit entries.');
        }

        return $items;
    }

    /**
     * @param  array{account_code: string, gfs_code: string}  $credit
     * @param  array<string, mixed>  $amounts
     * @return list<array<string, mixed>>
     */
    private function buildEntries(PayrollRun $run, array $cfg, array $credit, string $kind, array $amounts): array
    {
        $strategy = $this->payableConfig->resolveDebitStrategy($run, $kind);
        $total = round((float) ($amounts['total'] ?? 0), 2);

        if ($strategy === 'employee_employer' && ! array_key_exists('employee', $amounts)) {
            $amounts['employee'] = $total;
            $amounts['employer'] = 0.0;
        }

        $debits = match ($strategy) {
            'employee_employer' => $this->employeeEmployerDebits($cfg, $kind, $amounts),
            default => [$this->debitLine(
                $this->singleDebitAccount($cfg, $kind),
                $this->singleDebitGfs($cfg, $kind),
                $total,
                $kind,
            )],
        };

        $debits = array_values(array_filter($debits, static fn (array $line) => (float) ($line['amount'] ?? 0) > 0));

        if ($debits === [] && $total > 0) {
            $debits = [$this->debitLine(
                $this->singleDebitAccount($cfg, $kind),
                $this->singleDebitGfs($cfg, $kind),
                $total,
                $kind,
            )];
        }

        if ($debits === []) {
            throw new InvalidArgumentException("No debit lines for deduction payable \"{$kind}\".");
        }

        $debitSum = round(array_sum(array_map(
            static fn (array $line) => (float) ($line['amount'] ?? 0),
            $debits
        )), 2);

        if (abs($debitSum - $total) > 0.01) {
            throw new InvalidArgumentException(sprintf(
                'Deduction payable "%s" entries are unbalanced (debits=%.2f, credit=%.2f).',
                $kind,
                $debitSum,
                $total
            ));
        }

        $lines = $debits;
        $lines[] = [
            'accountCode' => $credit['account_code'],
            'amount' => $total,
            'gfsCode' => $credit['gfs_code'],
            'bookSide' => 'CREDIT',
            'serviceEntry' => true,
            'taxEntry' => false,
        ];

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $amounts
     * @return list<array<string, mixed>>
     */
    private function employeeEmployerDebits(array $cfg, string $kind, array $amounts): array
    {
        $lines = [];
        $employee = round((float) ($amounts['employee'] ?? 0), 2);
        $employer = round((float) ($amounts['employer'] ?? 0), 2);

        if ($employee > 0) {
            $lines[] = $this->debitLine(
                $this->kindDebitAccount($cfg, $kind),
                $this->kindDebitGfs($cfg, $kind),
                $employee,
                $kind,
            );
        }

        if ($employer > 0) {
            $employerAccount = trim((string) ($cfg["debit_{$kind}_employer_account_code"] ?? ''));
            $employerGfs = trim((string) ($cfg["{$kind}_gfs_code"] ?? ''));

            $lines[] = $this->debitLine(
                $employerAccount = $employerAccount,
                $employerGfs = $employerGfs,
                $employer,
                $kind,
            );
        }

        return $lines;
    }

    private function singleDebitAccount(array $cfg, string $kind): string
    {
        return $this->kindDebitAccount($cfg, $kind);
    }

    private function singleDebitGfs(array $cfg, string $kind): string
    {
        return $this->kindDebitGfs($cfg, $kind);
    }

    private function kindDebitAccount(array $cfg, string $kind): string
    {
        return trim((string) ($cfg["{$kind}_account_code"]));
    }

    private function kindDebitGfs(array $cfg, string $kind): string
    {
        $gfs = trim((string) ($cfg["{$kind}_gfs_code"]));

        return $gfs;
    }

    /**
     * @return array<string, mixed>
     */
    private function debitLine(string $accountCode, string $gfsCode, float $amount, ?string $kind = null): array
    {
        if (trim($accountCode) === '') {
            $forKind = ($kind !== null && $kind !== '') ? " for \"{$kind}\"" : '';
            $hint = ($kind !== null && $kind !== '')
                ? "Set erms.payable_settings.{$kind}_account_code."
                : 'Set erms.payable_settings.{kind}_account_code for each deduction payable kind.';

            throw new InvalidArgumentException(
                "Missing deduction payable debit account{$forKind} in erms.payable_settings. {$hint}"
            );
        }

        return [
            'accountCode' => $accountCode,
            'amount' => round($amount, 2),
            'gfsCode' => $gfsCode,
            'bookSide' => 'DEBIT',
            'serviceEntry' => true,
            'taxEntry' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function description(PayrollRun $run, array $definition): string
    {
        $month = Carbon::create((int) $run->payroll_year, (int) $run->payroll_month, 1)->format('M');
        $label = (string) ($definition['label'] ?? 'Deduction');

        return "Nyerere Bridge {$label} - {$month}-{$run->payroll_year}";
    }

    private function buildSourceRef(string $payrollNumber, string $kind, array $context): string
    {
        if (! empty($context['source_ref'])) {
            return (string) $context['source_ref'];
        }

        $suffix = (string) ($this->payableConfig->definition($kind)['source_ref_suffix'] ?? strtoupper($kind));

        return $payrollNumber.'-'.$suffix;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function externalResources(PayrollRun $run, string $kind, array $context = []): array
    {
        $url = trim((string) ($context['external_resource_url'] ?? ''));
        if ($url !== '') {
            return [
                [
                    'fullUrl' => $url,
                    'resourceType' => 'RESOURCE_REFERENCE',
                    'requiresAuthentication' => (bool) ($context['external_resource_requires_auth'] ?? true),
                    'title' => (string) ($context['external_resource_title'] ?? 'Payroll supporting document'),
                ],
            ];
        }

        $docs = app(PayrollDocumentService::class)->externalResourcesForKind($run, $kind);
        $minutes = app(PayrollDocumentService::class)->externalResourcesForKind($run, 'minutes');

        return array_values(array_filter(array_merge($docs, $minutes)));
    }

    private function ensureValidRun(PayrollRun $run): void
    {
        if (! in_array(strtolower((string) ($run->status ?? '')), ['posted'], true)) {
            throw new InvalidArgumentException('Payroll run must be posted before submitting deduction payables.');
        }
    }

    private function payrollNumber(PayrollRun $run): string
    {
        $payrollNumber = trim((string) ($run->payroll_number ?? ''));
        if ($payrollNumber === '') {
            throw new InvalidArgumentException('Payroll run is missing payroll_number.');
        }

        return $payrollNumber;
    }

    private function resolvePayerBankAccountNumber(array $context): string
    {
        foreach ([
            $context['payer_bank_account_number'] ?? null,
            $context['pay_bank_account_number'] ?? null,
        ] as $candidate) {
            $account = trim((string) $candidate);
            if ($account !== '') {
                return $account;
            }
        }

        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];

        $account = trim((string) (
            $payable['default_payer_bank_account_number']
            ?? $payable['payroll_net_pay_default_pay_bank_account_number']
            ?? $payable['default_pay_bank_account_number']
            ?? ''
        ));

        if ($account === '') {
            throw new InvalidArgumentException(
                'Missing payer bank account. Set ERMS_PAYABLE_DEFAULT_PAYER_BANK_ACCOUNT.'
            );
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $c = config('erms.payable_settings', []);
        $c = is_array($c) ? $c : [];

        $settings = [
            'currency' => (string) config('erms.default_config.currency_code'),
            'payee' => config('erms.default_config.payee'),
            'payment_processing_method' => (string) config('erms.default_config.payment_processing_method'),
            'use_budget' => config('erms.default_config.use_budget'),
            'has_budget_reservation' => config('erms.default_config.has_budget_reservation'),
            'budget_reservation_ref' => config('erms.default_config.budget_reservation_ref'),
            'department_code' => (string) ($c['department_code']),
            'branch_code' => (string) ($c['branch_code']),
            'business_line_code' => (string) ($c['business_line_code']),
            'payment_type' => (string) ($c['payment_type']),
            'client_category' => (string) ($c['client_category']),
            'loyalty_type' => (string) ($c['loyalty_type']),
            'group_code' => (string) ($c['group_code']),
            'sub_activity_code' => (string) ($c['sub_activity_salary_code']),
            'distribution_gfs_code' => (string) ($c['distribution_gfs_code']),
            'default_bank_code' => (string) ($c['default_bank_code']),
            'default_branch_code' => (string) ($c['default_branch_code']),
            'default_bank_name' => (string) ($c['default_bank_name']),
            'debit_psssf_employer_account_code' => (string) ($c['debit_psssf_employer_account_code']),
            'debit_psssf_employer_gfs_code' => (string) ($c['psssf_gfs_code']),
        ];

        foreach ($this->payableConfig->kinds() as $kind) {
            $settings["{$kind}_account_code"] = (string) ($c["{$kind}_account_code"]);
            $settings["{$kind}_gfs_code"] = (string) ($c["{$kind}_gfs_code"]);
        }

        return $settings;
    }
}
