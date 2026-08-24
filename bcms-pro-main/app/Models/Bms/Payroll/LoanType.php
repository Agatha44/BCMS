<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanType extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'loan_type';

    protected $primaryKey = 'loan_type_id';

    public $timestamps = false;

    protected $fillable = [
        'loan_name',
        'loan_code',
        'has_interest',
        'interest_percentage',
        'interest_calculation_method',
        'minimum_loan_amount',
        'maximum_loan_amount',
        'minimum_repayment_months',
        'maximum_repayment_months',
        'is_active',
        'priority',
        'contract_type',
        'department_section',
        'job_title_position',
        'start_date',
        'end_date',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'has_interest' => 'boolean',
        'interest_percentage' => 'decimal:2',
        'minimum_loan_amount' => 'decimal:2',
        'maximum_loan_amount' => 'decimal:2',
        'minimum_repayment_months' => 'integer',
        'maximum_repayment_months' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}
