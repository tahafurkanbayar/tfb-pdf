<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\DocumentService;
use App\Services\ToolCatalog;

final class HomeController extends Controller
{
    /**
     * "/" → tercih edilen dile yönlendir (cookie → tarayıcı dili → tr).
     */
    public function root(Request $request): Response
    {
        return Response::redirect($this->url()->page('/', [], (string) $request->attribute('locale')));
    }

    public function index(Request $request): Response
    {
        $owner = $this->owner()->hash();
        $documents = $this->service(DocumentService::class);

        return $this->view('pages/home', [
            'tools' => $this->service(ToolCatalog::class)->all(),
            'recentDocuments' => $documents->listForOwner($owner, 5),
            'recentOperations' => $documents->recentOperations($owner, 6),
            'expiringSoon' => $documents->expiringSoon($owner),
            'storageUsed' => $documents->storageUsage($owner),
            'storageLimit' => (int) $this->config()->get('limits.max_storage_per_owner'),
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view('pages/about');
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy');
    }
}
