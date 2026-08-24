# Attendance Sync Service Setup (Windows & Linux)

## Overview

This service runs the attendance auto-sync continuously, syncing data from the biometric device every 2 minutes without requiring Laravel's scheduler or queue workers. It works on both **Windows** and **Linux** systems.

## Architecture

**Single Service Approach - No Jobs, No Queues, No Scheduler**

The Windows/Linux service runs the sync directly using the `AttendanceLogService`:
- ✅ **No queue needed** - Service handles sync directly
- ✅ **No scheduler needed** - Service runs continuously  
- ✅ **No jobs needed** - Direct service → service method
- ✅ **Simpler architecture** - One command, one service
- ✅ **Better for Windows/Linux** - Native service management

## Installation

---

# Windows Installation

### Method 1: Using Installer Scripts (Recommended)

#### Option A: PowerShell Script
1. **Right-click** on `install-service.ps1`
2. Select **"Run with PowerShell"** (or "Run as Administrator")
3. The service will be installed and started automatically

#### Option B: Batch File
1. **Right-click** on `install-service.bat`
2. Select **"Run as Administrator"**
3. Follow the prompts

### Method 2: Using sc.exe (Built-in Windows)

1. **Open Command Prompt as Administrator**

2. **Navigate to project directory:**
   ```cmd
   cd C:\Users\miraji.ayubu\Desktop\bcms-pro
   ```

3. **Create the service:**
   ```cmd
   sc create AttendanceSyncService binPath= "C:\php\php.exe C:\Users\miraji.ayubu\Desktop\bcms-pro\artisan attendance:sync-service --interval=120 --minutes=5" start= auto DisplayName= "BCMS Attendance Sync Service"
   ```

4. **Set service description:**
   ```cmd
   sc description AttendanceSyncService "Automatically syncs attendance logs from biometric device every 2 minutes"
   ```

5. **Start the service:**
   ```cmd
   sc start AttendanceSyncService
   ```

### Method 2: Using NSSM (Recommended - Better Control)

1. **Download NSSM:**
   - Visit: https://nssm.cc/download
   - Extract to a folder (e.g., `C:\nssm`)

2. **Install service:**
   ```cmd
   C:\nssm\win64\nssm.exe install AttendanceSyncService
   ```

3. **Configure in NSSM GUI:**
   - Application tab:
     - Path: `C:\php\php.exe`
     - Startup directory: `C:\Users\miraji.ayubu\Desktop\bcms-pro`
     - Arguments: `artisan attendance:sync-service --interval=120 --minutes=5`
   - Details tab:
     - Display name: `BCMS Attendance Sync Service`
     - Description: `Automatically syncs attendance logs from biometric device every 2 minutes`
   - Log on tab:
     - Select account (usually Local System or your user account)
   - I/O tab:
     - Output: `C:\Users\miraji.ayubu\Desktop\bcms-pro\storage\logs\attendance-sync-service.log`
     - Error: `C:\Users\miraji.ayubu\Desktop\bcms-pro\storage\logs\attendance-sync-service-error.log`

4. **Start service:**
   ```cmd
   C:\nssm\win64\nssm.exe start AttendanceSyncService
   ```

## Service Management

### Using sc.exe:
```cmd
# Start service
sc start AttendanceSyncService

# Stop service
sc stop AttendanceSyncService

# Restart service
sc stop AttendanceSyncService && sc start AttendanceSyncService

# Check status
sc query AttendanceSyncService

# Delete service
sc delete AttendanceSyncService
```

### Using NSSM:
```cmd
# Start
C:\nssm\win64\nssm.exe start AttendanceSyncService

# Stop
C:\nssm\win64\nssm.exe stop AttendanceSyncService

# Restart
C:\nssm\win64\nssm.exe restart AttendanceSyncService

# Status
C:\nssm\win64\nssm.exe status AttendanceSyncService

# Remove
C:\nssm\win64\nssm.exe remove AttendanceSyncService confirm
```

### Using Services GUI:
1. Press `Win + R`, type `services.msc`, press Enter
2. Find "BCMS Attendance Sync Service"
3. Right-click for Start/Stop/Restart options

## Testing

### Test the command manually (run once):
```cmd
php artisan attendance:sync-service --once --minutes=5
```

### Test with custom interval:
```cmd
php artisan attendance:sync-service --once --interval=60 --minutes=5
```

### Run continuously for testing (Ctrl+C to stop):
```cmd
php artisan attendance:sync-service --interval=120 --minutes=5
```

## Configuration

### Command Options:
- `--interval=120` - Seconds between syncs (default: 120 = 2 minutes)
- `--minutes=5` - Minutes to look back for recent logs (default: 5)
- `--once` - Run once and exit (for testing)

### Device Configuration:
Update in `.env` or `config/attendance.php`:
```env
ATTENDANCE_DEVICE_IP=10.10.13.66
ATTENDANCE_DEVICE_PORT=4370
```

## Monitoring

### View Logs:
```cmd
# Service output log (if using NSSM)
type storage\logs\attendance-sync-service.log

# Laravel application logs
type storage\logs\laravel.log | findstr "attendance sync"
```

### Check Service Status:
```cmd
sc query AttendanceSyncService
```

### View in Event Viewer:
1. Open Event Viewer (`eventvwr.msc`)
2. Windows Logs → Application
3. Filter by source: "AttendanceSyncService"

## Troubleshooting

### Service won't start:
1. Check PHP path is correct
2. Check project path is correct
3. Verify service account has permissions
4. Check logs for errors

### Service stops unexpectedly:
1. Check Laravel logs: `storage\logs\laravel.log`
2. Check service logs (if using NSSM)
3. Verify device connectivity
4. Check Windows Event Viewer

### No data syncing:
1. Verify device is online and reachable
2. Check device IP/port configuration
3. Test connection manually:
   ```cmd
   php artisan attendance:sync-service --once
   ```

---

# Linux Installation

### Method 1: Using Installer Script (Recommended)

1. **Make the script executable:**
   ```bash
   chmod +x install-service.sh
   ```

2. **Run the installer as root:**
   ```bash
   sudo ./install-service.sh
   ```

3. The service will be installed, enabled, and started automatically

### Method 2: Manual Installation

1. **Copy the service file** to systemd directory:
   ```bash
   sudo cp attendance-sync.service /etc/systemd/system/
   ```

2. **Edit the service file** to match your environment:
   ```bash
   sudo nano /etc/systemd/system/attendance-sync.service
   ```
   
   Update these values:
   - `WorkingDirectory` - Your Laravel project path (e.g., `/var/www/bcms-pro`)
   - `ExecStart` - Full path to PHP and artisan
   - `User` and `Group` - Your web server user (usually `www-data` or `apache`)

3. **Reload systemd:**
   ```bash
   sudo systemctl daemon-reload
   ```

4. **Enable the service** (start on boot):
   ```bash
   sudo systemctl enable attendance-sync.service
   ```

5. **Start the service:**
   ```bash
   sudo systemctl start attendance-sync.service
   ```

6. **Verify it's running:**
   ```bash
   sudo systemctl status attendance-sync.service
   ```

## Linux Service Management

### Start Service
```bash
sudo systemctl start attendance-sync
```

### Stop Service
```bash
sudo systemctl stop attendance-sync
```

### Restart Service
```bash
sudo systemctl restart attendance-sync
```

### Check Status
```bash
sudo systemctl status attendance-sync
```

### View Logs
```bash
# Live logs (follow)
sudo journalctl -u attendance-sync -f

# Last 50 lines
sudo journalctl -u attendance-sync -n 50

# Laravel logs
tail -f storage/logs/laravel.log
```

### Enable/Disable Auto-start
```bash
# Enable service to start on boot
sudo systemctl enable attendance-sync

# Disable auto-start
sudo systemctl disable attendance-sync
```

### Delete Service
```bash
sudo systemctl stop attendance-sync
sudo systemctl disable attendance-sync
sudo rm /etc/systemd/system/attendance-sync.service
sudo systemctl daemon-reload
```

## Linux Troubleshooting

### Service Won't Start
1. Check service status: `sudo systemctl status attendance-sync`
2. Check logs: `sudo journalctl -u attendance-sync -n 50`
3. Verify PHP path: `which php`
4. Verify Laravel paths: `ls -la /var/www/bcms-pro/artisan`
5. Check permissions:
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

### Permission Issues
```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/bcms-pro/storage
sudo chown -R www-data:www-data /var/www/bcms-pro/bootstrap/cache

# Set permissions
sudo chmod -R 775 /var/www/bcms-pro/storage
sudo chmod -R 775 /var/www/bcms-pro/bootstrap/cache
```

---

## Advantages of Service Approach (Windows & Linux)

✅ **No queue system needed** - Direct execution  
✅ **No scheduler needed** - Service runs continuously  
✅ **Native OS integration** - Managed like other system services  
✅ **Automatic startup** - Starts with system boot  
✅ **Better error recovery** - Service can auto-restart on failure  
✅ **Simpler architecture** - One less moving part  
✅ **Cross-platform** - Works on both Windows and Linux  

## Comparison: Service vs Job + Scheduler

| Feature | System Service | Job + Scheduler |
|---------|----------------|-----------------|
| Setup Complexity | Medium | High (needs scheduler + queue) |
| Resource Usage | Low | Medium (scheduler + queue worker) |
| Error Handling | Built-in | Queue retry mechanism |
| OS Integration | Native (systemd/Windows Services) | Requires cron/equivalent |
| Monitoring | System service tools | Laravel queue monitoring |
| Best For | Single server deployments | Cross-platform, distributed systems |

## Recommendation

For single-server deployments (Windows or Linux), **use the System Service approach** - it's simpler, more reliable, and better integrated with the operating system.

