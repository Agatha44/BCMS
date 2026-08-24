<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

class EmployeeBenefit extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'employee_benefit';

    protected $primaryKey = 'employee_benefit_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_national_id',
        'benefit_type_id',
        'benefit_amount',
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
        'benefit_type_id' => 'integer',
        'benefit_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_free_amount' => 'decimal:2',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function benefitType(): BelongsTo
    {
        return $this->belongsTo(BenefitType::class, 'benefit_type_id', 'benefit_type_id');
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

