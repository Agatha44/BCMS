<?php

namespace App\Services\Payroll;

use App\Constants\EmployeeStatus;
use App\Models\Bms\Bank;
use App\Models\Bms\Payroll\PayrollRun;
use App\Services\Erms\Payroll\BankResolver;
use App\Services\Erms\Payroll\PayrollDeductionPayableConfig;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollReportService
{
    public function __construct(
        private BankResolver $bankResolver,
        private PayrollDeductionPayableConfig $deductionPayableConfig,
        private PayrollDocumentService $documentService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listReportTypes(): array
    {
        $types = [];

        foreach ($this->typesConfig() as $key => $definition) {
            if (! ($definition['enabled'] ?? true)) {
                continue;
            }

            $label = (string) ($definition['label'] ?? strtoupper($key));
            $types[] = [
                'key' => $key,
                'label' => $this->resolveInstitutionLabel($key, $label),
                'description' => (string) ($definition['description'] ?? ''),
                'sort_order' => (int) ($definition['sort_order'] ?? 0),
                'download_available' => $this->documentTypeForReport($key) !== null,
                'filters' => $this->buildFilters($definition['filters'] ?? []),
            ];
        }

        usort($types, static fn (array $a, array $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return $types;
    }

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     filters: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     pagination: array<string, int>
     * }
     */
    public function generateReport(
        string $reportType,
        string $month,
        int $page = 1,
        int $perPage = 15,
        ?string $bank = null,
        ?int $loanTypeId = null,
    ): array {
        $canonicalType = $this->normalizeReportType($reportType);
        ['run' => $run, 'period' => $period] = $this->resolveRunContext($month);

        $bankFilter = null;
        if ($bank !== null && trim($bank) !== '') {
            if ($canonicalType !== 'netpay') {
                throw new InvalidArgumentException('bank filter is only supported for netpay reports.');
            }
            $bankFilter = $this->resolveNetPayBankFilter($bank);
        }

        if ($loanTypeId !== null && $canonicalType !== 'loans') {
            throw new InvalidArgumentException('loan_type_id filter is only supported for loans reports.');
        }

        if ($loanTypeId !== null) {
            $this->assertLoanTypeExists($loanTypeId);
        }

        $txTable = $this->txTableForRun($run);
        $allRows = match ($canonicalType) {
            'netpay' => $this->buildNetPayRows($run, $txTable, $bankFilter),
            'paye' => $this->buildPayeRows($run, $txTable),
            'psssf' => $this->buildPsssfRows($run, $txTable),
            'heslb' => $this->buildHeslbRows($run, $txTable),
            'other' => $this->buildOtherRows($run),
            'loans' => $this->buildLoansRows($run, $loanTypeId),
            default => throw new InvalidArgumentException("Unsupported report_type: {$canonicalType}."),
        };

        $summary = $this->buildSummary($canonicalType, $allRows, $bankFilter, $loanTypeId);
        $total = $allRows->count();
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;
        $typeConfig = $this->typeConfig($canonicalType);

        return [
            'meta' => array_merge($this->runMeta($run, $canonicalType, $period, $txTable), [
                'bank_filter' => $bankFilter['value'] ?? null,
                'bank_filter_type' => $bankFilter['type'] ?? null,
                'loan_type_id' => $loanTypeId,
                'download' => $this->documentDownloadMeta($canonicalType, $run, $bankFilter),
            ]),
            'filters' => $this->buildFilters($typeConfig['filters'] ?? []),
            'rows' => $allRows->slice($offset, $perPage)->values()->all(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function downloadDocument(
        string $reportType,
        string $month,
        ?string $bank,
        ?string $format,
        bool $wantsJson,
        bool $expectsJson,
        ?int $loanTypeId = null,
    ): array {
        $canonicalType = $this->normalizeReportType($reportType);
        $documentType = $this->documentTypeForReport($canonicalType);

        if ($documentType === null) {
            throw new InvalidArgumentException('No PDF document is available for this report type.');
        }

        $run = $this->resolveRunContext($month)['run'];
        $runLookup = (string) $run->id;
        $format = $format !== null && trim($format) !== '' ? trim($format) : null;
        $bankBucket = $canonicalType === 'netpay' ? $this->netPayBankBucket($bank) : null;

        if ($loanTypeId !== null && $canonicalType !== 'loans') {
            throw new InvalidArgumentException('loan_type_id filter is only supported for loans reports.');
        }

        if ($loanTypeId !== null) {
            $this->assertLoanTypeExists($loanTypeId);
        }

        return match ($documentType) {
            'net_pay' => $this->documentService->getNetPayDocumentEndpoint($runLookup, $format, $wantsJson, $expectsJson, $bankBucket),
            'payee' => $this->documentService->getPayeeDocumentEndpoint($runLookup, $format, $wantsJson, $expectsJson),
            'psssf' => $this->documentService->getPsssfDocumentEndpoint($runLookup, $format, $wantsJson, $expectsJson),
            'heslb' => $this->documentService->getHeslbDocumentEndpoint($runLookup, $format, $wantsJson, $expectsJson),
            'loans' => $this->documentService->getLoansDocumentEndpoint($runLookup, $format, $wantsJson, $expectsJson, $loanTypeId),
            default => throw new InvalidArgumentException('No PDF document is available for this report type.'),
        };
    }

    public function normalizeReportType(string $reportType): string
    {
        $reportType = strtolower(trim($reportType));
        if ($reportType === '') {
            throw new InvalidArgumentException('report_type is required.');
        }

        foreach ($this->typesConfig() as $key => $definition) {
            if (! ($definition['enabled'] ?? true)) {
                continue;
            }

            if ($key === $reportType) {
                return $key;
            }

            foreach ($definition['aliases'] ?? [] as $alias) {
                if (strtolower(trim((string) $alias)) === $reportType) {
                    return $key;
                }
            }
        }

        throw new InvalidArgumentException("Unknown report_type: {$reportType}.");
    }

    /**
     * @return array{run: PayrollRun, period: array{year: int, month: int, label: string, value: string}}
     */
    private function resolveRunContext(string $month): array
    {
        $period = $this->parseMonth($month);
        $run = PayrollRun::query()
            ->where('payroll_year', $period['year'])
            ->where('payroll_month', $period['month'])
            ->orderByDesc('id')
            ->first();

        if ($run === null) {
            throw new DomainException('No payroll run found for '.$period['label'].'.');
        }

        return ['run' => $run, 'period' => $period];
    }

    /**
     * @return array{year: int, month: int, label: string, value: string}
     */
    private function parseMonth(string $month): array
    {
        $month = trim($month);
        if (! preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
            throw new InvalidArgumentException('month must be in YYYY-MM format.');
        }

        $year = (int) $matches[1];
        $monthNum = (int) $matches[2];

        if ($monthNum < 1 || $monthNum > 12) {
            throw new InvalidArgumentException('month must be between 01 and 12.');
        }

        return [
            'year' => $year,
            'month' => $monthNum,
            'label' => Carbon::create($year, $monthNum, 1)->format('F Y'),
            'value' => $matches[1].'-'.$matches[2],
        ];
    }

    private function txTableForRun(PayrollRun $run): string
    {
        if (strtolower((string) ($run->status ?? '')) === 'posted') {
            return 'payroll_transaction';
        }

        $finalExists = DB::connection('bcmis2')
            ->table('payroll_transaction')
            ->where('payroll_run_id', $run->id)
            ->exists();

        return $finalExists ? 'payroll_transaction' : 'payroll_transaction_preview';
    }

    private function employeeTxQuery(PayrollRun $run, string $txTable): Builder
    {
        return DB::connection('bcmis2')->table($txTable.' as pt')
            ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'pt.national_id')
            ->leftJoin('bcmis2.bank as b', 'b.bank_id', '=', 'pt.bank_id')
            ->where('b.is_active', true)
            ->whereIn('be.employee_status', EmployeeStatus::activeValues())
            ->where('pt.payroll_run_id', $run->id);
    }

    /**
     * @param  array{type: 'bucket'|'bank_id', value: string|int}|null  $bankFilter
     * @return Collection<int, array<string, mixed>>
     */
    private function buildNetPayRows(PayrollRun $run, string $txTable, ?array $bankFilter): Collection
    {
        $query = $this->employeeTxQuery($run, $txTable)->where('pt.net_pay', '>', 0);

        if (($bankFilter['type'] ?? null) === 'bank_id') {
            $query->where('pt.bank_id', (int) $bankFilter['value']);
        }

        $rows = $query
            ->select([
                'pt.pf_number',
                'pt.national_id',
                'pt.bank_id',
                'pt.net_pay',
                'pt.account_number',
                'b.bank_name',
                DB::raw("TRIM(CONCAT(be.fname, ' ', be.mname, ' ', be.sname)) AS employee_name"),
            ])
            ->orderBy('pt.pf_number')
            ->get();

        if ($bankFilter === null || ($bankFilter['type'] ?? null) !== 'bucket') {
            return $rows->map(fn ($row) => $this->formatNetPayRow($row));
        }

        return $this->filterRowsByBankBucket($rows, (string) $bankFilter['value'])
            ->map(fn ($row) => $this->formatNetPayRow($row));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPayeRows(PayrollRun $run, string $txTable): Collection
    {
        return $this->employeeTxQuery($run, $txTable)
            ->where('pt.paye', '>', 0)
            ->select([
                'pt.pf_number',
                'be.tin',
                'pt.national_id',
                'pt.basic_salary',
                'pt.gross_pay',
                'pt.paye',
                DB::raw("TRIM(CONCAT(be.fname, ' ', be.mname, ' ', be.sname)) AS employee_name"),
            ])
            ->orderBy('pt.pf_number')
            ->get()
            ->map(fn ($row) => array_merge($this->employeeRow($row), [
                'tin' => (string) ($row->tin ?? ''),
                'basic_salary' => $this->amount($row->basic_salary),
                'gross_pay' => $this->amount($row->gross_pay),
                'paye' => $this->amount($row->paye),
            ]));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPsssfRows(PayrollRun $run, string $txTable): Collection
    {
        return $this->employeeTxQuery($run, $txTable)
            ->where('pt.psssf_contribution', '>', 0)
            ->select([
                'pt.pf_number',
                'pt.national_id',
                'be.basicsalary',
                'pt.psssf_contribution',
                'pt.psssf_employer_contribution',
                DB::raw("TRIM(CONCAT(be.fname, ' ', be.mname, ' ', be.sname)) AS employee_name"),
            ])
            ->orderBy('pt.pf_number')
            ->get()
            ->map(fn ($row) => array_merge($this->employeeRow($row), [
                'basic_salary' => $this->amount($row->basicsalary),
                'psssf_contribution' => $this->amount($row->psssf_contribution),
                'psssf_employer_contribution' => $this->amount($row->psssf_employer_contribution),
            ]));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildHeslbRows(PayrollRun $run, string $txTable): Collection
    {
        $typeIds = $this->deductionPayableConfig->deductionTypeIdsForKind('heslb');
        if ($typeIds === []) {
            return collect();
        }

        return $this->employeeTxQuery($run, $txTable)
            ->join('employee_payroll_items as i', function ($join) {
                $join->on('i.payroll_run_id', '=', 'pt.payroll_run_id')
                    ->on('i.national_id', '=', 'pt.national_id');
            })
            ->where('i.is_void', false)
            ->where('i.item_type', 'deduction')
            ->where('i.type_table', 'deduction_type')
            ->whereIn('i.type_id', $typeIds)
            ->groupBy(['pt.pf_number', 'pt.national_id', 'be.fname', 'be.mname', 'be.sname'])
            ->havingRaw('COALESCE(SUM(i.amount), 0) > 0')
            ->select([
                'pt.pf_number',
                'pt.national_id',
                DB::raw("TRIM(CONCAT(be.fname, ' ', be.mname, ' ', be.sname)) AS employee_name"),
                DB::raw('COALESCE(SUM(i.amount), 0) as heslb'),
            ])
            ->orderBy('pt.pf_number')
            ->get()
            ->map(fn ($row) => array_merge($this->employeeRow($row), [
                'heslb' => $this->amount($row->heslb),
            ]));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildLoansRows(PayrollRun $run, ?int $loanTypeId): Collection
    {
        $query = DB::connection('bcmis2')->table('employee_payroll_items as i')
            ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'i.national_id')
            ->leftJoin('bcmis2.loan_type as lt', function ($join) {
                $join->on('lt.loan_type_id', '=', 'i.type_id')
                    ->where('i.type_table', '=', 'loan_type');
            })
            ->leftJoin('bcmis2.employee_loan as el', function ($join) {
                $join->on('el.employee_loan_id', '=', 'i.source_id')
                    ->where('i.source_table', '=', 'employee_loan');
            })
            ->leftJoin('bcmis2.employee_loan_repayment as lr', function ($join) use ($run) {
                $join->on('lr.employee_loan_id', '=', 'el.employee_loan_id')
                    ->where('lr.repayment_source', '=', 'payroll')
                    ->where('lr.payroll_month', '=', (int) $run->payroll_month)
                    ->where('lr.payroll_year', '=', (int) $run->payroll_year);
            })
            ->whereIn('be.employee_status', EmployeeStatus::activeValues())
            ->where('i.payroll_run_id', $run->id)
            ->where('i.is_void', false)
            ->where('i.item_type', 'loan')
            ->where('i.source_table', 'employee_loan')
            ->where('i.amount', '>', 0);

        if ($loanTypeId !== null) {
            $query->where('i.type_table', 'loan_type')
                ->where('i.type_id', $loanTypeId);
        }

        return $query
            ->select([
                'be.pfno',
                'i.national_id',
                'i.type_id as loan_type_id',
                'i.source_id as employee_loan_id',
                'i.amount as total_repayment_raw',
                'lt.loan_name',
                'lt.loan_code',
                'el.loan_reference_number',
                'el.monthly_principal_amount',
                'el.monthly_interest_amount',
                'lr.repayment_principal_amount',
                'lr.repayment_interest_amount',
                'lr.opening_outstanding_balance_amount',
                'lr.closing_outstanding_balance_amount',
                DB::raw("TRIM(CONCAT(be.fname, ' ', be.mname, ' ', be.sname)) AS employee_name"),
            ])
            ->orderBy('be.pfno')
            ->orderBy('lt.loan_name')
            ->get()
            ->map(fn ($row) => $this->formatLoanRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLoanRow(object $row): array
    {
        $totalRepayment = $this->amount($row->total_repayment_raw);
        $principal = $this->amount($row->repayment_principal_amount ?? $row->monthly_principal_amount ?? 0);
        $interest = $this->amount($row->repayment_interest_amount ?? $row->monthly_interest_amount ?? 0);

        if ($principal + $interest <= 0 && $totalRepayment > 0) {
            $principal = $totalRepayment;
            $interest = 0.0;
        } elseif (abs(($principal + $interest) - $totalRepayment) > 0.01) {
            $interest = $this->amount(max(0, $totalRepayment - $principal));
        }

        return array_merge($this->employeeRow((object) [
            'pf_number' => $row->pfno ?? '',
            'national_id' => $row->national_id ?? '',
            'employee_name' => $row->employee_name ?? '',
        ]), [
            'loan_type_id' => (int) ($row->loan_type_id ?? 0),
            'employee_loan_id' => (int) ($row->employee_loan_id ?? 0),
            'loan_name' => (string) ($row->loan_name ?? ''),
            'loan_code' => (string) ($row->loan_code ?? ''),
            'loan_reference_number' => (string) ($row->loan_reference_number ?? ''),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_repayment' => $totalRepayment,
            'opening_balance' => $this->amount($row->opening_outstanding_balance_amount),
            'closing_balance' => $this->amount($row->closing_outstanding_balance_amount),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildOtherRows(PayrollRun $run): Collection
    {
        $excludedTypeIds = $this->statutoryDeductionTypeIds();
        $grouped = [];

        $items = DB::connection('bcmis2')->table('employee_payroll_items as i')
            ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'i.national_id')
            ->whereIn('be.employee_status', EmployeeStatus::activeValues())
            ->where('i.payroll_run_id', $run->id)
            ->where('i.is_void', false)
            ->whereIn('i.item_type', ['deduction', 'loan'])
            ->select(['i.national_id', 'i.amount', 'be.pfno', 'be.fname', 'be.mname', 'be.sname', 'i.item_type', 'i.type_id'])
            ->get();

        foreach ($items as $item) {
            if ($item->item_type === 'deduction' && in_array((int) $item->type_id, $excludedTypeIds, true)) {
                continue;
            }

            $nationalId = (string) ($item->national_id ?? '');
            if ($nationalId === '') {
                continue;
            }

            $amount = $this->amount($item->amount);
            if ($amount <= 0) {
                continue;
            }

            if (! isset($grouped[$nationalId])) {
                $grouped[$nationalId] = [
                    'pf_number' => (string) ($item->pfno ?? ''),
                    'national_id' => $nationalId,
                    'employee_name' => trim(implode(' ', array_filter([
                        (string) ($item->fname ?? ''),
                        (string) ($item->mname ?? ''),
                        (string) ($item->sname ?? ''),
                    ]))),
                    'total' => 0.0,
                ];
            }

            $grouped[$nationalId]['total'] += $amount;
        }

        return collect($grouped)
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortBy('pf_number')
            ->values()
            ->map(fn (array $row) => [
                'pf_number' => $row['pf_number'],
                'national_id' => $row['national_id'],
                'employee_name' => $row['employee_name'],
                'total' => $this->amount($row['total']),
            ]);
    }

    /**
     * @return list<int>
     */
    private function statutoryDeductionTypeIds(): array
    {
        $ids = [];
        foreach (['paye', 'psssf', 'heslb'] as $kind) {
            if ($this->deductionPayableConfig->isKnownKind($kind)) {
                $ids = array_merge($ids, $this->deductionPayableConfig->deductionTypeIdsForKind($kind));
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param  array{type: 'bucket'|'bank_id', value: string|int}|null  $bankFilter
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildSummary(string $reportType, Collection $rows, ?array $bankFilter, ?int $loanTypeId = null): array
    {
        $count = ['employee_count' => $rows->count()];

        return match ($reportType) {
            'netpay' => $count + [
                'total_net_pay' => $this->amount($rows->sum('net_pay')),
                'bank_filter' => $bankFilter['value'] ?? null,
                'bank_filter_type' => $bankFilter['type'] ?? null,
            ],
            'paye' => $count + ['total_paye' => $this->amount($rows->sum('paye'))],
            'psssf' => $count + [
                'total_employee_contribution' => $this->amount($rows->sum('psssf_contribution')),
                'total_employer_contribution' => $this->amount($rows->sum('psssf_employer_contribution')),
                'total_psssf' => $this->amount($rows->sum('psssf_contribution') + $rows->sum('psssf_employer_contribution')),
            ],
            'heslb' => $count + ['total_heslb' => $this->amount($rows->sum('heslb'))],
            'other' => $count + ['total' => $this->amount($rows->sum('total'))],
            'loans' => $this->buildLoansSummary($rows, $loanTypeId),
            default => [],
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildLoansSummary(Collection $rows, ?int $loanTypeId): array
    {
        $byLoanType = $rows
            ->groupBy('loan_type_id')
            ->map(function (Collection $typeRows, $typeId) {
                $first = $typeRows->first();
                $loanName = (string) ($first['loan_name'] ?? '');
                $loanCode = (string) ($first['loan_code'] ?? '');

                return [
                    'loan_type_id' => (int) $typeId,
                    'loan_name' => $loanName,
                    'loan_code' => $loanCode,
                    'loan_type' => $this->loanTypeLabel($loanName, $loanCode),
                    'employee_count' => $typeRows->pluck('national_id')->unique()->count(),
                    'row_count' => $typeRows->count(),
                    'total_principal' => $this->amount($typeRows->sum('principal_amount')),
                    'total_interest' => $this->amount($typeRows->sum('interest_amount')),
                    'total_repayment' => $this->amount($typeRows->sum('total_repayment')),
                ];
            })
            ->sortBy('loan_type')
            ->values()
            ->all();

        return [
            'employee_count' => $rows->pluck('national_id')->unique()->count(),
            'row_count' => $rows->count(),
            'loan_type_filter' => $loanTypeId,
            'loan_type' => $this->resolveLoanTypeSummary($loanTypeId),
            'by_loan_type' => $byLoanType,
            'total_principal' => $this->amount($rows->sum('principal_amount')),
            'total_interest' => $this->amount($rows->sum('interest_amount')),
            'total_repayment' => $this->amount($rows->sum('total_repayment')),
        ];
    }

    /**
     * @return array{loan_type_id: int, loan_name: string, loan_code: string, loan_type: string}|null
     */
    private function resolveLoanTypeSummary(?int $loanTypeId): ?array
    {
        if ($loanTypeId === null) {
            return null;
        }

        $loanType = DB::connection('bcmis2')
            ->table('loan_type')
            ->where('loan_type_id', $loanTypeId)
            ->first(['loan_type_id', 'loan_name', 'loan_code']);

        if ($loanType === null) {
            return null;
        }

        $loanName = (string) ($loanType->loan_name ?? '');
        $loanCode = (string) ($loanType->loan_code ?? '');

        return [
            'loan_type_id' => (int) $loanType->loan_type_id,
            'loan_name' => $loanName,
            'loan_code' => $loanCode,
            'loan_type' => $this->loanTypeLabel($loanName, $loanCode),
        ];
    }

    private function loanTypeLabel(?string $loanName, ?string $loanCode): string
    {
        $loanName = trim((string) $loanName);
        $loanCode = trim((string) $loanCode);

        if ($loanName !== '' && $loanCode !== '') {
            return "{$loanName} ({$loanCode})";
        }

        return $loanName !== '' ? $loanName : $loanCode;
    }

    private function assertLoanTypeExists(int $loanTypeId): void
    {
        $exists = DB::connection('bcmis2')
            ->table('loan_type')
            ->where('loan_type_id', $loanTypeId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException("Unknown loan_type_id: {$loanTypeId}.");
        }
    }

    /**
     * @param  array{year: int, month: int, label: string, value: string}  $period
     * @return array<string, mixed>
     */
    private function runMeta(PayrollRun $run, string $reportType, array $period, string $txTable): array
    {
        return [
            'report_type' => $reportType,
            'month' => $period['value'],
            'period_label' => $period['label'],
            'payroll_run_id' => (int) $run->id,
            'payroll_number' => (string) ($run->payroll_number ?? ''),
            'status' => (string) ($run->status ?? ''),
            'source' => $txTable === 'payroll_transaction' ? 'final' : 'preview',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNetPayRow(object $row): array
    {
        return array_merge($this->employeeRow($row), [
            'bank_name' => (string) ($row->bank_name ?? ''),
            'bank_id' => (int) ($row->bank_id ?? 0),
            'account_number' => (string) ($row->account_number ?? ''),
            'net_pay' => $this->amount($row->net_pay),
        ]);
    }

    /**
     * @return array{pf_number: string, national_id: string, employee_name: string}
     */
    private function employeeRow(object $row): array
    {
        return [
            'pf_number' => (string) ($row->pf_number ?? ''),
            'national_id' => (string) ($row->national_id ?? ''),
            'employee_name' => trim((string) ($row->employee_name ?? '')),
        ];
    }

    private function amount(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function filterRowsByBankBucket(Collection $rows, string $bankBucket): Collection
    {
        $bankIds = $rows->pluck('bank_id')->filter()->unique()->values()->all();
        if ($bankIds === []) {
            return collect();
        }

        $banks = Bank::query()
            ->whereIn('bank_id', $bankIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('bank_id');

        return $rows
            ->filter(function ($row) use ($banks, $bankBucket) {
                $bank = $banks[(int) ($row->bank_id ?? 0)] ?? null;

                return $bank instanceof Bank && $this->bankResolver->resolve($bank) === $bankBucket;
            })
            ->values();
    }

    /**
     * Accepts an ERMS disbursement bucket (crdb, nbc, …) or a numeric bank_id.
     *
     * @return array{type: 'bucket'|'bank_id', value: string|int}
     */
    private function resolveNetPayBankFilter(string $bank): array
    {
        $bank = trim($bank);
        if ($bank === '') {
            throw new InvalidArgumentException('bank must not be empty when provided.');
        }

        if (ctype_digit($bank)) {
            $bankId = (int) $bank;
            if (! Bank::query()->where('bank_id', $bankId)->where('is_active', true)->exists()) {
                throw new InvalidArgumentException("Unknown bank: {$bankId}.");
            }

            return ['type' => 'bank_id', 'value' => $bankId];
        }

        $normalized = $this->bankResolver->normalizeBucketKey($bank);
        if (! $this->bankResolver->isKnownBucket($normalized)) {
            throw new InvalidArgumentException("Unknown bank bucket: {$bank}.");
        }

        return ['type' => 'bucket', 'value' => $normalized];
    }

    private function netPayBankBucket(?string $bank): ?string
    {
        if ($bank === null || trim($bank) === '') {
            return null;
        }

        $filter = $this->resolveNetPayBankFilter($bank);

        return ($filter['type'] ?? null) === 'bucket' ? (string) $filter['value'] : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function typesConfig(): array
    {
        $types = config('payroll_reports.types', []);

        return is_array($types) ? $types : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function typeConfig(string $reportType): array
    {
        return $this->typesConfig()[$reportType] ?? [];
    }

    private function documentTypeForReport(string $reportType): ?string
    {
        $document = trim((string) ($this->typeConfig($reportType)['document'] ?? ''));

        return $document !== '' ? $document : null;
    }

    /**
     * @param  array{type: 'bucket'|'bank_id', value: string|int}|null  $bankFilter
     * @return array<string, mixed>
     */
    private function documentDownloadMeta(string $reportType, PayrollRun $run, ?array $bankFilter): array
    {
        $documentType = $this->documentTypeForReport($reportType);
        if ($documentType === null) {
            return ['available' => false];
        }

        $meta = [
            'available' => true,
            'format' => 'pdf',
            'document_type' => $documentType,
            'endpoint' => '/api/payroll/reports/download',
            'method' => 'POST',
            'payroll_run_id' => (int) $run->id,
        ];

        if ($reportType === 'netpay') {
            $meta['bank_param'] = 'bank';
            if (($bankFilter['type'] ?? null) === 'bucket' && ($bankFilter['value'] ?? '') !== '') {
                $meta['bank'] = (string) $bankFilter['value'];
            }
        }

        if ($reportType === 'loans') {
            $meta['loan_type_id_param'] = 'loan_type_id';
        }

        return $meta;
    }

    private function resolveInstitutionLabel(string $key, string $fallback): string
    {
        if (! $this->deductionPayableConfig->isKnownKind($key)) {
            return $fallback;
        }

        try {
            $label = trim((string) ($this->deductionPayableConfig->definition($key)['label'] ?? ''));

            return $label !== '' ? "{$label} report" : $fallback;
        } catch (InvalidArgumentException) {
            return $fallback;
        }
    }

    /**
     * @param  list<string>|mixed  $filterNames
     * @return list<array<string, mixed>>
     */
    private function buildFilters(mixed $filterNames): array
    {
        if (! is_array($filterNames)) {
            return [];
        }

        $filters = [];
        foreach ($filterNames as $name) {
            $normalized = strtolower(trim((string) $name));

            if ($normalized === 'bank') {
                $filters[] = [
                    'name' => 'bank',
                    'type' => 'select',
                    'label' => 'Bank',
                    'options' => $this->bankFilterOptions(),
                ];
            }

            if ($normalized === 'loan_type_id') {
                $filters[] = [
                    'name' => 'loan_type_id',
                    'type' => 'select',
                    'label' => 'Loan type',
                    'options' => $this->loanTypeFilterOptions(),
                ];
            }
        }

        return $filters;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function bankFilterOptions(): array
    {
        $options = [['value' => '', 'label' => 'All banks']];

        foreach ($this->bankResolver->bucketKeys() as $bucketKey) {
            $options[] = [
                'value' => $bucketKey,
                'label' => $this->bankResolver->label($bucketKey),
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function loanTypeFilterOptions(): array
    {
        $options = [['value' => '', 'label' => 'All loan types']];

        $loanTypes = DB::connection('bcmis2')
            ->table('loan_type')
            ->where('is_active', true)
            ->orderBy('loan_name')
            ->get(['loan_type_id', 'loan_name', 'loan_code']);

        foreach ($loanTypes as $loanType) {
            $label = (string) ($loanType->loan_name ?? '');
            $code = trim((string) ($loanType->loan_code ?? ''));

            if ($code !== '') {
                $label = "{$label} ({$code})";
            }

            $options[] = [
                'value' => (string) $loanType->loan_type_id,
                'label' => $label,
            ];
        }

        return $options;
    }
}
