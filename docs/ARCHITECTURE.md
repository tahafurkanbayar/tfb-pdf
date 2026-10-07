# Mimari

> Yaşayan belge. Kod değiştikçe güncellenir. "Planlanan" diye işaretli kısımlar henüz yazılmamıştır.

## Genel bakış

Framework'süz, katmanlı PHP uygulaması. Tek giriş noktası `public/index.php` (front controller).

```text
HTTP isteği
  → public/index.php
  → bootstrap/app.php        (env, config, error handler, session, container)
  → Router                   (routes/web.php, routes/api.php)
  → Middleware               (güvenlik header'ları, locale, CSRF, rate limit)
  → Controller               (ince: girdi okur, servisi çağırır, yanıt döner)
  → Service                  (iş kuralları: DocumentService, OperationService, PdfService ...)
  → Repository               (yalnızca SQL, PDO prepared statements)
  → MySQL / MariaDB
```

PDF işleme `src/Pdf/` altında, view'lardan ve controller'lardan bağımsızdır. Gelecekte REST API veya mobil istemci aynı servisleri kullanır.

## Dizin yapısı (planlanan)

```text
tfb-pdf/
├── .htaccess              # Proje kökü web root olursa: her şeyi public/'e yönlendirir, gerisini kapatır
├── .env.example
├── composer.json
├── bin/                   # CLI: migrate.php, check-translations.php, check-env.php, verify-audit.php
├── bootstrap/app.php
├── config/                # .env'den okunan yapılandırma dizileri
├── cron/cleanup.php       # cPanel Cron Job
├── database/
│   ├── migrations/        # 0001_xxx.sql ... (sıralı, sadece ileri)
│   └── schema.sql         # phpMyAdmin ile içe aktarım için birleşik şema (üretilen)
├── docs/
├── public/                # Tek web'e açık dizin
│   ├── index.php
│   ├── .htaccess
│   └── assets/            # css, js, vendor/bootstrap, vendor/pdfjs (yerel kopya, CDN yok)
├── resources/
│   ├── lang/{tr,en}/*.php # Çeviri dosyaları (grup başına bir dosya)
│   └── views/             # Düz PHP şablonları
├── routes/
├── src/                   # Namespace App\ (PSR-4)
│   ├── Core/              # Request, Response, Router, Container, Config, Env, Session, Csrf, View, Logger, Database, ErrorHandler
│   ├── Http/Controllers/
│   ├── Http/Middleware/
│   ├── Services/
│   ├── Repositories/
│   ├── Pdf/               # PDF okuma/normalizasyon, işlemler (merge, split ...)
│   ├── Tools/             # Harici araç tespiti ve çalıştırma (gs, soffice, tesseract)
│   ├── I18n/
│   └── Exceptions/
├── storage/               # Web'e kapalı
│   ├── documents/         # Orijinaller: documents/ab/<id>/original.pdf
│   ├── versions/          # versions/ab/<id>/v001.pdf ...
│   ├── previews/          # Thumbnail cache
│   ├── temporary/         # İşlem sırasında geçici dosyalar
│   ├── exports/           # Hazırlanan ZIP export'lar
│   ├── sessions/
│   └── logs/
└── tests/
```

## Temel kavramlar

- **Owner (sahip):** İlk sürümde kullanıcı hesabı yok. Her tarayıcıya uzun ömürlü, HttpOnly bir `owner` cookie'si verilir; veritabanında yalnızca HMAC'i tutulur. Belgeye erişim yetkisi bu kimliğe göre kontrol edilir. İleride `users` tablosu eklendiğinde `documents.user_id` ile genişletilir.
- **Document:** Yüklenen dosya. Orijinali (`version_number = 0`) asla değişmez.
- **Version:** Bir işlemin ürettiği her dosya yeni bir version'dır (`v001`, `v002` ...). Bölme işlemi birden çok version üretebilir.
- **Operation:** Bir işlemin kaydı (tip, parametreler, girdi version'ları, durum, süre).
- **Audit event:** Append-only olay kaydı; hash zinciriyle değişiklik tespit edilebilir (tamper-evident).

## İşlem akışı (OperationService)

```text
yetki + girdi doğrulama → rate limit → operation kaydı (processing)
→ PDF işlemi temporary/ altında
→ SHA-256
→ TRANSACTION { version numarası (satır kilidi) → dosyayı versions/'a taşı → version kaydı → operation completed → audit event }
→ hata olursa: rollback + taşınan dosyayı sil + operation failed + audit event (failed)
```

## Çoklu dil (i18n)

- Dosyalar: `resources/lang/{tr,en}/{grup}.php` → iç içe dizi. Anahtar: `grup.anahtar[.alt]` (ör. `upload.success`).
- Kullanım: `__('upload.file_too_large', ['max' => '25 MB'])`, çoğul: `trans_choice('common.page_count', $n)` (`"tekil|çoğul"`, Türkçede tek biçim).
- Eksik anahtar: önce Türkçe yedeğe, sonra anahtarın kendisine düşer; istek içinde `missingKeys()` ile raporlanır.
- Dil seçimi (`LocaleNegotiator`): URL öneki → `tfb_locale` cookie → `Accept-Language` → `tr`.
- Kontrol: `php bin/check-translations.php` (ve `TranslationCompletenessTest`): iki dilde anahtar eşitliği, boş değer, `:yer_tutucu` uyumu, kodda kullanılan ama tanımsız anahtarlar. PHP'de ilk bölümü bir dil grubu olan tüm string sabitleri, JS'de `t('...')` çağrıları taranır.

## Çekirdek (Aşama 7)

| Bileşen | Dosya | Not |
|---|---|---|
| Önyükleme | `bootstrap/app.php`, `bootstrap/services.php` | Env → Config → Container; servisler açıkça tanımlı, autowiring yok |
| Uygulama | `src/Core/Application.php` | `handle(Request): Response` (testlerde web sunucusuz çalışır), `runHttp()`, istek sonrası görevler (`after_response`) |
| HTTP | `src/Http/{Request,Response,FileResponse,UploadedFile,Router}.php` | FileResponse: streaming + tek aralıklı Range, `Content-Disposition` RFC 6266 |
| Middleware | `ForceHttps` → `ResolveLocale` → `VerifyCsrfToken` → controller | Güvenlik başlıkları ve owner cookie hata yanıtları dahil `Application::finalize` içinde |
| Oturum / CSRF | `src/Core/{Session,Csrf}.php` | strict mode, HttpOnly, SameSite=Lax, `storage/sessions`; token + Origin kontrolü |
| Sahiplik | `src/Security/{OwnerContext,Hmac}.php` | `tfb_owner` cookie, DB'de HMAC |
| View | `src/Core/View.php`, `resources/views` | düz PHP, `extend/section/partial`, `icon()` (SVG sprite) |
| Hata | `src/Core/ErrorHandler.php`, `src/Exceptions/*` | kategori → HTTP kodu + çevrilmiş mesaj + hata kodu; ayrıntı yalnızca log'da |
| Log | `src/Core/Logger.php` | JSON satır, request id, hassas anahtarlar maskelenir, argümansız stack trace |

Controller kuralı: `[Sınıf, 'metod']`, imza `metod(Request $request, array $params): Response`.
Frontend: `public/assets/js/app.js` (ES module: `t()`, `api()`, `toast()`), sayfa verisi `<script type="application/json" id="tfb-config">` ile (inline script yok, CSP `script-src 'self'`).

## Storage (Aşama 8)

- `StorageService`: DB'de yalnızca göreli yol. Kalıplar: `documents/ab/<id>/original.<ext>`, `versions/ab/<id>/v001.pdf`, `previews/ab/<id>/<n>/...`, `signatures/ab/<id>/...`, `temporary/<24hex>/`.
- `resolve()`: izinli kök dizin listesi + segment regex, `..`/`\`/null byte reddi, `realpath` ile kök içinde kalma kontrolü (symlink kaçışı).
- `moveIntoPlace()`: hedef varsa reddeder (immutable), `.part-*` geçici adla atomik rename.
- `FilenameSanitizer`: kullanıcı adı yalnızca metadata/indirme adı; yol bileşenleri, kontrol karakterleri, RTL override, baştaki noktalar temizlenir.
- `HashService`: akışlı SHA-256 (`hash_file`), `hash_equals` ile doğrulama.
