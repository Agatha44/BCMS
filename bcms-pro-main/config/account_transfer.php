<?php

return [
    'approval_document' => [
        'disk' => env('ACCOUNT_TRANSFER_DOCUMENT_DISK', 'local'),
        'directory' => env('ACCOUNT_TRANSFER_DOCUMENT_DIRECTORY', 'account-transfers'),
        'max_file_size_kb' => (int) env('ACCOUNT_TRANSFER_DOCUMENT_MAX_KB', 10240),
        'allowed_mimes' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],
    ],
];
