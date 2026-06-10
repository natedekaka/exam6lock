<?php

session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Mohon isi semua field!';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Password baru dan konfirmasi tidak cocok!';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password baru minimal 6 karakter!';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($current_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET password = ?, password_change_required = 0 WHERE id = ?");
            $stmt->bind_param("si", $hashed, $_SESSION['admin_id']);
            if ($stmt->execute()) {
                unset($_SESSION['password_change_required']);
                $message = 'Password berhasil diubah!';
                $message_type = 'success';
            } else {
                $message = 'Gagal mengubah password.';
                $message_type = 'danger';
            }
            $stmt->close();
        } else {
            $message = 'Password saat ini salah!';
            $message_type = 'danger';
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        .password-strength { height: 4px; border-radius: 4px; transition: all 0.3s; }
    </style>
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li class="active">Ganti Password</li>
            </ul>
            <h3><i class="bi bi-key-fill me-2"></i>Ganti Password</h3>
        </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($message_type === 'success'): ?>
            <div class="alert alert-success animate-fade-in" role="alert">
                <i class="bi bi-check-circle me-2"></i>Password berhasil diubah. <a href="index.php" class="alert-link">Kembali ke Dashboard</a>
            </div>
            <?php endif; ?>

            <div class="card animate-fade-in">
                <div class="card-header">
                    <i class="bi bi-lock me-2"></i>Form Ganti Password
                </div>
                <div class="card-body">
                    <form method="POST" autocomplete="off" style="max-width: 500px;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password Saat Ini</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-check-circle-fill"></i></span>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Ubah Password
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.overlay').classList.toggle('show');
        }
    </script>
</body>
</html>
