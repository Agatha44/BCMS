<?php

namespace App\Services\Erms\Mappers;

/**
 * Maps a configured institution beneficiary to one ERMS payeeList item.
 */
class PayrollInstitutionPayeeMapper
{
    /**
     * @param  array<string, mixed>  $institution
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    public function map(float $amount, array $institution, array $cfg, string $referenceNumber): array
    {
        $amount = round($amount, 2);

        $client = [
            'clientType' => (string) ($institution['client_type'] ?? 'PUBLIC_INSTITUTION'),
            'name' => (string) ($institution['name'] ?? ''),
            'code' => (string) ($institution['code'] ?? ''),
            'phone' => (string) ($institution['phone'] ?? ''),
            'email' => (string) ($institution['email'] ?? ''),
            'address' => (string) ($institution['address'] ?? ''),
            'tin' => (string) ($institution['tin'] ?? ''),
            'vrn' => null,
            'loyaltyType' => (string) ($cfg['loyalty_type'] ?? ''),
            'clientCategory' => (string) ($cfg['client_category'] ?? ''),
            'groupCodes' => [(string) ($cfg['group_code'] ?? '')],
        ];

        $bankAccount = $this->institutionBankAccount($institution, $cfg);
        if ($bankAccount !== null) {
            $client['clientBankAccount'] = $bankAccount;
        }

        return [
            'netPayAmount' => $amount,
            'amount' => $amount,
            'controlNumber' => null,
            'client' => $client,
            'payeeItemDistribution' => [[
                'netPayAmount' => $amount,
                'amount' => $amount,
                'gfsCode' => (string) ($cfg['distribution_gfs_code'] ?? ''),
                'unitCost' => $amount,
                'units' => 1,
            ]],
            'referenceNumber' => $referenceNumber,
        ];
    }

    /**
     * @param  array<string, mixed>  $institution
     * @return array<string, mixed>
     */
    public function mapHeader(array $institution): array
    {
        return [
            'clientType' => (string) ($institution['client_type'] ?? 'PUBLIC_INSTITUTION'),
            'name' => (string) ($institution['name'] ?? ''),
            'email' => (string) ($institution['email'] ?? ''),
            'phone' => (string) ($institution['phone'] ?? ''),
            'code' => (string) ($institution['code'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $institution
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>|null
     */
    private function institutionBankAccount(array $institution, array $cfg): ?array
    {
        $accountNumber = trim((string) ($institution['bank_account_number'] ?? ''));
        if ($accountNumber === '') {
            return null;
        }

        return [
            'accountName' => (string) ($institution['bank_account_name'] ?? $institution['name'] ?? ''),
            'accountNumber' => $accountNumber,
            'bankCode' => (string) ($institution['bank_code'] ?? $cfg['default_bank_code'] ?? ''),
            'branchCode' => (string) ($institution['branch_code'] ?? $cfg['default_branch_code'] ?? ''),
            'branchName' => (string) ($institution['branch_name'] ?? $cfg['default_bank_name'] ?? ''),
            'bankName' => (string) ($institution['bank_name'] ?? $cfg['default_bank_name'] ?? ''),
            'currencyCode' => (string) ($cfg['currency'] ?? 'TZS'),
            'preffered' => true,
        ];
    }
}
