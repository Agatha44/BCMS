<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Device IP Address
    |--------------------------------------------------------------------------
    |
    | IP address of the ZKTeco attendance device
    | Must be set in .env file as ATTENDANCE_DEVICE_IP
    |
    */
    'device_ip' => env('ATTENDANCE_DEVICE_IP'),

    /*
    |--------------------------------------------------------------------------
    | Device Port
    |--------------------------------------------------------------------------
    |
    | TCP port for device communication (default: 4370)
    | Must be set in .env file as ATTENDANCE_DEVICE_PORT
    |
    */
    'device_port' => env('ATTENDANCE_DEVICE_PORT'),

    /*
    |--------------------------------------------------------------------------
    | Connection Timeout
    |--------------------------------------------------------------------------
    |
    | Connection timeout in seconds
    | Must be set in .env file as ATTENDANCE_CONNECTION_TIMEOUT
    */
    'connection_timeout' => env('ATTENDANCE_CONNECTION_TIMEOUT'),

    /*
    |--------------------------------------------------------------------------
    | Auto Clear Logs
    |--------------------------------------------------------------------------
    |
    | Automatically clear device logs after successful sync
    | Must be set in .env file as ATTENDANCE_AUTO_CLEAR_LOGS
    */
    'auto_clear_logs' => env('ATTENDANCE_AUTO_CLEAR_LOGS'), 

    /*
    |--------------------------------------------------------------------------
    | Auto Sync Enabled
    |--------------------------------------------------------------------------
    |
    | Enable automatic syncing of attendance logs during signing
    | When enabled, the system will automatically sync logs at regular intervals
    |
    */
    'auto_sync_enabled' => env('ATTENDANCE_AUTO_SYNC_ENABLED'), // Must be set in .env file as ATTENDANCE_AUTO_SYNC_ENABLED

    /*
    |--------------------------------------------------------------------------
    | Sync Schedule
    |--------------------------------------------------------------------------
    |
    | How often to sync attendance logs (in minutes)
    | For real-time sync during signing, set to 1-2 minutes
    |
    */
    'sync_interval' => env('ATTENDANCE_SYNC_INTERVAL'), // Must be set in .env file as ATTENDANCE_SYNC_INTERVAL

    /*
    |--------------------------------------------------------------------------
    | Recent Logs Window
    |--------------------------------------------------------------------------
    |
    | When auto-syncing, only sync logs from the last N minutes
    | This makes syncs faster and more efficient
    |
    */
    'recent_logs_window' => env('ATTENDANCE_RECENT_LOGS_WINDOW'), // Must be set in .env file as ATTENDANCE_RECENT_LOGS_WINDOW

    /*
    |--------------------------------------------------------------------------
    | User ID Mapping
    |--------------------------------------------------------------------------
    |
    | If your device user IDs don't match Laravel user IDs, you can create
    | a mapping table or use a callback function to map device IDs to user IDs.
    | Set to null to use device user ID directly.
    |
    */
    'user_id_mapping' => env('ATTENDANCE_USER_ID_MAPPING'), // Must be set in .env file as ATTENDANCE_USER_ID_MAPPING

    /*
    |--------------------------------------------------------------------------
    | Last Sync Timestamp Cache Key
    |--------------------------------------------------------------------------
    |
    | Cache key to store the last sync timestamp for incremental syncs
    |
    */
    'last_sync_cache_key' => 'attendance_last_sync_timestamp',

    /*
    |--------------------------------------------------------------------------
    | Attendance Violation Email Notifications
    |--------------------------------------------------------------------------
    |
    | Enable or disable email notifications for attendance violations.
    | Set ATTENDANCE_VIOLATION_EMAIL_ENABLED=false in .env to disable.
    |
    */
    'violation_email_enabled' => env('ATTENDANCE_VIOLATION_EMAIL_ENABLED'),
];

