<?php
session_start();
if (!isset($_SESSION['siswa_id'])) {
    header('Location: ../login_siswa.php');
    exit;
}

require_once '../config/database.php';

$siswa_kelas = $_SESSION['siswa_kelas'] ?? '';
$siswa_jurusan_id = $_SESSION['siswa_jurusan_id'] ?? 0;

$sql = "SELECT * FROM pengumuman WHERE tipe='umum' OR (tipe='kelas' AND (target_kelas IS NULL OR target_kelas='' OR target_kelas=?)) ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $siswa_kelas);
$stmt->execute();
$list = $stmt->get_result();
$stmt->close();
$active_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman - <?= htmlspecialchars($sekolah['nama_sekolah'] ?? 'Siswa') ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="assets/css/siswa.css" rel="stylesheet">
    <style>
        .page-content { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        .badge-tipe { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 20px; }
        .announcement-body { white-space: pre-wrap; }
    </style>
</head>
<body>
    <?php require 'partials/navbar.php'; ?>
    <div class="page-content">
        <h4 class="fw-bold mb-4"><i class="bi bi-megaphone-fill me-2 text-primary"></i>Pengumuman</h4>

        <?php if ($list && $list->num_rows > 0): ?>
        <?php while ($row = $list->fetch_assoc()): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold"><?= htmlspecialchars($row['judul']) ?></span>
                <span class="d-flex align-items-center gap-2">
                    <?php $cls = $row['tipe'] === 'umum' ? 'bg-info' : 'bg-warning text-dark'; ?>
                    <span class="badge badge-tipe <?= $cls ?>"><?= htmlspecialchars($row['tipe']) ?></span>
                    <small class="text-muted"><?= htmlspecialchars($row['created_at']) ?></small>
                </span>
            </div>
            <div class="card-body announcement-body text-muted"><?= htmlspecialchars($row['isi']) ?></div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
            <p class="mt-2">Belum ada pengumuman</p>
        </div>
        <?php endif; ?>
    </div>
    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
</body>
</html>
