<?php
/**
 * Dashboard: yükleme, araçlar, son belgeler, son işlemler, depolama, yakında silinecekler.
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
?>
<section class="hero py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h1 class="display-6 fw-bold mb-3"><?= e(__('home.title')) ?></h1>
                <p class="lead text-body-secondary"><?= e(__('home.subtitle')) ?></p>
                <p class="small text-body-secondary d-flex gap-2 mb-0">
                    <?= $view->icon('shield-check', 'flex-shrink-0 mt-1') ?><span><?= e(__('notices.privacy')) ?></span>
                </p>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-1"><?= e(__('dashboard.upload_heading')) ?></h2>
                        <p class="small text-body-secondary"><?= e(__('dashboard.upload_hint')) ?></p>
                        <?= $view->partial('partials/dropzone', [
                            'accept' => '.pdf,application/pdf',
                            'types' => 'PDF',
                            'maxSize' => $maxSize,
                            'inputId' => 'home-upload',
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="tools" class="container mb-5" aria-labelledby="tools-heading">
    <h2 id="tools-heading" class="h4 mb-3"><?= e(__('tools.heading')) ?></h2>
    <?= $view->partial('partials/tool-grid', ['tools' => $tools]) ?>
</section>

<div class="container">
    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="recent-docs-heading">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 id="recent-docs-heading" class="h5 mb-0"><?= e(__('dashboard.recent_documents')) ?></h2>
                        <a class="small" href="<?= e($url->page('/documents')) ?>"><?= e(__('common.view_all')) ?></a>
                    </div>
                    <?php if ($recentDocuments === []): ?>
                        <p class="text-body-secondary mb-0"><?= e(__('dashboard.no_documents')) ?></p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentDocuments as $doc): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3">
                                    <a class="text-truncate d-flex align-items-center gap-2" href="<?= e($url->page('/documents/' . $doc['public_id'])) ?>">
                                        <?= $view->icon('file-earmark-pdf', 'flex-shrink-0') ?>
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

        <div class="col-lg-5 d-flex flex-column gap-4">
            <section class="card" aria-labelledby="storage-heading">
                <div class="card-body">
                    <h2 id="storage-heading" class="h5"><?= $view->icon('hdd') ?> <?= e(__('dashboard.storage')) ?></h2>
                    <?php if ($storageLimit > 0): ?>
                        <progress class="storage-meter w-100" max="<?= (int) $storageLimit ?>" value="<?= (int) min($storageUsed, $storageLimit) ?>"
                                  aria-labelledby="storage-heading"></progress>
                        <p class="small text-body-secondary mb-0"><?= e(__('dashboard.storage_used', [
                            'used' => App\Support\Size::format($storageUsed),
                            'limit' => App\Support\Size::format($storageLimit),
                        ])) ?></p>
                    <?php else: ?>
                        <p class="small text-body-secondary mb-0"><?= e(__('dashboard.storage_used_unlimited', ['used' => App\Support\Size::format($storageUsed)])) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card" aria-labelledby="expiring-heading">
                <div class="card-body">
                    <h2 id="expiring-heading" class="h5"><?= $view->icon('hourglass-split') ?> <?= e(__('dashboard.expiring_soon')) ?></h2>
                    <?php if ($expiringSoon === []): ?>
                        <p class="small text-body-secondary mb-0"><?= e(__('dashboard.expiring_none')) ?></p>
                    <?php else: ?>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($expiringSoon as $item): ?>
                                <li class="d-flex justify-content-between gap-2 py-1">
                                    <a class="text-truncate" href="<?= e($url->page('/documents/' . $item['public_id'])) ?>"><?= e($item['original_name']) ?></a>
                                    <time class="text-nowrap text-body-secondary" datetime="<?= e(App\Support\DateFormatter::iso($item['expires_at'])) ?>"><?= e($dates->format($item['expires_at'], $locale)) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card" aria-labelledby="ops-heading">
                <div class="card-body">
                    <h2 id="ops-heading" class="h5"><?= $view->icon('clock-history') ?> <?= e(__('dashboard.recent_operations')) ?></h2>
                    <?php if ($recentOperations === []): ?>
                        <p class="small text-body-secondary mb-0"><?= e(__('dashboard.no_operations')) ?></p>
                    <?php else: ?>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($recentOperations as $op): ?>
                                <li class="d-flex justify-content-between gap-2 py-1">
                                    <span>
                                        <?= e(__('audit.events.' . $op['type'])) ?>
                                        <?php if ($op['document_public_id'] !== null): ?>
                                            · <a href="<?= e($url->page('/documents/' . $op['document_public_id'])) ?>"><?= e((string) $op['original_name']) ?></a>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge <?= $op['status'] === 'failed' ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= e(__('audit.status.' . $op['status'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
