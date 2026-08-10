<?php
session_start();

require_once '../config/security_headers.php';

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

if (!isset($_SESSION['siswa_id'])) {
    header('Location: login.php');
    exit;
}

$siswa_nis  = $_SESSION['siswa_nis'];
$siswa_nama = $_SESSION['siswa_nama'];
$siswa_kelas = $_SESSION['siswa_kelas'] ?? '';

if (!isset($_GET['id_hasil']) || empty($_GET['id_hasil'])) {
    die("Parameter tidak valid");
}

$id_hasil = (int)$_GET['id_hasil'];

$stmt = $conn->prepare("SELECT * FROM hasil_ujian WHERE id = ? AND nis = ?");
$stmt->bind_param("is", $id_hasil, $siswa_nis);
$stmt->execute();
$hasil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hasil) {
    die("Data tidak ditemukan");
}

// Ambil judul ujian
$stmt_u = $conn->prepare("SELECT judul_ujian FROM ujian WHERE id = ?");
$stmt_u->bind_param("i", $hasil['id_ujian']);
$stmt_u->execute();
$ujian_data = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

$detail_jawaban = json_decode($hasil['detail_jawaban'], true);
if (!$detail_jawaban) {
    $detail_jawaban = [];
}

$total_benar = 0;
foreach ($detail_jawaban as $jw) {
    if (isset($jw['is_correct']) && $jw['is_correct']) {
        $total_benar++;
    }
}
$total_soal = count($detail_jawaban);
$persentase = $total_soal > 0 ? round(($total_benar / $total_soal) * 100, 1) : 0;

$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Jawaban - <?= htmlspecialchars($ujian_data['judul_ujian'] ?? 'Ujian') ?> - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        .review-header-bar {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            padding: 20px 0;
            margin-bottom: 24px;
        }
        .review-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            padding: 25px;
            border-left: 5px solid #e9ecef;
        }
        .review-card.benar {
            border-left-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }
        .review-card.salah {
            border-left-color: #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        .soal-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .badge-benar {
            background: #10b981;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .badge-salah {
            background: #ef4444;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .skor-summary {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            color: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            margin-bottom: 24px;
        }
        .skor-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
        .option-label {
            width: 30px;
            flex-shrink: 0;
        }
        .jawaban-box {
            background: rgba(255,255,255,0.7);
            padding: 12px 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php require 'partials/navbar.php'; ?>

    <main class="dashboard-container">
        <!-- Header -->
        <div class="d-flex align-items-center gap-3 mb-3">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <div>
                <h5 class="fw-bold mb-0">Detail Jawaban</h5>
                <small class="text-muted"><?= htmlspecialchars($ujian_data['judul_ujian'] ?? '') ?></small>
            </div>
        </div>

        <!-- Score Summary -->
        <div class="skor-summary">
            <div class="row">
                <div class="col-4">
                    <div class="skor-number"><?= (int)$hasil['total_skor'] ?></div>
                    <div class="opacity-75">Total Skor</div>
                </div>
                <div class="col-4">
                    <div class="skor-number"><?= $total_benar ?>/<?= $total_soal ?></div>
                    <div class="opacity-75">Benar</div>
                </div>
                <div class="col-4">
                    <div class="skor-number"><?= $persentase ?>%</div>
                    <div class="opacity-75">Nilai</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="mb-3">
            <h5 class="fw-bold mb-2"><i class="bi bi-card-checklist me-2"></i>Pembahasan Jawaban</h5>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary active" onclick="filterAnswers('all')">Semua</button>
                <button type="button" class="btn btn-outline-success" onclick="filterAnswers('benar')">Benar Saja</button>
                <button type="button" class="btn btn-outline-danger" onclick="filterAnswers('salah')">Salah Saja</button>
            </div>
        </div>

        <!-- Jawaban Cards -->
        <?php if (empty($detail_jawaban)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>Detail jawaban tidak tersedia untuk hasil ujian ini.
            </div>
        <?php else: ?>
            <?php $no = 1; foreach ($detail_jawaban as $jw): ?>
            <div class="review-card <?= isset($jw['is_correct']) && $jw['is_correct'] ? 'benar' : 'salah' ?>">
                <div class="d-flex align-items-start mb-3">
                    <span class="soal-number"><?= $no ?></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <p class="mb-2 fw-medium"><?= nl2br(htmlspecialchars($jw['pertanyaan'] ?? '')) ?></p>
                            <span class="<?= isset($jw['is_correct']) && $jw['is_correct'] ? 'badge-benar' : 'badge-salah' ?> text-nowrap">
                                <i class="bi bi-<?= isset($jw['is_correct']) && $jw['is_correct'] ? 'check-circle' : 'x-circle' ?> me-1"></i>
                                <?= isset($jw['is_correct']) && $jw['is_correct'] ? 'BENAR' : 'SALAH' ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-star me-1"></i>Poin: <?= $jw['poin_diperoleh'] ?? 0 ?>/<?= $jw['poin'] ?? 0 ?>
                        </small>
                    </div>
                </div>

                <div class="ms-0 ms-sm-5">
                    <?php
                    $options = [
                        'a' => $jw['opsi_a'] ?? '',
                        'b' => $jw['opsi_b'] ?? '',
                        'c' => $jw['opsi_c'] ?? '',
                        'd' => $jw['opsi_d'] ?? '',
                        'e' => $jw['opsi_e'] ?? ''
                    ];

                    foreach ($options as $key => $opt):
                        if (empty($opt)) continue;

                        $is_jawaban_siswa = (isset($jw['jawaban_siswa']) && $jw['jawaban_siswa'] === $key);
                        $is_kunci = (isset($jw['kunci_jawaban']) && $jw['kunci_jawaban'] === $key);
                    ?>
                    <div class="d-flex align-items-center mb-2 gap-2">
                        <span class="badge bg-secondary option-label"><?= strtoupper($key) ?></span>
                        <span class="flex-grow-1"><?= htmlspecialchars($opt) ?></span>
                        <?php if ($is_kunci): ?>
                            <span class="badge bg-success text-nowrap"><i class="bi bi-check"></i> Jawaban Benar</span>
                        <?php elseif ($is_jawaban_siswa): ?>
                            <span class="badge bg-danger text-nowrap"><i class="bi bi-x"></i> Jawaban Anda</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $no++; endforeach; ?>
        <?php endif; ?>
    </main>

    <footer class="footer-student">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($sekolah['nama_sekolah']) ?> — Sistem Ujian Online</p>
        </div>
    </footer>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function filterAnswers(type) {
            const cards = document.querySelectorAll('.review-card');
            cards.forEach(card => {
                if (type === 'all') {
                    card.style.display = '';
                } else if (type === 'benar') {
                    card.style.display = card.classList.contains('benar') ? '' : 'none';
                } else if (type === 'salah') {
                    card.style.display = card.classList.contains('salah') ? '' : 'none';
                }
            });
            document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
