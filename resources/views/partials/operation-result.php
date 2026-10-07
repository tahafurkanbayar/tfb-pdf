<?php
/**
 * İşlem sonucu alanı. JS (tools/common.js) doldurur; başlangıçta gizlidir.
 *
 * @var App\Core\View $view
 */
?>
<section class="card border-success-subtle mt-4" data-result hidden aria-labelledby="result-heading" tabindex="-1">
    <div class="card-body">
        <h2 id="result-heading" class="h5 d-flex align-items-center gap-2" data-result-title>
            <?= $view->icon('check-circle', 'text-success') ?> <span data-result-message></span>
        </h2>
        <ul class="list-unstyled mb-3" data-result-outputs></ul>
        <div class="d-flex flex-wrap gap-2 mb-3" data-result-actions>
            <a class="btn btn-primary" data-result-download hidden><?= $view->icon('download') ?> <?= e(__('operations.download_result')) ?></a>
            <a class="btn btn-outline-secondary" data-result-document hidden><?= $view->icon('box-arrow-up-right') ?> <?= e(__('operations.open_document')) ?></a>
        </div>
        <div class="alert alert-warning small mb-0" data-result-warnings hidden>
            <p class="fw-semibold mb-1"><?= e(__('operations.warnings_title')) ?></p>
            <ul class="mb-0"></ul>
        </div>
    </div>
</section>
