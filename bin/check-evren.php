<?php

declare(strict_types=1);

/** Send only a synthetic test image; print schema paths, never credentials or OCR text. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';
App\Core\Env::load(APP_ROOT . '/.env');
$config = require APP_ROOT . '/config/evren.php';
if ($config['api_key'] === '' || preg_match('/[\x00-\x20\x7f]/', $config['api_key'])
    || !in_array($config['model'], ['dots-ocr', 'deepseek-ocr-2'], true)
    || !function_exists('curl_init') || !function_exists('imagejpeg')) {
    fwrite(STDERR, "Requires EVREN_API_KEY, a supported OCR model, cURL and GD.\n");
    exit(1);
}
if (($argv[1] ?? '') === '--terms') {
    try {
        $response = (new App\Services\Evren\EvrenHttpClient())->readTerms($config['api_key'], $config['timeout']);
        fwrite(STDOUT, 'HTTP ' . $response['status'] . "\n");
        if ($response['status'] !== 200) exit(1);
        $data = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
        // This read-only endpoint returns the public terms text, not account credentials.
        $safe = str_replace($config['api_key'], '[redacted]', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents(APP_ROOT . '/build/evren-terms.json', $safe);
        fwrite(STDOUT, $safe . "\n");
        exit(0);
    } catch (Throwable) {
        fwrite(STDERR, "Could not read Evren terms.\n");
        exit(1);
    }
}
// Deliberate CLI action, never triggered by an OCR request or installation.
if (($argv[1] ?? '') === '--accept-terms-v1') {
    try {
        $response = (new App\Services\Evren\EvrenHttpClient())->acceptTermsV1($config['api_key'], $config['timeout']);
        fwrite(STDOUT, 'Terms acceptance HTTP ' . $response['status'] . "\n");
        exit($response['status'] >= 200 && $response['status'] < 300 ? 0 : 1);
    } catch (Throwable) {
        fwrite(STDERR, "Could not submit Evren terms acceptance.\n");
        exit(1);
    }
}
$image = imagecreatetruecolor(800, 300);
imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
imagestring($image, 5, 150, 130, 'TFB PDF OCR TEST 12345', imagecolorallocate($image, 0, 0, 0));
ob_start();
imagejpeg($image, null, 90);
$bytes = (string) ob_get_clean();
imagedestroy($image);
try {
    $response = (new App\Services\Evren\EvrenHttpClient())->post($config['api_key'], [
        'model' => $config['model'], 'image' => 'data:image/jpeg;base64,' . base64_encode($bytes),
    ], $config['timeout']);
    fwrite(STDOUT, 'HTTP ' . $response['status'] . "\n");
    if ($response['status'] < 200 || $response['status'] >= 300) {
        $error = json_decode($response['body'], true);
        $code = is_array($error) ? ($error['error']['code'] ?? $error['code'] ?? null) : null;
        if (is_string($code) && preg_match('/^[a-zA-Z0-9_\-]{1,80}$/D', $code)) {
            fwrite(STDOUT, 'ERROR_CODE=' . $code . "\n");
        }
        $message = is_array($error) ? ($error['error']['message'] ?? null) : null;
        if (is_string($message)) {
            $safeMessage = str_replace($config['api_key'], '[redacted]', $message);
            $safeMessage = (string) preg_replace('/[\x00-\x1f\x7f]/', ' ', $safeMessage);
            fwrite(STDOUT, 'PROVIDER_MESSAGE=' . mb_substr($safeMessage, 0, 500) . "\n");
        }
        $type = explode(';', $response['content_type'] ?? '')[0];
        if (preg_match('#^[a-zA-Z0-9.+\-/]+$#D', $type)) fwrite(STDOUT, 'CONTENT_TYPE=' . $type . "\n");
        exit(1);
    }
    $data = json_decode($response['body'], true, 64, JSON_THROW_ON_ERROR);
    $paths = [];
    $visit = static function (mixed $node, string $path = '', int $depth = 0) use (&$visit, &$paths): void {
        if ($depth > 20 || count($paths) > 100) return;
        if (is_array($node)) {
            foreach ($node as $key => $value) {
                // Only structural identifiers can be printed, not arbitrary provider keys.
                if (!preg_match('/^[a-zA-Z0-9_]+$/D', (string) $key)) continue;
                $visit($value, $path === '' ? (string) $key : $path . '.' . $key, $depth + 1);
            }
        } elseif (is_string($node) && preg_match('/(?:^|\.)(text|markdown|content|ocr_text)$/', $path)) {
            $paths[] = [$path, mb_strlen($node), str_contains($node, 'TFB')];
        }
    };
    $visit($data);
    foreach ($paths as [$path, $length, $matched]) {
        fwrite(STDOUT, 'TEXT_PATH=' . $path . '; characters=' . $length . '; test_marker=' . ($matched ? 'yes' : 'no') . "\n");
    }
    if ($paths === []) {
        fwrite(STDERR, "No recognized text field. Review the successful response schema in the provider documentation.\n");
        exit(2);
    }
} catch (Throwable $e) {
    // Do not print exception traces, response bodies or request data.
    fwrite(STDERR, "Evren connection or JSON decoding failed.\n");
    exit(1);
}
