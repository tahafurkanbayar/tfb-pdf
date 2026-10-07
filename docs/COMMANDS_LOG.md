# Çalıştırılan Komutlar

Geliştirme sırasında gerçekten çalıştırılan komutlar ve gerçek sonuçları. Çalıştırılmayan komut yazılmaz; başarısız sonuç başarılı gösterilmez.

`php` = `C:\xampp\php\php.exe` (PHP 8.2.12)

## Aşama 0 — Ortam keşfi ve repo

| Komut | Sonuç |
|---|---|
| `ls -la <memory dizini>; ls -la c:/xampp/htdocs/tfb-pdf` | Proje dizini boş |
| `git --version` | git 2.56.0.windows.2 |
| `gh --version` | gh 2.102.0 |
| `gh auth status` | `tahafurkanbayar` olarak oturum açık, scope: repo, workflow, gist, read:org |
| `composer --version` | **Başarısız**: composer PATH'te yok |
| `php -v` | PHP 8.2.12 (cli, ZTS, x64) |
| `C:\xampp\mysql\bin\mysql.exe --version` | MariaDB 10.4.32 |
| `git config --global user.name / user.email / init.defaultBranch; gh repo view tahafurkanbayar/tfb-pdf` | Global git kimliği tanımlı değil; repo GitHub'da yok (exit 1) |
| `gh api user --jq ...` | login: tahafurkanbayar, ad: Taha Furkan Bayar |
| `git init -b main` | Boş repo oluşturuldu |
| `git config user.name "Taha Furkan Bayar"` / `git config user.email "<noreply>"` / `git config core.autocrlf false` | Repo-yerel kimlik ayarlandı |
| `git status` | main, commit yok |
