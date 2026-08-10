<?php
// admin/profil_sekolah.php - Pengaturan Profil Sekolah

require_once "../config/security_headers.php";

session_start();


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
    </style>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li>Data Master</li>
                <li class="active">Profil Sekolah</li>
            </ul>
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
