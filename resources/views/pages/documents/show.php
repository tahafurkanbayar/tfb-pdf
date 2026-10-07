<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var App\Support\DateFormatter $dates
 * @var string $locale
 * @var App\Domain\Document $document
 * @var list<App\Domain\DocumentVersion> $versions
 * @var array{policy: string, expires_at: ?string}|null $expiry
 * @var list<array<string, mixed>> $operations
 * @var list<array<string, mixed>> $auditEvents
 * @var array<int, array{inputs: list<int>, external: int}> $sources
 */
$view->extend('layouts/app');
$view->section('title', $document->originalName);
$view->section('robots', 'noindex');
$view->section('i18n', 'preview,hash');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/pages/document.js')) . '"></script>');

$latest = $versions === [] ? null : $versions[array_key_last($versions)];
$hasPdf = array_filter($versions, static fn (App\Domain\DocumentVersion $v): bool => $v->isPdf()) !== [];
$downloadUrl = static fn (App\Domain\DocumentVersion $v): string => $url->to('/api/documents/' . $document->publicId . '/versions/' . $v->versionNumber . '/download');
$policy = $expiry['policy'] ?? 'never';
$pdfVersions = array_values(array_filter($versions, static fn (App\Domain\DocumentVersion $v): bool => $v->isPdf()));
?>
<div class="container py-4" data-document="<?= e($document->publicId) ?>">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e($url->page('/documents')) ?>"><?= e(__('documents.title')) ?></a></li>
            <li class="breadcrumb-item active text-truncate" aria-current="page"><?= e($document->originalName) ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div class="min-w-0">
            <h1 class="h3 text-break mb-1"><?= e($document->originalName) ?></h1>
            <p class="text-body-secondary small mb-0">
                <?= e(__('documents.kind.' . $document->sourceType)) ?> ·
                <?= e(__('documents.created')) ?>: <time datetime="<?= e(App\Support\DateFormatter::iso($document->createdAt)) ?>"><?= e($dates->format($document->createdAt, $locale)) ?></time>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($latest !== null): ?>
                <a class="btn btn-primary" href="<?= e($downloadUrl($latest)) ?>">
                    <?= $view->icon('download') ?> <?= e(__('documents.download_version', ['version' => $latest->isOriginal() ? __('documents.original') : __('documents.version_n', ['number' => $latest->versionNumber])])) ?>
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-modal">
                <?= $view->icon('trash') ?> <?= e(__('documents.delete')) ?>
            </button>
        </div>
    </div>

    <div class="alert alert-light border d-flex gap-2 small" role="note">
        <?= $view->icon('lock', 'flex-shrink-0 mt-1') ?><span><?= e(__('documents.original_protected')) ?></span>
    </div>

    <?php if ($hasPdf): ?>
        <section class="mb-4" aria-labelledby="process-heading">
            <h2 id="process-heading" class="h6 text-body-secondary text-uppercase small fw-semibold"><?= e(__('documents.process_with')) ?></h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (['split', 'reorder', 'rotate', 'compress', 'watermark', 'redact', 'merge', 'sign'] as $tool): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e($url->page('/tools/' . $tool, ['document' => $document->publicId])) ?>"><?= e(__('pdf.' . $tool)) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card mb-4" aria-labelledby="versions-heading">
        <div class="card-body">
            <h2 id="versions-heading" class="h5"><?= e(__('documents.versions')) ?></h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col"><?= e(__('common.version')) ?></th>
                        <th scope="col" class="d-none d-md-table-cell"><?= e(__('documents.pages')) ?></th>
                        <th scope="col"><?= e(__('documents.size')) ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?= e(__('documents.sha256')) ?></th>
                        <th scope="col" class="d-none d-md-table-cell"><?= e(__('documents.created')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_reverse($versions) as $version): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold"><?= e($version->isOriginal() ? __('documents.original') : __('documents.version_n', ['number' => $version->versionNumber])) ?></span>
                                <span class="d-block small text-body-secondary">
                                    <?= e($version->filename) ?>
                                    <?php if ($version->operationType !== null): ?> · <?= e(__('audit.events.' . $version->operationType)) ?><?php endif; ?>
                                    <?php if ($version->label !== null): ?> · <?= e($version->label) ?><?php endif; ?>
                                </span>
                                <?php if (isset($sources[$version->id])): ?>
                                    <span class="d-block small text-body-secondary">
                                        <?php if ($sources[$version->id]['inputs'] !== []): ?>
                                            <?= e(__('documents.source_versions', ['versions' => implode(', ', array_map(static fn (int $n): string => $n === 0 ? __('documents.original') : __('documents.version_n', ['number' => $n]), $sources[$version->id]['inputs']))])) ?>
                                        <?php endif; ?>
                                        <?php if ($sources[$version->id]['external'] > 0): ?>
                                            <?= e(trans_choice('documents.source_external', $sources[$version->id]['external'])) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell"><?= $version->pageCount === null ? '—' : (int) $version->pageCount ?></td>
                            <td class="text-nowrap"><?= e(App\Support\Size::format($version->fileSize)) ?></td>
                            <td class="d-none d-lg-table-cell">
                                <code class="small hash" title="<?= e($version->sha256) ?>"><?= e(substr($version->sha256, 0, 16)) ?>…</code>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="<?= e($version->sha256) ?>" aria-label="<?= e(__('documents.copy_hash')) ?>">
                                    <?= $view->icon('clipboard') ?>
                                </button>
                            </td>
                            <td class="d-none d-md-table-cell small text-nowrap"><time datetime="<?= e(App\Support\DateFormatter::iso($version->createdAt)) ?>"><?= e($dates->format($version->createdAt, $locale)) ?></time></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e($downloadUrl($version)) ?>">
                                    <?= $view->icon('download') ?><span class="visually-hidden"> <?= e(__('documents.download')) ?></span>
                                </a>
                                <?php if ($version->isPdf()): ?>
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="<?= e(__('documents.use_version')) ?>" title="<?= e(__('documents.use_version')) ?>"><?= $view->icon('gear') ?></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php foreach (['split', 'reorder', 'rotate', 'compress', 'watermark', 'redact', 'ocr', 'sign'] as $tool): ?>
                                                <li><a class="dropdown-item" href="<?= e($url->page('/tools/' . $tool, ['document' => $document->publicId, 'version' => $version->versionNumber])) ?>"><?= e(__('pdf.' . $tool)) ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-body-secondary mt-3 mb-0"><?= e(__('notices.hash')) ?></p>
        </div>
    </section>

    <section class="card mb-4" aria-labelledby="integrity-heading" data-integrity
             data-hashes="<?= e((string) json_encode(array_map(static fn (App\Domain\DocumentVersion $v): array => [
                 'label' => $v->isOriginal() ? __('documents.original') : __('documents.version_n', ['number' => $v->versionNumber]),
                 'sha256' => $v->sha256,
             ], $versions), JSON_UNESCAPED_UNICODE)) ?>">
        <div class="card-body">
            <h2 id="integrity-heading" class="h5"><?= $view->icon('shield-check') ?> <?= e(__('hash.title')) ?></h2>
            <p class="small text-body-secondary"><?= e(__('hash.intro')) ?></p>
            <div class="row g-3">
                <div class="col-md-6">
                    <button type="button" class="btn btn-outline-primary" data-verify><?= e(__('hash.verify_server')) ?></button>
                    <div class="small mt-2" data-verify-result role="status" aria-live="polite"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="compare-file"><?= e(__('hash.compare_local')) ?></label>
                    <input class="form-control form-control-sm" type="file" id="compare-file" data-compare aria-describedby="compare-help">
                    <div class="form-text" id="compare-help"><?= e(__('hash.compare_help')) ?></div>
                    <div class="small mt-2" data-compare-result role="status" aria-live="polite"></div>
                </div>
            </div>
            <p class="small text-body-secondary mt-3 mb-0"><?= e(__('notices.hash')) ?></p>
        </div>
    </section>

    <?php if ($pdfVersions !== []): ?>
        <section class="card mb-4" aria-labelledby="preview-heading" data-preview>
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 id="preview-heading" class="h5 mb-0"><?= $view->icon('eye') ?> <?= e(__('preview.title')) ?></h2>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-body-secondary text-nowrap" for="preview-version"><?= e(__('preview.version_select')) ?></label>
                        <select class="form-select form-select-sm" id="preview-version" data-preview-version>
                            <?php foreach (array_reverse($pdfVersions) as $v): ?>
                                <option value="<?= (int) $v->versionNumber ?>"><?= e($v->isOriginal() ? __('documents.original') : __('documents.version_n', ['number' => $v->versionNumber])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="thumb-grid" data-preview-grid aria-live="polite"></div>
                <p class="small text-body-secondary mt-3 mb-0"><?= e(__('preview.browser_note')) ?></p>
            </div>
        </section>
    <?php endif; ?>

    <div class="row g-4">
        <section class="col-lg-5" aria-labelledby="expiry-heading">
            <div class="card h-100">
                <div class="card-body">
                    <h2 id="expiry-heading" class="h5"><?= $view->icon('hourglass-split') ?> <?= e(__('documents.expiry')) ?></h2>
                    <form class="d-flex gap-2 align-items-end" data-expiry-form>
                        <div class="flex-grow-1">
                            <label class="form-label small" for="expiry-policy"><?= e(__('documents.expiry')) ?></label>
                            <select class="form-select" id="expiry-policy" name="policy" aria-describedby="expiry-help">
                                <?php foreach (array_keys(App\Services\ExpiryPolicy::POLICIES) as $option): ?>
                                    <option value="<?= e($option) ?>"<?= $option === $policy ? ' selected' : '' ?>><?= e(__('documents.expiry_options.' . $option)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary"><?= e(__('common.save')) ?></button>
                    </form>
                    <p class="small text-body-secondary mt-2 mb-0" id="expiry-help" data-expiry-status>
                        <?= e(($expiry['expires_at'] ?? null) === null
                            ? __('documents.expires_never')
                            : __('documents.expires_at', ['date' => $dates->format($expiry['expires_at'], $locale)])) ?>
                    </p>
                    <p class="small text-body-secondary mb-0"><?= e(__('documents.expiry_help')) ?></p>
                </div>
            </div>
        </section>

        <section class="col-lg-7" aria-labelledby="history-heading">
            <div class="card h-100">
                <div class="card-body">
                    <h2 id="history-heading" class="h5"><?= $view->icon('clock-history') ?> <?= e(__('documents.history')) ?></h2>
                    <?php if ($operations === []): ?>
                        <p class="small text-body-secondary mb-0"><?= e(__('documents.history_empty')) ?></p>
                    <?php else: ?>
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($operations as $op): ?>
                                <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                                    <span><?= e(__('audit.events.' . $op['type'])) ?></span>
                                    <span class="text-nowrap">
                                        <span class="badge <?= $op['status'] === 'failed' ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= e(__('audit.status.' . $op['status'])) ?></span>
                                        <time class="text-body-secondary ms-1" datetime="<?= e(App\Support\DateFormatter::iso($op['started_at'])) ?>"><?= e($dates->format($op['started_at'], $locale)) ?></time>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <section class="card mt-4" aria-labelledby="audit-heading">
        <div class="card-body">
            <h2 id="audit-heading" class="h5"><?= $view->icon('fingerprint') ?> <?= e(__('documents.audit')) ?></h2>
            <p class="small text-body-secondary"><?= e(__('documents.audit_help')) ?></p>
            <?php if ($auditEvents === []): ?>
                <p class="small text-body-secondary mb-0"><?= e(__('documents.audit_empty')) ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead>
                        <tr>
                            <th scope="col"><?= e(__('documents.date')) ?></th>
                            <th scope="col"><?= e(__('documents.event')) ?></th>
                            <th scope="col"><?= e(__('documents.result')) ?></th>
                            <th scope="col" class="d-none d-md-table-cell"><?= e(__('documents.sha256')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($auditEvents as $event): ?>
                            <tr>
                                <td class="text-nowrap"><time datetime="<?= e(App\Support\DateFormatter::iso($event['created_at'])) ?>"><?= e($dates->format($event['created_at'], $locale)) ?></time></td>
                                <td><?= e(__('audit.events.' . $event['event_type'])) ?></td>
                                <td><?= e(__('audit.status.' . $event['status'])) ?></td>
                                <td class="d-none d-md-table-cell"><code class="hash"><?= e(substr((string) ($event['output_hash'] ?? $event['input_hash'] ?? ''), 0, 16)) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= $view->partial('partials/page-viewer') ?>

<div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="delete-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="delete-modal-title"><?= e(__('documents.delete_title')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0"><?= e(__('documents.delete_confirm')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="button" class="btn btn-danger" data-delete-confirm><?= e(__('documents.delete_confirm_button')) ?></button>
            </div>
        </div>
    </div>
</div>
