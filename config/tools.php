<?php

declare(strict_types=1);

use App\Core\Env;

/*
 * Opsiyonel harici araçlar. Değer:
 *   boş       → PATH üzerinde otomatik aranır
 *   mutlak yol → yalnızca o dosya kullanılır
 *   disabled  → özellik kapalı
 */
return [
    'ghostscript' => Env::string('GHOSTSCRIPT_PATH', ''),
    'libreoffice' => Env::string('LIBREOFFICE_PATH', ''),
    'tesseract' => Env::string('TESSERACT_PATH', ''),
    'tesseract_languages' => Env::string('TESSERACT_LANGS', 'tur+eng'),
    // Saniye. Paylaşımlı hostingde PHP max_execution_time'ı aşmamalı.
    'timeout' => Env::int('EXTERNAL_TOOL_TIMEOUT', 120),
    // Tespit sonuçlarının önbellekte tutulma süresi (saniye)
    'detection_cache_ttl' => Env::int('TOOL_DETECTION_CACHE_TTL', 3600),
];
