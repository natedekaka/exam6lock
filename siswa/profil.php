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

$siswa_id = $_SESSION['siswa_id'];

$stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result();
$siswa = $result->fetch_assoc();
$stmt->close();

if (!$siswa) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

$jurusan_list = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profil'])) {
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        $kelas = trim($_POST['kelas']);
        $jurusan_id = !empty($_POST['jurusan_id']) ? (int)$_POST['jurusan_id'] : null;

        if (empty($nama_lengkap) || empty($kelas)) {
            $message = 'Nama dan kelas wajib diisi!';
            $message_type = 'danger';
        } else {
            $foto = $siswa['foto'];
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $foto_name = 'siswa_' . $siswa_id . '_' . time() . '.' . $ext;
                    $upload_path = '../uploads/' . $foto_name;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                        if ($siswa['foto'] && file_exists('../uploads/' . $siswa['foto'])) {
                            unlink('../uploads/' . $siswa['foto']);
                        }
                        $foto = $foto_name;
                    } else {
                        $message = 'Gagal upload foto!';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Format foto tidak didukung!';
                    $message_type = 'danger';
                }
            }

            if (empty($message)) {
                $stmt = $conn->prepare("UPDATE siswa SET nama_lengkap = ?, email = ?, kelas = ?, jurusan_id = ?, foto = ? WHERE id = ?");
                $stmt->bind_param("sssisi", $nama_lengkap, $email, $kelas, $jurusan_id, $foto, $siswa_id);
                if ($stmt->execute()) {
                    $_SESSION['siswa_nama'] = $nama_lengkap;
                    $_SESSION['siswa_kelas'] = $kelas;
                    $message = 'Profil berhasil diperbarui!';
                    $message_type = 'success';
                    $siswa['nama_lengkap'] = $nama_lengkap;
                    $siswa['email'] = $email;
                    $siswa['kelas'] = $kelas;
                    $siswa['jurusan_id'] = $jurusan_id;
                    $siswa['foto'] = $foto;
                } else {
                    $message = 'Gagal memperbarui profil!';
                    $message_type = 'danger';
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['update_password'])) {
        $password_lama = $_POST['password_lama'];
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];

        if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
            $message = 'Mohon lengkapi semua field password!';
            $message_type = 'danger';
        } elseif (!password_verify($password_lama, $siswa['password'])) {
            $message = 'Password lama salah!';
            $message_type = 'danger';
        } elseif ($password_baru !== $konfirmasi_password) {
            $message = 'Password baru tidak cocok!';
            $message_type = 'danger';
        } elseif (strlen($password_baru) < 6) {
            $message = 'Password minimal 6 karakter!';
            $message_type = 'danger';
        } else {
            $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE siswa SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $siswa_id);
            if ($stmt->execute()) {
                $message = 'Password berhasil diubah!';
                $message_type = 'success';
            } else {
                $message = 'Gagal mengubah password!';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 30px 0;
        }
        .profile-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .profile-header {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            margin-bottom: 15px;
        }
        .profile-body { padding: 30px; }
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
        .btn-primary {
            background: <?= $sekolah['warna_primer'] ?>;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: <?= $sekolah['warna_sekunder'] ?>;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px <?= $sekolah['warna_primer'] ?>40;
        }
        .alert { border-radius: 12px; }
        .nav-link { color: <?= $sekolah['warna_primer'] ?>; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="profile-header">
                        <?php if ($siswa['foto'] && file_exists('../uploads/' . $siswa['foto'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($siswa['foto']) ?>" alt="Foto" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar d-inline-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.2);">
                                <i class="bi bi-person-fill" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($siswa['nama_lengkap']) ?></h4>
                        <p class="mb-0 opacity-75">NIS: <?= htmlspecialchars($siswa['nis']) ?></p>
                    </div>
                    <div class="profile-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i><?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">
                                    <i class="bi bi-pencil me-1"></i>Edit Profil
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                    <i class="bi bi-lock me-1"></i>Ubah Password
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="profileTabContent">
                            <div class="tab-pane fade show active" id="edit" role="tabpanel">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">NIS</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($siswa['nis']) ?>" disabled>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                            <input type="text" name="nama_lengkap" class="form-control" required value="<?= htmlspecialchars($siswa['nama_lengkap']) ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                            <select name="kelas" class="form-select" required>
                                                <option value="">Pilih Kelas</option>
                                                <?php
                                                $kelas_list->data_seek(0);
                                                while ($k = $kelas_list->fetch_assoc()):
                                                ?>
                                                <option value="<?= htmlspecialchars($k['nama_kelas']) ?>" <?= $siswa['kelas'] === $k['nama_kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Jurusan</label>
                                            <select name="jurusan_id" class="form-select">
                                                <option value="">Pilih Jurusan</option>
                                                <?php
                                                $jurusan_list->data_seek(0);
                                                while ($j = $jurusan_list->fetch_assoc()):
                                                ?>
                                                <option value="<?= $j['id'] ?>" <?= $siswa['jurusan_id'] == $j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['nama_jurusan']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($siswa['email'] ?? '') ?>">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Foto</label>
                                        <input type="file" name="foto" class="form-control" accept="image/*">
                                        <small class="text-muted">Format: jpg, jpeg, png, gif, webp</small>
                                    </div>
                                    <button type="submit" name="update_profil" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Simpan Profil
                                    </button>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="password" role="tabpanel">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                        <input type="password" name="password_lama" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                        <input type="password" name="password_baru" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                        <input type="password" name="konfirmasi_password" class="form-control" required>
                                    </div>
                                    <button type="submit" name="update_password" class="btn btn-primary">
                                        <i class="bi bi-key me-1"></i>Ubah Password
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house-door me-1"></i>Kembali ke Beranda
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
