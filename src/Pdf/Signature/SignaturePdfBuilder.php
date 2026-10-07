<?php

declare(strict_types=1);

namespace App\Pdf\Signature;

use App\I18n\Translator;
use App\Pdf\Fpdi;

/**
 * İmzalı final PDF: kaynak sürümün sayfaları + alanlara basılmış imzalar + imza sertifikası sayfası.
 *
 * Bu, basit bir elektronik imza kaydıdır. PDF'e kriptografik dijital imza (PAdES) EKLENMEZ;
 * nitelikli elektronik imza (QES / eIDAS) değildir. Sertifika sayfası bunu açıkça belirtir.
 */
final class SignaturePdfBuilder
{
    public function __construct(private readonly Translator $translator)
    {
    }

    /**
     * @param list<array{page: int, x: float, y: float, w: float, h: float, signer: int}> $fields
     * @param array<int, array{name: string, email: ?string, type: string, image: ?string, typed: ?string, consented_at: ?string, signed_at: ?string, ip: ?string}> $signers signer id => bilgi
     * @param array{document: string, request: string, created_at: string, completed_at: string, source_sha256: string, events: list<array{time: string, type: string, signer: ?string, ip: ?string}>} $certificate
     */
    public function build(string $input, string $output, array $fields, array $signers, array $certificate): int
    {
        $pdf = new Fpdi();
        try {
            $count = $pdf->setSourceFile($input);
            $byPage = [];
            foreach ($fields as $field) {
                $byPage[$field['page']][] = $field;
            }

            for ($page = 1; $page <= $count; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);

                foreach ($byPage[$page] ?? [] as $field) {
                    $signer = $signers[$field['signer']] ?? null;
                    if ($signer !== null) {
                        $this->stamp($pdf, $field, $signer, $size['width'], $size['height']);
                    }
                }
            }

            $this->certificatePage($pdf, $signers, $certificate);
            $pdf->Output('F', $output);

            return $count + 1;
        } finally {
            $pdf->cleanUp(true);
        }
    }

    /**
     * @param array{page: int, x: float, y: float, w: float, h: float, signer: int} $field
     * @param array{name: string, email: ?string, type: string, image: ?string, typed: ?string, consented_at: ?string, signed_at: ?string, ip: ?string} $signer
     */
    private function stamp(Fpdi $pdf, array $field, array $signer, float $pageWidth, float $pageHeight): void
    {
        $x = $field['x'] * $pageWidth;
        $y = $field['y'] * $pageHeight;
        $w = $field['w'] * $pageWidth;
        $h = $field['h'] * $pageHeight;

        if ($signer['type'] === 'drawn' && $signer['image'] !== null && is_file($signer['image'])) {
            [$iw, $ih] = getimagesize($signer['image']) ?: [1, 1];
            $scale = min($w / $iw, $h / $ih);
            $dw = $iw * $scale;
            $dh = $ih * $scale;
            $pdf->Image($signer['image'], $x + ($w - $dw) / 2, $y + ($h - $dh) / 2, $dw, $dh, 'PNG');
        } else {
            // Yazılı imza: kutuya sığan en büyük boyut
            $text = (string) ($signer['typed'] ?? $signer['name']);
            $size = min($h * 0.6, 36);
            $pdf->useUnicodeFont($size);
            while ($size > 6 && $pdf->GetStringWidth($text) > $w * 0.95) {
                $size -= 1;
                $pdf->useUnicodeFont($size);
            }
            $pdf->SetTextColor(20, 40, 120);
            $pdf->Text($x + ($w - $pdf->GetStringWidth($text)) / 2, $y + $h / 2 + $size * 0.35, $text);
            $pdf->SetDrawColor(20, 40, 120);
            $pdf->SetLineWidth(0.6);
            $pdf->Line($x + $w * 0.05, $y + $h * 0.85, $x + $w * 0.95, $y + $h * 0.85);
        }

        // Alanın altında küçük açıklama: ad ve UTC zaman
        $pdf->useUnicodeFont(6);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Text($x, min($pageHeight - 4, $y + $h + 7), $signer['name'] . ' · ' . ($signer['signed_at'] ?? '') . ' UTC');
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * @param array<int, array{name: string, email: ?string, type: string, image: ?string, typed: ?string, consented_at: ?string, signed_at: ?string, ip: ?string}> $signers
     * @param array{document: string, request: string, created_at: string, completed_at: string, source_sha256: string, events: list<array{time: string, type: string, signer: ?string, ip: ?string}>} $c
     */
    private function certificatePage(Fpdi $pdf, array $signers, array $c): void
    {
        $t = fn (string $key, array $replace = []): string => $this->translator->get($key, $replace, 'tr') . ' / ' . $this->translator->get($key, $replace, 'en');

        // Uzun imzalayan / olay listeleri için otomatik sayfa sonu (belge sayfalarında kapalıdır)
        $pdf->SetMargins(48, 48, 48);
        $pdf->SetAutoPageBreak(true, 48);
        $pdf->AddPage('P', [595.28, 841.89]);
        $pdf->SetXY(48, 48);
        $pdf->useUnicodeFont(16, true);
        $pdf->MultiCell(0, 20, $t('signature.certificate.title'));
        $pdf->Ln(6);

        $row = function (string $label, string $value) use ($pdf): void {
            $pdf->useUnicodeFont(8, true);
            $pdf->MultiCell(0, 11, $label);
            $pdf->useUnicodeFont(9);
            $pdf->MultiCell(0, 12, $value);
            $pdf->Ln(3);
        };

        $row($t('signature.certificate.document'), $c['document']);
        $row($t('signature.certificate.request'), $c['request']);
        $row($t('signature.certificate.created'), $c['created_at'] . ' UTC');
        $row($t('signature.certificate.completed'), $c['completed_at'] . ' UTC');
        $row($t('signature.certificate.source_hash'), $c['source_sha256']);

        $pdf->Ln(4);
        $pdf->useUnicodeFont(11, true);
        $pdf->MultiCell(0, 14, $t('signature.certificate.signers'));
        foreach ($signers as $signer) {
            $pdf->useUnicodeFont(9, true);
            $pdf->MultiCell(0, 12, $signer['name'] . ($signer['email'] ? ' <' . $signer['email'] . '>' : ''));
            $pdf->useUnicodeFont(8);
            $pdf->MultiCell(0, 11, implode("\n", [
                $t('signature.certificate.method') . ': ' . $t('signature.methods.' . $signer['type']),
                $t('signature.certificate.consented') . ': ' . ($signer['consented_at'] ?? '-') . ' UTC',
                $t('signature.certificate.signed') . ': ' . ($signer['signed_at'] ?? '-') . ' UTC',
                'IP: ' . ($signer['ip'] ?? '-'),
            ]));
            $pdf->Ln(3);
        }

        $pdf->Ln(4);
        $pdf->useUnicodeFont(11, true);
        $pdf->MultiCell(0, 14, $t('signature.certificate.events'));
        $pdf->useUnicodeFont(7);
        foreach ($c['events'] as $event) {
            $pdf->MultiCell(0, 9, $event['time'] . ' UTC · ' . $this->translator->get('audit.events.signature_' . $event['type'], [], 'tr')
                . ' / ' . $this->translator->get('audit.events.signature_' . $event['type'], [], 'en')
                . ($event['signer'] !== null ? ' · ' . $event['signer'] : '') . ($event['ip'] !== null ? ' · IP ' . $event['ip'] : ''));
        }

        $pdf->Ln(8);
        $pdf->useUnicodeFont(7);
        $pdf->SetTextColor(90, 90, 90);
        foreach (['tr', 'en'] as $locale) {
            $pdf->MultiCell(0, 9, $this->translator->get('signature.certificate.disclaimer', [], $locale));
            $pdf->Ln(2);
        }
        $pdf->SetTextColor(0, 0, 0);
    }
}
