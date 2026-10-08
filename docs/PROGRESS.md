# İlerleme

Durum işaretleri: `[x]` tamamlandı · `[~]` devam ediyor · `[ ]` bekliyor · `[!]` sorunlu / kısmi

Son güncelleme: 2026-10-08

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
| 18 | Redaction | [x] | Karartılan sayfa görüntüye dönüştürülür + kutular piksellere yakılır (sunucu Ghostscript veya tarayıcı PDF.js; sunucu kutuları yeniden uygular); gizli metnin dosyada kalmadığı testle kanıtlandı |
| 19 | OCR capability detection | [x] | Tesseract + Ghostscript/pdftoppm tespiti; varsa 300 DPI OCR (dil kesişimi, sayfa PDF'lerini birleştirme); yoksa açık mesaj ve 503; araç yokken uygulamanın çalıştığı test edildi |
| 20 | Office conversion capability detection | [x] | LibreOffice headless (ayrı profil, kabuksuz, zaman aşımı); orijinal Office dosyası sürüm 0, PDF sürüm 1; yoksa yükleme 503 + açık mesaj |
| 21 | Versioning | [x] | Sıralı, değiştirilemez sürümler; köken (kaynak sürüm) gösterimi; araçlarla belirli sürüm seçimi; DB tekillik ve üzerine yazma reddi testleri |
| 22 | Hashing | [x] | Sunucuda tüm sürümlerin bütünlük doğrulaması (ok/mismatch/missing), tarayıcıda yerel dosya karşılaştırma (WebCrypto, PHP ile birebir) |
| 23 | Audit logging | [x] | Append-only + hash zinciri, bin/verify-audit.php, tüm işlem türlerinin kapsam testi, audit ve log'larda hassas veri olmadığı testleri |
| 24 | Expiration | [x] | CleanupService (süresi dolan belgeler + audit 'expiry', geçici/export/önizleme/oturum, yetim dizinler, rate limit), cron/cleanup.php, fırsatçı temizlik (gerçek isteklerle doğrulandı) |
| 25 | Export | [x] | Belge ve tüm veriler ZIP: dosyalar, sürümler, metadata, işlem geçmişi, audit (silinmişler dahil), SHA-256 manifest; zip yoksa açık mesaj |
| 26 | Signature workflow | [x] | Alanlar, imzalayanlar, davet (SMTP opsiyonel), consent kaydı, çizim/yazılı imza, ret/iptal/süre, final PDF + sertifika + SHA-256, audit; nitelikli e-imza değil |
| 27 | Security hardening | [x] | Rate limiting (DB, HMAC'li), yapılandırma koruması, post_max_size tespiti, erken hata sayfası düzeltmesi, güvenlik testleri + Apache erişim kontrolleri |
| 28 | Responsive UI | [x] | 320/390/820 px ve masaüstünde 15 sayfa yatay taşmasız; `[hidden]` ile Bootstrap `.d-flex` çakışması, mobil menü kenar boşluğu, yığılan kart boşluğu, etiketsiz imzalayan seçimi düzeltildi; küçük resimler, imzalayan önizlemesi ve temel erişilebilirlik taraması gerçek Chrome'da doğrulandı |
| 29 | Türkçe/İngilizce UI kontrolü | [x] | 26 sayfa × 2 dilde sunucu ve JS sonrası tarama (sızıntı yok), sayfa başına JS çeviri grubu kapsamı, dil değiştirme (yol + sorgu korunuyor, açık yönlendirme yok), çevrilmiş API hataları; TR ondalık virgül (sunucu + JS aynı), süre metni sunucuda biçimleniyor |
| 30 | Tests | [x] | §45 eşlemesi: HashService, ExpiryPolicy, sürüm numaralama, PdfService bölme unit testleri; Upload→DB→işlem→sürüm→hash→audit entegrasyon testi; HTTP üzerinden uçtan uca happy path (indirme + audit zinciri); §46 için 25 yol × TR/EN otomatik çeviri taraması; mutasyonla hassasiyet kontrolü; zip eklentisiz 271 test (3 atlandı) ve eklentili 271 test (1 atlandı) geçti |
| 31 | cPanel deployment | [x] | `/install` web kurulum (INSTALL_KEY, dosya tabanlı deneme kilidi, 22 ortam kontrolü: PHP/eklenti/ini, .env, depolama, DB bağlantı + sürüm, migration, araçlar; tek tuşla migration, cron komutu); eski PHP için anlaşılır mesaj; public_html + app-root.php yerleşimi gerçek Apache'de doğrulandı; 289 test |
| 32 | README | [x] | Spec §47 bölümlerinin tamamı + üçüncü taraf lisansları; SSH'siz 12 adımlı cPanel kurulumu (3 yerleşim), cPanel ve manuel backup, cron, tüm env değişkenleri (eksik 2 değişken .env.example'a eklendi); schema.sql içe aktarımı gerçek veritabanında doğrulandı; ARCHITECTURE dizin ağacı güncellendi |
| 33 | Final verification | [x] | PHP 8.3.35 (resmi paket, SHA-256 doğrulandı) ile 278 dosya lint + 289 test; Apache'ye karşı §55 HTTP doğrulaması 30/30 (ilk turda bulunan istek başı PHP uyarısı .htaccess + .user.ini ile düzeltildi); production hata yönetimi gerçek DB hatasıyla; README'de composer.phar hatası düzeltildi; son rapor: docs/FINAL_REPORT.md |
| 34 | Tasarım sistemi ve koyu tema | [x] | app.css bölümlere ayrıldı (tokens/base/components/pages/dark), tüm renkler CSS değişkeni; Bootstrap 5.3 data-bs-theme ile Açık/Koyu/Sistem, flaşsız yerel theme-init.js (CSP), localStorage; Inter (yerel, OFL); AA kontrast (#E5484D vurgu, #C9373C buton/bağlantı); araç kartları renkli, devre dışı rozet + tooltip; 16 sayfa kopyasında kontrast ve taşma ölçüldü |
| 35 | Logo ve marka | [x] | Geometrik monogramlı belge işareti ve kelime işareti (logo-mark / logo-full / logo-full-inverse SVG), favicon SVG + 32 px PNG, apple-touch 180, manifest 192/512 (maskable), og:image 1200×630 ve og/twitter etiketleri; navbar'da satır içi logo (mobilde yalnızca işaret) |
| 36 | Sayfa tasarımları | [ ] | |

## Sıradaki adım

Aşama 36: Sayfa tasarımları (navbar, hero, yükleme alanı, dashboard kartları, footer, tüm sayfalarda tutarlılık; 360/768/1280 px ve kontrast doğrulaması).

## Aşama notları

Her aşama tamamlandığında buraya kısa bir not eklenir: ne yapıldı, nasıl doğrulandı, commit.
