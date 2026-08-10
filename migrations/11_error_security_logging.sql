-- Migration 11: Error & Security Logging Infrastructure
-- =====================================================
-- Adds structured logging support: log_entries table for application errors,
-- security events, and operational info. Also ensures migrations_history table
-- exists for tracking applied migrations.

DROP PROCEDURE IF EXISTS add_col_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_col_if_not_exists(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT
)
BEGIN
    DECLARE col_count INT DEFAULT 0;
    SELECT COUNT(*) INTO col_count FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = p_table AND column_name = p_column;
    IF col_count = 0 THEN
        SET @stmt = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_column, ' ', p_definition);
        PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- 1. MIGRATIONS_HISTORY - Track which migrations have been applied
CREATE TABLE IF NOT EXISTS `migrations_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_name` VARCHAR(255) NOT NULL,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `applied_by` VARCHAR(100) NULL,
    UNIQUE KEY `unique_migration` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. LOG_ENTRIES - Structured application, error, and security event logging
CREATE TABLE IF NOT EXISTS `log_entries` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `level` ENUM('error', 'info', 'warning', 'security') NOT NULL,
    `message` TEXT NOT NULL,
    `context` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `request_uri` VARCHAR(500) NULL,
    `user_id` INT NULL,
    `user_type` ENUM('admin', 'siswa') NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_level` (`level`),
    INDEX `idx_log_created_at` (`created_at`),
    INDEX `idx_log_ip` (`ip_address`),
    INDEX `idx_log_user` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert this migration record
INSERT IGNORE INTO `migrations_history` (`migration_name`) VALUES ('11_error_security_logging');

DROP PROCEDURE IF EXISTS add_col_if_not_exists;
