-- tfb-pdf veritabani semasi (otomatik uretilir: php bin/migrate.php schema)
-- Bu dosyayi elle duzenlemeyin; database/migrations altindaki dosyalari degistirin.
-- phpMyAdmin > Import ile bos bir veritabanina ice aktarilabilir.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    checksum CHAR(64) NOT NULL,
    batch INT UNSIGNED NOT NULL,
    applied_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 0001_create_settings_table
-- ----------------------------------------------------------------------
CREATE TABLE settings (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0001_create_settings_table', 'f7bf7d4a7373e14663525181b6a6c6d49061522b445898f1fb4fb3e4bbf06fd3', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0002_create_documents_table
-- ----------------------------------------------------------------------
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

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0002_create_documents_table', 'bd8fe1aca8d67e245c6bc5942e031a5578d58c09f5f2a0d4a312fcbac8e9bdc1', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0003_create_operations_table
-- ----------------------------------------------------------------------
CREATE TABLE operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(32) NOT NULL,
    document_id BIGINT UNSIGNED NULL,
    owner_hash CHAR(64) NOT NULL,
    type VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL,
    engine VARCHAR(30) NULL,
    params JSON NULL,
    input_versions JSON NULL,
    result JSON NULL,
    error_code VARCHAR(100) NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    duration_ms INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operations_public_id (public_id),
    KEY idx_operations_owner (owner_hash, started_at),
    KEY idx_operations_document (document_id),
    CONSTRAINT fk_operations_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0003_create_operations_table', '239823eb157448afcdac2f225de77f464b8dbdf4b3633ab2a69303bae6dbc3e1', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0004_create_document_versions_table
-- ----------------------------------------------------------------------
CREATE TABLE document_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    operation_id BIGINT UNSIGNED NULL,
    filename VARCHAR(50) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    page_count INT UNSIGNED NULL,
    label VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_versions_document_number (document_id, version_number),
    UNIQUE KEY uq_versions_storage_path (storage_path),
    KEY idx_versions_operation (operation_id),
    KEY idx_versions_sha256 (sha256),
    CONSTRAINT fk_versions_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_versions_operation FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0004_create_document_versions_table', '7e97744587e6214d832866875aab3d640161a38523a0c8b3af8384c5c320c3c2', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0005_create_audit_events_table
-- ----------------------------------------------------------------------
CREATE TABLE audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id CHAR(32) NOT NULL,
    owner_hash CHAR(64) NULL,
    document_public_id CHAR(32) NULL,
    operation_public_id CHAR(32) NULL,
    event_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    actor VARCHAR(20) NOT NULL,
    input_hash CHAR(64) NULL,
    output_hash CHAR(64) NULL,
    metadata JSON NULL,
    error_message VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    prev_hash CHAR(64) NOT NULL,
    event_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_audit_event_id (event_id),
    KEY idx_audit_document (document_public_id, id),
    KEY idx_audit_owner (owner_hash, id),
    KEY idx_audit_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`, updated_at)
VALUES ('audit_chain_head', '0000000000000000000000000000000000000000000000000000000000000000', UTC_TIMESTAMP());

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0005_create_audit_events_table', '6f017468b816a35936a44b72bfa099cb764b2d955bd9b5b7a2c9b4ba9b1d4f72', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0006_create_file_expiry_table
-- ----------------------------------------------------------------------
CREATE TABLE file_expiry (
    document_id BIGINT UNSIGNED NOT NULL,
    policy VARCHAR(10) NOT NULL,
    expires_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (document_id),
    KEY idx_expiry_expires_at (expires_at),
    CONSTRAINT fk_expiry_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0006_create_file_expiry_table', 'aa11335c2e2108cceec08a8738cf5401723e7fbf285d63b0e88751da125b1643', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0007_create_signature_tables
-- ----------------------------------------------------------------------
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

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0007_create_signature_tables', '2ee6b8e4612608d12c16f0f85b783d36f308c24fbe330d4d72f14b7b60c64e5d', 1, UTC_TIMESTAMP());

-- ----------------------------------------------------------------------
-- 0008_create_rate_limits_table
-- ----------------------------------------------------------------------
CREATE TABLE rate_limits (
    bucket CHAR(64) NOT NULL,
    window_start DATETIME NOT NULL,
    hits INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket, window_start),
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, checksum, batch, applied_at) VALUES ('0008_create_rate_limits_table', 'b5bdef0814789acc75fa3975b231787d6324c785d650d0ddc8a3cba6c1d4b608', 1, UTC_TIMESTAMP());
