<?php
require_once 'config/database.php';
require_once 'config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$checkSkorAwal = $db->columnExists('hasil_ujian', 'skor_awal');
if (!$checkSkorAwal) {
    $conn->query("ALTER TABLE hasil_ujian ADD COLUMN skor_awal INT DEFAULT NULL AFTER total_skor");
}

$filter_ujian = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;

$ujian_list = [];
$res_u = $conn->query("SELECT * FROM ujian ORDER BY created_at DESC");
while ($row = $res_u->fetch_assoc()) {
    $ujian_list[$row['id']] = $row;
}

$where = "";
$params = [];
$types = "";

if ($filter_ujian > 0) {
    $where = "WHERE h.id_ujian = ?";
    $params[] = $filter_ujian;
    $types = "i";
}

$sql = "SELECT h.*, 
          (SELECT COUNT(*) FROM exam_violations v WHERE v.id_ujian = h.id_ujian AND v.nis = h.nis) as jumlah_pelanggaran
          FROM hasil_ujian h 
          $where
          ORDER BY h.submitted_at DESC";

if ($filter_ujian > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $filter_ujian);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$hasil_list = [];
while ($row = $result->fetch_assoc()) {
    $skor_awal = $row['skor_awal'] ?? null;
    $penalty = 0;
    $jumlah_pelanggaran = (int)($row['jumlah_pelanggaran'] ?? 0);
    
    if ($skor_awal !== null) {
        $penalty = $skor_awal - $row['total_skor'];
    } else if ($jumlah_pelanggaran > 0) {
        $estimated_penalty = min($jumlah_pelanggaran * 10, $row['total_skor'] * 0.5);
        $penalty = $estimated_penalty;
        $skor_awal = $row['total_skor'] + $penalty;
    }
    
    $row['skor_awal'] = $skor_awal;
    $row['penalty'] = $penalty;
    $row['jumlah_pelanggaran'] = $jumlah_pelanggaran;
    $hasil_list[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Nilai - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="vendor/fonts/poppins.css" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: #f0f2f5; }
        .header-section {
            background: linear-gradient(135deg, <?= $sekolah['warna_primer'] ?> 0%, <?= $sekolah['warna_sekunder'] ?> 100%);
            padding: 30px 0;
            color: white;
            margin-bottom: 30px;
        }
        .rekap-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 20px;
        }
        .table-responsive { border-radius: 12px; overflow: hidden; }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding: 12px 8px;
        }
        .table td {
            padding: 12px 8px;
            vertical-align: middle;
        }
        .skor-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .skor-awal { background: #d1ecf1; color: #0c5460; }
        .skor-akhir { background: #d4edda; color: #155724; }
        .penalty-badge { background: #f8d7da; color: #721c24; }
        .violation-badge { background: #fff3cd; color: #856404; }
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 15px;
        }
        .btn-back:hover { background: rgba(255,255,255,0.3); color: white; }
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .summary-item {
            text-align: center;
            padding: 15px;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
        }
        .summary-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left me-2"></i>Kembali
            </a>
            <h2 class="fw-bold mb-1"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></h2>
            <p class="mb-0 opacity-75">Rekapitulasi Nilai Ujian</p>
        </div>
    </div>

    <div class="container">
        <!-- Summary Statistics -->
        <div class="summary-box">
            <div class="row">
                <div class="col-6 col-md-3 summary-item">
                    <div class="summary-value"><?= count($hasil_list) ?></div>
                    <div class="summary-label">Total Hasil</div>
                </div>
                <div class="col-6 col-md-3 summary-item">
                    <div class="summary-value">
                        <?php 
                        $totalSkor = 0;
                        foreach ($hasil_list as $h) $totalSkor += $h['total_skor'];
                        echo $totalSkor;
                        ?>
                    </div>
                    <div class="summary-label">Total Skor Akhir</div>
                </div>
                <div class="col-6 col-md-3 summary-item">
                    <div class="summary-value">
                        <?php 
                        $totalAwal = 0;
                        foreach ($hasil_list as $h) $totalAwal += ($h['skor_awal'] ?? $h['total_skor']);
                        echo $totalAwal;
                        ?>
                    </div>
                    <div class="summary-label">Total Skor Asli</div>
                </div>
                <div class="col-6 col-md-3 summary-item">
                    <div class="summary-value">
                        <?php 
                        $totalPenalty = 0;
                        foreach ($hasil_list as $h) $totalPenalty += $h['penalty'];
                        echo $totalPenalty;
                        ?>
                    </div>
                    <div class="summary-label">Total Pengurangan</div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="rekap-card mb-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Pilih Ujian</label>
                    <select name="ujian" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Semua Ujian --</option>
                        <?php foreach ($ujian_list as $ujian): ?>
                            <option value="<?= $ujian['id'] ?>" <?= ($filter_ujian == $ujian['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ujian['judul_ujian']) ?> (ID: <?= $ujian['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <a href="rekap_nilai.php" class="btn btn-outline-light">Reset Filter</a>
                </div>
            </form>
        </div>

        <!-- Rekap Table -->
        <div class="rekap-card">
            <h5 class="mb-3"><i class="bi bi-table me-2"></i>Detail Nilai Siswa</h5>
            
            <?php if (empty($hasil_list)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Belum ada hasil ujian yang tercatat.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Ujian</th>
                                <th>Skor Asli</th>
                                <th>Skor Akhir</th>
                                <th>Pengurangan</th>
                                <th>Pelanggaran</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hasil_list as $i => $hasil): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($hasil['nis']) ?></strong></td>
                                <td><?= htmlspecialchars($hasil['nama']) ?></td>
                                <td><?= htmlspecialchars($hasil['kelas']) ?></td>
                                <td>
                                    <?= htmlspecialchars($ujian_list[$hasil['id_ujian']]['judul_ujian'] ?? 'Unknown') ?>
                                </td>
                                <td>
                                    <?php if ($hasil['skor_awal'] !== null): ?>
                                        <span class="skor-badge skor-awal">
                                            <?= $hasil['skor_awal'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="skor-badge skor-akhir">
                                        <?= $hasil['total_skor'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($hasil['penalty'] > 0): ?>
                                        <span class="skor-badge penalty-badge">
                                            -<?= $hasil['penalty'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasil['jumlah_pelanggaran'] > 0): ?>
                                        <span class="skor-badge violation-badge">
                                            <?= $hasil['jumlah_pelanggaran'] ?>x
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($hasil['submitted_at'])) ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Keterangan:</strong> 
                        Skor Asli = Skor sebelum dikurangi pelanggaran. 
                        Skor Akhir = Skor yang didapat setelah pengurangan (maksimal 50% dari skor asli).
                        Pengurangan = 10 poin per pelanggaran fullscreen.
                    </small>
                </div>
            <?php endif; ?>
        </div>
        
        <footer class="text-center text-muted py-4">
            <small>&copy; <?= date('Y') ?> Sistem Ujian Online - by natedekaka</small>
        </footer>
    </div>
</body>
</html>
