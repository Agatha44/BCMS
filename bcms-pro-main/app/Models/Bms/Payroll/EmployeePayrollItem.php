<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayrollItem extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'employee_payroll_items';

    public $timestamps = false;

    protected $fillable = [
        'national_id',
        'payroll_month',
        'payroll_year',
        'item_type',
        'source_table',
        'source_id',
        'type_table',
        'type_id',
        'name',
        'is_void',
        'void_reason',
        'void_at',
        'void_by',
        'amount',
        'taxed_amount',
        'taxfree_amount',
        'employee_amount',
        'employer_amount',
        'employee_percent',
        'employer_percent',
        'meta',
        'payroll_run_id',
        'created_at',
    ];

    protected $casts = [
        'payroll_month' => 'integer',
        'payroll_year' => 'integer',
        'amount' => 'decimal:2',
        'taxed_amount' => 'decimal:2',
        'taxfree_amount' => 'decimal:2',
        'employee_amount' => 'decimal:2',
        'employer_amount' => 'decimal:2',
        'employee_percent' => 'decimal:4',
        'employer_percent' => 'decimal:4',
        'meta' => 'array',
        'payroll_run_id' => 'integer',
        'void_at' => 'datetime',
        'void_by' => 'integer',
        'is_void' => 'boolean',
        'created_at' => 'datetime',
    ];
}
