<?php

declare(strict_types=1);

use App\Core\Container;
use App\Http\Controllers\Api\DocumentApiController;
use App\Http\Controllers\Api\OperationApiController;
use App\Http\Controllers\Api\PreviewApiController;
use App\Http\Controllers\Api\SignatureApiController;
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
    $router->get($doc . '/verify', [DocumentApiController::class, 'verify']);
    $router->get($doc . '/export', [DocumentApiController::class, 'export']);
    $router->get('/api/export', [DocumentApiController::class, 'exportAll']);
    $router->get($doc . '/versions/{number:\d{1,6}}/download', [DocumentApiController::class, 'download']);

    $router->post('/api/operations/merge', [OperationApiController::class, 'merge']);
    $router->post('/api/operations/split', [OperationApiController::class, 'split']);
    $router->post('/api/operations/reorder', [OperationApiController::class, 'reorder']);
    $router->post('/api/operations/rotate', [OperationApiController::class, 'rotate']);
    $router->post('/api/operations/compress', [OperationApiController::class, 'compress']);
    $router->post('/api/operations/watermark', [OperationApiController::class, 'watermark']);
    $router->post('/api/operations/redact', [OperationApiController::class, 'redact']);
    $router->post('/api/operations/ocr', [OperationApiController::class, 'ocr']);
    $router->post('/api/operations/office', [OperationApiController::class, 'officeConvert']);
    $router->get('/api/operations/{id:[a-f0-9]{32}}/download', [OperationApiController::class, 'download']);

    // İmza talepleri (sahip)
    $sig = '/api/signatures/{id:[a-f0-9]{32}}';
    $router->post('/api/signatures', [SignatureApiController::class, 'store']);
    $router->post($sig . '/cancel', [SignatureApiController::class, 'cancel']);
    $router->post($sig . '/signers/{signer:\d{1,10}}/link', [SignatureApiController::class, 'regenerate']);
    $router->post($sig . '/finalize', [SignatureApiController::class, 'finalize']);

    // İmzalayan (davet token'ı)
    $sign = '/api/sign/{token:[a-f0-9]{64}}';
    $router->post($sign, [SignatureApiController::class, 'sign']);
    $router->post($sign . '/decline', [SignatureApiController::class, 'decline']);
    $router->get($sign . '/document', [SignatureApiController::class, 'document']);
    $router->get($sign . '/final', [SignatureApiController::class, 'final']);

    $previews = $doc . '/versions/{number:\d{1,6}}/previews';
    $router->get($previews, [PreviewApiController::class, 'index']);
    $router->get($previews . '/{page:\d{1,5}}', [PreviewApiController::class, 'show']);
    $router->put($previews . '/{page:\d{1,5}}', [PreviewApiController::class, 'store']);
};
