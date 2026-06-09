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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; }
        .main-content { padding: 2rem; max-width: 900px; margin: 0 auto; }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .card-header { background: transparent; border-bottom: 1px solid #eee; font-weight: 600; }
        .badge-tipe { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 20px; }
        .announcement-body { white-space: pre-wrap; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #6c757d; text-decoration: none; margin-bottom: 1rem; }
        .back-link:hover { color: #0d6efd; }
    </style>
</head>
<body>
<div class="main-content">
    <a href="../index.php" class="back-link"><i class="bi bi-arrow-left"></i>Kembali</a>
    <h4 class="fw-bold mb-4"><i class="bi bi-megaphone-fill me-2"></i>Pengumuman</h4>

    <?php if ($list && $list->num_rows > 0): ?>
    <?php while ($row = $list->fetch_assoc()): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($row['judul']) ?></span>
            <span>
                <?php
                $cls = $row['tipe'] === 'umum' ? 'bg-info' : 'bg-warning text-dark';
                ?>
                <span class="badge badge-tipe <?= $cls ?>"><?= htmlspecialchars($row['tipe']) ?></span>
                <small class="text-muted ms-2"><?= htmlspecialchars($row['created_at']) ?></small>
            </span>
        </div>
        <div class="card-body announcement-body"><?= htmlspecialchars($row['isi']) ?></div>
    </div>
    <?php endwhile; ?>
    <?php else: ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
        <p class="mt-2">Belum ada pengumuman</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
