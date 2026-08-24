<?php

/*
|--------------------------------------------------------------------------
| ERMS external receivables API
|--------------------------------------------------------------------------
| Credentials and endpoint paths from environment. Use config('erms.*')
| anywhere (controllers, jobs, services). Full URLs join base_url with paths.
*/

$base = rtrim((string) env('ERMS_BASE_URL', ''), '/');

$fullUrl = static function (string $path) use ($base): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    $path = ltrim($path, '/');

    return $base === '' ? $path : $base . '/' . $path;
};

// Public BMS API base for ERMS callbacks and document URLs (inbound to this app).
$bmsPublicApi = rtrim((string) env('BMS_PUBLIC_API_URL', ''), '/');

$bmsPublicUrl = static function (string $path) use ($bmsPublicApi): string {
    $path = trim($path);
    if ($path === '') {
        return $bmsPublicApi;
    }

    return $bmsPublicApi . '/' . ltrim($path, '/');
};

$pathAuthToken = (string) env('ERMS_AUTH_TOKEN_URL', 'erms-auth/thibitisha/ingia');
$pathInvoiceCreate = (string) env('ERMS_INVOICE_CREATE', 'erms-accounts/ext/api/receivables/invoice/create');
$pathInvoiceUpdate = (string) env('ERMS_INVOICE_UPDATE', 'erms-accounts/ext/api/receivables/invoice/update');
$pathReceiptCreate = (string) env('ERMS_RECEIPT_CREATE', 'erms-accounts/ext/api/receivables/receipt/create');
$pathReceiptCancel = (string) env('ERMS_RECEIPT_CANCEL', 'erms-accounts/ext/api/receivables/invoice-receipt/cancel');
$pathSubmitSale = (string) env('ERMS_SUBMIT_SALE', 'erms-accounts/ext/api/receivables/submit-sale');
$pathCreateSaleReceipt = (string) env('ERMS_CREATE_SALE_RECEIPT', 'erms-accounts/ext/api/receivables/create-sale-receipt');
$pathInvoiceReceiptCancelSale = (string) env('ERMS_INVOICE_RECEIPT_CANCEL_SALE', 'erms-accounts/ext/api/receivables/invoice-receipt/cancel-sale');
$pathMiscellaneousEntries = (string) env('ERMS_MISCELLANEOUS_ENTRIES', 'erms-accounts/ext/api/misc-records/submit');
$pathCreatePayablePaymentRequest = (string) env('ERMS_CREATE_PAYABLE_PAYMENT_REQUEST', 'erms-accounts/ext/api/v2/payment-requests/create');
$pathMemberRegister = (string) env('ERMS_MEMBER_REGISTER', '');

return [
    'base_url' => $base,

    'client_id' => env('ERMS_CLIENT_ID'),
    'client_secret' => env('ERMS_CLIENT_SECRET'),

    /**
     * Password for ERMS_PRIVATE_PFX (PKCS#12).
     */
    'private_pfx_password' => env('ERMS_PRIVATE_PFX_PASSWORD', ''),

    'private_pem_password' => env('ERMS_PRIVATE_PEM_PASSWORD', ''),


    'default_config' => [

        'branch_code' => env('ERMS_BRANCH_CODE', '322'),

        'business_line_code' => env('ERMS_BUSINESS_LINE_CODE', '100'),

        'department_code' => env('ERMS_DEPARTMENT_CODE', '104060'),

        'generated_by' => env('ERMS_GENERATED_BY', 'BRIDGE_MANAGEMENT_SYSTEM'),

        'client_address' => env('ERMS_CLIENT_ADDRESS', 'Nyerere Bridge, Dar Es Salaam'),

        'default_loyalty_type' => env('ERMS_DEFAULT_LOYALTY_TYPE', 'RECURRING'),

        'default_client_category' => env('ERMS_DEFAULT_CLIENT_CATEGORY', 'CUSTOMER'),

        'currency_code' => env('ERMS_CURRENCY_CODE', 'TZS'),

        'phone_country_prefix' => env('ERMS_PHONE_COUNTRY_PREFIX', '255'),

        'exchange_rate' => env('ERMS_EXCHANGE_RATE', 1),

        'default_client_type' => env('ERMS_DEFAULT_CLIENT_TYPE', 'INDIVIDUAL'),

        'payment_processing_method' => env('ERMS_PAYMENT_PROCESSING_METHOD', 'BULK'),

        'use_budget' => env('ERMS_USE_BUDGET', false),

        'has_budget_reservation' => env('ERMS_HAS_BUDGET_RESERVATION', false),

        'budget_reservation_ref' => env('ERMS_BUDGET_RESERVATION_REF', null),

        "payee" => [
            'clientType' => 'PUBLIC_INSTITUTION',
            'name' => 'National Social Security Fund',
            'email' => 'dg@nssf.go.tz',
            'phone' => '255800116773',
            'code' => 'NSSF'

        ],

    ],


    // Receivable receipt payload mapping used by receipt preview/create routes.
    'receivable_receipt' => [
        // 'debit_account_code' => env('ERMS_RECEIPT_DEBIT_ACCOUNT'),
        'credit_account_code' => env('ERMS_RECEIPT_CREDIT_ACCOUNT', '4060010000'),
        'prepayment_credit_account_code' => env('ERMS_RECEIPT_PREPAYMENT_ACCOUNT_CODE', '2070370000'),
        'prepayment_credit_gfs_code' => env('ERMS_RECEIPT_PREPAYMENT_GFS_CODE', '33181117'),
        'debit_gfs_code' => env('ERMS_RECEIPT_DEBIT_GFS_CODE', '0'),
        'credit_gfs_code' => env('ERMS_RECEIPT_CREDIT_GFS_CODE', '14220445'),

        'department_code' => env('ERMS_RECEIPT_DEPARTMENT_CODE', '104020'),
        // Bridge bill receivable receipts: third line (VAT credit)
        'vat_credit_account_code' => env('ERMS_RECEIPT_VAT_CREDIT_ACCOUNT', env('ERMS_MISC_CASHLESS_TOLL_VAT_CREDIT_ACCOUNT', '2040150000')),
        'vat_credit_gfs_code' => env('ERMS_RECEIPT_VAT_CREDIT_GFS', env('ERMS_MISC_CASHLESS_TOLL_VAT_CREDIT_GFS', '33182108')),
        'vat_credit_line_description' => env('ERMS_RECEIPT_VAT_CREDIT_LINE_DESCRIPTION', 'VAT'),
        'bill_number_format' => env('ERMS_RECEIPT_BILL_NUMBER_FORMAT', 'pay_ref_id'),
        'title_template' => env('ERMS_RECEIPT_TITLE_TEMPLATE'),
        'debit_line_description' => env('ERMS_RECEIPT_DEBIT_LINE_DESCRIPTION', 'Bank payment'),
        'credit_line_description' => env('ERMS_RECEIPT_CREDIT_LINE_DESCRIPTION', 'Toll fee'),
        'exchequer_no' => env('ERMS_RECEIPT_EXCHEQUER_NO', ''),
        'portfolio_key' => env('ERMS_RECEIPT_PORTFOLIO_KEY', 'portifolio'),
        'business_line_code' => env('ERMS_RECEIPT_BUSINESS_LINE_CODE', '100'),
        'sub_activity_code' => env('ERMS_RECEIPT_SUB_ACTIVITY_CODE', '1040101060'),
        'portfolio' => [],
    ],

    // Miscellaneous Entries API (signed body: data + signature).
    'miscellaneous_entries' => [
        // Cashless toll (prepaid balance) miscellaneous entry lines — finance should set per environment.
        'cashless_toll_debit_account_code' => env('ERMS_MISC_CASHLESS_TOLL_DEBIT_ACCOUNT', '2070370000'),
        'cashless_toll_credit_account_code' => env('ERMS_MISC_CASHLESS_TOLL_CREDIT_ACCOUNT', '4060010000'),
        'cashless_toll_debit_gfs_code' => env('ERMS_MISC_CASHLESS_TOLL_DEBIT_GFS', '33181117'),
        'cashless_toll_credit_gfs_code' => env('ERMS_MISC_CASHLESS_TOLL_CREDIT_GFS', '14220445'),
        'cashless_toll_vat_credit_account_code' => env('ERMS_MISC_CASHLESS_TOLL_VAT_CREDIT_ACCOUNT', '2040150000'),
        'cashless_toll_vat_credit_gfs_code' => env('ERMS_MISC_CASHLESS_TOLL_VAT_CREDIT_GFS', '33182108'),
        'cashless_toll_entry_purpose' => env('ERMS_MISC_CASHLESS_TOLL_ENTRY_PURPOSE', 'DEPOSIT_CONSUMPTION'),

        'department_code' => env('ERMS_MISC_CASHLESS_TOLL_DEPARTMENT_CODE', '104020'),

        'business_line_code' => env('ERMS_MISC_CASHLESS_TOLL_BUSINESS_LINE_CODE', '100'),
        'sub_activity_code' => env('ERMS_MISC_CASHLESS_TOLL_SUB_ACTIVITY_CODE', '1040101060'),

        // TBS bundle revenue recognition at subscription expiry (deferred → income + VAT).
        'tbs_bundle_deferred_account_code' => env('ERMS_MISC_TBS_BUNDLE_DEFERRED_ACCOUNT', '2070370000'),
        'tbs_bundle_income_account_code' => env('ERMS_MISC_TBS_BUNDLE_INCOME_ACCOUNT', '4060010000'),
        'tbs_bundle_vat_credit_account_code' => env('ERMS_MISC_TBS_BUNDLE_VAT_CREDIT_ACCOUNT', '2040150000'),
        'tbs_bundle_deferred_gfs_code' => env('ERMS_MISC_TBS_BUNDLE_DEFERRED_GFS', '33181117'),
        'tbs_bundle_income_gfs_code' => env('ERMS_MISC_TBS_BUNDLE_INCOME_GFS', '14220445'),
        'tbs_bundle_vat_gfs_code' => env('ERMS_MISC_TBS_BUNDLE_VAT_GFS', '33182108'),
        'tbs_bundle_entry_purpose' => env('ERMS_MISC_TBS_BUNDLE_ENTRY_PURPOSE', 'DEPOSIT_CONSUMPTION'),
        'tbs_bundle_business_line_code' => env('ERMS_MISC_TBS_BUNDLE_BUSINESS_LINE_CODE', '100'),
        'tbs_bundle_sub_activity_code' => env('ERMS_MISC_TBS_BUNDLE_SUB_ACTIVITY_CODE', '1040101060'),

        // Payroll run miscellaneous accrual (posted payroll → misc-records/submit).
        'payroll_entry_purpose' => env('ERMS_MISC_PAYROLL_ENTRY_PURPOSE', 'MISC'),
        'payroll_business_line_code' => env('ERMS_MISC_PAYROLL_BUSINESS_LINE_CODE', '100'),
        'payroll_sub_activity_code' => env('ERMS_MISC_PAYROLL_SUB_ACTIVITY_CODE', env('ERMS_PAYABLE_SUB_ACTIVITY_SALARY_CODE', '1040601010')),
        'payroll_department_code' => env('ERMS_MISC_PAYROLL_DEPARTMENT_CODE', env('ERMS_PAYABLE_DEPARTMENT_CODE', '104060')),

        // Payroll run miscellaneous client details.
        'payroll_client_type' => env('ERMS_MISC_PAYROLL_CLIENT_TYPE', 'PUBLIC_INSTITUTION'),
        'payroll_client_category' => env('ERMS_MISC_PAYROLL_CLIENT_CATEGORY', 'CUSTOMER'),
        'payroll_client_name' => env('ERMS_MISC_PAYROLL_CLIENT_NAME', 'National Social Security Fund'),
        'payroll_client_code' => env('ERMS_MISC_PAYROLL_CLIENT_CODE', 'NSSF'),
        'payroll_client_phone' => env('ERMS_MISC_PAYROLL_CLIENT_PHONE', '255800116773'),
        'payroll_client_email' => env('ERMS_MISC_PAYROLL_CLIENT_EMAIL', 'dg@nssf.go.tz'),

        // Payroll run miscellaneous debit and credit accounts.
        'payroll_debit_account_code' => env('ERMS_MISC_PAYROLL_DEBIT_ACCOUNT', env('ERMS_PAYABLE_BRIDGE_OPERATING_ACCOUNT_CODE', '5025010000')),
        'payroll_debit_gfs_code' => env('ERMS_MISC_PAYROLL_DEBIT_GFS', env('ERMS_PAYABLE_BRIDGE_OPERATING_GFS_CODE', '21111108')),
        'payroll_salary_credit_account_code' => env('ERMS_MISC_PAYROLL_SALARY_CREDIT_ACCOUNT', env('ERMS_NET_PAY_ACCOUNT_CODE', '2080500000')),
        'payroll_salary_credit_gfs_code' => env('ERMS_MISC_PAYROLL_SALARY_CREDIT_GFS', env('ERMS_NET_PAY_GFS_CODE', '33181117')),
        'payroll_paye_credit_account_code' => env('ERMS_MISC_PAYROLL_PAYE_CREDIT_ACCOUNT', env('ERMS_PAYE_ACCOUNT_CODE', '2080030000')),
        'payroll_paye_credit_gfs_code' => env('ERMS_MISC_PAYROLL_PAYE_CREDIT_GFS', env('ERMS_PAYE_GFS_CODE', '33181117')),
        'payroll_psssf_credit_account_code' => env('ERMS_MISC_PAYROLL_PSSSF_CREDIT_ACCOUNT', env('ERMS_PSSSF_ACCOUNT_CODE', '2080050000')),
        'payroll_psssf_credit_gfs_code' => env('ERMS_MISC_PAYROLL_PSSSF_CREDIT_GFS', env('ERMS_PSSSF_GFS_CODE', '33181117')),
        'payroll_heslb_credit_account_code' => env('ERMS_MISC_PAYROLL_HESLB_CREDIT_ACCOUNT', env('ERMS_HESLB_ACCOUNT_CODE', '2080130000')),
        'payroll_heslb_credit_gfs_code' => env('ERMS_MISC_PAYROLL_HESLB_CREDIT_GFS', env('ERMS_HESLB_GFS_CODE', '33181117')),
        'payroll_deductions_credit_account_code' => env('ERMS_MISC_PAYROLL_DEDUCTIONS_CREDIT_ACCOUNT', env('ERMS_DEDUCTIONS_ACCOUNT_CODE', '2080500000')),
        'payroll_deductions_credit_gfs_code' => env('ERMS_MISC_PAYROLL_DEDUCTIONS_CREDIT_GFS', env('ERMS_DEDUCTIONS_GFS_CODE', '33181117')),
        'payroll_loans_credit_account_code' => env('ERMS_MISC_PAYROLL_LOANS_CREDIT_ACCOUNT', env('ERMS_LOANS_ACCOUNT_CODE', '2080500000')),
        'payroll_loans_credit_gfs_code' => env('ERMS_MISC_PAYROLL_LOANS_CREDIT_GFS', env('ERMS_LOANS_GFS_CODE', '33181117')),
        'payroll_arrears_credit_account_code' => env('ERMS_MISC_PAYROLL_ARREARS_CREDIT_ACCOUNT', env('ERMS_ARREARS_ACCOUNT_CODE', '2080500000')),
        'payroll_arrears_credit_gfs_code' => env('ERMS_MISC_PAYROLL_ARREARS_CREDIT_GFS', env('ERMS_ARREARS_GFS_CODE', '33181117')),
    ],

    /**
     * Net-pay payable bank buckets (one ERMS payment request per bucket).
     * Match is case-insensitive substring against bank short_name, bank_name, swift_code, etc.
     * Unmatched banks go to other_banks (combined batch, disbursed via CRDB).
     */
    'payroll_net_pay_bank_buckets' => [
        'crdb' => [
            'label' => 'CRDB',
            'source_ref_suffix' => 'NET-CRDB',
            'match' => array_filter(array_map('trim', explode(',', (string) env('ERMS_NET_PAY_CRDB_MATCH', 'CRDB')))),
            'pay_bank_account_number' => env('ERMS_NET_PAY_CRDB_PAY_BANK_ACCOUNT')
                ?: env('ERMS_NET_PAY_CRDB_PAYER_BANK_ACCOUNT')
                ?: env('ERMS_PAYABLE_CRDB_PAY_BANK_ACCOUNT')
                ?: env('ERMS_PAYABLE_CRDB_PAYER_BANK_ACCOUNT', '01J1028249500'),
        ],
        'nbc' => [
            'label' => 'NBC',
            'source_ref_suffix' => 'NET-NBC',
            'match' => array_filter(array_map('trim', explode(',', (string) env('ERMS_NET_PAY_NBC_MATCH', 'NBC')))),
            'pay_bank_account_number' => env('ERMS_NET_PAY_NBC_PAY_BANK_ACCOUNT')
                ?: env('ERMS_NET_PAY_NBC_PAYER_BANK_ACCOUNT', '011103035722'),
        ],
        'nmb' => [
            'label' => 'NMB',
            'source_ref_suffix' => 'NET-NMB',
            'match' => array_filter(array_map('trim', explode(',', (string) env('ERMS_NET_PAY_NMB_MATCH', 'NMB')))),
            'pay_bank_account_number' => env('ERMS_NET_PAY_NMB_PAY_BANK_ACCOUNT')
                ?: env('ERMS_NET_PAY_NMB_PAYER_BANK_ACCOUNT', '20106600118'),
        ],
        'other_banks' => [
            'label' => env('ERMS_NET_PAY_OTHER_BANKS_LABEL', 'Other Banks'),
            'source_ref_suffix' => env('ERMS_NET_PAY_OTHER_BANKS_SOURCE_REF_SUFFIX', 'NET-OTHER-BANKS'),
            'match' => [],
            'pay_bank_account_number' => env('ERMS_NET_PAY_OTHER_BANKS_PAY_BANK_ACCOUNT', '01J1028249500'),
        ],
    ],

    /**
     * Deduction type → ERMS credit account (used by PayrollRunMiscellaneousMapper via DeductionAccountResolver).
     * Match is case-insensitive against deduction_type.deduction_code and display name.
     */
    'payroll_deduction_accounts' => [
        'psssf' => [
            'match' => array_filter(array_map('trim', explode(',', (string) env('ERMS_DEDUCTION_PSSSF_MATCH', 'psssf')))),
        ],
        'heslb' => [
            'match' => array_filter(array_map('trim', explode(',', (string) env('ERMS_DEDUCTION_HESLB_MATCH', 'heslb')))),
        ],
        'paye' => [
            'match' => array_filter(array_map('trim', explode(',', (string) env('ERMS_DEDUCTION_PAYE_MATCH', 'paye')))),
        ],
    ],

    /**
     * Deduction / statutory payable behaviour (see PayrollDeductionPayableMapper).
     * Add a kind here + matching erms.payroll_institution_payees.{kind}.
     * Deduction payables share one credit GL: payable_supplier_* in payable_settings.
     */
    'payroll_deduction_payables_execution_enabled' => env('ERMS_PAYROLL_DEDUCTION_PAYABLES_ENABLED', false),

    'payroll_deduction_payables' => [
        'paye' => [
            'enabled' => true,
            'aliases' => ['payee'],
            'label' => env('ERMS_PAYROLL_PAYE_LABEL', 'PAYE'),
            'source_ref_suffix' => env('ERMS_PAYROLL_PAYE_SOURCE_REF_SUFFIX', 'PAYE'),
            'amount_source' => 'transaction_column',
            'transaction_column' => 'paye',
            'debit_strategy' => 'auto',
            'credit_account_key' => 'paye',
        ],
        'psssf' => [
            'enabled' => true,
            'label' => env('ERMS_PAYROLL_PSSSF_LABEL', 'PSSSF'),
            'source_ref_suffix' => env('ERMS_PAYROLL_PSSSF_SOURCE_REF_SUFFIX', 'PSSSF'),
            'amount_source' => 'psssf_split',
            'debit_strategy' => 'auto',
            'credit_account_key' => 'psssf',
        ],
        'heslb' => [
            'enabled' => true,
            'label' => env('ERMS_PAYROLL_HESLB_LABEL', 'HESLB'),
            'source_ref_suffix' => env('ERMS_PAYROLL_HESLB_SOURCE_REF_SUFFIX', 'HESLB'),
            'amount_source' => 'deduction_items',
            'debit_strategy' => 'auto',
            'credit_account_key' => 'heslb',
        ],
    ],

    /**
     * Institution beneficiaries for split payroll payables (PSSSF, HESLB, PAYE, …).
     * Used by PayrollDeductionPayableMapper + PayrollInstitutionPayeeMapper.
     */
    'payroll_institution_payees' => [
        'psssf' => [
            'label' => env('ERMS_PAYROLL_PSSSF_LABEL', 'PSSSF'),
            'source_ref_suffix' => env('ERMS_PAYROLL_PSSSF_SOURCE_REF_SUFFIX', 'PSSSF'),
            'client_type' => env('ERMS_PAYROLL_PSSSF_CLIENT_TYPE', 'PUBLIC_INSTITUTION'),
            'name' => env('ERMS_PAYROLL_PSSSF_NAME', 'Public Service Social Security Fund'),
            'code' => env('ERMS_PAYROLL_PSSSF_CODE', 'PSSSF'),
            'email' => env('ERMS_PAYROLL_PSSSF_EMAIL', 'info@psssf.go.tz'),
            'phone' => env('ERMS_PAYROLL_PSSSF_PHONE', '255222157324'),
            'address' => env('ERMS_PAYROLL_PSSSF_ADDRESS', ''),
            'tin' => env('ERMS_PAYROLL_PSSSF_TIN', ''),
            'bank_account_number' => env('ERMS_PAYROLL_PSSSF_BANK_ACCOUNT', ''),
            'bank_account_name' => env('ERMS_PAYROLL_PSSSF_BANK_ACCOUNT_NAME', ''),
            'bank_code' => env('ERMS_PAYROLL_PSSSF_BANK_CODE', ''),
            'branch_code' => env('ERMS_PAYROLL_PSSSF_BRANCH_CODE', ''),
            'bank_name' => env('ERMS_PAYROLL_PSSSF_BANK_NAME', ''),
            'branch_name' => env('ERMS_PAYROLL_PSSSF_BRANCH_NAME', ''),
        ],
        'heslb' => [
            'label' => env('ERMS_PAYROLL_HESLB_LABEL', 'HESLB'),
            'source_ref_suffix' => env('ERMS_PAYROLL_HESLB_SOURCE_REF_SUFFIX', 'HESLB'),
            'client_type' => env('ERMS_PAYROLL_HESLB_CLIENT_TYPE', 'PUBLIC_INSTITUTION'),
            'name' => env('ERMS_PAYROLL_HESLB_NAME', 'Higher Education Students Loans Board'),
            'code' => env('ERMS_PAYROLL_HESLB_CODE', 'HESLB'),
            'email' => env('ERMS_PAYROLL_HESLB_EMAIL', 'info@heslb.go.tz'),
            'phone' => env('ERMS_PAYROLL_HESLB_PHONE', '255222113512'),
            'address' => env('ERMS_PAYROLL_HESLB_ADDRESS', ''),
            'tin' => env('ERMS_PAYROLL_HESLB_TIN', ''),
            'bank_account_number' => env('ERMS_PAYROLL_HESLB_BANK_ACCOUNT', ''),
            'bank_account_name' => env('ERMS_PAYROLL_HESLB_BANK_ACCOUNT_NAME', ''),
            'bank_code' => env('ERMS_PAYROLL_HESLB_BANK_CODE', ''),
            'branch_code' => env('ERMS_PAYROLL_HESLB_BRANCH_CODE', ''),
            'bank_name' => env('ERMS_PAYROLL_HESLB_BANK_NAME', ''),
            'branch_name' => env('ERMS_PAYROLL_HESLB_BRANCH_NAME', ''),
        ],
        'paye' => [
            'label' => env('ERMS_PAYROLL_PAYE_LABEL', 'PAYE'),
            'source_ref_suffix' => env('ERMS_PAYROLL_PAYE_SOURCE_REF_SUFFIX', 'PAYE'),
            'client_type' => env('ERMS_PAYROLL_PAYE_CLIENT_TYPE', 'PUBLIC_INSTITUTION'),
            'name' => env('ERMS_PAYROLL_PAYE_NAME', 'Tanzania Revenue Authority'),
            'code' => env('ERMS_PAYROLL_PAYE_CODE', 'TRA'),
            'email' => env('ERMS_PAYROLL_PAYE_EMAIL', 'info@tra.go.tz'),
            'phone' => env('ERMS_PAYROLL_PAYE_PHONE', '255262600000'),
            'address' => env('ERMS_PAYROLL_PAYE_ADDRESS', ''),
            'tin' => env('ERMS_PAYROLL_PAYE_TIN', ''),
            'bank_account_number' => env('ERMS_PAYROLL_PAYE_BANK_ACCOUNT', ''),
            'bank_account_name' => env('ERMS_PAYROLL_PAYE_BANK_ACCOUNT_NAME', ''),
            'bank_code' => env('ERMS_PAYROLL_PAYE_BANK_CODE', ''),
            'branch_code' => env('ERMS_PAYROLL_PAYE_BRANCH_CODE', ''),
            'bank_name' => env('ERMS_PAYROLL_PAYE_BANK_NAME', ''),
            'branch_name' => env('ERMS_PAYROLL_PAYE_BRANCH_NAME', ''),
        ],
    ],

    // Payable API (signed body: data + signature).
    'payable_settings' => [

        'payable_bridge_operating_account_code' => env('ERMS_PAYABLE_BRIDGE_OPERATING_ACCOUNT_CODE', '5025010000'),
        'payable_bridge_operating_gfs_code' => env('ERMS_PAYABLE_BRIDGE_OPERATING_GFS_CODE', '21111108'),

        // Payable supplier accounts and GFS codes (PAYE, PSSSF, HESLB, …).
        'payable_supplier_account_code' => env('ERMS_PAYABLE_SUPPLIER_ACCOUNT_CODE', '2080220000'),
        'payable_supplier_gfs_code' => env('ERMS_PAYABLE_SUPPLIER_GFS_CODE', '33181117'),

        // Payable staff (overtime) accounts and GFS codes.
        'payable_staff_account_code' => env('ERMS_PAYABLE_STAFF_ACCOUNT_CODE', '2080230000'),
        'payable_staff_gfs_code' => env('ERMS_PAYABLE_STAFF_GFS_CODE', '33181117'),
        
        'benefits_account_code' => env('ERMS_BENEFITS_ACCOUNT_CODE', '2080500000'),
        'deductions_account_code' => env('ERMS_DEDUCTIONS_ACCOUNT_CODE', '2080500000'),
        'loans_account_code' => env('ERMS_LOANS_ACCOUNT_CODE', '2080500000'),
        'arrears_account_code' => env('ERMS_ARREARS_ACCOUNT_CODE', '2080500000'),
        
        'benefits_gfs_code' => env('ERMS_BENEFITS_GFS_CODE', '33181117'),
        'deductions_gfs_code' => env('ERMS_DEDUCTIONS_GFS_CODE', '33181117'),
        'loans_gfs_code' => env('ERMS_LOANS_GFS_CODE', '33181117'),
        'arrears_gfs_code' => env('ERMS_ARREARS_GFS_CODE', '33181117'),


        'paye_account_code' => env('ERMS_PAYE_ACCOUNT_CODE', '2080030000'),
        'paye_gfs_code' => env('ERMS_PAYE_GFS_CODE', '33181117'),

        'psssf_account_code' => env('ERMS_PSSSF_ACCOUNT_CODE', '2080050000'),
        'psssf_gfs_code' => env('ERMS_PSSSF_GFS_CODE', '33181117'),
        'debit_psssf_employer_account_code' => env('ERMS_DEBIT_PSSSF_EMPLOYER_ACCOUNT_CODE','5021050000'),

        'heslb_account_code' => env('ERMS_HESLB_ACCOUNT_CODE', '2080130000'),
        'heslb_gfs_code' => env('ERMS_HESLB_GFS_CODE', '33181117'),

        'net_pay_account_code' => env('ERMS_NET_PAY_ACCOUNT_CODE', '2080500000'),
        'net_pay_gfs_code' => env('ERMS_NET_PAY_GFS_CODE', '33181117'),

        'description' => env('ERMS_PAYABLE_DESCRIPTION', 'Overtime Payment'),
        'department_code' => env('ERMS_PAYABLE_DEPARTMENT_CODE', '104060'),
        'branch_code' => env('ERMS_PAYABLE_BRANCH_CODE', env('ERMS_BRANCH_CODE', '322')),
        'business_line_code' => env('ERMS_PAYABLE_BUSINESS_LINE_CODE', env('ERMS_MISC_PAYROLL_BUSINESS_LINE_CODE', '100')),
        'sub_activity_salary_code' => env('ERMS_PAYABLE_SUB_ACTIVITY_SALARY_CODE', '1040601010'),
        'sub_activity_overtime_code' => env('ERMS_PAYABLE_SUB_ACTIVITY_OVERTIME_CODE', '1040601030'),
        'payment_type' => env('ERMS_PAYABLE_PAYMENT_TYPE', 'STAFF_ALLOWANCE'),
        'request_item_gfs_code' => env('ERMS_PAYABLE_REQUEST_ITEM_GFS_CODE', '21111108'),
        'distribution_gfs_code' => env('ERMS_PAYABLE_DISTRIBUTION_GFS_CODE', '21111108'),
        'client_type' => env('ERMS_PAYABLE_CLIENT_TYPE', 'INDIVIDUAL'),
        'client_category' => env('ERMS_PAYABLE_CLIENT_CATEGORY', 'VENDOR'),
        'loyalty_type' => env('ERMS_PAYABLE_LOYALTY_TYPE', 'RECURRING'),
        'group_code' => env('ERMS_PAYABLE_GROUP_CODE', 'STAFF_ALLOWANCE'),
        'retirable' => env('ERMS_PAYABLE_RETIRABLE', false),
        'default_bank_code' => env('ERMS_PAYABLE_DEFAULT_BANK_CODE'),  
        'default_branch_code' => env('ERMS_PAYABLE_DEFAULT_BRANCH_CODE', '322'),
        'default_bank_name' => env('ERMS_PAYABLE_DEFAULT_BANK_NAME'),
        'default_email' => env('ERMS_PAYABLE_DEFAULT_EMAIL'),
        'default_client_address' => env('ERMS_PAYABLE_DEFAULT_CLIENT_ADDRESS'),
        'phone_country_prefix' => env('ERMS_PAYABLE_PHONE_COUNTRY_PREFIX', '255'),

        'default_pay_bank_account_number' => env('ERMS_PAYABLE_DEFAULT_PAY_BANK_ACCOUNT', '01J1028249500'),
        'default_payer_bank_account_number' => env('ERMS_PAYABLE_DEFAULT_PAYER_BANK_ACCOUNT', env('ERMS_PAYABLE_DEFAULT_PAY_BANK_ACCOUNT', '01J1028249500')),
        'payroll_net_pay_default_pay_bank_account_number' => env('ERMS_PAYROLL_NET_PAY_DEFAULT_PAY_BANK_ACCOUNT', env('ERMS_PAYABLE_DEFAULT_PAYER_BANK_ACCOUNT', '01J1028249500')),

        'payable_callback_url' => env('ERMS_PAYABLE_CALLBACK_URL', $bmsPublicUrl('payable/callback')),

        'overtime_document_url' => env('ERMS_OVERTIME_DOCUMENT_URL', $bmsPublicUrl('overtime/overtime-document/')),

        'payroll_jv_document_url' => env('ERMS_PAYROLL_JV_DOCUMENT_URL', $bmsPublicUrl('payroll/jv-document/')),
        'payroll_payee_document_url' => env('ERMS_PAYROLL_PAYEE_DOCUMENT_URL', $bmsPublicUrl('payroll/payee-document/')),
        'payroll_psssf_document_url' => env('ERMS_PAYROLL_PSSSF_DOCUMENT_URL', $bmsPublicUrl('payroll/psssf-document/')),
        'payroll_heslb_document_url' => env('ERMS_PAYROLL_HESLB_DOCUMENT_URL', $bmsPublicUrl('payroll/heslb-document/')),
        'payroll_net_pay_document_url' => env('ERMS_PAYROLL_NET_PAY_DOCUMENT_URL', $bmsPublicUrl('payroll/net-pay-document/')),
        'payroll_minutes_document_url' => env('ERMS_PAYROLL_MINUTES_DOCUMENT_URL', $bmsPublicUrl('payroll/minutes-document/')),
    ],

    // GePG fallback values used when model fields are empty.
    'gepg' => [
        'currency' => env('ERMS_GEPG_CURRENCY', 'TZS'),
        'gfs_code' => env('ERMS_GEPG_GFS_CODE'),
        'vote_code' => env('ERMS_GEPG_VOTE_CODE'),
        'sp_code' => env('ERMS_GEPG_SP_CODE'),
        'sub_sp_code' => env('ERMS_GEPG_SUB_SP_CODE'),
    ],

    'paths' => [
        'private_pfx' => storage_path((string) env('ERMS_PRIVATE_PFX', 'keys/erms_clientprivate.pfx')),
        'public_pfx' => storage_path((string) env('ERMS_PUBLIC_PFX', 'keys/erms_clientpublic.pfx')),
        'private_pem' => storage_path((string) env('ERMS_PRIVATE_PEM', 'keys/erms_clientprivate.pem')),

        'auth_token' => $pathAuthToken,
        'create_invoice' => $pathInvoiceCreate,
        'update_invoice' => $pathInvoiceUpdate,
        'create_receipt' => $pathReceiptCreate,
        'cancel_receipt' => $pathReceiptCancel,
        'submit_sale' => $pathSubmitSale,
        'create_sale_receipt' => $pathCreateSaleReceipt,
        'cancel_sale_receipt' => $pathInvoiceReceiptCancelSale,
        'miscellaneous_entries' => $pathMiscellaneousEntries,
    ],

    'urls' => [
        'auth_token' => $fullUrl($pathAuthToken),
        'create_invoice' => $fullUrl($pathInvoiceCreate),
        'update_invoice' => $fullUrl($pathInvoiceUpdate),
        'create_receipt' => $fullUrl($pathReceiptCreate),
        'cancel_receipt' => $fullUrl($pathReceiptCancel),
        'submit_sale' => $fullUrl($pathSubmitSale),
        'create_sale_receipt' => $fullUrl($pathCreateSaleReceipt),
        'cancel_sale_receipt' => $fullUrl($pathInvoiceReceiptCancelSale),
        'miscellaneous_entries' => $fullUrl($pathMiscellaneousEntries),
        'create_payable_payment_request' => $fullUrl($pathCreatePayablePaymentRequest),
        'member_register' => $pathMemberRegister !== '' ? $fullUrl($pathMemberRegister) : '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled retry of failed / non-posted sale receipts (not payables)
    |--------------------------------------------------------------------------
    */
    'receipt_retry' => [
        'enabled' => filter_var(env('ERMS_RECEIPT_RETRY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'limit_per_source' => (int) env('ERMS_RECEIPT_RETRY_LIMIT', 500),
        'trx_dt_tm_from' => env('ERMS_RECEIPT_RETRY_TRX_DT_TM_FROM', '2026-07-01 00:00:00'),
        'schedule_every_minutes' => (int) env('ERMS_RECEIPT_RETRY_SCHEDULE_MINUTES', 5),
        'overlap_minutes' => (int) env('ERMS_RECEIPT_RETRY_OVERLAP_MINUTES', 15),
        'sources' => [
            'overload_fine' => [
                'enabled' => filter_var(env('ERMS_RECEIPT_RETRY_OVERLOAD_FINE', true), FILTER_VALIDATE_BOOLEAN),
                'label' => 'overload fine',
                'table' => 'overload_fine',
                'order_column' => 'trx_dt_tm',
            ],
            'incident_fine' => [
                'enabled' => filter_var(env('ERMS_RECEIPT_RETRY_INCIDENT_FINE', true), FILTER_VALIDATE_BOOLEAN),
                'label' => 'incident fine',
                'table' => 'incident_fine',
                'order_column' => 'trx_dt_tm',
            ],
            'bridge_bill' => [
                'enabled' => filter_var(env('ERMS_RECEIPT_RETRY_BRIDGE_BILL', true), FILTER_VALIDATE_BOOLEAN),
                'label' => 'bridge bill',
                'table' => 'bridge_bills',
                'order_column' => 'trx_dt_tm',
            ],
            'event_payment' => [
                'enabled' => filter_var(env('ERMS_RECEIPT_RETRY_EVENT_PAYMENT', true), FILTER_VALIDATE_BOOLEAN),
                'label' => 'event payment',
                'table' => 'event_payment',
                'order_column' => 'trx_dt_tm',
            ],
            'top_up' => [
                'enabled' => filter_var(env('ERMS_RECEIPT_RETRY_TOP_UP', true), FILTER_VALIDATE_BOOLEAN),
                'label' => 'top up (prepayment)',
                'table' => 'top_up',
                'order_column' => 'trx_dt_tm',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled retry of failed / non-posted cashless toll miscellaneous entries
    |--------------------------------------------------------------------------
    */
    'miscellaneous_retry' => [
        'enabled' => filter_var(env('ERMS_MISC_RETRY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'limit' => (int) env('ERMS_MISC_RETRY_LIMIT', 500),
        'created_at_from' => env('ERMS_MISC_RETRY_CREATED_AT_FROM', '2026-07-01 00:00:00'),
        'order_column' => 'created_at',
        'schedule_every_minutes' => (int) env('ERMS_MISC_RETRY_SCHEDULE_MINUTES', 5),
        'overlap_minutes' => (int) env('ERMS_MISC_RETRY_OVERLAP_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled retry of failed / non-posted TBS bundle miscellaneous entries
    |--------------------------------------------------------------------------
    | Posts deferred revenue recognition for expired bundle subscriptions.
    | Deactivation (status -> inactive) is owned by bundle:check-expiration;
    | this only picks subscriptions already inactive (status = 2) and not yet
    | posted to ERMS (erms_status != 1).
    */
    'bundle_retry' => [
        'enabled' => filter_var(env('ERMS_BUNDLE_RETRY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'limit' => (int) env('ERMS_BUNDLE_RETRY_LIMIT', 500),
        'expire_date_from' => env('ERMS_BUNDLE_RETRY_EXPIRE_DATE_FROM', '2026-07-01 00:00:00'),
        'schedule_every_minutes' => (int) env('ERMS_BUNDLE_RETRY_SCHEDULE_MINUTES', 5),
        'overlap_minutes' => (int) env('ERMS_BUNDLE_RETRY_OVERLAP_MINUTES', 15),
    ],

];
