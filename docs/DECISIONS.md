# Kararlar

Her karar: tarih, ne, neden. Yeni kararlar en alta eklenir.

## 2026-10-07 — PHP 8.2 uyumluluğu
Spec PHP 8.3+ diyor, yerel XAMPP 8.2.12. Kod 8.2 ile uyumlu yazılır, 8.3'e özel özellikler kullanılmaz. `composer.json` → `"php": ">=8.2"`. Böylece 8.3+ cPanel'de de çalışır.

## 2026-10-07 — Veritabanı uyumluluğu
Yerel MariaDB 10.4.32. Migration'lar hem MariaDB 10.4 hem MySQL 8 ile uyumlu: `utf8mb4_unicode_ci`, `JSON` tipi (MariaDB'de LONGTEXT takma adı), zaman damgaları UTC `DATETIME`.

## 2026-10-07 — Migration sistemi
Numaralı `.sql` dosyaları (`database/migrations/0001_*.sql`), `migrations` tablosu ile takip. Üç çalıştırma yolu: `php bin/migrate.php` (CLI), `.env`'deki `INSTALL_KEY` ile korunan web kurulum sayfası (SSH'siz cPanel), phpMyAdmin'den `database/schema.sql` içe aktarma. DDL ifadeleri MySQL'de örtük commit yaptığı için tam rollback garanti edilmez; hata olursa durup hangi dosyada kaldığını bildirir.

## 2026-10-07 — Git ve GitHub
Public repo `tahafurkanbayar/tfb-pdf`, her aşama ayrı commit + push. Commit e-postası GitHub noreply adresi (gerçek e-posta public repoda görünmesin diye).

## 2026-10-07 — Lisans
MIT. Bağımlılıklarla uyumlu (FPDI MIT, Bootstrap MIT, PDF.js Apache-2.0).

## 2026-10-07 — Kullanıcı hesabı yerine owner cookie
Spec ilk sürümde kullanıcı sistemi istemiyor ama indirmede yetki kontrolü istiyor. Çözüm: tarayıcı başına rastgele owner token (cookie), DB'de HMAC-SHA256 hali. Cookie kaybolursa belgelere erişim kaybolur (README'de belirtilecek; export bu yüzden önemli).

## 2026-10-07 — Frontend kütüphaneleri yerelde
Bootstrap ve PDF.js `public/assets/vendor/` altına kopyalanır, CDN kullanılmaz. Neden: gizlilik (§37–38: kullanıcı IP'si üçüncü taraflara gitmesin), build adımı yok, CSP `'self'` ile sıkı tutulabilir.
