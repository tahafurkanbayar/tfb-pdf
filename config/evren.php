<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'enabled' => Env::bool('EVREN_OCR_ENABLED', false),
    'api_key' => Env::string('EVREN_API_KEY', ''),
    'model' => Env::string('EVREN_OCR_MODEL', 'dots-ocr'),
    // Set from the provider's successful response example; do not guess the schema.
    'text_path' => Env::string('EVREN_OCR_TEXT_PATH', ''),
    'timeout' => max(5, min(120, Env::int('EVREN_TIMEOUT', 60))),
    'max_pages' => max(1, min(10, Env::int('EVREN_OCR_MAX_PAGES', 5))),
];
