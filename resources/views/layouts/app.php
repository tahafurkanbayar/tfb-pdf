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
 * @var App\Core\Config $config
 */
$title = $view->yield('title');
$pageTitle = $title === '' ? $appName : $title . ' · ' . $appName;
$jsConfig = [
    'locale' => $locale,
    'baseUrl' => $url->to('/'),
    'apiUrl' => $url->to('/api'),
    'csrfToken' => $csrfToken(),
    'icons' => $url->asset('img/icons.svg'),
    // JS'in ihtiyaç duyduğu çeviri grupları; sayfa ek gruplar isteyebilir ($view->section('i18n', 'tools,pdf'))
    'i18n' => array_merge(...array_map(
        static fn (string $group): array => App\I18n\Lang::translator()->group(trim($group)),
        array_filter(['js', 'states', 'errors', 'upload', 'theme', ...explode(',', $view->yield('i18n'))])
    )),
];
$themes = ['light' => 'sun', 'dark' => 'moon-stars', 'auto' => 'circle-half'];
// Etkin menü bölümü: /tr/tools/... → tools, /tr/documents... → documents, /tr/about → about
$section = explode('/', trim(explode('?', $currentPath, 2)[0], '/'))[1] ?? '';
$navCurrent = static fn (string $name): string => $section === $name ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php /* Tema ilk çizimden önce uygulanır (beyaz flaş olmaz); CSP nedeniyle inline değil, engelleyici yerel dosya */ ?>
    <script src="<?= e($url->asset('js/theme-init.js')) ?>"></script>
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f6f7f9" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e1014" media="(prefers-color-scheme: dark)">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($view->yield('description', __('common.app_tagline'))) ?>">
    <meta name="robots" content="<?= e($view->yield('robots', 'index, follow')) ?>">
    <?php foreach (array_keys($locales) as $code): ?>
        <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($url->to(App\Core\Url::swapLocale($currentPath, $code, array_keys($locales)))) ?>">
    <?php endforeach; ?>
    <link rel="icon" href="<?= e($url->asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= e($url->asset('img/brand/favicon-32.png')) ?>" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= e($url->asset('img/brand/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e($url->to('/site.webmanifest')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($appName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($view->yield('description', __('common.app_tagline'))) ?>">
    <meta property="og:url" content="<?= e($url->absolute(explode('?', $currentPath, 2)[0])) ?>">
    <meta property="og:image" content="<?= e($url->absolute('/assets/img/brand/og-image.png')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e(__('common.og_image_alt', ['app' => $appName])) ?>">
    <meta property="og:locale" content="<?= $locale === 'tr' ? 'tr_TR' : 'en_US' ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="<?= e($url->asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e($url->asset('css/app.css')) ?>">
    <script type="application/json" id="tfb-config"><?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body class="d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable skip-link" href="#main"><?= e(__('common.skip_to_content')) ?></a>

<header class="site-header">
    <nav class="navbar navbar-expand-lg" aria-label="<?= e(__('nav.main')) ?>">
        <div class="container">
            <a class="navbar-brand brand" href="<?= e($url->page('/')) ?>">
                <?= $view->partial('partials/logo') ?>
                <span class="visually-hidden"><?= e($appName) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                    aria-controls="main-nav" aria-expanded="false" aria-label="<?= e(__('nav.toggle_menu')) ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="main-nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link"<?= $navCurrent('tools') ?> href="<?= e($url->page('/')) ?>#tools"><?= e(__('nav.tools')) ?></a></li>
                    <li class="nav-item"><a class="nav-link"<?= $navCurrent('documents') ?> href="<?= e($url->page('/documents')) ?>"><?= e(__('nav.documents')) ?></a></li>
                    <li class="nav-item"><a class="nav-link"<?= $navCurrent('about') ?> href="<?= e($url->page('/about')) ?>"><?= e(__('nav.about')) ?></a></li>
                </ul>
                <div class="navbar-actions d-flex align-items-center gap-2 pb-2 pb-lg-0">
                    <div class="dropdown">
                        <button class="btn btn-ghost btn-sm dropdown-toggle d-flex align-items-center gap-1" type="button"
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
                                       href="<?= e($url->to('/language/' . $code, ['return' => $currentPath])) ?>"><?= e($label) ?><?= $view->icon('check2', 'dropdown-check') ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="dropdown" data-theme-switcher>
                        <button class="btn btn-ghost btn-sm dropdown-toggle d-flex align-items-center gap-1" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false" data-theme-toggle
                                aria-label="<?= e(__('theme.switch')) ?>" title="<?= e(__('theme.label')) ?>">
                            <?php foreach ($themes as $value => $themeIcon): ?>
                                <?= $view->icon($themeIcon, 'theme-icon theme-icon-' . $value) ?>
                            <?php endforeach; ?>
                            <span class="d-lg-none"><?= e(__('theme.label')) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($themes as $value => $themeIcon): ?>
                                <li>
                                    <button type="button" class="dropdown-item" data-theme-value="<?= e($value) ?>" aria-pressed="false">
                                        <?= $view->icon($themeIcon) ?><span><?= e(__('theme.' . $value)) ?></span><?= $view->icon('check2', 'dropdown-check') ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main" class="flex-grow-1" tabindex="-1">
    <?= $view->yield('content') ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="brand mb-3" href="<?= e($url->page('/')) ?>">
                    <?= $view->partial('partials/logo') ?>
                    <span class="visually-hidden"><?= e($appName) ?></span>
                </a>
                <p class="mb-3"><?= e(__('common.app_tagline')) ?></p>
                <p class="small d-flex gap-2 mb-0"><?= $view->icon('shield-check', 'flex-shrink-0 mt-1') ?><span><?= e(__('notices.privacy')) ?> <?= e(__('notices.no_tracking')) ?></span></p>
            </div>
            <nav class="col-6 col-lg-3 offset-lg-1" aria-labelledby="footer-product">
                <h2 class="footer-heading" id="footer-product"><?= e(__('footer.product')) ?></h2>
                <ul class="footer-links">
                    <li><a href="<?= e($url->page('/')) ?>#tools"><?= e(__('nav.tools')) ?></a></li>
                    <li><a href="<?= e($url->page('/documents')) ?>"><?= e(__('nav.documents')) ?></a></li>
                    <li><a href="<?= e($url->page('/about')) ?>"><?= e(__('nav.about')) ?></a></li>
                    <li><a href="<?= e($url->page('/privacy')) ?>"><?= e(__('nav.privacy')) ?></a></li>
                </ul>
            </nav>
            <nav class="col-6 col-lg-3" aria-labelledby="footer-project">
                <h2 class="footer-heading" id="footer-project"><?= e(__('footer.project')) ?></h2>
                <ul class="footer-links">
                    <li><a href="<?= e((string) $config->get('app.repository')) ?>" rel="noopener"><?= $view->icon('github') ?> <?= e(__('footer.source')) ?></a></li>
                    <li><a href="<?= e($url->page('/about')) ?>"><?= e(__('notices.disclaimer_title')) ?></a></li>
                    <li><span><?= e(__('footer.license')) ?></span></li>
                </ul>
            </nav>
        </div>
        <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
            <span>© <?= e(gmdate('Y')) ?> <?= e($appName) ?> · <?= e(__('footer.open_source')) ?></span>
            <span><?= e(__('footer.version', ['version' => (string) $config->get('app.version')])) ?></span>
        </div>
    </div>
</footer>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-area" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e($url->asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script type="module" src="<?= e($url->asset('js/app.js')) ?>"></script>
<?= $view->yield('scripts') ?>
</body>
</html>
