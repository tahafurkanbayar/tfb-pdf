# Bilinen Sınırlamalar

Çalışmayan, kısmen çalışan veya bilinçli olarak eklenmeyen her şey burada listelenir. README'deki "Limitations" bölümü buradan beslenir.

## Bilinçli olarak eklenmeyenler (spec gereği)
- Nitelikli elektronik imza (QES), eIDAS nitelikli imza, düzenlenmiş e-imza.
- Resmi / devlet kimlik doğrulaması.
- Social login, OAuth, abonelik, ödeme.
- Analytics, telemetry, tracking.

## Teknik sınırlamalar
- **Şifreli PDF'ler** (kullanıcı veya sahip şifresi, `/Encrypt`) desteklenmez; yükleme sırasında açık bir mesajla reddedilir.
- Object stream'lerde PNG/TIFF predictor kullanılmışsa (nadir) dosya okunamaz ve geçersiz olarak reddedilir. Xref stream'lerde PNG predictor desteklenir.
- Sıkıştırılmış xref desteği sentetik test dosyalarıyla doğrulandı; geliştirme makinesinde Word/LibreOffice olmadığı için gerçek Office çıktısıyla test edilmedi.
- FPDI sayfaları şablon (XObject) olarak yeniden oluşturur: birleştirme/bölme/sıralama/döndürme/filigran sonrasında **bağlantılar, açıklamalar (notlar), yer imleri, doldurulabilir form alanları, ek dosyalar ve dijital imzalar** yeni dosyaya aktarılmaz; görünüm korunur. Kullanıcıya işlem sonucunda uyarı gösterilir.
- PDF çıktısı bellekte oluşturulur (tFPDF): çok büyük birleştirmeler PHP `memory_limit`'e takılabilir. Sayfa sınırı `MAX_PAGES_PER_DOCUMENT` ile korunur.
- Birleştirme için listeye eklenen her dosya önce "Belgelerim"e yüklenir; listeden kaldırmak dosyayı silmez (saklama süresi sonunda silinir).
- PHP tabanlı sıkıştırma (Ghostscript yokken) yalnızca JPEG görüntüleri ve filtresiz akışları optimize eder: CMYK JPEG, JPEG2000, Flate ile sıkıştırılmış görüntüler ve fontlar olduğu gibi kalır. Metin ağırlıklı PDF'lerde kazanç genellikle olmaz (bu durumda yeni sürüm oluşturulmaz).
- Ghostscript ile sıkıştırma yolu, geliştirme makinesinde Ghostscript kurulu olmadığı için yalnızca sahte çalıştırıcıyla (komut ve geri dönüş davranışı) test edildi; gerçek Ghostscript çıktısıyla test edilmedi.
- Karartma yapılan sayfalar görüntüye dönüşür: bu sayfalarda metin seçilemez/aranamaz, dosya boyutu artabilir, görüntü kalitesi 150 DPI ile sınırlıdır. Tek işlemde en fazla 30 sayfa karartılabilir.
- Karartma yalnızca seçilen alanlara uygulanır; aynı bilgi diğer sayfalarda geçiyorsa kullanıcının onları da seçmesi gerekir (arayüzde açık uyarı var). Otomatik hassas veri arama yoktur.
- Ghostscript yoksa karartılan sayfanın görüntüsü tarayıcıdaki PDF.js çizimidir; PDF.js'in desteklemediği öğeler (nadir) görüntüde eksik çıkabilir. Kullanıcı bunu önizlemede görür.
- Karartma düzenleyicisinde alan seçimi işaretçi (fare/dokunmatik) gerektirir; klavye kullanıcıları için "tüm sayfayı karart" seçeneği vardır, serbest alan seçimi yoktur.
- OCR yalnızca sunucuda Tesseract + (Ghostscript veya pdftoppm) varsa çalışır; geliştirme makinesinde bu araçlar olmadığından OCR akışı sahte çalıştırıcıyla (komutlar, dil seçimi, birleştirme) test edildi, gerçek Tesseract ile test edilmedi. Tek seferde en fazla 50 sayfa. OCR çıktısı sayfaları görüntü + görünmez metin katmanı olarak yeniden oluşturur.
- Office → PDF yalnızca sunucuda LibreOffice varsa çalışır; geliştirme makinesinde LibreOffice olmadığından dönüşüm sahte çalıştırıcıyla (komut, profil dizini, ortam, hata kaydı) test edildi, gerçek LibreOffice ile test edilmedi. Makro içeren OOXML paketleri reddedilir; eski formatlar (DOC/XLS/PPT) makro içerebilir ancak headless dönüşümde makrolar çalıştırılmaz.
- Dışa aktarma (ve bölme sonuçlarının toplu ZIP indirmesi) PHP `zip` eklentisi gerektirir. Yerel XAMPP'te bu eklenti kapalı olduğundan tarayıcıdan denenemedi; testler `php -d extension=zip` ile çalıştırıldı.
- İmza akışı basit elektronik imzadır: imzalayanın kimliği doğrulanmaz (bağlantıya sahip olan imzalayabilir), PDF'e kriptografik dijital imza (PAdES) eklenmez. Nitelikli elektronik imza / eIDAS bilinçli olarak yoktur.
- E-posta gönderimi SMTP yapılandırmasına bağlıdır; geliştirme ortamında SMTP olmadığından gerçek gönderim test edilmedi (devre dışı yolu test edildi).
- İmzalayan sayfasında önizleme en fazla ilk 30 sayfayı gösterir.
