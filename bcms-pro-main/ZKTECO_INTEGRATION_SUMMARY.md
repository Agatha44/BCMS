# ZKTeco Attendance Integration - Implementation Summary

## ✅ What Has Been Implemented

### 1. **AttendanceLogService** (`app/Services/AttendanceLogService.php`)
   - Service class for communicating with ZKTeco devices
   - Methods for connecting/disconnecting from device
   - Retrieving attendance logs (all, by date range, by user, incremental)
   - Converting device logs to application session format
   - Device management (get time, version, clear logs)

### 2. **Configuration File** (`config/attendance.php`)
   - Device IP and port configuration
   - Connection timeout settings
   - Auto-clear logs option
   - Sync interval configuration
   - User ID mapping configuration

### 3. **Controller Methods** (Added to `app/Http/Controllers/Fingerprint/AttendanceLogController.php`)
   - `fetchDeviceLogs()` - Fetch logs from device without saving
   - `syncDeviceLogs()` - Sync logs from device to database
   - `getDeviceInfo()` - Get device version and time information
   - `mapDeviceUserIdToLaravelUserId()` - Map device user IDs to Laravel user IDs

### 4. **Artisan Command** (`app/Console/Commands/SyncAttendanceLogs.php`)
   - Command: `php artisan attendance:sync`
   - Options:
     - `--clear` - Clear device logs after sync
     - `--start-date=YYYY-MM-DD` - Start date for sync
     - `--end-date=YYYY-MM-DD` - End date for sync
     - `--incremental` - Only sync new logs since last sync

### 5. **API Routes** (Added to `routes/api.php`)
   - `GET /api/attendance/device/fetch` - Fetch logs from device
   - `POST /api/attendance/device/sync` - Sync logs to database
   - `GET /api/attendance/device/info` - Get device information

### 6. **Scheduled Task** (Updated `app/Console/Kernel.php`)
   - Automatic sync based on `ATTENDANCE_SYNC_INTERVAL` configuration
   - Runs in background without overlapping
   - Logs output to `storage/logs/attendance-sync.log`

### 7. **Composer Dependency** (Updated `composer.json`)
   - Added `adrobinoga/zk-lib` package

## 📋 Next Steps

### Step 1: Install ZKLibrary

Run the following command to install the ZKLibrary package:

```bash
composer require adrobinoga/zk-lib
```

**Note:** If `adrobinoga/zk-lib` doesn't work with your device, try:
```bash
composer require hamdanasim/zk-library
```

If you use a different library, you'll need to update the `AttendanceLogService` class to match that library's API.

### Step 2: Configure Environment Variables

Add these to your `.env` file:

```env
# ZKTeco Device Configuration
ATTENDANCE_DEVICE_IP=192.168.1.201
ATTENDANCE_DEVICE_PORT=4370
ATTENDANCE_CONNECTION_TIMEOUT=5
ATTENDANCE_AUTO_CLEAR_LOGS=false
ATTENDANCE_SYNC_INTERVAL=60
```

**Important:** Replace `192.168.1.201` with your actual device IP address.

### Step 3: Configure User ID Mapping

The system needs to map device user IDs to Laravel user IDs. By default, it assumes device user IDs match Laravel user IDs.

**If your device user IDs are different**, you need to update the `mapDeviceUserIdToLaravelUserId()` method in:
- `app/Http/Controllers/Fingerprint/AttendanceLogController.php` (for API calls)
- `app/Console/Commands/SyncAttendanceLogs.php` (for artisan command)

**Example scenarios:**

1. **Device user ID matches Laravel user ID** (default - no changes needed)
2. **Device user ID is stored in a user field** (e.g., `fingerprint_id` column in `auth_user` table)
3. **You have a mapping table** (create a `device_user_mapping` table and update the method)

### Step 4: Test the Integration

1. **Test device connection:**
   ```bash
   php artisan attendance:sync --start-date=2024-01-01 --end-date=2024-01-31
   ```

2. **Test API endpoint:**
   ```bash
   GET /api/attendance/device/info
   ```

3. **Check logs:**
   - Application logs: `storage/logs/laravel.log`
   - Sync logs: `storage/logs/attendance-sync.log`

### Step 5: Set Up Scheduled Sync (Optional)

If you want automatic syncing, ensure your cron is configured:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

The sync will run automatically based on `ATTENDANCE_SYNC_INTERVAL` setting.

## 🔧 How It Works

1. **Device Communication**: Service connects to ZKTeco device via TCP/IP
2. **Log Retrieval**: Fetches attendance logs (user ID, timestamp, punch type)
3. **Session Conversion**: Converts punch records to time_in/time_out sessions
4. **User Mapping**: Maps device user IDs to Laravel user IDs
5. **Database Storage**: Saves sessions to `attendance_logs` table (prevents duplicates)

## 📝 Important Notes

1. **Device Format**: The system converts ZKTeco device logs (timestamp + punch type) to your database format (time_in/time_out sessions)

2. **Duplicate Prevention**: The system checks for existing sessions before inserting to prevent duplicates

3. **Device Disabling**: During data retrieval, the device is temporarily disabled to prevent new logs. It's automatically re-enabled after retrieval.

4. **Error Handling**: All errors are logged. Check `storage/logs/laravel.log` for details.

5. **Incremental Sync**: Use `--incremental` flag for faster syncs by only fetching new logs since last sync.

## 🐛 Troubleshooting

### Connection Failed
- Verify device IP in `.env`
- Check network connectivity
- Ensure port 4370 is open
- Test with: `telnet <device_ip> 4370`

### No Logs Retrieved
- Check if device has attendance records
- Verify device time is correct
- Check device storage capacity

### User Mapping Issues
- Check logs for "Device user ID not found" warnings
- Update `mapDeviceUserIdToLaravelUserId()` method
- Verify users exist in `auth_user` table

## 📚 Documentation

See `ZKTECO_ATTENDANCE_SETUP.md` for detailed setup and usage instructions.

## 🔗 Related Files

- `app/Services/AttendanceLogService.php` - Device communication service
- `app/Http/Controllers/Fingerprint/AttendanceLogController.php` - API endpoints
- `app/Console/Commands/SyncAttendanceLogs.php` - Artisan command
- `config/attendance.php` - Configuration
- `routes/api.php` - API routes
- `app/Console/Kernel.php` - Scheduled tasks

## ✅ Integration Checklist

- [x] Service class created
- [x] Configuration file created
- [x] Controller methods added
- [x] Artisan command created
- [x] API routes added
- [x] Scheduled task configured
- [x] Composer dependency added
- [ ] ZKLibrary installed (you need to run `composer require`)
- [ ] Environment variables configured
- [ ] User ID mapping configured (if needed)
- [ ] Device connection tested
- [ ] First sync completed successfully

