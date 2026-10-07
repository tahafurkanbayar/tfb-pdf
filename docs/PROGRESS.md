# İlerleme

Durum işaretleri: `[x]` tamamlandı · `[~]` devam ediyor · `[ ]` bekliyor · `[!]` sorunlu / kısmi

Son güncelleme: 2026-10-07

| # | Aşama | Durum | Not |
|---|---|---|---|
| 0 | Repo, dokümantasyon iskeleti, GitHub | [x] | docs/, CLAUDE.md, LICENSE (MIT), .gitignore |
| 1 | Proje yapısı | [x] | Dizinler, kök + public .htaccess, private dizinlerde deny-all, front controller |
| 2 | Composer | [x] | fpdi 2.6.8, tfpdf 1.33, phpmailer 7.1.1, phpunit 11.5 (dev); composer.phar yerelde |
| 3 | Configuration | [x] | Env okuyucu, Config (nokta notasyonu), config/*.php, .env.example, Size |
| 4 | Localization sistemi | [x] | Translator, LocaleNegotiator, __()/trans_choice(), 8 dil grubu, bin/check-translations.php |
| 5 | MySQL bağlantısı | [x] | Database (PDO, strict+UTC oturum, transaction), hata kategorileri, entegrasyon testleri |
| 6 | Migration sistemi | [x] | 8 migration (tüm tablolar), Migrator (checksum, GET_LOCK), bin/migrate.php, schema.sql |
| 7 | Core PHP architecture | [x] | Request/Response/Router, middleware, Session/CSRF, OwnerContext, View+layout, ErrorHandler, Logger, Bootstrap 5.3.8 + ikonlar |
| 8 | Storage sistemi | [x] | StorageService (güvenli yol, üzerine yazmayı reddeden atomik taşıma), FilenameSanitizer, HashService |
| 9 | Upload validation | [ ] | |
| 10 | Document management | [ ] | |
| 11 | PDF preview | [ ] | |
| 12 | Merge | [ ] | |
| 13 | Split | [ ] | |
| 14 | Reorder | [ ] | |
| 15 | Rotate | [ ] | |
| 16 | Compress | [ ] | |
| 17 | Watermark | [ ] | |
| 18 | Redaction | [ ] | |
| 19 | OCR capability detection | [ ] | |
| 20 | Office conversion capability detection | [ ] | |
| 21 | Versioning | [ ] | |
| 22 | Hashing | [ ] | |
| 23 | Audit logging | [ ] | |
| 24 | Expiration | [ ] | |
| 25 | Export | [ ] | |
| 26 | Signature workflow | [ ] | |
| 27 | Security hardening | [ ] | |
| 28 | Responsive UI | [ ] | |
| 29 | Türkçe/İngilizce UI kontrolü | [ ] | |
| 30 | Tests | [ ] | |
| 31 | cPanel deployment | [ ] | |
| 32 | README | [ ] | |
| 33 | Final verification | [ ] | |

## Sıradaki adım

Aşama 9: Upload validation.

## Aşama notları

Her aşama tamamlandığında buraya kısa bir not eklenir: ne yapıldı, nasıl doğrulandı, commit.
