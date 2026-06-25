-- Backup Database: ujian_online
-- Tanggal: 2026-06-11 21:23:19
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+07:00';


-- --------------------------------------------------------
-- Table structure for `admin_users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `admin_users` — 3 rows
INSERT INTO `admin_users` (`id`, `username`, `password`, `password_change_required`, `remember_token`, `nama_lengkap`, `created_at`, `role`, `last_login`) VALUES
('1', 'admin', '$2y$12$uZF2KVRdMyqOKyBhpvCFBe72ia/.CWYZseASp75gvzC9XZ/OVoEGy', '0', NULL, 'Administrator', '2026-03-04 08:33:28', 'super_admin', '2026-06-11 21:10:47'),
('6', 'edi', '$2y$10$COk2px9Wh1FVZjZoHBMRr./rMhJBgzcm4y7uzLzAAPmn1mftviiDa', '0', NULL, 'Edi Kusnadi', '2026-06-10 23:28:20', 'admin', NULL),
('7', 'hana', '$2y$10$LwDGrw03MNksqvOEBd/7juH66lm8F0s/JHwVLNT2KWr1jKLCoyRom', '0', NULL, 'Raden Hana Amalia', '2026-06-10 23:33:02', 'admin', NULL);

-- --------------------------------------------------------
-- Table structure for `audit_log`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `admin_username` varchar(100) DEFAULT NULL,
  `aksi` varchar(50) NOT NULL,
  `entitas` varchar(50) NOT NULL,
  `entitas_id` int DEFAULT NULL,
  `detail` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created_at` (`created_at`),
  KEY `idx_audit_admin_id` (`admin_id`),
  KEY `idx_audit_entitas` (`entitas`,`entitas_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `audit_log` — 31 rows
INSERT INTO `audit_log` (`id`, `admin_id`, `admin_username`, `aksi`, `entitas`, `entitas_id`, `detail`, `ip_address`, `created_at`) VALUES
('34', '1', 'admin', 'DELETE', 'audit_log', '0', 'Hapus 33 entri audit log dari 2026-06-10 sampai 2026-06-10', '10.89.13.5', '2026-06-10 22:00:28'),
('35', '1', 'admin', 'DELETE', 'UJIAN', '21', 'Menghapus ujian: PPKn - Latihan soal Ulangan (Kelas XI)', '10.89.13.5', '2026-06-10 22:01:00'),
('36', '1', 'admin', 'DELETE', 'UJIAN', '20', 'Menghapus ujian: Informatika - Konfigurasi Keamanan Jaringan (Kelas XI)', '10.89.13.5', '2026-06-10 22:01:10'),
('37', '1', 'admin', 'DELETE', 'UJIAN', '19', 'Menghapus ujian: Informatika - Pretest Sistem Komputer & Otomatisasi Perangkat Lunak Kantor (Kelas X)', '10.89.13.5', '2026-06-10 22:01:18'),
('38', '1', 'admin', 'DELETE', 'UJIAN', '18', 'Menghapus ujian: Informatika - Postest HTML Dasar (Kelas XI)', '10.89.13.5', '2026-06-10 22:01:32'),
('39', '1', 'admin', 'DELETE', 'UJIAN', '17', 'Menghapus ujian: Informatika - Pretest HTML Dasar (Kelas XI)', '10.89.13.5', '2026-06-10 22:01:40'),
('40', '1', 'admin', 'DELETE', 'UJIAN', '9', 'Menghapus ujian: Informatika - Latihan1 Merancang Jaringan Komputer (Kelas XI)', '10.89.13.5', '2026-06-10 22:01:49'),
('41', '1', 'admin', 'DELETE', 'UJIAN', '10', 'Menghapus ujian: Informatika - Latihan2 Instalasi dan Konfgurasi Jaringan Komputer & Keamanan Jaringan Komputer (Kelas XI)', '10.89.13.5', '2026-06-10 22:01:55'),
('42', '1', 'admin', 'DELETE', 'UJIAN', '11', 'Menghapus ujian: Informatika - Pretest1 Jaringan Komputer dan Internet (Kelas X)', '10.89.13.5', '2026-06-10 22:02:03'),
('43', '1', 'admin', 'DELETE', 'UJIAN', '12', 'Menghapus ujian: Informatika - Postest Jaringan Komputer dan Internet (Kelas X)', '10.89.13.5', '2026-06-10 22:02:11'),
('44', '1', 'admin', 'DELETE', 'UJIAN', '13', 'Menghapus ujian: Kimia X - Reduksi', '10.89.13.5', '2026-06-10 22:02:20'),
('45', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 22:21:28'),
('46', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 22:24:52'),
('47', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 22:28:43'),
('48', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 22:30:27'),
('49', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 22:35:50'),
('50', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 22:45:02'),
('51', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 23:14:59'),
('52', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 23:21:24'),
('53', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 23:27:46'),
('54', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 23:38:23'),
('55', '1', 'admin', 'CREATE', 'UJIAN', '22', 'Menambahkan ujian: Test Soal 2', '10.89.13.5', '2026-06-10 23:40:11'),
('56', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-10 23:46:02'),
('57', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-11 19:07:49'),
('58', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-11 19:18:25'),
('59', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-11 20:26:02'),
('60', '1', 'admin', 'import', 'siswa', '0', 'Import CSV: 3 siswa berhasil, 0 skipped', '10.89.13.5', '2026-06-11 20:33:10'),
('61', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-11 20:38:08'),
('62', '1', 'admin', 'CREATE', 'SOAL', '302', 'Soal untuk ujian ID: 16', '10.89.13.5', '2026-06-11 20:55:56'),
('63', '1', 'admin', 'UPDATE', 'SOAL', '302', 'Soal untuk ujian ID: 16', '10.89.13.5', '2026-06-11 20:56:43'),
('64', '1', 'admin', 'LOGIN', 'ADMIN', '1', 'Login berhasil', '10.89.13.5', '2026-06-11 21:10:47');

-- --------------------------------------------------------
-- Table structure for `exam_violations`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `exam_violations`;
CREATE TABLE `exam_violations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `nis` varchar(50) NOT NULL,
  `jenis_violation` varchar(50) NOT NULL,
  `detail` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ujian_nis` (`id_ujian`,`nis`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=478 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `exam_violations` — 228 rows
INSERT INTO `exam_violations` (`id`, `id_ujian`, `nis`, `jenis_violation`, `detail`, `created_at`) VALUES
('206', '11', '252610037', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:47:31'),
('207', '11', '252610037', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 08:47:31'),
('208', '11', '252610042', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:47:46'),
('209', '11', '252610037', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 08:47:47'),
('210', '11', '252610037', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 08:47:47'),
('211', '11', '252610036', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:48:56'),
('212', '11', '252610036', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:48:57'),
('213', '11', '252610037', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:48:59'),
('214', '11', '252610042', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:49:07'),
('215', '11', '252610042', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:49:08'),
('216', '11', '252610042', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:49:09'),
('217', '11', '252610042', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:49:11'),
('218', '11', '252610042', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 08:49:12'),
('219', '11', '252610042', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 08:49:12'),
('220', '11', '252610042', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:49:16'),
('221', '11', '252610042', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 08:49:16'),
('222', '11', '252610015', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:49:45'),
('223', '11', '0104965756', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:50:25'),
('224', '11', '252610045', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:50:29'),
('225', '11', '252610045', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 08:50:30'),
('226', '11', '252610045', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 08:50:31'),
('227', '11', '252610034', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:51:32'),
('228', '11', '252610034', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 08:51:33'),
('229', '11', '252610045', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:51:34'),
('230', '11', '252610045', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 08:51:35'),
('231', '11', '252610034', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 08:51:35'),
('232', '11', '252610034', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 08:51:41'),
('233', '11', '252610034', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 08:51:41'),
('234', '11', '252610045', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 08:51:46'),
('235', '11', '252610045', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 08:51:46'),
('236', '11', '252610017', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:56:01'),
('237', '11', '252610017', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:56:55'),
('238', '11', '252610017', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:57:13'),
('239', '11', '252610017', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:57:40'),
('240', '11', '252610008', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 08:58:17'),
('241', '11', '252610008', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 08:58:18'),
('242', '11', '252610008', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 08:58:43'),
('243', '11', '252610008', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 08:58:43'),
('244', '11', '252610010', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 08:59:37'),
('245', '11', '252610032', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 09:00:03'),
('246', '11', '252610032', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 09:00:09'),
('247', '11', '252610032', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 09:00:09'),
('248', '11', '252610010', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 09:00:43'),
('249', '11', '0093582245', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 09:00:55'),
('250', '11', '0095325840', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:02:54'),
('251', '11', '0095325840', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 09:02:56'),
('252', '11', '252610032', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:03:09'),
('253', '11', '252610032', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 09:03:09'),
('254', '11', '252610032', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 09:03:26'),
('255', '11', '252610032', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 09:03:27');
INSERT INTO `exam_violations` (`id`, `id_ujian`, `nis`, `jenis_violation`, `detail`, `created_at`) VALUES
('256', '11', '0095325840', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 09:03:49'),
('257', '11', '0095325840', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 09:03:49'),
('258', '11', '252610037', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 09:04:34'),
('259', '11', '245891042', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:05:44'),
('260', '11', '245891042', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 09:05:44'),
('261', '11', '245891042', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 09:05:44'),
('262', '11', '245891042', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:06:21'),
('263', '11', '245891042', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 09:06:21'),
('264', '11', '252610078', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 09:15:22'),
('265', '11', '252610078', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 09:15:24'),
('266', '11', '252610078', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:15:27'),
('267', '11', '252610078', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:15:28'),
('268', '11', '252610078', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:15:29'),
('269', '11', '252610078', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 09:15:30'),
('270', '11', '252610078', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 09:15:30'),
('271', '11', '252610078', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 09:15:34'),
('272', '11', '252610078', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 09:28:19'),
('273', '11', '252610078', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 09:28:19'),
('274', '11', '252610078', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:28:20'),
('275', '11', '252610078', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:28:22'),
('276', '11', '252610078', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 09:28:23'),
('277', '11', '1231234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:28:51'),
('278', '11', '252610087', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 09:32:25'),
('279', '11', '1231234', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 10:30:53'),
('280', '11', '1231234', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 10:30:53'),
('281', '11', '1231234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 10:30:54'),
('282', '11', '1231234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 10:30:56'),
('283', '11', '1231234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 10:30:57'),
('284', '11', '1231234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 10:31:00'),
('285', '11', '1231234', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-04 10:31:00'),
('286', '11', '1231234', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 10:31:10'),
('287', '11', '1231234', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 10:31:10'),
('288', '11', '1231234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 10:31:13'),
('290', '17', '.', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:01:22'),
('291', '17', '242510109', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:03:48'),
('292', '17', '.', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:03:51'),
('293', '17', '252611006', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:04:31'),
('294', '17', '252611006', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:06:16'),
('295', '17', '.', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:06:24'),
('296', '17', '252611006', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:07:08'),
('297', '17', '242510268', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:11:18'),
('298', '17', '242510166', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:12:18'),
('299', '17', '242510288', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:16:52'),
('300', '17', '252611006', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 12:20:18'),
('303', '17', '25261124', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:09:55'),
('304', '17', '25261124', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:09:59'),
('305', '18', '24251023', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 14:21:16'),
('306', '18', '24251023', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 14:21:17'),
('307', '18', '24251023', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:21:18'),
('308', '18', '24251023', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:21:25');
INSERT INTO `exam_violations` (`id`, `id_ujian`, `nis`, `jenis_violation`, `detail`, `created_at`) VALUES
('309', '18', '24251023', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:21:33'),
('310', '18', '242510096', 'copy_paste', 'Siswa mencoba menyalin teks', '2026-05-04 14:21:44'),
('311', '18', '242510096', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:21:45'),
('312', '18', '24251023', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:21:55'),
('313', '18', '24251023', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 14:21:55'),
('314', '18', '24251023', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:21:59'),
('315', '18', '24251023', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:22:00'),
('316', '18', '24251023', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 14:22:00'),
('317', '18', '24251023', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:23:13'),
('318', '18', '24251023', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:23:17'),
('319', '18', '24251023', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 14:23:17'),
('320', '18', '24251023', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:23:18'),
('321', '18', '242510288', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 14:25:13'),
('322', '18', '2425117', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 14:25:16'),
('323', '18', '242510109', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-04 14:25:23'),
('324', '18', '242510109', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:25:26'),
('325', '18', '242510109', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 14:25:26'),
('326', '18', '242510413', 'orientation_change', 'HP dirotasi', '2026-05-04 14:26:47'),
('327', '18', '242510413', 'orientation_change', 'HP dirotasi', '2026-05-04 14:26:49'),
('328', '18', '24251006', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-04 14:28:07'),
('329', '18', '24251006', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-04 14:28:07'),
('330', '18', '242510264', 'right_click', 'Siswa mencoba klik kanan', '2026-05-04 14:29:30'),
('331', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 09:02:50'),
('332', '19', '123', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 09:02:50'),
('333', '19', '123', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 09:02:52'),
('334', '19', '123', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 09:02:52'),
('335', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 09:11:41'),
('336', '19', '123', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 09:11:42'),
('337', '19', '123', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 09:11:42'),
('338', '19', '252610086', 'orientation_change', 'HP dirotasi', '2026-05-06 10:13:16'),
('339', '19', '202610094', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:13:29'),
('340', '19', '202610094', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:13:31'),
('341', '19', '202610094', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:13:34'),
('342', '19', '202610094', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:13:35'),
('343', '19', '202610094', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:13:36'),
('344', '19', '202610094', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:13:36'),
('345', '19', '252610069', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:13:47'),
('346', '19', '202610094', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:13:50'),
('347', '19', '252610069', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:13:51'),
('348', '19', '252610069', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:13:59'),
('349', '19', '252610069', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:14:03'),
('350', '19', '252610069', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:14:05'),
('351', '19', '252610069', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:05'),
('352', '19', '252610069', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:10'),
('353', '19', '252610069', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:14:11'),
('354', '19', '252610069', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:14:11'),
('355', '19', '252610089', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:14:12'),
('356', '19', '252610069', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:14:13'),
('357', '19', '252610089', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:14:14'),
('358', '19', '252610089', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:15');
INSERT INTO `exam_violations` (`id`, `id_ujian`, `nis`, `jenis_violation`, `detail`, `created_at`) VALUES
('359', '19', '252610059', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:14:15'),
('360', '19', '252610059', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:14:18'),
('361', '19', '252610059', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:19'),
('362', '19', '252610089', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:22'),
('363', '19', '252610059', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:22'),
('364', '19', '252610089', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:23'),
('365', '19', '252610089', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:14:24'),
('366', '19', '252610089', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:14:24'),
('367', '19', '252610059', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:25'),
('368', '19', '252610053', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:14:47'),
('369', '19', '252610053', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:14:48'),
('370', '19', '252610053', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:15:04'),
('371', '19', '252610053', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:15:04'),
('372', '19', '252610090', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:01'),
('373', '19', '252610090', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:06'),
('374', '19', '252610090', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:11'),
('375', '19', '252610090', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:16'),
('376', '19', '252610090', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:18'),
('377', '19', '252610056', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:16:20'),
('378', '19', '252610056', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:16:25'),
('379', '19', '252610056', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:16:35'),
('380', '19', '252610056', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:16:35'),
('381', '19', '2526100066', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:55'),
('382', '19', '2526100066', 'right_click', 'Siswa mencoba klik kanan', '2026-05-06 10:16:56'),
('383', '19', '2526100066', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:16:58'),
('384', '19', '2526100066', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:17:00'),
('385', '19', '2526100066', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:17:01'),
('386', '19', '2526100066', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:17:01'),
('387', '19', '2526100066', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:17:01'),
('388', '19', '252610082', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:17:09'),
('389', '19', '252610082', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:17:15'),
('390', '19', '252610082', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:17:15'),
('391', '19', '252610056', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:18:43'),
('392', '19', '252610056', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:18:55'),
('393', '19', '252610056', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:18:55'),
('394', '19', '252610086', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:19:30'),
('395', '19', '252610086', 'orientation_change', 'HP dirotasi', '2026-05-06 10:22:18'),
('396', '19', '252610057', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:22:23'),
('397', '19', '252610057', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:22:25'),
('398', '19', '252610056', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:23:58'),
('399', '19', '252610056', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:24:02'),
('400', '19', '252610056', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:24:03'),
('401', '19', '252610057', 'idle_too_long', 'Siswa tidak aktif terlalu lama', '2026-05-06 10:24:58'),
('402', '19', '252610068', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-06 10:32:07'),
('403', '19', '252610068', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:32:41'),
('404', '19', '252610068', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-06 10:32:42'),
('405', '19', '252610068', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-06 10:32:43'),
('406', '19', '252610068', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-06 10:32:43'),
('407', '19', '123', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-07 06:09:20'),
('408', '19', '123', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-07 06:09:20');
INSERT INTO `exam_violations` (`id`, `id_ujian`, `nis`, `jenis_violation`, `detail`, `created_at`) VALUES
('409', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:22'),
('410', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:24'),
('411', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:24'),
('412', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:25'),
('413', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:26'),
('414', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:28'),
('415', '19', '123', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:09:30'),
('416', '19', '123', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-07 06:09:34'),
('417', '19', '1234', 'right_click', 'Siswa mencoba klik kanan', '2026-05-07 06:10:14'),
('418', '19', '1234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:10:20'),
('419', '19', '1234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:10:21'),
('420', '19', '1234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:10:21'),
('421', '19', '1234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:10:22'),
('422', '19', '1234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:10:23'),
('423', '19', '1234', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:10:32'),
('424', '19', '1234', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-07 06:10:32'),
('425', '19', '12344', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:27:47'),
('426', '19', '12344', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-07 06:27:47'),
('427', '19', '12344', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-07 06:27:53'),
('428', '19', '12344', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-07 06:27:53'),
('429', '19', '12344', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:28:08'),
('430', '19', '12344', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-07 06:28:08'),
('431', '19', '12344', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-05-07 06:38:54'),
('432', '19', '12344', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen', '2026-05-07 06:38:54'),
('433', '19', '12344', 'window_blur', 'Siswa keluar dari window/aplikasi', '2026-05-07 06:39:10'),
('434', '19', '12344', 'tab_switch', 'Siswa meninggalkan tab ujian', '2026-05-07 06:39:11'),
('476', '22', '12345', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-06-10 23:44:58'),
('477', '22', '12345', 'exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)', '2026-06-11 19:29:54');

-- --------------------------------------------------------
-- Table structure for `hasil_ujian`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hasil_ujian`;
CREATE TABLE `hasil_ujian` (
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
  KEY `idx_ujian_nis` (`id_ujian`,`nis`),
  CONSTRAINT `hasil_ujian_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=366 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `hasil_ujian` — 1 rows
INSERT INTO `hasil_ujian` (`id`, `id_ujian`, `nis`, `nama`, `kelas`, `total_skor`, `skor_awal`, `waktu_submit`, `detail_jawaban`, `device_fingerprint`, `ip_address`, `total_violations`, `submitted_at`) VALUES
('365', '16', '12345', 'Siswa Test', 'X-A', '110', '110', '2026-06-11 21:11:45', '[{\"soal_id\":164,\"pertanyaan\":\"Manakah yang merupakan singkatan dari World Wide Web?\",\"jawaban_siswa\":\"b\",\"kunci_jawaban\":\"b\",\"is_correct\":true,\"poin\":25,\"poin_diperoleh\":25,\"opsi_a\":\"Wrestling World Wide\",\"opsi_b\":\"World Wide Web\",\"opsi_c\":\"World Web Wide\",\"opsi_d\":\"Web World Wide\",\"opsi_e\":\"World Wide Website\"},{\"soal_id\":165,\"pertanyaan\":\"Program atau aplikasi yang digunakan untuk menjelajahi halaman web disebut...\",\"jawaban_siswa\":\"a\",\"kunci_jawaban\":\"a\",\"is_correct\":true,\"poin\":25,\"poin_diperoleh\":25,\"opsi_a\":\"Web Browser\",\"opsi_b\":\"Microsoft Word\",\"opsi_c\":\"Antivirus\",\"opsi_d\":\"Windows Explorer\",\"opsi_e\":\"Keyboard\"},{\"soal_id\":166,\"pertanyaan\":\"Simbol \'@\' dalam alamat email digunakan untuk memisahkan nama pengguna dengan...\",\"jawaban_siswa\":\"c\",\"kunci_jawaban\":\"c\",\"is_correct\":true,\"poin\":25,\"poin_diperoleh\":25,\"opsi_a\":\"Nomor telepon\",\"opsi_b\":\"Alamat rumah\",\"opsi_c\":\"Nama domain\\/penyedia layanan\",\"opsi_d\":\"Kata sandi\",\"opsi_e\":\"Nama lengkap\"},{\"soal_id\":167,\"pertanyaan\":\"Manakah dari berikut ini yang merupakan contoh mesin pencari (search engine)?\",\"jawaban_siswa\":\"b\",\"kunci_jawaban\":\"b\",\"is_correct\":true,\"poin\":25,\"poin_diperoleh\":25,\"opsi_a\":\"Photoshop\",\"opsi_b\":\"Google\",\"opsi_c\":\"Notepad\",\"opsi_d\":\"Calculator\",\"opsi_e\":\"Media Player\"},{\"soal_id\":302,\"pertanyaan\":\"apakah kamu sudah makan\",\"jawaban_siswa\":\"a\",\"kunci_jawaban\":\"a\",\"is_correct\":true,\"poin\":10,\"poin_diperoleh\":10,\"opsi_a\":\"aaa\",\"opsi_b\":\"bbb\",\"opsi_c\":\"ccc\",\"opsi_d\":\"ddd\",\"opsi_e\":\"eee\"}]', NULL, NULL, '0', NULL);

-- --------------------------------------------------------
-- Table structure for `izin_remedi`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `izin_remedi`;
CREATE TABLE `izin_remedi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `nis` varchar(50) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `diberikan_oleh` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `alasan` text,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_remedi_ujian_nis` (`id_ujian`,`nis`),
  KEY `idx_nis` (`nis`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_izin_remedi_approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `izin_remedi` — 1 rows
INSERT INTO `izin_remedi` (`id`, `id_ujian`, `nis`, `nama`, `kelas`, `diberikan_oleh`, `created_at`, `alasan`, `approved_by`, `approved_at`) VALUES
('6', '16', '123456', 'Daniarsyah', 'XI-1', 'admin', '2026-06-11 19:18:43', NULL, NULL, '2026-06-11 19:18:43');

-- --------------------------------------------------------
-- Table structure for `jawaban_sEMENTARA`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `jawaban_sEMENTARA`;
CREATE TABLE `jawaban_sEMENTARA` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `nis` varchar(50) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `answers` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_fingerprint` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ujian_nis` (`id_ujian`,`nis`),
  KEY `idx_nis` (`nis`),
  KEY `idx_ujian` (`id_ujian`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `jawaban_sEMENTARA` — 3 rows
INSERT INTO `jawaban_sEMENTARA` (`id`, `id_ujian`, `nis`, `nama`, `kelas`, `answers`, `created_at`, `updated_at`, `ip_address`, `device_fingerprint`) VALUES
('1', '16', '12345', '', '', '{\"164\": \"b\", \"165\": \"a\", \"166\": \"c\", \"167\": \"b\", \"302\": \"a\"}', '2026-06-10 06:53:14', '2026-06-11 21:11:44', NULL, NULL),
('29', '16', '123456', '', '', '{\"164\": \"b\", \"165\": \"a\", \"166\": \"c\", \"167\": \"a\"}', '2026-06-10 21:56:34', '2026-06-10 21:56:44', NULL, NULL),
('33', '22', '12345', '', '', '{\"297\": \"a\", \"298\": \"a\", \"299\": \"d\", \"300\": \"a\", \"301\": \"b\"}', '2026-06-10 23:44:34', '2026-06-11 20:54:13', NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `jawaban_sementara`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `jawaban_sementara`;
CREATE TABLE `jawaban_sementara` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `nis` varchar(50) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `answers` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ujian_nis` (`id_ujian`,`nis`),
  KEY `idx_nis` (`nis`),
  KEY `idx_ujian` (`id_ujian`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `jawaban_sementara` — 2 rows
INSERT INTO `jawaban_sementara` (`id`, `id_ujian`, `nis`, `nama`, `kelas`, `answers`, `created_at`, `updated_at`) VALUES
('2', '2', '1213123', NULL, NULL, '[]', '2026-04-11 00:22:48', '2026-04-11 00:23:05'),
('6', '2', '3', NULL, NULL, '{\"2\": \"b\"}', '2026-04-11 00:23:06', '2026-04-11 00:23:06');

-- --------------------------------------------------------
-- Table structure for `jurusan`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `jurusan`;
CREATE TABLE `jurusan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_jurusan` varchar(100) NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `jurusan` — 1 rows
INSERT INTO `jurusan` (`id`, `nama_jurusan`, `kode`, `created_at`) VALUES
('1', 'IPA', 'A1', '2026-06-10 18:47:56');

-- --------------------------------------------------------
-- Table structure for `kelas`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `kelas`;
CREATE TABLE `kelas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_kelas` varchar(100) NOT NULL,
  `jurusan_id` int DEFAULT NULL,
  `tingkat` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kelas_jurusan` (`jurusan_id`),
  CONSTRAINT `kelas_ibfk_1` FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `kelas` — 1 rows
INSERT INTO `kelas` (`id`, `nama_kelas`, `jurusan_id`, `tingkat`, `created_at`) VALUES
('1', 'XI-1', '1', 'XI', '2026-06-10 18:48:26');

-- --------------------------------------------------------
-- Table structure for `konfigurasi_sekolah`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `konfigurasi_sekolah`;
CREATE TABLE `konfigurasi_sekolah` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_sekolah` varchar(255) NOT NULL DEFAULT 'SMA Negeri 6 Cimahi',
  `logo` varchar(255) DEFAULT NULL,
  `warna_primer` varchar(20) DEFAULT '#667eea',
  `warna_sekunder` varchar(20) DEFAULT '#764ba2',
  `tampilkan_riwayat` enum('ya','tidak') DEFAULT 'ya',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `konfigurasi_sekolah` — 1 rows
INSERT INTO `konfigurasi_sekolah` (`id`, `nama_sekolah`, `logo`, `warna_primer`, `warna_sekunder`, `tampilkan_riwayat`, `created_at`, `updated_at`) VALUES
('1', 'SMA Negeri 6 Cimahi', 'logo_1781092664.png', '#667eea', '#764ba2', 'ya', '2026-04-11 06:47:09', '2026-06-10 18:57:44');

-- --------------------------------------------------------
-- Table structure for `pengumuman`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pengumuman`;
CREATE TABLE `pengumuman` (
  `id` int NOT NULL AUTO_INCREMENT,
  `judul` varchar(255) NOT NULL,
  `isi` text NOT NULL,
  `tipe` enum('umum','kelas','jurusan') DEFAULT 'umum',
  `target_kelas` varchar(100) DEFAULT NULL,
  `target_jurusan_id` int DEFAULT NULL,
  `dibuat_oleh` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `target_jurusan_id` (`target_jurusan_id`),
  KEY `idx_pengumuman_tipe` (`tipe`),
  KEY `idx_pengumuman_dibuat` (`dibuat_oleh`),
  KEY `idx_pengumuman_created` (`created_at`),
  CONSTRAINT `pengumuman_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengumuman_ibfk_2` FOREIGN KEY (`target_jurusan_id`) REFERENCES `jurusan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- --------------------------------------------------------
-- Table structure for `siswa`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `siswa`;
CREATE TABLE `siswa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nis` varchar(50) NOT NULL,
  `nama_lengkap` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_change_required` tinyint(1) DEFAULT '0',
  `kelas` varchar(50) DEFAULT NULL,
  `jurusan_id` int DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `remember_token` varchar(128) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  KEY `idx_siswa_nis` (`nis`),
  KEY `idx_siswa_kelas` (`kelas`),
  KEY `idx_siswa_jurusan` (`jurusan_id`),
  KEY `idx_siswa_remember_token` (`remember_token`),
  CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `siswa` — 7 rows
INSERT INTO `siswa` (`id`, `nis`, `nama_lengkap`, `password`, `password_change_required`, `kelas`, `jurusan_id`, `email`, `foto`, `remember_token`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '12345', 'Siswa Test', '$2y$10$RoOUh72O6rUYmOug6krhy.Waqzapfd/3xJEgN2ivOO3o4lab693t.', '0', 'X-A', NULL, '', NULL, NULL, '1', '2026-06-10 05:27:54', '2026-06-10 19:59:30'),
('2', '123456', 'Daniarsyah', '$2y$10$kTmF5ygAou8ywXLBQTBMUOHfmGruhNQXEjXJjxLMVwozUkFdGP8Ee', '0', 'XI-1', '1', '', NULL, NULL, '1', '2026-06-10 20:55:30', '2026-06-10 20:56:39'),
('3', '123455', 'natedekaka', '$2y$10$.s/7RSFnvTOp8Vs/lElHkeWSM4VHkhj5vSOMEPVMOiNJRNlMWtqai', '1', 'XI-1', '1', '', NULL, NULL, '1', '2026-06-10 21:15:06', '2026-06-10 21:15:06'),
('4', '123444', 'dekaka', '$2y$10$eMfJRyhKMXLtCXJA58IorOZIxco1Wm1LqkpDHC6dtTnfE7.18hcVy', '0', 'XI-1', '1', '', NULL, NULL, '1', '2026-06-10 21:30:55', '2026-06-10 21:30:55'),
('5', '1234567890', 'Budi Santoso', '$2y$10$Q4boIUQDhZQsF.Ovyleha.i1U19DS1fiwIWd/6pNyBQMvCDuTkg/.', '0', 'XI-1', '1', 'a@b.c', NULL, NULL, '1', '2026-06-11 20:33:10', '2026-06-11 20:33:10'),
('6', '1234567891', 'Siti Aisyah', '$2y$10$jY7FaIQgxOMXDtwGRU2atuD2VrvjfOx93uLX99FO5VUrM5KoLyTYu', '0', 'XI-1', '1', 'a@b.d', NULL, NULL, '1', '2026-06-11 20:33:10', '2026-06-11 20:33:10'),
('7', '1234567892', 'Ahmad Junaedi', '$2y$10$RgMaLW4ZkkM9VeaXtHJpk.EdHetu3oHCAZBJm9M0GmgCI.iT38rSO', '0', 'XI-1', '1', 'a@b.e', NULL, NULL, '1', '2026-06-11 20:33:10', '2026-06-11 20:33:10');

-- --------------------------------------------------------
-- Table structure for `soal`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `soal`;
CREATE TABLE `soal` (
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_ujian` (`id_ujian`),
  CONSTRAINT `soal_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=303 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `soal` — 10 rows
INSERT INTO `soal` (`id`, `id_ujian`, `pertanyaan`, `gambar_pertanyaan`, `opsi_a`, `gambar_a`, `opsi_b`, `gambar_b`, `opsi_c`, `gambar_c`, `opsi_d`, `gambar_d`, `opsi_e`, `gambar_e`, `kunci_jawaban`, `poin`, `kategori`, `timer_soal`, `updated_at`) VALUES
('164', '16', 'Manakah yang merupakan singkatan dari World Wide Web?', 'soal_a689333d08f4b30c.jpg', 'Wrestling World Wide', 'opsia_e958b0e92a4ee301.png', 'World Wide Web', 'opsib_dfcf5b2ecdaaf8f8.png', 'World Web Wide', 'opsic_88b1d8c0a4c5ab44.png', 'Web World Wide', NULL, 'World Wide Website', NULL, 'b', '25', '', '60', '2026-05-03 12:53:37'),
('165', '16', 'Program atau aplikasi yang digunakan untuk menjelajahi halaman web disebut...', NULL, 'Web Browser', NULL, 'Microsoft Word', NULL, 'Antivirus', NULL, 'Windows Explorer', NULL, 'Keyboard', NULL, 'a', '25', '0', '60', '2026-05-01 21:20:17'),
('166', '16', 'Simbol \'@\' dalam alamat email digunakan untuk memisahkan nama pengguna dengan...', NULL, 'Nomor telepon', NULL, 'Alamat rumah', NULL, 'Nama domain/penyedia layanan', NULL, 'Kata sandi', NULL, 'Nama lengkap', NULL, 'c', '25', '0', '60', '2026-05-01 21:20:17'),
('167', '16', 'Manakah dari berikut ini yang merupakan contoh mesin pencari (search engine)?', NULL, 'Photoshop', NULL, 'Google', NULL, 'Notepad', NULL, 'Calculator', NULL, 'Media Player', NULL, 'b', '25', '0', '60', '2026-05-01 21:20:17'),
('297', '22', 'Apa yang dimaksud dengan prosesor dalam sebuah komputer?', NULL, 'Komponen yang berfungsi sebagai otak komputer untuk mengolah data dan menjalankan instruksi', NULL, 'Perangkat untuk menyimpan data permanen', NULL, 'Perangkat untuk menampilkan gambar ke monitor', NULL, 'Komponen untuk menghubungkan komputer ke internet', NULL, 'Perangkat untuk mencetak dokumen', NULL, 'a', '10', '0', '60', '2026-06-10 23:43:24'),
('298', '22', 'Fungsi utama prosesor pada sistem komputer adalah...', NULL, 'Menyimpan file pengguna', NULL, 'Mengolah data dan mengeksekusi instruksi program', NULL, 'Menampilkan hasil pengolahan data ke layar', NULL, 'Menghubungkan komputer dengan jaringan', NULL, 'Mencadangkan data secara otomatis', NULL, 'b', '10', '0', '60', '2026-06-10 23:43:24'),
('299', '22', 'Kecepatan kerja sebuah prosesor umumnya diukur dalam satuan...', NULL, 'Byte', NULL, 'Pixel', NULL, 'Volt', NULL, 'Gigahertz (GHz)', NULL, 'Inci', NULL, 'd', '10', '0', '45', '2026-06-10 23:43:24'),
('300', '22', 'Bagian prosesor yang bertugas melakukan perhitungan aritmatika dan operasi logika disebut...', NULL, 'RAM', NULL, 'ALU (Arithmetic Logic Unit)', NULL, 'Cache', NULL, 'Motherboard', NULL, 'Power Supply', NULL, 'b', '10', '0', '60', '2026-06-10 23:43:24'),
('301', '22', 'Semakin tinggi jumlah core pada sebuah prosesor, maka umumnya...', NULL, 'Kapasitas hard disk bertambah', NULL, 'Ukuran monitor menjadi lebih besar', NULL, 'Kemampuan menjalankan banyak proses secara bersamaan meningkat', NULL, 'Kecepatan internet menjadi lebih tinggi', NULL, 'Konsumsi listrik komputer menjadi nol', NULL, 'c', '10', '0', '75', '2026-06-10 23:43:24'),
('302', '16', 'apakah kamu sudah makan', 'soal_e49761db18cd91b9.png', 'aaa', NULL, 'bbb', NULL, 'ccc', NULL, 'ddd', NULL, 'eee', NULL, 'a', '10', '', '0', '2026-06-11 20:56:43');

-- --------------------------------------------------------
-- Table structure for `ujian`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ujian`;
CREATE TABLE `ujian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `judul_ujian` varchar(255) NOT NULL,
  `deskripsi` text,
  `status` enum('aktif','nonaktif') DEFAULT 'nonaktif',
  `tgl_dibuat` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `kode_ujian` varchar(20) DEFAULT NULL,
  `allow_ip` text,
  `enable_browser_lock` enum('ya','tidak') DEFAULT 'tidak',
  `max_violations` int DEFAULT '10',
  `enable_device_check` enum('ya','tidak') DEFAULT 'tidak',
  `waktu_tersedia` int DEFAULT '0',
  `acak_soal` enum('ya','tidak') DEFAULT 'tidak',
  `acak_opsi` enum('ya','tidak') DEFAULT 'tidak',
  `tampilkan_review` enum('ya','tidak') DEFAULT 'tidak',
  `tampilkan_skor` enum('ya','tidak') DEFAULT 'ya',
  `tanggal_mulai` datetime DEFAULT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `durasi_per_soal` int DEFAULT '0',
  `tampil_hasil_langsung` enum('ya','tidak') DEFAULT 'ya',
  PRIMARY KEY (`id`),
  KEY `idx_ujian_status` (`status`,`tgl_dibuat` DESC)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `ujian` — 2 rows
INSERT INTO `ujian` (`id`, `judul_ujian`, `deskripsi`, `status`, `tgl_dibuat`, `updated_at`, `kode_ujian`, `allow_ip`, `enable_browser_lock`, `max_violations`, `enable_device_check`, `waktu_tersedia`, `acak_soal`, `acak_opsi`, `tampilkan_review`, `tampilkan_skor`, `tanggal_mulai`, `tanggal_selesai`, `durasi_per_soal`, `tampil_hasil_langsung`) VALUES
('16', 'test Soal 1', 'Test Soal 1', 'aktif', '2026-05-01 22:16:36', '2026-05-13 01:42:32', '12345', NULL, 'ya', '10', 'tidak', '45', 'tidak', 'tidak', 'ya', 'ya', NULL, NULL, '0', 'ya'),
('22', 'Test Soal 2', '', 'aktif', '2026-06-10 23:40:11', '2026-06-10 23:40:11', '12345', NULL, 'ya', '10', 'tidak', '10', 'ya', 'tidak', 'ya', 'ya', NULL, NULL, '0', 'ya');

-- --------------------------------------------------------
-- Table structure for `ujian_kelas`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ujian_kelas`;
CREATE TABLE `ujian_kelas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ujian` int NOT NULL,
  `id_kelas` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ujian_kelas` (`id_ujian`,`id_kelas`),
  KEY `idx_ujian_kelas_ujian` (`id_ujian`),
  KEY `idx_ujian_kelas_kelas` (`id_kelas`),
  CONSTRAINT `ujian_kelas_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ujian_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


SET FOREIGN_KEY_CHECKS = 1;
-- Backup completed: 2026-06-11 21:23:19
