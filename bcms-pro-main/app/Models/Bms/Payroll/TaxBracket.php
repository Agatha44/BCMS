<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;

class TaxBracket extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'tax_brackets';

    protected $primaryKey = 'tax_bracket_id';

    public $timestamps = false;

    protected $fillable = [
        'taxable_income_range_start',
        'taxable_income_range_end',
        'tax_rate_percentage',
        'base_tax_amount',
        'is_active',
        'effective_start_date',
        'effective_end_date',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'taxable_income_range_start' => 'decimal:2',
        'taxable_income_range_end' => 'decimal:2',
        'tax_rate_percentage' => 'decimal:4',
        'base_tax_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeEffectiveAsOf(Builder $query, CarbonInterface $asOf): Builder
    {
        return $query
            ->where(function (Builder $q) use ($asOf) {
                $q->whereNull('effective_start_date')
                    ->orWhereDate('effective_start_date', '<=', $asOf);
            })
            ->where(function (Builder $q) use ($asOf) {
                $q->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            });
    }
}

