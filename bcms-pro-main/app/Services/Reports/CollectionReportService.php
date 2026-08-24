<?php

namespace App\Services\Reports;

use App\Models\Collections;
use App\Support\Reports\ShiftWindow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CollectionReportService
{
    private static function operatorNameSql(string $alias = 'au'): string
    {
        return "TRIM(CONCAT(COALESCE({$alias}.first_name, ''), ' ', COALESCE({$alias}.middle_name, ''), ' ', COALESCE({$alias}.surname, ''))) AS operator_name";
    }

    public function resolveOperatorMeta(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $operator = DB::table('auth_user')->where('id', $userId)->first();
        if (!$operator) {
            return 'N/A';
        }

        $name = trim(implode(' ', array_filter([
            $operator->first_name ?? '',
            $operator->middle_name ?? '',
            $operator->surname ?? '',
        ])));

        return $name !== '' ? $name : 'N/A';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array{rows: \Illuminate\Support\Collection, pagination: array<string, int>}
     */
    protected function paginateQuery($query, array $params, string $countColumn): array
    {
        $perPage = min(max((int) ($params['per_page'] ?? 15), 1), 200);
        $page = max((int) ($params['page'] ?? 1), 1);

        $total = (clone $query)->count(DB::raw($countColumn));
        $rows = $query->forPage($page, $perPage)->get();

        return [
            'rows' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, (int) ceil($total / max($perPage, 1))),
            ],
        ];
    }

    public function dailyCollection(string $fromDate, string $toDate): Collection
    {
        $rows = DB::table('toll_transaction as tt')
            ->join('shift as s', 's.id', '=', 'tt.shift_id')
            ->selectRaw('s.name as shift, COUNT(tt.id) as count, COALESCE(SUM(tt.charged_amount), 0) as amount')
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$fromDate, $toDate])
            ->where('tt.shift_id', '!=', ShiftWindow::EVENING_SHIFT_ID)
            ->groupBy('tt.shift_id', 's.name')
            ->orderBy('s.name')
            ->get();

        $evening = $this->eveningShiftAggregate($fromDate, $toDate);
        if ($evening) {
            $rows->push((object) $evening);
        }

        return $rows;
    }

    public function dailyShiftCollection(string $fromDate, string $toDate, int $shiftId): Collection
    {
        if ($shiftId === ShiftWindow::EVENING_SHIFT_ID) {
            [$from, $to] = ShiftWindow::eveningBounds($fromDate);

            return DB::table('toll_transaction as tt')
                ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
                ->selectRaw('s.name, COUNT(tt.id) as count, COALESCE(SUM(tt.charged_amount), 0) as amount')
                ->whereBetween('tt.created_at', [$from, $to])
                ->where('tt.shift_id', ShiftWindow::EVENING_SHIFT_ID)
                ->whereNull('tt.status')
                ->where('tt.trans_type', 'CASH')
                ->groupBy('tt.shift_id', 's.name')
                ->get();
        }

        return DB::table('toll_transaction as tt')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->selectRaw('s.name, COUNT(tt.id) as count, COALESCE(SUM(tt.charged_amount), 0) as amount')
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$fromDate, $toDate])
            ->where('tt.trans_type', 'CASH')
            ->where('tt.shift_id', $shiftId)
            ->groupBy('tt.shift_id', 's.name')
            ->get();
    }

    public function bodyTypeCollection(
        string $fromDate,
        string $toDate,
        ?int $bodyTypeId = null,
        ?int $operatorId = null
    ): Collection {
        [$start, $end] = ShiftWindow::dateRangeBounds($fromDate, $toDate);

        $query = DB::table('toll_transaction as tt')
            ->join('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->join('price_list as pl', 'pl.body_type_id', '=', 'tt.body_type_id')
            ->selectRaw(
                'pl.amount AS fee, bt.name AS body_type, COUNT(tt.id) AS count, COALESCE(SUM(tt.charged_amount), 0) AS amount'
            )
            ->where('tt.created_at', '>=', $start)
            ->where('tt.created_at', '<', $end)
            ->groupBy('tt.body_type_id', 'pl.amount', 'bt.name')
            ->orderBy('bt.name');

        if ($bodyTypeId !== null) {
            $query->where('tt.body_type_id', $bodyTypeId);
        }
        if ($operatorId !== null) {
            $query->where('tt.created_by', $operatorId);
        }

        return $query->get();
    }

    public function boothCollection(string $fromDate, string $toDate, ?int $laneId = null): Collection
    {
        $query = DB::table('toll_transaction as tt')
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->selectRaw('l.lane_no AS lane, COUNT(tt.id) AS count, COALESCE(SUM(tt.charged_amount), 0) AS amount')
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$fromDate, $toDate])
            ->groupBy('tt.lane_id', 'l.lane_no')
            ->orderBy('l.lane_no');

        if ($laneId !== null) {
            $query->where('tt.lane_id', $laneId);
        }

        return $query->get();
    }

    public function bodyTypeAudit(
        string $fromDate,
        string $toDate,
        ?int $operatorId = null,
        array $params = []
    ): array {
        $query = DB::table('body_type_audit as bta')
            ->join('auth_user as au', 'au.id', '=', 'bta.created_by')
            ->join('vehicle as v', 'v.id', '=', 'bta.vehicle_id')
            ->join('body_type as bt', 'bt.id', '=', 'bta.previous_body_type')
            ->join('price_list as pl', 'pl.body_type_id', '=', 'bta.previous_body_type')
            ->join('body_type as bt2', 'bt2.id', '=', 'bta.new_body_type')
            ->join('price_list as pl2', 'pl2.body_type_id', '=', 'bta.new_body_type')
            ->selectRaw("
                bta.created_at AS date,
                CONCAT(au.first_name, ' ', au.surname) AS operator,
                v.plate_no AS plateno,
                bta.previous_body_type AS previos_bodytype,
                bt.name AS prev_body_type,
                pl.amount AS amount,
                bta.new_body_type AS current_bodytype,
                bt2.name AS current_body,
                pl2.amount AS current_amount,
                (pl.amount - pl2.amount) AS difference
            ")
            ->whereColumn('bta.previous_body_type', '!=', 'bta.new_body_type')
            ->whereBetween(DB::raw('DATE(bta.created_at)'), [$fromDate, $toDate]);

        if ($operatorId !== null) {
            $query->where('bta.created_by', $operatorId);
        }

        return $this->paginateQuery($query->orderByDesc('bta.created_at'), $params, 'bta.id');
    }

    public function exemptedVehicles(
        string $fromDate,
        string $toDate,
        ?int $operatorId = null,
        array $params = []
    ): array {
        $query = DB::table('toll_transaction as tt')
            ->leftJoin('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->leftJoin('auth_user as au', 'au.id', '=', 'tt.created_by')
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->selectRaw("
                CONCAT(au.first_name, ' ', au.surname) AS operator,
                tt.charged_amount AS amount,
                bt.name AS body_type,
                l.lane_no AS booth,
                s.name AS shift,
                tt.trans_type AS method,
                tt.plate_no,
                tt.created_at AS day
            ")
            ->where('tt.exemption', 1)
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$fromDate, $toDate]);

        if ($operatorId !== null) {
            $query->where('tt.created_by', $operatorId);
        }

        return $this->paginateQuery($query->orderByDesc('tt.created_at'), $params, 'tt.id');
    }

    public function dailyCashlessCollection(string $fromDate, string $toDate): Collection
    {
        return DB::table('toll_transaction as tt')
            ->selectRaw('COUNT(tt.id) AS Vehicles, COALESCE(SUM(tt.charged_amount), 0) AS AmountCollected, DATE(tt.created_at) AS Date')
            ->where('tt.trans_type', 'CASHLESS')
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(tt.created_at)'))
            ->orderBy('Date')
            ->get();
    }

    public function bodyCashlessCollection(string $fromDate, string $toDate): Collection
    {
        return DB::table('toll_transaction as tt')
            ->join('vehicle as v', 'v.id', '=', 'tt.vehicle_id')
            ->join('body_type as bt', 'bt.id', '=', 'v.body_type_id')
            ->selectRaw('COUNT(tt.id) AS Vehicles, COALESCE(SUM(tt.charged_amount), 0) AS AmountCollected, bt.name AS bodyType')
            ->where('tt.trans_type', 'CASHLESS')
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$fromDate, $toDate])
            ->groupBy('v.body_type_id', 'bt.name')
            ->orderBy('bt.name')
            ->get();
    }

    public function cancelledTransactions(
        string $fromDate,
        string $toDate,
        ?int $operatorId = null,
        array $params = []
    ): array {
        $query = DB::table('toll_transaction as tt')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->leftJoin('auth_user as au', 'au.id', '=', 'tt.updated_by')
            ->leftJoin('auth_user as aub', 'aub.id', '=', 'tt.created_by')
            ->selectRaw("
                s.name AS shift_name,
                tt.plate_no,
                tt.receipt_num,
                tt.charged_amount,
                tt.reason,
                CONCAT(aub.first_name, ' ', aub.surname) AS operator,
                CONCAT(au.first_name, ' ', au.surname) AS canceled_by
            ")
            ->whereBetween(DB::raw('DATE(tt.updated_at)'), [$fromDate, $toDate]);

        if ($operatorId !== null) {
            $query->where('tt.updated_by', $operatorId);
        }

        return $this->paginateQuery($query->orderByDesc('tt.updated_at'), $params, 'tt.id');
    }

    public function bundleCollectionReport(
        string $fromDate,
        string $toDate,
        ?int $bodyTypeId = null
    ): Collection {
        $query = DB::table('bridge_bills as bs')
            ->join('vehicle as v', 'bs.dist_param', '=', 'v.plate_no')
            ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->join('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
            ->selectRaw("
                bt.name AS body_type,
                SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN 1 ELSE 0 END) AS Daily_Bundle,
                SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN 1 ELSE 0 END) AS Weekly_Bundle,
                SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN 1 ELSE 0 END) AS Monthly_Bundle,
                COUNT(*) AS Total_Vehicle,
                SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN bs.bill_amount ELSE 0 END) AS Daily_Bundle_Amount,
                SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN bs.bill_amount ELSE 0 END) AS Weekly_Bundle_Amount,
                SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN bs.bill_amount ELSE 0 END) AS Monthly_Bundle_Amount,
                COALESCE(SUM(bs.bill_amount), 0) AS total_amount
            ")
            ->whereNotNull('bs.bundle_id')
            ->whereBetween(DB::raw('DATE(bs.bill_gen_at)'), [$fromDate, $toDate])
            ->whereNotNull('bs.trx_dt_tm')
            ->groupBy('bt.name')
            ->orderBy('bt.name');

        if ($bodyTypeId !== null) {
            $query->where('v.body_type_id', $bodyTypeId);
        }

        return $query->get();
    }

    public function bundleRegistrationReport(
        string $fromDate,
        string $toDate,
        int $options,
        ?int $bodyTypeId = null,
        ?int $operatorId = null
    ): Collection {
        $query = DB::table('vehicle as v')
            ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->selectRaw('bt.name, COUNT(v.id) AS VehicleCount')
            ->whereNotNull('v.card_number')
            ->whereBetween(
                DB::raw($options === 1 ? 'DATE(v.created_at)' : 'DATE(v.updated_at)'),
                [$fromDate, $toDate]
            )
            ->groupBy('bt.name')
            ->orderBy('bt.name');

        if ($bodyTypeId !== null) {
            $query->where('v.body_type_id', $bodyTypeId);
        }

        if ($options === 1 && $operatorId !== null) {
            $query->where('v.created_by', $operatorId);
        } elseif ($options !== 1 && $operatorId !== null) {
            $query->where('v.updated_by', $operatorId);
        }

        return $query->get();
    }

    public function bundleSubscriptionReport(
        string $fromDate,
        string $toDate,
        ?int $status = null
    ): Collection {
        $query = DB::table('bundle_subscriptions as bs')
            ->join('vehicle as v', 'bs.vehicle_id', '=', 'v.id')
            ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
            ->join('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
            ->selectRaw("
                bt.name,
                SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN 1 ELSE 0 END) AS Daily_Bundle,
                SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN 1 ELSE 0 END) AS Weekly_Bundle,
                SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN 1 ELSE 0 END) AS Monthly_Bundle,
                COUNT(*) AS Total_Vehicle
            ")
            ->whereBetween(DB::raw('DATE(bs.created_at)'), [$fromDate, $toDate])
            ->groupBy('bt.name')
            ->orderBy('bt.name');

        if ($status !== null) {
            $query->where('bs.status', $status);
        }

        return $query->get();
    }

    public function vehiclePassageReport(
        string $fromDate,
        string $toDate,
        ?int $operatorId = null,
        array $params = []
    ): array {
        $query = DB::table('toll_transaction as t')
            ->leftJoin('lane as l', 'l.id', '=', 't.lane_id')
            ->leftJoin('vehicle as v', 'v.id', '=', 't.vehicle_id')
            ->leftJoin('auth_user as u', 'u.id', '=', 't.created_by')
            ->selectRaw("
                l.lane_no,
                v.plate_no,
                t.charged_amount,
                t.trans_type,
                t.created_at,
                u.first_name,
                u.middle_name,
                u.surname
            ")
            ->whereBetween(DB::raw('DATE(t.created_at)'), [$fromDate, $toDate]);

        if ($operatorId !== null) {
            $query->where('t.created_by', $operatorId);
        }

        return $this->paginateQuery($query->orderByDesc('t.created_at'), $params, 't.id');
    }

    public function queryVehiclePassage(array $params): array
    {
        $perPage = min(max((int) ($params['per_page'] ?? 15), 1), 200);
        $page = max((int) ($params['page'] ?? 1), 1);
        $search = trim((string) ($params['search'] ?? ''));
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $base = DB::table('toll_transaction as t')
            ->leftJoin('lane as l', 'l.id', '=', 't.lane_id')
            ->leftJoin('vehicle as v', 'v.id', '=', 't.vehicle_id')
            ->leftJoin('auth_user as u', 'u.id', '=', 't.created_by')
            ->leftJoin('body_type as bt', 'bt.id', '=', 't.body_type_id');

        if ($fromDate && $toDate) {
            $base->whereBetween(DB::raw('DATE(t.created_at)'), [$fromDate, $toDate]);
        } else {
            $base->whereDate('t.created_at', now()->toDateString());
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $base->where(function ($q) use ($like) {
                $q->where('u.first_name', 'like', $like)
                    ->orWhere('u.middle_name', 'like', $like)
                    ->orWhere('u.surname', 'like', $like)
                    ->orWhere('l.lane_no', 'like', $like)
                    ->orWhere('bt.name', 'like', $like)
                    ->orWhere('v.plate_no', 'like', $like);
            });
        }

        $total = (clone $base)->count('t.id');

        $rows = (clone $base)
            ->selectRaw("
                bt.name AS body_type,
                t.trans_type AS payment_method,
                t.id,
                l.lane_no,
                v.plate_no,
                t.charged_amount,
                t.created_at,
                CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.middle_name,''),' ',COALESCE(u.surname,'')) AS name
            ")
            ->orderByDesc('t.id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ];
    }

    public function tollCollectionDetail(array $filters): array
    {
        $query = DB::table('toll_transaction as tt')
            ->leftJoin('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->leftJoin('auth_user as au', 'au.id', '=', 'tt.created_by')
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->selectRaw("
                CONCAT(au.first_name, ' ', au.surname) AS operator,
                tt.charged_amount AS amount,
                bt.name AS body_type,
                l.lane_no AS booth,
                s.name AS shift,
                tt.trans_type AS method,
                tt.created_at AS day
            ")
            ->whereBetween(DB::raw('DATE(tt.created_at)'), [$filters['from_date'], $filters['to_date']]);

        foreach (['created_by' => 'user_id', 'body_type_id' => 'body_type_id', 'lane_id' => 'lane', 'shift_id' => 'shift_id'] as $col => $key) {
            if (!empty($filters[$key])) {
                $query->where('tt.' . $col, $filters[$key]);
            }
        }

        return $this->paginateQuery($query->orderByDesc('tt.created_at'), $filters, 'tt.id');
    }

    public function paymentReconciliationList(): Collection
    {
        $incident = DB::table('incident_fine as i')
            ->join('receipt_type as r', 'r.id', '=', 'i.receipt_type')
            ->selectRaw("
                i.receipt_type, r.name, i.amount, i.receipt_number,
                i.psp_receipt_num AS receipt_number, i.trx_dt_tm AS bank_date,
                i.created_at, i.updated_at AS receipt_date, i.control_num,
                i.usd_pay_chn AS payment_channel, 'incident' AS source
            ")
            ->where('i.psp_receipt_num', 'not like', 'CANC%');

        $event = DB::table('event_payment as e')
            ->join('receipt_type as r', 'r.id', '=', 'e.receipt_type')
            ->selectRaw("
                e.receipt_type, r.name, e.amount, e.receipt_number,
                e.psp_receipt_num AS receipt_number, e.trx_dt_tm AS bank_date,
                e.created_at, e.updated_at AS receipt_date, e.control_num,
                e.usd_pay_chn AS payment_channel, 'event' AS source
            ")
            ->where('e.psp_receipt_num', 'not like', 'CANC%');

        $topUp = DB::table('top_up as t')
            ->join('receipt_type as r', 'r.id', '=', 't.receipt_type')
            ->selectRaw("
                t.receipt_type, r.name, t.bill_amount AS amount, t.receipt_number,
                t.psp_receipt_num AS receipt_num, t.trx_dt_tm AS bank_date,
                t.bill_gen_at AS created_at, t.updated_at AS receipt_date,
                t.contr_num AS control_num, t.usd_pay_chn AS payment_channel, 'top_up' AS source
            ")
            ->where('t.psp_receipt_num', 'not like', 'CANC%');

        return $incident->unionAll($event)->unionAll($topUp)->orderByDesc('created_at')->get();
    }

    public function resolveReportWindow(
        ?string $openCounter,
        ?string $closeCounter,
        ?int $shiftId,
        ?string $shiftDate
    ): ?array {
        if ($openCounter && $closeCounter) {
            return [$openCounter, $closeCounter];
        }

        if (!$shiftId || !$shiftDate) {
            return null;
        }

        return ShiftWindow::shiftTransactionBounds($shiftId, $shiftDate);
    }

    public function endOfShiftOverall(
        string $openCounter,
        string $closeCounter,
        ?int $userId,
        int $shiftId,
        ?int $laneId = null
    ): Collection {
        return DB::table('toll_transaction as t')
            ->leftJoin('auth_user as au', 'au.id', '=', 't.created_by')
            ->leftJoin('lane as l', 'l.id', '=', 't.lane_id')
            ->leftJoin('shift as s', 's.id', '=', 't.shift_id')
            ->leftJoin('body_type as b', 'b.id', '=', 't.body_type_id')
            ->selectRaw("
                t.charged_amount, t.created_at, b.name AS body_type,
                au.first_name, au.middle_name, au.surname,
                " . self::operatorNameSql('au') . ",
                l.lane_no, s.name, t.trans_type, t.plate_no
            ")
            ->whereBetween('t.created_at', [$openCounter, $closeCounter])
            ->where('t.shift_id', $shiftId)
            ->where('t.trans_type', 'CASH')
            ->when($userId !== null, fn ($query) => $query->where('t.created_by', $userId))
            ->when($laneId !== null, fn ($query) => $query->where('t.lane_id', $laneId))
            ->orderBy('t.created_at')
            ->get();
    }

    public function shiftSummaryReport(
        string $openCounter,
        string $closeCounter,
        ?int $userId,
        int $shiftId,
        ?int $laneId = null
    ): array {
        $data = DB::table('toll_transaction as tt')
            ->leftJoin('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
            ->leftJoin('auth_user as au', 'au.id', '=', 'tt.created_by')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->selectRaw("
                tt.body_type_id, bt.name, s.name AS shift, COUNT(tt.body_type_id) AS count,
                tt.charged_amount, l.lane_no, au.middle_name, au.first_name, au.surname,
                " . self::operatorNameSql('au') . ",
                COALESCE(SUM(tt.charged_amount), 0) AS TOTAL_TYPE
            ")
            ->whereBetween('tt.created_at', [$openCounter, $closeCounter])
            ->where('tt.shift_id', $shiftId)
            ->when($userId !== null, fn ($query) => $query->where('tt.created_by', $userId))
            ->where('tt.trans_type', 'CASH')
            ->whereNull('tt.status')
            ->when($laneId !== null, fn ($query) => $query->where('tt.lane_id', $laneId))
            ->groupBy('tt.body_type_id', 'tt.charged_amount', 'tt.lane_id', 'bt.name', 's.name', 'l.lane_no', 'au.middle_name', 'au.first_name', 'au.surname')
            ->havingRaw('count > 0')
            ->get();

        $cancelled = $this->cancelledReceiptsInWindow($openCounter, $closeCounter, $userId, $shiftId, $laneId);

        return [
            'data' => $data,
            'cancelled_receipts' => $cancelled,
            'operator_name' => $this->resolveOperatorMeta($userId),
        ];
    }

    public function shiftSummaryAuditReport(
        string $openCounter,
        string $closeCounter,
        ?int $userId,
        int $shiftId,
        ?int $laneId = null
    ): array {
        $operatorName = null;
        if ($userId !== null) {
            $operator = DB::table('auth_user')->where('id', $userId)->first();
            $operatorName = $operator
                ? trim(($operator->first_name ?: 'N/A') . ' ' . ($operator->middle_name ?: 'N/A') . ' ' . ($operator->surname ?: 'N/A'))
                : 'N/A N/A N/A';
        }

        $sub = DB::table('toll_transaction')
            ->select('body_type_id', 'charged_amount', 'created_by', 'shift_id', 'lane_id')
            ->whereBetween('created_at', [$openCounter, $closeCounter])
            ->where('shift_id', $shiftId)
            ->when($userId !== null, fn ($query) => $query->where('created_by', $userId))
            ->where('trans_type', 'CASH')
            ->whereNull('status')
            ->when($laneId !== null, fn ($query) => $query->where('lane_id', $laneId));

        $data = DB::table('body_type as bt')
            ->leftJoinSub($sub, 'tt', 'bt.id', '=', 'tt.body_type_id')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->selectRaw("
                tt.body_type_id, bt.name, s.name AS shift, COUNT(tt.body_type_id) AS count,
                COALESCE(SUM(tt.charged_amount), 0) AS TOTAL_TYPE, tt.charged_amount, l.lane_no
            ")
            ->groupBy('tt.body_type_id', 'bt.name', 's.name', 'tt.charged_amount', 'l.lane_no')
            ->orderBy('bt.name')
            ->get();

        $shift = 'N/A';
        $laneNo = 'N/A';
        foreach ($data as $row) {
            if (!empty($row->shift)) {
                $shift = $row->shift;
            }
            if (!empty($row->lane_no)) {
                $laneNo = $row->lane_no;
            }
        }

        $cancelled = $this->cancelledReceiptsInWindow($openCounter, $closeCounter, $userId, $shiftId, $laneId);

        return [
            'data' => $data,
            'operator_name' => $operatorName,
            'shift' => $shift,
            'lane_no' => $laneNo,
            'cancelled_receipts' => $cancelled,
        ];
    }

    private function cancelledReceiptsInWindow(
        string $openCounter,
        string $closeCounter,
        ?int $userId,
        int $shiftId,
        ?int $laneId = null
    ): Collection {
        return DB::table('toll_transaction')
            ->select('plate_no', 'receipt_num', 'charged_amount', 'reason')
            ->where('status', 1)
            ->whereBetween('created_at', [$openCounter, $closeCounter])
            ->when($userId !== null, fn ($query) => $query->where('created_by', $userId))
            ->where('shift_id', $shiftId)
            ->when($laneId !== null, fn ($query) => $query->where('lane_id', $laneId))
            ->get();
    }

    public function tollCollectionSummary(string $fromDate, string $toDate, string $collectionType): Collection
    {
        if ($collectionType === 'bundle') {
            $response = Collections::getBundleCollection($fromDate, $toDate, $collectionType);
        } elseif ($collectionType === 'cash') {
            $response = Collections::getCashCollection($fromDate, $toDate, $collectionType);
        } else {
            $response = Collections::getPrepaymentCollection($fromDate, $toDate, 'topUp');
        }

        $data = $response->getData(true);

        return $this->flattenTollCollectionSummary(is_array($data) ? $data : [], $collectionType);
    }

    public function incidentCollectionSummary(string $fromDate, string $toDate): Collection
    {
        $data = Collections::getIncidentCollection($fromDate, $toDate)->getData(true);
        $items = $data['incidentType'] ?? $data['incident_type'] ?? [];

        return collect($items)->map(static function ($row) {
            $row = (array) $row;

            return (object) [
                'name' => $row['name'] ?? '',
                'incident_count' => (int) ($row['incidentCount'] ?? $row['incident_count'] ?? 0),
                'total_amount' => (float) ($row['totalAmount'] ?? $row['total_amount'] ?? 0),
            ];
        });
    }

    public function overloadCollectionSummary(string $fromDate, string $toDate): Collection
    {
        $data = Collections::getOverloadCollection($fromDate, $toDate)->getData(true);
        $items = $data['bodyType'] ?? $data['body_type'] ?? [];

        return collect($items)->map(static function ($row) {
            $row = (array) $row;

            return (object) [
                'name' => $row['name'] ?? '',
                'overload_count' => (int) ($row['overloadCount'] ?? $row['overload_count'] ?? 0),
                'amount_collected' => (float) ($row['amountCollected'] ?? $row['amount_collected'] ?? 0),
            ];
        });
    }

    public function eventCollectionSummary(string $fromDate, string $toDate): Collection
    {
        $data = Collections::getEventCollection($fromDate, $toDate)->getData(true);
        $items = $data['eventType'] ?? $data['event_type'] ?? [];

        return collect($items)->map(static function ($row) {
            $row = (array) $row;

            return (object) [
                'name' => $row['name'] ?? '',
                'event_count' => (int) ($row['eventCount'] ?? $row['event_count'] ?? 0),
                'amount_collected' => (float) ($row['amountCollected'] ?? $row['amount_collected'] ?? 0),
            ];
        });
    }

    public function monthlyCollectionSummary(int $year): Collection
    {
        $data = Collections::getOverallMonthlyCollections($year)->getData(true);
        $items = $data['monthlySummary'] ?? $data['monthly_summary'] ?? [];

        return collect($items)->map(static function ($row) {
            $row = (array) $row;

            return (object) [
                'month' => $row['month'] ?? '',
                'toll_collections' => (float) ($row['tollCollections'] ?? $row['toll_collections'] ?? 0),
                'incident_fines' => (float) ($row['incidentFines'] ?? $row['incident_fines'] ?? 0),
                'overload_fines' => (float) ($row['overloadFines'] ?? $row['overload_fines'] ?? 0),
                'events' => (float) ($row['events'] ?? 0),
                'total' => (float) ($row['total'] ?? 0),
            ];
        });
    }

    /**
     * @return array{rows: Collection, meta: array<string, mixed>}
     */
    public function shiftCollectionPerOperator(int $shiftId, string $shiftDate): array
    {
        [$fromDate, $toDate] = ShiftWindow::shiftTransactionBounds($shiftId, $shiftDate);

        $shift = DB::table('shift')->where('id', $shiftId)->first();
        $shiftName = $shift->name ?? 'Unknown';

        $billingAmount = DB::table('toll_transaction as tt')
            ->where('tt.shift_id', $shiftId)
            ->where('tt.trans_type', 'CASH')
            ->whereNull('tt.status')
            ->whereBetween('tt.created_at', [$fromDate, $toDate])
            ->sum('tt.charged_amount');

        $rows = DB::table('toll_transaction as tt')
            ->select(
                DB::raw("COALESCE(au.first_name, '') as first_name"),
                DB::raw("COALESCE(au.middle_name, '') as middle_name"),
                DB::raw("COALESCE(au.surname, '') as surname"),
                DB::raw(self::operatorNameSql('au')),
                DB::raw("COALESCE(l.lane_no, 'N/A') as booth"),
                DB::raw('COALESCE(SUM(tt.charged_amount), 0) as Collection')
            )
            ->leftJoin('auth_user as au', 'au.id', '=', 'tt.created_by')
            ->leftJoin('lane as l', 'l.id', '=', 'tt.lane_id')
            ->where('tt.shift_id', $shiftId)
            ->where('tt.trans_type', 'CASH')
            ->whereNull('tt.status')
            ->whereBetween('tt.created_at', [$fromDate, $toDate])
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

        return [
            'rows' => $rows,
            'meta' => [
                'shift' => $shiftName,
                'shift_date' => $shiftDate,
                'billing_amount' => number_format((float) ($billingAmount ?? 0), 2, '.', ''),
            ],
        ];
    }

    protected function flattenTollCollectionSummary(array $data, string $type): Collection
    {
        $rows = collect();
        $bodyTypes = $data['bodyType'] ?? $data['body_type'] ?? [];

        if ($type === 'bundle') {
            foreach ($bodyTypes as $bt) {
                $bt = (array) $bt;
                foreach (['Daily' => 'daily', 'Weekly' => 'weekly', 'Monthly' => 'monthly'] as $label => $key) {
                    $period = $bt[$key] ?? null;
                    if (!is_array($period)) {
                        continue;
                    }
                    $rows->push((object) [
                        'name' => $bt['name'] ?? '',
                        'period' => $label,
                        'count' => (int) ($period['passage'] ?? 0),
                        'amount' => (float) ($period['amount'] ?? 0),
                    ]);
                }
            }

            return $rows;
        }

        if ($type === 'topUp') {
            if (!empty($data['deposits'])) {
                $rows->push((object) [
                    'name' => 'All accounts',
                    'period' => 'Deposits',
                    'count' => null,
                    'amount' => (float) $data['deposits'],
                ]);
            }
            foreach ($bodyTypes as $bt) {
                $bt = (array) $bt;
                $rows->push((object) [
                    'name' => $bt['name'] ?? '',
                    'period' => 'Passages',
                    'count' => (int) ($bt['passage'] ?? 0),
                    'amount' => (float) ($bt['earned'] ?? 0),
                ]);
            }

            return $rows;
        }

        foreach ($bodyTypes as $bt) {
            $bt = (array) $bt;
            $rows->push((object) [
                'name' => $bt['name'] ?? '',
                'period' => 'Cash',
                'count' => (int) ($bt['passage'] ?? 0),
                'amount' => (float) ($bt['amount'] ?? 0),
            ]);
        }

        return $rows;
    }

    private function eveningShiftAggregate(string $fromDate, string $toDate): ?array
    {
        [$from, $to] = ShiftWindow::eveningBounds($fromDate);

        $row = DB::table('toll_transaction as tt')
            ->leftJoin('shift as s', 's.id', '=', 'tt.shift_id')
            ->selectRaw('s.name AS shift, COUNT(tt.id) AS count, COALESCE(SUM(tt.charged_amount), 0) AS amount')
            ->whereBetween('tt.created_at', [$from, $to])
            ->where('tt.shift_id', ShiftWindow::EVENING_SHIFT_ID)
            ->whereNull('tt.status')
            ->where('tt.trans_type', 'CASH')
            ->groupBy('tt.shift_id', 's.name')
            ->first();

        return $row ? (array) $row : null;
    }
}
