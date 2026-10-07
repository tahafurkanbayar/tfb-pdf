<?php

declare(strict_types=1);

use App\Core\Container;
use App\Http\Controllers\Api\DocumentApiController;
use App\Http\Router;

/*
 * JSON API rotaları (/api/...). Arayüzün fetch istekleri ve gelecekteki REST istemcileri için.
 * Durum değiştiren tüm istekler CSRF token gerektirir.
 */
return static function (Router $router, Container $c): void {
    $doc = '/api/documents/{id:[a-f0-9]{32}}';

    $router->get('/api/documents', [DocumentApiController::class, 'index']);
    $router->post('/api/documents', [DocumentApiController::class, 'store']);
    $router->get($doc, [DocumentApiController::class, 'show']);
    $router->delete($doc, [DocumentApiController::class, 'destroy']);
    $router->put($doc . '/expiry', [DocumentApiController::class, 'expiry']);
    $router->get($doc . '/versions/{number:\d{1,6}}/download', [DocumentApiController::class, 'download']);
};
