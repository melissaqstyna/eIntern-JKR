-- Import once into the existing e-Intern database using phpMyAdmin.
-- Existing students and users are not changed.
CREATE TABLE IF NOT EXISTS certificate_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    settings_json MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO certificate_settings (id, settings_json) VALUES (1, '{}');
CREATE TABLE IF NOT EXISTS internship_certificates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payload_json LONGTEXT NOT NULL,
    issued_by INT NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_certificate_per_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
