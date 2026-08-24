<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payroll report catalog
    |--------------------------------------------------------------------------
    |
    | Keys are passed as report_type on POST /api/payroll/reports.
    | Labels and descriptions drive the frontend report-type dropdown.
    | document links each report type to PayrollDocumentService for PDF download.
    |
    */
    'types' => [
        'netpay' => [
            'enabled' => true,
            'label' => 'Net pay report',
            'description' => 'Employee net pay disbursement by salary bank',
            'sort_order' => 1,
            'document' => 'net_pay',
            'aliases' => ['net_pay', 'net-pay'],
            'filters' => ['bank'],
        ],
        'paye' => [
            'enabled' => true,
            'label' => 'PAYE report',
            'description' => 'Pay As You Earn tax deductions',
            'sort_order' => 2,
            'document' => 'payee',
            'aliases' => ['payee'],
            'filters' => [],
        ],
        'psssf' => [
            'enabled' => true,
            'label' => 'PSSSF report',
            'description' => 'Public Service Social Security Fund contributions',
            'sort_order' => 3,
            'document' => 'psssf',
            'aliases' => [],
            'filters' => [],
        ],
        'heslb' => [
            'enabled' => true,
            'label' => 'HESLB report',
            'description' => 'Higher Education Students Loans Board deductions',
            'sort_order' => 4,
            'document' => 'heslb',
            'aliases' => [],
            'filters' => [],
        ],
        'other' => [
            'enabled' => true,
            'label' => 'Other deductions',
            'description' => 'Non-statutory deductions and loan repayments',
            'sort_order' => 5,
            'aliases' => [],
            'filters' => [],
        ],
        'loans' => [
            'enabled' => true,
            'label' => 'Staff loans report',
            'description' => 'Payroll loan deductions by employee and loan type',
            'sort_order' => 6,
            'document' => 'loans',
            'aliases' => [],
            'filters' => [],
        ],
    ],

];
