<?php

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require APP_ROOT . '/vendor/autoload.php';

// Veritabanı bağlantı bilgileri için (.env yoksa varsayılanlar kullanılır)
App\Core\Env::load(APP_ROOT . '/.env');
