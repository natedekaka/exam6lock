<?php
if (!isset($active_page)) $active_page = basename($_SERVER['PHP_SELF']);
$is_super = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
$admin_username = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');
$admin_nama = htmlspecialchars($_SESSION['admin_nama'] ?? $admin_username);
$admin_role = isset($_SESSION['admin_role']) ? ($_SESSION['admin_role'] === 'super_admin' ? 'Super Admin' : 'Admin') : 'Admin';
$sekolah_nama = htmlspecialchars($sekolah['nama_sekolah'] ?? 'Sekolah');
$avatar_char = strtoupper(substr($admin_nama, 0, 1));
?>
<link href="assets/css/admin.css" rel="stylesheet">
<button class="mobile-toggle" onclick="toggleSidebar()">
	<i class="bi bi-list"></i>
</button>
<div class="overlay" onclick="toggleSidebar()"></div>

<div class="sidebar">
	<div class="sidebar-brand">
		<div class="school-logo">
			<?php if (!empty($sekolah['logo']) && file_exists('../uploads/' . $sekolah['logo'])): ?>
				<img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
			<?php else: ?>
				<i class="bi bi-mortarboard-fill" style="font-size:1.6rem;"></i>
			<?php endif; ?>
		</div>
		<div class="school-name"><?= $sekolah_nama ?></div>
		<h5><i class="bi bi-gear me-1"></i>Admin Panel</h5>
	</div>

	<div class="sidebar-menu-wrapper">
		<!-- Ringkasan -->
		<div class="sidebar-section-label">Ringkasan</div>
		<div class="sidebar-menu">
			<a href="index.php" class="<?= $active_page === 'index.php' ? 'active' : '' ?>">
				<i class="bi bi-speedometer2"></i> Dashboard
			</a>
		</div>

		<!-- Ujian -->
		<div class="sidebar-section-label">Ujian</div>
		<div class="sidebar-menu">
			<a href="tambah_soal.php" class="<?= $active_page === 'tambah_soal.php' ? 'active' : '' ?>">
				<i class="bi bi-question-circle"></i> Kelola Soal
			</a>
			<a href="bank_soal.php" class="<?= $active_page === 'bank_soal.php' ? 'active' : '' ?>">
				<i class="bi bi-database"></i> Bank Soal Global
			</a>
			<a href="import_soal.php" class="<?= $active_page === 'import_soal.php' ? 'active' : '' ?>">
				<i class="bi bi-upload"></i> Import Soal
			</a>
		</div>

		<!-- Nilai & Analisis -->
		<div class="sidebar-section-label">Nilai &amp; Analisis</div>
		<div class="sidebar-menu">
			<a href="rekap_nilai.php" class="<?= $active_page === 'rekap_nilai.php' ? 'active' : '' ?>">
				<i class="bi bi-bar-chart"></i> Rekap Nilai
			</a>
			<a href="analytics.php" class="<?= $active_page === 'analytics.php' ? 'active' : '' ?>">
				<i class="bi bi-graph-up"></i> Analytics
			</a>
			<a href="monitor_ujian.php" class="<?= $active_page === 'monitor_ujian.php' ? 'active' : '' ?>">
				<i class="bi bi-display"></i> Monitor Ujian
			</a>
		</div>

		<!-- Data Master -->
		<div class="sidebar-section-label">Data Master</div>
		<div class="sidebar-menu">
			<a href="kelola_kelas.php" class="<?= $active_page === 'kelola_kelas.php' ? 'active' : '' ?>">
				<i class="bi bi-diagram-3"></i> Kelola Kelas
			</a>
			<a href="kelola_siswa.php" class="<?= $active_page === 'kelola_siswa.php' ? 'active' : '' ?>">
				<i class="bi bi-people"></i> Kelola Siswa
			</a>
			<a href="profil_sekolah.php" class="<?= $active_page === 'profil_sekolah.php' ? 'active' : '' ?>">
				<i class="bi bi-building"></i> Profil Sekolah
			</a>
		</div>

		<!-- Pengaturan -->
		<div class="sidebar-section-label">Pengaturan</div>
		<div class="sidebar-menu">
			<a href="pengumuman.php" class="<?= $active_page === 'pengumuman.php' ? 'active' : '' ?>">
				<i class="bi bi-megaphone"></i> Pengumuman
			</a>
			<a href="izin_remedi.php" class="<?= $active_page === 'izin_remedi.php' ? 'active' : '' ?>">
				<i class="bi bi-arrow-repeat"></i> Izin Remedi
			</a>
			<a href="ganti_password.php" class="<?= $active_page === 'ganti_password.php' ? 'active' : '' ?>">
				<i class="bi bi-key"></i> Ganti Password
			</a>
			<?php if ($is_super): ?>
			<a href="manage_users.php" class="<?= $active_page === 'manage_users.php' ? 'active' : '' ?>">
				<i class="bi bi-shield-lock"></i> Kelola Admin
			</a>
			<a href="audit_log.php" class="<?= $active_page === 'audit_log.php' ? 'active' : '' ?>">
				<i class="bi bi-journal-text"></i> Audit Log
			</a>
			<a href="backup_restore.php" class="<?= $active_page === 'backup_restore.php' ? 'active' : '' ?>">
				<i class="bi bi-cloud-arrow-up"></i> Backup &amp; Restore
			</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="sidebar-footer">
		<div class="sidebar-user">
			<div class="sidebar-user-avatar"><?= $avatar_char ?></div>
			<div class="sidebar-user-info">
				<div class="sidebar-user-name"><?= $admin_nama ?></div>
				<div class="sidebar-user-role">
					<span class="status-dot"></span>
					<?= $admin_role ?>
				</div>
			</div>
		</div>
		<a href="logout.php" class="sidebar-logout">
			<i class="bi bi-box-arrow-right"></i> Logout
		</a>
	</div>
</div>
<script src="assets/js/admin.js" defer></script>
