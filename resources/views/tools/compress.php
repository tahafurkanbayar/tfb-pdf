<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 * @var array{id: string, name: string, version: int, pages: ?int}|null $preselected
 * @var array<string, bool> $capabilities
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.compress'));
$view->section('description', __('tools.descriptions.compress'));
$view->section('i18n', 'tools,operations,preview,common,compress');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/compress.js')) . '"></script>');
?>
<div class="container py-4 tool-page" data-tool="compress">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="card" aria-labelledby="source-heading">
                <div class="card-body">
                    <h2 id="source-heading" class="h5"><?= e(__('tools.ui.source_heading')) ?></h2>
                    <?= $view->partial('partials/source-picker', ['preselected' => $preselected, 'inputId' => 'compress-file']) ?>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <form class="card" data-settings novalidate>
                <div class="card-body">
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6"><?= e(__('compress.level_label')) ?></legend>
                        <?php foreach (App\Pdf\Compression\Compressor::LEVELS as $level): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="level" id="level-<?= e($level) ?>" value="<?= e($level) ?>"
                                       aria-describedby="level-<?= e($level) ?>-help"<?= $level === 'medium' ? ' checked' : '' ?>>
                                <label class="form-check-label" for="level-<?= e($level) ?>"><?= e(__('compress.levels.' . $level)) ?></label>
                                <div class="form-text mt-0" id="level-<?= e($level) ?>-help"><?= e(__('compress.level_help.' . $level)) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="small alert alert-light border d-flex gap-2">
                        <?= $view->icon('info-circle', 'flex-shrink-0 mt-1') ?>
                        <span><?= e(($capabilities['ghostscript'] ?? false) ? __('compress.engine_ghostscript') : __('compress.engine_php')) ?></span>
                    </p>
                    <button type="submit" class="btn btn-primary btn-lg w-100" data-run disabled>
                        <?= $view->icon('file-zip') ?> <?= e(__('compress.action')) ?>
                    </button>
                    <div class="mt-3" data-run-status role="status" aria-live="polite"></div>
                </div>
            </form>
        </div>
    </div>

    <?= $view->partial('partials/operation-result') ?>
</div>
