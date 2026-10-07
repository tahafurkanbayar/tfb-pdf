# Production-Ready PDF Yönetim ve Düzenleme Platformu

> Bu dosya projenin asıl gereksinim belgesidir (spec). Proje sahibinin 2026-10-07 tarihinde verdiği prompt birebir saklanmıştır. Değiştirilmemelidir; kararlar ve sapmalar `DECISIONS.md` içinde tutulur.

Boş bir repository üzerinde, ILovePDF Premium'a alternatif olacak, modern, güvenli ve production-ready bir **PDF yönetim ve düzenleme platformu** geliştir.

Uygulamanın temel amacı kullanıcıların PDF dosyalarını yüklemesi, düzenlemesi, dönüştürmesi, birleştirmesi, bölmesi, sıkıştırması, önizlemesi ve indirmesidir.

Uygulama öncelikli olarak **standart cPanel shared hosting ortamlarında çalışabilecek şekilde** tasarlanmalıdır.

Aynı kod tabanı ileride VPS, özel sunucu veya daha büyük bir SaaS sistemine taşınabilecek şekilde temiz ve genişletilebilir olmalıdır.

---

# 1. Zorunlu Teknoloji Stack'i

Aşağıdaki teknolojileri kullan.

## Backend

* PHP 8.3+
* Composer
* PDO
* MySQL 8+ veya MariaDB

**Aşağıdaki teknolojileri kullanma:**

* Laravel
* Symfony
* CodeIgniter
* WordPress
* Python
* FastAPI
* Node.js
* PostgreSQL
* MongoDB
* Redis
* Docker

Framework kullanmak yerine **modüler, nesne yönelimli ve temiz PHP** kullan.

Kod yapısı kolay anlaşılabilir ve sürdürülebilir olmalı.

---

# 2. Frontend

Frontend için:

* HTML5
* CSS3
* Bootstrap 5
* Vanilla JavaScript
* Fetch API / AJAX
* PDF.js

kullan.

Frontend için Node.js veya herhangi bir build sistemi zorunlu olmamalıdır.

Uygulama aşağıdaki komutlara ihtiyaç duymadan çalışabilmelidir:

```text
npm install
npm run build
node
```

Amaç, proje dosyalarının doğrudan cPanel'e yüklenerek çalıştırılabilmesidir.

---

# 3. cPanel Uyumluluğu

Bu proje için **cPanel uyumluluğu kritik bir gereksinimdir.**

Uygulama standart shared hosting ortamında çalışabilmelidir.

Aşağıdakileri zorunlu kılma:

* Docker
* VPS
* Root erişimi
* SSH
* Node.js
* Python
* PostgreSQL
* Redis
* Supervisor
* systemd
* sürekli çalışan background worker

Uygulama mümkün olduğunca şu cPanel özellikleriyle çalışmalıdır:

```text
cPanel
├── File Manager / FTP
├── PHP Selector
├── MySQL Databases
├── phpMyAdmin
└── Cron Jobs
```

SSH erişimi olmayan bir hosting kullanıcısının da uygulamayı kurabilmesini hedefle.

Composer hosting üzerinde bulunmuyorsa:

```text
Yerel bilgisayarda composer install
        ↓
vendor/
        ↓
cPanel'e yükleme
```

şeklinde alternatif kurulum yöntemi sun.

---

# 4. Uygulama Dili

Uygulama başlangıçtan itibaren **çoklu dil destekli** olmalıdır.

İlk sürümde:

* Türkçe (`tr`)
* İngilizce (`en`)

desteklenmelidir.

Kod içerisinde kullanıcıya gösterilecek metinleri doğrudan HTML/PHP dosyalarına yazma.

Örneğin bunu yapma:

```php
echo "Dosya başarıyla yüklendi.";
```

Bunun yerine:

```php
echo __('upload.success');
```

benzeri bir çeviri sistemi kullan.

---

# 5. Çoklu Dil Sistemi

Merkezi bir localization/i18n sistemi oluştur.

Örneğin:

```text
resources/
└── lang/
    ├── tr/
    │   └── messages.php
    └── en/
        └── messages.php
```

veya daha uygun bir yapı kullanabilirsin.

Çeviri anahtarları anlamlı ve kategorize edilmiş olmalı.

Örneğin:

```text
common.save
common.cancel
common.delete

upload.title
upload.success
upload.invalid_file
upload.file_too_large

pdf.merge
pdf.split
pdf.rotate
pdf.compress

errors.generic
errors.permission_denied
errors.processing_failed
```

Tüm kullanıcı arayüzü metinleri bu sistem üzerinden gelmeli.

---

# 6. Dil Seçimi

Kullanıcı arayüzünden dili değiştirebilmeli.

Örneğin:

```text
Türkçe
English
```

Dil seçimi:

1. Kullanıcının açık tercihini saklamalı.
2. Tercih yoksa tarayıcı dilini algılayabilmeli.
3. Tarayıcı dili desteklenmiyorsa varsayılan olarak Türkçe kullanılmalı.

Dil tercihi kullanıcı hesabı gerektirmeden cookie/session gibi uygun bir yöntemle saklanabilir.

Dil değiştirildiğinde kullanıcı aynı sayfada mümkün olduğunca kalmalı.

---

# 7. URL Yapısı ve Dil

Gelecekte SEO açısından genişletilebilecek bir yapı oluştur.

Uygun bir yapı kullan:

```text
/tr/
/en/
```

veya

```text
/tr/tools/merge
/en/tools/merge
```

Ancak gereksiz yere karmaşık routing sistemi oluşturma.

İlk sürümde SEO açısından anlamlı public sayfalar için dil bazlı URL yapısını destekle.

---

# 8. Türkçe ve İngilizce İçerik Kalitesi

Türkçe çeviriler doğal ve profesyonel olmalı.

Makine çevirisi gibi görünen ifadeler kullanma.

Örneğin:

```text
PDF Birleştir
PDF Böl
PDF Sıkıştır
Sayfaları Düzenle
Filigran Ekle
OCR
Office → PDF
```

İngilizce karşılıkları:

```text
Merge PDF
Split PDF
Compress PDF
Reorder Pages
Add Watermark
OCR
Office → PDF
```

şeklinde profesyonel bir terminoloji kullan.

---

# 9. PDF İşleme

PHP ile uyumlu PDF kütüphaneleri kullan.

Tercih edilen kütüphaneler:

* FPDI
* FPDF
* Setasign FPDI

Composer üzerinden yönet.

İleri seviye PDF işlemleri için gerekli olması durumunda aşağıdaki sistem araçlarını **opsiyonel** olarak destekle:

* Ghostscript
* LibreOffice Headless
* Tesseract OCR

Ancak bu araçların standart cPanel hostingde bulunacağını varsayma.

---

# 10. Opsiyonel Sistem Araçları

Bir özellik için harici binary gerekiyorsa uygulama öncelikle bunun mevcut olup olmadığını kontrol etmeli.

Örneğin:

```text
LibreOffice mevcut
→ Office → PDF aktif

LibreOffice mevcut değil
→ Office → PDF devre dışı
```

Aynı yaklaşımı:

* Ghostscript
* Tesseract

için de kullan.

Harici araç bulunmadığında uygulama hata vermemeli.

Kullanıcıya açık bir mesaj göster:

> Bu özellik mevcut hosting ortamında kullanılamıyor.

Ancak mümkün olan diğer PDF özellikleri çalışmaya devam etmeli.

---

# 11. Ana Kullanıcı Akışı

Temel işlem akışı:

```text
Dosya yükle
      ↓
Dosyayı doğrula
      ↓
Orijinali koru
      ↓
İşlemi gerçekleştir
      ↓
Yeni version oluştur
      ↓
SHA-256 hash oluştur
      ↓
Audit log oluştur
      ↓
Sonucu göster
      ↓
İndir
```

Orijinal dosya **asla değiştirilmemeli veya üzerine yazılmamalıdır.**

---

# 12. Desteklenecek PDF İşlemleri

Aşağıdaki özellikleri implement et:

## PDF Birleştirme

Birden fazla PDF'i tek PDF'de birleştir.

Kullanıcı:

* Birden fazla PDF yükleyebilmeli
* Dosyaların sırasını değiştirebilmeli
* Dosya kaldırabilmeli
* Önizleme yapabilmeli
* Birleştirme işlemini başlatabilmeli

---

## PDF Bölme

PDF'i:

* Tek tek sayfalara
* Sayfa aralıklarına
* Seçilen sayfalara

bölebil.

Örnek:

```text
1-3
5
8-12
```

gibi sayfa aralıklarını destekle.

Geçersiz aralıkları işlem başlamadan önce yakala.

---

## Sayfa Sıralama

PDF sayfalarını sürükle-bırak yöntemiyle yeniden sırala.

Sayfa thumbnail'ları göster.

Orijinal dosyayı değiştirme.

---

## Sayfa Döndürme

Destekle:

```text
90°
180°
270°
```

Tek veya birden fazla sayfayı döndürmeye izin ver.

---

## PDF Sıkıştırma

PDF boyutunu küçült.

Ancak shared hosting ortamında Ghostscript bulunmayabileceğini dikkate al.

Ghostscript varsa gelişmiş compression kullan.

Yoksa güvenli PHP tabanlı optimizasyon mümkünse kullan.

Gerçek bir sıkıştırma yapılmadıysa kullanıcıya yapılmış gibi gösterme.

---

## Filigran

Metin tabanlı filigran ekle.

Kullanıcı aşağıdakileri belirleyebilsin:

* Metin
* Konum
* Döndürme
* Opaklık
* Font boyutu

---

## Redaction / Kalıcı Karartma

Hassas bilgileri PDF'den gerçekten kaldırmaya çalış.

Sadece üstüne siyah kutu çizmek yeterli değildir.

Eğer belirli PDF yapılarında güvenli ve gerçek redaction garanti edilemiyorsa kullanıcıya açık uyarı göster.

---

## OCR

Taranmış PDF'lerde OCR desteği sağla.

Tesseract mevcutsa kullan.

Mevcut değilse özelliği devre dışı bırak.

OCR sonucunun %100 doğru olmayabileceğini belirt.

---

## Office → PDF

Destekle:

```text
DOC
DOCX
XLS
XLSX
PPT
PPTX
```

LibreOffice Headless mevcutsa kullan.

Mevcut değilse özelliği devre dışı bırak ve kullanıcıya nedenini bildir.

---

# 13. PDF Önizleme

PDF.js kullanarak tarayıcı tabanlı PDF önizleme oluştur.

Özellikle:

* Birleştirme
* Bölme
* Sıralama
* Döndürme
* Filigran
* Redaction

işlemlerinde sayfa önizlemeleri göster.

Büyük PDF'lerde performans için thumbnail cache kullan.

---

# 14. Dosya Doğrulama

Yüklenen dosyaları güvenli şekilde doğrula.

Kontrol et:

* Uzantı
* MIME type
* Gerçek dosya içeriği
* PDF geçerliliği
* Dosya boyutu
* Sayfa sayısı

Kullanıcı tarafından gönderilen uzantıya güvenme.

---

# 15. Güvenli Dosya İsimleri

Orijinal filename'i filesystem path olarak doğrudan kullanma.

Path traversal saldırılarını engelle:

```text
../
../../
/etc/
C:\
null byte
```

gibi değerleri engelle.

Dosyalar için güvenli random identifier kullan.

Orijinal filename yalnızca metadata olarak saklanabilir.

---

# 16. Storage

Private dosyaları mümkün olduğunca public web root dışında tut.

Örneğin:

```text
storage/
├── documents/
├── versions/
├── previews/
├── temporary/
├── exports/
└── logs/
```

Bu dizinler doğrudan HTTP üzerinden erişilebilir olmamalıdır.

İndirme işlemleri PHP kontrollü endpoint üzerinden yapılmalı.

---

# 17. Immutable Versioning

Orijinal dosya:

```text
original.pdf
```

olarak korunmalı.

İşlem sonucunda:

```text
v001.pdf
v002.pdf
v003.pdf
```

gibi yeni version'lar oluştur.

Her version için:

```text
version_id
document_id
version_number
filename
storage_path
file_size
sha256
created_at
operation_id
```

sakla.

---

# 18. SHA-256 Hash

Her orijinal ve oluşturulan PDF için SHA-256 hash hesapla.

Hash'i database'de sakla.

Hash:

* Dosya bütünlüğü
* Audit
* Version kontrolü

amacıyla kullanılmalı.

Hash'in tek başına hukuki geçerlilik veya belge doğrulaması anlamına gelmediğini belirt.

---

# 19. Audit Log

Append-only audit log sistemi oluştur.

Aşağıdaki işlemleri kaydet:

* Upload
* Merge
* Split
* Reorder
* Rotate
* Compress
* Watermark
* Redact
* OCR
* Office conversion
* Download
* Export
* Delete
* Expiry
* Signature events

Her kayıt mümkün olduğunca:

```text
event_id
document_id
operation_id
event_type
timestamp
input_hash
output_hash
status
metadata
error_message
```

içermeli.

---

# 20. Dosya Süresi / Expiry

Dosyaların otomatik olarak silinebileceği expiry sistemi oluştur.

Örneğin:

```text
1 gün
7 gün
30 gün
Manuel sil
```

gibi seçenekler sunulabilir.

Shared hosting ortamında sürekli çalışan worker kullanma.

Bunun yerine:

1. cPanel Cron Job
2. Manuel cleanup
3. Güvenli opportunistic cleanup

yaklaşımını destekle.

---

# 21. cPanel Cron

Cleanup için ayrı bir script oluştur.

Örneğin:

```text
cron/cleanup.php
```

Bu script:

* Süresi dolan dosyaları
* Temporary dosyaları
* Gereksiz preview dosyalarını

temizleyebilmeli.

README'de cPanel:

```text
Cron Jobs
```

üzerinden nasıl ayarlanacağını anlat.

---

# 22. Download

Dosya indirmelerini PHP kontrollü endpoint üzerinden gerçekleştir.

İndirme öncesinde:

* Dosyanın varlığını kontrol et
* Yetkiyi kontrol et
* Path traversal kontrolü yap
* Beklenen storage dizini içerisinde olduğunu doğrula
* Uygun HTTP headers gönder

Gerçek filesystem path'ini kullanıcıya gösterme.

---

# 23. Delete

Dosya silme işlemi için confirmation göster.

Örneğin Türkçe:

> Bu işlem belgeyi ve oluşturulan tüm sürümlerini kalıcı olarak silecektir. Devam etmek istediğinize emin misiniz?

İngilizce:

> This will permanently delete the document and all generated versions. Are you sure you want to continue?

Silme işlemini GET request ile gerçekleştirme.

CSRF koruması kullan.

Silme işlemini audit log'a kaydet.

---

# 24. Export

Kullanıcının kendi verilerini dışa aktarabilmesini sağla.

Mümkün olduğunca:

* PDF dosyaları
* Version'lar
* Metadata
* İşlem geçmişi
* Audit log

export içerisinde bulunmalı.

---

# 25. Backup

README'de:

* MySQL backup
* Document storage backup
* Configuration backup

konularını anlat.

cPanel Backup ve manuel backup yöntemlerini ayrı ayrı açıkla.

Uygulama yapılmamış bir backup'ı yapılmış gibi gösterme.

---

# 26. Basit İmza Workflow'u

Temel bir PDF imza workflow'u oluştur.

Destekle:

* İmza alanı
* İmzalayacak kişi
* Davet
* Consent kaydı
* İmza event'leri
* Final PDF
* Final PDF SHA-256 hash
* Audit log

Ancak kesinlikle aşağıdakileri implement etme:

* Qualified Electronic Signature
* Regulated Electronic Signature
* eIDAS Qualified Signature
* Government identity verification
* Government digital identity
* Resmi kimlik doğrulama

Bu sistemi hukuken nitelikli elektronik imza hizmeti olarak sunma.

---

# 27. Email

Email sistemi opsiyonel olmalı.

SMTP bilgileri `.env` üzerinden sağlanırsa email gönderimini destekle.

SMTP yapılandırılmamışsa uygulama çalışmaya devam etmeli.

Credential bilgilerini hard-code etme.

---

# 28. Authentication

İlk sürümde karmaşık SaaS kullanıcı sistemi oluşturma.

Ancak mimari gelecekte kullanıcı sistemi eklenebilecek şekilde tasarlanmalı.

İlk sürüm için gereksiz yere:

* Social login
* OAuth
* Subscription
* Billing

ekleme.

---

# 29. API'ye Hazır Mimari

İlk sürümde tam bir mobil API yazmak zorunda değilsin.

Ancak business logic'i view/controller içerisine gömme.

Örneğin:

```text
Controller
    ↓
Service
    ↓
Repository
    ↓
Database
```

yapısını kullan.

PDF işlemleri için:

```text
PdfService
DocumentService
StorageService
HashService
AuditService
OperationService
ExportService
SignatureService
```

gibi servisler oluştur.

Gelecekte REST API veya mobil uygulama eklendiğinde aynı servisler kullanılabilmeli.

---

# 30. Database

Minimum tablolar:

```text
documents
document_versions
operations
audit_events
file_expiry
signature_requests
signature_events
settings
```

PDO prepared statements kullan.

SQL injection'a karşı güvenli ol.

Migration sistemi oluştur.

---

# 31. Güvenlik

En azından aşağıdakileri uygula:

### CSRF

State-changing request'lerde CSRF protection.

### SQL Injection

PDO prepared statements.

### XSS

Output escaping.

### Session Security

Güvenli session ayarları.

### Upload Security

Dosya doğrulama.

### Path Traversal

Güvenli path çözümleme.

### Error Disclosure

Kullanıcıya:

* SQL query
* Stack trace
* Server path
* Credential
* `.env`

gibi bilgiler gösterme.

---

# 32. Rate Limiting

Public web kullanımına hazırlanabilecek basit abuse protection oluştur.

Örneğin:

* Maksimum dosya boyutu
* Maksimum dosya sayısı
* Maksimum sayfa sayısı
* İşlem limiti
* Upload rate limit

Redis kullanma.

Shared hosting uyumlu database/session/file tabanlı yaklaşım kullan.

---

# 33. UI / UX

Modern ve sade bir Bootstrap 5 arayüz oluştur.

Eski tip PHP admin paneli görünümünden kaçın.

Dashboard'da:

* Dosya yükleme
* PDF araçları
* Son belgeler
* Son işlemler
* Storage kullanımı
* Süresi yaklaşan dosyalar
* Dil seçimi

bulunsun.

---

# 34. Responsive Design

Uygulama:

* Desktop
* Tablet
* Mobile

ekranlarında düzgün çalışmalı.

PDF sayfa sıralama gibi karmaşık işlemlerde mobil kullanılabilirliği de düşün.

---

# 35. UI State'leri

Her önemli işlem için:

* Empty
* Loading
* Processing
* Success
* Warning
* Recoverable Error
* Fatal Error

durumlarını oluştur.

Örneğin Türkçe:

```text
Dosya yükleniyor...
PDF işleniyor...
PDF başarıyla oluşturuldu.
İşlem sırasında bir hata oluştu.
```

İngilizce:

```text
Uploading file...
Processing PDF...
PDF created successfully.
An error occurred while processing the file.
```

---

# 36. PDF Değişiklik Uyarıları

Aşağıdaki durumlarda kullanıcıyı bilgilendir:

* Font değişikliği
* Form değişikliği
* Metadata değişikliği
* Encryption değişikliği
* Dijital imza geçersizliği
* Embedded file değişikliği
* Layout değişikliği

Office → PDF dönüşümünde özellikle:

* Font substitution
* Layout changes
* Form changes
* Embedded objects
* Digital signature invalidation

konularında uyarı göster.

---

# 37. Gizlilik

Uygulama varsayılan olarak kullanıcı dosyalarını üçüncü taraf PDF servislerine göndermemeli.

Kullanıcıya açıkça:

> Dosyalarınız bu uygulamanın kurulu olduğu sunucuda saklanır ve varsayılan olarak üçüncü taraf PDF işleme servislerine gönderilmez.

mesajı gösterilebilir.

İngilizce:

> Your files are stored on the server where this application is installed and are not sent to third-party PDF processing services by default.

---

# 38. Telemetry ve Analytics

Aşağıdakileri ekleme:

* Google Analytics
* Sentry
* PostHog
* Mixpanel
* Tracking pixel
* User behavior tracking
* Telemetry

Uygulama varsayılan olarak üçüncü taraflara kullanım verisi göndermemeli.

---

# 39. Düşük Riskli Kullanım Uyarısı

Uygulamada görünür bir bilgilendirme bulunmalı.

Uygulama:

* Resmi belge doğrulama sistemi değildir.
* Nitelikli elektronik imza sistemi değildir.
* Devlet kimlik doğrulama sistemi değildir.
* Enterprise document governance sistemi değildir.
* Adobe Acrobat seviyesinde tam PDF editörü değildir.

Bu uyarıları hem Türkçe hem İngilizce sun.

---

# 40. Erişilebilirlik

Temel accessibility kurallarına uy.

Kullan:

* Semantic HTML
* Label
* Keyboard navigation
* Accessible buttons
* Focus states
* Hata mesajları
* Gerekli yerlerde ARIA

Önemli işlemleri sadece mouse hover'a bağlama.

---

# 41. Logging

Teknik hataları teşhis etmek için log sistemi oluştur.

Loglara:

* Timestamp
* Operation
* Document ID
* Request ID
* Status
* Error type

gibi bilgiler eklenebilir.

Ancak:

* PDF içeriği
* Şifreler
* API keys
* SMTP credentials
* Authentication tokens

loglanmamalı.

---

# 42. Performans

Shared hosting kaynaklarını dikkate al.

Gereksiz şekilde büyük dosyaları RAM'e yükleme.

Download işlemlerinde mümkün olduğunca streaming kullan.

Limitleri `.env` üzerinden yapılandırılabilir yap:

```text
MAX_UPLOAD_SIZE
MAX_FILES_PER_OPERATION
MAX_PAGES_PER_DOCUMENT
```

CPU ve RAM'in sınırsız olduğunu varsayma.

---

# 43. Hata Yönetimi

Merkezi error handling oluştur.

Hataları kategorilere ayır:

```text
Validation Error
Processing Error
Storage Error
Database Error
Permission Error
External Tool Unavailable
Unexpected Error
```

Kullanıcıya teknik olmayan anlaşılır mesaj göster.

Hataların da Türkçe ve İngilizce karşılıkları bulunmalı.

---

# 44. Database Transactions

Birden fazla database işleminin beraber tamamlanması gereken durumlarda transaction kullan.

Örneğin:

```text
Operation oluştur
      ↓
Version oluştur
      ↓
Hash kaydet
      ↓
Audit event oluştur
```

Kritik bir aşama başarısız olursa uygun şekilde rollback yap.

---

# 45. Testler

En az aşağıdaki testleri oluştur.

## Unit Tests

Test et:

* Filename sanitization
* File validation
* SHA-256
* Page range parsing
* PDF merge
* PDF split
* PDF rotation
* Version numbering
* Expiry logic
* Localization system

## Integration Test

Şu akışı test et:

```text
Upload
→ Database
→ PDF processing
→ New version
→ Hash
→ Audit event
```

## End-to-End Happy Path

Gerçek bir test PDF'i ile:

```text
PDF yükle
→ PDF işle
→ Version oluştur
→ Hash oluştur
→ Audit event oluştur
→ Sonucu indir
```

akışını test et.

---

# 46. Localization Testleri

Hem Türkçe hem İngilizce arayüzü test et.

Özellikle:

* Menü
* Butonlar
* Formlar
* Hatalar
* Success mesajları
* Warning mesajları
* PDF araçları
* Confirmation dialogları
* Dashboard

iki dilde de eksiksiz olmalı.

Eksik translation key'lerini tespit etmek için bir kontrol mekanizması mümkünse ekle.

---

# 47. README

Profesyonel bir README oluştur.

Aşağıdaki bölümler bulunmalı:

## Proje Hakkında

## Özellikler

## Teknoloji Stack'i

## Sistem Gereksinimleri

## Kurulum

## Yerel Geliştirme

## cPanel Kurulumu

## MySQL Kurulumu

## Composer

## Environment Variables

## Storage

## Güvenlik

## Backup

## Cron Job

## PDF İşleme

## Opsiyonel Server Tools

## Türkçe / İngilizce Dil Sistemi

## Testler

## Limitations

---

# 48. cPanel Kurulum Dokümantasyonu

README'de SSH erişimi olmayan bir kullanıcıyı da düşün.

Kurulum:

```text
1. cPanel'e giriş yap
2. PHP sürümünü seç
3. MySQL database oluştur
4. Database user oluştur
5. Dosyaları yükle
6. .env oluştur
7. Database bilgilerini gir
8. Composer bağımlılıklarını yükle
9. Storage klasörlerinin izinlerini ayarla
10. Migration çalıştır
11. Cron Job ayarla
12. Siteyi aç
```

şeklinde anlaşılır şekilde anlat.

---

# 49. .htaccess

Apache/cPanel için gerekli `.htaccess` yapılandırmasını oluştur.

Private storage alanlarının doğrudan erişimini engelle.

`.env` ve benzeri hassas dosyaların public erişimini engelle.

HTTPS kullanımı için uygun yapılandırma sun.

Ancak hosting sağlayıcısının farklı Apache yapılandırmalarına sahip olabileceğini dikkate al.

---

# 50. Git

`.gitignore` oluştur.

Kesinlikle commit edilmemesi gerekenler:

```text
.env
storage/documents/*
storage/temporary/*
storage/logs/*
database dumps
private keys
credentials
```

Gerekli klasörleri `.gitkeep` ile repository'de tutabilirsin.

---

# 51. Kod Kalitesi

Kod:

* Okunabilir
* Modüler
* Maintainable
* Güvenli
* Test edilebilir
* PHP 8.3 uyumlu

olmalı.

Gereksiz abstraction oluşturma.

Gereksiz dependency ekleme.

Çalışan kodu gereksiz yere yeniden yazma.

PDF işleme kodlarını view dosyalarına koyma.

---

# 52. Geliştirme Sırası

Projeyi tek seferde karmaşık ve kontrolsüz bir şekilde oluşturma.

Aşağıdaki sırayı takip et:

```text
1. Proje yapısı
2. Composer
3. Configuration
4. Localization sistemi
5. MySQL bağlantısı
6. Migration sistemi
7. Core PHP architecture
8. Storage sistemi
9. Upload validation
10. Document management
11. PDF preview
12. Merge
13. Split
14. Reorder
15. Rotate
16. Compress
17. Watermark
18. Redaction
19. OCR capability detection
20. Office conversion capability detection
21. Versioning
22. Hashing
23. Audit logging
24. Expiration
25. Export
26. Signature workflow
27. Security hardening
28. Responsive UI
29. Türkçe/İngilizce UI kontrolü
30. Tests
31. cPanel deployment
32. README
33. Final verification
```

Her aşamada mevcut sistemi bozmadığından emin ol.

---

# 53. AI Coding Agent Kuralları

Kod yazmaya başlamadan önce repository'yi incele.

Mevcut kodu anlamadan büyük çaplı değişiklik yapma.

Yeni bir dependency eklemeden önce gerçekten gerekli olup olmadığını değerlendir.

Aşağıdaki kurallara uy:

1. Stack'i değiştirme.
2. Laravel veya başka framework ekleme.
3. Node.js ekleme.
4. Python ekleme.
5. Docker ekleme.
6. Redis ekleme.
7. PostgreSQL ekleme.
8. Gereksiz dependency ekleme.
9. Fake implementation oluşturma.
10. Çalışmayan özelliği çalışıyor gibi gösterme.
11. Kullanıcı dosyalarını silme veya overwrite etme.
12. `.env` credential'larını commit etme.
13. Kullanıcıya teknik hata detaylarını gösterme.
14. Türkçe/İngilizce çeviri sistemini bypass etme.
15. UI içerisinde hard-coded kullanıcı metni bırakma.

Bir özellik shared hosting ortamında kullanılamıyorsa **capability detection + graceful fallback** kullan.

---

# 54. Production Kontrol Listesi

Projeyi tamamlamadan önce aşağıdakilerin tamamını kontrol et:

* PHP 8.3 uyumluluğu
* MySQL bağlantısı
* Migration
* Composer dependencies
* Upload
* PDF validation
* Merge
* Split
* Reorder
* Rotate
* Compression
* Watermark
* Redaction
* OCR capability detection
* Office conversion capability detection
* PDF preview
* Versioning
* SHA-256
* Audit log
* Download
* Delete
* Expiry
* Export
* Signature workflow
* CSRF
* XSS
* SQL injection protection
* Path traversal protection
* Secure filenames
* Private storage
* `.env` protection
* Error handling
* Rate limiting
* Responsive UI
* Türkçe dil
* İngilizce dil
* Language switching
* Translation completeness
* Tests
* cPanel deployment
* Cron cleanup
* README

---

# 55. Final Verification

Geliştirme tamamlandığında gerçekten çalıştır:

1. Composer dependencies yükle.
2. PHP uyumluluğunu kontrol et.
3. Database bağlantısını kontrol et.
4. Migration'ları çalıştır.
5. Testleri çalıştır.
6. Gerçek test PDF'i yükle.
7. Merge işlemini test et.
8. Split işlemini test et.
9. Rotate işlemini test et.
10. Yeni version oluştur.
11. Original PDF'in değişmediğini doğrula.
12. SHA-256 hash'i doğrula.
13. Audit log'u kontrol et.
14. Download işlemini test et.
15. Delete işlemini test et.
16. Expiry cleanup'ı test et.
17. Hatalı PDF yükle.
18. Büyük dosya yükleme limitini test et.
19. Private storage erişimini test et.
20. `.env` erişimini test et.
21. CSRF korumasını test et.
22. Türkçe arayüzü test et.
23. İngilizce arayüzü test et.
24. Dil değiştirmeyi test et.
25. Eksik translation key'lerini kontrol et.
26. Optional tools bulunmadığında uygulamanın çalışmaya devam ettiğini test et.
27. Production error handling'i kontrol et.
28. `.gitignore` kontrol et.
29. README'yi kontrol et.

---

# 56. Son Çıktı

Geliştirme sonunda aşağıdaki bilgileri raporla:

## Proje Özeti

Ne geliştirildiğini kısa şekilde açıkla.

## Mimari

Final mimariyi açıkla.

## Tamamlanan Özellikler

Gerçekten tamamlanan özellikleri listele.

## Opsiyonel Özellikler

Sunucu desteğine bağlı olan özellikleri listele.

## Bilinen Sınırlamalar

Çalışmayan veya bilinçli olarak eklenmeyen özellikleri belirt.

## Test Sonuçları

Gerçekten çalıştırılan testleri ve sonuçlarını belirt.

## cPanel Kurulumu

Kurulum adımlarını özetle.

## Türkçe / İngilizce Dil Sistemi

Localization sisteminin nasıl çalıştığını açıkla.

## Çalıştırılan Komutlar

**En önemli kural:**

Gerçekten çalıştırdığın komutların tamamını eksiksiz şekilde listele.

Çalıştırmadığın bir komutu çalıştırılmış gibi gösterme.

Başarısız olan bir testi başarılı olarak raporlama.

Implement edilmemiş bir özelliği tamamlanmış gibi gösterme.

---

# Nihai Hedef

Sonuç;

**PHP + MySQL + Bootstrap + Vanilla JavaScript + Composer + PDF kütüphaneleri**

kullanılarak geliştirilmiş,

**Türkçe ve İngilizce destekleyen,**

**standard cPanel shared hosting üzerinde çalışabilen,**

modern, responsive, güvenli, modüler, test edilmiş ve production kullanımına uygun bir PDF platformu olmalıdır.

Uygulama Docker, Node.js, Python, PostgreSQL, Redis veya root erişimi gerektirmemelidir.

Aynı zamanda mimari gelecekte REST API, mobil uygulama, kullanıcı hesapları ve daha gelişmiş SaaS özellikleri eklenebilecek şekilde tasarlanmalıdır.
