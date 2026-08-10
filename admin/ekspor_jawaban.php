<?php
// Admin: Export student answers for a specific ujian (exam) as Excel (.xls)

require_once '../config/security_headers.php';

session_start();

// Basic security headers (kept consistent with other admin exports)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:;");

// Admin authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

if (!isset($_GET['ujian']) || empty($_GET['ujian'])) {
    die("Parameter tidak valid");
}

$id_ujian = (int)$_GET['ujian'];

// Fetch judul ujian for filename/imformation
$stmt = $conn->prepare("SELECT judul_ujian FROM ujian WHERE id = ?");
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result = $stmt->get_result();
$ujian = $result->fetch_assoc();
$stmt->close();

if (!$ujian) {
    die("Ujian tidak ditemukan");
}

// Fetch all hasil_ujian for this ujian
$sql = "SELECT h.*, 
                (SELECT COUNT(*) FROM exam_violations v WHERE v.id_ujian = h.id_ujian AND v.nis = h.nis) as jumlah_pelanggaran
                FROM hasil_ujian h 
                WHERE h.id_ujian = ? 
                ORDER BY h.nis ASC, h.nama ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$result = $stmt->get_result();
$hasil_list = [];
while ($row = $result->fetch_assoc()) {
    $hasil_list[] = $row;
}
$stmt->close();

// Determine total number of questions for dynamic columns
$stmt = $conn->prepare("SELECT COUNT(*) as total_soal FROM soal WHERE id_ujian = ?");
$stmt->bind_param("i", $id_ujian);
$stmt->execute();
$res_soal = $stmt->get_result();
$total_soal = (int)($res_soal->fetch_assoc()['total_soal'] ?? 0);
$stmt->close();

// Excel file headers (download as .xls)
$nama_file = 'jawaban_ujian_' . $id_ujian . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$nama_file}\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: public");

// Output HTML table compatible with Excel
echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse; font-family: Arial, sans-serif; font-size:12px;">';
echo '<tr><th>NIS</th><th>Nama</th><th>Kelas</th>';
for ($i = 1; $i <= $total_soal; $i++) {
    echo '<th>Soal '.$i.'</th>';
}
echo '<th>Total Skor</th></tr>';

if (count($hasil_list) > 0) {
    foreach ($hasil_list as $hasil) {
        $nis = isset($hasil['nis']) ? htmlspecialchars($hasil['nis']) : '';
        $nama = isset($hasil['nama']) ? htmlspecialchars($hasil['nama']) : '';
        $kelas = isset($hasil['kelas']) ? htmlspecialchars($hasil['kelas']) : '';

        // Decode detail_jawaban which stores per-question answers
        $detail = json_decode($hasil['detail_jawaban'], true);
        if (!is_array($detail)) { $detail = []; }

        echo '<tr>';
        echo '<td>'.$nis.'</td>';
        echo '<td>'.$nama.'</td>';
        echo '<td>'.$kelas.'</td>';

        // Build per-question cells, color-coding by correctness
        for ($q = 0; $q < $total_soal; $q++) {
            $letter = '';
            $is_correct = false;
            if (isset($detail[$q])) {
                $jw = $detail[$q];
                $letter = isset($jw['jawaban_siswa']) ? strtoupper($jw['jawaban_siswa']) : '';
                $is_correct = isset($jw['is_correct']) ? (bool)$jw['is_correct'] : false;
            }
            $cell = htmlspecialchars($letter);
            $bg = '';
            if ($letter !== '' && $is_correct) {
                $bg = ' bgcolor="#d4edda"';
            } elseif ($letter !== '') {
                $bg = ' bgcolor="#f8d7da"';
            }
            echo '<td'.$bg.' style="text-align:center;">'.$cell.'</td>';
        }

        $total_skor = isset($hasil['total_skor']) ? $hasil['total_skor'] : 0;
        echo '<td style="text-align:center;">'.htmlspecialchars($total_skor).'</td>';
        echo '</tr>';
    }
} else {
    $colspan = 3 + $total_soal + 1;
    echo '<tr><td colspan="'.$colspan.'" style="text-align:center;">Belum ada peserta yang menyelesaikan ujian</td></tr>';
}

echo '</table>';
echo '</body></html>';

?>
