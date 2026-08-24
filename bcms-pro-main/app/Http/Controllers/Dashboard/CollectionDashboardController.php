<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BasicController;
use App\Services\Dashboard\CollectionDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CollectionDashboardController extends BasicController
{
    protected CollectionDashboardService $dashboard;

    public function __construct(CollectionDashboardService $dashboard)
    {
        $this->dashboard = $dashboard;
    }

    public function kpis(): JsonResponse
    {
        return $this->sendResponse($this->dashboard->kpis(), 'Collection dashboard KPIs');
    }

    public function bodyTypes(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'period' => 'nullable|in:today,week,month',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $period = $request->input('period', 'today');

        return $this->sendResponse(
            $this->dashboard->bodyTypePerformance($period),
            'Body type performance'
        );
    }

    public function lanePerformance(): JsonResponse
    {
        return $this->sendResponse($this->dashboard->lanePerformance(), 'Lane performance');
    }

    public function tollTrends(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'fy_start_year' => 'nullable|integer|min:2000|max:2100',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $year = (int) ($request->input('fy_start_year') ?: (
            now()->month >= 7 ? now()->year : now()->year - 1
        ));

        return $this->sendResponse($this->dashboard->tollTrends($year), 'Toll trends');
    }

    public function financialYears(): JsonResponse
    {
        return $this->sendResponse(
            ['years' => $this->dashboard->availableFinancialYears()],
            'Financial years'
        );
    }
}
