<?php
return [
    'paths' => [
        'request_bill' => 'https://idsdev.nssf.go.tz/bills/post_bill',
        'direct_sms' => 'https://esb.nssf.go.tz/api/req/sms',
        'ictms_sms_notification' => 'https://ictms-api.nssf.go.tz/api/send-notification',
    ],
    
    'server_ips' => [
        'production' => '10.10.104.202',
        'production_alt' => '10.10.47.88',
        'staging' => '10.10.47.201',
        'pre_production' => '10.10.47.202',
        'development' => '10.10.47.100',
    ],
    
    'api_urls' => [
        'cfms_pro' => [
            'production' => 'https://cfmspro-api.nssf.go.tz/api/',
            'default' => 'https://cfmspre-api.nssf.go.tz/api/',
        ],
        'gepg' => [
            'production' => 'https://imsgepg.nssf.go.tz/bills/post_bill',
            'development' => 'https://idsdev.nssf.go.tz/bills/post_bill',
        ],
        'gepg_base' => [
            'production' => 'https://imsgepg.nssf.go.tz',
            'development' => 'https://idsdev.nssf.go.tz',
        ],
        'eoffice' => [
            'production' => 'https://eoffice.nssf.go.tz:8081/',
            'development' => 'https://demo-eoffice.nssf.go.tz:8082/',
        ],
        'nssf_portal' => [
            'production' => 'https://portal.nssf.go.tz/#/',
            'pre_production' => 'https://portal-pre.nssf.go.tz/#/',
        ],
        'bms_portal' => [
            'production' => env('BMS_PORTAL_URL_PRODUCTION', 'https://bms.nssf.go.tz'),
            'pre_production' => env('BMS_PORTAL_URL_PREPRODUCTION', 'https://bms-dev.nssf.go.tz'),
        ],
        'hrp_db' => [
            'production' => 'hrp2ebs ',
            'pre_production' => 'preprod2',
        ],
        'tload' => [
            'base_url' => 'http://10.10.104.202:4500/api/v1',
            'endpoints' => [
                'control_number' => '/post-control-number',
                'payment' => '/post-payment',
            ],
        ],
        'vfd' => [
            'production' => 'http://10.10.35.34:88/',
            'development' => '', // TODO: Add development URL
            'default' => 'http://10.10.35.34:88/',
        ],
    ],
    
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'nssf-app-77430'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', storage_path('app/firebase-service-account.json')),
        // Legacy support (deprecated, use service_account_path instead)
        'server_key' => env('FIREBASE_SERVER_KEY', ''),
    ],
];
