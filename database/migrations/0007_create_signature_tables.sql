-- Basit imza akisi. Nitelikli elektronik imza (QES / eIDAS) DEGILDIR.
CREATE TABLE signature_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(32) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    version_id BIGINT UNSIGNED NOT NULL,
    owner_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL,
    message TEXT NULL,
    source_sha256 CHAR(64) NOT NULL,
    final_version_id BIGINT UNSIGNED NULL,
    final_sha256 CHAR(64) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_signature_requests_public_id (public_id),
    KEY idx_signature_requests_owner (owner_hash, created_at),
    KEY idx_signature_requests_document (document_id),
    CONSTRAINT fk_sigreq_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_sigreq_version FOREIGN KEY (version_id) REFERENCES document_versions (id) ON DELETE CASCADE,
    CONSTRAINT fk_sigreq_final_version FOREIGN KEY (final_version_id) REFERENCES document_versions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- token_hash: davet baglantisindaki tokenin HMAC degeri. Tokenin kendisi saklanmaz.
CREATE TABLE signature_signers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NULL,
    token_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL,
    signature_type VARCHAR(10) NULL,
    signature_path VARCHAR(255) NULL,
    typed_name VARCHAR(150) NULL,
    consented_at DATETIME NULL,
    signed_at DATETIME NULL,
    declined_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_signers_token_hash (token_hash),
    KEY idx_signers_request (request_id),
    CONSTRAINT fk_signers_request FOREIGN KEY (request_id) REFERENCES signature_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Imza alani konumu sayfaya gore oransal (0..1), sol ust kose referansli.
CREATE TABLE signature_fields (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    signer_id BIGINT UNSIGNED NOT NULL,
    page_number INT UNSIGNED NOT NULL,
    pos_x DECIMAL(7,6) NOT NULL,
    pos_y DECIMAL(7,6) NOT NULL,
    width DECIMAL(7,6) NOT NULL,
    height DECIMAL(7,6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fields_request (request_id),
    CONSTRAINT fk_fields_request FOREIGN KEY (request_id) REFERENCES signature_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_fields_signer FOREIGN KEY (signer_id) REFERENCES signature_signers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Talebe ozel olay gecmisi (son PDF'e eklenen imza sayfasinda listelenir). Kalici kayit ayrica audit_events'tedir.
CREATE TABLE signature_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    signer_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sigevents_request (request_id, id),
    CONSTRAINT fk_sigevents_request FOREIGN KEY (request_id) REFERENCES signature_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_sigevents_signer FOREIGN KEY (signer_id) REFERENCES signature_signers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
