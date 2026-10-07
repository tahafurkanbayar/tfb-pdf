<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Session;
use App\Core\Logger;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Services\Install\EnvironmentCheck;
use App\Services\Install\InstallGuard;

/**
 * Web kurulum sayfası (/install): SSH erişimi olmayan cPanel kullanıcıları için ortam kontrolü
 * ve migration çalıştırma. .env içinde INSTALL_KEY boşsa sayfa yoktur (404).
 */
final class InstallController extends Controller
{
    public function show(Request $request): Response
    {
        $guard = $this->guard();
        $session = $this->service(Session::class);

        $data = [
            'authorized' => false,
            'weakKey' => $guard->keyTooShort(),
            'loginError' => $session->pullFlash('install_login'),
            'migrationResult' => $session->pullFlash('install_migrate'),
        ];

        if ($guard->authorized($session)) {
            $check = $this->service(EnvironmentCheck::class);
            $groups = $check->run($request->isSecure((array) $this->config()->get('app.trusted_proxies', [])));
            $data += [
                'groups' => $groups,
                'summary' => EnvironmentCheck::summary($groups),
                'migrations' => $this->migrationList($check, $groups),
                'cronCommand' => 'php ' . str_replace('\\', '/', APP_ROOT) . '/cron/cleanup.php',
            ];
            $data['authorized'] = true;
        }

        $response = $this->view('pages/install', $data);
        // Kurulum sayfası hiçbir önbellekte tutulmaz ve arama motorlarına kapalıdır
        $response->setHeader('Cache-Control', 'no-store');
        $response->setHeader('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    public function login(Request $request): Response
    {
        $guard = $this->guard();
        $session = $this->service(Session::class);
        $key = $request->input('key');

        $result = $guard->attempt(
            $session,
            $request->ip((array) $this->config()->get('app.trusted_proxies', [])),
            is_string($key) ? $key : ''
        );
        if ($result !== 'ok') {
            $session->flash('install_login', $result);
        }

        return Response::redirect($this->url()->to('/install'));
    }

    public function migrate(Request $request): Response
    {
        $guard = $this->guard();
        $session = $this->service(Session::class);
        if (!$guard->authorized($session)) {
            return Response::redirect($this->url()->to('/install'));
        }

        try {
            $ran = $this->service(EnvironmentCheck::class)->migrator()->migrate();
            $session->flash('install_migrate', ['status' => 'ok', 'count' => count($ran)]);
        } catch (DatabaseException|\PDOException $e) {
            // Teknik ayrıntı log'a; yöneticiye hangi adımda kalındığı ve log'a bakması söylenir
            $this->service(Logger::class)->error('Installer migration failed', ['exception' => $e]);
            $session->flash('install_migrate', ['status' => 'failed']);
        }

        return Response::redirect($this->url()->to('/install'));
    }

    public function logout(Request $request): Response
    {
        $this->guard()->logout($this->service(Session::class));

        return Response::redirect($this->url()->to('/install'));
    }

    private function guard(): InstallGuard
    {
        $guard = $this->service(InstallGuard::class);
        if (!$guard->enabled()) {
            throw new NotFoundException('Installer disabled (INSTALL_KEY empty)');
        }

        return $guard;
    }

    /**
     * @param array<string, list<array{id: string, status: string}>> $groups
     * @return list<array{name: string, applied: bool}>|null Veritabanına bağlanılamıyorsa null
     */
    private function migrationList(EnvironmentCheck $check, array $groups): ?array
    {
        $connection = $groups['database'][0] ?? null;
        if ($connection === null || $connection['status'] !== EnvironmentCheck::OK) {
            return null;
        }

        try {
            $migrator = $check->migrator();
            $applied = $migrator->applied();

            return array_map(
                static fn (string $name): array => ['name' => $name, 'applied' => isset($applied[$name])],
                array_keys($migrator->files())
            );
        } catch (DatabaseException|\PDOException) {
            return null;
        }
    }
}
