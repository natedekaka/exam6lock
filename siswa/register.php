<?php
session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';
$message_type = '';

if (isset($_SESSION['siswa_id'])) {
    header('Location: ../index.php');
    exit;
}

$jurusan_list = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    $kelas = trim($_POST['kelas']);
    $jurusan_id = !empty($_POST['jurusan_id']) ? (int)$_POST['jurusan_id'] : null;
    $email = trim($_POST['email']);

    if (empty($nis) || empty($nama_lengkap) || empty($password) || empty($konfirmasi_password) || empty($kelas)) {
        $message = 'Mohon lengkapi semua field wajib!';
        $message_type = 'danger';
    } elseif ($password !== $konfirmasi_password) {
        $message = 'Password dan konfirmasi password tidak cocok!';
        $message_type = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password minimal 6 karakter!';
        $message_type = 'danger';
    } else {
        $check = $conn->prepare("SELECT id FROM siswa WHERE nis = ?");
        $check->bind_param("s", $nis);
        $check->execute();
        $check_res = $check->get_result();

        if ($check_res->num_rows > 0) {
            $message = 'NIS sudah terdaftar!';
            $message_type = 'danger';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO siswa (nis, nama_lengkap, password, kelas, jurusan_id, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $nis, $nama_lengkap, $hashed_password, $kelas, $jurusan_id, $email);

            if ($stmt->execute()) {
                $siswa_id = $stmt->insert_id;
                $stmt->close();

                $_SESSION['siswa_id'] = $siswa_id;
                $_SESSION['siswa_nis'] = $nis;
                $_SESSION['siswa_nama'] = $nama_lengkap;
                $_SESSION['siswa_kelas'] = $kelas;
                $_SESSION['siswa_jurusan_id'] = $jurusan_id;

                header('Location: ../index.php');
                exit;
            } else {
                $message = 'Gagal mendaftar. Silakan coba lagi.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Siswa - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
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
            padding: 20px;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            padding: 45px 40px;
            max-width: 520px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .school-logo {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .school-logo i { font-size: 2rem; color: white; }
        .school-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; padding: 8px; }
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: <?= $sekolah['warna_primer'] ?>;
            box-shadow: 0 0 0 4px <?= $sekolah['warna_primer'] ?>20;
        }
        .form-label { font-weight: 500; font-size: 0.9rem; color: #444; }
        .btn-register {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px <?= $sekolah['warna_primer'] ?>40;
        }
        .password-toggle {
            cursor: pointer;
            color: #6c757d;
        }
        .password-toggle:hover { color: <?= $sekolah['warna_primer'] ?>; }
        .form-check-input:checked {
            background-color: <?= $sekolah['warna_primer'] ?>;
            border-color: <?= $sekolah['warna_primer'] ?>;
        }
        .alert { border-radius: 12px; border: none; }
        .input-group .form-control { border-right: none; }
        .input-group .input-group-text { background: white; border: 2px solid #e9ecef; border-left: none; border-radius: 0 12px 12px 0; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="text-center mb-4">
            <div class="school-logo">
                <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                    <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill"></i>
                <?php endif; ?>
            </div>
            <h4 class="fw-bold" style="color: <?= $sekolah['warna_primer'] ?>">Daftar Siswa Baru</h4>
            <p class="text-muted mb-0"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">NIS <span class="text-danger">*</span></label>
                <input type="text" name="nis" class="form-control" required placeholder="Masukkan NIS">
            </div>
            <div class="mb-3">
                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="nama_lengkap" class="form-control" required placeholder="Nama lengkap">
            </div>
            <div class="row mb-3">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                    <select name="kelas" class="form-select" required>
                        <option value="">Pilih Kelas</option>
                        <?php while ($k = $kelas_list->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($k['nama_kelas']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jurusan</label>
                    <select name="jurusan_id" class="form-select">
                        <option value="">Pilih Jurusan</option>
                        <?php while ($j = $jurusan_list->fetch_assoc()): ?>
                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jurusan']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Email (Opsional)</label>
                <input type="email" name="email" class="form-control" placeholder="contoh@email.com">
            </div>
            <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter" minlength="6">
                    <span class="input-group-text password-toggle" onclick="togglePassword('password', 'eye1')">
                        <i class="bi bi-eye" id="eye1"></i>
                    </span>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="konfirmasi_password" class="form-control" required placeholder="Ulangi password">
                    <span class="input-group-text password-toggle" onclick="togglePassword('konfirmasi_password', 'eye2')">
                        <i class="bi bi-eye" id="eye2"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-register text-white w-100">
                <i class="bi bi-person-plus me-2"></i>Daftar
            </button>
        </form>
        <div class="text-center mt-3">
            <p class="text-muted small">Sudah punya akun? <a href="login.php" style="color: <?= $sekolah['warna_primer'] ?>">Masuk</a></p>
            <a href="../index.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i>Kembali ke Beranda</a>
        </div>
    </div>
    <script>
        function togglePassword(inputId, iconId) {
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
