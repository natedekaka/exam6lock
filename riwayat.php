<?php
// riwayat.php - Halaman Riwayat Nilai Siswa (wajib login)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wajib login — amankan dari akses publik
if (!isset($_SESSION['siswa_id'])) {
    header('Location: siswa/login.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

// Selalu pakai NIS dari session, bukan dari GET parameter
// untuk mencegah siswa lain melihat nilai siswa lain
$nis = $_SESSION['siswa_nis'];

// Cek apakah kolom tampilkan_review sudah ada
$has_review_col = $db->columnExists('ujian', 'tampilkan_review');

if ($has_review_col) {
    $stmt = $conn->prepare("
        SELECT h.*, u.judul_ujian, u.tampilkan_review 
        FROM hasil_ujian h 
        JOIN ujian u ON h.id_ujian = u.id 
        WHERE h.nis = ? 
        ORDER BY h.waktu_submit DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT h.*, u.judul_ujian 
        FROM hasil_ujian h 
        JOIN ujian u ON h.id_ujian = u.id 
        WHERE h.nis = ? 
        ORDER BY h.waktu_submit DESC
    ");
}
$stmt->bind_param("s", $nis);
$stmt->execute();
$result = $stmt->get_result();
$riwayat_list = [];
while ($row = $result->fetch_assoc()) {
    $riwayat_list[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT DISTINCT nama, kelas FROM hasil_ujian WHERE nis = ? LIMIT 1");
$stmt->bind_param("s", $nis);
$stmt->execute();
$result = $stmt->get_result();
$siswa = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Nilai - <?= htmlspecialchars($siswa['nama'] ?? $nis) ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="vendor/fonts/poppins.css" rel="stylesheet">
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
        }
        
        .hero-section {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .history-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .skor-badge {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 12px;
        }
        
        .skor-sangat-baik {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }
        
        .skor-baik {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            color: white;
        }
        
        .skor-cukup {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: white;
        }
        
        .skor-kurang {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 5rem;
            color: #dee2e6;
        }
        
        .btn-review {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-review:hover {
            transform: scale(1.05);
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <a href="index.php" class="text-white text-decoration-none mb-3 d-inline-block">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="text-white fw-bold mb-1">
                        <i class="bi bi-clock-history me-2"></i>Riwayat Nilai
                    </h2>
                    <p class="text-white-50 mb-0">Riwayat ujian yang telah Anda selesaikan</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <?php if ($siswa): ?>
        <div class="profile-card">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($siswa['nama'] ?? 'S', 0, 1)) ?>
                    </div>
                </div>
                <div class="col">
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($siswa['nama']) ?></h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-person-badge me-2"></i>NIS: <?= htmlspecialchars($nis) ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-mortarboard me-2"></i>Kelas: <?= htmlspecialchars($siswa['kelas']) ?>
                    </p>
                </div>
                <div class="col-auto text-end">
                    <div class="text-muted small">Total Ujian</div>
                    <div class="fw-bold fs-4"><?= count($riwayat_list) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <h5 class="fw-bold mb-4">
            <i class="bi bi-list-ol me-2"></i>Daftar Ujian yang Diikuti
        </h5>

        <?php if (count($riwayat_list) > 0): ?>
            <?php foreach ($riwayat_list as $rw): ?>
            <?php 
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal WHERE id_ujian = ?");
            $stmt->bind_param("i", $rw['id_ujian']);
            $stmt->execute();
            $jml_soal = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            
            $nilai_persen = $jml_soal > 0 ? round(($rw['total_skor'] / ($jml_soal * 10)) * 100) : 0;
            
            if ($nilai_persen >= 80) {
                $skor_class = 'skor-sangat-baik';
            } elseif ($nilai_persen >= 60) {
                $skor_class = 'skor-baik';
            } elseif ($nilai_persen >= 40) {
                $skor_class = 'skor-cukup';
            } else {
                $skor_class = 'skor-kurang';
            }
            ?>
            <div class="history-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($rw['judul_ujian']) ?></h5>
                        <p class="text-muted mb-0 small">
                            <i class="bi bi-calendar3 me-1"></i><?= date('d M Y, H:i', strtotime($rw['waktu_submit'])) ?>
                        </p>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="skor-badge <?= $skor_class ?>">
                            <?= $rw['total_skor'] ?>
                        </div>
                        <small class="text-muted"><?= $nilai_persen ?>%</small>
                    </div>
                    <div class="col-md-3 text-end">
                        <?php if ($has_review_col && isset($rw['tampilkan_review']) && $rw['tampilkan_review'] === 'ya'): ?>
                        <a href="review.php?nis=<?= urlencode($nis) ?>&id_ujian=<?= $rw['id_ujian'] ?>" class="btn btn-review">
                            <i class="bi bi-card-checklist me-1"></i>Lihat Pembahasan
                        </a>
                        <?php else: ?>
                        <span class="text-muted small">Pembahasan tidak tersedia</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-inbox empty-icon"></i>
            <h4 class="mt-3 text-muted">Belum Ada Riwayat</h4>
            <p class="text-muted">Anda belum mengikuti ujian apapun.</p>
            <a href="index.php" class="btn btn-primary mt-2">
                <i class="bi bi-clipboard-check me-2"></i>Pilih Ujian
            </a>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-house-door me-2"></i>Kembali ke Halaman Utama
            </a>
        </div>
        
        <footer class="text-center text-muted py-4 mt-5">
            <small>&copy; <?= date('Y') ?> Sistem Ujian Online - by natedekaka</small>
        </footer>
    </div>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
