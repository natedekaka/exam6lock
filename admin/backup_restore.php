<?php
session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    echo '<!DOCTYPE html><html><head><title>Akses Ditolak</title>';
    echo '<link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">';
    echo '<link href="../vendor/fonts/inter.css" rel="stylesheet">';
    echo '<style>body{background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,sans-serif;}</style>';
    echo '</head><body><div class="card shadow-sm" style="max-width:500px"><div class="card-body text-center p-5">';
    echo '<i class="bi bi-shield-exclamation text-danger" style="font-size:4rem;"></i>';
    echo '<h3 class="fw-bold mt-3">Akses Ditolak</h3>';
    echo '<p class="text-muted">Hanya Super Admin yang dapat mengakses halaman ini.</p>';
    echo '<a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
    echo '</div></div></body></html>';
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);
$backup_dir = realpath(__DIR__ . '/../backup_db');
$message = '';
$message_type = '';

if (!$backup_dir || !is_dir($backup_dir)) {
    $backup_dir = __DIR__ . '/../backup_db';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
}

function sanitize_filename($name) {
    $name = basename($name);
    $name = preg_replace('/[^a-zA-Z0-9_\-\.\(\)]/', '_', $name);
    return $name;
}

/**
 * PHP-based database backup (no shell commands needed)
 */
function php_backup($backup_dir, $conn, $database) {
    global $message;

    $timestamp = date('Y-m-d_H-i');
    $filename = 'backup_' . $timestamp . '.sql';
    $filepath = $backup_dir . '/' . $filename;

    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if (!$result) {
        return ['error' => 'Gagal mendapatkan daftar tabel: ' . $conn->error];
    }
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    $result->free();

    if (empty($tables)) {
        return ['error' => 'Tidak ada tabel yang ditemukan di database.'];
    }

    $sql_output = "-- Backup Database: {$database}\n";
    $sql_output .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
    $sql_output .= "-- --------------------------------------------------------\n\n";
    $sql_output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql_output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql_output .= "SET time_zone = '+07:00';\n\n";

    foreach ($tables as $table) {
        // Get CREATE TABLE
        $create = $conn->query("SHOW CREATE TABLE `{$table}`");
        if (!$create) continue;
        $create_row = $create->fetch_array();
        $create->free();
        $sql_output .= "\n-- --------------------------------------------------------\n";
        $sql_output .= "-- Table structure for `{$table}`\n";
        $sql_output .= "-- --------------------------------------------------------\n";
        $sql_output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql_output .= $create_row[1] . ";\n\n";

        // Get data
        $data = $conn->query("SELECT * FROM `{$table}`");
        if (!$data) continue;

        $num_rows = $data->num_rows;
        $num_fields = $data->field_count;

        if ($num_rows > 0) {
            $sql_output .= "-- Dumping data for table `{$table}` — {$num_rows} rows\n";

            // Get field names
            $fields = [];
            while ($finfo = $data->fetch_field()) {
                $fields[] = "`{$finfo->name}`";
            }
            $field_list = implode(', ', $fields);

            // Batch INSERT for best performance
            $batch_size = 50;
            $counter = 0;
            $rows_buffer = [];

            while ($row = $data->fetch_array(MYSQLI_NUM)) {
                $escaped_values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $escaped_values[] = 'NULL';
                    } else {
                        $escaped_values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $rows_buffer[] = '(' . implode(', ', $escaped_values) . ')';
                $counter++;

                if (count($rows_buffer) >= $batch_size) {
                    $sql_output .= "INSERT INTO `{$table}` ({$field_list}) VALUES\n";
                    $sql_output .= implode(",\n", $rows_buffer) . ";\n";
                    $rows_buffer = [];
                }
            }

            // Flush remaining rows
            if (!empty($rows_buffer)) {
                $sql_output .= "INSERT INTO `{$table}` ({$field_list}) VALUES\n";
                $sql_output .= implode(",\n", $rows_buffer) . ";\n";
            }
        }
        $data->free();
    }

    $sql_output .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    $sql_output .= "-- Backup completed: " . date('Y-m-d H:i:s') . "\n";

    // Write file
    $written = file_put_contents($filepath, $sql_output);
    if ($written === false || $written < 100) {
        if (file_exists($filepath)) unlink($filepath);
        return ['error' => 'Gagal menulis file backup. Periksa permission folder backup_db/.'];
    }

    return ['success' => $filename, 'filepath' => $filepath];
}

/**
 * PHP-based database restore (no shell commands needed)
 */
function php_restore($filepath, $conn) {
    $sql = file_get_contents($filepath);
    if ($sql === false || strlen($sql) < 10) {
        return ['error' => 'File backup kosong atau tidak valid.'];
    }

    // Remove comments and split by semicolons
    $sql_lines = explode("\n", $sql);
    $clean_lines = [];
    foreach ($sql_lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) continue;
        if (strpos($trimmed, '-- ') === 0) continue;
        if (strpos($trimmed, '#') === 0) continue;
        // Remove inline comments at end of line
        $line_no_comment = preg_replace('/\s*-- .*$/', '', $trimmed);
        if (!empty(trim($line_no_comment))) {
            $clean_lines[] = rtrim($line_no_comment);
        }
    }

    $full_sql = implode("\n", $clean_lines);
    $statements = explode(";\n", $full_sql);

    $conn->begin_transaction();
    try {
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;
            // Skip SET statements and comments
            if (stripos($stmt, 'SET ') === 0) continue;
            if (stripos($stmt, '--') === 0) continue;
            if (!$conn->query($stmt)) {
                throw new Exception("Error executing: " . substr($stmt, 0, 80) . "... - " . $conn->error);
            }
        }
        $conn->commit();
        return ['success' => 'Database berhasil direstore! Semua data telah diimpor.'];
    } catch (Exception $e) {
        $conn->rollback();
        return ['error' => 'Restore gagal: ' . $e->getMessage()];
    }
}

/**
 * Execute restore with error tolerance (skip errors on views, procedures, etc.)
 */
function php_restore_tolerant($filepath, $conn) {
    $sql = file_get_contents($filepath);
    if ($sql === false || strlen($sql) < 10) {
        return ['error' => 'File backup kosong atau tidak valid.'];
    }

    $full_sql = preg_replace('/^\s*-- .*$/m', '', $sql);
    $full_sql = preg_replace('/^\s*#.*$/m', '', $full_sql);
    $statements = explode(";\n", $full_sql);

    $errors = [];
    $count = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        if (stripos($stmt, 'SET ') === 0) continue;
        if (stripos($stmt, '--') === 0) continue;
        if (!$conn->query($stmt)) {
            $errors[] = substr($stmt, 0, 100) . ' — ' . $conn->error;
        } else {
            $count++;
        }
    }

    if (empty($errors)) {
        return ['success' => "Restore selesai! {$count} query berhasil dieksekusi."];
    }

    $err_msg = "Restore selesai dengan {$count} query berhasil, " . count($errors) . " error (dilewati).";
    return ['success' => $err_msg, 'errors' => $errors];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['buat_backup'])) {
        $result = php_backup($backup_dir, $conn, $database);
        if (isset($result['success'])) {
            $message = 'Backup berhasil: ' . htmlspecialchars($result['success']);
            $message_type = 'success';
        } else {
            $message = htmlspecialchars($result['error']);
            $message_type = 'danger';
        }
    }

    if (isset($_POST['restore_file'])) {
        $selected_file = $_POST['selected_file'] ?? '';
        $uploaded_file = $_FILES['upload_file'] ?? null;
        $confirmed = isset($_POST['restore_confirm']) && $_POST['restore_confirm'] === '1';
        $temp_file = null;

        if (!$confirmed) {
            $message = 'Anda harus menyetujui checkbox konfirmasi sebelum merestore!';
            $message_type = 'danger';
        } else {
            if ($uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['sql', 'gz'])) {
                    $message = 'File harus berekstensi .sql atau .sql.gz';
                    $message_type = 'danger';
                } else {
                    $temp_filename = 'restore_temp_' . bin2hex(random_bytes(8)) . '.sql';
                    $temp_file = $backup_dir . '/' . $temp_filename;
                    move_uploaded_file($uploaded_file['tmp_name'], $temp_file);
                }
            } elseif (!empty($selected_file)) {
                $selected_file = sanitize_filename($selected_file);
                $potential = $backup_dir . '/' . $selected_file;
                $real_base = realpath($backup_dir);
                $real_file = realpath($potential);
                if ($real_file && strpos($real_file, $real_base) === 0 && file_exists($real_file)) {
                    $temp_file = $real_file;
                } else {
                    $message = 'File backup tidak valid.';
                    $message_type = 'danger';
                }
            }

            if ($temp_file && file_exists($temp_file)) {
                $process_file = $temp_file;
                $cleanup_gz = false;
                if (pathinfo($process_file, PATHINFO_EXTENSION) === 'gz') {
                    $unzipped = preg_replace('/\.gz$/', '', $process_file);
                    $gz_data = gzfile($process_file);
                    if ($gz_data !== false) {
                        $unzipped_content = implode('', $gz_data);
                        if (file_put_contents($unzipped, $unzipped_content) !== false) {
                            $process_file = $unzipped;
                            $cleanup_gz = true;
                        } else {
                            $message = 'Gagal mengekstrak file .gz';
                            $message_type = 'danger';
                            $process_file = null;
                        }
                    } else {
                        $message = 'Gagal membaca file .gz';
                        $message_type = 'danger';
                        $process_file = null;
                    }
                }

                if ($process_file) {
                    $result = php_restore_tolerant($process_file, $conn);
                    if (isset($result['success'])) {
                        $message = $result['success'];
                        $message_type = 'success';
                    } else {
                        $message = $result['error'];
                        $message_type = 'danger';
                    }
                }

                if ($cleanup_gz && isset($unzipped) && file_exists($unzipped)) {
                    unlink($unzipped);
                }
                if (isset($temp_filename) && file_exists($temp_file)) {
                    unlink($temp_file);
                }
            } else {
                if (empty($message)) {
                    $message = 'Pilih file backup yang akan direstore atau upload file.';
                    $message_type = 'danger';
                }
            }
        }
    }

    if (isset($_POST['delete_file'])) {
        $file_to_delete = $_POST['delete_file'] ?? '';
        $file_to_delete = sanitize_filename($file_to_delete);
        $real_base = realpath($backup_dir);
        $real_file = realpath($backup_dir . '/' . $file_to_delete);
        if ($real_file && strpos($real_file, $real_base) === 0 && file_exists($real_file)) {
            unlink($real_file);
            $gz_file = $real_file . '.gz';
            if (file_exists($gz_file)) {
                unlink($gz_file);
            }
            $message = 'File berhasil dihapus: ' . htmlspecialchars($file_to_delete);
            $message_type = 'success';
        } else {
            $message = 'File tidak ditemukan atau tidak valid.';
            $message_type = 'danger';
        }
    }
}

$backup_files = [];
if ($handle = opendir($backup_dir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry !== '.' && $entry !== '..' && !is_dir($backup_dir . '/' . $entry)) {
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, ['sql', 'gz', 'zip'])) {
                $filepath = $backup_dir . '/' . $entry;
                $backup_files[] = [
                    'name' => $entry,
                    'size' => filesize($filepath),
                    'date' => date('Y-m-d H:i:s', filemtime($filepath)),
                ];
            }
        }
    }
    closedir($handle);
}

usort($backup_files, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$total_backup_size = array_sum(array_column($backup_files, 'size'));

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Backup & Restore Database</title>
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
        .btn-danger { background: var(--danger); border-color: var(--danger); }
        .table { table-layout: auto; width: 100%; }
        .table thead th { background: #f8fafc; border-bottom: 2px solid var(--border); color: var(--secondary); font-weight: 600; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 1rem; white-space: nowrap; }
        .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border); }
        .table tbody tr:hover { background: #f8fafc; }
        .badge { font-weight: 500; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; }
        .mobile-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1001; background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 0.625rem; font-size: 1.25rem; }
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .animate-fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .warning-box { background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
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
            <a href="bank_soal.php"><i class="bi bi-database-fill"></i> Bank Soal Global</a>
            <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
            <a href="analytics.php"><i class="bi bi-graph-up"></i> Analytics</a>
            <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
            <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
            <a href="backup_restore.php" class="active"><i class="bi bi-cloud-arrow-up-fill"></i> Backup & Restore</a>
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
            <h3><i class="bi bi-cloud-arrow-up-fill me-2"></i>Backup & Restore Database</h3>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="alert alert-info animate-fade-in">
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong>Informasi:</strong> Backup & restore menggunakan metode PHP-native. File backup disimpan di folder <code>backup_db/</code>.
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <i class="bi bi-download me-2"></i>Buat Backup Database
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <p class="text-muted">Membuat backup database <strong><?= htmlspecialchars($database) ?></strong> dalam format SQL.</p>
                            <button type="submit" name="buat_backup" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-cloud-arrow-down me-2"></i>Buat Backup Database
                            </button>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                File backup akan disimpan di folder <code>backup_db/</code>.
                            </small>
                        </form>
                    </div>
                </div>

                <div class="card animate-fade-in">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-files me-2"></i>Daftar File Backup</span>
                        <span class="badge bg-primary"><?= count($backup_files) ?> file</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3 p-3 bg-light rounded">
                            <div>
                                <small class="text-muted d-block">Total File Backup</small>
                                <strong><?= count($backup_files) ?> file</strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Total Ukuran</small>
                                <strong><?= format_bytes($total_backup_size) ?></strong>
                            </div>
                        </div>

                        <?php if (count($backup_files) > 0): ?>
                        <div class="table-scroll" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama File</th>
                                        <th class="text-center">Ukuran</th>
                                        <th class="text-center">Tanggal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $file): ?>
                                    <tr>
                                        <td style="word-break: break-all;">
                                            <i class="bi bi-file-earmark-zip me-1 text-primary"></i>
                                            <?= htmlspecialchars($file['name']) ?>
                                        </td>
                                        <td class="text-center"><?= format_bytes($file['size']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($file['date']) ?></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <a href="../backup_db/<?= urlencode($file['name']) ?>" class="btn btn-sm btn-primary" download>
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus file backup <?= htmlspecialchars(addslashes($file['name'])) ?>?')">
                                                    <input type="hidden" name="delete_file" value="<?= htmlspecialchars($file['name']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">Belum ada file backup</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card animate-fade-in border-danger">
                    <div class="card-header text-danger">
                        <i class="bi bi-upload me-2"></i>Restore Database
                    </div>
                    <div class="card-body">
                        <div class="warning-box">
                            <div class="d-flex gap-3">
                                <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h6 class="fw-bold text-danger mb-1">PERINGATAN!</h6>
                                    <p class="mb-0 text-danger">
                                        Me-restore database akan <strong>MENIMPA semua data yang ada!</strong> 
                                        Pastikan Anda telah membuat backup sebelum merestore.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Upload File Backup (.sql atau .sql.gz)</label>
                                <input type="file" name="upload_file" class="form-control" accept=".sql,.gz">
                            </div>

                            <hr class="my-3">
                            <p class="text-muted text-center mb-3"><small>ATAU pilih dari file backup yang ada</small></p>

                            <?php if (count($backup_files) > 0): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pilih File Backup</label>
                                <select name="selected_file" class="form-select">
                                    <option value="">-- Pilih File --</option>
                                    <?php foreach ($backup_files as $file): ?>
                                    <option value="<?= htmlspecialchars($file['name']) ?>"><?= htmlspecialchars($file['name']) ?> (<?= format_bytes($file['size']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="restoreConfirm" name="restore_confirm" value="1" required>
                                <label class="form-check-label fw-bold text-danger" for="restoreConfirm">
                                    <i class="bi bi-check2-square me-1"></i>
                                    Saya mengerti bahwa semua data akan ditimpa
                                </label>
                            </div>

                            <button type="submit" name="restore_file" class="btn btn-danger btn-lg w-100" onclick="return confirm('PERINGATAN: Semua data yang ada akan ditimpa! Lanjutkan restore?')">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Restore Database
                            </button>
                        </form>
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
