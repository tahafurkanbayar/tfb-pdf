<?php

declare(strict_types=1);

/*
 * Servis tanımları. Bağımlılıklar burada açıkça görünür; otomatik çözümleme yoktur.
 */

use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\ResolveLocale;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Request;
use App\Http\Router;
use App\I18n\Lang;
use App\I18n\LocaleNegotiator;
use App\I18n\Translator;
use App\Security\Hmac;
use App\Security\OwnerContext;

return static function (Container $c, Config $config): void {
    $storage = $config->get('storage.path');

    $c->set(Logger::class, fn () => new Logger($storage . '/logs', (string) $config->get('app.log_level', 'info')));

    $c->set(ErrorHandler::class, function (Container $c) use ($config): ErrorHandler {
        $handler = new ErrorHandler(
            $c->get(Logger::class),
            $config->get('app.debug') === true && $config->get('app.env') === 'local'
        );
        $handler->setPageRenderer(fn (int $status, string $message, string $requestId): string => $c->get(View::class)->render(
            'errors/error',
            ['status' => $status, 'message' => $message, 'requestId' => $requestId]
        ));

        return $handler;
    });

    $c->set(Translator::class, function () use ($config): Translator {
        $translator = new Translator(APP_ROOT . '/resources/lang', (string) $config->get('i18n.default'), 'tr');
        Lang::set($translator);

        return $translator;
    });
    // __() ilk çağrıda doğru Translator'ı kullansın
    Lang::set($c->get(Translator::class));

    $c->set(LocaleNegotiator::class, fn () => new LocaleNegotiator(
        array_keys($config->get('i18n.locales')),
        (string) $config->get('i18n.default')
    ));

    $c->set(Url::class, function () use ($config): Url {
        $base = (string) $config->get('app.url');
        if ($base === '') {
            // APP_URL boşsa istekten türet: http(s)://host/alt-dizin
            $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            $host = preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', (string) ($_SERVER['HTTP_HOST'] ?? '')) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
            $dir = preg_replace('#/public$#', '', rtrim($dir, '/')) ?? '';
            $base = ($https ? 'https' : 'http') . '://' . $host . $dir;
        }

        return new Url(rtrim($base, '/'), (string) $config->get('i18n.default'));
    });

    $c->set(View::class, function (Container $c) use ($config): View {
        $view = new View(APP_ROOT . '/resources/views');
        $view->share('url', $c->get(Url::class));
        $view->share('appName', (string) $config->get('app.name'));
        $view->share('locales', $config->get('i18n.locales'));
        $view->share('config', $config);
        $view->share('csrfToken', fn (): string => $c->get(Csrf::class)->token());

        return $view;
    });

    $c->set(Session::class, function (Container $c) use ($config, $storage): Session {
        $request = $c->has(Request::class) ? $c->get(Request::class) : null;
        $path = $c->get(Url::class)->basePath();

        return new Session(
            $storage . '/sessions',
            $request?->isSecure($config->get('app.trusted_proxies')) ?? false,
            $path === '' ? '/' : $path . '/'
        );
    });

    $c->set(Csrf::class, fn (Container $c) => new Csrf($c->get(Session::class)));

    $c->set(Hmac::class, fn () => new Hmac((string) $config->get('app.key')));

    $c->set(OwnerContext::class, function (Container $c) use ($config): OwnerContext {
        $request = $c->get(Request::class);
        $path = $c->get(Url::class)->basePath();

        return new OwnerContext(
            $c->get(Hmac::class),
            $request->cookie(OwnerContext::COOKIE),
            $request->isSecure($config->get('app.trusted_proxies')),
            $path === '' ? '/' : $path . '/'
        );
    });

    $c->set(Database::class, fn () => new Database($config->get('database')));

    $c->set(Router::class, function () use ($c): Router {
        $router = new Router();
        (require APP_ROOT . '/routes/web.php')($router, $c);
        (require APP_ROOT . '/routes/api.php')($router, $c);

        return $router;
    });

    $c->set(ForceHttps::class, fn () => new ForceHttps(
        (bool) $config->get('app.force_https'),
        (bool) $config->get('app.hsts'),
        $config->get('app.trusted_proxies')
    ));
    $c->set(ResolveLocale::class, fn (Container $c) => new ResolveLocale(
        $c->get(LocaleNegotiator::class),
        $c->get(Translator::class),
        $c->get(Url::class),
        $c->get(View::class),
        (string) $config->get('i18n.cookie')
    ));
    $c->set(VerifyCsrfToken::class, fn (Container $c) => new VerifyCsrfToken($c->get(Csrf::class)));

    // İstek gönderildikten sonra çalışacak görevler (ör. fırsatçı temizlik)
    $c->set('after_response', fn () => []);
};
