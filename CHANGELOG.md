# Değişiklik Günlüğü

Bu projedeki önemli değişiklikler bu dosyada tutulur.
Biçim [Keep a Changelog](https://keepachangelog.com/tr-TR/1.1.0/) esaslıdır, sürüm numaraları [Semantic Versioning](https://semver.org/lang/tr/) kurallarına uyar.

## [Yayınlanmadı]

## [1.1.0] - 2026-10-12

### Eklendi
- Evren'in adanmış `/v1/ocr` ucuna opsiyonel bağlantı: PDF.js sayfa görüntüleri, sunucuda cURL ile çağrı, orijinal görünümü koruyan aranabilir PDF sürümü, kullanıcı gönderim onayı ve TR/EN hata mesajları.
- `bin/check-evren.php`: gerçek belge kullanmadan bağlantı/yanıt alanı kontrolü ve kullanım koşullarını okuma; koşul kabulü yalnızca açık yönetici eylemiyle.

### Düzeltildi
- Test ortamı canlı Evren yapılandırmasından ayrıldı; yerel API ayarları opsiyonel araç testlerini etkilemez.

### Sınırlamalar
- Evren OCR yapay görselle canlı doğrulandı (HTTP 200, `text` alanı). Metin koordinatları yaklaşık olduğundan seçim/arama vurguları özgün sözcüklerle birebir örtüşmeyebilir; varsayılan sınır 5 sayfadır.
- Evren üzerinden Office→PDF dönüşümü doğrulanmadı; özgün düzenin korunması talebi nedeniyle AI ile içerik yeniden oluşturma eklenmedi.

## [1.0.0] - 2026-10-08

İlk kararlı sürüm.

### Eklendi
- **Yükleme ve önizleme:** sürükle-bırak yükleme, PDF içerik doğrulaması, boyut/sayfa sınırları; PDF.js ile sayfa küçük resimleri (sunucuda önbellekli) ve sayfa görüntüleyici.
- **PDF araçları:** birleştirme, bölme (her sayfa / aralık / seçili sayfalar, çoklu sonuç ZIP), sayfa sıralama ve kaldırma, döndürme, sıkıştırma (Ghostscript veya PHP), filigran (Türkçe karakter desteği), kalıcı karartma (redaction).
- **Opsiyonel araçlar:** Tesseract ile OCR, LibreOffice ile Office → PDF; araç sunucuda yoksa özellik kapalı gösterilir ve gerekeni söyler.
- **Basit elektronik imza:** imza alanları, imzalayanlar, davet bağlantısı (SMTP varsa e-posta), çizim/yazılı imza, ret/iptal/süre, imza sertifika sayfası.
- **Sürümler ve bütünlük:** orijinal dosya asla değişmez, her işlem yeni değiştirilemez sürüm üretir; her dosyanın SHA-256 özeti, sunucu ve tarayıcı tarafı doğrulama.
- **Audit log:** append-only, hash zinciriyle korunan işlem kaydı.
- **Saklama süresi:** 1 / 7 / 30 gün veya manuel; cron ya da fırsatçı otomatik temizlik.
- **Dışa aktarma:** belge veya tüm veriler (dosyalar, sürümler, metadata, audit, SHA-256 manifest) ZIP olarak.
- **Dil:** Türkçe ve İngilizce, URL önekli.
- **Kurulum:** SSH gerektirmeyen `/install` sayfası (ortam kontrolü, migration), anahtar ve deneme sınırı korumalı.
- **Arayüz:** açık / koyu / sistem teması (flaşsız), yerel Inter fontu, hero ve güven rozeti, büyük yükleme alanı ile dosya önizleme ve ilerleme, dashboard kartları ve boş durumlar, yapışkan navbar, footer; WCAG AA kontrast, 360–1280 px uyumlu.
- **Marka:** logo (işaret + kelime işareti), favicon, apple-touch ve manifest ikonları, paylaşım görseli (og:image).
- **Güvenlik:** CSRF, sıkı CSP (`script-src 'self'`), güvenli çerezler, kullanıcıya teknik hata ayrıntısı gösterilmez, depolama web erişimine kapalı.

[Yayınlanmadı]: https://github.com/tahafurkanbayar/tfb-pdf/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/tahafurkanbayar/tfb-pdf/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tahafurkanbayar/tfb-pdf/releases/tag/v1.0.0
