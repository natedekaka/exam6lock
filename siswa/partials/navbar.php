<?php
if (!isset($active_page)) $active_page = basename($_SERVER['PHP_SELF']);
$siswa_nama = htmlspecialchars($_SESSION['siswa_nama'] ?? 'Siswa');
$avatar_char = strtoupper(substr($siswa_nama, 0, 1));
?>
<link rel="stylesheet" href="assets/css/siswa.css">

<nav class="navbar-student">
	<div class="container">
		<a class="navbar-brand" href="dashboard.php">
			<?php if (!empty($sekolah['logo']) && file_exists('../uploads/' . $sekolah['logo'])): ?>
				<img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
			<?php else: ?>
				<div class="avatar-circle" style="background:linear-gradient(135deg,<?= $sekolah['warna_primer'] ?>,<?= $sekolah['warna_sekunder'] ?>);">S</div>
			<?php endif; ?>
			<div class="brand-text">
				<span class="school-label"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></span>
				<span>Sistem Ujian Online</span>
			</div>
		</a>

		<div class="d-flex align-items-center gap-2">
			<!-- User dropdown (always visible) -->
			<div class="dropdown user-dropdown">
				<button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
					<div class="avatar-circle"><?= $avatar_char ?></div>
					<span class="d-none d-md-inline"><?= $siswa_nama ?></span>
					<i class="bi bi-chevron-down ms-1 d-none d-md-inline" style="font-size:0.75rem;"></i>
				</button>
				<ul class="dropdown-menu dropdown-menu-end shadow">
					<li><a class="dropdown-item" href="profil.php"><i class="bi bi-person"></i> Profil</a></li>
					<li><a class="dropdown-item" href="ganti_password.php"><i class="bi bi-key"></i> Ganti Password</a></li>
					<li><hr class="dropdown-divider"></li>
					<li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
				</ul>
			</div>

			<!-- Hamburger toggle for nav links -->
			<button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="collapse" data-bs-target="#siswaNavbar" aria-controls="siswaNavbar" aria-expanded="false">
				<i class="bi bi-list" style="font-size:1.5rem;color:var(--dark);"></i>
			</button>
		</div>
	</div>

	<!-- Collapsible nav links -->
	<div class="collapse" id="siswaNavbar">
		<div class="container pb-2">
			<ul class="list-unstyled mb-0 d-flex flex-column gap-1">
				<li>
					<a class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded <?= $active_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
						<i class="bi bi-house-door"></i> Beranda
					</a>
				</li>
				<li>
					<a class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded" href="../index.php">
						<i class="bi bi-pencil-square"></i> Ujian
					</a>
				</li>
				<li>
					<a class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded" href="../riwayat.php?nis=<?= urlencode($_SESSION['siswa_nis'] ?? '') ?>">
						<i class="bi bi-bar-chart"></i> Nilai
					</a>
				</li>
				<li>
					<a class="nav-link d-flex align-items-center gap-2 px-3 py-2 rounded <?= $active_page === 'pengumuman.php' ? 'active' : '' ?>" href="pengumuman.php">
						<i class="bi bi-megaphone"></i> Pengumuman
					</a>
				</li>
			</ul>
		</div>
	</div>
</nav>
