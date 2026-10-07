-- Yuklenen veya uretilen belgeler. original_name yalnizca metadata; dosya yolu olarak kullanilmaz.
-- owner_hash: tarayici bazli sahip kimliginin HMAC degeri. user_id: gelecekteki kullanici sistemi icin.
CREATE TABLE documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(32) NOT NULL,
    owner_hash CHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    source_type VARCHAR(20) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_public_id (public_id),
    KEY idx_documents_owner (owner_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
