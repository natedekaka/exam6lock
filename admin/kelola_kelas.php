<?php
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_jurusan'])) {
    $nama_jurusan = trim($_POST['nama_jurusan']);
    $kode = trim($_POST['kode']);
    $edit_id = isset($_POST['edit_jurusan_id']) ? (int)$_POST['edit_jurusan_id'] : 0;

    if (empty($nama_jurusan)) {
        $message = 'Nama jurusan wajib diisi!';
        $message_type = 'danger';
    } else {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE jurusan SET nama_jurusan = ?, kode = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nama_jurusan, $kode, $edit_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO jurusan (nama_jurusan, kode) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama_jurusan, $kode);
        }
        if ($stmt->execute()) {
            $message = 'Jurusan berhasil disimpan!';
            $message_type = 'success';
        } else {
            $message = 'Gagal menyimpan jurusan!';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['hapus_jurusan'])) {
    $id = (int)$_GET['hapus_jurusan'];
    $stmt = $conn->prepare("DELETE FROM jurusan WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $conn->query("UPDATE kelas SET jurusan_id = NULL WHERE jurusan_id = $id");
        $message = 'Jurusan berhasil dihapus!';
        $message_type = 'success';
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_kelas'])) {
    $nama_kelas = trim($_POST['nama_kelas']);
    $jurusan_id = !empty($_POST['jurusan_id']) ? (int)$_POST['jurusan_id'] : null;
    $tingkat = trim($_POST['tingkat']);
    $edit_id = isset($_POST['edit_kelas_id']) ? (int)$_POST['edit_kelas_id'] : 0;

    if (empty($nama_kelas)) {
        $message = 'Nama kelas wajib diisi!';
        $message_type = 'danger';
    } else {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE kelas SET nama_kelas = ?, jurusan_id = ?, tingkat = ? WHERE id = ?");
            $stmt->bind_param("sisi", $nama_kelas, $jurusan_id, $tingkat, $edit_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, jurusan_id, tingkat) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $nama_kelas, $jurusan_id, $tingkat);
        }
        if ($stmt->execute()) {
            $message = 'Kelas berhasil disimpan!';
            $message_type = 'success';
        } else {
            $message = 'Gagal menyimpan kelas!';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['hapus_kelas'])) {
    $id = (int)$_GET['hapus_kelas'];
    $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Kelas berhasil dihapus!';
        $message_type = 'success';
    }
    $stmt->close();
}

$jurusan_result = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$kelas_result = $conn->query("SELECT k.*, j.nama_jurusan FROM kelas k LEFT JOIN jurusan j ON k.jurusan_id = j.id ORDER BY k.nama_kelas ASC");

$edit_jurusan = null;
if (isset($_GET['edit_jurusan'])) {
    $id = (int)$_GET['edit_jurusan'];
    $stmt = $conn->prepare("SELECT * FROM jurusan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_jurusan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$edit_kelas = null;
if (isset($_GET['edit_kelas'])) {
    $id = (int)$_GET['edit_kelas'];
    $stmt = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_kelas = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Kelola Kelas & Jurusan - Admin</title>
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
            transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h5 { color: #fff; font-weight: 600; margin: 0; }
        .school-logo {
            width: 55px; height: 55px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
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
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { background: #fff; border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; font-weight: 600; color: var(--dark); }
        .card-body { padding: 1.5rem; }
        .form-control, .form-select { border: 1px solid var(--border); border-radius: 8px; padding: 0.625rem 0.875rem; font-size: 0.9375rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .btn { border-radius: 8px; padding: 0.625rem 1.25rem; font-weight: 500; transition: all 0.2s ease; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .table { margin-bottom: 0; }
        .table thead th { background: #f8fafc; border-bottom: 2px solid var(--border); color: var(--secondary); font-weight: 600; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 1rem; white-space: nowrap; }
        .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border); }
        .table tbody tr:hover { background: #f8fafc; }
        .badge { font-weight: 500; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; }
        .mobile-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1001; background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 0.625rem; font-size: 1.25rem; }
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .animate-fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 4rem 1rem 1rem; }
            .mobile-toggle { display: flex; }
            .overlay.show { display: block; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <div class="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar">
        <div class="sidebar-brand text-center">
            <div class="school-logo mb-2">
                <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                    <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:50%;">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill" style="font-size:1.8rem;"></i>
                <?php endif; ?>
            </div>
            <div class="text-white fw-bold" style="font-size:0.85rem;"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></div>
            <h5 class="mt-2"><i class="bi bi-gear me-1"></i>Admin Panel</h5>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
            <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
            <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
            <a href="analytics.php"><i class="bi bi-graph-up"></i> Analytics</a>
            <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
            <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
            <a href="kelola_kelas.php" class="active"><i class="bi bi-diagram-3-fill"></i> Kelola Kelas</a>
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
        <div class="container-fluid px-4">
            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="page-header animate-fade-in">
                <h3><i class="bi bi-diagram-3-fill me-2"></i>Kelola Kelas & Jurusan</h3>
            </div>

            <div class="row">
                <!-- Jurusan Section -->
                <div class="col-lg-6">
                    <div class="card animate-fade-in">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-bookmark-fill me-2"></i><?= $edit_jurusan ? 'Edit Jurusan' : 'Tambah Jurusan' ?></span>
                            <?php if ($edit_jurusan): ?>
                            <a href="kelola_kelas.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i> Batal</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($edit_jurusan): ?>
                                <input type="hidden" name="edit_jurusan_id" value="<?= $edit_jurusan['id'] ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nama Jurusan <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_jurusan" class="form-control" required value="<?= $edit_jurusan ? htmlspecialchars($edit_jurusan['nama_jurusan']) : '' ?>" placeholder="Contoh: Teknik Informatika">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Kode</label>
                                    <input type="text" name="kode" class="form-control" value="<?= $edit_jurusan ? htmlspecialchars($edit_jurusan['kode'] ?? '') : '' ?>" placeholder="Contoh: TI">
                                </div>
                                <button type="submit" name="simpan_jurusan" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i> <?= $edit_jurusan ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card animate-fade-in">
                        <div class="card-header"><span><i class="bi bi-list me-2"></i>Daftar Jurusan</span></div>
                        <div class="card-body p-0">
                            <?php if ($jurusan_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Jurusan</th>
                                            <th>Kode</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; $jurusan_result->data_seek(0); while ($j = $jurusan_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($j['nama_jurusan']) ?></td>
                                            <td><?= htmlspecialchars($j['kode'] ?? '-') ?></td>
                                            <td class="text-center">
                                                <a href="?edit_jurusan=<?= $j['id'] ?>" class="btn btn-sm btn-warning me-1" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?hapus_jurusan=<?= $j['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus jurusan ini?')" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">Belum ada jurusan</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Kelas Section -->
                <div class="col-lg-6">
                    <div class="card animate-fade-in">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-collection-fill me-2"></i><?= $edit_kelas ? 'Edit Kelas' : 'Tambah Kelas' ?></span>
                            <?php if ($edit_kelas): ?>
                            <a href="kelola_kelas.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i> Batal</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($edit_kelas): ?>
                                <input type="hidden" name="edit_kelas_id" value="<?= $edit_kelas['id'] ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nama Kelas <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_kelas" class="form-control" required value="<?= $edit_kelas ? htmlspecialchars($edit_kelas['nama_kelas']) : '' ?>" placeholder="Contoh: X-1">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tingkat</label>
                                    <select name="tingkat" class="form-select">
                                        <option value="">Pilih Tingkat</option>
                                        <?php foreach (['X', 'XI', 'XII'] as $t): ?>
                                        <option value="<?= $t ?>" <?= $edit_kelas && $edit_kelas['tingkat'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Jurusan</label>
                                    <select name="jurusan_id" class="form-select">
                                        <option value="">Pilih Jurusan</option>
                                        <?php
                                        $jurusan_opt = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
                                        while ($j_opt = $jurusan_opt->fetch_assoc()):
                                        ?>
                                        <option value="<?= $j_opt['id'] ?>" <?= $edit_kelas && $edit_kelas['jurusan_id'] == $j_opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j_opt['nama_jurusan']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <button type="submit" name="simpan_kelas" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i> <?= $edit_kelas ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card animate-fade-in">
                        <div class="card-header"><span><i class="bi bi-list me-2"></i>Daftar Kelas</span></div>
                        <div class="card-body p-0">
                            <?php if ($kelas_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Kelas</th>
                                            <th>Tingkat</th>
                                            <th>Jurusan</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; $kelas_result->data_seek(0); while ($k = $kelas_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($k['nama_kelas']) ?></td>
                                            <td><?= htmlspecialchars($k['tingkat'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($k['nama_jurusan'] ?? '-') ?></td>
                                            <td class="text-center">
                                                <a href="?edit_kelas=<?= $k['id'] ?>" class="btn btn-sm btn-warning me-1" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?hapus_kelas=<?= $k['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus kelas ini?')" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">Belum ada kelas</div>
                            <?php endif; ?>
                        </div>
                    </div>
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
        document.querySelectorAll('.sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                    document.querySelector('.overlay').classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
