<?php

namespace App\Services\Erms\Mappers;

use App\Models\Bms\Bank;
use App\Models\Bms\Payroll\PayrollTransaction;

/**
 * Maps one payroll_transaction row to an ERMS payeeList item (net pay only).
 */
class PayrollTransactionPayeeMapper
{
    /**
     * @param  object  $employee  Row from bcmis2.bridge_employee.
     * @param  array<string, mixed>  $cfg  Payable settings from PayrollNetPayMapper.
     * @return array<string, mixed>
     */
    public function map(
        PayrollTransaction $transaction,
        object $employee,
        Bank $bank,
        array $cfg,
        array $context = []
    ): array {
        $netPay = round((float) ($transaction->net_pay), 2);
        $pfNumber = trim((string) ($transaction->pf_number));
        $fullName = $this->employeeFullName($employee);

        $referenceNumber = (string) ($context['reference_number'] ?? $transaction->id);

        return [
            'netPayAmount' => $netPay,
            'amount' => $netPay,
            'controlNumber' => null,
            'client' => [
                'clientType' => (string) ($cfg['client_type']),
                'name' => $fullName,
                'code' => $pfNumber,
                'phone' => $this->normalizePhone((string) ($employee->mobile), (string) ($cfg['phone_country_prefix'])),
                'email' => (string) ($employee->email),
                'address' => (string) ($employee->employment_place),
                'tin' => (string) ($employee->tin),
                'vrn' => null,
                'clientBankAccount' => [
                    'accountName' => $fullName,
                    'accountNumber' => (string) ($transaction->account_number),
                    'bankCode' => (string) ($bank->swift_code),
                    'branchCode' => (string) ($bank->sort_code),
                    'branchName' => (string) ($bank->bank_name),
                    'bankName' => (string) ($bank->bank_name),
                    'currencyCode' => (string) ($cfg['currency']),
                    'preffered' => true,
                ],
                'loyaltyType' => (string) ($cfg['loyalty_type']),
                'clientCategory' => (string) ($cfg['client_category']),
                'groupCodes' => [(string) ($cfg['group_code'])],
            ],
            'payeeItemDistribution' => [[
                'netPayAmount' => $netPay,
                'amount' => $netPay,
                'gfsCode' => (string) ($cfg['distribution_gfs_code']),
                'unitCost' => $netPay,
                'units' => 1,
            ]],
            'referenceNumber' => $referenceNumber,
        ];
    }

    protected function employeeFullName(object $employee): string
    {
        $fullName = trim(
            trim((string) ($employee->fname ?? '')).' '.
            trim((string) ($employee->mname ?? '')).' '.
            trim((string) ($employee->sname ?? ''))
        );

        return $fullName;
    }

    protected function normalizePhone(string $phone, string $prefix): string
    {
        $cell = preg_replace('/\D/', '', $phone) ?? '';
        $prefix = ltrim($prefix, '+');

        if ($cell === '') {
            return $prefix !== '' ? $prefix.'000000000' : '';
        }

        if (str_starts_with($cell, '255')) {
            $national = ltrim(substr($cell, 3), '0');

            return $national !== '' ? '255'.$national : '255';
        }

        if (str_starts_with($cell, '0')) {
            $cell = ltrim($cell, '0');

            return $prefix !== '' && $cell !== '' ? $prefix.$cell : $cell;
        }

        if ($prefix !== '' && str_starts_with($cell, $prefix)) {
            return $cell;
        }

        return $prefix !== '' ? $prefix.$cell : $cell;
    }
}
