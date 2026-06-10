<?php
// index.php - Halaman Depan (List Ujian)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$has_scheduling = $conn->query("SHOW COLUMNS FROM ujian LIKE 'tanggal_mulai'")->num_rows > 0;
$has_ujian_kelas = $conn->query("SHOW TABLES LIKE 'ujian_kelas'")->num_rows > 0;
$has_kelas_table = $conn->query("SHOW TABLES LIKE 'kelas'")->num_rows > 0;

$ujian_ids = [];
$ujian_array = [];

if (isset($redis)) {
    $cached = $redis->get('ujian:list_aktif');
    if ($cached !== null) {
        $ujian_array = $cached;
        $ujian_ids = array_keys($ujian_array);
    }
}

if (empty($ujian_ids)) {
    $ujian_list = $conn->query("SELECT * FROM ujian WHERE status = 'aktif' ORDER BY tgl_dibuat DESC");
    while ($row = $ujian_list->fetch_assoc()) {
        $ujian_ids[] = $row['id'];
        $ujian_array[$row['id']] = $row;
    }
    if (isset($redis)) {
        $redis->set('ujian:list_aktif', $ujian_array, 300);
    }
}

$ujian_kelas_map = [];
if ($has_ujian_kelas && !empty($ujian_ids)) {
    $ids_ph = implode(',', array_fill(0, count($ujian_ids), '?'));
    $stmt_uk = $conn->prepare("SELECT id_ujian, id_kelas FROM ujian_kelas WHERE id_ujian IN ($ids_ph)");
    $stmt_uk->bind_param(str_repeat('i', count($ujian_ids)), ...$ujian_ids);
    $stmt_uk->execute();
    $uk_res = $stmt_uk->get_result();
    while ($uk = $uk_res->fetch_assoc()) {
        $ujian_kelas_map[$uk['id_ujian']][] = $uk['id_kelas'];
    }
    $stmt_uk->close();
}

$is_siswa_logged_in = isset($_SESSION['siswa_id']);
$siswa_kelas = null;
if ($is_siswa_logged_in) {
    $siswa_kelas = $_SESSION['siswa_kelas'] ?? null;
}

$now = date('Y-m-d H:i:s');
$filtered_ujian = [];
foreach ($ujian_array as $id => $ujian) {
    if ($has_scheduling && !empty($ujian['tanggal_mulai']) && $now < $ujian['tanggal_mulai']) {
        continue;
    }
    if ($has_scheduling && !empty($ujian['tanggal_selesai']) && $now > $ujian['tanggal_selesai']) {
        continue;
    }
    if ($has_ujian_kelas && !empty($ujian_kelas_map[$id])) {
        if (!$is_siswa_logged_in) {
            continue;
        }
        if ($has_kelas_table && $siswa_kelas) {
            $stmt_sk = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
            $stmt_sk->bind_param("s", $siswa_kelas);
            $stmt_sk->execute();
            $sk_res = $stmt_sk->get_result();
            $sk_row = $sk_res->fetch_assoc();
            $kelas_id = $sk_row ? $sk_row['id'] : null;
            $stmt_sk->close();

            if ($kelas_id && !in_array($kelas_id, $ujian_kelas_map[$id])) {
                continue;
            }
        }
    }
    $filtered_ujian[$id] = $ujian;
}
$ujian_array = $filtered_ujian;

$soal_counts = [];
$waktu_counts = [];
if (!empty($ujian_ids)) {
    $ids_placeholder = implode(',', array_fill(0, count($ujian_ids), '?'));
    
    $stmt = $conn->prepare("SELECT id_ujian, COUNT(*) as total FROM soal WHERE id_ujian IN ($ids_placeholder) GROUP BY id_ujian");
    $stmt->bind_param(str_repeat('i', count($ujian_ids)), ...$ujian_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $soal_counts[$row['id_ujian']] = $row['total'];
    }
    $stmt->close();
    
    $result_cols = $conn->query("SHOW COLUMNS FROM ujian LIKE 'waktu_tersedia'");
    if ($result_cols && $result_cols->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id, waktu_tersedia FROM ujian WHERE id IN ($ids_placeholder)");
        $stmt->bind_param(str_repeat('i', count($ujian_ids)), ...$ujian_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $waktu_counts[$row['id']] = $row['waktu_tersedia'] ?? 0;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Ujian Online - <?= htmlspecialchars($sekolah['nama_sekolah']) ?>">
    <title>Sistem Ujian Online</title>
    
    <link href="vendor/fonts/poppins.css" rel="stylesheet">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif; }
    </style>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
    
    <style>
        * {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Poppins', sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .school-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .school-logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        
        .school-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 5px;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .hero-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
        }
        
        .ujian-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            background: white;
        }
        
        .ujian-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .ujian-card .card-header {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            color: white;
            border: none;
            padding: 20px;
        }
        
        .ujian-card .card-body {
            padding: 25px;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .btn-ujian {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-ujian:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 30px 0;
            margin-top: 60px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 5rem;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <div class="school-logo">
                        <?php if ($sekolah['logo'] && file_exists('uploads/' . $sekolah['logo'])): ?>
                            <img src="uploads/<?= $sekolah['logo'] ?>" alt="Logo" width="60" height="60">
                        <?php else: ?>
                            <i class="bi bi-mortarboard-fill" style="font-size: 2.5rem; color: <?= $sekolah['warna_primer'] ?>;"></i>
                        <?php endif; ?>
                    </div>
                    <p class="school-name mb-1"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></p>
                    <h1 class="hero-title">
                        <i class="bi bi-clipboard-check me-2"></i>Sistem Ujian Online
                    </h1>
                    <p class="hero-subtitle">Selamat datang! Silakan pilih ujian yang tersedia di bawah ini untuk memulai.</p>
                    <div class="mt-4">
                        <?php if (isset($_SESSION['siswa_id'])): ?>
                            <a href="siswa/dashboard.php" class="btn btn-light me-2">
                                <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($_SESSION['siswa_nama']) ?>
                            </a>
                            <a href="siswa/logout.php" class="btn btn-outline-light">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar
                            </a>
                        <?php else: ?>
                            <a href="siswa/login.php" class="btn btn-light">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Siswa
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($is_siswa_logged_in): ?>
    <?php
    $siswa_kelas = $_SESSION['siswa_kelas'] ?? '';
    $stmt_p = $conn->prepare("SELECT * FROM pengumuman WHERE tipe='umum' OR (tipe='kelas' AND (target_kelas IS NULL OR target_kelas='' OR target_kelas=?)) ORDER BY created_at DESC LIMIT 3");
    $stmt_p->bind_param("s", $siswa_kelas);
    $stmt_p->execute();
    $pengumuman_list = $stmt_p->get_result();
    $stmt_p->close();
    ?>
    <?php if ($pengumuman_list && $pengumuman_list->num_rows > 0): ?>
    <section class="py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2 text-primary"></i>Pengumuman</h5>
                <a href="siswa/pengumuman.php" class="small">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php while ($p = $pengumuman_list->fetch_assoc()): ?>
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= htmlspecialchars($p['judul']) ?></strong>
                            <p class="mb-0 small text-muted"><?= htmlspecialchars(mb_substr($p['isi'], 0, 200)) ?><?= mb_strlen($p['isi']) > 200 ? '...' : '' ?></p>
                        </div>
                        <small class="text-muted ms-3 text-nowrap"><?= htmlspecialchars($p['created_at']) ?></small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Ujian List -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="fw-bold">
                        <i class="bi bi-collection-fill me-2 text-primary"></i>Ujian Tersedia
                    </h4>
                    <p class="text-muted"><?= isset($_SESSION['siswa_id']) ? 'Klik pada kartu ujian untuk memulai' : 'Login terlebih dahulu untuk mengerjakan ujian' ?></p>
                </div>
            </div>
            
            <?php if (!empty($ujian_array)): ?>
            <div class="row g-4">
                <?php foreach ($ujian_array as $ujian): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="ujian-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold"><?= htmlspecialchars($ujian['judul_ujian']) ?></h5>
                                <span class="status-badge bg-white bg-opacity-25">
                                    <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($ujian['deskripsi']): ?>
                            <p class="text-muted mb-3"><?= htmlspecialchars($ujian['deskripsi']) ?></p>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center mb-3 text-muted small">
                                <div class="info-icon me-2">
                                    <i class="bi bi-calendar3"></i>
                                </div>
                                <span><?= date('d M Y', strtotime($ujian['tgl_dibuat'])) ?></span>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3 text-muted small">
                                <div class="info-icon me-2">
                                    <i class="bi bi-question-circle"></i>
                                </div>
                                <span><?= $soal_counts[$ujian['id']] ?? 0 ?> Soal</span>
                            </div>
                            
                            <?php 
                            $waktu = $waktu_counts[$ujian['id']] ?? 0;
                            ?>
                            <?php if ($waktu > 0): ?>
                            <div class="d-flex align-items-center mb-3 text-muted small">
                                <div class="info-icon me-2" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <span><?= $waktu ?> menit</span>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_scheduling && !empty($ujian['tanggal_mulai'])): ?>
                            <div class="d-flex align-items-center mb-3 text-muted small">
                                <div class="info-icon me-2" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <span>Mulai: <?= date('d M Y H:i', strtotime($ujian['tanggal_mulai'])) ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($has_scheduling && !empty($ujian['tanggal_selesai'])): ?>
                            <div class="d-flex align-items-center mb-3 text-muted small">
                                <div class="info-icon me-2" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                    <i class="bi bi-calendar-x"></i>
                                </div>
                                <span>Selesai: <?= date('d M Y H:i', strtotime($ujian['tanggal_selesai'])) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['siswa_id'])): ?>
                            <a href="ujian.php?id=<?= $ujian['id'] ?>" class="btn btn-ujian text-white w-100">
                                <i class="bi bi-pencil-square me-2"></i>Mulai Ujian
                            </a>
                            <?php else: ?>
                            <a href="siswa/login.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login untuk Mengerjakan
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox empty-icon"></i>
                <h4 class="mt-3 text-muted">Belum Ada Ujian Tersedia</h4>
                <p class="text-muted">Silakan hubungi guru atau administrator untuk informasi lebih lanjut.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-mortarboard-fill me-2"></i>Sistem Ujian Online</h5>
                    <p class="text-white-50 mb-0">Platform ujian online untuk memudahkan proses pembelajaran.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-white-50 mb-0">&copy; <?= date('Y') ?> Sistem Ujian Online - by MGMP Informatika 6 Cimahi</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
