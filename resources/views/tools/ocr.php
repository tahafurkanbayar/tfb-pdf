<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 * @var array<string, bool> $capabilities
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.ocr'));
$view->section('description', __('tools.descriptions.ocr'));
$view->section('i18n', 'tools,operations,preview,common,ocr');
if ($tool['available']) {
    $view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/ocr.js')) . '"></script>');
}
?>
<div class="container py-4 tool-page" data-tool="ocr">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <p><?= e(__('ocr.intro')) ?></p>

    <?php if (!$tool['available']): ?>
        <div class="card">
            <div class="card-body">
                <p class="mb-2"><?= e(__('ocr.requirements')) ?></p>
                <p class="small text-body-secondary mb-0"><?= e(__('notices.privacy')) ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card" aria-labelledby="source-heading">
                    <div class="card-body">
                        <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
                        <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'ocr-file']) ?>
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-warning small d-flex gap-2" role="note">
                            <?= $view->icon('exclamation-triangle', 'flex-shrink-0 mt-1') ?><span><?= e(__('ocr.accuracy')) ?></span>
                        </div>
                        <p class="small text-body-secondary"><?= e(__('ocr.slow_note')) ?></p>
                        <button type="button" class="btn btn-primary btn-lg w-100" data-run disabled>
                            <?= $view->icon('fonts') ?> <?= e(__('ocr.action')) ?>
                        </button>
                        <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </div>
        <?= $view->partial('partials/operation-result') ?>
    <?php endif; ?>
</div>
