<?php
/**
 * Araç sayfası başlığı ve sunucu desteği uyarısı.
 *
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 */
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e($url->page('/')) ?>#tools"><?= e(__('nav.tools')) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(__('pdf.' . $tool['slug'])) ?></li>
    </ol>
</nav>
<header class="d-flex gap-3 align-items-center mb-4">
    <span class="tool-icon tool-icon-lg tool-icon-<?= e($tool['slug']) ?>" aria-hidden="true"><?= $view->icon($tool['icon']) ?></span>
    <div>
        <h1 class="h3 mb-1"><?= e(__('pdf.' . $tool['slug'])) ?></h1>
        <p class="text-body-secondary mb-0"><?= e(__('tools.descriptions.' . $tool['slug'])) ?></p>
    </div>
</header>
<?php if (!$tool['available']): ?>
    <div class="alert alert-warning d-flex gap-2" role="alert">
        <?= $view->icon('exclamation-triangle', 'flex-shrink-0 mt-1') ?>
        <span><?= e(__('errors.tool_unavailable')) ?></span>
    </div>
<?php endif; ?>
