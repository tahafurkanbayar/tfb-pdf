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
