<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BridgeInterfaceStatusService
{
    public const CACHE_KEY = 'bridge_status_interface';

    public function getCached(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds($this->cacheTtlSeconds()),
            fn (): array => $this->compute()
        );
    }

    public function refreshCache(): array
    {
        $snapshot = $this->compute();
        Cache::put(self::CACHE_KEY, $snapshot, now()->addSeconds($this->cacheTtlSeconds()));

        return $snapshot;
    }

    public function compute(): array
    {
        $this->applyInterfaceSelectGuard(true);

        try {
            $traLink = $this->checkTraInterface();
            $erpLink = $this->checkErpInterface();
            $gepgLink = $this->checkGepgInterface();
            $bundleNotifications = $this->checkBundleExpiryNotifications();

            if (($bundleNotifications['status'] ?? 1) == 3
                || $traLink['status'] == 3
                || $erpLink['status'] == 3
                || $gepgLink['status'] == 3) {
                $status = 0;
                $message = 'Error';
            } elseif (($bundleNotifications['status'] ?? 1) == 2) {
                $status = 0;
                $message = 'Warning';
            } else {
                $status = 1;
            }

            $response = [
                'status' => $status,
                'data' => [
                    $this->interfaceMetricsRow('Fiscal Receipts', $traLink, 1),
                    $this->interfaceMetricsRow('Collection Receipts', $erpLink, 1),
                    $this->interfaceMetricsRow('Collection Bills (GePG)', $gepgLink, 1),
                    $this->bundleNotificationsMetricsRow($bundleNotifications),
                ],
            ];

            if ($status !== 1) {
                $response['message'] = $message;
            }

            return $response;
        } finally {
            $this->applyInterfaceSelectGuard(false);
        }
    }

    private function cacheTtlSeconds(): int
    {
        return max(5, (int) config('bridge_status.interface.cache_ttl_seconds', 120));
    }

    private function applyInterfaceSelectGuard(bool $enable): void
    {
        $ms = (int) config('bridge_status.interface.max_select_execution_ms', 0);
        if ($ms <= 0 || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('SET SESSION MAX_EXECUTION_TIME = ' . ($enable ? max(1, $ms) : 0));
        } catch (Throwable $e) {
            // Driver may not support MAX_EXECUTION_TIME.
        }
    }

    /**
     * @param array{total: mixed, error: mixed, overdue: mixed, status?: int} $metrics
     *
     * @return array{name: string, total: string, normal: string, error: string, overdue: string, status: int}
     */
    private function interfaceMetricsRow(string $name, array $metrics, int $status): array
    {
        $total = (int) ($metrics['total'] ?? 0);
        $error = (int) ($metrics['error'] ?? 0);
        $overdue = (int) ($metrics['overdue'] ?? 0);
        $normal = max(0, $total - $overdue - $error);

        return [
            'name' => $name,
            'total' => (string) $total,
            'normal' => (string) $normal,
            'error' => (string) $error,
            'overdue' => (string) $overdue,
            'status' => $status,
        ];
    }

    /**
     * @param array{status: int, data?: array{total_sms?: int, sent_sms?: int, overdue_sms?: int}} $bundleNotifications
     *
     * @return array{name: string, total: string, normal: string, error: string, overdue: string, status: int}
     */
    private function bundleNotificationsMetricsRow(array $bundleNotifications): array
    {
        $data = $bundleNotifications['data'] ?? [];
        $total = (int) ($data['total_sms'] ?? 0);
        $sent = (int) ($data['sent_sms'] ?? 0);
        $overdue = (int) ($data['overdue_sms'] ?? 0);

        return [
            'name' => 'Bridge Notifications',
            'total' => (string) $total,
            'normal' => (string) $sent,
            'error' => '0',
            'overdue' => (string) $overdue,
            'status' => (int) ($bundleNotifications['status'] ?? 1),
        ];
    }

    /**
     * Yii2 bridge-core: StatusCheckController::checkTraInterface (targeted COUNT queries).
     *
     * @return array{status: int, message: string, total: int, error: int, overdue: int}
     */
    private function checkTraInterface(): array
    {
        try {
            $cutoff = $this->rowCutoffClause('created_at');

            $total = $this->queryCount(
                'SELECT COUNT(gc) AS cnt FROM transactions'
                . ' WHERE rctnum IS NULL AND verification_url IS NULL AND dc IS NOT NULL'
                . $cutoff
            );

            $error = $this->queryCount(
                'SELECT COUNT(gc) AS cnt FROM transactions'
                . ' WHERE ack_code IN (1,2,3,4,5,6) AND verification_url IS NULL'
                . $cutoff
            );

            $overdue = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM transactions'
                . ' WHERE rctnum IS NULL AND verification_url IS NULL AND dc IS NOT NULL'
                . ' AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > 1'
                . $cutoff
            );

            return $this->buildInterfaceCheckResult($total, $error, $overdue);
        } catch (Throwable $exception) {
            return $this->buildInterfaceCheckResult(0, 0, 0, 3, $exception->getMessage());
        }
    }

    /**
     * Yii2 bridge-core: StatusCheckController::checkErpInterface.
     *
     * @return array{status: int, message: string, total: int, error: int, overdue: int}
     */
    private function checkErpInterface(): array
    {
        try {
            $cutoff = $this->rowCutoffClause('payment_date');

            $total = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM received_payments WHERE erp_status IS NULL' . $cutoff
            );

            $error = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM received_payments'
                . ' WHERE erp_status IS NULL AND DATEDIFF(CURDATE(), DATE(payment_date)) > 10'
                . $cutoff
            );

            $overdue = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM received_payments'
                . ' WHERE erp_status IS NULL AND DATEDIFF(CURDATE(), DATE(payment_date)) > 1'
                . $cutoff
            );

            return $this->buildInterfaceCheckResult($total, $error, $overdue);
        } catch (Throwable $exception) {
            return $this->buildInterfaceCheckResult(0, 0, 0, 3, $exception->getMessage());
        }
    }

    /**
     * Yii2 bridge-core: StatusCheckController::checkGepgInterface.
     *
     * @return array{status: int, message: string, total: int, error: int, overdue: int}
     */
    private function checkGepgInterface(): array
    {
        try {
            $cutoff = $this->rowCutoffClause('bill_gen_at');

            $total = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM bridge_bills'
                . ' WHERE contr_num IS NULL AND bill_cancel_date IS NULL AND is_cancelled IS NULL AND source IS NOT NULL'
                . $cutoff
            );

            $error = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM bridge_bills'
                . ' WHERE contr_num IS NULL AND bill_cancel_date IS NULL AND is_cancelled'
                . ' AND error_code != 7101 AND error_code IS NOT NULL'
                . $cutoff
            );

            $overdue = $this->queryCount(
                'SELECT COUNT(*) AS cnt FROM bridge_bills'
                . ' WHERE contr_num IS NULL AND bill_cancel_date IS NULL AND is_cancelled IS NULL'
                . ' AND error_code != 7101 AND TIMESTAMPDIFF(MINUTE, bill_gen_at, NOW()) > 5'
                . $cutoff
            );

            return $this->buildInterfaceCheckResult($total, $error, $overdue);
        } catch (Throwable $exception) {
            return $this->buildInterfaceCheckResult(0, 0, 0, 3, $exception->getMessage());
        }
    }

    /**
     * Yii2 bridge-core: StatusCheckController::checkBundleExpiryNotifications.
     *
     * @return array{status: int, message: string, total?: int, error?: int, overdue?: int, data?: array<string, mixed>}
     */
    private function checkBundleExpiryNotifications(): array
    {
        try {
            $row = (array) DB::selectOne(
                "SELECT
                    COUNT(*) AS total_sms,
                    SUM(CASE WHEN expiry_notification = 'Y' THEN 1 ELSE 0 END) AS sent_sms,
                    SUM(CASE WHEN expiry_notification = 'N' THEN 1 ELSE 0 END) AS overdue_sms
                FROM bundle_subscriptions
                WHERE DATE(expire_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                  AND bundle_id IN (2, 3)"
            );

            $data = [
                'total_sms' => (int) ($row['total_sms'] ?? 0),
                'sent_sms' => (int) ($row['sent_sms'] ?? 0),
                'overdue_sms' => (int) ($row['overdue_sms'] ?? 0),
            ];

            if ($data['total_sms'] > 0) {
                if ($data['overdue_sms'] > 0) {
                    return [
                        'status' => 2,
                        'message' => 'Some notifications pending',
                        'data' => $data,
                    ];
                }

                return [
                    'status' => 1,
                    'message' => 'All notifications sent',
                    'data' => $data,
                ];
            }

            return [
                'status' => 1,
                'message' => 'No notifications needed',
                'data' => $data,
            ];
        } catch (Throwable $exception) {
            return ['status' => 3, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: int, message: string, total: int, error: int, overdue: int}
     */
    private function buildInterfaceCheckResult(
        int $total,
        int $error,
        int $overdue,
        int $status = 1,
        string $message = 'Up'
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'total' => $total,
            'error' => $error,
            'overdue' => $overdue,
        ];
    }

    private function queryCount(string $sql): int
    {
        $row = DB::selectOne($sql);
        if ($row === null) {
            return 0;
        }

        return (int) array_values((array) $row)[0];
    }

    private function rowCutoffClause(string $column): string
    {
        $days = (int) config('bridge_status.interface.row_cutoff_days', 0);
        if ($days <= 0) {
            return '';
        }

        return " AND {$column} >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
    }
}
