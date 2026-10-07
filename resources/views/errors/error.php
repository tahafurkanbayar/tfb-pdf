<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var int $status
 * @var string $message
 * @var string $requestId
 */
$view->extend('layouts/app');
$view->section('title', $status === 404 ? __('errors.not_found_title') : __('errors.title'));
$view->section('robots', 'noindex');
?>
<div class="container py-5 page-narrow text-center">
    <p class="display-4 fw-bold text-body-tertiary mb-2"><?= (int) $status ?></p>
    <h1 class="h3 mb-3"><?= e($status === 404 ? __('errors.not_found_title') : __('errors.title')) ?></h1>
    <p class="lead"><?= e($message) ?></p>
    <?php if ($status >= 500): ?>
        <p class="text-body-secondary small mb-1"><?= e(__('errors.request_id', ['id' => $requestId])) ?></p>
        <p class="text-body-secondary small"><?= e(__('errors.request_id_hint')) ?></p>
    <?php endif; ?>
    <a class="btn btn-primary mt-3" href="<?= e($url->page('/')) ?>"><?= e(__('errors.back_home')) ?></a>
</div>
