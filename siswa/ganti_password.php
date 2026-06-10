<?php
session_start();
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

if (!isset($_SESSION['siswa_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

$siswa_id = $_SESSION['siswa_id'];
$stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$siswa || !$siswa['is_active']) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi)) {
        $message = 'Semua field wajib diisi.';
        $message_type = 'danger';
    } elseif ($password_baru !== $konfirmasi) {
        $message = 'Password baru dan konfirmasi tidak cocok.';
        $message_type = 'danger';
    } elseif (strlen($password_baru) < 6) {
        $message = 'Password baru minimal 6 karakter.';
        $message_type = 'danger';
    } elseif (!password_verify($password_lama, $siswa['password'])) {
        $message = 'Password lama salah.';
        $message_type = 'danger';
    } else {
        $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE siswa SET password = ?, password_change_required = 0 WHERE id = ?");
        $stmt->bind_param("si", $hash_baru, $siswa_id);
        if ($stmt->execute()) {
            $message = 'Password berhasil diubah.';
            $message_type = 'success';
            $_SESSION['password_changed'] = true;
            header('Location: ../index.php');
            exit;
        } else {
            $message = 'Gagal mengubah password.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}
$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="assets/css/siswa.css" rel="stylesheet">
    <style>
        .ganti-password-wrap {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-form {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 40px;
            max-width: 440px;
            width: 100%;
        }
        .icon-lock {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: white;
            font-size: 2rem;
        }
        .form-label { font-weight: 500; font-size: 0.9rem; color: #444; }
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: <?= $sekolah['warna_primer'] ?>;
            box-shadow: 0 0 0 4px <?= $sekolah['warna_primer'] ?>20;
        }
        .btn-submit {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px <?= $sekolah['warna_primer'] ?>40;
        }
        .alert { border-radius: 12px; border: none; }
        .input-group .form-control { border-right: none; }
        .input-group .input-group-text { background: white; border: 2px solid #e9ecef; border-left: none; border-radius: 0 12px 12px 0; }
    </style>
</head>
<body>
    <?php require 'partials/navbar.php'; ?>
    <div class="ganti-password-wrap">
        <div class="card-form">
        <div class="text-center mb-4">
            <div class="icon-lock">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h4 class="fw-bold" style="color: <?= $sekolah['warna_primer'] ?>">Ganti Password</h4>
            <p class="text-muted mb-0">Halo, <strong><?= htmlspecialchars($siswa['nama_lengkap']) ?></strong></p>
            <p class="text-muted small">(<?= htmlspecialchars($siswa['nis']) ?>)</p>
        </div>

        <?php if ($siswa['password_change_required']): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Anda harus mengganti password sebelum dapat menggunakan aplikasi.
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Password Lama</label>
                <div class="input-group">
                    <input type="password" name="password_lama" id="pwd1" class="form-control" required placeholder="Masukkan password lama">
                    <span class="input-group-text" onclick="togglePwd('pwd1','eye1')" style="cursor:pointer">
                        <i class="bi bi-eye" id="eye1"></i>
                    </span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <div class="input-group">
                    <input type="password" name="password_baru" id="pwd2" class="form-control" required placeholder="Minimal 6 karakter" minlength="6">
                    <span class="input-group-text" onclick="togglePwd('pwd2','eye2')" style="cursor:pointer">
                        <i class="bi bi-eye" id="eye2"></i>
                    </span>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Konfirmasi Password Baru</label>
                <div class="input-group">
                    <input type="password" name="konfirmasi_password" id="pwd3" class="form-control" required placeholder="Ulangi password baru">
                    <span class="input-group-text" onclick="togglePwd('pwd3','eye3')" style="cursor:pointer">
                        <i class="bi bi-eye" id="eye3"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-submit text-white w-100">
                <i class="bi bi-check-lg me-2"></i>Simpan Password Baru
            </button>
        </form>

        <?php if (!$siswa['password_change_required']): ?>
        <div class="text-center mt-3">
            <a href="dashboard.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard</a>
    </div>
    <?php endif; ?>
    </div>
</div>
    <script>
        function togglePwd(inputId, iconId) {
            const p = document.getElementById(inputId);
            const i = document.getElementById(iconId);
            if (!p) return;
            if (p.type === 'password') {
                p.type = 'text';
                i.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                p.type = 'password';
                i.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
