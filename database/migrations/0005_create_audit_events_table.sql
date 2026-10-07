-- Append-only audit log. Uygulama bu tabloda UPDATE veya DELETE yapmaz.
-- Belge silinse de olaylar kalir; bu yuzden belgeye foreign key yoktur, public id saklanir.
-- prev_hash / event_hash: hash zinciri. Kayitlarin sonradan degistirilmesi tespit edilebilir (tamper-evident).
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

-- Zincirin baslangic degeri (genesis)
INSERT INTO settings (`key`, `value`, updated_at)
VALUES ('audit_chain_head', '0000000000000000000000000000000000000000000000000000000000000000', UTC_TIMESTAMP());
