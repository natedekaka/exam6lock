<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/audit_helper.php';

$sekolah = getKonfigurasiSekolah($conn);

if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE izin_remedi SET approved_by=?, approved_at=NOW() WHERE id=?");
    $stmt->bind_param("ii", $_SESSION['admin_id'], $id);
    $stmt->execute();
    $stmt->close();
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'UPDATE', 'IZIN_REMEDI', $id, 'Menyetujui izin remedi ID: ' . $id);
    header('Location: izin_remedi.php');
    exit;
}

if (isset($_GET['tolak'])) {
    $id = (int)$_GET['tolak'];
    $stmt = $conn->prepare("DELETE FROM izin_remedi WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'IZIN_REMEDI', $id, 'Menolak izin remedi ID: ' . $id);
    header('Location: izin_remedi.php');
    exit;
}

$list = $conn->query("SELECT ir.*, u.judul_ujian FROM izin_remedi ir LEFT JOIN ujian u ON ir.id_ujian = u.id ORDER BY ir.created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Izin Remedi - <?= htmlspecialchars($sekolah['nama_sekolah'] ?? 'Exam6') ?></title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li class="active">Izin Remedi</li>
            </ul>
            <h3><i class="bi bi-arrow-repeat me-2"></i>Izin Remedi</h3>
        </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Siswa</th>
                                <th>NIS</th>
                                <th>Kelas</th>
                                <th>Ujian</th>
                                <th>Alasan</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list && $list->num_rows > 0): ?>
                            <?php while ($row = $list->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['nis']) ?></td>
                                <td><?= htmlspecialchars($row['kelas']) ?></td>
                                <td><?= htmlspecialchars($row['judul_ujian'] ?? '-') ?></td>
                                <td class="small"><?= htmlspecialchars($row['alasan'] ?? '-') ?></td>
                                <td>
                                    <?php if ($row['approved_at']): ?>
                                    <span class="badge bg-success">Disetujui</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning text-dark">Menunggu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($row['created_at']) ?></td>
                                <td>
                                    <?php if (!$row['approved_at']): ?>
                                    <a href="izin_remedi.php?approve=<?= $row['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Setujui izin remedi ini?')"><i class="bi bi-check-lg"></i></a>
                                    <a href="izin_remedi.php?tolak=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tolak izin remedi ini?')"><i class="bi bi-x-lg"></i></a>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-inbox me-1"></i>Belum ada pengajuan izin remedi</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
