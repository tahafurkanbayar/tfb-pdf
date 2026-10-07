<?php

return [
    'title' => 'Gizlilik',
    'storage_title' => 'Dosyalarınız nerede saklanır?',
    'retention_title' => 'Ne kadar süre saklanır?',
    'retention_text' => 'Her belge için bir saklama süresi seçebilirsiniz (1 gün, 7 gün, 30 gün veya siz silene kadar). Süresi dolan belgeler ve tüm sürümleri otomatik olarak kalıcı biçimde silinir.',
    'cookies_title' => 'Çerezler',
    'cookies_intro' => 'Bu uygulama yalnızca çalışması için gerekli çerezleri kullanır:',
    'cookies' => [
        'session' => 'Oturum çerezi: form güvenliği (CSRF koruması) için. Tarayıcı kapanınca silinir.',
        'owner' => 'Belge sahipliği çerezi: belgelerinize yalnızca sizin erişebilmeniz için rastgele bir kimlik. 1 yıl geçerlidir.',
        'locale' => 'Dil tercihi çerezi: seçtiğiniz dili hatırlamak için. 1 yıl geçerlidir.',
    ],
    'tracking_title' => 'İzleme',
    'export_title' => 'Verilerinizi dışa aktarma',
    'export_text' => 'Belgelerinizi, tüm sürümlerini, işlem geçmişini ve audit kayıtlarını istediğiniz zaman tek bir ZIP dosyası olarak indirebilirsiniz.',
    'signature_title' => 'İmza talepleri',
    'signature_text' => 'Bir imza talebini imzaladığınızda veya reddettiğinizde, onay zamanınız, IP adresiniz ve tarayıcı bilginiz imza kaydına eklenir ve belge sahibinin göreceği imza sertifikasında yer alır. Bu bilgiler belge silinene kadar saklanır.',
];
