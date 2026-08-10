<?php
// admin/monitor_ujian.php - Monitor Progress Ujian Aktif

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/audit_helper.php';
$id_ujian_terpilih = isset($_GET['id_ujian']) ? (int)$_GET['id_ujian'] : (isset($_POST['id_ujian']) ? (int)$_POST['id_ujian'] : 0);

// Handle delete violations
if (isset($_POST['delete_violation']) && $id_ujian_terpilih > 0) {
    $delId = (int)$_POST['delete_violation'];
    $stmt = $conn->prepare("DELETE FROM exam_violations WHERE id = ? AND id_ujian = ?");
    $stmt->bind_param("ii", $delId, $id_ujian_terpilih);
    $stmt->execute();
    $stmt->close();
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'VIOLATION', $delId, 'Hapus pelanggaran ID: ' . $delId . ' pada ujian ID: ' . $id_ujian_terpilih);
    header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih");
    exit;
}

if (isset($_POST['delete_selected']) && $id_ujian_terpilih > 0 && !empty($_POST['selected_ids'])) {
    $ids = array_filter(array_map('intval', $_POST['selected_ids']));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("DELETE FROM exam_violations WHERE id IN ($placeholders) AND id_ujian = ?");
        $params = array_merge($ids, [$id_ujian_terpilih]);
        $stmt->bind_param($types . 'i', ...$params);
        $stmt->execute();
        $stmt->close();
    }
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'VIOLATION', 0, 'Hapus ' . count($ids) . ' pelanggaran terpilih pada ujian ID: ' . $id_ujian_terpilih);
    header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih");
    exit;
}

if (isset($_POST['delete_all']) && $id_ujian_terpilih > 0) {
    $stmt = $conn->prepare("DELETE FROM exam_violations WHERE id_ujian = ?");
    $stmt->bind_param("i", $id_ujian_terpilih);
    $stmt->execute();
    $stmt->close();
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'VIOLATION', 0, 'Hapus semua pelanggaran pada ujian ID: ' . $id_ujian_terpilih);
    header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih");
    exit;
}

// Handle delete students from progress (hasil_ujian)
if (isset($_POST['delete_student']) && $id_ujian_terpilih > 0) {
    $delNis = $_POST['delete_student'];
    $stmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id_ujian = ? AND nis = ?");
    $stmt->bind_param("is", $id_ujian_terpilih, $delNis);
    if ($stmt->execute()) {
        // Also delete from jawaban_sementara
        $stmt2 = $conn->prepare("DELETE FROM jawaban_sementara WHERE id_ujian = ? AND nis = ?");
        $stmt2->bind_param("is", $id_ujian_terpilih, $delNis);
        $stmt2->execute();
        $stmt2->close();
        logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'SISWA', $id_ujian_terpilih, 'Hapus siswa NIS: ' . $delNis . ' dari ujian ID: ' . $id_ujian_terpilih);
        header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih&student_deleted=1");
        exit;
    }
    $stmt->close();
}

// Handle delete selected students
if (isset($_POST['delete_selected_students']) && $id_ujian_terpilih > 0 && !empty($_POST['selected_nis'])) {
    $nis_list = array_filter(array_map('trim', $_POST['selected_nis']));
    if (!empty($nis_list)) {
        foreach ($nis_list as $nis) {
            $stmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id_ujian = ? AND nis = ?");
            $stmt->bind_param("is", $id_ujian_terpilih, $nis);
            $stmt->execute();
            $stmt->close();
            
            $stmt2 = $conn->prepare("DELETE FROM jawaban_sementara WHERE id_ujian = ? AND nis = ?");
            $stmt2->bind_param("is", $id_ujian_terpilih, $nis);
            $stmt2->execute();
            $stmt2->close();
        }
        logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'SISWA', $id_ujian_terpilih, 'Hapus ' . count($nis_list) . ' siswa terpilih dari ujian ID: ' . $id_ujian_terpilih);
        header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih&students_deleted=" . count($nis_list));
        exit;
    }
}

// Handle delete all students
if (isset($_POST['delete_all_students']) && $id_ujian_terpilih > 0) {
    $stmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id_ujian = ?");
    $stmt->bind_param("i", $id_ujian_terpilih);
    $stmt->execute();
    $stmt->close();
    
    $stmt2 = $conn->prepare("DELETE FROM jawaban_sementara WHERE id_ujian = ?");
    $stmt2->bind_param("i", $id_ujian_terpilih);
    $stmt2->execute();
    $stmt2->close();
    
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'SISWA', $id_ujian_terpilih, 'Hapus semua siswa dari ujian ID: ' . $id_ujian_terpilih);
    header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih&all_students_deleted=1");
    exit;
}

// Handle reset student exam (keep saved answers, delete hasil_ujian)
if (isset($_POST['reset_student']) && $id_ujian_terpilih > 0) {
    $resetNis = $_POST['reset_student'];
    
    // Delete from hasil_ujian (so student can retake)
    $stmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id_ujian = ? AND nis = ?");
    $stmt->bind_param("is", $id_ujian_terpilih, $resetNis);
    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'RESET', 'SISWA', $id_ujian_terpilih, 'Reset ujian siswa NIS: ' . $resetNis . ' pada ujian ID: ' . $id_ujian_terpilih);
        // Note: we do NOT delete from jawaban_sementara so student can continue
        header("Location: monitor_ujian.php?id_ujian=$id_ujian_terpilih&student_reset=1");
        exit;
    }
    $stmt->close();
}

$sekolah = getKonfigurasiSekolah($conn);


$ujian_list = $conn->query("SELECT id, judul_ujian, status, waktu_tersedia FROM ujian ORDER BY id DESC LIMIT 20");
$ujian_arr = [];
while ($row = $ujian_list->fetch_assoc()) {
    $ujian_arr[] = $row;
}

$ujian_list = $conn->query("SELECT id, judul_ujian, status, waktu_tersedia FROM ujian ORDER BY id DESC LIMIT 20");
$ujian_arr = [];
while ($row = $ujian_list->fetch_assoc()) {
    $ujian_arr[] = $row;
}

$progress_data = [];
$total_soal = 0;
if ($id_ujian_terpilih > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total_soal FROM soal WHERE id_ujian = ?");
    $stmt->bind_param("i", $id_ujian_terpilih);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_soal = $result->fetch_assoc()['total_soal'] ?? 0;
    $stmt->close();
    
    // Check if jawaban_sementara has ip_address column
    $hasIpCol = $db->columnExists('jawaban_sementara', 'ip_address');
    
    // Ambil data dari jawaban_sementara (sedang ujian)
    if ($hasIpCol) {
        $stmt = $conn->prepare("SELECT nis, nama, kelas, answers, updated_at, ip_address, device_fingerprint FROM jawaban_sementara WHERE id_ujian = ?");
    } else {
        $stmt = $conn->prepare("SELECT nis, nama, kelas, answers, updated_at FROM jawaban_sementara WHERE id_ujian = ?");
    }
    $stmt->bind_param("i", $id_ujian_terpilih);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $answers = json_decode($row['answers'] ?? '{}', true);
        $answered_count = is_array($answers) ? count($answers) :0;
        $progress_data[$row['nis']] = [
            'nis' => $row['nis'],
            'nama' => $row['nama'],
            'kelas' => $row['kelas'],
            'answered' => $answered_count,
            'last_active' => $row['updated_at'],
            'status' => 'belum_submit',
            'ip_address' => $hasIpCol ? ($row['ip_address'] ?? '-') : '-',
            'device_fingerprint' => $hasIpCol ? ($row['device_fingerprint'] ?? '-') : '-'
        ];
    }
    $stmt->close();
    
    // Check if hasil_ujian has ip_address column
    $hasIpCol2 = $db->columnExists('hasil_ujian', 'ip_address');
    
    // Ambil data dari hasil_ujian (sudah submit)
    if ($hasIpCol2) {
        $stmt = $conn->prepare("SELECT nis, nama, kelas, total_skor, waktu_submit, ip_address, device_fingerprint FROM hasil_ujian WHERE id_ujian = ?");
    } else {
        $stmt = $conn->prepare("SELECT nis, nama, kelas, total_skor, waktu_submit FROM hasil_ujian WHERE id_ujian = ?");
    }
    $stmt->bind_param("i", $id_ujian_terpilih);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $nis = $row['nis'];
        $ip = $hasIpCol2 ? ($row['ip_address'] ?? '-') : '-';
        $fp = $hasIpCol2 ? ($row['device_fingerprint'] ?? '-') : '-';
        if (isset($progress_data[$nis])) {
            $progress_data[$nis]['status'] = 'sudah_submit';
            $progress_data[$nis]['skor'] = $row['total_skor'];
            $progress_data[$nis]['ip_address'] = $ip;
            $progress_data[$nis]['device_fingerprint'] = $fp;
        } else {
            $progress_data[$nis] = [
                'nis' => $nis,
                'nama' => $row['nama'],
                'kelas' => $row['kelas'],
                'answered' => $total_soal,
                'last_active' => $row['waktu_submit'],
                'status' => 'sudah_submit',
                'skor' => $row['total_skor'],
                'ip_address' => $ip,
                'device_fingerprint' => $fp
            ];
        }
    }
    $stmt->close();
}
$violation_data = [];
if ($id_ujian_terpilih > 0) {
    // Get violations with student names from both hasil_ujian and jawaban_sementara
    $sql = "SELECT v.id, v.nis, v.jenis_violation, v.detail, v.created_at, 
                   COALESCE(h.nama, js.nama) as nama, 
                   COALESCE(h.kelas, js.kelas) as kelas
            FROM exam_violations v
            LEFT JOIN hasil_ujian h ON v.nis = h.nis AND v.id_ujian = h.id_ujian
            LEFT JOIN jawaban_sementara js ON v.nis = js.nis AND v.id_ujian = js.id_ujian
            WHERE v.id_ujian = $id_ujian_terpilih
            ORDER BY v.created_at DESC";
    
    $result = $conn->query($sql);
    if (!$result) {
        echo "<!-- SQL Error: " . $conn->error . " -->";
    } else {
        while ($row = $result->fetch_assoc()) {
            $violation_data[] = $row;
        }
    }
}
$violation_summary = [];
foreach ($violation_data as $v) {
    $nis = $v['nis'];
    if (!isset($violation_summary[$nis])) {
        $violation_summary[$nis] = ['nama' => $v['nama'] ?? '', 'kelas' => $v['kelas'] ?? '', 'count' => 0, 'types' => []];
    }
    $violation_summary[$nis]['count']++;
    $violation_summary[$nis]['types'][] = $v['jenis_violation'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Ujian - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/poppins.css" rel="stylesheet">
    <style>
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-active { background: #10b981; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .progress-bar-mini { height: 6px; border-radius: 3px; background: #e5e7eb; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); }
    </style>
</head>
<body>
    <div class="d-flex">
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header-with-breadcrumb">
                <ul class="breadcrumb-custom">
                    <li><a href="index.php"><i class="bi bi-house"></i> Dashboard</a></li>
                    <li>Nilai &amp; Analisis</li>
                    <li class="active">Monitor Ujian</li>
                </ul>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3><i class="bi bi-display"></i> Monitor Progress Ujian</h3>
                    <button onclick="location.reload()" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Pilih Ujian</label>
                            <select name="id_ujian" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Pilih Ujian --</option>
                                <?php foreach ($ujian_arr as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $id_ujian_terpilih == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['judul_ujian']) ?> (<?= $u['status'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <?php if ($id_ujian_terpilih > 0): ?>
                            <div class="badge bg-primary fs-6">
                                <i class="bi bi-question-circle"></i> Total <?= $total_soal ?> soal
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php if ($id_ujian_terpilih > 0 && !empty($progress_data)): ?>
        <div class="card mt-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-people"></i> Progress Semua Siswa (<?= count($progress_data) ?>)</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="form-control form-control-sm" id="searchProgress" placeholder="Cari NIS/Nama..." style="width: 200px;" onkeyup="filterProgress()">
                    </div>
                    <select class="form-select form-select-sm" id="filterStatus" onchange="filterProgress()" style="width: auto;">
                        <option value="">Semua Status</option>
                        <option value="submit">Sudah Submit</option>
                        <option value="lagi">Lagi Ujian</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleSelectAllProgress()">
                        <i class="bi bi-check-all"></i> <span id="selectAllProgressText">Pilih Semua</span>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteSelectedStudents()" id="btnDeleteSelectedProgress" style="display:none;">
                        <i class="bi bi-trash"></i> Hapus Terpilih (<span id="selectedStudentCount">0</span>)
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAllStudents()">
                        <i class="bi bi-trash-fill"></i> Hapus Semua
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <form method="POST" id="progressBatchForm">
                    <input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">
                <table class="table table-hover mb-0" id="progressTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAllProgress" onchange="toggleSelectAllProgressCheckbox()"></th>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Jawaban</th>
                            <th>Progress</th>
                            <th>IP Address</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Skor</th>
                            <th>Last Active</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($progress_data as $p): ?>
                        <tr data-nis="<?= htmlspecialchars($p['nis']) ?>" data-nama="<?= htmlspecialchars($p['nama']) ?>" data-status="<?= $p['status'] ?>">
                            <td>
                                <input type="checkbox" class="progress-checkbox" value="<?= htmlspecialchars($p['nis']) ?>" onchange="updateDeleteButtonProgress()">
                            </td>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($p['nis']) ?></strong></td>
                            <td><?= htmlspecialchars($p['nama']) ?></td>
                            <td><?= htmlspecialchars($p['kelas']) ?></td>
                            <td>
                                <span class="badge bg-<?= $p['answered'] >= $total_soal ? 'success' : 'primary' ?>">
                                    <?= $p['answered'] ?>/<?= $total_soal ?>
                                </span>
                            </td>
                            <td style="width: 150px;">
                                <?php $progress_pct = round(($p['answered'] / max(1, $total_soal)) * 100); ?>
                                <div class="progress-bar-mini">
                                    <div class="progress-bar-fill" style="width: <?= $progress_pct ?>%; background: <?= $progress_pct >= 100 ? 'linear-gradient(90deg, #10b981, #059669)' : 'linear-gradient(90deg, #667eea, #764ba2)' ?>;"></div>
                                </div>
                                <small class="text-muted"><?= $progress_pct ?>%</small>
                            </td>
                            <td>
                                <?php if (($p['ip_address'] ?? '-') !== '-'): ?>
                                <small class="font-monospace" title="<?= htmlspecialchars($p['ip_address']) ?>" style="cursor: pointer;">
                                    <?= htmlspecialchars(substr($p['ip_address'], 0, 15)) ?><?= strlen($p['ip_address']) > 15 ? '...' : '' ?>
                                </small>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($p['device_fingerprint'] ?? '-') !== '-'): ?>
                                <small class="font-monospace" title="<?= htmlspecialchars($p['device_fingerprint']) ?>" style="cursor: pointer;">
                                    <?= htmlspecialchars(substr($p['device_fingerprint'], 0, 8)) ?>...
                                </small>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'sudah_submit'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Submit</span>
                                <?php else: ?>
                                <span class="badge bg-warning"><span class="status-dot status-active me-1"></span>Lagi Ujian</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($p['skor'])): ?>
                                <strong class="<?= $p['skor'] >= 70 ? 'text-success' : 'text-warning' ?>"><?= $p['skor'] ?></strong>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('H:i', strtotime($p['last_active'] ?? date('Y-m-d H:i:s'))) ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteStudent('<?= htmlspecialchars($p['nis']) ?>')" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="resetStudent('<?= htmlspecialchars($p['nis']) ?>')" title="Reset Ujian">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
            </div>
        </div>
        <?php elseif ($id_ujian_terpilih > 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">Belum ada siswa yang memulai ujian ini</p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($id_ujian_terpilih > 0 && !empty($violation_data)): ?>
            <div class="card mt-4 border-danger" id="violationCard">
                <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Pelanggaran Tersedia (<span id="violationCount"><?= count($violation_data) ?></span>)</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" class="form-control form-control-sm" id="searchViolation" placeholder="Cari NIS/Nama/Jenis..." style="width:200px;" onkeyup="filterViolations()">
                        <button class="btn btn-sm btn-light border-white text-danger" onclick="deleteAllViolations()" title="Hapus Semua"><i class="bi bi-trash"></i> Hapus Semua</button>
                    </div>
                </div>
                <div class="card-body py-2 d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-danger" onclick="toggleSelectAll()"><i class="bi bi-check-all"></i> Pilih Semua</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteSelectedViolations()" id="btnDeleteSelected" style="display:none;"><i class="bi bi-trash"></i> Hapus Terpilih (<span id="selectedCount">0</span>)</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="violationTable">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" id="selectAllViolations" onchange="toggleSelectAll()"></th>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Jenis Pelanggaran</th>
                                <th>Detail</th>
                                <th>Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($violation_data as $v): ?>
                            <tr class="<?= $v['jenis_violation'] === 'tab_switch' ? 'table-warning' : '' ?>" data-nis="<?= htmlspecialchars($v['nis']) ?>" data-nama="<?= htmlspecialchars($v['nama'] ?? '') ?>" data-jenis="<?= htmlspecialchars($v['jenis_violation']) ?>">
                                <td><input type="checkbox" class="violation-checkbox" value="<?= (int)$v['id'] ?>" onchange="updateDeleteButton()"></td>
                                <td class="row-no"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($v['nis']) ?></td>
                                <td><?= htmlspecialchars($v['nama'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($v['kelas'] ?? '-') ?></td>
                                <td>
                                    <?php 
                                        $badge_map = [
                                            'tab_switch' => 'bg-warning',
                                            'idle_too_long' => 'bg-info',
                                            'window_blur' => 'bg-secondary',
                                            'right_click' => 'bg-dark',
                                            'copy_paste' => 'bg-primary'
                                        ];
                                        $badge_class = $badge_map[$v['jenis_violation']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($v['jenis_violation']) ?></span>
                                </td>
                                <td><small><?= htmlspecialchars($v['detail'] ?? '-') ?></small></td>
                                <td><small><?= date('H:i:s', strtotime($v['created_at'])) ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteViolation(<?= (int)$v['id'] ?>, this)" title="Hapus"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($violation_summary)): ?>
            <div class="card mt-3">
                <div class="card-body py-2">
                    <h6 class="mb-2"><i class="bi bi-person-x"></i> Ringkasan per Siswa</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($violation_summary as $nis => $vs): ?>
                        <span class="badge bg-light text-dark p-2">
                            <strong><?= htmlspecialchars($nis) ?></strong> 
                            <?= htmlspecialchars($vs['nama'] ?? '') ?> 
                            - <?= $vs['count'] ?>x
                            <small class="text-muted">(<?= implode(', ', array_unique($vs['types'])) ?>)</small>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        // Search violations table
        function filterViolations() {
            const q = document.getElementById('searchViolation').value.toLowerCase();
            const rows = document.querySelectorAll('#violationTable tbody tr');
            let visible = 0;
            rows.forEach(row => {
                const nis = (row.dataset.nis || '').toLowerCase();
                const nama = (row.dataset.nama || '').toLowerCase();
                const jenis = (row.dataset.jenis || '').toLowerCase();
                const match = nis.includes(q) || nama.includes(q) || jenis.includes(q) || q === '';
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('violationCount').textContent = visible;
        }

        // Select/Deselect all violations
        let allSelected = false;
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.violation-checkbox');
            allSelected = !allSelected; // Toggle state
            checkboxes.forEach(cb => {
                cb.checked = allSelected;
                cb.closest('tr').style.background = allSelected ? '#fff3cd' : '';
            });
            updateDeleteButton();
            
            // Update button text
            const btn = document.querySelector('[onclick="toggleSelectAll()"]');
            if (btn) {
                btn.innerHTML = allSelected ? 
                    '<i class="bi bi-x-circle"></i> Batal Pilih' : 
                    '<i class="bi bi-check-all"></i> Pilih Semua';
            }
        }

        // Update delete selected button
        function updateDeleteButton() {
            const checked = document.querySelectorAll('.violation-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = checked;
            document.getElementById('btnDeleteSelected').style.display = checked > 0 ? 'inline-block' : 'none';
        }

        // Delete single violation
        function deleteViolation(id) {
            if (!confirm('Hapus pelanggaran ini?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_violation" value="' + id + '">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            document.body.appendChild(form);
            form.submit();
        }

        // Delete selected violations
        function deleteSelectedViolations() {
            const checked = document.querySelectorAll('.violation-checkbox:checked');
            if (checked.length === 0) return;
            if (!confirm('Hapus ' + checked.length + ' pelanggaran terpilih?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_selected" value="1">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            checked.forEach(cb => {
                form.innerHTML += '<input type="hidden" name="selected_ids[]" value="' + cb.value + '">';
            });
            document.body.appendChild(form);
            form.submit();
        }

        // Delete all violations
        function deleteAllViolations() {
            if (!confirm('Hapus SEMUA pelanggaran untuk ujian ini?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_all" value="1">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            document.body.appendChild(form);
            form.submit();
        }

        // Highlight on checkbox change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('violation-checkbox')) {
                e.target.closest('tr').style.background = e.target.checked ? '#fff3cd' : '';
            }
        });
        
        // Filter Progress table
        function filterProgress() {
            const search = document.getElementById('searchProgress').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const rows = document.querySelectorAll('#progressTable tbody tr');
            
            rows.forEach(row => {
                const nis = (row.dataset.nis || '').toLowerCase();
                const nama = (row.dataset.nama || '').toLowerCase();
                const status = (row.dataset.status || '').toLowerCase();
                
                const matchSearch = !search || nis.includes(search) || nama.includes(search);
                const matchStatus = !statusFilter || status.includes(statusFilter);
                
                row.style.display = (matchSearch && matchStatus) ? '' : 'none';
            });
        }
        
        // Toggle select all for progress table (button version)
        let allProgressSelected = false;
        function toggleSelectAllProgress() {
            const checkboxes = document.querySelectorAll('.progress-checkbox');
            allProgressSelected = !allProgressSelected;
            checkboxes.forEach(cb => {
                cb.checked = allProgressSelected;
                cb.closest('tr').style.background = allProgressSelected ? '#e7f3ff' : '';
            });
            updateDeleteButtonProgress();
            
            // Update button text
            const btnText = document.getElementById('selectAllProgressText');
            if (btnText) {
                btnText.textContent = allProgressSelected ? 'Batal Pilih' : 'Pilih Semua';
            }
        }
        
        // Toggle select all for progress table (checkbox version)
        function toggleSelectAllProgressCheckbox() {
            const checkAll = document.getElementById('checkAllProgress');
            const checkboxes = document.querySelectorAll('.progress-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkAll.checked;
                cb.closest('tr').style.background = checkAll.checked ? '#e7f3ff' : '';
            });
            updateDeleteButtonProgress();
        }
        
        // Update delete selected button for progress
        function updateDeleteButtonProgress() {
            const checked = document.querySelectorAll('.progress-checkbox:checked').length;
            document.getElementById('selectedStudentCount').textContent = checked;
            document.getElementById('btnDeleteSelectedProgress').style.display = checked > 0 ? 'inline-block' : 'none';
        }
        
        // Delete single student
        function deleteStudent(nis) {
            if (!confirm('Hapus hasil ujian untuk NIS: ' + nis + '?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_student" value="' + nis + '">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            document.body.appendChild(form);
            form.submit();
        }
        
        // Delete selected students
        function deleteSelectedStudents() {
            const checked = document.querySelectorAll('.progress-checkbox:checked');
            if (checked.length === 0) return;
            if (!confirm('Hapus ' + checked.length + ' siswa terpilih?')) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_selected_students" value="1">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            checked.forEach(cb => {
                form.innerHTML += '<input type="hidden" name="selected_nis[]" value="' + cb.value + '">';
            });
            document.body.appendChild(form);
            form.submit();
        }
        
        // Delete all students
        function deleteAllStudents() {
            if (!confirm('Hapus SEMUA hasil ujian untuk ujian ini?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_all_students" value="1">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            document.body.appendChild(form);
            form.submit();
        }
        
        // Reset student exam (keep saved answers, delete hasil_ujian)
        function resetStudent(nis) {
            if (!confirm('Reset ujian untuk NIS: ' + nis + '?\nSiswa dapat mengerjakan ulang dengan jawaban tersimpan.')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="reset_student" value="' + nis + '">' +
                            '<input type="hidden" name="id_ujian" value="<?= $id_ujian_terpilih ?>">';
            document.body.appendChild(form);
            form.submit();
        }
        
        // Highlight on checkbox change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('progress-checkbox')) {
                e.target.closest('tr').style.background = e.target.checked ? '#e7f3ff' : '';
                updateDeleteButtonProgress();
            }
        });
    </script>
</body>
</html>