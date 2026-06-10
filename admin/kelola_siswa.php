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
require_once '../config/audit_helper.php';

$sekolah = getKonfigurasiSekolah($conn);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nis = trim($_POST['nis'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $kelas = trim($_POST['kelas'] ?? '');
        $jurusan_id = !empty($_POST['jurusan_id']) ? (int)$_POST['jurusan_id'] : null;
        $email = trim($_POST['email'] ?? '');
        $edit_id = $action === 'edit' ? (int)($_POST['edit_id'] ?? 0) : 0;

        if (empty($nis) || empty($nama_lengkap) || empty($kelas)) {
            $message = 'NIS, Nama, dan Kelas wajib diisi!';
            $message_type = 'danger';
        } else {
            if ($edit_id) {
                // EDIT: update existing
                $check = $conn->prepare("SELECT id FROM siswa WHERE nis = ? AND id != ?");
                $check->bind_param("si", $nis, $edit_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $message = 'NIS sudah digunakan siswa lain!';
                    $message_type = 'danger';
                } else {
                    $stmt = $conn->prepare("UPDATE siswa SET nis=?, nama_lengkap=?, kelas=?, jurusan_id=?, email=? WHERE id=?");
                    $stmt->bind_param("ssssii", $nis, $nama_lengkap, $kelas, $jurusan_id, $email, $edit_id);
                    if ($stmt->execute()) {
                        $message = 'Data siswa berhasil diupdate.';
                        $message_type = 'success';
                        logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'update', 'siswa', $edit_id, "Update siswa: $nis - $nama_lengkap");
                    }
                    $stmt->close();
                }
                $check->close();
            } else {
                // ADD new
                $check = $conn->prepare("SELECT id FROM siswa WHERE nis = ?");
                $check->bind_param("s", $nis);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $message = 'NIS sudah terdaftar!';
                    $message_type = 'danger';
                } else {
                    $hashed = password_hash($nis, PASSWORD_DEFAULT); // default password = NIS
                    $stmt = $conn->prepare("INSERT INTO siswa (nis, nama_lengkap, password, password_change_required, kelas, jurusan_id, email) VALUES (?, ?, ?, 1, ?, ?, ?)");
                    $stmt->bind_param("ssssis", $nis, $nama_lengkap, $hashed, $kelas, $jurusan_id, $email);
                    if ($stmt->execute()) {
                        $new_id = $stmt->insert_id;
                        $message = 'Siswa berhasil ditambahkan. Password default: NIS. Siswa wajib ganti password saat login pertama.';
                        $message_type = 'success';
                        logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'create', 'siswa', $new_id, "Tambah siswa: $nis - $nama_lengkap");
                    }
                    $stmt->close();
                }
                $check->close();
            }
        }
    }

    // TOGGLE ACTIVE
    elseif ($action === 'toggle_active') {
        $id = (int)($_POST['siswa_id'] ?? 0);
        $current = (int)($_POST['current_status'] ?? 0);
        $new_status = $current ? 0 : 1;
        $stmt = $conn->prepare("UPDATE siswa SET is_active=? WHERE id=?");
        $stmt->bind_param("ii", $new_status, $id);
        if ($stmt->execute()) {
            $message = 'Status siswa berhasil diubah.';
            $message_type = 'success';
            logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'update', 'siswa', $id, ($new_status ? 'Aktifkan' : 'Nonaktifkan') . " siswa ID: $id");
        }
        $stmt->close();
    }

    // RESET PASSWORD
    elseif ($action === 'reset_password') {
        $id = (int)($_POST['siswa_id'] ?? 0);
        $stmt = $conn->prepare("SELECT nis FROM siswa WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $new_hash = password_hash($row['nis'], PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE siswa SET password=?, password_change_required=1, remember_token=NULL WHERE id=?");
            $stmt2->bind_param("si", $new_hash, $id);
            if ($stmt2->execute()) {
                $message = 'Password siswa direset ke NIS.';
                $message_type = 'success';
                logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'reset_password', 'siswa', $id, "Reset password siswa: {$row['nis']}");
            }
            $stmt2->close();
        }
        $stmt->close();
    }

    // IMPORT CSV
    elseif ($action === 'import_csv' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        $imported = 0;
        $skipped = 0;
        $errors = [];

        if ($handle) {
            // Read header row to detect format
            $header = fgetcsv($handle);
            if (!$header) {
                $message = 'File CSV kosong atau tidak valid.';
                $message_type = 'danger';
            } else {
                // Normalize header names
                $h = array_map('strtolower', array_map('trim', $header));
                // Map expected columns
                $idx_nis = array_search('nis', $h);
                $idx_nama = array_search('nama', $h) ?? array_search('nama_lengkap', $h) ?? array_search('nama lengkap', $h);
                $idx_kelas = array_search('kelas', $h);
                $idx_jurusan = array_search('jurusan', $h);
                $idx_email = array_search('email', $h);

                if ($idx_nis === false || $idx_nama === false || $idx_kelas === false) {
                    $message = 'CSV harus memiliki kolom: NIS, Nama, Kelas. Kolom ditemukan: ' . implode(', ', $h);
                    $message_type = 'danger';
                } else {
                    while (($row = fgetcsv($handle)) !== false) {
                        $nis = trim($row[$idx_nis] ?? '');
                        $nama = trim($row[$idx_nama] ?? '');
                        $kelas = trim($row[$idx_kelas] ?? '');
                        $jurusan_str = $idx_jurusan !== false ? trim($row[$idx_jurusan] ?? '') : '';
                        $email = $idx_email !== false ? trim($row[$idx_email] ?? '') : '';

                        if (empty($nis) || empty($nama) || empty($kelas)) {
                            $skipped++;
                            continue;
                        }

                        // Cari jurusan_id by name if provided
                        $jurusan_id = null;
                        if (!empty($jurusan_str)) {
                            $qj = $conn->prepare("SELECT id FROM jurusan WHERE nama_jurusan = ?");
                            $qj->bind_param("s", $jurusan_str);
                            $qj->execute();
                            $rj = $qj->get_result();
                            if ($rj_row = $rj->fetch_assoc()) $jurusan_id = $rj_row['id'];
                            $qj->close();
                        }

                        // Check duplicate NIS
                        $ck = $conn->prepare("SELECT id FROM siswa WHERE nis = ?");
                        $ck->bind_param("s", $nis);
                        $ck->execute();
                        if ($ck->get_result()->num_rows > 0) {
                            $skipped++;
                            $ck->close();
                            continue;
                        }
                        $ck->close();

                        $hashed = password_hash($nis, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO siswa (nis, nama_lengkap, password, password_change_required, kelas, jurusan_id, email) VALUES (?, ?, ?, 1, ?, ?, ?)");
                        $stmt->bind_param("ssssis", $nis, $nama, $hashed, $kelas, $jurusan_id, $email);
                        if ($stmt->execute()) $imported++;
                        else $skipped++;
                        $stmt->close();
                    }
                    $message = "Import selesai: $imported berhasil, $skipped dilewati (duplikat/data kosong).";
                    $message_type = 'success';
                    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'import', 'siswa', 0, "Import CSV: $imported siswa berhasil, $skipped skipped");
                }
            }
            fclose($handle);
        } else {
            $message = 'Gagal membaca file CSV.';
            $message_type = 'danger';
        }
    }

    // DELETE student
    elseif ($action === 'delete') {
        $id = (int)($_POST['siswa_id'] ?? 0);
        $stmt = $conn->prepare("SELECT nis, nama_lengkap FROM siswa WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stmt2 = $conn->prepare("DELETE FROM siswa WHERE id=?");
            $stmt2->bind_param("i", $id);
            if ($stmt2->execute()) {
                $message = "Siswa {$row['nis']} - {$row['nama_lengkap']} berhasil dihapus.";
                $message_type = 'success';
                logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'delete', 'siswa', $id, "Hapus siswa: {$row['nis']} - {$row['nama_lengkap']}");
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// DOWNLOAD CSV TEMPLATE
if (isset($_GET['export_template'])) {
    $headers = ['NIS', 'Nama', 'Kelas', 'Jurusan', 'Email'];
    $sample_data = [
        ['1234567890', 'Budi Santoso', 'XII-RPL-1', 'Rekayasa Perangkat Lunak', 'budi@example.com'],
        ['1234567891', 'Siti Aisyah', 'XII-RPL-1', 'Rekayasa Perangkat Lunak', ''],
        ['1234567892', 'Ahmad Junaedi', 'XII-TKJ-1', 'Teknik Komputer dan Jaringan', 'ahmad@example.com'],
    ];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_siswa.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel UTF-8
    fputcsv($output, $headers, ',', '"');
    foreach ($sample_data as $row) {
        fputcsv($output, $row, ',', '"');
    }
    fclose($output);
    exit;
}

$search = trim($_GET['search'] ?? '');
$filter_kelas = trim($_GET['kelas'] ?? '');
$filter_jurusan = (int)($_GET['jurusan_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(s.nis LIKE ? OR s.nama_lengkap LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}
if ($filter_kelas) {
    $where[] = "s.kelas = ?";
    $params[] = $filter_kelas;
    $types .= 's';
}
if ($filter_jurusan) {
    $where[] = "s.jurusan_id = ?";
    $params[] = $filter_jurusan;
    $types .= 'i';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_sql = "SELECT COUNT(*) as total FROM siswa s $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_siswa = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = max(1, ceil($total_siswa / $per_page));

$data_sql = "SELECT s.*, j.nama_jurusan FROM siswa s LEFT JOIN jurusan j ON s.jurusan_id = j.id $where_sql ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$data_stmt = $conn->prepare($data_sql);
$data_params = $params;
$data_types = $types;
$data_params[] = $per_page;
$data_params[] = $offset;
$data_types .= 'ii';
if ($data_params) $data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$siswa_list = $data_stmt->get_result();
$data_stmt->close();

$kelas_filter = $conn->query("SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
$jurusan_filter = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$jurusan_list = $conn->query("SELECT * FROM jurusan ORDER BY nama_jurusan ASC");
$kelas_list = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        .search-box { max-width: 350px; }
        .filter-form { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
        .filter-form .form-select, .filter-form .form-control { width: auto; min-width: 160px; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.8rem; border-radius: 6px; }
        .modal-content { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-header { border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: 1px solid var(--border); padding: 1rem 1.5rem; }
        @media (max-width: 992px) {
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form .form-select, .filter-form .form-control { width: 100%; min-width: unset; }
            .search-box { max-width: 100%; }
        }
    </style>
</head>
<body>
    <?php require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
                <li class="active">Data Master</li>
                <li class="active">Kelola Siswa</li>
            </ul>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3><i class="bi bi-people-fill me-2"></i>Kelola Siswa</h3>
                <span class="badge bg-primary fs-6"><?= number_format($total_siswa) ?> siswa</span>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-person-plus me-1"></i>Tambah Siswa
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload me-1"></i>Import CSV
            </button>
            <a href="?export_template=1" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1"></i>Download Template CSV
            </a>
        </div>

        <!-- Filter & Search -->
        <div class="card animate-fade-in">
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="input-group search-box">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Cari NIS atau Nama..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php while ($k = $kelas_filter->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $filter_kelas === $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select name="jurusan_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        <?php while ($j = $jurusan_filter->fetch_assoc()): ?>
                        <option value="<?= $j['id'] ?>" <?= $filter_jurusan === $j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['nama_jurusan']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <?php if ($search || $filter_kelas || $filter_jurusan): ?>
                    <a href="kelola_siswa.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reset</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm d-none">Filter</button>
                </form>
            </div>
        </div>

        <!-- Student Table -->
        <div class="card animate-fade-in">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Lengkap</th>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                            <th>Status</th>
                            <th>Password</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($siswa_list->num_rows > 0): ?>
                        <?php $no = $offset + 1; while ($s = $siswa_list->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted"><?= $no++ ?></td>
                            <td class="fw-medium"><?= htmlspecialchars($s['nis']) ?></td>
                            <td><?= htmlspecialchars($s['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($s['kelas']) ?></td>
                            <td><?= htmlspecialchars($s['nama_jurusan'] ?? '-') ?></td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                                <?php if ($s['password_change_required']): ?>
                                <span class="badge bg-warning text-dark">Ganti Password</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Reset password siswa ini ke NIS?')">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="siswa_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning btn-action" title="Reset ke NIS">
                                        <i class="bi bi-arrow-clockwise"></i> Reset
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary btn-action" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?= $s['id'] ?>"
                                        data-nis="<?= htmlspecialchars($s['nis']) ?>"
                                        data-nama="<?= htmlspecialchars($s['nama_lengkap']) ?>"
                                        data-kelas="<?= htmlspecialchars($s['kelas']) ?>"
                                        data-jurusan="<?= $s['jurusan_id'] ?>"
                                        data-email="<?= htmlspecialchars($s['email'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('<?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> siswa ini?')">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="siswa_id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $s['is_active'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $s['is_active'] ? 'danger' : 'success' ?> btn-action" title="<?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="bi bi-<?= $s['is_active'] ? 'person-x' : 'person-check' ?>"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger btn-action" title="Hapus"
                                        onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nis'])) ?> - <?= htmlspecialchars(addslashes($s['nama_lengkap'])) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox me-1"></i>Belum ada data siswa.
                                <?php if ($search || $filter_kelas || $filter_jurusan): ?>
                                Coba ubah filter pencarian.
                                <?php else: ?>
                                Tambah siswa atau import CSV.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="card-body border-top">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&kelas=<?= urlencode($filter_kelas) ?>&jurusan_id=<?= $filter_jurusan ?>">Sebelumnya</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&kelas=<?= urlencode($filter_kelas) ?>&jurusan_id=<?= $filter_jurusan ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&kelas=<?= urlencode($filter_kelas) ?>&jurusan_id=<?= $filter_jurusan ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Siswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">NIS <span class="text-danger">*</span></label>
                            <input type="text" name="nis" class="form-control" required placeholder="Nomor Induk Siswa">
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
                                    <?php $kelas_list->data_seek(0); while ($k = $kelas_list->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($k['nama_kelas']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jurusan</label>
                                <select name="jurusan_id" class="form-select">
                                    <option value="">Pilih Jurusan</option>
                                    <?php $jurusan_list->data_seek(0); while ($j = $jurusan_list->fetch_assoc()): ?>
                                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jurusan']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email (Opsional)</label>
                            <input type="email" name="email" class="form-control" placeholder="contoh@email.com">
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-1"></i>Password default = <strong>NIS</strong>. Siswa wajib ganti password saat login pertama.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Edit Siswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">NIS <span class="text-danger">*</span></label>
                            <input type="text" name="nis" id="edit_nis" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                <select name="kelas" id="edit_kelas" class="form-select" required>
                                    <option value="">Pilih Kelas</option>
                                    <?php $kelas_list->data_seek(0); while ($k = $kelas_list->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($k['nama_kelas']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jurusan</label>
                                <select name="jurusan_id" id="edit_jurusan" class="form-select">
                                    <option value="">Pilih Jurusan</option>
                                    <?php $jurusan_list->data_seek(0); while ($j = $jurusan_list->fetch_assoc()): ?>
                                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jurusan']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i>Import CSV</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="import_csv">
                        <div class="mb-3">
                            <label class="form-label">File CSV</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <div class="form-text">Format: NIS, Nama, Kelas, Jurusan (opsional), Email (opsional)</div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Password default = <strong>NIS</strong>. Siswa dengan NIS duplikat akan dilewati.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" class="d-none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="siswa_id" id="delete_id">
    </form>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        // Edit modal - populate data
        document.getElementById('editModal')?.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            document.getElementById('edit_id').value = btn.dataset.id;
            document.getElementById('edit_nis').value = btn.dataset.nis;
            document.getElementById('edit_nama').value = btn.dataset.nama;
            document.getElementById('edit_kelas').value = btn.dataset.kelas || '';
            document.getElementById('edit_jurusan').value = btn.dataset.jurusan || '';
            document.getElementById('edit_email').value = btn.dataset.email || '';
        });

        // Confirm delete
        function confirmDelete(id, label) {
            if (confirm(`Hapus siswa ${label}?\nData terkait (jawaban, hasil ujian) juga akan terhapus.`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }


    </script>
</body>
</html>
