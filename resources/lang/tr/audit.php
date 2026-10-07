<?php

// Audit olay türleri ve durumlarının görünen adları
return [
    'events' => [
        'upload' => 'Yükleme',
        'merge' => 'Birleştirme',
        'split' => 'Bölme',
        'reorder' => 'Sayfa düzenleme',
        'rotate' => 'Döndürme',
        'compress' => 'Sıkıştırma',
        'watermark' => 'Filigran',
        'redact' => 'Karartma',
        'ocr' => 'OCR',
        'office_convert' => 'Office dönüştürme',
        'download' => 'İndirme',
        'export' => 'Dışa aktarma',
        'delete' => 'Silme',
        'expiry' => 'Süre dolumu ile silme',
        'expiry_changed' => 'Saklama süresi değişikliği',
        'signature_created' => 'İmza talebi oluşturuldu',
        'signature_invited' => 'İmza daveti gönderildi',
        'signature_viewed' => 'İmza talebi görüntülendi',
        'signature_consented' => 'Elektronik imza onayı verildi',
        'signature_signed' => 'İmzalandı',
        'signature_declined' => 'İmza reddedildi',
        'signature_completed' => 'İmza süreci tamamlandı',
        'signature_cancelled' => 'İmza talebi iptal edildi',
    ],
    'status' => [
        'success' => 'Başarılı',
        'failed' => 'Başarısız',
        'no_change' => 'Değişiklik yok',
        'processing' => 'İşleniyor',
        'completed' => 'Tamamlandı',
    ],
    'actor' => [
        'owner' => 'Belge sahibi',
        'signer' => 'İmzacı',
        'system' => 'Sistem',
        'cron' => 'Zamanlanmış görev',
    ],
];
