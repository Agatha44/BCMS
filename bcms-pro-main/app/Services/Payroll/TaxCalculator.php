<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\TaxBracket;
use Carbon\Carbon;

class TaxCalculator
{
    /**
     * tax = base_tax_amount + (taxable_income - taxable_income_range_start) * (tax_rate_percentage / 100)
     *
     * @return array{tax: float, bracket_id: int|null, rate: float, base: float}
     */
    public function calculatePaye(float $taxableIncome, ?Carbon $asOf = null): array
    {
        $income = max(0, round($taxableIncome, 2));
        $asOf = ($asOf ?? Carbon::now())->startOfDay();

        if ($income <= 0) {
            return ['tax' => 0.0, 'bracket_id' => null, 'rate' => 0.0, 'base' => 0.0];
        }

        $bracket = TaxBracket::query()
            ->active()
            ->effectiveAsOf($asOf)
            ->where('taxable_income_range_start', '<=', $income)
            ->where('taxable_income_range_end', '>=', $income)
            ->orderByDesc('taxable_income_range_start')
            ->first();

        if (! $bracket) {
            return ['tax' => 0.0, 'bracket_id' => null, 'rate' => 0.0, 'base' => 0.0];
        }

        $rangeFrom = (float) $bracket->taxable_income_range_start;
        $rate = (float) $bracket->tax_rate_percentage;
        $base = (float) $bracket->base_tax_amount;

        $tax = $base + max(0, $income - $rangeFrom) * ($rate / 100);

        return [
            'tax' => round(max(0, $tax), 2),
            'bracket_id' => (int) $bracket->tax_bracket_id,
            'rate' => $rate,
            'base' => $base,
        ];
    }
}

