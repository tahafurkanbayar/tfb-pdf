-- Her PDF isleminin kaydi. document_id: sonuc surumlerinin ait oldugu belge
-- (birlestirmede islem sonunda olusturulan yeni belge; islem basarisiz olursa NULL kalabilir).
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
