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
