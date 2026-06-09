<?php
session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: ../uploads/;");
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

$filter_ujian = isset($_GET['filter_ujian']) ? (int)$_GET['filter_ujian'] : 0;
$filter_kategori = isset($_GET['filter_kategori']) ? trim($_GET['filter_kategori']) : '';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

$ujian_list = $conn->query("SELECT id, judul_ujian, status FROM ujian ORDER BY judul_ujian");

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $conn->prepare("SELECT gambar_pertanyaan, gambar_a, gambar_b, gambar_c, gambar_d, gambar_e FROM soal WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $soal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($soal) {
        foreach (['gambar_pertanyaan', 'gambar_a', 'gambar_b', 'gambar_c', 'gambar_d', 'gambar_e'] as $gbr) {
            if ($soal[$gbr] && file_exists('../uploads/' . $soal[$gbr])) {
                unlink('../uploads/' . $soal[$gbr]);
            }
        }
        $stmt = $conn->prepare("DELETE FROM soal WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Soal berhasil dihapus!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['copy_selected'])) {
        $target_ujian = isset($_POST['target_ujian']) ? (int)$_POST['target_ujian'] : 0;
        $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : '';

        if ($target_ujian > 0 && !empty($selected_ids)) {
            $ids = explode(',', $selected_ids);
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids);
            $copied = 0;

            foreach ($ids as $soal_id) {
                $stmt = $conn->prepare("SELECT * FROM soal WHERE id = ?");
                $stmt->bind_param("i", $soal_id);
                $stmt->execute();
                $soal = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($soal) {
                    $check = $conn->prepare("SELECT id FROM soal WHERE id_ujian = ? AND pertanyaan = ?");
                    $check->bind_param("is", $target_ujian, $soal['pertanyaan']);
                    $check->execute();
                    $exists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$exists) {
                        $stmt = $conn->prepare("INSERT INTO soal (id_ujian, pertanyaan, gambar_pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban, poin, kategori, timer_soal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssssssssssisi", $target_ujian, $soal['pertanyaan'], $soal['gambar_pertanyaan'], $soal['opsi_a'], $soal['gambar_a'], $soal['opsi_b'], $soal['gambar_b'], $soal['opsi_c'], $soal['gambar_c'], $soal['opsi_d'], $soal['gambar_d'], $soal['opsi_e'], $soal['gambar_e'], $soal['kunci_jawaban'], $soal['poin'], $soal['kategori'], $soal['timer_soal']);
                        if ($stmt->execute()) {
                            $copied++;
                        }
                        $stmt->close();
                    }
                }
            }

            if ($copied > 0) {
                $message = "Berhasil menyalin $copied soal ke ujian tujuan!";
                $message_type = 'success';
            } else {
                $message = "Tidak ada soal yang disalin (mungkin sudah ada di ujian tujuan).";
                $message_type = 'warning';
            }
        } else {
            $message = "Pilih ujian tujuan dan minimal satu soal!";
            $message_type = 'danger';
        }
    }

    if (isset($_POST['move_selected'])) {
        $target_ujian = isset($_POST['target_ujian_move']) ? (int)$_POST['target_ujian_move'] : 0;
        $selected_ids = isset($_POST['selected_ids_move']) ? $_POST['selected_ids_move'] : '';

        if ($target_ujian > 0 && !empty($selected_ids)) {
            $ids = explode(',', $selected_ids);
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids);
            $moved = 0;

            foreach ($ids as $soal_id) {
                $stmt = $conn->prepare("SELECT * FROM soal WHERE id = ?");
                $stmt->bind_param("i", $soal_id);
                $stmt->execute();
                $soal = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($soal) {
                    $check = $conn->prepare("SELECT id FROM soal WHERE id_ujian = ? AND pertanyaan = ?");
                    $check->bind_param("is", $target_ujian, $soal['pertanyaan']);
                    $check->execute();
                    $exists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$exists) {
                        $stmt = $conn->prepare("UPDATE soal SET id_ujian = ? WHERE id = ?");
                        $stmt->bind_param("ii", $target_ujian, $soal_id);
                        if ($stmt->execute()) {
                            $moved++;
                        }
                        $stmt->close();
                    }
                }
            }

            if ($moved > 0) {
                $message = "Berhasil memindahkan $moved soal ke ujian tujuan!";
                $message_type = 'success';
            } else {
                $message = "Tidak ada soal yang dipindahkan (mungkin sudah ada di ujian tujuan).";
                $message_type = 'warning';
            }
        } else {
            $message = "Pilih ujian tujuan dan minimal satu soal!";
            $message_type = 'danger';
        }
    }
}

$sql = "SELECT s.*, u.judul_ujian FROM soal s JOIN ujian u ON s.id_ujian = u.id WHERE 1=1";
$params = [];
$types = '';

if ($filter_ujian > 0) {
    $sql .= " AND s.id_ujian = ?";
    $params[] = $filter_ujian;
    $types .= 'i';
}

if ($filter_kategori !== '') {
    $sql .= " AND s.kategori = ?";
    $params[] = $filter_kategori;
    $types .= 's';
}

if ($search_keyword !== '') {
    $sql .= " AND (s.pertanyaan LIKE ? OR s.opsi_a LIKE ? OR s.opsi_b LIKE ? OR s.opsi_c LIKE ? OR s.opsi_d LIKE ? OR s.opsi_e LIKE ?)";
    $keyword = '%' . $search_keyword . '%';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $keyword;
    }
    $types .= 'ssssss';
}

$sql .= " ORDER BY u.judul_ujian, s.id";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$all_soal = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Bank Soal Global</title>
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
            --warning: #f59e0b;
            --dark: #1e293b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 260px;
        }
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f1f5f9; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); min-height: 100vh; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); position: fixed; left: 0; top: 0; z-index: 1000; transition: transform 0.3s ease; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h5 { color: #fff; font-weight: 600; margin: 0; }
        .school-logo { width: 55px; height: 55px; background: rgba(255,255,255,0.15); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; }
        .sidebar a { color: rgba(255,255,255,0.7); text-decoration: none; padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s ease; border-left: 3px solid transparent; font-size: 0.9375rem; }
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar a.active { background: rgba(79, 70, 229, 0.2); color: #fff; border-left-color: var(--primary); }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; transition: margin-left 0.3s ease; width: calc(100% - var(--sidebar-width)); box-sizing: border-box; min-width: 0; }
        .page-header { background: #fff; border-radius: 12px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .page-header h3 { margin: 0; font-weight: 600; color: var(--dark); }
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { background: #fff; border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; font-weight: 600; color: var(--dark); }
        .card-body { padding: 1.5rem; }
        .form-control, .form-select { border: 1px solid var(--border); border-radius: 8px; padding: 0.625rem 0.875rem; font-size: 0.9375rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .btn { border-radius: 8px; padding: 0.625rem 1.25rem; font-weight: 500; transition: all 0.2s ease; white-space: nowrap; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-success { background: var(--success); border-color: var(--success); }
        .btn-warning { background: var(--warning); border-color: var(--warning); color: #fff; }
        .btn-danger { background: var(--danger); border-color: var(--danger); }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
        .table { margin-bottom: 0; }
        .table thead th { background: #f8fafc; border-bottom: 2px solid var(--border); color: var(--secondary); font-weight: 600; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 1rem; white-space: nowrap; }
        .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border); }
        .table tbody tr:hover { background: #f8fafc; }
        .table tbody tr.soal-row { cursor: pointer; }
        .badge { font-weight: 500; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; }
        .mobile-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1001; background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 0.625rem; font-size: 1.25rem; }
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .animate-fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .soal-detail { display: none; background: #f8fafc; border-top: 1px solid var(--border); }
        .soal-detail.show { display: table-row; }
        .soal-detail td { padding: 1.5rem; }
        .action-buttons { display: flex; gap: 0.5rem; justify-content: center; align-items: center; }
        .action-btn-group { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; text-decoration: none; border: none; background: none; cursor: pointer; }
        .action-btn { width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; border: none; transition: all 0.2s ease; font-size: 1.1rem; text-decoration: none; }
        .action-btn-label { font-size: 0.65rem; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
        .action-btn-group:hover .action-btn { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .action-btn-edit { background: #fef3c7; color: #d97706 !important; }
        .action-btn-edit:hover { background: #fde68a; color: #b45309 !important; }
        .action-btn-delete { background: #f3f4f6; color: #6b7280 !important; }
        .action-btn-delete:hover { background: #fee2e2; color: #dc2626 !important; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 4rem 1rem 1rem; }
            .mobile-toggle { display: flex; }
            .overlay.show { display: block; }
        }
        @media (max-width: 576px) {
            .page-header { padding: 1rem; flex-direction: column; align-items: flex-start; }
            .card-body { padding: 1rem; }
            .btn { width: 100%; margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    <div class="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar">
        <div class="sidebar-brand text-center">
            <div class="school-logo mb-2">
                <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                    <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill" style="font-size: 1.8rem;"></i>
                <?php endif; ?>
            </div>
            <div class="text-white fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></div>
            <h5 class="mt-2"><i class="bi bi-gear me-1"></i>Admin Panel</h5>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
            <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
            <a href="bank_soal.php" class="active"><i class="bi bi-database-fill"></i> Bank Soal Global</a>
            <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
            <a href="analytics.php"><i class="bi bi-graph-up"></i> Analytics</a>
            <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
            <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
            <a href="backup_restore.php"><i class="bi bi-cloud-arrow-up-fill"></i> Backup & Restore</a>
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
            <h3><i class="bi bi-database-fill me-2"></i>Bank Soal Global</h3>
            <span class="badge bg-primary fs-6"><?= $all_soal->num_rows ?> soal</span>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-circle' : 'exclamation-triangle') ?>-fill me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Filter Ujian</label>
                        <select name="filter_ujian" class="form-select" onchange="this.form.submit()">
                            <option value="0">-- Semua Ujian --</option>
                            <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>" <?= $filter_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ujian['judul_ujian']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter Kategori</label>
                        <select name="filter_kategori" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Semua Kategori --</option>
                            <option value="Mudah" <?= $filter_kategori === 'Mudah' ? 'selected' : '' ?>>Mudah</option>
                            <option value="Sedang" <?= $filter_kategori === 'Sedang' ? 'selected' : '' ?>>Sedang</option>
                            <option value="Sulit" <?= $filter_kategori === 'Sulit' ? 'selected' : '' ?>>Sulit</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cari Soal</label>
                        <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Cari
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-list-ol me-2"></i>Daftar Soal Global</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $all_soal->num_rows ?> soal</span>
                    <div class="bulk-actions" style="display: none;">
                        <button type="button" class="btn btn-sm btn-primary" id="copySelectedBtn">
                            <i class="bi bi-copy me-1"></i>Copy ke Ujian Lain
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="moveSelectedBtn">
                            <i class="bi bi-arrow-right-circle me-1"></i>Pindahkan
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($all_soal->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                                <th class="text-center" style="width: 50px;">ID</th>
                                <th>Pertanyaan</th>
                                <th>Ujian</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Poin</th>
                                <th class="text-center">Kunci</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($soal = $all_soal->fetch_assoc()): ?>
                            <tr class="soal-row" onclick="toggleDetail(<?= $soal['id'] ?>)" data-id="<?= $soal['id'] ?>">
                                <td class="text-center" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="soal-checkbox" value="<?= $soal['id'] ?>">
                                </td>
                                <td class="text-center"><?= $soal['id'] ?></td>
                                <td style="white-space: normal; word-wrap: break-word; max-width: 300px;">
                                    <?= htmlspecialchars(mb_strimwidth(strip_tags($soal['pertanyaan']), 0, 120, '...')) ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($soal['judul_ujian']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($soal['kategori'])): ?>
                                        <span class="badge bg-<?= $soal['kategori'] === 'Mudah' ? 'success' : ($soal['kategori'] === 'Sedang' ? 'warning' : 'danger') ?>">
                                            <?= htmlspecialchars($soal['kategori']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold"><?= (int)$soal['poin'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $soal['kunci_jawaban'] === 'a' ? 'primary' : ($soal['kunci_jawaban'] === 'b' ? 'success' : ($soal['kunci_jawaban'] === 'c' ? 'warning' : ($soal['kunci_jawaban'] === 'd' ? 'danger' : 'info'))) ?>">
                                        <?= strtoupper($soal['kunci_jawaban']) ?>
                                    </span>
                                </td>
                                <td class="text-center" onclick="event.stopPropagation()">
                                    <div class="action-buttons">
                                        <a href="tambah_soal.php?ujian=<?= $soal['id_ujian'] ?>&edit=<?= $soal['id'] ?>" class="action-btn-group">
                                            <span class="action-btn action-btn-edit"><i class="bi bi-pencil"></i></span>
                                            <span class="action-btn-label">Edit</span>
                                        </a>
                                        <a href="?hapus=<?= $soal['id'] ?>" class="action-btn-group" onclick="return confirm('Hapus soal ini?')">
                                            <span class="action-btn action-btn-delete"><i class="bi bi-trash3"></i></span>
                                            <span class="action-btn-label">Hapus</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr class="soal-detail" id="detail-<?= $soal['id'] ?>">
                                <td colspan="8">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="fw-bold mb-2">Pertanyaan:</h6>
                                            <p class="mb-3"><?= nl2br(htmlspecialchars($soal['pertanyaan'])) ?></p>
                                            <?php if ($soal['gambar_pertanyaan']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($soal['gambar_pertanyaan']) ?>" alt="Gambar" style="max-width: 200px; max-height: 150px; object-fit: contain;" class="mb-3 border rounded">
                                            <?php endif; ?>
                                            <div class="row">
                                                <?php $opsi = ['a' => $soal['opsi_a'], 'b' => $soal['opsi_b'], 'c' => $soal['opsi_c'], 'd' => $soal['opsi_d'], 'e' => $soal['opsi_e']]; ?>
                                                <?php $gambar = ['a' => $soal['gambar_a'], 'b' => $soal['gambar_b'], 'c' => $soal['gambar_c'], 'd' => $soal['gambar_d'], 'e' => $soal['gambar_e']]; ?>
                                                <?php foreach ($opsi as $huruf => $teks): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="p-2 rounded <?= $soal['kunci_jawaban'] === $huruf ? 'bg-success bg-opacity-10 border border-success' : 'bg-light' ?>">
                                                        <strong><?= strtoupper($huruf) ?>.</strong>
                                                        <?= nl2br(htmlspecialchars($teks)) ?>
                                                        <?php if ($soal['kunci_jawaban'] === $huruf): ?>
                                                            <span class="badge bg-success ms-1"><i class="bi bi-check"></i> Kunci</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($gambar[$huruf])): ?>
                                                            <br><img src="../uploads/<?= htmlspecialchars($gambar[$huruf]) ?>" alt="Gambar <?= $huruf ?>" style="max-width: 80px; max-height: 60px; object-fit: contain;" class="mt-1 border rounded">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card bg-light">
                                                <div class="card-body py-3">
                                                    <small class="text-muted d-block">ID Soal: <strong><?= $soal['id'] ?></strong></small>
                                                    <small class="text-muted d-block">Ujian: <strong><?= htmlspecialchars($soal['judul_ujian']) ?></strong></small>
                                                    <small class="text-muted d-block">Kategori: <strong><?= htmlspecialchars($soal['kategori'] ?? '-') ?></strong></small>
                                                    <small class="text-muted d-block">Poin: <strong><?= (int)$soal['poin'] ?></strong></small>
                                                    <small class="text-muted d-block">Timer: <strong><?= ($soal['timer_soal'] ?? 0) > 0 ? $soal['timer_soal'] . ' dtk' : '-' ?></strong></small>
                                                </div>
                                            </div>
                                        </div>
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
                    <p class="text-muted mt-2">Tidak ada soal ditemukan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Copy Modal -->
    <div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 16px;">
                <div class="modal-header border-0">
                    <h5 class="fw-bold"><i class="bi bi-copy me-2"></i>Copy ke Ujian Lain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="selected_ids" id="copySelectedIds" value="">
                        <p>Pilih ujian tujuan untuk menyalin soal yang dipilih:</p>
                        <select name="target_ujian" class="form-select" required>
                            <option value="">-- Pilih Ujian --</option>
                            <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>"><?= htmlspecialchars($ujian['judul_ujian']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <p class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Soal yang sudah ada di ujian tujuan (berdasarkan teks pertanyaan) tidak akan disalin.</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="copy_selected" class="btn btn-primary"><i class="bi bi-copy me-1"></i>Copy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Move Modal -->
    <div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 16px;">
                <div class="modal-header border-0">
                    <h5 class="fw-bold"><i class="bi bi-arrow-right-circle me-2"></i>Pindahkan ke Ujian Lain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="selected_ids_move" id="moveSelectedIds" value="">
                        <p>Pilih ujian tujuan untuk memindahkan soal yang dipilih:</p>
                        <select name="target_ujian_move" class="form-select" required>
                            <option value="">-- Pilih Ujian --</option>
                            <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>"><?= htmlspecialchars($ujian['judul_ujian']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="alert alert-warning mt-2 py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Soal akan dipindahkan (id_ujian diubah), bukan disalin. Soal tidak akan ada lagi di ujian asal.
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="move_selected" class="btn btn-warning" onclick="return confirm('Pindahkan soal yang dipilih ke ujian tujuan?')"><i class="bi bi-arrow-right-circle me-1"></i>Pindahkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function toggleDetail(id) {
            const detail = document.getElementById('detail-' + id);
            detail.classList.toggle('show');
        }

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

        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.soal-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        document.querySelectorAll('.soal-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });

        function updateBulkActions() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            const bulkActions = document.querySelector('.bulk-actions');
            if (checked.length > 0) {
                bulkActions.style.display = 'inline-flex';
            } else {
                bulkActions.style.display = 'none';
            }
        }

        document.getElementById('copySelectedBtn')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            if (checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('copySelectedIds').value = ids;
            new bootstrap.Modal(document.getElementById('copyModal')).show();
        });

        document.getElementById('moveSelectedBtn')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            if (checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('moveSelectedIds').value = ids;
            new bootstrap.Modal(document.getElementById('moveModal')).show();
        });
    </script>
</body>
</html>
