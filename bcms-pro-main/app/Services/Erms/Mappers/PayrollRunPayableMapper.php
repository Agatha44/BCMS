<?php

namespace App\Services\Erms\Mappers;

use App\Constants\EmployeeStatus;
use App\Models\Bms\Bank;
use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Carbon\Carbon;

class PayrollRunPayableMapper
{
    protected array $skipped = [];

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

    public function map(PayrollRun $run, array $context = []): array
    {
        $this->ensureValidRun($run);

        $cfg = $this->settings();
        $transactions = $this->getTransactions($run, $context);

        $employees = $this->loadEmployees($transactions);
        $banks = $this->loadBanks($transactions, $employees);

        $payees = [];
        $totals = [
            'salary' => 0, 'benefits' => 0, 'deductions' => 0,
            'loans' => 0, 'paye' => 0, 'psssf' => 0, 'arrears' => 0,
        ];

        $totalAmount = 0;
        $totalNetPay = 0;

        foreach ($transactions as $tx) {

            // basic validation
            if ($tx->gross_pay <= 0) {
                $this->skip($tx, 'gross<=0');
                continue;
            }

            $emp = $employees[$tx->national_id] ?? null;
            $bank = $banks[$tx->bank_id] ?? null;

            if (!$emp) {
                $this->skip($tx, 'no_employee');
                continue;
            }

            if (!$bank || !$tx->account_number) {
                $this->skip($tx, 'no_bank');
                continue;
            }

            // totals
            $totalAmount += $tx->gross_pay;
            $totalNetPay += $tx->net_pay;

            $totals['salary'] += $tx->net_pay;
            $totals['benefits'] += $tx->total_benefits;
            $totals['deductions'] += $tx->total_deductions;
            $totals['loans'] += $tx->total_loans;
            $totals['paye'] += $tx->paye;
            $totals['psssf'] += $tx->psssf_contribution;
            $totals['arrears'] += $tx->total_arrears;

            // payee
            if ($tx->gross_pay > 0) {
                $payees[] = $this->makePayee($tx, $emp, $bank, $cfg);
            }
        }

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('No valid payroll data');
        }

        return [
            'currencyCode' => $cfg['currency'],
            'exchangeRate' => 1,
            'departmentCode' => $cfg['department_code'],
            'paymentProcessingMethod' => $cfg['payment_processing_method'],
            'useBudget' => (bool) ($cfg['use_budget']),
            'hasBudgetReservation' => (bool) ($cfg['has_budget_reservation']),
            'budgetReservationRef' => $cfg['budget_reservation_ref'],
            'subActivityCode' => $cfg['sub_activity_code'],
            'paymentType' => $cfg['payment_type'],
            'requestedDate' => now()->format('Y-m-d'),
            'sourceRef' => $run->payroll_number,
            'netPayAmount' => round($totalNetPay, 2),
            'amount' => round($totalAmount, 2),
            'description' => $this->description($run),
            'payee' => $cfg['payee'],
            'externalResources' => $this->externalResources($run, $context),
            'requestItems' => $this->requestItems($cfg, $totalAmount),
            'entries' => $this->entries($cfg, $totalAmount, $totals),
            'payeeList' => $payees,
        ];
    }

    private function ensureValidRun(PayrollRun $run): void
    {
        if (!in_array(strtolower($run->status), ['posted', 'approved'])) {
            throw new InvalidArgumentException('Run must be posted/approved');
        }
    }

    private function getTransactions(PayrollRun $run, array $context)
    {
        return PayrollTransaction::where('payroll_run_id', $run->id)
            ->when(!($context['include_posted'] ?? false), fn($q) => $q->where('erms_status', '!=', 1))
            ->get();
    }

    private function loadEmployees($transactions): array
    {
        $ids = $transactions->pluck('national_id')->filter()->unique();

        return DB::connection('bcmis2')
            ->table('bridge_employee')
            ->whereIn('national_id', $ids)
            ->whereIn('employee_status', EmployeeStatus::activeValues())
            ->get()
            ->keyBy('national_id')
            ->toArray();
    }

    private function loadBanks($transactions, $employees): array
    {
        $ids = [];

        foreach ($transactions as $t) {
            if ($t->bank_id) $ids[] = $t->bank_id;
        }

        foreach ($employees as $e) {
            if ($e->bank_id) $ids[] = $e->bank_id;
        }

        // Keep Eloquent models here (NOT arrays) because makePayee() type-hints Bank.
        // Using ->all() returns an array of Bank instances keyed by bank_id.
        return Bank::whereIn('bank_id', array_values(array_unique($ids)))
            ->where('is_active', true)
            ->get()
            ->keyBy('bank_id')
            ->all();
    }

    private function makePayee($tx, $emp, Bank $bank, $cfg): array
    {
        $name = trim("{$emp->fname} {$emp->mname} {$emp->sname}");
        return [
            'netPayAmount' => round((float) ($tx->net_pay), 2),
            'amount' => round($tx->gross_pay, 2),// put gross pay here for testing it should be net pay
            // 'amount' => round($tx->net_pay, 2), // For production use net pay
            'controlNumber' => null,
            'client' => [
                'clientType' => $cfg['client_type'],
                'name' => $name,
                'code' => $tx->pf_number,
                'phone' => $this->phone($emp->mobile),
                'email' => $emp->email,
                'address' => $emp->employment_place,
                'tin' => $emp->tin,
                'vrn' => null,
                'clientBankAccount' => [
                    'accountName' => $name,
                    'accountNumber' => $tx->account_number,
                    'bankCode' => (string) ($bank?->swift_code),
                    'branchCode' => $bank->sort_code,
                    'branchName' => $bank->bank_name,
                    'bankName' => $bank->bank_name,
                    'currencyCode' => $cfg['currency'],
                    'preffered' => true,
                ],
                'loyaltyType' => $cfg['loyalty_type'],
                'clientCategory' => $cfg['client_category'],
                'groupCodes' => [$cfg['group_code']],
            ],
            'payeeItemDistribution' => [[
                'amount' => round($tx->gross_pay, 2), //For testing it should be net pay
                'netPayAmount' => round($tx->net_pay, 2), //For production use net pay
                'gfsCode' => $cfg['gfs'],
                'unitCost' => round($tx->gross_pay, 2), //For testing it should be net pay
                // 'unitCost' => round($tx->net_pay, 2), //For production use net pay
                'units' => 1,
            ]],
            'referenceNumber' => $tx->id,
            // 'retirable' => false,
        ];
    }

    private function entries($cfg, $total, $t): array
    {
        return [
            ['accountCode' => $cfg['debit'], 'amount' => round($total, 2), 'gfsCode' => $cfg['debit_payable_gfs_code'], 'bookSide' => 'DEBIT', 'serviceEntry' => true, 'taxEntry' => false],

            ['accountCode' => $cfg['salary'], 'amount' => round($t['salary'], 2), 'gfsCode' => $cfg['credit_salary_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
            // ['accountCode' => $cfg['benefits'], 'amount' => round($t['benefits'], 2), 'gfsCode' => $cfg['credit_benefits_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
            // ['accountCode' => $cfg['deductions'], 'amount' => round($t['deductions'], 2), 'gfsCode' => $cfg['credit_deductions_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
            // ['accountCode' => $cfg['loans'], 'amount' => round($t['loans'], 2), 'gfsCode' => $cfg['credit_loans_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
            ['accountCode' => $cfg['paye'], 'amount' => round($t['paye'], 2), 'gfsCode' => $cfg['credit_paye_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
            ['accountCode' => $cfg['psssf'], 'amount' => round($t['psssf'], 2), 'gfsCode' => $cfg['credit_psssf_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
            // ['accountCode' => $cfg['arrears'], 'amount' => round($t['arrears'], 2), 'gfsCode' => $cfg['credit_arrears_gfs_code'], 'bookSide' => 'CREDIT', 'serviceEntry' => true, 'taxEntry' => false],
        ];
    }

    private function description(PayrollRun $run): string
    {
        $month = Carbon::create()->month($run->payroll_month)->format('M');
        return "Nyerere Bridge Salary For - {$month}-{$run->payroll_year}";
    }

    private function phone($raw): string
    {
        $p = preg_replace('/\D/', '', (string) $raw) ?? '';
        if ($p === '') {
            return '';
        }

        if (str_starts_with($p, '255')) {
            $national = substr($p, 3);
            $national = ltrim($national, '0');

            return $national !== '' ? '255'.$national : '255';
        }

        if (str_starts_with($p, '0')) {
            $p = ltrim($p, '0');

            return $p !== '' ? '255'.$p : '';
        }

        return '255'.$p;
    }

    private function nssfPayee(): array
    {
        return [
            'clientType' => $cfg['client_type'],
            'name' => $cfg['client_name'],
            'email' => $cfg['client_email'],
            'phone' => $cfg['client_phone'],
            'code' => $cfg['client_code'],
        ];
    }

    private function externalResources(PayrollRun $run, array $context = []): array
    {
        unset($run);

        // Match overtime style: only include when we have an actual document URL.
        $url = (string) ($context['external_resource_url'] ?? '');
        if (trim($url) === '') {
            return [];
        }

        return [
            [
                'fullUrl' => $url,
                'resourceType' => 'RESOURCE_REFERENCE',
                'requiresAuthentication' => (bool) ($context['external_resource_requires_auth'] ?? true),
                'title' => (string) ($context['external_resource_title'] ?? 'Payroll supporting document'),
            ],
        ];
    }

    private function requestItems($cfg, $totalAmount): array
    {
        return [
            [
                'amount' => $totalAmount,
                'gfsCode' => $cfg['request_item_gfs_code'],
            ],
        ];
    }

    private function skip($tx, $reason)
    {
        $this->skipped[] = [$tx->id, $reason];
    }

    private function settings(): array
    {
        $c = config('erms.payable_settings');
        $c = is_array($c) ? $c : [];

        return [
            'currency' => config('erms.default_config.currency_code'),
            'payment_processing_method' => config('erms.default_config.payment_processing_method'),
            'use_budget' => config('erms.default_config.use_budget'),
            'has_budget_reservation' => config('erms.default_config.has_budget_reservation'),
            'budget_reservation_ref' => config('erms.default_config.budget_reservation_ref'),
            'payee' => config('erms.default_config.payee'),
            'department_code' => $c['department_code'],
            'payment_type' => $c['payment_type'],
            'client_type' => $c['client_type'],
            'client_category' => $c['client_category'],
            'loyalty_type' => $c['loyalty_type'],
            'group_code' => $c['group_code'],
            'sub_activity_code' => $c['sub_activity_salary_code'],
            'gfs' => $c['distribution_gfs_code'],
            'request_item_gfs_code' => $c['request_item_gfs_code'],

            'debit' => (string) ($c['payable_bridge_operating_account_code']),
            'salary' => (string) ($c['net_pay_account_code']),
            'benefits' => (string) ($c['benefits_account_code']),
            'deductions' => (string) ($c['deductions_account_code']),
            'loans' => (string) ($c['loans_account_code']),
            'paye' => (string) ($c['paye_account_code']),
            'psssf' => (string) ($c['psssf_account_code']),
            'arrears' => (string) ($c['arrears_account_code']),
            'heslb' => (string) ($c['heslb_account_code']),

            'debit_payable_gfs_code' => (string) ($c['payable_bridge_operating_gfs_code']),
            'credit_salary_gfs_code' => (string) ($c['net_pay_gfs_code']),
            'credit_benefits_gfs_code' => (string) ($c['benefits_gfs_code']),
            'credit_deductions_gfs_code' => (string) ($c['deductions_gfs_code']),
            'credit_loans_gfs_code' => (string) ($c['loans_gfs_code']),
            'credit_paye_gfs_code' => (string) ($c['paye_gfs_code']),
            'credit_psssf_gfs_code' => (string) ($c['psssf_gfs_code']),
            'credit_arrears_gfs_code' => (string) ($c['arrears_gfs_code']),
            'credit_heslb_gfs_code' => (string) ($c['heslb_gfs_code']),
        ];
    }
}