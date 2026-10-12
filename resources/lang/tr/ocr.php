<?php

return [
    'intro' => 'Taranmış (görüntü olarak kaydedilmiş) PDF sayfalarındaki metni tanır ve dosyayı aranabilir, metni seçilebilir hale getirir.',
    'accuracy' => 'OCR sonucu %100 doğru olmayabilir; özellikle düşük çözünürlüklü, eğik veya el yazısı içeren sayfalarda hatalar görülebilir.',
    'requirements' => 'Sunucuda Tesseract OCR ile Ghostscript/pdftoppm veya yapılandırılmış Evren OCR bağlantısı (cURL ve GD) gereklidir.',
    'evren_notice' => 'Sayfa görüntüleri metin tanıma için Evren hizmetine gönderilir. Orijinal dosya bu sunucuda korunur; Evren’in kendi veri saklama koşulları geçerlidir.',
    'evren_consent' => 'Bu belgenin sayfa görüntülerinin OCR için Evren’e gönderilmesini kabul ediyorum.',
    'evren_consent_required' => 'Evren’e gönderim için önce onay kutusunu işaretleyin.',
    'approximate_selection' => 'Sayfa görünümü korunur. OCR metni aranabilir; metin alanlarının konumu yaklaşık olduğu için seçim ve arama vurgusu özgün kelimelerle birebir örtüşmeyebilir.',
    'images_invalid' => 'OCR sayfa görüntüleri eksik veya geçersiz. Belgeyi yeniden açıp tekrar deneyin.',
    'images_too_large' => 'Sayfa görüntüleri sunucunun yükleme sınırını aşıyor. PDF’i daha küçük parçalara bölüp tekrar deneyin.',
    'preparing' => 'OCR için sayfa hazırlanıyor: :current / :total',
    'evren_failed' => 'Evren OCR isteği tamamlanamadı. Bağlantı, API erişimi veya zaman sınırını kontrol edip yeniden deneyin.',
    'evren_response' => 'Evren yanıtındaki OCR metni okunamadı. Yönetici yanıt metni alanı yapılandırmasını kontrol etmelidir.',
    'evren_rate_limit' => 'Evren kullanım sınırına ulaşıldı. Bir süre sonra yeniden deneyin.',
    'evren_terms' => 'Evren hesabında LLM API kullanım koşulları henüz kabul edilmemiş. Yönetici Evren hesabından koşulları kabul ettikten sonra yeniden deneyin.',
    'languages' => 'Tanıma dilleri: :languages',
    'too_many_pages' => 'OCR tek seferde en fazla :max sayfalık belgelere uygulanabilir.',
    'slow_note' => 'OCR yoğun bir işlemdir; sayfa sayısına göre birkaç dakika sürebilir.',
    'action' => 'OCR uygula',
];
