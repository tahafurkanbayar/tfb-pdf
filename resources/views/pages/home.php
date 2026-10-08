<?php
/**
 * Ana sayfa: hero + yükleme, araçlar, dashboard kartları (son belgeler, depolama, yakında silinecekler, son işlemler).
 *
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var App\Support\DateFormatter $dates
 * @var string $locale
 * @var list<array<string, mixed>> $tools
 * @var list<array<string, mixed>> $recentDocuments
 * @var list<array<string, mixed>> $recentOperations
 * @var list<array<string, mixed>> $expiringSoon
 * @var int $storageUsed
 * @var int $storageLimit
 */
$view->extend('layouts/app');
$view->section('description', __('home.meta_description'));
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/pages/home.js')) . '"></script>');
$maxSize = $view->shared('maxUploadSize');
$storagePercent = $storageLimit > 0 ? (int) round(min($storageUsed, $storageLimit) / $storageLimit * 100) : null;

/** Boş durum: ikon, kısa başlık ve açıklama */
$emptyState = static fn (string $icon, string $title, string $text): string => sprintf(
    '<div class="empty-state empty-state-sm"><span class="empty-state-icon" aria-hidden="true">%s</span><p class="empty-state-title">%s</p><p class="empty-state-text">%s</p></div>',
    $view->icon($icon),
    e($title),
    e($text)
);
?>
<section class="hero" aria-labelledby="hero-title">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="eyebrow"><?= e(__('home.eyebrow')) ?></span>
                <h1 id="hero-title" class="hero-title mb-3"><?= e(__('home.title')) ?></h1>
                <p class="hero-lead mb-4"><?= e(__('home.subtitle')) ?></p>
                <p class="mb-4">
                    <span class="trust-badge">
                        <span class="icon-chip" aria-hidden="true"><?= $view->icon('server') ?></span>
                        <?= e(__('home.trust_badge')) ?>
                    </span>
                </p>
                <ul class="hero-points mb-3">
                    <?php foreach (['no_account', 'no_tracking', 'versions'] as $point): ?>
                        <li><?= $view->icon('check2') ?><?= e(__('home.points.' . $point)) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="small text-body-secondary d-flex gap-2 mb-0">
                    <?= $view->icon('shield-check', 'flex-shrink-0 mt-1') ?><span><?= e(__('notices.privacy')) ?></span>
                </p>
            </div>
            <div class="col-lg-6">
                <div class="card upload-card">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h5 mb-1"><?= e(__('dashboard.upload_heading')) ?></h2>
                        <p class="small text-body-secondary mb-3"><?= e(__('dashboard.upload_hint')) ?></p>
                        <?= $view->partial('partials/dropzone', [
                            'accept' => '.pdf,application/pdf',
                            'types' => 'PDF',
                            'maxSize' => $maxSize,
                            'inputId' => 'home-upload',
                            'large' => true,
                            'preview' => true,
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="tools" class="container py-4 py-lg-5" aria-labelledby="tools-heading">
    <div class="section-heading">
        <div>
            <h2 id="tools-heading" class="h3 mb-0"><?= e(__('tools.heading')) ?></h2>
            <p><?= e(__('home.tools_lead')) ?></p>
        </div>
    </div>
    <?= $view->partial('partials/tool-grid', ['tools' => $tools]) ?>
</section>

<div class="container">
    <div class="row g-4">
        <section class="col-lg-8" aria-labelledby="recent-docs-heading">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="dash-card-header">
                        <span class="icon-chip" aria-hidden="true"><?= $view->icon('files') ?></span>
                        <h2 id="recent-docs-heading"><?= e(__('dashboard.recent_documents')) ?></h2>
                        <?php if ($recentDocuments !== []): ?>
                            <a class="small ms-auto" href="<?= e($url->page('/documents')) ?>"><?= e(__('common.view_all')) ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if ($recentDocuments === []): ?>
                        <?= $emptyState('inbox', __('dashboard.no_documents'), __('dashboard.no_documents_hint')) ?>
                    <?php else: ?>
                        <ul class="dash-list">
                            <?php foreach ($recentDocuments as $doc): ?>
                                <li>
                                    <a class="d-flex align-items-center gap-2 min-w-0" href="<?= e($url->page('/documents/' . $doc['public_id'])) ?>">
                                        <span class="file-chip-icon" aria-hidden="true"><?= $view->icon('file-earmark-pdf') ?></span>
                                        <span class="text-truncate"><?= e($doc['original_name']) ?></span>
                                    </a>
                                    <span class="small text-body-secondary text-nowrap">
                                        <?= e(trans_choice('documents.version_count', (int) $doc['version_count'])) ?>
                                        · <?= e(App\Support\Size::format((int) $doc['total_size'])) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="col-lg-4" aria-labelledby="storage-heading">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="dash-card-header">
                        <span class="icon-chip" aria-hidden="true"><?= $view->icon('hdd') ?></span>
                        <h2 id="storage-heading"><?= e(__('dashboard.storage')) ?></h2>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                        <span class="stat-value"><?= e(App\Support\Size::format($storageUsed)) ?></span>
                        <?php if ($storagePercent !== null): ?>
                            <span class="small text-body-secondary"><?= e(__('dashboard.storage_percent', ['percent' => $storagePercent])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($storageLimit > 0): ?>
                        <progress class="storage-meter w-100 mb-2<?= $storagePercent >= 90 ? ' is-high' : '' ?>" max="<?= (int) $storageLimit ?>" value="<?= (int) min($storageUsed, $storageLimit) ?>"
                                  aria-labelledby="storage-heading" aria-describedby="storage-caption"></progress>
                        <p class="small text-body-secondary mb-0" id="storage-caption"><?= e(__('dashboard.storage_used', [
                            'used' => App\Support\Size::format($storageUsed),
                            'limit' => App\Support\Size::format($storageLimit),
                        ])) ?></p>
                    <?php else: ?>
                        <p class="small text-body-secondary mb-0"><?= e(__('dashboard.storage_used_unlimited', ['used' => App\Support\Size::format($storageUsed)])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="col-lg-6" aria-labelledby="expiring-heading">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="dash-card-header">
                        <span class="icon-chip" aria-hidden="true"><?= $view->icon('hourglass-split') ?></span>
                        <h2 id="expiring-heading"><?= e(__('dashboard.expiring_soon')) ?></h2>
                    </div>
                    <?php if ($expiringSoon === []): ?>
                        <?= $emptyState('clock-history', __('dashboard.expiring_none'), __('dashboard.expiring_hint')) ?>
                    <?php else: ?>
                        <ul class="dash-list small">
                            <?php foreach ($expiringSoon as $item): ?>
                                <li>
                                    <a class="text-truncate" href="<?= e($url->page('/documents/' . $item['public_id'])) ?>"><?= e($item['original_name']) ?></a>
                                    <time class="text-nowrap text-body-secondary" datetime="<?= e(App\Support\DateFormatter::iso($item['expires_at'])) ?>"><?= e($dates->format($item['expires_at'], $locale)) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="col-lg-6" aria-labelledby="ops-heading">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="dash-card-header">
                        <span class="icon-chip" aria-hidden="true"><?= $view->icon('clock-history') ?></span>
                        <h2 id="ops-heading"><?= e(__('dashboard.recent_operations')) ?></h2>
                    </div>
                    <?php if ($recentOperations === []): ?>
                        <?= $emptyState('inbox', __('dashboard.no_operations'), __('dashboard.no_operations_hint')) ?>
                    <?php else: ?>
                        <ul class="dash-list small">
                            <?php foreach ($recentOperations as $op): ?>
                                <li>
                                    <span class="min-w-0 text-truncate">
                                        <span class="fw-semibold"><?= e(__('audit.events.' . $op['type'])) ?></span>
                                        <?php if ($op['document_public_id'] !== null): ?>
                                            · <a href="<?= e($url->page('/documents/' . $op['document_public_id'])) ?>"><?= e((string) $op['original_name']) ?></a>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge <?= match ($op['status']) { 'failed' => 'badge-soft-danger', 'completed' => 'badge-soft-success', default => 'badge-soft' } ?>"><?= e(__('audit.status.' . $op['status'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
