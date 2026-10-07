-- Uygulama geneli anahtar/deger ayarlari (audit zinciri basi, arac tespit onbellegi vb.)
CREATE TABLE settings (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
