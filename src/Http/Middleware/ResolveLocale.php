<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Url;
use App\Core\View;
use App\I18n\LocaleNegotiator;
use App\I18n\Translator;
use App\Http\Request;
use App\Http\Response;

/**
 * İsteğin dilini belirler ve Translator / Url / View'a aktarır.
 * Sayfalarda URL öneki (/tr, /en) belirleyicidir; API isteklerinde X-Locale başlığı
 * (arayüzün o anki dili), sonra cookie ve tarayıcı dili kullanılır.
 */
final class ResolveLocale implements Middleware
{
    public function __construct(
        private readonly LocaleNegotiator $negotiator,
        private readonly Translator $translator,
        private readonly Url $url,
        private readonly View $view,
        private readonly string $cookieName,
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        $first = explode('/', ltrim($request->path, '/'))[0];
        $urlLocale = $this->negotiator->isSupported($first) ? $first : null;
        $headerLocale = $request->header('x-locale');

        $locale = $this->negotiator->negotiate(
            $urlLocale ?? ($this->negotiator->isSupported($headerLocale) ? $headerLocale : null),
            $request->cookie($this->cookieName),
            $request->header('accept-language')
        );

        $this->translator->setLocale($locale);
        $this->url->setLocale($locale);
        $this->view->share('locale', $locale);
        // Dil değiştirici: aynı sayfaya geri dönebilmek için (taban yol olmadan)
        $query = $request->query === [] ? '' : '?' . http_build_query($request->query);
        $this->view->share('currentPath', $request->path . $query);
        $request->withAttribute('locale', $locale);

        $response = $next($request);
        $response->setHeader('Content-Language', $locale);
        if (!$response->hasHeader('Vary')) {
            $response->setHeader('Vary', 'Accept-Language, Cookie');
        }

        return $response;
    }
}
