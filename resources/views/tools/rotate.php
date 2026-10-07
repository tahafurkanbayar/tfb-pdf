<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.rotate'));
$view->section('description', __('tools.descriptions.rotate'));
$view->section('i18n', 'tools,operations,preview,common,rotate');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/rotate.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="rotate">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <section class="card mb-4" aria-labelledby="source-heading">
        <div class="card-body">
            <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
            <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'rotate-file']) ?>
        </div>
    </section>

    <section class="card" aria-labelledby="pages-heading" data-pages-section hidden>
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 id="pages-heading" class="h5 mb-1"><?= e(__('tools.ui.pages_heading')) ?></h2>
                    <p class="small text-body-secondary mb-0"><?= e(__('rotate.hint')) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-all-left><?= $view->icon('arrow-counterclockwise') ?> <?= e(__('rotate.all_left')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-all-right><?= $view->icon('arrow-clockwise') ?> <?= e(__('rotate.all_right')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-reset><?= e(__('rotate.reset')) ?></button>
                </div>
            </div>
            <div class="page-sort-grid" data-grid></div>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                <button type="button" class="btn btn-primary btn-lg" data-run disabled><?= $view->icon('arrow-clockwise') ?> <?= e(__('rotate.action')) ?></button>
                <span class="small fw-semibold" data-summary aria-live="polite"></span>
            </div>
            <p class="small text-body-secondary mt-2 mb-0"><?= e(__('rotate.quality_note')) ?></p>
            <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
        </div>
    </section>

    <?= $view->partial('partials/operation-result') ?>
</div>
