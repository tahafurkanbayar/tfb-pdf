<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.merge'));
$view->section('description', __('tools.descriptions.merge'));
$view->section('i18n', 'tools,operations,preview,common');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/merge.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="merge"
     data-preselected="<?= e($preselected === null ? '' : json_encode($preselected, JSON_UNESCAPED_UNICODE)) ?>">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <?= $view->partial('partials/dropzone', [
                        'accept' => '.pdf,application/pdf',
                        'types' => 'PDF',
                        'maxSize' => $view->shared('maxUploadSize'),
                        'multiple' => true,
                        'inputId' => 'merge-files',
                    ]) ?>
                </div>
            </div>

            <section class="mt-4" aria-labelledby="files-heading">
                <h2 id="files-heading" class="h5"><?= e(__('tools.ui.files_heading')) ?></h2>
                <p class="small text-body-secondary" data-empty><?= e(__('tools.ui.empty_list')) ?></p>
                <ol class="file-list list-unstyled mb-2" data-file-list></ol>
                <p class="small text-body-secondary"><?= e(__('tools.ui.list_note')) ?></p>
                <div class="visually-hidden" aria-live="polite" data-announcer></div>
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="card sticky-lg-top tool-sidebar">
                <div class="card-body">
                    <p class="small text-body-secondary"><?= e(__('tools.merge.hint')) ?></p>
                    <button type="button" class="btn btn-primary btn-lg w-100" data-run disabled>
                        <?= $view->icon('files') ?> <?= e(__('tools.merge.action')) ?>
                    </button>
                    <p class="small text-body-secondary mt-2 mb-0" data-run-hint><?= e(__('tools.merge.need_two')) ?></p>
                    <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
                </div>
            </div>
        </aside>
    </div>

    <?= $view->partial('partials/operation-result') ?>
</div>
