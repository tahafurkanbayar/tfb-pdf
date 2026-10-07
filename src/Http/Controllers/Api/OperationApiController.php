<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OperationPresenter;
use App\Http\Request;
use App\Http\Response;
use App\Services\Operations\OperationResult;
use App\Services\Operations\PdfToolService;

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
}
