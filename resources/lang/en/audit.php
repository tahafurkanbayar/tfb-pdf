<?php

// Display names of audit event types and statuses
return [
    'events' => [
        'upload' => 'Upload',
        'merge' => 'Merge',
        'split' => 'Split',
        'reorder' => 'Page reorder',
        'rotate' => 'Rotate',
        'compress' => 'Compress',
        'watermark' => 'Watermark',
        'redact' => 'Redaction',
        'ocr' => 'OCR',
        'office_convert' => 'Office conversion',
        'download' => 'Download',
        'export' => 'Export',
        'delete' => 'Delete',
        'expiry' => 'Deleted on expiry',
        'expiry_changed' => 'Retention period changed',
        'signature_created' => 'Signature request created',
        'signature_invited' => 'Signature invitation sent',
        'signature_viewed' => 'Signature request viewed',
        'signature_consented' => 'Consent to sign electronically given',
        'signature_signed' => 'Signed',
        'signature_declined' => 'Signature declined',
        'signature_completed' => 'Signature process completed',
        'signature_cancelled' => 'Signature request cancelled',
    ],
    'status' => [
        'success' => 'Successful',
        'failed' => 'Failed',
        'no_change' => 'No change',
        'processing' => 'Processing',
        'completed' => 'Completed',
    ],
    'actor' => [
        'owner' => 'Document owner',
        'signer' => 'Signer',
        'system' => 'System',
        'cron' => 'Scheduled task',
    ],
];
