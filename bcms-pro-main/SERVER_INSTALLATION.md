# Server Installation Guide - ZKTeco Package

This guide covers installing the ZKTeco package and dependencies on your server (Development or Production).

## Prerequisites

- PHP 8.0 or higher
- Composer installed on the server
- SSH access to the server
- Laravel application already deployed

## Quick Installation (Recommended)

### For Development Server

Use the provided deployment script:

```bash
chmod +x deploy-server.sh
./deploy-server.sh
```

### For Production Server

Modify the script or use manual commands below with `--no-dev` flag.

## Manual Installation Steps

### 1. SSH into Your Server

```bash
ssh user@your-server-ip
cd /path/to/your/laravel/project
```

### 2. Install/Update Composer Dependencies

**For Development Server:**
```bash
composer install --optimize-autoloader
```

**For Production Server:**
```bash
composer install --no-dev --optimize-autoloader
```

**If the package is already installed but not working**, regenerate the autoloader:

```bash
composer dump-autoload --optimize
```

### 3. Verify ZKTeco Package Installation

Check if the package is installed:

```bash
composer show jmrashed/zkteco
```

Verify the package files exist:

```bash
ls -la vendor/jmrashed/zkteco/src/Lib/ZKTeco.php
```

### 4. Clear Laravel Caches

After installing dependencies, clear all Laravel caches:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

### 5. Regenerate Optimized Files

**For Development Server:**
```bash
# Just regenerate autoloader, don't cache config/routes/views
composer dump-autoload --optimize
```

**For Production Server:**
```bash
# Optimize everything for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 6. Verify Installation

Test that the class can be loaded:

```bash
php artisan tinker
```

Then in tinker:

```php
use Jmrashed\Zkteco\Lib\ZKTeco;
class_exists('Jmrashed\Zkteco\Lib\ZKTeco');
// Should return: true
```

## Troubleshooting

### Issue: "Class not found" error persists

**Solution 1: Regenerate autoloader**
```bash
composer dump-autoload --optimize
```

**Solution 2: Check file permissions**
```bash
chmod -R 755 vendor/
chown -R www-data:www-data vendor/  # Adjust user/group as needed
```

**Solution 3: Verify PHP extensions**
The ZKTeco package requires PHP sockets extension:
```bash
php -m | grep sockets
```

If not installed, install it:
- Ubuntu/Debian: `sudo apt-get install php-sockets`
- CentOS/RHEL: `sudo yum install php-sockets`
- Or enable in php.ini: `extension=sockets`

**Solution 4: Clear OPcache (if enabled)**
```bash
php artisan opcache:clear
# Or restart PHP-FPM
sudo service php-fpm restart
# Or restart Apache
sudo service apache2 restart
```

### Issue: Composer not found

Install Composer on the server:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Issue: Permission denied errors

Fix permissions:
```bash
sudo chown -R $USER:$USER /path/to/your/laravel/project
chmod -R 755 /path/to/your/laravel/project
```

## Development vs Production

### Development Server Setup

- **Includes dev dependencies** (testing tools, debuggers, etc.)
- **Caches are cleared** for easier debugging
- **Config/routes/views are NOT cached** - changes reflect immediately
- **Better for active development** where you're making frequent changes

Use: `./deploy-server.sh` (already configured for development)

### Production Server Setup

- **Excludes dev dependencies** (`--no-dev` flag)
- **All caches are enabled** for better performance
- **Config/routes/views are cached** - faster response times
- **Optimized for performance** and stability

To convert the script for production, change line 60 from:
```bash
composer install --optimize-autoloader --no-interaction
```
to:
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

And uncomment/re-enable the caching steps in Step 4.

## Post-Installation Checklist

- [ ] Composer dependencies installed
- [ ] ZKTeco package files exist in `vendor/jmrashed/zkteco/`
- [ ] Laravel caches cleared
- [ ] PHP sockets extension enabled
- [ ] File permissions correct
- [ ] Application tested and working

## Notes

- The `composer.lock` file should be committed to your repository to ensure consistent versions
- Always test in a staging environment before deploying to production
- Keep your `composer.json` and `composer.lock` files in sync between local and server

