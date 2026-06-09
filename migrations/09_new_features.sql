-- Migration 09: New Features - Student Auth, Class Management, Scheduling, Notifications, Remedial, Audit Trail
-- =====================================================================================

DROP PROCEDURE IF EXISTS add_column_if_not_exists//
CREATE PROCEDURE add_column_if_not_exists(
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

DROP PROCEDURE IF EXISTS add_index_if_not_exists//
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_columns TEXT
)
BEGIN
    DECLARE idx_count INT DEFAULT 0;
    SELECT COUNT(*) INTO idx_count FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = p_table AND index_name = p_index;
    IF idx_count = 0 THEN
        SET @stmt = CONCAT('CREATE INDEX ', p_index, ' ON `', p_table, '`(', p_columns, ')');
        PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END//

-- 1. JURUSAN
CREATE TABLE IF NOT EXISTS `jurusan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_jurusan` VARCHAR(100) NOT NULL,
    `kode` VARCHAR(20) UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. KELAS
CREATE TABLE IF NOT EXISTS `kelas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kelas` VARCHAR(100) NOT NULL,
    `jurusan_id` INT NULL,
    `tingkat` VARCHAR(20),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CALL add_index_if_not_exists('kelas', 'idx_kelas_jurusan', '`jurusan_id`');

-- 3. SISWA
CREATE TABLE IF NOT EXISTS `siswa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nis` VARCHAR(50) UNIQUE NOT NULL,
    `nama_lengkap` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `kelas` VARCHAR(50),
    `jurusan_id` INT NULL,
    `email` VARCHAR(255) NULL,
    `foto` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CALL add_index_if_not_exists('siswa', 'idx_siswa_nis', '`nis`');
CALL add_index_if_not_exists('siswa', 'idx_siswa_kelas', '`kelas`');
CALL add_index_if_not_exists('siswa', 'idx_siswa_jurusan', '`jurusan_id`');

-- 4. UJIAN - New columns for scheduling
CALL add_column_if_not_exists('ujian', 'tanggal_mulai', 'DATETIME NULL AFTER `tampilkan_skor`');
CALL add_column_if_not_exists('ujian', 'tanggal_selesai', 'DATETIME NULL AFTER `tanggal_mulai`');
CALL add_column_if_not_exists('ujian', 'durasi_per_soal', 'INT DEFAULT 0 AFTER `tanggal_selesai`');
CALL add_column_if_not_exists('ujian', 'tampil_hasil_langsung', "ENUM('ya','tidak') DEFAULT 'ya' AFTER `durasi_per_soal`");

-- 5. UJIAN_KELAS
CREATE TABLE IF NOT EXISTS `ujian_kelas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_ujian` INT NOT NULL,
    `id_kelas` INT NOT NULL,
    UNIQUE KEY `unique_ujian_kelas` (`id_ujian`, `id_kelas`),
    FOREIGN KEY (`id_ujian`) REFERENCES `ujian`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`id_kelas`) REFERENCES `kelas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CALL add_index_if_not_exists('ujian_kelas', 'idx_ujian_kelas_ujian', '`id_ujian`');
CALL add_index_if_not_exists('ujian_kelas', 'idx_ujian_kelas_kelas', '`id_kelas`');

-- 6. IZIN_REMEDI - New columns
CALL add_column_if_not_exists('izin_remedi', 'alasan', 'TEXT NULL AFTER `created_at`');
CALL add_column_if_not_exists('izin_remedi', 'approved_by', 'INT NULL AFTER `alasan`');
CALL add_column_if_not_exists('izin_remedi', 'approved_at', 'TIMESTAMP NULL AFTER `approved_by`');
CALL add_index_if_not_exists('izin_remedi', 'idx_izin_remedi_approved_by', '`approved_by`');

-- 7. PENGUMUMAN
CREATE TABLE IF NOT EXISTS `pengumuman` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `judul` VARCHAR(255) NOT NULL,
    `isi` TEXT NOT NULL,
    `tipe` ENUM('umum','kelas','jurusan') DEFAULT 'umum',
    `target_kelas` VARCHAR(100) NULL,
    `target_jurusan_id` INT NULL,
    `dibuat_oleh` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`dibuat_oleh`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`target_jurusan_id`) REFERENCES `jurusan`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CALL add_index_if_not_exists('pengumuman', 'idx_pengumuman_tipe', '`tipe`');
CALL add_index_if_not_exists('pengumuman', 'idx_pengumuman_dibuat', '`dibuat_oleh`');
CALL add_index_if_not_exists('pengumuman', 'idx_pengumuman_created', '`created_at`');

-- 8. AUDIT_LOG
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `admin_username` VARCHAR(100),
    `aksi` VARCHAR(50) NOT NULL,
    `entitas` VARCHAR(50) NOT NULL,
    `entitas_id` INT NULL,
    `detail` TEXT NULL,
    `ip_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_created_at` (`created_at`),
    INDEX `idx_audit_admin_id` (`admin_id`),
    INDEX `idx_audit_entitas` (`entitas`, `entitas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 9. ADMIN_USERS - Force password change column
CALL add_column_if_not_exists('admin_users', 'password_change_required', "TINYINT(1) DEFAULT 0 AFTER `password`");

-- Cleanup
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
