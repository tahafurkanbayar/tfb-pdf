# Veritabanı

## Bağlantı (`App\Core\Database`)

- PDO, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES = false` (gerçek prepared statement).
- Bağlantı ilk sorguda açılır (lazy). Veritabanı gerektirmeyen sayfalar bağlantı açmaz.
- Her bağlantıda oturum ayarları: `utf8mb4_unicode_ci`, strict `sql_mode`, `time_zone = '+00:00'`. Sunucunun global ayarından bağımsız tutarlı davranış sağlar (XAMPP MariaDB'de strict mod varsayılan olarak kapalı).
- Tüm zaman damgaları UTC `DATETIME` olarak saklanır, arayüzde `APP_TIMEZONE` ile gösterilir.
- `transaction(callable)`: hata olursa rollback. İç içe çağrılar dıştaki transaction'a katılır.
- PDO hataları `DatabaseException`'a çevrilir. SQL ve parametreler yalnızca log'a gider, kullanıcıya gösterilmez.
- Şifre parametresi `#[\SensitiveParameter]` ile stack trace'lerden gizlenir.

## Yerel veritabanları

- `tfb_pdf`: uygulama
- `tfb_pdf_test`: testler (`DB_TEST_DATABASE`). Test altyapısı adı `_test` ile bitmeyen veritabanında çalışmayı reddeder.

## Migration sistemi

- Dosyalar: `database/migrations/NNNN_ad.sql`, ad sırasıyla, yalnızca ileri yönlü. Uygulanmış bir dosya **değiştirilmez**; değişiklik için yeni dosya eklenir.
- Kurallar: yorumlar `--` ile başlayan satırlar, her ifade satır sonundaki `;` ile biter, yalnızca ASCII (test ile denetlenir).
- `migrations` tablosu: dosya adı, SHA-256 checksum, batch, zaman. Değiştirilmiş eski dosyalar `status` çıktısında uyarı verir.
- `GET_LOCK` ile eşzamanlı çalıştırma engellenir.
- Çalıştırma yolları:
  - `php bin/migrate.php` / `status` / `schema`
  - Web kurulum sayfası (`INSTALL_KEY` ile, SSH'siz cPanel) — Aşama 31
  - phpMyAdmin → Import → `database/schema.sql` (her migration + `migrations` kayıtları; `php bin/migrate.php schema` ile üretilir, testle güncelliği denetlenir)

## Tablolar

| Tablo | Amaç | Önemli noktalar |
|---|---|---|
| `settings` | Anahtar/değer | `audit_chain_head` (audit hash zinciri başı) |
| `documents` | Belge | `public_id` (32 hex, URL'de), `owner_hash` (HMAC), `user_id` (gelecek), `original_name` yalnızca metadata, `source_type`: upload_pdf / upload_office / generated |
| `operations` | İşlem kaydı | `type`, `status` (processing/completed/failed/no_change), `engine`, `params`/`input_versions`/`result` JSON, `error_code`, süre |
| `document_versions` | Değiştirilemez sürüm | `version_number` 0 = orijinal; `(document_id, version_number)` ve `storage_path` tekil; `sha256`, `file_size`, `page_count`, `label` |
| `audit_events` | Append-only olay günlüğü | FK yok (belge silinse de kalır); `prev_hash` + `event_hash` zinciri; IP/UA yalnızca imza olaylarında |
| `file_expiry` | Saklama süresi | `policy` 1d/7d/30d/never, `expires_at` (never → NULL) |
| `signature_requests` | İmza talebi | imzalanacak `version_id`, `source_sha256`, `final_version_id`, `final_sha256` |
| `signature_signers` | İmzacı | `token_hash` (token'ın kendisi saklanmaz), consent/sign/decline zamanları |
| `signature_fields` | İmza alanı | sayfa + oransal konum (0..1) |
| `signature_events` | Talebe özel olaylar | son PDF'teki imza sayfası için |
| `rate_limits` | İstek sınırlama | `bucket` = HMAC(eylem+IP), sabit pencere |
| `migrations` | Migration takibi | migrator tarafından oluşturulur |

Silme davranışı: belge silinince sürümler, işlemler, expiry ve imza kayıtları `ON DELETE CASCADE` ile silinir; `audit_events` kalır.
