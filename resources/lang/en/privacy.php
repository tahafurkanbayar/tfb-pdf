<?php

return [
    'title' => 'Privacy',
    'storage_title' => 'Where are your files stored?',
    'retention_title' => 'How long are they kept?',
    'retention_text' => 'You can choose a retention period for each document (1 day, 7 days, 30 days or until you delete it). Expired documents and all their versions are permanently deleted automatically.',
    'cookies_title' => 'Cookies',
    'cookies_intro' => 'This application only uses cookies that are required for it to work:',
    'cookies' => [
        'session' => 'Session cookie: for form security (CSRF protection). Deleted when you close the browser.',
        'owner' => 'Document ownership cookie: a random identifier so that only you can access your documents. Valid for 1 year.',
        'locale' => 'Language cookie: remembers the language you selected. Valid for 1 year.',
    ],
    'tracking_title' => 'Tracking',
    'export_title' => 'Exporting your data',
    'export_text' => 'You can download your documents, all their versions, the processing history and audit records as a single ZIP file at any time.',
];
