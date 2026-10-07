<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\SignController;
use App\Http\Controllers\ToolController;
use App\Http\Router;

/*
 * Sayfa rotaları. Herkese açık sayfalar dil önekli: /tr/..., /en/...
 */
return static function (Router $router, Container $c): void {
    $locales = implode('|', array_keys($c->get(Config::class)->get('i18n.locales')));
    $l = '/{locale:' . $locales . '}';

    $router->get('/', [HomeController::class, 'root']);
    $router->get('/language/{target:' . $locales . '}', [LanguageController::class, 'switch']);

    $router->get($l, [HomeController::class, 'index']);
    $router->get($l . '/about', [HomeController::class, 'about']);
    $router->get($l . '/privacy', [HomeController::class, 'privacy']);

    $router->get($l . '/tools/{tool:[a-z]+}', [ToolController::class, 'show']);

    $router->get($l . '/sign/{token:[a-f0-9]{64}}', [SignController::class, 'show']);

    $router->get($l . '/documents', [DocumentController::class, 'index']);
    $router->get($l . '/documents/{id:[a-f0-9]{32}}', [DocumentController::class, 'show']);

    // Web kurulum (dil öneksiz; INSTALL_KEY boşsa 404)
    $router->get('/install', [InstallController::class, 'show']);
    $router->post('/install/login', [InstallController::class, 'login']);
    $router->post('/install/migrate', [InstallController::class, 'migrate']);
    $router->post('/install/logout', [InstallController::class, 'logout']);
};
