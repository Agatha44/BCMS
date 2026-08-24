<?php

namespace Database\Seeders\ReportEngine;

/**
 * Canonical SQL scripts for collection-management reports (mirrors CollectionReportService).
 */
class CollectionReportQueries
{
    public static function forScript(string $script): ?string
    {
        return self::all()[$script] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'daily-collection' => self::dailyCollection(),
            'daily-shift-collection' => self::dailyShiftCollection(),
            'body-type-collection' => self::bodyTypeCollection(),
            'booth-collection' => self::boothCollection(),
            'body-type-audit' => self::bodyTypeAudit(),
            'exempted-vehicles' => self::exemptedVehicles(),
            'daily-cashless' => self::dailyCashless(),
            'body-cashless' => self::bodyCashless(),
            'cancelled-transactions' => self::cancelledTransactions(),
            'bundle-collection' => self::bundleCollection(),
            'bundle-registration' => self::bundleRegistration(),
            'bundle-subscription' => self::bundleSubscription(),
            'vehicle-passage' => self::vehiclePassage(),
            'vehicle-passage/paginated' => self::vehiclePassagePaginated(),
            'toll-collection-detail' => self::tollCollectionDetail(),
            'payment-reconciliation' => self::paymentReconciliation(),
            'end-of-shift-overall' => self::endOfShiftOverall(),
            'shift-summary' => self::shiftSummary(),
            'shift-collection' => self::shiftCollection(),
            'toll-collection-summary' => self::tollCollectionSummary(),
            'incident-collection-summary' => self::incidentCollectionSummary(),
            'overload-collection-summary' => self::overloadCollectionSummary(),
            'event-collection-summary' => self::eventCollectionSummary(),
            'monthly-collection-summary' => self::monthlyCollectionSummary(),
        ];
    }

    private static function dailyCollection(): string
    {
        return <<<'SQL'
-- Main shift totals (evening shift row is merged in PHP when shift_id = 3).
SELECT
    s.name AS shift,
    COUNT(tt.id) AS count,
    COALESCE(SUM(tt.charged_amount), 0) AS amount
FROM toll_transaction AS tt
JOIN shift AS s ON s.id = tt.shift_id
WHERE DATE(tt.created_at) BETWEEN :from_date AND :to_date
  AND tt.shift_id != 3
GROUP BY tt.shift_id, s.name
ORDER BY s.name
SQL;
    }

    private static function dailyShiftCollection(): string
    {
        return <<<'SQL'
-- For shift_id = 3 use evening window (18:00 same day to 09:00 next day); otherwise filter by date range.
SELECT
    s.name,
    COUNT(tt.id) AS count,
    COALESCE(SUM(tt.charged_amount), 0) AS amount
FROM toll_transaction AS tt
LEFT JOIN shift AS s ON s.id = tt.shift_id
WHERE DATE(tt.created_at) BETWEEN :from_date AND :to_date
  AND tt.trans_type = 'CASH'
  AND tt.shift_id = :shift_id
GROUP BY tt.shift_id, s.name
SQL;
    }

    private static function bodyTypeCollection(): string
    {
        return <<<'SQL'
SELECT
    pl.amount AS fee,
    bt.name AS body_type,
    COUNT(tt.id) AS count,
    COALESCE(SUM(tt.charged_amount), 0) AS amount
FROM toll_transaction AS tt
JOIN body_type AS bt ON bt.id = tt.body_type_id
JOIN price_list AS pl ON pl.body_type_id = tt.body_type_id
WHERE tt.created_at >= CONCAT(:from_date, ' 00:00:00')
  AND tt.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
  AND (:body_type IS NULL OR tt.body_type_id = :body_type)
  AND (:operator IS NULL OR tt.created_by = :operator)
GROUP BY tt.body_type_id, pl.amount, bt.name
ORDER BY bt.name
SQL;
    }

    private static function boothCollection(): string
    {
        return <<<'SQL'
SELECT
    l.lane_no AS lane,
    COUNT(tt.id) AS count,
    COALESCE(SUM(tt.charged_amount), 0) AS amount
FROM toll_transaction AS tt
LEFT JOIN lane AS l ON l.id = tt.lane_id
WHERE DATE(tt.created_at) BETWEEN :from_date AND :to_date
  AND (:lane IS NULL OR tt.lane_id = :lane)
GROUP BY tt.lane_id, l.lane_no
ORDER BY l.lane_no
SQL;
    }

    private static function bodyTypeAudit(): string
    {
        return <<<'SQL'
SELECT
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
FROM body_type_audit AS bta
JOIN auth_user AS au ON au.id = bta.created_by
JOIN vehicle AS v ON v.id = bta.vehicle_id
JOIN body_type AS bt ON bt.id = bta.previous_body_type
JOIN price_list AS pl ON pl.body_type_id = bta.previous_body_type
JOIN body_type AS bt2 ON bt2.id = bta.new_body_type
JOIN price_list AS pl2 ON pl2.body_type_id = bta.new_body_type
WHERE bta.previous_body_type != bta.new_body_type
  AND DATE(bta.created_at) BETWEEN :from_date AND :to_date
  AND (:operator IS NULL OR bta.created_by = :operator)
ORDER BY bta.created_at DESC
SQL;
    }

    private static function exemptedVehicles(): string
    {
        return <<<'SQL'
SELECT
    CONCAT(au.first_name, ' ', au.surname) AS operator,
    tt.charged_amount AS amount,
    bt.name AS body_type,
    l.lane_no AS booth,
    s.name AS shift,
    tt.trans_type AS method,
    tt.plate_no,
    tt.created_at AS day
FROM toll_transaction AS tt
LEFT JOIN body_type AS bt ON bt.id = tt.body_type_id
LEFT JOIN auth_user AS au ON au.id = tt.created_by
LEFT JOIN lane AS l ON l.id = tt.lane_id
LEFT JOIN shift AS s ON s.id = tt.shift_id
WHERE tt.exemption = 1
  AND DATE(tt.created_at) BETWEEN :from_date AND :to_date
  AND (:operator IS NULL OR tt.created_by = :operator)
ORDER BY tt.created_at DESC
SQL;
    }

    private static function dailyCashless(): string
    {
        return <<<'SQL'
SELECT
    COUNT(tt.id) AS Vehicles,
    COALESCE(SUM(tt.charged_amount), 0) AS AmountCollected,
    DATE(tt.created_at) AS Date
FROM toll_transaction AS tt
WHERE tt.trans_type = 'CASHLESS'
  AND DATE(tt.created_at) BETWEEN :from_date AND :to_date
GROUP BY DATE(tt.created_at)
ORDER BY Date
SQL;
    }

    private static function bodyCashless(): string
    {
        return <<<'SQL'
SELECT
    COUNT(tt.id) AS Vehicles,
    COALESCE(SUM(tt.charged_amount), 0) AS AmountCollected,
    bt.name AS bodyType
FROM toll_transaction AS tt
JOIN vehicle AS v ON v.id = tt.vehicle_id
JOIN body_type AS bt ON bt.id = v.body_type_id
WHERE tt.trans_type = 'CASHLESS'
  AND DATE(tt.created_at) BETWEEN :from_date AND :to_date
GROUP BY v.body_type_id, bt.name
ORDER BY bt.name
SQL;
    }

    private static function cancelledTransactions(): string
    {
        return <<<'SQL'
SELECT
    s.name AS shift_name,
    tt.plate_no,
    tt.receipt_num,
    tt.charged_amount,
    tt.reason,
    CONCAT(aub.first_name, ' ', aub.surname) AS operator,
    CONCAT(au.first_name, ' ', au.surname) AS canceled_by
FROM toll_transaction AS tt
LEFT JOIN shift AS s ON s.id = tt.shift_id
LEFT JOIN auth_user AS au ON au.id = tt.updated_by
LEFT JOIN auth_user AS aub ON aub.id = tt.created_by
WHERE DATE(tt.updated_at) BETWEEN :from_date AND :to_date
  AND (:operator IS NULL OR tt.updated_by = :operator)
ORDER BY tt.updated_at DESC
SQL;
    }

    private static function bundleCollection(): string
    {
        return <<<'SQL'
SELECT
    bt.name AS body_type,
    SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN 1 ELSE 0 END) AS Daily_Bundle,
    SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN 1 ELSE 0 END) AS Weekly_Bundle,
    SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN 1 ELSE 0 END) AS Monthly_Bundle,
    COUNT(*) AS Total_Vehicle,
    SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN bs.bill_amount ELSE 0 END) AS Daily_Bundle_Amount,
    SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN bs.bill_amount ELSE 0 END) AS Weekly_Bundle_Amount,
    SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN bs.bill_amount ELSE 0 END) AS Monthly_Bundle_Amount,
    COALESCE(SUM(bs.bill_amount), 0) AS total_amount
FROM bridge_bills AS bs
JOIN vehicle AS v ON bs.dist_param = v.plate_no
JOIN body_type AS bt ON v.body_type_id = bt.id
JOIN toll_bundles AS tb ON tb.id = bs.bundle_id
WHERE bs.bundle_id IS NOT NULL
  AND DATE(bs.bill_gen_at) BETWEEN :from_date AND :to_date
  AND bs.trx_dt_tm IS NOT NULL
  AND (:body_type_id IS NULL OR v.body_type_id = :body_type_id)
GROUP BY bt.name
ORDER BY bt.name
SQL;
    }

    private static function bundleRegistration(): string
    {
        return <<<'SQL'
-- options = 1 uses created_at; otherwise updated_at for the date window.
SELECT
    bt.name,
    COUNT(v.id) AS VehicleCount
FROM vehicle AS v
JOIN body_type AS bt ON v.body_type_id = bt.id
WHERE v.card_number IS NOT NULL
  AND DATE(CASE WHEN :options = 1 THEN v.created_at ELSE v.updated_at END) BETWEEN :from_date AND :to_date
  AND (:body_type IS NULL OR v.body_type_id = :body_type)
  AND (:operator IS NULL OR (CASE WHEN :options = 1 THEN v.created_by ELSE v.updated_by END) = :operator)
GROUP BY bt.name
ORDER BY bt.name
SQL;
    }

    private static function bundleSubscription(): string
    {
        return <<<'SQL'
SELECT
    bt.name,
    SUM(CASE WHEN tb.bundle_description = 'Daily Bundle' THEN 1 ELSE 0 END) AS Daily_Bundle,
    SUM(CASE WHEN tb.bundle_description = 'Weekly Bundle' THEN 1 ELSE 0 END) AS Weekly_Bundle,
    SUM(CASE WHEN tb.bundle_description = 'Monthly Bundle' THEN 1 ELSE 0 END) AS Monthly_Bundle,
    COUNT(*) AS Total_Vehicle
FROM bundle_subscriptions AS bs
JOIN vehicle AS v ON bs.vehicle_id = v.id
JOIN body_type AS bt ON v.body_type_id = bt.id
JOIN toll_bundles AS tb ON tb.id = bs.bundle_id
WHERE DATE(bs.created_at) BETWEEN :from_date AND :to_date
  AND (:options IS NULL OR bs.status = :options)
GROUP BY bt.name
ORDER BY bt.name
SQL;
    }

    private static function vehiclePassage(): string
    {
        return <<<'SQL'
SELECT
    l.lane_no,
    v.plate_no,
    t.charged_amount,
    t.trans_type,
    t.created_at,
    u.first_name,
    u.middle_name,
    u.surname
FROM toll_transaction AS t
LEFT JOIN lane AS l ON l.id = t.lane_id
LEFT JOIN vehicle AS v ON v.id = t.vehicle_id
LEFT JOIN auth_user AS u ON u.id = t.created_by
WHERE DATE(t.created_at) BETWEEN :from_date AND :to_date
  AND (:operator IS NULL OR t.created_by = :operator)
ORDER BY t.created_at DESC
SQL;
    }

    private static function vehiclePassagePaginated(): string
    {
        return <<<'SQL'
SELECT
    bt.name AS body_type,
    t.trans_type AS payment_method,
    t.id,
    l.lane_no,
    v.plate_no,
    t.charged_amount,
    t.created_at,
    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.middle_name, ''), ' ', COALESCE(u.surname, '')) AS name
FROM toll_transaction AS t
LEFT JOIN lane AS l ON l.id = t.lane_id
LEFT JOIN vehicle AS v ON v.id = t.vehicle_id
LEFT JOIN auth_user AS u ON u.id = t.created_by
LEFT JOIN body_type AS bt ON bt.id = t.body_type_id
WHERE DATE(t.created_at) BETWEEN :from_date AND :to_date
ORDER BY t.id DESC
SQL;
    }

    private static function tollCollectionDetail(): string
    {
        return <<<'SQL'
SELECT
    CONCAT(au.first_name, ' ', au.surname) AS operator,
    tt.charged_amount AS amount,
    bt.name AS body_type,
    l.lane_no AS booth,
    s.name AS shift,
    tt.trans_type AS method,
    tt.created_at AS day
FROM toll_transaction AS tt
LEFT JOIN body_type AS bt ON bt.id = tt.body_type_id
LEFT JOIN auth_user AS au ON au.id = tt.created_by
LEFT JOIN lane AS l ON l.id = tt.lane_id
LEFT JOIN shift AS s ON s.id = tt.shift_id
WHERE DATE(tt.created_at) BETWEEN :from_date AND :to_date
  AND (:shift_id IS NULL OR tt.shift_id = :shift_id)
  AND (:body_type_id IS NULL OR tt.body_type_id = :body_type_id)
  AND (:lane IS NULL OR tt.lane_id = :lane)
  AND (:user_id IS NULL OR tt.created_by = :user_id)
ORDER BY tt.created_at DESC
SQL;
    }

    private static function paymentReconciliation(): string
    {
        return <<<'SQL'
SELECT
    i.receipt_type,
    r.name,
    i.amount,
    i.receipt_number,
    i.psp_receipt_num AS receipt_number,
    i.trx_dt_tm AS bank_date,
    i.created_at,
    i.updated_at AS receipt_date,
    i.control_num,
    i.usd_pay_chn AS payment_channel,
    'incident' AS source
FROM incident_fine AS i
JOIN receipt_type AS r ON r.id = i.receipt_type
WHERE i.psp_receipt_num NOT LIKE 'CANC%'
UNION ALL
SELECT
    e.receipt_type,
    r.name,
    e.amount,
    e.receipt_number,
    e.psp_receipt_num AS receipt_number,
    e.trx_dt_tm AS bank_date,
    e.created_at,
    e.updated_at AS receipt_date,
    e.control_num,
    e.usd_pay_chn AS payment_channel,
    'event' AS source
FROM event_payment AS e
JOIN receipt_type AS r ON r.id = e.receipt_type
WHERE e.psp_receipt_num NOT LIKE 'CANC%'
UNION ALL
SELECT
    t.receipt_type,
    r.name,
    t.bill_amount AS amount,
    t.receipt_number,
    t.psp_receipt_num AS receipt_num,
    t.trx_dt_tm AS bank_date,
    t.bill_gen_at AS created_at,
    t.updated_at AS receipt_date,
    t.contr_num AS control_num,
    t.usd_pay_chn AS payment_channel,
    'top_up' AS source
FROM top_up AS t
JOIN receipt_type AS r ON r.id = t.receipt_type
WHERE t.psp_receipt_num NOT LIKE 'CANC%'
ORDER BY created_at DESC
SQL;
    }

    private static function endOfShiftOverall(): string
    {
        return <<<'SQL'
-- open_counter / close_counter are resolved from shift_id + shift_date when not supplied.
SELECT
    t.charged_amount,
    t.created_at,
    b.name AS body_type,
    au.first_name,
    au.middle_name,
    au.surname,
    TRIM(CONCAT(COALESCE(au.first_name, ''), ' ', COALESCE(au.middle_name, ''), ' ', COALESCE(au.surname, ''))) AS operator_name,
    l.lane_no,
    s.name,
    t.trans_type,
    t.plate_no
FROM toll_transaction AS t
LEFT JOIN auth_user AS au ON au.id = t.created_by
LEFT JOIN lane AS l ON l.id = t.lane_id
LEFT JOIN shift AS s ON s.id = t.shift_id
LEFT JOIN body_type AS b ON b.id = t.body_type_id
WHERE t.created_at BETWEEN :open_counter AND :close_counter
  AND t.shift_id = :shift_id
  AND t.trans_type = 'CASH'
  AND (:user_id IS NULL OR t.created_by = :user_id)
  AND (:lane_id IS NULL OR t.lane_id = :lane_id)
ORDER BY t.created_at
SQL;
    }

    private static function shiftSummary(): string
    {
        return <<<'SQL'
-- Cancelled receipts are returned separately in the PHP handler (nested report).
SELECT
    tt.body_type_id,
    bt.name,
    s.name AS shift,
    COUNT(tt.body_type_id) AS count,
    tt.charged_amount,
    l.lane_no,
    au.middle_name,
    au.first_name,
    au.surname,
    TRIM(CONCAT(COALESCE(au.first_name, ''), ' ', COALESCE(au.middle_name, ''), ' ', COALESCE(au.surname, ''))) AS operator_name,
    COALESCE(SUM(tt.charged_amount), 0) AS TOTAL_TYPE
FROM toll_transaction AS tt
LEFT JOIN body_type AS bt ON bt.id = tt.body_type_id
LEFT JOIN auth_user AS au ON au.id = tt.created_by
LEFT JOIN shift AS s ON s.id = tt.shift_id
LEFT JOIN lane AS l ON l.id = tt.lane_id
WHERE tt.created_at BETWEEN :open_counter AND :close_counter
  AND tt.shift_id = :shift_id
  AND tt.trans_type = 'CASH'
  AND tt.status IS NULL
  AND (:user_id IS NULL OR tt.created_by = :user_id)
  AND (:lane_id IS NULL OR tt.lane_id = :lane_id)
GROUP BY tt.body_type_id, tt.charged_amount, tt.lane_id, bt.name, s.name, l.lane_no, au.middle_name, au.first_name, au.surname
HAVING count > 0
SQL;
    }

    private static function shiftCollection(): string
    {
        return <<<'SQL'
-- Shift window bounds are derived from shift_id + shift_date in PHP.
SELECT
    COALESCE(au.first_name, '') AS first_name,
    COALESCE(au.middle_name, '') AS middle_name,
    COALESCE(au.surname, '') AS surname,
    TRIM(CONCAT(COALESCE(au.first_name, ''), ' ', COALESCE(au.middle_name, ''), ' ', COALESCE(au.surname, ''))) AS operator_name,
    COALESCE(l.lane_no, 'N/A') AS booth,
    COALESCE(SUM(tt.charged_amount), 0) AS Collection
FROM toll_transaction AS tt
LEFT JOIN auth_user AS au ON au.id = tt.created_by
LEFT JOIN lane AS l ON l.id = tt.lane_id
WHERE tt.shift_id = :shift_id
  AND tt.trans_type = 'CASH'
  AND tt.status IS NULL
  AND tt.created_at BETWEEN :open_counter AND :close_counter
GROUP BY tt.created_by, tt.lane_id, au.first_name, au.middle_name, au.surname, l.lane_no
ORDER BY au.surname, au.first_name, l.lane_no
SQL;
    }

    private static function tollCollectionSummary(): string
    {
        return <<<'SQL'
-- collection_type: cash | bundle | topUp. Result is reshaped in PHP before display.
SELECT
    bt.name AS name,
    COUNT(tt.id) AS passage,
    COALESCE(SUM(tt.charged_amount), 0) AS amount
FROM toll_transaction AS tt
JOIN body_type AS bt ON tt.body_type_id = bt.id
WHERE DATE(tt.created_at) BETWEEN :from_date AND :to_date
  AND tt.trans_type != 'CASHLESS'
  AND :collection_type = 'cash'
GROUP BY tt.body_type_id, bt.name
ORDER BY bt.name
SQL;
    }

    private static function incidentCollectionSummary(): string
    {
        return <<<'SQL'
SELECT
    ini.name AS name,
    COUNT(inf.id) AS incident_count,
    COALESCE(SUM(inf.amount), 0) AS total_amount
FROM incident_fine AS inf
JOIN incident_nature AS ini ON inf.nature_incident = ini.id
WHERE inf.incident_date BETWEEN :from_date AND :to_date
GROUP BY ini.name
ORDER BY ini.name
SQL;
    }

    private static function overloadCollectionSummary(): string
    {
        return <<<'SQL'
SELECT
    'Semi Trailer' AS name,
    COUNT(*) AS overload_count,
    ROUND(COALESCE(SUM(bill_amount), 0)) AS amount_collected
FROM overload_fine
WHERE trx_id IS NOT NULL
  AND bill_gen_at BETWEEN :from_date AND :to_date
SQL;
    }

    private static function eventCollectionSummary(): string
    {
        return <<<'SQL'
SELECT
    'photography and video recording' AS name,
    COUNT(*) AS event_count,
    ROUND(COALESCE(SUM(amount), 0)) AS amount_collected
FROM event_payment
WHERE psp_name IS NOT NULL
  AND bill_gen_at BETWEEN :from_date AND :to_date
SQL;
    }

    private static function monthlyCollectionSummary(): string
    {
        return <<<'SQL'
-- Full monthly summary merges toll, bundle, incident, overload, and event totals in PHP.
SELECT
    DATE_FORMAT(tt.created_at, '%M') AS month,
    COALESCE(SUM(tt.charged_amount), 0) AS toll_collections
FROM toll_transaction AS tt
WHERE YEAR(tt.created_at) = :year
  AND tt.receipt_num != 'EXEMPTED'
GROUP BY DATE_FORMAT(tt.created_at, '%M')
ORDER BY MIN(tt.created_at)
SQL;
    }
}
