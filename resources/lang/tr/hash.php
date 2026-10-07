<?php

return [
    'title' => 'Bütünlük kontrolü',
    'intro' => 'Kayıtlı SHA-256 özetleriyle dosyaların değişmediğini doğrulayın veya elinizdeki bir dosyanın hangi sürüme ait olduğunu bulun.',
    'verify_server' => 'Sunucudaki dosyaları doğrula',
    'verifying' => 'Doğrulanıyor...',
    'verify_ok' => 'Tüm sürümler kayıtlı özetleriyle eşleşiyor; dosyalar kaydedildiğinden beri değişmemiş.',
    'verify_failed' => 'Bazı sürümler kayıtlı özetleriyle eşleşmiyor veya bulunamıyor. Site yöneticisine bildirin.',
    'status' => [
        'ok' => 'Eşleşiyor',
        'mismatch' => 'Eşleşmiyor',
        'missing' => 'Dosya bulunamadı',
    ],
    'compare_local' => 'Bilgisayarınızdaki bir dosyayı karşılaştırın',
    'compare_help' => 'Dosya sunucuya gönderilmez; özet tarayıcınızda hesaplanır.',
    'compare_match' => 'Bu dosya :version ile birebir aynı.',
    'compare_no_match' => 'Bu dosya bu belgenin hiçbir sürümüyle eşleşmiyor (SHA-256: :hash).',
    'compare_unsupported' => 'Tarayıcınız bu kontrolü yalnızca güvenli (HTTPS) bağlantıda yapabilir.',
    'computing' => 'Özet hesaplanıyor...',
];
