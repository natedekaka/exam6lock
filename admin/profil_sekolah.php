<?php
// admin/profil_sekolah.php - Pengaturan Profil Sekolah

session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_profil'])) {
    $nama_sekolah = trim($_POST['nama_sekolah']);
    $warna_primer = trim($_POST['warna_primer']);
    $warna_sekunder = trim($_POST['warna_sekunder']);
    $tampilkan_riwayat = isset($_POST['tampilkan_riwayat']) ? 'ya' : 'tidak';
    $logo = $sekolah['logo'];
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $filename = 'logo_' . time() . '.' . $ext;
            $target = '../uploads/' . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])) {
                    unlink('../uploads/' . $sekolah['logo']);
                }
                $logo = $filename;
            }
        }
    }
    
    if (updateKonfigurasiSekolah($conn, $nama_sekolah, $logo, $warna_primer, $warna_sekunder, $tampilkan_riwayat)) {
        $message = 'Profil sekolah berhasil diperbarui!';
        $message_type = 'success';
        $sekolah = getKonfigurasiSekolah($conn);
    } else {
        $message = 'Gagal menyimpan perubahan.';
        $message_type = 'danger';
    }
}

$sekolah = getKonfigurasiSekolah($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Sekolah - Admin</title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 260px;
        }
        
        * { font-family: 'Inter', sans-serif; }
        
        body { background-color: #f1f5f9; min-height: 100vh; }
        
        .sidebar { 
            width: var(--sidebar-width); 
            min-height: 100vh; 
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .sidebar-brand { 
            padding: 1.5rem; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            text-align: center;
        }
        
        .sidebar-brand h5 { color: #fff; font-weight: 600; margin: 0; }
        
        .school-logo {
            width: 55px;
            height: 55px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .sidebar a { 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            padding: 0.875rem 1.5rem; 
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            font-size: 0.9375rem;
        }
        
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar a.active { background: rgba(79, 70, 229, 0.2); color: #fff; border-left-color: var(--primary); }
        
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; transition: margin-left 0.3s ease; }
        
        .page-header {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .page-header h3 { margin: 0; font-weight: 600; color: var(--dark); }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-header { background: #fff; border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; font-weight: 600; }
        
        .form-control, .form-select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
        }
        
        .logo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            overflow: hidden;
            border: 3px solid var(--border);
        }
        
        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid var(--border);
        }
        
        .animate-fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: #1e293b;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 1.2rem;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: flex;
            }

            .overlay.show {
                display: block;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 4rem 1rem 1rem;
            }

            .page-header {
                padding: 1rem;
                flex-direction: column;
                gap: 0.75rem;
            }

            .page-header h3 {
                font-size: 1.1rem;
            }

            .logo-preview {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 767.98px) {
            .card-body {
                padding: 1rem;
            }

            .col-md-4, .col-md-8 {
                width: 100%;
                margin-bottom: 1rem;
            }

            .logo-preview {
                width: 90px;
                height: 90px;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .form-control, .form-select {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .card {
                border-radius: 8px;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .main-content {
                padding: 3.5rem 0.5rem 1rem;
            }

            .page-header {
                padding: 0.75rem;
            }

            .page-header h3 {
                font-size: 1rem;
            }

            .logo-preview {
                width: 80px;
                height: 80px;
            }

            .mobile-toggle {
                padding: 8px 12px;
                font-size: 1rem;
                top: 0.5rem;
                left: 0.5rem;
            }

            .mobile-toggle span {
                display: none;
            }
            
            .sidebar-brand {
                padding: 1rem;
            }
            
            .sidebar-menu a {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="school-logo">
                <i class="bi bi-mortarboard-fill" style="font-size: 1.8rem;"></i>
            </div>
            <div class="text-white fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></div>
            <h5 class="mt-2"><i class="bi bi-gear me-1"></i>Admin Panel</h5>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
            <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
            <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
            <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
            <a href="profil_sekolah.php" class="active"><i class="bi bi-building"></i> Profil Sekolah</a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
            <a href="audit_log.php"><i class="bi bi-journal-text"></i> Audit Log</a>
            <?php endif; ?>
            <a href="pengumuman.php"><i class="bi bi-megaphone-fill"></i> Pengumuman</a>
            <a href="izin_remedi.php"><i class="bi bi-arrow-repeat"></i> Izin Remedi</a>
            <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
            <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header animate-fade-in">
            <h3><i class="bi bi-building me-2"></i>Profil Sekolah</h3>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-header">
                <i class="bi bi-pencil-square me-2"></i>Edit Profil Sekolah
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <label class="form-label fw-semibold">Logo Sekolah</label>
                            <div class="logo-preview mb-3">
                                <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                                    <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
                                <?php else: ?>
                                    <i class="bi bi-mortarboard-fill text-secondary" style="font-size: 3rem;"></i>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <small class="text-muted">Max 2MB (JPG, PNG, GIF, WEBP)</small>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama Sekolah</label>
                                <input type="text" name="nama_sekolah" class="form-control" 
                                       value="<?= htmlspecialchars($sekolah['nama_sekolah']) ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Warna Primer</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" name="warna_primer" class="form-control form-control-color" 
                                               value="<?= $sekolah['warna_primer'] ?>" style="width: 60px; height: 45px;">
                                        <input type="text" class="form-control" value="<?= $sekolah['warna_primer'] ?>" 
                                               id="warnaPrimerValue" readonly>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Warna Sekunder</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" name="warna_sekunder" class="form-control form-control-color" 
                                               value="<?= $sekolah['warna_sekunder'] ?>" style="width: 60px; height: 45px;">
                                        <input type="text" class="form-control" value="<?= $sekolah['warna_sekunder'] ?>" 
                                               id="warnaSekunderValue" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Preview Tampilan</label>
                                <div class="p-3 rounded" style="background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);">
                                    <div class="d-flex align-items-center gap-3 text-white">
                                        <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-mortarboard-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></div>
                                            <small>Sistem Ujian Online</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="tampilkan_riwayat" 
                                       id="tampilkanRiwayat" <?= ($sekolah['tampilkan_riwayat'] ?? 'ya') === 'ya' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="tampilkanRiwayat">
                                    Tampilkan Fitur Riwayat Nilai
                                </label>
                                <div class="text-muted small">Jika dinonaktifkan, siswa tidak dapat melihat riwayat nilai di halaman utama</div>
                            </div>
                            
                            <button type="submit" name="simpan_profil" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.overlay').classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth < 992 && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
                document.querySelector('.overlay').classList.remove('show');
            }
        });
        
        document.querySelectorAll('.sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    document.getElementById('sidebar').classList.remove('active');
                    document.querySelector('.overlay').classList.remove('show');
                }
            });
        });

        document.querySelector('input[name="warna_primer"]').addEventListener('input', function() {
            document.getElementById('warnaPrimerValue').value = this.value;
            updatePreview();
        });
        
        document.querySelector('input[name="warna_sekunder"]').addEventListener('input', function() {
            document.getElementById('warnaSekunderValue').value = this.value;
            updatePreview();
        });
        
        function updatePreview() {
            const primer = document.querySelector('input[name="warna_primer"]').value;
            const sekunder = document.querySelector('input[name="warna_sekunder"]').value;
            document.querySelector('.rounded[style*="background"]').style.background = 
                `linear-gradient(135deg, ${primer} 0%, ${sekunder} 100%)`;
        }
    </script>
</body>
</html>
