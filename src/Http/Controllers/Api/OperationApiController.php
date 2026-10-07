<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\FileResponse;
use App\Http\Presenters\OperationPresenter;
use App\Http\Request;
use App\Http\Response;
use App\Services\Operations\OperationArchiveService;
use App\Services\Operations\OperationResult;
use App\Services\Operations\PdfToolService;
use App\Services\StorageService;

/**
 * POST /api/operations/{type} — JSON gövdeli PDF işlemleri.
 */
final class OperationApiController extends Controller
{
    private function tools(): PdfToolService
    {
        return $this->service(PdfToolService::class);
    }

    private function respond(OperationResult $result): Response
    {
        return $this->json((new OperationPresenter($this->url()))->result($result), $result->status === 'completed' ? 201 : 200);
    }

    private function requireOwner(): string
    {
        $owner = $this->owner()->hash();
        if ($owner === null) {
            // Sahip kimliği yoksa hiçbir belgeye erişemez
            throw new \App\Exceptions\NotFoundException('No owner');
        }

        return $owner;
    }

    public function merge(Request $request): Response
    {
        $items = $request->input('items');
        if (!is_array($items)) {
            throw new ValidationException('items missing', 'operations.merge_min_files');
        }

        return $this->respond($this->tools()->merge($this->requireOwner(), array_values(array_filter($items, 'is_array'))));
    }

    public function split(Request $request): Response
    {
        return $this->respond($this->tools()->split(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            self::versionInput($request),
            (string) $request->input('mode', ''),
            (string) $request->input('ranges', '')
        ));
    }

    public function reorder(Request $request): Response
    {
        $order = $request->input('order');

        return $this->respond($this->tools()->reorder(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            self::versionInput($request),
            is_array($order) ? array_values(array_filter($order, 'is_numeric')) : []
        ));
    }

    public function rotate(Request $request): Response
    {
        $rotations = $request->input('rotations');

        return $this->respond($this->tools()->rotate(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            self::versionInput($request),
            is_array($rotations) ? $rotations : []
        ));
    }

    public function compress(Request $request): Response
    {
        return $this->respond($this->tools()->compress(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            self::versionInput($request),
            (string) $request->input('level', 'medium')
        ));
    }

    public function watermark(Request $request): Response
    {
        $settings = $request->input('settings');

        return $this->respond($this->tools()->watermark(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            self::versionInput($request),
            is_array($settings) ? $settings : [],
            (string) $request->input('pages', '')
        ));
    }

    /**
     * multipart: document, version, boxes (JSON), page_<n> (tarayıcı görüntüleri; sunucu render yoksa)
     */
    public function redact(Request $request): Response
    {
        $images = [];
        foreach (array_keys($request->files) as $field) {
            if (preg_match('/^page_(\d{1,5})$/', (string) $field, $m) && ($file = $request->files((string) $field)[0] ?? null) !== null) {
                $images[(int) $m[1]] = $file;
            }
        }

        return $this->respond($this->tools()->redact(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            self::versionInput($request),
            $request->input('boxes'),
            $images
        ));
    }

    /**
     * GET /api/operations/{id}/download — işlemin tüm çıktıları tek ZIP.
     */
    public function download(Request $request, array $params): Response
    {
        $archives = $this->service(OperationArchiveService::class);
        $zip = $archives->build($params['id'], $this->requireOwner());

        // Geçici ZIP yanıt gönderildikten sonra silinir
        $storage = $this->service(StorageService::class);
        register_shutdown_function(static fn () => $storage->deleteTempDirectory(dirname($zip['path'])));

        return new FileResponse($zip['path'], $zip['name'], 'application/zip');
    }

    private static function versionInput(Request $request): ?int
    {
        $version = $request->input('version');

        return is_numeric($version) ? (int) $version : null;
    }
}
