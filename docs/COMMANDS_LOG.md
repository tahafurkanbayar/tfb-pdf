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
