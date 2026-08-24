<?php

namespace App\Http\Controllers\Configurations;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

class ConfigurationController extends Controller
{
    /**
     * Input for vehicle update validation. Merges JSON or urlencoded body when clients send PUT/PATCH
     * without Content-Type (common with fetch/axios); route id wins over body for vehicle_id.
     *
     * @return array<string, mixed>
     */
    protected function vehicleUpdatePayload(Request $request, ?int $vehicleIdFromRoute = null): array
    {
        $payload = $this->jsonRequestPayload($request);

        $content = $request->getContent();
        if ($content !== '' && $content !== false) {
            if (
                in_array(strtoupper($request->getMethod()), ['PUT', 'PATCH'], true)
                && str_contains(strtolower((string) $request->header('Content-Type', '')), 'application/x-www-form-urlencoded')
            ) {
                parse_str($content, $parsed);
                if (is_array($parsed)) {
                    $payload = array_merge($payload, $parsed);
                }
            }
        }

        if ($vehicleIdFromRoute !== null) {
            $payload['vehicle_id'] = $vehicleIdFromRoute;
        }

        return $payload;
    }

    /**
     * Merge Laravel-parsed input with a JSON request body (POST/PUT/PATCH).
     * Booth/ANPR clients often send large base64 images; reading raw content ensures
     * fields are available even when Content-Type is missing or Laravel's bag is empty.
     *
     * @return array<string, mixed>
     */
    protected function jsonRequestPayload(Request $request): array
    {
        $payload = $request->all();

        if ($request->isJson()) {
            $jsonBag = $request->json();
            if ($jsonBag !== null) {
                $decoded = $jsonBag->all();
                if ($decoded !== []) {
                    $payload = array_merge($payload, $decoded);
                }
            }
        }

        $formFields = $request->request->all();
        if ($formFields !== []) {
            $payload = array_merge($payload, $formFields);
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        if (str_contains($contentType, 'multipart/form-data')) {
            return $payload;
        }

        $content = $request->getContent();
        if (! is_string($content) || $content === '') {
            return $payload;
        }

        $trimmed = ltrim($content);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $payload;
        }

        $decoded = json_decode($content, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Raw body wins over Laravel's parsed bag (large base64 strings are sometimes dropped).
            $payload = array_merge($payload, $decoded);
        }

        return $payload;
    }

    /**
     * Input for toll capture (booth/ANPR). Same JSON/urlencoded merge as {@see vehicleUpdatePayload}.
     *
     * @return array<string, mixed>
     */
    protected function tollCapturePayload(Request $request): array
    {
        $payload = $this->jsonRequestPayload($request);

        $content = $request->getContent();
        if ($content !== '' && $content !== false) {
            if (
                in_array(strtoupper($request->getMethod()), ['PUT', 'PATCH'], true)
                && str_contains(strtolower((string) $request->header('Content-Type', '')), 'application/x-www-form-urlencoded')
            ) {
                parse_str($content, $parsed);
                if (is_array($parsed)) {
                    $payload = array_merge($payload, $parsed);
                }
            }
        }

        foreach (['lane_id', 'shift_id', 'user_id', 'body_type_id', 'toll_capture_id'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                continue;
            }
            if (ctype_digit($payload[$key])) {
                $payload[$key] = (int) $payload[$key];
            }
        }

        return $this->applyCaptureImageLegacyAliases($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyCaptureImageLegacyAliases(array $payload): array
    {
        $map = [
            'image' => 'capture_image',
            'vehicle_image' => 'capture_image',
            'image_base64' => 'capture_image_base64',
            'vehicle_image_base64' => 'capture_image_base64',
        ];

        foreach ($map as $from => $to) {
            $candidate = $payload[$from] ?? null;
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            $existing = $payload[$to] ?? null;
            if (is_string($existing) && trim($existing) !== '') {
                continue;
            }
            $payload[$to] = $candidate;
        }

        return $payload;
    }

    /**
     * Rules for capture_image: multipart file, or JSON base64 / data URI (mirrors {@see vehicleImageFieldRules}).
     *
     * @return array<int, string>
     */
    protected function captureImageFieldRules(Request $request): array
    {
        if ($request->hasFile('capture_image')) {
            $maxKb = (int) config('toll_capture.images.max_file_upload_kb', 20480);

            return ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:' . $maxKb];
        }

        $maxB64 = (int) config('toll_capture.images.max_base64_chars', 30000000);

        return ['nullable', 'string', 'max:' . $maxB64];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     */
    protected function validateWithCaptureImageExplained(Request $request, array $payload, array $rules): ValidatorInstance
    {
        if ($request->hasFile('capture_image')) {
            $file = $request->file('capture_image');
            if ($file instanceof \Illuminate\Http\UploadedFile && ! $file->isValid()) {
                unset($payload['capture_image'], $rules['capture_image']);
                $validator = Validator::make($payload, $rules);
                $validator->after(function (ValidatorInstance $validator) use ($file) {
                    $validator->errors()->add('capture_image', $this->messageForInvalidCaptureImageUpload($file));
                });

                return $validator;
            }
        }

        return Validator::make($payload, $rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolvedCaptureImageBase64Payload(array $validated): ?string
    {
        foreach (['capture_image_base64', 'capture_image'] as $key) {
            $value = $validated[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function messageForInvalidCaptureImageUpload(\Illuminate\Http\UploadedFile $file): string
    {
        $detail = $file->getErrorMessage();
        $code = (int) $file->getError();

        $head = 'Capture image upload failed at PHP/SAPI level'
            . ($detail !== '' ? ': ' . $detail : '')
            . " (upload error code {$code}). ";

        if ($code === \UPLOAD_ERR_NO_TMP_DIR) {
            return $head
                . 'Server configuration: PHP has no usable temporary directory for multipart file uploads (UPLOAD_ERR_NO_TMP_DIR). '
                . 'Fix on the host: set `upload_tmp_dir` in php.ini to a writable path. '
                . 'Client workaround: send `application/json` with `capture_image` as base64/data URI, or use `capture_image_base64`.';
        }

        if ($code === \UPLOAD_ERR_CANT_WRITE) {
            return $head
                . 'PHP could not write the upload to disk. Alternatively send `capture_image` or `capture_image_base64` as a JSON base64 string.';
        }

        if ($code === \UPLOAD_ERR_INI_SIZE || $code === \UPLOAD_ERR_FORM_SIZE) {
            return $head
                . 'The file exceeds `upload_max_filesize` or `post_max_size`. Alternatively send `capture_image` or `capture_image_base64` as a JSON base64 string.';
        }

        if ($code === \UPLOAD_ERR_PARTIAL) {
            return $head
                . 'The upload was truncated. Retry or send `capture_image` as a JSON base64 string.';
        }

        return $head
            . 'Use field name `capture_image` and POST `/api/toll-capture`, '
            . 'or send JSON with `capture_image` (base64 or data URI) or `capture_image_base64`.';
    }

    /**
     * When required JSON fields are missing but the client sent a large body, explain why
     * (truncated JSON, post_max_size, etc.) instead of a generic "field required" error.
     *
     * @param  list<string>  $requiredKeys
     */
    protected function jsonBodyParseErrorResponse(
        Request $request,
        array $payload,
        array $requiredKeys,
        ?string $bodyNotReceivedHint = null
    ): ?JsonResponse {
        $missingRequired = false;
        foreach ($requiredKeys as $key) {
            $value = $payload[$key] ?? null;
            if ($value === null || $value === '') {
                $missingRequired = true;
                break;
            }
        }

        if (! $missingRequired) {
            return null;
        }

        $content = $request->getContent();
        $rawLen = is_string($content) ? strlen($content) : 0;
        $contentLength = (int) $request->header('Content-Length', 0);
        $postMaxBytes = $this->parseIniSize((string) ini_get('post_max_size'));
        $contentType = (string) $request->header('Content-Type', '');
        $defaultBodyHint = 'Send the request as application/json with Content-Type: application/json. '
            . 'For large base64 images, ensure post_max_size, upload_max_filesize, and proxy client_max_body_size are large enough.';

        if ($contentLength > 1024 && $rawLen === 0) {
            if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
                return $this->sendError('Request body too large or not received', [
                    'message' => 'PHP discarded the POST body before Laravel could read it. Increase post_max_size and upload_max_filesize on the server (and nginx client_max_body_size if applicable).',
                    'content_length_header' => $contentLength,
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                ], 0, 413);
            }

            $underPhpLimit = $postMaxBytes > 0 && $contentLength <= $postMaxBytes;
            $proxyHint = $underPhpLimit
                ? 'Content-Length is ' . number_format($contentLength) . ' bytes but PHP post_max_size is '
                . ini_get('post_max_size') . ' — the body was dropped by nginx/Apache/proxy (not PHP). '
                . 'Set nginx `client_max_body_size 32m;` (or raise Apache LimitRequestBody), reload the web server, and retry. '
                . 'Workaround without changing nginx: POST /api/toll-capture with plate_no/lane only (no image), '
                . 'then POST /api/toll-capture/image with toll_capture_id and capture_image_base64.'
                : null;

            return $this->sendError('Request body was not received', [
                'message' => $proxyHint ?? ($bodyNotReceivedHint ?? $defaultBodyHint),
                'content_length_header' => $contentLength,
                'content_type' => $contentType !== '' ? $contentType : null,
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'parsed_fields' => array_keys($payload),
            ]);
        }

        if ($rawLen > 512) {
            json_decode($content, true);
            $jsonError = json_last_error_msg();

            if ($jsonError !== 'No error') {
                return $this->sendError('Invalid JSON request body', [
                    'message' => $jsonError,
                    'body_bytes' => $rawLen,
                    'hint' => 'Keep the base64 image on one line inside the JSON string, close all quotes, and send Content-Type: application/json.',
                ]);
            }
        }

        return null;
    }

    protected function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    /**
     * When PHP marks the upload invalid, Laravel's file rules only report "failed to upload".
     * Strip the broken file from validation data, omit file rules, then add one clear message with the SAPI error.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     */
    protected function validateWithVehicleImageExplained(Request $request, array $payload, array $rules): ValidatorInstance
    {
        if ($request->hasFile('vehicle_image')) {
            $file = $request->file('vehicle_image');
            if ($file instanceof \Illuminate\Http\UploadedFile && !$file->isValid()) {
                unset($payload['vehicle_image'], $rules['vehicle_image']);
                $validator = Validator::make($payload, $rules);
                $validator->after(function (ValidatorInstance $validator) use ($file) {
                    $validator->errors()->add('vehicle_image', $this->messageForInvalidVehicleImageUpload($file));
                });

                return $validator;
            }
        }

        return Validator::make($payload, $rules);
    }

    /**
     * Rules for vehicle_image: multipart file, or JSON string (base64 / data URI) when not using PHP file upload.
     *
     * @return array<int, string>
     */
    protected function vehicleImageFieldRules(Request $request): array
    {
        if ($request->hasFile('vehicle_image')) {
            $maxKb = (int) config('vehicle.images.max_file_upload_kb', 20480);

            return ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:' . $maxKb];
        }

        $maxB64 = (int) config('vehicle.images.max_base64_chars', 30000000);

        return ['nullable', 'string', 'max:' . $maxB64];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolvedVehicleImageBase64Payload(array $validated): ?string
    {
        foreach (['vehicle_image_base64', 'vehicle_image'] as $key) {
            $v = $validated[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return $v;
            }
        }

        return null;
    }

    protected function messageForInvalidVehicleImageUpload(\Illuminate\Http\UploadedFile $file): string
    {
        $detail = $file->getErrorMessage();
        $code = (int) $file->getError();

        $head = 'Vehicle image upload failed at PHP/SAPI level'
            . ($detail !== '' ? ': ' . $detail : '')
            . " (upload error code {$code}). ";

        if ($code === \UPLOAD_ERR_NO_TMP_DIR) {
            return $head
                . 'Server configuration: PHP has no usable temporary directory for multipart file uploads (UPLOAD_ERR_NO_TMP_DIR). '
                . 'Fix on the host: set `upload_tmp_dir` in php.ini to a writable path (this app also tries `storage/framework/upload-tmp` at boot if allowed). '
                . 'Client workaround: send the same update as `application/json` and put the image in `vehicle_image` as a base64 or `data:image/...;base64,...` string (no multipart file), or use `vehicle_image_base64`; the API still saves a file and stores the path in the database.';
        }

        if ($code === \UPLOAD_ERR_CANT_WRITE) {
            return $head
                . 'PHP could not write the upload to disk (permissions, full disk, or security policy). '
                . 'Check the upload temp directory and disk space. Alternatively send `vehicle_image` or `vehicle_image_base64` as a JSON base64 string.';
        }

        if ($code === \UPLOAD_ERR_INI_SIZE || $code === \UPLOAD_ERR_FORM_SIZE) {
            return $head
                . 'The file exceeds `upload_max_filesize` or `post_max_size` (or form/proxy limits). Increase limits on the server or send a smaller file. '
                . 'Alternatively send `vehicle_image` or `vehicle_image_base64` as a JSON base64 string.';
        }

        if ($code === \UPLOAD_ERR_PARTIAL) {
            return $head
                . 'The upload was truncated (network, timeout, or proxy). Retry, check proxy limits, or send `vehicle_image` as a JSON base64 string.';
        }

        return $head
            . 'Common causes: file larger than `upload_max_filesize` or `post_max_size`, or proxy `client_max_body_size`; '
            . 'or the body was not `multipart/form-data`. Use field name `vehicle_image` and POST `/api/vehicles/update` '
            . 'or POST `/api/collection-management/vehicles/{id}/update` (not PUT). '
            . 'Alternatively send JSON with `vehicle_image` (base64 or data URI) or `vehicle_image_base64`; the file is written to disk and the path saved on the vehicle row.';
    }

    protected function createSingleAccessToken(Model $tokenable, string $tokenName): string
    {
        return DB::transaction(function () use ($tokenable, $tokenName) {
            $tokenableClass = get_class($tokenable);

            $lockedTokenable = $tokenableClass::query()
                ->whereKey($tokenable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTokenable->tokens()->delete();

            return $lockedTokenable->createToken($tokenName)->plainTextToken;
        });
    }

    /**
     * success response method.
     *
     * @param $result
     * @param $message
     * @return JsonResponse
     */
    public function sendResponse($result, $message): JsonResponse
    {
        $response = [
            'success' => true,
            'status_code' => 1,
            'data'    => $result,
            'message' => $message,
        ];
        return response()->json($response);
    }

    /**
     * return error response.
     *
     * @param $error
     * @param array $errorMessages
     * @param int $code
     * @return JsonResponse
     */
    public function sendError($error, array $errorMessages = [], $error_code = 0, $http_status_code = 200): JsonResponse
    {
        $response = [
            'success' => false,
            'status_code' => $error_code,
            'message' => $error,
        ];
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
        return response()->json($response)->setStatusCode($http_status_code);
    }
}
