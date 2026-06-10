<?php
session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

if (!isset($_SESSION['siswa_id'])) {
    header('Location: login.php');
    exit;
}

$siswa_nis   = $_SESSION['siswa_nis'];
$siswa_nama  = $_SESSION['siswa_nama'];
$siswa_kelas = $_SESSION['siswa_kelas'] ?? '';

// Cek fitur tabel
$has_scheduling  = $conn->query("SHOW COLUMNS FROM ujian LIKE 'tanggal_mulai'")->num_rows > 0;
$has_ujian_kelas = $conn->query("SHOW TABLES LIKE 'ujian_kelas'")->num_rows > 0;
$has_kelas_table = $conn->query("SHOW TABLES LIKE 'kelas'")->num_rows > 0;

$now = date('Y-m-d H:i:s');

// Ambil ujian aktif + jadwal + filter kelas
$ujian_query = "SELECT id, judul_ujian, deskripsi, waktu_tersedia";
if ($has_scheduling) {
    $ujian_query .= ", tanggal_mulai, tanggal_selesai";
}
$ujian_query .= " FROM ujian WHERE status = 'aktif'";
if ($has_scheduling) {
    $ujian_query .= " AND (tanggal_mulai IS NULL OR tanggal_mulai <= '$now') AND (tanggal_selesai IS NULL OR tanggal_selesai >= '$now')";
}
$ujian_query .= " ORDER BY tgl_dibuat DESC";

$ujian_ids = [];
$ujian_detail = [];
$res = $conn->query($ujian_query);
while ($r = $res->fetch_assoc()) {
    $ujian_ids[] = $r['id'];
    $ujian_detail[$r['id']] = $r;
}

// Filter by kelas if ujian_kelas exists
if ($has_ujian_kelas && !empty($ujian_ids) && $siswa_kelas) {
    $kelas_id = null;
    if ($has_kelas_table) {
        $st = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
        $st->bind_param("s", $siswa_kelas);
        $st->execute();
        $sr = $st->get_result();
        if ($sr_row = $sr->fetch_assoc()) $kelas_id = $sr_row['id'];
        $st->close();
    }
    if ($kelas_id) {
        $filtered = [];
        foreach ($ujian_detail as $uid => $ujian) {
            $ck = $conn->prepare("SELECT id FROM ujian_kelas WHERE id_ujian = ? AND id_kelas = ?");
            $ck->bind_param("ii", $uid, $kelas_id);
            $ck->execute();
            if ($ck->get_result()->num_rows > 0) $filtered[$uid] = $ujian;
            $ck->close();
        }
        $ujian_detail = $filtered;
    }
}

// Ambil info jumlah soal per ujian
$soal_counts = [];
if (!empty($ujian_ids)) {
    $ids_str = implode(',', $ujian_ids);
    $st = $conn->query("SELECT id_ujian, COUNT(*) as total FROM soal WHERE id_ujian IN ($ids_str) GROUP BY id_ujian");
    while ($r = $st->fetch_assoc()) $soal_counts[$r['id_ujian']] = $r['total'];
}

$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Peserta - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f0f2f5;
        }
        .card-kartu {
            max-width: 500px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        .card-header-kartu {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            color: white;
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            position: relative;
        }
        .card-header-kartu::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: #fff;
            border-radius: 50% 50% 0 0;
        }
        .card-header-kartu .school-logo {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }
        .card-header-kartu .school-logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .card-header-kartu .school-logo i {
            font-size: 2rem;
            color: white;
        }
        .card-header-kartu h5 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .card-header-kartu .subtitle {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .card-body-kartu {
            padding: 2rem 1.5rem 1.5rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .info-value {
            font-weight: 600;
            color: #1a1a2e;
        }
        .exam-list-kartu {
            margin-top: 1rem;
        }
        .exam-list-kartu .exam-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 0.5rem;
        }
        .exam-list-kartu .exam-item .exam-name {
            font-weight: 500;
            font-size: 0.9rem;
        }
        .exam-list-kartu .exam-item .exam-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .btn-cetak {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-cetak:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: white;
        }
        .footer-kartu {
            text-align: center;
            padding: 0 1.5rem 1.5rem;
            font-size: 0.8rem;
            color: #adb5bd;
        }

        /* Print styles */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .card-kartu {
                box-shadow: none !important;
                border: 2px solid #dee2e6;
                border-radius: 12px;
                margin: 0.5in auto;
                page-break-inside: avoid;
                max-width: 400px;
            }
            .card-header-kartu {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .exam-list-kartu .exam-item {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .btn-cetak {
                display: none !important;
            }
            .footer-kartu {
                display: none !important;
            }
            @page {
                margin: 0;
            }
        }

        @media (max-width: 576px) {
            .card-kartu {
                margin: 1rem;
                border-radius: 16px;
            }
            .card-header-kartu {
                padding: 1.5rem 1rem 1.25rem;
            }
            .card-body-kartu {
                padding: 1.5rem 1rem 1rem;
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($_GET['print'])): ?>
    <?php require 'partials/navbar.php'; ?>
    <?php endif; ?>

    <div class="<?= isset($_GET['print']) ? 'container-fluid py-4' : 'dashboard-container' ?>">
        <?php if (!isset($_GET['print'])): ?>
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
        <?php endif; ?>

        <div class="card-kartu" id="kartuPeserta">
            <!-- Header -->
            <div class="card-header-kartu">
                <div class="school-logo">
                    <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                        <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
                    <?php else: ?>
                        <i class="bi bi-mortarboard-fill"></i>
                    <?php endif; ?>
                </div>
                <h5><?= htmlspecialchars($sekolah['nama_sekolah']) ?></h5>
                <div class="subtitle">KARTU PESERTA UJIAN</div>
            </div>

            <!-- Body -->
            <div class="card-body-kartu">
                <div class="info-row">
                    <span class="info-label">NIS</span>
                    <span class="info-value"><?= htmlspecialchars($siswa_nis) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Lengkap</span>
                    <span class="info-value"><?= htmlspecialchars($siswa_nama) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($siswa_kelas) ?></span>
                </div>

                <?php if (!empty($ujian_detail)): ?>
                <div class="exam-list-kartu">
                    <p class="fw-bold mb-2" style="font-size:0.9rem;">Jadwal Ujian Tersedia</p>
                    <?php foreach ($ujian_detail as $ujian): ?>
                    <div class="exam-item">
                        <div>
                            <div class="exam-name"><?= htmlspecialchars($ujian['judul_ujian']) ?></div>
                            <div class="exam-meta">
                                <?php if (isset($soal_counts[$ujian['id']])): ?>
                                <?= $soal_counts[$ujian['id']] ?> Soal
                                <?php endif; ?>
                                <?php if (!empty($ujian['waktu_tersedia'])): ?>
                                &middot; <?= $ujian['waktu_tersedia'] ?> menit
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($has_scheduling && !empty($ujian['tanggal_mulai'])): ?>
                        <div class="text-end" style="font-size:0.8rem;">
                            <div><?= date('d M', strtotime($ujian['tanggal_mulai'])) ?></div>
                            <div class="text-muted"><?= date('H:i', strtotime($ujian['tanggal_mulai'])) ?> WIB</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($ujian_detail)): ?>
            <div class="footer-kartu">
                <i class="bi bi-info-circle me-1"></i>Hadir 15 menit sebelum ujian dimulai
            </div>
            <?php endif; ?>
        </div>

        <?php if (!isset($_GET['print'])): ?>
        <div class="text-center no-print" style="max-width:500px;margin:0 auto;">
            <button class="btn btn-cetak" onclick="cetakKartu()">
                <i class="bi bi-printer me-2"></i>Cetak Kartu
            </button>
            <p class="text-muted small mt-2">
                <i class="bi bi-info-circle me-1"></i>Kartu akan dicetak dalam format pas foto 4×6
            </p>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!isset($_GET['print'])): ?>
    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function cetakKartu() {
            window.print();
        }
    </script>
    <?php endif; ?>
</body>
</html>
