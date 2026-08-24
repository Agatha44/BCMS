<?php

namespace App\Services\TollCapture;

use App\Models\TollCapture;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TollCaptureImageStorageService
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Decode base64/data-URI, convert to PNG on disk (same as vehicle images), return DB filename.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function storeFromBase64Payload(string $plateNo, ?string $laneNumber, string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new \InvalidArgumentException('Empty image payload');
        }

        if (str_starts_with($payload, 'data:')) {
            if (! preg_match('#^data:([^;]+);base64,(.+)$#s', $payload, $m)) {
                throw new \InvalidArgumentException('Invalid data URI');
            }
            $b64 = $m[2];
        } else {
            $b64 = $payload;
        }

        $b64 = preg_replace('/\s+/', '', $b64) ?? '';

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Invalid base64 image');
        }

        if (@getimagesizefromstring($binary) === false) {
            throw new \InvalidArgumentException('Decoded data is not a valid image');
        }

        $pngBytes = $this->decodedRasterToPngBytes($binary);

        return $this->storeBinary($plateNo, $laneNumber, $pngBytes, 'png');
    }

    /**
     * Store a multipart upload (filename under base path, same as vehicle images).
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function storeFromUploadedFile(string $plateNo, ?string $laneNumber, UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Invalid image upload: ' . $file->getErrorMessage());
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \InvalidArgumentException('Unsupported image type');
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Empty image upload');
        }

        return $this->storeBinary($plateNo, $laneNumber, $binary, $ext);
    }

    public function absolutePathFromStoredValue(?string $storedValue): ?string
    {
        if ($storedValue === null || trim($storedValue) === '') {
            return null;
        }

        $path = $this->absolutePathFromImageField(trim($storedValue));

        return ($path !== null && is_file($path)) ? $path : null;
    }

    /**
     * Remove a previously stored file if it lives under the configured image directory.
     */
    public function deleteStoredFileIfManaged(?string $storedValue): void
    {
        if ($storedValue === null || trim($storedValue) === '') {
            return;
        }

        $path = $this->absolutePathFromImageField(trim($storedValue));
        if ($path === null || ! is_file($path)) {
            return;
        }

        $base = realpath(TollCapture::getImageBasePath());
        $dir = realpath(dirname($path));
        if ($base === false || $dir === false) {
            return;
        }

        $candidate = $dir . DIRECTORY_SEPARATOR . basename($path);
        if (! str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        @unlink($path);
    }

    /**
     * @throws \InvalidArgumentException|\RuntimeException
     */
    private function decodedRasterToPngBytes(string $binary): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            throw new \RuntimeException('PHP GD extension is required to convert toll capture images to PNG (enable ext-gd).');
        }

        $im = @imagecreatefromstring($binary);
        if ($im === false) {
            throw new \InvalidArgumentException('Could not decode image for PNG conversion');
        }

        if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }

        imagealphablending($im, false);
        imagesavealpha($im, true);

        $png = null;
        ob_start();
        try {
            $compression = max(0, min(9, (int) config('toll_capture.images.png_compression', 2)));
            if (! imagepng($im, null, $compression)) {
                ob_end_clean();
                throw new \RuntimeException('Failed to encode PNG');
            }
            $png = ob_get_clean();
        } finally {
            imagedestroy($im);
        }

        if (! is_string($png) || $png === '') {
            throw new \RuntimeException('Failed to encode PNG');
        }

        return $png;
    }

    private function storeBinary(string $plateNo, ?string $laneNumber, string $binary, string $ext): string
    {
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $maxBytes = (int) config('toll_capture.images.max_decoded_bytes', 10485760);
        if (strlen($binary) > $maxBytes) {
            throw new \InvalidArgumentException('Image too large');
        }

        $dir = TollCapture::getImageBasePath();
        if (! File::isDirectory($dir)) {
            try {
                File::makeDirectory($dir, 0755, true);
            } catch (\Throwable $e) {
                Log::error('TollCaptureImageStorageService: cannot create image directory', [
                    'dir' => $dir,
                    'message' => $e->getMessage(),
                ]);
                throw new \RuntimeException(
                    'Cannot create toll capture image directory. Set TOLL_CAPTURE_IMAGES_PATH in .env to a writable path, or fix permissions on '
                    . $dir
                    . ' (original error: ' . $e->getMessage() . ')',
                    0,
                    $e
                );
            }
        }

        $filename = $this->buildFilename($plateNo, $laneNumber, $ext);
        $full = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($full, $binary) === false) {
            Log::error('TollCaptureImageStorageService: failed to write file', ['path' => $full]);
            throw new \RuntimeException('Failed to save toll capture image');
        }

        if (strlen($filename) > 500) {
            @unlink($full);
            throw new \RuntimeException('Generated image path exceeds column limit');
        }

        return $filename;
    }

    private function buildFilename(string $plateNo, ?string $laneNumber, string $ext): string
    {
        $safePlate = preg_replace('/[^A-Z0-9]/', '', strtoupper($plateNo)) ?: 'UNKNOWN';
        $lane = $laneNumber !== null && $laneNumber !== ''
            ? preg_replace('/[^A-Z0-9]/', '', strtoupper($laneNumber))
            : '0';

        return sprintf(
            'tc_%s_L%s_%s_%s.%s',
            $safePlate,
            $lane,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $ext
        );
    }

    private function absolutePathFromImageField(string $imageFieldValue): ?string
    {
        $basePath = TollCapture::getImageBasePath();

        if (strpos($imageFieldValue, '/') === 0) {
            return $imageFieldValue;
        }

        if (strpos($imageFieldValue, ':') === 0) {
            return $basePath . '/' . substr($imageFieldValue, 1);
        }

        return $basePath . '/' . $imageFieldValue;
    }
}
