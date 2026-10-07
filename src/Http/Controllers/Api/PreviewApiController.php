<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\FileResponse;
use App\Http\Request;
use App\Http\Response;
use App\Services\DocumentService;
use App\Services\ThumbnailService;

/**
 * Sayfa küçük resmi önbelleği. Yalnızca belge sahibi erişebilir.
 */
final class PreviewApiController extends Controller
{
    /**
     * GET /api/documents/{id}/versions/{number}/previews
     */
    public function index(Request $request, array $params): Response
    {
        [$document, $version] = $this->resolve($params);
        $thumbnails = $this->service(ThumbnailService::class);

        return $this->json([
            'pages' => $version->pageCount,
            'cached' => $thumbnails->cachedPages($document, $version),
            'cache_enabled' => ThumbnailService::available(),
            'max_dimension' => ThumbnailService::MAX_DIMENSION,
        ]);
    }

    /**
     * GET /api/documents/{id}/versions/{number}/previews/{page}
     */
    public function show(Request $request, array $params): Response
    {
        [$document, $version] = $this->resolve($params);
        $path = $this->service(ThumbnailService::class)->path($document, $version, (int) $params['page']);

        $response = new FileResponse($path, 'page-' . (int) $params['page'] . '.jpg', 'image/jpeg', true);
        // Sürümler değişmez; küçük resim güvenle önbelleğe alınabilir (yalnızca bu tarayıcı)
        $response->setHeader('Cache-Control', 'private, max-age=604800, immutable');

        return $response;
    }

    /**
     * PUT /api/documents/{id}/versions/{number}/previews/{page}  (gövde: görsel)
     */
    public function store(Request $request, array $params): Response
    {
        [$document, $version] = $this->resolve($params);
        $this->service(ThumbnailService::class)->store($document, $version, (int) $params['page'], $request->rawBody());

        return $this->json([], 201);
    }

    /**
     * @return array{\App\Domain\Document, \App\Domain\DocumentVersion}
     */
    private function resolve(array $params): array
    {
        $documents = $this->service(DocumentService::class);
        $document = $documents->get($params['id'], $this->owner()->hash());

        return [$document, $documents->version($document, (int) $params['number'])];
    }
}
