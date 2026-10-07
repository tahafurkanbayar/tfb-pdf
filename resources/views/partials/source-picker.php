<?php
/**
 * Tek belgeyle çalışan araçlar için kaynak seçimi: ön seçili belge veya yeni yükleme.
 * JS: assets/js/tools/source.js
 *
 * @var App\Core\View $view
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 * @var string $inputId
 */
?>
<div data-source data-preselected="<?= e($preselected === null ? '' : json_encode($preselected, JSON_UNESCAPED_UNICODE)) ?>">
    <div data-source-upload>
        <?= $view->partial('partials/dropzone', [
            'accept' => '.pdf,application/pdf',
            'types' => 'PDF',
            'maxSize' => $view->shared('maxUploadSize'),
            'inputId' => $inputId,
        ]) ?>
    </div>
    <div class="d-flex align-items-center gap-3" data-source-selected hidden>
        <span class="file-thumb" aria-hidden="true"><?= $view->icon('file-earmark-pdf') ?></span>
        <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate" data-source-name></div>
            <div class="small text-body-secondary" data-source-meta></div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-source-change><?= e(__('tools.ui.change_file')) ?></button>
    </div>
</div>
