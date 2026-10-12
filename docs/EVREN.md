# Evren OCR bağlantısı

Bu bağlantı cPanel'de Tesseract, Ghostscript veya bir worker gerektirmeden OCR çalıştırmak içindir. PHP cURL, GD ve Evren OCR erişimi gereklidir. Office dosyalarını Evren ile PDF'e dönüştüren bir API doğrulanmadı; Office aracı mevcut LibreOffice akışını kullanır.

## Kurulum

Anahtarı yalnızca sunucudaki `.env` dosyasında tutun:

```ini
EVREN_OCR_ENABLED=true
EVREN_API_KEY=
EVREN_OCR_MODEL=dots-ocr
EVREN_OCR_TEXT_PATH=text
EVREN_TIMEOUT=60
EVREN_OCR_MAX_PAGES=5
```

Metin alanı (`EVREN_OCR_TEXT_PATH`) başarılı `/v1/ocr` yanıtındaki metin dizgesinin noktalı yoludur. 12 Ekim 2026 canlı testi `/v1/ocr` yanıtındaki `text` alanını doğruladı; bu hesap için `EVREN_OCR_TEXT_PATH=text` kullanılır. Başka bir yanıt yapısı için yol ayrıca doğrulanmalıdır. Boşsa Evren aracı etkinleşmez; mevcut yerel OCR varsa kullanılabilir.

`php bin/check-evren.php` yalnızca yapay bir test görseli gönderir. Başarılı yanıtta metin alanlarının yollarını ve karakter sayılarını gösterir; anahtarı ve OCR metnini yazdırmaz. Bir model listesi OCR sonucu olarak kabul edilmez.

Sağlayıcı `403 terms_not_accepted` döndürürse `php bin/check-evren.php --terms` ile güncel koşulları okuyun. Hesap sahibinin açık kabulünden sonra yönetici `php bin/check-evren.php --accept-terms-v1` komutunu çalıştırabilir. Koşullar hiçbir OCR çağrısı veya kurulum sırasında otomatik kabul edilmez. Sonrasında bağlantı testini yeniden çalıştırıp metin alanını ayarlayın.

## Akış ve sınırlar

1. Kullanıcı OCR ekranında Evren'e sayfa görüntüsü gönderimini onaylar. API bu onayı ayrıca kontrol eder.
2. Tarayıcı PDF.js ile yaklaşık 200 DPI (en büyük kenar 3000 piksel) JPEG sayfalar üretir. Tek dosya ve toplam POST boyutu sınırları aşılırsa gönderim yapılmaz.
3. Sunucu tüm görüntülerin sayısını, türünü, boyutunu, kaynak sayfa oranını ve çözülebilirliğini kontrol eder; GD ile beyaz zeminli JPEG'e yeniden kodlar.
4. Görüntüler sabit `https://evren-llmapi.ssyz.org.tr/v1/ocr` adresine sunucu üzerinden gönderilir. Model yalnızca `dots-ocr` veya `deepseek-ocr-2` olabilir. TLS doğrulaması açık, yönlendirme kapalı; yanıt boyutu en fazla 2 MiB.
5. FPDI kaynak sayfa görünümünü içe aktarır; Unicode OCR metnini görünmez metin katmanına ekler. Yeni sürüm, SHA-256, kota ve audit işlemleri mevcut OperationService akışındadır. Herhangi bir sayfa başarısızsa kısmi sürüm oluşturulmaz. API anahtarı, görseller ve OCR metni audit/log kayıtlarına yazılmaz.

Varsayılan sınır 5 sayfa; yapılandırma 1–10 arasında sınırlandırılır. Tarayıcı ayrıca PHP `max_file_uploads` sınırını gözetir. Uzun süreli çağrılar işlem süresi sınırında hata verir; sessizce eksik PDF üretilmez. Büyük belgeler önce bölünebilir.

Yanıt metin olarak işlendiğinden kelime koordinatları yaklaşık yerleştirilir; seçim/arama vurguları özgün sözcüklerle birebir örtüşmeyebilir. Sağlayıcı Markdown veya JSON dizgesini döndürüyorsa bu biçim kendiliğinden dönüştürülmez. Koordinatlı OCR çıktısı desteği ayrıca doğrulama gerektirir. FPDI'nin form, bağlantı, imza ve diğer belge özelliklerine ilişkin mevcut uyarıları geçerlidir.

Evren'e gönderilen görüntüler dış hizmette işlenir. Bu sunucudaki silme/saklama işlemleri sağlayıcıdaki verileri silmez; sağlayıcı koşulları ayrıca geçerlidir. Yerel orijinal dosya değiştirilmez.

## Doğrulama durumu — 12 Ekim 2026

Kullanıcı tarafından verilen resmî `/v1/ocr` istek örneği uygulandı. İlk çağrı `403 terms_not_accepted` döndürdü. Koşul metni okundu (HTTP 200), hesap sahibinin açık onayıyla v1 koşulları kabul edildi (HTTP 200). Ardından yalnızca yapay test görseliyle OCR çağrısı HTTP 200 döndürdü: `text` alanı, 22 karakter ve test işareti doğrulandı. Yerel yapılandırmada metin yolu `text` olarak etkinleştirildi. Gerçek kullanıcı belgesi bu testte dış hizmete gönderilmedi.

Kaynak: [Evren LLM](https://evren.ssyz.org.tr/llm-inference). Evren kullanım koşulları `GET https://evren-llmapi.ssyz.org.tr/v1/terms/text` ile okunabilir.
