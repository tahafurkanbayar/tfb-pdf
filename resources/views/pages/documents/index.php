<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var App\Support\DateFormatter $dates
 * @var string $locale
 * @var list<array<string, mixed>> $documents
 * @var int $storageUsed
 */
$view->extend('layouts/app');
$view->section('title', __('documents.title'));
$view->section('robots', 'noindex');
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0"><?= e(__('documents.title')) ?></h1>
        <a class="btn btn-primary" href="<?= e($url->page('/')) ?>"><?= $view->icon('cloud-arrow-up') ?> <?= e(__('documents.upload_new')) ?></a>
    </div>

    <?php if ($documents === []): ?>
        <div class="empty-state card text-center p-5">
            <div class="empty-state-icon mb-3" aria-hidden="true"><?= $view->icon('files') ?></div>
            <p class="fw-semibold mb-1"><?= e(__('documents.empty')) ?></p>
            <p class="text-body-secondary mb-0"><?= e(__('documents.empty_hint')) ?></p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col"><?= e(__('documents.name')) ?></th>
                        <th scope="col" class="d-none d-md-table-cell"><?= e(__('documents.versions')) ?></th>
                        <th scope="col" class="d-none d-sm-table-cell"><?= e(__('documents.total_size')) ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?= e(__('documents.updated')) ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?= e(__('documents.expiry')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td class="text-break">
                                <a class="d-flex align-items-center gap-2" href="<?= e($url->page('/documents/' . $doc['public_id'])) ?>">
                                    <?= $view->icon('file-earmark-pdf', 'flex-shrink-0') ?><span><?= e($doc['original_name']) ?></span>
                                </a>
                            </td>
                            <td class="d-none d-md-table-cell"><?= e(trans_choice('documents.version_count', (int) $doc['version_count'])) ?></td>
                            <td class="d-none d-sm-table-cell text-nowrap"><?= e(App\Support\Size::format((int) $doc['total_size'])) ?></td>
                            <td class="d-none d-lg-table-cell text-nowrap">
                                <time datetime="<?= e(App\Support\DateFormatter::iso($doc['updated_at'])) ?>"><?= e($dates->format($doc['updated_at'], $locale)) ?></time>
                            </td>
                            <td class="d-none d-lg-table-cell small text-body-secondary">
                                <?= e($doc['expires_at'] === null
                                    ? __('documents.expires_never')
                                    : __('documents.expires_at', ['date' => $dates->format($doc['expires_at'], $locale)])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-body-secondary mt-3"><?= e(__('dashboard.storage_used_unlimited', ['used' => App\Support\Size::format($storageUsed)])) ?></p>
    <?php endif; ?>

    <p class="small text-body-secondary mt-3 d-flex gap-2"><?= $view->icon('info-circle', 'flex-shrink-0 mt-1') ?><span><?= e(__('notices.cookie')) ?></span></p>
</div>
