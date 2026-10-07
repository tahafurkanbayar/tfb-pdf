<?php

return [
    'level_label' => 'Sıkıştırma düzeyi',
    'levels' => [
        'low' => 'Hafif (en iyi kalite)',
        'medium' => 'Önerilen',
        'high' => 'Güçlü (en küçük boyut)',
    ],
    'level_help' => [
        'low' => 'Görüntü kalitesi neredeyse hiç değişmez, kazanç sınırlı olabilir.',
        'medium' => 'Ekranda okumak ve paylaşmak için iyi bir denge.',
        'high' => 'Görüntüler belirgin şekilde küçültülür; baskı kalitesi düşebilir.',
    ],
    'engine_ghostscript' => 'Bu sunucuda Ghostscript bulunduğu için gelişmiş sıkıştırma kullanılacak.',
    'engine_php' => 'Bu sunucuda Ghostscript yok; PHP tabanlı optimizasyon kullanılacak (JPEG görüntüler küçültülür, sıkıştırılmamış veriler sıkıştırılır). Kazanç dosyaya göre değişir.',
    'action' => 'PDF\'i sıkıştır',
    'invalid_level' => 'Geçersiz sıkıştırma düzeyi.',
    'result_sizes' => ':before → :after (%:percent daha küçük)',
    'result_no_gain' => 'Önceki boyut: :before. Ulaşılabilen boyut: :after.',
];
