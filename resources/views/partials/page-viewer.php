<?php
/**
 * Büyük sayfa görüntüleyici (modal). JS: assets/js/viewer.js
 *
 * @var App\Core\View $view
 */
?>
<div class="modal fade" id="page-viewer" tabindex="-1" aria-labelledby="page-viewer-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6" id="page-viewer-title"><?= e(__('preview.viewer_title')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body text-center bg-body-tertiary viewer-body">
                <div class="viewer-status small text-body-secondary py-5" data-viewer-status role="status"></div>
                <canvas class="viewer-canvas shadow-sm" data-viewer-canvas></canvas>
            </div>
            <div class="modal-footer justify-content-center gap-3">
                <button type="button" class="btn btn-outline-secondary" data-viewer-prev aria-label="<?= e(__('preview.previous')) ?>"><?= $view->icon('chevron-left') ?></button>
                <span class="small" data-viewer-indicator aria-live="polite"></span>
                <button type="button" class="btn btn-outline-secondary" data-viewer-next aria-label="<?= e(__('preview.next')) ?>"><?= $view->icon('chevron-right') ?></button>
            </div>
        </div>
    </div>
</div>
