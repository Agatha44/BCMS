<?php

namespace App\Services\Erms\Mappers;

use App\Constants\EmployeeStatus;
use App\Models\Bms\Bank;
use App\Models\Bms\OvertimeBatch;
use App\Models\Bms\OvertimeRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OvertimeBatchPayableMapper
{
    /** @var list<array{request_id:int|string, pf_number:string, reason:string}> */
    protected array $skippedRequests = [];

    protected OvertimeRequestPayeeMapper $requestMapper;

    public function __construct(?OvertimeRequestPayeeMapper $requestMapper = null)
    {
        $this->requestMapper = $requestMapper ?? new OvertimeRequestPayeeMapper();
    }

    /**
     * Build the full ERMS request inner "data" payload (unsigned).
     *
     * Responsibilities:
     * - Loads missing batch requests
     * - Bulk loads employees + banks (avoids per-request DB fetch)
     * - Computes totals, requestItems, entries
     * - Maps payees, skipping invalid requests but recording them
     *
     * @return array<string, mixed>
     */
    public function map(OvertimeBatch $batch, array $context = []): array
    {
        $this->skippedRequests = [];

        $batch->loadMissing(['overtimeRequests']);
        $status = strtolower(trim((string) ($batch->status ?? '')));
        if ($status !== 'approved') {
            throw new InvalidArgumentException("Only approved overtime batches can be sent to ERMS. Current batch status: '{$status}'.");
        }

        /** @var Collection<int, OvertimeRequest> $requests */
        $requests = $batch->overtimeRequests ?? collect();

        $cfg = $this->settings($batch);

        $requestedDate = (string) optional($batch->submitted_at ?? $batch->created_at ?? now())->format('Y-m-d');

        $employeeByPf = $this->loadEmployeesByPfNumbers($requests);
        $bankById = $this->loadBanksByEmployees($employeeByPf);

        $validAmounts = [];
        $validTaxes = [];
        $validNetPays = [];
        foreach ($requests as $req) {
            $amount = round((float) ($req->total_amount), 2);
            if ($amount <= 0) {
                $this->skip($req, 'amount<=0');
                continue;
            }

            $pf = trim((string) ($req->pf_number));
            if ($pf === '' || ! isset($employeeByPf[$pf])) {
                $this->skip($req, 'missing_employee');
                continue;
            }

            $employee = $employeeByPf[$pf];
            $bankId = (int) ($employee->bank_id);
            $accountNo = trim((string) ($employee->account_no));
            if ($bankId <= 0 || ! isset($bankById[$bankId]) || $accountNo === '') {
                $this->skip($req, 'missing_bank');
                continue;
            }

            $validAmounts[] = $amount;
            $validTaxes[] = round((float) ($req->tax), 2);
            $validNetPays[] = round((float) ($req->net_pay), 2);
        }

        $totalAmount = round(array_sum($validAmounts), 2);
        $totalTax = round(array_sum($validTaxes), 2);
        $totalNetPay = round(array_sum($validNetPays), 2);
        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Overtime batch ERMS payable payload has no valid payable requests.');
        }

        $title = $this->renderTemplate(
            (string) ($context['title_template'] ?? ($cfg['title_template'] ?? '')),
            $batch,
            $requestedDate
        ) ?: ('Overtime Payment - ' . (string) $batch->batch_number);

        $description = $this->renderTemplate(
            (string) ($cfg['description_template']),
            $batch,
            $requestedDate
        ) ?: ('Overtime payment for batch ' . (string) $batch->batch_number . ' (' . $requestedDate . ')');

        $payeeList = [];
        foreach ($requests as $req) {
            $amount = round((float) ($req->total_amount), 2);
            if ($amount <= 0) {
                continue;
            }

            $pf = trim((string) ($req->pf_number));
            $employee = $pf !== '' ? ($employeeByPf[$pf] ?? null) : null;
            if (! is_object($employee)) {
                continue;
            }

            $bankId = (int) ($employee->bank_id);
            $accountNo = trim((string) ($employee->account_no));
            $bank = ($bankId > 0 && isset($bankById[$bankId])) ? $bankById[$bankId] : null;
            if ($bank === null || $accountNo === '') {
                continue;
            }

            $payeeList[] = $this->requestMapper->map($req, $batch, $employee, $bank, $cfg, $context);
        }

        $requestItems = [
            [
                'amount' => round($totalAmount+$totalTax, 2),
                'gfsCode' => (string) $cfg['request_item_gfs_code'],
            ],
        ];

        $entries = $this->entries($cfg, $totalAmount, $totalTax);

        return [
            'currencyCode' => (string) $cfg['currency_code'],
            'exchangeRate' => (float) $cfg['exchange_rate'],
            'departmentCode' => (string) $cfg['department_code'],
            'subActivityCode' => (string) $cfg['sub_activity_code'],
            'paymentType' => (string) $cfg['payment_type'],
            'requestedDate' => $requestedDate,
            'sourceRef' => $batch->batch_number,
            'netPayAmount' => round($totalNetPay, 2),
            'amount' => (round($totalAmount + $totalTax, 2)),
            'branchCode' => (string) $cfg['branch_code'],
            'businessLineCode' => (string) $cfg['business_line_code'],
            // 'title' => $title,
            'description' => $description,
            'paymentProcessingMethod' => $this->defaultConfig('payment_processing_method'),
            'useBudget' => $this->defaultConfig('use_budget'),
            'hasBudgetReservation' => $this->defaultConfig('has_budget_reservation'),
            'budgetReservationRef' => $this->defaultConfig('budget_reservation_ref'),
            'payerBankAccountNumber' => '01J1028249500', // CRDB BANK
            // 'payerBankAccountNumber' => '001000222408', // AZANIA BANK
            // 'payerBankAccountNumber' => '22301300005', // NMB BANK
            'payee' => $this->defaultConfig('payee'),
            'externalResources' => $this->externalResources($batch, $cfg),
            'requestItems' => $requestItems,
            'entries' => $entries,
            'payeeList' => $payeeList,
        ];
    }

    /** @return array<string, mixed> */
    public function mapParts(OvertimeBatch $batch, array $context = []): array
    {
        return $this->unsignedToTraitParts($this->map($batch, $context));
    }

    /**
     * Attributes for an ephemeral model using ErmsPayaplePayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function mapPayloadAttributes(OvertimeBatch $batch, array $context = []): array
    {
        $unsigned = $this->map($batch, $context);

        return [
            'erms_payable_parts' => $this->unsignedToTraitParts($unsigned),
            'erms_payable_root_extensions' => [
                'sourceRef' => (string) ($unsigned['sourceRef'] ?? ''),
                'amount' => (float) ($unsigned['amount'] ?? 0),
            ],
            'erms_payable_context_defaults' => $this->traitContextDefaultsFromBatch($batch),
        ];
    }

    /** @param array<string, mixed> $unsigned */
    protected function unsignedToTraitParts(array $unsigned): array
    {
        return [
            'totalAmount' => (float) ($unsigned['amount'] ?? 0),
            'requestedDate' => (string) ($unsigned['requestedDate'] ?? ''),
            'payeeList' => is_array($unsigned['payeeList'] ?? null) ? $unsigned['payeeList'] : [],
            'entries' => is_array($unsigned['entries'] ?? null) ? $unsigned['entries'] : [],
            'externalResources' => is_array($unsigned['externalResources'] ?? null) ? $unsigned['externalResources'] : [],
            'description' => (string) ($unsigned['description'] ?? ''),
        ];
    }

    /** Batch-level bank/contact fallbacks merged into trait payable settings (aligned with {@see settings()}). */
    protected function traitContextDefaultsFromBatch(OvertimeBatch $batch): array
    {
        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];

        return [
            'default_bank_code' => (string) ($batch->bank_code ?? ($payable['default_bank_code'] ?? '')),
            'default_branch_code' => (string) ($batch->branch_code ?? ($payable['default_branch_code'] ?? '')),
            'default_bank_name' => (string) ($batch->bank_name ?? ($payable['default_bank_name'] ?? '')),
            'default_email' => (string) ($batch->email ?? ($payable['default_email'] ?? '')),
            'default_client_address' => (string) ($batch->client_address ?? ($payable['default_client_address'] ?? $this->defaultConfig('client_address', ''))),
            'phone_country_prefix' => (string) ($payable['phone_country_prefix'] ?? $this->defaultConfig('phone_country_prefix', '255')),
        ];
    }

    /**
     * For validation error reporting.
     *
     * @return list<array{request_id:int|string, pf_number:string, reason:string}>
     */
    public function skippedRequests(): array
    {
        return $this->skippedRequests;
    }

    protected function skip(OvertimeRequest $request, string $reason): void
    {
        $this->skippedRequests[] = [
            'request_id' => $request->id ?? '',
            'pf_number' => (string) ($request->pf_number ?? ''),
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, object>
     */
    protected function loadEmployeesByPfNumbers(Collection $requests): array
    {
        $pfNumbers = $requests
            ->map(fn ($r) => trim((string) ($r->pf_number)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($pfNumbers === []) {
            return [];
        }

        $rows = DB::connection('bcmis2')
            ->table('bridge_employee')
            ->whereIn('pfno', $pfNumbers)
            ->whereIn('employee_status', EmployeeStatus::activeValues())
            ->get();

        $byPf = [];
        foreach ($rows as $row) {
            $pf = trim((string) ($row->pfno));
            if ($pf !== '') {
                $byPf[$pf] = $row;
            }
        }

        return $byPf;
    }

    /**
     * @param  array<string, object>  $employeeByPf
     * @return array<int, Bank>
     */
    protected function loadBanksByEmployees(array $employeeByPf): array
    {
        $bankIds = [];
        foreach ($employeeByPf as $employee) {
            $id = (int) ($employee->bank_id ?? 0);
            if ($id > 0) {
                $bankIds[] = $id;
            }
        }

        $bankIds = array_values(array_unique($bankIds));
        if ($bankIds === []) {
            return [];
        }

        $banks = Bank::query()->whereIn('bank_id', $bankIds)
        ->where('is_active', true)
        ->get();
        $byId = [];
        foreach ($banks as $bank) {
            $byId[(int) $bank->bank_id] = $bank;
        }

        return $byId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function entries(array $cfg, float $totalAmount, float $totalTax): array
    {
        $debitAccount = (string) $cfg['debit_account_code'];
        $creditAccount = (string) $cfg['credit_account_code'];
        $payeAccount = (string) $cfg['paye_account_code'];
        $payeGfs = (string) $cfg['paye_gfs_code'];

        $totalAmount = round($totalAmount, 2);
        $totalTax = round($totalTax, 2);
        $totalBatchAmount = round($totalAmount + $totalTax, 2);

        if ($debitAccount === '' || $creditAccount === '') {
            throw new InvalidArgumentException(
                'Configure erms.payable_settings.payable_bridge_operating_account_code and payable_supplier_account_code for overtime payable entries.'
            );
        }

        if ($totalTax > 0 && $payeAccount === '') {
            throw new InvalidArgumentException(
                'Configure erms.payable_settings.paye_account_code for overtime PAYE/tax credit lines.'
            );
        }

        $lines = [
            [
                'accountCode' => $debitAccount,
                'amount' => $totalBatchAmount,
                'gfsCode' => (string) $cfg['debit_gfs_code'],
                'bookSide' => 'DEBIT',
                'serviceEntry' => true,
                'taxEntry' => false,
            ],
            [
                'accountCode' => $creditAccount,
                'amount' => $totalAmount,
                'gfsCode' => (string) $cfg['credit_gfs_code'],
                'bookSide' => 'CREDIT',
                'serviceEntry' => true,
                'taxEntry' => false,
            ],
        ];

        if ($totalTax > 0) {
            $lines[] = [
                'accountCode' => $payeAccount,
                'amount' => $totalTax,
                'gfsCode' => $payeGfs,
                'bookSide' => 'CREDIT',
                'serviceEntry' => true,
                'taxEntry' => true,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function externalResources(OvertimeBatch $batch, array $cfg): array
    {
        $batchNumber = (string) ($batch->batch_number ?? '');
        if (trim($batchNumber) === '') {
            return [];
        }

        $overtimeDocumentUrl = (string) ($cfg['overtime_document_url']). $batchNumber;

        return [
            [
                'fullUrl' => $overtimeDocumentUrl,
                'resourceType' => 'RESOURCE_REFERENCE',
                'requiresAuthentication' => false,
                'title' => 'Overtime Calculation Sheet',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function settings(OvertimeBatch $batch): array
    {
        $defaultCurrency = (string) $this->defaultConfig('currency_code');
        $defaultRate = (float) $this->defaultConfig('exchange_rate');
        $defaultDepartment = (string) $this->defaultConfig('department_code');
        // $defaultDepartment = '124';

        $payable = config('erms.payable_settings', []);
        $payable = is_array($payable) ? $payable : [];

        return [
            'currency_code' => (string) ($defaultCurrency),
            'exchange_rate' => (float) $defaultRate,
            'department_code' => (string) $defaultDepartment,
            'sub_activity_code' => (string) ($payable['sub_activity_overtime_code']),

            'payment_type' => (string) ($payable['payment_type']),

            'branch_code' => $this->defaultConfig('branch_code'),
            'business_line_code' => $this->defaultConfig('business_line_code'),

            'request_item_gfs_code' => (string) ($payable['request_item_gfs_code']),
            'distribution_gfs_code' => (string) ($payable['distribution_gfs_code']),

            // Journal entries (see entries()).
            'debit_account_code' => (string) ($payable['payable_bridge_operating_account_code']),
            'debit_gfs_code' => (string) ($payable['payable_bridge_operating_gfs_code']),
            'credit_account_code' => (string) ($payable['payable_staff_account_code']),
            'credit_gfs_code' => (string) ($payable['payable_staff_gfs_code']),
            'paye_account_code' => (string) ($payable['paye_account_code']),
            'paye_gfs_code' => (string) ($payable['paye_gfs_code']),

            // Payee client defaults.
            'client_type' => (string) ($payable['client_type']),
            'client_category' => (string) ($payable['client_category']),
            'loyalty_type' => (string) ($payable['loyalty_type']),
            'group_code' => (string) ($payable['group_code']),
            'overtime_document_url' => (string) ($payable['overtime_document_url']),
            // 'retirable' => (bool) ($payable['retirable'] ?? false),

            // Optional templates.
            // 'title_template' => (string) ($payable['title_template']),
            'description_template' => (string) (($payable['description'])).' - '.(string) ($batch->batch_number),

            // Fallbacks for bank details (batch overrides if present).
            'default_bank_code' => (string) ($batch->bank_code ?? ($payable['default_bank_code'] ?? '')),
            'default_branch_code' => (string) ($batch->branch_code ?? ($payable['default_branch_code'] ?? '')),
            'default_bank_name' => (string) ($batch->bank_name ?? ($payable['default_bank_name'] ?? '')),
            'default_email' => (string) ($batch->email ?? ($payable['default_email'] ?? '')),
            'default_client_address' => (string) ($batch->client_address ?? ($payable['default_client_address'] ?? $this->defaultConfig('client_address', ''))),
            'phone_country_prefix' => (string) ($payable['phone_country_prefix'] ?? $this->defaultConfig('phone_country_prefix', '255')),
        ];
    }

    protected function renderTemplate(string $template, OvertimeBatch $batch, string $requestedDate): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $replacements = [
            '{batch_number}' => (string) ($batch->batch_number),
            '{requested_date}' => $requestedDate,
            '{batch_name}' => (string) ($batch->batch_name),
        ];

        return strtr($template, $replacements);
    }

    protected function defaultConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.default_config.' . $key, Config::get('services.erms.default_config.' . $key, $default));
    }
}
