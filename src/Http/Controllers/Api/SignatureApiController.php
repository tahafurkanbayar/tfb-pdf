<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\FileResponse;
use App\Http\Request;
use App\Http\Response;
use App\Services\DocumentService;
use App\Services\SignatureService;

/**
 * İmza talepleri. Sahip uçları owner cookie ile, imzalayan uçları davet token'ı ile yetkilendirilir.
 */
final class SignatureApiController extends Controller
{
    private function signatures(): SignatureService
    {
        return $this->service(SignatureService::class);
    }

    private function requireOwner(): string
    {
        $owner = $this->owner()->hash();
        if ($owner === null) {
            throw new NotFoundException('No owner');
        }

        return $owner;
    }

    private function trusted(Request $request): array
    {
        $proxies = $this->config()->get('app.trusted_proxies');

        return [$request->ip($proxies), $request->userAgent()];
    }

    // --- Belge sahibi

    /**
     * POST /api/signatures  {document, version?, message?, signers: [{name, email?, fields: [{page,x,y,w,h}]}]}
     */
    public function store(Request $request): Response
    {
        $version = $request->input('version');
        $signers = $request->input('signers');
        $result = $this->signatures()->create(
            $this->requireOwner(),
            (string) $request->input('document', ''),
            is_numeric($version) ? (int) $version : null,
            is_array($signers) ? array_values(array_filter($signers, 'is_array')) : [],
            (string) $request->input('message', ''),
            (string) $request->attribute('locale', 'tr')
        );

        return $this->json(['message' => __('signature.created')] + $result + [
            'expires_note' => __('signature.expires_in', ['days' => (int) $this->config()->get('signing.invite_ttl_days', 14)]),
        ], 201);
    }

    public function cancel(Request $request, array $params): Response
    {
        $this->signatures()->cancel($this->requireOwner(), $params['id']);

        return $this->json(['message' => __('signature.cancelled')]);
    }

    public function regenerate(Request $request, array $params): Response
    {
        $url = $this->signatures()->regenerateLink($this->requireOwner(), $params['id'], (int) $params['signer'], (string) $request->attribute('locale', 'tr'));

        return $this->json(['message' => __('signature.new_link_created'), 'url' => $url]);
    }

    public function finalize(Request $request, array $params): Response
    {
        $completed = $this->signatures()->retryFinalize($this->requireOwner(), $params['id']);

        return $this->json(['completed' => $completed, 'message' => $completed ? __('signature.completed_done') : __('signature.waiting_others')]);
    }

    // --- İmzalayan (token)

    /**
     * GET /api/sign/{token}/document — imzalanacak sürüm (önizleme; Range destekli)
     */
    public function document(Request $request, array $params): Response
    {
        $context = $this->signatures()->signerContext($params['token']);
        $documents = $this->service(DocumentService::class);
        $version = null;
        foreach ($documents->versions($context['document']) as $v) {
            if ($v->id === (int) $context['signer']['version_id']) {
                $version = $v;
            }
        }
        if ($version === null) {
            throw new NotFoundException('Version gone');
        }

        return new FileResponse(
            $documents->pathForDownload($context['document'], $version, audit: false),
            $documents->downloadName($context['document'], $version),
            'application/pdf',
            true,
            $request->header('range')
        );
    }

    /**
     * GET /api/sign/{token}/final — tüm imzalar tamamlandıktan sonra imzalı PDF
     */
    public function final(Request $request, array $params): Response
    {
        [$document, $version] = $this->signatures()->finalVersionForSigner($params['token']);
        $documents = $this->service(DocumentService::class);

        return new FileResponse(
            $documents->pathForDownload($document, $version, audit: true, actor: 'signer'),
            $documents->downloadName($document, $version),
            'application/pdf'
        );
    }

    /**
     * POST /api/sign/{token}  {consent: bool, type: drawn|typed, signature: string}
     */
    public function sign(Request $request, array $params): Response
    {
        [$ip, $ua] = $this->trusted($request);
        $result = $this->signatures()->sign(
            $params['token'],
            filter_var($request->input('consent', false), FILTER_VALIDATE_BOOL),
            (string) $request->input('type', ''),
            (string) $request->input('signature', ''),
            $ip,
            $ua,
            (string) $request->attribute('locale', 'tr')
        );

        return $this->json([
            'completed' => $result['completed'],
            'message' => $result['completed'] ? __('signature.completed_done') : __('signature.signed_done'),
        ]);
    }

    public function decline(Request $request, array $params): Response
    {
        [$ip, $ua] = $this->trusted($request);
        $this->signatures()->decline($params['token'], (string) $request->input('reason', ''), $ip, $ua);

        return $this->json(['message' => __('signature.declined_done')]);
    }
}
