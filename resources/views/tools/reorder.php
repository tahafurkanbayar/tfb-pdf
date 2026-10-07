<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.reorder'));
$view->section('description', __('tools.descriptions.reorder'));
$view->section('i18n', 'tools,operations,preview,common,reorder');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/reorder.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="reorder">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <section class="card mb-4" aria-labelledby="source-heading">
        <div class="card-body">
            <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
            <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'reorder-file']) ?>
        </div>
    </section>

    <section class="card" aria-labelledby="pages-heading" data-pages-section hidden>
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 id="pages-heading" class="h5 mb-1"><?= e(__('tools.ui.pages_heading')) ?></h2>
                    <p class="small text-body-secondary mb-0"><?= e(__('reorder.hint')) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-outline-secondary" data-reset><?= $view->icon('arrow-counterclockwise') ?> <?= e(__('reorder.reset')) ?></button>
                    <button type="button" class="btn btn-primary" data-run disabled><?= $view->icon('grid-3x3-gap') ?> <?= e(__('reorder.action')) ?></button>
                </div>
            </div>
            <p class="small fw-semibold" data-summary aria-live="polite"></p>
            <div class="page-sort-grid" data-sort-grid></div>
            <div class="visually-hidden" aria-live="polite" data-announcer></div>
            <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
        </div>
    </section>

    <?= $view->partial('partials/operation-result') ?>
</div>
