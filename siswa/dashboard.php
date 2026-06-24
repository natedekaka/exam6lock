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

$siswa_id   = $_SESSION['siswa_id'];
$siswa_nis  = $_SESSION['siswa_nis'];
$siswa_nama = $_SESSION['siswa_nama'];
$siswa_kelas = $_SESSION['siswa_kelas'] ?? '';

// ---- STAT QUERIES ----

// 1. Total ujian aktif yang tersedia (filter jadwal + kelas)
$has_scheduling  = $conn->query("SHOW COLUMNS FROM ujian LIKE 'tanggal_mulai'")->num_rows > 0;
$has_ujian_kelas = $conn->query("SHOW TABLES LIKE 'ujian_kelas'")->num_rows > 0;
$has_kelas_table = $conn->query("SHOW TABLES LIKE 'kelas'")->num_rows > 0;

$now = date('Y-m-d H:i:s');
$ujian_query = "SELECT id FROM ujian WHERE status = 'aktif'";
if ($has_scheduling) {
    $ujian_query .= " AND (tanggal_mulai IS NULL OR tanggal_mulai <= '$now') AND (tanggal_selesai IS NULL OR tanggal_selesai >= '$now')";
}
$ujian_ids = [];
$res = $conn->query($ujian_query);
while ($r = $res->fetch_assoc()) {
    $ujian_ids[] = $r['id'];
}

// Filter by kelas if ujian_kelas exists (same logic as index.php)
if ($has_ujian_kelas && !empty($ujian_ids)) {
    $ids_ph = implode(',', array_fill(0, count($ujian_ids), '?'));
    $stmt_uk = $conn->prepare("SELECT id_ujian, id_kelas FROM ujian_kelas WHERE id_ujian IN ($ids_ph)");
    $stmt_uk->bind_param(str_repeat('i', count($ujian_ids)), ...$ujian_ids);
    $stmt_uk->execute();
    $uk_res = $stmt_uk->get_result();
    $ujian_kelas_map = [];
    while ($uk = $uk_res->fetch_assoc()) {
        $ujian_kelas_map[$uk['id_ujian']][] = $uk['id_kelas'];
    }
    $stmt_uk->close();

    $kelas_id = null;
    if ($siswa_kelas && $has_kelas_table) {
        $st = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
        $st->bind_param("s", $siswa_kelas);
        $st->execute();
        $sr = $st->get_result();
        if ($sr_row = $sr->fetch_assoc()) $kelas_id = $sr_row['id'];
        $st->close();
    }

    $new_ids = [];
    foreach ($ujian_ids as $uid) {
        if (empty($ujian_kelas_map[$uid])) {
            // No class restriction → show to all
            $new_ids[] = $uid;
        } elseif ($kelas_id && in_array($kelas_id, $ujian_kelas_map[$uid])) {
            // Has restriction AND student's class is allowed → show
            $new_ids[] = $uid;
        }
    }
    $ujian_ids = $new_ids;
}

$total_ujian_tersedia = count($ujian_ids);

// 2. Ujian sudah dikerjakan
$stmt = $conn->prepare("SELECT COUNT(DISTINCT id_ujian) as total FROM hasil_ujian WHERE nis = ?");
$stmt->bind_param("s", $siswa_nis);
$stmt->execute();
$ujian_dikerjakan = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// 3. Rata-rata nilai
$stmt = $conn->prepare("SELECT AVG(total_skor) as avg_skor FROM hasil_ujian WHERE nis = ? AND total_skor IS NOT NULL");
$stmt->bind_param("s", $siswa_nis);
$stmt->execute();
$avg_row = $stmt->get_result()->fetch_assoc();
$rata_rata = $avg_row['avg_skor'] ? round($avg_row['avg_skor'], 1) : null;
$stmt->close();

// 4. Ujian yang belum dikerjakan (sisa)
$sisa_ujian = max(0, $total_ujian_tersedia - $ujian_dikerjakan);

// 5. Pengumuman terbaru (3)
$stmt_p = $conn->prepare("SELECT * FROM pengumuman WHERE tipe='umum' OR (tipe='kelas' AND (target_kelas IS NULL OR target_kelas='' OR target_kelas=?)) ORDER BY created_at DESC LIMIT 3");
$stmt_p->bind_param("s", $siswa_kelas);
$stmt_p->execute();
$pengumuman_list = $stmt_p->get_result();
$stmt_p->close();

// 6. Ujian tersedia dengan detail (untuk list)
$ujian_detail = [];
if (!empty($ujian_ids)) {
    $ids_str = implode(',', $ujian_ids);
    $res2 = $conn->query("SELECT id, judul_ujian, deskripsi, tgl_dibuat, waktu_tersedia FROM ujian WHERE id IN ($ids_str) ORDER BY tgl_dibuat DESC");
    $soal_counts = [];
    $st = $conn->query("SELECT id_ujian, COUNT(*) as total FROM soal WHERE id_ujian IN ($ids_str) GROUP BY id_ujian");
    while ($r = $st->fetch_assoc()) $soal_counts[$r['id_ujian']] = $r['total'];
    while ($r = $res2->fetch_assoc()) {
        $r['jumlah_soal'] = $soal_counts[$r['id']] ?? 0;
        $ujian_detail[] = $r;
    }
}

// 7. Riwayat 5 ujian terakhir
$stmt_r = $conn->prepare("
    SELECT h.id, h.id_ujian, h.total_skor, h.waktu_submit, u.judul_ujian
    FROM hasil_ujian h
    JOIN ujian u ON h.id_ujian = u.id
    WHERE h.nis = ? AND h.total_skor IS NOT NULL
    ORDER BY h.waktu_submit DESC
    LIMIT 5
");
$stmt_r->bind_param("s", $siswa_nis);
$stmt_r->execute();
$riwayat_list = $stmt_r->get_result();
$stmt_r->close();

$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard Siswa - <?= htmlspecialchars($sekolah['nama_sekolah']) ?>">
    <title>Dashboard - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
</head>
<body>
    <?php require 'partials/navbar.php'; ?>

    <main class="dashboard-container">
        <!-- Greeting -->
        <div class="greeting-section">
            <h2>👋 Selamat datang, <?= htmlspecialchars($siswa_nama) ?>!</h2>
            <p class="kelas-info"><?= htmlspecialchars($siswa_kelas) ?> — <?= htmlspecialchars($sekolah['nama_sekolah']) ?></p>
        </div>

        <!-- Stat Cards -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card-icon blue"><i class="bi bi-pencil-square"></i></div>
                <div class="stat-card-info">
                    <div class="stat-label">Ujian Tersedia</div>
                    <div class="stat-value"><?= $total_ujian_tersedia ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon green"><i class="bi bi-check-circle"></i></div>
                <div class="stat-card-info">
                    <div class="stat-label">Sudah Dikerjakan</div>
                    <div class="stat-value"><?= $ujian_dikerjakan ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon purple"><i class="bi bi-bar-chart"></i></div>
                <div class="stat-card-info">
                    <div class="stat-label">Rata-rata Nilai</div>
                    <div class="stat-value <?= $rata_rata === null ? '' : 'small' ?>"><?= $rata_rata !== null ? $rata_rata : '-' ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon orange"><i class="bi bi-clock"></i></div>
                <div class="stat-card-info">
                    <div class="stat-label">Sisa Ujian</div>
                    <div class="stat-value"><?= $sisa_ujian ?></div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid: Pengumuman + Ujian Tersedia -->
        <div class="dashboard-grid">
            <!-- Pengumuman -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5><i class="bi bi-megaphone-fill"></i> Pengumuman</h5>
                    <a href="pengumuman.php" class="small text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-card-body">
                    <?php if ($pengumuman_list && $pengumuman_list->num_rows > 0): ?>
                        <?php while ($p = $pengumuman_list->fetch_assoc()): ?>
                        <div class="announcement-item">
                            <div class="ann-title"><?= htmlspecialchars($p['judul']) ?></div>
                            <p class="ann-excerpt"><?= htmlspecialchars(mb_substr($p['isi'], 0, 150)) ?><?= mb_strlen($p['isi']) > 150 ? '...' : '' ?></p>
                            <div class="ann-date"><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($p['created_at']) ?></div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>Belum ada pengumuman</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ujian Tersedia (Ringkasan) -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5><i class="bi bi-collection"></i> Ujian Tersedia</h5>
                    <a href="../index.php" class="small text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-card-body">
                    <?php if (!empty($ujian_detail)): ?>
                        <div class="exam-list">
                            <?php foreach (array_slice($ujian_detail, 0, 4) as $ujian): ?>
                            <a href="../ujian.php?id=<?= $ujian['id'] ?>" class="exam-item">
                                <div class="exam-info">
                                    <div class="exam-title"><?= htmlspecialchars($ujian['judul_ujian']) ?></div>
                                    <div class="exam-meta">
                                        <span><i class="bi bi-question-circle"></i> <?= $ujian['jumlah_soal'] ?> Soal</span>
                                        <?php if (!empty($ujian['waktu_tersedia'])): ?>
                                        <span><i class="bi bi-clock"></i> <?= $ujian['waktu_tersedia'] ?> menit</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="btn-exam-start">Mulai <i class="bi bi-arrow-right ms-1"></i></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-journal-text"></i>
                            <p>Belum ada ujian tersedia saat ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Riwayat Nilai Terakhir -->
        <div class="recent-scores">
            <div class="dashboard-card-header">
                <h5><i class="bi bi-clock-history"></i> Riwayat Nilai Terakhir</h5>
                <a href="../riwayat.php?nis=<?= urlencode($siswa_nis) ?>" class="small text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ujian</th>
                            <th>Nilai</th>
                            <th>Tanggal</th>
                            <th class="text-end">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat_list && $riwayat_list->num_rows > 0): ?>
                            <?php while ($r = $riwayat_list->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($r['judul_ujian']) ?></td>
                                <td>
                                    <?php $score = (int)$r['total_skor']; ?>
                                    <span class="score-badge <?= $score >= 80 ? 'high' : ($score >= 60 ? 'medium' : 'low') ?>">
                                        <?php if ($score >= 80): ?><i class="bi bi-star-fill"></i>
                                        <?php elseif ($score >= 60): ?><i class="bi bi-check-circle"></i>
                                        <?php else: ?><i class="bi bi-exclamation-circle"></i>
                                        <?php endif; ?>
                                        <?= $score ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($r['waktu_submit'])) ?></td>
                                <td class="text-end">
                                    <a href="detail_jawaban.php?id_hasil=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox me-2"></i>Belum ada riwayat nilai
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-student">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($sekolah['nama_sekolah']) ?> — Sistem Ujian Online</p>
        </div>
    </footer>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
