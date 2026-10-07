<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\FileResponse;
use App\Http\Presenters\DocumentPresenter;
use App\Http\Request;
use App\Http\Response;
use App\Services\DocumentService;

final class DocumentApiController extends Controller
{
    private function documents(): DocumentService
    {
        return $this->service(DocumentService::class);
    }

    private function presenter(): DocumentPresenter
    {
        return new DocumentPresenter($this->url());
    }

    /**
     * POST /api/documents  (multipart: file, expiry?, office?)
     */
    public function store(Request $request): Response
    {
        $files = $request->files('file');
        if ($files === []) {
            throw new ValidationException('No file field', 'upload.no_file');
        }

        $expiry = $request->input('expiry');
        $document = $this->documents()->upload(
            $files[0],
            $this->owner()->ensure(),
            is_string($expiry) && $expiry !== '' ? $expiry : null,
            $request->input('office') === '1'
        );

        return $this->json([
            'message' => __('upload.success'),
            'document' => $this->presenter()->document($document, $this->documents()->versions($document), $this->documents()->expiry($document)),
        ], 201);
    }

    /**
     * GET /api/documents
     */
    public function index(Request $request): Response
    {
        $rows = $this->documents()->listForOwner($this->owner()->hash());

        return $this->json([
            'documents' => array_map(fn (array $row): array => [
                'id' => $row['public_id'],
                'name' => $row['original_name'],
                'versions' => (int) $row['version_count'],
                'size' => (int) $row['total_size'],
                'updated_at' => \App\Support\DateFormatter::iso($row['updated_at']),
                'url' => $this->url()->page('/documents/' . $row['public_id']),
            ], $rows),
        ]);
    }

    /**
     * GET /api/documents/{id}
     */
    public function show(Request $request, array $params): Response
    {
        $document = $this->documents()->get($params['id'], $this->owner()->hash());

        return $this->json([
            'document' => $this->presenter()->document($document, $this->documents()->versions($document), $this->documents()->expiry($document)),
        ]);
    }

    /**
     * DELETE /api/documents/{id}
     */
    public function destroy(Request $request, array $params): Response
    {
        $document = $this->documents()->get($params['id'], $this->owner()->hash());
        $this->documents()->delete($document);

        return $this->json(['message' => __('documents.deleted'), 'redirect' => $this->url()->page('/documents')]);
    }

    /**
     * PUT /api/documents/{id}/expiry  {policy}
     */
    public function expiry(Request $request, array $params): Response
    {
        $document = $this->documents()->get($params['id'], $this->owner()->hash());
        $policy = $request->input('policy');
        $expiresAt = $this->documents()->setExpiry($document, is_string($policy) ? $policy : '');

        return $this->json([
            'message' => __('documents.expiry_saved'),
            'expiry' => ['policy' => $policy, 'expires_at' => \App\Support\DateFormatter::iso($expiresAt)],
        ]);
    }

    /**
     * GET /api/documents/{id}/verify — tüm sürümlerin SHA-256 bütünlük kontrolü (salt okunur)
     */
    public function verify(Request $request, array $params): Response
    {
        $document = $this->documents()->get($params['id'], $this->owner()->hash());
        $results = $this->documents()->verifyIntegrity($document);
        $allOk = array_filter($results, static fn (array $r): bool => $r['status'] !== 'ok') === [];

        return $this->json([
            'intact' => $allOk,
            'message' => $allOk ? __('hash.verify_ok') : __('hash.verify_failed'),
            'results' => $results,
        ]);
    }

    /**
     * GET /api/documents/{id}/versions/{number}/download[?inline=1]
     *
     * inline=1: tarayıcı içi önizleme (PDF.js) — Range istekleri desteklenir, audit kaydı oluşturulmaz.
     * Aksi halde dosya indirilir ve "download" olayı kaydedilir.
     */
    public function download(Request $request, array $params): Response
    {
        $document = $this->documents()->get($params['id'], $this->owner()->hash());
        $version = $this->documents()->version($document, (int) $params['number']);
        $inline = $request->query('inline') === '1';

        $path = $this->documents()->pathForDownload($document, $version, audit: !$inline);

        return new FileResponse(
            $path,
            $this->documents()->downloadName($document, $version),
            $version->mimeType,
            $inline,
            $inline ? $request->header('range') : null
        );
    }
}
