<?php
/**
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var list<array{slug: string, icon: string, available: bool, requires: ?string}> $tools
 */
?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
    <?php foreach ($tools as $tool): ?>
        <div class="col">
            <a class="tool-card card h-100 text-decoration-none<?= $tool['available'] ? '' : ' tool-card-disabled' ?>"
               href="<?= e($url->page('/tools/' . $tool['slug'])) ?>">
                <div class="card-body d-flex gap-3">
                    <span class="tool-icon tool-icon-<?= e($tool['slug']) ?>" aria-hidden="true"><?= $view->icon($tool['icon']) ?></span>
                    <span>
                        <span class="d-block fw-semibold text-body"><?= e(__('pdf.' . $tool['slug'])) ?></span>
                        <span class="d-block small text-body-secondary"><?= e(__('tools.descriptions.' . $tool['slug'])) ?></span>
                        <?php if (!$tool['available']): ?>
                            <span class="badge text-bg-light border mt-2"><?= e(__('tools.unavailable_badge')) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
