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

## Tablolar

> Aşama 6'da (migration sistemi) doldurulacak.

Spec'in istediği minimum tablolar: `documents`, `document_versions`, `operations`, `audit_events`, `file_expiry`, `signature_requests`, `signature_events`, `settings`.
