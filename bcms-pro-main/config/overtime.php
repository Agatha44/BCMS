<?php

return [
    'submission_documents' => [
        'disk' => env('OVERTIME_SUBMISSION_DOCUMENTS_DISK', 'local'),
        'directory' => env('OVERTIME_SUBMISSION_DOCUMENTS_DIRECTORY', 'overtime/submission-documents'),
        'max_file_size_kb' => (int) env('OVERTIME_SUBMISSION_DOCUMENTS_MAX_KB', 10240),
        'allowed_mimes' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],

        'types' => [
            'approval_memo',
        ],

        'overlap_policy' => 'reject',
        'selection_rule' => 'newest_upload_wins',
    ],

    'eoffice_attachments' => [
        [
            'document_type' => 'approval_memo',
            'document_name' => 'Approval Memo',
            'attachment_flag' => 'SUPPORTIVE',
            'file_name_prefix' => 'Approval_Memo_',
            'required' => true,
        ],
    ],
];
