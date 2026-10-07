<?php

return [
    'level_label' => 'Compression level',
    'levels' => [
        'low' => 'Light (best quality)',
        'medium' => 'Recommended',
        'high' => 'Strong (smallest size)',
    ],
    'level_help' => [
        'low' => 'Image quality stays almost the same; the savings may be limited.',
        'medium' => 'A good balance for reading on screen and sharing.',
        'high' => 'Images are reduced noticeably; print quality may suffer.',
    ],
    'engine_ghostscript' => 'Ghostscript is available on this server, so advanced compression will be used.',
    'engine_php' => 'Ghostscript is not available on this server; PHP-based optimization will be used (JPEG images are downsampled, uncompressed data is compressed). Savings depend on the file.',
    'action' => 'Compress PDF',
    'invalid_level' => 'Invalid compression level.',
    'result_sizes' => ':before → :after (:percent% smaller)',
    'result_no_gain' => 'Previous size: :before. Achievable size: :after.',
];
