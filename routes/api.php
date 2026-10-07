<?php

declare(strict_types=1);

use App\Core\Container;
use App\Http\Router;

/*
 * JSON API rotaları (/api/...). Arayüzün fetch istekleri ve gelecekteki REST istemcileri için.
 * Durum değiştiren tüm istekler CSRF token gerektirir.
 */
return static function (Router $router, Container $c): void {
};
