<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Collections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function TollCollection(Request $request)
    {
        $collection_type = $request->collection_type;

        if ($collection_type == 'bundle') {
            $from_date = $request->from_date;
            $to_date = $request->to_date;
            return Collections::getBundleCollection($from_date, $to_date, $collection_type);
        } elseif ($collection_type == 'cash') {
            $from_date = $request->from_date;
            $to_date = $request->to_date;
            return Collections::getCashCollection($from_date, $to_date, $collection_type);

        } elseif ($collection_type == 'topUp') {
            $from_date = $request->from_date;
            $to_date = $request->to_date;
            return Collections::getPrepaymentCollection($from_date, $to_date, $collection_type);
        } else
            return false;

    }

    public function IncidentCollection(Request $request)
    {
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        if ($from_date != "" || $to_date != "") {
            return Collections::getIncidentCollection($from_date, $to_date);
        }
    }

    public function OverloadCollection(Request $request)
    {
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        if ($from_date != "" || $to_date != "") {
            return Collections::getOverloadCollection($from_date, $to_date);
        }
    }

    public function EventCollection(Request $request)
    {
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        if ($from_date != "" || $to_date != "") {
            return Collections::getEventCollection($from_date, $to_date);
        }
        return false;
    }

    public function OverallMonthlyCollection(Request $request)
    {
        $year = $request->year;
        if ($year != "") {
            return Collections::getOverallMonthlyCollections($year);
        }
        return false;
    }

    public function OverallYearCollection(Request $request)
    {
        $yearFrom = $request->year_from;
        $yearTo = $request->year_to;
        if ($yearTo != "" || $yearFrom != "") {
            return Collections::getYearCollection($yearFrom, $yearTo);
        }
        return false;
    }

    public function OverallProjectReport(Request $request)
    {
        $year = $request->year;

        if ($year != null) {
            return Collections::getOverallProjectReport($year);
        }
        return false;

    }

    public function accountPassage(Request $request)
    {
        $accountNo = $request->account_no;

        $query = DB::table('vehicle as v')
            ->select(DB::raw("DISTINCT a.account_no, CONCAT(a.first_name, ' ', a.surname) AS name, v.plate_no, v.card_number, bsp.arrival_time AS passageTime"))
            ->join('bundle_subscription_passage as bsp', 'bsp.card_number', '=', 'v.card_number')
            ->join('account as a', 'a.account_no', '=', 'v.account_no')
            ->where('a.account_no', $accountNo)
            ->union(function ($query) use ($accountNo) {
                $query->select(DB::raw("DISTINCT a.account_no, CONCAT(a.first_name, ' ', a.middle_name, ' ', a.surname) AS name, v.plate_no, v.card_number, tt.created_at AS passageTime"))
                    ->from('toll_transaction as tt')
                    ->join('vehicle as v', 'v.account_no', '=', 'tt.account_no')
                    ->join('account as a', 'a.account_no', '=', 'v.account_no')
                    ->where('a.account_no', $accountNo);
            });

        $querySql = $query->orderBy('passageTime', 'desc')->limit(5)->toSql();
        $results = DB::table(DB::raw("($querySql) as a"))
            ->mergeBindings($query)
            ->orderBy('passageTime', 'desc')
            ->get();

        return response()->json(['data' => $results]);

    }

    /**
     * Shift Collection Report
     * 
     * Returns collection report for a specific shift on a specific date.
     * Handles evening shift (shift_id = 3) with special time range (18:00 to next day 09:00)
     * and regular shifts (shift_id = 1 or 2) for full day.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Request Parameters:
     * - shift_id (required): Shift ID (1, 2, or 3)
     * - shift_date (required): Date in Y-m-d format (e.g., '2024-01-15')
     * 
     * Response:
     * {
     *   "status": 1,
     *   "data": [
     *     {
     *       "first_name": "John",
     *       "middle_name": "M",
     *       "surname": "Doe",
     *       "booth": "A1",
     *       "Collection": 150000.00
     *     }
     *   ],
     *   "shift": "Evening",
     *   "shift_date": "2024-01-15",
     *   "billingAmount": 500000.00
     * }
     */
    public function shiftCollectionReport(Request $request)
    {
        try {
            // Validate required parameters
            $request->validate([
                'shift_id' => 'required|integer|in:1,2,3',
                'shift_date' => 'required|date_format:Y-m-d'
            ]);

            $shift_id = $request->shift_id;
            $shift_date = $request->shift_date;

            // Calculate date range based on shift_id
            if ($shift_id == 3) {
                // Evening shift: 18:00:00 on shift_date to 09:00:00 next day
                $from_date = $shift_date . ' 18:00:00';
                $to_date = date('Y-m-d', strtotime($shift_date . ' +1 day')) . ' 09:00:00';
            } else {
                // Day/Morning shift: Full day (00:00:00 to 23:59:59)
                $from_date = $shift_date . ' 00:00:00';
                $to_date = $shift_date . ' 23:59:59';
            }

            // Get shift name
            $shift = DB::table('shift')
                ->where('id', $shift_id)
                ->first();

            $shift_name = $shift ? $shift->name : 'Unknown';

            // Query 1: Get billing amount (total collection)
            $billingAmount = DB::table('toll_transaction as tt')
                ->where('tt.shift_id', $shift_id)
                ->where('tt.trans_type', 'CASH')
                ->whereNull('tt.status')
                ->whereBetween('tt.created_at', [$from_date, $to_date])
                ->sum('tt.charged_amount');

            $billingAmount = $billingAmount ?? 0.00;

            // Query 2: Get detailed collection report grouped by operator and booth
            $data = DB::table('toll_transaction as tt')
                ->select(
                    DB::raw("COALESCE(au.first_name, '') as first_name"),
                    DB::raw("COALESCE(au.middle_name, '') as middle_name"),
                    DB::raw("COALESCE(au.surname, '') as surname"),
                    DB::raw("COALESCE(l.lane_no, 'N/A') as booth"),
                    DB::raw("COALESCE(SUM(tt.charged_amount), 0) as Collection")
                )
                ->leftJoin('auth_user as au', 'au.id', '=', 'tt.created_by')
                ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
                ->where('tt.shift_id', $shift_id)
                ->where('tt.trans_type', 'CASH')
                ->whereNull('tt.status')
                ->whereBetween('tt.created_at', [$from_date, $to_date])
                ->groupBy(
                    'tt.created_by',
                    'tt.lane_id',
                    'au.first_name',
                    'au.middle_name',
                    'au.surname',
                    'l.lane_no'
                )
                ->orderBy('au.surname')
                ->orderBy('au.first_name')
                ->orderBy('l.lane_no')
                ->get();

            // Format the response
            if ($data->count() > 0) {
                return response()->json([
                    'status' => 1,
                    'data' => $data,
                    'shift' => $shift_name,
                    'shift_date' => $shift_date,
                    'billingAmount' => number_format($billingAmount, 2, '.', '')
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'No records found for the specified shift and date',
                    'data' => [],
                    'shift' => $shift_name,
                    'shift_date' => $shift_date,
                    'billingAmount' => '0.00'
                ]);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while generating the report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Toll Passes with Pagination and Filters
     * 
     * Returns combined toll passes from both bundle subscriptions and toll transactions
     * with comprehensive filtering and pagination support.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * Request Parameters:
     * - account_no (required): Account number to filter passes
     * - from_date (optional): Start date filter (Y-m-d H:i:s or Y-m-d)
     * - to_date (optional): End date filter (Y-m-d H:i:s or Y-m-d)
     * - pass_type (optional): Filter by pass type ('Bundle', 'CASH', 'CASHLESS', 'Prepayment')
     *   Note: CASHLESS transactions are returned as 'Prepayment' in the response
     * - plate_no (optional): Filter by plate number
     * - lane_no (optional): Filter by lane number
     * - body_type_id (optional): Filter by body type ID
     * - page (optional): Page number (default: 1)
     * - per_page (optional): Items per page (default: 15, max: 100)
     * - sort_by (optional): Sort field (default: 'pass_date') - Options: 'pass_date', 'plate_no', 'lane_no', 'pass_type', 'pass_charge'
     * - sort_order (optional): Sort direction ('asc' or 'desc', default: 'desc')
     * 
     * Response:
     * {
     *   "status": 1,
     *   "message": "Toll passes retrieved successfully",
     *   "account": {
     *     "account_no": "ACC001",
     *     "firstName": "John",
     *     "middleName": "Doe",
     *     "surname": "Smith"
     *   },
     *   "data": [
     *     {
     *       "plateNumber": "T123ABC",
     *       "bodyType": "Car",
     *       "laneNo": "A1",
     *       "id": 123,
     *       "paymentMethod": "Bundle",
     *       "paymentReceipt": "N/A",
     *       "passDate": "2024-01-15 10:30:00",
     *       "paymentAmount": "Bundle"
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 100,
     *     "last_page": 7,
     *     "from": 1,
     *     "to": 15,
     *     "has_more": true
     *   }
     * }
     */
    public function getTollPasses(Request $request)
    {
        try {
            // Validate required parameters
            $request->validate([
                'account_no' => 'required|string',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'pass_type' => 'nullable|string|in:Bundle,CASH,CASHLESS,Prepayment',
                'plate_no' => 'nullable|string',
                'lane_no' => 'nullable|string',
                'body_type_id' => 'nullable|integer',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:pass_date,plate_no,lane_no,pass_type,pass_charge,passDate,plateNumber,laneNo,paymentMethod,paymentAmount',
                'sort_order' => 'nullable|string|in:asc,desc'
            ]);

            $account_no = $request->account_no;
            $from_date = $request->from_date;
            $to_date = $request->to_date;
            $pass_type = $request->pass_type;
            $plate_no = $request->plate_no;
            $lane_no = $request->lane_no;
            $body_type_id = $request->body_type_id;

            // Pagination parameters
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 15), 100); // Max 100 per page
            $sortBy = $request->get('sort_by', 'pass_date');
            $sortOrder = $request->get('sort_order', 'desc');

            // Build SQL query with proper parameter binding
            $sql = "
                SELECT
                    v.plate_no AS plateNumber,
                    COALESCE(bt.name, 'N/A') AS bodyType,
                    COALESCE(l.lane_no, 'N/A') AS laneNo,
                    bsp.id AS id,
                    'Bundle' AS paymentMethod,
                    'N/A' AS paymentReceipt,
                    bsp.arrival_time AS passDate,
                    'N/A' AS paymentAmount
                FROM bundle_subscription_passage bsp
                LEFT JOIN lane l ON l.id = bsp.lane_id
                LEFT JOIN vehicle v ON v.card_number = bsp.card_number
                LEFT JOIN account_vehicle av ON av.vehicle_id = v.id
                LEFT JOIN body_type bt ON v.body_type_id = bt.id
                WHERE v.account_no = ?
            ";

            $bindings = [$account_no];

            // Apply filters for bundle query
            if ($from_date) {
                $sql .= " AND bsp.arrival_time >= ?";
                $bindings[] = $from_date;
            }
            if ($to_date) {
                $sql .= " AND bsp.arrival_time <= ?";
                $bindings[] = $to_date . ' 23:59:59';
            }
            if ($pass_type && $pass_type !== 'Bundle') {
                // Exclude bundle passes if filtering for other types
                $sql .= " AND 1 = 0";
            }
            if ($plate_no) {
                $sql .= " AND v.plate_no LIKE ?";
                $bindings[] = "%{$plate_no}%";
            }
            if ($lane_no) {
                $sql .= " AND l.lane_no LIKE ?";
                $bindings[] = "%{$lane_no}%";
            }
            if ($body_type_id) {
                $sql .= " AND v.body_type_id = ?";
                $bindings[] = $body_type_id;
            }

            $sql .= "
                UNION ALL

                SELECT
                    tt.plate_no AS plateNumber,
                    COALESCE(bt.name, 'N/A') AS bodyType,
                    COALESCE(l.lane_no, 'N/A') AS laneNo,
                    tt.id AS id,
                    CASE 
                        WHEN tt.trans_type = 'CASHLESS' THEN 'Prepayment'
                        ELSE COALESCE(tt.trans_type, 'N/A')
                    END AS paymentMethod,
                    COALESCE(tt.receipt_num, 'N/A') AS paymentReceipt,
                    tt.created_at AS passDate,
                    COALESCE(tt.charged_amount, 0) AS paymentAmount
                FROM toll_transaction tt
                LEFT JOIN vehicle v ON v.plate_no = tt.plate_no
                LEFT JOIN body_type bt ON bt.id = v.body_type_id
                LEFT JOIN lane l ON l.id = tt.lane_id
                WHERE tt.account_no = ?
            ";

            $bindings[] = $account_no;

            // Apply filters for toll transaction query
            if ($from_date) {
                $sql .= " AND tt.created_at >= ?";
                $bindings[] = $from_date;
            }
            if ($to_date) {
                $sql .= " AND tt.created_at <= ?";
                $bindings[] = $to_date . ' 23:59:59';
            }
            if ($pass_type && $pass_type !== 'Bundle') {
                // Convert "Prepayment" to "CASHLESS" for filtering
                $filterType = ($pass_type === 'Prepayment') ? 'CASHLESS' : $pass_type;
                $sql .= " AND tt.trans_type = ?";
                $bindings[] = $filterType;
            } elseif ($pass_type === 'Bundle') {
                // Exclude toll transactions if filtering for Bundle only
                $sql .= " AND 1 = 0";
            }
            if ($plate_no) {
                $sql .= " AND tt.plate_no LIKE ?";
                $bindings[] = "%{$plate_no}%";
            }
            if ($lane_no) {
                $sql .= " AND l.lane_no LIKE ?";
                $bindings[] = "%{$lane_no}%";
            }
            if ($body_type_id) {
                $sql .= " AND v.body_type_id = ?";
                $bindings[] = $body_type_id;
            }

            // Wrap in subquery for counting and pagination
            $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as combined";
            $total = DB::selectOne($countSql, $bindings)->total;

            // Validate and sanitize sort parameters
            // Map user-friendly sort fields to database column names
            $sortFieldMap = [
                'pass_date' => 'passDate',
                'plate_no' => 'plateNumber',
                'lane_no' => 'laneNo',
                'pass_type' => 'paymentMethod',
                'pass_charge' => 'paymentAmount',
                // Also support camelCase field names
                'passDate' => 'passDate',
                'plateNumber' => 'plateNumber',
                'laneNo' => 'laneNo',
                'paymentMethod' => 'paymentMethod',
                'paymentAmount' => 'paymentAmount'
            ];
            
            $allowedSortFields = array_keys($sortFieldMap);
            $sortBy = in_array($sortBy, $allowedSortFields) ? $sortFieldMap[$sortBy] : 'passDate';
            $sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

            // Ensure pagination values are integers
            $perPage = (int)$perPage;
            $offset = (int)(($page - 1) * $perPage);

            // Add sorting and pagination
            $sql .= " ORDER BY {$sortBy} {$sortOrder}";
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";

            $results = DB::select($sql, $bindings);

            // Get account details
            $account = DB::table('account')
                ->where('account_no', $account_no)
                ->select(
                    'account_no',
                    'first_name',
                    'middle_name',
                    'surname'
                )
                ->first();

            // Return account details with separate name fields
            $accountDetails = [
                'account_no' => $account ? $account->account_no : $account_no,
                'firstName' => $account ? ($account->first_name ?? '') : '',
                'middleName' => $account ? ($account->middle_name ?? '') : '',
                'surname' => $account ? ($account->surname ?? '') : ''
            ];

            // Calculate pagination metadata
            $lastPage = ceil($total / $perPage);
            $from = $total > 0 ? $offset + 1 : 0;
            $to = min($offset + $perPage, $total);

            return response()->json([
                'status' => 1,
                'message' => 'Toll passes retrieved successfully',
                'account' => $accountDetails,
                'data' => $results,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $from,
                    'to' => $to,
                    'has_more' => $page < $lastPage
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while retrieving toll passes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
