<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vehicle Images Configuration
    |--------------------------------------------------------------------------
    |
    | Base path defaults to storage/app/vehicle-images (writable). Override VEHICLE_IMAGES_PATH in production
    | when images must live on a shared legacy path (e.g. /d01/web/.../images).
    |
    */

    'images' => [
        // Writable default for local/dev; production: set VEHICLE_IMAGES_PATH to your shared image root (e.g. /d01/web/.../images).
        'base_path' => env('VEHICLE_IMAGES_PATH', storage_path('app/vehicle-images')),

        // Shown when a vehicle has no image file; bundled asset avoids missing /d01/.../car.png on dev machines.
        'default_image' => env('VEHICLE_DEFAULT_IMAGE', public_path('images/collection/no_vehicle_image.png')),

        // Bundled "no vehicle image" for collection-management APIs (readable on all hosts)
        'collection_placeholder' => env(
            'VEHICLE_COLLECTION_PLACEHOLDER',
            public_path('images/collection/no_vehicle_image.png')
        ),
        
        // Max vehicle image size (multipart file rule uses kilobytes).
        'max_file_upload_kb' => (int) env('VEHICLE_MAX_IMAGE_FILE_KB', 20480),

        // Max JSON/base64 payload length (approx. 4/3 of raw bytes in base64).
        'max_base64_chars' => (int) env('VEHICLE_MAX_BASE64_CHARS', 30000000),

        // Max decoded image size before/after conversion (bytes).
        'max_decoded_bytes' => (int) env('VEHICLE_MAX_IMAGE_BYTES', 26214400),

        // PNG compression 0 (larger files, fast) .. 9 (smallest). Lower = visually larger files on disk.
        'png_compression' => max(0, min(9, (int) env('VEHICLE_PNG_COMPRESSION', 2))),

        // Whether to use absolute or relative paths
        'use_absolute_path' => env('VEHICLE_USE_ABSOLUTE_PATH', true),

        // Server IP-based path configuration
        'ip_based_paths' => [
            // Development server IP - use the working dev path
            // IP loaded from params config
            'pre_production' => [
                'base_path' => '/u01/web/bcms-rest-api/web/images',
                'default_image' => '/u01/web/bcms-rest-api/web/car.png',
            ],
            // Add more server IPs as needed
            // 'custom_env' => [
            //     'base_path' => '/custom/path/images',
            //     'default_image' => '/custom/path/car.png',
            // ],
        ],
    ],
];
