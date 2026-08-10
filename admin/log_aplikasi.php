<?php
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/log_helper.php';

$sekolah = getKonfigurasiSekolah($conn);

// Check if log_entries table exists
$tablesExist = $conn->query("SHOW TABLES LIKE 'log_entries'");
$tableReady = $tablesExist && $tablesExist->num_rows > 0;

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$filter_level = isset($_GET['level']) ? trim($_GET['level']) : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_dari = isset($_GET['dari']) ? trim($_GET['dari']) : '';
$filter_sampai = isset($_GET['sampai']) ? trim($_GET['sampai']) : '';

$message = '';
$message_type = '';

// Handle log clearing (super_admin only)
$is_super = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
if ($is_super && isset($_GET['clear']) && $_GET['clear'] === '1' && $tableReady) {
    if ($conn->query("DELETE FROM log_entries") === true) {
        $message = 'Semua log telah dihapus.';
        $message_type = 'success';
    } else {
        $message = 'Gagal menghapus log.';
        $message_type = 'danger';
    }
}

if (!$tableReady) {
    $message = 'Tabel log_entries belum ada. Jalankan migrasi 11_error_security_logging.sql.';
    $message_type = 'warning';
}

$logs = [];
$total = 0;
$total_pages = 1;

if ($tableReady) {
    $where = [];
    $params = [];
    $types = '';

    if ($filter_level !== '') {
        $where[] = 'level = ?';
        $params[] = $filter_level;
        $types .= 's';
    }

    if ($filter_search !== '') {
        $where[] = 'message LIKE ?';
        $params[] = '%' . $filter_search . '%';
        $types .= 's';
    }

    if ($filter_dari !== '') {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $filter_dari;
        $types .= 's';
    }

    if ($filter_sampai !== '') {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $filter_sampai;
        $types .= 's';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM log_entries {$whereClause}";
    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalRow = $countResult->fetch_assoc();
    $total = $totalRow['total'];
    $stmt->close();

    $total_pages = max(1, ceil($total / $limit));

    // Fetch logs
    $sql = "SELECT * FROM log_entries {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aplikasi - <?= htmlspecialchars($sekolah['nama_sekolah'] ?? 'Exam6') ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li class="active">Log Aplikasi</li>
            </ul>
            <h3><i class="bi bi-bug me-2"></i>Log Aplikasi <span class="badge bg-secondary ms-2"><?= number_format($total) ?> entri</span></h3>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body filter-box">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small">Level</label>
                        <select name="level" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="error" <?= $filter_level === 'error' ? 'selected' : '' ?>>Error</option>
                            <option value="security" <?= $filter_level === 'security' ? 'selected' : '' ?>>Security</option>
                            <option value="warning" <?= $filter_level === 'warning' ? 'selected' : '' ?>>Warning</option>
                            <option value="info" <?= $filter_level === 'info' ? 'selected' : '' ?>>Info</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Cari</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari di pesan..." value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dari Tanggal</label>
                        <input type="date" name="dari" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_dari) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Sampai Tanggal</label>
                        <input type="date" name="sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_sampai) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-filter me-1"></i>Filter</button>
                        <a href="log_aplikasi.php" class="btn btn-outline-secondary btn-sm w-100 mt-1"><i class="bi bi-x-circle me-1"></i>Reset</a>
                    </div>
                    <?php if ($is_super && $tableReady): ?>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="clearLogs()" title="Hapus semua log"><i class="bi bi-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabel log -->
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Level</th>
                            <th>Pesan</th>
                            <th>User</th>
                            <th>IP</th>
                            <th>Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$tableReady): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-exclamation-triangle me-1"></i>Tabel log_entries belum tersedia
                            </td>
                        </tr>
                        <?php elseif (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap small"><?= htmlspecialchars($log['created_at']) ?></td>
                            <td>
                                <?php
                                $levelClass = 'bg-secondary';
                                if ($log['level'] === 'error') $levelClass = 'bg-danger';
                                elseif ($log['level'] === 'security') $levelClass = 'bg-warning text-dark';
                                elseif ($log['level'] === 'warning') $levelClass = 'bg-warning text-dark';
                                elseif ($log['level'] === 'info') $levelClass = 'bg-info text-dark';
                                ?>
                                <span class="badge badge-level <?= $levelClass ?>"><?= htmlspecialchars($log['level']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($log['message']) ?></td>
                            <td class="small text-muted">
                                <?= $log['user_type'] ? htmlspecialchars($log['user_type']) . ' #' . (int)$log['user_id'] : '-' ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                            <td class="log-context"><?= htmlspecialchars($log['context'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox me-1"></i>Tidak ada log ditemukan
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
                    <a class="page-link" href="?page=<?= $page - 1 ?>&level=<?= urlencode($filter_level) ?>&search=<?= urlencode($filter_search) ?>&dari=<?= urlencode($filter_dari) ?>&sampai=<?= urlencode($filter_sampai) ?>">&laquo;</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&level=<?= urlencode($filter_level) ?>&search=<?= urlencode($filter_search) ?>&dari=<?= urlencode($filter_dari) ?>&sampai=<?= urlencode($filter_sampai) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&level=<?= urlencode($filter_level) ?>&search=<?= urlencode($filter_search) ?>&dari=<?= urlencode($filter_dari) ?>&sampai=<?= urlencode($filter_sampai) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        function clearLogs() {
            if (confirm('Yakin ingin menghapus SEMUA log aplikasi? Tindakan ini tidak dapat dibatalkan.')) {
                window.location.href = 'log_aplikasi.php?clear=1';
            }
        }
    </script>
</body>
</html>
