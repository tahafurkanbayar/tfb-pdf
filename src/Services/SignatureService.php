<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Url;
use App\Domain\Document;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\I18n\Translator;
use App\Pdf\Signature\SignaturePdfBuilder;
use App\Repositories\DocumentRepository;
use App\Repositories\SignatureRepository;
use App\Security\Hmac;
use App\Services\Operations\OperationOutput;
use App\Services\Operations\OperationService;
use App\Services\Operations\PdfToolService;
use App\Services\Operations\ProcessResult;

/**
 * Basit imza akışı (spec §26): imza alanı, imzalayan, davet, onay (consent) kaydı, imza olayları,
 * final PDF + SHA-256, audit.
 *
 * Bu bir BASİT elektronik imza kaydıdır. Nitelikli elektronik imza (QES), eIDAS nitelikli imza,
 * düzenlenmiş e-imza veya resmi/devlet kimlik doğrulaması DEĞİLDİR ve öyle sunulmaz.
 * İmzalayanın kimliği doğrulanmaz; davet bağlantısına sahip olan kişi imzalayabilir.
 */
final class SignatureService
{
    public const CONSENT_VERSION = '2026-10-v1';

    public const MAX_SIGNERS = 5;

    public const MAX_FIELDS_PER_SIGNER = 20;

    public function __construct(
        private readonly Database $db,
        private readonly SignatureRepository $signatures,
        private readonly DocumentRepository $documents,
        private readonly DocumentService $documentService,
        private readonly PdfToolService $tools,
        private readonly OperationService $operations,
        private readonly StorageService $storage,
        private readonly AuditService $audit,
        private readonly MailService $mail,
        private readonly Hmac $hmac,
        private readonly Url $url,
        private readonly Translator $translator,
        private readonly Logger $logger,
        private readonly int $inviteTtlDays,
    ) {
    }

    // ------------------------------------------------------------------ Belge sahibi

    /**
     * @param list<array{name?: mixed, email?: mixed, fields?: mixed}> $signers
     * @return array{request: string, links: list<array{name: string, email: ?string, url: string, emailed: bool}>}
     */
    public function create(string $ownerHash, string $documentId, ?int $versionNumber, array $signers, string $message, string $locale): array
    {
        [$document, $version] = $this->tools->resolveInput($ownerHash, $documentId, $versionNumber);
        $pages = (int) $version->pageCount;

        $message = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message));
        if (mb_strlen($message) > 1000) {
            throw new ValidationException('Message too long', 'signature.message_too_long');
        }
        if ($signers === [] || count($signers) > self::MAX_SIGNERS) {
            throw new ValidationException('Signer count', 'signature.signer_count', ['max' => self::MAX_SIGNERS]);
        }

        $clean = [];
        foreach ($signers as $signer) {
            $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($signer['name'] ?? '')));
            $email = trim((string) ($signer['email'] ?? ''));
            if ($name === '' || mb_strlen($name) > 150) {
                throw new ValidationException('Signer name', 'signature.name_invalid');
            }
            if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254)) {
                throw new ValidationException('Signer email', 'signature.email_invalid');
            }
            $fields = is_array($signer['fields'] ?? null) ? $signer['fields'] : [];
            if ($fields === [] || count($fields) > self::MAX_FIELDS_PER_SIGNER) {
                throw new ValidationException('Signer fields', 'signature.fields_required', ['name' => $name]);
            }
            $clean[] = ['name' => $name, 'email' => $email === '' ? null : $email, 'fields' => array_map(fn (mixed $f): array => self::field($f, $pages), $fields)];
        }

        $publicId = Hmac::publicId();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $this->inviteTtlDays * 86400);
        $tokens = [];

        $this->db->transaction(function () use ($publicId, $document, $version, $ownerHash, $message, $expiresAt, $clean, $locale, &$tokens): void {
            $requestId = $this->signatures->createRequest($publicId, $document->id, $version->id, $ownerHash, $message === '' ? null : $message, $version->sha256, $expiresAt);
            $this->signatures->addEvent($requestId, null, 'created', metadata: ['signers' => count($clean), 'version' => $version->versionNumber, 'locale' => $locale]);

            foreach ($clean as $signer) {
                $token = Hmac::randomToken(32);
                $signerId = $this->signatures->createSigner($requestId, $signer['name'], $signer['email'], $this->hmac->hash('signer', $token));
                foreach ($signer['fields'] as $field) {
                    $this->signatures->createField($requestId, $signerId, $field);
                }
                $this->signatures->addEvent($requestId, $signerId, 'invited');
                $tokens[] = ['token' => $token, 'name' => $signer['name'], 'email' => $signer['email']];
            }

            $this->audit->record(
                'signature_created',
                ownerHash: $ownerHash,
                documentPublicId: $document->publicId,
                inputHash: $version->sha256,
                metadata: ['request' => $publicId, 'signers' => count($clean), 'version' => $version->versionNumber]
            );
        });

        // E-posta transaction dışında: SMTP gecikmesi / hatası kaydı etkilemez
        $links = [];
        foreach ($tokens as $item) {
            $link = $this->url->absolute('/' . $locale . '/sign/' . $item['token']);
            $emailed = $item['email'] !== null && $this->mail->send(
                $item['email'],
                $this->translator->get('signature.email.subject', ['document' => $document->originalName], $locale),
                $this->translator->get('signature.email.body', [
                    'name' => $item['name'],
                    'document' => $document->originalName,
                    'link' => $link,
                    'message' => $message === '' ? '-' : $message,
                    'days' => $this->inviteTtlDays,
                ], $locale)
            );
            $links[] = ['name' => $item['name'], 'email' => $item['email'], 'url' => $link, 'emailed' => $emailed];
        }

        return ['request' => $publicId, 'links' => $links];
    }

    /**
     * @return array{page: int, x: float, y: float, w: float, h: float}
     */
    private static function field(mixed $f, int $pages): array
    {
        if (!is_array($f)) {
            throw new ValidationException('Field', 'signature.field_invalid');
        }
        $page = filter_var($f['page'] ?? null, FILTER_VALIDATE_INT);
        $values = [];
        foreach (['x', 'y', 'w', 'h'] as $k) {
            $v = $f[$k] ?? null;
            if (!is_numeric($v)) {
                throw new ValidationException('Field value', 'signature.field_invalid');
            }
            $values[$k] = (float) $v;
        }
        if ($page === false || $page < 1 || $page > $pages
            || $values['x'] < 0 || $values['y'] < 0 || $values['w'] < 0.02 || $values['h'] < 0.01
            || $values['x'] + $values['w'] > 1.0001 || $values['y'] + $values['h'] > 1.0001) {
            throw new ValidationException('Field bounds', 'signature.field_invalid');
        }

        return ['page' => $page] + $values;
    }

    /**
     * Belge sahibinin bir belge için talepleri (imzalayanlar ve olaylarla).
     *
     * @return list<array<string, mixed>>
     */
    public function forDocument(Document $document): array
    {
        $result = [];
        foreach ($this->signatures->requestsForDocument($document->id) as $request) {
            $request = $this->refreshExpiry($request);
            $result[] = $request + [
                'signers' => $this->signatures->signers((int) $request['id']),
                'events' => $this->signatures->events((int) $request['id']),
            ];
        }

        return $result;
    }

    public function cancel(string $ownerHash, string $requestPublicId): void
    {
        $request = $this->ownerRequest($ownerHash, $requestPublicId);
        if ($request['status'] !== SignatureRepository::PENDING) {
            throw new ValidationException('Not pending', 'signature.not_pending');
        }
        $document = $this->documents->findById((int) $request['document_id']);

        $this->db->transaction(function () use ($request, $ownerHash, $document): void {
            $this->signatures->setRequestStatus((int) $request['id'], SignatureRepository::CANCELLED);
            $this->signatures->addEvent((int) $request['id'], null, 'cancelled');
            $this->audit->record('signature_cancelled', ownerHash: $ownerHash, documentPublicId: $document?->publicId, metadata: ['request' => $request['public_id']]);
        });
    }

    /**
     * Kaybolan bağlantı için yeni token; eskisi geçersiz olur.
     */
    public function regenerateLink(string $ownerHash, string $requestPublicId, int $signerId, string $locale): string
    {
        $request = $this->ownerRequest($ownerHash, $requestPublicId);
        if ($request['status'] !== SignatureRepository::PENDING) {
            throw new ValidationException('Not pending', 'signature.not_pending');
        }
        $signer = null;
        foreach ($this->signatures->signers((int) $request['id']) as $row) {
            if ((int) $row['id'] === $signerId) {
                $signer = $row;
            }
        }
        if ($signer === null || in_array($signer['status'], [SignatureRepository::SIGNER_SIGNED, SignatureRepository::SIGNER_DECLINED], true)) {
            throw new NotFoundException('Signer not found or already finished');
        }

        $token = Hmac::randomToken(32);
        $this->db->transaction(function () use ($signer, $token, $request): void {
            $this->signatures->updateSigner((int) $signer['id'], ['token_hash' => $this->hmac->hash('signer', $token)]);
            $this->signatures->addEvent((int) $request['id'], (int) $signer['id'], 'invited', metadata: ['regenerated' => true]);
        });

        return $this->url->absolute('/' . $locale . '/sign/' . $token);
    }

    /**
     * Tüm imzalar tamamsa final PDF'i (yeniden) oluşturmayı dener (ör. önceki deneme başarısız olduysa).
     */
    public function retryFinalize(string $ownerHash, string $requestPublicId): bool
    {
        $request = $this->ownerRequest($ownerHash, $requestPublicId);

        return $this->finalizeIfComplete((int) $request['id']);
    }

    // ------------------------------------------------------------------ İmzalayan

    /**
     * Token ile imzalayan bağlamı. Geçersiz token → 404 (varlık sızdırılmaz).
     *
     * @return array<string, mixed>
     */
    public function signerContext(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new NotFoundException('Invalid token format');
        }
        $signer = $this->signatures->findSignerByTokenHash($this->hmac->hash('signer', $token));
        if ($signer === null) {
            throw new NotFoundException('Unknown signer token');
        }

        $request = $this->refreshExpiry((array) $this->signatures->findRequest((int) $signer['request_id']));
        $signer['request_status'] = $request['status'];
        $document = $this->documents->findById((int) $signer['document_id']);
        if ($document === null) {
            throw new NotFoundException('Document gone');
        }

        $fields = array_values(array_filter(
            $this->signatures->fields((int) $signer['request_id']),
            static fn (array $f): bool => (int) $f['signer_id'] === (int) $signer['id']
        ));

        return ['signer' => $signer, 'document' => $document, 'fields' => $fields, 'request' => $request];
    }

    public function markViewed(array $context, string $ip, string $userAgent): void
    {
        $signer = $context['signer'];
        if ($signer['status'] !== SignatureRepository::SIGNER_PENDING || $signer['request_status'] !== SignatureRepository::PENDING) {
            return;
        }

        $this->db->transaction(function () use ($signer, $context, $ip, $userAgent): void {
            $this->signatures->updateSigner((int) $signer['id'], ['status' => SignatureRepository::SIGNER_VIEWED]);
            $this->signatures->addEvent((int) $signer['request_id'], (int) $signer['id'], 'viewed', $ip, $userAgent);
            $this->audit->record('signature_viewed', actor: 'signer', ownerHash: $signer['owner_hash'], documentPublicId: $context['document']->publicId,
                metadata: ['request' => $signer['request_public_id']], ipAddress: $ip, userAgent: $userAgent);
        });
    }

    /**
     * Onay + imza. $type: drawn (PNG data URL) | typed (ad).
     *
     * @return array{completed: bool}
     */
    public function sign(string $token, bool $consent, string $type, string $payload, string $ip, string $userAgent, string $locale): array
    {
        $context = $this->signerContext($token);
        $signer = $context['signer'];
        $document = $context['document'];
        $this->assertCanAct($signer);

        if (!$consent) {
            throw new ValidationException('Consent required', 'signature.consent_required');
        }

        $typed = null;
        $image = null;
        if ($type === 'drawn') {
            $image = self::validateSignatureImage($payload);
        } elseif ($type === 'typed') {
            $typed = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $payload));
            if ($typed === '' || mb_strlen($typed) > 150) {
                throw new ValidationException('Typed signature', 'signature.typed_invalid');
            }
        } else {
            throw new ValidationException('Signature type', 'signature.type_invalid');
        }

        $consentText = $this->translator->get('signature.consent_text', [], $locale);
        $relative = null;
        if ($image !== null) {
            $relative = 'signatures/' . substr($document->publicId, 0, 2) . '/' . $document->publicId . '/' . $signer['request_public_id'] . '-' . $signer['id'] . '.png';
            $this->storage->putContents($relative, $image);
        }

        try {
            $this->db->transaction(function () use ($signer, $document, $consentText, $type, $relative, $typed, $ip, $userAgent, $locale): void {
                $this->signatures->lockRequest((int) $signer['request_id']);
                $fresh = $this->signerContextById((int) $signer['id']);
                $this->assertCanAct($fresh);

                $now = Database::now();
                $consentMeta = ['consent_version' => self::CONSENT_VERSION, 'consent_text_sha256' => hash('sha256', $consentText), 'locale' => $locale];
                $this->signatures->addEvent((int) $signer['request_id'], (int) $signer['id'], 'consented', $ip, $userAgent, $consentMeta);
                $this->signatures->updateSigner((int) $signer['id'], [
                    'status' => SignatureRepository::SIGNER_SIGNED,
                    'signature_type' => $type,
                    'signature_path' => $relative,
                    'typed_name' => $typed,
                    'consented_at' => $now,
                    'signed_at' => $now,
                ]);
                $this->signatures->addEvent((int) $signer['request_id'], (int) $signer['id'], 'signed', $ip, $userAgent, ['method' => $type]);

                foreach (['signature_consented' => $consentMeta, 'signature_signed' => ['method' => $type]] as $event => $meta) {
                    $this->audit->record($event, actor: 'signer', ownerHash: $signer['owner_hash'], documentPublicId: $document->publicId,
                        inputHash: $signer['source_sha256'], metadata: $meta + ['request' => $signer['request_public_id']], ipAddress: $ip, userAgent: $userAgent);
                }
            });
        } catch (\Throwable $e) {
            if ($relative !== null) {
                $this->storage->delete($relative);
            }
            throw $e;
        }

        return ['completed' => $this->finalizeIfComplete((int) $signer['request_id'])];
    }

    public function decline(string $token, string $reason, string $ip, string $userAgent): void
    {
        $context = $this->signerContext($token);
        $signer = $context['signer'];
        $this->assertCanAct($signer);
        $reason = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $reason)), 0, 500);

        $this->db->transaction(function () use ($signer, $context, $reason, $ip, $userAgent): void {
            $this->signatures->lockRequest((int) $signer['request_id']);
            $this->signatures->updateSigner((int) $signer['id'], ['status' => SignatureRepository::SIGNER_DECLINED, 'declined_at' => Database::now()]);
            $this->signatures->setRequestStatus((int) $signer['request_id'], SignatureRepository::DECLINED);
            $this->signatures->addEvent((int) $signer['request_id'], (int) $signer['id'], 'declined', $ip, $userAgent, $reason === '' ? [] : ['reason' => $reason]);
            $this->audit->record('signature_declined', actor: 'signer', ownerHash: $signer['owner_hash'], documentPublicId: $context['document']->publicId,
                metadata: ['request' => $signer['request_public_id']], ipAddress: $ip, userAgent: $userAgent);
        });
    }

    /**
     * Final PDF yolu (imzalayan veya sahip indirir).
     */
    public function finalVersionForSigner(string $token): array
    {
        $context = $this->signerContext($token);
        $request = $context['request'];
        if ($request['status'] !== SignatureRepository::COMPLETED || $request['final_version_id'] === null) {
            throw new NotFoundException('Final not ready');
        }
        foreach ($this->documentService->versions($context['document']) as $version) {
            if ($version->id === (int) $request['final_version_id']) {
                return [$context['document'], $version];
            }
        }
        throw new NotFoundException('Final version missing');
    }

    // ------------------------------------------------------------------ Final PDF ve süre

    /**
     * Tüm imzalayanlar imzaladıysa final PDF'i oluşturur. Başarısız olursa talep "pending" kalır,
     * sahip yeniden deneyebilir.
     */
    private function finalizeIfComplete(int $requestId): bool
    {
        $request = $this->signatures->findRequest($requestId);
        if ($request === null || $request['status'] !== SignatureRepository::PENDING) {
            return $request !== null && $request['status'] === SignatureRepository::COMPLETED;
        }
        $signers = $this->signatures->signers($requestId);
        foreach ($signers as $signer) {
            if ($signer['status'] !== SignatureRepository::SIGNER_SIGNED) {
                return false;
            }
        }

        $document = $this->documents->findById((int) $request['document_id']);
        $source = null;
        foreach ($document !== null ? $this->documentService->versions($document) : [] as $version) {
            if ($version->id === (int) $request['version_id']) {
                $source = $version;
            }
        }
        if ($document === null || $source === null) {
            return false;
        }

        $events = $this->signatures->events($requestId);
        $signerData = [];
        $names = [];
        foreach ($signers as $signer) {
            $names[(int) $signer['id']] = $signer['name'];
            $ip = null;
            foreach ($events as $event) {
                if ((int) $event['signer_id'] === (int) $signer['id'] && $event['event_type'] === 'signed') {
                    $ip = $event['ip_address'];
                }
            }
            $signerData[(int) $signer['id']] = [
                'name' => $signer['name'],
                'email' => $signer['email'],
                'type' => (string) $signer['signature_type'],
                'image' => $signer['signature_path'] !== null ? $this->storage->resolve($signer['signature_path']) : null,
                'typed' => $signer['typed_name'],
                'consented_at' => $signer['consented_at'],
                'signed_at' => $signer['signed_at'],
                'ip' => $ip,
            ];
        }
        $fields = array_map(static fn (array $f): array => [
            'page' => (int) $f['page_number'],
            'x' => (float) $f['pos_x'],
            'y' => (float) $f['pos_y'],
            'w' => (float) $f['width'],
            'h' => (float) $f['height'],
            'signer' => (int) $f['signer_id'],
        ], $this->signatures->fields($requestId));

        $certificate = [
            'document' => $document->originalName,
            'request' => (string) $request['public_id'],
            'created_at' => (string) $request['created_at'],
            'completed_at' => Database::now(),
            'source_sha256' => (string) $request['source_sha256'],
            'events' => array_map(static fn (array $e): array => [
                'time' => (string) $e['created_at'],
                'type' => (string) $e['event_type'],
                'signer' => $e['signer_id'] !== null ? ($names[(int) $e['signer_id']] ?? null) : null,
                'ip' => $e['ip_address'],
            ], $events),
        ];

        try {
            $result = $this->operations->run(
                'signature_completed',
                (string) $request['owner_hash'],
                [[$document, $source]],
                ['request' => $request['public_id'], 'signers' => count($signers)],
                function (string $tmp, array $paths) use ($fields, $signerData, $certificate): ProcessResult {
                    $file = $tmp . '/signed.pdf';
                    $pages = (new SignaturePdfBuilder($this->translator))->build($paths[0], $file, $fields, $signerData, $certificate);

                    return new ProcessResult([new OperationOutput($file, $pages, 'signed')], 'fpdi', ['pages' => $pages], ['warnings.links_removed']);
                }
            );
        } catch (\Throwable $e) {
            $this->logger->error('Signature finalization failed', ['exception' => $e, 'request' => $request['public_id']]);

            return false;
        }

        $final = $result->versions[0];
        $this->db->transaction(function () use ($requestId, $final): void {
            $this->signatures->setFinal($requestId, $final->id, $final->sha256);
            $this->signatures->addEvent($requestId, null, 'completed', metadata: ['final_sha256' => $final->sha256, 'version' => $final->versionNumber]);
        });

        return true;
    }

    /**
     * Süresi dolan bekleyen talepler (temizlik görevi).
     */
    public function expireDue(): int
    {
        $count = 0;
        foreach ($this->signatures->dueForExpiry(Database::now()) as $request) {
            $this->expire($request);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function refreshExpiry(array $request): array
    {
        if ($request !== [] && $request['status'] === SignatureRepository::PENDING && strtotime($request['expires_at'] . ' UTC') <= time()) {
            $this->expire($request);
            $request['status'] = SignatureRepository::EXPIRED;
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function expire(array $request): void
    {
        $document = $this->documents->findById((int) $request['document_id']);
        $this->db->transaction(function () use ($request, $document): void {
            $this->signatures->setRequestStatus((int) $request['id'], SignatureRepository::EXPIRED);
            $this->signatures->addEvent((int) $request['id'], null, 'expired');
            $this->audit->record('signature_expired', actor: 'system', ownerHash: (string) $request['owner_hash'], documentPublicId: $document?->publicId,
                metadata: ['request' => $request['public_id']]);
        });
    }

    /**
     * @param array<string, mixed> $signer
     */
    private function assertCanAct(array $signer): void
    {
        if ($signer['request_status'] !== SignatureRepository::PENDING) {
            throw new ValidationException('Request not pending', 'signature.not_pending');
        }
        if (in_array($signer['status'], [SignatureRepository::SIGNER_SIGNED, SignatureRepository::SIGNER_DECLINED], true)) {
            throw new ValidationException('Signer already finished', 'signature.already_done');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function signerContextById(int $signerId): array
    {
        $row = $this->db->first(
            'SELECT s.*, r.status AS request_status FROM signature_signers s JOIN signature_requests r ON r.id = s.request_id WHERE s.id = ?',
            [$signerId]
        );
        if ($row === null) {
            throw new NotFoundException('Signer gone');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerRequest(string $ownerHash, string $publicId): array
    {
        $request = preg_match('/^[a-f0-9]{32}$/', $publicId) ? $this->signatures->findRequestForOwner($publicId, $ownerHash) : null;
        if ($request === null) {
            throw new NotFoundException('Signature request not found');
        }

        return $this->refreshExpiry($request);
    }

    /**
     * Çizilmiş imza: PNG data URL → doğrulanmış, GD ile yeniden kodlanmış PNG (saydamlık korunur).
     */
    public static function validateSignatureImage(string $dataUrl): string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) || strlen($m[1]) > 700_000) {
            throw new ValidationException('Signature image', 'signature.image_invalid');
        }
        $binary = base64_decode($m[1], true);
        $info = $binary === false ? false : @getimagesizefromstring($binary);
        if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] < 50 || $info[1] < 20 || $info[0] > 1600 || $info[1] > 800) {
            throw new ValidationException('Signature image', 'signature.image_invalid');
        }

        $image = @imagecreatefromstring((string) $binary);
        if ($image === false) {
            throw new ValidationException('Signature image', 'signature.image_invalid');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
