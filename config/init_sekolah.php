<?php
// config/init_sekolah.php - Inisialisasi data sekolah

require_once 'database.php';

$konfigurasi_cache = null;

function initKonfigurasiSekolah($conn) {
    global $konfigurasi_cache;
    
    $table_check = $conn->query("SHOW TABLES LIKE 'konfigurasi_sekolah'");

    if ($table_check->num_rows === 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS konfigurasi_sekolah (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nama_sekolah VARCHAR(255) NOT NULL DEFAULT 'SMA Negeri 6 Cimahi',
            logo VARCHAR(255) DEFAULT NULL,
            warna_primer VARCHAR(20) DEFAULT '#667eea',
            warna_sekunder VARCHAR(20) DEFAULT '#764ba2',
            tampilkan_riwayat ENUM('ya','tidak') DEFAULT 'ya',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $conn->query("INSERT INTO konfigurasi_sekolah (nama_sekolah) VALUES ('SMA Negeri 6 Cimahi')");
    } else {
        $col_exists = $conn->query("SHOW COLUMNS FROM konfigurasi_sekolah LIKE 'tampilkan_riwayat'");
        if ($col_exists->num_rows === 0) {
            $conn->query("ALTER TABLE konfigurasi_sekolah ADD COLUMN tampilkan_riwayat ENUM('ya','tidak') DEFAULT 'ya'");
        }
    }
}

function getKonfigurasiSekolah($conn) {
    global $konfigurasi_cache;
    
    if ($konfigurasi_cache !== null) {
        return $konfigurasi_cache;
    }
    
    if (isset($GLOBALS['redis'])) {
        $cached = $GLOBALS['redis']->get('sekolah:config');
        if ($cached !== null) {
            $konfigurasi_cache = $cached;
            return $konfigurasi_cache;
        }
    }
    
    $result = $conn->query("SELECT * FROM konfigurasi_sekolah LIMIT 1");
    $konfigurasi_cache = $result->fetch_assoc();
    
    if (isset($GLOBALS['redis'])) {
        $GLOBALS['redis']->set('sekolah:config', $konfigurasi_cache, 3600);
    }
    
    return $konfigurasi_cache;
}

function updateKonfigurasiSekolah($conn, $nama_sekolah, $logo, $warna_primer, $warna_sekunder, $tampilkan_riwayat = 'ya') {
    global $konfigurasi_cache;
    
    $stmt = $conn->prepare("UPDATE konfigurasi_sekolah SET nama_sekolah = ?, logo = ?, warna_primer = ?, warna_sekunder = ?, tampilkan_riwayat = ? WHERE id = 1");
    $stmt->bind_param("sssss", $nama_sekolah, $logo, $warna_primer, $warna_sekunder, $tampilkan_riwayat);
    $result = $stmt->execute();
    $stmt->close();
    
    $konfigurasi_cache = null;
    
    if (isset($GLOBALS['redis'])) {
        $GLOBALS['redis']->delete('sekolah:config');
    }
    
    return $result;
}

initKonfigurasiSekolah($conn);

function initConcurrencyControl($conn) {
    $result = $conn->query("SHOW COLUMNS FROM ujian LIKE 'updated_at'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE ujian ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    $result = $conn->query("SHOW COLUMNS FROM soal LIKE 'updated_at'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE soal ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    $table_check = $conn->query("SHOW TABLES LIKE 'jawaban_sementara'");
    if ($table_check->num_rows === 0) {
        $table_check_old = $conn->query("SHOW TABLES LIKE 'jawaban_sEMENTARA'");
        if ($table_check_old->num_rows > 0) {
            $conn->query("RENAME TABLE `jawaban_sEMENTARA` TO `jawaban_sementara`");
        }
        $conn->query("CREATE TABLE IF NOT EXISTS `jawaban_sementara` (
            `id` int NOT NULL AUTO_INCREMENT,
            `id_ujian` int NOT NULL,
            `nis` varchar(50) NOT NULL,
            `nama` varchar(100) DEFAULT NULL,
            `kelas` varchar(50) DEFAULT NULL,
            `answers` json DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_ujian_nis` (`id_ujian`, `nis`),
            INDEX `idx_nis` (`nis`),
            INDEX `idx_ujian` (`id_ujian`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    }
    
    // Add remember_token column to admin_users if not exists
    $col_check = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'remember_token'");
    if ($col_check->num_rows === 0) {
        $conn->query("ALTER TABLE admin_users ADD COLUMN remember_token VARCHAR(128) DEFAULT NULL AFTER password");
        $conn->query("ALTER TABLE admin_users ADD INDEX idx_remember_token (remember_token)");
    }
    
    $siswa_table = $conn->query("SHOW TABLES LIKE 'siswa'");
    if ($siswa_table && $siswa_table->num_rows > 0) {
        $col_siswa = $conn->query("SHOW COLUMNS FROM siswa LIKE 'remember_token'");
        if ($col_siswa && $col_siswa->num_rows === 0) {
            $conn->query("ALTER TABLE siswa ADD COLUMN remember_token VARCHAR(128) DEFAULT NULL AFTER foto");
            $conn->query("ALTER TABLE siswa ADD INDEX idx_siswa_remember_token (remember_token)");
        }
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'exam_violations'");
    if ($table_check->num_rows === 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS `exam_violations` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_ujian` INT NOT NULL,
            `nis` VARCHAR(50) NOT NULL,
            `jenis_violation` VARCHAR(50) NOT NULL,
            `detail` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ujian_nis` (`id_ujian`, `nis`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    }
}

initConcurrencyControl($conn);
?>
