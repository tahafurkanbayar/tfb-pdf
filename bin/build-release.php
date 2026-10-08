<?php

declare(strict_types=1);

/*
 * Yayın paketi üretir: build/tfb-pdf-<sürüm>.zip (+ .sha256).
 * Paket HEAD commit'inin içeriği + `composer install --no-dev` ile kurulmuş vendor/ içerir;
 * SSH/Composer olmayan cPanel kullanıcıları doğrudan yükleyip açabilir.
 *
 * Kullanım: php bin/build-release.php
 * Sürüm config/app.php içindeki 'version' değeridir; HEAD'de v<sürüm> etiketi yoksa durur.
 * Çıkış kodu: 0 = paket üretildi, 1 = hata
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';

/** Pakete girmeyen, yalnızca geliştirmeye ait yollar (depo kökünden) */
const EXCLUDE = [
    'tests',
    'phpunit.xml.dist',
    'CLAUDE.md',
    'docs/PROMPT.md',
    'docs/COMMANDS_LOG.md',
    'docs/PROGRESS.md',
];

function fail(string $message): never
{
    fwrite(STDERR, 'HATA: ' . $message . PHP_EOL);
    exit(1);
}

function run(string $command, ?string $cwd = null): string
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd ?? APP_ROOT);
    if (!is_resource($process)) {
        fail('Komut başlatılamadı: ' . $command);
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        fail("Komut başarısız: {$command}\n{$err}{$out}");
    }
    return trim((string) $out);
}

function removeTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? removeDir($item->getPathname()) : unlink($item->getPathname());
    }
    removeDir($path);
}

/** Windows'ta silinen dosyalar (virüs tarayıcı / indeksleyici açık tutarken) kısa süre dizinde kalabilir: birkaç kez dene */
function removeDir(string $path): void
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        if (@rmdir($path) || !is_dir($path)) {
            return;
        }
        usleep(100_000);
    }
    fail('Dizin silinemedi: ' . $path);
}

$config = require APP_ROOT . '/config/app.php';
$version = (string) $config['version'];
if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
    fail("config/app.php sürümü SemVer değil: {$version}");
}
$tag = 'v' . $version;

if (run('git status --porcelain') !== '') {
    fail('Çalışma dizininde commit edilmemiş değişiklik var.');
}
$tags = preg_split('/\R/', run('git tag --points-at HEAD')) ?: [];
if (!in_array($tag, $tags, true)) {
    fail("HEAD'de {$tag} etiketi yok. Önce: git tag -a {$tag} -m \"TFB PDF {$version}\"");
}

$composer = is_file(APP_ROOT . '/composer.phar')
    ? escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(APP_ROOT . '/composer.phar')
    : 'composer';

$buildDir = APP_ROOT . '/build';
$name = 'tfb-pdf-' . $version;
$stage = $buildDir . '/' . $name;
$tar = $buildDir . '/' . $name . '-src.tar';
$zip = $buildDir . '/' . $name . '.zip';

removeTree($stage);
@unlink($tar);
@unlink($zip);
@unlink($zip . '.sha256');
if (!is_dir($buildDir) && !mkdir($buildDir, 0775, true)) {
    fail('build/ dizini oluşturulamadı.');
}

echo "1/4 Kaynak ({$tag}) çıkarılıyor..." . PHP_EOL;
run('git archive --format=tar -o ' . escapeshellarg($tar) . ' ' . escapeshellarg($tag));
(new PharData($tar))->extractTo($stage);
unlink($tar);
foreach (EXCLUDE as $path) {
    $full = $stage . '/' . $path;
    is_dir($full) ? removeTree($full) : @unlink($full);
}

echo '2/4 composer install --no-dev...' . PHP_EOL;
run($composer . ' install --no-dev --optimize-autoloader --no-interaction --no-progress', $stage);
if (is_dir($stage . '/vendor/phpunit')) {
    fail('vendor/ içinde geliştirme bağımlılığı kaldı (phpunit).');
}

echo '3/4 ZIP oluşturuluyor...' . PHP_EOL;
// PharData zip yazımı zip eklentisi gerektirmez. Kök klasör: tfb-pdf-<sürüm>/
$archive = new PharData($zip, 0, null, Phar::ZIP);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($files as $file) {
    if ($file->isFile()) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1));
        $archive->addFile($file->getPathname(), $name . '/' . $relative);
        $count++;
    }
}
unset($archive);
removeTree($stage);

echo '4/4 SHA-256...' . PHP_EOL;
$hash = hash_file('sha256', $zip);
file_put_contents($zip . '.sha256', $hash . '  ' . basename($zip) . "\n");

printf("OK: %s (%d dosya, %.1f MB)\nSHA-256: %s\n", 'build/' . basename($zip), $count, filesize($zip) / 1048576, $hash);
