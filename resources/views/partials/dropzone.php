<?php
/**
 * Dosya yükleme alanı. JS: assets/js/upload.js (data-dropzone).
 *
 * @var App\Core\View $view
 * @var string $accept      input accept değeri
 * @var bool $multiple
 * @var string $types       Kullanıcıya gösterilen tür listesi
 * @var int $maxSize
 * @var string $inputId
 * @var bool $large         Büyük, belirgin alan (ana sayfa)
 * @var bool $preview       Seçilen dosyaları ad/boyut/ilerleme ile listele (data-dropzone-files)
 */
$multiple ??= false;
$inputId ??= 'file-input';
$large ??= false;
$preview ??= false;
?>
<div class="dropzone text-center <?= $large ? 'dropzone-lg' : 'p-4 p-md-5' ?>" data-dropzone>
    <div class="dropzone-icon mb-3" aria-hidden="true"><?= $view->icon('cloud-arrow-up') ?></div>
    <p class="fw-semibold fs-5 mb-1"><?= e(__('upload.drop_here')) ?></p>
    <p class="text-body-secondary small mb-3"><?= e(__('upload.or')) ?></p>
    <label class="btn btn-primary<?= $large ? ' btn-lg px-4' : '' ?>" for="<?= e($inputId) ?>">
        <?= $view->icon('plus-lg') ?> <?= e(__('upload.browse')) ?>
    </label>
    <input class="visually-hidden" type="file" id="<?= e($inputId) ?>" accept="<?= e($accept) ?>"<?= $multiple ? ' multiple' : '' ?>
           data-dropzone-input aria-describedby="<?= e($inputId) ?>-help">
    <p class="small text-body-secondary mt-3 mb-0" id="<?= e($inputId) ?>-help">
        <?= e(__('upload.allowed_types', ['types' => $types])) ?> · <?= e(__('upload.max_size', ['size' => App\Support\Size::format($maxSize)])) ?>
    </p>
    <?php if ($preview): ?>
        <ul class="file-chips" data-dropzone-files aria-label="<?= e(__('upload.selected_files')) ?>" hidden></ul>
    <?php endif; ?>
    <div class="dropzone-status mt-3" data-dropzone-status role="status" aria-live="polite"></div>
</div>
