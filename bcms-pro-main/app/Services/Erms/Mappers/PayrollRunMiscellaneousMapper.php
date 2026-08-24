<?php

namespace App\Services\Erms\Mappers;

use App\Models\Bms\Payroll\PayrollRun;
use App\Services\Erms\Payroll\DeductionAccountResolver;
use App\Services\Payroll\PayrollDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollRunMiscellaneousMapper
{
    public function __construct(
        private ?DeductionAccountResolver $deductionAccountResolver = null,
    ) {}

    /**
     * Canonical mapper output consumed by ErmsMiscellaneousPayloadTrait.
     *
     * Journal-aligned miscellaneous accrual for a posted payroll run (debits: basic, benefits,
     * arrears; credits: PAYE, deductions, loans, net pay).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function map(PayrollRun $run, array $context = []): array
    {
        $this->ensureValidRun($run);

        $cfg = $this->settings($run);
        $journal = $this->loadJournalAggregates((int) $run->id);
        $includeEntryClient = (bool) (false);
        $entryClient = $includeEntryClient ? $this->buildEntryClientDetails($cfg) : null;
        $entries = $this->buildEntries($journal, $cfg, $entryClient);

        if ($entries === []) {
            throw new InvalidArgumentException('Payroll miscellaneous payload has no journal lines.');
        }

        $totalDebit = round(array_sum(array_map(
            static fn (array $row) => strtoupper((string) ($row['book_side'] ?? '')) === 'DEBIT' ? (float) ($row['amount'] ?? 0) : 0.0,
            $entries
        )), 2);

        $totalCredit = round(array_sum(array_map(
            static fn (array $row) => strtoupper((string) ($row['book_side'] ?? '')) === 'CREDIT' ? (float) ($row['amount'] ?? 0) : 0.0,
            $entries
        )), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(sprintf(
                'Payroll miscellaneous journal is unbalanced (debit=%.2f, credit=%.2f).',
                $totalDebit,
                $totalCredit
            ));
        }

        $payrollNumber = trim((string) ($run->payroll_number ?? ''));
        if ($payrollNumber === '') {
            throw new InvalidArgumentException('Payroll run is missing payroll_number.');
        }

        $sourceRef = (string) ($context['source_ref'] ?? $payrollNumber.'-MISC');
        $trackingReference = (string) ($context['tracking_reference'] ?? $sourceRef);

        $recordDate = $context['record_date'] ?? $run->approved_at ?? now();

        return [
            'description' => $this->buildDescription($run),
            'record_date' => $recordDate,
            'branch_code' => (string) ($context['branch_code'] ?? $cfg['branch_code']),
            'department_code' => (string) ($context['department_code'] ?? $cfg['department_code']),
            'amount' => $totalDebit,
            'entry_purpose' => $cfg['entry_purpose'],
            'business_line_code' => $cfg['business_line_code'],
            'sub_activity_code' => $cfg['sub_activity_code'],
            'source_ref' => $sourceRef,
            'tracking_reference' => $trackingReference,
            'currency_code' => $cfg['currency_code'],
            'exchange_rate' => (float) $cfg['exchange_rate'],
            'phone_country_prefix' => $cfg['phone_country_prefix'],
            'include_entry_client_details' => $includeEntryClient,
            'client_details' => $this->buildClientDetails($cfg),
            'entries' => $entries,
            'external_resources' => $this->externalResources($run, $context),
            'source_type' => 'payroll_run',
            'journal_totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
            ],
        ];
    }

    private function ensureValidRun(PayrollRun $run): void
    {
        if (! in_array(strtolower((string) ($run->status ?? '')), ['posted', 'approved'], true)) {
            throw new InvalidArgumentException('Payroll run must be approved or posted before miscellaneous submission.');
        }
    }

    /**
     * Align with payroll journal API: only items for employees in payroll_transaction.
     * Net pay credit is derived so debits (gross) equal credits (paye + deductions + loans + net).
     *
     * @return array{
     *   basic: float,
     *   paye: float,
     *   net_pay: float,
     *   benefits: Collection,
     *   arrears: Collection,
     *   deductions: Collection,
     *   loans: Collection
     * }
     */
    private function loadJournalAggregates(int $payrollRunId): array
    {
        $conn = DB::connection('bcmis2');

        $txTotals = $conn->table('payroll_transaction as t')
            ->where('t.payroll_run_id', $payrollRunId)
            ->selectRaw('
                COALESCE(SUM(t.basic_salary), 0) as basic_salary,
                COALESCE(SUM(t.gross_pay), 0) as gross_pay,
                COALESCE(SUM(t.paye), 0) as paye
            ')
            ->first();

        $itemJoin = static function ($query) use ($payrollRunId) {
            $query->join('payroll_transaction as t', function ($join) {
                $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                    ->on('t.national_id', '=', 'i.national_id');
            })
                ->where('i.payroll_run_id', $payrollRunId)
                ->where('i.is_void', false);
        };

        $benefits = $conn->table('employee_payroll_items as i')
            ->tap($itemJoin)
            ->leftJoin('benefit_type as bt', function ($join) {
                $join->on('bt.benefit_type_id', '=', 'i.type_id')
                    ->where('i.type_table', '=', 'benefit_type');
            })
            ->where('i.item_type', 'benefit')
            ->groupBy('i.type_id', 'display_name')
            ->orderBy('display_name')
            ->select([
                'i.type_id',
                DB::raw("COALESCE(NULLIF(i.name, ''), bt.benefit_name, 'Benefit') as display_name"),
                DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
            ])
            ->get();

        $arrears = $conn->table('employee_payroll_items as i')
            ->tap($itemJoin)
            ->leftJoin('arrears_reasons as ar', function ($join) {
                $join->on('ar.arrears_reason_id', '=', 'i.type_id')
                    ->where('i.type_table', '=', 'arrears_reason');
            })
            ->where('i.item_type', 'arrears')
            ->groupBy('i.type_id', 'display_name')
            ->orderBy('display_name')
            ->select([
                'i.type_id',
                DB::raw("COALESCE(NULLIF(i.name, ''), ar.reason_name, 'Arrears') as display_name"),
                DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
            ])
            ->get();

        $deductions = $conn->table('employee_payroll_items as i')
            ->tap($itemJoin)
            ->leftJoin('deduction_type as dt', function ($join) {
                $join->on('dt.deduction_type_id', '=', 'i.type_id')
                    ->where('i.type_table', '=', 'deduction_type');
            })
            ->where('i.item_type', 'deduction')
            ->groupBy('i.type_id', 'dt.deduction_code', 'display_name')
            ->orderBy('display_name')
            ->select([
                'i.type_id',
                DB::raw("COALESCE(NULLIF(dt.deduction_code, ''), '') as deduction_code"),
                DB::raw("COALESCE(NULLIF(i.name, ''), dt.deduction_name, 'Deduction') as display_name"),
                DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
            ])
            ->get();

        $loans = $conn->table('employee_payroll_items as i')
            ->tap($itemJoin)
            ->leftJoin('loan_type as lt', function ($join) {
                $join->on('lt.loan_type_id', '=', 'i.type_id')
                    ->where('i.type_table', '=', 'loan_type');
            })
            ->where('i.item_type', 'loan')
            ->groupBy('i.type_id', 'display_name')
            ->orderBy('display_name')
            ->select([
                'i.type_id',
                DB::raw("COALESCE(NULLIF(i.name, ''), lt.loan_name, 'Loan') as display_name"),
                DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
            ])
            ->get();

        $gross = round((float) ($txTotals->gross_pay ?? 0), 2);
        $basic = round((float) ($txTotals->basic_salary ?? 0), 2);
        $paye = round((float) ($txTotals->paye ?? 0), 2);

        $benefitsSum = round((float) $benefits->sum('amount'), 2);
        $arrearsSum = round((float) $arrears->sum('amount'), 2);
        $deductionsSum = round((float) $deductions->sum('amount'), 2);
        $loansSum = round((float) $loans->sum('amount'), 2);

        $netPay = round($gross - $paye - $deductionsSum - $loansSum, 2);

        return [
            'basic' => $basic,
            'paye' => $paye,
            'net_pay' => $netPay,
            'benefits' => $benefits,
            'arrears' => $arrears,
            'deductions' => $deductions,
            'loans' => $loans,
        ];
    }

    /**
     * @param  array<string, mixed>  $journal
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>|null  $entryClient
     * @return list<array<string, mixed>>
     */
    private function buildEntries(array $journal, array $cfg, ?array $entryClient): array
    {
        $entries = [];
        $periodLabel = (string) ($cfg['period_label'] ?? '');

        if ((float) $journal['basic'] !== 0.0) {
            $entries[] = $this->line(
                $cfg['debit_account_code'],
                $cfg['debit_gfs_code'],
                'DEBIT',
                'Basic salary - '.$periodLabel,
                (float) $journal['basic'],
                $entryClient
            );
        }

        foreach ($journal['benefits'] as $row) {
            $amt = round((float) ($row->amount ?? 0), 2);
            if ($amt === 0.0) {
                continue;
            }
            $entries[] = $this->line(
                $cfg['debit_account_code'],
                $cfg['debit_gfs_code'],
                'DEBIT',
                (string) ($row->display_name) . ' - ' . $periodLabel,
                $amt,
                $entryClient
            );
        }

        foreach ($journal['arrears'] as $row) {
            $amt = round((float) ($row->amount ?? 0), 2);
            if ($amt === 0.0) {
                continue;
            }
            $entries[] = $this->line(
                $cfg['debit_account_code'],
                $cfg['debit_gfs_code'],
                'DEBIT',
                (string) ($row->display_name) . ' - ' . $periodLabel,
                $amt,
                $entryClient
            );
        }

        if ((float) $journal['paye'] !== 0.0) {
            $entries[] = $this->line(
                $cfg['paye_account_code'],
                $cfg['paye_gfs_code'],
                'CREDIT',
                'PAYE - ' . $periodLabel,
                (float) $journal['paye'],
                $entryClient
            );
        }

        foreach ($journal['deductions'] as $row) {
            $amt = round((float) ($row->amount ?? 0), 2);
            if ($amt === 0.0) {
                continue;
            }
            $deductionAccounts = $this->deductionAccountResolver()->resolve($row);
            $entries[] = $this->line(
                $deductionAccounts['account_code'],
                $deductionAccounts['gfs_code'],
                'CREDIT',
                (string) ($row->display_name).' - '.$periodLabel,
                $amt,
                $entryClient
            );
        }

        foreach ($journal['loans'] as $row) {
            $amt = round((float) ($row->amount ?? 0), 2);
            if ($amt === 0.0) {
                continue;
            }
            $entries[] = $this->line(
                $cfg['loans_account_code'],
                $cfg['loans_gfs_code'],
                'CREDIT',
                (string) ($row->display_name ?? 'Loan').$periodLabel,
                $amt,
                $entryClient
            );
        }

        if ((float) $journal['net_pay'] !== 0.0) {
            $entries[] = $this->line(
                $cfg['salary_account_code'],
                $cfg['salary_gfs_code'],
                'CREDIT',
                'Net pay - ' . $periodLabel,
                (float) $journal['net_pay'],
                $entryClient
            );
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>|null  $entryClient
     * @return array<string, mixed>
     */
    private function line(
        string $accountCode,
        string $gfsCode,
        string $bookSide,
        string $description,
        float $amount,
        ?array $entryClient
    ): array {
        $row = [
            'account_code' => $accountCode,
            'gfs_code' => $gfsCode,
            'book_side' => strtoupper($bookSide),
            'description' => $description,
            'amount' => round($amount, 2),
        ];

        if ($entryClient !== null && $entryClient !== []) {
            $row['entry_client_details'] = $entryClient;
        }

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function externalResources(PayrollRun $run, array $context = []): array
    {
        $url = trim((string) ($context['external_resource_url'] ?? ''));
        if ($url !== '') {
            return [
                [
                    'fullUrl' => $url,
                    'resourceType' => 'RESOURCE_REFERENCE',
                    'requiresAuthentication' => (bool) ($context['external_resource_requires_auth'] ?? false),
                    'title' => (string) (PayrollDocumentService::documentTitleForKind('jv')),
                ],
            ];
        }

        return app(PayrollDocumentService::class)->externalResourcesForKind($run, 'jv');
    }

    private function buildDescription(PayrollRun $run): string
    {
        $month = Carbon::create((int) $run->payroll_year, (int) $run->payroll_month, 1)->format('M');
        $number = trim((string) ($run->payroll_number ?? ''));

        return trim("Nyerere Bridge Payroll - {$month}-{$run->payroll_year}");
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function buildClientDetails(array $cfg): array
    {
        return [
            'client_type' => (string) ($cfg['client_type'] ?? ''),
            'loyalty_type' => (string) ($cfg['loyalty_type'] ?? ''),
            'client_category' => (string) ($cfg['client_category'] ?? ''),
            'name' => (string) ($cfg['client_name'] ?? ''),
            'code' => (string) ($cfg['client_code'] ?? ''),
            'phone' => (string) ($cfg['client_phone'] ?? ''),
            'email' => (string) ($cfg['client_email'] ?? ''),
            'address' => (string) ($cfg['client_address'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function buildEntryClientDetails(array $cfg): array
    {
        return [
            'client_type' => (string) ($cfg['client_type'] ?? ''),
            'name' => (string) ($cfg['client_name'] ?? ''),
            'code' => (string) ($cfg['client_code'] ?? ''),
            'address' => (string) ($cfg['client_address'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(PayrollRun $run): array
    {
        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];

        $month = Carbon::create((int) $run->payroll_year, (int) $run->payroll_month, 1)->format('M');

        return [
            'branch_code' => (string) $this->defaultConfig('branch_code'),
            'department_code' => (string) $this->miscellaneousEntriesConfig(
                'payroll_department_code',
                $this->defaultConfig('department_code')
            ),
            'entry_purpose' => (string) $this->miscellaneousEntriesConfig('payroll_entry_purpose'),
            'business_line_code' => (string) $this->miscellaneousEntriesConfig('payroll_business_line_code'),
            'sub_activity_code' => (string) $this->miscellaneousEntriesConfig(
                'payroll_sub_activity_code'
            ),
            'currency_code' => (string) $this->defaultConfig('currency_code'),
            'exchange_rate' => (float) $this->defaultConfig('exchange_rate'),
            'phone_country_prefix' => (string) $this->defaultConfig('phone_country_prefix'),
            'loyalty_type' => (string) $this->defaultConfig('default_loyalty_type'),
            'client_address' => (string) $this->defaultConfig('client_address'),
            'client_type' => (string) $this->miscellaneousEntriesConfig('payroll_client_type'),
            'client_category' => (string) $this->miscellaneousEntriesConfig('payroll_client_category'),
            'client_name' => (string) $this->miscellaneousEntriesConfig('payroll_client_name'),
            'client_code' => (string) $this->miscellaneousEntriesConfig('payroll_client_code'),
            'client_phone' => (string) $this->miscellaneousEntriesConfig('payroll_client_phone'),
            'client_email' => (string) $this->miscellaneousEntriesConfig('payroll_client_email'),
            'debit_account_code' => (string) $this->miscellaneousEntriesConfig('payroll_debit_account_code'),
            'debit_gfs_code' => (string) $this->miscellaneousEntriesConfig('payroll_debit_gfs_code'),
            'salary_account_code' => (string) ($payable['net_pay_account_code']),
            'salary_gfs_code' => (string) ($payable['net_pay_gfs_code']),
            'paye_account_code' => (string) ($payable['paye_account_code']),
            'paye_gfs_code' => (string) ($payable['paye_gfs_code']),
            'deductions_account_code' => (string) ($payable['deductions_account_code']),
            'deductions_gfs_code' => (string) ($payable['deductions_gfs_code']),
            'loans_account_code' => (string) ($payable['loans_account_code']),
            'loans_gfs_code' => (string) ($payable['loans_gfs_code']),
            'period_label' => "{$month}-{$run->payroll_year}",
        ];
    }

    protected function defaultConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.default_config.'.$key, Config::get('services.erms.default_config.'.$key, $default));
    }

    protected function miscellaneousEntriesConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.miscellaneous_entries.'.$key, $default);
    }

    private function deductionAccountResolver(): DeductionAccountResolver
    {
        return $this->deductionAccountResolver ??= new DeductionAccountResolver();
    }

}
