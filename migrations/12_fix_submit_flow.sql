-- Migration 12: Perbaikan alur submit ujian
-- ============================================================
-- 1. Tambah kolom status/dismissed_by/dismissed_at ke exam_violations
--    (sebelumnya gagal: ADD COLUMN IF NOT EXISTS tidak didukung MySQL 8)
-- 2. Normalisasi tabel jawaban_sEMENTARA -> jawaban_sementara
--    (MySQL Linux case-sensitive, dua tabel ini berbeda; data digabung)

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

-- 1a. Kolom status untuk exam_violations
CALL add_col_if_not_exists('exam_violations', 'status', "ENUM('active','dismissed') DEFAULT 'active' AFTER `detail`");
CALL add_col_if_not_exists('exam_violations', 'dismissed_by', 'INT NULL AFTER `status`');
CALL add_col_if_not_exists('exam_violations', 'dismissed_at', 'TIMESTAMP NULL AFTER `dismissed_by`');

-- 2a. Gabungkan data jawaban_sEMENTARA ke jawaban_sementara (skip duplikat id_ujian+nis)
INSERT IGNORE INTO `jawaban_sementara` (`id_ujian`, `nis`, `nama`, `kelas`, `answers`, `created_at`, `updated_at`)
SELECT `id_ujian`, `nis`, `nama`, `kelas`, `answers`, `created_at`, `updated_at`
FROM `jawaban_sEMENTARA`;

-- 2b. Hapus tabel lama yang case-sensitive
DROP TABLE IF EXISTS `jawaban_sEMENTARA`;

DROP PROCEDURE IF EXISTS add_col_if_not_exists;
