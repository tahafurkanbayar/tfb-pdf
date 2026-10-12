<?php

declare(strict_types=1);

namespace App\Services\Evren;

use App\Exceptions\ProcessingException;
use App\Exceptions\ToolUnavailableException;

final class EvrenOcrClient
{
    public function __construct(
        private readonly EvrenHttpClient $http,
        private readonly bool $enabled,
        #[\SensitiveParameter] private readonly string $key,
        private readonly string $model,
        private readonly string $textPath,
        private readonly int $timeout = 60,
    ) {
    }

    public function configured(): bool
    {
        return $this->enabled && $this->key !== '' && !preg_match('/[\x00-\x20\x7f]/', $this->key)
            && in_array($this->model, ['dots-ocr', 'deepseek-ocr-2'], true)
            && (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)*$/D', $this->textPath);
    }

    public function available(): bool
    {
        return $this->configured() && function_exists('curl_init');
    }

    /** $image is a server-validated and re-encoded JPEG, not an arbitrary URL. */
    public function recognize(string $image): string
    {
        if (!$this->available()) {
            throw new ToolUnavailableException('Evren OCR is not configured', 'errors.tool_unavailable');
        }
        $result = $this->http->post($this->key, [
            'model' => $this->model,
            'image' => 'data:image/jpeg;base64,' . base64_encode($image),
        ], $this->timeout);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            $message = $result['status'] === 429 ? 'ocr.evren_rate_limit' : 'ocr.evren_failed';
            $error = json_decode($result['body'], true);
            if ($result['status'] === 403 && is_array($error) && ($error['error']['code'] ?? $error['code'] ?? null) === 'terms_not_accepted') {
                $message = 'ocr.evren_terms';
            }
            throw new ProcessingException('Evren HTTP status ' . $result['status'], $message);
        }
        try {
            $value = json_decode($result['body'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ProcessingException('Invalid Evren JSON response', 'ocr.evren_response');
        }
        foreach (explode('.', $this->textPath) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new ProcessingException('Evren OCR text field missing', 'ocr.evren_response');
            }
            $value = $value[$segment];
        }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 100000) {
            throw new ProcessingException('Invalid Evren OCR text', 'ocr.evren_response');
        }
        return str_replace(["\r\n", "\r"], "\n", (string) preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $value));
    }
}
