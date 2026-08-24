<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Toll Capture Images (same pattern as vehicle.images)
    |--------------------------------------------------------------------------
    |
    | When TOLL_CAPTURE_IMAGES_PATH is unset, files are stored under the same
    | directory as vehicle images (Vehicle::getImageBasePath()).
    |
    */

    'images' => [
        'base_path' => env('TOLL_CAPTURE_IMAGES_PATH'),

        'max_file_upload_kb' => (int) env('TOLL_CAPTURE_MAX_FILE_UPLOAD_KB', 20480),
        'max_base64_chars' => (int) env('TOLL_CAPTURE_MAX_BASE64_CHARS', 30000000),
        'max_decoded_bytes' => (int) env('TOLL_CAPTURE_MAX_IMAGE_BYTES', 26214400),
        'png_compression' => max(0, min(9, (int) env('TOLL_CAPTURE_PNG_COMPRESSION', 2))),
    ],
];
