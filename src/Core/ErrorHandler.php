<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AppException;
use App\Exceptions\ErrorCategory;
use App\Http\MethodNotAllowedException;
use App\Http\Request;
use App\Http\Response;

/**
 * Merkezi hata yönetimi (spec §31, §43).
 *
 * Kullanıcı yalnızca kategoriye göre çevrilmiş, teknik olmayan bir mesaj ve
 * destek için bir hata kodu (request id) görür. SQL, stack trace, dosya yolu,
 * kimlik bilgisi gibi ayrıntılar yalnızca log dosyasına yazılır.
 * Ayrıntılı çıktı sadece APP_DEBUG=true VE APP_ENV=local iken gösterilir.
 */
final class ErrorHandler
{
    /** @var (\Closure(int, string, string): string)|null HTML hata sayfası üretici */
    private ?\Closure $pageRenderer = null;

    public function __construct(
        private readonly Logger $logger,
        private readonly bool $showDetails,
    ) {
    }

    /**
     * @param \Closure(int, string, string): string $renderer (status, message, requestId) => html
     */
    public function setPageRenderer(\Closure $renderer): void
    {
        $this->pageRenderer = $renderer;
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false; // @ ile bastırılmış
            }
            if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                $this->logger->debug('Deprecated: ' . $message, ['file' => basename($file) . ':' . $line]);

                return true;
            }

            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        set_exception_handler(function (\Throwable $e): void {
            $this->toResponse($e, null)->send();
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            $this->logger->critical('Fatal error', [
                'error' => $error['message'],
                'file' => basename($error['file']) . ':' . $error['line'],
            ]);
            if (!headers_sent()) {
                $this->toResponse(new \ErrorException($error['message'], 0, $error['type']), null)->send();
            }
        });
    }

    public function toResponse(\Throwable $e, ?Request $request): Response
    {
        [$category, $status, $messageKey, $replace, $fieldErrors] = $this->classify($e);
        $this->report($e, $category);

        $message = $this->translate($messageKey, $replace);
        $requestId = $this->logger->requestId();
        $wantsJson = $request?->wantsJson() ?? (PHP_SAPI === 'cli');

        if ($wantsJson) {
            $payload = [
                'ok' => false,
                'error' => [
                    'category' => $category->value,
                    'message' => $message,
                    'recoverable' => $category->isRecoverable(),
                    'request_id' => $requestId,
                ],
            ];
            if ($fieldErrors !== []) {
                $payload['error']['fields'] = array_map(fn (string $key): string => $this->translate($key, $replace), $fieldErrors);
            }
            if ($this->showDetails) {
                $payload['error']['debug'] = Logger::describeThrowable($e);
            }
            $response = Response::json($payload, $status);
        } else {
            $response = Response::html($this->renderPage($status, $message, $requestId, $e), $status);
        }

        if ($e instanceof MethodNotAllowedException) {
            $response->setHeader('Allow', implode(', ', $e->allowed));
        }
        if ($category === ErrorCategory::RateLimited) {
            $response->setHeader('Retry-After', '60');
        }

        return $response;
    }

    /**
     * @return array{ErrorCategory, int, string, array<string, string|int|float>, array<string, string>}
     */
    private function classify(\Throwable $e): array
    {
        if ($e instanceof AppException) {
            return [$e->category(), $e->httpStatus(), $e->messageKey(), $e->replace(), $e->fieldErrors()];
        }

        return [ErrorCategory::Unexpected, 500, ErrorCategory::Unexpected->defaultMessageKey(), [], []];
    }

    private function report(\Throwable $e, ErrorCategory $category): void
    {
        $level = match ($category) {
            ErrorCategory::Validation, ErrorCategory::NotFound, ErrorCategory::RateLimited, ErrorCategory::Permission => 'info',
            ErrorCategory::Processing, ErrorCategory::ToolUnavailable => 'warning',
            default => 'error',
        };

        $this->logger->log($level, $category->value . ' error', ['exception' => $e, 'error_type' => $category->value]);
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    private function translate(string $key, array $replace): string
    {
        try {
            return __($key, $replace);
        } catch (\Throwable) {
            return $key;
        }
    }

    private function renderPage(int $status, string $message, string $requestId, \Throwable $e): string
    {
        if ($this->pageRenderer !== null) {
            try {
                $html = ($this->pageRenderer)($status, $message, $requestId);
                if ($this->showDetails) {
                    $html .= '<pre class="container small">' . e(print_r(Logger::describeThrowable($e), true)) . '</pre>';
                }

                return $html;
            } catch (\Throwable $renderError) {
                $this->logger->error('Error page rendering failed', ['exception' => $renderError]);
            }
        }

        // Son çare: şablon sistemi de çalışmıyorsa sade sayfa
        return '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>'
            . '<h1>' . $status . '</h1><p>' . e($message) . '</p><p><code>' . e($requestId) . '</code></p>';
    }
}
