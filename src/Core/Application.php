<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\Middleware;
use App\Http\Middleware\ResolveLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Security\OwnerContext;

final class Application
{
    /** İstekten türetilen ve her istekte yeniden oluşturulması gereken servisler */
    private const REQUEST_SCOPED = [OwnerContext::class];

    public function __construct(private readonly Container $container)
    {
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Gerçek HTTP isteği: globals → Request → Response → gönder → istek sonrası işler.
     */
    public function runHttp(): void
    {
        /** @var Url $url */
        $url = $this->container->get(Url::class);
        $request = Request::fromGlobals($url->basePath());

        $response = $this->handle($request);
        $response->send();

        $this->afterResponse();
    }

    public function handle(Request $request): Response
    {
        $this->container->instance(Request::class, $request);
        // İstekten türetilen kimlik bir sonraki isteğe taşınmasın (aynı süreçte birden çok istek: testler, ileride worker)
        foreach (self::REQUEST_SCOPED as $id) {
            $this->container->reset($id);
        }

        try {
            $this->container->get(Session::class)->start();

            $pipeline = array_reduce(
                array_reverse($this->middleware()),
                static fn (\Closure $next, Middleware $mw): \Closure => static fn (Request $r): Response => $mw->process($r, $next),
                fn (Request $r): Response => $this->dispatch($r)
            );

            $response = $pipeline($request);
        } catch (\Throwable $e) {
            $response = $this->container->get(ErrorHandler::class)->toResponse($e, $request);
        }

        return $this->finalize($request, $response);
    }

    /**
     * @return list<Middleware>
     */
    private function middleware(): array
    {
        return [
            $this->container->get(ForceHttps::class),
            $this->container->get(ResolveLocale::class),
            $this->container->get(VerifyCsrfToken::class),
        ];
    }

    private function dispatch(Request $request): Response
    {
        /** @var Router $router */
        $router = $this->container->get(Router::class);
        $match = $router->match($request->method, $request->path);

        foreach ($match['params'] as $key => $value) {
            $request->withAttribute($key, $value);
        }

        $handler = $match['handler'];
        if (is_array($handler)) {
            [$class, $method] = $handler;
            /** @var Controller $controller */
            $controller = new $class($this->container);

            return $controller->{$method}($request, $match['params']);
        }

        return $handler($request, $match['params']);
    }

    private function finalize(Request $request, Response $response): Response
    {
        SecurityHeaders::apply($response);

        if ($this->container->has(OwnerContext::class)) {
            // HTML sayfa ziyaretlerinde owner cookie süresi uzatılır
            $isPage = $request->isMethod('GET') && !$request->isApi()
                && str_starts_with((string) $response->header('Content-Type'), 'text/html');
            $this->container->get(OwnerContext::class)->applyTo($response, $isPage);
        }

        if ($request->isMethod('HEAD')) {
            return new Response('', $response->status(), $response->headers());
        }

        return $response;
    }

    private function afterResponse(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        foreach ($this->container->get('after_response') as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                $this->container->get(Logger::class)->error('After-response task failed', ['exception' => $e]);
            }
        }
    }
}
