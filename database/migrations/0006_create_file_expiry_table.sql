-- Belge saklama suresi. policy: 1d | 7d | 30d | never. never icin expires_at NULL.
CREATE TABLE file_expiry (
    document_id BIGINT UNSIGNED NOT NULL,
    policy VARCHAR(10) NOT NULL,
    expires_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (document_id),
    KEY idx_expiry_expires_at (expires_at),
    CONSTRAINT fk_expiry_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
