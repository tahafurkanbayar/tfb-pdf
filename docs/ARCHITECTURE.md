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
