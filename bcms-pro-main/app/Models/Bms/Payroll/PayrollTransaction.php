<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollTransaction extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'payroll_transaction';

    public $timestamps = false;

    protected $fillable = [
        'payroll_run_id',
        'national_id',
        'payroll_month',
        'payroll_year',
        'payroll_number',
        'pf_number',
        'bank_id',
        'account_number',
        'department_id',
        'scheme_id',
        'basic_salary',
        'total_arrears',
        'total_benefits',
        'total_deductions',
        'psssf_contribution',
        'psssf_employer_contribution',
        'total_loans',
        'gross_pay',
        'taxable_pay',
        'paye',
        'net_pay',
        'erms_status',
        'erms_submitted_at',
        'erms_reference',
    ];

    protected $casts = [
        'payroll_run_id' => 'integer',
        'payroll_month' => 'integer',
        'payroll_year' => 'integer',
        'bank_id' => 'integer',
        'department_id' => 'integer',
        'scheme_id' => 'integer',
        'basic_salary' => 'decimal:2',
        'total_arrears' => 'decimal:2',
        'total_benefits' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'psssf_contribution' => 'decimal:2',
        'psssf_employer_contribution' => 'decimal:2',
        'total_loans' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'taxable_pay' => 'decimal:2',
        'paye' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'erms_status' => 'integer',
        'erms_submitted_at' => 'datetime',
    ];
}

