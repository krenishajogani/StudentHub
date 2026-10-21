USE studenthub;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    admin_name VARCHAR(150) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS registrations (
    registration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_registrations_registered_at (registered_at),
    INDEX idx_registrations_student_id (student_id),
    INDEX idx_registrations_event_id (event_id)
);

SET @has_registration_timestamp = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'registrations'
      AND COLUMN_NAME IN ('registered_at', 'registration_date', 'created_at')
);
SET @registration_timestamp_migration = IF(
    @has_registration_timestamp = 0,
    'ALTER TABLE registrations ADD COLUMN registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE registration_timestamp_statement FROM @registration_timestamp_migration;
EXECUTE registration_timestamp_statement;
DEALLOCATE PREPARE registration_timestamp_statement;
