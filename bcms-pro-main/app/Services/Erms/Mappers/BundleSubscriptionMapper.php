<?php

namespace App\Services\Erms\Mappers;

use Illuminate\Support\Facades\Config;

/**
 * Maps a bundle_subscriptions row for ERMS miscellaneous entries (deferred → income + VAT at expiry).
 */
class BundleSubscriptionMapper
{
    /**
     * @param  object  $subscription  bundle_subscriptions row (must include bill paid fields)
     * @param  object|null  $account  account row for client details
     * @return array<string, mixed>
     */
    public function map(object $subscription, ?object $account = null): array
    {
        $amount = round((float) ($subscription->paid_amt), 2);
        $incomeAmount = round((float) ($amount / 1.18), 2);
        $vatAmount = round((float) ($amount - $incomeAmount), 2);

        $receiptNumber = trim((string) ($subscription->receipt_number));
        $plateNo = trim((string) ($subscription->dist_param));
        $bundleSubscriptionId = (int) ($subscription->id);

        $debitAccount = (string) $this->miscellaneousEntriesConfig('tbs_bundle_deferred_account_code');
        $creditAccount = (string) $this->miscellaneousEntriesConfig('tbs_bundle_income_account_code');
        $vatAccount = (string) $this->miscellaneousEntriesConfig('tbs_bundle_vat_credit_account_code');
        $debitGfs = (string) $this->miscellaneousEntriesConfig('tbs_bundle_deferred_gfs_code');
        $creditGfs = (string) $this->miscellaneousEntriesConfig('tbs_bundle_income_gfs_code');
        $vatGfs = (string) $this->miscellaneousEntriesConfig('tbs_bundle_vat_gfs_code');

        $debitDesc = 'TBS bundle deferred release - '. $receiptNumber. ' '. $plateNo;
        $creditDesc = 'TBS bundle revenue - '. $receiptNumber.' '. $plateNo;
        $vatDesc = 'TBS bundle VAT - '. $receiptNumber.' '. $plateNo;

        $sourceRef = 'TBS-'.$bundleSubscriptionId;
        $trackingReference = $receiptNumber;

        $entryClient = $this->buildEntryClientDetails($subscription, $account);

        return [
            'description' => $this->buildDescription($bundleSubscriptionId, $receiptNumber, $plateNo),
            'record_date' => $subscription->expire_date ?? now(),
            'branch_code' => (string) $this->defaultConfig('branch_code'),
            // 'department_code' => (string) $this->defaultConfig('department_code'),
            'department_code' => (string) $this->miscellaneousEntriesConfig('department_code'),
            'amount' => $amount,
            'entry_purpose' => (string) $this->miscellaneousEntriesConfig('tbs_bundle_entry_purpose'),
            'business_line_code' => (string) $this->miscellaneousEntriesConfig('tbs_bundle_business_line_code'),
            'sub_activity_code' => (string) $this->miscellaneousEntriesConfig('tbs_bundle_sub_activity_code'),
            'source_ref' => $sourceRef,
            'tracking_reference' => $trackingReference,
            'currency_code' => (string) $this->defaultConfig('currency_code'),
            'exchange_rate' => (float) $this->defaultConfig('exchange_rate'),
            'phone_country_prefix' => (string) $this->defaultConfig('phone_country_prefix'),
            'include_entry_client_details' => true,
            'client_details' => $this->buildClientDetails($subscription, $account),
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
                    'account_code' => $vatAccount,
                    'gfs_code' => $vatGfs,
                    'book_side' => 'CREDIT',
                    'description' => $vatDesc,
                    'amount' => $vatAmount,
                    'entry_client_details' => $entryClient,
                ],
            ],
            'source_type' => 'tbs_bundle_revenue',
        ];
    }

    protected function buildDescription(int $bundleSubscriptionId, string $receiptNumber, string $plateNo): string
    {
        $parts = ['Toll bundle Income', 'subscription '.$bundleSubscriptionId];
        if ($receiptNumber !== '') {
            $parts[] = 'receipt '.$receiptNumber;
        }
        if ($plateNo !== '') {
            $parts[] = 'plate '.$plateNo;
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClientDetails(object $subscription, ?object $account): array
    {
        $code = trim((string) ($subscription->account_id ?? ''));
        $name = $this->resolveClientName($subscription, $account);

        return [
            'client_type' => (string) $this->defaultConfig('default_client_type'),
            'loyalty_type' => (string) $this->defaultConfig('default_loyalty_type'),
            'client_category' => (string) $this->defaultConfig('default_client_category'),
            'name' => $name,
            'code' => $code,
            'phone' => $account !== null ? (string) ($account->phone ?? '') : '',
            'email' => $account !== null ? ($account->email ?? null) : null,
            'address' => (string) $this->defaultConfig('client_address'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildEntryClientDetails(object $subscription, ?object $account): array
    {
        $code = trim((string) ($subscription->account_id ?? ''));
        $name = $this->resolveClientName($subscription, $account);

        return [
            'client_type' => (string) $this->defaultConfig('default_client_type'),
            'name' => $name,
            'code' => $code,
            'address' => (string) $this->defaultConfig('client_address'),
        ];
    }

    protected function resolveClientName(object $subscription, ?object $account): string
    {
        $payerName = trim((string) ($subscription->payer_name ?? ''));
        if ($payerName !== '') {
            return $payerName;
        }

        if ($account !== null) {
            $name = trim((string) (($account->first_name ?? '').' '.($account->surname ?? '')));
            if ($name !== '') {
                return $name;
            }
        }

        return 'Payer';
    }

    protected function defaultConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.default_config.'.$key, Config::get('services.erms.default_config.'.$key, $default));
    }

    protected function miscellaneousEntriesConfig(string $key, mixed $default = null): mixed
    {
        return config('erms.miscellaneous_entries.'.$key, $default);
    }
}
