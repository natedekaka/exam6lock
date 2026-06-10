<?php
session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data: ../uploads/;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';
$message_type = '';

$filter_ujian = isset($_GET['filter_ujian']) ? (int)$_GET['filter_ujian'] : 0;
$filter_kategori = isset($_GET['filter_kategori']) ? trim($_GET['filter_kategori']) : '';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

$ujian_list = $conn->query("SELECT id, judul_ujian, status FROM ujian ORDER BY judul_ujian");

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $conn->prepare("SELECT gambar_pertanyaan, gambar_a, gambar_b, gambar_c, gambar_d, gambar_e FROM soal WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $soal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($soal) {
        foreach (['gambar_pertanyaan', 'gambar_a', 'gambar_b', 'gambar_c', 'gambar_d', 'gambar_e'] as $gbr) {
            if ($soal[$gbr] && file_exists('../uploads/' . $soal[$gbr])) {
                unlink('../uploads/' . $soal[$gbr]);
            }
        }
        $stmt = $conn->prepare("DELETE FROM soal WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Soal berhasil dihapus!";
            $message_type = 'success';
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['copy_selected'])) {
        $target_ujian = isset($_POST['target_ujian']) ? (int)$_POST['target_ujian'] : 0;
        $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : '';

        if ($target_ujian > 0 && !empty($selected_ids)) {
            $ids = explode(',', $selected_ids);
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids);
            $copied = 0;

            foreach ($ids as $soal_id) {
                $stmt = $conn->prepare("SELECT * FROM soal WHERE id = ?");
                $stmt->bind_param("i", $soal_id);
                $stmt->execute();
                $soal = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($soal) {
                    $check = $conn->prepare("SELECT id FROM soal WHERE id_ujian = ? AND pertanyaan = ?");
                    $check->bind_param("is", $target_ujian, $soal['pertanyaan']);
                    $check->execute();
                    $exists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$exists) {
                        $stmt = $conn->prepare("INSERT INTO soal (id_ujian, pertanyaan, gambar_pertanyaan, opsi_a, gambar_a, opsi_b, gambar_b, opsi_c, gambar_c, opsi_d, gambar_d, opsi_e, gambar_e, kunci_jawaban, poin, kategori, timer_soal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssssssssssisi", $target_ujian, $soal['pertanyaan'], $soal['gambar_pertanyaan'], $soal['opsi_a'], $soal['gambar_a'], $soal['opsi_b'], $soal['gambar_b'], $soal['opsi_c'], $soal['gambar_c'], $soal['opsi_d'], $soal['gambar_d'], $soal['opsi_e'], $soal['gambar_e'], $soal['kunci_jawaban'], $soal['poin'], $soal['kategori'], $soal['timer_soal']);
                        if ($stmt->execute()) {
                            $copied++;
                        }
                        $stmt->close();
                    }
                }
            }

            if ($copied > 0) {
                $message = "Berhasil menyalin $copied soal ke ujian tujuan!";
                $message_type = 'success';
            } else {
                $message = "Tidak ada soal yang disalin (mungkin sudah ada di ujian tujuan).";
                $message_type = 'warning';
            }
        } else {
            $message = "Pilih ujian tujuan dan minimal satu soal!";
            $message_type = 'danger';
        }
    }

    if (isset($_POST['move_selected'])) {
        $target_ujian = isset($_POST['target_ujian_move']) ? (int)$_POST['target_ujian_move'] : 0;
        $selected_ids = isset($_POST['selected_ids_move']) ? $_POST['selected_ids_move'] : '';

        if ($target_ujian > 0 && !empty($selected_ids)) {
            $ids = explode(',', $selected_ids);
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids);
            $moved = 0;

            foreach ($ids as $soal_id) {
                $stmt = $conn->prepare("SELECT * FROM soal WHERE id = ?");
                $stmt->bind_param("i", $soal_id);
                $stmt->execute();
                $soal = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($soal) {
                    $check = $conn->prepare("SELECT id FROM soal WHERE id_ujian = ? AND pertanyaan = ?");
                    $check->bind_param("is", $target_ujian, $soal['pertanyaan']);
                    $check->execute();
                    $exists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$exists) {
                        $stmt = $conn->prepare("UPDATE soal SET id_ujian = ? WHERE id = ?");
                        $stmt->bind_param("ii", $target_ujian, $soal_id);
                        if ($stmt->execute()) {
                            $moved++;
                        }
                        $stmt->close();
                    }
                }
            }

            if ($moved > 0) {
                $message = "Berhasil memindahkan $moved soal ke ujian tujuan!";
                $message_type = 'success';
            } else {
                $message = "Tidak ada soal yang dipindahkan (mungkin sudah ada di ujian tujuan).";
                $message_type = 'warning';
            }
        } else {
            $message = "Pilih ujian tujuan dan minimal satu soal!";
            $message_type = 'danger';
        }
    }
}

$sql = "SELECT s.*, u.judul_ujian FROM soal s JOIN ujian u ON s.id_ujian = u.id WHERE 1=1";
$params = [];
$types = '';

if ($filter_ujian > 0) {
    $sql .= " AND s.id_ujian = ?";
    $params[] = $filter_ujian;
    $types .= 'i';
}

if ($filter_kategori !== '') {
    $sql .= " AND s.kategori = ?";
    $params[] = $filter_kategori;
    $types .= 's';
}

if ($search_keyword !== '') {
    $sql .= " AND (s.pertanyaan LIKE ? OR s.opsi_a LIKE ? OR s.opsi_b LIKE ? OR s.opsi_c LIKE ? OR s.opsi_d LIKE ? OR s.opsi_e LIKE ?)";
    $keyword = '%' . $search_keyword . '%';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $keyword;
    }
    $types .= 'ssssss';
}

$sql .= " ORDER BY u.judul_ujian, s.id";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$all_soal = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Bank Soal Global</title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        .soal-detail { display: none; background: #f8fafc; border-top: 1px solid var(--border); }
        .soal-detail.show { display: table-row; }
        .soal-detail td { padding: 1.5rem; }
        table tbody tr.soal-row { cursor: pointer; }
    </style>
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li class="active">Bank Soal Global</li>
            </ul>
            <h3><i class="bi bi-database-fill me-2"></i>Bank Soal Global</h3>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show animate-fade-in" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-circle' : 'exclamation-triangle') ?>-fill me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card animate-fade-in">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Filter Ujian</label>
                        <select name="filter_ujian" class="form-select" onchange="this.form.submit()">
                            <option value="0">-- Semua Ujian --</option>
                            <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>" <?= $filter_ujian == $ujian['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ujian['judul_ujian']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Filter Kategori</label>
                        <select name="filter_kategori" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Semua Kategori --</option>
                            <option value="Mudah" <?= $filter_kategori === 'Mudah' ? 'selected' : '' ?>>Mudah</option>
                            <option value="Sedang" <?= $filter_kategori === 'Sedang' ? 'selected' : '' ?>>Sedang</option>
                            <option value="Sulit" <?= $filter_kategori === 'Sulit' ? 'selected' : '' ?>>Sulit</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cari Soal</label>
                        <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Cari
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card animate-fade-in">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-list-ol me-2"></i>Daftar Soal Global</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?= $all_soal->num_rows ?> soal</span>
                    <div class="bulk-actions" style="display: none;">
                        <button type="button" class="btn btn-sm btn-primary" id="copySelectedBtn">
                            <i class="bi bi-copy me-1"></i>Copy ke Ujian Lain
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="moveSelectedBtn">
                            <i class="bi bi-arrow-right-circle me-1"></i>Pindahkan
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($all_soal->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                                <th class="text-center" style="width: 50px;">ID</th>
                                <th>Pertanyaan</th>
                                <th>Ujian</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Poin</th>
                                <th class="text-center">Kunci</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($soal = $all_soal->fetch_assoc()): ?>
                            <tr class="soal-row" onclick="toggleDetail(<?= $soal['id'] ?>)" data-id="<?= $soal['id'] ?>">
                                <td class="text-center" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="soal-checkbox" value="<?= $soal['id'] ?>">
                                </td>
                                <td class="text-center"><?= $soal['id'] ?></td>
                                <td style="white-space: normal; word-wrap: break-word; max-width: 300px;">
                                    <?= htmlspecialchars(mb_strimwidth(strip_tags($soal['pertanyaan']), 0, 120, '...')) ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($soal['judul_ujian']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($soal['kategori'])): ?>
                                        <span class="badge bg-<?= $soal['kategori'] === 'Mudah' ? 'success' : ($soal['kategori'] === 'Sedang' ? 'warning' : 'danger') ?>">
                                            <?= htmlspecialchars($soal['kategori']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold"><?= (int)$soal['poin'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $soal['kunci_jawaban'] === 'a' ? 'primary' : ($soal['kunci_jawaban'] === 'b' ? 'success' : ($soal['kunci_jawaban'] === 'c' ? 'warning' : ($soal['kunci_jawaban'] === 'd' ? 'danger' : 'info'))) ?>">
                                        <?= strtoupper($soal['kunci_jawaban']) ?>
                                    </span>
                                </td>
                                <td class="text-center" onclick="event.stopPropagation()">
                                    <div class="action-buttons">
                                        <a href="tambah_soal.php?ujian=<?= $soal['id_ujian'] ?>&edit=<?= $soal['id'] ?>" class="action-btn-group">
                                            <span class="action-btn action-btn-edit"><i class="bi bi-pencil"></i></span>
                                            <span class="action-btn-label">Edit</span>
                                        </a>
                                        <a href="?hapus=<?= $soal['id'] ?>" class="action-btn-group" onclick="return confirm('Hapus soal ini?')">
                                            <span class="action-btn action-btn-delete"><i class="bi bi-trash3"></i></span>
                                            <span class="action-btn-label">Hapus</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr class="soal-detail" id="detail-<?= $soal['id'] ?>">
                                <td colspan="8">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="fw-bold mb-2">Pertanyaan:</h6>
                                            <p class="mb-3"><?= nl2br(htmlspecialchars($soal['pertanyaan'])) ?></p>
                                            <?php if ($soal['gambar_pertanyaan']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($soal['gambar_pertanyaan']) ?>" alt="Gambar" style="max-width: 200px; max-height: 150px; object-fit: contain;" class="mb-3 border rounded">
                                            <?php endif; ?>
                                            <div class="row">
                                                <?php $opsi = ['a' => $soal['opsi_a'], 'b' => $soal['opsi_b'], 'c' => $soal['opsi_c'], 'd' => $soal['opsi_d'], 'e' => $soal['opsi_e']]; ?>
                                                <?php $gambar = ['a' => $soal['gambar_a'], 'b' => $soal['gambar_b'], 'c' => $soal['gambar_c'], 'd' => $soal['gambar_d'], 'e' => $soal['gambar_e']]; ?>
                                                <?php foreach ($opsi as $huruf => $teks): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="p-2 rounded <?= $soal['kunci_jawaban'] === $huruf ? 'bg-success bg-opacity-10 border border-success' : 'bg-light' ?>">
                                                        <strong><?= strtoupper($huruf) ?>.</strong>
                                                        <?= nl2br(htmlspecialchars($teks)) ?>
                                                        <?php if ($soal['kunci_jawaban'] === $huruf): ?>
                                                            <span class="badge bg-success ms-1"><i class="bi bi-check"></i> Kunci</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($gambar[$huruf])): ?>
                                                            <br><img src="../uploads/<?= htmlspecialchars($gambar[$huruf]) ?>" alt="Gambar <?= $huruf ?>" style="max-width: 80px; max-height: 60px; object-fit: contain;" class="mt-1 border rounded">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card bg-light">
                                                <div class="card-body py-3">
                                                    <small class="text-muted d-block">ID Soal: <strong><?= $soal['id'] ?></strong></small>
                                                    <small class="text-muted d-block">Ujian: <strong><?= htmlspecialchars($soal['judul_ujian']) ?></strong></small>
                                                    <small class="text-muted d-block">Kategori: <strong><?= htmlspecialchars($soal['kategori'] ?? '-') ?></strong></small>
                                                    <small class="text-muted d-block">Poin: <strong><?= (int)$soal['poin'] ?></strong></small>
                                                    <small class="text-muted d-block">Timer: <strong><?= ($soal['timer_soal'] ?? 0) > 0 ? $soal['timer_soal'] . ' dtk' : '-' ?></strong></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">Tidak ada soal ditemukan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Copy Modal -->
    <div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 16px;">
                <div class="modal-header border-0">
                    <h5 class="fw-bold"><i class="bi bi-copy me-2"></i>Copy ke Ujian Lain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="selected_ids" id="copySelectedIds" value="">
                        <p>Pilih ujian tujuan untuk menyalin soal yang dipilih:</p>
                        <select name="target_ujian" class="form-select" required>
                            <option value="">-- Pilih Ujian --</option>
                            <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>"><?= htmlspecialchars($ujian['judul_ujian']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <p class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Soal yang sudah ada di ujian tujuan (berdasarkan teks pertanyaan) tidak akan disalin.</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="copy_selected" class="btn btn-primary"><i class="bi bi-copy me-1"></i>Copy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Move Modal -->
    <div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 16px;">
                <div class="modal-header border-0">
                    <h5 class="fw-bold"><i class="bi bi-arrow-right-circle me-2"></i>Pindahkan ke Ujian Lain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="selected_ids_move" id="moveSelectedIds" value="">
                        <p>Pilih ujian tujuan untuk memindahkan soal yang dipilih:</p>
                        <select name="target_ujian_move" class="form-select" required>
                            <option value="">-- Pilih Ujian --</option>
                            <?php $ujian_list->data_seek(0); while ($ujian = $ujian_list->fetch_assoc()): ?>
                            <option value="<?= $ujian['id'] ?>"><?= htmlspecialchars($ujian['judul_ujian']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="alert alert-warning mt-2 py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Soal akan dipindahkan (id_ujian diubah), bukan disalin. Soal tidak akan ada lagi di ujian asal.
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="move_selected" class="btn btn-warning" onclick="return confirm('Pindahkan soal yang dipilih ke ujian tujuan?')"><i class="bi bi-arrow-right-circle me-1"></i>Pindahkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function toggleDetail(id) {
            const detail = document.getElementById('detail-' + id);
            detail.classList.toggle('show');
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.overlay').classList.toggle('show');
        }

        document.querySelectorAll('.sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                    document.querySelector('.overlay').classList.remove('show');
                }
            });
        });

        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.soal-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        document.querySelectorAll('.soal-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });

        function updateBulkActions() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            const bulkActions = document.querySelector('.bulk-actions');
            if (checked.length > 0) {
                bulkActions.style.display = 'inline-flex';
            } else {
                bulkActions.style.display = 'none';
            }
        }

        document.getElementById('copySelectedBtn')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            if (checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('copySelectedIds').value = ids;
            new bootstrap.Modal(document.getElementById('copyModal')).show();
        });

        document.getElementById('moveSelectedBtn')?.addEventListener('click', function() {
            const checked = document.querySelectorAll('.soal-checkbox:checked');
            if (checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('moveSelectedIds').value = ids;
            new bootstrap.Modal(document.getElementById('moveModal')).show();
        });
    </script>
</body>
</html>
