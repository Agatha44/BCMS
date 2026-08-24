<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\TollTransaction;
use App\Models\BundleSubscription;
use App\Models\BodyType;

class Vehicle extends Model
{
    use HasFactory;
    protected $table = 'vehicle';

    public function passages()
    {
        return $this->hasMany(TollTransaction::class, 'vehicle_id', 'id');
    }

    public function bundles()
    {
        return $this->hasMany(BundleSubscription::class, 'vehicle_id', 'id')->with('tollBundle');
    }

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class, 'body_type_id', 'id')->with('price');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_no', 'account_no');
    }

    public function accountVehicles()
    {
        return $this->hasMany(AccountVehicle::class, 'vehicle_id', 'id');
    }

    public static function getImageBase64($vehicle_id): ?string
    {
        $vehicle = Vehicle::where('id', $vehicle_id)->first();
        if ($vehicle == null) {
            return null;
        }

        $basePath = self::getImageBasePath();
        $defaultImage = self::getDefaultImagePath();

        $path = null;
        
        if (!is_null($vehicle->image) && !empty(trim($vehicle->image))) {
            $imagePath = trim($vehicle->image);
            
            // Check if it's an absolute path (starts with /)
            if (strpos($imagePath, '/') === 0) {
                $path = $imagePath;
            } 
            // Check if it starts with ':' (format like :filename.png)
            elseif (strpos($imagePath, ':') === 0) {
                $filename = substr($imagePath, 1); // Remove the leading ':'
                $path = $basePath . '/' . $filename;
            }
            // Otherwise, treat it as a relative filename
            else {
                $path = $basePath . '/' . $imagePath;
            }
        } else {
            $path = $defaultImage;
        }

        // Check if file exists before trying to read it
        if (!file_exists($path)) {
            // Log for debugging
                Log::warning('Vehicle image file not found', [
                'vehicle_id' => $vehicle_id,
                'image_field' => $vehicle->image,
                'constructed_path' => $path,
                'base_path' => $basePath,
                'default_image' => $defaultImage
            ]);
            
            // Fallback to default car image if the specific image doesn't exist
            if (file_exists($defaultImage)) {
                $path = $defaultImage;
            } else {
                return null; // Return null if no image is available
            }
        }

        try {
            $fileContents = file_get_contents($path);
            if ($fileContents === false) {
                return null;
            }
            return 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($fileContents);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getImageContent($vehicle_id): ?string
    {
        $vehicle = Vehicle::where('id', $vehicle_id)->first();
        if ($vehicle == null) {
            return null;
        }

        $basePath = self::getImageBasePath();
        $defaultImage = self::getDefaultImagePath();

        $path = null;
        
        if (!is_null($vehicle->image) && !empty(trim($vehicle->image))) {
            $imagePath = trim($vehicle->image);
            
            // Check if it's an absolute path (starts with /)
            if (strpos($imagePath, '/') === 0) {
                $path = $imagePath;
            } 
            // Check if it starts with ':' (format like :filename.png)
            elseif (strpos($imagePath, ':') === 0) {
                $filename = substr($imagePath, 1); // Remove the leading ':'
                $path = $basePath . '/' . $filename;
            }
            // Otherwise, treat it as a relative filename
            else {
                $path = $basePath . '/' . $imagePath;
            }
        } else {
            $path = $defaultImage;
        }

        if (!file_exists($path)) {
            return null;
        }

        try {
            $fileContents = file_get_contents($path);
            return $fileContents !== false ? $fileContents : null;
        } catch (\Exception $e) {
            return null;
        }
    }


    public static function getImageStorageBasePath(): string
    {
        return self::getImageBasePath();
    }


    public static function getImageBasePath(): string
    {
        $serverIp = self::getServerIp();
        $ipBasedPaths = config('vehicle.images.ip_based_paths', []);
        
        // Check if there's a specific configuration for this server IP
        if (isset($ipBasedPaths[$serverIp])) {
            return $ipBasedPaths[$serverIp]['base_path'];
        }
        
        // Use default production path for all other server IPs
        return config('vehicle.images.base_path', '/d01/web/bcms-rest-api/web/images');
    }

    /**
     * Get the default image path based on server IP
     */
    private static function getDefaultImagePath(): string
    {
        $serverIp = self::getServerIp();
        $ipBasedPaths = config('vehicle.images.ip_based_paths', []);
        
        // Check if there's a specific configuration for this server IP
        if (isset($ipBasedPaths[$serverIp])) {
            return $ipBasedPaths[$serverIp]['default_image'];
        }
        
        // Use default production path for all other server IPs
        return config('vehicle.images.default_image', '/d01/web/bcms-rest-api/web/car.png');
    }

    /**
     * Get the server's IP address
     */
    private static function getServerIp(): string
    {
        // Try to get server IP from various sources
        $serverIpSources = [
            $_SERVER['SERVER_ADDR'] ?? null,           // Server's IP address
            $_SERVER['HTTP_HOST'] ?? null,            // Host header
            gethostbyname(gethostname()),             // Server's hostname IP
            gethostbyname($_SERVER['SERVER_NAME'] ?? 'localhost'), // Server name IP
        ];

        foreach ($serverIpSources as $source) {
            if (!empty($source) && filter_var($source, FILTER_VALIDATE_IP)) {
                return $source;
            }
        }

        // Fallback to localhost
        return '127.0.0.1';
    }

    /**
     * Get the client's IP address
     */
    private static function getClientIp(): string
    {
        // Try to get IP from various headers (for load balancers, proxies, etc.)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Image payload for collection-management detail API: raw base64 + content-type.
     * Uses the same on-disk resolution rules as {@see getImageBase64()}; if that yields nothing,
     * returns the bundled placeholder (cached so the file is not re-read every request).
     *
     * @return array{content_type: string, base64: string, is_placeholder: bool}
     */
    public static function getCollectionManagementImagePayload(?int $vehicleId): array
    {
        if ($vehicleId) {
            $dataUri = self::getImageBase64($vehicleId);
            if ($dataUri !== null && $dataUri !== '') {
                return [
                    'content_type' => self::mimeFromDataUri($dataUri) ?? 'image/png',
                    'base64' => self::base64FromDataUri($dataUri),
                    'is_placeholder' => false,
                ];
            }
        }

        $cached = self::getCachedCollectionPlaceholderPayload();

        return [
            'content_type' => $cached['content_type'],
            'base64' => $cached['base64'],
            'is_placeholder' => true,
        ];
    }

    /**
     * @return array{content_type: string, base64: string}
     */
    private static function getCachedCollectionPlaceholderPayload(): array
    {
        return Cache::rememberForever('vehicle.collection_placeholder.payload_v1', function () {
            $path = config('vehicle.images.collection_placeholder');
            if (!is_string($path) || $path === '' || !is_readable($path)) {
                Log::warning('Vehicle collection placeholder image missing or unreadable', ['path' => $path]);

                return ['content_type' => 'image/png', 'base64' => ''];
            }

            $binary = @file_get_contents($path);
            if ($binary === false || $binary === '') {
                return ['content_type' => 'image/png', 'base64' => ''];
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            return [
                'content_type' => $mime,
                'base64' => base64_encode($binary),
            ];
        });
    }

    private static function mimeFromDataUri(string $dataUri): ?string
    {
        return preg_match('#^data:([^;]+);base64,#', $dataUri, $m) ? $m[1] : null;
    }

    private static function base64FromDataUri(string $dataUri): string
    {
        $pos = strpos($dataUri, 'base64,');
        if ($pos === false) {
            return '';
        }

        return substr($dataUri, $pos + 7);
    }

}
