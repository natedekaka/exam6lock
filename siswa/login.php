<?php
session_start();

require_once '../config/security_headers.php';

function checkRateLimit($identifier, $maxAttempts = 5, $windowSeconds = 900) {
    $rateFile = sys_get_temp_dir() . '/rate_login_' . md5($identifier) . '.json';
    $attempts = [];
    if (file_exists($rateFile)) {
        $data = json_decode(file_get_contents($rateFile), true);
        if ($data) $attempts = $data;
    }
    $now = time();
    $attempts = array_filter($attempts, fn($t) => ($now - $t) < $windowSeconds);
    if (count($attempts) >= $maxAttempts) {
        $oldestAttempt = min($attempts);
        $retryAfter = $windowSeconds - ($now - $oldestAttempt);
        return ['allowed' => false, 'retry_after' => $retryAfter];
    }
    return ['allowed' => true, 'attempts_left' => $maxAttempts - count($attempts)];
}

function recordLoginAttempt($identifier) {
    $rateFile = sys_get_temp_dir() . '/rate_login_' . md5($identifier) . '.json';
    $attempts = [];
    if (file_exists($rateFile)) {
        $data = json_decode(file_get_contents($rateFile), true);
        if ($data) $attempts = $data;
    }
    $attempts[] = time();
    file_put_contents($rateFile, json_encode($attempts));
}

function clearRateLimit($identifier) {
    $rateFile = sys_get_temp_dir() . '/rate_login_' . md5($identifier) . '.json';
    if (file_exists($rateFile)) {
        unlink($rateFile);
    }
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/log_helper.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';
$message_type = '';

// Handle redirect after login
$redirect = '';
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $r = $_GET['redirect'];
    // Only allow relative paths (no external URLs)
    if (strpos($r, 'http') !== 0 && strpos($r, '//') !== 0) {
        $redirect = $r;
    }
}

if (isset($_SESSION['siswa_id'])) {
    header('Location: ' . ($redirect ?: 'dashboard.php'));
    exit;
}

if (isset($_COOKIE['student_remember'])) {
    $token = $_COOKIE['student_remember'];
    $stmt = $conn->prepare("SELECT * FROM siswa WHERE remember_token = ? AND is_active = 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($siswa = $result->fetch_assoc()) {
        $_SESSION['siswa_id'] = $siswa['id'];
        $_SESSION['siswa_nis'] = $siswa['nis'];
        $_SESSION['siswa_nama'] = $siswa['nama_lengkap'];
        $_SESSION['siswa_kelas'] = $siswa['kelas'];
        $_SESSION['siswa_jurusan_id'] = $siswa['jurusan_id'];
        header('Location: ' . ($redirect ?: 'dashboard.php'));
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = "siswa_{$nis}_{$clientIP}";
    $rateCheck = checkRateLimit($rateKey);

    if (!$rateCheck['allowed']) {
        $message = "Terlalu banyak percobaan login. Coba lagi dalam " . ceil($rateCheck['retry_after'] / 60) . " menit.";
        $message_type = 'danger';
    } elseif ($nis && $password) {
        $stmt = $conn->prepare("SELECT * FROM siswa WHERE nis = ?");
        $stmt->bind_param("s", $nis);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($siswa = $result->fetch_assoc()) {
            if ($siswa['is_active'] == 0) {
                $message = 'Akun Anda dinonaktifkan. Hubungi administrator.';
                $message_type = 'danger';
            } elseif (password_verify($password, $siswa['password'])) {
                session_regenerate_id(true);
                
                $_SESSION['siswa_id'] = $siswa['id'];
                $_SESSION['siswa_nis'] = $siswa['nis'];
                $_SESSION['siswa_nama'] = $siswa['nama_lengkap'];
                $_SESSION['siswa_kelas'] = $siswa['kelas'];
                $_SESSION['siswa_jurusan_id'] = $siswa['jurusan_id'];

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $stmt2 = $conn->prepare("UPDATE siswa SET remember_token = ? WHERE id = ?");
                    $stmt2->bind_param("si", $token, $siswa['id']);
                    $stmt2->execute();
                    $stmt2->close();
                    setcookie('student_remember', $token, time() + 86400 * 30, '/', '', false, true);
                }

                $stmt->close();

                clearRateLimit($rateKey);

                header('Location: ' . ($redirect ?: 'dashboard.php'));
                exit;
            } else {
                $message = 'Password salah!';
                $message_type = 'danger';
                recordLoginAttempt($rateKey);
                logSecurity('Student login failed - wrong password', ['nis' => $nis]);
            }
        } else {
            $message = 'NIS tidak ditemukan!';
            $message_type = 'danger';
            recordLoginAttempt($rateKey);
            logSecurity('Student login failed - NIS not found', ['nis' => $nis]);
        }
        $stmt->close();
    } else {
        $message = 'Mohon isi NIS dan password!';
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/login.css" rel="stylesheet">
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 45px 40px;
            max-width: 420px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .school-logo {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        .school-logo:hover { transform: scale(1.05); }
        .school-logo i { font-size: 2.5rem; color: white; }
        .school-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; padding: 8px; }
        .form-control {
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
            padding: 14px 16px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-bottom-color: <?= $sekolah['warna_primer'] ?>;
            background: #fff;
            box-shadow: none;
        }
        .input-group-text {
            background: #f8f9fa;
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-right: none;
            border-radius: 0;
            color: #6c757d;
        }
        .input-group .form-control {
            border-left: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
        }
        .input-group .form-control:focus {
            border-bottom-color: <?= $sekolah['warna_primer'] ?>;
            box-shadow: none;
        }
        .form-floating > .form-control {
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
            background: #f8f9fa;
            padding: 14px 16px;
        }
        .form-floating > .form-control:focus {
            border-bottom-color: <?= $sekolah['warna_primer'] ?>;
            background: #fff;
            box-shadow: none;
        }
        .form-floating > label {
            color: #6c757d;
            padding: 14px 16px;
        }
        .btn-login {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px <?= $sekolah['warna_primer'] ?>40;
        }
        .password-toggle {
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s ease;
        }
        .password-toggle:hover { color: <?= $sekolah['warna_primer'] ?>; }
        .form-check-input:checked {
            background-color: <?= $sekolah['warna_primer'] ?>;
            border-color: <?= $sekolah['warna_primer'] ?>;
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 4px <?= $sekolah['warna_primer'] ?>20;
        }
        .alert { border-radius: 12px; border: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="school-logo">
                <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                    <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill"></i>
                <?php endif; ?>
            </div>
            <h4 class="fw-bold" style="color: <?= $sekolah['warna_primer'] ?>"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></h4>
            <p class="text-muted mb-0">Login Siswa</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <div class="form-floating mb-3">
                <input type="text" name="nis" class="form-control" id="nis" placeholder="NIS" required autofocus>
                <label for="nis"><i class="bi bi-person-badge me-2"></i>NIS</label>
            </div>
            <div class="form-floating mb-3 position-relative">
                <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                <span class="position-absolute top-50 end-0 translate-middle-y me-3 password-toggle" onclick="togglePassword()">
                    <i class="bi bi-eye" id="eye-icon"></i>
                </span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label text-muted" for="remember">Ingat saya</label>
                </div>
                <a href="register.php" class="text-decoration-none" style="color: <?= $sekolah['warna_primer'] ?>">Belum daftar?</a>
            </div>
            <button type="submit" class="btn btn-login text-white w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="../index.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i>Kembali ke Beranda</a>
        </div>
        <div class="text-center mt-4">
            <p class="text-muted small mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($sekolah['nama_sekolah']) ?></p>
        </div>
    </div>
    <script>
        function togglePassword() {
            const p = document.getElementById('password');
            const i = document.getElementById('eye-icon');
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
