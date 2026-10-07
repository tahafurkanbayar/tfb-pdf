<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Services\Operations\PdfToolService;
use App\Services\ToolCatalog;
use App\Tools\Capabilities;

final class ToolController extends Controller
{
    /**
     * GET /{locale}/tools/{tool}[?document=<id>]
     */
    public function show(Request $request, array $params): Response
    {
        $slug = $params['tool'];
        $template = 'tools/' . $slug;
        if (!ToolCatalog::exists($slug) || !is_file(APP_ROOT . '/resources/views/' . $template . '.php')) {
            throw new NotFoundException('Unknown tool: ' . $slug);
        }

        $tool = null;
        foreach ($this->service(ToolCatalog::class)->all() as $item) {
            if ($item['slug'] === $slug) {
                $tool = $item;
            }
        }

        return $this->view($template, [
            'tool' => $tool,
            'preselected' => $this->preselected($request),
            'capabilities' => $this->service(Capabilities::class)->toArray(),
        ]);
    }

    /**
     * ?document=<id> ile gelen belge (yalnızca sahibi ise).
     *
     * @return array{id: string, name: string, version: int, pages: ?int}|null
     */
    private function preselected(Request $request): ?array
    {
        $id = $request->query('document');
        $owner = $this->owner()->hash();
        if (!is_string($id) || $owner === null) {
            return null;
        }

        try {
            $number = $request->query('version');
            [$document, $version] = $this->service(PdfToolService::class)->resolveInput($owner, $id, is_numeric($number) ? (int) $number : null);
        } catch (AppException) {
            return null;
        }

        $label = $version->isOriginal() ? __('documents.original') : __('documents.version_n', ['number' => $version->versionNumber]);

        return ['id' => $document->publicId, 'name' => $document->originalName . ' — ' . $label, 'version' => $version->versionNumber, 'pages' => $version->pageCount];
    }
}
