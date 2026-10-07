<?php

declare(strict_types=1);

use App\Core\Env;
use App\Support\Size;

return [
    // Tek dosya için üst sınır. Etkin sınır ayrıca php.ini upload_max_filesize / post_max_size ile sınırlanır.
    'max_upload_size' => Size::parse(Env::string('MAX_UPLOAD_SIZE', '25M')),
    'max_files_per_operation' => Env::int('MAX_FILES_PER_OPERATION', 20),
    'max_pages_per_document' => Env::int('MAX_PAGES_PER_DOCUMENT', 500),
    // Tarayıcı (owner) başına toplam depolama. 0 = sınırsız.
    'max_storage_per_owner' => Size::parse(Env::string('MAX_STORAGE_PER_OWNER', '500M')),
    // Saatlik istek sınırları (IP başına). 0 = kapalı.
    'rate_uploads_per_hour' => Env::int('RATE_LIMIT_UPLOADS_PER_HOUR', 60),
    'rate_operations_per_hour' => Env::int('RATE_LIMIT_OPERATIONS_PER_HOUR', 120),
    'rate_signatures_per_hour' => Env::int('RATE_LIMIT_SIGNATURES_PER_HOUR', 30),
];
