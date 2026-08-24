<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanRepayment extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'employee_loan_repayment';

    protected $primaryKey = 'employee_loan_repayment_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_loan_id',
        'repayment_date',
        'payroll_month',
        'payroll_year',
        'payroll_number',
        'repayment_total_amount',
        'repayment_principal_amount',
        'repayment_interest_amount',
        'opening_outstanding_balance_amount',
        'closing_outstanding_balance_amount',
        'repayment_source',
        'payment_reference_number',
        'notes',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'employee_loan_id' => 'integer',
        'repayment_date' => 'date',
        'payroll_month' => 'integer',
        'payroll_year' => 'integer',
        'repayment_total_amount' => 'decimal:2',
        'repayment_principal_amount' => 'decimal:2',
        'repayment_interest_amount' => 'decimal:2',
        'opening_outstanding_balance_amount' => 'decimal:2',
        'closing_outstanding_balance_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id', 'employee_loan_id');
    }
}

