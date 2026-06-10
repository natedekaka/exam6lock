-- Migration 01: Base Tables untuk fresh install
-- Membuat tabel dasar yang tidak ada di migration lain

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_change_required` tinyint(1) DEFAULT '0',
  `remember_token` varchar(128) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `soal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `pertanyaan` text NOT NULL,
  `gambar_pertanyaan` varchar(255) DEFAULT NULL,
  `opsi_a` varchar(255) NOT NULL,
  `gambar_a` varchar(255) DEFAULT NULL,
  `opsi_b` varchar(255) NOT NULL,
  `gambar_b` varchar(255) DEFAULT NULL,
  `opsi_c` varchar(255) NOT NULL,
  `gambar_c` varchar(255) DEFAULT NULL,
  `opsi_d` varchar(255) NOT NULL,
  `gambar_d` varchar(255) DEFAULT NULL,
  `opsi_e` varchar(255) NOT NULL,
  `gambar_e` varchar(255) DEFAULT NULL,
  `kunci_jawaban` enum('a','b','c','d','e') NOT NULL,
  `poin` int DEFAULT '10',
  `kategori` varchar(100) DEFAULT NULL,
  `timer_soal` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_ujian` (`id_ujian`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `hasil_ujian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `nis` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas` varchar(50) NOT NULL,
  `total_skor` int DEFAULT '0',
  `skor_awal` int DEFAULT NULL,
  `waktu_submit` datetime DEFAULT CURRENT_TIMESTAMP,
  `detail_jawaban` text,
  `device_fingerprint` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `total_violations` int DEFAULT '0',
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_ujian` (`id_ujian`),
  KEY `nis` (`nis`),
  KEY `idx_ujian_nis` (`id_ujian`, `nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
