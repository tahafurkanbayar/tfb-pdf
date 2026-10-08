# Son Rapor — tfb-pdf

> Spec §56'ya göre hazırlanmıştır. Tarih: 2026-10-08. Commit geçmişi: Aşama 0–33, her aşama ayrı commit.
> Bu rapordaki her sonuç gerçekten çalıştırılmış bir komuta dayanır; komutların tamamı, başarısız olanlar dahil, [COMMANDS_LOG.md](COMMANDS_LOG.md) dosyasındadır.

## Proje Özeti

tfb-pdf, standart cPanel shared hosting üzerinde çalışan, framework'süz PHP + MySQL/MariaDB + Bootstrap 5 + vanilla JavaScript ile yazılmış, Türkçe ve İngilizce arayüzlü, açık kaynaklı (MIT) bir PDF yönetim ve düzenleme platformudur. Kullanıcılar PDF yükler, önizler, birleştirir, böler, sıralar, döndürür, sıkıştırır, filigran ekler, kalıcı olarak karartır, basit imza akışı başlatır ve sonuçları indirir. Orijinal dosya hiçbir zaman değiştirilmez; her işlem SHA-256 özetli, değiştirilemez yeni bir sürüm üretir ve hash zinciriyle korunan append-only bir audit log'a yazılır.

Docker, Node.js/npm, Python, Redis, PostgreSQL, root/SSH erişimi veya sürekli çalışan worker gerekmez. Analytics/telemetry ve üçüncü taraf CDN yoktur.

## Mimari

```text
public/index.php (front controller; app-root.php ile public_html yerleşimi)
  → bootstrap/app.php     PHP sürüm kontrolü, .env, config, servis tanımları (açık DI container), hata yakalayıcı
  → Middleware            ForceHttps → ResolveLocale → EnsureConfigured → VerifyCsrfToken → ThrottleRequests
  → Router                routes/web.php (dil önekli sayfalar, /install), routes/api.php (JSON API)
  → Controller            ince: girdi okur, servisi çağırır, Presenter ile yanıt
  → Service               DocumentService, OperationService (ortak işlem akışı), PdfToolService, AuditService,
                          CleanupService, ExportService, SignatureService, StorageService, HashService, Install/*
  → Repository            yalnızca SQL (PDO prepared statements, emülasyon kapalı)
  → MySQL 8 / MariaDB 10.4
```

- **PDF katmanı** (`src/Pdf/`): kendi ayrıştırıcısı (klasik xref, xref stream, object stream, hibrit), FPDI + tFPDF (Unicode/DejaVu) ile üretim; sıkıştırma, karartma, OCR, Office ve imza alt modülleri. Controller/view'dan bağımsızdır.
- **İşlem akışı:** girdi sürümünün özeti doğrulanır → geçici dizinde işlem → çıktı doğrulanır ve özetlenir → atomik taşıma (üzerine yazma reddi) → sürüm + operation + audit kaydı tek transaction'da. Hata olursa işlem `failed` kaydedilir, yarım dosya kalmaz.
- **Kimlik:** hesap yok; HMAC'li owner çerezi. `documents.owner_hash` ileride `user_id` ile genişletilebilir. JSON API ve servis katmanı mobil/REST istemciye hazırdır.
- **Harici araçlar** (`src/Tools/`): tespit + önbellek, kabuksuz çalıştırma, zaman aşımı; yoksa özellik kapanır.
- Ayrıntı: [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md), [DECISIONS.md](DECISIONS.md).

## Tamamlanan Özellikler

Aşağıdakiler gerçek kodla uygulanmış, otomatik testlerle ve (aksi belirtilmedikçe) gerçek Apache sunucusunda doğrulanmıştır:

- Yükleme ve doğrulama (PDF içerik/yapı kontrolü, şifreli PDF reddi, boyut/sayfa/depolama kotası, güvenli dosya adları)
- PDF önizleme (PDF.js, tembel küçük resimler, sunucu önbelleği, sayfa görüntüleyici)
- Birleştirme, bölme (her sayfa / aralıklar / çıkarma, ZIP), sıralama ve sayfa kaldırma, döndürme
- Sıkıştırma (PHP yöntemi; Ghostscript yolu sahte çalıştırıcıyla test edildi)
- Metin filigranı (konum, döşeme, açı, opaklık, renk, sayfa aralığı, Türkçe karakter)
- Kalıcı karartma (sayfa görüntüye dönüşür, gizli metnin dosyada kalmadığı testle kanıtlandı)
- Değiştirilemez sürümleme ve köken bilgisi; SHA-256 (sunucuda yeniden doğrulama, tarayıcıda yerel karşılaştırma)
- Append-only audit log + hash zinciri + `bin/verify-audit.php`
- Saklama süresi (1g/7g/30g/manuel), `cron/cleanup.php`, fırsatçı temizlik
- Dışa aktarma (belge veya tüm veriler: dosyalar, sürümler, metadata, işlem geçmişi, audit, SHA-256 manifest)
- Onaylı, CSRF korumalı, audit'li silme
- Basit imza akışı (alanlar, imzalayanlar, davet bağlantısı, onay kaydı, çizim/yazılı imza, ret/iptal/süre, final PDF + sertifika sayfası)
- Güvenlik: CSRF + Origin kontrolü, CSP ve güvenlik başlıkları, prepared statements, path traversal koruması, private storage, `.env` koruması, rate limiting, teknik ayrıntı göstermeyen hata yönetimi
- Responsive arayüz (320/390/820 px ve masaüstü), temel erişilebilirlik
- Eksiksiz Türkçe/İngilizce arayüz ve API mesajları
- Web kurulum sayfası (`/install`): ortam kontrolü ve migration (SSH'siz)

## Opsiyonel Özellikler

Sunucu desteğine bağlıdır; yoksa uygulama çalışmaya devam eder ve kullanıcıya açık mesaj gösterilir (Aşama 33'te araçlar yokken OCR API'nin 503 + anlaşılır mesaj, sıkıştırmanın PHP yöntemiyle çalıştığı gerçek sunucuda doğrulandı):

| Özellik | Gereken |
|---|---|
| OCR | Tesseract + (Ghostscript veya pdftoppm) + `proc_open` |
| Office → PDF | LibreOffice + `proc_open` |
| Ghostscript ile sıkıştırma ve sunucuda sayfa görüntüsü | Ghostscript + `proc_open` (yoksa PHP yöntemi / tarayıcı görüntüsü) |
| İmza davetlerinin e-postayla gönderimi | SMTP ayarları (yoksa bağlantı ekranda gösterilir) |
| Toplu ZIP indirme ve dışa aktarma | PHP `zip` eklentisi |
| Küçük resim önbelleği, PHP görsel sıkıştırma | PHP `gd` eklentisi |

## Bilinen Sınırlamalar

Tam liste: [LIMITATIONS.md](LIMITATIONS.md). Öne çıkanlar:

- **Bilinçli olarak yok:** nitelikli e-imza (QES/eIDAS), PAdES kriptografik imza, resmi kimlik doğrulama, kullanıcı hesapları/social login/ödeme, analytics.
- İmza basit elektronik imzadır; imzalayanın kimliği doğrulanmaz.
- Şifreli PDF'ler desteklenmez.
- İşlemlerden sonra bağlantılar, notlar, form alanları, yer imleri, ekler ve dijital imzalar yeni sürüme aktarılmaz (kullanıcı uyarılır).
- Karartılan sayfalar görüntüdür (150 DPI, metin seçilemez).
- PHP sıkıştırması yalnızca JPEG ve sıkıştırılmamış akışlarda kazanç sağlar.
- **Gerçek araçlarla test edilmeyenler:** Ghostscript, LibreOffice, Tesseract ve SMTP gönderimi (geliştirme makinesinde yoklar; komut ve geri dönüş davranışları sahte çalıştırıcılarla test edildi).
- **Denenemeyenler:** "PHP 8.2 veya üzeri gerekli" mesajı (eski PHP yok); `public/.user.ini` etkisi (geliştirme ortamı mod_php; mod_php karşılığı denendi); zip eklentisi kapalı XAMPP'te ZIP indirmenin tarayıcıdan denenmesi (testler `-d extension=zip` ile çalıştı).
- Tarayıcı otomasyonu test paketinde yok (Node/npm yasağı); arayüz headless Chrome ile elle doğrulandı.

## Test Sonuçları

Son çalıştırmalar (2026-10-08, Aşama 33):

| Ortam | Komut | Sonuç |
|---|---|---|
| PHP 8.2.12 (XAMPP), zip yok | `php vendor/bin/phpunit` | OK — 289 test, 22310 assertion, 3 atlandı (zip gerektirenler) |
| PHP 8.2.12, zip ile | `php -d extension=zip vendor/bin/phpunit` | OK — 289 test, 22344 assertion, 1 atlandı ("zip yokken" testi) |
| PHP 8.3.35 (resmi Windows paketi), zip ile | `phpunit` (`upload_max_filesize=40M`) | OK — 289 test, 22344 assertion, 1 atlandı; deprecation yok |
| PHP 8.3.35, zip yok | aynı | OK — 289 test, 22310 assertion, 3 atlandı |
| PHP 8.3.35 | `php -l` (278 dosya), `composer check-platform-reqs` | 0 hata, tümü success |
| Suite dağılımı (8.2 + zip) | `--testsuite Unit/Integration/Feature` | 193 / 63 (1 atlandı) / 33 test |
| Çeviri | `php bin/check-translations.php` | OK — TR/EN 686 anahtar |
| Audit | `php bin/verify-audit.php` | OK — zincir sağlam |
| Bağımlılıklar | `composer validate`, `composer audit` | geçerli; bilinen güvenlik açığı yok |
| Canlı sunucu (Apache + MariaDB 10.4) | spec §55 HTTP doğrulama betiği | **30/30** (ilk turda 29/30 — aşağıya bakın) |

**Dürüstlük notları (başarısız olan ve düzeltilenler):**

- PHP 8.3 ile ilk tam çalıştırma **1 hata** verdi: php.ini'siz (`-n`) çalıştırıldığı için `upload_max_filesize` 2M kaldı ve 3,4 MB'lık test yüklemesi reddedildi. Bu uygulamanın doğru davranışıdır (php.ini sınırına uyar); XAMPP ile aynı 40M sınırıyla tekrar çalıştırıldı ve geçti.
- §55 HTTP doğrulamasının ilk turu **29/30** oldu: `post_max_size`'ı aşan yüklemede PHP'nin istek başı uyarısı JSON yanıtının önüne basılıyordu. `public/.htaccess` (mod_php) ve `public/.user.ini` (PHP-FPM) ile `display_errors` kapatıldı; ikinci tur 30/30.
- README'de "depoda `composer.phar` bulunur" ifadesi yanlıştı (dosya `.gitignore`'da); düzeltildi.
- Önceki aşamalarda bulunan ve düzeltilen hatalar ile başarısız komutlar ilgili aşama tablolarında kayıtlıdır.

§55 maddelerinin karşılığı:

| # | Madde | Nasıl doğrulandı |
|---|---|---|
| 1 | Composer dependencies | `composer validate`, `install`, `check-platform-reqs`, `audit` |
| 2 | PHP uyumluluğu | PHP 8.2.12 ve 8.3.35 ile lint + tüm testler |
| 3–4 | Database bağlantısı, migration | `bin/migrate.php status` / `migrate`; `/install` (boş veritabanında test); `schema.sql` içe aktarımı (Aşama 32) |
| 5 | Testler | yukarıdaki tablo |
| 6–15 | Yükleme, birleştirme, bölme, döndürme, yeni sürüm, orijinal değişmedi, SHA-256, audit, indirme, silme | HTTP doğrulama betiği (gerçek PDF, Apache), ayrıca Feature/Integration testleri |
| 16 | Expiry cleanup | süre geçmişe çekildi → `php cron/cleanup.php` (çıkış 0) → belge silindi, audit `expiry` |
| 17 | Hatalı PDF | HTML içerikli `.pdf` ve kesik PDF → 422, çevrilmiş mesaj |
| 18 | Büyük dosya limiti | 26 MB → "maximum size is 25.0 MB"; 42 MB (> post_max_size) → "exceeds the upload size allowed by the server" |
| 19–20 | Private storage, `.env` | 11 yol (belge dosyası, logs, `.env`, `.git/config`, `src`, `config`, `schema.sql`, `public/../storage`) → 403; başka tarayıcı → 404 |
| 21 | CSRF | token yok / yanlış token / yabancı Origin → 403, belge silinmedi |
| 22–24 | TR, EN, dil değiştirme | HTTP + `LocalizationPagesTest` (25 yol × 2 dil); dış adrese yönlendirme yok |
| 25 | Eksik çeviri anahtarı | `check-translations.php` + `Translator::missingKeys` testi |
| 26 | Opsiyonel araçlar yokken | gs/soffice/tesseract yok: OCR sayfası 200, OCR API 503, sıkıştırma PHP ile |
| 27 | Production error handling | `APP_ENV=production`, `APP_DEBUG=true`, yanlış DB parolası: genel mesaj + hata kodu, ayrıntı yalnızca log'da, parola log'da da yok |
| 28 | `.gitignore` | `git check-ignore`; geçmişte `.env` ve APP_KEY yok |
| 29 | README | bölümler, göreli bağlantılar, GitHub'da içindekiler bağlantıları (20/20) |

## cPanel Kurulumu

Ayrıntı: [README → cPanel Kurulumu](../README.md#cpanel-kurulumu). Özet:

1. PHP 8.3 (en az 8.2) seçin, `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip` eklentilerini açın.
2. MySQL veritabanı ve kullanıcı oluşturun, kullanıcıya ALL PRIVILEGES verin.
3. Yerelde `composer install --no-dev --optimize-autoloader`, projeyi `vendor/` ile birlikte yükleyin.
4. Document root'u `public/` yapın; mümkün değilse `public/` içeriğini `public_html`'e kopyalayıp `app-root.php` ekleyin.
5. `.env` oluşturun (`APP_URL`, `APP_KEY`, `DB_*`, `INSTALL_KEY`).
6. `storage/` izinleri 755 (gerekirse 775).
7. `https://alanadiniz/install` → kontrolleri inceleyin → "Migration'ları çalıştır" (alternatif: phpMyAdmin'den `database/schema.sql`).
8. Cron: saatte bir `php /home/KULLANICI/tfb-pdf/cron/cleanup.php`.
9. `INSTALL_KEY`'i boşaltın, HTTPS kurup `FORCE_HTTPS=true` yapın.

## Türkçe / İngilizce Dil Sistemi

- Her kullanıcı metni çeviri anahtarıyla üretilir: PHP'de `__('grup.anahtar')` / `trans_choice()`, JavaScript'te `t()` / `tc()`. Dosyalar: `resources/lang/{tr,en}/<grup>.php` (31 grup, dil başına 686 anahtar).
- URL dil önekli (`/tr/...`, `/en/...`); önek yoksa çerez → `Accept-Language` → varsayılan `tr`. Dil değiştirici aynı sayfa ve sorgu dizesiyle diğer dile geçer, dış adrese yönlendirmez.
- API mesajları `X-Locale` başlığına göre çevrilir. Tarih ve sayı biçimleri sunucuda dile göre üretilir (TR `13,5 KB`, `08.10.2026 14:30`).
- JavaScript'e yalnızca sayfanın ihtiyaç duyduğu çeviri grupları, sayfa içi JSON yapılandırmasıyla gönderilir (inline script yok).
- Kontrol mekanizmaları: `php bin/check-translations.php` (anahtar ve yer tutucu eşitliği), `TranslationCompletenessTest`, `LocalizationPagesTest` (tüm sayfalarda eksik anahtar, çözülmemiş anahtar metni, İngilizce sayfada Türkçe metin, JS çevirilerinin sayfa diliyle uyumu).

## Çalıştırılan Komutlar

Projede çalıştırılan komutların **tamamı**, aşama aşama ve gerçek sonuçlarıyla (başarısız denemeler, hatalı ilk çalıştırmalar ve düzeltmeler dahil) [COMMANDS_LOG.md](COMMANDS_LOG.md) dosyasındadır. Bu raporda yeniden kopyalanmamıştır; son doğrulama aşamasının komutları o dosyanın "Aşama 33" bölümündedir.

Geçici doğrulama betikleri ve indirilen PHP 8.3 paketi depo dışında (oturumun geçici dizininde) tutuldu; depoya eklenmedi. Doğrulama sırasında oluşturulan geçici web dizinleri (`tfbpub-smoke`, `tfbprod-smoke`) ve geçici veritabanı (`tfb_pdf_schema_test`) silindi. Yerel geliştirme veritabanında doğrulama sırasında yüklenen örnek belgeler saklama süreleri dolunca temizlik görevi tarafından silinecektir.
