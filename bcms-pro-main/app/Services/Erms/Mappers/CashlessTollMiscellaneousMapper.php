<?php

namespace App\Services\Erms\Mappers;

use App\Models\Account;
use App\Models\TollTransaction;
use Illuminate\Support\Facades\Config;

class CashlessTollMiscellaneousMapper
{
    /**
     * Canonical mapper output consumed by ErmsMiscellaneousPayloadTrait.
     *
     * @return array<string, mixed>
     */
    public function map(TollTransaction $toll, Account $account): array
    {
        $amount = round((float) ($toll->charged_amount), 2);

        $branch = (string) $this->defaultConfig('branch_code');
        // $department = (string) $this->defaultConfig('department_code');
        $department = (string) $this->miscellaneousEntriesConfig('department_code');

        $debitAccount = (string) $this->miscellaneousEntriesConfig('cashless_toll_debit_account_code');
        $VATCreditAccount = (string) $this->miscellaneousEntriesConfig('cashless_toll_vat_credit_account_code');
        $creditAccount = (string) $this->miscellaneousEntriesConfig('cashless_toll_credit_account_code');
        $debitGfs = (string) $this->miscellaneousEntriesConfig('cashless_toll_debit_gfs_code');
        $VATCreditGfs = (string) $this->miscellaneousEntriesConfig('cashless_toll_vat_credit_gfs_code');
        $creditGfs = (string) $this->miscellaneousEntriesConfig('cashless_toll_credit_gfs_code');
        $debitDesc = (string) ('Prepayment passage -'. ' Receipt '.$toll->receipt_num. ' Plate No. '. $toll->plate_no);
        $creditDesc = (string) ('Prepayment revenue passage -' . ' Receipt '.$toll->receipt_num. ' Plate No. '. $toll->plate_no);
        $incomeAmount = round((float) ($amount/1.18), 2);
        $VATAmount = round((float) ($amount - $incomeAmount), 2);
        $businessLineCode = (string) $this->miscellaneousEntriesConfig('business_line_code');
        $subActivityCode = (string) $this->miscellaneousEntriesConfig('sub_activity_code');

        $receiptNum = trim((string) ($toll->receipt_num));
        $plateNo = trim((string) ($toll->plate_no));
        $sourceRef = (string) $toll->receipt_num;
        $trackingReference = (string) $toll->receipt_num;

        $description = $this->buildDescription($receiptNum, $plateNo);
        $entryPurpose = (string) $this->miscellaneousEntriesConfig('cashless_toll_entry_purpose');

        $clientDetails = $this->buildClientDetails($account);
        $entryClient = $this->buildEntryClientDetails($account);

        return [
            'description' => $description,
            'record_date' => $toll->created_at,
            'branch_code' => $branch,
            'department_code' => $department,
            'amount' => $amount,
            'entry_purpose' => $entryPurpose,
            'business_line_code' => $businessLineCode,
            'sub_activity_code' => $subActivityCode,
            'source_ref' => $sourceRef,
            'tracking_reference' => $trackingReference,
            'currency_code' => (string) $this->defaultConfig('currency_code'),
            'exchange_rate' => (float) $this->defaultConfig('exchange_rate'),
            'phone_country_prefix' => (string) $this->defaultConfig('phone_country_prefix'),
            'include_entry_client_details' => true,
            'client_details' => $clientDetails,
            'entries' => [
                [
                    'account_code' => $debitAccount,
                    'gfs_code' => $debitGfs,
                    'book_side' => 'DEBIT',
                    'description' => $debitDesc,
                    'amount' => $amount,
                    'entry_client_details' => $entryClient,
                ],
                [
                    'account_code' => $creditAccount,
                    'gfs_code' => $creditGfs,
                    'book_side' => 'CREDIT',
                    'description' => $creditDesc,
                    'amount' => $incomeAmount,
                    'entry_client_details' => $entryClient,
                ],
                [
                    'account_code' => $VATCreditAccount,
                    'gfs_code' => $VATCreditGfs,
                    'book_side' => 'CREDIT',
                    'description' => $creditDesc,
                    'amount' => $VATAmount,
                    'entry_client_details' => $entryClient,
                ],
            ],
            'source_type' => 'cashless_toll',
        ];
    }

    protected function buildDescription(string $receiptNum, string $plateNo): string
    {
        $parts = ['Cashless toll passage'];
        if ($receiptNum !== '') {
            $parts[] = 'receipt '.$receiptNum;
        }
        if ($plateNo !== '') {
            $parts[] = 'plate '.$plateNo;
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClientDetails(Account $account): array
    {
        $code = trim((string) ($account->account_no));
        $name = trim((string) ($account->first_name.' '.$account->surname));

        return [
            'client_type' => (string) $this->defaultConfig('default_client_type'),
            'loyalty_type' => (string) $this->defaultConfig('default_loyalty_type'),
            'client_category' => (string) $this->defaultConfig('default_client_category'),
            'name' => $name,
            'code' => $code,
            'phone' => (string) ($account->phone),
            'email' => ($account->email),
            'address' => (string) $this->defaultConfig('client_address'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildEntryClientDetails(Account $account): array
    {
        $code = trim((string) ($account->account_no));
        $name = trim((string) ($account->first_name.' '.$account->surname));

        return [
            'client_type' => (string) $this->defaultConfig('default_client_type'),
            'name' => $name,
            'code' => $code,
            'address' => (string) $this->defaultConfig('client_address'),
        ];
    }


    protected function defaultConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.default_config.' . $key, Config::get('services.erms.default_config.' . $key, $default));
    }

    /**
     * Cashless toll line settings live under erms.miscellaneous_entries.
     */
    protected function miscellaneousEntriesConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.miscellaneous_entries.'.$key, $default);
    }
}
