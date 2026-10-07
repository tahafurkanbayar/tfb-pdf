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
use App\Pdf\Compression\Compressor;
use App\Pdf\PdfInspector;
use App\Pdf\Ocr\OcrEngine;
use App\Pdf\Office\OfficeConverter;
use App\Pdf\PdfService;
use App\Pdf\Redaction\Redactor;
use App\Services\Operations\OperationArchiveService;
use App\Services\Operations\OperationService;
use App\Services\Operations\PdfToolService;
use App\Services\HashService;
use App\Repositories\DocumentRepository;
use App\Repositories\ExpiryRepository;
use App\Repositories\OperationRepository;
use App\Repositories\VersionRepository;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\ThumbnailService;
use App\Services\ToolCatalog;
use App\Services\Upload\UploadValidator;
use App\Support\DateFormatter;
use App\Tools\Capabilities;
use App\Tools\ProcessRunner;
use App\Tools\ToolDetector;
use App\Services\StorageService;

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
        $view->share('dates', $c->get(DateFormatter::class));
        $view->share('maxUploadSize', $c->get(UploadValidator::class)->effectiveMaxSize());

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

    $c->set(StorageService::class, fn () => new StorageService($storage));
    $c->set(HashService::class, fn () => new HashService());
    $c->set(PdfInspector::class, fn () => new PdfInspector());
    $c->set(UploadValidator::class, fn (Container $c) => new UploadValidator(
        $c->get(PdfInspector::class),
        (int) $config->get('limits.max_upload_size'),
        (int) $config->get('limits.max_pages_per_document')
    ));

    // Repository'ler
    $c->set(DocumentRepository::class, fn (Container $c) => new DocumentRepository($c->get(Database::class)));
    $c->set(VersionRepository::class, fn (Container $c) => new VersionRepository($c->get(Database::class)));
    $c->set(OperationRepository::class, fn (Container $c) => new OperationRepository($c->get(Database::class)));
    $c->set(ExpiryRepository::class, fn (Container $c) => new ExpiryRepository($c->get(Database::class)));

    // Servisler
    $c->set(AuditService::class, fn (Container $c) => new AuditService($c->get(Database::class)));
    // Opsiyonel sunucu araçları (Ghostscript, LibreOffice, Tesseract) ve yetenekler
    $c->set(ProcessRunner::class, fn () => new ProcessRunner());
    $c->set(ToolDetector::class, fn (Container $c) => new ToolDetector(
        $c->get(ProcessRunner::class),
        [
            ToolDetector::GHOSTSCRIPT => (string) $config->get('tools.ghostscript'),
            ToolDetector::LIBREOFFICE => (string) $config->get('tools.libreoffice'),
            ToolDetector::TESSERACT => (string) $config->get('tools.tesseract'),
            ToolDetector::PDFTOPPM => '',
        ],
        $storage . '/cache/tools.json',
        (int) $config->get('tools.detection_cache_ttl', 3600)
    ));
    $c->set(Capabilities::class, fn (Container $c) => new Capabilities($c->get(ToolDetector::class)));
    $c->set(Compressor::class, fn (Container $c) => new Compressor(
        $c->get(ProcessRunner::class),
        $c->get(ToolDetector::class)->path(ToolDetector::GHOSTSCRIPT),
        (int) $config->get('tools.timeout', 120),
        $c->get(Logger::class)
    ));
    $c->set(ToolCatalog::class, fn (Container $c) => new ToolCatalog($c->get(Capabilities::class)->toArray()));
    $c->set(DocumentService::class, fn (Container $c) => new DocumentService(
        $c->get(Database::class),
        $c->get(DocumentRepository::class),
        $c->get(VersionRepository::class),
        $c->get(OperationRepository::class),
        $c->get(ExpiryRepository::class),
        $c->get(StorageService::class),
        $c->get(HashService::class),
        $c->get(AuditService::class),
        $c->get(UploadValidator::class),
        fn (): bool => $c->get(Capabilities::class)->office(),
        (int) $config->get('limits.max_storage_per_owner'),
        (string) $config->get('storage.default_expiry', '7d')
    ));
    $c->set(PdfService::class, fn () => new PdfService((int) $config->get('limits.max_pages_per_document')));
    $c->set(OperationService::class, fn (Container $c) => new OperationService(
        $c->get(Database::class),
        $c->get(DocumentRepository::class),
        $c->get(VersionRepository::class),
        $c->get(OperationRepository::class),
        $c->get(ExpiryRepository::class),
        $c->get(StorageService::class),
        $c->get(HashService::class),
        $c->get(AuditService::class),
        $c->get(DocumentService::class),
        $c->get(Logger::class),
        (string) $config->get('storage.default_expiry', '7d')
    ));
    $c->set(PdfToolService::class, fn (Container $c) => new PdfToolService(
        $c->get(OperationService::class),
        $c->get(DocumentService::class),
        $c->get(PdfService::class),
        $c->get(PdfInspector::class),
        (int) $config->get('limits.max_files_per_operation'),
        $c->get(Compressor::class),
        $c->get(Redactor::class),
        $c->get(OcrEngine::class),
        $c->get(OfficeConverter::class)
    ));
    $c->set(OfficeConverter::class, fn (Container $c) => new OfficeConverter(
        $c->get(ProcessRunner::class),
        $c->get(ToolDetector::class)->path(ToolDetector::LIBREOFFICE),
        (int) $config->get('tools.timeout', 120)
    ));
    $c->set(OcrEngine::class, fn (Container $c) => new OcrEngine(
        $c->get(ProcessRunner::class),
        $c->get(PdfService::class),
        $c->get(ToolDetector::class)->path(ToolDetector::TESSERACT),
        $c->get(ToolDetector::class)->path(ToolDetector::GHOSTSCRIPT),
        $c->get(ToolDetector::class)->path(ToolDetector::PDFTOPPM),
        (string) $config->get('tools.tesseract_languages', 'tur+eng'),
        (int) $config->get('tools.timeout', 120)
    ));
    $c->set(Redactor::class, fn (Container $c) => new Redactor(
        $c->get(ProcessRunner::class),
        $c->get(ToolDetector::class)->path(ToolDetector::GHOSTSCRIPT),
        (int) $config->get('tools.timeout', 120)
    ));
    $c->set(OperationArchiveService::class, fn (Container $c) => new OperationArchiveService(
        $c->get(OperationRepository::class),
        $c->get(DocumentRepository::class),
        $c->get(VersionRepository::class),
        $c->get(StorageService::class),
        $c->get(AuditService::class)
    ));
    $c->set(ThumbnailService::class, fn (Container $c) => new ThumbnailService($c->get(StorageService::class)));
    $c->set(DateFormatter::class, fn () => new DateFormatter((string) $config->get('app.timezone', 'Europe/Istanbul')));

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
