<?php
/**
 * İmzalayan sayfası.
 *
 * @var App\Core\View $view
 * @var App\Core\Url $url
 * @var string $token
 * @var array<string, mixed> $signer
 * @var App\Domain\Document $document
 * @var list<array<string, mixed>> $fields
 * @var array<string, mixed> $request
 */
$view->extend('layouts/app');
$view->section('title', __('signature.sign_title'));
$view->section('robots', 'noindex, nofollow');
$view->section('i18n', 'signature,preview,common');
$view->section('scripts', '<script type="module" src="' . e($url->asset('js/pages/sign.js')) . '"></script>');

$active = $request['status'] === 'pending' && in_array($signer['status'], ['pending', 'viewed'], true);
$fieldData = array_map(static fn (array $f): array => [
    'page' => (int) $f['page_number'], 'x' => (float) $f['pos_x'], 'y' => (float) $f['pos_y'], 'w' => (float) $f['width'], 'h' => (float) $f['height'],
], $fields);
?>
<div class="container py-4 page-narrow" data-sign data-token="<?= e($token) ?>" data-fields="<?= e((string) json_encode($fieldData)) ?>">
    <h1 class="h3"><?= $view->icon('pen') ?> <?= e(__('signature.sign_title')) ?></h1>
    <p class="lead"><?= e(__('signature.sign_intro', ['document' => $document->originalName])) ?></p>

    <div class="alert alert-warning small d-flex gap-2" role="note">
        <?= $view->icon('exclamation-triangle', 'flex-shrink-0 mt-1') ?><span><?= e(__('signature.not_qes')) ?></span>
    </div>

    <?php if (($request['message'] ?? null) !== null): ?>
        <figure class="card card-body mb-4">
            <figcaption class="small text-body-secondary mb-1"><?= e(__('signature.sender_message')) ?></figcaption>
            <blockquote class="mb-0"><?= nl2br(e((string) $request['message'])) ?></blockquote>
        </figure>
    <?php endif; ?>

    <?php if ($request['status'] === 'completed'): ?>
        <div class="alert alert-success" role="status">
            <p class="mb-2"><?= e(__('signature.completed_done')) ?></p>
            <a class="btn btn-success" href="<?= e($url->to('/api/sign/' . $token . '/final')) ?>"><?= $view->icon('download') ?> <?= e(__('signature.download_final')) ?></a>
            <?php if ($request['final_sha256'] !== null): ?>
                <p class="small mt-2 mb-0"><code class="hash"><?= e(__('signature.final_hash', ['hash' => (string) $request['final_sha256']])) ?></code></p>
            <?php endif; ?>
        </div>
    <?php elseif ($signer['status'] === 'signed'): ?>
        <div class="alert alert-info" role="status"><?= e(__('signature.waiting_others')) ?></div>
    <?php elseif ($signer['status'] === 'declined'): ?>
        <div class="alert alert-secondary" role="status"><?= e(__('signature.declined_done')) ?></div>
    <?php elseif (!$active): ?>
        <div class="alert alert-secondary" role="status"><?= e(__('signature.state_not_pending')) ?> (<?= e(__('signature.request_status.' . $request['status'])) ?>)</div>
    <?php endif; ?>

    <section class="mb-4" aria-labelledby="doc-heading">
        <h2 id="doc-heading" class="h5"><?= e(__('preview.title')) ?></h2>
        <?php if ($active): ?><p class="small text-body-secondary"><?= e(__('signature.your_fields')) ?></p><?php endif; ?>
        <div class="sign-pages" data-pages aria-live="polite"></div>
    </section>

    <?php if ($active): ?>
        <form class="card" data-sign-form novalidate>
            <div class="card-body">
                <div class="form-check mb-3 p-3 border rounded bg-body-tertiary">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="consent" name="consent" required aria-describedby="consent-text">
                    <label class="form-check-label fw-semibold" for="consent"><?= e(__('signature.consent_label')) ?></label>
                    <p class="small mb-0 mt-2" id="consent-text"><?= e(__('signature.consent_text')) ?></p>
                </div>

                <fieldset class="mb-3">
                    <legend class="form-label fs-6"><?= e(__('signature.method_label')) ?></legend>
                    <?php foreach (['drawn', 'typed'] as $method): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type" id="method-<?= e($method) ?>" value="<?= e($method) ?>"<?= $method === 'drawn' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="method-<?= e($method) ?>"><?= e(__('signature.methods.' . $method)) ?></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <div data-method-drawn>
                    <p class="small text-body-secondary" id="pad-help"><?= e(__('signature.draw_help')) ?></p>
                    <canvas class="signature-pad" width="600" height="200" data-pad role="img" aria-label="<?= e(__('signature.draw_area')) ?>" aria-describedby="pad-help"></canvas>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-clear><?= e(__('signature.clear_drawing')) ?></button>
                </div>
                <div data-method-typed hidden>
                    <label class="form-label" for="typed-name"><?= e(__('signature.typed_label')) ?></label>
                    <input class="form-control signature-typed" id="typed-name" maxlength="150" value="<?= e((string) $signer['name']) ?>">
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg" data-submit><?= $view->icon('pen') ?> <?= e(__('signature.sign_button')) ?></button>
                </div>
                <div class="mt-3" data-status role="status" aria-live="polite"></div>

                <details class="mt-4">
                    <summary class="small"><?= e(__('signature.decline_button')) ?></summary>
                    <label class="form-label small mt-2" for="decline-reason"><?= e(__('signature.decline_reason')) ?></label>
                    <textarea class="form-control form-control-sm" id="decline-reason" rows="2" maxlength="500"></textarea>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-decline><?= e(__('signature.decline_button')) ?></button>
                </details>
            </div>
        </form>
    <?php endif; ?>
</div>
