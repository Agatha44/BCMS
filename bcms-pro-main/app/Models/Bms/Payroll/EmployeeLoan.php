<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;

class EmployeeLoan extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'employee_loan';

    protected $primaryKey = 'employee_loan_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_national_id',
        'loan_type_id',
        'loan_reference_number',
        'loan_issue_date',
        'repayment_start_date',
        'principal_amount',
        'total_interest_amount',
        'repayment_period_months',
        'monthly_principal_amount',
        'monthly_interest_amount',
        'monthly_total_repayment_amount',
        'total_repaid_amount',
        'outstanding_balance_amount',
        'loan_status',
        'effective_start_date',
        'effective_end_date',
        'is_active',
        'notes',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'loan_type_id' => 'integer',
        'loan_issue_date' => 'date',
        'repayment_start_date' => 'date',
        'principal_amount' => 'decimal:2',
        'total_interest_amount' => 'decimal:2',
        'repayment_period_months' => 'integer',
        'monthly_principal_amount' => 'decimal:2',
        'monthly_interest_amount' => 'decimal:2',
        'monthly_total_repayment_amount' => 'decimal:2',
        'total_repaid_amount' => 'decimal:2',
        'outstanding_balance_amount' => 'decimal:2',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeEffectiveForPeriod(Builder $query, CarbonInterface $periodStart, CarbonInterface $periodEnd): Builder
    {
        return $query
            ->where(function (Builder $q) use ($periodEnd) {
                $q->whereNull('effective_start_date')
                    ->orWhereDate('effective_start_date', '<=', $periodEnd);
            })
            ->where(function (Builder $q) use ($periodStart) {
                $q->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $periodStart);
            });
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(EmployeeLoanRepayment::class, 'employee_loan_id', 'employee_loan_id');
    }
}

