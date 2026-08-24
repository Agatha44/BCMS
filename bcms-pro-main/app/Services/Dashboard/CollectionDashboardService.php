<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionDashboardService
{
    private function percentChange(float $current, float $previous): string
    {
        if ($previous <= 0) {
            return $current > 0 ? '+100%' : '+0%';
        }
        $pct = (($current - $previous) / $previous) * 100;
        $sign = $pct >= 0 ? '+' : '';

        return $sign . number_format($pct, 1) . '%';
    }

    private function tollCount(Carbon $start, Carbon $end, ?string $transType = null): int
    {
        $query = DB::table('toll_transaction')
            ->whereNull('status')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        if ($transType !== null) {
            $query->where('trans_type', $transType);
        }

        return (int) $query->count();
    }

    private function bundlePassageCount(Carbon $start, Carbon $end): int
    {
        return (int) DB::table('bundle_subscription_passage')
            ->whereBetween('arrival_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();
    }

    public function kpis(): array
    {
        $now = Carbon::now();
        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $prevStart = $now->copy()->subMonth()->startOfMonth();
        $prevEnd = $now->copy()->subMonth()->endOfMonth();

        $cashCurrent = $this->tollCount($currentStart, $currentEnd, 'CASH');
        $cashPrev = $this->tollCount($prevStart, $prevEnd, 'CASH');

        $prepayCurrent = $this->tollCount($currentStart, $currentEnd, 'CASHLESS');
        $prepayPrev = $this->tollCount($prevStart, $prevEnd, 'CASHLESS');

        $bundleCurrent = $this->bundlePassageCount($currentStart, $currentEnd);
        $bundlePrev = $this->bundlePassageCount($prevStart, $prevEnd);

        $totalCurrent = $cashCurrent + $prepayCurrent + $bundleCurrent;
        $totalPrev = $cashPrev + $prepayPrev + $bundlePrev;

        return [
            'total_passages' => [
                'value' => $totalCurrent,
                'change' => $this->percentChange($totalCurrent, $totalPrev),
            ],
            'bundle_passages' => [
                'value' => $bundleCurrent,
                'change' => $this->percentChange($bundleCurrent, $bundlePrev),
            ],
            'prepayment_passages' => [
                'value' => $prepayCurrent,
                'change' => $this->percentChange($prepayCurrent, $prepayPrev),
            ],
            'cash_passages' => [
                'value' => $cashCurrent,
                'change' => $this->percentChange($cashCurrent, $cashPrev),
            ],
            'period_label' => 'last month',
        ];
    }

    public function bodyTypePerformance(string $period): array
    {
        $now = Carbon::now();
        if ($period === 'week') {
            $start = $now->copy()->startOfWeek();
            $end = $now->copy()->endOfDay();
        } elseif ($period === 'month') {
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfDay();
        } else {
            $start = $now->copy()->startOfDay();
            $end = $now->copy()->endOfDay();
            $period = 'today';
        }

        $tollRows = DB::table('toll_transaction as tt')
            ->join('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->whereNull('tt.status')
            ->whereBetween('tt.created_at', [$start, $end])
            ->whereNotNull('tt.body_type_id')
            ->groupBy('bt.id', 'bt.name')
            ->selectRaw('bt.name as name, COUNT(tt.id) as value')
            ->get();

        $bundleRows = DB::table('bundle_subscription_passage as bsp')
            ->join('vehicle as v', 'v.card_number', '=', 'bsp.card_number')
            ->join('body_type as bt', 'bt.id', '=', 'v.body_type_id')
            ->whereBetween('bsp.arrival_time', [$start, $end])
            ->groupBy('bt.id', 'bt.name')
            ->selectRaw('bt.name as name, COUNT(bsp.id) as value')
            ->get();

        $merged = [];
        foreach ($tollRows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $merged[$name] = ($merged[$name] ?? 0) + (int) $row->value;
        }
        foreach ($bundleRows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $merged[$name] = ($merged[$name] ?? 0) + (int) $row->value;
        }

        $items = collect($merged)
            ->map(static fn ($value, $name) => ['name' => $name, 'value' => $value])
            ->filter(static fn ($row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->take(15)
            ->all();

        return [
            'period' => $period,
            'items' => $items,
        ];
    }

    public function lanePerformance(): array
    {
        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->endOfDay();

        $tollByLane = DB::table('toll_transaction as tt')
            ->join('lane as l', 'l.id', '=', 'tt.lane_id')
            ->whereNull('tt.status')
            ->whereBetween('tt.created_at', [$start, $end])
            ->groupBy('l.lane_no')
            ->selectRaw('UPPER(l.lane_no) as code, COUNT(tt.id) as count')
            ->pluck('count', 'code');

        $bundleByLane = DB::table('bundle_subscription_passage as bsp')
            ->join('lane as l', 'l.id', '=', 'bsp.lane_id')
            ->whereBetween('bsp.arrival_time', [$start, $end])
            ->groupBy('l.lane_no')
            ->selectRaw('UPPER(l.lane_no) as code, COUNT(bsp.id) as count')
            ->pluck('count', 'code');

        $codes = $tollByLane->keys()
            ->merge($bundleByLane->keys())
            ->unique()
            ->map(static fn ($code) => strtoupper((string) $code))
            ->filter(static fn ($code) => $code !== '');

        $lanes = $codes
            ->map(function ($code) use ($tollByLane, $bundleByLane) {
                $count = (int) ($tollByLane[$code] ?? 0) + (int) ($bundleByLane[$code] ?? 0);

                return [
                    'code' => $code,
                    'count' => $count,
                ];
            })
            ->filter(static fn ($lane) => $lane['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->take(14)
            ->all();

        return [
            'lanes' => $lanes,
            'as_of' => Carbon::now()->toIso8601String(),
        ];
    }

    public function tollTrends(int $fyStartYear): array
    {
        $fyStart = Carbon::create($fyStartYear, 7, 1)->startOfMonth();
        $fyEnd = Carbon::create($fyStartYear + 1, 6, 1)->endOfMonth();

        $passagesByMonth = DB::table('toll_transaction')
            ->whereNull('status')
            ->whereBetween('created_at', [$fyStart, $fyEnd])
            ->selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, COUNT(id) as passages, COALESCE(SUM(charged_amount), 0) as revenue')
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->get()
            ->keyBy(static fn ($row) => sprintf('%04d-%02d', $row->y, $row->m));

        $bundleByMonth = DB::table('bundle_subscription_passage')
            ->whereBetween('arrival_time', [$fyStart, $fyEnd])
            ->selectRaw('YEAR(arrival_time) as y, MONTH(arrival_time) as m, COUNT(id) as passages')
            ->groupBy(DB::raw('YEAR(arrival_time)'), DB::raw('MONTH(arrival_time)'))
            ->get()
            ->keyBy(static fn ($row) => sprintf('%04d-%02d', $row->y, $row->m));

        $monthLabels = ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        $passages = [];
        $revenue = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $fyStart->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $toll = $passagesByMonth[$key] ?? null;
            $bundle = $bundleByMonth[$key] ?? null;

            $passageCount = (int) (optional($toll)->passages ?? 0) + (int) (optional($bundle)->passages ?? 0);
            $passages[] = $passageCount;
            $revenue[] = (float) (optional($toll)->revenue ?? 0);
        }

        $totalPassages = array_sum($passages);
        $totalRevenue = array_sum($revenue);

        $prevFyStart = $fyStart->copy()->subYear();
        $prevFyEnd = $fyEnd->copy()->subYear();
        $prevPassages = (int) DB::table('toll_transaction')
            ->whereNull('status')
            ->whereBetween('created_at', [$prevFyStart, $prevFyEnd])
            ->count();
        $prevRevenue = (float) DB::table('toll_transaction')
            ->whereNull('status')
            ->whereBetween('created_at', [$prevFyStart, $prevFyEnd])
            ->sum('charged_amount');

        return [
            'fy_start_year' => $fyStartYear,
            'fy_label' => 'FY ' . $fyStartYear . '/' . ($fyStartYear + 1),
            'months' => $monthLabels,
            'passages' => [
                'data' => $passages,
                'headline' => number_format($totalPassages),
                'delta' => $this->percentChange($totalPassages, $prevPassages),
            ],
            'revenue' => [
                'data' => $revenue,
                'headline' => $this->formatRevenueHeadline($totalRevenue),
                'delta' => $this->percentChange($totalRevenue, $prevRevenue),
            ],
        ];
    }

    public function availableFinancialYears(): array
    {
        $currentStartYear = Carbon::now()->month >= 7
            ? Carbon::now()->year
            : Carbon::now()->year - 1;

        $years = [];
        for ($i = 0; $i < 3; $i++) {
            $y = $currentStartYear - $i;
            $years[] = [
                'start_year' => $y,
                'label' => 'FY ' . $y . '/' . ($y + 1),
            ];
        }

        return $years;
    }

    private function formatRevenueHeadline(float $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return 'TZS ' . rtrim(rtrim(number_format($amount / 1_000_000_000, 2), '0'), '.') . 'B';
        }
        if ($amount >= 1_000_000) {
            return 'TZS ' . round($amount / 1_000_000) . 'M';
        }

        return 'TZS ' . number_format($amount);
    }
}
