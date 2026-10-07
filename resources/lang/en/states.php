<?php

// UI states (spec §35): empty, loading, processing, success, warning, recoverable error, fatal error
return [
    'loading' => 'Loading...',
    'uploading' => 'Uploading file...',
    'processing' => 'Processing PDF...',
    'success' => 'PDF created successfully.',
    'error' => 'An error occurred while processing the file.',
    'recoverable' => 'The operation could not be completed. Check the settings and try again.',
    'fatal' => 'The operation could not be completed and cannot be retried. Please refresh the page.',
    'warning' => 'Warning',
    'empty' => 'Nothing here yet.',
    'please_wait' => 'Please wait, this may take a while for large files.',
];
