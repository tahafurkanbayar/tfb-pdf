<?php

return [
    'range_empty' => 'Lütfen en az bir sayfa veya sayfa aralığı girin.',
    'range_invalid' => '":token" geçerli bir sayfa aralığı değil. Örnek: 1-3, 5, 8-12',
    'range_zero' => '":token" geçersiz: sayfa numaraları 1\'den başlar.',
    'range_reversed' => '":token" geçersiz: aralığın başı sonundan büyük olamaz.',
    'range_out_of_bounds' => '":token" belgenin dışında kalıyor. Belgede :total sayfa var.',
    'mode_label' => 'Nasıl bölünsün?',
    'modes' => [
        'each' => 'Her sayfayı ayrı PDF yap',
        'ranges' => 'Her aralığı ayrı PDF yap',
        'extract' => 'Seçilen sayfaları tek PDF\'te topla',
    ],
    'mode_help' => [
        'each' => 'Belgedeki her sayfa ayrı bir dosya olur.',
        'ranges' => 'Yazdığınız her aralık (ör. 1-3) ayrı bir dosya olur.',
        'extract' => 'Seçtiğiniz sayfalar yazdığınız sırayla tek bir dosyada birleştirilir.',
    ],
    'ranges_label' => 'Sayfa aralıkları',
    'ranges_help' => 'Virgülle veya satır satır ayırın. Örnek: 1-3, 5, 8-12. "8-" yazarsanız 8. sayfadan sona kadar alınır.',
    'select_hint' => 'Sayfa küçük resimlerine tıklayarak da seçim yapabilirsiniz.',
    'action' => 'PDF\'i böl',
    'outputs_count' => ':count dosya oluşturulacak.',
    'download_zip' => 'Tümünü ZIP olarak indir',
    'zip_unavailable' => 'ZIP indirme bu sunucuda kullanılamıyor; dosyaları tek tek indirebilirsiniz.',
    'select_page' => 'Sayfa :number',
    'single_page' => 'Bu belge tek sayfa olduğu için sayfalara bölünemez.',
];
