<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'https://bridge-core-dev.nssf.go.tz',
        'https://bridge-core-staging.nssf.go.tz',
        'https://bridge-core.nssf.go.tz',
        'https://bms-dev.nssf.go.tz',
        'https://bms.nssf.go.tz',
        'https://bcmspro-api.nssf.go.tz',
        'https://portal-pre.nssf.go.tz',
    ],

    // When in local environment, allow any localhost/127.0.0.1 and any 192.* addresses (optionally with ports)
    'allowed_origins_patterns' => env('APP_ENV') === 'local'
        ? [
            '#^http?://(localhost|127\.0\.0\.1)(:\\d+)?$#',
            '#^http?://192\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}(:\\d+)?$#',
        ]
        : [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-API-Key',
        'Origin',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
