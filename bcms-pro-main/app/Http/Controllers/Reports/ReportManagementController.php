<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Http\Controllers\Controller;
use App\Models\ReportsManagement\Report;
use App\Models\ReportsManagement\ReportCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportManagementController extends ConfigurationController
{
    public function reportCategories()
    {
        $response = ReportCategory::all();
        if ($response) {
            return $this->sendResponse($response, 'Successfully retrieved');
        } else {
            return $this->sendError('Something went Wrong');
        }
    }

    public function reportStats(Request $request)
    {
        $category_id = $request->category_id;
        $stats = DB::table('report_statistics')
            ->select('id', 'name', 'value')
            ->where('category_id', $category_id)
            ->get();

        if ($stats->count() > 0) {

            return $this->sendResponse($stats, 'Successfully Retrieved');
        } else {
            return $this->sendError("Something went wrong");
        }

    }

    public function getReportsByCategory(Request $request)
    {
        $category_id = $request->category_id;
        $reports = Report::select('reports.id', DB::raw('CASE WHEN reports.status = 1 THEN "Active" ELSE "Inactive" END AS status'),
            'reports.name as reportName', 'report_categories.name as categoryName', 'reports.last_run_at','reports.data_fields as columns','data_source')
            ->join('report_categories', 'reports.category_id', '=', 'report_categories.id')
            ->where('reports.category_id', '=', $category_id)
            ->get();
        return $this->sendResponse($reports, 'Successfully Retrieved');

    }

}
