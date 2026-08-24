<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Http\Controllers\Controller;
use App\Constants\EmployeeStatus;
use App\Services\Erms\Payroll\PayrollErmsOrchestrationService;
use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollTransaction;
use App\Services\Payroll\PayrollDocumentService;
use App\Services\Payroll\PayrollWorkflowService;
use DomainException;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class PayrollController extends Controller
{
    private function isPaidPayrollRunStatus(?string $status): bool
    {
        return strtoupper(trim((string) $status)) === 'PAID';
    }

    private function generatePayrollNumber(int $month, int $year): string
    {
        $mm = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $yyyy = (string) $year;

        // Format: PAY-MM-YYYY-### (sequential per month/year)
        $prefix = "PAY-{$mm}-{$yyyy}-";

        $latest = DB::connection('bcmis2')
            ->table('payroll_runs')
            ->where('payroll_month', $month)
            ->where('payroll_year', $year)
            ->where('payroll_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('payroll_number');

        $nextSeq = 1;
        if (is_string($latest) && str_starts_with($latest, $prefix)) {
            $suffix = substr($latest, strlen($prefix));
            if (preg_match('/^\d{3}$/', $suffix) === 1) {
                $nextSeq = ((int) $suffix) + 1;
            }
        }

        return $prefix . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Assign a payroll number only when the run does not already have one.
     * Prevents prepare requests from overwriting an existing number (e.g. client-generated sequence on each call).
     */
    private function ensurePayrollNumberAssigned(
        PayrollRun $run,
        int $month,
        int $year,
        Request $request,
    ): void {
        if (trim((string) ($run->payroll_number ?? '')) !== '') {
            return;
        }

        $run->update([
            'payroll_number' => $this->resolvePayrollNumberForRun($run, $month, $year, $request),
        ]);
        $run->refresh();
    }

    private function resolvePayrollNumberForRun(
        ?PayrollRun $run,
        int $month,
        int $year,
        Request $request,
    ): string {
        $incoming = trim((string) $request->input('payroll_number', ''));
        if ($incoming !== '') {
            return $incoming;
        }

        if ($run !== null && trim((string) ($run->payroll_number ?? '')) !== '') {
            return (string) $run->payroll_number;
        }

        return $this->generatePayrollNumber($month, $year);
    }

    public function ListPayrollRuns(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search'); // payroll_number
            $status = $request->get('status');
            $payrollMonth = $request->get('payroll_month');
            $payrollYear = $request->get('payroll_year');

            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = (int) $request->get('per_page', 15);

            $query = PayrollRun::query()
                ->leftJoin('bcmis.auth_user as prepared_user', 'prepared_user.pf_number', '=', 'payroll_runs.prepared_by')
                ->leftJoin('bcmis.auth_user as initiated_user', 'initiated_user.pf_number', '=', 'payroll_runs.initiated_by')
                ->leftJoin('bcmis.auth_user as examined_user', 'examined_user.pf_number', '=', 'payroll_runs.examined_by')
                ->leftJoin('bcmis.auth_user as verified_user', 'verified_user.pf_number', '=', 'payroll_runs.verified_by')
                ->leftJoin('bcmis.auth_user as approved_user', 'approved_user.pf_number', '=', 'payroll_runs.approved_by')
                ->select([
                    'payroll_runs.*',
                    DB::raw("NULLIF(TRIM(CONCAT(prepared_user.first_name, ' ', prepared_user.surname)), '') as prepared_by_name"),
                    DB::raw("NULLIF(TRIM(CONCAT(initiated_user.first_name, ' ', initiated_user.surname)), '') as initiated_by_name"),
                    DB::raw("NULLIF(TRIM(CONCAT(examined_user.first_name, ' ', examined_user.surname)), '') as examined_by_name"),
                    DB::raw("NULLIF(TRIM(CONCAT(verified_user.first_name, ' ', verified_user.surname)), '') as verified_by_name"),
                    DB::raw("NULLIF(TRIM(CONCAT(approved_user.first_name, ' ', approved_user.surname)), '') as approved_by_name"),
                ]);

            if (! empty($search)) {
                $query->where('payroll_number', 'like', '%' . trim($search) . '%');
            }

            if ($status !== null && $status !== '') {
                $query->where('status', $status);
            }

            if ($payrollMonth !== null && $payrollMonth !== '') {
                $query->where('payroll_month', (int) $payrollMonth);
            }

            if ($payrollYear !== null && $payrollYear !== '') {
                $query->where('payroll_year', (int) $payrollYear);
            }

            $runs = $query
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Payroll runs retrieved successfully',
                'data' => [
                    $runs->items(),
                    'pagination' => [
                        'current_page' => $runs->currentPage(),
                        'last_page' => $runs->lastPage(),
                        'per_page' => $runs->perPage(),
                        'total' => $runs->total(),
                        'from' => $runs->firstItem(),
                        'to' => $runs->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll runs: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showPayrollRun(int $id): JsonResponse
    {
        try {
            $payload = $this->loadPayrollRunWithActorNames($id);

            if ($payload === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            $run = PayrollRun::query()->find($id);
            $ermsExecutions = app(PayrollErmsOrchestrationService::class)->executionStatusForRun($run);

            return response()->json([
                'success' => true,
                'message' => 'Payroll run retrieved successfully',
                'data' => [
                    'run' => $payload['run'],
                    'history' => $payload['history'],
                    'erms' => $ermsExecutions,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve payroll run', [
                'payroll_run_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll run: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getPayrollTransactions(Request $request, int $id)
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            if ($perPage <= 0) {
                $perPage = 15;
            }
            if ($perPage > 200) {
                $perPage = 200;
            }

            $run = PayrollRun::query()->find($id);

            if (! $run) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            // Use preview table for all non-posted statuses (draft/prepared/initiated/verified/examined/approved).
            $txTable = ($run->status === 'posted')
                ? 'payroll_transaction'
                : 'payroll_transaction_preview';

            $baseQuery = DB::connection('bcmis2')->table($txTable . ' as pt')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'pt.national_id')
                ->leftJoin('bcmis2.bank as b', 'b.bank_id', '=', 'pt.bank_id')
                ->where('b.is_active', true)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->where('pt.payroll_run_id', $id)
                ->select([
                    'pt.*',
                    'be.national_id',
                    'be.fname',
                    'be.mname',
                    'be.sname',
                    'b.bank_name',
                    'b.bank_code',
                    DB::raw("CONCAT(be.fname, ' ', be.mname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(b.bank_name, ' ', b.bank_code) AS bank_display_name"),
                ])
                ->orderByDesc('pt.id');

            // Optional document mode (mirrors overtime/payslip behavior).
            // - `format=download` -> inline PDF response
            // - `format=base64`   -> JSON with base64 content
            // - if `document=1` without format -> base64 for API clients, download for browsers
            $wantsDocument = $request->boolean('document') || $request->has('format');
            if ($wantsDocument) {
                $format = $request->input('format');
                if (! $format) {
                    $format = ($request->wantsJson() || $request->expectsJson()) ? 'base64' : 'download';
                }

                $rows = $baseQuery->get();

                // Ensure temp directory exists for mPDF cache.
                $tempDir = storage_path('app/tmp/mpdf');
                if (! file_exists($tempDir)) {
                    if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                        throw new \Exception("Failed to create temp directory: {$tempDir}");
                    }
                }
                if (! is_writable($tempDir)) {
                    @chmod($tempDir, 0777);
                }
                if (! is_writable($tempDir)) {
                    throw new \Exception("mPDF temp directory is not writable: {$tempDir}");
                }

                $mpdf = new Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'L',
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 10,
                    'margin_header' => 8,
                    'margin_footer' => 8,
                    'tempDir' => $tempDir,
                ]);

                $logoPath = public_path('images/nssf-log1.png');
                $coatPath = public_path('images/Tanzania Coat of Arms_.png');
                $logoImageSrc = file_exists($logoPath) ? str_replace('\\', '/', $logoPath) : '';
                $coatImageSrc = file_exists($coatPath) ? str_replace('\\', '/', $coatPath) : '';

                $periodLabel = str_pad((string) ((int) ($run->payroll_month ?? 0)), 2, '0', STR_PAD_LEFT)
                    . '-' . (string) ((int) ($run->payroll_year ?? 0));
                $fileName = 'Payroll_Transactions_' . (string) ($run->payroll_number ?? ('RUN_' . $id)) . '_' . $periodLabel . '.pdf';
                $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', (string) $fileName) ?: 'Payroll_Transactions.pdf';

                $authUser = auth()->user();
                $generatedBy = trim((string) ($authUser?->first_name ?? '') . ' ' . ($authUser?->surname ?? ''));
                $html = view('payroll.documents.payroll_transactions_document', [
                    'payroll_run' => [
                        'id' => (int) $run->id,
                        'payroll_number' => (string) ($run->payroll_number ?? ''),
                        'payroll_month' => (int) ($run->payroll_month ?? 0),
                        'payroll_year' => (int) ($run->payroll_year ?? 0),
                        'status' => (string) ($run->status ?? ''),
                    ],
                    'transactions' => $rows,
                    'logoImageSrc' => $logoImageSrc,
                    'coatImageSrc' => $coatImageSrc,
                    'generatedBy' => $generatedBy,
                ])->render();

                $mpdf->WriteHTML($html);
                $pdfOutput = $mpdf->Output('', 'S');

                if ($format === 'download') {
                    return response($pdfOutput, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payroll transactions PDF generated successfully',
                    'data' => [
                        'pdf_base64' => base64_encode($pdfOutput),
                        'file_name' => $fileName,
                        'payroll_run_id' => (int) $run->id,
                    ],
                ]);
            }

            $transactions = $baseQuery->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Payroll transactions retrieved successfully',
                'data' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll transactions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve an employee's paid payslip by PF number.
     *
     * Query params (optional):
     * - payroll_run_id: int (fetch specific run)
     * - month: YYYY-MM (frontend month picker value)
     * - payroll_month: int (1-12)
     * - payroll_year: int
     */
    public function getPayslip(Request $request): JsonResponse
    {
        try {

            $pfNumber = $request->input('pf_number');

            $validator = Validator::make(
                $request->all(),
                [
                    'pf_number' => 'required|string|max:50',
                    'payroll_run_id' => 'nullable|integer|min:1',
                    'month' => 'nullable|date_format:Y-m',
                    'payroll_month' => 'nullable|integer|min:1|max:12',
                    'payroll_year' => 'nullable|integer|min:2000|max:2500',
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $conn = DB::connection('bcmis2');

            $txQuery = $conn->table('payroll_transaction as pt')
                ->join('payroll_runs as pr', 'pr.id', '=', 'pt.payroll_run_id')
                ->leftJoin('bridge_employee as be', 'be.national_id', '=', 'pt.national_id')
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->leftJoin('bank as b', 'b.bank_id', '=', 'pt.bank_id')
                ->where('b.is_active', true)
                ->where('pt.pf_number', $pfNumber);
                // ->where(function ($q) use ($paidStatuses) {
                //     $q->whereIn(DB::raw('UPPER(pr.status)'), $paidStatuses)
                //         ->orWhere('pt.erms_status', 1);
                // });

            if ($request->filled('payroll_run_id')) {
                $txQuery->where('pt.payroll_run_id', (int) $request->input('payroll_run_id'));
            } else {
                if ($request->filled('month')) {
                    $monthStr = (string) $request->input('month');
                    [$y, $m] = array_pad(explode('-', $monthStr, 2), 2, null);
                    $y = is_string($y) ? (int) $y : null;
                    $m = is_string($m) ? (int) $m : null;
                    if ($y && $m) {
                        $txQuery->where('pt.payroll_year', $y);
                        $txQuery->where('pt.payroll_month', $m);
                    }
                } else {
                if ($request->filled('payroll_month')) {
                    $txQuery->where('pt.payroll_month', (int) $request->input('payroll_month'));
                }
                if ($request->filled('payroll_year')) {
                    $txQuery->where('pt.payroll_year', (int) $request->input('payroll_year'));
                }
                }
            }

            $tx = $txQuery
                ->orderByDesc('pt.payroll_year')
                ->orderByDesc('pt.payroll_month')
                ->orderByDesc('pt.id')
                ->select([
                    'pt.*',
                    'pr.status as payroll_run_status',
                    'pr.erms_status as payroll_run_erms_status',
                    'pr.erms_reference as payroll_run_erms_reference',
                    'pr.erms_submitted_at as payroll_run_erms_submitted_at',
                    'be.fname',
                    'be.mname',
                    'be.sname',
                    'be.pfno',
                    'be.gender',
                    'be.mobile',
                    'be.email',
                    'be.department_id as employee_department_id',
                    'be.scheme_id as employee_scheme_id',
                    'b.bank_name',
                    'b.bank_code',
                    DB::raw("CONCAT(be.fname, ' ', be.mname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(b.bank_name, ' ', b.bank_code) AS bank_display_name"),
                ])
                ->first();

            if (! $tx) {
                return response()->json([
                    'success' => false,
                    'message' => 'No paid payslip found for the provided PF number.',
                ], 404);
            }

            $items = $conn->table('employee_payroll_items as i')
                ->leftJoin('benefit_type as bt', function ($join) {
                    $join->on('bt.benefit_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'benefit_type');
                })
                ->leftJoin('arrears_reasons as ar', function ($join) {
                    $join->on('ar.arrears_reason_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'arrears_reason');
                })
                ->leftJoin('deduction_type as dt', function ($join) {
                    $join->on('dt.deduction_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'deduction_type');
                })
                ->leftJoin('loan_type as lt', function ($join) {
                    $join->on('lt.loan_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'loan_type');
                })
                ->where('i.payroll_run_id', (int) $tx->payroll_run_id)
                ->where('i.national_id', (string) $tx->national_id)
                ->where('i.is_void', false)
                ->orderBy('i.item_type')
                ->orderBy('i.name')
                ->select([
                    'i.id',
                    'i.item_type',
                    DB::raw("
                        COALESCE(
                            NULLIF(i.name, ''),
                            bt.benefit_name,
                            ar.reason_name,
                            dt.deduction_name,
                            lt.loan_name,
                            ''
                        ) as display_name
                    "),
                    'i.amount',
                    'i.employee_amount',
                    'i.employer_amount',
                ])
                ->get();

            $grouped = [
                'benefit' => [],
                'arrears' => [],
                'deduction' => [],
                'loan' => [],
            ];

            foreach ($items as $it) {
                $t = strtolower((string) ($it->item_type ?? ''));
                if (! array_key_exists($t, $grouped)) {
                    $t = 'other';
                }
                $grouped[$t][] = $it;
            }

            // PAYE is stored on the transaction; include it inside deductions for display.
            $payeAmount = (float) ($tx->paye ?? 0);
            if ($payeAmount != 0.0) {
                $payeRow = new \stdClass();
                $payeRow->id = null;
                $payeRow->item_type = 'deduction';
                $payeRow->display_name = 'PAYE';
                $payeRow->amount = $payeAmount;
                $payeRow->employee_amount = $payeAmount;
                $payeRow->employer_amount = null;
                $grouped['deduction'][] = $payeRow;
            }

            $paidFlag = $this->isPaidPayrollRunStatus((string) ($tx->payroll_run_status ?? ''))
                || ((int) ($tx->erms_status ?? 0) === 1);

            return response()->json([
                'success' => true,
                'message' => 'Payslip retrieved successfully',
                'data' => [
                    'paid' => $paidFlag,
                    'employee' => [
                        'pf_number' => (string) ($tx->pf_number ?? ''),
                        'national_id' => (string) ($tx->national_id ?? ''),
                        'name' => (string) ($tx->employee_name ?? ''),
                        'gender' => $tx->gender ?? null,
                        'mobile' => $tx->mobile ?? null,
                        'email' => $tx->email ?? null,
                    ],
                    'payroll_run' => [
                        'payroll_run_id' => (int) ($tx->payroll_run_id ?? 0),
                        'payroll_number' => (string) ($tx->payroll_number ?? ''),
                        'payroll_month' => (int) ($tx->payroll_month ?? 0),
                        'payroll_year' => (int) ($tx->payroll_year ?? 0),
                        'status' => (string) ($tx->payroll_run_status ?? ''),
                        'erms_status' => (int) ($tx->payroll_run_erms_status ?? 0),
                        'erms_reference' => $tx->payroll_run_erms_reference ?? null,
                        'erms_submitted_at' => $tx->payroll_run_erms_submitted_at ?? null,
                    ],
                    'transaction' => [
                        'id' => (int) ($tx->id ?? 0),
                        'bank_id' => $tx->bank_id ?? null,
                        'bank_name' => $tx->bank_name ?? null,
                        'bank_code' => $tx->bank_code ?? null,
                        'bank_display_name' => $tx->bank_display_name ?? null,
                        'account_number' => $tx->account_number ?? null,
                        'department_id' => $tx->department_id ?? null,
                        'scheme_id' => $tx->scheme_id ?? null,
                        'basic_salary' => (float) ($tx->basic_salary ?? 0),
                        'total_arrears' => (float) ($tx->total_arrears ?? 0),
                        'total_benefits' => (float) ($tx->total_benefits ?? 0),
                        'total_deductions' => (float) ($tx->total_deductions ?? 0),
                        'total_loans' => (float) ($tx->total_loans ?? 0),
                        'gross_pay' => (float) ($tx->gross_pay ?? 0),
                        'taxable_pay' => (float) ($tx->taxable_pay ?? 0),
                        'paye' => (float) ($tx->paye ?? 0),
                        'net_pay' => (float) ($tx->net_pay ?? 0),
                        'erms_status' => (int) ($tx->erms_status ?? 0),
                        'erms_submitted_at' => $tx->erms_submitted_at ?? null,
                        'erms_reference' => $tx->erms_reference ?? null,
                    ],
                    'items' => $grouped,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payslip: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate Payslip PDF (mPDF) for a paid employee by PF number + period.
     *
     * Request params:
     * - pf_number: string (required)
     * - month: YYYY-MM (optional)
     * - payroll_month: int (optional)
     * - payroll_year: int (optional)
     * - payroll_run_id: int (optional)
     * - format: base64|download (optional; defaults based on Accept header)
     */
    public function getPayslipPdf(Request $request)
    {
        try {
            $format = $request->input('format');
            if (! $format) {
                $format = ($request->wantsJson() || $request->expectsJson()) ? 'base64' : 'download';
            }

            $validator = Validator::make($request->all(), [
                'pf_number' => 'required|string|max:50',
                'payroll_run_id' => 'nullable|integer|min:1',
                'month' => 'nullable|date_format:Y-m',
                'payroll_month' => 'nullable|integer|min:1|max:12',
                'payroll_year' => 'nullable|integer|min:2000|max:2500',
                'format' => 'nullable|in:base64,download',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $pfNumber = (string) $request->input('pf_number');

            $conn = DB::connection('bcmis2');

            $txQuery = $conn->table('payroll_transaction as pt')
                ->join('payroll_runs as pr', 'pr.id', '=', 'pt.payroll_run_id')
                ->leftJoin('bridge_employee as be', 'be.national_id', '=', 'pt.national_id')
                ->leftJoin('bank as b', 'b.bank_id', '=', 'pt.bank_id')
                ->where('b.is_active', true)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->where('pt.pf_number', $pfNumber);
                // ->where(function ($q) use ($paidStatuses) {
                //     $q->whereIn(DB::raw('UPPER(pr.status)'), $paidStatuses)
                //         ->orWhere('pt.erms_status', 1);
                // });

            if ($request->filled('payroll_run_id')) {
                $txQuery->where('pt.payroll_run_id', (int) $request->input('payroll_run_id'));
            } else {
                if ($request->filled('month')) {
                    $monthStr = (string) $request->input('month');
                    [$y, $m] = array_pad(explode('-', $monthStr, 2), 2, null);
                    $y = is_string($y) ? (int) $y : null;
                    $m = is_string($m) ? (int) $m : null;
                    if ($y && $m) {
                        $txQuery->where('pt.payroll_year', $y);
                        $txQuery->where('pt.payroll_month', $m);
                    }
                } else {
                    if ($request->filled('payroll_month')) {
                        $txQuery->where('pt.payroll_month', (int) $request->input('payroll_month'));
                    }
                    if ($request->filled('payroll_year')) {
                        $txQuery->where('pt.payroll_year', (int) $request->input('payroll_year'));
                    }
                }
            }

            $tx = $txQuery
                ->orderByDesc('pt.payroll_year')
                ->orderByDesc('pt.payroll_month')
                ->orderByDesc('pt.id')
                ->select([
                    'pt.*',
                    'pr.status as payroll_run_status',
                    'pr.erms_status as payroll_run_erms_status',
                    'pr.erms_reference as payroll_run_erms_reference',
                    'pr.erms_submitted_at as payroll_run_erms_submitted_at',
                    'be.fname',
                    'be.mname',
                    'be.sname',
                    'be.pfno',
                    'be.gender',
                    'be.mobile',
                    'be.email',
                    'be.department_id as employee_department_id',
                    'be.scheme_id as employee_scheme_id',
                    'b.bank_name',
                    'b.bank_code',
                    DB::raw("CONCAT(be.fname, ' ', be.mname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(b.bank_name, ' ', b.bank_code) AS bank_display_name"),
                ])
                ->first();

            if (! $tx) {
                return response()->json([
                    'success' => false,
                    'message' => 'No paid payslip found for the provided PF number.',
                ], 404);
            }

            $items = $conn->table('employee_payroll_items as i')
                ->leftJoin('benefit_type as bt', function ($join) {
                    $join->on('bt.benefit_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'benefit_type');
                })
                ->leftJoin('arrears_reasons as ar', function ($join) {
                    $join->on('ar.arrears_reason_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'arrears_reason');
                })
                ->leftJoin('deduction_type as dt', function ($join) {
                    $join->on('dt.deduction_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'deduction_type');
                })
                ->leftJoin('loan_type as lt', function ($join) {
                    $join->on('lt.loan_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'loan_type');
                })
                ->where('i.payroll_run_id', (int) $tx->payroll_run_id)
                ->where('i.national_id', (string) $tx->national_id)
                ->where('i.is_void', false)
                ->orderBy('i.item_type')
                ->orderBy('i.name')
                ->select([
                    'i.id',
                    'i.item_type',
                    DB::raw("
                        COALESCE(
                            NULLIF(i.name, ''),
                            bt.benefit_name,
                            ar.reason_name,
                            dt.deduction_name,
                            lt.loan_name,
                            ''
                        ) as display_name
                    "),
                    'i.amount',
                    'i.employee_amount',
                    'i.employer_amount',
                ])
                ->get();

            $grouped = [
                'benefit' => [],
                'arrears' => [],
                'deduction' => [],
                'loan' => [],
                'other' => [],
            ];

            foreach ($items as $it) {
                $t = strtolower((string) ($it->item_type ?? ''));
                if (! array_key_exists($t, $grouped)) {
                    $t = 'other';
                }
                $grouped[$t][] = $it;
            }

            $paidFlag = $this->isPaidPayrollRunStatus((string) ($tx->payroll_run_status ?? ''))
                || ((int) ($tx->erms_status ?? 0) === 1);

            // PAYE is stored on the transaction; include it inside deductions for PDF display.
            $payeAmount = (float) ($tx->paye ?? 0);
            if ($payeAmount != 0.0) {
                $payeRow = new \stdClass();
                $payeRow->id = null;
                $payeRow->item_type = 'deduction';
                $payeRow->display_name = 'PAYE';
                $payeRow->amount = $payeAmount;
                $payeRow->employee_amount = $payeAmount;
                $payeRow->employer_amount = null;
                $grouped['deduction'][] = $payeRow;
            }

            // Ensure temp directory exists for mPDF cache.
            $tempDir = storage_path('app/tmp/mpdf');
            if (! file_exists($tempDir)) {
                if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                    throw new \Exception("Failed to create temp directory: {$tempDir}");
                }
            }
            if (! is_writable($tempDir)) {
                @chmod($tempDir, 0777);
            }
            if (! is_writable($tempDir)) {
                throw new \Exception("mPDF temp directory is not writable: {$tempDir}");
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 12,
                'margin_header' => 8,
                'margin_footer' => 8,
                'tempDir' => $tempDir,
            ]);

            $logoPath = public_path('images/nssf-log1.png');
            $coatPath = public_path('images/Tanzania Coat of Arms_.png');
            $logoImageSrc = file_exists($logoPath) ? str_replace('\\', '/', $logoPath) : '';
            $coatImageSrc = file_exists($coatPath) ? str_replace('\\', '/', $coatPath) : '';

            $periodLabel = str_pad((string) ((int) ($tx->payroll_month ?? 0)), 2, '0', STR_PAD_LEFT)
                . '-' . (string) ($tx->payroll_year ?? '');
            $fileName = 'Payslip_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $pfNumber) . '_' . $periodLabel . '.pdf';

            $authUser = auth()->user();
            $generatedBy = trim((string) ($authUser?->first_name ?? '') . ' ' . ($authUser?->surname ?? ''));

            $html = view('payroll.documents.payslip_document', [
                'paid' => $paidFlag,
                'employee' => [
                    'pf_number' => (string) ($tx->pf_number ?? ''),
                    'national_id' => (string) ($tx->national_id ?? ''),
                    'name' => (string) ($tx->employee_name ?? ''),
                    'gender' => $tx->gender ?? null,
                    'mobile' => $tx->mobile ?? null,
                    'email' => $tx->email ?? null,
                ],
                'payroll_run' => [
                    'payroll_number' => (string) ($tx->payroll_number ?? ''),
                    'payroll_month' => (int) ($tx->payroll_month ?? 0),
                    'payroll_year' => (int) ($tx->payroll_year ?? 0),
                    'status' => (string) ($tx->payroll_run_status ?? ''),
                ],
                'transaction' => [
                    'bank_display_name' => $tx->bank_display_name ?? null,
                    'account_number' => $tx->account_number ?? null,
                    'basic_salary' => (float) ($tx->basic_salary ?? 0),
                    'total_benefits' => (float) ($tx->total_benefits ?? 0),
                    'total_arrears' => (float) ($tx->total_arrears ?? 0),
                    'total_deductions' => (float) ($tx->total_deductions ?? 0),
                    'psssf_contribution' => (float) ($tx->psssf_contribution ?? 0),
                    'psssf_employer_contribution' => (float) ($tx->psssf_employer_contribution ?? 0),
                    'total_loans' => (float) ($tx->total_loans ?? 0),
                    'gross_pay' => (float) ($tx->gross_pay ?? 0),
                    'taxable_pay' => (float) ($tx->taxable_pay ?? 0),
                    'paye' => (float) ($tx->paye ?? 0),
                    'net_pay' => (float) ($tx->net_pay ?? 0),
                ],
                'items' => $grouped,
                'logoImageSrc' => $logoImageSrc,
                'coatImageSrc' => $coatImageSrc,
                'generatedBy' => $generatedBy,
            ])->render();

            $mpdf->WriteHTML($html);
            $pdfOutput = $mpdf->Output('', 'S');

            if ($format === 'download') {
                return response($pdfOutput, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payslip PDF generated successfully',
                'data' => [
                    'pdf_base64' => base64_encode($pdfOutput),
                    'file_name' => $fileName,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate payslip PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payslip PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Workflow: draft -> prepared -> initiated -> examined -> verified -> approved -> posted
     */
    public function updateWorkflow(Request $request, int $id, PayrollWorkflowService $workflow): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:prepare,initiate,examine,verify,approve,return,reject,process',
                'comment' => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $run = PayrollRun::query()->find($id);

            if (! $run) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            $action = $request->input('action');
            $userId = (string) (auth()->user()->pf_number ?? auth()->id() ?? '');

            if ($userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine user identifier',
                ], 401);
            }

            if ($action === 'process') {
                $count = $workflow->process($run, $userId);

                return response()->json([
                    'success' => true,
                    'message' => 'Payroll processed successfully',
                    'data' => [
                        'count' => $count,
                        'run' => $run->fresh(),
                    ],
                ]);
            }

            $comment = $request->input('comment');

            $updated = match ($action) {
                'prepare' => $workflow->prepare($run, $userId, $comment),
                'initiate' => $workflow->initiate($run, $userId, $comment),
                'examine' => $workflow->examine($run, $userId, $comment),
                'verify' => $workflow->verify($run, $userId, $comment),
                'approve' => $workflow->approve($run, $userId, $comment),
                'return' => $workflow->returnForCorrection($run, $userId, $comment),
                'reject' => $workflow->reject($run, $userId, $comment),
                default => throw new DomainException('Unknown workflow action.'),
            };

            $payload = $this->loadPayrollRunWithActorNames((int) $updated->id);

            return response()->json([
                'success' => true,
                'message' => 'Workflow updated successfully',
                'data' => $payload['run'] ?? $updated,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    public function preparePayrollRun(Request $request, PayrollWorkflowService $workflow): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'payroll_month' => 'nullable|integer|min:1|max:12',
                'payroll_year' => 'nullable|integer|min:2000|max:2500',
                'payroll_number' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = (string) (auth()->user()->pf_number ?? auth()->id() ?? '');

            if ($userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine user identifier',
                ], 401);
            }

            $period = now();
            $month = (int) ($request->input('payroll_month') ?? $period->month);
            $year = (int) ($request->input('payroll_year') ?? $period->year);

            $run = PayrollRun::query()
                ->where('payroll_month', $month)
                ->where('payroll_year', $year)
                ->first();

            if (! $run) {
                $run = PayrollRun::query()->create([
                    'payroll_month' => $month,
                    'payroll_year' => $year,
                    'payroll_number' => $this->resolvePayrollNumberForRun($run, $month, $year, $request),
                ]);
            } else {
                $this->ensurePayrollNumberAssigned($run, $month, $year, $request);
            }

            $run = $workflow->prepare($run, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Payroll prepared successfully',
                'data' => $run,
            ]);

        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare payroll: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getPayrollJournal(int $id): JsonResponse
    {
        try {
            $run = PayrollRun::query()->find($id);

            if (! $run) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            $conn = DB::connection('bcmis2');

            // Match getPayrollTransactions: preview until the run is posted.
            $useFinal = strtolower((string) ($run->status ?? '')) === 'posted';
            $txTable = $useFinal ? 'payroll_transaction' : 'payroll_transaction_preview';

            $txTotals = $conn->table($txTable . ' as t')
                ->where('t.payroll_run_id', $id)
                ->selectRaw('
                    COALESCE(SUM(t.basic_salary), 0) as basic_salary,
                    COALESCE(SUM(t.total_arrears), 0) as total_arrears,
                    COALESCE(SUM(t.paye), 0) as paye,
                    COALESCE(SUM(t.net_pay), 0) as net_pay,
                    COALESCE(SUM(t.total_loans), 0) as total_loans
                ')
                ->first();

            $benefits = $conn->table('employee_payroll_items as i')
                ->join($txTable . ' as t', function ($join) {
                    $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                        ->on('t.national_id', '=', 'i.national_id');
                })
                ->leftJoin('benefit_type as bt', function ($join) {
                    $join->on('bt.benefit_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'benefit_type');
                })
                ->where('i.payroll_run_id', $id)
                ->where('i.is_void', false)
                ->where('i.item_type', 'benefit')
                ->groupBy('i.type_id', 'display_name')
                ->orderBy('display_name')
                ->select([
                    'i.type_id',
                    DB::raw("COALESCE(NULLIF(i.name, ''), bt.benefit_name, '') as display_name"),
                    DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
                ])
                ->get();

            $arrears = $conn->table('employee_payroll_items as i')
                ->join($txTable . ' as t', function ($join) {
                    $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                        ->on('t.national_id', '=', 'i.national_id');
                })
                ->leftJoin('arrears_reasons as ar', function ($join) {
                    $join->on('ar.arrears_reason_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'arrears_reason');
                })
                ->where('i.payroll_run_id', $id)
                ->where('i.is_void', false)
                ->where('i.item_type', 'arrears')
                ->groupBy('i.type_id', 'display_name')
                ->orderBy('display_name')
                ->select([
                    'i.type_id',
                    DB::raw("COALESCE(NULLIF(i.name, ''), ar.reason_name, '') as display_name"),
                    DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
                ])
                ->get();

            $deductions = $conn->table('employee_payroll_items as i')
                ->join($txTable . ' as t', function ($join) {
                    $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                        ->on('t.national_id', '=', 'i.national_id');
                })
                ->leftJoin('deduction_type as dt', function ($join) {
                    $join->on('dt.deduction_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'deduction_type');
                })
                ->where('i.payroll_run_id', $id)
                ->where('i.is_void', false)
                ->where('i.item_type', 'deduction')
                ->groupBy('i.type_id', 'display_name')
                ->orderBy('display_name')
                ->select([
                    'i.type_id',
                    DB::raw("COALESCE(NULLIF(i.name, ''), dt.deduction_name, '') as display_name"),
                    DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
                ])
                ->get();

            $loans = $conn->table('employee_payroll_items as i')
                ->join($txTable . ' as t', function ($join) {
                    $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                        ->on('t.national_id', '=', 'i.national_id');
                })
                ->leftJoin('loan_type as lt', function ($join) {
                    $join->on('lt.loan_type_id', '=', 'i.type_id')
                        ->where('i.type_table', '=', 'loan_type');
                })
                ->where('i.payroll_run_id', $id)
                ->where('i.is_void', false)
                ->where('i.item_type', 'loan')
                ->groupBy('i.type_id', 'display_name')
                ->orderBy('display_name')
                ->select([
                    'i.type_id',
                    DB::raw("COALESCE(NULLIF(i.name, ''), lt.loan_name, 'loan') as display_name"),
                    DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
                ])
                ->get();

            $lines = [];
            $sn = 1;

            $basic = (float) ($txTotals->basic_salary);
            $totalArrears = (float) ($txTotals->total_arrears);
            $paye = (float) ($txTotals->paye);
            $net = (float) ($txTotals->net_pay);

            if ($basic != 0.0) {
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'basic',
                    'component' => 'Basic Salary',
                    'type_id' => null,
                    'debit' => round($basic, 2),
                    'credit' => 0.0,
                    'shortage' => 0.0,
                ];
            }

            foreach ($benefits as $b) {
                $amt = (float) ($b->amount ?? 0);
                if ($amt == 0.0) {
                    continue;
                }
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'benefit',
                    'component' => (string) ($b->display_name ?? ''),
                    'type_id' => $b->type_id,
                    'debit' => round($amt, 2),
                    'credit' => 0.0,
                    'shortage' => 0.0,
                ];
            }

            // Arrears are part of gross/net computation; include them on debit side.
            foreach ($arrears as $a) {
                $amt = (float) ($a->amount ?? 0);
                if ($amt == 0.0) {
                    continue;
                }
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'arrears',
                    'component' => (string) ($a->display_name ?? ''),
                    'type_id' => $a->type_id,
                    'debit' => round($amt, 2),
                    'credit' => 0.0,
                    'shortage' => 0.0,
                ];
            }

            if ($paye != 0.0) {
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'paye',
                    'component' => 'PAYE',
                    'type_id' => null,
                    'debit' => 0.0,
                    'credit' => round($paye, 2),
                    'shortage' => 0.0,
                ];
            }

            foreach ($deductions as $d) {
                $amt = (float) ($d->amount ?? 0);
                if ($amt == 0.0) {
                    continue;
                }
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'deduction',
                    'component' => (string) ($d->display_name ?? ''),
                    'type_id' => $d->type_id,
                    'debit' => 0.0,
                    'credit' => round($amt, 2),
                    'shortage' => 0.0,
                ];
            }

            foreach ($loans as $l) {
                $amt = (float) ($l->amount ?? 0);
                if ($amt == 0.0) {
                    continue;
                }
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'loan',
                    'component' => (string) ($l->display_name ?? ''),
                    'type_id' => $l->type_id,
                    'debit' => 0.0,
                    'credit' => round($amt, 2),
                    'shortage' => 0.0,
                ];
            }

            $totalLoans = (float) ($txTotals->total_loans ?? 0);
            $loansCredit = round(array_sum(array_map(
                fn ($l) => (float) ($l['credit'] ?? 0),
                array_filter($lines, fn ($l) => ($l['kind'] ?? '') === 'loan')
            )), 2);

            if ($totalLoans > 0) {
                $remainder = round($totalLoans - $loansCredit, 2);
                if ($remainder >= 0.01) {
                    $lines[] = [
                        'sn' => $sn++,
                        'kind' => 'loan',
                        'component' => 'Loans',
                        'type_id' => null,
                        'debit' => 0.0,
                        'credit' => $remainder,
                        'shortage' => 0.0,
                    ];
                }
            }

            if ($net != 0.0) {
                $lines[] = [
                    'sn' => $sn++,
                    'kind' => 'net_pay',
                    'component' => 'Net Pay',
                    'type_id' => null,
                    'debit' => 0.0,
                    'credit' => round($net, 2),
                    'shortage' => 0.0,
                ];
            }

            $totalDebit = round(array_sum(array_map(fn ($l) => (float) $l['debit'], $lines)), 2);
            $totalCredit = round(array_sum(array_map(fn ($l) => (float) $l['credit'], $lines)), 2);
            $shortage = round($totalDebit - $totalCredit, 2);

            $hasLines = count($lines) > 0;
            $journalStatus = ! $hasLines
                ? 'pending'
                : (abs($shortage) < 0.01 ? 'balanced' : 'unbalanced');

            return response()->json([
                'success' => true,
                'message' => 'Payroll journal retrieved successfully',
                'data' => [
                    'source' => $useFinal ? 'final' : 'preview',
                    'journal_status' => $journalStatus,
                    'shortage' => $shortage,
                    'totals' => [
                        'debit' => $totalDebit,
                        'credit' => $totalCredit,
                    ],
                    'lines' => $lines,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll journal: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listErmsExecutions(int $id, PayrollErmsOrchestrationService $orchestration): JsonResponse
    {
        try {
            $run = PayrollRun::query()->find($id);

            if (! $run) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll ERMS executions retrieved successfully',
                'data' => $orchestration->executionStatusForRun($run),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ERMS executions: '.$e->getMessage(),
            ], 500);
        }
    }

    public function repostFailedErmsExecutions(int $id, PayrollErmsOrchestrationService $orchestration): JsonResponse
    {
        try {
            $run = PayrollRun::query()->find($id);

            if (! $run) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            $result = $orchestration->retryFailedExecutions($run);

            $httpStatus = ($result['ok'] ?? false) ? 200 : 422;

            return response()->json([
                'success' => (bool) ($result['ok'] ?? false),
                'message' => ($result['ok'] ?? false)
                    ? 'Failed ERMS batches reposted successfully.'
                    : 'ERMS repost completed with one or more failures.',
                'data' => $result,
            ], $httpStatus);
        } catch (DomainException|InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to repost ERMS batches: '.$e->getMessage(),
            ], 500);
        }
    }

    public function processPayrollRun(int $id, PayrollWorkflowService $workflow): JsonResponse
    {
        try {
            $run = PayrollRun::query()->find($id);

            if (! $run) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run not found',
                ], 404);
            }

            $userId = (string) (auth()->user()->pf_number ?? auth()->id() ?? '');

            if ($userId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine user identifier',
                ], 401);
            }

            $count = $workflow->process($run, $userId);

            $run = $run->fresh() ?? $run;

            $erms = app(PayrollErmsOrchestrationService::class)->submitPostedRun($run);

            $message = $erms['ok']
                ? 'Payroll processed and submitted to ERMS successfully.'
                : 'Payroll processed; ERMS submission completed with one or more failures.';

            if (! ($erms['misc']['ok'] ?? false)) {
                $message = 'Payroll processed; ERMS miscellaneous submission failed — payables were not sent.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'transactions_created' => $count,
                    'run' => $run->fresh(),
                    'erms' => $erms,
                ],
                'erms_ok' => (bool) ($erms['ok'] ?? false),
                'erms_reference' => $erms['erms_reference'] ?? null,
            ]);

        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payroll: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function getPayrollSummary(Request $request): JsonResponse
    {
        try {
            $conn = DB::connection('bcmis2');

            // Pick latest two runs automatically
            $runs = PayrollRun::query()
                ->orderByDesc('payroll_year')
                ->orderByDesc('payroll_month')
                ->limit(2)
                ->get()
                ->values();

            $toRun = $runs->get(0);
            $fromRun = $runs->get(1);

            $periodInfo = function (?PayrollRun $run) use ($conn): array {
                if (! $run) {
                    return [
                        'payroll_run_id' => null,
                        'month' => null,
                        'year' => null,
                        'label' => null,
                        'source' => null,
                        'tx_table' => null,
                    ];
                }

                $finalExists = $conn->table('payroll_transaction')
                    ->where('payroll_run_id', $run->id)
                    ->exists();

                $useFinal = ($run->status === 'posted') || $finalExists;

                return [
                    'payroll_run_id' => (int) $run->id,
                    'month' => (int) $run->payroll_month,
                    'year' => (int) $run->payroll_year,
                    'label' => $run->payroll_month . '/' . $run->payroll_year,
                    'source' => $useFinal ? 'final' : 'preview',
                    'tx_table' => $useFinal ? 'payroll_transaction' : 'payroll_transaction_preview',
                ];
            };

            $from = $periodInfo($fromRun);
            $to = $periodInfo($toRun);

            // Helper: totals from tx table
            $txTotals = function (?int $runId, ?string $txTable) use ($conn): array {
                if (! $runId || ! $txTable) {
                    return [
                        'employees_cases' => 0,
                        'basic_amount' => 0.0,
                        'gross_amount' => 0.0,
                        'paye_amount' => 0.0,
                        'net_amount' => 0.0,
                        'paye_cases' => 0,
                        'net_cases' => 0,
                        'basic_cases' => 0,
                        'gross_cases' => 0,
                    ];
                }

                $row = $conn->table($txTable . ' as t')
                    ->where('t.payroll_run_id', $runId)
                    ->selectRaw('
                        COUNT(DISTINCT t.national_id) as employees_cases,
                        COALESCE(SUM(t.basic_salary),0) as basic_amount,
                        COALESCE(SUM(t.gross_pay),0) as gross_amount,
                        COALESCE(SUM(t.paye),0) as paye_amount,
                        COALESCE(SUM(t.net_pay),0) as net_amount,
                        SUM(CASE WHEN t.basic_salary <> 0 THEN 1 ELSE 0 END) as basic_cases,
                        SUM(CASE WHEN t.gross_pay <> 0 THEN 1 ELSE 0 END) as gross_cases,
                        SUM(CASE WHEN t.paye <> 0 THEN 1 ELSE 0 END) as paye_cases,
                        SUM(CASE WHEN t.net_pay <> 0 THEN 1 ELSE 0 END) as net_cases
                    ')
                    ->first();

                return [
                    'employees_cases' => (int) ($row->employees_cases ?? 0),
                    'basic_amount' => (float) ($row->basic_amount ?? 0),
                    'gross_amount' => (float) ($row->gross_amount ?? 0),
                    'paye_amount' => (float) ($row->paye_amount ?? 0),
                    'net_amount' => (float) ($row->net_amount ?? 0),
                    'basic_cases' => (int) ($row->basic_cases ?? 0),
                    'gross_cases' => (int) ($row->gross_cases ?? 0),
                    'paye_cases' => (int) ($row->paye_cases ?? 0),
                    'net_cases' => (int) ($row->net_cases ?? 0),
                ];
            };

            // Helper: item totals from employee_payroll_items
            $itemTotals = function (?int $runId, string $itemType) use ($conn): array {
                if (! $runId) {
                    return ['cases' => 0, 'amount' => 0.0];
                }

                $row = $conn->table('employee_payroll_items as i')
                    ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'i.national_id')
                    ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                    ->where('i.payroll_run_id', $runId)
                    ->where('i.is_void', false)
                    ->where('i.item_type', $itemType)
                    ->selectRaw('
                        COUNT(DISTINCT i.national_id) as cases,
                        COALESCE(SUM(i.amount),0) as amount
                    ')
                    ->first();

                return [
                    'cases' => (int) ($row->cases ?? 0),
                    'amount' => (float) ($row->amount ?? 0),
                ];
            };

            $fromTx = $txTotals($from['payroll_run_id'], $from['tx_table']);
            $toTx = $txTotals($to['payroll_run_id'], $to['tx_table']);

            $fromBenefits = $itemTotals($from['payroll_run_id'], 'benefit');
            $toBenefits = $itemTotals($to['payroll_run_id'], 'benefit');

            $fromDeductions = $itemTotals($from['payroll_run_id'], 'deduction');
            $toDeductions = $itemTotals($to['payroll_run_id'], 'deduction');

            $fromLoans = $itemTotals($from['payroll_run_id'], 'loan');
            $toLoans = $itemTotals($to['payroll_run_id'], 'loan');

            $fromArrears = $itemTotals($from['payroll_run_id'], 'arrears');
            $toArrears = $itemTotals($to['payroll_run_id'], 'arrears');

            // Salary Changes (as defined by business): compare aggregate Basic Pay between periods.
            // Cases = employees paid basic salary in the period, Amount = total basic salary in the period.
            $salaryChangesFrom = ['cases' => $fromTx['basic_cases'], 'amount' => $fromTx['basic_amount']];
            $salaryChangesTo = ['cases' => $toTx['basic_cases'], 'amount' => $toTx['basic_amount']];

            $mkRow = function (string $component, array $fromVal, array $toVal): array {
                return [
                    'component' => $component,
                    'from' => $fromVal,
                    'to' => $toVal,
                    'variance' => [
                        'cases' => (int) $toVal['cases'] - (int) $fromVal['cases'],
                        'amount' => (float) $toVal['amount'] - (float) $fromVal['amount'],
                    ],
                ];
            };

            $rows = [
                $mkRow('Total Employees',
                    ['cases' => $fromTx['employees_cases'], 'amount' => $fromTx['gross_amount']],
                    ['cases' => $toTx['employees_cases'], 'amount' => $toTx['gross_amount']]
                ),
                $mkRow('Salary Changes',
                    $salaryChangesFrom,
                    $salaryChangesTo
                ),
                $mkRow('Deductions', $fromDeductions, $toDeductions),
                $mkRow('Benefits', $fromBenefits, $toBenefits),
                $mkRow('Loans', $fromLoans, $toLoans),
                $mkRow('Arrears', $fromArrears, $toArrears),
                $mkRow('Basic Pay',
                    ['cases' => $fromTx['basic_cases'], 'amount' => $fromTx['basic_amount']],
                    ['cases' => $toTx['basic_cases'], 'amount' => $toTx['basic_amount']]
                ),
                $mkRow('Gross Pay',
                    ['cases' => $fromTx['gross_cases'], 'amount' => $fromTx['gross_amount']],
                    ['cases' => $toTx['gross_cases'], 'amount' => $toTx['gross_amount']]
                ),
                $mkRow('PAYE',
                    ['cases' => $fromTx['paye_cases'], 'amount' => $fromTx['paye_amount']],
                    ['cases' => $toTx['paye_cases'], 'amount' => $toTx['paye_amount']]
                ),
                $mkRow('NET PAY',
                    ['cases' => $fromTx['net_cases'], 'amount' => $fromTx['net_amount']],
                    ['cases' => $toTx['net_cases'], 'amount' => $toTx['net_amount']]
                ),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Payroll summary retrieved successfully',
                'data' => [
                    'from' => $from,
                    'to' => $to,
                    'rows' => $rows,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll summary: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getJvDocumentEndpoint(Request $request, string $run, PayrollDocumentService $payrollDocumentService)
    {
        return $this->payrollDocumentResponse(
            $payrollDocumentService->getJvDocumentEndpoint(
                $run,
                $request->input('format'),
                $request->wantsJson(),
                $request->expectsJson()
            )
        );
    }

    public function getPayeeDocumentEndpoint(Request $request, string $run, PayrollDocumentService $payrollDocumentService)
    {
        return $this->payrollDocumentResponse(
            $payrollDocumentService->getPayeeDocumentEndpoint(
                $run,
                $request->input('format'),
                $request->wantsJson(),
                $request->expectsJson()
            )
        );
    }

    public function getPsssfDocumentEndpoint(Request $request, string $run, PayrollDocumentService $payrollDocumentService)
    {
        return $this->payrollDocumentResponse(
            $payrollDocumentService->getPsssfDocumentEndpoint(
                $run,
                $request->input('format'),
                $request->wantsJson(),
                $request->expectsJson()
            )
        );
    }

    public function getHeslbDocumentEndpoint(Request $request, string $run, PayrollDocumentService $payrollDocumentService)
    {
        return $this->payrollDocumentResponse(
            $payrollDocumentService->getHeslbDocumentEndpoint(
                $run,
                $request->input('format'),
                $request->wantsJson(),
                $request->expectsJson()
            )
        );
    }

    public function getNetPayDocumentEndpoint(Request $request, string $run, PayrollDocumentService $payrollDocumentService)
    {
        $bankBucket = $request->input('bank') ?? $request->input('bucket');

        return $this->payrollDocumentResponse(
            $payrollDocumentService->getNetPayDocumentEndpoint(
                $run,
                $request->input('format'),
                $request->wantsJson(),
                $request->expectsJson(),
                is_string($bankBucket) ? $bankBucket : null
            )
        );
    }

    public function getMinutesDocumentEndpoint(Request $request, string $run, PayrollDocumentService $payrollDocumentService)
    {
        return $this->payrollDocumentResponse(
            $payrollDocumentService->getMinutesDocumentEndpoint(
                $run,
                $request->input('format'),
                $request->wantsJson(),
                $request->expectsJson()
            )
        );
    }

    /**
     * @return array{run: array<string, mixed>, history: list<array<string, mixed>>}|null
     */
    private function loadPayrollRunWithActorNames(int $id): ?array
    {
        $rows = DB::table('bcmis2.payroll_runs as pr')
            ->leftJoin('bcmis.auth_user as prepared_user', fn ($join) => $join
                ->on('prepared_user.pf_number', '=', 'pr.prepared_by')
                ->orWhereRaw('CAST(prepared_user.id AS CHAR) = pr.prepared_by'))
            ->leftJoin('bcmis.auth_user as initiated_user', fn ($join) => $join
                ->on('initiated_user.pf_number', '=', 'pr.initiated_by')
                ->orWhereRaw('CAST(initiated_user.id AS CHAR) = pr.initiated_by'))
            ->leftJoin('bcmis.auth_user as examined_user', fn ($join) => $join
                ->on('examined_user.pf_number', '=', 'pr.examined_by')
                ->orWhereRaw('CAST(examined_user.id AS CHAR) = pr.examined_by'))
            ->leftJoin('bcmis.auth_user as verified_user', fn ($join) => $join
                ->on('verified_user.pf_number', '=', 'pr.verified_by')
                ->orWhereRaw('CAST(verified_user.id AS CHAR) = pr.verified_by'))
            ->leftJoin('bcmis.auth_user as approved_user', fn ($join) => $join
                ->on('approved_user.pf_number', '=', 'pr.approved_by')
                ->orWhereRaw('CAST(approved_user.id AS CHAR) = pr.approved_by'))
            ->leftJoin('bcmis2.payroll_runs_history as h', 'h.payroll_run_id', '=', 'pr.id')
            ->leftJoin('bcmis.auth_user as performed_user', fn ($join) => $join
                ->on('performed_user.pf_number', '=', 'h.performed_by')
                ->orWhereRaw('CAST(performed_user.id AS CHAR) = h.performed_by'))
            ->where('pr.id', $id)
            ->orderByDesc('h.id')
            ->select([
                'pr.*',
                DB::raw("NULLIF(TRIM(CONCAT(prepared_user.first_name, ' ', prepared_user.surname)), '') as prepared_by_name"),
                DB::raw("NULLIF(TRIM(CONCAT(initiated_user.first_name, ' ', initiated_user.surname)), '') as initiated_by_name"),
                DB::raw("NULLIF(TRIM(CONCAT(examined_user.first_name, ' ', examined_user.surname)), '') as examined_by_name"),
                DB::raw("NULLIF(TRIM(CONCAT(verified_user.first_name, ' ', verified_user.surname)), '') as verified_by_name"),
                DB::raw("NULLIF(TRIM(CONCAT(approved_user.first_name, ' ', approved_user.surname)), '') as approved_by_name"),
                'h.id as history_id',
                'h.action as history_action',
                'h.status as history_status',
                'h.workflow_status as history_workflow_status',
                'h.performed_by as history_performed_by',
                'h.performed_by_role as history_performed_by_role',
                'h.comment as history_comment',
                'h.created_at as history_created_at',
                DB::raw("NULLIF(TRIM(CONCAT(performed_user.first_name, ' ', performed_user.surname)), '') as performed_by_name"),
            ])
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $history = $rows
            ->filter(fn ($row) => $row->history_id !== null)
            ->map(fn ($row) => [
                'id' => $row->history_id,
                'payroll_run_id' => $row->id,
                'action' => $row->history_action,
                'status' => $row->history_status,
                'workflow_status' => $row->history_workflow_status,
                'performed_by' => $row->history_performed_by,
                'performed_by_role' => $row->history_performed_by_role,
                'comment' => $row->history_comment,
                'created_at' => $row->history_created_at,
                'performed_by_name' => $row->performed_by_name,
            ])
            ->values()
            ->all();

        $runData = (array) $rows->first();
        foreach ([
            'history_id',
            'history_action',
            'history_status',
            'history_workflow_status',
            'history_performed_by',
            'history_performed_by_role',
            'history_comment',
            'history_created_at',
            'performed_by_name',
        ] as $historyColumn) {
            unset($runData[$historyColumn]);
        }

        $runModel = PayrollRun::query()->with('ermsExecutions')->find($id);
        $runData['history'] = $history;
        $runData['erms_executions'] = $runModel?->ermsExecutions?->toArray() ?? [];

        return [
            'run' => $runData,
            'history' => $history,
        ];
    }

    /**
     * @param  array{success: true, data?: array, response?: mixed, message?: string}|array{success: false, error: string, http: int}  $result
     */
    private function payrollDocumentResponse(array $result)
    {
        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to generate payroll document',
            ], (int) ($result['http'] ?? 500));
        }

        if (isset($result['response'])) {
            return $result['response'];
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Payroll document generated successfully',
            'data' => $result['data'] ?? [],
        ]);
    }
}

