<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\DocumentService;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $owner = $this->owner()->hash();
        $documents = $this->service(DocumentService::class);

        return $this->view('pages/documents/index', [
            'documents' => $documents->listForOwner($owner),
            'storageUsed' => $documents->storageUsage($owner),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $service = $this->service(DocumentService::class);
        $document = $service->get($params['id'], $this->owner()->hash());

        return $this->view('pages/documents/show', [
            'document' => $document,
            'versions' => $service->versions($document),
            'expiry' => $service->expiry($document),
            'operations' => $service->operationsForDocument($document),
            'auditEvents' => $this->service(AuditService::class)->forDocument($document->publicId, 50),
        ]);
    }
}
