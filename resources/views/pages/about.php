<?php
/**
 * @var App\Core\View $view
 * @var string $appName
 */
$view->extend('layouts/app');
$view->section('title', __('about.title'));
?>
<div class="container py-5 page-narrow">
    <h1 class="h2 mb-4"><?= e(__('about.title')) ?></h1>
    <p><?= e(__('about.intro', ['app' => $appName])) ?></p>

    <div class="alert alert-warning d-flex gap-3 my-4" role="note">
        <?= $view->icon('exclamation-triangle', 'flex-shrink-0 fs-4') ?>
        <div>
            <h2 class="h6 fw-semibold"><?= e(__('notices.disclaimer_title')) ?></h2>
            <p class="mb-2"><?= e(__('notices.disclaimer_intro')) ?></p>
            <ul class="mb-0">
                <?php foreach (['official', 'qes', 'identity', 'governance', 'editor'] as $item): ?>
                    <li><?= e(__('notices.disclaimer_items.' . $item)) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <h2 class="h5 mt-4"><?= e(__('about.versions_title')) ?></h2>
    <p><?= e(__('about.versions_text')) ?></p>
    <p class="text-body-secondary"><?= e(__('notices.hash')) ?></p>
    <p class="mt-4"><?= e(__('about.open_source')) ?></p>
</div>
