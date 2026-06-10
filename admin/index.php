<?php
// admin/index.php - Dashboard Admin (Manajemen Ujian)

session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/audit_helper.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';
$message_type = '';

// Cek kolom ujian — single query, array-driven
$column_flags = [
    'acak_soal'            => 'new_columns',
    'tampilkan_skor'       => 'tampilkan_skor',
    'acak_opsi'            => 'acak_opsi',
    'tampilkan_review'     => 'tampilkan_review',
    'kode_ujian'           => 'kode_ujian',
    'allow_ip'             => 'allow_ip',
    'enable_browser_lock'  => 'browser_lock',
    'enable_device_check'  => 'device_check',
    'timer_per_soal'       => 'timer_per_soal',
    'show_timer_per_soal'  => 'show_timer_per_soal',
    'tanggal_mulai'        => 'tanggal_mulai',
    'tanggal_selesai'      => 'tanggal_selesai',
    'tampil_hasil_langsung'=> 'tampil_hasil_langsung',
    'durasi_per_soal'      => 'durasi_per_soal',
];
$has = array_fill_keys(array_unique(array_values($column_flags)), false);
try {
    $existing = $conn->query("SHOW COLUMNS FROM ujian");
    if ($existing) {
        $col_names = [];
        while ($c = $existing->fetch_assoc()) $col_names[] = $c['Field'];
        $existing->free();
        foreach ($column_flags as $col => $flag) {
            if (in_array($col, $col_names)) {
                $has[$flag] = true;
            }
        }
    }
} catch (Exception $e) {
    // all remain false
}
$has_new_columns = $has['new_columns'];
$has_tampilkan_skor = $has['tampilkan_skor'];
$has_acak_opsi = $has['acak_opsi'];
$has_tampilkan_review = $has['tampilkan_review'];
$has_kode_ujian = $has['kode_ujian'];
$has_allow_ip = $has['allow_ip'];
$has_browser_lock = $has['browser_lock'];
$has_device_check = $has['device_check'];
$has_timer_per_soal = $has['timer_per_soal'];
$has_show_timer_per_soal = $has['show_timer_per_soal'];
$has_tanggal_mulai = $has['tanggal_mulai'];
$has_tanggal_selesai = $has['tanggal_selesai'];
$has_tampil_hasil_langsung = $has['tampil_hasil_langsung'];
$has_durasi_per_soal = $has['durasi_per_soal'];

if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'] === 'aktif' ? 'nonaktif' : 'aktif';
    
    $stmt = $conn->prepare("UPDATE ujian SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        $message = "Status ujian berhasil diubah!";
        $message_type = 'success';
        if (isset($redis)) $redis->delete('ujian:list_aktif');
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_ujian'])) {
    $judul = trim($_POST['judul_ujian'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = in_array($_POST['status'] ?? 'nonaktif', ['aktif', 'nonaktif']) ? $_POST['status'] : 'nonaktif';
    $waktu_tersedia = isset($_POST['waktu_tersedia']) ? (int)$_POST['waktu_tersedia'] : 0;
    
    // Validasi ketat untuk acak_soal dan tampilkan_review
    $acak_soal = 'tidak';
    if (isset($_POST['acak_soal']) && ($_POST['acak_soal'] === 'ya' || $_POST['acak_soal'] === 'tidak')) {
        $acak_soal = $_POST['acak_soal'];
    }
    
    $acak_opsi = 'tidak';
    if ($has_acak_opsi && isset($_POST['acak_opsi']) && ($_POST['acak_opsi'] === 'ya' || $_POST['acak_opsi'] === 'tidak')) {
        $acak_opsi = $_POST['acak_opsi'];
    }
    
    $tampilkan_review = 'tidak';
    if (isset($_POST['tampilkan_review']) && ($_POST['tampilkan_review'] === 'ya' || $_POST['tampilkan_review'] === 'tidak')) {
        $tampilkan_review = $_POST['tampilkan_review'];
    }
    
    $tampilkan_skor = 'ya';
    if ($has_tampilkan_skor && isset($_POST['tampilkan_skor']) && ($_POST['tampilkan_skor'] === 'ya' || $_POST['tampilkan_skor'] === 'tidak')) {
        $tampilkan_skor = $_POST['tampilkan_skor'];
    }
    
    $tanggal_mulai = null;
    if ($has_tanggal_mulai && isset($_POST['tanggal_mulai']) && !empty($_POST['tanggal_mulai'])) {
        $tanggal_mulai = $_POST['tanggal_mulai'];
    }
    $tanggal_selesai = null;
    if ($has_tanggal_selesai && isset($_POST['tanggal_selesai']) && !empty($_POST['tanggal_selesai'])) {
        $tanggal_selesai = $_POST['tanggal_selesai'];
    }
    $tampil_hasil_langsung = 'ya';
    if ($has_tampil_hasil_langsung && isset($_POST['tampil_hasil_langsung']) && ($_POST['tampil_hasil_langsung'] === 'ya' || $_POST['tampil_hasil_langsung'] === 'tidak')) {
        $tampil_hasil_langsung = $_POST['tampil_hasil_langsung'];
    }

    $durasi_per_soal = 0;
    if ($has_durasi_per_soal && isset($_POST['durasi_per_soal'])) {
        $durasi_per_soal = max(0, min(3600, (int)$_POST['durasi_per_soal']));
    }

    $ujian_kelas_ids = isset($_POST['ujian_kelas']) ? $_POST['ujian_kelas'] : [];

    // New security fields
    $kode_ujian = '';
    if ($has_kode_ujian && isset($_POST['kode_ujian'])) {
        $kode_ujian = trim($_POST['kode_ujian']);
    }
    
    $allow_ip = null;
    if ($has_allow_ip && isset($_POST['allow_ip']) && !empty($_POST['allow_ip'])) {
        $allow_ip_json = $_POST['allow_ip'];
        $ip_list = array_filter(array_map('trim', explode(',', $allow_ip_json)));
        $allow_ip = json_encode(array_values($ip_list));
    }
    
    $enable_browser_lock = 'tidak';
    if ($has_browser_lock && isset($_POST['enable_browser_lock']) && ($_POST['enable_browser_lock'] === 'ya' || $_POST['enable_browser_lock'] === 'tidak')) {
        $enable_browser_lock = $_POST['enable_browser_lock'];
    }
    
    $max_violations = 10;
    if (isset($_POST['max_violations']) && (int)$_POST['max_violations'] > 0) {
        $max_violations = (int)$_POST['max_violations'];
    }
    
    $enable_device_check = 'tidak';
    if ($has_device_check && isset($_POST['enable_device_check']) && ($_POST['enable_device_check'] === 'ya' || $_POST['enable_device_check'] === 'tidak')) {
        $enable_device_check = $_POST['enable_device_check'];
    }
    
    $timer_per_soal = 0;
    if ($has_timer_per_soal && isset($_POST['timer_per_soal'])) {
        $timer_per_soal = max(0, min(3600, (int)$_POST['timer_per_soal']));
    }
    
    $show_timer_per_soal = 'tidak';
    if ($has_show_timer_per_soal && isset($_POST['show_timer_per_soal']) && ($_POST['show_timer_per_soal'] === 'ya' || $_POST['show_timer_per_soal'] === 'tidak')) {
        $show_timer_per_soal = $_POST['show_timer_per_soal'];
    }
    
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $original_updated = $_POST['original_updated'] ?? '';
    
    if (empty($judul)) {
        $message = "Judul ujian wajib diisi!";
        $message_type = 'danger';
    } else {
        // Build optional field list (same for INSERT and UPDATE)
        $opt = [];
        if ($has_new_columns) {
            $opt[] = ['col' => 'waktu_tersedia', 'type' => 'i', 'val' => $waktu_tersedia];
            $opt[] = ['col' => 'acak_soal',       'type' => 's', 'val' => $acak_soal];
        }
        if ($has_acak_opsi)           $opt[] = ['col' => 'acak_opsi',            'type' => 's', 'val' => $acak_opsi];
        if ($has_tampilkan_skor)      $opt[] = ['col' => 'tampilkan_skor',       'type' => 's', 'val' => $tampilkan_skor];
        if ($has_tampilkan_review)    $opt[] = ['col' => 'tampilkan_review',     'type' => 's', 'val' => $tampilkan_review];
        if ($has_tanggal_mulai)       $opt[] = ['col' => 'tanggal_mulai',        'type' => 's', 'val' => $tanggal_mulai];
        if ($has_tanggal_selesai)     $opt[] = ['col' => 'tanggal_selesai',      'type' => 's', 'val' => $tanggal_selesai];
        if ($has_tampil_hasil_langsung) $opt[] = ['col' => 'tampil_hasil_langsung','type' => 's', 'val' => $tampil_hasil_langsung];
        if ($has_kode_ujian)          $opt[] = ['col' => 'kode_ujian',           'type' => 's', 'val' => $kode_ujian];
        if ($has_allow_ip)            $opt[] = ['col' => 'allow_ip',             'type' => 's', 'val' => $allow_ip];
        if ($has_browser_lock) {
            $opt[] = ['col' => 'enable_browser_lock', 'type' => 's', 'val' => $enable_browser_lock];
            $opt[] = ['col' => 'max_violations',      'type' => 'i', 'val' => $max_violations];
        }
        if ($has_device_check)        $opt[] = ['col' => 'enable_device_check',  'type' => 's', 'val' => $enable_device_check];
        if ($has_timer_per_soal)      $opt[] = ['col' => 'timer_per_soal',       'type' => 'i', 'val' => $timer_per_soal];
        if ($has_show_timer_per_soal) $opt[] = ['col' => 'show_timer_per_soal',  'type' => 's', 'val' => $show_timer_per_soal];
        if ($has_durasi_per_soal)     $opt[] = ['col' => 'durasi_per_soal',      'type' => 'i', 'val' => $durasi_per_soal];

        if ($edit_id > 0) {
            $stmt = $conn->prepare("SELECT updated_at FROM ujian WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_data = $result->fetch_assoc();
            $stmt->close();

            if ($current_data && $original_updated !== $current_data['updated_at']) {
                $message = "Data telah diubah oleh pengguna lain. Silakan refresh dan coba lagi.";
                $message_type = 'danger';
            } else {
                $fields = "judul_ujian = ?, deskripsi = ?, status = ?";
                $types = "sss";
                $params = [$judul, $deskripsi, $status];
                foreach ($opt as $f) {
                    $fields .= ", {$f['col']} = ?";
                    $params[] = $f['val'];
                    $types .= $f['type'];
                }
                $fields .= " WHERE id = ?";
                $params[] = $edit_id;
                $types .= "i";

                $stmt = $conn->prepare("UPDATE ujian SET $fields");
                $stmt->bind_param($types, ...$params);
                $message = "Ujian berhasil diperbarui!";
            }
        } else {
            $col_names = "judul_ujian, deskripsi, status";
            $placeholders = "?, ?, ?";
            $types = "sss";
            $params = [$judul, $deskripsi, $status];
            foreach ($opt as $f) {
                $col_names .= ", {$f['col']}";
                $placeholders .= ", ?";
                $params[] = $f['val'];
                $types .= $f['type'];
            }
            $stmt = $conn->prepare("INSERT INTO ujian ($col_names) VALUES ($placeholders)");
            $stmt->bind_param($types, ...$params);
            $message = "Ujian berhasil ditambahkan!";
        }

        if (empty($message_type)) {
            if ($stmt->execute()) {
                $message_type = 'success';
                if (isset($redis)) $redis->delete('ujian:list_aktif');

                $ujian_id = $edit_id > 0 ? $edit_id : $stmt->insert_id;

                if (!empty($ujian_kelas_ids)) {
                    $conn->query("DELETE FROM ujian_kelas WHERE id_ujian = $ujian_id");
                    $stmt_kelas = $conn->prepare("INSERT INTO ujian_kelas (id_ujian, id_kelas) VALUES (?, ?)");
                    foreach ($ujian_kelas_ids as $id_kelas) {
                        $id_kelas = (int)$id_kelas;
                        if ($id_kelas > 0) {
                            $stmt_kelas->bind_param("ii", $ujian_id, $id_kelas);
                            $stmt_kelas->execute();
                        }
                    }
                    $stmt_kelas->close();
                }

                if ($edit_id > 0) {
                    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'UPDATE', 'UJIAN', $edit_id, 'Mengupdate ujian: ' . $judul);
                } else {
                    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'CREATE', 'UJIAN', $stmt->insert_id, 'Menambahkan ujian: ' . $judul);
                }
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $conn->prepare("SELECT judul_ujian FROM ujian WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $h_result = $stmt->get_result();
    $h_ujian = $h_result->fetch_assoc();
    $stmt->close();
    
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'UJIAN', $id, 'Menghapus ujian: ' . ($h_ujian['judul_ujian'] ?? 'ID: ' . $id));
    
    $stmt = $conn->prepare("DELETE FROM ujian WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        if (isset($redis)) $redis->delete('ujian:list_aktif');
        header('Location: index.php?deleted=1');
        exit;
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM ujian ORDER BY tgl_dibuat DESC");

$ujian_kelas_map = [];
if ($has_tanggal_mulai || $has_tanggal_selesai) {
    $uk_all = $conn->query("SELECT uk.id_ujian, k.nama_kelas FROM ujian_kelas uk JOIN kelas k ON uk.id_kelas = k.id");
    while ($uk = $uk_all->fetch_assoc()) {
        $ujian_kelas_map[$uk['id_ujian']][] = htmlspecialchars($uk['nama_kelas']);
    }
}

$edit_ujian = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM ujian WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    $edit_ujian = $edit_result->fetch_assoc();
    $stmt->close();
}

// Dashboard stats
$total_ujian = 0; $aktif_ujian = 0; $total_siswa = 0; $total_kelas = 0;
$r_ujian = $conn->query("SELECT COUNT(*) as c, SUM(status='aktif') as a FROM ujian");
if ($r_ujian) { $row = $r_ujian->fetch_assoc(); $total_ujian = $row['c']; $aktif_ujian = $row['a']; $r_ujian->free(); }
$r_siswa = $conn->query("SELECT COUNT(*) as c FROM siswa");
if ($r_siswa) { $total_siswa = $r_siswa->fetch_assoc()['c']; $r_siswa->free(); }
$r_kelas = $conn->query("SELECT COUNT(*) as c FROM kelas");
if ($r_kelas) { $total_kelas = $r_kelas->fetch_assoc()['c']; $r_kelas->free(); }
$r_soal = $conn->query("SELECT COUNT(*) as c FROM soal");
$total_soal = $r_soal ? $r_soal->fetch_assoc()['c'] : 0;
if ($r_soal) $r_soal->free();
$total_nilai = 0;
$r_nilai = $conn->query("SELECT AVG(total_skor) as rata FROM hasil_ujian");
if ($r_nilai) { $total_nilai = round($r_nilai->fetch_assoc()['rata'] ?? 0, 1); $r_nilai->free(); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Dashboard - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        .alert-danger-conflict {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li class="active">Dashboard</li>
            </ul>
            <h3><i class="bi bi-speedometer2 me-2"></i>Dashboard <?= htmlspecialchars($sekolah['nama_sekolah']) ?></h3>
        </div>

        <!-- Dashboard Stats -->
        <div class="row g-3 mb-4 animate-fade-in">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
                    <div class="stat-value"><?= $total_ujian ?></div>
                    <div class="stat-label">Total Ujian</div>
                    <div class="stat-trend text-success"><i class="bi bi-check-circle-fill"></i> <?= $aktif_ujian ?> aktif</div>
                    <div class="stat-bg-icon bi bi-journal-text"></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-value"><?= $total_siswa ?></div>
                    <div class="stat-label">Total Siswa</div>
                    <div class="stat-trend text-muted"><i class="bi bi-diagram-3 me-1"></i><?= $total_kelas ?> kelas</div>
                    <div class="stat-bg-icon bi bi-people"></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="bi bi-question-circle"></i></div>
                    <div class="stat-value"><?= $total_soal ?></div>
                    <div class="stat-label">Total Soal</div>
                    <div class="stat-trend text-muted"><i class="bi bi-database me-1"></i>bank soal</div>
                    <div class="stat-bg-icon bi bi-question-circle"></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="bi bi-bar-chart"></i></div>
                    <div class="stat-value"><?= $total_nilai ?></div>
                    <div class="stat-label">Rata-rata Nilai</div>
                    <div class="stat-trend text-muted">dari semua ujian</div>
                    <div class="stat-bg-icon bi bi-bar-chart"></div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert <?= ($message_type === 'danger' && strpos($message, 'pengguna lain') !== false) ? 'alert-danger-conflict' : 'alert-'.$message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
            <?php if ($message_type === 'danger' && strpos($message, 'pengguna lain') !== false): ?>
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>
                        <strong>Konflik Data!</strong><br>
                        <?= htmlspecialchars($message) ?>
                    </div>
                </div>
            <?php else: ?>
                <?= htmlspecialchars($message) ?>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-<?= $edit_ujian ? 'pencil-square' : 'plus-circle' ?> me-2"></i><?= $edit_ujian ? 'Edit Ujian' : 'Tambah Ujian Baru' ?></span>
                <?php if ($edit_ujian): ?>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="bi bi-x-lg"></i> Batal
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <?php if ($edit_ujian): ?>
                        <input type="hidden" name="edit_id" value="<?= $edit_ujian['id'] ?>">
                        <input type="hidden" name="original_updated" value="<?= $edit_ujian['updated_at'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Judul Ujian <span class="text-danger">*</span></label>
                            <input type="text" name="judul_ujian" class="form-control" required 
                                   value="<?= $edit_ujian ? htmlspecialchars($edit_ujian['judul_ujian']) : '' ?>"
                                   placeholder="Contoh: Ujian Informatika Semester 1">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="nonaktif" <?= $edit_ujian && $edit_ujian['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                <option value="aktif" <?= $edit_ujian && $edit_ujian['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            </select>
                        </div>

                        <?php if ($has_new_columns): ?>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Waktu</label>
                            <input type="number" name="waktu_tersedia" class="form-control" 
                                   value="<?= $edit_ujian ? htmlspecialchars($edit_ujian['waktu_tersedia'] ?? 0) : 0 ?>"
                                   placeholder="0" min="0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Acak Soal</label>
                            <select name="acak_soal" class="form-select">
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['acak_soal'] ?? 'tidak') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['acak_soal'] ?? 'tidak') === 'ya' ? 'selected' : '' ?>>Ya</option>
                            </select>
                        </div>

                        <?php if ($has_acak_opsi): ?>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Acak Opsi</label>
                            <select name="acak_opsi" class="form-select">
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['acak_opsi'] ?? 'tidak') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['acak_opsi'] ?? 'tidak') === 'ya' ? 'selected' : '' ?>>Ya</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($has_tampilkan_review): ?>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Review</label>
                            <select name="tampilkan_review" class="form-select">
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['tampilkan_review'] ?? 'tidak') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['tampilkan_review'] ?? 'tidak') === 'ya' ? 'selected' : '' ?>>Ya</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($has_tampilkan_skor): ?>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Tampilkan Skor</label>
                            <select name="tampilkan_skor" class="form-select">
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['tampilkan_skor'] ?? 'ya') === 'ya' ? 'selected' : '' ?>>Ya</option>
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['tampilkan_skor'] ?? 'ya') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($has_timer_per_soal): ?>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Timer/Soal (dtk)</label>
                            <input type="number" name="timer_per_soal" class="form-control" 
                                   value="<?= $edit_ujian ? (int)($edit_ujian['timer_per_soal'] ?? 0) : 0 ?>"
                                   placeholder="0 = tidak aktif" min="0" max="3600">
                            <small class="text-muted">0 = tidak aktif</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Tampil Timer</label>
                            <select name="show_timer_per_soal" class="form-select">
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['show_timer_per_soal'] ?? 'tidak') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['show_timer_per_soal'] ?? 'tidak') === 'ya' ? 'selected' : '' ?>>Ya</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($has_kode_ujian || $has_allow_ip || $has_browser_lock || $has_device_check): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-primary"><i class="bi bi-shield-lock me-2"></i>Keamanan</h6>
                        </div>
                    </div>
                    <hr>
                    
                    <div class="row">
                        <?php if ($has_kode_ujian): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Kode Ujian</label>
                            <input type="text" name="kode_ujian" class="form-control" 
                                   value="<?= $edit_ujian ? htmlspecialchars($edit_ujian['kode_ujian'] ?? '') : '' ?>"
                                   placeholder="Opsional">
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($has_allow_ip): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Batasan IP</label>
                            <input type="text" name="allow_ip" class="form-control" 
                                   value="<?= $edit_ujian && !empty($edit_ujian['allow_ip']) ? htmlspecialchars(implode(', ', json_decode($edit_ujian['allow_ip'] ?? '[]', true) ?: [])) : '' ?>"
                                   placeholder="Contoh: 192.168.1.1">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <?php if ($has_browser_lock): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Browser Lock</label>
                            <select name="enable_browser_lock" class="form-select">
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['enable_browser_lock'] ?? 'tidak') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['enable_browser_lock'] ?? 'tidak') === 'ya' ? 'selected' : '' ?>>Ya</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Max Pelanggaran</label>
                            <input type="number" name="max_violations" class="form-control" 
                                   value="<?= $edit_ujian ? (int)($edit_ujian['max_violations'] ?? 10) : 10 ?>"
                                   min="1" max="10">
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($has_device_check): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Device Check</label>
                            <select name="enable_device_check" class="form-select">
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['enable_device_check'] ?? 'tidak') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['enable_device_check'] ?? 'tidak') === 'ya' ? 'selected' : '' ?>>Ya</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($has_tanggal_mulai || $has_tanggal_selesai): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-primary"><i class="bi bi-calendar-event me-2"></i>Penjadwalan</h6>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <?php if ($has_tanggal_mulai): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tanggal Mulai</label>
                            <input type="datetime-local" name="tanggal_mulai" class="form-control"
                                   value="<?= $edit_ujian && !empty($edit_ujian['tanggal_mulai']) ? date('Y-m-d\TH:i', strtotime($edit_ujian['tanggal_mulai'])) : '' ?>">
                        </div>
                        <?php endif; ?>
                        <?php if ($has_tanggal_selesai): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tanggal Selesai</label>
                            <input type="datetime-local" name="tanggal_selesai" class="form-control"
                                   value="<?= $edit_ujian && !empty($edit_ujian['tanggal_selesai']) ? date('Y-m-d\TH:i', strtotime($edit_ujian['tanggal_selesai'])) : '' ?>">
                        </div>
                        <?php endif; ?>
                        <?php if ($has_tampil_hasil_langsung): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Tampil Hasil</label>
                            <select name="tampil_hasil_langsung" class="form-select">
                                <option value="ya" <?= $edit_ujian && ($edit_ujian['tampil_hasil_langsung'] ?? 'ya') === 'ya' ? 'selected' : '' ?>>Ya</option>
                                <option value="tidak" <?= $edit_ujian && ($edit_ujian['tampil_hasil_langsung'] ?? 'ya') === 'tidak' ? 'selected' : '' ?>>Tidak</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if ($has_durasi_per_soal): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Durasi/Soal (dtk)</label>
                            <input type="number" name="durasi_per_soal" class="form-control"
                                   value="<?= $edit_ujian ? (int)($edit_ujian['durasi_per_soal'] ?? 0) : 0 ?>"
                                   placeholder="0" min="0" max="3600">
                            <small class="text-muted">0 = gunakan durasi ujian</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php
                    $kelas_all = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");
                    $ujian_kelas_selected = [];
                    if ($edit_ujian) {
                        $stmt_uk = $conn->prepare("SELECT id_kelas FROM ujian_kelas WHERE id_ujian = ?");
                        $stmt_uk->bind_param("i", $edit_ujian['id']);
                        $stmt_uk->execute();
                        $uk_res = $stmt_uk->get_result();
                        while ($uk = $uk_res->fetch_assoc()) {
                            $ujian_kelas_selected[] = $uk['id_kelas'];
                        }
                        $stmt_uk->close();
                    }
                    ?>
                    <?php if ($kelas_all && $kelas_all->num_rows > 0): ?>
                    <div class="row mt-3">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Batasi ke Kelas</label>
                            <select name="ujian_kelas[]" class="form-select" multiple style="min-height: 120px;">
                                <?php $kelas_all->data_seek(0); while ($kl = $kelas_all->fetch_assoc()): ?>
                                <option value="<?= $kl['id'] ?>" <?= in_array($kl['id'], $ujian_kelas_selected) ? 'selected' : '' ?>><?= htmlspecialchars($kl['nama_kelas']) ?> <?= $kl['tingkat'] ? '(' . htmlspecialchars($kl['tingkat']) . ')' : '' ?></option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Kosongkan jika tidak ada batasan kelas (Ctrl+klik untuk multi-pilih)</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="2" 
                                      placeholder="Opsional"><?= $edit_ujian ? htmlspecialchars($edit_ujian['deskripsi']) : '' ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="submit" name="simpan_ujian" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> <?= $edit_ujian ? 'Perbarui' : 'Simpan' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ol me-2"></i>Daftar Ujian</span>
                <span class="badge bg-primary"><?= $result->num_rows ?> ujian</span>
            </div>
            <div class="card-body p-0">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-scroll">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th class="text-center">ID</th>
                                <th>Judul</th>
                                <th class="text-center">Status</th>
                                <?php if ($has_new_columns): ?>
                                <th class="text-center">Waktu</th>
                                <th class="text-center">Acak</th>
                                <?php if ($has_acak_opsi): ?>
                                <th class="text-center">Opsi</th>
                                <?php endif; ?>
                                <th class="text-center">Review</th>
                                <?php if ($has_tampilkan_skor): ?>
                                <th class="text-center">Skor</th>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($has_tanggal_mulai): ?>
                                <th class="text-center">Mulai</th>
                                <?php endif; ?>
                                <?php if ($has_tanggal_selesai): ?>
                                <th class="text-center">Selesai</th>
                                <?php endif; ?>
                                <th class="text-center">Kelas</th>
                                <th class="text-center">Tgl</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="text-center text-muted"><?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['judul_ujian']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($row['deskripsi'] ?? '', 0, 60, '...')) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $row['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                                        <?= strtoupper($row['status']) ?>
                                    </span>
                                </td>
<?php if ($has_new_columns): ?>
                                <td class="text-center">
                                    <?php if (($row['waktu_tersedia'] ?? 0) > 0): ?>
                                    <span class="badge bg-info"><?= $row['waktu_tersedia'] ?> mnt</span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (($row['acak_soal'] ?? 'tidak') === 'ya'): ?>
                                    <span class="badge bg-warning"><i class="bi bi-shuffle"></i></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($has_acak_opsi): ?>
                                <td class="text-center">
                                    <?php if (($row['acak_opsi'] ?? 'tidak') === 'ya'): ?>
                                    <span class="badge bg-info"><i class="bi bi-shuffle"></i></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if (($row['tampilkan_review'] ?? 'tidak') === 'ya'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($has_tampilkan_skor): ?>
                                <td class="text-center">
                                    <?php if (($row['tampilkan_skor'] ?? 'ya') === 'ya'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check"></i></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-x"></i></span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($has_tanggal_mulai): ?>
                                <td class="text-center">
                                    <?php if (!empty($row['tanggal_mulai'])): ?>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($row['tanggal_mulai'])) ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($has_tanggal_selesai): ?>
                                <td class="text-center">
                                    <?php if (!empty($row['tanggal_selesai'])): ?>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($row['tanggal_selesai'])) ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if (!empty($ujian_kelas_map[$row['id']])): ?>
                                    <span class="badge bg-info" title="<?= implode(', ', $ujian_kelas_map[$row['id']]) ?>"><?= count($ujian_kelas_map[$row['id']]) ?> kelas</span>
                                    <?php else: ?>
                                    <span class="text-muted">Semua</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-muted"><?= date('d/m/Y', strtotime($row['tgl_dibuat'])) ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-gear"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="?edit=<?= $row['id'] ?>">
                                                <i class="bi bi-pencil me-2"></i>Edit
                                            </a></li>
                                            <li><a class="dropdown-item" href="?id=<?= $row['id'] ?>&status=<?= $row['status'] ?>&toggle=1">
                                                <i class="bi bi-toggle-<?= $row['status'] === 'aktif' ? 'on' : 'off' ?> me-2"></i><?= $row['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </a></li>
                                            <li><a class="dropdown-item" href="tambah_soal.php?ujian=<?= $row['id'] ?>">
                                                <i class="bi bi-list-ol me-2"></i>Kelola Soal
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="?hapus=<?= $row['id'] ?>" onclick="return confirm('Yakin hapus?')">
                                                <i class="bi bi-trash me-2"></i>Hapus
                                            </a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">Belum ada ujian</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade delete-modal" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="delete-icon">
                        <i class="bi bi-trash3"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Hapus Ujian?</h5>
                    <p class="text-muted mb-0">Apakah Anda yakin ingin menghapus ujian "<strong id="deleteUjianTitle"></strong>"?</p>
                    <p class="text-danger small mb-0 mt-2"><i class="bi bi-exclamation-triangle me-1"></i>Tindakan ini tidak dapat dibatalkan. Semua soal juga akan dihapus.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Batal
                    </button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast toast-success p-3" role="alert">
            <div class="d-flex align-items-center">
                <div class="toast-icon me-3">
                    <i class="bi bi-check-circle-fill" style="font-size: 1.5rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="d-block">Berhasil!</strong>
                    <small class="text-muted" id="toastMessage">Ujian berhasil dihapus.</small>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        var deleteModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            
            // Check for delete success message in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('deleted') === '1') {
                showToast('Ujian berhasil dihapus!');
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function showDeleteModal(id, title) {
            document.getElementById('deleteUjianTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = '?hapus=' + id;
            deleteModal.show();
        }

        function showToast(message) {
            var toast = document.getElementById('toastNotification');
            document.getElementById('toastMessage').textContent = message;
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.overlay').classList.toggle('show');
        }
        
        // Close sidebar when clicking a link on mobile
        document.querySelectorAll('.sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                    document.querySelector('.overlay').classList.remove('show');
                }
            });
        });
        
        function copyLink(id) {
            var copyText = document.getElementById("link" + id);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(window.location.origin + '/' + copyText.value).then(function() {
                alert("Link ujian copied!");
            }).catch(function() {
                copyText.select();
                document.execCommand('copy');
                alert("Link ujian copied!");
            });
        }
    </script>
</body>
</html>
