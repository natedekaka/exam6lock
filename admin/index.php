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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Dashboard Admin - Manajemen Ujian</title>
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
            width: 55px;
            height: 55px;
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
            position: relative;
            z-index: 10;
        }
        
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar a.active { background: rgba(79, 70, 229, 0.2); color: #fff; border-left-color: var(--primary); }
        
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; transition: margin-left 0.3s ease; width: calc(100% - var(--sidebar-width)); box-sizing: border-box; min-width: 0; z-index: 1; }
        
        .page-header {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h3 { margin: 0; font-weight: 600; color: var(--dark); }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { background: #fff; border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; font-weight: 600; color: var(--dark); }
        .card-body { padding: 1.5rem; }
        
        .table-scroll { 
            overflow-x: auto; 
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn { border-radius: 8px; padding: 0.625rem 1.25rem; font-weight: 500; transition: all 0.2s ease; white-space: nowrap; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
        
        .table { table-layout: auto; width: 100%; border-collapse: separate; }
        .table thead th { background: #f8fafc; border-bottom: 2px solid var(--border); color: var(--secondary); font-weight: 600; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 1rem; white-space: nowrap; position: sticky; top: 0; z-index: 10; }
        .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .table tbody tr:hover { background: #f8fafc; }
        
        .badge { font-weight: 500; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; }
        
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.625rem;
            font-size: 1.25rem;
        }
        
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        
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
            font-family: bootstrap-icons !important;
        }
        
        .action-btn i {
            font-family: bootstrap-icons !important;
            font-style: normal;
            font-variant: normal;
            text-transform: none;
            line-height: 1;
            font-size: 1.1rem;
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
        
        .action-btn-edit {
            background: #fef3c7;
            color: #d97706 !important;
        }
        
        .action-btn-edit:hover {
            background: #fde68a;
            color: #b45309 !important;
        }
        
        .action-btn-toggle-on {
            background: #dcfce7;
            color: #16a34a !important;
        }
        
        .action-btn-toggle-on:hover {
            background: #bbf7d0;
            color: #15803d !important;
        }
        
        .action-btn-toggle-off {
            background: #fee2e2;
            color: #dc2626 !important;
        }
        
        .action-btn-toggle-off:hover {
            background: #fecaca;
            color: #b91c1c !important;
        }
        
        .action-btn-bank {
            background: #dbeafe;
            color: #2563eb !important;
        }
        
        .action-btn-bank:hover {
            background: #bfdbfe;
            color: #1d4ed8 !important;
        }
        
        .action-btn-delete {
            background: #f3f4f6;
            color: #6b7280 !important;
        }
        
        .action-btn-delete:hover {
            background: #fee2e2;
            color: #dc2626 !important;
        }
        
        .btn-action-label {
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 4rem 1rem 1rem; }
            .mobile-toggle { display: flex; }
            .overlay.show { display: block; }
            .page-header { padding: 1rem; flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 576px) {
            .page-header { padding: 1rem; }
            .page-header h3 { font-size: 1.1rem; }
            .card { margin-bottom: 1rem; border-radius: 8px; }
            .card-header { padding: 1rem; font-size: 0.95rem; flex-direction: column; gap: 0.5rem; align-items: flex-start !important; }
            .card-body { padding: 1rem; }
            .card-body .row { margin: 0; }
            .card-body .col-md-6, .card-body .col-md-3, .card-body .col-md-4, .card-body .col-md-12 { 
                padding-left: 0; 
                padding-right: 0; 
                margin-bottom: 0.75rem;
            }
            .form-label { font-size: 0.875rem; margin-bottom: 0.25rem; }
            .form-control, .form-select { 
                font-size: 0.875rem; 
                padding: 0.5rem 0.75rem;
            }
            .btn { 
                width: 100%; 
                margin-bottom: 0.5rem; 
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
            .btn-group .btn { width: auto; margin-bottom: 0; }
            .table-scroll { 
                margin: 0 -1rem; 
                padding: 0 1rem;
            }
            .table { 
                font-size: 0.8rem; 
                min-width: 600px;
            }
            .table thead th, .table tbody td { 
                padding: 0.5rem 0.25rem; 
                white-space: nowrap;
            }
            .badge { 
                font-size: 0.65rem; 
                padding: 0.25rem 0.5rem;
            }
            .dropdown-menu { 
                font-size: 0.875rem; 
                min-width: 140px;
            }
            .dropdown-item { 
                padding: 0.5rem 0.75rem; 
            }
            .sidebar-brand { padding: 1rem; }
            .sidebar-brand h5 { font-size: 1rem; }
            .sidebar-menu a { padding: 0.75rem 1rem; font-size: 0.875rem; }
            .school-logo { width: 40px; height: 40px; }
            .text-white.fw-bold { font-size: 0.75rem; }
            .mobile-toggle { 
                top: 0.75rem; 
                left: 0.75rem; 
                padding: 0.5rem;
                font-size: 1.1rem;
            }
            .main-content { padding: 3.5rem 0.75rem 0.75rem; }
            .toast-notification { 
                right: 0.5rem; 
                left: 0.5rem; 
                min-width: auto;
            }
        }
        
        .animate-fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .alert-danger-conflict {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
        }

        .delete-modal .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .delete-modal .modal-header {
            border-bottom: none;
            padding: 1.5rem 1.5rem 0;
        }

        .delete-modal .modal-body {
            padding: 1rem 1.5rem 1.5rem;
            text-align: center;
        }

        .delete-modal .modal-footer {
            border-top: none;
            padding: 0 1.5rem 1.5rem;
            justify-content: center;
            gap: 0.75rem;
        }

        .delete-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .delete-icon i {
            font-size: 2.5rem;
            color: #ef4444;
        }

        .toast-notification {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            min-width: 300px;
        }

        .toast-notification.show {
            animation: slideIn 0.4s ease, fadeOut 0.4s ease 2.6s forwards;
        }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        .toast-success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #a7f3d0;
            border-left: 4px solid #10b981;
            border-radius: 12px;
        }

        .toast-success .toast-icon {
            color: #10b981;
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
            <a href="index.php" class="active"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
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
            <a href="izin_remedi.php"><i class="bi bi-arrow-repeat"></i> Izin Remedi</a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="audit_log.php"><i class="bi bi-journal-text"></i> Audit Log</a>
            <?php endif; ?>
            <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
            <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid px-4">
        <div class="page-header animate-fade-in">
            <h3><i class="bi bi-clipboard-data me-2"></i>Manajemen Ujian - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></h3>
            <span class="badge bg-primary fs-6"><?= $result->num_rows ?> ujian</span>
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
