# ZKTeco Attendance Device Integration Setup Guide

This guide explains how to set up and use the ZKTeco attendance device integration for pulling attendance logs.

## Prerequisites

1. ZKTeco attendance device connected to the network
2. Device IP address and port (default: 4370)
3. PHP 8.0 or higher
4. Laravel 8.x

## Installation Steps

### 1. Install ZKLibrary

The integration uses ZKLibrary to communicate with ZKTeco devices. Install it via Composer:

```bash
composer require adrobinoga/zk-lib
```

**Alternative:** If `adrobinoga/zk-lib` doesn't work with your device, try:

```bash
composer require hamdanasim/zk-library
```

If you use a different library, you may need to update the `AttendanceLogService` class to match the library's API.

### 2. Configure Environment Variables

Add the following to your `.env` file:

```env
# ZKTeco Device Configuration
ATTENDANCE_DEVICE_IP=192.168.1.201
ATTENDANCE_DEVICE_PORT=4370
ATTENDANCE_CONNECTION_TIMEOUT=5
ATTENDANCE_AUTO_CLEAR_LOGS=false
ATTENDANCE_SYNC_INTERVAL=60
```

**Configuration Options:**
- `ATTENDANCE_DEVICE_IP`: IP address of your ZKTeco device
- `ATTENDANCE_DEVICE_PORT`: TCP port (default: 4370)
- `ATTENDANCE_CONNECTION_TIMEOUT`: Connection timeout in seconds
- `ATTENDANCE_AUTO_CLEAR_LOGS`: Automatically clear device logs after sync (default: false)
- `ATTENDANCE_SYNC_INTERVAL`: How often to sync in minutes (0 = disabled)

### 3. Understanding Device Log Format

ZKTeco devices return attendance logs in the following format:

```json
{
    "id": "123",           // Device User ID (the user ID stored on the device)
    "timestamp": "2024-01-15 08:30:00",  // When the punch occurred
    "status": "0",         // Status code (varies by device)
    "punch": 0            // Punch type: 0 = Check-in, 1 = Check-out
}
```

**Important:** The `id` field is the **device user ID**, not necessarily your Laravel user ID. This is the user identifier as stored on the ZKTeco device itself.

### 4. User ID Mapping

The system needs to map device user IDs to Laravel user IDs. By default, it assumes device user IDs match Laravel user IDs in the `auth_user` table.

If your device user IDs are different, you have two options:

**Option A: Update the mapping method**

Edit `app/Http/Controllers/Fingerprint/AttendanceLogController.php` and modify the `mapDeviceUserIdToLaravelUserId()` method to implement your custom mapping logic.

**Option B: Create a mapping table**

1. Create a migration for a `device_user_mapping` table
2. Update the `mapDeviceUserIdToLaravelUserId()` method to query this table

### 5. Test Device Connection

Test the connection to your device:

```bash
php artisan attendance:sync --start-date=2024-01-01 --end-date=2024-01-31
```

Or use the API endpoint:

```bash
GET /api/attendance/device/info
```

## Usage

### Manual Sync via Artisan Command

```bash
# Sync all logs
php artisan attendance:sync

# Sync logs for a date range
php artisan attendance:sync --start-date=2024-01-01 --end-date=2024-01-31

# Incremental sync (only new logs since last sync)
php artisan attendance:sync --incremental

# Clear device logs after sync
php artisan attendance:sync --clear
```

### API Endpoints

All endpoints require authentication (`auth:sanctum` middleware).

#### 1. Fetch Logs from Device (without saving)

```http
GET /api/attendance/device/fetch
```

Returns raw logs from the device without saving to database.

#### 2. Sync Logs from Device to Database

```http
POST /api/attendance/device/sync
Content-Type: application/json

{
    "start_date": "2024-01-01",  // Optional
    "end_date": "2024-01-31",    // Optional
    "clear_device": false         // Optional: clear device logs after sync
}
```

#### 3. Get Device Information

```http
GET /api/attendance/device/info
```

Returns device version, time, IP, and port information.

### Scheduled Sync

The system is configured to automatically sync attendance logs based on the `ATTENDANCE_SYNC_INTERVAL` setting. The scheduled task runs in the background.

To ensure scheduled tasks run, make sure your cron is configured:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## How It Works

1. **Device Communication**: The `AttendanceLogService` connects to the ZKTeco device via TCP/IP using ZKLibrary.

2. **Log Retrieval**: Attendance logs are retrieved from the device. Each log contains:
   - User ID (device user ID)
   - Timestamp
   - Punch type (0 = Check-in, 1 = Check-out)
   - Status

3. **Session Conversion**: Device logs are converted to sessions (time_in/time_out pairs) to match your database structure.

4. **User Mapping**: Device user IDs are mapped to Laravel user IDs.

5. **Database Storage**: Sessions are saved to the `attendance_logs` table, avoiding duplicates.

## Troubleshooting

### Connection Failed

- **Check device IP**: Verify the device IP address in `.env`
- **Network connectivity**: Ensure the server can reach the device
- **Firewall**: Check if port 4370 is open
- **Test connection**: Use `telnet <device_ip> 4370` to test connectivity

### No Logs Retrieved

- **Check device**: Verify the device has attendance records
- **Device time**: Ensure device time is correct
- **Storage capacity**: Check if device storage is full

### User Mapping Issues

- **Check logs**: Review Laravel logs for "Device user ID not found" warnings
- **Update mapping**: Modify `mapDeviceUserIdToLaravelUserId()` method if needed
- **Verify users**: Ensure users exist in the `auth_user` table

### Sync Errors

- **Database connection**: Verify database connection
- **Check logs**: Review `storage/logs/laravel.log` for detailed errors
- **Transaction rollback**: Check if transactions are being rolled back

## Important Notes

1. **Device Disabling**: During data retrieval, the device is temporarily disabled to prevent new logs from being created. It's automatically re-enabled after retrieval.

2. **Log Clearing**: Only clear device logs after confirming successful sync to database. Use `ATTENDANCE_AUTO_CLEAR_LOGS=false` initially.

3. **Duplicate Prevention**: The system checks for existing sessions before inserting to prevent duplicates.

4. **Incremental Sync**: Use `--incremental` flag for faster syncs by only fetching new logs since last sync.

5. **Error Handling**: All errors are logged. Check `storage/logs/laravel.log` for details.

## API Response Examples

### Fetch Device Logs

```json
{
    "success": true,
    "count": 150,
    "logs": [
        {
            "id": "123",
            "timestamp": "2024-01-15 08:30:00",
            "status": "0",
            "punch": 0
        }
    ]
}
```

**Note:** The `id` field in the logs represents the **device user ID** (the user ID stored on the ZKTeco device). This may or may not match your Laravel user ID depending on how users are registered on the device. The `punch` field indicates the type: `0` = Check-in, `1` = Check-out.

### Sync Device Logs

```json
{
    "success": true,
    "message": "Attendance logs synced successfully",
    "synced": 45,
    "skipped": 5,
    "errors": 0,
    "total_device_logs": 150,
    "total_sessions": 50
}
```

### Device Info

```json
{
    "success": true,
    "version": "6.60.1.0",
    "device_time": "2024-01-15 14:30:00",
    "device_ip": "192.168.1.201",
    "device_port": 4370
}
```

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check attendance sync log: `storage/logs/attendance-sync.log`
3. Review device documentation
4. Verify ZKLibrary compatibility with your device model

