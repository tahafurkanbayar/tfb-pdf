<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 * @var array<string, bool> $capabilities
 */
$serverRender = ($capabilities['ghostscript'] ?? false) && ($capabilities['process'] ?? false);
$view->extend('layouts/app');
$view->section('title', __('pdf.redact'));
$view->section('description', __('tools.descriptions.redact'));
$view->section('i18n', 'tools,operations,preview,common,redact');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/redact.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="redact" data-server-render="<?= $serverRender ? '1' : '0' ?>" data-dpi="<?= App\Pdf\Redaction\Redactor::DPI ?>">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <div class="alert alert-info d-flex gap-2 small" role="note">
        <?= $view->icon('info-circle', 'flex-shrink-0 mt-1') ?>
        <div>
            <p class="mb-1"><?= e(__('redact.how_it_works')) ?></p>
            <p class="mb-0"><?= e($serverRender ? __('redact.renderer_server') : __('redact.renderer_browser')) ?></p>
        </div>
    </div>

    <section class="card mb-4" aria-labelledby="source-heading">
        <div class="card-body">
            <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
            <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'redact-file']) ?>
        </div>
    </section>

    <section class="card" aria-labelledby="pages-heading" data-pages-section hidden>
        <div class="card-body">
            <h2 id="pages-heading" class="h5 mb-1"><?= e(__('tools.ui.pages_heading')) ?></h2>
            <p class="small text-body-secondary"><?= e(__('redact.intro')) ?></p>
            <div class="page-sort-grid" data-grid></div>
            <div class="alert alert-warning small mt-4 d-flex gap-2" role="note">
                <?= $view->icon('exclamation-triangle', 'flex-shrink-0 mt-1') ?><span><?= e(__('redact.residual_warning')) ?></span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <button type="button" class="btn btn-danger btn-lg" data-run disabled><?= $view->icon('eraser') ?> <?= e(__('redact.action')) ?></button>
                <span class="small fw-semibold" data-summary aria-live="polite"></span>
            </div>
            <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
        </div>
    </section>

    <?= $view->partial('partials/operation-result') ?>
</div>

<div class="modal fade" id="redact-editor" tabindex="-1" aria-labelledby="redact-editor-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6" id="redact-editor-title" data-editor-title></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body bg-body-tertiary text-center">
                <p class="small text-body-secondary" id="redact-hint"><?= e(__('redact.drag_hint')) ?></p>
                <div class="redact-stage" data-stage aria-describedby="redact-hint">
                    <canvas data-editor-canvas></canvas>
                    <div class="redact-overlay" data-overlay></div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger" data-whole-page><?= e(__('redact.whole_page')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-clear><?= e(__('redact.clear')) ?></button>
                </div>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= e(__('redact.done')) ?></button>
            </div>
        </div>
    </div>
</div>
