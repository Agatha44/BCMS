# Install Attendance Sync Service

This guide covers installation for both **Windows** and **Linux** systems.

---

# Windows Installation

## Quick Install (Recommended)

### Option 1: PowerShell Script (Recommended)
1. **Right-click** on `install-service.ps1`
2. Select **"Run with PowerShell"** (or "Run as Administrator")
3. If prompted, click **"Yes"** to allow the script to run
4. The service will be installed and started automatically

### Option 2: Batch File
1. **Right-click** on `install-service.bat`
2. Select **"Run as Administrator"**
3. Follow the prompts

### Option 3: Manual Installation

Open **PowerShell as Administrator** and run:

```powershell
cd "C:\Users\miraji.ayubu\Desktop\bcms-pro"

# Create the service
sc create AttendanceSyncService binPath= "C:\php\php.exe C:\Users\miraji.ayubu\Desktop\bcms-pro\artisan attendance:sync-service --interval=120 --minutes=5" start= auto DisplayName= "BCMS Attendance Sync Service"

# Set description
sc description AttendanceSyncService "Automatically syncs attendance logs from biometric device every 2 minutes"

# Configure auto-restart on failure
sc failure AttendanceSyncService reset= 86400 actions= restart/60000/restart/60000/restart/60000

# Start the service
sc start AttendanceSyncService

# Verify it's running
sc query AttendanceSyncService
```

## Verify Installation

After installation, check the service status:

```powershell
Get-Service AttendanceSyncService
```

Or using `sc`:
```cmd
sc query AttendanceSyncService
```

## Service Management

### Start Service
```powershell
Start-Service AttendanceSyncService
# or
sc start AttendanceSyncService
```

### Stop Service
```powershell
Stop-Service AttendanceSyncService
# or
sc stop AttendanceSyncService
```

### Restart Service
```powershell
Restart-Service AttendanceSyncService
# or
sc stop AttendanceSyncService && sc start AttendanceSyncService
```

### Check Status
```powershell
Get-Service AttendanceSyncService
# or
sc query AttendanceSyncService
```

### View Logs
```powershell
Get-Content storage\logs\laravel.log -Tail 50 -Wait
```

### Delete Service (if needed)
```powershell
sc stop AttendanceSyncService
sc delete AttendanceSyncService
```

## Service Configuration

- **Sync Interval**: 120 seconds (2 minutes)
- **Look-back Period**: 5 minutes
- **Start Type**: Automatic (starts with Windows)
- **Recovery**: Auto-restart on failure

## Troubleshooting

### Service Won't Start
1. Check PHP path is correct: `C:\php\php.exe`
2. Check Laravel logs: `storage\logs\laravel.log`
3. Verify biometric device is accessible
4. Check Windows Event Viewer for service errors

### View Service Logs
```powershell
# Laravel logs
Get-Content storage\logs\laravel.log -Tail 100

# Windows Event Viewer
eventvwr.msc
# Navigate to: Windows Logs > Application
# Filter by: AttendanceSyncService
```

### Test Service Manually
Before installing as a service, test it manually:
```powershell
php artisan attendance:sync-service --once --minutes=5
```

## Next Steps

After installation:
1. ✅ Service is installed and running
2. ✅ Service will auto-start with Windows
3. ✅ Service will sync every 2 minutes
4. ✅ Check logs to verify it's working: `Get-Content storage\logs\laravel.log -Tail 50`

---

# Linux Installation

## Quick Install (Recommended)

### Option 1: Installation Script (Recommended)
1. Make the script executable:
   ```bash
   chmod +x install-service.sh
   ```
2. Run the installer as root:
   ```bash
   sudo ./install-service.sh
   ```
3. The service will be installed, enabled, and started automatically

### Option 2: Manual Installation

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

3. **Reload systemd**:
   ```bash
   sudo systemctl daemon-reload
   ```

4. **Enable the service** (start on boot):
   ```bash
   sudo systemctl enable attendance-sync.service
   ```

5. **Start the service**:
   ```bash
   sudo systemctl start attendance-sync.service
   ```

6. **Verify it's running**:
   ```bash
   sudo systemctl status attendance-sync.service
   ```

## Verify Installation

After installation, check the service status:

```bash
sudo systemctl status attendance-sync.service
```

## Service Management

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

### Enable/Disable Auto-start
```bash
# Enable service to start on boot
sudo systemctl enable attendance-sync

# Disable auto-start
sudo systemctl disable attendance-sync
```

### View Logs

**Systemd Journal (Recommended)**:
```bash
# Live logs (follow)
sudo journalctl -u attendance-sync -f

# Last 50 lines
sudo journalctl -u attendance-sync -n 50

# Logs since today
sudo journalctl -u attendance-sync --since today

# Logs with timestamps
sudo journalctl -u attendance-sync --since "1 hour ago"
```

**Laravel Logs**:
```bash
tail -f storage/logs/laravel.log
```

### Delete Service (if needed)
```bash
sudo systemctl stop attendance-sync
sudo systemctl disable attendance-sync
sudo rm /etc/systemd/system/attendance-sync.service
sudo systemctl daemon-reload
```

## Service Configuration

The service file (`attendance-sync.service`) includes:

- **Sync Interval**: 120 seconds (2 minutes)
- **Look-back Period**: 5 minutes
- **Start Type**: Automatic (starts on boot)
- **Restart Policy**: Always restart on failure
- **Security**: Restricted permissions, no new privileges
- **Resource Limits**: 512MB memory limit

### Customizing the Service

Edit the service file:
```bash
sudo nano /etc/systemd/system/attendance-sync.service
```

After editing, reload and restart:
```bash
sudo systemctl daemon-reload
sudo systemctl restart attendance-sync
```

## Troubleshooting

### Service Won't Start

1. **Check service status**:
   ```bash
   sudo systemctl status attendance-sync
   ```

2. **Check logs**:
   ```bash
   sudo journalctl -u attendance-sync -n 50
   ```

3. **Verify PHP path**:
   ```bash
   which php
   php --version
   ```

4. **Verify Laravel paths**:
   ```bash
   ls -la /var/www/bcms-pro/artisan
   ```

5. **Check permissions**:
   ```bash
   # Ensure web user owns storage and cache directories
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

6. **Test manually**:
   ```bash
   cd /var/www/bcms-pro
   php artisan attendance:sync-service --once --minutes=5
   ```

### Permission Issues

If you see permission errors:

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/bcms-pro/storage
sudo chown -R www-data:www-data /var/www/bcms-pro/bootstrap/cache

# Set permissions
sudo chmod -R 775 /var/www/bcms-pro/storage
sudo chmod -R 775 /var/www/bcms-pro/bootstrap/cache
```

### Service Keeps Restarting

Check the logs to see why:
```bash
sudo journalctl -u attendance-sync -n 100 --no-pager
```

Common issues:
- PHP not found in PATH
- Laravel configuration errors
- Database connection issues
- Biometric device not accessible

### View Detailed Logs

```bash
# All logs
sudo journalctl -u attendance-sync

# Last 100 lines
sudo journalctl -u attendance-sync -n 100

# Since specific time
sudo journalctl -u attendance-sync --since "2024-01-01 00:00:00"

# Follow logs in real-time
sudo journalctl -u attendance-sync -f
```

## Test Service Manually

Before installing as a service, test it manually:

```bash
cd /var/www/bcms-pro
php artisan attendance:sync-service --once --minutes=5
```

## Next Steps

After installation:
1. ✅ Service is installed and running
2. ✅ Service will auto-start on boot
3. ✅ Service will sync every 2 minutes
4. ✅ Check logs to verify it's working: `sudo journalctl -u attendance-sync -f`

