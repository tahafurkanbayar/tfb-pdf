# Değişiklik Günlüğü

Bu projedeki önemli değişiklikler bu dosyada tutulur.
Biçim [Keep a Changelog](https://keepachangelog.com/tr-TR/1.1.0/) esaslıdır, sürüm numaraları [Semantic Versioning](https://semver.org/lang/tr/) kurallarına uyar.

## [Yayınlanmadı]

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

[Yayınlanmadı]: https://github.com/tahafurkanbayar/tfb-pdf/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/tahafurkanbayar/tfb-pdf/releases/tag/v1.0.0
