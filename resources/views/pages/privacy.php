<?php
/**
 * @var App\Core\View $view
 */
$view->extend('layouts/app');
$view->section('title', __('privacy.title'));
?>
<div class="container py-5 page-narrow">
    <h1 class="h2 mb-4"><?= e(__('privacy.title')) ?></h1>

    <h2 class="h5"><?= e(__('privacy.storage_title')) ?></h2>
    <p><?= e(__('notices.privacy')) ?></p>

    <h2 class="h5 mt-4"><?= e(__('privacy.retention_title')) ?></h2>
    <p><?= e(__('privacy.retention_text')) ?></p>
    <p class="text-body-secondary"><?= e(__('notices.cookie')) ?></p>

    <h2 class="h5 mt-4"><?= e(__('privacy.cookies_title')) ?></h2>
    <p><?= e(__('privacy.cookies_intro')) ?></p>
    <ul>
        <?php foreach (['session', 'owner', 'locale'] as $cookie): ?>
            <li><?= e(__('privacy.cookies.' . $cookie)) ?></li>
        <?php endforeach; ?>
    </ul>

    <h2 class="h5 mt-4"><?= e(__('privacy.signature_title')) ?></h2>
    <p><?= e(__('privacy.signature_text')) ?></p>

    <h2 class="h5 mt-4"><?= e(__('privacy.tracking_title')) ?></h2>
    <p><?= e(__('notices.no_tracking')) ?></p>

    <h2 class="h5 mt-4"><?= e(__('privacy.export_title')) ?></h2>
    <p><?= e(__('privacy.export_text')) ?></p>
</div>
