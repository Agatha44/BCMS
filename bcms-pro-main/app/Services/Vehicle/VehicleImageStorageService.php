<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VehicleImageStorageService
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Store an uploaded image and return the value for {@see Vehicle::$image} (filename under base path).
     *
     * @throws \InvalidArgumentException
     */
    public function storeUploadedFile(int $vehicleId, UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \InvalidArgumentException('Unsupported image type');
        }

        $binary = @file_get_contents($file->getRealPath());
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Empty image upload');
        }

        return $this->storeBinary($vehicleId, $binary, $ext);
    }

    /**
     * Decode a data URI or raw base64 string, convert to PNG on disk, return DB field value (filename under base path).
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function storeFromBase64Payload(int $vehicleId, string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new \InvalidArgumentException('Empty image payload');
        }

        if (str_starts_with($payload, 'data:')) {
            if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $payload, $m)) {
                throw new \InvalidArgumentException('Invalid data URI');
            }
            $b64 = $m[2];
        } else {
            $b64 = $payload;
        }

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Invalid base64 image');
        }

        if (@getimagesizefromstring($binary) === false) {
            throw new \InvalidArgumentException('Decoded data is not a valid image');
        }

        $pngBytes = $this->decodedRasterToPngBytes($binary);

        return $this->storeBinary($vehicleId, $pngBytes, 'png');
    }

    /**
     * Re-encode arbitrary raster bytes (JPEG/PNG/GIF/WebP when GD supports them) as PNG.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    private function decodedRasterToPngBytes(string $binary): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new \RuntimeException('PHP GD extension is required to convert vehicle images to PNG (enable ext-gd).');
        }

        $im = @imagecreatefromstring($binary);
        if ($im === false) {
            throw new \InvalidArgumentException('Could not decode image for PNG conversion');
        }

        if (function_exists('imagepalettetotruecolor') && !imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }

        imagesavealpha($im, true);

        $png = null;
        ob_start();
        try {
            $compression = max(0, min(9, (int) config('vehicle.images.png_compression', 2)));
            if (!imagepng($im, null, $compression)) {
                ob_end_clean();
                throw new \RuntimeException('Failed to encode PNG');
            }
            $png = ob_get_clean();
        } finally {
            imagedestroy($im);
        }

        if (!is_string($png) || $png === '') {
            throw new \RuntimeException('Failed to encode PNG');
        }

        return $png;
    }

    /**
     * Remove a previously stored file if it lives under the configured vehicle image directory.
     */
    public function deleteStoredFileIfManaged(?string $imageFieldValue): void
    {
        if ($imageFieldValue === null || trim($imageFieldValue) === '') {
            return;
        }

        $path = $this->absolutePathFromImageField(trim($imageFieldValue));
        if ($path === null || !is_file($path)) {
            return;
        }

        $base = realpath(Vehicle::getImageBasePath());
        $dir = realpath(dirname($path));
        if ($base === false || $dir === false) {
            return;
        }

        $candidate = $dir . DIRECTORY_SEPARATOR . basename($path);
        if (!str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        @unlink($path);
    }

    private function storeBinary(int $vehicleId, string $binary, string $ext): string
    {
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        if (strlen($binary) > (int) config('vehicle.images.max_decoded_bytes', 26214400)) {
            throw new \InvalidArgumentException('Image too large');
        }

        $dir = Vehicle::getImageBasePath();
        if (!File::isDirectory($dir)) {
            try {
                File::makeDirectory($dir, 0755, true);
            } catch (\Throwable $e) {
                Log::error('VehicleImageStorageService: cannot create vehicle image directory', [
                    'dir' => $dir,
                    'message' => $e->getMessage(),
                ]);
                throw new \RuntimeException(
                    'Cannot create vehicle image directory. Set VEHICLE_IMAGES_PATH in .env to a path the web server user can write to, or fix permissions on '
                    . $dir
                    . ' (original error: ' . $e->getMessage() . ')',
                    0,
                    $e
                );
            }
        }

        $safe = 'v_' . $vehicleId . '_' . Str::random(12) . '.' . $ext;
        $full = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe;

        if (file_put_contents($full, $binary) === false) {
            Log::error('VehicleImageStorageService: failed to write file', ['path' => $full]);

            throw new \RuntimeException('Failed to save vehicle image');
        }

        if (strlen($safe) > 500) {
            @unlink($full);
            throw new \RuntimeException('Generated image path exceeds column limit');
        }

        return $safe;
    }

    private function absolutePathFromImageField(string $imageFieldValue): ?string
    {
        $basePath = Vehicle::getImageBasePath();

        if (strpos($imageFieldValue, '/') === 0) {
            return $imageFieldValue;
        }

        if (strpos($imageFieldValue, ':') === 0) {
            return $basePath . '/' . substr($imageFieldValue, 1);
        }

        return $basePath . '/' . $imageFieldValue;
    }
}
