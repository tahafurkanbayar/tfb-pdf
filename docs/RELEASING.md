# Sürüm Yayınlama

## Sürüm numarası (SemVer: MAJOR.MINOR.PATCH)

| Değişiklik | Artan | Örnek |
|---|---|---|
| Hata düzeltmesi, metin/görünüm düzeltmesi, geriye uyumlu | PATCH | 1.0.0 → 1.0.1 |
| Yeni özellik / araç, geriye uyumlu (yeni migration olabilir) | MINOR | 1.0.1 → 1.1.0 |
| Uyumu bozan değişiklik: `.env` anahtarı kaldırma/yeniden adlandırma, minimum PHP/MySQL yükseltme, elle müdahale gerektiren güncelleme | MAJOR | 1.1.0 → 2.0.0 |

Sürümün tek kaynağı `config/app.php` içindeki `'version'` değeridir (footer'da gösterilir). Etiket `v<sürüm>` biçimindedir.

## Geliştirme sırasında

- Her birleşen değişiklik `CHANGELOG.md` içinde `## [Yayınlanmadı]` başlığı altına eklenir (`Eklendi`, `Değişti`, `Düzeltildi`, `Kaldırıldı`, `Güvenlik`).
- Yeni migration varsa CHANGELOG'da belirtilir (güncellemede `/install` üzerinden çalıştırılması gerekir).

## Yayın adımları

1. Testler ve çeviriler temiz olmalı:
   ```bash
   php vendor/bin/phpunit
   php bin/check-translations.php
   ```
2. `config/app.php` içindeki `'version'` değerini artırın.
3. `CHANGELOG.md`: `[Yayınlanmadı]` içeriğini `## [X.Y.Z] - YYYY-MM-DD` başlığına taşıyın, alttaki karşılaştırma bağlantılarını güncelleyin.
4. Commit ve etiket:
   ```bash
   git commit -am "Sürüm X.Y.Z"
   git tag -a vX.Y.Z -m "TFB PDF X.Y.Z"
   git push origin main vX.Y.Z
   ```
5. Paketi üretin (HEAD'de etiket yoksa veya çalışma dizini temiz değilse betik durur):
   ```bash
   php bin/build-release.php
   ```
   Çıktı: `build/tfb-pdf-X.Y.Z.zip` (kaynak + `vendor/` (`--no-dev`), testler ve geliştirme notları hariç) ve `.sha256`.
6. GitHub release (notlar CHANGELOG'daki ilgili bölümden):
   ```bash
   gh release create vX.Y.Z build/tfb-pdf-X.Y.Z.zip build/tfb-pdf-X.Y.Z.zip.sha256 --title "TFB PDF X.Y.Z" --notes-file <notlar.md>
   ```

## Kullanıcı tarafında güncelleme

README → Kurulum → **Güncelleme** bölümü: yeni paketi yükleyin, `.env` ve `storage/` dizinine dokunmayın, `/install` üzerinden bekleyen migration'ları çalıştırın. Öncesinde yedek alın.
