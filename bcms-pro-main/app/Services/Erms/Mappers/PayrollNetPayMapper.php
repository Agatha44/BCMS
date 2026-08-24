<?php

namespace App\Services\Erms\Mappers;

use App\Constants\EmployeeStatus;
use App\Models\Bms\Bank;
use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollTransaction;
use App\Services\Erms\Payroll\BankResolver;
use App\Services\Payroll\PayrollDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Net-pay ERMS payables grouped by salary bank bucket (CRDB, NBC, NMB, other_banks).
 *
 * One unsigned payable payload per bucket; misc accrual is handled separately.
 */
class PayrollNetPayMapper
{
    /** @var list<array{transaction_id:int|string, reason:string}> */
    protected array $skipped = [];

    public function __construct(
        private BankResolver $bankResolver,
        private PayrollTransactionPayeeMapper $payeeMapper,
    ) {}

    /** @return list<array{transaction_id:int|string, reason:string}> */
    public function skippedTransactions(): array
    {
        return array_map(static function ($row) {
            return [
                'transaction_id' => $row[0] ?? '',
                'reason' => (string) ($row[1] ?? ''),
            ];
        }, $this->skipped);
    }

    /**
     * @return list<string>
     */
    public function bucketKeys(): array
    {
        return $this->bankResolver->bucketKeys();
    }

    public function bankLabel(string $bucketKey): string
    {
        return $this->bankResolver->label($bucketKey);
    }

    /**
     * Summarize each bucket (amounts and payee counts) without building full payloads.
     *
     * @return list<array<string, mixed>>
     */
    public function summarizeBuckets(PayrollRun $run, array $context = []): array
    {
        $this->skipped = [];
        $this->ensureValidRun($run);

        $payrollNumber = $this->payrollNumber($run);
        $grouped = $this->groupTransactionsByBucket($run, $context);
        $summary = [];

        foreach ($this->bankResolver->bucketKeys() as $bucketKey) {
            /** @var Collection<int, PayrollTransaction> $transactions */
            $transactions = $grouped[$bucketKey] ?? collect();
            $netTotal = round((float) $transactions->sum(fn (PayrollTransaction $tx) => (float) $tx->net_pay), 2);

            $summary[] = [
                'bucket' => $bucketKey,
                'label' => $this->bankResolver->label($bucketKey),
                'source_ref' => $this->buildSourceRef($payrollNumber, $bucketKey),
                'payer_bank_account_number' => $this->bankResolver->payBankAccountNumber($bucketKey),
                'payee_count' => $transactions->count(),
                'net_pay_total' => $netTotal,
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, array<string, mixed>> keyed by bucket
     */
    public function mapAll(PayrollRun $run, array $context = []): array
    {
        $payloads = [];

        foreach ($this->bankResolver->bucketKeys() as $bucketKey) {
            try {
                $payloads[$bucketKey] = $this->mapBucket($run, $bucketKey, $context);
            } catch (InvalidArgumentException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'no valid')) {
                    throw $e;
                }
            }
        }

        return $payloads;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapBucket(PayrollRun $run, string $bucketKey, array $context = []): array
    {
        $this->skipped = [];
        $this->ensureValidRun($run);

        $bucketKey = $this->bankResolver->normalizeBucketKey($bucketKey);
        if (! $this->bankResolver->isKnownBucket($bucketKey)) {
            throw new InvalidArgumentException("Unknown net-pay bank bucket: {$bucketKey}.");
        }

        $cfg = $this->settings();
        $payrollNumber = $this->payrollNumber($run);
        $grouped = $this->groupTransactionsByBucket($run, $context);

        /** @var Collection<int, PayrollTransaction> $transactions */
        $transactions = $grouped[$bucketKey] ?? collect();

        $employees = $this->loadEmployees($transactions);
        $banks = $this->loadBanks($transactions, $employees);

        $payees = [];
        $netTotal = 0.0;

        foreach ($transactions as $tx) {
            $netPay = round((float) ($tx->net_pay), 2);
            if ($netPay <= 0) {
                $this->skip($tx, 'net_pay<=0');
                continue;
            }

            $emp = $employees[$tx->national_id] ?? null;
            if (! is_object($emp)) {
                $this->skip($tx, 'no_employee');
                continue;
            }

            $bank = $banks[$tx->bank_id] ?? null;
            if (! $bank instanceof Bank || trim((string) $tx->account_number) === '') {
                $this->skip($tx, 'no_bank');
                continue;
            }

            $netTotal += $netPay;
            $payees[] = $this->payeeMapper->map($tx, $emp, $bank, $cfg, $context);
        }

        $netTotal = round($netTotal, 2);
        if ($netTotal <= 0 || $payees === []) {
            throw new InvalidArgumentException(
                'No valid net-pay transactions for bucket '.$this->bankResolver->label($bucketKey).'.'
            );
        }

        $sourceRef = (string) ($context['source_ref'] ?? $this->buildSourceRef($payrollNumber, $bucketKey));
        $requestedDate = (string) ($context['requested_date'] ?? now()->format('Y-m-d'));
        $payerBankAccountNumber = $this->resolvePayerBankAccountNumber($bucketKey, $context);
        $entries = $this->entries($cfg, $netTotal);

        return [
            'currencyCode' => (string) $cfg['currency'],
            'exchangeRate' => 1,
            'departmentCode' => (string) $cfg['department_code'],
            'branchCode' => (string) ($context['branch_code'] ?? $cfg['branch_code']),
            'businessLineCode' => (string) (
                $context['business_line_code'] ?? $cfg['business_line_code']
            ),
            'payerBankAccountNumber' => $payerBankAccountNumber,
            'paymentProcessingMethod' => (string) $cfg['payment_processing_method'],
            'useBudget' => (bool) ($cfg['use_budget']),
            'hasBudgetReservation' => (bool) ($cfg['has_budget_reservation']),
            'budgetReservationRef' => $cfg['budget_reservation_ref'],
            'subActivityCode' => (string) $cfg['sub_activity_code'],
            'paymentType' => (string) $cfg['payment_type'],
            'requestedDate' => $requestedDate,
            'autoSettlement' => true,
            'sourceRef' => $sourceRef,
            'netPayAmount' => $netTotal,
            'amount' => $netTotal,
            'description' => $this->description($run, $bucketKey),
            'payee' => $cfg['payee'],
            'externalResources' => $this->externalResources($run, $bucketKey, $context),
            'requestItems' => $this->requestItemsFromDebitEntries($entries),
            'entries' => $entries,
            'payeeList' => $payees,
        ];
    }

    private function buildSourceRef(string $payrollNumber, string $bucketKey): string
    {
        return $payrollNumber.'-'.$this->bankResolver->sourceRefSuffix($bucketKey);
    }

    /**
     * ERMS payable API requires payerBankAccountNumber (not payBankAccountNumber).
     */
    private function resolvePayerBankAccountNumber(string $bucketKey, array $context): string
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

        $account = $this->bankResolver->payBankAccountNumber($bucketKey);
        if ($account === '') {
            throw new InvalidArgumentException(
                'Missing payer bank account for net-pay bucket '.$this->bankResolver->label($bucketKey).'.'
            );
        }

        return $account;
    }

    private function payrollNumber(PayrollRun $run): string
    {
        $payrollNumber = trim((string) ($run->payroll_number ?? ''));
        if ($payrollNumber === '') {
            throw new InvalidArgumentException('Payroll run is missing payroll_number.');
        }

        return $payrollNumber;
    }

    /**
     * @return array<string, Collection<int, PayrollTransaction>>
     */
    private function groupTransactionsByBucket(PayrollRun $run, array $context): array
    {
        $transactions = $this->getTransactions($run, $context);
        $banks = $this->loadBanks($transactions, []);

        $grouped = [];
        foreach ($this->bankResolver->bucketKeys() as $key) {
            $grouped[$key] = collect();
        }

        foreach ($transactions as $tx) {
            $bank = $banks[$tx->bank_id] ?? null;
            if (! $bank instanceof Bank) {
                $this->skip($tx, 'no_bank_for_bucket');
                continue;
            }

            $bucket = $this->bankResolver->resolve($bank);
            $grouped[$bucket]->push($tx);
        }

        return $grouped;
    }

    private function ensureValidRun(PayrollRun $run): void
    {
        if (! in_array(strtolower((string) ($run->status ?? '')), ['posted'], true)) {
            throw new InvalidArgumentException('Payroll run must be posted before submitting net-pay payables.');
        }
    }

    /**
     * @return Collection<int, PayrollTransaction>
     */
    private function getTransactions(PayrollRun $run, array $context): Collection
    {
        return PayrollTransaction::query()
            ->where('payroll_run_id', $run->id)
            ->when(
                ! ($context['include_posted'] ?? false),
                fn ($q) => $q->where(function ($query) {
                    $query->whereNull('erms_status')
                        ->orWhere('erms_status', '!=', 1);
                })
            )
            ->get();
    }

    /**
     * @param  Collection<int, PayrollTransaction>  $transactions
     * @return array<string, object>
     */
    private function loadEmployees(Collection $transactions): array
    {
        $ids = $transactions->pluck('national_id')->filter()->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        return DB::connection('bcmis2')
            ->table('bridge_employee')
            ->whereIn('national_id', $ids)
            ->whereIn('employee_status', EmployeeStatus::activeValues())
            ->get()
            ->keyBy('national_id')
            ->all();
    }

    /**
     * @param  Collection<int, PayrollTransaction>|iterable<PayrollTransaction>  $transactions
     * @param  array<string, object>  $employees
     * @return array<int, Bank>
     */
    private function loadBanks($transactions, array $employees): array
    {
        $ids = [];

        foreach ($transactions as $t) {
            if ($t->bank_id) {
                $ids[] = (int) $t->bank_id;
            }
        }

        foreach ($employees as $e) {
            if (! empty($e->bank_id)) {
                $ids[] = (int) $e->bank_id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        return Bank::query()
            ->whereIn('bank_id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('bank_id')
            ->all();
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
     * @return list<array<string, mixed>>
     */
    private function entries(array $cfg, float $netTotal): array
    {
        $netTotal = round($netTotal, 2);

        return [
            [
                'accountCode' => (string) $cfg['debit'],
                'amount' => $netTotal,
                'gfsCode' => (string) $cfg['debit_gfs_code'],
                'bookSide' => 'DEBIT',
                'serviceEntry' => true,
                'taxEntry' => false,
            ],
            [
                'accountCode' => (string) $cfg['credit'],
                'amount' => $netTotal,
                'gfsCode' => (string) $cfg['credit_gfs_code'],
                'bookSide' => 'CREDIT',
                'serviceEntry' => true,
                'taxEntry' => false,
            ],
        ];
    }

    private function description(PayrollRun $run, string $bucketKey): string
    {
        $month = Carbon::create((int) $run->payroll_year, (int) $run->payroll_month, 1)->format('M');
        $label = $this->bankResolver->label($bucketKey);

        return "Nyerere Bridge net salary ({$label}) - {$month}-{$run->payroll_year}";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function externalResources(PayrollRun $run, string $bucketKey, array $context = []): array
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

        $docs = app(PayrollDocumentService::class)->externalResourcesForNetPayBucket($run, $bucketKey);
        $minutes = app(PayrollDocumentService::class)->externalResourcesForKind($run, 'minutes');

        return array_values(array_filter(array_merge($docs, $minutes)));
    }

    private function skip(PayrollTransaction $tx, string $reason): void
    {
        $this->skipped[] = [$tx->id, $reason];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $c = config('erms.payable_settings', []);
        $c = is_array($c) ? $c : [];

        return [
            'currency' => (string) config('erms.default_config.currency_code', 'TZS'),
            'payment_processing_method' => (string) config('erms.default_config.payment_processing_method'),
            'use_budget' => config('erms.default_config.use_budget'),
            'has_budget_reservation' => config('erms.default_config.has_budget_reservation'),
            'budget_reservation_ref' => config('erms.default_config.budget_reservation_ref'),
            'payee' => config('erms.default_config.payee'),
            'department_code' => (string) ($c['department_code']),
            'branch_code' => (string) (config('erms.default_config.branch_code')
            ),
            'business_line_code' => (string) (
                $c['business_line_code']
                ?? config('erms.miscellaneous_entries.payroll_business_line_code', '100')
            ),
            'payment_type' => (string) ($c['payment_type']),
            'client_type' => (string) ($c['client_type']),
            'client_category' => (string) ($c['client_category']),
            'loyalty_type' => (string) ($c['loyalty_type']),
            'group_code' => (string) ($c['group_code']),
            'sub_activity_code' => (string) ($c['sub_activity_salary_code']),
            'distribution_gfs_code' => (string) ($c['distribution_gfs_code']),
            'phone_country_prefix' => (string) ($c['phone_country_prefix'] ?? config('erms.default_config.phone_country_prefix', '255')),
            'debit' => (string) ($c['net_pay_account_code']),
            'credit' => (string) ($c['payable_supplier_account_code']),
            'debit_gfs_code' => (string) ($c['net_pay_gfs_code']),
            'credit_gfs_code' => (string) ($c['payable_supplier_gfs_code']),
        ];
    }
}
