# CLAUDE.md — tfb-pdf

Bu dosya her oturumda otomatik okunur. Kısa tutulmalıdır; ayrıntılar `docs/` altındadır.

## Oturum başında
1. `docs/PROGRESS.md` oku — hangi aşamadayız, sıradaki iş ne.
2. Gerekirse `docs/ARCHITECTURE.md` ve `docs/DECISIONS.md` oku.
3. Asıl gereksinim belgesi: `docs/PROMPT.md` (değiştirilmez).

## Değişmez kurallar
- Stack: PHP (8.2 uyumlu, hedef 8.3+), Composer, PDO + MySQL 8 / MariaDB 10.4+, Bootstrap 5, vanilla JS (ES modules), PDF.js.
- YASAK: Laravel/Symfony/CodeIgniter/WordPress veya herhangi bir framework, Node.js/npm/build adımı, Python, Docker, Redis, PostgreSQL, MongoDB, analytics/telemetry, üçüncü taraf CDN.
- PHP 8.3'e özel özellik kullanma (`json_validate`, typed class constants, `#[\Override]`). Yerel ortam PHP 8.2.12.
- SQL hem MariaDB 10.4 hem MySQL 8 ile çalışmalı; collation `utf8mb4_unicode_ci`.
- Kullanıcıya görünen her metin `__('grup.anahtar')` üzerinden; TR ve EN dosyaları birlikte güncellenir. `php bin/check-translations.php` temiz olmalı.
- Orijinal dosya asla değiştirilmez/üzerine yazılmaz. Her işlem yeni version üretir.
- Fake implementation yok. Çalışmayan/eksik özellik çalışıyor gibi gösterilmez; `docs/LIMITATIONS.md`'ye yazılır.
- Harici araçlar (Ghostscript, LibreOffice, Tesseract) her zaman capability detection + graceful fallback ile.
- Business logic controller/view içine yazılmaz: Controller → Service → Repository → DB.
- Teknik hata detayı (SQL, stack trace, path, credential) kullanıcıya gösterilmez.

## Çalışma düzeni
- Her geliştirme aşaması = ayrı commit, ardından `git push`.
- Çalıştırılan her komut `docs/COMMANDS_LOG.md`'ye gerçek sonucuyla eklenir.
- Aşama bitince `docs/PROGRESS.md` güncellenir; önemli kararlar `docs/DECISIONS.md`'ye.
- Composer: proje kökündeki `composer.phar` ile → `C:\xampp\php\php.exe composer.phar ...`
- Testler: `C:\xampp\php\php.exe vendor/bin/phpunit`

## Sürümleme (v1.0.0 sonrası)
- SemVer. Her değişiklik `CHANGELOG.md` → `## [Yayınlanmadı]` altına eklenir.
- Sürümün tek kaynağı `config/app.php` → `version`. Yayın adımları: `docs/RELEASING.md` (etiket `vX.Y.Z`, `php bin/build-release.php`, `gh release create`).
