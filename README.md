# tfb-pdf

Standart cPanel shared hosting üzerinde çalışabilen, açık kaynaklı, Türkçe/İngilizce PDF yönetim ve düzenleme platformu.

*Self-hosted, open-source PDF management and editing platform (Turkish/English) that runs on standard cPanel shared hosting — no SSH, Node.js, Docker or Redis required. This README is in Turkish; the user interface is fully available in both languages.*

Lisans: [MIT](LICENSE) · İlerleme ve karar kayıtları: [docs/](docs/)

---

## İçindekiler

- [Proje Hakkında](#proje-hakkında)
- [Özellikler](#özellikler)
- [Teknoloji Stack'i](#teknoloji-stacki)
- [Sistem Gereksinimleri](#sistem-gereksinimleri)
- [Kurulum](#kurulum)
- [Yerel Geliştirme](#yerel-geliştirme)
- [cPanel Kurulumu](#cpanel-kurulumu)
- [MySQL Kurulumu](#mysql-kurulumu)
- [Composer](#composer)
- [Environment Variables](#environment-variables)
- [Storage](#storage)
- [Güvenlik](#güvenlik)
- [Backup](#backup)
- [Cron Job](#cron-job)
- [PDF İşleme](#pdf-i̇şleme)
- [Opsiyonel Server Tools](#opsiyonel-server-tools)
- [Türkçe / İngilizce Dil Sistemi](#türkçe--i̇ngilizce-dil-sistemi)
- [Testler](#testler)
- [Limitations](#limitations)
- [Üçüncü Taraf Lisansları](#üçüncü-taraf-lisansları)

---

## Proje Hakkında

tfb-pdf, PDF dosyalarını **kendi sunucunuzda** yüklemek, işlemek, önizlemek ve indirmek için geliştirilmiş bir web uygulamasıdır. Dosyalar uygulamanın kurulu olduğu sunucuda saklanır ve varsayılan olarak üçüncü taraf PDF işleme servislerine gönderilmez. Uygulama analiz, izleme veya telemetri aracı kullanmaz.

Temel ilkeler:

- **Orijinal dosya asla değiştirilmez.** Her işlem yeni, değiştirilemez bir sürüm (v001, v002 …) üretir.
- **Her dosyanın SHA-256 özeti** hesaplanır ve saklanır; bütünlük her an yeniden doğrulanabilir.
- **Her işlem kayıt altındadır:** append-only, hash zinciriyle korunan bir audit log tutulur.
- **Paylaşımlı hosting önceliklidir:** sürekli çalışan worker, kuyruk veya build adımı yoktur.

Uygulama hesap sistemi içermez: belgeler tarayıcıya verilen güvenli bir kimlik çerezine (owner cookie) bağlıdır; aynı tarayıcı yalnızca kendi belgelerini görür. Mimari, ileride kullanıcı hesabı eklenebilecek şekilde katmanlıdır.

> **Önemli bilgilendirme.** Bu uygulama resmi belge doğrulama sistemi, nitelikli elektronik imza (QES / eIDAS) sistemi, devlet kimlik doğrulama sistemi veya kurumsal belge yönetişimi (enterprise document governance) sistemi değildir; Adobe Acrobat seviyesinde tam bir PDF editörü de değildir. İmza özelliği **basit elektronik imzadır**.

## Özellikler

| Özellik | Açıklama |
|---|---|
| Yükleme | Sürükle-bırak, PDF içerik doğrulaması (uzantıya güvenilmez), boyut/sayfa sınırları, güvenli dosya adları |
| Önizleme | PDF.js ile sayfa küçük resimleri ve sayfa görüntüleyici; küçük resimler sunucuda önbelleğe alınır |
| Birleştirme | Birden çok PDF'i sürükle-bırakla sıralayıp birleştirme |
| Bölme | Her sayfa ayrı, sayfa aralıkları (`1-3, 5, 8-10`) veya seçili sayfaları çıkarma; çoklu sonuçlar ZIP olarak indirilebilir |
| Sıralama | Sayfaları sürükle-bırak (veya klavye) ile sıralama ve sayfa kaldırma |
| Döndürme | Sayfa bazında veya toplu 90° adımlarla, kayıpsız |
| Sıkıştırma | Ghostscript varsa onunla, yoksa PHP ile görüntü optimizasyonu; kazanç yoksa yeni sürüm oluşturulmaz |
| Filigran | Metin, konum/döşeme, açı, opaklık, boyut, renk, sayfa aralığı; Türkçe karakter desteği |
| Karartma (redaction) | Seçilen alanlar kalıcı olarak yok edilir: sayfa görüntüye dönüştürülür, alttaki metin dosyada kalmaz |
| OCR | Sunucuda Tesseract varsa taranmış PDF'lere aranabilir metin katmanı (yoksa açık mesajla kapalı) |
| Office → PDF | Sunucuda LibreOffice varsa Word/Excel/PowerPoint dönüştürme (yoksa açık mesajla kapalı) |
| Basit imza | İmza alanları, imzalayanlar, davet bağlantısı (SMTP varsa e-posta), onay kaydı, çizim/yazılı imza, ret/iptal/süre, imza sertifika sayfası ve SHA-256 |
| Sürümler | Sıralı, değiştirilemez sürümler; hangi sürümden üretildiği gösterilir; araçlar belirli bir sürüm üzerinde çalışabilir |
| Bütünlük | Sunucuda tüm sürümlerin özet doğrulaması; tarayıcıda yerel dosyayı sunucuya göndermeden karşılaştırma |
| Audit log | Yükleme, tüm işlemler, indirme, silme, süre değişikliği, imza olayları; girdi/çıktı özetleriyle |
| Saklama süresi | 1 gün / 7 gün / 30 gün / manuel silme; cron veya fırsatçı temizlik ile otomatik silme |
| Dışa aktarma | Belge veya tüm veriler: dosyalar, sürümler, metadata, işlem geçmişi, audit kayıtları, SHA-256 manifest (ZIP) |
| Silme | Onaylı, CSRF korumalı, audit'e kaydedilir; belge ve tüm sürümleri kalıcı olarak silinir |
| Dil | Türkçe ve İngilizce, URL önekli (`/tr/...`, `/en/...`), eksiksiz çeviri kontrolü |
| Kurulum | SSH gerektirmeyen web kurulum sayfası (`/install`): ortam kontrolü ve migration |

## Teknoloji Stack'i

- **Backend:** PHP 8.2+ (hedef 8.3+), framework yok — katmanlı, nesne yönelimli PHP (Controller → Service → Repository → PDO)
- **Veritabanı:** MySQL 8.0+ veya MariaDB 10.4+ (PDO, prepared statements, `utf8mb4_unicode_ci`)
- **PDF:** [FPDI](https://github.com/Setasign/FPDI) + [tFPDF](http://fpdf.org/en/script/script92.php) (Unicode/DejaVu), kendi PDF ayrıştırıcısı (xref stream, object stream)
- **Frontend:** Bootstrap 5.3 (açık/koyu/sistem teması), vanilla JavaScript (ES modules), [PDF.js](https://mozilla.github.io/pdf.js/), Inter fontu — hepsi yerel kopya, CDN yok, build adımı yok
- **E-posta (opsiyonel):** PHPMailer (SMTP)
- **Test:** PHPUnit 11

Kullanılmayanlar (bilinçli): Laravel/Symfony vb. framework, Node.js/npm, Python, Docker, Redis, PostgreSQL, MongoDB, analytics/telemetry, üçüncü taraf CDN.

## Sistem Gereksinimleri

| Gereksinim | Not |
|---|---|
| PHP 8.2 veya üzeri | 8.3+ önerilir. Daha eski sürümde uygulama anlaşılır bir "sürümü yükseltin" mesajı gösterir |
| PHP eklentileri (zorunlu) | `pdo`, `pdo_mysql`, `mbstring`, `json`, `zlib`, `hash`, `session` |
| PHP eklentileri (önerilen) | `fileinfo` (MIME doğrulama), `gd` (küçük resim önbelleği, imza görselleri, PHP sıkıştırma), `zip` (toplu indirme, dışa aktarma), `openssl` (şifreli SMTP) |
| MySQL 8.0+ / MariaDB 10.4+ | Kullanıcıya veritabanında tüm yetkiler (ALL PRIVILEGES) |
| Apache + `mod_rewrite` | cPanel'de standarttır; `.htaccess` dosyaları hazırdır |
| php.ini | `memory_limit` ≥ 128M, `max_execution_time` ≥ 60 önerilir; `upload_max_filesize` ve `post_max_size` ≥ `MAX_UPLOAD_SIZE` |
| Opsiyonel araçlar | Ghostscript, LibreOffice, Tesseract — bkz. [Opsiyonel Server Tools](#opsiyonel-server-tools) |

Önerilen eklentiler veya araçlar yoksa uygulama çalışmaya devam eder; yalnızca ilgili özellik kapanır ve kullanıcıya açıkça söylenir.

## Kurulum

Kısa özet (ayrıntılar aşağıdaki bölümlerde):

1. Dosyaları sunucuya yükleyin (`vendor/` dizini dahil — bkz. [Composer](#composer)).
2. Bir MySQL/MariaDB veritabanı ve kullanıcısı oluşturun.
3. `.env.example` dosyasını `.env` adıyla kopyalayıp doldurun (`APP_KEY`, `APP_URL`, `DB_*`, `INSTALL_KEY`).
4. `storage/` dizininin yazılabilir olduğundan emin olun.
5. Tabloları oluşturun: tarayıcıdan `/install`, ya da SSH ile `php bin/migrate.php`, ya da phpMyAdmin'den `database/schema.sql`.
6. Cron Job ekleyin: `php /tam/yol/cron/cleanup.php` (saatte bir).
7. Siteyi açın; kurulum bitince `.env` içindeki `INSTALL_KEY` değerini boşaltın.

## Yerel Geliştirme

XAMPP (Windows) veya benzeri bir Apache + PHP + MariaDB ortamı yeterlidir.

```bash
git clone https://github.com/tahafurkanbayar/tfb-pdf.git
cd tfb-pdf
composer install                   # Composer kurulu değilse: php composer.phar install (bkz. Composer)
cp .env.example .env               # değerleri doldurun; yerelde APP_ENV=local
php bin/migrate.php                # tabloları oluşturur
php bin/migrate.php status         # durum
```

- Proje `htdocs/tfb-pdf` altındaysa `http://localhost/tfb-pdf/` adresinden açılır; kökteki `.htaccess` istekleri `public/` dizinine yönlendirir. `APP_URL=http://localhost/tfb-pdf` yazın.
- Bağımsız sanal host kullanıyorsanız document root'u doğrudan `public/` yapın.
- `APP_ENV=local` ve `APP_DEBUG=true` iken hata ayrıntıları sayfada görünür; canlı ortamda **asla** kullanmayın.

Yararlı komutlar:

```bash
php bin/migrate.php [migrate|status|schema]   # migration; schema: database/schema.sql'i yeniden üretir
php bin/check-translations.php                # TR/EN çeviri dosyalarının eksiksizliği
php bin/verify-audit.php                      # audit log hash zincirini doğrular
php cron/cleanup.php                          # temizliği elle çalıştırır
php vendor/bin/phpunit                        # testler
```

## cPanel Kurulumu

Bu adımlar **SSH erişimi olmayan** bir kullanıcı için yazılmıştır. Menü adları cPanel temasına göre küçük farklılıklar gösterebilir.

1. **cPanel'e giriş yapın.**
2. **PHP sürümünü seçin.** "Select PHP Version" (CloudLinux) veya "MultiPHP Manager" ile alan adınız için PHP 8.3 (en az 8.2) seçin. "Extensions" sekmesinde `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip` eklentilerinin işaretli olduğundan emin olun. Aynı ekranda veya "MultiPHP INI Editor"de `upload_max_filesize` ve `post_max_size` değerlerini (ör. `32M`) ayarlayabilirsiniz.
3. **MySQL veritabanı oluşturun.** "MySQL Databases" → "Create New Database" (ör. `tfbpdf`). cPanel adı kullanıcı adınızla öneklenir: `kullanici_tfbpdf`.
4. **Veritabanı kullanıcısı oluşturun.** Aynı sayfada "Add New User" ile güçlü parolalı bir kullanıcı oluşturun, ardından "Add User To Database" ile kullanıcıyı veritabanına ekleyip **ALL PRIVILEGES** verin.
5. **Dosyaları yükleyin.** Yerel bilgisayarınızda `composer install --no-dev` çalıştırın (bkz. [Composer](#composer)), proje klasörünü (vendor/ dahil) ZIP'leyin; "File Manager" ile yükleyip "Extract" ile açın. İki yerleşimden birini seçin:
   - **Önerilen — document root `public/`:** Projeyi ana dizininize (ör. `/home/kullanici/tfb-pdf`) çıkarın. Alt alan adı veya ek alan adı oluştururken ("Domains") document root olarak `/home/kullanici/tfb-pdf/public` yazın. Böylece `storage/`, `.env`, `src/` web'den hiç erişilemez.
   - **Ana alan adı (`public_html`) değiştirilemiyorsa:** Projeyi `/home/kullanici/tfb-pdf` dizinine çıkarın, yalnızca `public/` dizininin **içeriğini** (gizli `.htaccess` ve `.user.ini` dosyaları dahil) `public_html/` içine kopyalayın ve `public_html/app-root.php` dosyasını oluşturun:
     ```php
     <?php return '/home/kullanici/tfb-pdf';
     ```
     (Bu dosya `.htaccess` tarafından web erişimine kapatılır.)
   - Tüm projeyi doğrudan `public_html/` içine koymak da çalışır (kökteki `.htaccess` her şeyi `public/` altına yönlendirir ve hassas dosyaları reddeder), ancak bu yalnızca bir güvenlik ağıdır; ilk iki yöntem tercih edilmelidir.
6. **`.env` oluşturun.** File Manager'da `.env.example` dosyasını kopyalayıp adını `.env` yapın ("Settings" → "Show Hidden Files" ile gizli dosyaları görünür yapmanız gerekebilir).
7. **Bilgileri girin.** `.env` dosyasını "Edit" ile açıp en az şunları doldurun:
   ```ini
   APP_URL=https://pdf.example.com
   APP_KEY=                     # en az 32 karakter rastgele; kurulum sayfası sizin için bir değer önerir
   INSTALL_KEY=                 # en az 16 karakter rastgele; yalnızca kurulum süresince
   DB_HOST=localhost
   DB_DATABASE=kullanici_tfbpdf
   DB_USERNAME=kullanici_tfbpdf
   DB_PASSWORD=...
   ```
8. **Composer bağımlılıkları.** SSH yoksa `vendor/` dizinini 5. adımda zaten yüklediniz. SSH ve Composer varsa sunucuda `composer install --no-dev --optimize-autoloader` de çalıştırabilirsiniz.
9. **Storage izinleri.** File Manager'da `storage/` ve alt dizinlerinin izinlerini `755` yapın (bazı sunucularda `775` gerekir). `.env` için `640` veya `600` önerilir. Uygulama eksik alt dizinleri kendisi oluşturur.
10. **Migration çalıştırın.** Tarayıcıda `https://alanadiniz/install` adresini açın, `INSTALL_KEY` değerini girin. Sayfa PHP, eklentiler, php.ini limitleri, `.env`, depolama, veritabanı bağlantısı ve sürümü, opsiyonel araçlar için kontrolleri gösterir; sorun varsa çözümünü cPanel menü adlarıyla söyler. **"Migration'ları çalıştır"** düğmesiyle tablolar oluşturulur. Alternatif: phpMyAdmin → veritabanını seçin → "Import" → `database/schema.sql`.
11. **Cron Job ayarlayın.** Bkz. [Cron Job](#cron-job). Kurulum sayfası sunucunuza uygun komutu gösterir.
12. **Siteyi açın.** Bir PDF yükleyip deneyin. Ardından:
    - `.env` içindeki `INSTALL_KEY` değerini **boşaltın** (kurulum sayfası 404 döner),
    - "SSL/TLS Status" → AutoSSL ile sertifika kurulduktan sonra `FORCE_HTTPS=true` (isteğe bağlı olarak `HSTS_ENABLED=true`) yapın,
    - `APP_ENV=production` ve `APP_DEBUG=false` olduğundan emin olun.

**Güncelleme:** Yeni sürümün dosyalarını (`vendor/` dahil) yükleyin, `.env` ve `storage/` dizinine dokunmayın; ardından `/install` sayfasından (geçici olarak `INSTALL_KEY` vererek) bekleyen migration'ları çalıştırın. Öncesinde [yedek](#backup) alın.

## MySQL Kurulumu

- Desteklenen sunucular: **MySQL 8.0+** ve **MariaDB 10.4+**. Kurulum sayfası sürümü kontrol eder.
- Veritabanı karakter seti: `utf8mb4`, collation: `utf8mb4_unicode_ci` (tablolar bunu açıkça belirtir).
- Uygulama her bağlantıda strict SQL modu ve UTC saat dilimi ayarlar; sunucu varsayılanlarına bağımlı değildir.
- Şema `database/migrations/NNNN_*.sql` dosyalarındadır ve yalnızca ileri yönlüdür. Uygulanan migration'lar `migrations` tablosunda dosya özetiyle tutulur; sonradan değiştirilmiş bir migration dosyası tespit edilir ve uyarı verilir. Aynı anda iki migration çalıştırılması `GET_LOCK` ile engellenir.
- Üç kurulum yolu:
  1. Web: `/install` (SSH gerekmez)
  2. CLI: `php bin/migrate.php`
  3. phpMyAdmin: boş veritabanına `database/schema.sql` içe aktarımı. Bu dosya migration'lardan üretilir (`php bin/migrate.php schema`) ve `migrations` tablosunu da doldurur; sonraki güncellemeler normal migration akışıyla devam eder.
- Tablolar: `documents`, `document_versions`, `operations`, `audit_events`, `file_expiry`, `signature_*`, `rate_limits`, `settings`, `migrations`. Ayrıntı: [docs/DATABASE.md](docs/DATABASE.md).

## Composer

Bağımlılıklar `composer.json` / `composer.lock` ile sabitlenmiştir. Bilgisayarınızda Composer kurulu değilse [getcomposer.org/download](https://getcomposer.org/download/) adresinden tek dosyalık `composer.phar` indirip proje köküne koyabilir ve aşağıdaki komutları `php composer.phar ...` biçiminde çalıştırabilirsiniz (`composer.phar` depoya dahil değildir, `.gitignore`'dadır).

| Ortam | Komut |
|---|---|
| Geliştirme | `composer install` |
| Canlıya yüklemek için (yerelde) | `composer install --no-dev --optimize-autoloader` |
| Sunucuda (SSH + Composer varsa) | `composer install --no-dev --optimize-autoloader` |

**Hostingde Composer yoksa:** bağımlılıkları yerel bilgisayarınızda `--no-dev` ile kurun, oluşan `vendor/` dizinini projeyle birlikte yükleyin. `vendor/` eksikse uygulama ham hata yerine "vendor/ missing" mesajı verir. Yüklemeden sonra geliştirme makinenizde tekrar `composer install` çalıştırarak PHPUnit gibi geliştirme bağımlılıklarını geri getirebilirsiniz.

Platform PHP sürümü `composer.json` içinde `8.2.12` olarak sabitlenmiştir; böylece bağımlılıklar PHP 8.2 ile uyumlu sürümlerde kalır.

## Environment Variables

Tüm ayarlar `.env` dosyasından okunur (`.env.example` her değişkeni açıklamasıyla içerir). `.env` **asla commit edilmez** ve web'den erişime kapalıdır. Aynı adla tanımlanmış sunucu ortam değişkenleri `.env`'den önceliklidir.

| Değişken | Varsayılan | Açıklama |
|---|---|---|
| `APP_NAME` | `TFB PDF` | Arayüzde görünen ad |
| `APP_ENV` | `production` | `production` veya `local` |
| `APP_DEBUG` | `false` | Yalnızca `APP_ENV=local` ile birlikte hata ayrıntısı gösterir |
| `APP_URL` | (boş) | Sitenin tam adresi, sonda `/` olmadan. Boşsa istekten türetilir; e-posta bağlantıları için doldurun |
| `APP_KEY` | (boş) | **Zorunlu**, en az 32 karakter rastgele. Owner çerezi, rate limit anahtarları, imza bağlantıları için HMAC anahtarı. Değiştirilirse mevcut tarayıcılar belgelerine erişimi kaybeder |
| `APP_TIMEZONE` | `Europe/Istanbul` | Tarihlerin gösterimi (veritabanında UTC) |
| `FORCE_HTTPS` | `false` | HTTP isteklerini HTTPS'e yönlendirir, çerezleri `Secure` yapar |
| `HSTS_ENABLED` | `false` | `Strict-Transport-Security` başlığı (yalnızca HTTPS kalıcıysa) |
| `TRUSTED_PROXIES` | (boş) | Cloudflare / reverse proxy IP'leri (virgülle) |
| `LOG_LEVEL` | `info` | `debug`, `info`, `warning`, `error` |
| `INSTALL_KEY` | (boş) | `/install` sayfasının anahtarı (en az 16 karakter); boş = sayfa kapalı |
| `DB_HOST` / `DB_PORT` / `DB_SOCKET` | `localhost` / `3306` / (boş) | Veritabanı sunucusu |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | (boş) | Veritabanı bilgileri |
| `STORAGE_PATH` | `storage/` | Dosya deposu; mümkünse web kökü dışında mutlak yol |
| `DEFAULT_EXPIRY` | `7d` | Yeni belgelerin saklama süresi: `1d`, `7d`, `30d`, `never` |
| `OPPORTUNISTIC_CLEANUP` | `true` | Cron yoksa istekler sırasında küçük temizlik |
| `TEMPORARY_TTL_HOURS` | `6` | Geçici dosyaların ömrü |
| `PREVIEW_TTL_DAYS` | `14` | Küçük resim önbelleğinin ömrü |
| `MAX_UPLOAD_SIZE` | `25M` | Dosya başına üst sınır (php.ini limitleri de uygulanır) |
| `MAX_FILES_PER_OPERATION` | `20` | Birleştirmede en fazla dosya |
| `MAX_PAGES_PER_DOCUMENT` | `500` | Belge başına en fazla sayfa |
| `MAX_STORAGE_PER_OWNER` | `500M` | Tarayıcı başına toplam depolama (`0` = sınırsız) |
| `RATE_LIMIT_UPLOADS_PER_HOUR` | `60` | IP başına saatlik yükleme (`0` = kapalı) |
| `RATE_LIMIT_OPERATIONS_PER_HOUR` | `120` | IP başına saatlik işlem |
| `RATE_LIMIT_SIGNATURES_PER_HOUR` | `30` | IP başına saatlik imza isteği |
| `GHOSTSCRIPT_PATH` / `LIBREOFFICE_PATH` / `TESSERACT_PATH` | (boş) | Boş: otomatik ara · mutlak yol · `disabled` |
| `TESSERACT_LANGS` | `tur+eng` | OCR dilleri |
| `EXTERNAL_TOOL_TIMEOUT` | `120` | Harici araç zaman aşımı (saniye) |
| `TOOL_DETECTION_CACHE_TTL` | `3600` | Araç tespit sonucunun önbellek süresi (saniye) |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USERNAME` / `SMTP_PASSWORD` | (boş) / `587` | Boş `SMTP_HOST` = e-posta kapalı |
| `SMTP_ENCRYPTION` / `SMTP_TIMEOUT` | `tls` / `15` | `tls`, `ssl`, `none` |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | (boş) / `TFB PDF` | Gönderen |
| `SIGNATURE_INVITE_DAYS` | `14` | İmza davetlerinin geçerlilik süresi |

Testler için ayrıca `DB_TEST_DATABASE` (varsayılan `tfb_pdf_test`) kullanılır; bkz. [Testler](#testler).

## Storage

Dosyalar veritabanında değil, dosya sisteminde tutulur; veritabanında yalnızca göreli yol, boyut ve SHA-256 özeti bulunur.

```text
storage/
├── documents/ab/<id>/original.pdf   # Orijinaller (asla değiştirilmez)
├── versions/ab/<id>/v001.pdf ...    # İşlem sonuçları (değiştirilemez)
├── previews/                        # Küçük resim önbelleği (silinebilir)
├── temporary/                       # İşlem sırasındaki geçici dosyalar
├── exports/                         # Dışa aktarma ZIP'leri (kısa ömürlü)
├── signatures/                      # İmza görselleri
├── sessions/  logs/  cache/
└── .htaccess                        # Require all denied
```

- Web'den **doğrudan erişilemez**: dosyalar yalnızca yetki ve yol kontrolü yapan PHP uç noktası üzerinden indirilir; gerçek dosya yolu kullanıcıya gösterilmez.
- Yollar beklenen dizin dışına çıkamaz (path traversal koruması); var olan bir dosyanın üzerine yazma reddedilir (atomik taşıma).
- Konum `STORAGE_PATH` ile web kökü dışına taşınabilir (önerilir). Kurulum sayfası depolamanın `public/` içinde olup olmadığını kontrol eder.
- Temizlik: süresi dolan belgeler, eski geçici dosyalar, dışa aktarmalar, önizleme önbelleği, oturum dosyaları ve yetim dizinler [cron](#cron-job) ile silinir.

## Güvenlik

- **CSRF:** Durum değiştiren her istek (POST/PUT/DELETE) token ister; ayrıca `Origin` başlığı kendi alan adıyla eşleşmelidir. Silme GET ile yapılamaz.
- **XSS:** Tüm çıktılar `e()` ile kaçışlanır; sıkı Content Security Policy (`script-src 'self'`, inline script yok), `frame-ancestors 'none'`, `X-Content-Type-Options`, `Referrer-Policy`, `Cross-Origin-Opener-Policy`.
- **SQL injection:** Yalnızca PDO prepared statements (gerçek, emüle edilmemiş).
- **Dosya güvenliği:** PDF içerik doğrulaması (imza, yapı, şifreleme kontrolü), uzantı/MIME tutarlılığı, güvenli ve normalize dosya adları, makro içeren Office paketlerinin reddi.
- **Erişim:** Belgeler owner çerezine (HMAC'li) bağlıdır; başka tarayıcıdan erişim 404 döner. İmza bağlantıları tahmin edilemez token'lardır.
- **Rate limiting:** IP başına saatlik yükleme, işlem ve imza sınırları (veritabanında, IP düz metin saklanmadan).
- **Hata yönetimi:** Kullanıcıya teknik ayrıntı (SQL, stack trace, dosya yolu, kimlik bilgisi) gösterilmez; ayrıntılar `storage/logs/` altına, hassas veriler maskelenerek yazılır. Hatalar istek kimliği ile ilişkilendirilir. PHP'nin uygulama başlamadan bastığı uyarılar (ör. `post_max_size` aşımı) için `display_errors` hem `public/.htaccess` (mod_php) hem `public/.user.ini` (PHP-FPM/CGI/LiteSpeed) ile kapatılır.
- **Harici araçlar:** Kabuk kullanılmadan, argüman dizisiyle, zaman aşımıyla ve Ghostscript'te `-dSAFER` ile çalıştırılır.
- **`.htaccess`:** `.env`, `.git`, `composer.*`, `*.sql`, `*.log`, `*.md` vb. dosyalar ile `storage/`, `src/`, `config/` dizinleri reddedilir; dizin listeleme kapalıdır. HTTPS yönlendirmesi için hazır (yorum satırında) kurallar vardır.
- **Kurulum sayfası:** Yalnızca `INSTALL_KEY` doluyken açıktır; yanlış denemeler kilitlenir. Kurulumdan sonra anahtarı boşaltın.

Canlıya almadan önce kontrol listesi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` dolu, `INSTALL_KEY` boş, HTTPS + `FORCE_HTTPS=true`, `https://alanadiniz/.env` ve `https://alanadiniz/storage/` adreslerinin **403/404** döndüğünü tarayıcıda doğrulayın.

## Backup

Uygulama kendi başına sunucu yedeği **almaz**. Kullanıcıların kendi verilerini indirmesi için "Dışa aktar" özelliği vardır, ancak bu bir sunucu yedeği değildir. Tam bir yedek üç parçadan oluşur ve **aynı anda** alınmalıdır (veritabanı kayıtları ile dosyalar birbirine bağlıdır):

| Parça | İçerik |
|---|---|
| MySQL veritabanı | Belgeler, sürümler, işlemler, audit log, imza kayıtları |
| Document storage | `storage/documents/`, `storage/versions/`, `storage/signatures/` (önizleme, geçici dosya ve log dizinleri isteğe bağlı) |
| Configuration | `.env` (özellikle `APP_KEY` — kaybolursa mevcut tarayıcılar belgelerine erişemez ve imza bağlantıları geçersizleşir) |

### cPanel Backup ile

- **cPanel → "Backup" / "Backup Wizard"**: "Download a Full Account Backup" tüm hesabı (ev dizini, veritabanları, e-posta) tek arşiv olarak alır. Ayrıca "Partial Backups" bölümünden **Home Directory** (proje + `storage/` + `.env`) ve **MySQL Databases** (veritabanınız) ayrı ayrı indirilebilir.
- Hosting sağlayıcınızın otomatik yedekleri (ör. JetBackup) varsa, hem dosyaları hem veritabanını kapsadığını kontrol edin.
- Geri yükleme: aynı ekrandan "Restore a Home Directory Backup" ve "Restore a MySQL Database Backup".

### Manuel yedek

1. **Veritabanı:** phpMyAdmin → veritabanını seçin → "Export" → "Quick", format SQL. SSH varsa: `mysqldump --single-transaction --default-character-set=utf8mb4 -u KULLANICI -p VERITABANI > yedek.sql`
2. **Dosyalar:** File Manager'da `storage/` dizinine sağ tıklayıp "Compress" → ZIP, ardından indirin. SSH varsa: `tar -czf storage-yedek.tar.gz storage/documents storage/versions storage/signatures`
3. **Yapılandırma:** `.env` dosyasını indirip güvenli bir yerde (şifreli) saklayın.

Geri yüklemeden sonra `php bin/verify-audit.php` ile audit zincirini ve belge sayfalarındaki "Bütünlüğü doğrula" ile dosya özetlerini kontrol edebilirsiniz.

## Cron Job

Süresi dolan belgeleri ve geçici dosyaları silmek için `cron/cleanup.php` saatte bir çalıştırılmalıdır.

1. cPanel → **"Cron Jobs"**.
2. "Common Settings" → **"Once Per Hour (0 * * * *)"**.
3. "Command" alanına (yolu kendi hesabınıza göre düzenleyin):
   ```text
   /usr/local/bin/php /home/KULLANICI/tfb-pdf/cron/cleanup.php
   ```
4. "Add New Cron Job".

Notlar:

- Cron'daki PHP sürümü web sürümünden farklı olabilir. PHP 8.2'den eskiyse script açık bir hata mesajıyla durur; bu durumda sürüme özel yolu kullanın, ör. `/opt/cpanel/ea-php83/root/usr/bin/php`. `/install` sayfası sunucunuz için komutu gösterir.
- Script şunları temizler: süresi dolan belgeler ve tüm sürümleri (audit'e `expiry` olarak yazılır), eski geçici dosyalar, dışa aktarma ZIP'leri, eski önizleme önbelleği, oturum dosyaları, yetim dizinler, eski rate limit kayıtları.
- Aynı anda iki temizlik çalışmaz (kilit). Çıkış kodları: `0` tamam, `1` hata, `3` başka temizlik çalışıyor.
- **Cron kurulamıyorsa:** `OPPORTUNISTIC_CLEANUP=true` (varsayılan) ile site ziyaretleri sırasında, yanıt gönderildikten sonra küçük ve süre sınırlı temizlik adımları çalışır. Manuel temizlik için SSH'de `php cron/cleanup.php` çalıştırılabilir.

## PDF İşleme

- Tüm temel işlemler **saf PHP** ile yapılır (FPDI + tFPDF): birleştirme, bölme, sıralama, döndürme, filigran, PHP tabanlı sıkıştırma, karartma, imza sayfası. Harici araç gerekmez.
- Yüklemede PDF yapısı uygulamanın kendi ayrıştırıcısıyla doğrulanır: klasik xref tablosu, xref stream, object stream ve hibrit dosyalar desteklenir; şifreli PDF'ler reddedilir.
- **Her işlem aynı akıştan geçer:** girdi sürümünün özeti doğrulanır → işlem geçici dizinde yapılır → çıktı doğrulanır ve özetlenir → yeni sürüm atomik olarak yerine taşınır → veritabanı kaydı ve audit olayı tek transaction'da yazılır. Hata olursa işlem "failed" olarak kaydedilir ve yarım dosya kalmaz.
- Değişiklik olmayan işlemler (ör. küçülmeyen sıkıştırma, aynı sıra) yeni sürüm oluşturmaz.
- FPDI sayfaları yeniden oluşturduğu için bazı PDF öğeleri (bağlantılar, notlar, form alanları, yer imleri, dijital imzalar) yeni sürüme aktarılmaz; işlem sonucunda kullanıcıya **uyarı** gösterilir. Bkz. [Limitations](#limitations).
- Önizleme tarayıcıda PDF.js ile yapılır; küçük resimler bir kez çizilip sunucuda (GD ile yeniden kodlanarak) önbelleğe alınır.

## Opsiyonel Server Tools

Standart shared hostingde genellikle bulunmayan araçlar **zorunlu değildir**. Uygulama her birini otomatik tespit eder (PATH, bilinen kurulum yerleri, `--version` doğrulaması; sonuç önbelleğe alınır) ve yoksa ilgili özelliği kapatıp kullanıcıya açık bir mesaj gösterir.

| Araç | Kullanım | Yoksa |
|---|---|---|
| Ghostscript (`gs`) | Daha güçlü sıkıştırma; karartmada sayfa görüntüsünün sunucuda üretilmesi; OCR için sayfa görüntüsü | PHP sıkıştırma ve tarayıcıda (PDF.js) üretilen sayfa görüntüsü kullanılır |
| LibreOffice (`soffice`) | Office → PDF dönüştürme | Office yüklemesi açık bir mesajla reddedilir |
| Tesseract (+ Ghostscript veya `pdftoppm`) | OCR | OCR aracı "kullanılamıyor" olarak gösterilir |

- Yolları `.env` ile verebilirsiniz (`GHOSTSCRIPT_PATH=/usr/bin/gs`), `disabled` yazarak kapatabilirsiniz.
- Araçların çalışması için PHP'de `proc_open` açık olmalıdır; birçok hosting bunu kapatır. Kurulum sayfası bu durumu gösterir.
- Bu araçların geliştirme ortamında **gerçek kurulumlarıyla test edilmediğini**, komut ve geri dönüş davranışlarının sahte çalıştırıcılarla test edildiğini not edin (bkz. [Limitations](#limitations)).

## Türkçe / İngilizce Dil Sistemi

- Arayüzdeki her metin çeviri anahtarıyla üretilir: PHP'de `__('grup.anahtar')`, JavaScript'te `t('grup.anahtar')`. Çeviri dosyaları `resources/lang/tr/*.php` ve `resources/lang/en/*.php` altındadır (grup başına bir dosya).
- URL dil önekli: `/tr/tools/merge`, `/en/tools/merge`. Önek yoksa sırasıyla dil çerezi, tarayıcı dili (`Accept-Language`) ve varsayılan `tr` kullanılır. Dil değiştirici aynı sayfada kalır.
- API yanıtları (hata/başarı mesajları) arayüzün diline göre (`X-Locale` başlığı) çevrilir.
- Tarih ve sayı biçimleri dile göredir (TR `13,5 KB`, `08.10.2026 14:30`; EN `13.5 KB`, `2026-10-08 14:30`) ve sunucuda biçimlenir.
- JavaScript'e yalnızca sayfanın ihtiyaç duyduğu çeviri grupları gönderilir.
- **Eksik çeviri kontrolü:** `php bin/check-translations.php` iki dildeki anahtarların ve `:yer_tutucu`ların birebir aynı olduğunu doğrular (CI'da veya her değişiklikten sonra çalıştırın). Ayrıca testler tüm sayfaları iki dilde açıp çözülmemiş anahtar ve İngilizce sayfada Türkçe metin arar.
- Yeni dil eklemek: `resources/lang/<kod>/` dizinini oluşturup tüm dosyaları çevirin ve `config/i18n.php` içindeki `locales` listesine ekleyin. Tarih biçimi `src/Support/DateFormatter.php` içinde dile göre seçilir (şu an `tr` dışındaki diller `Y-m-d` kullanır).

## Testler

```bash
php vendor/bin/phpunit                         # tümü
php vendor/bin/phpunit --testsuite Unit        # yalnızca unit (veritabanı gerekmez)
php -d extension=zip vendor/bin/phpunit        # zip eklentisi kapalı bir PHP'de zip dallarını da çalıştırmak için
php bin/check-translations.php
```

- Entegrasyon ve feature testleri ayrı bir test veritabanı kullanır: `.env` bağlantı bilgileriyle, adı `DB_TEST_DATABASE` (varsayılan `tfb_pdf_test`) olan veritabanı. Güvenlik için adı `_test` ile bitmeyen bir veritabanında testler çalışmaz. Veritabanı yoksa bu testler atlanır. Testler geçici storage dizinleri kullanır; geliştirme verilerine dokunmaz.
- **Unit:** dosya adı temizleme, dosya doğrulama, SHA-256, sayfa aralığı ayrıştırma, birleştirme, bölme, döndürme, sürüm numaralama, saklama süresi, dil sistemi, PDF ayrıştırıcı, sıkıştırma, depolama güvenliği, kurulum koruması.
- **Integration:** Upload → Database → PDF işleme → yeni sürüm → hash → audit zinciri; her araç, sürümleme, bütünlük, audit kapsamı, temizlik, dışa aktarma, imza akışı.
- **Feature (uçtan uca, HTTP):** gerçek PDF ile yükle → işle → sürüm → hash → audit → indir; tüm sayfaların iki dilde eksiksiz çizilmesi; güvenlik (CSRF, yetki, rate limit); kurulum sayfası.
- Son çalıştırma sonuçları ve kullanılan komutlar: [docs/COMMANDS_LOG.md](docs/COMMANDS_LOG.md).
- Tarayıcı otomasyonu (Selenium/Playwright) Node/npm gerektirmemek için test paketinde yoktur; tarayıcı tarafı headless Chrome ile elle doğrulanmıştır.

## Limitations

Ayrıntılı ve güncel liste: [docs/LIMITATIONS.md](docs/LIMITATIONS.md). Özetle:

- **Kapsam dışı (bilinçli):** nitelikli elektronik imza (QES/eIDAS), PDF'e kriptografik dijital imza (PAdES), resmi kimlik doğrulama, kullanıcı hesapları/social login/ödeme, analytics/telemetry.
- **İmza:** basit elektronik imzadır; imzalayanın kimliği doğrulanmaz, bağlantıya sahip olan imzalayabilir.
- **Şifreli PDF'ler** desteklenmez (yüklemede açık mesajla reddedilir).
- İşlemlerden sonra **bağlantılar, notlar, form alanları, yer imleri, ek dosyalar ve dijital imzalar** yeni sürüme aktarılmaz (görünüm korunur, kullanıcı uyarılır).
- Karartılan sayfalar görüntüye dönüşür (metin seçilemez, 150 DPI); tek işlemde en fazla 30 sayfa.
- PHP tabanlı sıkıştırma yalnızca JPEG görüntüleri ve sıkıştırılmamış akışları optimize eder; metin ağırlıklı PDF'lerde kazanç genellikle olmaz.
- Çok büyük birleştirmeler PHP `memory_limit` sınırına takılabilir.
- Ghostscript, LibreOffice, Tesseract ve SMTP gönderimi geliştirme ortamında **gerçek araçlarla test edilmedi** (sahte çalıştırıcılarla test edildi); Office dönüşümü ve OCR yalnızca bu araçlar sunucuda varsa çalışır.
- Toplu ZIP indirme ve dışa aktarma PHP `zip` eklentisi gerektirir.
- Kurulum sayfasındaki "PHP 8.2'den eski" mesajı geliştirme ortamında eski bir PHP sürümüyle denenmedi.
- `public/.user.ini` (display_errors) yalnızca PHP-FPM/CGI sunucularda etkilidir; geliştirme ortamı mod_php olduğundan bu dosyanın etkisi denenmedi (mod_php karşılığı `.htaccess` kuralı denendi).

## Üçüncü Taraf Lisansları

| Bileşen | Sürüm | Lisans | Konum |
|---|---|---|---|
| [setasign/fpdi](https://github.com/Setasign/FPDI) | 2.6.8 | MIT | `vendor/` |
| [setasign/tfpdf](https://github.com/Setasign/tFPDF) | 1.33 | LGPL-2.1 | `vendor/` |
| [phpmailer/phpmailer](https://github.com/PHPMailer/PHPMailer) | 7.1.1 | LGPL-2.1-only | `vendor/` |
| [Bootstrap](https://getbootstrap.com/) | 5.3.8 | MIT | `public/assets/vendor/bootstrap/LICENSE` |
| [Bootstrap Icons](https://icons.getbootstrap.com/) (seçilmiş ikonlar) | — | MIT | `public/assets/img/icons.LICENSE.txt` |
| [PDF.js](https://github.com/mozilla/pdf.js) (pdfjs-dist, legacy build) | 6.4.299 | Apache-2.0 | `public/assets/vendor/pdfjs/LICENSE` |
| PDF.js ile gelen cmaps, standart fontlar (Foxit, Liberation), ICC profilleri, WASM çözücüler (OpenJPEG, JBIG2/PDFium, qcms) | — | Her biri kendi lisans dosyasıyla (BSD, CC0, Liberation Font lisansı vb.) | `public/assets/vendor/pdfjs/**/LICENSE*` |
| [Inter](https://rsms.me/inter/) (@fontsource-variable/inter, latin + latin-ext, değişken ağırlık) | 5.3.0 | OFL-1.1 | `public/assets/fonts/inter/OFL.txt` |
| [DejaVu fontları](https://dejavu-fonts.github.io/) | — | Bitstream Vera / Arev lisansı (serbest) | `resources/fonts/unifont/DejaVu_LICENSE.txt` |
| PHPUnit (yalnızca geliştirme) | 11.5 | BSD-3-Clause | `vendor/` (`--no-dev` ile yüklenmez) |

LGPL lisanslı kütüphaneler (tFPDF, PHPMailer) değiştirilmeden, Composer bağımlılığı olarak kullanılır; kendi sürümünüzle değiştirebilirsiniz. Projenin kendi kodu [MIT](LICENSE) lisanslıdır.
