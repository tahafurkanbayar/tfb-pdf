<?php
/**
 * Ana layout.
 *
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var string $locale
 * @var array<string, string> $locales
 * @var string $appName
 * @var string $currentPath
 * @var Closure(): string $csrfToken
 */
$title = $view->yield('title');
$pageTitle = $title === '' ? $appName : $title . ' · ' . $appName;
$jsConfig = [
    'locale' => $locale,
    'baseUrl' => $url->to('/'),
    'apiUrl' => $url->to('/api'),
    'csrfToken' => $csrfToken(),
    // JS'in ihtiyaç duyduğu çeviri grupları; sayfa ek gruplar isteyebilir ($view->section('i18n', 'tools,pdf'))
    'i18n' => array_merge(...array_map(
        static fn (string $group): array => App\I18n\Lang::translator()->group(trim($group)),
        array_filter(['js', 'states', 'errors', 'upload', ...explode(',', $view->yield('i18n'))])
    )),
];
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($view->yield('description', __('common.app_tagline'))) ?>">
    <meta name="robots" content="<?= e($view->yield('robots', 'index, follow')) ?>">
    <?php foreach (array_keys($locales) as $code): ?>
        <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($url->to(App\Core\Url::swapLocale($currentPath, $code, array_keys($locales)))) ?>">
    <?php endforeach; ?>
    <link rel="icon" href="<?= e($url->asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($url->asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e($url->asset('css/app.css')) ?>">
    <script type="application/json" id="tfb-config"><?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body class="d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable skip-link" href="#main"><?= e(__('common.skip_to_content')) ?></a>

<header class="site-header border-bottom bg-body">
    <nav class="navbar navbar-expand-lg container" aria-label="<?= e(__('nav.main')) ?>">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="<?= e($url->page('/')) ?>">
            <span class="brand-mark" aria-hidden="true"><?= $view->icon('file-earmark-pdf') ?></span>
            <span><?= e($appName) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                aria-controls="main-nav" aria-expanded="false" aria-label="<?= e(__('nav.toggle_menu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="main-nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= e($url->page('/')) ?>#tools"><?= e(__('nav.tools')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($url->page('/documents')) ?>"><?= e(__('nav.documents')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($url->page('/about')) ?>"><?= e(__('nav.about')) ?></a></li>
            </ul>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-1" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('language.switch')) ?>">
                    <?= $view->icon('translate') ?>
                    <span><?= e($locales[$locale]) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($locales as $code => $label): ?>
                        <li>
                            <a class="dropdown-item<?= $code === $locale ? ' active' : '' ?>" lang="<?= e($code) ?>"
                               hreflang="<?= e($code) ?>"
                               <?= $code === $locale ? 'aria-current="true"' : '' ?>
                               href="<?= e($url->to('/language/' . $code, ['return' => $currentPath])) ?>"><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main id="main" class="flex-grow-1" tabindex="-1">
    <?= $view->yield('content') ?>
</main>

<footer class="site-footer border-top mt-5 py-4 bg-body-tertiary">
    <div class="container small text-body-secondary">
        <p class="mb-2 d-flex gap-2"><?= $view->icon('shield-check', 'flex-shrink-0 mt-1') ?><span><?= e(__('notices.privacy')) ?> <?= e(__('notices.no_tracking')) ?></span></p>
        <p class="mb-0">
            <a href="<?= e($url->page('/about')) ?>"><?= e(__('notices.disclaimer_title')) ?></a>
            · <a href="<?= e($url->page('/privacy')) ?>"><?= e(__('nav.privacy')) ?></a>
        </p>
    </div>
</footer>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-area" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e($url->asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script type="module" src="<?= e($url->asset('js/app.js')) ?>"></script>
<?= $view->yield('scripts') ?>
</body>
</html>
