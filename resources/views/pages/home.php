<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 */
$view->extend('layouts/app');
$view->section('description', __('home.meta_description'));
?>
<section class="hero py-5">
    <div class="container text-center">
        <h1 class="display-6 fw-bold mb-3"><?= e(__('home.title')) ?></h1>
        <p class="lead text-body-secondary mx-auto hero-lead"><?= e(__('home.subtitle')) ?></p>
    </div>
</section>

<section id="tools" class="container" aria-labelledby="tools-heading">
    <h2 id="tools-heading" class="h4 mb-3"><?= e(__('tools.heading')) ?></h2>
    <?= $view->partial('partials/tool-grid', ['tools' => (new App\Services\ToolCatalog())->all()]) ?>
</section>
