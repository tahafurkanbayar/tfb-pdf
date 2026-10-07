<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.sign'));
$view->section('description', __('tools.descriptions.sign'));
$view->section('i18n', 'tools,operations,preview,common,signature');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/sign.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="sign" data-max-signers="<?= App\Services\SignatureService::MAX_SIGNERS ?>">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <p><?= e(__('signature.intro')) ?></p>
    <div class="alert alert-warning small d-flex gap-2" role="note">
        <?= $view->icon('exclamation-triangle', 'flex-shrink-0 mt-1') ?><span><?= e(__('signature.not_qes')) ?></span>
    </div>

    <section class="card mb-4" aria-labelledby="source-heading">
        <div class="card-body">
            <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
            <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'sign-file']) ?>
        </div>
    </section>

    <div data-editor-area hidden>
        <section class="card mb-4" aria-labelledby="signers-heading">
            <div class="card-body">
                <h2 id="signers-heading" class="h5"><?= e(__('signature.signers_heading')) ?></h2>
                <div data-signers></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-signer><?= $view->icon('plus-lg') ?> <?= e(__('signature.add_signer')) ?></button>
                <p class="form-text"><?= e(__('signature.signer_email_help')) ?></p>
            </div>
        </section>

        <section class="card mb-4" aria-labelledby="fields-heading">
            <div class="card-body">
                <h2 id="fields-heading" class="h5 mb-1"><?= e(__('signature.fields_heading')) ?></h2>
                <p class="small text-body-secondary"><?= e(__('signature.fields_hint')) ?></p>
                <div class="page-sort-grid" data-grid></div>
            </div>
        </section>

        <section class="card" aria-labelledby="send-heading">
            <div class="card-body">
                <label class="form-label" for="sign-message" id="send-heading"><?= e(__('signature.message')) ?></label>
                <textarea class="form-control mb-3" id="sign-message" rows="3" maxlength="1000"></textarea>
                <button type="button" class="btn btn-primary btn-lg" data-run><?= $view->icon('pen') ?> <?= e(__('signature.create')) ?></button>
                <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
            </div>
        </section>
    </div>

    <section class="card border-success-subtle mt-4" data-links hidden tabindex="-1" aria-labelledby="links-heading">
        <div class="card-body">
            <h2 class="h5" id="links-heading"><?= $view->icon('check-circle', 'text-success') ?> <?= e(__('signature.created')) ?></h2>
            <p class="small"><?= e(__('signature.links_help')) ?></p>
            <ul class="list-unstyled mb-2" data-link-list></ul>
            <p class="small text-body-secondary" data-expires></p>
            <a class="btn btn-outline-secondary" data-open-document><?= $view->icon('box-arrow-up-right') ?> <?= e(__('operations.open_document')) ?></a>
        </div>
    </section>
</div>

<div class="modal fade" id="field-editor" tabindex="-1" aria-labelledby="field-editor-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6" id="field-editor-title" data-editor-title></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
            </div>
            <div class="modal-body bg-body-tertiary text-center">
                <p class="small text-body-secondary" data-editor-signer></p>
                <div class="redact-stage" data-stage>
                    <canvas data-editor-canvas></canvas>
                    <div class="redact-overlay" data-overlay></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= e(__('redact.done')) ?></button>
            </div>
        </div>
    </div>
</div>
