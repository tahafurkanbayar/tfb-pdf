<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.split'));
$view->section('description', __('tools.descriptions.split'));
$view->section('i18n', 'tools,operations,preview,common,split');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/split.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="split">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card mb-4" aria-labelledby="source-heading">
                <div class="card-body">
                    <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
                    <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'split-file']) ?>
                </div>
            </section>

            <section class="card" aria-labelledby="pages-heading" data-pages-section hidden>
                <div class="card-body">
                    <h2 id="pages-heading" class="h5"><?= e(__('tools.ui.pages_heading')) ?></h2>
                    <p class="small text-body-secondary" data-select-hint><?= e(__('split.select_hint')) ?></p>
                    <div class="thumb-grid" data-page-grid></div>
                </div>
            </section>
        </div>

        <aside class="col-lg-4">
            <form class="card sticky-lg-top tool-sidebar" data-settings novalidate>
                <div class="card-body">
                    <h2 class="h5"><?= e(__('tools.ui.settings_heading')) ?></h2>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6"><?= e(__('split.mode_label')) ?></legend>
                        <?php foreach (App\Services\Operations\PdfToolService::SPLIT_MODES as $i => $mode): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode-<?= e($mode) ?>" value="<?= e($mode) ?>"
                                       aria-describedby="mode-<?= e($mode) ?>-help"<?= $mode === 'ranges' ? ' checked' : '' ?>>
                                <label class="form-check-label" for="mode-<?= e($mode) ?>"><?= e(__('split.modes.' . $mode)) ?></label>
                                <div class="form-text mt-0" id="mode-<?= e($mode) ?>-help"><?= e(__('split.mode_help.' . $mode)) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>

                    <div class="mb-3" data-ranges-group>
                        <label class="form-label" for="ranges"><?= e(__('split.ranges_label')) ?></label>
                        <textarea class="form-control font-monospace" id="ranges" name="ranges" rows="3" placeholder="1-3, 5, 8-12"
                                  aria-describedby="ranges-help ranges-error" spellcheck="false" autocomplete="off"></textarea>
                        <div class="invalid-feedback" id="ranges-error" data-ranges-error></div>
                        <div class="form-text" id="ranges-help"><?= e(__('split.ranges_help')) ?></div>
                    </div>

                    <p class="small text-body-secondary" data-outputs-count aria-live="polite"></p>
                    <button type="submit" class="btn btn-primary btn-lg w-100" data-run disabled>
                        <?= $view->icon('scissors') ?> <?= e(__('split.action')) ?>
                    </button>
                    <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
                </div>
            </form>
        </aside>
    </div>

    <?= $view->partial('partials/operation-result') ?>
</div>
