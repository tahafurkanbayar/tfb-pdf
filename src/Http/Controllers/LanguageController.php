<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Url;
use App\Http\Request;
use App\Http\Response;

final class LanguageController extends Controller
{
    /**
     * Açık dil tercihini cookie'ye kaydeder ve kullanıcıyı aynı sayfanın yeni dildeki
     * karşılığına yönlendirir (spec §6). Yalnızca bir tercih cookie'si ayarlar; hassas
     * bir durum değişikliği olmadığı için GET ile yapılır.
     */
    public function switch(Request $request, array $params): Response
    {
        $target = $params['target'];
        $locales = array_keys($this->config()->get('i18n.locales'));

        $return = (string) $request->query('return', '/');
        if (!Url::isSafeInternalPath($return)) {
            $return = '/';
        }

        // return: taban yol olmadan uygulama içi yol ("/tr/tools/merge?x=1")
        $path = (string) parse_url($return, PHP_URL_PATH);
        $query = (string) parse_url($return, PHP_URL_QUERY);
        $destination = $this->url()->to(Url::swapLocale($path, $target, $locales)) . ($query !== '' ? '?' . $query : '');

        $basePath = $this->url()->basePath();

        return Response::redirect($destination)->withCookie((string) $this->config()->get('i18n.cookie'), $target, [
            'expires' => time() + 365 * 86400,
            'path' => $basePath === '' ? '/' : $basePath . '/',
            'secure' => $request->isSecure($this->config()->get('app.trusted_proxies')),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
