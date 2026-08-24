<?php

namespace App\Services\Payroll;

use App\Constants\EmployeeStatus;
use App\Models\Bms\Bank;
use App\Models\Bms\Payroll\PayrollRun;
use App\Services\Erms\Payroll\BankResolver;
use App\Services\Erms\Payroll\PayrollDeductionPayableConfig;
use InvalidArgumentException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class PayrollDocumentService
{
    public function __construct(
        private PayrollDeductionPayableConfig $deductionPayableConfig,
        private BankResolver $bankResolver,
    ) {}

    public function resolveRun(string $lookup): ?PayrollRun
    {
        $lookup = trim($lookup);
        if ($lookup === '') {
            return null;
        }

        if (ctype_digit($lookup)) {
            return PayrollRun::query()->find((int) $lookup);
        }

        return PayrollRun::query()->where('payroll_number', $lookup)->first();
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getJvDocumentEndpoint(string $runLookup, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        return $this->documentEndpoint(
            $runLookup,
            'jv',
            fn (PayrollRun $run) => $this->generateJvDocument($run),
            'Payroll_JV_Sheet',
            'Payroll JV sheet generated successfully',
            $format,
            $wantsJson,
            $expectsJson
        );
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getPayeeDocumentEndpoint(string $runLookup, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        return $this->documentEndpoint(
            $runLookup,
            'payee',
            fn (PayrollRun $run) => $this->generatePayeeDocument($run),
            'Payroll_PAYE_Sheet',
            'Payroll PAYE sheet generated successfully',
            $format,
            $wantsJson,
            $expectsJson
        );
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getPsssfDocumentEndpoint(string $runLookup, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        return $this->documentEndpoint(
            $runLookup,
            'psssf',
            fn (PayrollRun $run) => $this->generatePsssfDocument($run),
            'Payroll_PSSSF_Sheet',
            'Payroll PSSSF sheet generated successfully',
            $format,
            $wantsJson,
            $expectsJson
        );
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getHeslbDocumentEndpoint(string $runLookup, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        return $this->documentEndpoint(
            $runLookup,
            'heslb',
            fn (PayrollRun $run) => $this->generateHeslbDocument($run),
            'Payroll_HESLB_Sheet',
            'Payroll HESLB sheet generated successfully',
            $format,
            $wantsJson,
            $expectsJson
        );
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getLoansDocumentEndpoint(
        string $runLookup,
        ?string $format,
        bool $wantsJson,
        bool $expectsJson,
        ?int $loanTypeId = null,
    ): array {
        try {
            $run = $this->resolveRun($runLookup);
            if ($run === null) {
                return [
                    'success' => false,
                    'error' => 'Payroll run not found',
                    'http' => 404,
                ];
            }

            if (! $format) {
                $format = ($wantsJson || $expectsJson) ? 'base64' : 'download';
            }

            $loanTypeLabel = $this->resolveLoanTypeLabel($loanTypeId);
            $pdfBase64 = $this->generateLoansDocument($run, $loanTypeId, $loanTypeLabel);
            if (! $pdfBase64) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate payroll document',
                    'http' => 500,
                ];
            }

            $filePrefix = 'Payroll_Loans_Sheet';
            if ($loanTypeLabel !== null) {
                $filePrefix .= '_'.preg_replace('/[^A-Za-z0-9_\-]+/', '_', $loanTypeLabel);
            }
            $fileName = $this->buildFileName($filePrefix, $run);

            if ($format === 'download') {
                return [
                    'success' => true,
                    'response' => response(base64_decode($pdfBase64), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                    ]),
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdfBase64,
                    'payroll_run_id' => (int) $run->id,
                    'payroll_number' => (string) ($run->payroll_number ?? ''),
                    'document_type' => 'loans',
                    'loan_type_id' => $loanTypeId,
                    'loan_type_label' => $loanTypeLabel,
                    'file_name' => $fileName,
                ],
                'message' => 'Payroll loans sheet generated successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Payroll document generation failed', [
                'run' => $runLookup,
                'type' => 'loans',
                'loan_type_id' => $loanTypeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate payroll document: '.$e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getNetPayDocumentEndpoint(
        string $runLookup,
        ?string $format,
        bool $wantsJson,
        bool $expectsJson,
        ?string $bankBucket = null,
    ): array {
        try {
            $run = $this->resolveRun($runLookup);
            if ($run === null) {
                return [
                    'success' => false,
                    'error' => 'Payroll run not found',
                    'http' => 404,
                ];
            }

            $bankBucket = $this->normalizeNetPayBankBucket($bankBucket);

            if (! $format) {
                $format = ($wantsJson || $expectsJson) ? 'base64' : 'download';
            }

            $pdfBase64 = $this->generateNetPayDocument($run, $bankBucket);
            if (! $pdfBase64) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate payroll document',
                    'http' => 500,
                ];
            }

            $filePrefix = 'Payroll_Net_Pay_Sheet';
            if ($bankBucket !== null) {
                $filePrefix .= '_'.strtoupper($bankBucket);
            }
            $fileName = $this->buildFileName($filePrefix, $run);

            if ($format === 'download') {
                return [
                    'success' => true,
                    'response' => response(base64_decode($pdfBase64), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                    ]),
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdfBase64,
                    'payroll_run_id' => (int) $run->id,
                    'payroll_number' => (string) ($run->payroll_number ?? ''),
                    'document_type' => 'net_pay',
                    'bank_bucket' => $bankBucket,
                    'bank_bucket_label' => $bankBucket !== null
                        ? $this->bankResolver->label($bankBucket)
                        : null,
                    'file_name' => $fileName,
                ],
                'message' => 'Payroll net pay sheet generated successfully',
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http' => 422,
            ];
        } catch (\Exception $e) {
            Log::error('Payroll document generation failed', [
                'run' => $runLookup,
                'type' => 'net_pay',
                'bank_bucket' => $bankBucket,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate payroll document: '.$e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    public function getMinutesDocumentEndpoint(string $runLookup, ?string $format, bool $wantsJson, bool $expectsJson): array
    {
        return $this->documentEndpoint(
            $runLookup,
            'minutes',
            fn (PayrollRun $run) => $this->generateMinutesDocument($run),
            'Payroll_Minutes',
            'Payroll minutes document generated successfully',
            $format,
            $wantsJson,
            $expectsJson
        );
    }

    public function generateMinutesDocument(PayrollRun $run): string
    {
        $history = DB::connection('bcmis2')
            ->table('payroll_runs_history as h')
            ->leftJoin('bcmis.auth_user as performed_user', function ($join) {
                $join->on('performed_user.pf_number', '=', 'h.performed_by')
                    ->orWhereRaw('CAST(performed_user.id AS CHAR) = h.performed_by');
            })
            ->where('h.payroll_run_id', (int) $run->id)
            ->orderBy('h.id', 'asc')
            ->select([
                'h.id',
                'h.action',
                'h.status',
                'h.workflow_status',
                'h.performed_by',
                'h.performed_by_role',
                'h.comment',
                'h.created_at',
                DB::raw("NULLIF(TRIM(CONCAT(performed_user.first_name, ' ', performed_user.surname)), '') as performed_by_name"),
            ])
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'action' => $row->action,
                    'status' => $row->status,
                    'workflow_status' => $row->workflow_status,
                    'performed_by' => $row->performed_by,
                    'performed_by_role' => $row->performed_by_role,
                    'comment' => $row->comment,
                    'created_at' => $row->created_at,
                    'performed_by_name' => $row->performed_by_name,
                ];
            })
            ->values()
            ->all();

        return $this->renderPdf('payroll.documents.payroll_minutes', [
            'payrollNumber' => (string) ($run->payroll_number ?? ''),
            'history' => $history,
        ]);
    }

    public function generateJvDocument(PayrollRun $run): string
    {
        $rows = $this->loadJvRows($run);

        return $this->renderPdf('payroll.documents.jv_document', [
            'runInfo' => $this->runInfo($run),
            'employees' => $rows,
        ]);
    }

    public function generatePayeeDocument(PayrollRun $run): string
    {
        $rows = $this->loadJvRows($run)
            ->filter(fn ($row) => (float) ($row->paye ?? 0) > 0)
            ->values();

        return $this->renderPdf('payroll.documents.deduction_list_document', [
            'runInfo' => $this->runInfo($run),
            'employees' => $rows,
            'documentTitle' => 'Payroll PAYE (Payee) Sheet',
            'basicSalaryColumnLabel' => 'Gross Pay',
            'basicSalaryField' => 'gross_pay',
            'amountColumnLabel' => 'PAYE',
            'amountField' => 'paye',
        ]);
    }

    public function generatePsssfDocument(PayrollRun $run): string
    {
        $rows = $this->loadJvRows($run)
            ->filter(function ($row) {
                return (float) ($row->psssf_contribution ?? 0) > 0
                    || (float) ($row->psssf_employer_contribution ?? 0) > 0;
            })
            ->values();

        return $this->renderPdf('payroll.documents.deduction_list_document', [
            'runInfo' => $this->runInfo($run),
            'employees' => $rows,
            'documentTitle' => 'Payroll PSSSF Sheet',
            'basicSalaryColumnLabel' => 'Basic Salary',
            'basicSalaryField' => 'basic_salary',
            'amountColumnLabel' => 'Employee Contribution',
            'amountField' => 'psssf_contribution',
            'employerAmountColumnLabel' => 'Employer Contribution',
            'employerAmountField' => 'psssf_employer_contribution',
        ]);
    }

    public function generateHeslbDocument(PayrollRun $run): string
    {
        $rows = $this->loadJvRows($run)
            ->filter(fn ($row) => (float) ($row->heslb ?? 0) > 0)
            ->values();

        return $this->renderPdf('payroll.documents.deduction_list_document', [
            'runInfo' => $this->runInfo($run),
            'employees' => $rows,
            'documentTitle' => 'Payroll HESLB Sheet',
            'amountColumnLabel' => 'HESLB',
            'amountField' => 'heslb',
        ]);
    }

    public function generateLoansDocument(PayrollRun $run, ?int $loanTypeId = null, ?string $loanTypeLabel = null): string
    {
        $rows = $this->loadLoansDocumentRows($run, $loanTypeId);

        $documentTitle = 'Staff Loans Report';
        if ($loanTypeLabel !== null && $loanTypeLabel !== '') {
            $documentTitle .= ' ('.$loanTypeLabel.')';
        }

        return $this->renderPdf('payroll.documents.loans_list_document', [
            'runInfo' => $this->runInfo($run),
            'rows' => $rows,
            'documentTitle' => $documentTitle,
        ], 'L');
    }

    public function generateNetPayDocument(PayrollRun $run, ?string $bankBucket = null): string
    {
        $bankBucket = $this->normalizeNetPayBankBucket($bankBucket);
        $rows = $this->loadNetPayRows($run, $bankBucket);
        $runInfo = $this->runInfo($run);

        $documentTitle = 'Payroll Net Pay Sheet';
        if ($bankBucket !== null) {
            $bucketLabel = $this->bankResolver->label($bankBucket);
            $documentTitle .= " ({$bucketLabel})";
            $runInfo['bank_bucket'] = $bankBucket;
            $runInfo['bank_bucket_label'] = $bucketLabel;
        }

        return $this->renderPdf('payroll.documents.net_pay_list_document', [
            'runInfo' => $runInfo,
            'employees' => $rows,
            'documentTitle' => $documentTitle,
            'amountColumnLabel' => 'Net Pay',
            'amountField' => 'net_pay',
        ]);
    }

    /**
     * @param  callable(PayrollRun): string  $generate
     * @return array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}
     */
    private function documentEndpoint(
        string $runLookup,
        string $type,
        callable $generate,
        string $filePrefix,
        string $successMessage,
        ?string $format,
        bool $wantsJson,
        bool $expectsJson,
    ): array {
        try {
            $run = $this->resolveRun($runLookup);
            if ($run === null) {
                return [
                    'success' => false,
                    'error' => 'Payroll run not found',
                    'http' => 404,
                ];
            }

            if (! $format) {
                $format = ($wantsJson || $expectsJson) ? 'base64' : 'download';
            }

            $pdfBase64 = $generate($run);
            if (! $pdfBase64) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate payroll document',
                    'http' => 500,
                ];
            }

            $fileName = $this->buildFileName($filePrefix, $run);

            if ($format === 'download') {
                return [
                    'success' => true,
                    'response' => response(base64_decode($pdfBase64), 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                    ]),
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'pdf_base64' => $pdfBase64,
                    'payroll_run_id' => (int) $run->id,
                    'payroll_number' => (string) ($run->payroll_number ?? ''),
                    'document_type' => $type,
                    'file_name' => $fileName,
                ],
                'message' => $successMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Payroll document generation failed', [
                'run' => $runLookup,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate payroll document: '.$e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function loadNetPayRows(PayrollRun $run, ?string $bankBucket = null): Collection
    {
        $txTable = strtolower((string) ($run->status ?? '')) === 'posted'
            ? 'payroll_transaction'
            : 'payroll_transaction_preview';

        $rows = DB::connection('bcmis2')->table($txTable.' as pt')
            ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'pt.national_id')
            ->leftJoin('bcmis2.bank as b', 'b.bank_id', '=', 'pt.bank_id')
            ->where('b.is_active', true)
            ->whereIn('be.employee_status', EmployeeStatus::activeValues())
            ->where('pt.payroll_run_id', $run->id)
            ->where('pt.net_pay', '>', 0)
            ->select([
                'pt.pf_number',
                'pt.national_id',
                'pt.bank_id',
                'pt.net_pay',
                'pt.account_number',
                'b.bank_name',
                DB::raw("CONCAT(be.fname, ' ', be.mname, ' ', be.sname) AS employee_name"),
            ])
            ->orderBy('pt.pf_number')
            ->get();

        if ($bankBucket === null || $bankBucket === '') {
            return $rows;
        }

        $bankBucket = $this->bankResolver->normalizeBucketKey($bankBucket);
        $bankIds = $rows->pluck('bank_id')->filter()->unique()->values()->all();
        if ($bankIds === []) {
            return collect();
        }

        $banks = Bank::query()
            ->whereIn('bank_id', $bankIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('bank_id');

        return $rows->filter(function ($row) use ($banks, $bankBucket) {
            $bank = $banks[(int) ($row->bank_id ?? 0)] ?? null;
            if (! $bank instanceof Bank) {
                return false;
            }

            return $this->bankResolver->resolve($bank) === $bankBucket;
        })->values();
    }

    private function normalizeNetPayBankBucket(?string $bankBucket): ?string
    {
        $bankBucket = trim((string) $bankBucket);
        if ($bankBucket === '') {
            return null;
        }

        $normalized = $this->bankResolver->normalizeBucketKey($bankBucket);
        if (! $this->bankResolver->isKnownBucket($normalized)) {
            throw new InvalidArgumentException("Unknown net-pay bank bucket: {$bankBucket}.");
        }

        return $normalized;
    }

    /**
     * @return Collection<int, object>
     */
    private function loadJvRows(PayrollRun $run): Collection
    {
        $txTable = strtolower((string) ($run->status ?? '')) === 'posted'
            ? 'payroll_transaction'
            : 'payroll_transaction_preview';

        $heslbByNationalId = $this->heslbAmountsByNationalId((int) $run->id, $txTable);

        $rows = DB::connection('bcmis2')->table($txTable.' as pt')
            ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'pt.national_id')
            ->leftJoin('bcmis2.bank as b', 'b.bank_id', '=', 'pt.bank_id')
            ->where('b.is_active', true)
            ->whereIn('be.employee_status', EmployeeStatus::activeValues())
            ->where('pt.payroll_run_id', $run->id)
            ->select([
                'pt.pf_number',
                'pt.national_id',
                'pt.basic_salary',
                'pt.gross_pay',
                'pt.net_pay',
                'pt.paye',
                'pt.psssf_contribution',
                'pt.psssf_employer_contribution',
                'pt.total_loans',
                DB::raw("CONCAT(be.fname, ' ', be.mname, ' ', be.sname) AS employee_name"),
            ])
            ->orderBy('pt.pf_number')
            ->get();

        return $rows->map(function ($row) use ($heslbByNationalId) {
            $nationalId = (string) ($row->national_id ?? '');
            $row->heslb = round((float) ($heslbByNationalId[$nationalId] ?? 0), 2);

            return $row;
        });
    }

    /**
     * @return array<string, float>
     */
    private function heslbAmountsByNationalId(int $payrollRunId, string $txTable): array
    {
        $typeIds = $this->deductionPayableConfig->deductionTypeIdsForKind('heslb');
        if ($typeIds === []) {
            return [];
        }

        return DB::connection('bcmis2')
            ->table('employee_payroll_items as i')
            ->join($txTable.' as t', function ($join) {
                $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                    ->on('t.national_id', '=', 'i.national_id');
            })
            ->where('i.payroll_run_id', $payrollRunId)
            ->where('i.is_void', false)
            ->where('i.item_type', 'deduction')
            ->where('i.type_table', 'deduction_type')
            ->whereIn('i.type_id', $typeIds)
            ->groupBy('i.national_id')
            ->selectRaw('i.national_id, COALESCE(SUM(i.amount), 0) as heslb_amount')
            ->pluck('heslb_amount', 'national_id')
            ->map(fn ($amount) => round((float) $amount, 2))
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    private function loadLoansDocumentRows(PayrollRun $run, ?int $loanTypeId = null): Collection
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
                'be.pfno as pf_number',
                DB::raw("TRIM(CONCAT(be.fname, ' ', be.mname, ' ', be.sname)) AS employee_name"),
                'lt.loan_name',
                'lt.loan_code',
                'el.loan_reference_number',
                'i.amount as total_repayment_raw',
                'el.monthly_principal_amount',
                'el.monthly_interest_amount',
                'lr.repayment_principal_amount',
                'lr.repayment_interest_amount',
                'lr.opening_outstanding_balance_amount',
                'lr.closing_outstanding_balance_amount',
            ])
            ->orderBy('be.pfno')
            ->orderBy('lt.loan_name')
            ->get()
            ->map(fn ($row) => (object) $this->formatLoanDocumentRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLoanDocumentRow(object $row): array
    {
        $totalRepayment = round((float) ($row->total_repayment_raw ?? 0), 2);
        $principal = round((float) ($row->repayment_principal_amount ?? $row->monthly_principal_amount ?? 0), 2);
        $interest = round((float) ($row->repayment_interest_amount ?? $row->monthly_interest_amount ?? 0), 2);

        if ($principal + $interest <= 0 && $totalRepayment > 0) {
            $principal = $totalRepayment;
            $interest = 0.0;
        } elseif (abs(($principal + $interest) - $totalRepayment) > 0.01) {
            $interest = round(max(0, $totalRepayment - $principal), 2);
        }

        return [
            'pf_number' => (string) ($row->pf_number ?? ''),
            'employee_name' => (string) ($row->employee_name ?? ''),
            'loan_name' => (string) ($row->loan_name ?? ''),
            'loan_code' => (string) ($row->loan_code ?? ''),
            'loan_reference_number' => (string) ($row->loan_reference_number ?? ''),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_repayment' => $totalRepayment,
            'opening_balance' => round((float) ($row->opening_outstanding_balance_amount ?? 0), 2),
            'closing_balance' => round((float) ($row->closing_outstanding_balance_amount ?? 0), 2),
        ];
    }

    private function resolveLoanTypeLabel(?int $loanTypeId): ?string
    {
        if ($loanTypeId === null) {
            return null;
        }

        $loanType = DB::connection('bcmis2')
            ->table('loan_type')
            ->where('loan_type_id', $loanTypeId)
            ->first(['loan_name', 'loan_code']);

        if ($loanType === null) {
            return null;
        }

        $label = trim((string) ($loanType->loan_name ?? ''));
        $code = trim((string) ($loanType->loan_code ?? ''));

        if ($code !== '') {
            return $label !== '' ? "{$label} ({$code})" : $code;
        }

        return $label !== '' ? $label : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function runInfo(PayrollRun $run): array
    {
        $month = (int) ($run->payroll_month);
        $year = (int) ($run->payroll_year);
        $periodLabel = ($month >= 1 && $month <= 12 && $year > 0)
            ? Carbon::create($year, $month, 1)->format('F Y')
            : 'N/A';

        return [
            'payroll_run_id' => (int) $run->id,
            'payroll_number' => (string) ($run->payroll_number ?? ''),
            'payroll_month' => $month,
            'payroll_year' => $year,
            'period_label' => $periodLabel,
            'status' => (string) ($run->status),
        ];
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function renderPdf(string $view, array $viewData, string $orientation = 'P'): string
    {
        $tempDir = storage_path('app/tmp/mpdf');
        if (! file_exists($tempDir)) {
            if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                throw new \Exception("Failed to create temp directory: {$tempDir}");
            }
        }

        $logoPath = public_path('images/nssf-log1.png');
        $coatPath = public_path('images/Tanzania Coat of Arms_.png');
        $logoImageSrc = file_exists($logoPath) ? str_replace('\\', '/', $logoPath) : '';
        $coatImageSrc = file_exists($coatPath) ? str_replace('\\', '/', $coatPath) : '';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => strtoupper($orientation) === 'L' ? 'L' : 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 10,
            'margin_footer' => 10,
            'tempDir' => $tempDir,
        ]);

        if (file_exists($logoPath)) {
            $mpdf->SetWatermarkImage($logoPath, 0.04, [65, 65], 'P');
            $mpdf->showWatermarkImage = true;
        }

        $html = view($view, array_merge($viewData, [
            'logoImageSrc' => $logoImageSrc,
            'coatImageSrc' => $coatImageSrc,
        ]))->render();

        $mpdf->WriteHTML($html);

        return base64_encode($mpdf->Output('', 'S'));
    }

    private function buildFileName(string $prefix, PayrollRun $run): string
    {
        $periodLabel = str_pad((string) ((int) ($run->payroll_month)), 2, '0', STR_PAD_LEFT)
            .'-'.(string) ((int) ($run->payroll_year));
        $fileName = $prefix.'_'.(string) ($run->payroll_number).'_'.$periodLabel.'.pdf';

        return $fileName;
    }

    public function runDocumentKey(PayrollRun $run): string
    {
        $payrollNumber = trim((string) ($run->payroll_number));

        return $payrollNumber;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function externalResourcesForKind(PayrollRun $run, string $kind): array
    {
        $url = $this->documentUrlForKind($kind, $run);
        if ($url === '') {
            return [];
        }

        return [
            [
                'fullUrl' => $url,
                'resourceType' => 'RESOURCE_REFERENCE',
                'requiresAuthentication' => false,
                'title' => $this->documentTitleForKind($kind),
            ],
        ];
    }

    /**
     * Net-pay ERMS batches are per salary bank bucket (CRDB, NBC, NMB, other_banks).
     *
     * @return list<array<string, mixed>>
     */
    public function externalResourcesForNetPayBucket(PayrollRun $run, string $bankBucket): array
    {
        $bankBucket = $this->normalizeNetPayBankBucket($bankBucket);
        if ($bankBucket === null) {
            return [];
        }

        $url = $this->documentUrlForNetPayBucket($run, $bankBucket);
        if ($url === '') {
            return [];
        }

        return [
            [
                'fullUrl' => $url,
                'resourceType' => 'RESOURCE_REFERENCE',
                'requiresAuthentication' => false,
                'title' => $this->documentTitleForNetPayBucket($bankBucket),
            ],
        ];
    }

    public function documentUrlForKind(string $kind, PayrollRun $run): string
    {
        $kind = strtolower(trim($kind));
        $configKey = match ($kind) {
            'jv' => 'payroll_jv_document_url',
            'payee', 'paye' => 'payroll_payee_document_url',
            'psssf' => 'payroll_psssf_document_url',
            'heslb' => 'payroll_heslb_document_url',
            'net_pay', 'netpay', 'net-pay' => 'payroll_net_pay_document_url',
            'minutes' => 'payroll_minutes_document_url',
            default => '',
        };

        if ($configKey === '') {
            return '';
        }

        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];
        $base = rtrim((string) ($payable[$configKey] ?? ''), '/').'/';

        if ($base === '/') {
            return '';
        }

        return $base.$this->runDocumentKey($run);
    }

    public function documentUrlForNetPayBucket(PayrollRun $run, string $bankBucket): string
    {
        $bankBucket = $this->normalizeNetPayBankBucket($bankBucket);
        if ($bankBucket === null) {
            return '';
        }

        $base = $this->documentUrlForKind('net_pay', $run);
        if ($base === '') {
            return '';
        }

        return $base.'?bank='.rawurlencode($bankBucket);
    }

    private function documentTitleForKind(string $kind): string
    {
        return match (strtolower(trim($kind))) {
            'jv' => 'Payroll JV Sheet',
            'payee', 'paye' => 'Payroll PAYE Sheet',
            'psssf' => 'Payroll PSSSF Sheet',
            'heslb' => 'Payroll HESLB Sheet',
            'loans' => 'Staff Loans Report',
            'net_pay', 'netpay', 'net-pay' => 'Payroll Net Pay Sheet',
            'minutes' => 'Payroll Minutes',
            default => 'Payroll supporting document',
        };
    }

    private function documentTitleForNetPayBucket(string $bankBucket): string
    {
        $bankBucket = $this->normalizeNetPayBankBucket($bankBucket);
        if ($bankBucket === null) {
            return 'Payroll Net Pay Sheet';
        }

        return 'Payroll Net Pay Sheet ('.$this->bankResolver->label($bankBucket).')';
    }
}
