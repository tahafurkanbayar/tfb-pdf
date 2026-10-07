<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\SignatureService;

/**
 * İmzalayan sayfası: /{locale}/sign/{token}. Hesap gerekmez; davet bağlantısı yetkilendirir.
 */
final class SignController extends Controller
{
    public function show(Request $request, array $params): Response
    {
        $signatures = $this->service(SignatureService::class);
        $context = $signatures->signerContext($params['token']);
        $proxies = $this->config()->get('app.trusted_proxies');
        $signatures->markViewed($context, $request->ip($proxies), $request->userAgent());

        $response = $this->view('pages/sign', [
            'token' => $params['token'],
            'signer' => $context['signer'],
            'document' => $context['document'],
            'fields' => $context['fields'],
            'request' => $context['request'],
        ]);
        // Davet token'ı URL'de: başka sitelere Referer ile sızmasın, arama motorları indekslemesin
        $response->setHeader('Referrer-Policy', 'no-referrer');
        $response->setHeader('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
