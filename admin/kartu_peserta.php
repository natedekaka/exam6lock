<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;

$kelas_list = $conn->query("SELECT DISTINCT kelas FROM siswa WHERE is_active = 1 AND kelas IS NOT NULL AND kelas != '' ORDER BY kelas");

$count_sql = "SELECT COUNT(*) as total FROM siswa WHERE is_active = 1";
$count_params = [];
$count_types = '';
if (!empty($filter_kelas)) {
    $count_sql .= " AND kelas = ?";
    $count_params[] = $filter_kelas;
    $count_types .= 's';
}
$st_count = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $st_count->bind_param($count_types, ...$count_params);
}
$st_count->execute();
$total_siswa = (int)$st_count->get_result()->fetch_assoc()['total'];
$st_count->close();

$total_pages = max(1, ceil($total_siswa / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT nis, nama_lengkap, kelas FROM siswa WHERE is_active = 1";
if (!empty($filter_kelas)) {
    $sql .= " AND kelas = ?";
}
$sql .= " ORDER BY kelas, nama_lengkap LIMIT ? OFFSET ?";
$st = $conn->prepare($sql);
if (!empty($filter_kelas)) {
    $st->bind_param("sii", $filter_kelas, $per_page, $offset);
} else {
    $st->bind_param("ii", $per_page, $offset);
}
$st->execute();
$siswa_list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Peserta - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; }
        .table-siswa th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; white-space: nowrap; }
        .table-siswa td { font-size: 0.88rem; vertical-align: middle; }
        .table-siswa .nis-col { font-family: monospace; font-weight: 500; }
        .pagination-info { font-size: 0.85rem; color: #6c757d; }
        .page-link { font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header animate-fade-in">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-custom">
                    <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door-fill me-1"></i>Beranda</a></li>
                    <li class="breadcrumb-item active">Kartu Peserta</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h4 class="fw-bold mb-0"><i class="bi bi-card-text me-2"></i>Kartu Peserta</h4>
            </div>
        </div>

        <div class="container-fluid pb-4">
            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Filter Kelas</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Semua Kelas --</option>
                                <?php while ($k = $kelas_list->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $filter_kelas === $k['kelas'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kelas']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">&nbsp;</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary fs-6"><?= $total_siswa ?> peserta</span>
                                <span class="pagination-info">Hal <?= $page ?> dari <?= $total_pages ?></span>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($total_siswa > 0): ?>
                            <a href="kartu_peserta_cetak.php<?= !empty($filter_kelas) ? '?kelas=' . urlencode($filter_kelas) : '' ?>"
                               class="btn btn-primary btn-cetak-kartu" target="_blank">
                                <i class="bi bi-printer me-2"></i>Cetak Semua (<?= $total_siswa ?>)
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel siswa -->
            <?php if (empty($siswa_list)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>Tidak ada siswa aktif ditemukan.
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover table-siswa mb-0">
                        <thead>
                            <tr>
                                <th width="60">No</th>
                                <th>Username (NIS)</th>
                                <th>Nama Lengkap</th>
                                <th width="120">Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; ?>
                            <?php foreach ($siswa_list as $siswa): ?>
                            <tr>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td class="nis-col"><?= htmlspecialchars($siswa['nis']) ?></td>
                                <td><?= htmlspecialchars($siswa['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                    <small class="text-muted">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_siswa) ?> dari <?= $total_siswa ?> peserta</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= !empty($filter_kelas) ? 'kelas=' . urlencode($filter_kelas) . '&' : '' ?>&page=<?= $page - 1 ?>">Sebelum</a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= !empty($filter_kelas) ? 'kelas=' . urlencode($filter_kelas) . '&' : '' ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= !empty($filter_kelas) ? 'kelas=' . urlencode($filter_kelas) . '&' : '' ?>&page=<?= $page + 1 ?>">Berikut</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
