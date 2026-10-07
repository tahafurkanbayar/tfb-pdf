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
| 9 | Upload validation | [x] | UploadValidator (PDF + Office), PdfInspector, xref stream / object stream / hybrid parser uzantısı, `App\Pdf\Fpdi` (tFPDF+DejaVu) |
| 10 | Document management | [x] | Repository katmanı, DocumentService, AuditService (hash zinciri), upload/liste/detay/indirme (Range)/silme/saklama API + sayfalar, dashboard |
| 11 | PDF preview | [x] | PDF.js 6.4.299 legacy (yerel), tembel küçük resimler + sunucu önbelleği (GD ile yeniden kodlama), sayfa görüntüleyici; headless Chrome ile doğrulandı |
| 12 | Merge | [x] | OperationService (ortak işlem akışı, girdi hash kontrolü), PdfService, PdfToolService, WarningCollector, araç sayfası iskeleti + birleştirme UI (sürükle-bırak, klavye) |
| 13 | Split | [x] | PageRangeParser (PHP+JS eşdeğer), each/ranges/extract modları, ZIP indirme (ext-zip varsa), küçük resimden sayfa seçimi |
| 14 | Reorder | [x] | Sayfa sıralama + kaldırma, değişmeyen sıra = yeni sürüm yok, sürükle-bırak Chrome ile doğrulandı |
| 15 | Rotate | [x] | Sayfa bazında ve toplu 90° adımlar, /Rotate ile kayıpsız; önceden döndürülmüş kaynakta piksel karşılaştırmasıyla doğrulandı |
| 16 | Compress | [x] | Ghostscript (varsa, -dSAFER) + PHP optimizasyonu (JPEG yeniden örnekleme, Flate); %3'ten az küçülme = yeni sürüm yok; araç tespiti altyapısı |
| 17 | Watermark | [x] | Metin, 8 konum + döşeme, döndürme, opaklık (ExtGState), boyut, renk, katman, sayfa aralığı; Türkçe karakter (DejaVu); Chrome ile görsel doğrulama |
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

Aşama 18: Redaction (gerçek kaldırma: karartılan sayfa görüntüye dönüştürülür; açık uyarılar).

## Aşama notları

Her aşama tamamlandığında buraya kısa bir not eklenir: ne yapıldı, nasıl doğrulandı, commit.
