-- Degistirilemez surumler. version_number 0 = orijinal dosya (original.pdf, original.docx ...),
-- 1, 2, 3 ... = islem sonuclari (v001.pdf, v002.pdf ...). Bir dosya bir kez yazilir, asla uzerine yazilmaz.
-- label: dilden bagimsiz kisa aciklama (ornegin bolmede sayfa araligi "1-3").
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
