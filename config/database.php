<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host' => Env::string('DB_HOST', 'localhost'),
    'port' => Env::int('DB_PORT', 3306),
    'socket' => Env::string('DB_SOCKET', ''),
    'database' => Env::string('DB_DATABASE', ''),
    'username' => Env::string('DB_USERNAME', ''),
    'password' => Env::string('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
