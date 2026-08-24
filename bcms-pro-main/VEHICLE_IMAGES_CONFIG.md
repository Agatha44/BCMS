# Vehicle Images Configuration

## Server IP-Based Path Selection

The system automatically selects the correct image path based on the **server's IP address** (where the code is running):

- **Server IP `10.10.47.202` (Development)**: Uses `/u01/web/bcms-rest-api/web/images` (configured in `config/vehicle.php`)
- **All other server IPs (Production)**: Uses `/d01/web/bcms-rest-api/web/images` (from `.env` or config default)

## Configuration Files

### 1. `config/vehicle.php`
```php
'images' => [
    // Default paths (used for production)
    'base_path' => env('VEHICLE_IMAGES_PATH', '/d01/web/bcms-rest-api/web/images'),
    'default_image' => env('VEHICLE_DEFAULT_IMAGE', '/d01/web/bcms-rest-api/web/car.png'),
    
    // Server IP-based path configuration
    'ip_based_paths' => [
        '10.10.47.202' => [
            'base_path' => '/u01/web/bcms-rest-api/web/images',
            'default_image' => '/u01/web/bcms-rest-api/web/car.png',
        ],
        // Add more server IPs as needed
    ],
],
```

### 2. `.env` file (for production defaults)
```bash
# Vehicle Images Configuration
VEHICLE_IMAGES_PATH=/d01/web/bcms-rest-api/web/images
VEHICLE_DEFAULT_IMAGE=/d01/web/bcms-rest-api/web/car.png
VEHICLE_USE_ABSOLUTE_PATH=true
```

## How It Works

### Configuration Priority
1. **Server IP-specific config**: If server IP matches `ip_based_paths` in `config/vehicle.php`
2. **Environment config**: Falls back to `.env` variables
3. **Default config**: Uses hardcoded defaults in `config/vehicle.php`

### Adding New Server IPs
To add support for additional server IPs, edit `config/vehicle.php`:

```php
'ip_based_paths' => [
    '10.10.47.202' => [
        'base_path' => '/u01/web/bcms-rest-api/web/images',
        'default_image' => '/u01/web/bcms-rest-api/web/car.png',
    ],
    '192.168.1.100' => [
        'base_path' => '/custom/path/images',
        'default_image' => '/custom/path/car.png',
    ],
],
```

## Development vs Production

### Development Environment (Server IP: 10.10.47.202)
- **Configuration**: Set in `config/vehicle.php` under `ip_based_paths`
- **Images Path**: `/u01/web/bcms-rest-api/web/images`
- **Default Image**: `/u01/web/bcms-rest-api/web/car.png`

### Production Environment (All other server IPs)
- **Configuration**: Set via `.env` file or config defaults
- **Images Path**: `/d01/web/bcms-rest-api/web/images`
- **Default Image**: `/d01/web/bcms-rest-api/web/car.png`

## Logging

The system includes comprehensive logging for debugging and monitoring:

### Log Levels and Messages

#### **INFO Level**
- `Vehicle image request` - Initial request with server IP and path info
- `Vehicle image path resolved` - Final resolved path and file existence
- `Using IP-specific base path` - When server IP-specific config is used
- `Using default base path` - When default config is used
- `Server IP detected` - Server IP detection from various sources
- `Vehicle image successfully loaded` - Successful image loading with file details

#### **WARNING Level**
- `Vehicle image file not found, trying fallback` - When specific image doesn't exist
- `Vehicle image content file not found` - When image content file doesn't exist

#### **ERROR Level**
- `No vehicle image available (neither specific nor fallback)` - No images available
- `Failed to read vehicle image file` - File read failure
- `Exception while loading vehicle image` - Exception during image loading

### Log Data Structure

Each log entry includes relevant context:

```json
{
  "vehicle_id": 123,
  "plate_no": "T342DXZ",
  "server_ip": "10.10.47.202",
  "base_path": "/u01/web/bcms-rest-api/web/images",
  "default_image_path": "/u01/web/bcms-rest-api/web/car.png",
  "vehicle_image_field": "T342DXZ:2022-02-06 14:31:51:197.jpg",
  "resolved_path": "/u01/web/bcms-rest-api/web/images/:T342DXZ:2022-02-06 14:31:51:197.jpg",
  "file_exists": true,
  "file_size": 45678,
  "image_type": "jpg"
}
```

### Monitoring

To monitor vehicle image requests, search logs for:
- `Vehicle image request` - All image requests
- `server_ip` - Track specific server IPs
- `file_exists: false` - Missing images
- `Exception while loading` - Errors

## Benefits

- ✅ **Configuration-Driven**: All paths managed in config files
- ✅ **Easy Maintenance**: Add/remove IPs by editing config
- ✅ **Environment Flexibility**: Different configs for different environments
- ✅ **No Hardcoding**: All paths are configurable
- ✅ **Proxy Support**: Works behind load balancers and proxies
- ✅ **Fallback Handling**: Graceful fallback when images don't exist
