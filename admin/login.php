<?php
// admin/login.php - Halaman Login Admin

session_start();

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Handle forgot password request
if (isset($_GET['action']) && $_GET['action'] === 'forgot') {
    $message = 'Hubungi admin sekolah untuk mereset password Anda.';
}

// Handle remember me cookie
if (!isset($_SESSION['admin_id']) && isset($_COOKIE['admin_remember'])) {
    $token = $_COOKIE['admin_remember'];
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_nama'] = $user['nama_lengkap'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = $user['role'] ?? 'admin';
        if (isset($user['password_change_required']) && $user['password_change_required'] == 1) {
            $_SESSION['password_change_required'] = true;
            header('Location: ganti_password.php');
            exit;
        }
        header('Location: index.php');
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_nama'] = $user['nama_lengkap'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                
                if (isset($user['password_change_required']) && $user['password_change_required'] == 1) {
                    $_SESSION['password_change_required'] = true;
                    header('Location: ganti_password.php');
                    exit;
                }
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $stmt2 = $conn->prepare("UPDATE admin_users SET remember_token = ? WHERE id = ?");
                    $stmt2->bind_param("si", $token, $user['id']);
                    $stmt2->execute();
                    $stmt2->close();
                    setcookie('admin_remember', $token, time() + 86400 * 30, '/', '', false, true);
                }
                
                $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $stmt->close();
                
                require_once '../config/audit_helper.php';
                logAudit($conn, $user['id'], $username, 'LOGIN', 'ADMIN', $user['id'], 'Login berhasil');
                
                header('Location: index.php');
                exit;
            } else {
                $message = 'Password salah!';
            }
        } else {
            $message = 'Username tidak ditemukan!';
        }
        $stmt->close();
    } else {
        $message = 'Mohon isi username dan password!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Sistem Ujian Online</title>
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
        .school-logo:hover {
            transform: scale(1.05);
        }
        .school-logo i {
            font-size: 2.5rem;
            color: white;
        }
        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            padding: 8px;
        }
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
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px <?= $sekolah['warna_primer'] ?>40;
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .password-toggle {
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s ease;
        }
        .password-toggle:hover {
            color: <?= $sekolah['warna_primer'] ?>;
        }
        .form-check-input:checked {
            background-color: <?= $sekolah['warna_primer'] ?>;
            border-color: <?= $sekolah['warna_primer'] ?>;
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 4px <?= $sekolah['warna_primer'] ?>20;
        }
        .alert {
            border-radius: 12px;
            border: none;
        }
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
            <p class="text-muted mb-0">Sistem Ujian Online</p>
            <small class="text-muted">Login Admin</small>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="mt-4">
            <div class="form-floating mb-3">
                <input type="text" name="username" class="form-control" id="username" placeholder="Username" required autofocus>
                <label for="username"><i class="bi bi-person me-2"></i>Username</label>
            </div>
            
            <div class="form-floating mb-3">
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
                <a href="login.php?action=forgot" class="text-decoration-none" style="color: <?= $sekolah['warna_primer'] ?>">Lupa password?</a>
            </div>
            
            <button type="submit" class="btn btn-login text-white w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
            </button>
        </form>
        
        <div class="text-center mt-4">
            <p class="text-muted small mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($sekolah['nama_sekolah']) ?></p>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (password.type === 'password') {
                password.type = 'text';
                eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                password.type = 'password';
                eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
    
    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
