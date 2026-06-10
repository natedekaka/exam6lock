-- Migration 10: Add password_change_required column to siswa
-- ============================================================

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

CALL add_col_if_not_exists('siswa', 'password_change_required', "TINYINT(1) DEFAULT 0 AFTER `password`");

DROP PROCEDURE IF EXISTS add_col_if_not_exists;
