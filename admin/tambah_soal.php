<?php
// admin/tambah_soal.php - Bank Soal dengan Upload Gambar (Secure & Responsive)

session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['csrf_token_time']) || time() - $_SESSION['csrf_token_time'] > 3600) {
    unset($_SESSION['csrf_token']);
    $_SESSION['csrf_token_time'] = time();
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/audit_helper.php';

$sekolah = getKonfigurasiSekolah($conn);

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateFileUpload($file) {
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $maxSize = 2 * 1024 * 1024;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file['size'] > $maxSize) {
        return ['error' => 'File terlalu besar. Maksimal 2MB'];
    }
    
    if (!isset($allowed[$ext])) {
        return ['error' => 'Format file tidak diizinkan'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed)) {
        return ['error' => 'Tipe file tidak valid'];
    }
    
    return ['valid' => true, 'ext' => $ext];
}

$upload_dir = '../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function uploadGambar($file, $prefix) {
    $validation = validateFileUpload($file);
    if (isset($validation['error'])) {
        return ['error' => $validation['error']];
    }
    
    $ext = $validation['ext'];
    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = '../uploads/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => $filename];
    }
    return ['error' => 'Gagal upload file'];
}

function hapusGambar($filename) {
    if ($filename && file_exists('../uploads/' . $filename)) {
        unlink('../uploads/' . $filename);
    }
}

generateCsrfToken();

$message = '';
$message_type = '';

$ujian_list = $conn->query("SELECT id, judul_ujian, status FROM ujian ORDER BY judul_ujian");

$all_mode = isset($_GET['all']) && $_GET['all'] === '1';
if ($all_mode) {
    $selected_ujian = 0;
} else {
    $selected_ujian = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;
    if ($selected_ujian <= 0) {
        $first = $ujian_list->fetch_assoc();
        $selected_ujian = $first['id'] ?? 0;
    }
}
if ($selected_ujian > 0) {
    $ujian_list->data_seek(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_soal'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $id_ujian = (int)$_POST['id_ujian'];
        $pertanyaan = trim($_POST['pertanyaan']);
        $opsi_a = trim($_POST['opsi_a']);
        $opsi_b = trim($_POST['opsi_b']);
        $opsi_c = trim($_POST['opsi_c']);
        $opsi_d = trim($_POST['opsi_d']);
        $opsi_e = trim($_POST['opsi_e']);
        $kunci = in_array($_POST['kunci_jawaban'], ['a','b','c','d','e']) ? $_POST['kunci_jawaban'] : 'a';
        $poin = max(1, (int)$_POST['poin']);
        $kategori = isset($_POST['kategori']) ? trim($_POST['kategori']) : null;
        $timer_soal = isset($_POST['timer_soal']) ? max(0, (int)$_POST['timer_soal']) : 0;
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
        $original_updated = $_POST['original_updated'] ?? '';
        
        $field_wajib = [
            'Pertanyaan' => $pertanyaan,
            'Opsi A' => $opsi_a,
            'Opsi B' => $opsi_b,
            'Opsi C' => $opsi_c,
            'Opsi D' => $opsi_d,
            'Opsi E' => $opsi_e,
        ];
        $field_kosong = array_keys(array_filter($field_wajib, fn($v) => $v === ''));

        if (!empty($field_kosong)) {
            $message = 'Field wajib diisi masih kosong: ' . implode(', ', $field_kosong);
            $message_type = 'danger';
        } else {
            $gambar_pertanyaan = null;
            $gambar_a = null;
            $gambar_b = null;
            $gambar_c = null;
            $gambar_d = null;
            $gambar_e = null;
            
            if ($edit_id > 0) {
                $stmt = $conn->prepare("SELECT * FROM soal WHERE id = ?");
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
                $old_soal = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($old_soal && $original_updated !== $old_soal['updated_at']) {
                    $message = 'Data soal telah diubah oleh pengguna lain. Silakan refresh dan coba lagi.';
                    $message_type = 'danger';
                } elseif (!$old_soal) {
                    $message = 'Soal tidak ditemukan';
                    $message_type = 'danger';
                } else {
                    $gambar_pertanyaan = $old_soal['gambar_pertanyaan'] ?? null;
                    $gambar_a = $old_soal['gambar_a'] ?? null;
                    $gambar_b = $old_soal['gambar_b'] ?? null;
                    $gambar_c = $old_soal['gambar_c'] ?? null;
                    $gambar_d = $old_soal['gambar_d'] ?? null;
                    $gambar_e = $old_soal['gambar_e'] ?? null;
                }
            }
            
            if (empty($message)) {
                if (!empty($_FILES['gambar_pertanyaan']['name'])) {
                    $result = uploadGambar($_FILES['gambar_pertanyaan'], 'soal');
                    if (isset($result['error'])) {
                        $message = $result['error'];
                        $message_type = 'danger';
                    } else {
                        $gambar_pertanyaan = $result['success'];
                    }
                }
                
                if (empty($message) && !empty($_FILES['gambar_a']['name'])) {
                    $result = uploadGambar($_FILES['gambar_a'], 'opsia');
                    if (isset($result['error'])) {
                        $message = $result['error'];
                        $message_type = 'danger';
                    } else {
                        $gambar_a = $result['success'];
                    }
                }
                
                if (empty($message) && !empty($_FILES['gambar_b']['name'])) {
                    $result = uploadGambar($_FILES['gambar_b'], 'opsib');
                    if (isset($result['error'])) {
                        $message = $result['error'];
                        $message_type = 'danger';
                    } else {
                        $gambar_b = $result['success'];
                    }
                }
                
                if (empty($message) && !empty($_FILES['gambar_c']['name'])) {
                    $result = uploadGambar($_FILES['gambar_c'], 'opsic');
                    if (isset($result['error'])) {
                        $message = $result['error'];
                        $message_type = 'danger';
                    } else {
                        $gambar_c = $result['success'];
                    }
                }
                
                if (empty($message) && !empty($_FILES['gambar_d']['name'])) {
                    $result = uploadGambar($_FILES['gambar_d'], 'opsid');
                    if (isset($result['error'])) {
                        $message = $result['error'];
                        $message_type = 'danger';
                    } else {
                        $gambar_d = $result['success'];
                    }
                }
                
                if (empty($message) && !empty($_FILES['gambar_e']['name'])) {
                    $result = uploadGambar($_FILES['gambar_e'], 'opsie');
                    if (isset($result['error'])) {
                        $message = $result['error'];
                        $message_type = 'danger';
                    } else {
                        $gambar_e = $result['success'];
                    }
                }
            }
            
            if (empty($message)) {
                if ($edit_id > 0) {
                    $stmt = $conn->prepare("UPDATE soal SET pertanyaan=?, gambar_pertanyaan=?, opsi_a=?, gambar_a=?, opsi_b=?, gambar_b=?, opsi_c=?, gambar_c=?, opsi_d=?, gambar_d=?, opsi_e=?, gambar_e=?, kunci_jawaban=?, poin=?, kategori=?, timer_soal=? WHERE id=?");
                    $stmt->bind_param("sssssssssssssisii", $pertanyaan, $gambar_pertanyaan, $opsi_a, $gambar_a, $opsi_b, $gambar_b, $opsi_c, $gambar_c, $opsi_d, $gambar_d, $opsi_e, $gambar_e, $kunci, $poin, $kategori, $timer_soal, $edit_id);
                    $message = "Soal berhasil diperbarui!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO soal (id_ujian, pertanyaan, gambar_pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban, poin, kategori, timer_soal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssssssssssisi", $id_ujian, $pertanyaan, $gambar_pertanyaan, $opsi_a, $gambar_a, $opsi_b, $gambar_b, $opsi_c, $gambar_c, $opsi_d, $gambar_d, $opsi_e, $gambar_e, $kunci, $poin, $kategori, $timer_soal);
                    $message = "Soal berhasil ditambahkan!";
                }
                
                if ($stmt->execute()) {
                    $message_type = 'success';
                    $soal_id = $edit_id > 0 ? $edit_id : $stmt->insert_id;
                    $aksi = $edit_id > 0 ? 'UPDATE' : 'CREATE';
                    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], $aksi, 'SOAL', $soal_id, 'Soal untuk ujian ID: ' . $id_ujian);
                } else {
                    $message = "Gagal menyimpan: " . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_soal'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
        $target_ujian = isset($_POST['copy_target_ujian']) ? (int)$_POST['copy_target_ujian'] : 0;

        if ($edit_id > 0 && $target_ujian > 0) {
            $stmt = $conn->prepare("SELECT * FROM soal WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $soal = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($soal) {
                $check = $conn->prepare("SELECT id FROM soal WHERE id_ujian = ? AND pertanyaan = ?");
                $check->bind_param("is", $target_ujian, $soal['pertanyaan']);
                $check->execute();
                $exists = $check->get_result()->fetch_assoc();
                $check->close();

                if ($exists) {
                    $message = "Soal sudah ada di ujian tujuan!";
                    $message_type = 'warning';
                } else {
                    $stmt = $conn->prepare("INSERT INTO soal (id_ujian, pertanyaan, gambar_pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban, poin, kategori, timer_soal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssssssssssisi", $target_ujian, $soal['pertanyaan'], $soal['gambar_pertanyaan'], $soal['opsi_a'], $soal['gambar_a'], $soal['opsi_b'], $soal['gambar_b'], $soal['opsi_c'], $soal['gambar_c'], $soal['opsi_d'], $soal['gambar_d'], $soal['opsi_e'], $soal['gambar_e'], $soal['kunci_jawaban'], $soal['poin'], $soal['kategori'], $soal['timer_soal']);
                    if ($stmt->execute()) {
                        $message = "Soal berhasil disalin ke ujian tujuan!";
                        $message_type = 'success';
                    } else {
                        $message = "Gagal menyalin soal!";
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
            } else {
                $message = "Soal tidak ditemukan!";
                $message_type = 'danger';
            }
        } else {
            $message = "Pilih ujian tujuan!";
            $message_type = 'danger';
        }
    }
}

if (isset($_GET['hapus_gambar']) && isset($_GET['token']) && isset($_GET['edit'])) {
    if (!validateCsrfToken($_GET['token'])) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $soal_id = (int)$_GET['edit'];
        $ujian_id = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;
        $gambar_field = $_GET['hapus_gambar'];
        
        $allowed_fields = ['gambar_pertanyaan', 'gambar_a', 'gambar_b', 'gambar_c', 'gambar_d', 'gambar_e'];
        
        if (in_array($gambar_field, $allowed_fields)) {
            $stmt = $conn->prepare("SELECT $gambar_field FROM soal WHERE id = ?");
            $stmt->bind_param("i", $soal_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $soal = $result->fetch_assoc();
            $stmt->close();
            
            if ($soal && $soal[$gambar_field]) {
                hapusGambar($soal[$gambar_field]);
                
                $stmt = $conn->prepare("UPDATE soal SET $gambar_field = NULL WHERE id = ?");
                $stmt->bind_param("i", $soal_id);
                $stmt->execute();
                $stmt->close();
                
                $message = "Gambar berhasil dihapus!";
                $message_type = 'success';
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ujian=' . $ujian_id . '&edit=' . $soal_id);
        exit;
    }
}

if (isset($_GET['hapus']) && isset($_GET['token'])) {
    $id = (int)$_GET['hapus'];
    
    if (!validateCsrfToken($_GET['token'])) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT s.* FROM soal s WHERE s.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $soal = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($soal) {
            hapusGambar($soal['gambar_pertanyaan']);
            hapusGambar($soal['gambar_a']);
            hapusGambar($soal['gambar_b']);
            hapusGambar($soal['gambar_c']);
            hapusGambar($soal['gambar_d']);
            hapusGambar($soal['gambar_e']);
            
            $stmt = $conn->prepare("DELETE FROM soal WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Soal berhasil dihapus!";
                $message_type = 'success';
                logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'SOAL', $id, 'Menghapus soal untuk ujian ID: ' . $id_ujian);
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['bulk_delete']) && isset($_GET['ids']) && isset($_GET['token'])) {
    if (!validateCsrfToken($_GET['token'])) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $ids = explode(',', $_GET['ids']);
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM soal WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $message = "Berhasil menghapus " . $stmt->affected_rows . " soal!";
                $message_type = 'success';
                logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'SOAL', 0, 'Bulk delete ' . $stmt->affected_rows . ' soal (ID: ' . $_GET['ids'] . ')');
            }
            $stmt->close();
        }
    }
}

if (isset($_POST['bulk_update']) && isset($_POST['bulk_ids'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $ids = explode(',', $_POST['bulk_ids']);
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        
        $kategori = isset($_POST['bulk_kategori']) ? trim($_POST['bulk_kategori']) : null;
        $poin = isset($_POST['bulk_poin']) ? max(1, (int)$_POST['bulk_poin']) : null;
        
        if (!empty($ids)) {
            $updates = [];
            $params = [];
            $types = '';
            
            if ($kategori !== null && $kategori !== '') {
                $updates[] = "kategori = ?";
                $params[] = $kategori;
                $types .= 's';
            }
            if ($poin !== null) {
                $updates[] = "poin = ?";
                $params[] = $poin;
                $types .= 'i';
            }
            
            if (!empty($updates)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "UPDATE soal SET " . implode(', ', $updates) . " WHERE id IN ($placeholders)";
                $params = array_merge($params, $ids);
                $types .= str_repeat('i', count($ids));
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $message = "Berhasil mengupdate " . $stmt->affected_rows . " soal!";
                    $message_type = 'success';
                }
                $stmt->close();
            }
        }
    }
}

$soal_list = [];
if ($all_mode) {
    $result = $conn->query("SELECT s.*, u.judul_ujian FROM soal s JOIN ujian u ON s.id_ujian = u.id ORDER BY u.judul_ujian, s.id");
    while ($row = $result->fetch_assoc()) {
        $soal_list[] = $row;
    }
} elseif ($selected_ujian > 0) {
    $stmt = $conn->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY id");
    $stmt->bind_param("i", $selected_ujian);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $soal_list[] = $row;
    }
    $stmt->close();
}

$edit_soal = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT s.* FROM soal s WHERE s.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    $edit_soal = $edit_result->fetch_assoc();
    $stmt->close();
    if ($edit_soal) {
        $selected_ujian = $edit_soal['id_ujian'];
    }
}

$csrf_token = $_SESSION['csrf_token'];

if (isset($_SESSION['import_message'])) {
    $message = $_SESSION['import_message'];
    $message_type = $_SESSION['import_message_type'] ?? 'danger';
    unset($_SESSION['import_message'], $_SESSION['import_message_type']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Bank Soal - Manajemen Ujian Online">
    <title>Bank Soal</title>
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
        
        .preview-img { 
            max-width: 120px; 
            max-height: 80px; 
            object-fit: contain; 
            border: 1px solid var(--border); 
            border-radius: 6px; 
        }
        
        .gambar-preview { 
            max-width: 60px; 
            max-height: 50px; 
            object-fit: contain; 
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .file-upload-wrapper {
            position: relative;
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            border: 1px dashed var(--border);
            border-radius: 8px;
            color: var(--secondary);
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .file-upload-wrapper:hover .file-upload-label {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(79, 70, 229, 0.02);
        }
        
        .opsi-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        
        .opsi-card:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .opsi-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }
        
        .opsi-a { background: #dbeafe; color: #1d4ed8; }
        .opsi-b { background: #dcfce7; color: #15803d; }
        .opsi-c { background: #fef3c7; color: #b45309; }
        .opsi-d { background: #fce7f3; color: #be185d; }
        .opsi-e { background: #e0e7ff; color: #4338ca; }
        
        @media (max-width: 576px) {
            .question-box {
                padding: 1rem;
            }
            
            .question-box textarea {
                font-size: 0.875rem;
            }
            
            .opsi-card {
                padding: 0.5rem;
                font-size: 0.8125rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .opsi-number {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
            }
            
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .table-scroll {
                margin: 0 -0.75rem;
                padding: 0 0.75rem;
            }
        }
        
        @media (max-width: 768px) {
            .opsi-card {
                padding: 0.75rem;
            }
        }
        
        .question-box {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .question-box:hover {
            border-color: var(--primary);
        }
        
        .toast-header.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        }
        
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
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header animate-fade-in">
            <div class="d-flex align-items-center gap-3">
                <h3><i class="bi bi-journal-text me-2"></i>Bank Soal <?= $all_mode ? '<small class="text-muted fs-6 fw-normal">- Semua Ujian</small>' : '' ?></h3>
                <?php if ($selected_ujian > 0 || $all_mode): ?>
                <span class="badge bg-primary fs-6"><?= count($soal_list) ?> soal</span>
                <?php endif; ?>
            </div>
            <?php if (!$all_mode && $selected_ujian > 0 && count($soal_list) > 0): ?>
            <a href="ekspor_soal_pdf.php?ujian=<?= $selected_ujian ?>" class="btn btn-success" target="_blank">
                <i class="bi bi-file-pdf me-1"></i> Export PDF
            </a>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
        <div class="toast-container">
            <div class="toast show" role="alert" data-bs-delay="5000">
                <div class="toast-header <?= ($message_type === 'danger' && strpos($message, 'pengguna lain') !== false) ? 'bg-danger' : 'bg-'.$message_type ?> text-white">
                    <i class="bi bi-<?= ($message_type === 'danger' && strpos($message, 'pengguna lain') !== false) ? 'exclamation-triangle-fill' : ($message_type === 'success' ? 'check-circle' : 'exclamation-circle') ?>-fill me-2"></i>
                    <strong class="me-auto"><?= ($message_type === 'danger' && strpos($message, 'pengguna lain') !== false) ? 'Konflik Data!' : ($message_type === 'success' ? 'Berhasil' : 'Peringatan') ?></strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label"><i class="bi bi-file-earmark-text me-1"></i>Pilih Ujian</label>
                        <select name="ujian" class="form-select" onchange="handleUjianChange(this)">
                            <option value="">-- Pilih Ujian --</option>
                            <option value="all" <?= $all_mode ? 'selected' : '' ?>>-- Semua Ujian (Bank Global) --</option>
                            <?php 
                            $ujian_list->data_seek(0);
                            while ($ujian = $ujian_list->fetch_assoc()): 
                            ?>
                            <option value="<?= $ujian['id'] ?>" <?= !$all_mode && $selected_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ujian['judul_ujian']) ?> (<?= $ujian['status'] ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_ujian > 0 || $all_mode): ?>
        
        <?php if (!$all_mode): ?>
        <div class="card animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-<?= $edit_soal ? 'pencil-square' : 'plus-circle' ?> me-2"></i><?= $edit_soal ? 'Edit Soal' : 'Tambah Soal Baru' ?></span>
                <?php if ($edit_soal): ?>
                <a href="tambah_soal.php?ujian=<?= $selected_ujian ?>" class="btn btn-sm btn-secondary">
                    <i class="bi bi-x-lg"></i> Batal
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="soalForm" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id_ujian" value="<?= $selected_ujian ?>">
                    <?php if ($edit_soal): ?>
                        <input type="hidden" name="edit_id" value="<?= $edit_soal['id'] ?>">
                        <input type="hidden" name="original_updated" value="<?= $edit_soal['updated_at'] ?>">
                    <?php endif; ?>
                    
                    <div class="question-box">
                        <label class="form-label fw-bold"><i class="bi bi-chat-left-text me-2"></i>Pertanyaan <span class="text-danger">*</span></label>
                        <textarea name="pertanyaan" class="form-control mb-3" rows="8" placeholder="Masukkan pertanyaan soal..." required><?= $edit_soal ? htmlspecialchars($edit_soal['pertanyaan']) : '' ?></textarea>
                        
                        <label class="form-label"><i class="bi bi-image me-1"></i>Gambar Pertanyaan (opsional)</label>
                        <div class="file-upload-wrapper mb-2">
                            <input type="file" name="gambar_pertanyaan" accept="image/*" onchange="updateFileName(this, 'label-pertanyaan')">
                            <div class="file-upload-label" id="label-pertanyaan">
                                <i class="bi bi-cloud-upload"></i> Klik untuk upload gambar
                            </div>
                        </div>
                        <?php if ($edit_soal && $edit_soal['gambar_pertanyaan']): ?>
                            <div class="mt-2 position-relative d-inline-block">
                                <img src="../uploads/<?= $edit_soal['gambar_pertanyaan'] ?>" class="preview-img" alt="Gambar Pertanyaan">
                                <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $edit_soal['id'] ?>&hapus_gambar=gambar_pertanyaan&token=<?= $csrf_token ?>" class="btn btn-sm btn-danger position-absolute" style="top: -10px; right: -10px;" onclick="return confirm('Hapus gambar ini?')" title="Hapus gambar">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <small class="text-muted d-block">Gambar sudah ada</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><span class="opsi-label opsi-a">A</span>Opsi A <span class="text-danger">*</span></label>
                                <textarea name="opsi_a" class="form-control mb-2" rows="3" placeholder="Masukkan opsi A..." required><?= $edit_soal ? htmlspecialchars($edit_soal['opsi_a']) : '' ?></textarea>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="gambar_a" accept="image/*" onchange="updateFileName(this, 'label-a')">
                                    <div class="file-upload-label" id="label-a">
                                        <i class="bi bi-image"></i> Gambar Opsi A
                                    </div>
                                </div>
                                <?php if ($edit_soal && $edit_soal['gambar_a']): ?>
                                    <div class="mt-2 position-relative d-inline-block">
                                        <img src="../uploads/<?= $edit_soal['gambar_a'] ?>" class="gambar-preview" alt="Gambar A">
                                        <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $edit_soal['id'] ?>&hapus_gambar=gambar_a&token=<?= $csrf_token ?>" class="btn btn-sm btn-danger position-absolute" style="top: -5px; right: -5px;" onclick="return confirm('Hapus gambar ini?')" title="Hapus gambar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><span class="opsi-label opsi-b">B</span>Opsi B <span class="text-danger">*</span></label>
                                <textarea name="opsi_b" class="form-control mb-2" rows="3" placeholder="Masukkan opsi B..." required><?= $edit_soal ? htmlspecialchars($edit_soal['opsi_b']) : '' ?></textarea>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="gambar_b" accept="image/*" onchange="updateFileName(this, 'label-b')">
                                    <div class="file-upload-label" id="label-b">
                                        <i class="bi bi-image"></i> Gambar Opsi B
                                    </div>
                                </div>
                                <?php if ($edit_soal && $edit_soal['gambar_b']): ?>
                                    <div class="mt-2 position-relative d-inline-block">
                                        <img src="../uploads/<?= $edit_soal['gambar_b'] ?>" class="gambar-preview" alt="Gambar B">
                                        <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $edit_soal['id'] ?>&hapus_gambar=gambar_b&token=<?= $csrf_token ?>" class="btn btn-sm btn-danger position-absolute" style="top: -5px; right: -5px;" onclick="return confirm('Hapus gambar ini?')" title="Hapus gambar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><span class="opsi-label opsi-c">C</span>Opsi C <span class="text-danger">*</span></label>
                                <textarea name="opsi_c" class="form-control mb-2" rows="3" placeholder="Masukkan opsi C..." required><?= $edit_soal ? htmlspecialchars($edit_soal['opsi_c']) : '' ?></textarea>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="gambar_c" accept="image/*" onchange="updateFileName(this, 'label-c')">
                                    <div class="file-upload-label" id="label-c">
                                        <i class="bi bi-image"></i> Gambar Opsi C
                                    </div>
                                </div>
                                <?php if ($edit_soal && $edit_soal['gambar_c']): ?>
                                    <div class="mt-2 position-relative d-inline-block">
                                        <img src="../uploads/<?= $edit_soal['gambar_c'] ?>" class="gambar-preview" alt="Gambar C">
                                        <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $edit_soal['id'] ?>&hapus_gambar=gambar_c&token=<?= $csrf_token ?>" class="btn btn-sm btn-danger position-absolute" style="top: -5px; right: -5px;" onclick="return confirm('Hapus gambar ini?')" title="Hapus gambar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><span class="opsi-label opsi-d">D</span>Opsi D <span class="text-danger">*</span></label>
                                <textarea name="opsi_d" class="form-control mb-2" rows="3" placeholder="Masukkan opsi D..." required><?= $edit_soal ? htmlspecialchars($edit_soal['opsi_d']) : '' ?></textarea>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="gambar_d" accept="image/*" onchange="updateFileName(this, 'label-d')">
                                    <div class="file-upload-label" id="label-d">
                                        <i class="bi bi-image"></i> Gambar Opsi D
                                    </div>
                                </div>
                                <?php if ($edit_soal && $edit_soal['gambar_d']): ?>
                                    <div class="mt-2 position-relative d-inline-block">
                                        <img src="../uploads/<?= $edit_soal['gambar_d'] ?>" class="gambar-preview" alt="Gambar D">
                                        <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $edit_soal['id'] ?>&hapus_gambar=gambar_d&token=<?= $csrf_token ?>" class="btn btn-sm btn-danger position-absolute" style="top: -5px; right: -5px;" onclick="return confirm('Hapus gambar ini?')" title="Hapus gambar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><span class="opsi-label opsi-e">E</span>Opsi E <span class="text-danger">*</span></label>
                                <textarea name="opsi_e" class="form-control mb-2" rows="3" placeholder="Masukkan opsi E..." required><?= $edit_soal ? htmlspecialchars($edit_soal['opsi_e']) : '' ?></textarea>
                                <div class="file-upload-wrapper">
                                    <input type="file" name="gambar_e" accept="image/*" onchange="updateFileName(this, 'label-e')">
                                    <div class="file-upload-label" id="label-e">
                                        <i class="bi bi-image"></i> Gambar Opsi E
                                    </div>
                                </div>
                                <?php if ($edit_soal && $edit_soal['gambar_e']): ?>
                                    <div class="mt-2 position-relative d-inline-block">
                                        <img src="../uploads/<?= $edit_soal['gambar_e'] ?>" class="gambar-preview" alt="Gambar E">
                                        <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $edit_soal['id'] ?>&hapus_gambar=gambar_e&token=<?= $csrf_token ?>" class="btn btn-sm btn-danger position-absolute" style="top: -5px; right: -5px;" onclick="return confirm('Hapus gambar ini?')" title="Hapus gambar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><i class="bi bi-check2-square me-1"></i>Kunci Jawaban</label>
                                <select name="kunci_jawaban" class="form-select">
                                    <option value="a" <?= $edit_soal && $edit_soal['kunci_jawaban'] === 'a' ? 'selected' : '' ?>>A</option>
                                    <option value="b" <?= $edit_soal && $edit_soal['kunci_jawaban'] === 'b' ? 'selected' : '' ?>>B</option>
                                    <option value="c" <?= $edit_soal && $edit_soal['kunci_jawaban'] === 'c' ? 'selected' : '' ?>>C</option>
                                    <option value="d" <?= $edit_soal && $edit_soal['kunci_jawaban'] === 'd' ? 'selected' : '' ?>>D</option>
                                    <option value="e" <?= $edit_soal && $edit_soal['kunci_jawaban'] === 'e' ? 'selected' : '' ?>>E</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><i class="bi bi-star me-1"></i>Poin</label>
                                <input type="number" name="poin" class="form-control" value="<?= $edit_soal ? (int)$edit_soal['poin'] : 10 ?>" min="1" max="100">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><i class="bi bi-folder me-1"></i>Kategori</label>
                                <select name="kategori" class="form-control">
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="Mudah" <?= ($edit_soal && ($edit_soal['kategori'] ?? '') == 'Mudah') ? 'selected' : '' ?>>Mudah</option>
                                    <option value="Sedang" <?= ($edit_soal && ($edit_soal['kategori'] ?? '') == 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                                    <option value="Sulit" <?= ($edit_soal && ($edit_soal['kategori'] ?? '') == 'Sulit') ? 'selected' : '' ?>>Sulit</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="opsi-card">
                                <label class="form-label fw-bold"><i class="bi bi-clock me-1"></i>Timer/Soal (dtk)</label>
                                <input type="number" name="timer_soal" class="form-control" value="<?= $edit_soal ? (int)($edit_soal['timer_soal'] ?? 0) : 0 ?>" min="0" max="3600" placeholder="0 = tidak digunakan">
                                <small class="text-muted">0 = tidak digunakan</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" name="simpan_soal" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> <?= $edit_soal ? 'Perbarui' : 'Simpan' ?> Soal
                        </button>
                    </div>

                    <?php if ($edit_soal): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-copy me-2"></i>Copy Soal ke Ujian Lain</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <select name="copy_target_ujian" class="form-select" form="copyForm">
                                    <option value="">-- Pilih Ujian Tujuan --</option>
                                    <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                                    <option value="<?= $ujian['id'] ?>" <?= $ujian['id'] == $edit_soal['id_ujian'] ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($ujian['judul_ujian']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" name="copy_soal" class="btn btn-outline-primary" form="copyForm" onclick="return confirm('Copy soal ini ke ujian yang dipilih?')">
                                    <i class="bi bi-copy me-1"></i>Copy ke Ujian Lain
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>

                <?php if ($edit_soal): ?>
                <form method="POST" id="copyForm" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="edit_id" value="<?= $edit_soal['id'] ?>">
                    <input type="hidden" name="id_ujian" value="<?= $selected_ujian ?>">
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-list-ol me-2"></i>Daftar Soal</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= count($soal_list) ?> soal</span>
                    <?php if (count($soal_list) > 0): ?>
                    <div class="bulk-actions" style="display: none;">
                        <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">
                            <i class="bi bi-trash me-1"></i>Hapus Terpilih (<span id="selectedCount">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="bulkEditBtn">
                            <i class="bi bi-pencil me-1"></i>Edit Massal
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body scrollable-table">
                <?php if (count($soal_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                                <th class="text-center" style="width: 50px;">No</th>
                                <?php if ($all_mode): ?>
                                <th>Ujian</th>
                                <?php endif; ?>
                                <th>Pertanyaan</th>
                                <th class="text-center" style="width: 70px;">Kat.</th>
                                <th class="text-center" style="width: 60px;">Timer</th>
                                <th class="text-center" style="width: 60px;">Gbr</th>
                                <th class="text-center" style="width: 60px;">Kunci</th>
                                <th class="text-center" style="width: 50px;">Poin</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($soal_list as $soal): ?>
                            <tr>
                                <td class="text-center"><input type="checkbox" class="soal-checkbox" value="<?= $soal['id'] ?>"></td>
                                <td class="text-center"><?= $no++ ?></td>
                                <?php if ($all_mode): ?>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($soal['judul_ujian']) ?></span></td>
                                <?php endif; ?>
                                <td style="white-space: normal; word-wrap: break-word; min-width: 150px;">
                                    <?= nl2br(htmlspecialchars($soal['pertanyaan'])) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($soal['kategori'])): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($soal['kategori']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (($soal['timer_soal'] ?? 0) > 0): ?>
                                        <span class="badge bg-info"><?= $soal['timer_soal'] ?> dtk</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($soal['gambar_pertanyaan'] || $soal['gambar_a'] || $soal['gambar_b'] || $soal['gambar_c'] || $soal['gambar_d'] || $soal['gambar_e']): ?>
                                        <i class="bi bi-image-fill text-success"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $soal['kunci_jawaban'] === 'a' ? 'primary' : ($soal['kunci_jawaban'] === 'b' ? 'success' : ($soal['kunci_jawaban'] === 'c' ? 'warning' : ($soal['kunci_jawaban'] === 'd' ? 'danger' : 'info'))) ?>">
                                        <?= strtoupper($soal['kunci_jawaban']) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= $soal['poin'] ?></td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <a href="?ujian=<?= $selected_ujian ?>&edit=<?= $soal['id'] ?>" 
                                           class="action-btn-group" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="Edit">
                                            <span class="action-btn action-btn-edit">
                                                <i class="bi bi-pencil" style="font-size: 1rem;"></i>
                                            </span>
                                            <span class="action-btn-label">Edit</span>
                                        </a>
                                        <button type="button" 
                                            class="action-btn-group btn-hapus-soal" 
                                            data-id="<?= $soal['id'] ?>" 
                                            data-token="<?= $csrf_token ?>"
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="top" 
                                            title="Hapus">
                                            <span class="action-btn action-btn-delete">
                                                <i class="bi bi-trash3" style="font-size: 1rem;"></i>
                                            </span>
                                            <span class="action-btn-label">Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2"><?= $all_mode ? 'Belum ada soal di semua ujian' : 'Belum ada soal untuk ujian ini' ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <div class="card animate-fade-in">
            <div class="card-body text-center py-5">
                <i class="bi bi-folder2-open text-muted" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3">Silakan pilih ujian terlebih dahulu untuk mengelola bank soal</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($all_mode && !$edit_soal): ?>
        <div class="card animate-fade-in">
            <div class="card-body text-center py-4">
                <i class="bi bi-info-circle text-primary" style="font-size: 2rem;"></i>
                <p class="text-muted mt-2">Mode Semua Ujian: Anda dapat melihat semua soal dari seluruh ujian. Pilih ujian tertentu untuk menambah atau mengedit soal.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        function handleUjianChange(select) {
            if (select.value === 'all') {
                window.location.href = '?all=1';
            } else if (select.value !== '') {
                window.location.href = '?ujian=' + select.value;
            } else {
                window.location.href = 'tambah_soal.php';
            }
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
        
        function updateFileName(input, labelId) {
            const label = document.getElementById(labelId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 2 * 1024 * 1024;
                
                if (!validTypes.includes(file.type)) {
                    alert('Format file tidak valid. Gunakan JPG, PNG, GIF, atau WebP');
                    input.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('File terlalu besar. Maksimal 2MB');
                    input.value = '';
                    return;
                }
                
                label.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + file.name;
            }
        }
        
        function updateDocxName(input, labelId) {
            const label = document.getElementById(labelId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const validTypes = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
                const maxSize = 5 * 1024 * 1024;
                const ext = input.files[0].name.split('.').pop().toLowerCase();
                const validExts = ['docx', 'txt'];
                
                if (!validExts.includes(ext)) {
                    alert('Format file tidak valid. Gunakan file .docx atau .txt');
                    input.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('File terlalu besar. Maksimal 5MB');
                    input.value = '';
                    return;
                }
                
                label.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + file.name;
            }
        }
        
        document.getElementById('soalForm').addEventListener('submit', function(e) {
            const fields = [
                { name: 'Pertanyaan', el: document.querySelector('textarea[name="pertanyaan"]') },
                { name: 'Opsi A', el: document.querySelector('textarea[name="opsi_a"]') },
                { name: 'Opsi B', el: document.querySelector('textarea[name="opsi_b"]') },
                { name: 'Opsi C', el: document.querySelector('textarea[name="opsi_c"]') },
                { name: 'Opsi D', el: document.querySelector('textarea[name="opsi_d"]') },
                { name: 'Opsi E', el: document.querySelector('textarea[name="opsi_e"]') }
            ];
            const kosong = fields.filter(f => !f.el.value.trim());
            
            if (kosong.length > 0) {
                e.preventDefault();
                const nama = kosong.map(f => f.name).join(', ');
                alert('Field wajib diisi masih kosong: ' + nama);
                kosong[0].el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                kosong[0].el.focus();
                return;
            }
            
            const pertanyaan = fields[0].el.value.trim();
            if (pertanyaan.length < 5) {
                e.preventDefault();
                alert('Pertanyaan terlalu pendek');
                fields[0].el.focus();
                return;
            }
        });
        
        document.querySelectorAll('textarea').forEach(function(textarea) {
            textarea.addEventListener('input', function() {
                this.value = this.value.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
            
            const toastEl = document.querySelector('.toast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const deleteBtn = document.querySelectorAll('.btn-hapus-soal');
            const deleteLink = document.getElementById('deleteLink');
            
            deleteBtn.forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const token = this.getAttribute('data-token');
                    deleteLink.href = '?ujian=<?= $selected_ujian ?>&hapus=' + id + '&token=' + token;
                    deleteModal.show();
                });
            });
            
            deleteLink.addEventListener('click', function(e) {
                deleteModal.hide();
            });
        });
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
                    <h4 class="fw-bold mb-2" style="color: #1e293b;">Hapus Soal?</h4>
                    <p class="text-muted mb-0">Soal yang dihapus tidak dapat dikembalikan. Apakah Anda yakin?</p>
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


    <script>
        // Bulk actions functionality
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.soal-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });
        
        document.querySelectorAll('.soal-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });
        
        function updateBulkActions() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            const bulkActions = document.querySelector('.bulk-actions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checked.length > 0) {
                bulkActions.style.display = 'inline-flex';
                selectedCount.textContent = checked.length;
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        document.getElementById('bulkDeleteBtn')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            if (checked.length === 0) return;
            
            if (confirm('Hapus ' + checked.length + ' soal yang dipilih?')) {
                const ids = Array.from(checked).map(cb => cb.value).join(',');
                window.location.href = '?ujian=<?= $selected_ujian ?>&bulk_delete=1&ids=' + ids + '&token=<?= $csrf_token ?>';
            }
        });
        
        document.getElementById('bulkEditBtn')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            if (checked.length === 0) return;
            
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('bulkEditIds').value = ids;
            document.getElementById('bulkEditModal').style.display = 'flex';
        });
        
        // Delete single soal
        document.querySelectorAll('.btn-hapus-soal').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const token = this.dataset.token;
                if (confirm('Hapus soal ini?')) {
                    window.location.href = '?ujian=<?= $selected_ujian ?>&hapus=' + id + '&token=' + token;
                }
            });
        });
        
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
        
        function updateFileName(input, labelId) {
            const label = document.getElementById(labelId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                const icon = ext === 'pdf' ? 'bi-file-pdf' : (['jpg','jpeg','png','gif','webp'].includes(ext) ? 'bi-file-image' : 'bi-file-earmark');
                label.innerHTML = '<i class="bi ' + icon + '"></i> ' + file.name;
            }
        }
        
        // Show toast message
        const toastEl = document.querySelector('.toast');
        if (toastEl) {
            new bootstrap.Toast(toastEl).show();
        }
        
        // Bulk edit modal
        document.querySelector('.close-bulk-edit')?.addEventListener('click', function() {
            document.getElementById('bulkEditModal').style.display = 'none';
        });
    </script>

    <!-- Bulk Edit Modal -->
    <div id="bulkEditModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 400px; width: 90%;">
            <h5 class="mb-3"><i class="bi bi-pencil me-2"></i>Edit Massal</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="bulk_ids" id="bulkEditIds" value="">
                <input type="hidden" name="bulk_update" value="1">
                
                <div class="mb-3">
                    <label class="form-label">Kategori Baru (kosongkan jika tidak diubah)</label>
                    <select name="bulk_kategori" class="form-control">
                        <option value="">-- Tidak diubah --</option>
                        <option value="Mudah">Mudah</option>
                        <option value="Sedang">Sedang</option>
                        <option value="Sulit">Sulit</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Poin Baru (kosongkan jika tidak diubah)</label>
                    <input type="number" name="bulk_poin" class="form-control" placeholder="Contoh: 10" min="1">
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary close-bulk-edit">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    </body>
</html>
