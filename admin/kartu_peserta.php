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

$kelas_list = $conn->query("SELECT DISTINCT kelas FROM siswa WHERE is_active = 1 AND kelas IS NOT NULL AND kelas != '' ORDER BY kelas");

$siswa_list = [];
if (!empty($filter_kelas)) {
    $st = $conn->prepare("SELECT nis, nama_lengkap, kelas FROM siswa WHERE is_active = 1 AND kelas = ? ORDER BY nama_lengkap");
    $st->bind_param("s", $filter_kelas);
} else {
    $st = $conn->prepare("SELECT nis, nama_lengkap, kelas FROM siswa WHERE is_active = 1 ORDER BY kelas, nama_lengkap");
}
$st->execute();
$siswa_list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Peserta - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; }

        .kartu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            justify-content: center;
            padding: 1rem 0;
        }
        .kartu-item {
            width: 320px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .kartu-header {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            color: white;
            padding: 1.25rem 1rem 1rem;
            text-align: center;
            position: relative;
        }
        .kartu-header::after {
            content: '';
            position: absolute;
            bottom: -16px;
            left: 0; right: 0;
            height: 32px;
            background: #fff;
            border-radius: 50% 50% 0 0;
        }
        .kartu-header .logo-wrap {
            width: 56px; height: 56px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            overflow: hidden;
        }
        .kartu-header .logo-wrap img { width: 48px; height: 48px; object-fit: contain; }
        .kartu-header .logo-wrap i { font-size: 1.5rem; color: white; }
        .kartu-header h6 { font-weight: 700; font-size: 0.85rem; margin-bottom: 0.15rem; }
        .kartu-header .label-kartu { font-size: 0.7rem; opacity: 0.85; letter-spacing: 1px; }

        .kartu-body { padding: 1.25rem 1rem 1rem; }
        .kartu-body .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.82rem;
        }
        .kartu-body .info-row:last-child { border-bottom: none; }
        .kartu-body .info-label { color: #6c757d; }
        .kartu-body .info-value { font-weight: 600; color: #1a1a2e; }

        .kartu-footer {
            text-align: center;
            padding: 0 1rem 1rem;
            font-size: 0.7rem;
            color: #adb5bd;
        }

        @media print {
            body { background: white !important; margin: 0; }
            .sidebar, .overlay, .page-header, .main-content > .container-fluid > .card { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .container-fluid { padding: 0 !important; max-width: none !important; }
            .kartu-wrapper { display: block; padding: 0.3in 0 0; gap: 0; }
            .kartu-item {
                box-shadow: none !important;
                border: 1.5px solid #dee2e6;
                border-radius: 10px;
                margin: 0 auto 0.5in;
                width: 340px;
                page-break-after: always;
            }
            .kartu-item:last-child { page-break-after: auto; }
            .kartu-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 0.3in; }
        }
    </style>
</head>
<body>
    <?php require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header animate-fade-in">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-custom">
                    <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door-fill me-1"></i>Beranda</a></li>
                    <li class="breadcrumb-item active">Kartu Peserta</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h4 class="fw-bold mb-0"><i class="bi bi-card-text me-2"></i>Kartu Peserta</h4>
            </div>
        </div>

        <div class="container-fluid pb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Filter Kelas</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Semua Kelas --</option>
                                <?php while ($k = $kelas_list->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $filter_kelas === $k['kelas'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kelas']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">&nbsp;</label>
                            <div>
                                <span class="badge bg-primary fs-6"><?= count($siswa_list) ?> peserta</span>
                            </div>
                        </div>
                        <div class="col-md-5 text-end">
                            <?php if (!empty($siswa_list)): ?>
                            <button type="button" class="btn btn-primary no-print" onclick="window.print()">
                                <i class="bi bi-printer me-2"></i>Cetak Semua (<?= count($siswa_list) ?>)
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (empty($siswa_list)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>Tidak ada siswa aktif ditemukan.
            </div>
            <?php else: ?>
            <div class="text-center text-muted small mb-3 no-print">
                <i class="bi bi-printer me-1"></i>Klik <strong>Cetak Semua</strong> untuk mencetak <?= count($siswa_list) ?> kartu peserta sekaligus
            </div>

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
                            <span class="info-value"><?= htmlspecialchars($siswa['nis']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Password</span>
                            <span class="info-value" style="font-family: monospace; letter-spacing: 1px;"><?= htmlspecialchars($siswa['nis']) ?></span>
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

                    <div class="kartu-footer">
                        <i class="bi bi-info-circle me-1"></i>Hadir 15 menit sebelum ujian dimulai
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
