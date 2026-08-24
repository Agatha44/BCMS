<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

class EmployeeDeduction extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'employee_deduction';

    protected $primaryKey = 'employee_deduction_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_national_id',
        'deduction_type_id',
        'total_deduction_amount',
        'employee_contribution_percentage',
        'employee_contribution_amount',
        'employer_contribution_percentage',
        'employer_contribution_amount',
        'is_before_tax',
        'taxable_amount',
        'tax_free_amount',
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
        'deduction_type_id' => 'integer',
        'total_deduction_amount' => 'decimal:2',
        'employee_contribution_percentage' => 'decimal:2',
        'employee_contribution_amount' => 'decimal:2',
        'employer_contribution_percentage' => 'decimal:2',
        'employer_contribution_amount' => 'decimal:2',
        'is_before_tax' => 'boolean',
        'taxable_amount' => 'decimal:2',
        'tax_free_amount' => 'decimal:2',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }

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
}

