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

if (!empty($filter_kelas)) {
    $st = $conn->prepare("SELECT nis, nama_lengkap, kelas FROM siswa WHERE is_active = 1 AND kelas = ? ORDER BY nama_lengkap");
    $st->bind_param("s", $filter_kelas);
} else {
    $st = $conn->prepare("SELECT nis, nama_lengkap, kelas FROM siswa WHERE is_active = 1 ORDER BY kelas, nama_lengkap");
}
$st->execute();
$siswa_list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kartu Peserta - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; }

        .kartu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 4mm;
            padding: 5mm;
            justify-content: center;
        }
        .kartu-item {
            width: calc(50% - 2mm);
            border: 1px solid #adb5bd;
            border-radius: 4px;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
            box-shadow: 0 0.5mm 1mm rgba(0,0,0,0.06);
        }
        .kartu-header {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            color: white;
            text-align: center;
            padding: 2.5mm 2mm 2mm;
            position: relative;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .kartu-header::after {
            content: '';
            position: absolute;
            bottom: -3mm;
            left: 0; right: 0;
            height: 6mm;
            background: white;
            border-radius: 50% 50% 0 0;
        }
        .kartu-header .logo-wrap {
            width: 10mm; height: 10mm;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5mm;
            overflow: hidden;
        }
        .kartu-header .logo-wrap img { width: 9mm; height: 9mm; object-fit: contain; }
        .kartu-header .logo-wrap i { font-size: 5mm; color: white; }
        .kartu-header h6 { font-size: 3.2mm; font-weight: 700; margin-bottom: 0.2mm; }
        .kartu-header .label-kartu { font-size: 2.5mm; opacity: 0.85; letter-spacing: 1px; }

        .kartu-body { padding: 3mm 2.5mm 1.5mm; }
        .kartu-body .info-row {
            display: flex;
            align-items: baseline;
            padding: 0.8mm 0;
            border-bottom: 0.3px solid #e0e0e0;
            font-size: 2.8mm;
        }
        .kartu-body .info-row:last-child { border-bottom: none; }
        .kartu-body .info-label {
            color: #555;
            min-width: 30mm;
            font-size: 2.6mm;
        }
        .kartu-body .info-value {
            font-weight: 600;
            color: #1a1a2e;
            text-align: right;
            flex: 1;
        }
        .kartu-body .info-value.mono {
            font-family: 'Courier New', monospace;
            letter-spacing: 1.2px;
            font-weight: 700;
        }

        .kartu-footer {
            text-align: center;
            padding: 0 2mm 1.5mm;
            font-size: 2.2mm;
            color: #999;
        }

        @page { margin: 5mm; size: A4; }
    </style>
</head>
<body>
    <?php if (empty($siswa_list)): ?>
    <p style="text-align:center;padding:2rem;color:#999;">Tidak ada siswa untuk dicetak.</p>
    <?php else: ?>
    <div class="kartu-wrapper">
        <?php foreach ($siswa_list as $siswa): ?>
        <div class="kartu-item">
            <div class="kartu-header">
                <div class="logo-wrap">
                    <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                        <img src="../uploads/<?= $sekolah['logo'] ?>" alt="Logo">
                    <?php else: ?>
                        <i class="bi bi-mortarboard-fill"></i>
                    <?php endif; ?>
                </div>
                <h6><?= htmlspecialchars($sekolah['nama_sekolah']) ?></h6>
                <div class="label-kartu">KARTU PESERTA</div>
            </div>

            <div class="kartu-body">
                <div class="info-row">
                    <span class="info-label">Username</span>
                    <span class="info-value mono"><?= htmlspecialchars($siswa['nis']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Password</span>
                    <span class="info-value mono"><?= htmlspecialchars($siswa['nis']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nama Lengkap</span>
                    <span class="info-value"><?= htmlspecialchars($siswa['nama_lengkap']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($siswa['kelas']) ?></span>
                </div>
            </div>

            <div class="kartu-footer">Hadir 15 menit sebelum ujian dimulai</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <script>
        window.onload = function() { window.print(); };
        window.onafterprint = function() { window.close(); };
    </script>
</body>
</html>
