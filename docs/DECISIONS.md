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

## 2026-10-07 — PDF kütüphaneleri
- `setasign/fpdi` ^2.6 (MIT): mevcut PDF sayfalarını içe aktarma.
- `setasign/tfpdf` ^1.33 (LGPL-2.1): FPDF'in UTF-8 sürümü. Çekirdek FPDF fontları cp1252 olduğundan ş, ğ, ı, İ gibi Türkçe karakterleri basamaz; tFPDF paketiyle gelen DejaVu Sans (serbest lisans) gömülü font olarak kullanılır. FPDI, tFPDF'i resmi olarak destekler (`setasign\Fpdi\Tfpdf\Fpdi`). Sayfa döndürme (`AddPage(..., $rotation)`) mevcut.
- `phpmailer/phpmailer` ^7 (LGPL-2.1): opsiyonel SMTP. Kendi SMTP istemcimizi yazmak (STARTTLS, AUTH, header encoding, dot-stuffing) hataya açık; tek ve yaygın bir bağımlılık tercih edildi.
- Dev: `phpunit/phpunit` ^11.5 (PHP 8.2 destekleyen son ana sürüm).
- LGPL kütüphaneler değiştirilmeden bağımlılık olarak kullanılır; MIT lisanslı proje kodu ile uyumludur.

## 2026-10-07 — Ücretsiz FPDI'nin sıkıştırılmış xref sınırlaması
Doğrulandı: FPDI 2.6.8 ücretsiz parser'ı cross-reference stream (PDF 1.5+, object stream) kullanan dosyalarda `COMPRESSED_XREF` hatası veriyor (`CrossReference.php:268`). Word/Office gibi birçok modern üretici bu formatı kullanır; destek olmazsa uygulama gerçek dosyaların önemli bir kısmında çalışmaz. Ticari "FPDI PDF-Parser" eklentisi kullanılmayacak.
Çözüm: FPDI'nin resmi genişletme noktası `FpdiTrait::getPdfParserInstance()` üzerinden kendi `PdfParser` / `CrossReference` alt sınıflarımız: xref stream okuma (PNG predictor dahil), object stream içindeki nesneleri çözme. Saf PHP, harici araç gerektirmez. Kapsamlı birim testleriyle doğrulanacak.

## 2026-10-07 — Composer çalıştırma ve zip eklentisi
Composer sisteme kurulmadı; `composer.phar` proje kökünde (gitignore'da), SHA-256 doğrulandı. Yerel XAMPP'te `ext-zip` kapalı; `php.ini` değiştirilmeden komutlar `php -d extension=zip` ile çalıştırılıyor. Uygulama zip yoksa export özelliğini kapatıp nedenini gösterecek. `config.platform.php = 8.2.12` ile kilit dosyası 8.2 sunucularla uyumlu tutulur.

## 2026-10-07 — Sıkıştırılmış xref desteği uygulandı (Aşama 9)
`src/Pdf/Parser/`: `XrefStreamReader` (W/Index, tip 0/1/2, FlateDecode + PNG predictor), `HybridReader` (klasik tablo + /XRefStm), `ExtendedCrossReference` (object stream nesneleri, okuyucu önceliği), `ExtendedPdfParser`. `App\Pdf\Fpdi` bunu `getPdfParserInstance()` ile kullanır. Testte stok FPDI'nin aynı dosyayı `COMPRESSED_XREF` ile reddettiği, bizim sınıfımızın okuyup sayfa içe aktarabildiği doğrulanıyor. Test dosyaları `tests/Support/CompressedPdfWriter.php` ile üretiliyor (makinede Word/LibreOffice yok; gerçek Office çıktısıyla test yapılamadı).

## 2026-10-07 — Fontlar resources/fonts altında
tFPDF font ölçü önbelleğini (`*.mtx.php`, çalıştırılan PHP) font dizinine yazar. `vendor/` içine yazmasın diye DejaVu Sans (Regular/Bold) `resources/fonts/unifont/`'a kopyalandı; `App\Pdf\Fpdi` `fontpath`'i buraya ayarlar; önbellek dosyaları gitignore'da. `resources/` web'e kapalıdır.

## 2026-10-07 — Upload doğrulama
Uzantı yalnızca ön eleme; asıl karar içerikten: PDF için `%PDF-` imzası (ilk 1024 bayt) + fileinfo MIME (varsa) + tam ayrıştırma + sayfa sayısı. Office için OOXML ZIP imzası + merkezi dizinde `[Content_Types].xml` ve uzantıya uygun ana bölüm (word/ xl/ ppt/), makro (`vbaProject.bin`) reddi; eski formatlarda OLE imzası + UTF-16LE akış adı. ext-zip gerekmez. Etkin boyut sınırı = min(MAX_UPLOAD_SIZE, upload_max_filesize, post_max_size).

## 2026-10-07 — PDF önizleme ve küçük resim önbelleği (Aşama 11)
- PDF.js 6.4.299 **legacy** build (`public/assets/vendor/pdfjs/`, npm integrity doğrulandı): eski tarayıcı ve mobil uyumluluğu için. cmaps, standard_fonts, wasm (openjpeg/jbig2/qcms), iccs dahil; PDF içi JavaScript çalıştırma (quickjs) dosyaları bilinçli olarak kaldırıldı.
- CSP'ye yalnızca `'wasm-unsafe-eval'` eklendi (WebAssembly derleme; JS `eval` değil). PDF.js'in görüntü çözücüleri için gerekli.
- Küçük resimler sunucuda üretilemeyebilir (Ghostscript/Imagick yok). Bu yüzden tarayıcıda PDF.js ile bir kez çizilir, JPEG olarak sunucuya PUT edilir; GD ile yeniden kodlanıp (meta veri / polyglot içerik atılır, en fazla 400 px, 400 KB) `storage/previews/` altına yazılır. Sonraki açılışlarda PDF yerine bu görseller gelir. GD yoksa önbellek kapalıdır, önizleme yine tarayıcıda çalışır.
- Önizleme için PDF `?inline=1` + HTTP Range ile alınır (`disableAutoFetch`): büyük dosyada yalnızca gereken bölümler iner; bu istekler audit'e "indirme" olarak yazılmaz.

## 2026-10-07 — Harici araç altyapısı ve sıkıştırma (Aşama 16)
- `Tools\ProcessRunner`: komut dizi olarak, kabuksuz (`bypass_shell`), çıktılar geçici dosyalara (Windows'ta boru + stream_select çalışmaz), zaman aşımında süreç sonlandırılır. `proc_open` kapalıysa tüm harici araç özellikleri kapanır.
- `Tools\ToolDetector`: `.env` (boş = otomatik, mutlak yol, `disabled`) → PATH ve bilinen kurulum yerleri → `--version` ile doğrulama; sonuç `storage/cache/tools.json`'da önbellekte (varsayılan 1 saat). `Tools\Capabilities`: ghostscript, office (LibreOffice), ocr (Tesseract + Ghostscript/pdftoppm), zip, gd.
- Sıkıştırma: Ghostscript varsa `pdfwrite -dSAFER -dPDFSETTINGS=/printer|/ebook|/screen`, çıktının sayfa sayısı doğrulanır, hata olursa PHP yöntemine geri dönülür. PHP yöntemi (`OptimizingFpdi` + `StreamOptimizer`): JPEG (RGB/Gri/ICC N=1|3, 8 bit, /Decode ve /Mask yok) görüntüler seviyeye göre en büyük kenar 2400/1600/1100 px ve kalite 85/70/55 ile GD üzerinden yeniden kodlanır; filtresiz akışlar Flate ile sıkıştırılır; yalnızca %10'dan fazla küçülen akışlar değiştirilir.
- **En az %3 küçülme yoksa yeni sürüm oluşturulmaz** (`no_change`), kullanıcıya "küçültülemedi" denir ve ulaşılabilen boyut gösterilir.

## 2026-10-07 — Kalıcı karartma yöntemi (Aşama 18)
Siyah kutu çizmek yeterli değildir (alttaki metin seçilebilir/kopyalanabilir kalır). Karartma yapılan sayfa **tamamen görüntüye dönüştürülür** (150 DPI JPEG) ve kutular piksellere yakılır; sayfanın özgün içerik akışı, fontları, gizli katmanları ve açıklamaları çıktıya hiç aktarılmaz. Diğer sayfalar FPDI ile aktarılır (açıklamalar, formlar, ekler, yer imleri, özgün metadata da düşer).
Sayfa görüntüsü: Ghostscript varsa sunucuda (`png16m -dSAFER`); yoksa tarayıcıda PDF.js ile üretilip gönderilir. Tarayıcı görüntüsü sunucuda doğrulanır (tür, boyut, sayfa en-boy oranı ±%3), GD ile yeniden kodlanır ve **kutular sunucu tarafından yeniden yakılır** (istemci kutuyu atlasa bile alan karartılır). Testte gizli metnin UTF-16BE kodlamasının çıktıdaki hiçbir akışta bulunmadığı, aynı aramanın kaynakta bulduğu doğrulanıyor.

## 2026-10-08 — Arayüz doğrulama yöntemi ve `[hidden]` kuralı (Aşama 28)
- `app.css` başında `[hidden] { display: none !important; }`: Bootstrap görünüm yardımcıları (`.d-flex` vb.) `!important` olduğundan `hidden` özniteliğini eziyordu (ör. kaynak seçicide seçili dosya satırı dosya seçilmeden görünüyordu). JS'te görünürlük her yerde `hidden` ile yönetilir.
- Navbar `nav > .container` yapısında: `.navbar` yatay dolgusu aynı öğedeki `.container` dolgusunu sıfırlıyordu, mobil menü kenara yapışıyordu.
- Headless Chrome ile doğrulama notları: `--virtual-time-budget` PDF.js worker'ı ile güvenilir değil (PDF hiç indirilmeden görüntü alınır), gerçek zamanlı `--timeout` kullanılır. Uygulama sayfaları `frame-ancestors 'none'` ve COOP `same-origin` gönderdiği için iframe/popup ile ölçüm yalnızca geçici, aynı COOP başlıklı bir test sayfasından yapılabilir; bu dosyalar doğrulamadan sonra silinir ve depoya girmez. Headless pencere en az ~500 px genişlikte çizildiği için 320/390 px ölçümleri iframe/popup ile yapıldı.

## 2026-10-08 — Sayı ve tarih biçimleri (Aşama 29)
- Boyutlar: ondalık ayırıcı çeviri anahtarı `common.decimal_separator` (TR `,`, EN `.`); `Size::format` etkin dili kullanır, JS `formatSize` aynı kuralı `Intl.NumberFormat(locale, {useGrouping: false})` ile uygular (TR "13,5 KB", EN "13.5 KB"). Yüzdeler çeviri metninde (TR "%30", EN "30%").
- Tarihler yalnızca sunucuda `DateFormatter` ile (uygulama saat dilimi; TR `d.m.Y H:i`, EN `Y-m-d H:i`) biçimlenir. Saklama süresi güncellendiğinde API görünen metni (`expiry.status`) döndürür; JS tarayıcı saat dilimiyle ayrıca biçimlendirme yapmaz (sayfa ile JS arasında tutarsızlık olmaz).

## 2026-10-08 — Test kapsamı ve uçtan uca testler (Aşama 30)
- Spec §45 uçtan uca akış `Feature\HappyPathTest` ile **süreç içi HTTP** üzerinden test edilir (gerçek PDF, gerçek test veritabanı ve storage; yükle → döndür → sürüm → hash → audit → indir). Tarayıcı otomasyonu (Selenium/Playwright) Node/npm yasağı ve cPanel hedefi nedeniyle test paketinde yok; tarayıcı tarafı Aşama 11, 28 ve 29'da headless Chrome ile elle doğrulandı.
- `Feature\LocalizationPagesTest`: 25 yol × TR/EN; eksik anahtar (`Translator::missingKeys`), çözülmemiş `grup.anahtar` metni, EN sayfada Türkçe harf, `<html lang>`, menü ve JS'e giden çevirilerin sayfa diliyle aynı olması. Aşama 29'daki elle taramanın kalıcı hali.
- Zip eklentisine bağlı testler eklenti yoksa atlanır, "zip yokken" davranış testi eklenti varsa atlanır; tam doğrulama için paket iki kez çalıştırılır: `php vendor/bin/phpunit` ve `php -d extension=zip vendor/bin/phpunit`.

## 2026-10-08 — Web kurulum sayfası (Aşama 31)
- `/install` (dil öneksiz): `INSTALL_KEY` boşsa 404; 16 karakterden kısa anahtar kabul edilmez. Yanlış denemeler IP başına (HMAC'siz SHA-256 özetiyle, düz IP yok) `storage/cache/install-attempts.json`'da sayılır — veritabanı henüz hazır olmayabileceği için DB rate limiter kullanılmaz; 15 dakikada 5 hata → kilit. Oturumda anahtarın özeti tutulur; `.env`'de anahtar değişince oturum düşer.
- Sayfa yalnızca bilgilendirir ve migration çalıştırır; `.env`'yi **yazmaz** (web sürecinin yapılandırma dosyasını değiştirmesi riskli). `APP_KEY` eksikse rastgele öneri gösterilir, kaydedilmez.
- Veritabanı hataları kategoriye indirgenir (erişim reddi / veritabanı yok / sunucuya ulaşılamıyor / diğer); ham mesaj yalnızca log'a. Yönetici sayfasında cron komutu için sunucu yolu gösterilir (anahtarla korunan sayfa).
- `bootstrap/app.php` en başta PHP < 8.2 için iki dilli düz metin mesajı verir (cPanel varsayılan / cron CLI sürümü eski olabilir).
- `public/` içeriği `public_html`'e kopyalanan yerleşimde `app-root.php` `public/.htaccess` ile doğrudan erişime kapalı.

## 2026-10-08 — İstek başı PHP uyarıları (Aşama 33)
- PHP, `post_max_size` aşımı gibi uyarıları betik başlamadan basar; sunucuda `display_errors=On` ise bu HTML JSON yanıtını bozar ve teknik metin gösterir. Uygulamanın `ini_set('display_errors', '0')` çağrısı bunu engelleyemez.
- Çözüm sunucu düzeyinde, iki SAPI için: `public/.htaccess` içinde `<IfModule mod_php.c> php_flag display_errors Off </IfModule>` (koşulsuz `php_flag` PHP-FPM/CGI'de 500 hatası verir) ve `public/.user.ini` (`display_errors = Off`, PHP-FPM/CGI/LiteSpeed). İkisi de nokta ile başladığı için web erişimine kapalıdır.

## 2026-10-08 — Tasarım sistemi ve koyu tema (Aşama 34)
- Tema: Bootstrap 5.3 `data-bs-theme` (`<html>`). Tercih `localStorage["tfb-theme"]` = `light | dark | auto` (erişilemezse `auto`); `auto` `prefers-color-scheme`'i izler. Flaşı önleyen kod istenen inline script yerine `<head>`'de CSS'ten önce **engelleyici yerel dosya** (`assets/js/theme-init.js`): CSP `script-src 'self'` gevşetilmez. Tema seçicideki ikon/onay işareti `<html data-theme-preference>` ile CSS'te seçilir.
- Bootstrap değişkenleri `:root,[data-bs-theme=light]` ve `[data-bs-theme=dark]` altında ayrı ayrı tanımlanır (yalnızca `:root`'ta tanımlamak koyu temayı ezerdi). `--bs-body-bg` yüzey (kart/form/tablo/menü/modal) rengidir; sayfa zemini body'de `--tfb-bg`.
- Renk: vurgu `#E5484D` (ikon, gradient, logo); beyaz metinle 3.9:1 olduğu için dolu buton ve bağlantılarda `#C9373C` (5.1:1) / bağlantı `#B02F34`; koyu temada bağlantı `#FF8A8E`. Tehlike/başarı renkleri de token'a bağlandı (Bootstrap varsayılan outline-danger 4.2:1 idi).
- PDF sayfaları (küçük resim, görüntüleyici, karartma, imza alanı) iki temada da kâğıt beyazı kalır; imza alanının beyaz kalması çizilen siyah mürekkebin PDF'e aynen aktarılması içindir.
- Font: Inter değişken (yalnızca latin + latin-ext, Türkçe karakterler dahil, ~133 KB), yerel; `@fontsource-variable/inter` 5.3.0 npm arşivinden integrity doğrulanarak alındı.

## 2026-10-08 — Logo ve marka varlıkları (Aşama 35)
- Logo: köşesi kıvrık belge + "TFB" monogramı; harfler font yerine geometrik stroke path'lerle (32'lik ızgara, 16 px'te 1 px çizgi) çizilir, kelime işareti ("TFB" metin rengi, "PDF" mercan) aynı harf sistemiyle.
- `<img>` içindeki SVG sayfanın temasını değil işletim sistemi tercihini izlediği için dosya halleri sabit varyanttır: `logo-full.svg` (açık zemin), `logo-full-inverse.svg` (koyu zemin), `logo-mark.svg` (temadan bağımsız). Uygulama içinde logo satır içi SVG'dir (`partials/logo.php`) ve renkleri CSS değişkenlerinden alır.
- PNG'ler (apple-touch 180, manifest 192/512, favicon 32, og:image 1200×630) SVG/HTML kaynaklarından headless Chrome + GD ile üretildi; üretim için Node/ImageMagick gerekmez. Manifest `site.webmanifest` adındadır (kök .htaccess `.json` dosyalarını reddeder).

## 2026-10-08 — Sayfa tasarımları (Aşama 36)
- Footer'daki sürüm ve GitHub bağlantısı `config/app.php` içindeki `version` ve `repository` değerlerinden gelir (tek kaynak; .env'e taşınmadı çünkü kuruluma özgü değil).
- Boş durumlar (ikon + başlık + kısa yönlendirme) her liste kartında gösterilir; Belgelerim boşsa yükleme bağlantısı verilir.
- Yapışkan navbar bağlantı hedeflerini örtmesin diye `html { scroll-padding-top: 5.5rem; }`.
- Ana sayfa yükleme alanı seçilen dosyaları ad, boyut ve dosya başına ilerleme çubuğuyla listeler (`renderFileChips`); diğer araç sayfaları mevcut durum alanını kullanmaya devam eder.

## 2026-10-08 — Sürümleme ve yayın paketi (v1.0.0)
- Semantic Versioning; ilk kararlı sürüm 1.0.0. Sürümün tek kaynağı `config/app.php` `version` (footer); etiket `vX.Y.Z`. Değişiklikler `CHANGELOG.md` (Keep a Changelog, Türkçe başlıklar).
- `.env` anahtarı kaldırma/yeniden adlandırma, minimum PHP/MySQL yükseltmesi veya elle müdahale gerektiren güncelleme MAJOR sayılır; yeni migration tek başına MINOR'dur (`/install` ile çalıştırılır).
- Release'e `vendor/` dahil hazır ZIP eklenir: hedef kitle SSH/Composer olmayan cPanel kullanıcıları. Paket `bin/build-release.php` ile etiketlenmiş commit'ten (`git archive`) üretilir, `composer install --no-dev` uygulanır; testler ve geliştirme notları (CLAUDE.md, PROMPT, COMMANDS_LOG, PROGRESS) hariç. ZIP PharData ile yazılır (zip eklentisi gerektirmez); yanında SHA-256 dosyası.
- GitHub'ın otomatik kaynak arşivleri tam kalsın diye `.gitattributes export-ignore` kullanılmadı; hariç tutma betikte.
