<?php

declare(strict_types=1);

namespace App\Services\Evren;

use App\Exceptions\ProcessingException;

/** HTTPS only, fixed provider destination, no redirects or provider bodies in errors. */
class EvrenHttpClient
{
    public const OCR_URL = 'https://evren-llmapi.ssyz.org.tr/v1/ocr';
    public const MAX_RESPONSE_BYTES = 2 * 1024 * 1024;

    /** @return array{status: int, body: string, content_type?: string} */
    public function post(#[\SensitiveParameter] string $key, array $payload, int $timeout): array
    {
        return $this->request(self::OCR_URL, $key, $payload, $timeout);
    }

    /** Read-only provider terms; acceptance is never performed implicitly. */
    public function readTerms(#[\SensitiveParameter] string $key, int $timeout): array
    {
        return $this->request('https://evren-llmapi.ssyz.org.tr/v1/terms/text', $key, null, $timeout);
    }

    /** Administrative action; call only after the account owner explicitly approves v1. */
    public function acceptTermsV1(#[\SensitiveParameter] string $key, int $timeout): array
    {
        return $this->request('https://evren-llmapi.ssyz.org.tr/v1/terms/accept', $key, ['version' => 1], $timeout);
    }

    private function request(string $url, #[\SensitiveParameter] string $key, ?array $payload, int $timeout): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new ProcessingException('Cannot initialize Evren request', 'ocr.evren_failed');
        }
        $body = '';
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => $payload !== null,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if ($payload !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            }
            if (curl_exec($handle) === false) {
                // Never log curl_error(), headers, request payload or provider response.
                throw new ProcessingException('Evren transport failed', 'ocr.evren_failed');
            }
            return [
                'status' => (int) curl_getinfo($handle, CURLINFO_HTTP_CODE), 'body' => $body,
                'content_type' => (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
            ];
        } finally {
            curl_close($handle);
        }
    }
}
