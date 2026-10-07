<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.watermark'));
$view->section('description', __('tools.descriptions.watermark'));
$view->section('i18n', 'tools,operations,preview,common,watermark,split');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/watermark.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="watermark">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <div class="row g-4">
        <div class="col-lg-7 d-flex flex-column gap-4">
            <section class="card" aria-labelledby="source-heading">
                <div class="card-body">
                    <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
                    <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'watermark-file']) ?>
                </div>
            </section>
            <section class="card" aria-labelledby="preview-heading" data-preview-section hidden>
                <div class="card-body">
                    <h2 id="preview-heading" class="h5"><?= e(__('preview.title')) ?></h2>
                    <div class="watermark-preview" data-preview>
                        <img alt="" data-preview-image>
                        <span class="watermark-preview-text" data-preview-text aria-hidden="true"></span>
                    </div>
                    <p class="small text-body-secondary mt-2 mb-0"><?= e(__('watermark.preview_note')) ?></p>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <form class="card" data-settings novalidate>
                <div class="card-body">
                    <h2 class="h5"><?= e(__('tools.ui.settings_heading')) ?></h2>
                    <div class="mb-3">
                        <label class="form-label" for="wm-text"><?= e(__('watermark.text')) ?></label>
                        <input class="form-control" id="wm-text" name="text" maxlength="100" required
                               placeholder="<?= e(__('watermark.text_placeholder')) ?>" aria-describedby="wm-text-error">
                        <div class="invalid-feedback" id="wm-text-error"><?= e(__('watermark.text_invalid')) ?></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label" for="wm-position"><?= e(__('watermark.position')) ?></label>
                            <select class="form-select" id="wm-position" name="position">
                                <?php foreach (App\Pdf\WatermarkOptions::POSITIONS as $position): ?>
                                    <option value="<?= e($position) ?>"><?= e(__('watermark.positions.' . $position)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="wm-rotation"><?= e(__('watermark.rotation')) ?></label>
                            <input class="form-control" type="number" id="wm-rotation" name="rotation" value="45" min="-180" max="180" step="5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between" for="wm-opacity">
                            <span><?= e(__('watermark.opacity')) ?></span><output data-opacity-value></output>
                        </label>
                        <input class="form-range" type="range" id="wm-opacity" name="opacity" min="5" max="100" step="5" value="30">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="wm-size"><?= e(__('watermark.font_size')) ?></label>
                            <input class="form-control" type="number" id="wm-size" name="font_size" value="48" min="6" max="200">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="wm-color"><?= e(__('watermark.color')) ?></label>
                            <input class="form-control form-control-color w-100" type="color" id="wm-color" name="color" value="#808080">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="wm-bold" name="bold" checked>
                        <label class="form-check-label" for="wm-bold"><?= e(__('watermark.bold')) ?></label>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6"><?= e(__('watermark.layer')) ?></legend>
                        <?php foreach (['over', 'under'] as $layer): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="layer" id="wm-layer-<?= e($layer) ?>" value="<?= e($layer) ?>"<?= $layer === 'over' ? ' checked' : '' ?>>
                                <label class="form-check-label" for="wm-layer-<?= e($layer) ?>"><?= e(__('watermark.layers.' . $layer)) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-text"><?= e(__('watermark.layer_help')) ?></div>
                    </fieldset>
                    <div class="mb-3">
                        <label class="form-label" for="wm-pages"><?= e(__('watermark.pages')) ?></label>
                        <input class="form-control font-monospace" id="wm-pages" name="pages" placeholder="1-3, 5" autocomplete="off"
                               aria-describedby="wm-pages-help wm-pages-error">
                        <div class="invalid-feedback" id="wm-pages-error" data-pages-error></div>
                        <div class="form-text" id="wm-pages-help"><?= e(__('watermark.pages_help')) ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" data-run disabled>
                        <?= $view->icon('droplet') ?> <?= e(__('watermark.action')) ?>
                    </button>
                    <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
                </div>
            </form>
        </div>
    </div>

    <?= $view->partial('partials/operation-result') ?>
</div>
