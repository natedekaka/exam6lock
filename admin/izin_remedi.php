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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; padding: 1.5rem 1rem; }
        .sidebar h5 { color: #e0e0e0; font-weight: 600; letter-spacing: 0.5px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #b0b0b0; text-decoration: none; border-radius: 10px; transition: all 0.2s; font-size: 0.9rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar-menu a i { width: 20px; text-align: center; }
        .main-content { padding: 2rem; }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border-top: none; }
        .table td { vertical-align: middle; font-size: 0.9rem; }
        @media (max-width: 768px) { .sidebar { min-height: auto; } .main-content { padding: 1rem; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="text-center mb-4">
                <i class="bi bi-arrow-repeat text-white" style="font-size: 2.5rem;"></i>
                <h5 class="mt-2"><i class="bi bi-gear me-1"></i>Admin Panel</h5>
            </div>
            <div class="sidebar-menu">
                <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
                <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
                <a href="bank_soal.php"><i class="bi bi-database-fill"></i> Bank Soal Global</a>
                <a href="rekap_nilai.php" class="active"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
                <a href="analytics.php"><i class="bi bi-graph-up"></i> Analytics</a>
                <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
                <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
                <a href="kelola_kelas.php"><i class="bi bi-diagram-3-fill"></i> Kelola Kelas</a>
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
                <a href="backup_restore.php"><i class="bi bi-cloud-arrow-up-fill"></i> Backup & Restore</a>
                <?php endif; ?>
                <a href="pengumuman.php"><i class="bi bi-megaphone-fill"></i> Pengumuman</a>
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                <a href="audit_log.php"><i class="bi bi-journal-text"></i> Audit Log</a>
                <?php endif; ?>
                <a href="izin_remedi.php"><i class="bi bi-arrow-repeat"></i> Izin Remedi</a>
                <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
                <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
            </div>
        </div>
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-arrow-repeat me-2"></i>Izin Remedi</h4>
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
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
