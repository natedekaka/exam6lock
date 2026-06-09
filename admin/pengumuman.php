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

if (!empty($_POST['simpan'])) {
    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $tipe = $_POST['tipe'] ?? 'umum';
    $target_kelas = $_POST['target_kelas'] ?? '';
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if ($judul === '' || $isi === '') {
        $message = 'Judul dan isi harus diisi!';
        $message_type = 'danger';
    } else {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE pengumuman SET judul=?, isi=?, tipe=?, target_kelas=? WHERE id=?");
            $stmt->bind_param("ssssi", $judul, $isi, $tipe, $target_kelas, $edit_id);
            if ($stmt->execute()) {
                $message = 'Pengumuman berhasil diperbarui!';
                $message_type = 'success';
                logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'UPDATE', 'PENGUMUMAN', $edit_id, 'Mengupdate pengumuman: ' . $judul);
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO pengumuman (judul, isi, tipe, target_kelas, dibuat_oleh) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $judul, $isi, $tipe, $target_kelas, $_SESSION['admin_id']);
            if ($stmt->execute()) {
                $message = 'Pengumuman berhasil ditambahkan!';
                $message_type = 'success';
                logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'CREATE', 'PENGUMUMAN', $stmt->insert_id, 'Menambahkan pengumuman: ' . $judul);
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $conn->prepare("SELECT judul FROM pengumuman WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $h = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM pengumuman WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Pengumuman berhasil dihapus!';
        $message_type = 'success';
        logAudit($conn, $_SESSION['admin_id'], $_SESSION['admin_username'], 'DELETE', 'PENGUMUMAN', $id, 'Menghapus pengumuman: ' . ($h['judul'] ?? 'ID: ' . $id));
    }
    $stmt->close();
}

$edit_pengumuman = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM pengumuman WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_pengumuman = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$list = $conn->query("SELECT p.*, a.username as admin_name FROM pengumuman p LEFT JOIN admin_users a ON p.dibuat_oleh = a.id ORDER BY p.created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman - <?= htmlspecialchars($sekolah['nama_sekolah'] ?? 'Exam6') ?></title>
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
        .badge-tipe { font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 20px; }
        @media (max-width: 768px) { .sidebar { min-height: auto; } .main-content { padding: 1rem; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="text-center mb-4">
                <i class="bi bi-megaphone text-white" style="font-size: 2.5rem;"></i>
                <h5 class="mt-2"><i class="bi bi-gear me-1"></i>Admin Panel</h5>
            </div>
            <div class="sidebar-menu">
                <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
                <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
                <a href="bank_soal.php"><i class="bi bi-database-fill"></i> Bank Soal Global</a>
                <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
                <a href="analytics.php"><i class="bi bi-graph-up"></i> Analytics</a>
                <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
                <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
                <a href="kelola_kelas.php"><i class="bi bi-diagram-3-fill"></i> Kelola Kelas</a>
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
                <a href="backup_restore.php"><i class="bi bi-cloud-arrow-up-fill"></i> Backup & Restore</a>
                <?php endif; ?>
                <a href="pengumuman.php" class="active"><i class="bi bi-megaphone-fill"></i> Pengumuman</a>
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                <a href="audit_log.php"><i class="bi bi-journal-text"></i> Audit Log</a>
                <?php endif; ?>
                <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
                <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
            </div>
        </div>
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2"></i>Pengumuman</h4>
            </div>

            <?php if (isset($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="card p-4 mb-4">
                <h5 class="fw-bold mb-3"><?= $edit_pengumuman ? 'Edit Pengumuman' : 'Tambah Pengumuman' ?></h5>
                <form method="POST">
                    <input type="hidden" name="edit_id" value="<?= $edit_pengumuman['id'] ?? 0 ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul</label>
                        <input type="text" name="judul" class="form-control" required
                               value="<?= htmlspecialchars($edit_pengumuman['judul'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Isi</label>
                        <textarea name="isi" class="form-control" rows="5" required><?= htmlspecialchars($edit_pengumuman['isi'] ?? '') ?></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipe</label>
                            <select name="tipe" class="form-select">
                                <option value="umum" <?= ($edit_pengumuman['tipe'] ?? 'umum') === 'umum' ? 'selected' : '' ?>>Umum</option>
                                <option value="kelas" <?= ($edit_pengumuman['tipe'] ?? '') === 'kelas' ? 'selected' : '' ?>>Per Kelas</option>
                                <option value="jurusan" <?= ($edit_pengumuman['tipe'] ?? '') === 'jurusan' ? 'selected' : '' ?>>Per Jurusan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Target Kelas</label>
                            <input type="text" name="target_kelas" class="form-control" placeholder="Kosongkan jika semua"
                                   value="<?= htmlspecialchars($edit_pengumuman['target_kelas'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" name="simpan" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?= $edit_pengumuman ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($edit_pengumuman): ?>
                    <a href="pengumuman.php" class="btn btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Judul</th>
                                <th>Tipe</th>
                                <th>Target</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list && $list->num_rows > 0): ?>
                            <?php while ($row = $list->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($row['judul']) ?></td>
                                <td>
                                    <?php
                                    $cls = $row['tipe'] === 'umum' ? 'bg-info' : ($row['tipe'] === 'kelas' ? 'bg-warning text-dark' : 'bg-secondary');
                                    ?>
                                    <span class="badge badge-tipe <?= $cls ?>"><?= htmlspecialchars($row['tipe']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['target_kelas'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['admin_name'] ?? '-') ?></td>
                                <td class="small"><?= htmlspecialchars($row['created_at']) ?></td>
                                <td>
                                    <a href="pengumuman.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="pengumuman.php?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Hapus pengumuman ini?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-inbox me-1"></i>Belum ada pengumuman</td></tr>
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
