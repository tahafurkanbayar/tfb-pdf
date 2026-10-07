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
