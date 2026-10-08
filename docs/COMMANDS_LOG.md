# Çalıştırılan Komutlar

Geliştirme sırasında gerçekten çalıştırılan komutlar ve gerçek sonuçları. Çalıştırılmayan komut yazılmaz; başarısız sonuç başarılı gösterilmez.

`php` = `C:\xampp\php\php.exe` (PHP 8.2.12)

## Aşama 0 — Ortam keşfi ve repo

| Komut | Sonuç |
|---|---|
| `ls -la <memory dizini>; ls -la c:/xampp/htdocs/tfb-pdf` | Proje dizini boş |
| `git --version` | git 2.56.0.windows.2 |
| `gh --version` | gh 2.102.0 |
| `gh auth status` | `tahafurkanbayar` olarak oturum açık, scope: repo, workflow, gist, read:org |
| `composer --version` | **Başarısız**: composer PATH'te yok |
| `php -v` | PHP 8.2.12 (cli, ZTS, x64) |
| `C:\xampp\mysql\bin\mysql.exe --version` | MariaDB 10.4.32 |
| `git config --global user.name / user.email / init.defaultBranch; gh repo view tahafurkanbayar/tfb-pdf` | Global git kimliği tanımlı değil; repo GitHub'da yok (exit 1) |
| `gh api user --jq ...` | login: tahafurkanbayar, ad: Taha Furkan Bayar |
| `git init -b main` | Boş repo oluşturuldu |
| `git config user.name "Taha Furkan Bayar"` / `git config user.email "<noreply>"` / `git config core.autocrlf false` | Repo-yerel kimlik ayarlandı |
| `git status` | main, commit yok |
| `git add -A; git commit` | 9e5c482 — Aşama 0 |
| `gh repo create tahafurkanbayar/tfb-pdf --public --source . --remote origin --push` | https://github.com/tahafurkanbayar/tfb-pdf oluşturuldu, main push edildi |

## Aşama 1 — Proje yapısı

| Komut | Sonuç |
|---|---|
| `mkdir -p storage/{documents,versions,previews,temporary,exports,sessions,logs,cache} ...; touch .gitkeep; deny .htaccess yazımı` (bash döngüsü) | Dizin iskeleti ve 12 adet deny-all `.htaccess` oluşturuldu |
| `git add -A; git commit; git push` | 4dc57fe — Aşama 1 |

## Aşama 2 — Composer

| Komut | Sonuç |
|---|---|
| `curl -sSL -o composer.phar https://getcomposer.org/download/latest-stable/composer.phar` + `.sha256sum` + `sha256sum composer.phar` | Composer 2.10.3, checksum eşleşti (7a2d379d…c8d6) |
| `php composer.phar --version` | Composer 2.10.3, PHP 8.2.12 |
| `php -m` | `zip`, `intl`, `sodium` yüklü değil; `gd`, `fileinfo`, `pdo_mysql`, `mbstring`, `zlib`, `openssl` yüklü |
| `ls C:\xampp\php\ext; grep extension= php.ini; php -r ini_get(...)` | `php_zip.dll` mevcut ama php.ini'de kapalı; memory_limit 512M, upload_max_filesize 40M, post_max_size 40M |
| `php -d extension=zip composer.phar show -a setasign/tfpdf / setasign/fpdi / phpmailer/phpmailer` | tfpdf v1.33 (LGPL-2.1), fpdi v2.6.8 (MIT), phpmailer v7.1.1 (LGPL-2.1) |
| `php -d extension=zip composer.phar validate --strict` | composer.json geçerli |
| `php -d extension=zip composer.phar install --no-interaction` | 30 paket kuruldu (fpdi 2.6.8, tfpdf 1.33, phpmailer 7.x, phpunit 11.5.57) |
| `grep COMPRESSED_XREF vendor/setasign/fpdi/src` ve kaynak incelemesi | Ücretsiz FPDI xref stream'leri reddediyor; `getPdfParserInstance()` genişletme noktası mevcut |
| `git add -A; git commit; git push` | 33c8927 — Aşama 2 |

## Aşama 3 — Configuration

| Komut | Sonuç |
|---|---|
| `php vendor/bin/phpunit` | OK (13 tests, 27 assertions) — EnvTest, SizeTest |
| `php -r 'echo bin2hex(random_bytes(32));'` (APP_KEY) ve 16 bayt (INSTALL_KEY) → `sed ... .env.example > .env` | Yerel `.env` oluşturuldu (APP_ENV=local, DB tfb_pdf / root); `git check-ignore .env` ile ignore edildiği doğrulandı |
| `git add -A; git commit; git push` | 2eec75c — Aşama 3 |

## Aşama 4 — Localization

| Komut | Sonuç |
|---|---|
| `php vendor/bin/phpunit` | OK (34 tests, 100 assertions) |
| `php bin/check-translations.php` | tr: 127, en: 127 anahtar — "OK: çeviriler eksiksiz.", exit 0 |
| `git add -A; git commit; git push` | b1d85e4 — Aşama 4 |

## Aşama 5 — MySQL bağlantısı

| Komut | Sonuç |
|---|---|
| `mysql -u root -h 127.0.0.1 -e "SELECT VERSION(); SHOW DATABASES;"` | 10.4.32-MariaDB çalışıyor |
| `mysql ... -e "CREATE DATABASE IF NOT EXISTS tfb_pdf ...; CREATE DATABASE IF NOT EXISTS tfb_pdf_test ...; SELECT @@sql_mode, @@time_zone"` | İki veritabanı oluşturuldu (utf8mb4_unicode_ci). Sunucu sql_mode strict değil, time_zone SYSTEM |
| `php vendor/bin/phpunit` | OK (39 tests, 110 assertions) — fakat EnvTest'in .env değerlerini sildiği fark edildi |
| `php vendor/bin/phpunit` (EnvTest düzeltmesi sonrası) | OK (39 tests, 110 assertions) |
| `php bin/check-translations.php` | OK |
| `git add -A; git commit; git push` | ecbd0ab — Aşama 5 |

## Aşama 6 — Migration sistemi

| Komut | Sonuç |
|---|---|
| bash heredoc ile migration dosyası yazımı | **Başarısız**: bash ayrıştırma hatası (unexpected EOF), hiçbir dosya oluşmadı; dosyalar Write aracıyla yazıldı |
| `php bin/migrate.php schema` | `database/schema.sql` üretildi |
| `php vendor/bin/phpunit` | OK (43 tests, 156 assertions) — test DB'de taze migration, idempotency, schema.sql içe aktarımı |
| `php bin/migrate.php` | `tfb_pdf` üzerinde 0001–0008 uygulandı |
| `php bin/migrate.php` (tekrar) | "Bekleyen migration yok" |
| `php bin/migrate.php status` | 8/8 [x] |
| `php bin/migrate.php bogus` | "Bilinmeyen komut", exit 2 |
| `git add -A; git commit; git push` | 74108aa — Aşama 6 |

## Aşama 7 — Core PHP architecture

| Komut | Sonuç |
|---|---|
| `curl https://registry.npmjs.org/bootstrap/latest`, `.../pdfjs-dist/latest` | bootstrap 5.3.8, pdfjs-dist 6.4.299 |
| `curl -o bootstrap.tgz ...bootstrap-5.3.8.tgz; openssl dgst -sha512` + `tar -xzf` + `cp` | Integrity npm kaydıyla eşleşti; `public/assets/vendor/bootstrap/` (min.css, bundle.min.js, LICENSE) |
| `curl bootstrap-icons 1.13.1 tgz; openssl dgst -sha512; tar -xzf` | Integrity eşleşti |
| `php scratchpad/build-sprite.php ... ` | 40 ikonluk `public/assets/img/icons.svg` |
| `php composer.phar dump-autoload` + `php vendor/bin/phpunit` | **2 failure + 1 error**: Router `{32}` kısıt ayrıştırma hatası, Content-Disposition Türkçe karakter, app.js yorumunda tanımsız çeviri anahtarı |
| `php vendor/bin/phpunit --filter RouterTest` | Hata ayrıntısı incelendi |
| `php vendor/bin/phpunit` (düzeltmeler sonrası) | OK (84 tests, 251 assertions) |
| `curl` ile Apache smoke test (13 yol) | `/`→302 `/en/` (Accept-Language en); `/tr/`, `/en/about`, `/tr/privacy` 200; `/tr/yok` 404; `/.env`, `/storage/logs/`, `/src/helpers.php`, `/composer.json`, `/database/schema.sql` 403; `/vendor/autoload.php` 404; CSP, X-Frame-Options, HttpOnly session cookie mevcut |
| `git add -A; git commit; git push` | 72017e1 — Aşama 7 |

## Aşama 8 — Storage sistemi

| Komut | Sonuç |
|---|---|
| `sed` (DIRECTORIES listesine signatures) + `mkdir storage/signatures; touch .gitkeep` | Tamam |
| `php vendor/bin/phpunit` | **2 failure**: FilenameSanitizer test beklentileri (`.env`→`env`, bozuk UTF-8 baytı atılıyor) — kod davranışı daha güvenli olduğu için beklentiler güncellendi |
| `sed` + Edit (test beklentisi) + `php vendor/bin/phpunit` | 1 failure kaldı (sed ikinci satırı değiştiremedi), Edit ile düzeltildi |
| `php vendor/bin/phpunit` | OK (119 tests, 376 assertions) |
| `git add -A; git commit; git push` | 6a463f8 — Aşama 8 |

## Aşama 9 — Upload validation

| Komut | Sonuç |
|---|---|
| `grep/sed` ile FPDI kaynak incelemesi (PdfStream, StreamReader, FixedReader, Page, FpdiTrait::writePdfType) | API'ler doğrulandı |
| `grep` tFPDF font yükleme | Önbelleği font dizinine yazdığı görüldü |
| `mkdir resources/fonts/unifont; cp DejaVuSans*.ttf DejaVu_LICENSE.txt` + `.gitignore` ekleme | Tamam |
| `php vendor/bin/phpunit --filter PdfParsingTest` | OK (12 tests, 29 assertions) |
| `sed` (docblock silme) + `ls` Office/LibreOffice/Ghostscript + `where tesseract gswin64c soffice` | Hiçbiri kurulu değil. sed docblock'u bozdu (açık `/**`), Edit ile düzeltildi |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK (139 tests, 432 assertions); çeviriler eksiksiz |
| `git add -A; git commit; git push` | e6439c9 — Aşama 9 |

## Aşama 10 — Document management

| Komut | Sonuç |
|---|---|
| `sed` (PROGRESS düzeltmesi, LIMITATIONS yer tutucu silme) | Tamam |
| `php -r` ile js.php çeviri ekleme; `sed` ile layout i18n değişikliği | Çeviri eklendi; **sed layout'u değiştiremedi**, Edit ile yapıldı |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | 1 failure: Translator yorumundaki örnek anahtar `documents.page_count` tanımsız → yorum düzeltildi |
| `php vendor/bin/phpunit` | 1 failure: test varsayımı `%PDF-1.4` (tFPDF 1.3 yazıyor) → beklenti kaynaktan okunacak şekilde düzeltildi |
| `php vendor/bin/phpunit` | OK (152 tests, 513 assertions) |
| `php scratchpad/make-pdf.php` + curl smoke (ilk deneme) | Yükleme yanıtı boş: Git Bash curl `-F file=@/c/...` yolunu okuyamadı (uygulama hatası değil) |
| curl smoke (göreli yol ile, Apache) | upload 201; indirme 200, SHA-256 eşit; Range 206 (`%PDF-1.3`); sayfa 200; owner'sız 404; storage doğrudan 403; CSRF'siz DELETE 403; DELETE 200; silme sonrası 404; audit: upload/download/delete. Not: ilk denemedeki 1 test belgesi geliştirme DB'sinde kaldı (7 günlük süreyle silinecek) |
| `curl -sI` başlık kontrolü | `X-Content-Type-Options` çift gönderiliyordu → public/.htaccess yalnızca statik dosyalara uygulanacak şekilde düzeltildi; sonrası 1 adet |
| `git add -A; git commit; git push` | 8a82532 — Aşama 10 |

## Aşama 11 — PDF preview

| Komut | Sonuç |
|---|---|
| `curl -o pdfjs.tgz .../pdfjs-dist-6.4.299.tgz; openssl dgst -sha512; tar -xzf` | Integrity eşleşti (AVl138z…) |
| `cp legacy/build/pdf.min.mjs pdf.worker.min.mjs + cmaps, standard_fonts, wasm, iccs` → `public/assets/vendor/pdfjs` | 5.6 MB; quickjs-eval.* silindi |
| `php -r gd_info()` | GD: JPEG/PNG/WebP destekli |
| `grep api.d.ts` (PDF.js 6 parametreleri) | `canvas`, `wasmUrl`, `iccUrl`, `standardFontDataUrl`, `cMapUrl` doğrulandı; `isEvalSupported` artık yok → kaldırıldı |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK (155 tests, 526 assertions); çeviriler eksiksiz (273 anahtar) |
| `curl` PDF.js varlık MIME kontrolü | `.mjs` application/javascript, `.wasm` application/wasm, 200 |
| headless Chrome `--dump-dom` / `--virtual-time-budget` / `--timeout` / `--screenshot` denemeleri (geçici `public/assets/__smoke.html`) | **Sonuç alınamadı**: sanal zaman worker'ı ilerletmiyor; `--screenshot` sayfa yüklenince kapanıyor (geçersiz deneme) |
| `timeout 20 chrome --headless=new --remote-debugging-port=9333 __smoke.html` + Apache access.log | **OK**: klasik ve sıkıştırılmış PDF 3 sayfa açıldı, 90° döndürmeli çizim 200x141, iki dosyada da nonWhite=1034. Geçici dosyalar silindi; arkada Chrome süreci kalmadığı doğrulandı |
| curl önizleme API smoke (Apache) | previews 200 (cache_enabled), PUT 201, aralık dışı sayfa 404, GET image/jpeg 200, owner'sız 404, belge sayfası 200, silme 200 |
| `rmdir` boş shard dizinleri | Silmede boş üst dizin kalıyordu → `deleteDocumentFiles` artık boş üst dizini de kaldırıyor |
| `php vendor/bin/phpunit` | OK (155 tests, 526 assertions) |
| `git add -A; git commit; git push` | ca472e6 — Aşama 11 |

## Aşama 12 — Merge

| Komut | Sonuç |
|---|---|
| `grep` FPDI AddPage/cleanUp/useTemplate/getTemplateSize | İmzalar doğrulandı (AddPage rotation parametresi var) |
| `php vendor/bin/phpunit` | **1 failure + 1 warning**: başarısız işlemde FPDI dosya tanıtıcılarını kapatmıyordu (gerçek bug → `finally { cleanUp(true) }`); test yardımcısı kaynak PDF'leri temporary/ içine koyuyordu |
| `sed` (Services test yardımcısı) + `php vendor/bin/phpunit` | OK (163 tests, 567 assertions) |
| `php -r` ile tools.php çeviri ekleme + `php -l` | TR dosyasında kesme işareti kaçış hatası (parse error) → Edit ile düzeltildi, lint temiz |
| `php -r` (tc/icon yardımcıları, merge/common JS düzeltmeleri, layout icons) | Tamam |
| `php vendor/bin/phpunit` | 2 failure (test: HTML5 `&apos;` çözümleme, taban yol `/tfb-pdf` soyulmuyordu) |
| düzeltme sonrası `php vendor/bin/phpunit` | 1 failure: **yabancı owner birleştirme 201 döndü** — container `OwnerContext`'i istekler arasında önbelleğe alıyordu (aynı süreçte çoklu istek). Düzeltme: istek kapsamlı servis sıfırlama |
| `php vendor/bin/phpunit` (Session/Csrf da sıfırlanınca) | 5 failure (CLI oturumu bellekte; sıfırlama CSRF'yi bozdu) → yalnızca OwnerContext sıfırlanıyor |
| `php vendor/bin/phpunit` | OK (165 tests, 582 assertions) |
| headless Chrome (geçici `__smoke.html`, access.log ile sonuç) | app/upload/sortable/pdf-preview/viewer/common modülleri yüklendi; home.js, document.js ok; merge.js beklenen TypeError (DOM yok), SyntaxError yok |
| `git add -A; git commit; git push` | dd2cd61 — Aşama 12 |

## Aşama 13 — Split

| Komut | Sonuç |
|---|---|
| `sed` (single_page mesajı), `php -r` (controller/presenter/routes/services bağlama) + `php -l` (4 dosya) | Sözdizimi temiz |
| `sed` (tools.php çeviri ekleme) + `php -l`; `php -r` (sonuç kartına ZIP butonu) | Tamam |
| `php vendor/bin/phpunit` | OK (189 tests, 626 assertions, 1 skipped: ZIP testi ext-zip olmadan) |
| `php -d extension=zip vendor/bin/phpunit --filter SplitOperationTest` | OK (4 tests, 27 assertions) — ZIP içeriği doğrulandı |
| `php bin/check-translations.php` | OK (357 anahtar) |
| headless Chrome + `range-php.php`: JS ve PHP aralık ayrıştırıcı karşılaştırması (19 girdi) | **Birebir aynı**; source.js modülü hatasız yüklendi |
| `git add -A; git commit; git push` | baf6fb5 — Aşama 13 |

## Aşama 14 — Reorder

| Komut | Sonuç |
|---|---|
| `php -r` (reorder API + route) + `php -l` | Sözdizimi temiz |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK (192 tests, 639 assertions, 1 skipped — ext-zip); 369 anahtar |
| `php -r` (sortable.js setPointerCapture try/catch) + headless Chrome sürükle-bırak testi (geçici `__smoke.html`) | drag=2314 (1. kart 3. kartın sağına), moveItem=3214, onEnd 1 kez — beklenen |
| bash komutu (PROGRESS sed) | **Başarısız**: tek tırnaklı sed içinde kesme işareti; çift tırnakla yeniden çalıştırıldı |
| `git add -A; git commit; git push` | 2461dbf — Aşama 14 |

## Aşama 15 — Rotate

| Komut | Sonuç |
|---|---|
| `php -r` (rotate API + route) + `php -l` | Temiz |
| `php vendor/bin/phpunit --filter RotateOperationTest` | OK (3 tests, 12 assertions) |
| `php scratchpad/rotate-fixture.php` + headless Chrome piksel karşılaştırması (kaynak +90° PDF.js çizimi vs sonuç dosyası) | src-rot (kaynakta /Rotate 90): 80x120 vs 80x120, **0 farklı piksel**; src-plain: 120x80 vs 120x80, **0 farklı piksel**. Geçici dosyalar silindi |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK; çeviriler eksiksiz |
| `git add -A; git commit; git push` | 794d11f — Aşama 15 |

## Aşama 16 — Compress

| Komut | Sonuç |
|---|---|
| `git mv` ProcessResult→CommandResult + `sed`, `php -r` (readonly ksort düzeltmesi) | Tamam |
| `php -r` (PdfToolService::compress ekleme, nowdoc ile) | **Başarısız**: bash tırnak çakışması (parse error), değişiklik yapılmadı → Edit ile yapıldı |
| `php -r` (services bağlama) + `php -l` | Temiz |
| `sed`/`php -r` (uyarı metni, endpoint, route, ToolController capabilities, common.js) + `php -l` | Temiz |
| `php vendor/bin/phpunit --filter "CompressorTest\|ProcessRunnerTest"` | OK (8 tests) fakat 545 deprecation (test kodunda float `%`) → düzeltildi |
| `php vendor/bin/phpunit` | OK (203 tests, 684 assertions, 1 skipped) |
| `php vendor/bin/phpunit --filter CompressOperationTest` | OK (3 tests, 12 assertions) |
| `php scratchpad/compress-fixture.php` + headless Chrome görsel karşılaştırma | 201315 → 44803 bayt (2 görüntü optimize, biri gri tonlamalı); ortalama piksel farkı 2.01/255, en büyük 67. Geçici dosyalar silindi |
| `git add -A; git commit; git push` | f143ad0 — Aşama 16 |

## Aşama 17 — Watermark

| Komut | Sonuç |
|---|---|
| `grep` tFPDF `_putresourcedict/_putresources/_enddoc/_put/_out` | Genişletme noktaları doğrulandı |
| `php -r` (servis importu, API, route) + `php -l` (3 dosya) | Temiz |
| `php vendor/bin/phpunit --filter WatermarkOperationTest` | OK (3 tests, 19 assertions) |
| `php scratchpad/wm-fixture.php` + headless Chrome piksel kontrolü | Sayfa 1 ve 3'te yarı saydam kırmızı filigran (1946 piksel), sayfa 2'de 0. Geçici dosyalar silindi |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK; çeviriler eksiksiz |
| `git add -A; git commit; git push` | 3ee1b30 — Aşama 17 |

## Aşama 18 — Redaction

| Komut | Sonuç |
|---|---|
| `php -r` (Redactor bağlama: servis, container, API, route) + `php -l` (4 dosya) | Temiz |
| `php -r` (renderPage pixelRatio, runOperation FormData) | Tamam |
| `php vendor/bin/phpunit --filter RedactOperationTest` | **2 failure**: test yardımcısı FPDF'in tüm sayfalarda ortak kaynak sözlüğündeki diğer sayfa şablonlarını da "sayfa içeriği" sayıyordu (karartma hatası değil) |
| `php -r` (test: yalnızca `Do` ile çizilen nesneler + tüm akışlarda gizli metin araması) + `php vendor/bin/phpunit --filter RedactOperationTest` | OK (5 tests, 44 assertions) — gizli numara çıktının hiçbir akışında yok, kaynakta var |
| headless Chrome: tüm araç JS modülleri import | Sözdizimi hatası yok (sayfa modüllerinde beklenen TypeError) |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK; çeviriler eksiksiz |
| `git add -A; git commit; git push` | 9a9efcb — Aşama 18 |

## Aşama 19 — OCR capability detection

| Komut | Sonuç |
|---|---|
| `php -r` (OcrEngine bağlama: servis, container, API, route) + `php -l` (4 dosya) | Temiz |
| `php vendor/bin/phpunit --filter "OcrEngineTest\|OptionalToolsHttpTest"` | 1 failure: "kullanılamıyor" kelimesi JS çeviri verisinde her sayfada geçtiği için kontrol hatalıydı → görünür `role="alert"` kutusuna özel hale getirildi |
| aynı komut | OK (6 tests, 29 assertions) |
| `php -r` (bootstrap + Capabilities + ToolDetector, gerçek tespit) | ghostscript/office/ocr/zip: yok; gd ve proc_open: var; tespit 0.03 sn, `storage/cache/tools.json` yazıldı |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK; çeviriler eksiksiz |
| `git add -A; git commit; git push` | adc2a20 — Aşama 19 |

## Aşama 20 — Office conversion capability detection

| Komut | Sonuç |
|---|---|
| `php -r` (OfficeConverter bağlama, uyarı metni) + `php -l` (6 dosya) | Temiz; TR metninde `\x27` kalmıştı → Edit ile düzeltildi |
| `php -r` (test servis grafiğine sahte LibreOffice) + `php -l` | Temiz |
| `php vendor/bin/phpunit --filter OfficeConvertOperationTest` | OK (5 tests, 21 assertions) |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK (226 tests, 815 assertions, 1 skipped — ext-zip); çeviriler eksiksiz |
| `git add -A; git commit; git push` | 91a69f9 — Aşama 20 |

## Aşama 21 — Versioning

| Komut | Sonuç |
|---|---|
| `grep` (ToolController preselected, belge sayfası araç bağlantıları) | İnceleme |
| `php -r` (`?version=` desteği, köken gösterimi, sürüm başına araç menüsü, çeviriler) + `php -l` (5 dosya) | Temiz |
| `php vendor/bin/phpunit --filter VersioningTest` | OK (3 tests, 16 assertions) |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK (230 tests, 837 assertions, 1 skipped); çeviriler eksiksiz |
| `git add -A; git commit; git push` | d1a8d45 — Aşama 21 |

## Aşama 22 — Hashing

| Komut | Sonuç |
|---|---|
| `php -r` (verify API + route) + `php -l` | Temiz |
| `php -r` (belge sayfasına bütünlük kartı, preg_replace) | **Eklenmedi**: kabukta `$pdfVersions` değişken olarak yorumlandı → Edit ile eklendi |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | 1 failure: kontrolcü dinamik `'hash.status.' + durum` önekini anahtar sandı → JS deseni noktayla biten önekleri yok sayacak şekilde düzeltildi |
| aynı komutlar | OK (231 tests, 841 assertions, 1 skipped); çeviriler eksiksiz |
| headless Chrome WebCrypto SHA-256 vs `php hash_file` | Birebir aynı (bf94d11a…a9c3); localhost güvenli bağlam (isSecureContext=true) |
| `git add -A; git commit; git push` | dea1e99 — Aşama 22 |

## Aşama 23 — Audit logging

| Komut | Sonuç |
|---|---|
| `php bin/verify-audit.php` (geliştirme DB) | "OK: 6 audit kaydı doğrulandı, zincir sağlam.", exit 0 |
| `mysql ... SELECT COUNT(*), audit_chain_head` | 6 olay, zincir başı 64fcfdb8… |
| `php vendor/bin/phpunit --filter "AuditCoverageTest\|LoggerTest"` | OK (4 tests, 89 assertions) |
| `php vendor/bin/phpunit` | OK (tam paket) |
| `git add -A; git commit; git push` | 932b988 — Aşama 23 |

## Aşama 24 — Expiration

| Komut | Sonuç |
|---|---|
| `sed` (RELATIVE_PATTERN'e sessions) + `php -r` (CleanupService ve after_response bağlama) + `php -l` | Temiz; ancak sessions'ı çözümleyiciye eklemek mevcut güvenlik testine aykırıydı → geri alındı, purge yolu izinli listeden doğrudan kuruluyor |
| `php vendor/bin/phpunit --filter "CleanupTest\|StorageServiceTest"` | OK (22 tests, 85 assertions) |
| `php cron/cleanup.php` (geliştirme) | Rapor: hepsi 0, completed evet, exit 0; belge sayısı değişmedi (1) |
| `php bin/verify-audit.php` | OK, zincir sağlam |
| 120 × `curl /tr/about` (Apache) + log kontrolü | Fırsatçı temizlik 2 kez çalıştı (actor=system), error/critical log yok |
| `php vendor/bin/phpunit` | OK (tam paket) |
| `git add -A; git commit; git push` | 2b975df — Aşama 24 |

## Aşama 25 — Export

| Komut | Sonuç |
|---|---|
| `php -r` (export API, route, servis; çeviri dosyaları; belge sayfaları butonları) + `php -l` (7 dosya) | Temiz |
| `php vendor/bin/phpunit --filter ExportTest` (zip yok) | OK (3 tests, 2 skipped — zip gerektirenler); "kullanılamıyor" yolu doğrulandı |
| `php -d extension=zip vendor/bin/phpunit --filter "ExportTest\|SplitOperationTest"` | OK (7 tests, 54 assertions, 1 skipped — zip yok testi) |
| `php vendor/bin/phpunit` / `php -d extension=zip vendor/bin/phpunit` | zip yok: 242 tests, 957 assertions, 3 skipped · zip var: 242 tests, 988 assertions, 1 skipped |
| `php bin/check-translations.php` | OK |
| `git add -A; git commit; git push` | 610d682 — Aşama 25 |

## Aşama 26 — Signature workflow

| Komut | Sonuç |
|---|---|
| `php -r` (bağlantılar, çeviriler, view'lar) + `php -l` (çok sayıda dosya) | Temiz |
| `php vendor/bin/phpunit --filter SignatureWorkflowTest` | OK (4 tests, 46 assertions) — ilk denemede |
| `php scratchpad/sig-fixture.php` + headless Chrome | 3 sayfa; çizilmiş imza alanında 171, yazılı imza alanında 265 piksel, alan dışında 0; sertifika metni seçilebilir, 6/6 anahtar ifade (Türkçe karakterli) |
| `php vendor/bin/phpunit --filter "SignatureHttpTest\|SignatureWorkflowTest\|CleanupTest"` | OK (10 tests, 90 assertions) |
| `php bin/check-translations.php` | 4 sorun: dinamik önek `audit.events.signature_` ve `config/signature.php` ile `signature` çeviri grubu ad çakışması → config `signing` olarak yeniden adlandırıldı (`git mv`), kontrolcü harf/rakamla bitmeyen anahtarları önek sayıyor (php -r ile değişiklik tutmadı, Edit ile yapıldı) |
| `php bin/check-translations.php` | OK (579 anahtar) |
| `php vendor/bin/phpunit` / `php -d extension=zip vendor/bin/phpunit` | zip yok: 248 tests, 1021 assertions, 3 skipped · zip var: 248 tests, 1052 assertions, 1 skipped |
| headless Chrome: tools/sign.js, pages/sign.js, pages/document.js import | Sözdizimi hatası yok |
| `git add -A; git commit; git push` | 2950e4a — Aşama 26 |

## Aşama 27 — Security hardening

| Komut | Sonuç |
|---|---|
| `php -r` (ThrottleRequests, EnsureConfigured, post_max_size kontrolü bağlama) + `php -l` | Temiz |
| `php vendor/bin/phpunit --filter SecurityHardeningTest` | 1 error + 1 failure: **gerçek bug** — APP_KEY eksikken middleware kurulumu sırasında Hmac hata fırlatıyordu ve hata sayfası `$locale` olmadan çiziliyordu; XSS testinde `<script>` adı yüklemede zaten temizleniyordu (test güncellendi) |
| düzeltme (View varsayılanları, finalize'da güvenli cookie, Hmac anahtar doğrulaması ilk kullanıma) + aynı komut | 1 failure (500≠503) → kök neden Hmac kurucusu; doğrulama `hash()`'e taşındı |
| `php vendor/bin/phpunit --filter SecurityHardeningTest` | OK (5 tests, 31 assertions) |
| `php vendor/bin/phpunit` + `php bin/check-translations.php` | OK (253 tests, 1052 assertions, 3 skipped); çeviriler eksiksiz |
| curl (Apache): 16 yol, başlıklar, CSRF'siz POST, DELETE | .env/.env.example/storage/src/config/database/composer.lock/docs/bin/cron/tests/resources/.git → 403; CSP, X-Frame-Options, nosniff, Referrer/Permissions/COOP başlıkları; CSRF'siz POST 403 (çevrilmiş mesaj) |
| `git add -A; git commit; git push` | 9ffc2e4 — Aşama 27 |

## Aşama 28 — Responsive UI

| Komut | Sonuç |
|---|---|
| headless Chrome `--window-size=390/1366 --screenshot` (6 sayfa × 2) | Masaüstü sorunsuz; mobil görüntüler sağdan kesik → headless pencerenin en küçük genişliği (~500 px) yüzünden, uygulama hatası değil |
| geçici `__smoke.html` (iframe `src`) | Rapor gelmedi: uygulama sayfaları `frame-ancestors 'none'` ile çerçevelenemiyor (beklenen güvenlik davranışı); ilk denemede yanlış URL (`/public/assets`) |
| geçici `__smoke.html` (fetch + iframe `srcdoc`) 320/390/820 px × 15 sayfa | 45/45 yatay taşma yok |
| `php -r` örnek PDF (Fpdi doğrudan / APP_ROOT'suz TestPdf) | 3 başarısız deneme (FPDF sınıfı yok, APP_ROOT tanımsız, helvetica tanımı yok); `define("APP_ROOT") + TestPdf::create` ile oluşturuldu |
| geçici `__shots.html` yan yana 390 px ekran görüntüleri (m1–m6) | Bulunanlar: `hidden` + `.d-flex` çakışması (seçili dosya satırı her zaman görünüyordu), mobil menü kenara yapışık, kaynak/ayar kartları arasında çift boşluk, imzalayan radyosunun görünür etiketi yok. Türkçe dosya adı bozuk göründü → test sayfasında `charset` yoktu; düzeltilince ad doğru kaydedildi (uygulama hatası değil) |
| `mysql -e "SELECT original_name, HEX(...)"` | Bozuk ad test sayfasından geldiği doğrulandı |
| düzeltmeler + aynı ekran görüntüleri | Dört sorun giderildi |
| küçük resim ölçümü (`--virtual-time-budget`, srcdoc) | 0/3: PDF hiç indirilmedi → ölçüm yapaylığı (sanal zaman + `about:srcdoc` adresi) |
| bağımsız modül testi (`ThumbnailSource` → `openPdf` → blob) | Çalışıyor (~400 ms) |
| popup ile ölçüm | İlk denemede rapor yok: COOP `same-origin` popup erişimini kesiyor; test sayfasına geçici `.htaccess` ile aynı COOP verildi |
| popup: split/reorder/rotate/redact/sign/watermark/compress | Küçük resimler 155×220 çiziliyor (ekran dışındakiler tembel, kaydırınca 3/3); tüm sayfalar 375/375 taşmasız |
| popup erişilebilirlik taraması (15 sayfa: etiket, erişilebilir ad, tekrarlı id, başlık atlama, tek h1, alt) | 15/15 temiz |
| imza talebi (POST /api/signatures) + imzalayan sayfası popup | 201; 375/375 taşmasız, 3 sayfa canvas (351 px), 1 alan vurgusu; `--timeout` ekran görüntüsü önizlemeden önce alınmıştı |
| `--timeout` masaüstü ekran görüntüsü (navbar) | İçerikle hizalı |
| geçici dosyaların silinmesi (`__smoke.html`, `__shots.html`, `__s.pdf`, `assets/.htaccess`, Chrome profilleri) | Silindi; `git status` yalnızca 5 kaynak dosyası |
| `php bin/check-translations.php` | OK (579 anahtar) |
| `php vendor/bin/phpunit` | OK (253 tests, 1052 assertions, 3 skipped — zip) |
| `git add -A; git commit; git push` | 89f6c0c — Aşama 28 |

## Aşama 29 — Türkçe/İngilizce UI kontrolü

| Komut | Sonuç |
|---|---|
| curl ile örnek belge yükleme (cookie jar) | 201 |
| `php scratchpad/lang-scan.php` (26 yol × TR/EN: görünür metin, aria-label/title/placeholder/alt, meta description, `<html lang>`, JS'e giden çeviri JSON'u) | 51/52 temiz; tek işaret TR Hakkında'daki bilinçli "(enterprise document governance)" terim açıklaması |
| `php scratchpad/js-groups.php` (sayfa başına JS modül ağacı → kullanılan `t()/tc()` anahtarları sayfaya gönderilmiş mi) | 15/15 sayfa tam; 3 dinamik çağrı (page-range hata anahtarları, reorder etiketleri) elle doğrulandı. İlk denemede imza token'ı boş (JSON'daki `\/` kaçışı) → php ile ayrıştırıldı |
| curl `/language/en?return=...` (7 durum) | İlk denemede `/tr/documents/..`, `/en/about` ana sayfaya düştü → **Git Bash MSYS yol dönüşümü** argümanı `C:/Program Files/Git/...` yapmıştı; elle kodlanmış URL ve `MSYS_NO_PATHCONV=1` ile: yol + sorgu korunuyor, `https://`, `//`, `/\` dış adresleri ana sayfaya düşüyor; `tfb_locale` cookie (HttpOnly, Lax, 1 yıl); Accept-Language en→/en/, tr→/tr/, de→/tr/ |
| curl API hataları (`X-Locale: tr/en`, 404 ve CSRF'siz POST) | Her iki dilde çevrilmiş mesaj, teknik ayrıntı yok |
| boyut/tarih biçimleri incelemesi | TR'de "13.5 KB" (nokta) ve süre uzatma sonrası JS'in `toLocaleString` ile farklı biçim/saat dilimi kullandığı bulundu → `common.decimal_separator`, `Size::format` dil duyarlı, JS `formatSize` Intl ile, API `expiry.status` sunucuda biçimleniyor; kullanılmayan `js.expires_on`/`js.expires_never` kaldırıldı |
| `php vendor/bin/phpunit` | 1 failure: `SizeTest::testFormat` eski davranışı ('1.5 KB') sabitliyordu → ayırıcı açık verildi, dil duyarlılığı için yeni test (TR/EN, binlik ayırıcı yok) |
| `php vendor/bin/phpunit --filter SizeTest` / `--filter DocumentHttpTest` | OK (11 tests, 15 assertions) / OK (4 tests, 45 assertions — `expiry.status` EN biçimi) |
| curl TR/EN `/documents` | "13,0 KB" / "13.0 KB" |
| geçici popup taraması (JS çalıştıktan sonra 13 EN sayfa: innerText + öznitelikler) | 13/13 temiz; aynı tarama TR sayfada Türkçe metinleri yakaladı (hassasiyet doğrulandı) |
| geçici modül testi (`formatSize`, TR/EN) | TR `512 B / 1,5 KB / 1023,5 KB / 25,0 MB`, EN `... 1.5 KB / 1023.5 KB / 25.0 MB`; sunucu ile aynı |
| geçici dosyaların silinmesi | Silindi |
| `php bin/check-translations.php` | OK (578 anahtar) |
| `php vendor/bin/phpunit` | OK (254 tests, 1056 assertions, 3 skipped — zip) |
| `git add -A; git commit; git push` | 7d1fedf — Aşama 29 |

## Aşama 30 — Testler

| Komut | Sonuç |
|---|---|
| spec §45/§46 ↔ `tests/` karşılaştırması | Var: dosya adı, doğrulama, sayfa aralığı, birleştirme, döndürme, çeviri. Eksik/dağınık: SHA-256 `StorageServiceTest` içinde, süre hesabı entegrasyon testinde, PdfService düzeyinde bölme yok, sürüm numaralama kuralları ayrı değil, §45 zinciri ve audit'i doğrulayan uçtan uca test yok, §46 sayfa taraması yalnızca elle (Aşama 29) |
| yeni testler: `Unit/Services/HashServiceTest`, `Unit/Services/ExpiryPolicyTest`, `Unit/Domain/VersionNumberingTest`, `PdfServiceTest::testSplitIntoRanges...`, `Integration/ProcessingPipelineTest`, `Feature/HappyPathTest`, `Feature/LocalizationPagesTest` | Yinelenen eski hash/süre testleri yeni dosyalara taşındı |
| `php vendor/bin/phpunit --filter 'HashServiceTest\|ExpiryPolicyTest\|...'` (8 sınıf) | OK (42 tests, 200 assertions) |
| `php vendor/bin/phpunit --filter LocalizationPagesTest` | 1 failure: testte yanlış anahtar adı (`dashboard.storage_usage`, doğrusu `dashboard.storage`) → düzeltildi; OK (2 tests, 21045 assertions) |
| mutasyon kontrolü: EN `common.skip_to_content` Türkçe yapıldı, `ExpiryPolicy::isExpired` `<=` → `<`, audit `outputHash` girdi hash'i yapıldı | 6 test düştü (LocalizationPages, ExpiryPolicy ×2, ProcessingPipeline ×2, HappyPath); `git checkout --` ile geri alındı |
| `php -m \| grep zip` | Boş (zip eklentisi yüklü değil; `ext/php_zip.dll` var) |
| `php vendor/bin/phpunit` | OK (271 tests, 22200 assertions, 3 skipped — zip gerektiren testler) |
| `php -d extension=zip vendor/bin/phpunit` | OK (271 tests, 22234 assertions, 1 skipped) |
| `php -d extension=zip vendor/bin/phpunit --display-skipped` | Atlanan: `ExportTest::testUnavailableWithoutZipExtension` (zip yokken davranış testi) — iki çalıştırma birlikte tüm dalları kapsıyor |
| `php bin/check-translations.php` | OK (578 anahtar) |
| `git add -A; git commit; git push` | bb0b1d7 — Aşama 30 |

## Aşama 31 — cPanel deployment

| Komut | Sonuç |
|---|---|
| mevcut durum incelemesi | `INSTALL_KEY` config'de ve `EnsureConfigured` `/install`'ı muaf tutuyordu, ancak kurulum sayfası yoktu |
| yeni: `Services/Install/InstallGuard`, `Services/Install/EnvironmentCheck`, `InstallController`, `pages/install.php`, `lang/{tr,en}/install.php`, 4 rota | — |
| `php -l` (6 dosya) | Sözdizimi hatası yok |
| `php bin/check-translations.php` | OK (686 anahtar) |
| curl yerel `/install` akışı (cookie jar; anahtar dosyadan okundu, yazdırılmadı) | Giriş formu; yanlış anahtar → 302 + "Kurulum anahtarı yanlış."; ilk doğru anahtar denemesi curl `@/c/...` yol biçimi nedeniyle okunamadı → `cygpath -m` ile 302; sayfa: `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`, 22 kontrol, "0 hata, 2 uyarı" (zip eklentisi yok, HTTPS yok — yerelde beklenen), 8/8 migration uygulandı |
| `php vendor/bin/phpunit --filter 'InstallHttpTest\|InstallTest'` | 1 error: testteki `SHOW TABLES LIKE ?` gerçek prepared statement ile çalışmıyor → information_schema sorgusu; OK (18 tests, 110 assertions) |
| public/ içeriği `htdocs/tfbpub-smoke`'a kopyalandı + `app-root.php` + `SetEnv APP_URL` (public_html senaryosu) | `/` 302 → `/tr/`, sayfalar ve `/install` 200, CSS/JS 200 doğru MIME, API 404 JSON, linkler `/tfbpub-smoke/...`; `.htaccess` 403; `app-root.php` 200 boş gövde → `public/.htaccess`'te reddedildi, tekrar: 403 |
| curl proje kökü yerleşimi (`/tfb-pdf/.env`, `/storage/logs`, `/src/...`, `/composer.json`, `/app-root.php`) | 403; `/vendor/autoload.php` 404 |
| test kopyasının silinmesi | Silindi |
| `php vendor/bin/phpunit` | OK (289 tests, 22310 assertions, 3 skipped — zip) |
| `php -d extension=zip vendor/bin/phpunit` | OK (289 tests, 22344 assertions, 1 skipped) |
| headless Chrome ekran görüntüsü (giriş yapılmış sayfanın geçici statik kopyası, 1280 ve 500 px) | Düzgün, taşma yok; geçici dosya ve profil silindi |
| eski PHP sürüm kontrolü (`bootstrap/app.php`, `bin/migrate.php`) | Yalnızca PHP 8.2.12 ile sözdizimi kontrolü yapıldı; yerelde eski PHP olmadığından < 8.2 mesajı **denenmedi** |
| `git add -A; git commit; git push` (ilk deneme: bash ayrıştırma hatası, belge eklemeleri yapılmış commit yapılmamıştı) | 001e6e1 — Aşama 31 |

## Aşama 32 — README

| Komut | Sonuç |
|---|---|
| spec §21, §25, §28, §37–39, §47–48, §54 + `docs/LIMITATIONS.md` incelemesi | README bölümleri ve içerik kaynakları belirlendi |
| `php composer.phar licenses --no-dev` + `public/assets/vendor/**/LICENSE*`, `icons.LICENSE.txt`, `DejaVu_LICENSE.txt` başlıkları | fpdi MIT, tfpdf LGPL-2.1, phpmailer LGPL-2.1-only, Bootstrap/Bootstrap Icons MIT, PDF.js Apache-2.0 (alt bileşenler kendi lisanslarıyla), DejaVu Bitstream Vera/Arev |
| config `Env::` anahtarları ↔ `.env.example` karşılaştırması | `SMTP_TIMEOUT`, `TOOL_DETECTION_CACHE_TTL` örnekte yoktu → eklendi |
| `php bin/migrate.php schema` + `git diff --stat database/schema.sql` | Fark yok (şema güncel) |
| `mysql` ile boş `tfb_pdf_schema_test` veritabanına `database/schema.sql` içe aktarma + Migrator kontrolü | 12 tablo, 8 migration kaydı, `pending=0 modified=0`; geçici veritabanı silindi |
| README iddialarının kodla karşılaştırılması (HSTS, güvenlik başlıkları, log maskeleme, PHPUnit lisansı, dil ekleme, test atlama) | 3 düzeltme: PDF.js alt lisansları "OFL" değil (Liberation kendi lisansı), kurulum kontrol sayısı ortama bağlı (sabit sayı kaldırıldı), yeni dilde tarih biçimi `DateFormatter`'da (not eklendi) |
| `php vendor/bin/phpunit --testsuite Unit` + `grep` (unit testlerde DB kullanımı) | OK (193 tests, 573 assertions); unit testler gerçek veritabanı kullanmıyor |
| `docs/ARCHITECTURE.md` | "planlanan" dizin ağacı ve var olmayan `bin/check-env.php` gerçek yapıya göre düzeltildi |
| `gh api /markdown ...` | İlk deneme: Git Bash yolu dönüştürdü ("invalid API endpoint"); `gh api markdown` ile render edildi ancak API başlık kimliği üretmediği için içindekiler bağlantıları bu yolla doğrulanamadı |
| `git add -A; git commit; git push` | 144fb7b — Aşama 32 |
| `curl -sL https://github.com/tahafurkanbayar/tfb-pdf` + başlık kimliği karşılaştırması (push sonrası) | 24 başlık kimliği; README içindekiler 20/20 bağlantı eşleşiyor ("İ" içeren `pdf-i̇şleme`, `türkçe--i̇ngilizce-dil-sistemi` dahil) |

## Aşama 33 — Final verification (spec §54–56)

| Komut | Sonuç |
|---|---|
| `which -a php`, `ls "C:/Program Files"` | Makinede yalnızca XAMPP PHP 8.2.12 (ZTS, x64) |
| `curl https://windows.php.net/downloads/releases/sha256sum.txt` | İlk deneme boş (302 yönlendirmesi izlenmedi); `-L` ile: `php-8.3.35-nts-Win32-vs16-x64.zip` ve SHA-256 değeri |
| `curl -L` PHP 8.3.35 zip → scratchpad, `sha256sum`, `unzip` | Özet php.net listesiyle birebir aynı (`25a8e2ac…fe45`); sisteme kurulmadı, PATH değişmedi |
| PHP 8.3: `php -n -d extension=… -l` (git'teki 278 PHP dosyası) | 0 sözdizimi hatası |
| PHP 8.3: `php composer.phar check-platform-reqs --no-dev` | Tümü success (php 8.3.35, ext-*) |
| PHP 8.3: `vendor/bin/phpunit --display-skipped --display-deprecations` (`-n`, php.ini yok) | 1 error: `CompressOperationTest` 3,4 MB yükleme `File too large` — php.ini olmadan `upload_max_filesize` varsayılanı 2M (uygulama ini sınırını doğru uyguluyor; PHP 8.3 sorunu değil) |
| PHP 8.3: aynı + `-d upload_max_filesize=40M -d post_max_size=40M` (XAMPP ile aynı) | OK (289 tests, 22344 assertions, 1 skipped); deprecation yok |
| PHP 8.3: aynı, zip eklentisi olmadan | OK (289 tests, 22310 assertions, 3 skipped) |
| `php scratchpad/final-verify.php` (Apache'ye karşı HTTP: TR/EN arayüz, dil değiştirme, gerçek PDF yükleme, birleştirme, bölme, döndürme, yeni sürüm, orijinal değişmedi (HTTP + disk), SHA-256 (yükleme, indirme, sunucu doğrulaması), audit + zincir, indirme, hatalı/kesik PDF, 26 MB ve 42 MB yükleme, storage/.env/.git/config/src erişimi, başka tarayıcı, CSRF (token yok/yanlış/yabancı Origin), silme + audit, süre dolumu + `cron/cleanup.php`, araçlar yokken OCR 503 / PHP sıkıştırma) | **29/30**: post_max_size aşan 42 MB yüklemede yanıt 422 ve doğru mesaj, ancak gövdenin başında PHP'nin istek başı uyarısı (`<b>Warning</b>: POST Content-Length … exceeds the limit`) — XAMPP `display_errors=On`; uygulamanın `ini_set` ile kapatması bu uyarıdan sonra çalışıyor |
| düzeltme: `public/.htaccess` `<IfModule mod_php.c> php_flag display_errors Off`, `public/.user.ini` (`display_errors = Off`, PHP-FPM/CGI) | curl tekrar: temiz JSON, 422 "The file exceeds the upload size allowed by the server."; `/.user.ini` 403, `/tr` ve CSS 200 |
| `php scratchpad/final-verify.php` (tekrar) | **30/30** |
| production hata yönetimi: `public/` kopyası + `SetEnv APP_ENV=production APP_DEBUG=true DB_PASSWORD=<yanlış>` | İlk deneme 200 döndü: owner çerezi olmayan ana sayfa ve `/api/documents` veritabanına sorgu atmıyor (geçici `envdump.php` ile SetEnv ve yapılandırmanın uygulamaya ulaştığı, bağlantının `DatabaseException` verdiği doğrulandı; `mysql -pyanlış` → 1045). Owner çereziyle `/tr/documents`: HTTP 500, "Bir sorun oluştu / Veriler kaydedilirken bir sorun oluştu… Hata kodu: e4a8848b14d5"; API 500 JSON `category: database`; SQLSTATE, kullanıcı, parola, yol, 1045 sayfada yok; log'da aynı istek kimliğiyle ayrıntı var, parola yok. Kopya ve `envdump.php` silindi |
| `php composer.phar validate` / `install` / `check-platform-reqs --no-dev` / `audit` | valid / değişiklik yok / success / "No security vulnerability advisories found." |
| `php bin/migrate.php status` + `php bin/migrate.php` | 8/8 uygulanmış; "Bekleyen migration yok" |
| `php bin/check-translations.php` | OK (686 anahtar) |
| `php bin/verify-audit.php` | OK: 42 kayıt, zincir sağlam |
| `.gitignore` + `git check-ignore` + `git ls-files` + `git log --all -- .env` + `git grep <APP_KEY> $(git rev-list --all)` | `.env`, `vendor/`, storage içerikleri, log'lar, `composer.phar`, `.phpunit.cache` izlenmiyor; geçmişte `.env` ve APP_KEY yok |
| README kontrolü (göreli bağlantılar, `composer.phar` ifadeleri) | Bağlantılar mevcut; **hata**: README "depoda composer.phar bulunur" diyordu ama dosya `.gitignore`'da → getcomposer.org yönlendirmesiyle düzeltildi; `.user.ini` kopyalama ve güvenlik notları eklendi |
| `php vendor/bin/phpunit` / `php -d extension=zip vendor/bin/phpunit` (PHP 8.2.12) | OK (289 tests, 22310 assertions, 3 skipped) / OK (289 tests, 22344 assertions, 1 skipped) |
| `--testsuite Unit / Integration / Feature` (zip ile) | 193 tests, 573 assertions / 63 tests, 462 assertions, 1 skipped / 33 tests, 21309 assertions |
| `git add -A; git commit; git push` | 5e9fad4 — Aşama 33 |
