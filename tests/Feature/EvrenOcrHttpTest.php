<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\Csrf;
use App\Http\Middleware\Middleware;
use App\Http\Middleware\ThrottleRequests;
use App\Http\Request;
use App\Http\Response;
use App\Services\Evren\EvrenHttpClient;
use App\Services\Evren\EvrenOcrClient;
use App\Tools\ProcessRunner;
use App\Tools\ToolDetector;
use Tests\Support\AppTestCase;

/** UI and consent checks do not need a database or a real API key. */
final class EvrenOcrHttpTest extends AppTestCase
{
    protected function createApp(): Application
    {
        $app = parent::createApp();
        $c = $app->container();
        $c->set(ToolDetector::class, fn () => new ToolDetector(new ProcessRunner(), [
            ToolDetector::GHOSTSCRIPT => 'disabled', ToolDetector::LIBREOFFICE => 'disabled',
            ToolDetector::TESSERACT => 'disabled', ToolDetector::PDFTOPPM => 'disabled',
        ], $this->storageRoot() . '/cache/tools.json'));
        $c->set(EvrenOcrClient::class, fn () => new EvrenOcrClient(new EvrenHttpClient(), true, 'ui-test-secret', 'dots-ocr', 'text'));
        // Test only the consent guard, without consuming a database rate-limit bucket.
        $c->instance(ThrottleRequests::class, new class implements Middleware {
            public function process(Request $request, \Closure $next): Response { return $next($request); }
        });
        return $app;
    }

    public function testEvrenEnablesOcrWithoutServerProgramsAndKeyNeverReachesPage(): void
    {
        foreach (['tr', 'en'] as $locale) {
            $response = $this->request('GET', '/' . $locale . '/tools/ocr');
            self::assertSame(200, $response->status());
            self::assertStringContainsString('data-evren="1"', $response->content());
            self::assertStringContainsString('data-evren-consent', $response->content());
            self::assertStringContainsString('js/tools/ocr.js', $response->content());
            self::assertStringNotContainsString('ui-test-secret', $response->content());
        }
        $office = $this->request('GET', '/tr/tools/office');
        self::assertStringNotContainsString('data-dropzone', $office->content(), 'OCR access must not claim Office conversion support');
    }

    public function testTransferConsentIsEnforcedOnServer(): void
    {
        foreach ([[], ['evren_consent' => 'false'], ['evren_consent' => 1]] as $post) {
            $app = $this->createApp();
            $response = $this->request('POST', '/api/operations/ocr', [
                'Accept' => 'application/json', 'X-Locale' => 'en',
                'X-CSRF-Token' => $app->container()->get(Csrf::class)->token(),
            ], ['tfb_owner' => str_repeat('a', 64)], $post, $app);
            self::assertSame(422, $response->status());
            $body = json_decode($response->content(), true);
            self::assertFalse($body['ok']);
            self::assertStringContainsString('consent', $body['error']['message']);
        }
    }
}
