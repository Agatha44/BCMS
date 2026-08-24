<?php

namespace App\Services\Erms\Mappers;

use App\Models\Bms\Bank;
use App\Models\Bms\OvertimeBatch;
use App\Models\Bms\OvertimeRequest;
use Illuminate\Support\Str;

class OvertimeRequestPayeeMapper
{
    /**
     * @param  object  $employee  Row from bcmis2.bridge_employee.
     * @return array<string, mixed>
     */
    public function map(
        OvertimeRequest $request,
        OvertimeBatch $batch,
        object $employee,
        ?Bank $bank,
        array $cfg,
        array $context = []
    ): array {

        $netPay = round((float) ($request->net_pay), 2);
        $grossPay = round((float) ($request->gross_pay), 2);
        $tax = round((float) ($request->tax), 2);

        $pfNumber = trim((string) ($request->pf_number));
        $fullName = $this->employeeFullName($employee, $pfNumber);

        $bankCode = (string) ($bank?->swift_code);
        // $bankCode = 'CRDB';
        $branchCode = (string) ($bank?->sort_code);
        // $branchCode = 'CRDBAZ';
        $bankName = (string) ($bank?->bank_name);
        // $bankName = 'CRDB Bank';
        $branchName = $bank?->bank_name;

        $phone = $this->normalizePhone((string) ($employee->mobile), (string) ($cfg['phone_country_prefix']));
        $email = (string) ($employee->email);
        $accountNo = (string) ($employee->account_no);
        $tin = (string) ($employee->tin);
        $vrn = (string) ($employee->national_id);

        $referenceNumber = $this->buildReferenceNumber($batch, $request, $context);
        $controlNumber = $this->buildControlNumber($batch, $request, $context);

        return [
            'netPayAmount' => $netPay,
            'amount' => $grossPay,
            'controlNumber' => $controlNumber,
            'client' => [
                'clientType' => (string) ($cfg['client_type']),
                'name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'code' => $pfNumber,
                'address' => (string) ($employee->employment_place),
                'tin' => $tin,
                'vrn' => $vrn,
                'clientBankAccount' => [
                    'accountName' => $fullName,
                    'accountNumber' => $accountNo,
                    'bankCode' => $bankCode,
                    'branchName' => $branchName,
                    'branchCode' => $branchCode,
                    'currencyCode' => (string) ($cfg['currency_code']),
                    'preffered' => true,
                    'bankName' => $bankName,
                ],
                'loyaltyType' => (string) ($cfg['loyalty_type']),
                'clientCategory' => (string) ($cfg['client_category']),
                'groupCodes' => [(string) ($cfg['group_code'])],
            ],
            'payeeItemDistribution' => [
                [
                    'netPayAmount' => $netPay,
                    'amount' => $grossPay,
                    'gfsCode' => (string) ($cfg['distribution_gfs_code']),
                    'unitCost' => $grossPay,
                    'units' => 1,
                ],
            ],
            'referenceNumber' => $referenceNumber,
            // 'retirable' => (bool) ($cfg['retirable'] ?? false),
        ];
    }

    protected function employeeFullName(object $employee, string $fallbackCode): string
    {
        $fullName = trim(
            trim((string) ($employee->fname ?? '')) . ' ' .
            trim((string) ($employee->mname ?? '')) . ' ' .
            trim((string) ($employee->sname ?? ''))
        );

        return $fullName !== '' ? $fullName : ('Employee ' . $fallbackCode);
    }

    protected function buildReferenceNumber(OvertimeBatch $batch, OvertimeRequest $request, array $context): string
    {
        $core = strtoupper(trim((string) ($batch->batch_number)));

        $requestId = (string) ($request->id);

        // return strtoupper(trim($core));
        return $requestId;
    }

    protected function buildControlNumber(OvertimeBatch $batch, OvertimeRequest $request, array $context): ?string
    {
        // if (array_key_exists('control_number', $context)) {
        //     return (string) $context['control_number'];
        // }

        // $batchNo = strtoupper(trim((string) ($batch->batch_number ?? '')));
        // $pf = strtoupper(trim((string) ($request->pf_number ?? '')));
        // $id = (string) ($request->id ?? '');

        // $raw = implode('-', array_values(array_filter([$batchNo, $pf, $id], fn ($v) => $v !== '')));

        return null;
    }

    protected function normalizePhone(string $phone, string $prefix): string
    {
        $cell = preg_replace('/\s+/', '', $phone) ?? '';
        $prefix = ltrim($prefix, '+');

        if ($cell === '') {
            return $prefix . '000000000';
        }
        if (str_starts_with($cell, '+')) {
            return ltrim($cell, '+');
        }
        if (str_starts_with($cell, '0')) {
            return $prefix . substr($cell, 1);
        }
        if ($prefix !== '' && str_starts_with($cell, $prefix)) {
            return $cell;
        }

        return $prefix . $cell;
    }
}

