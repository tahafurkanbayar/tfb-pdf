<?php

declare(strict_types=1);

use App\Core\Env;

// SMTP_HOST boşsa e-posta gönderimi kapalıdır; uygulama davet bağlantılarını ekranda gösterir.
return [
    'host' => Env::string('SMTP_HOST', ''),
    'port' => Env::int('SMTP_PORT', 587),
    'username' => Env::string('SMTP_USERNAME', ''),
    'password' => Env::string('SMTP_PASSWORD', ''),
    // tls | ssl | none
    'encryption' => strtolower(Env::string('SMTP_ENCRYPTION', 'tls')),
    'from_address' => Env::string('MAIL_FROM_ADDRESS', ''),
    'from_name' => Env::string('MAIL_FROM_NAME', 'TFB PDF'),
    'timeout' => Env::int('SMTP_TIMEOUT', 15),
];
