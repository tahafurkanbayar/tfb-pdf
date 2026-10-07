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
