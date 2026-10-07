<?php
/**
 * Web kurulum sayfası.
 *
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var Closure(): string $csrfToken
 * @var bool $authorized
 * @var bool $weakKey
 * @var string|null $loginError
 * @var array{status: string, count?: int}|null $migrationResult
 * @var array<string, list<array{id: string, status: string, message: string, params: array<string, string|int>}>> $groups
 * @var array{ok: int, warning: int, error: int} $summary
 * @var list<array{name: string, applied: bool}>|null $migrations
 * @var string $cronCommand
 */
$view->extend('layouts/app');
$view->section('title', __('install.title'));
$view->section('robots', 'noindex, nofollow');

$icons = [
    'ok' => ['check-circle', 'text-success'],
    'warning' => ['exclamation-triangle', 'text-warning-emphasis'],
    'error' => ['x-circle', 'text-danger'],
];
?>
<div class="container py-5 page-narrow">
    <h1 class="h2 mb-2"><?= e(__('install.title')) ?></h1>
    <p class="text-body-secondary mb-4"><?= e(__('install.intro')) ?></p>

    <?php if (!$authorized): ?>
        <section class="card shadow-sm" aria-labelledby="install-login-title">
            <div class="card-body">
                <h2 class="h5" id="install-login-title"><?= e(__('install.login_title')) ?></h2>
                <?php if ($weakKey): ?>
                    <div class="alert alert-danger mb-0" role="alert"><?= e(__('install.login_weak', ['min' => App\Services\Install\InstallGuard::MIN_KEY_LENGTH])) ?></div>
                <?php else: ?>
                    <p><?= e(__('install.login_intro')) ?></p>
                    <?php if ($loginError === 'invalid'): ?>
                        <div class="alert alert-danger" role="alert"><?= e(__('install.login_invalid')) ?></div>
                    <?php elseif ($loginError === 'locked'): ?>
                        <div class="alert alert-danger" role="alert"><?= e(__('install.login_locked', ['minutes' => intdiv(App\Services\Install\InstallGuard::LOCK_SECONDS, 60)])) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e($url->to('/install/login')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
                        <input type="hidden" name="_token" value="<?= e($csrfToken()) ?>">
                        <div class="flex-grow-1">
                            <label class="form-label" for="install-key"><?= e(__('install.key_label')) ?></label>
                            <input class="form-control" type="password" id="install-key" name="key" required autocomplete="off" autofocus>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= e(__('install.login_button')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <?php if ($migrationResult !== null): ?>
            <?php if ($migrationResult['status'] === 'ok'): ?>
                <div class="alert alert-success" role="status">
                    <?= e(($migrationResult['count'] ?? 0) > 0 ? __('install.migrate_ok', ['count' => $migrationResult['count']]) : __('install.migrate_none')) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger" role="alert"><?= e(__('install.migrate_failed')) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php $done = $summary['error'] === 0 && $migrations !== null && !in_array(false, array_column($migrations, 'applied'), true); ?>
        <div class="alert <?= $summary['error'] > 0 ? 'alert-danger' : ($summary['warning'] > 0 ? 'alert-warning' : 'alert-success') ?> d-flex gap-2" role="status">
            <?= $view->icon($summary['error'] > 0 ? 'x-circle' : ($summary['warning'] > 0 ? 'exclamation-triangle' : 'check-circle'), 'flex-shrink-0 mt-1') ?>
            <span data-install-summary><?= e($summary['error'] + $summary['warning'] === 0 ? __('install.summary_ok') : __('install.summary', ['errors' => $summary['error'], 'warnings' => $summary['warning']])) ?></span>
        </div>

        <?php foreach ($groups as $group => $items): ?>
            <section class="mb-4" aria-labelledby="install-group-<?= e($group) ?>">
                <h2 class="h5" id="install-group-<?= e($group) ?>"><?= e(__('install.groups.' . $group)) ?></h2>
                <ul class="list-group">
                    <?php foreach ($items as $item): ?>
                        <?php [$icon, $class] = $icons[$item['status']]; ?>
                        <li class="list-group-item d-flex gap-3" data-check="<?= e($item['id']) ?>" data-status="<?= e($item['status']) ?>">
                            <span class="<?= e($class) ?> flex-shrink-0 mt-1"><?= $view->icon($icon) ?><span class="visually-hidden"><?= e(__('install.status.' . $item['status'])) ?></span></span>
                            <div class="min-w-0">
                                <div class="fw-semibold"><?= e(__('install.check.' . $item['id'])) ?></div>
                                <div class="small text-body-secondary text-break"><?= e(__($item['message'], array_diff_key($item['params'], ['suggestion' => true]))) ?></div>
                                <?php if (isset($item['params']['suggestion'])): ?>
                                    <code class="d-block small mt-1 user-select-all text-break">APP_KEY=<?= e((string) $item['params']['suggestion']) ?></code>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($group === 'database'): ?>
                    <h3 class="h6 mt-3"><?= e(__('install.migrations_title')) ?></h3>
                    <?php if ($migrations === null): ?>
                        <p class="small text-body-secondary"><?= e(__('install.migrations_unavailable')) ?></p>
                    <?php else: ?>
                        <ul class="list-unstyled small font-monospace mb-3">
                            <?php foreach ($migrations as $migration): ?>
                                <li><?= $migration['applied'] ? '[x]' : '[ ]' ?> <?= e($migration['name']) ?>
                                    <span class="text-body-secondary">— <?= e(__($migration['applied'] ? 'install.migration_applied' : 'install.migration_pending')) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (in_array(false, array_column($migrations, 'applied'), true)): ?>
                            <form method="post" action="<?= e($url->to('/install/migrate')) ?>">
                                <input type="hidden" name="_token" value="<?= e($csrfToken()) ?>">
                                <button class="btn btn-primary" type="submit"><?= e(__('install.run_migrations')) ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="small text-body-secondary mt-2 mb-0"><?= e(__('install.phpmyadmin_alt')) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <section class="mb-4" aria-labelledby="install-cron">
            <h2 class="h5" id="install-cron"><?= e(__('install.cron_title')) ?></h2>
            <p class="mb-2"><?= e(__('install.cron_intro')) ?></p>
            <code class="d-block p-2 bg-body-tertiary rounded user-select-all text-break mb-2">0 * * * * <?= e($cronCommand) ?></code>
            <p class="small text-body-secondary mb-0"><?= e(__('install.cron_note')) ?></p>
        </section>

        <?php if ($done): ?>
            <section class="alert alert-success" aria-labelledby="install-done">
                <h2 class="h5" id="install-done"><?= e(__('install.done_title')) ?></h2>
                <p class="mb-2"><?= e(__('install.done_intro')) ?></p>
                <ol class="mb-3">
                    <?php foreach (['key', 'https', 'open'] as $step): ?>
                        <li><?= e(__('install.done_steps.' . $step)) ?></li>
                    <?php endforeach; ?>
                </ol>
                <a class="btn btn-success" href="<?= e($url->page('/')) ?>"><?= e(__('install.open_site')) ?></a>
            </section>
        <?php endif; ?>

        <form method="post" action="<?= e($url->to('/install/logout')) ?>">
            <input type="hidden" name="_token" value="<?= e($csrfToken()) ?>">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><?= e(__('install.logout')) ?></button>
        </form>
    <?php endif; ?>
</div>
