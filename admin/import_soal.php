<?php
// admin/import_soal.php - Import Massal soal dari Excel/CSV

session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: ../uploads/;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

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

generateCsrfToken();
$csrf_token = $_SESSION['csrf_token'];
$message = '';
$message_type = '';

$ujian_list = $conn->query("SELECT id, judul_ujian FROM ujian ORDER BY judul_ujian");
$selected_ujian = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_soal'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid';
        $message_type = 'danger';
    } else {
        $id_ujian = (int)$_POST['id_ujian'];
        
        if (empty($id_ujian)) {
            $message = 'Pilih ujian terlebih dahulu';
            $message_type = 'danger';
        } elseif (!isset($_FILES['file_import']) || $_FILES['file_import']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Pilih file yang akan diimport';
            $message_type = 'danger';
        } else {
            $file = $_FILES['file_import'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
                $message = 'Format file tidak didukung. Gunakan CSV, XLSX, atau XLS';
                $message_type = 'danger';
            } else {
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    $message = 'Gagal membuka file';
                    $message_type = 'danger';
                } else {
                    $headers = fgetcsv($handle, 0, $_POST['delimiter'] ?? ',');
                    if (!$headers) {
                        $message = 'File kosong atau format tidak valid';
                        $message_type = 'danger';
                    } else {
                        $headers = array_map('trim', $headers);
                        $headers = array_map('strtolower', $headers);
                        
                        $allowed_columns = ['pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci_jawaban', 'poin', 'kategori', 'timer_soal'];
                        $col_map = [];
                        foreach ($headers as $idx => $header) {
                            $header_clean = preg_replace('/[^a-z0-9_]/', '', $header);
                            if (in_array($header_clean, $allowed_columns)) {
                                $col_map[$header_clean] = $idx;
                            }
                        }
                        
                        $required_cols = ['pertanyaan', 'opsi_a', 'kunci_jawaban'];
                        $missing = array_diff($required_cols, array_keys($col_map));
                        
                        if (!empty($missing)) {
                            $message = "Kolom wajib tidak ditemukan: " . implode(', ', $missing);
                            $message_type = 'danger';
                        } else {
                            $imported = 0;
                            $errors = [];
                            $row_num = 2;
                            
                            $stmt = $conn->prepare("INSERT INTO soal (id_ujian, pertanyaan, gambar_pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban, poin, kategori, timer_soal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            while (($row = fgetcsv($handle, 0, $_POST['delimiter'] ?? ',')) !== FALSE) {
                                $pertanyaan = isset($row[$col_map['pertanyaan']]) ? trim($row[$col_map['pertanyaan']]) : '';
                                $opsi_a = isset($row[$col_map['opsi_a']]) ? trim($row[$col_map['opsi_a']]) : '';
                                $opsi_b = isset($row[$col_map['opsi_b']]) ? trim($row[$col_map['opsi_b']]) : '';
                                $opsi_c = isset($row[$col_map['opsi_c']]) ? trim($row[$col_map['opsi_c']]) : '';
                                $opsi_d = isset($row[$col_map['opsi_d']]) ? trim($row[$col_map['opsi_d']]) : '';
                                $opsi_e = isset($row[$col_map['opsi_e']]) ? trim($row[$col_map['opsi_e']]) : '';
                                $kunci = isset($row[$col_map['kunci_jawaban']]) ? strtolower(trim($row[$col_map['kunci_jawaban']])) : '';
                                $poin = isset($col_map['poin']) && isset($row[$col_map['poin']]) && $row[$col_map['poin']] !== '' ? max(1, (int)$row[$col_map['poin']]) : 10;
                                $kategori = (isset($col_map['kategori']) && isset($row[$col_map['kategori']]) && $row[$col_map['kategori']] !== '') ? trim($row[$col_map['kategori']]) : null;
                                $timer_soal = isset($col_map['timer_soal']) && isset($row[$col_map['timer_soal']]) && $row[$col_map['timer_soal']] !== '' ? max(0, (int)$row[$col_map['timer_soal']]) : 0;
                                
                                $gambar_pertanyaan = null;
                                $gambar_a = null;
                                $gambar_b = null;
                                $gambar_c = null;
                                $gambar_d = null;
                                $gambar_e = null;
                                
                                if (empty($pertanyaan) || empty($opsi_a) || empty($kunci)) {
                                    $errors[] = "Baris $row_num: Data tidak lengkap";
                                    $row_num++;
                                    continue;
                                }
                                
                                if (!in_array($kunci, ['a', 'b', 'c', 'd', 'e'])) {
                                    $errors[] = "Baris $row_num: Kunci jawaban tidak valid (a-e)";
                                    $row_num++;
                                    continue;
                                }
                                
                                $stmt->bind_param("isssssssssssssiis", $id_ujian, $pertanyaan, $gambar_pertanyaan, $opsi_a, $gambar_a, $opsi_b, $gambar_b, $opsi_c, $gambar_c, $opsi_d, $gambar_d, $opsi_e, $gambar_e, $kunci, $poin, $kategori, $timer_soal);
                                
                                if ($stmt->execute()) {
                                    $imported++;
                                } else {
                                    $errors[] = "Baris $row_num: Gagal menyimpan - " . $stmt->error;
                                }
                                $row_num++;
                            }
                            $stmt->close();
                            
                            if ($imported > 0) {
                                $message = "Berhasil import $imported soal!";
                                $message_type = 'success';
                            }
                            
                            if (!empty($errors)) {
                                $message .= " <br><small class='text-warning'>Errors: " . implode(', ', array_slice($errors, 0, 5));
                                if (count($errors) > 5) $message .= " ... +" . (count($errors) - 5) . " more";
                                $message .= "</small>";
                            }
                        }
                    }
                    fclose($handle);
                }
            }
        }
    }
}

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
    <title>Import soal Massal</title>
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
            --border: #e2e8f0;
        }
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f1f5f9; min-height: 100vh; }
        
        .sidebar { 
            width: 260px; min-height: 100vh; 
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            position: fixed; left: 0; top: 0; z-index: 1000;
        }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h5 { color: #fff; font-weight: 600; margin: 0; }
        .school-logo { width: 55px; height: 55px; background: rgba(255,255,255,0.15); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; }
        .sidebar a { color: rgba(255,255,255,0.7); text-decoration: none; padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar a.active { background: rgba(79, 70, 229, 0.2); color: #fff; border-left-color: var(--primary); }
        
        .main-content { margin-left: 260px; padding: 2rem; }
        
        .page-header { background: #fff; border-radius: 12px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-header h3 { margin: 0; font-weight: 600; color: var(--dark); }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { background: #fff; border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; font-weight: 600; color: var(--dark); }
        .card-body { padding: 1.5rem; }
        
        .form-label { font-weight: 500; color: var(--dark); margin-bottom: 0.5rem; }
        .form-control, .form-select { border: 1px solid var(--border); border-radius: 8px; padding: 0.625rem 0.875rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .btn { border-radius: 8px; padding: 0.625rem 1.25rem; font-weight: 500; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        
        .file-drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            background: #f8fafc;
        }
        .file-drop-zone:hover, .file-drop-zone.dragover {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.02);
        }
        .file-drop-zone i { font-size: 3rem; color: var(--secondary); }
        
        .template-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
        }
        
        .alert { border-radius: 8px; }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 4rem 1rem 1rem; }
        }
        
        .animate-fade-in { animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
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
            <a href="import_soal.php" class="active"><i class="bi bi-upload me-2"></i>Import Massal</a>
            <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
            <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
            <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
            <a href="pengumuman.php"><i class="bi bi-megaphone-fill"></i> Pengumuman</a>
            <a href="izin_remedi.php"><i class="bi bi-arrow-repeat"></i> Izin Remedi</a>
            <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
            <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header animate-fade-in">
            <h3><i class="bi bi-upload me-2"></i>Import soal Massal</h3>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-header">
                <i class="bi bi-file-earmark-arrow-up me-2"></i>Upload File
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Ujian <span class="text-danger">*</span></label>
                        <select name="id_ujian" class="form-select" required>
                            <option value="">-- Pilih Ujian --</option>
                            <?php while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>" <?= $selected_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ujian['judul_ujian']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pemisah Kolom (Delimiter)</label>
                        <select name="delimiter" class="form-select" style="max-width: 200px;">
                            <option value=",">Koma (,)</option>
                            <option value=";">Titik Koma (;)</option>
                            <option value="	">Tab</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">File (CSV/Excel) <span class="text-danger">*</span></label>
                        <div class="file-drop-zone" id="dropZone">
                            <i class="bi bi-cloud-upload"></i>
                            <p class="mt-2 mb-1">Klik atau drag file ke sini</p>
                            <small class="text-muted">Mendukung: .csv, .xlsx, .xls</small>
                            <input type="file" name="file_import" id="fileInput" accept=".csv,.xlsx,.xls" required style="display: none;">
                        </div>
                        <div id="fileName" class="mt-2 text-success" style="display: none;"></div>
                    </div>
                    
                    <button type="submit" name="import_soal" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Import soal
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card animate-fade-in">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Format Template
            </div>
            <div class="card-body">
                <p class="text-muted">Buat file CSV/Excel dengan header berikut:</p>
                <div class="template-box">
pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,poin,kategori,timer_soal
"Apa itu jaringan komputer?","Sekumpulan komputer yang saling terhubung","Sekumpulan kabel","Sekumpulan website","Sekumpulan server","Sekumpulan orang",a,10,"Jaringan",60
"Apa itu internet?","Jaringan global","Jaringan lokal","Jaringan sekolah","Jaringan rumah","Jaringan kantor",c,10,"Internet",45
                </div>
                <div class="mt-3">
                    <h6>Ket:</h6>
                    <ul class="text-muted small">
                        <li><strong>kunci_jawaban:</strong> a, b, c, d, atau e</li>
                        <li><strong>poin:</strong> default 10</li>
                        <li><strong>kategori:</strong> opsional - isi dengan: Mudah, Sedang, atau Sulit</li>
                        <li><strong>timer_soal:</strong> opsional - waktu per-soal dalam detik (0 = tidak digunakan)</li>
                    </ul>
                </div>
                <a href="download_template.php" class="btn btn-outline-primary btn-sm mt-3">
                    <i class="bi bi-download me-1"></i> Download Template CSV
                </a>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName();
            }
        });
        
        fileInput.addEventListener('change', updateFileName);
        
        function updateFileName() {
            const file = fileInput.files[0];
            const fileNameDisplay = document.getElementById('fileName');
            if (file) {
                fileNameDisplay.textContent = '✓ File dipilih: ' + file.name;
                fileNameDisplay.style.display = 'block';
            } else {
                fileNameDisplay.style.display = 'none';
            }
        }
    </script>
</body>
</html>