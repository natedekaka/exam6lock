<?php
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/audit_helper.php';

$sekolah = getKonfigurasiSekolah($conn);

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$filter_aksi = isset($_GET['aksi']) ? trim($_GET['aksi']) : '';
$filter_entitas = isset($_GET['entitas']) ? trim($_GET['entitas']) : '';
$filter_admin = isset($_GET['admin']) ? trim($_GET['admin']) : '';
$filter_tgl = isset($_GET['tgl']) ? trim($_GET['tgl']) : '';

$where = [];
$params = [];
$types = '';

if ($filter_aksi !== '') {
    $where[] = 'a.aksi = ?';
    $params[] = $filter_aksi;
    $types .= 's';
}
if ($filter_entitas !== '') {
    $where[] = 'a.entitas = ?';
    $params[] = $filter_entitas;
    $types .= 's';
}
if ($filter_admin !== '') {
    $where[] = 'a.admin_username LIKE ?';
    $params[] = '%' . $filter_admin . '%';
    $types .= 's';
}
if ($filter_tgl !== '') {
    $where[] = 'DATE(a.created_at) = ?';
    $params[] = $filter_tgl;
    $types .= 's';
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$count_sql = "SELECT COUNT(*) as total FROM audit_log a $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = ceil($total / $limit);

$sql = "SELECT a.* FROM audit_log a $where_clause ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bind_params = array_merge($params, [$limit, $offset]);
$bind_types = $types . 'ii';
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

$stmt_aksi = $conn->query("SELECT DISTINCT aksi FROM audit_log ORDER BY aksi");
$aksi_list = $stmt_aksi->fetch_all(MYSQLI_ASSOC);
$stmt_entitas = $conn->query("SELECT DISTINCT entitas FROM audit_log ORDER BY entitas");
$entitas_list = $stmt_entitas->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - <?= htmlspecialchars($sekolah['nama_sekolah'] ?? 'Exam6') ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; padding: 1.5rem 1rem; }
        .sidebar h5 { color: #e0e0e0; font-weight: 600; letter-spacing: 0.5px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #b0b0b0; text-decoration: none; border-radius: 10px; transition: all 0.2s; font-size: 0.9rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar-menu a i { width: 20px; text-align: center; }
        .main-content { padding: 2rem; }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .badge-aksi { font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 20px; }
        .table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border-top: none; }
        .table td { vertical-align: middle; font-size: 0.9rem; }
        .filter-box { background: #f8f9fa; border-radius: 12px; padding: 1rem; }
        @media (max-width: 768px) { .sidebar { min-height: auto; } .main-content { padding: 1rem; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="text-center mb-4">
                <i class="bi bi-shield-check text-white" style="font-size: 2.5rem;"></i>
                <h5 class="mt-2"><i class="bi bi-gear me-1"></i>Admin Panel</h5>
            </div>
            <div class="sidebar-menu">
                <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
                <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
                <a href="bank_soal.php"><i class="bi bi-database-fill"></i> Bank Soal Global</a>
                <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
                <a href="analytics.php"><i class="bi bi-graph-up"></i> Analytics</a>
                <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
                <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
                <a href="kelola_kelas.php"><i class="bi bi-diagram-3-fill"></i> Kelola Kelas</a>
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
                <a href="backup_restore.php"><i class="bi bi-cloud-arrow-up-fill"></i> Backup & Restore</a>
                <?php endif; ?>
                <a href="pengumuman.php"><i class="bi bi-megaphone-fill"></i> Pengumuman</a>
                <a href="audit_log.php" class="active"><i class="bi bi-journal-text"></i> Audit Log</a>
                <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
                <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
            </div>
        </div>
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Audit Log</h4>
                <div>
                    <span class="badge bg-secondary"><?= number_format($total) ?> entri</span>
                </div>
            </div>

            <div class="card p-3 mb-3">
                <form method="GET" class="row g-2 filter-box align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small">Aksi</label>
                        <select name="aksi" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <?php foreach ($aksi_list as $a): ?>
                            <option value="<?= htmlspecialchars($a['aksi']) ?>" <?= $filter_aksi === $a['aksi'] ? 'selected' : '' ?>><?= htmlspecialchars($a['aksi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Entitas</label>
                        <select name="entitas" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <?php foreach ($entitas_list as $e): ?>
                            <option value="<?= htmlspecialchars($e['entitas']) ?>" <?= $filter_entitas === $e['entitas'] ? 'selected' : '' ?>><?= htmlspecialchars($e['entitas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Admin</label>
                        <input type="text" name="admin" class="form-control form-control-sm" placeholder="Cari admin..." value="<?= htmlspecialchars($filter_admin) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tanggal</label>
                        <input type="date" name="tgl" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_tgl) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter me-1"></i>Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="audit_log.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-circle me-1"></i>Reset</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Admin</th>
                                <th>Aksi</th>
                                <th>Entitas</th>
                                <th>ID</th>
                                <th>Detail</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs && $logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                            <tr>
                                <td class="text-nowrap small"><?= htmlspecialchars($log['created_at']) ?></td>
                                <td><?= htmlspecialchars($log['admin_username']) ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'bg-secondary';
                                    if ($log['aksi'] === 'CREATE') $badge_class = 'bg-success';
                                    elseif ($log['aksi'] === 'UPDATE') $badge_class = 'bg-primary';
                                    elseif ($log['aksi'] === 'DELETE') $badge_class = 'bg-danger';
                                    elseif ($log['aksi'] === 'RESET') $badge_class = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge badge-aksi <?= $badge_class ?>"><?= htmlspecialchars($log['aksi']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($log['entitas']) ?></td>
                                <td><?= $log['entitas_id'] ? (int)$log['entitas_id'] : '-' ?></td>
                                <td class="small"><?= htmlspecialchars($log['detail']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox me-1"></i>Tidak ada data audit log
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&aksi=<?= urlencode($filter_aksi) ?>&entitas=<?= urlencode($filter_entitas) ?>&admin=<?= urlencode($filter_admin) ?>&tgl=<?= urlencode($filter_tgl) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&aksi=<?= urlencode($filter_aksi) ?>&entitas=<?= urlencode($filter_entitas) ?>&admin=<?= urlencode($filter_admin) ?>&tgl=<?= urlencode($filter_tgl) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&aksi=<?= urlencode($filter_aksi) ?>&entitas=<?= urlencode($filter_entitas) ?>&admin=<?= urlencode($filter_admin) ?>&tgl=<?= urlencode($filter_tgl) ?>">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
