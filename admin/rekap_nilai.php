<?php
// admin/rekap_nilai.php - Rekap Nilai

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

$ujian_list = $conn->query("SELECT id, judul_ujian FROM ujian ORDER BY judul_ujian");
$selected_ujian = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;

$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$filter_skor_min = isset($_GET['skor_min']) ? (int)$_GET['skor_min'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'skor_desc';

$kelas_list = [];

$izin_remedi_list = [];
$izin_remedi_data = [];
if ($selected_ujian > 0) {
    $stmt = $conn->prepare("SELECT nis, approved_at FROM izin_remedi WHERE id_ujian = ?");
    $stmt->bind_param("i", $selected_ujian);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $izin_remedi_list[] = $row['nis'];
        $izin_remedi_data[$row['nis']] = $row['approved_at'];
    }
    $stmt->close();
}

$hasil_list = [];
$all_results = [];
$stats = ['total' => 0, 'rata' => 0, 'tertinggi' => 0, 'terendah' => 0];
if ($selected_ujian > 0) {
    $order_by = 'h.total_skor DESC';
    switch ($sort_by) {
        case 'skor_asc': $order_by = 'h.total_skor ASC'; break;
        case 'nama_asc': $order_by = 'h.nama ASC'; break;
        case 'nama_desc': $order_by = 'h.nama DESC'; break;
        case 'nis_asc': $order_by = 'h.nis ASC'; break;
        case 'nis_desc': $order_by = 'h.nis DESC'; break;
        case 'waktu_asc': $order_by = 'h.waktu_submit ASC'; break;
        case 'waktu_desc': $order_by = 'h.waktu_submit DESC'; break;
        case 'kelas_asc': $order_by = 'h.kelas ASC, h.nama ASC'; break;
    }
    
    $sql = "SELECT h.*, u.judul_ujian, 
                (SELECT COUNT(*) FROM exam_violations v WHERE v.id_ujian = h.id_ujian AND v.nis = h.nis) as jumlah_pelanggaran
                FROM hasil_ujian h 
                JOIN ujian u ON h.id_ujian = u.id 
                WHERE h.id_ujian = ?";
    if ($filter_skor_min > 0) {
        $sql .= " AND h.total_skor >= " . (int)$filter_skor_min;
    }
    $sql .= " ORDER BY " . $order_by;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_ujian);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['has_remedi'] = in_array($row['nis'], $izin_remedi_list);
        $all_results[] = $row;
    }
    $stmt->close();
    
    if (count($all_results) > 0) {
        $scores = array_column($all_results, 'total_skor');
        $stats['total'] = count($all_results);
        $stats['rata'] = round(array_sum($scores) / count($scores), 1);
        $stats['tertinggi'] = max($scores);
        $stats['terendah'] = min($scores);
        
        // Calculate penalty stats
        $stats['skor_awal_total'] = 0;
        $stats['penalty_total'] = 0;
        foreach ($all_results as $r) {
            $skor_awal = $r['skor_awal'] ?? $r['total_skor'];
            $penalty = $skor_awal - $r['total_skor'];
            $stats['skor_awal_total'] += $skor_awal;
            $stats['penalty_total'] += $penalty;
        }
        
        $kelas_temp = array_unique(array_column($all_results, 'kelas'));
        sort($kelas_temp);
        $kelas_list = array_values($kelas_temp);
    }
    
    if ($filter_kelas !== '') {
        foreach ($all_results as $row) {
            if ($row['kelas'] === $filter_kelas) {
                $hasil_list[] = $row;
            }
        }
    } else {
        $hasil_list = $all_results;
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $id_ujian = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;
    $stmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header('Location: ?ujian=' . $id_ujian . '&deleted=1');
        exit;
    }
    $stmt->close();
}

if (!empty($_POST['give_remedi']) && isset($_POST['id_hasil']) && isset($_POST['id_ujian'])) {
    $id_hasil = (int)$_POST['id_hasil'];
    $id_ujian = (int)$_POST['id_ujian'];
    
    $stmt = $conn->prepare("SELECT nis, nama, kelas FROM hasil_ujian WHERE id = ?");
    $stmt->bind_param("i", $id_hasil);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();
    $stmt->close();
    
    if ($siswa) {
        $stmt = $conn->prepare("INSERT INTO izin_remedi (id_ujian, nis, nama, kelas, diberikan_oleh, approved_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE nama = VALUES(nama), kelas = VALUES(kelas), diberikan_oleh = VALUES(diberikan_oleh), approved_at = NOW()");
        $admin_user = $_SESSION['admin_username'];
        $stmt->bind_param("issss", $id_ujian, $siswa['nis'], $siswa['nama'], $siswa['kelas'], $admin_user);
        if ($stmt->execute()) {
            header('Location: ?ujian=' . $id_ujian . '&remedi=1');
            exit;
        }
        $stmt->close();
    }
}

if (!empty($_POST['remove_remedi']) && isset($_POST['id_hasil']) && isset($_POST['id_ujian'])) {
    $id_hasil = (int)$_POST['id_hasil'];
    $id_ujian = (int)$_POST['id_ujian'];
    
    $stmt = $conn->prepare("SELECT nis, nama FROM hasil_ujian WHERE id = ?");
    $stmt->bind_param("i", $id_hasil);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();
    $stmt->close();
    
    if ($siswa) {
        $stmt = $conn->prepare("DELETE FROM izin_remedi WHERE id_ujian = ? AND nis = ?");
        $stmt->bind_param("is", $id_ujian, $siswa['nis']);
        if ($stmt->execute()) {
            header('Location: ?ujian=' . $id_ujian . '&remedi_removed=1');
            exit;
        }
        $stmt->close();
    }
}

if (isset($_POST['batch_remedi']) && isset($_POST['selected_ids']) && isset($_POST['id_ujian_batch'])) {
    $selected_ids = $_POST['selected_ids'];
    $id_ujian = (int)$_POST['id_ujian_batch'];
    $admin_user = $_SESSION['admin_username'];
    
    $count = 0;
    foreach ($selected_ids as $id_hasil) {
        $id = (int)$id_hasil;
        $stmt = $conn->prepare("SELECT nis, nama, kelas FROM hasil_ujian WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $siswa = $result->fetch_assoc();
        $stmt->close();
        
        if ($siswa) {
            $stmt = $conn->prepare("INSERT INTO izin_remedi (id_ujian, nis, nama, kelas, diberikan_oleh, approved_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE nama = VALUES(nama), kelas = VALUES(kelas), diberikan_oleh = VALUES(diberikan_oleh), approved_at = NOW()");
            $stmt->bind_param("issss", $id_ujian, $siswa['nis'], $siswa['nama'], $siswa['kelas'], $admin_user);
            $stmt->execute();
            $stmt->close();
            $count++;
        }
    }
    
    header('Location: ?ujian=' . $id_ujian . '&batch_remedi=' . $count);
    exit;
}

if (isset($_POST['batch_remove_remedi']) && isset($_POST['selected_ids']) && isset($_POST['id_ujian_batch'])) {
    $selected_ids = $_POST['selected_ids'];
    $id_ujian = (int)$_POST['id_ujian_batch'];
    
    $count = 0;
    foreach ($selected_ids as $id_hasil) {
        $id = (int)$id_hasil;
        $stmt = $conn->prepare("SELECT nis FROM hasil_ujian WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $siswa = $result->fetch_assoc();
        $stmt->close();
        
        if ($siswa) {
            $stmt = $conn->prepare("DELETE FROM izin_remedi WHERE id_ujian = ? AND nis = ?");
            $stmt->bind_param("is", $id_ujian, $siswa['nis']);
            $stmt->execute();
            $stmt->close();
            $count++;
        }
    }
    
    header('Location: ?ujian=' . $id_ujian . '&batch_removed=' . $count);
    exit;
}

if (isset($_POST['delete_all']) && isset($_POST['id_ujian_batch'])) {
    $id_ujian = (int)$_POST['id_ujian_batch'];

    $stmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id_ujian = ?");
    $stmt->bind_param("i", $id_ujian);
    if ($stmt->execute()) {
        $count = $stmt->affected_rows;
        $stmt->close();
        header('Location: ?ujian=' . $id_ujian . '&deleted_all=' . $count);
        exit;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Rekap Nilai - Admin</title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        .card-body.scrollable-table {
            max-height: 500px;
            overflow-y: auto;
            padding: 0 !important;
        }
        
        .card-body.scrollable-table::-webkit-scrollbar {
            width: 8px;
        }
        
        .card-body.scrollable-table::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .card-body.scrollable-table::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .card-body.scrollable-table::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
            border-radius: 12px;
            padding: 1.25rem;
            color: white;
            text-align: center;
        }
        
        .stat-card.success { background: linear-gradient(135deg, var(--success) 0%, #34d399 100%); }
        .stat-card.warning { background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%); }
        .stat-card.danger { background: linear-gradient(135deg, var(--danger) 0%, #f87171 100%); }
        
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { font-size: 0.875rem; opacity: 0.9; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            align-items: center;
        }
        
        .action-btn-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            text-decoration: none;
            border: none;
            background: none;
            cursor: pointer;
        }
        
        .action-btn-group:hover {
            text-decoration: none;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.2s ease;
            font-size: 1.1rem;
            text-decoration: none;
        }
        
        .action-btn-label {
            font-size: 0.65rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .action-btn-group:hover .action-btn {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .action-btn-delete {
            background: #f3f4f6;
            color: #6b7280 !important;
        }
        
        .action-btn-delete:hover {
            background: #fee2e2;
            color: #dc2626 !important;
        }
        
        .action-btn-remedi {
            background: #e0e7ff;
            color: #4f46e5 !important;
        }
        
        .action-btn-remedi:hover {
            background: #c7d2fe;
            color: #4338ca !important;
        }
        
        .action-btn-remedi.active {
            background: #dcfce7;
            color: #16a34a !important;
        }
        
        .action-btn-remedi.active:hover {
            background: #bbf7d0;
            color: #15803d !important;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        .search-box input {
            padding-left: 36px;
            border-radius: 20px;
            font-size: 0.85rem;
            width: 220px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        
        .search-box input:focus {
            width: 280px;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .table tbody tr.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li>Nilai & Analisis</li>
                <li class="active">Rekap Nilai</li>
            </ul>
            <h3><i class="bi bi-bar-chart-line me-2"></i>Rekap Nilai</h3>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold"><i class="bi bi-file-earmark-text me-1"></i>Pilih Ujian</label>
                        <select name="ujian" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Pilih Ujian --</option>
                            <?php 
                            $ujian_list->data_seek(0);
                            while ($ujian = $ujian_list->fetch_assoc()): 
                            ?>
                            <option value="<?= $ujian['id'] ?>" <?= $selected_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ujian['judul_ujian']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if ($selected_ujian > 0): ?>
                    <div class="col-md-4">
                        <a href="ekspor_excel.php?ujian=<?= $selected_ujian ?>" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($selected_ujian > 0): ?>
        
        <?php if ($stats['total'] > 0): ?>
        <div class="row animate-fade-in">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Peserta</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-value"><?= round($stats['skor_awal_total'] / max($stats['total'], 1), 1) ?></div>
                    <div class="stat-label">Rata-rata Asli</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-value"><?= $stats['rata'] ?></div>
                    <div class="stat-label">Rata-rata Akhir</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card danger">
                    <div class="stat-value"><?= $stats['penalty_total'] ?></div>
                    <div class="stat-label">Total Potongan</div>
                </div>
            </div>
        </div>
        
        <?php 
        // Calculate grade distribution
        $grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
        foreach ($all_results as $r) {
            $skor = $r['total_skor'];
            if ($skor >= 85) $grades['A']++;
            elseif ($skor >= 70) $grades['B']++;
            elseif ($skor >= 55) $grades['C']++;
            elseif ($skor >= 40) $grades['D']++;
            else $grades['E']++;
        }
        ?>
        <div class="row mt-3 animate-fade-in">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex gap-3 flex-wrap justify-content-center">
                            <div class="text-center">
                                <div class="fw-bold text-success" style="font-size: 1.5rem;"><?= $grades['A'] ?></div>
                                <div class="text-muted small">A (85+)</div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold text-primary" style="font-size: 1.5rem;"><?= $grades['B'] ?></div>
                                <div class="text-muted small">B (70-84)</div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold text-info" style="font-size: 1.5rem;"><?= $grades['C'] ?></div>
                                <div class="text-muted small">C (55-69)</div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold text-warning" style="font-size: 1.5rem;"><?= $grades['D'] ?></div>
                                <div class="text-muted small">D (40-54)</div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold text-danger" style="font-size: 1.5rem;"><?= $grades['E'] ?></div>
                                <div class="text-muted small">E (<40)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="GET" id="filterForm">
            <input type="hidden" name="ujian" value="<?= $selected_ujian ?>">
            <div class="card animate-fade-in mt-3">
            <div class="card-header">
                <div class="row g-2 align-items-center">
                    <div class="col-md-auto">
                        <span><i class="bi bi-people me-2"></i>Hasil Ujian</span>
                    </div>
                    <div class="col-md-auto">
                        <select name="kelas" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($kelas_list as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $filter_kelas === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <select name="skor_min" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                            <option value="0" <?php if ($filter_skor_min == 0) echo 'selected'; ?>>Semua Skor</option>
                            <option value="70" <?php if ($filter_skor_min == 70) echo 'selected'; ?>>Skor &gt;= 70</option>
                            <option value="75" <?php if ($filter_skor_min == 75) echo 'selected'; ?>>Skor &gt;= 75</option>
                            <option value="80" <?php if ($filter_skor_min == 80) echo 'selected'; ?>>Skor &gt;= 80</option>
                            <option value="85" <?php if ($filter_skor_min == 85) echo 'selected'; ?>>Skor &gt;= 85</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <select name="sort" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                            <option value="skor_desc" <?php if ($sort_by == 'skor_desc') echo 'selected'; ?>>Skor Tertinggi</option>
                            <option value="skor_asc" <?php if ($sort_by == 'skor_asc') echo 'selected'; ?>>Skor Terendah</option>
                            <option value="nama_asc" <?php if ($sort_by == 'nama_asc') echo 'selected'; ?>>Nama A-Z</option>
                            <option value="nama_desc" <?php if ($sort_by == 'nama_desc') echo 'selected'; ?>>Nama Z-A</option>
                            <option value="kelas_asc" <?php if ($sort_by == 'kelas_asc') echo 'selected'; ?>>Kelas</option>
                            <option value="waktu_desc" <?php if ($sort_by == 'waktu_desc') echo 'selected'; ?>>Terbaru</option>
                            <option value="waktu_asc" <?php if ($sort_by == 'waktu_asc') echo 'selected'; ?>>Terlama</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari...">
                        </div>
                    </div>
                    <div class="col-md-auto ms-auto d-flex gap-2 align-items-center">
                        <span class="badge bg-primary"><?= $stats['total'] ?> peserta</span>
                        <a href="ekspor_excel.php?ujian=<?= $selected_ujian ?>" class="btn btn-success btn-sm">
                            <i class="bi bi-file-excel"></i> Export Excel
                        </a>
                        <button type="button" class="btn btn-danger btn-sm" id="btnHapusSemua" onclick="confirmDeleteAll()" <?= $stats['total'] == 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-trash"></i> Hapus Semua
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body scrollable-table">
                <?php if ($stats['total'] > 0): ?>
                <form method="POST" id="batchForm">
                    <input type="hidden" name="id_ujian_batch" value="<?= $selected_ujian ?>">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;">
                                    <input type="checkbox" id="checkAll" class="form-check-input">
                                </th>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th class="text-center" style="width: 80px;">Skor Asli</th>
                                <th class="text-center" style="width: 80px;">Skor Akhir</th>
                                <th class="text-center" style="width: 80px;">Potongan</th>
                                <th class="text-center" style="width: 140px;">Waktu Submit</th>
                                <th class="text-center" style="width: 80px;">Remedial</th>
                                <th class="text-center" style="width: 70px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($hasil_list as $hasil): 
                            $remedi_data = null;
                            if (in_array($hasil['nis'], $izin_remedi_list)) {
                                $remedi_data = ['approved_at' => $izin_remedi_data[$hasil['nis']]];
                            }
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="selected_ids[]" value="<?= $hasil['id'] ?>" class="checkbox-item">
                                </td>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($hasil['nis']) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($hasil['nama']) ?></td>
                                <td><?= htmlspecialchars($hasil['kelas']) ?></td>
                                <!-- Skor Asli -->
                                <td class="text-center">
                                    <?php 
                                    if ($hasil['skor_awal'] !== null) {
                                        $skor_asli = $hasil['skor_awal'];
                                    } else {
                                        $penalty_calc = min($hasil['jumlah_pelanggaran'] * 10, $hasil['total_skor'] * 0.5);
                                        $skor_asli = $hasil['total_skor'] + $penalty_calc;
                                    }
                                    ?>
                                    <span class="badge bg-info"><?= $skor_asli ?></span>
                                </td>
                                <!-- Skor Akhir -->
                                <td class="text-center">
                                    <span class="badge bg-<?= $hasil['total_skor'] >= $stats['rata'] ? 'success' : 'warning' ?>">
                                        <?= $hasil['total_skor'] ?>
                                    </span>
                                </td>
                                <!-- Potongan -->
                                <td class="text-center">
                                    <?php 
                                    if ($hasil['skor_awal'] !== null) {
                                        $penalty = $hasil['skor_awal'] - $hasil['total_skor'];
                                    } else {
                                        $penalty = min($hasil['jumlah_pelanggaran'] * 10, $hasil['total_skor'] * 0.5);
                                    }
                                    ?>
                                    <?php if ($penalty > 0): ?>
                                        <span class="badge bg-danger"><?= $penalty ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-muted">
                                    <?php if (!empty($hasil['waktu_submit'])): ?>
                                        <?= date('d/m/Y H:i', strtotime($hasil['waktu_submit'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($remedi_data): ?>
                                        <span class="badge bg-success">Remedi</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="detail_jawaban.php?id_hasil=<?= $hasil['id'] ?>" class="btn btn-sm btn-info" title="Lihat Jawaban">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($hasil['has_remedi']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Cabut Remedi"
                                                onclick="removeRemedi(<?= (int)$hasil['id'] ?>, <?= (int)$selected_ujian ?>, '<?= htmlspecialchars(addslashes($hasil['nama'])) ?>')">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-success" title="Beri Remedi"
                                                onclick="giveRemedi(<?= (int)$hasil['id'] ?>, <?= (int)$selected_ujian ?>, '<?= htmlspecialchars(addslashes($hasil['nama'])) ?>')">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-danger" title="Hapus" onclick="deleteRecord(<?= (int)$hasil['id'] ?>, <?= (int)$selected_ujian ?>, '<?= htmlspecialchars(addslashes($hasil['nama'])) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 d-flex gap-2 align-items-center">
                    <span class="text-muted small">Pilih siswa:</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelectAll">Pilih Semua</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">Batal Pilih</button>
                    <span class="text-muted small">dengan:</span>
                    <button type="submit" name="batch_remedi" class="btn btn-sm btn-success" onclick="return confirmBatch('remedi')">
                        <i class="bi bi-arrow-repeat me-1"></i> Beri Remedi
                    </button>
                    <button type="submit" name="batch_remove_remedi" class="btn btn-sm btn-outline-danger" onclick="return confirmBatch('remove')">
                        <i class="bi bi-x-circle me-1"></i> Cabut Remedi
                    </button>
                </div>
                </form>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">Belum ada peserta yang mengerjakan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <div class="card animate-fade-in">
            <div class="card-body text-center py-5">
                <i class="bi bi-folder2-open text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3">Silakan pilih ujian untuk melihat rekap nilai</p>
            </div>
        </div>
        <?php endif; ?>
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
        
        function showToast(message, type = 'success') {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            
            const bgColor = type === 'success' ? 'linear-gradient(135deg, #10b981 0%, #34d399 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
            
            toastContainer.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header" style="background: ${bgColor}; border: none;">
                        <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'} me-2 text-white"></i>
                        <strong class="me-auto text-white">${type === 'success' ? 'Berhasil' : 'Gagal'}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            document.body.appendChild(toastContainer);
            
            const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'), { delay: 3000 });
            toast.show();
            
            toastContainer.querySelector('.toast').addEventListener('hidden.bs.toast', function() {
                toastContainer.remove();
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
            
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('deleted')) {
                showToast('Data berhasil dihapus!', 'success');
                window.history.replaceState({}, document.title, window.location.pathname + '?ujian=' + urlParams.get('ujian'));
            }
            if (urlParams.has('remedi')) {
                showToast('Izin remedi berhasil diberikan!', 'success');
                window.history.replaceState({}, document.title, window.location.pathname + '?ujian=' + urlParams.get('ujian'));
            }
            if (urlParams.has('remedi_removed')) {
                showToast('Izin remedi berhasil dicabut!', 'success');
                window.history.replaceState({}, document.title, window.location.pathname + '?ujian=' + urlParams.get('ujian'));
            }
            if (urlParams.has('batch_remedi')) {
                showToast('Izin remedi berhasil diberikan kepada ' + urlParams.get('batch_remedi') + ' siswa!', 'success');
                window.history.replaceState({}, document.title, window.location.pathname + '?ujian=' + urlParams.get('ujian'));
            }
            if (urlParams.has('batch_removed')) {
                showToast('Izin remedi berhasil dicabut dari ' + urlParams.get('batch_removed') + ' siswa!', 'success');
                window.history.replaceState({}, document.title, window.location.pathname + '?ujian=' + urlParams.get('ujian'));
            }
            
            const deleteModalEl = document.getElementById('deleteModal');
            if (deleteModalEl) {
                const deleteModal = new bootstrap.Modal(deleteModalEl);
                const deleteBtn = document.querySelectorAll('.btn-hapus-hasil');
                
                deleteBtn.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const ujian = this.getAttribute('data-ujian');
                        document.getElementById('deleteLink').href = '?ujian=' + ujian + '&hapus=' + id;
                        deleteModal.show();
                    });
                });
                
                document.getElementById('deleteLink').addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    deleteModal.hide();
                    setTimeout(function() {
                        window.location.href = href;
                    }, 300);
                });
            }
            
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('table tbody tr');
                    rows.forEach(function(row) {
                        const nis = row.cells[2]?.textContent.toLowerCase() || '';
                        const nama = row.cells[3]?.textContent.toLowerCase() || '';
                        const kelas = row.cells[4]?.textContent.toLowerCase() || '';
                        if (query === '' || nis.includes(query) || nama.includes(query) || kelas.includes(query)) {
                            row.classList.remove('hidden');
                        } else {
                            row.classList.add('hidden');
                        }
                    });
                });
            }
            
            const checkAll = document.getElementById('checkAll');
            if (checkAll) {
                checkAll.addEventListener('change', function() {
                    document.querySelectorAll('.checkbox-item').forEach(cb => cb.checked = this.checked);
                });
            }
            
            const btnSelectAll = document.getElementById('btnSelectAll');
            if (btnSelectAll) {
                btnSelectAll.addEventListener('click', function() {
                    document.querySelectorAll('.checkbox-item').forEach(cb => cb.checked = true);
                    document.getElementById('checkAll').checked = true;
                });
            }
            
            const btnDeselectAll = document.getElementById('btnDeselectAll');
            if (btnDeselectAll) {
                btnDeselectAll.addEventListener('click', function() {
                    document.querySelectorAll('.checkbox-item').forEach(cb => cb.checked = false);
                    document.getElementById('checkAll').checked = false;
                });
            }
        });
        
        function confirmBatch(action) {
            const checked = document.querySelectorAll('.checkbox-item:checked');
            if (checked.length === 0) {
                alert('Pilih siswa terlebih dahulu!');
                return false;
            }
            return confirm('Apakah Anda yakin ingin ' + (action === 'remedi' ? 'memberi izin remedi' : 'mencabut izin remedi') + ' kepada ' + checked.length + ' siswa?');
        }

        function confirmDeleteAll() {
            if (!confirm('Apakah Anda yakin ingin menghapus SEMUA hasil ujian ini?\nData yang dihapus tidak dapat dikembalikan.')) {
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'id_ujian_batch';
            inputId.value = '<?= $selected_ujian ?>';
            form.appendChild(inputId);
            const inputAction = document.createElement('input');
            inputAction.type = 'hidden';
            inputAction.name = 'delete_all';
            inputAction.value = '1';
            form.appendChild(inputAction);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
                <div class="modal-header justify-content-center pt-4 pb-0 border-0">
                    <div class="delete-icon-wrapper">
                        <div class="delete-icon">
                            <i class="bi bi-trash-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="modal-body text-center px-4 pb-4">
                    <h4 class="fw-bold mb-2" style="color: #1e293b;">Hapus Data?</h4>
                    <p class="text-muted mb-0">Data yang dihapus tidak dapat dikembalikan. Apakah Anda yakin?</p>
                </div>
                <div class="modal-footer justify-content-center border-0 pb-4">
                    <button type="button" class="btn btn-secondary btn-batal" data-bs-dismiss="modal" style="padding: 10px 30px; border-radius: 25px; font-weight: 500;">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <a href="#" id="deleteLink" class="btn btn-danger btn-hapus" style="padding: 10px 30px; border-radius: 25px; font-weight: 500;">
                        <i class="bi bi-trash-fill me-1"></i> Hapus
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .delete-icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: bounce 0.5s ease;
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .delete-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }
        
        .delete-icon i {
            font-size: 1.5rem;
            color: white;
        }
        
        .btn-batal {
            background: #f1f5f9;
            border: none;
            color: #64748b;
            transition: all 0.2s;
        }
        
        .btn-batal:hover {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-hapus {
            border: none;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
            transition: all 0.2s;
        }
        
        .btn-hapus:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }
    </style>
    
    <!-- Hidden Action Forms (outside batch form to avoid nesting) -->
    <form id="deleteForm" method="GET" style="display:none;">
        <input type="hidden" name="ujian" id="deleteUjian">
        <input type="hidden" name="hapus" id="deleteId">
    </form>
    <form id="remediForm" method="POST" style="display:none;">
        <input type="hidden" name="give_remedi" value="">
        <input type="hidden" name="remove_remedi" value="">
        <input type="hidden" name="id_hasil" value="">
        <input type="hidden" name="id_ujian" value="">
    </form>
    
    <script>
    function deleteRecord(id, ujian, nama) {
        if (confirm('Hapus hasil ujian ' + nama + '?')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteUjian').value = ujian;
            document.getElementById('deleteForm').submit();
        }
    }

    function giveRemedi(id, ujian, nama) {
        if (!confirm('Beri izin remedi untuk ' + nama + '?')) return;
        var form = document.getElementById('remediForm');
        form.querySelector('[name="give_remedi"]').value = '1';
        form.querySelector('[name="remove_remedi"]').value = '';
        form.querySelector('[name="id_hasil"]').value = id;
        form.querySelector('[name="id_ujian"]').value = ujian;
        form.submit();
    }

    function removeRemedi(id, ujian, nama) {
        if (!confirm('Cabut izin remedi untuk ' + nama + '?')) return;
        var form = document.getElementById('remediForm');
        form.querySelector('[name="give_remedi"]').value = '';
        form.querySelector('[name="remove_remedi"]').value = '1';
        form.querySelector('[name="id_hasil"]').value = id;
        form.querySelector('[name="id_ujian"]').value = ujian;
        form.submit();
    }
    </script>
</body>
</html>
