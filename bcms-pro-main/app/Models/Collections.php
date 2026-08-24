<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Collections extends Model
{
    use HasFactory;

    public static function getBundleCollection($from_date, $to_date, $collection_type)
    {
        $body_type = null;
        $results = DB::table('bridge_bills as bs')
            ->join('vehicle as v', 'bs.dist_param', '=', 'v.plate_no')
            ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->join('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
            ->whereNotNull('bundle_id')
            ->whereBetween(DB::raw('DATE(bs.bill_gen_at)'), [Carbon::parse($from_date), Carbon::parse($to_date)])
            ->when($body_type, function ($query, $body_type) {
                return $query->where('v.body_type_id', $body_type);
            })
            ->whereNotNull('bs.trx_dt_tm')
            ->selectRaw("bt.name as body_type,
                  SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN 1 ELSE 0 END) AS DailyBundlePassage,
                  SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN 1 ELSE 0 END) AS WeeklyBundlePassage,
                  SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN 1 ELSE 0 END) AS MonthlyBundlePassage,
                  COUNT(*) AS TotalVehicle,
                  SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN bs.bill_amount ELSE 0 END) AS DailyBundleAmount,
                  SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN bs.bill_amount ELSE 0 END) AS WeeklyBundleAmount,
                  SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN bs.bill_amount ELSE 0 END) AS MonthlyBundleAmount,
                  SUM(bs.bill_amount) AS total_amount")
            ->groupBy('bt.name')
            ->orderBy('bt.name')
            ->get();

        $response = ['dateFrom' => $from_date, 'dateTo' => $to_date, 'collectionType' => $collection_type, 'bodyType' => [],
        ];

        foreach ($results as $result) {
            $body_type = ['name' => $result->body_type, 'daily' => ['passage' => $result->DailyBundlePassage, 'amount' => $result->DailyBundleAmount,],
                'weekly' => ['passage' => $result->WeeklyBundlePassage, 'amount' => $result->WeeklyBundleAmount,],
                'monthly' => ['passage' => $result->MonthlyBundlePassage, 'amount' => $result->MonthlyBundleAmount,],
            ];
            array_push($response['bodyType'], $body_type);
        }

        return response()->json($response);

    }

    public static function getCashCollection($from_date, $to_date, $collection_type)
    {


        $results = DB::table('toll_transaction as tt')
            ->join('body_type as bt', 'tt.body_type_id', '=', 'bt.id')
            ->select('bt.name as body_type', DB::raw('count(tt.id) as passage'), DB::raw('sum(tt.charged_amount) as amount'))
            ->whereBetween(DB::raw('date(tt.created_at)'), [DB::raw('IFNULL(?, tt.created_at)'), DB::raw('IFNULL(?, tt.created_at)')])
            ->where('tt.trans_type', '!=', 'CASHLESS')
            ->groupBy('tt.body_type_id')
            ->setBindings([$from_date, $to_date, 'CASHLESS'], 'where')
            ->get();

        $body_types = [];
        foreach ($results as $toll_transaction) {
            $body_type = [
                'name' => $toll_transaction->body_type,
                'passage' => $toll_transaction->passage,
                'amount' => $toll_transaction->amount
            ];
            $body_types[] = $body_type;
        }

        $response = [
            'dateFrom' => $from_date,
            'dateTo' => $to_date,
            'collectionType' => 'cash',
            'bodyType' => $body_types
        ];

        return response()->json($response);

    }

    public static function getPrepaymentCollection($from_date, $to_date, $collection_type)
    {

        // get the toll transaction data
        $toll_transactions = DB::table('toll_transaction')
            ->join('vehicle', 'toll_transaction.vehicle_id', '=', 'vehicle.id')
            ->join('body_type', 'vehicle.body_type_id', '=', 'body_type.id')
            ->select('body_type.name as name', DB::raw('count(toll_transaction.id) as passage'), DB::raw('sum(toll_transaction.charged_amount) as earned'))
            ->whereBetween(DB::raw('date(toll_transaction.created_at)'), [$from_date, $to_date])
            ->where('toll_transaction.trans_type', '=', 'CASHLESS')
            ->where('toll_transaction.receipt_num', '!=', 'EXEMPTED')
            ->groupBy('body_type.name')
            ->get();

        // get the top-up data
        $deposits = DB::table('top_up')
            ->select(DB::raw('sum(top_up.bill_amount) as deposits'))
            ->whereBetween(DB::raw('date(top_up.bill_gen_at)'), [$from_date, $to_date])
            ->whereNotNull('top_up.psp_name')
            ->whereNotNull('top_up.trx_id')
            ->get()[0]->deposits;

        $body_types = [];
        foreach ($toll_transactions as $toll_transaction) {
            $body_type = [
                'name' => $toll_transaction->name,
                'passage' => $toll_transaction->passage,
                'earned' => $toll_transaction->earned
            ];
            $body_types[] = $body_type;
        }

        $response = [
            'dateFrom' => $from_date,
            'dateTo' => $to_date,
            'collectionType' => 'topUp',
            'deposits' => $deposits,
            'bodyType' => $body_types
        ];

        return response()->json($response);


    }

    public static function getIncidentCollection($from_date, $to_date): JsonResponse
    {
        $incidents = DB::table('incident_fine as inf')
            ->join('incident_nature as ini', 'inf.nature_incident', '=', 'ini.id')
            ->select('ini.name as name', DB::raw('COUNT(*) as incident_count'), DB::raw('SUM(inf.amount) as total_amount'))
            ->whereBetween('inf.incident_date', [$from_date, $to_date])
            ->groupBy('ini.name')
            ->get();

        $response = [
            "dateFrom" => $from_date,
            "dateTo" => $to_date,
            "incidentType" => []
        ];

        foreach ($incidents as $incident) {
            $data = [
                "name" => $incident->name,
                "incidentCount" => $incident->incident_count,
                "totalAmount" => $incident->total_amount
            ];
            array_push($response["incidentType"], $data);
        }

        return response()->json($response);

    }

    public static function getOverloadCollection($from_date, $to_date)
    {
        $overloads = DB::table('overload_fine as of')
            ->select(
                DB::raw('COUNT(*) as incident_count'),
                DB::raw('ROUND(SUM(bill_amount)) as amount_collected')
            )
            ->whereNotNull('trx_id')
            ->whereBetween('of.bill_gen_at', [$from_date, $to_date])
            ->get();

        $data = [
            'dateFrom' => $from_date,
            'dateTo' => $to_date,
            'bodyType' => []
        ];

        foreach ($overloads as $overload) {
            $body_type_data =
                [
                    'name' => 'Semi Trailer',
                    'overloadCount' => $overload->incident_count,
                    'amountCollected' => $overload->amount_collected
                ];
            array_push($data["bodyType"], $body_type_data);

        }

        return response()->json($data);
    }

    public static function getEventCollection($from_date, $to_date): JsonResponse
    {
        $events = DB::table('event_payment as ep')
            ->select(
                DB::raw('COUNT(*) as event_count'),
                DB::raw('ROUND(SUM(amount)) as amount_collected')
            )
            ->whereNotNull('psp_name')
            ->whereBetween('ep.bill_gen_at', [$from_date, $to_date])
            ->get();

        $data = [
            'dateFrom' => $from_date,
            'dateTo' => $to_date,
            'eventType' => []
        ];

        foreach ($events as $event) {
            $event_data =
                [
                    'name' => 'photography and video recording',
                    'eventCount' => $event->event_count,
                    'amountCollected' => $event->amount_collected
                ];
            array_push($data["eventType"], $event_data);
        }
        return response()->json($data);
    }

    public static function getOverallMonthlyCollections($year)
    {

        #Run the five queries and store the results in variables
        $tollTransactions = DB::select("SELECT DATE_FORMAT(created_at, '%M') AS month, SUM(charged_amount) AS total_charged_amount FROM toll_transaction WHERE YEAR(created_at) = $year and receipt_num != 'EXEMPTED' GROUP BY month");
        $bridgeBills = DB::select("SELECT DATE_FORMAT(bill_gen_at, '%M') AS month, sum(bill_amount) total_charged_amount from bridge_bills where `bundle_id` is not null and trx_id is not null GROUP BY month");
        $incidentFines = DB::select("SELECT DATE_FORMAT(bill_gen_at, '%M') AS month, sum(amount) total_incident_fines_amount from incident_fine where YEAR(created_at) = $year and `trx_id` is not null and psp_name is not null GROUP BY month");
        $overloadFines = DB::select("SELECT DATE_FORMAT(bill_gen_at, '%M') AS month, sum(bill_amount) total_overload_fines_amount from overload_fine where YEAR(bill_gen_at) = $year and `trx_id` is not null and psp_name is not null GROUP BY month");
        $eventPayments = DB::select("SELECT DATE_FORMAT(bill_gen_at, '%M') AS month, sum(amount) total_events_amount from event_payment where YEAR(bill_gen_at) = $year and `trx_id` is not null and psp_name is not null GROUP BY month");

        #Merge the results into a single collection
        $data = collect($tollTransactions)
            ->merge($bridgeBills)
            ->merge($incidentFines)
            ->merge($overloadFines)
            ->merge($eventPayments);


        #Group the merged data by month
        $groupedData = $data->groupBy('month');

        #Map the grouped data to the desired JSON format
        $monthlySummary = $groupedData->map(function ($items, $month) {
            $totalChargedAmount = 0;
            $totalIncidentFinesAmount = 0;
            $totalOverloadFinesAmount = 0;
            $totalEventsAmount = 0;

            foreach ($items as $item) {
                if (isset($item->total_charged_amount)) {
                    $totalChargedAmount += $item->total_charged_amount;
                }
                if (isset($item->total_incident_fines_amount)) {
                    $totalIncidentFinesAmount += $item->total_incident_fines_amount;
                }
                if (isset($item->total_overload_fines_amount)) {
                    $totalOverloadFinesAmount += $item->total_overload_fines_amount;
                }
                if (isset($item->total_events_amount)) {
                    $totalEventsAmount += $item->total_events_amount;
                }
            }

            return [
                'month' => $month,
                'tollCollections' => $totalChargedAmount,
                'incidentFines' => $totalIncidentFinesAmount,
                'overloadFines' => $totalOverloadFinesAmount,
                'events' => $totalEventsAmount,
                'total' => $totalChargedAmount + $totalIncidentFinesAmount + $totalOverloadFinesAmount + $totalEventsAmount,
            ];
        })->values();

        # Initialize year summary totals
        $tollCollections = 0;
        $incidentFines = 0;
        $overloadFines = 0;
        $events = 0;

        #Loop through monthly summary and add up totals
        foreach ($monthlySummary as $month) {
            $tollCollections += intval(str_replace(',', '', $month['tollCollections']));
            $incidentFines += intval(str_replace(',', '', $month['incidentFines']));
            $overloadFines += intval(str_replace(',', '', $month['overloadFines']));
            $events += intval(str_replace(',', '', $month['events']));
        }

        #Build year summary array
        $yearSummary = [
            'tollCollections' => $tollCollections,
            'incidentFines' => $incidentFines,
            'overloadFines' => $overloadFines,
            'events' => $events
        ];
        #Generate the final JSON response
        $response = ['year' => $year, 'monthlySummary' => $monthlySummary, 'yearSummary' => $yearSummary];
        return response()->json($response);

    }

    public static function getYearCollection($yearFrom, $yearTo)
    {

        $tollCollections = DB::table('toll_transaction')
            ->select(DB::raw('YEAR(created_at) AS year, SUM(charged_amount) AS total'))
            ->whereBetween(DB::raw('YEAR(created_at)'), [$yearFrom, $yearTo])
            ->where('receipt_num', '!=', 'EXEMPTED')
            ->groupBy(DB::raw('YEAR(created_at)'))
            ->get();

        $incidentFines = DB::table('incident_fine')
            ->select(DB::raw('YEAR(bill_gen_at) AS year, SUM(amount) AS total'))
            ->whereBetween(DB::raw('YEAR(bill_gen_at)'), [$yearFrom, $yearTo])
            ->whereNotNull('trx_id')
            ->whereNotNull('psp_name')
            ->groupBy(DB::raw('YEAR(bill_gen_at)'))
            ->get();

        $overloadFines = DB::table('overload_fine')
            ->select(DB::raw('YEAR(bill_gen_at) AS year, SUM(bill_amount) AS total'))
            ->whereBetween(DB::raw('YEAR(bill_gen_at)'), [$yearFrom, $yearTo])
            ->whereNotNull('trx_id')
            ->whereNotNull('psp_name')
            ->groupBy(DB::raw('YEAR(bill_gen_at)'))
            ->get();

        $events = DB::table('event_payment')
            ->select(DB::raw('YEAR(bill_gen_at) AS year, SUM(amount) AS total'))
            ->whereBetween(DB::raw('YEAR(bill_gen_at)'), [$yearFrom, $yearTo])
            ->whereNotNull('trx_id')
            ->whereNotNull('psp_name')
            ->groupBy(DB::raw('YEAR(bill_gen_at)'))
            ->get();

// combine the results
        $yearSummary = [];
        foreach (range($yearFrom, $yearTo) as $year) {
            $yearSummary[] = [
                'year' => $year,
                'tollCollections' => $tollCollections->where('year', $year)->sum('total'),
                'incidentFines' => $incidentFines->where('year', $year)->sum('total'),
                'overloadFines' => $overloadFines->where('year', $year)->sum('total'),
                'events' => $events->where('year', $year)->sum('total'),
                'total' => 0,
            ];
        }

// calculate the totals
        $grandSummary = [
            'tollCollections' => $tollCollections->sum('total'),
            'incidentFines' => $incidentFines->sum('total'),
            'overloadFines' => $overloadFines->sum('total'),
            'events' => $events->sum('total'),
            'total' => 0,
        ];

// calculate the year totals
        foreach ($yearSummary as &$year) {
            $year['total'] = $year['tollCollections'] + $year['incidentFines'] + $year['overloadFines'] + $year['events'];
            $grandSummary['total'] += $year['total'];
        }

// create the final result
        $result = [
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
            'yearSummary' => $yearSummary,
            'grandSummary' => $grandSummary,
        ];

// return the result as JSON
        return response()->json($result);

    }

    public static function getOverallProjectReport($year)
    {

        $projects = DB::table('PROJECTS')
            ->join('PROJECT_TYPES', 'PROJECTS.project_type_id', '=', 'PROJECT_TYPES.id')
            ->where('PROJECTS.period', 'LIKE', "$year%")
            ->select('PROJECTS.period', 'PROJECT_TYPES.name', 'PROJECTS.planned_cost', 'PROJECTS.actual_cost')
            ->get();

        $response = [
            'period' => $projects[0]->period,
            'project_type' => [],
            'total_cost' => [
                'planned_cost' => 0,
                'actual_cost' => 0,
            ],
        ];

        foreach ($projects as $project) {
            $response['project_type'][] = [
                'name' => $project->name,
                'planned_cost' => $project->planned_cost,
                'actual_cost' => $project->actual_cost,
            ];

            $response['total_cost']['planned_cost'] += $project->planned_cost;
            $response['total_cost']['actual_cost'] += $project->actual_cost;
        }

        return response()->json($response);

    }


}
