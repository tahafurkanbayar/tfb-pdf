<?php
/**
 * Araç kartları: masaüstünde 3–4, tablette 2, mobilde 1 sütun. Her araç kendi renkli ikon kutusuyla.
 * Sunucuda bulunmayan araçlar soluk, "Devre dışı" rozetli ve gereken bileşeni söyleyen tooltip'le gösterilir.
 *
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var list<array{slug: string, icon: string, available: bool, requires: ?string}> $tools
 */
?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-3">
    <?php foreach ($tools as $tool): ?>
        <?php
        $disabledHint = !$tool['available'] && $tool['requires'] !== null
            ? __('tools.disabled_tooltip', ['tool' => __('tools.requirements.' . $tool['requires'])])
            : null;
        ?>
        <div class="col">
            <a class="tool-card card h-100 text-decoration-none<?= $tool['available'] ? '' : ' tool-card-disabled' ?>"
               href="<?= e($url->page('/tools/' . $tool['slug'])) ?>"
               <?php if ($disabledHint !== null): ?>data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= e($disabledHint) ?>"<?php endif; ?>>
                <div class="card-body d-flex flex-column gap-3 p-4">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <span class="tool-icon tool-icon-<?= e($tool['slug']) ?>" aria-hidden="true"><?= $view->icon($tool['icon']) ?></span>
                        <?php if ($tool['available']): ?>
                            <span class="tool-card-arrow" aria-hidden="true"><?= $view->icon('chevron-right') ?></span>
                        <?php else: ?>
                            <span class="badge badge-soft"><?= e(__('tools.disabled_badge')) ?></span>
                        <?php endif; ?>
                    </div>
                    <span>
                        <span class="tool-card-title d-block fw-semibold text-body mb-1"><?= e(__('pdf.' . $tool['slug'])) ?></span>
                        <span class="d-block small text-body-secondary"><?= e(__('tools.descriptions.' . $tool['slug'])) ?></span>
                        <?php if ($disabledHint !== null): ?>
                            <span class="visually-hidden" data-tool-requirement><?= e($disabledHint) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
