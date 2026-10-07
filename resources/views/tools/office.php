<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var array{slug: string, icon: string, available: bool, requires: ?string} $tool
 */
$view->extend('layouts/app');
$view->section('title', __('pdf.office'));
$view->section('description', __('tools.descriptions.office'));
$view->section('i18n', 'tools,operations,preview,common,office');
if ($tool['available']) {
    $view->section('scripts', '<script type="module" src="' . e($url->asset('js/tools/office.js')) . '"></script>');
}
?>
<div class="container py-4 tool-page" data-tool="office">
    <?= $view->partial('partials/tool-header', ['tool' => $tool]) ?>

    <p><?= e(__('office.intro')) ?></p>

    <?php if (!$tool['available']): ?>
        <div class="card">
            <div class="card-body">
                <p class="mb-0"><?= e(__('office.requirements')) ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <?= $view->partial('partials/dropzone', [
                            'accept' => '.doc,.docx,.xls,.xlsx,.ppt,.pptx',
                            'types' => __('office.types'),
                            'maxSize' => $view->shared('maxUploadSize'),
                            'inputId' => 'office-file',
                        ]) ?>
                    </div>
                </div>
            </div>
            <aside class="col-lg-5">
                <div class="card">
                    <div class="card-body small">
                        <h2 class="h6"><?= e(__('office.notes_title')) ?></h2>
                        <ul class="mb-0">
                            <?php foreach (['font_substitution', 'layout_changes', 'office_forms', 'embedded_objects', 'office_signatures'] as $warning): ?>
                                <li><?= e(__('warnings.' . $warning)) ?></li>
                            <?php endforeach; ?>
                            <li><?= e(__('office.macros_note')) ?></li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
        <?= $view->partial('partials/operation-result') ?>
    <?php endif; ?>
</div>
