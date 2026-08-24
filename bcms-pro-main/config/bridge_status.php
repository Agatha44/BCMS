<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bridge status: /api/status-check/bridge-status?function=interface
    |--------------------------------------------------------------------------
    |
    | SQL matches bridge-core (Yii2) StatusCheckController — targeted COUNT
    | queries per metric. Snapshot is cached and refreshed by:
    |   php artisan bridge-status:refresh-interface-snapshot
    |
    | On large tables, set BRIDGE_STATUS_INTERFACE_ROW_CUTOFF_DAYS (e.g. 365).
    | 0 = Yii2-identical (no date filter on interface counts).
    |
    */

    'interface' => [
        'cache_ttl_seconds' => (int) env('BRIDGE_STATUS_INTERFACE_CACHE_SECONDS', 120),

        'row_cutoff_days' => (int) env('BRIDGE_STATUS_INTERFACE_ROW_CUTOFF_DAYS', 0),

        'schedule_refresh' => filter_var(
            env('BRIDGE_STATUS_INTERFACE_SCHEDULE_REFRESH', true),
            FILTER_VALIDATE_BOOLEAN
        ),

        /**
         * MySQL 8+: max SELECT wall time in ms for this request (0 = unset).
         * Use together with row_cutoff_days for predictable latency.
         */
        'max_select_execution_ms' => (int) env('BRIDGE_STATUS_INTERFACE_MAX_SELECT_MS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service health checks: /api/status-check/bridge-status?function=service
    |--------------------------------------------------------------------------
    */

    'frontend_url' => env('BRIDGE_STATUS_FRONTEND_URL', 'https://bms.nssf.go.tz'),
    'backend_url' => env('BRIDGE_STATUS_BACKEND_URL', 'https://bcmspro-api.nssf.go.tz'),

];
