-- Sabit pencereli istek sinirlama (Redis yerine). bucket = HMAC(eylem + IP); IP duz metin saklanmaz.
CREATE TABLE rate_limits (
    bucket CHAR(64) NOT NULL,
    window_start DATETIME NOT NULL,
    hits INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket, window_start),
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
