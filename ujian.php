<?php
// ujian.php - Halaman Ujian Siswa (Tampilan Baru)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Reset exam code setiap masuk halaman ujian — biar gak persist antar ujian
unset($_SESSION['exam_code_verified']);

require_once 'config/database.php';
require_once 'config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';
$message_type = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID ujian tidak valid");
}

$id_ujian = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM ujian WHERE id = ?");
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result = $stmt->get_result();
$ujian = $result->fetch_assoc();
$stmt->close();

if (!$ujian) {
    die("Ujian tidak ditemukan");
}

$has_scheduling = $conn->query("SHOW COLUMNS FROM ujian LIKE 'tanggal_mulai'")->num_rows > 0;
if ($has_scheduling) {
    $now = date('Y-m-d H:i:s');
    if (!empty($ujian['tanggal_mulai']) && $now < $ujian['tanggal_mulai']) {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Ujian Belum Dimulai</title>
            <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
            <link href="vendor/fonts/poppins.css" rel="stylesheet">
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
                body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card p-5 text-center">
                            <i class="bi bi-clock text-primary" style="font-size: 5rem;"></i>
                            <h2 class="mt-4 fw-bold">Ujian Belum Dimulai</h2>
                            <p class="text-muted"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
                            <p class="text-muted">Ujian akan dimulai pada <?= date('d M Y H:i', strtotime($ujian['tanggal_mulai'])) ?></p>
                            <a href="index.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    if (!empty($ujian['tanggal_selesai']) && $now > $ujian['tanggal_selesai']) {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Ujian Berakhir</title>
            <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
            <link href="vendor/fonts/poppins.css" rel="stylesheet">
            <style>
                * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
                body { background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card p-5 text-center">
                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 5rem;"></i>
                            <h2 class="mt-4 fw-bold">Ujian Telah Berakhir</h2>
                            <p class="text-muted"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
                            <p class="text-muted">Ujian telah berakhir pada <?= date('d M Y H:i', strtotime($ujian['tanggal_selesai'])) ?></p>
                            <a href="index.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left me-2"></i>Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

if ($ujian['status'] !== 'aktif') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ujian Ditutup</title>
        <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
        <link href="vendor/fonts/poppins.css" rel="stylesheet">
        <style>
            * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
            body { background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card p-5 text-center">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size: 5rem;"></i>
                        <h2 class="mt-4 fw-bold">Maaf, Ujian Ditutup</h2>
                        <p class="text-muted"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
                        <p class="text-muted">Silakan hubungi guru untuk informasi lebih lanjut.</p>
                        <a href="index.php" class="btn btn-secondary mt-3">
                            <i class="bi bi-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---- Cek apakah sudah pernah dikerjakan (untuk siswa login) ----
if (isset($_SESSION['siswa_id']) && isset($_SESSION['siswa_nis'])) {
    $check_done = $conn->prepare("SELECT id, total_skor, submitted_at FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
    $check_done->bind_param("is", $id_ujian, $_SESSION['siswa_nis']);
    $check_done->execute();
    $done_res = $check_done->get_result();
    if ($done_res->num_rows > 0) {
        $done_data = $done_res->fetch_assoc();

        // Cek apakah ada izin remedi — kalau ada, biarkan lanjut
        $cek_remedi = $conn->prepare("SELECT id FROM izin_remedi WHERE id_ujian = ? AND nis = ? LIMIT 1");
        $cek_remedi->bind_param("is", $id_ujian, $_SESSION['siswa_nis']);
        $cek_remedi->execute();
        $ada_remedi = $cek_remedi->get_result()->num_rows > 0;
        $cek_remedi->close();

        if (!$ada_remedi) {
        // Hitung total soal untuk display
        $q_total = $conn->prepare("SELECT COUNT(*) FROM soal WHERE id_ujian = ?");
        $q_total->bind_param("i", $id_ujian);
        $q_total->execute();
        $q_total->bind_result($total_soal);
        $q_total->fetch();
        $q_total->close();
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sudah Mengerjakan</title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="vendor/fonts/poppins.css" rel="stylesheet">
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 480px; }
        .nilai-angka { font-size: 2.5rem; font-weight: 700; color: #667eea; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card p-5 text-center">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    <h3 class="mt-4 fw-bold">Anda sudah menyelesaikan ujian ini</h3>
                    <p class="text-muted mb-0"><?= htmlspecialchars($ujian['judul_ujian']) ?></p>
                    <?php if ($done_data['submitted_at']): ?>
                    <p class="text-muted small">Selesai: <?= date('d M Y H:i', strtotime($done_data['submitted_at'])) ?></p>
                    <?php endif; ?>

                    <?php if ($done_data['total_skor'] !== null): ?>
                    <hr>
                    <p class="mb-1 text-muted">Nilai Anda</p>
                    <div class="nilai-angka"><?= (int)$done_data['total_skor'] ?></div>
                    <p class="text-muted">dari <?= $total_soal ?> soal</p>
                    <hr>
                    <?php endif; ?>

                    <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
                        <a href="siswa/dashboard.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-speedometer2 me-2"></i>Kembali ke Dashboard
                        </a>
                        <a href="riwayat.php?nis=<?= urlencode($_SESSION['siswa_nis']) ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-clock-history me-2"></i>Lihat Nilai Saya
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
    $check_done->close();
    }
}
// ---- End cek sudah dikerjakan ----

$stmt = $conn->prepare("SELECT * FROM soal WHERE id_ujian = ?");
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result = $stmt->get_result();
$soal_list = [];
while ($row = $result->fetch_assoc()) {
    $soal_list[] = $row;
}
$stmt->close();

if (isset($ujian['acak_soal']) && $ujian['acak_soal'] === 'ya') {
    shuffle($soal_list);
}

$soal_per_halaman = 1;

if (count($soal_list) === 0) {
    die("Belum ada soal. Hubungi guru.");
}

$soal_json = json_encode($soal_list, JSON_HEX_TAG | JSON_HEX_APOS);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ujian'])) {
    $nis = trim($_POST['nis']);
    $nama = trim($_POST['nama']);
    $kelas = trim($_POST['kelas']);
    
    if (empty($nis) || empty($nama) || empty($kelas)) {
        $message = "Mohon lengkapi data identitas!";
        $message_type = 'danger';
    } else {
        $total_skor = 0;
        $detail_jawaban = [];
        
        foreach ($soal_list as $soal) {
            $jawaban = isset($_POST['jawaban_' . $soal['id']]) ? $_POST['jawaban_' . $soal['id']] : '';
            $is_correct = ($jawaban === $soal['kunci_jawaban']);
            
            if ($is_correct) {
                $total_skor += $soal['poin'];
            }
            
            $detail_jawaban[] = [
                'soal_id' => $soal['id'],
                'pertanyaan' => $soal['pertanyaan'],
                'jawaban_siswa' => $jawaban,
                'kunci_jawaban' => $soal['kunci_jawaban'],
                'is_correct' => $is_correct,
                'poin' => $soal['poin'],
                'poin_diperoleh' => $is_correct ? $soal['poin'] : 0,
                'opsi_a' => $soal['opsi_a'],
                'opsi_b' => $soal['opsi_b'],
                'opsi_c' => $soal['opsi_c'],
                'opsi_d' => $soal['opsi_d'],
                'opsi_e' => $soal['opsi_e']
            ];
        }
        
        $detail_jawaban_json = json_encode($detail_jawaban);
        $submit_success = false;
        
        try {
            $conn->begin_transaction();
            
            $lock_name = "ujian_submit_{$id_ujian}_{$nis}";
            $lock_result = $conn->query("SELECT GET_LOCK('$lock_name', 10) AS lock_result");
            $lock_row = $lock_result->fetch_assoc();
            
            if (!$lock_row || $lock_row['lock_result'] != 1) {
                throw new Exception("Gagal mendapatkan lock. Silakan coba lagi.");
            }
            
            $check = $conn->prepare("SELECT id FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
            $check->bind_param("is", $id_ujian, $nis);
            $check->execute();
            $check_result = $check->get_result();
            $has_existing = $check_result->num_rows > 0;
            
            // Cek izin remedi kalau sudah pernah submit
            $allow_submit = true;
            if ($has_existing) {
                $cek_remedi_submit = $conn->prepare("SELECT id FROM izin_remedi WHERE id_ujian = ? AND nis = ? LIMIT 1");
                $cek_remedi_submit->bind_param("is", $id_ujian, $nis);
                $cek_remedi_submit->execute();
                $allow_submit = $cek_remedi_submit->get_result()->num_rows > 0;
                $cek_remedi_submit->close();
            }
            
            if ($has_existing && !$allow_submit) {
                $conn->query("DO RELEASE_LOCK('$lock_name')");
                $conn->rollback();
                $message = "Anda sudah submit ujian ini! Tidak ada izin remedi.";
                $message_type = 'warning';
            } else {
                $check->close();
                
                if ($detail_jawaban_json) {
                    $stmt = $conn->prepare("INSERT INTO hasil_ujian (id_ujian, nis, nama, kelas, total_skor, detail_jawaban, submitted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isssis", $id_ujian, $nis, $nama, $kelas, $total_skor, $detail_jawaban_json);
                } else {
                    $stmt = $conn->prepare("INSERT INTO hasil_ujian (id_ujian, nis, nama, kelas, total_skor, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isssi", $id_ujian, $nis, $nama, $kelas, $total_skor);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Gagal menyimpan hasil ujian.");
                }
                
                $conn->query("DO RELEASE_LOCK('$lock_name')");
                $conn->commit();
                
                $submit_success = true;
            }
        } catch (Exception $e) {
            $conn->query("DO RELEASE_LOCK('$lock_name')");
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'danger';
        }
        
        if ($submit_success) {
            ?>
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Ujian Selesai</title>
                <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
                <link href="vendor/fonts/poppins.css" rel="stylesheet">
                <style>
                    * { font-family: 'Poppins', sans-serif; }
                    body { 
                        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                        min-height: 100vh; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center;
                        overflow: hidden;
                    }
                    
                    body::before {
                        content: '';
                        position: absolute;
                        width: 200%;
                        height: 200%;
                        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%);
                        animation: pulse 4s ease-in-out infinite;
                    }
                    
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); opacity: 0.5; }
                        50% { transform: scale(1.1); opacity: 0.3; }
                    }
                    
                    .card { 
                        border: none; 
                        border-radius: 24px; 
                        box-shadow: 0 25px 80px rgba(0,0,0,0.25);
                        position: relative;
                        overflow: hidden;
                        animation: slideUp 0.6s ease-out;
                    }
                    
                    @keyframes slideUp {
                        from { opacity: 0; transform: translateY(30px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    
                    .success-icon {
                        width: 120px;
                        height: 120px;
                        border-radius: 50%;
                        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto;
                        animation: scaleIn 0.5s ease-out 0.3s both;
                        box-shadow: 0 10px 40px rgba(17, 153, 142, 0.4);
                    }
                    
                    @keyframes scaleIn {
                        from { transform: scale(0); }
                        to { transform: scale(1); }
                    }
                    
                    .success-icon i {
                        font-size: 4rem;
                        color: white;
                        animation: checkBounce 0.5s ease-out 0.6s both;
                    }
                    
                    @keyframes checkBounce {
                        from { transform: scale(0) rotate(-45deg); }
                        50% { transform: scale(1.2) rotate(0deg); }
                        to { transform: scale(1) rotate(0deg); }
                    }
                    
                    .skor-box { 
                        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                        -webkit-background-clip: text; 
                        -webkit-text-fill-color: transparent; 
                        font-size: 5rem; 
                        font-weight: 700; 
                        animation: countUp 1s ease-out 0.8s both;
                    }
                    
                    @keyframes countUp {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    
                    .info-card {
                        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                        border-radius: 16px;
                        padding: 20px;
                        animation: slideUp 0.6s ease-out 0.5s both;
                    }
                    
                    .btn-home {
                        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                        border: none;
                        padding: 15px 40px;
                        border-radius: 30px;
                        font-weight: 600;
                        font-size: 1.1rem;
                        transition: all 0.3s ease;
                        box-shadow: 0 10px 30px rgba(17, 153, 142, 0.3);
                        animation: slideUp 0.6s ease-out 1s both;
                    }
                    
                    .btn-home:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 15px 40px rgba(17, 153, 142, 0.4);
                    }
                    
                    .confetti {
                        position: absolute;
                        width: 10px;
                        height: 10px;
                        border-radius: 50%;
                        animation: fall 3s ease-in-out infinite;
                    }
                    
                    @keyframes fall {
                        0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
                        100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="card p-5 text-center">
                                <!-- Confetti -->
                                <div class="confetti" style="left: 10%; background: #ff6b6b; animation-delay: 0s;"></div>
                                <div class="confetti" style="left: 30%; background: #ffd93d; animation-delay: 0.5s;"></div>
                                <div class="confetti" style="left: 50%; background: #6bcb77; animation-delay: 1s;"></div>
                                <div class="confetti" style="left: 70%; background: #4d96ff; animation-delay: 1.5s;"></div>
                                <div class="confetti" style="left: 90%; background: #ff6b6b; animation-delay: 2s;"></div>
                                
                                <div class="success-icon mb-4">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                
                                <h2 class="fw-bold mb-2" style="animation: slideUp 0.6s ease-out 0.4s both;">Selamat!</h2>
                                <p class="text-muted mb-4" style="animation: slideUp 0.6s ease-out 0.5s both;">Jawaban Anda telah berhasil disubmit</p>
                                
                                <?php if (!isset($ujian['tampilkan_skor']) || $ujian['tampilkan_skor'] === 'ya'): ?>
                                <div class="my-4" style="animation: slideUp 0.6s ease-out 0.6s both;">
                                    <p class="text-muted mb-2 fw-medium">Total Skor Anda</p>
                                    <div class="skor-box"><?= $total_skor ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-card mb-4">
                                    <div class="row">
                                        <div class="col-12">
                                            <p class="mb-2"><strong class="fs-5"><?= htmlspecialchars($nama) ?></strong></p>
                                        </div>
                                        <div class="col-6 text-start">
                                            <p class="mb-0 text-muted small">NIS</p>
                                            <p class="mb-0 fw-semibold"><?= htmlspecialchars($nis) ?></p>
                                        </div>
                                        <div class="col-6 text-end">
                                            <p class="mb-0 text-muted small">Kelas</p>
                                            <p class="mb-0 fw-semibold"><?= htmlspecialchars($kelas) ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <a href="index.php" class="btn btn-home text-white">
                                    <i class="bi bi-house-door me-2"></i>Kembali ke Halaman Utama
                                </a>
                                <?php if (isset($ujian['tampilkan_review']) && $ujian['tampilkan_review'] === 'ya'): ?>
                                <a href="review.php?nis=<?= urlencode($nis) ?>&id_ujian=<?= $id_ujian ?>" class="btn btn-outline-primary mt-3">
                                    <i class="bi bi-card-checklist me-2"></i>Lihat Pembahasan
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            $message = "Terjadi kesalahan. Coba lagi.";
            $message_type = 'danger';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang id>
<head>
    <meta charset UTF-8>
    <meta name viewport content width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no>
    <meta name description content Ujian Online>
    <meta name mobile-web-app-capable content yes>
    <meta name apple-mobile-web-app-capable content yes>
    <meta name apple-mobile-web-app-status-bar-style content default>
<title><?= htmlspecialchars($ujian['judul_ujian']) ?> - Ujian Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="vendor/fonts/poppins.css" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; -webkit-font-smoothing: antialiased; box-sizing: border-box; }
        body { background: #f0f2f5; margin: 0; padding: 0; }
        img { max-width: 100%; height: auto; }
        
/* Header */
.ujian-header {
    background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
    padding: 30px 15px 40px;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
}
.ujian-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.ujian-header .school-logo {
    width: 70px;
    height: 70px;
    background: white;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    margin-bottom: 15px;
}
.ujian-header .school-logo img {
    width: 50px;
    height: 50px;
    object-fit: contain;
}
.ujian-header .school-logo i {
    font-size: 2rem;
    color: #667eea;
}
.ujian-header .school-name {
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 15px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.ujian-header .header-content .back-link {
    display: inline-block;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 12px;
}
.ujian-header .header-content .back-link:hover {
    color: white;
}
.ujian-header .exam-title {
    color: white;
    font-weight: 700;
    font-size: 1.4rem;
    margin-bottom: 8px;
}
.ujian-header .exam-desc {
    color: rgba(255,255,255,0.8);
    font-size: 0.9rem;
    margin-bottom: 15px;
}
.ujian-header .header-badges {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}
.ujian-header .badge-item {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    backdrop-filter: blur(4px);
}
.ujian-header .badge-item.warning {
    background: #ffc107;
    color: #333;
}
        .ujian-header .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        /* Container */
        .container { padding: 0 15px 20px; max-width: 600px; margin-top: -15px; position: relative; }
        
        /* Cards */
        .exam-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .exam-card h5 {
            color: #1a1a2e;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Form Inputs */
        .exam-card .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .exam-card .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .exam-card label {
            font-weight: 500;
            font-size: 0.9rem;
            color: #444;
            margin-bottom: 6px;
        }
        
        /* Buttons */
        .btn-start {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            margin-top: 10px;
        }
        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.3);
        }
        
        .btn-code {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        /* Question Card */
        .soal-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 15px;
        }
        .soal-card .soal-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            margin-right: 10px;
        }
        .soal-card .question {
            font-weight: 500;
            font-size: 1rem;
            color: #1a1a2e;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        /* Options */
        .option-label {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option-label:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .option-label input:checked + .option-letter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .option-letter {
            background: #f0f0f0;
            color: #555;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 12px;
            transition: all 0.2s;
        }
        .option-content {
            flex: 1;
            color: #333;
            font-size: 0.95rem;
        }
        
        /* Navigation */
        .soal-navigator {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 15px;
            margin-bottom: 15px;
        }
        .soal-navigator .nav-info {
            text-align: center;
            margin-bottom: 12px;
            color: #666;
            font-size: 0.9rem;
        }
        .soal-navigator .nav-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }
        .soal-navigator .nav-buttons button {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .soal-navigator .soal-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .soal-navigator .soal-grid button {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        /* Progress */
        .progress-indicator {
            position: fixed;
            bottom: 15px;
            right: 15px;
            background: white;
            padding: 10px 15px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 100;
        }
        .progress-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
        }
        
        .progress-text {
            font-size: 0.85rem;
            color: #444;
        }
        
        /* Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
        }
        
/* Responsive Desktop */
        @media (min-width: 768px) {
            .container { max-width: 700px; margin: -20px auto 20px; padding: 0 20px; }
            .ujian-header { padding: 40px 20px 50px; margin-bottom: 30px; }
            .ujian-header .school-logo { width: 80px; height: 80px; }
            .ujian-header .school-logo img { width: 60px; height: 60px; }
            .ujian-header .school-name { font-size: 1.3rem; }
            .ujian-header .exam-title { font-size: 1.8rem; }
            .ujian-header .exam-desc { font-size: 1rem; }
            .soal-card, .exam-card { padding: 25px; border-radius: 20px; }
            .option-label { padding: 15px 20px; }
            .btn-start, .btn-submit { width: auto; display: inline-block; }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Timer Warning Styles */
        @keyframes blinkRed {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .timer-danger {
            animation: blinkRed 1s infinite !important;
            color: #dc3545 !important;
        }
        .timer-warning {
            color: #ffc107 !important;
        }
        
        /* Summary Modal */
        .summary-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .summary-content {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .summary-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .summary-header h4 {
            color: #333;
            font-weight: 600;
        }
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
        }
        .summary-message {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            font-weight: 500;
        }
        .unanswered-list {
            max-height: 150px;
            overflow-y: auto;
        }
        .unanswered-list .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .unanswered-list .button-group button {
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .unanswered-list .button-group button:hover {
            background: #f0f0f0;
        }
        .summary-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .summary-footer button {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-success {
            background: #198754;
            color: white;
        }

        /* ─── Toast Notification ─── */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }
        .toast-container .toast-item {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            color: white;
            font-size: 0.9rem;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-width: 380px;
            min-width: 280px;
        }
        .toast-container .toast-item.show { transform: translateX(0); }
        .toast-container .toast-item.toast-success { background: #10b981; }
        .toast-container .toast-item.toast-error { background: #ef4444; }
        .toast-container .toast-item.toast-warning { background: #f59e0b; }
        .toast-container .toast-item.toast-info { background: #3b82f6; }
        .toast-container .toast-icon { font-size: 1.3rem; flex-shrink: 0; }
        .toast-container .toast-text { flex: 1; }
        .toast-container .toast-text strong { display: block; font-weight: 600; }
        .toast-container .toast-text small { opacity: 0.9; font-size: 0.85rem; }

        /* ─── Auto-save Status Enhanced ─── */
        #autoSaveStatus {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
            background: transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        #autoSaveStatus.saving {
            background: #fef3c7;
            color: #92400e;
        }
        #autoSaveStatus.saved {
            background: #d1fae5;
            color: #065f46;
        }
        #autoSaveStatus.error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ─── Confirmation Modal ─── */
        .confirm-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }
        .confirm-content {
            background: white;
            border-radius: 20px;
            max-width: 420px;
            width: 90%;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Header -->
    <div class="ujian-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 text-center">
                    <div class="school-logo">
                        <?php if ($sekolah['logo'] && file_exists('uploads/' . $sekolah['logo'])): ?>
                            <img src="uploads/<?= $sekolah['logo'] ?>" alt="Logo">
                        <?php else: ?>
                            <i class="bi bi-mortarboard-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div class="school-name"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></div>
                    
                    <div class="header-content">
                        <a href="index.php" class="back-link">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <h1 class="exam-title"><?= htmlspecialchars($ujian['judul_ujian']) ?></h1>
                        <?php if ($ujian['deskripsi']): ?>
                        <p class="exam-desc"><?= htmlspecialchars($ujian['deskripsi']) ?></p>
                        <?php endif; ?>
                        
                        <div class="header-badges">
                            <span class="badge-item">
                                <i class="bi bi-question-circle"></i> <?= count($soal_list) ?> Soal
                            </span>
                            <?php if (isset($ujian['waktu_tersedia']) && $ujian['waktu_tersedia'] > 0): ?>
                            <span class="badge-item warning" id="timerBadge">
                                <i class="bi bi-stopwatch"></i> <span id="timerDisplay"><?= floor((int)$ujian['waktu_tersedia'] / 60) ?>:<?= sprintf('%02d', (int)$ujian['waktu_tersedia'] % 60) ?>:00</span>
                            </span>
                            <?php elseif (isset($ujian['durasi_per_soal']) && $ujian['durasi_per_soal'] > 0): ?>
                            <span class="badge-item warning" id="perSoalTimerBadge">
                                <i class="bi bi-hourglass-split"></i> <span id="perSoalTimerDisplay"><?= floor((int)$ujian['durasi_per_soal'] / 60) ?>:<?= sprintf('%02d', (int)$ujian['durasi_per_soal'] % 60) ?></span>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Exam Code Form (if required) -->
        <?php if (!empty($ujian['kode_ujian'])): ?>
        <div id="examCodeForm" class="exam-card" style="<?= empty($_SESSION['exam_code_verified']) ? '' : 'display: none;' ?>">
            <h5><i class="bi bi-shield-lock text-primary"></i> Kode Ujian</h5>
            <div class="mb-3">
                <label class="form-label">Masukkan Kode Ujian <span class="text-danger">*</span></label>
                <input type="text" id="kodeUjianInput" class="form-control" placeholder="Masukkan kode rahasia" autocomplete="off">
            </div>
            <button type="button" class="btn-code" onclick="verifyExamCode()">
                <i class="bi bi-check2-circle me-2"></i>Masuk Ujian
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Main Exam Content -->
        <div id="examContent" style="<?= (!empty($ujian['kode_ujian']) && empty($_SESSION['exam_code_verified'])) ? 'display:none;' : '' ?>">
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="formUjian">
            <!-- Identitas - Tampil Pertama -->
            <div id="identitySection">
                <div class="exam-card">
                    <h5><i class="bi bi-person-badge text-primary"></i> Identitas Siswa</h5>
                    <?php
                    $prefill_nis = isset($_SESSION['siswa_nis']) ? htmlspecialchars($_SESSION['siswa_nis']) : '';
                    $prefill_nama = isset($_SESSION['siswa_nama']) ? htmlspecialchars($_SESSION['siswa_nama']) : '';
                    $prefill_kelas = isset($_SESSION['siswa_kelas']) ? htmlspecialchars($_SESSION['siswa_kelas']) : '';
                    ?>
                    <div class="mb-3">
                        <label class="form-label">NIS/Nomor Ujian <span class="text-danger">*</span></label>
                        <input type="text" name="nis" id="nisInput" class="form-control" required placeholder="Masukkan NIS/Nomor Ujian" value="<?= $prefill_nis ?>" <?= $prefill_nis ? 'readonly' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="namaInput" class="form-control" required placeholder="Masukkan nama" value="<?= $prefill_nama ?>" <?= $prefill_nama ? 'readonly' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <input type="text" name="kelas" id="kelasInput" class="form-control" required placeholder="Contoh: X-1" value="<?= $prefill_kelas ?>" <?= $prefill_kelas ? 'readonly' : '' ?>>
                    </div>
                    <button type="button" class="btn-start" onclick="startWithIdentity()">
                        <i class="bi bi-play-fill me-2"></i>Mulai Ujian
                    </button>
                </div>
            </div>

            <!-- Daftar Soal - Muncul Setelah Identitas -->
            <div id="questionSection" style="display: none;">
            <div id="soalContainer"></div>
            
            <div id="loadingIndicator" style="text-align: center; padding: 20px; color: #666;">
                <div style="border: 3px solid #f3f3f3; border-top: 3px solid #667eea; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                <p style="margin-top: 10px; font-size: 0.9rem;">Memuat soal...</p>
            </div>
            
            <div id="loadMoreSection" class="text-center mb-4" style="display: none;">
                <button type="button" class="btn btn-outline-primary" onclick="loadMoreSoal()">
                    <i class="bi bi-chevron-down me-2"></i>Lihat Lebih Banyak
                </button>
            </div>

<!-- Navigasi Superior -->
            <div class="soal-navigator">
                <div class="nav-info">
                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem;">
                        <i class="bi bi-file-text"></i> Soal <span id="currentPage">1</span> dari <span id="totalPages">1</span>
                    </span>
                    <span id="progressMobile" style="color: #666; font-size: 0.8rem; margin-left: 8px; display: none;">(0/0)</span>
                </div>
                
                <div class="nav-buttons">
                    <button type="button" class="btn btn-outline-secondary" onclick="prevPage()" id="prevBtn" disabled>
                        <i class="bi bi-chevron-left"></i> Sebelumnya
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="nextPage()" id="nextBtn">
                        Selanjutnya <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                
                <div class="soal-grid" id="soalNumbersContainer">
</div>
            </div>

            <!-- Submit -->
            <div style="text-align: center; margin-bottom: 20px;" id="submitSection">
                <button type="button" class="btn-submit" onclick="submitFinal()">
                    <i class="bi bi-send-fill me-2"></i>Kirim Jawaban
                </button>
            </div>
            </div><!-- End questionSection -->
        </form>
        </div><!-- End examContent -->

        <!-- Progress Indicator -->
        <div class="progress-indicator" id="progressIndicator">
            <div class="progress-circle">
                <span id="answeredCount">0/<?= count($soal_list) ?></span>
            </div>
            <div class="progress-text">
                <div class="fw-bold">Soal Terjawab</div>
                <small class="text-muted" id="progressPercent">0/<?= count($soal_list) ?></small>
                <small class="d-block" id="autoSaveStatus"><i class="bi bi-cloud text-muted"></i> Auto-save</small>
            </div>
        </div>
        
        <div class="mb-3" id="raguNavContainer" style="display: none;">
            <button type="button" class="btn btn-warning btn-sm w-100" onclick="showRaguList()">
                <i class="bi bi-exclamation-circle"></i> Lihat Soal Ragu-ragu (<span id="raguNavCount">0</span>)
            </button>
        </div>
        
        <!-- Summary Modal Before Submit -->
        <div id="summaryModal" class="summary-modal" style="display: none;">
            <div class="summary-content">
                <div class="summary-header">
                    <h4><i class="bi bi-clipboard-check text-primary"></i> Ringkasan Sebelum Submit</h4>
                </div>
                <div class="summary-body">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-number text-primary" id="summaryTotal">0</div>
                            <div class="stat-label">Total Soal</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number text-success" id="summaryAnswered">0</div>
                            <div class="stat-label">Dijawab</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number text-danger" id="summaryUnanswered">0</div>
                            <div class="stat-label">Belum Dijawab</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number text-warning" id="summaryRagu">0</div>
                            <div class="stat-label">Ragu-ragu</div>
                        </div>
                    </div>
                    
                    <div class="summary-message mt-3" id="summaryMessage"></div>
                    
                    <div class="unanswered-list mt-3" id="unansweredList" style="display: none;">
                        <h6><i class="bi bi-exclamation-circle text-warning"></i> Soal Belum Dijawab:</h6>
                        <div class="button-group" id="unansweredButtons"></div>
                    </div>
                    <div class="ragu-list mt-3" id="raguList" style="display: none;">
                        <h6><i class="bi bi-exclamation-circle text-warning"></i> Soal Ragu-ragu:</h6>
                        <div class="button-group" id="raguButtons"></div>
                    </div>
                </div>
                <div class="summary-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSummary()">
                        <i class="bi bi-pencil-square me-2"></i>Kembali Kerjakan
                    </button>
                    <button type="button" class="btn btn-success" onclick="confirmSubmit()">
                        <i class="bi bi-send-fill me-2"></i>Submit Sekarang
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal (step after summary) -->
        <div id="confirmModal" class="confirm-modal" style="display: none;">
            <div class="confirm-content text-center">
                <div style="font-size: 3rem; margin-bottom: 10px;">🤔</div>
                <h4 class="fw-bold mb-2">Yakin ingin mengumpulkan?</h4>
                <p class="text-muted mb-1" id="confirmMessage">Setelah dikumpulkan, jawaban tidak bisa diubah lagi.</p>
                <p class="text-muted small mb-3" id="confirmDetail"></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" onclick="closeConfirm()">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </button>
                    <button type="button" class="btn btn-danger px-4" onclick="doSubmitFinal()">
                        <i class="bi bi-send-fill me-1"></i>Ya, Kumpulkan!
                    </button>
                </div>
            </div>
        </div>
        
        <footer class="text-center text-muted py-4">
            <small>&copy; <?= date('Y') ?> Sistem Ujian Online - by natedekaka</small>
        </footer>
        
        <!-- Audio for timer warning -->
        <audio id="tickSound" preload="auto">
            <source src="data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=" type="audio/wav">
        </audio>
    </div>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        const API_URL = 'api/submit_jawaban.php';
        const ID_UJIAN = <?= $id_ujian ?>;
        const HAS_EXAM_CODE = <?= !empty($ujian['kode_ujian']) ? 'true' : 'false' ?>;
        const SOAL_DATA = <?= $soal_json ?>;
        const ACAK_OPSI = <?= isset($ujian['acak_opsi']) && $ujian['acak_opsi'] === 'ya' ? 'true' : 'false' ?>;
        const SOAL_PER_HALAMAN = <?= $soal_per_halaman ?>;
        
        const STORAGE_KEY = 'exam_' + ID_UJIAN;
        const IDENTITY_KEY = 'exam_identity_' + ID_UJIAN;
        let currentPage = 1;
        let displayedSoal = [];
        let optionsCache = {};
        let answers = {};
        let identitySaved = false;
        let csrfToken = '';
        let lastSaved = null;
        let tickSoundPlayed = false;
        let timerWarningShown = false;
        let lastAutoSaveTime = 0;
        let fullscreenExitHandler = null;
        let fsViolationCount = 0;
        let wasFs = false;
        
        function init() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
                return;
            }
            
            if (!SOAL_DATA || SOAL_DATA.length === 0) {
                console.error('No questions loaded!');
                return;
            }
            
            const totalSoal = SOAL_DATA.length;
            const totalPages = Math.ceil(totalSoal / SOAL_PER_HALAMAN);
            document.getElementById('totalPages').textContent = totalPages;
            
            document.getElementById('answeredCount').textContent = '0/' + totalSoal;
            
            const identitySection = document.getElementById('identitySection');
            const questionSection = document.getElementById('questionSection');
            const examCodeForm = document.getElementById('examCodeForm');
            const examContent = document.getElementById('examContent');
            
            if (HAS_EXAM_CODE && examCodeForm) {
                examCodeForm.style.display = 'block';
                examContent.style.display = 'none';
                identitySection.style.display = 'none';
                questionSection.style.display = 'none';
            } else {
                examContent.style.display = 'block';
                <?php if (isset($_SESSION['siswa_id']) && empty($ujian['kode_ujian'])): ?>
                // Logged-in student - hide identity section immediately (no flash)
                identitySection.style.display = 'none';
                questionSection.style.display = 'block';
                document.getElementById('loadingIndicator').style.display = 'block';
                <?php else: ?>
                identitySection.style.display = 'block';
                questionSection.style.display = 'none';
                <?php endif; ?>
            }
            
            loadFromLocalStorage();

            <?php if (isset($_SESSION['siswa_id'])): ?>
            // Student is logged in - mark identity as saved
            identitySaved = true;
            
            <?php if (empty($ujian['kode_ujian'])): ?>
            // Auto-start exam for logged-in students (no exam code needed)
            setTimeout(startWithIdentity, 100);
            <?php endif; ?>
            <?php endif; ?>
        }
        
        function loadFromLocalStorage() {
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    const data = JSON.parse(saved);
                    if (data.answers) {
                        answers = data.answers;
                        optionsCache = data.optionsCache || {};
                        currentPage = data.currentPage || 1;
                        lastSaved = data.timestamp;
                        
                        if (data.identity) {
                            document.getElementById('nisInput').value = data.identity.nis || '';
                            document.getElementById('namaInput').value = data.identity.nama || '';
                            document.getElementById('kelasInput').value = data.identity.kelas || '';
                            identitySaved = true;
                            localStorage.setItem('exam_nis', data.identity.nis);
                        }
                        
                        updateProgress();
                        document.getElementById('autoSaveStatus').className = 'saved';
                        document.getElementById('autoSaveStatus').innerHTML = 
                            '<i class="bi bi-cloud-check-fill"></i> Dipulihkan dari cache';
                    }
                }
            } catch (e) {
                console.log('Tidak ada cache tersimpan');
            }
        }
        
        function saveToLocalStorage() {
            try {
                const data = {
                    answers: answers,
                    optionsCache: optionsCache,
                    currentPage: currentPage,
                    timestamp: Date.now()
                };
                if (identitySaved) {
                    data.identity = {
                        nis: document.getElementById('nisInput').value,
                        nama: document.getElementById('namaInput').value,
                        kelas: document.getElementById('kelasInput').value
                    };
                }
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            } catch (e) {
                console.log('Gagal menyimpan ke localStorage');
            }
        }
        
        function startWithIdentity() {
            const nis = document.getElementById('nisInput').value.trim();
            const nama = document.getElementById('namaInput').value.trim();
            const kelas = document.getElementById('kelasInput').value.trim();
            
            if (!nis || !nama || !kelas) {
                showToast('Lengkapi identitas terlebih dahulu!', 'warning');
                return;
            }
            
            document.getElementById('identitySection').style.display = 'none';
            document.getElementById('questionSection').style.display = 'block';
            document.getElementById('loadingIndicator').style.display = 'block';
            
            loadPage(1);
            
            // Reset submission flag and activate fullscreen mode
            isSubmittingExam = false;
            enterFullscreen();

            // Activate force fullscreen exit detection
            setTimeout(initFullscreenExitDetection, 300);
            
            // Initialize in background without waiting
            setTimeout(initExamFeatures, 500);
            
            // Mobile overlay detection (IntersectionObserver)
            setTimeout(initOverlayObserver, 1000);
            
            // Minta Wake Lock agar HP tidak sleep (opsional, bukan pelanggaran jika sleep)
            setTimeout(requestWakeLock, 2000);
        }
        
          function enterFullscreen() {
              const elem = document.documentElement;
              try {
                  if (elem.requestFullscreen) {
                      elem.requestFullscreen().catch(e => console.warn('Fullscreen request failed:', e));
                  } else if (elem.webkitRequestFullscreen) { /* Safari */
                      elem.webkitRequestFullscreen();
                  } else if (elem.msRequestFullscreen) { /* IE11 */
                      elem.msRequestFullscreen();
                  }
              } catch(e) {
                  console.warn('Fullscreen error:', e);
              }
          }
         
          function exitFullscreen() {
              if (document.fullscreenElement) {
                  if (document.exitFullscreen) {
                      document.exitFullscreen();
                  } else if (document.webkitExitFullscreen) { /* Safari */
                      document.webkitExitFullscreen();
                  } else if (document.msExitFullscreen) { /* IE11 */
                      document.msExitFullscreen();
                  }
              }
          }

          // === Screen Wake Lock API: cegah HP sleep selama ujian ===
          let wakeLockObj = null;
          
          async function requestWakeLock() {
              try {
                  if ('wakeLock' in navigator) {
                      wakeLockObj = await navigator.wakeLock.request('screen');
                      wakeLockObj.addEventListener('release', () => {
                          console.log('Wake Lock released');
                      });
                      console.log('Wake Lock active — screen will not sleep');
                  } else {
                      console.log('Wake Lock API not supported on this device');
                  }
              } catch (e) {
                  console.warn('Wake Lock request failed:', e.name, e.message);
              }
          }
          
          function releaseWakeLock() {
              if (wakeLockObj) {
                  wakeLockObj.release();
                  wakeLockObj = null;
                  console.log('Wake Lock released manually');
              }
          }
          
          function initFullscreenExitDetection() {
              wasFs = !!document.fullscreenElement || !!document.webkitFullscreenElement;
              let fsGraceTimer = null;
              const FS_GRACE_PERIOD = 10000; // 10 detik grace period

              function handleFsChange() {
                  if (examFinished) return;
                  const isFsNow = !!(document.fullscreenElement || document.webkitFullscreenElement);
                  
                  // Jika HP sleep / layar mati — jangan anggap pelanggaran
                  if (document.hidden) { wasFs = isFsNow; return; }
                  
                  if (wasFs && !isFsNow && !isSubmittingExam) {
                      // Grace period: tunggu 10 detik sebelum catat pelanggaran
                      if (fsGraceTimer) clearTimeout(fsGraceTimer);
                      fsGraceTimer = setTimeout(() => {
                          if (examFinished) return;
                          fsViolationCount++;
                          logViolation('exit_fullscreen', 'Siswa keluar dari mode fullscreen (force)');
                          const maxViol = <?= (int)($ujian['max_violations'] ?? 10) ?>;
                          
                          if (fsViolationCount >= maxViol) {
                              showFsModal('ANDA TERLALU SERING KELUAR DARI FULLSCREEN.<br>Jawaban akan disubmit!', true);
                          } else {
                              showFsModal('PERINGATAN: Jangan keluar dari fullscreen!<br>Pelanggaran: ' + fsViolationCount + '/' + maxViol + '<br><small class="text-danger">Setiap pelanggaran akan mengurangi 10 poin dari nilai akhir!</small>', false);
                          }
                      }, FS_GRACE_PERIOD);
                  } else if (!wasFs && isFsNow) {
                      // Kembali ke fullscreen dalam grace period — batal
                      if (fsGraceTimer) {
                          clearTimeout(fsGraceTimer);
                          fsGraceTimer = null;
                      }
                  }
                  wasFs = isFsNow;
              }

              // === HP Sleep Handling: batalkan grace timer saat layar mati ===
              function handleVisibilityChange() {
                  if (examFinished) return;
                  if (document.hidden) {
                      // HP sleep / pindah tab — batalkan grace timer fullscreen
                      if (fsGraceTimer) {
                          clearTimeout(fsGraceTimer);
                          fsGraceTimer = null;
                      }
                      // Reset state agar tidak dianggap "exit fullscreen" saat bangun
                      wasFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
                  } else {
                      // Bangun dari sleep — coba masuk fullscreen lagi
                      if (!(document.fullscreenElement || document.webkitFullscreenElement)) {
                          setTimeout(enterFullscreen, 500);
                      }
                  }
              }

              document.removeEventListener('fullscreenchange', fullscreenExitHandler);
              document.removeEventListener('webkitfullscreenchange', fullscreenExitHandler);
              document.removeEventListener('visibilitychange', fullscreenExitHandler);

              fullscreenExitHandler = handleFsChange;
              document.addEventListener('fullscreenchange', fullscreenExitHandler);
              document.addEventListener('webkitfullscreenchange', fullscreenExitHandler);
              document.addEventListener('visibilitychange', handleVisibilityChange);
          }

          function showFsModal(message, isFinal) {
              const oldModal = document.getElementById('fsModal');
              if (oldModal) oldModal.remove();
              
              const overlay = document.createElement('div');
              overlay.id = 'fsModal';
              overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:2147483647;display:flex;align-items:center;justify-content:center;cursor:pointer;';
              
              const box = document.createElement('div');
              box.style.cssText = 'background:white;border-radius:16px;padding:35px;text-align:center;max-width:420px;width:90%;box-shadow:0 25px 80px rgba(0,0,0,0.5);animation:fsSlideUp 0.3s ease-out;cursor:default;';
              
              box.innerHTML = `
                  <i class="bi bi-exclamation-triangle-fill" style="font-size:3.5rem;color:#ffc107;"></i>
                  <h4 class="mt-3 mb-3 fw-bold">Peringatan Fullscreen</h4>
                  <p class="text-muted mb-4" style="font-size:1rem;">${message}</p>
                  <button id="fsModalBtn" type="button" class="btn btn-warning px-5 py-2" style="border-radius:10px;font-weight:600;font-size:1rem;pointer-events:auto;">
                      <i class="bi bi-arrows-fullscreen me-2"></i>Kembali ke Fullscreen
                  </button>
              `;
              
              overlay.appendChild(box);
              document.body.appendChild(overlay);
              
              // Use onclick for more reliable event handling
              document.getElementById('fsModalBtn').onclick = function(e) {
                  e.stopPropagation();
                  overlay.remove();
                  // Re-enter fullscreen - this is a valid user gesture
                  const elem = document.documentElement;
                  if (elem.requestFullscreen) {
                      elem.requestFullscreen().then(() => {
                          console.log('Fullscreen re-entered from modal');
                          if (isFinal) setTimeout(submitFinal, 500);
                       }).catch(e => {
                           console.error('Failed to re-enter fullscreen:', e);
                           showToast('Gagal masuk fullscreen. Coba lagi.', 'warning');
                       });
                  } else if (elem.webkitRequestFullscreen) {
                      elem.webkitRequestFullscreen();
                      if (isFinal) setTimeout(submitFinal, 500);
                  }
                  return false;
              };
          }
        
function initExamFeatures() {
            // Fetch client IP address (local)
            fetch('api/get_ip.php')
                .then(r => r.json())
                .then(data => {
                    localStorage.setItem('exam_ip', data.ip);
                })
                .catch(() => {
                    localStorage.setItem('exam_ip', '');
                });

            fetch(API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'generate_token'})
            })
            .then(r => r.json())
            .then(data => {
                if (data.csrf_token) csrfToken = data.csrf_token;
                
                <?php if (!empty($ujian['enable_browser_lock']) && $ujian['enable_browser_lock'] === 'ya'): ?>
                initBrowserLock();
                <?php endif; ?>
                <?php if (!empty($ujian['enable_device_check']) && $ujian['enable_device_check'] === 'ya'): ?>
                checkDeviceFingerprint();
                <?php endif; ?>
            })
            .catch(e => console.log('Token init skipped'));
            
            const savedNis = localStorage.getItem('exam_nis');
            if (savedNis) {
                checkCompletion(savedNis);
            }
        }
        
        function renderSoal(soalList) {
            if (!soalList || soalList.length === 0) {
                return '<div class=alert alert-warning>Tidak ada soal untuk ditampilkan</div>';
            }
            
            let html = '';
            let no = (currentPage - 1) * SOAL_PER_HALAMAN + 1;
            
            for (let i = 0; i < soalList.length; i++) {
                const soal = soalList[i];
                let options = {a:{t:soal.opsi_a,i:soal.gambar_a},b:{t:soal.opsi_b,i:soal.gambar_b},c:{t:soal.opsi_c,i:soal.gambar_c},d:{t:soal.opsi_d,i:soal.gambar_d},e:{t:soal.opsi_e,i:soal.gambar_e}};
                
                if (ACAK_OPSI) {
                    if (!optionsCache[soal.id]) {
                        const keys = Object.keys(options);
                        for (let j = keys.length - 1; j > 0; j--) {
                            const k = Math.floor(Math.random() * (j + 1));
                            [keys[j], keys[k]] = [keys[k], keys[j]];
                        }
                        const shuffled = {};
                        keys.forEach(k => shuffled[k] = options[k]);
                        optionsCache[soal.id] = shuffled;
                    }
                    options = optionsCache[soal.id];
                }
                
                const soalId = soal.id;
                const answered = answers[soalId] || '';
                
                html += '<div class=soal-card><div class=d-flex align-items-start mb-3><span class=soal-number>' + no + '</span><div class=flex-grow-1><p class=mb-2 fw-medium>' + soal.pertanyaan.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</p>';
                if (soal.gambar_pertanyaan) html += '<img src=uploads/' + soal.gambar_pertanyaan + ' class=soal-img alt=Gambar alt=true>';
                html += '<small class=text-muted d-block mt-2>Poin: ' + soal.poin + '</small></div></div><div class=ms-5>';
                
                for (const key in options) {
                    const opt = options[key];
                    const checked = answered === key ? ' checked' : '';
                    html += '<label class=option-label><input type=radio name=jawaban_' + soalId + ' value=' + key + checked + ' class=d-none onchange=updateProgress()><span class=option-letter>' + key.toUpperCase() + '</span><span class=option-content>';
                    if (opt.t) html += '<span class=opsi-text>' + opt.t.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span>';
                    if (opt.i) html += '<img src=uploads/' + opt.i + ' class=opsi-img alt=Opsi ' + key + '>';
                    html += '</span></label>';
                }
                
                html += '</div></div>';
                
                // Add Ragu-ragu checkbox
                const isRagu = raguRagu[soalId] || false;
                html += '<div class="mt-3"><label style="cursor: pointer;"><input type="checkbox" id="ragu_' + soalId + '" onchange="toggleRagu(' + soalId + ')" ' + (isRagu ? 'checked' : '') + '> <span style="color: #ffc107; font-size: 0.9rem;"><i class="bi bi-exclamation-circle"></i> Ragu-ragu</span></label></div>';
                
                no++;
            }
            
            return html;
        }
        
        function loadPage(page) {
            if (!SOAL_DATA || SOAL_DATA.length === 0) {
                document.getElementById('soalContainer').innerHTML = '<div class="alert alert-warning">Tidak ada soal</div>';
                return;
            }
            
            const totalPages = Math.ceil(SOAL_DATA.length / SOAL_PER_HALAMAN);
            if (page < 1 || page > totalPages) {
                console.error('Invalid page:', page);
                return;
            }
            
            const start = (page - 1) * SOAL_PER_HALAMAN;
            const end = Math.min(start + SOAL_PER_HALAMAN, SOAL_DATA.length);
            const soalPage = SOAL_DATA.slice(start, end);
            
            const html = renderSoal(soalPage);
            document.getElementById('soalContainer').innerHTML = html;
            document.getElementById('currentPage').textContent = page;
            
            document.getElementById('prevBtn').disabled = (page === 1);
            document.getElementById('nextBtn').disabled = (page === Math.ceil(SOAL_DATA.length / SOAL_PER_HALAMAN));
            
            updateSoalNumbers();
            updateProgress();
            
            // Hide loading indicator after soal loaded
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
            
            // Scroll ke atas halaman setiap pindah soal
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function updateSoalNumbers() {
            const container = document.getElementById('soalNumbersContainer');
            if (!container) return;
            
            let html = '';
            const total = SOAL_DATA.length;
            
            for (let i = 1; i <= total; i++) {
                const soalId = SOAL_DATA[i-1].id;
                const isAnswered = answers[soalId] ? true : false;
                const isCurrent = i === currentPage;
                
                let btnHtml = '';
                if (isCurrent) {
                    btnHtml = `<button type="button" class="btn btn-sm btn-primary fw-bold" onclick="goToPage(${i})" title="Soal ${i} (aktif)">${i}</button>`;
                } else if (isAnswered) {
                    btnHtml = `<button type="button" class="btn btn-sm btn-success" onclick="goToPage(${i})" title="Soal ${i} (dijawab)"><i class="bi bi-check"></i>${i}</button>`;
                } else {
                    btnHtml = `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="goToPage(${i})" title="Soal ${i} (belum dijawab)">${i}</button>`;
                }
                
                html += btnHtml;
            }
            
            container.innerHTML = html;
            
            // Update mobile progress
            const answeredCount = Object.keys(answers).length;
            const progressMobile = document.getElementById('progressMobile');
            if (progressMobile) {
                progressMobile.textContent = `${answeredCount}/${total} dijawab`;
            }
        }
        
        function goToPage(page) {
            currentPage = page;
            loadPage(currentPage);
        }
        
        function nextPage() {
            const totalPages = Math.ceil(SOAL_DATA.length / SOAL_PER_HALAMAN);
            if (currentPage < totalPages) {
                currentPage++;
                loadPage(currentPage);
                if (typeof resetPerSoalTimer === 'function') resetPerSoalTimer();
            }
        }
        
        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                loadPage(currentPage);
            }
        }
        
        async function startExam() {
            try {
                const tokenRes = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'generate_token'})
                });
                const tokenData = await tokenRes.json();
                
                if (tokenData.success && tokenData.csrf_token) {
                    csrfToken = tokenData.csrf_token;
                }
                <?php if (!empty($ujian['enable_browser_lock']) && $ujian['enable_browser_lock'] === 'ya'): ?>
                initBrowserLock();
                <?php endif; ?>
                <?php if (!empty($ujian['enable_device_check']) && $ujian['enable_device_check'] === 'ya'): ?>
                checkDeviceFingerprint();
                <?php endif; ?>
                const savedNis = localStorage.getItem('exam_nis');
                if (savedNis) {
                    checkCompletion(savedNis);
                }
            } catch (e) {
                console.error('Failed to initialize:', e);
            }
        }
        
        async function verifyExamCode() {
            const kode = document.getElementById('kodeUjianInput').value.trim();
            if (!kode) {
                showToast('Masukkan kode ujian!', 'warning');
                return;
            }
            
            try {
                console.log('Verifying code:', kode);
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'check_exam_code',
                        id_ujian: ID_UJIAN,
                        kode_ujian: kode
                    })
                });
                
                if (!res.ok) {
                    throw new Error('Network error: ' + res.status);
                }
                
                const data = await res.json();
                console.log('Verify response:', data);
                
                if (data.valid === true) {
                    // Show exam rules warning first before showing exam content
                    document.getElementById('examCodeForm').style.display = 'none';
                    
                    // Show rules modal with callback to display exam content
                    showExamRulesWarning(function() {
                        document.getElementById('examContent').style.display = 'block';
                        <?php if (isset($_SESSION['siswa_id'])): ?>
                        // Logged-in student - skip identity, auto-start exam
                        setTimeout(startWithIdentity, 100);
                        <?php else: ?>
                        document.getElementById('identitySection').style.display = 'block';
                        document.getElementById('questionSection').style.display = 'none';
                        <?php endif; ?>
                    });
                } else {
                    showToast(data.message || 'Kode ujian salah!', 'error');

                }
            } catch (e) {
                console.error('Verify error:', e);
                showToast('Gagal memverifikasi kode. Silakan coba lagi.', 'error');
            }
        }
        
        function generateDeviceFingerprint() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Fingerprint', 2, 2);
            const canvasHash = canvas.toDataURL();
            
            const fingerprint = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                canvasHash.substring(0, 20)
            ].join('|');
            
            let hash = 0;
            for (let i = 0; i < fingerprint.length; i++) {
                const char = fingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return 'fp_' + Math.abs(hash).toString(16);
        }
        
        function checkDeviceFingerprint() {
            const fp = generateDeviceFingerprint();
            const savedFp = localStorage.getItem('exam_fp');
            
            if (savedFp && savedFp !== fp) {
                showToast('Perangkat berbeda terdeteksi!', 'warning', 'Perubahan device akan dicatat sebagai pelanggaran.');
            }
            localStorage.setItem('exam_fp', fp);
        }
        
        let isSubmittingExam = false; // Flag to track intentional submission
        let examFinished = false; // Flag to indicate exam is completed
        
        // === Toast notification (stackable, auto-dismiss) ===
        function showToast(message, type, subtext) {
            type = type || 'info';
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
            const icon = icons[type] || icons.info;
            
            const item = document.createElement('div');
            item.className = 'toast-item toast-' + type;
            item.innerHTML = '<span class="toast-icon"><i class="bi ' + icon + '"></i></span>' +
                '<span class="toast-text"><strong>' + message + '</strong>' +
                (subtext ? '<small>' + subtext + '</small>' : '') + '</span>';
            container.appendChild(item);
            
            // Trigger animation
            requestAnimationFrame(() => { item.classList.add('show'); });
            
            setTimeout(() => {
                item.classList.remove('show');
                item.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                item.style.opacity = '0';
                setTimeout(() => item.remove(), 300);
            }, 4000);
        }
        
        function initBrowserLock() {
            // Skip if exam already finished
            if (examFinished) return;
            
            let violationCount = 0;
            const maxViolations = <?= (int)($ujian['max_violations'] ?? 10) ?>;
            let idleTimer = null;
            let lastActivity = Date.now();
            const idleLimit = 300000; // 5 menit (reduksi false positive)
            
            // === Away State: menggabungkan sleep/blur/focus hilang jadi 1 pelanggaran ===
            let awayState = { isAway: false, timer: null, lastViolationTime: 0 };
            const AWAY_GRACE_PERIOD = 3000;  // grace 3 detik (mencegah false positive sleep)
            const AWAY_COOLDOWN = 30000;     // min 30 detik antar pelanggaran away
            
            function handleAwayDetected() {
                if (examFinished || isSubmittingExam) return;
                if (awayState.isAway) return; // sudah dalam grace period
                
                const now = Date.now();
                if (now - awayState.lastViolationTime < AWAY_COOLDOWN) return;
                
                awayState.isAway = true;
                awayState.timer = setTimeout(() => {
                    if (examFinished || isSubmittingExam) { awayState.isAway = false; return; }
                    
                    awayState.lastViolationTime = Date.now();
                    violationCount++;
                    logViolation('tab_switch', 'Siswa meninggalkan tab/layar ujian (sleep/overlay)');
                    
                    if (violationCount >= maxViolations) {
                        showToast('Batas pelanggaran tercapai! Jawaban akan disubmit.', 'error', 'Anda terlalu sering meninggalkan layar ujian.');
                        setTimeout(submitFinal, 1500);
                    } else {
                        const remaining = maxViolations - violationCount;
                        showToast('Meninggalkan layar ujian terdeteksi!', 'warning', 'Pelanggaran ' + violationCount + '/' + maxViolations + ' — Sisa ' + remaining + 'x');
                    }
                    awayState.isAway = false;
                }, AWAY_GRACE_PERIOD);
            }
            
            function handleAwayReturned() {
                if (awayState.timer) { clearTimeout(awayState.timer); awayState.timer = null; }
                awayState.isAway = false;
            }
            
            // Fullscreen exit sudah ditangani oleh initFullscreenExitDetection()
            // (tidak perlu duplikasi handler di sini)
            
            function checkIdle() {
                // Skip if exam finished
                if (examFinished) return;
                
                const now = Date.now();
                if (now - lastActivity > idleLimit) {
                    violationCount++;
                    logViolation('idle_too_long', 'Siswa tidak aktif terlalu lama');
                    showToast('Tidak aktif terlalu lama!', 'warning', 'Pelanggaran ' + violationCount + '/' + maxViolations + ' — Jawaban akan disubmit jika terus tidak aktif.');
                    if (violationCount >= maxViolations) {
                        submitFinal();
                        return;
                    }
                }
                lastActivity = now;
            }
            
            let idleInterval = setInterval(checkIdle, 10000);
            
            document.addEventListener('visibilitychange', function() {
                if (examFinished) return;
                
                if (document.hidden) {
                    // HP sleep / layar mati: pause idle check agar tidak kena violation saat tidur
                    if (idleInterval) {
                        clearInterval(idleInterval);
                        idleInterval = null;
                    }
                } else {
                    // Kembali dari sleep: reset timer dan restart idle check
                    lastActivity = Date.now();
                    handleAwayReturned();
                    if (!idleInterval) {
                        idleInterval = setInterval(checkIdle, 10000);
                    }
                }
            });
            
            window.addEventListener('focus', function() {
                if (examFinished) return;
                lastActivity = Date.now();
            });
            
            document.addEventListener('touchstart', function() {
                if (examFinished) return;
                lastActivity = Date.now();
            }, {passive: true});
            
            document.addEventListener('click', function() {
                if (examFinished) return;
                lastActivity = Date.now();
            });
            
            document.addEventListener('keydown', function() {
                if (examFinished) return;
                lastActivity = Date.now();
            });
            
            // === Right-click detection with grace period & notification ===
            let lastRightClickTime = 0;
            const RIGHT_CLICK_COOLDOWN = 30000; // 30 detik antar pelanggaran

            document.addEventListener('contextmenu', function(e) {
                if (examFinished) return;
                e.preventDefault();

                const now = Date.now();
                if (now - lastRightClickTime < RIGHT_CLICK_COOLDOWN) return;
                lastRightClickTime = now;

                violationCount++;
                logViolation('right_click', 'Siswa mencoba klik kanan');

                showToast('⚠️ Klik kanan terdeteksi! (-10 poin). Pelanggaran: ' + violationCount + '/' + maxViolations, 'warning');

                if (violationCount >= maxViolations) {
                    showToast('❌ Terlalu banyak pelanggaran! Jawaban akan disubmit!', 'danger');
                    setTimeout(submitFinal, 2000);
                }

                return false;
            });
            
            // Copy diizinkan (hanya paste yang diblokir)
            
            document.addEventListener('paste', function(e) {
                if (examFinished) return;
                e.preventDefault();
                logViolation('paste', 'Siswa mencoba paste');
                return false;
            });
            
            // Sleep / layar mati / kehilangan fokus bukan pelanggran — tidak perlu proses violation
            
        }
        
        // === IntersectionObserver: Deteksi jika area soal tertutup overlay ===
        let overlayObserver = null;
        
        function initOverlayObserver() {
            const target = document.getElementById('soalContainer');
            if (!target) return;
            
            if (overlayObserver) overlayObserver.disconnect();
            
            let lastOverlayTime = 0;
            const OVERLAY_COOLDOWN = 15000;
            
            overlayObserver = new IntersectionObserver((entries) => {
                if (examFinished || isSubmittingExam) return;
                
                entries.forEach(entry => {
                    // Jika soal tertutup lebih dari 80% (intersectionRatio < 0.2) - direlaksasi dari 40%
                    if (entry.intersectionRatio < 0.2) {
                        const now = Date.now();
                        if (now - lastOverlayTime < OVERLAY_COOLDOWN) return;
                        lastOverlayTime = now;
                        
                        violationCount++;
                        logViolation('soal_tertutup', 'Area soal tertutup overlay/ aplikasi lain (IntersectionObserver)');
                        
                        if (violationCount >= maxViolations) {
                            showToast('Area ujian tertutup berulang kali! Jawaban akan disubmit.', 'error');
                            setTimeout(submitFinal, 1500);
                        } else {
                            const remaining = maxViolations - violationCount;
                            showToast('Area soal tertutup aplikasi lain!', 'warning', 'Pelanggaran ' + violationCount + '/' + maxViolations + ' — Sisa ' + remaining + 'x');
                        }
                    }
                });
            }, { threshold: [0.2] });
            
            overlayObserver.observe(target);
        }
        
        // Tambah animasi fadeIn jika belum ada
        if (!document.getElementById('fadeInAnimStyle')) {
            const style = document.createElement('style');
            style.id = 'fadeInAnimStyle';
            style.textContent = '@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }';
            document.head.appendChild(style);
        }
        
        async function logViolation(jenis, detail) {
            try {
                await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'log_violation',
                        id_ujian: ID_UJIAN,
                        nis: document.querySelector('input[name="nis"]')?.value || localStorage.getItem('exam_nis') || '',
                        jenis: jenis,
                        detail: detail,
                        device_fingerprint: localStorage.getItem('exam_fp') || '',
                        ip_address: '',
                        csrf_token: csrfToken,
                        expected_token: csrfToken
                    })
                });
            } catch (e) {
                console.error('Failed to log violation:', e);
            }
        }
        
        async function verifyExamCodeAndShowForm() {
            const kodeInput = document.getElementById('kodeUjianInput');
            if (!kodeInput) return;
            
            const kode = kodeInput.value.trim();
            if (!kode) {
                showToast('Masukkan kode ujian!', 'warning');
                return;
            }
            
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'check_exam_code',
                        id_ujian: ID_UJIAN,
                        kode_ujian: kode,
                        csrf_token: csrfToken,
                        expected_token: csrfToken
                    })
                });
                const data = await res.json();
                
                if (data.valid) {
                    document.getElementById('examCodeForm').style.display = 'none';
                    document.getElementById('examContent').style.display = 'block';
                    document.getElementById('identitySection').style.display = 'block';
                    document.getElementById('questionSection').style.display = 'none';
                } else {
                    showToast(data.message || 'Kode ujian salah!', 'error');
                }
            } catch (e) {
                showToast('Gagal memverifikasi kode. Silakan coba lagi.', 'error');
            }
        }
        
        async function getNewToken() {
            const tokenRes = await fetch(API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'generate_token'})
            });
            const tokenData = await tokenRes.json();
            return tokenData.csrf_token || '';
        }
        
        async function checkCompletion(nis) {
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'check_completion',
                        id_ujian: ID_UJIAN,
                        nis: nis,
                        csrf_token: csrfToken,
                        expected_token: csrfToken
                    })
                });
                const data = await res.json();
                
                if (data.completed) {
                    showAlreadyCompleted(data.result);
                } else if (data.has_saved && data.saved_data) {
                    loadSavedAnswers(data.saved_data);
                }
            } catch (e) {
                console.error('Check completion failed:', e);
            }
        }
        
        function showAlreadyCompleted(result) {
            // Check if student can retake (has saved answers but can retake)
            if (result.can_retake) {
                document.getElementById('formUjian').innerHTML = `
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-arrow-repeat" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Anda Dapat Mengerjakan Ulang</h3>
                        <p class="mb-2">Jawaban tersimpan ditemukan. Anda dapat mengerjakan ulang ujian ini.</p>
                        <button class="btn btn-primary mt-3" onclick="startWithIdentity()">
                            <i class="bi bi-play-fill me-2"></i>Mulai Ujian Ulang
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary mt-3 ms-2">Kembali ke Halaman Utama</a>
                    </div>
                `;
            } else {
                document.getElementById('formUjian').innerHTML = `
                    <div class="alert alert-warning text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Anda sudah mengerjakan ujian ini</h3>
                        <p class="mb-2">Skor Anda: <strong>${result.skor}</strong></p>
                        <p class="text-muted">Tanggal: ${new Date(result.tanggal).toLocaleString('id-ID')}</p>
                        <a href="index.php" class="btn btn-primary mt-3">Kembali ke Halaman Utama</a>
                    </div>
                `;
            }
            document.getElementById('progressIndicator').style.display = 'none';
        }
        
        function loadSavedAnswers(savedData) {
            if (savedData.nama) {
                document.querySelector('input[name="nama"]').value = savedData.nama;
            }
            if (savedData.kelas) {
                document.querySelector('input[name="kelas"]').value = savedData.kelas;
            }
            if (savedData.answers) {
                answers = savedData.answers;
                Object.entries(answers).forEach(([soalId, jawaban]) => {
                    const radio = document.querySelector(`input[name="jawaban_${soalId}"][value="${jawaban}"]`);
                    if (radio) {
                        radio.checked = true;
                    }
                });
                updateProgress();
                document.getElementById('autoSaveStatus').innerHTML = 
                    '<i class="bi bi-cloud-check-fill text-info"></i> Jawaban dimuat dari penyimpanan';
            }
            identitySaved = true;
        }
        
        function saveIdentity() {
            const nis = document.querySelector('input[name="nis"]').value.trim();
            const nama = document.querySelector('input[name="nama"]').value.trim();
            const kelas = document.querySelector('input[name="kelas"]').value.trim();
            
            if (!nis || !nama || !kelas) {
                return false;
            }
            
            if (!identitySaved && csrfToken) {
                fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'auto_save',
                        id_ujian: ID_UJIAN,
                        nis: nis,
                        nama: nama,
                        kelas: kelas,
                        answers: {},
                        csrf_token: csrfToken,
                        expected_token: csrfToken
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        identitySaved = true;
                        localStorage.setItem('exam_nis', nis);
                        checkCompletion(nis);
                    }
                }).catch(console.error);
            }
            return true;
        }
        
        function autoSaveAnswer(soalId, answer) {
            const nis = document.querySelector('input[name="nis"]').value.trim();
            if (!nis || !identitySaved) return;
            if (!csrfToken) {
                saveToLocalStorage();
                return;
            }
            
            answers[soalId] = answer;
            
            // Show "saving..." indicator
            const statusEl = document.getElementById('autoSaveStatus');
            statusEl.className = 'saving';
            statusEl.innerHTML = '<i class="bi bi-arrow-repeat"></i> Menyimpan...';
            
            clearTimeout(window.autoSaveTimer);
            window.autoSaveTimer = setTimeout(() => {
                fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'auto_save',
                        id_ujian: ID_UJIAN,
                        nis: nis,
                        answers: answers,
                        ip_address: localStorage.getItem('exam_ip') || '',
                        device_fingerprint: localStorage.getItem('exam_fp') || '',
                        csrf_token: csrfToken,
                        expected_token: csrfToken
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        statusEl.className = 'saved';
                        statusEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> Tersimpan';
                        setTimeout(() => {
                            statusEl.className = '';
                            statusEl.innerHTML = '<i class="bi bi-cloud text-muted"></i> Auto-save';
                        }, 3000);
                    } else if (data.message.includes('sudah menyelesaikan')) {
                        showToast(data.message, 'error');
                        location.reload();
                    } else {
                        statusEl.className = 'error';
                        statusEl.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Gagal simpan';
                        showToast('Gagal menyimpan jawaban', 'error');
                        setTimeout(() => {
                            statusEl.className = '';
                            statusEl.innerHTML = '<i class="bi bi-cloud text-muted"></i> Auto-save';
                        }, 4000);
                    }
                }).catch(() => {
                    statusEl.className = 'error';
                    statusEl.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Gagal simpan';
                    setTimeout(() => {
                        statusEl.className = '';
                        statusEl.innerHTML = '<i class="bi bi-cloud text-muted"></i> Auto-save';
                    }, 4000);
                });
            }, 2000);
        }
        
        function submitFinal() {
            const kodeValidInput = document.getElementById('kodeValid');
            if (kodeValidInput && kodeValidInput.value !== '1') {
                // Auto verify sebelum submit
                verifyExamCodeForSubmit();
                return;
            }
            
            doSubmitFinal();
        }
        
        async function verifyExamCodeForSubmit() {
            const kodeInput = document.getElementById('kodeUjianInput');
            if (!kodeInput) {
                doSubmitFinal();
                return;
            }
            
            const kode = kodeInput.value.trim();
            if (!kode) {
                showToast('Masukkan kode ujian!', 'warning');
                return;
            }
            
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'check_exam_code',
                        id_ujian: ID_UJIAN,
                        kode_ujian: kode,
                        csrf_token: csrfToken,
                        expected_token: csrfToken
                    })
                });
                const data = await res.json();
                
                if (data.valid) {
                    document.getElementById('kodeValid').value = '1';
                    doSubmitFinal();
                } else {
                    showToast(data.message || 'Kode ujian salah!', 'error');
                }
            } catch (e) {
                console.error('Failed to verify code:', e);
                showToast('Gagal memverifikasi kode. Silakan coba lagi.', 'error');
            }
        }
        
        function doSubmitFinal() {
            // Lepas Wake Lock agar HP bisa sleep normal setelah ujian
            releaseWakeLock();
            
            const nis = document.querySelector('input[name="nis"]').value.trim();
            const nama = document.querySelector('input[name="nama"]').value.trim();
            const kelas = document.querySelector('input[name="kelas"]').value.trim();
            
            if (!nis || !nama || !kelas) {
                showToast('Lengkapi identitas terlebih dahulu!', 'warning');
                return;
            }
            
            const totalSoal = SOAL_DATA.length;
            const answeredCount = Object.keys(answers).length;
            
            if (answeredCount < totalSoal) {
                // Hanya warning client-side — tidak dicatat ke server agar tidak kena penalti
                console.warn('Submit ditunda: jawaban belum lengkap (' + answeredCount + '/' + totalSoal + ')');
                
                // Show custom modal instead of alert (prevents fullscreen exit)
                showIncompleteModal(answeredCount, totalSoal);
                return;
            }
            
            console.log('Submitting answers:', answers);
            console.log('Total answered:', answeredCount);
            
            // Set flags BEFORE anything else
            isSubmittingExam = true;
            examFinished = true; // Disable ALL violation detection
            
            const btn = document.querySelector('.btn-submit');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengirim...';
            btn.disabled = true;
            
            console.log('Submitting with csrfToken:', csrfToken);
            console.log('Answers:', answers);
            
            // Exit fullscreen before submitting
            exitFullscreen();
             
             fetch(API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'submit_final',
                    id_ujian: ID_UJIAN,
                    nis: nis,
                    nama: nama,
                    kelas: kelas,
                    answers: answers,
                    csrf_token: csrfToken,
                    expected_token: csrfToken
                })
            })
            .then(r => {
                console.log('Submit response status:', r.status);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    localStorage.removeItem('exam_nis');
                    localStorage.removeItem(STORAGE_KEY);
                    // Pass penalty info to success page
                    showSuccessPage(data.skor, nis, nama, kelas, data.skor_awal || data.skor, data.penalty || 0, data.violation_count || 0);
                } else {
                    showToast('Error: ' + data.message, 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal mengirim jawaban. Silakan coba lagi.', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Ragu-ragu state
        let raguRagu = {};
        
        function toggleRagu(soalId) {
            raguRagu[soalId] = !raguRagu[soalId];
            updateRaguDisplay(soalId);
        }
        
        function updateRaguDisplay(soalId) {
            const checkbox = document.getElementById('ragu_' + soalId);
            if (checkbox) {
                checkbox.checked = raguRagu[soalId] || false;
            }
        }
        
        function showSummary() {
            const total = SOAL_DATA.length;
            const answered = Object.keys(answers).length;
            const unanswered = total - answered;
            const raguCount = Object.values(raguRagu).filter(v => v === true).length;
            
            document.getElementById('summaryTotal').textContent = total;
            document.getElementById('summaryAnswered').textContent = answered;
            document.getElementById('summaryUnanswered').textContent = unanswered;
            document.getElementById('summaryRagu').textContent = raguCount;
            
            // Message
            const msgEl = document.getElementById('summaryMessage');
            if (unanswered > 0) {
                msgEl.innerHTML = '<div class="alert alert-warning">Anda masih memiliki <strong>' + unanswered + '</strong> soal yang belum dijawab. Jika di-submit sekarang, soal yang belum dijawab akan dianggap salah.</div>';
                msgEl.style.display = 'block';
            } else if (raguCount > 0) {
                msgEl.innerHTML = '<div class="alert alert-info">Anda memiliki <strong>' + raguCount + '</strong> soal ragu-ragu. Pastikan jawaban sudah benar sebelum submit.</div>';
                msgEl.style.display = 'block';
            } else {
                msgEl.innerHTML = '<div class="alert alert-success">Semua soal sudah dijawab. Siap untuk submit!</div>';
                msgEl.style.display = 'block';
            }
            
            // Unanswered list
            const unansList = document.getElementById('unansweredList');
            const unansButtons = document.getElementById('unansweredButtons');
            
            if (unanswered > 0) {
                unansList.style.display = 'block';
                let html = '';
                for (let i = 0; i < SOAL_DATA.length; i++) {
                    const soal = SOAL_DATA[i];
                    if (!answers[soal.id]) {
                        const page = Math.floor(i / SOAL_PER_HALAMAN) + 1;
                        html += '<button type="button" class="btn btn-outline-warning btn-sm" onclick="closeSummary(); goToPage(' + page + ');">Soal ' + (i+1) + '</button>';
                    }
                }
                unansButtons.innerHTML = html;
            } else {
                unansList.style.display = 'none';
            }
            
            // Ragu-ragu list
            const raguList = document.getElementById('raguList');
            const raguButtons = document.getElementById('raguButtons');
            
            if (raguCount > 0) {
                raguList.style.display = 'block';
                let html = '';
                for (let i = 0; i < SOAL_DATA.length; i++) {
                    const soal = SOAL_DATA[i];
                    if (raguRagu[soal.id]) {
                        const page = Math.floor(i / SOAL_PER_HALAMAN) + 1;
                        html += '<button type="button" class="btn btn-outline-warning btn-sm" onclick="closeSummary(); goToPage(' + page + ');">Soal ' + (i+1) + '</button>';
                    }
                }
                raguButtons.innerHTML = html;
            } else {
                raguList.style.display = 'none';
            }
            
            document.getElementById('summaryModal').style.display = 'flex';
        }
        
        function closeSummary() {
            document.getElementById('summaryModal').style.display = 'none';
        }
        
        function showRaguList() {
            showSummary();
            setTimeout(() => {
                const raguList = document.getElementById('raguList');
                if (raguList && raguList.style.display !== 'none') {
                    raguList.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }
        
        function confirmSubmit() {
            closeSummary();
            const total = SOAL_DATA.length;
            const answered = Object.keys(answers).length;
            document.getElementById('confirmDetail').textContent = answered + '/' + total + ' soal terjawab';
            document.getElementById('confirmModal').style.display = 'flex';
        }
        
        function closeConfirm() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        // Modify submitFinal to show summary first
        const originalSubmitFinal = submitFinal;
        submitFinal = function() {
            const kodeValidInput = document.getElementById('kodeValid');
            if (kodeValidInput && kodeValidInput.value !== '1') {
                verifyExamCodeForSubmit();
                return;
            }
            
            showSummary();
        };
        
        function showSuccessPage(skor, nis, nama, kelas, skorAwal = null, penalty = 0, violationCount = 0) {
            localStorage.setItem('exam_nis', nis);
            localStorage.setItem('exam_nama', nama);
            localStorage.setItem('exam_kelas', kelas);
            
            // Build penalty info HTML if applicable
            let penaltyHtml = '';
            if (penalty > 0) {
                penaltyHtml = `
                    <div class="alert alert-warning mt-3" style="border-radius:12px;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Info Pengurangan Nilai:</strong><br>
                        Skor awal: ${skorAwal} | Dikurangi: ${penalty} poin (${violationCount} pelanggaran)<br>
                        <small class="text-muted">Setiap pelanggaran mengurangi 10 poin dari nilai akhir</small>
                    </div>
                `;
            }
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Ujian Selesai</title>
                    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
                    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
                    <link href="vendor/fonts/poppins.css" rel="stylesheet">
                    <style>
                        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
                        body { 
                            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                            min-height: 100vh; 
                            display: flex; 
                            align-items: center; 
                            justify-content: center;
                        }
                        .card { 
                            border: none; 
                            border-radius: 24px; 
                            box-shadow: 0 25px 80px rgba(0,0,0,0.25);
                            animation: slideUp 0.6s ease-out;
                        }
                        @keyframes slideUp {
                            from { opacity: 0; transform: translateY(30px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                        .success-icon {
                            width: 120px;
                            height: 120px;
                            border-radius: 50%;
                            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto;
                            animation: scaleIn 0.5s ease-out 0.3s both;
                            box-shadow: 0 10px 40px rgba(17, 153, 142, 0.4);
                        }
                        @keyframes scaleIn {
                            from { transform: scale(0); }
                            to { transform: scale(1); }
                        }
                        .success-icon i {
                            font-size: 4rem;
                            color: white;
                        }
                        .skor-box { 
                            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); 
                            -webkit-background-clip: text; 
                            -webkit-text-fill-color: transparent; 
                            font-size: 5rem; 
                            font-weight: 700; 
                        }
                        .info-card {
                            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                            border-radius: 16px;
                            padding: 20px;
                        }
                        .btn-home {
                            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                            border: none;
                            padding: 15px 40px;
                            border-radius: 30px;
                            font-weight: 600;
                            color: white;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card p-5 text-center">
                                    <div class="success-icon mb-4">
                                        <i class="bi bi-check-lg"></i>
                                    </div>
                                    <h2 class="fw-bold mb-2">Selamat!</h2>
                                    <p class="text-muted mb-4">Jawaban Anda telah berhasil disubmit</p>
                                    <div class="my-4">
                                        <p class="text-muted mb-2 fw-medium">Total Skor Anda</p>
                                        <div class="skor-box">${skor}</div>
                                    </div>
                                    <div class="info-card mb-4">
                                        <div class="row">
                                            <div class="col-12">
                                                <p class="mb-2"><strong class="fs-5">${nama}</strong></p>
                                            </div>
                                            <div class="col-6 text-start">
                                                <p class="mb-0 text-muted small">NIS</p>
                                                <p class="mb-0 fw-semibold">${nis}</p>
                                            </div>
                                            <div class="col-6 text-end">
                                                <p class="mb-0 text-muted small">Kelas</p>
                                                <p class="mb-0 fw-semibold">${kelas}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="index.php" class="btn btn-home">
                                        <i class="bi bi-house-door me-2"></i>Kembali ke Halaman Utama
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `;
        }
        
        // Progress indicator
        const radioButtons = document.querySelectorAll('input[type="radio"]');
        const answeredCount = document.getElementById('answeredCount');
        function updateProgress() {
            const radioButtons = document.querySelectorAll('input[type="radio"]');
            const answered = new Set();
            radioButtons.forEach(radio => {
                if (radio.checked) {
                    answered.add(radio.name);
                    const soalId = radio.name.replace('jawaban_', '');
                    answers[soalId] = radio.value;
                    console.log('Saved answer:', soalId, '=', radio.value);
                    autoSaveAnswer(soalId, radio.value);
                }
            });
            
            const total = SOAL_DATA.length;
            const count = answered.size;
            const percent = Math.round((count / total) * 100);
            
            // Show "X/Total" format instead of percentage
            const progressText = count + '/' + total;
            document.getElementById('answeredCount').textContent = progressText;
            document.getElementById('progressPercent').textContent = progressText;
            
            // Update mobile progress
            const progressMobile = document.getElementById('progressMobile');
            if (progressMobile) {
                progressMobile.textContent = '(' + count + '/' + total + ')';
            }
            
            const circle = document.querySelector('.progress-circle');
            if (percent === 100) {
                circle.style.background = 'linear-gradient(135deg, #10b981 0%, #34d399 100%)';
            } else if (percent >= 50) {
                circle.style.background = 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)';
            } else {
                circle.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
            
            // Update Ragu-ragu navigation button
            const raguCount = Object.values(raguRagu).filter(v => v === true).length;
            const raguNavContainer = document.getElementById('raguNavContainer');
            const raguNavCount = document.getElementById('raguNavCount');
            if (raguCount > 0) {
                raguNavContainer.style.display = 'block';
                raguNavCount.textContent = raguCount;
            } else {
                raguNavContainer.style.display = 'none';
            }
            
            // Auto-save ke localStorage
            clearTimeout(window.localSaveTimer);
            window.localSaveTimer = setTimeout(() => {
                saveToLocalStorage();
                const statusEl = document.getElementById('autoSaveStatus');
                if (statusEl) {
                    statusEl.className = 'saved';
                    statusEl.innerHTML = '<i class="bi bi-device-hdd-fill"></i> Tersimpan (lokal)';
                }
                setTimeout(() => {
                    if (document.getElementById('autoSaveStatus')) {
                        const se = document.getElementById('autoSaveStatus');
                        se.className = '';
                        se.innerHTML = '<i class="bi bi-cloud text-muted"></i> Auto-save';
                    }
                }, 2000);
            }, 1000);
        }
        
        // Save identity on input change
        ['nis', 'nama', 'kelas'].forEach(field => {
            const input = document.querySelector(`input[name="${field}"]`);
            if (input) {
                input.addEventListener('change', saveIdentity);
                input.addEventListener('blur', saveIdentity);
            }
        });
        
        // Hide progress indicator on mobile
        if (window.innerWidth < 768) {
            document.getElementById('progressIndicator').style.display = 'none';
        }
        
        // Timer functionality
        <?php if (isset($ujian['waktu_tersedia']) && $ujian['waktu_tersedia'] > 0): ?>
        let waktuTersedia = <?= (int)$ujian['waktu_tersedia'] ?> * 60;
        const timerDisplay = document.getElementById('timerDisplay');
        const timerBadge = document.getElementById('timerBadge');
        
        // Initial display in HH:MM:SS format
        if (timerDisplay) {
            const jam = Math.floor(waktuTersedia / 3600);
            const menit = Math.floor((waktuTersedia % 3600) / 60);
            const detik = waktuTersedia % 60;
            timerDisplay.textContent = jam + ':' + (menit < 10 ? '0' : '') + menit + ':' + (detik < 10 ? '0' : '') + detik;
        }
        
        function updateTimer() {
            const timerDisplay = document.getElementById('timerDisplay');
            if (!timerDisplay) return; // Jangan jalankan jika elemen belum ada!
            
            // Calculate HH:MM:SS format
            const jam = Math.floor(waktuTersedia / 3600);
            const menit = Math.floor((waktuTersedia % 3600) / 60);
            const detik = waktuTersedia % 60;
            
            // Format: HH:MM:SS (always show hours)
            const timeString = jam + ':' + (menit < 10 ? '0' : '') + menit + ':' + (detik < 10 ? '0' : '') + detik;
            timerDisplay.textContent = timeString;
            
            // Reset class
            timerDisplay.classList.remove('timer-danger', 'timer-warning');
            timerBadge.classList.remove('badge', 'bg-danger', 'bg-warning', 'fs-6', 'px-3', 'py-2');
            timerBadge.className = 'badge bg-primary fs-6 px-3 py-2';
            
            // Last 5 minutes: Red blinking
            if (waktuTersedia <= 300 && waktuTersedia > 60) {
                timerDisplay.classList.add('timer-danger');
                timerBadge.className = 'badge bg-danger fs-6 px-3 py-2';
                
                // Show warning once
                if (!timerWarningShown) {
                    timerWarningShown = true;
                    const remainingJam = Math.floor(waktuTersedia / 3600);
                    const remainingMenit = Math.floor((waktuTersedia % 3600) / 60);
                    const timeMsg = remainingJam > 0 ? 
                        remainingJam + ' jam ' + remainingMenit + ' menit' : 
                        remainingMenit + ' menit';
                    showToast('Waktu tersisa ' + timeMsg + '!', 'warning', 'Segera selesaikan jawaban Anda.');
                }
            }
            // Last 1 minute: Play tick sound
            else if (waktuTersedia <= 60 && waktuTersedia > 0) {
                timerDisplay.classList.add('timer-danger');
                timerBadge.className = 'badge bg-danger fs-6 px-3 py-2';
                
                // Play tick sound (once)
                if (!tickSoundPlayed) {
                    tickSoundPlayed = true;
                    const tickAudio = document.getElementById('tickSound');
                    if (tickAudio) {
                        tickAudio.loop = true;
                        tickAudio.play().catch(e => console.log('Audio play failed:', e));
                    }
                }
            }
            // Time's up
            else if (waktuTersedia <= 0) {
                // Stop tick sound
                const tickAudio = document.getElementById('tickSound');
                if (tickAudio) {
                    tickAudio.pause();
                    tickAudio.currentTime = 0;
                }
                
                const confirmSubmit = confirm('Waktu ujian telah habis! Jawaban akan otomatis dikirim. Klik OK untuk submit sekarang.');
                if (confirmSubmit) {
                    doSubmitFinal();
                }
                return;
            }
            // Between 5-10 minutes: Yellow warning
            else if (waktuTersedia <= 600) {
                timerBadge.className = 'badge bg-warning fs-6 px-3 py-2';
            }
            
            // Auto-save to server every 30 seconds
            if (identitySaved && waktuTersedia > 0) {
                const now = Date.now();
                if (now - lastAutoSaveTime >= 60000) { // 60 seconds
                    lastAutoSaveTime = now;
                    // Collect all answers and save
                    const allAnswers = {};
                    for (const soalId in answers) {
                        allAnswers[soalId] = answers[soalId];
                    }
                    
                    const nis = document.querySelector('input[name="nis"]')?.value.trim();
                    if (nis && Object.keys(allAnswers).length > 0) {
                        console.log('Auto-saving to server...');
                        fetch(API_URL, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'auto_save',
                                id_ujian: ID_UJIAN,
                                nis: nis,
                                answers: allAnswers,
                                ip_address: localStorage.getItem('exam_ip') || '',
                                device_fingerprint: localStorage.getItem('exam_fp') || '',
                                csrf_token: csrfToken,
                                expected_token: csrfToken
                            })
                        }).then(r => r.json()).then(data => {
                            if (data.success) {
                                console.log('Auto-saved to server successfully');
                                const statusEl = document.getElementById('autoSaveStatus');
                                if (statusEl) {
                                    statusEl.className = 'saved';
                                    statusEl.innerHTML = '<i class="bi bi-cloud-check-fill"></i> Tersimpan di server';
                                    setTimeout(() => {
                                        if (document.getElementById('autoSaveStatus')) {
                                            statusEl.className = '';
                                            statusEl.innerHTML = '<i class="bi bi-cloud text-muted"></i> Auto-save';
                                        }
                                    }, 3000);
                                }
                            } else {
                                const statusEl = document.getElementById('autoSaveStatus');
                                if (statusEl) {
                                    statusEl.className = 'error';
                                    statusEl.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Gagal sinkron';
                                }
                            }
                        }).catch(e => {
                            console.error('Auto-save failed:', e);
                            const statusEl = document.getElementById('autoSaveStatus');
                            if (statusEl) {
                                statusEl.className = 'error';
                                statusEl.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Gagal sinkron';
                                setTimeout(() => {
                                    statusEl.className = '';
                                    statusEl.innerHTML = '<i class="bi bi-cloud text-muted"></i> Auto-save';
                                }, 4000);
                            }
                        });
                    }
                }
            }
            
            waktuTersedia--;
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
        <?php endif; ?>

        <?php if (isset($ujian['durasi_per_soal']) && $ujian['durasi_per_soal'] > 0): ?>
        let perSoalTimeLeft = <?= (int)$ujian['durasi_per_soal'] ?>;
        const perSoalTimerDisplay = document.getElementById('perSoalTimerDisplay');
        const perSoalTimerBadge = document.getElementById('perSoalTimerBadge');

        function updatePerSoalTimer() {
            if (!perSoalTimerDisplay) return;
            perSoalTimeLeft--;
            if (perSoalTimeLeft < 0) perSoalTimeLeft = 0;
            const m = Math.floor(perSoalTimeLeft / 60);
            const s = perSoalTimeLeft % 60;
            perSoalTimerDisplay.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            if (perSoalTimeLeft <= 10) {
                perSoalTimerDisplay.style.color = '#dc3545';
                perSoalTimerDisplay.style.fontWeight = 'bold';
            }
            if (perSoalTimeLeft <= 0) {
                perSoalTimeLeft = 0;
                nextPage();
            }
        }

        function resetPerSoalTimer() {
            perSoalTimeLeft = <?= (int)$ujian['durasi_per_soal'] ?>;
            if (perSoalTimerDisplay) {
                perSoalTimerDisplay.style.color = '';
                perSoalTimerDisplay.style.fontWeight = '';
            }
        }

        setInterval(updatePerSoalTimer, 1000);
        <?php endif; ?>
        
        // ─── Swipe Gesture for Question Navigation ───
        let touchStartX = 0;
        let touchStartY = 0;
        const SWIPE_THRESHOLD = 60; // minimum px to trigger swipe
        
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchend', function(e) {
            if (isSubmittingExam || examFinished) return;
            const deltaX = e.changedTouches[0].clientX - touchStartX;
            const deltaY = e.changedTouches[0].clientY - touchStartY;
            
            // Only horizontal swipes, prevent triggering on scroll
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > SWIPE_THRESHOLD) {
                if (deltaX > 0) {
                    // Swipe right → previous
                    prevPage();
                } else {
                    // Swipe left → next
                    nextPage();
                }
            }
        }, { passive: true });
        
        init();
        
        // Custom modal for exam rules warning
        let examRulesCallback = null;
        
        function showExamRulesWarning(callback) {
            examRulesCallback = callback;
            
            const modal = document.createElement('div');
            modal.id = 'examRulesModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:99999;';
            
            modal.innerHTML = `
                <div style="background:white;padding:30px;border-radius:16px;max-width:520px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#dc2626,#ef4444);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-shield-exclamation" style="font-size:2.5rem;color:white;"></i>
                    </div>
                    <h4 style="font-weight:600;margin-bottom:15px;">PERHATIAN: Aturan Ujian</h4>
                    <p style="color:#6b7280;margin-bottom:20px;text-align:left;">
                        <strong>Dilarang Keras:</strong><br>
                        ❌ Membuka tab/jendela browser lain<br>
                        ❌ Berganti aplikasi (Alt+Tab / App Switcher)<br>
                        ❌ Aplikasi overlay (chat bubble, floating window)<br>
                        ❌ Copy-paste, klik kanan<br><br>
                        <strong>Sanksi:</strong> Setiap pelanggaran <strong>pemotongan 10 poin</strong>.
                        Jika batas terlampaui, jawaban <strong>otomatis disubmit</strong>.
                    </p>
                    <button id="examRulesBtn"
                            style="background:linear-gradient(135deg,#dc2626,#ef4444);color:white;border:none;padding:12px 30px;border-radius:30px;font-weight:600;cursor:pointer;">
                        <i class="bi bi-check-lg me-2"></i>Saya Mengerti, Akan Patuh
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
            
            const btn = document.getElementById('examRulesBtn');
            btn.addEventListener('click', function() {
                modal.remove();
                if (typeof examRulesCallback === 'function') {
                    examRulesCallback();
                }
            });
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    const btnEl = document.getElementById('examRulesBtn');
                    btnEl.style.animation = 'shake 0.5s';
                    setTimeout(() => { btnEl.style.animation = ''; }, 500);
                }
            });
            
            // Add shake animation if not exists
            if (!document.getElementById('shakeAnimation')) {
                const style = document.createElement('style');
                style.id = 'shakeAnimation';
                style.textContent = `
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                        20%, 40%, 60%, 80% { transform: translateX(5px); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        // Custom modal for incomplete answers (prevents fullscreen exit)
        function showIncompleteModal(answered, total) {
            // Remove existing modal if any
            const existingModal = document.getElementById('incompleteModal');
            if (existingModal) existingModal.remove();
            
            const modal = document.createElement('div');
            modal.id = 'incompleteModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999;';
            modal.innerHTML = `
                <div style="background:white;padding:30px;border-radius:16px;max-width:450px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#f59e0b,#fbbf24);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:2.5rem;color:white;"></i>
                    </div>
                    <h4 style="font-weight:600;margin-bottom:10px;">Soal Belum Lengkap!</h4>
                    <p style="color:#6b7280;margin-bottom:20px;">
                        Anda baru menjawab <strong>${answered}/${total}</strong> soal.<br>
                        Silakan lengkapi semua jawaban sebelum submit.
                    </p>
                    <p style="color:#dc2626;font-size:0.9rem;margin-bottom:20px;">
                        <i class="bi bi-exclamation-triangle"></i> Pelanggaran telah dicatat.
                    </p>
                    <button onclick="document.getElementById('incompleteModal').remove()" 
                            style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;padding:12px 30px;border-radius:30px;font-weight:600;cursor:pointer;">
                        <i class="bi bi-arrow-left me-2"></i>Kembali ke Soal
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close on background click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.remove();
            });
        }
    </script>
</body>
</html>
