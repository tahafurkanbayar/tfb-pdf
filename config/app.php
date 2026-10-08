<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::string('APP_NAME', 'TFB PDF'),
    // Uygulama sürümü (footer'da gösterilir). Yayınlanan her sürümde güncellenir.
    'version' => '1.0.0',
    // Kaynak kodu adresi (footer'daki GitHub bağlantısı)
    'repository' => 'https://github.com/tahafurkanbayar/tfb-pdf',
    // production | local | testing
    'env' => Env::string('APP_ENV', 'production'),
    // Yalnızca yerel geliştirmede true olmalı. Production'da teknik hata ayrıntıları asla gösterilmez.
    'debug' => Env::bool('APP_DEBUG', false),
    // Boşsa istekten türetilir. Sonunda / olmadan: https://pdf.example.com veya http://localhost/tfb-pdf
    'url' => rtrim(Env::string('APP_URL', ''), '/'),
    // HMAC anahtarı (owner token, IP hash, imza token'ları). En az 32 karakter, rastgele.
    'key' => Env::string('APP_KEY', ''),
    'timezone' => Env::string('APP_TIMEZONE', 'Europe/Istanbul'),
    'force_https' => Env::bool('FORCE_HTTPS', false),
    'hsts' => Env::bool('HSTS_ENABLED', false),
    // Virgülle ayrılmış reverse proxy IP'leri (X-Forwarded-* başlıklarına yalnızca bunlardan güvenilir)
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', Env::string('TRUSTED_PROXIES', ''))))),
    // Kurulum / migration web sayfası anahtarı. Boşsa web kurulum sayfası tamamen kapalıdır.
    'install_key' => Env::string('INSTALL_KEY', ''),
    'log_level' => Env::string('LOG_LEVEL', 'info'),
];
