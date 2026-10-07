<?php

return [
    'title' => 'Integrity check',
    'intro' => 'Verify that the files have not changed using their stored SHA-256 digests, or find out which version a file on your computer belongs to.',
    'verify_server' => 'Verify files on the server',
    'verifying' => 'Verifying...',
    'verify_ok' => 'All versions match their stored digests; the files have not changed since they were saved.',
    'verify_failed' => 'Some versions do not match their stored digests or are missing. Please inform the site administrator.',
    'status' => [
        'ok' => 'Matches',
        'mismatch' => 'Does not match',
        'missing' => 'File not found',
    ],
    'compare_local' => 'Compare a file from your computer',
    'compare_help' => 'The file is not uploaded; the digest is calculated in your browser.',
    'compare_match' => 'This file is identical to :version.',
    'compare_no_match' => 'This file does not match any version of this document (SHA-256: :hash).',
    'compare_unsupported' => 'Your browser can only perform this check over a secure (HTTPS) connection.',
    'computing' => 'Calculating digest...',
];
