<?php

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$selected_ujian = isset($_GET['ujian']) ? (int)$_GET['ujian'] : 0;
$kkm = isset($_GET['kkm']) ? (int)$_GET['kkm'] : 60;

$ujian_list = $conn->query("SELECT id, judul_ujian FROM ujian ORDER BY judul_ujian");

$analytics = [
    'total_peserta' => 0,
    'avg_score' => 0,
    'avg_original' => 0,
    'total_violations' => 0,
    'completion_rate' => 0,
    'grade_distribution' => ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0],
    'score_ranges' => [
        '0-20' => 0,
        '21-40' => 0,
        '41-60' => 0,
        '61-80' => 0,
        '81-100' => 0
    ],
    'violations_by_hour' => [],
    'recent_submissions' => [],
    'top_scorers' => [],
    'needs_remedi' => 0,
    'question_analysis' => []
];

if ($selected_ujian > 0) {
    // Get basic stats
    $sql = "
        SELECT 
            COUNT(*) as total,
            AVG(CASE WHEN skor_awal IS NOT NULL THEN skor_awal ELSE total_skor END) as avg_original,
            AVG(total_skor) as avg_score,
            MAX(total_skor) as highest,
            MIN(total_skor) as lowest
        FROM hasil_ujian 
        WHERE id_ujian = ?
    ";
    $stats = $db->fetchRow( $sql, [$selected_ujian], "i");
    
    $analytics['total_peserta'] = $stats['total'];
    $analytics['avg_score'] = round($stats['avg_score'], 1);
    $analytics['avg_original'] = round($stats['avg_original'], 1);
    
    $sql = "
        SELECT COUNT(*) as total_violations, COUNT(DISTINCT nis) as students_with_violations
        FROM exam_violations 
        WHERE id_ujian = ?
    ";
    $violation_stats = $db->fetchRow( $sql, [$selected_ujian], "i");
    
    $analytics['total_violations'] = $violation_stats['total_violations'];
    
    // Grade distribution
    $sql = "
        SELECT 
            CASE 
                WHEN total_skor >= ($kkm + 17) THEN 'A'
                WHEN total_skor >= ($kkm + 9) THEN 'B'
                WHEN total_skor >= $kkm THEN 'C'
                WHEN total_skor >= ($kkm - 15) THEN 'D'
                ELSE 'E'
            END as grade,
            COUNT(*) as count
        FROM hasil_ujian 
        WHERE id_ujian = ?
        GROUP BY grade
        ORDER BY grade
    ";
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    foreach ($result as $row) {
        $analytics['grade_distribution'][$row['grade']] = $row['count'];
    }
    
    $sql = "
        SELECT 
            CASE 
                WHEN total_skor <= 20 THEN '0-20'
                WHEN total_skor <= 40 THEN '21-40'
                WHEN total_skor <= 60 THEN '41-60'
                WHEN total_skor <= 80 THEN '61-80'
                ELSE '81-100'
            END as score_range,
            COUNT(*) as count
        FROM hasil_ujian 
        WHERE id_ujian = ?
        GROUP BY score_range
        ORDER BY score_range
    ";
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    foreach ($result as $row) {
        $analytics['score_ranges'][$row['score_range']] = $row['count'];
    }
    
    // Violations by hour
    $sql = "
        SELECT 
            HOUR(created_at) as hour, COUNT(*) as count
        FROM exam_violations 
        WHERE id_ujian = ?
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ";
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    $violations_by_hour = [];
    for ($i = 0; $i < 24; $i++) {
        $violations_by_hour[$i] = 0;
    }
    foreach ($result as $row) {
        $violations_by_hour[(int)$row['hour']] = $row['count'];
    }
    $analytics['violations_by_hour'] = $violations_by_hour;
    
    // Peak submission hours analysis
    $sql = "
        SELECT 
            HOUR(waktu_submit) as hour, COUNT(*) as count
        FROM hasil_ujian 
        WHERE id_ujian = ?
        GROUP BY HOUR(waktu_submit)
        ORDER BY hour
    ";
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    $submission_by_hour = [];
    for ($i = 0; $i < 24; $i++) {
        $submission_by_hour[$i] = 0;
    }
    foreach ($result as $row) {
        $submission_by_hour[(int)$row['hour']] = $row['count'];
    }
    $analytics['submission_by_hour'] = $submission_by_hour;
    $analytics['peak_hour'] = array_search(max($submission_by_hour), $submission_by_hour);
    
    // Recent submissions
    $sql = "
        SELECT nama, nis, total_skor, waktu_submit
        FROM hasil_ujian 
        WHERE id_ujian = ?
        ORDER BY waktu_submit DESC
        LIMIT 10
    ";
    $analytics['recent_submissions'] = $db->fetchAll( $sql, [$selected_ujian], "i");
    
    // Top scorers
    $sql = "
        SELECT nama, nis, total_skor, kelas
        FROM hasil_ujian 
        WHERE id_ujian = ?
        ORDER BY total_skor DESC
        LIMIT 5
    ";
    $analytics['top_scorers'] = $db->fetchAll( $sql, [$selected_ujian], "i");
    
    // Students needing remedial
    $sql = "
        SELECT h.id, h.nama, h.nis, h.kelas, h.total_skor
        FROM hasil_ujian h
        WHERE h.id_ujian = ? AND h.total_skor < ?
        ORDER BY h.total_skor ASC
    ";
    $analytics['needs_remedi_list'] = $db->fetchAll( $sql, [$selected_ujian, $kkm], "ii");
    $analytics['needs_remedi'] = count($analytics['needs_remedi_list']);
    
    // Class-wise performance breakdown
    $sql = "
        SELECT 
            kelas,
            COUNT(*) as total_students,
            AVG(total_skor) as avg_score,
            SUM(CASE WHEN total_skor >= ? THEN 1 ELSE 0 END) as passed,
            MIN(total_skor) as min_score,
            MAX(total_skor) as max_score
        FROM hasil_ujian 
        WHERE id_ujian = ?
        GROUP BY kelas
        ORDER BY kelas
    ";
    $result = $db->fetchAll( $sql, [$kkm, $selected_ujian], "ii");
    $class_stats = [];
    foreach ($result as $row) {
        $row['pass_rate'] = $row['total_students'] > 0 ? round(($row['passed'] / $row['total_students']) * 100, 1) : 0;
        $row['avg_score'] = round($row['avg_score'], 1);
        $class_stats[] = $row;
    }
    $analytics['class_stats'] = $class_stats;
    
    // Get list of students who already have remedial permission
    $sql = "SELECT nis FROM izin_remedi WHERE id_ujian = ?";
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    $remedi_given = [];
    foreach ($result as $row) {
        $remedi_given[] = $row['nis'];
    }
    $analytics['remedi_given'] = $remedi_given;
    
    // Question-level statistics
    $question_stats = [];
    
    // Get all soal for this ujian
    $sql = "SELECT id, pertanyaan, kunci_jawaban FROM soal WHERE id_ujian = ?";
    $soal_data = [];
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    foreach ($result as $row) {
        $soal_data[$row['id']] = $row;
    }
    
    // Get all hasil_ujian with detail_jawaban
    $sql = "SELECT detail_jawaban FROM hasil_ujian WHERE id_ujian = ? AND detail_jawaban IS NOT NULL";
    $result = $db->fetchAll( $sql, [$selected_ujian], "i");
    
    foreach ($result as $row) {
        $detail = json_decode($row['detail_jawaban'], true);
        if (!is_array($detail)) continue;
        
        foreach ($detail as $item) {
            $qid = $item['soal_id'];  // Get soal_id FROM JSON
            if (!isset($soal_data[$qid])) continue;  // Skip if soal not found
            
            if (!isset($question_stats[$qid])) {
                $question_stats[$qid] = [
                    'soal_id' => $qid,
                    'pertanyaan' => $soal_data[$qid]['pertanyaan'],
                    'kunci_jawaban' => $soal_data[$qid]['kunci_jawaban'],
                    'correct_count' => 0,
                    'total_answers' => 0,
                    'total_poin' => 0,
                    'correct_poin' => 0,
                    'success_rate' => 0
                ];
            }
            $question_stats[$qid]['total_answers']++;
            $question_stats[$qid]['total_poin'] += $item['poin_diperoleh'] ?? 0;
            
            if ($item['is_correct']) {
                $question_stats[$qid]['correct_count']++;
                $question_stats[$qid]['correct_poin'] += $item['poin_diperoleh'] ?? 0;
            }
        }
    }
    
    // Calculate averages and sort by success rate
    foreach ($question_stats as &$q) {
        $q['avg_poin'] = $q['total_answers'] > 0 ? $q['total_poin'] / $q['total_answers'] : 0;
        $q['success_rate'] = $q['total_answers'] > 0 ? ($q['correct_count'] / $q['total_answers']) : 0;
    }
    
    uasort($question_stats, function($a, $b) {
        return $a['success_rate'] <=> $b['success_rate'];
    });
    
    $analytics['question_analysis'] = array_slice(array_values($question_stats), 0, 20);
    
    // Get completion rate and remedial given count
    $sql = "
        SELECT COUNT(DISTINCT h.nis) as completed, 
               (SELECT COUNT(*) FROM izin_remedi WHERE id_ujian = ?) as remedi_given
        FROM hasil_ujian h
        WHERE h.id_ujian = ?
    ";
    $completion = $db->fetchRow( $sql, [$selected_ujian, $selected_ujian], "ii");
    
    $analytics['completion_rate'] = $stats['total'] > 0 ? round(($completion['completed'] / $stats['total']) * 100, 1) : 0;
}

$ujian_judul = '';
$sql = "SELECT judul_ujian FROM ujian WHERE id = ?";
$ujian_data = $db->fetchRow( $sql, [$selected_ujian], "i");
if ($ujian_data) {
    $ujian_judul = $ujian_data['judul_ujian'];
}

if (isset($_GET['export']) && $_GET['export'] === 'excel' && $selected_ujian > 0) {
    // Clear any output buffering
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=analytics_' . $selected_ujian . '_' . date('Y-m-d') . '.xls');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #4F46E5; color: white; font-weight: bold; padding: 8px; text-align: left; }
            td { border: 1px solid #ddd; padding: 6px; }
            .header { background-color: #4F46E5; color: white; font-weight: bold; }
        </style>
    </head>
    <body>
        <table>
            <tr class="header">
                <th colspan="2">Analytics Report - <?= htmlspecialchars($ujian_judul, ENT_XML1, 'UTF-8') ?></th>
            </tr>
            <tr>
                <td>Generated</td>
                <td><?= date('Y-m-d H:i:s') ?></td>
            </tr>
        </table>
        <br>
        
        <table>
            <tr class="header"><th colspan="2">Summary Statistics</th></tr>
            <tr><th>Metric</th><th>Value</th></tr>
            <tr><td>Total Participants</td><td><?= $analytics['total_peserta'] ?></td></tr>
            <tr><td>Average Score (Original)</td><td><?= $analytics['avg_original'] ?></td></tr>
            <tr><td>Average Score (Final)</td><td><?= $analytics['avg_score'] ?></td></tr>
            <tr><td>Completion Rate (%)</td><td><?= $analytics['completion_rate'] ?></td></tr>
            <tr><td>Needs Remedial</td><td><?= $analytics['needs_remedi'] ?></td></tr>
        </table>
        <br>
        
        <table>
            <tr class="header"><th colspan="2">Grade Distribution</th></tr>
            <tr><th>Grade</th><th>Count</th></tr>
            <?php foreach ($analytics['grade_distribution'] as $grade => $count): ?>
            <tr><td><?= $grade ?></td><td><?= $count ?></td></tr>
            <?php endforeach; ?>
        </table>
        <br>
        
        <table>
            <tr class="header"><th colspan="2">Score Ranges</th></tr>
            <tr><th>Range</th><th>Count</th></tr>
            <?php foreach ($analytics['score_ranges'] as $range => $count): ?>
            <tr><td><?= $range ?></td><td><?= $count ?></td></tr>
            <?php endforeach; ?>
        </table>
        <br>
        
        <table>
            <tr class="header"><th colspan="3">Top Scorers</th></tr>
            <tr><th>Name</th><th>NIS</th><th>Score</th></tr>
            <?php foreach ($analytics['top_scorers'] as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['nama'], ENT_XML1, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['nis'], ENT_XML1, 'UTF-8') ?></td>
                <td><?= $s['total_skor'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <br>
        
        <table>
            <tr class="header"><th colspan="4">Students Needing Remedial (Score < <?= $kkm ?>)</th></tr>
            <tr><th>Name</th><th>NIS</th><th>Class</th><th>Score</th></tr>
            <?php foreach ($analytics['needs_remedi_list'] as $student): ?>
            <tr>
                <td><?= htmlspecialchars($student['nama'], ENT_XML1, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($student['nis'], ENT_XML1, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($student['kelas'], ENT_XML1, 'UTF-8') ?></td>
                <td><?= $student['total_skor'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <br>
        
        <table>
            <tr class="header"><th colspan="7">Question Analysis (Lowest Success Rate)</th></tr>
            <tr>
                <th>Question ID</th><th>Category</th><th>Question</th>
                <th>Correct</th><th>Total</th><th>Success Rate (%)</th><th>Avg Poin</th>
            </tr>
            <?php foreach ($analytics['question_analysis'] as $qa):
                if ($qa['success_rate'] >= 0.7) $kat = 'Mudah';
                elseif ($qa['success_rate'] >= 0.4) $kat = 'Sedang';
                else $kat = 'Sulit';
            ?>
            <tr>
                <td><?= $qa['soal_id'] ?></td>
                <td><?= htmlspecialchars($kat, ENT_XML1, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(substr($qa['pertanyaan'], 0, 100), ENT_XML1, 'UTF-8') ?></td>
                <td><?= $qa['correct_count'] ?></td>
                <td><?= $qa['total_answers'] ?></td>
                <td><?= round($qa['success_rate'] * 100, 1) ?></td>
                <td><?= round($qa['avg_poin'], 1) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?><!DOCTYPE html>
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - <?= htmlspecialchars($sekolah['nama_sekolah']) ?></title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <script src="../vendor/chart.js/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --sidebar-width: 260px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #2c3e50;
            min-height: 100vh;
        }
        
        .sidebar { 
            width: var(--sidebar-width); 
            min-height: 100vh; 
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h5 { color: #fff; font-weight: 600; margin: 0; }
        
        .school-logo {
            width: 55px;
            height: 55px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .sidebar a { 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            padding: 0.875rem 1.5rem; 
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar a.active { background: rgba(79, 70, 229, 0.2); color: #fff; border-left-color: var(--primary); }
        
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; transition: margin-left 0.3s ease; width: calc(100% - var(--sidebar-width)); box-sizing: border-box; min-width: 0; z-index: 1; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        
        .header-actions a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .header-actions a:hover { background: rgba(255,255,255,0.2); }
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .filter-section form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-section select,
        .filter-section input[type="number"] {
            padding: 0.6rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            min-width: 300px;
        }
        
        .filter-section input[type="number"] {
            min-width: 80px;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card.primary .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.success .stat-icon { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); color: white; }
        .stat-card.warning .stat-icon { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: white; }
        .stat-card.danger .stat-icon { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); color: white; }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }
        
        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chart-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .table-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .table-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-primary { background: #e0e7ff; color: #667eea; }
        .badge-success { background: #d1fae5; color: #10b981; }
        .badge-warning { background: #fef3c7; color: #f59e0b; }
        .badge-danger { background: #fee2e2; color: #ef4444; }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .fw-semibold { font-weight: 600; }
        
        .d-flex { display: flex; }
        .align-items-center { align-items: center; }
        .gap-2 { gap: 0.5rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand text-center">
            <div class="school-logo mb-2">
                <?php if ($sekolah['logo'] && file_exists('../uploads/' . $sekolah['logo'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($sekolah['logo']) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill" style="font-size: 1.8rem;"></i>
                <?php endif; ?>
            </div>
            <div class="text-white fw-bold" style="font-size: 0.85rem;"><?= htmlspecialchars($sekolah['nama_sekolah']) ?></div>
            <h5 class="mt-2"><i class="bi bi-graph-up me-1"></i>Analytics</h5>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="bi bi-grid-1x2-fill"></i> Manajemen Ujian</a>
            <a href="tambah_soal.php"><i class="bi bi-question-circle-fill"></i> Bank Soal</a>
            <a href="rekap_nilai.php"><i class="bi bi-bar-chart-fill"></i> Rekap Nilai</a>
            <a href="analytics.php" class="active"><i class="bi bi-graph-up"></i> Analytics</a>
            <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor Ujian</a>
            <a href="profil_sekolah.php"><i class="bi bi-building"></i> Profil Sekolah</a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="manage_users.php"><i class="bi bi-people-fill"></i> Kelola Admin</a>
            <a href="audit_log.php"><i class="bi bi-journal-text"></i> Audit Log</a>
            <?php endif; ?>
            <a href="pengumuman.php"><i class="bi bi-megaphone-fill"></i> Pengumuman</a>
            <a href="izin_remedi.php"><i class="bi bi-arrow-repeat"></i> Izin Remedi</a>
            <a href="ganti_password.php"><i class="bi bi-key-fill"></i> Ganti Password</a>
            <a href="logout.php" class="text-warning mt-3"><i class="bi bi-box-arrow-right"></i> Logout (<?= htmlspecialchars($_SESSION['admin_username']) ?>)</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="bi bi-bar-chart-line me-2"></i>Analytics Dashboard</h1>
            <div class="header-actions">
                <a href="index.php"><i class="bi bi-house"></i> Home</a>
                <a href="rekap_nilai.php"><i class="bi bi-table"></i> Rekap Nilai</a>
                <a href="monitor_ujian.php"><i class="bi bi-display"></i> Monitor</a>
            </div>
        </div>
        
        <div class="filter-section">
            <form method="GET">
                <label for="ujian" style="font-weight: 600;">Pilih Ujian:</label>
                <select name="ujian" id="ujian" onchange="this.form.submit()">
                    <option value="0">-- Pilih Ujian --</option>
                    <?php while ($ujian = $ujian_list->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($ujian['id']) ?>" <?= $selected_ujian == $ujian['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ujian['judul_ujian']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <label for="kkm" style="font-weight: 600; margin-left: 1rem;">KKM (Kriteria Ketuntasan Minimal):</label>
                <input type="number" name="kkm" id="kkm" value="<?= $kkm ?>" min="0" max="100" style="padding: 0.6rem 1rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; width: 80px;">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <?php if ($selected_ujian > 0): ?>
                <a href="?ujian=<?= $selected_ujian ?>&kkm=<?= $kkm ?>&export=excel" class="btn btn-success" style="margin-left: 0.5rem;">
                    <i class="bi bi-download"></i> Export Excel
                </a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($selected_ujian > 0): ?>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= $analytics['total_peserta'] ?></div>
                <div class="stat-label">Total Peserta</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-value"><?= $analytics['avg_original'] ?></div>
                <div class="stat-label">Rata-rata Skor Asli</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon"><i class="bi bi-graph-down"></i></div>
                <div class="stat-value"><?= $analytics['avg_score'] ?></div>
                <div class="stat-label">Rata-rata Skor Akhir</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="stat-value"><?= $analytics['total_violations'] ?></div>
                <div class="stat-label">Total Pelanggaran</div>
            </div>
        </div>
        
        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="bi bi-pie-chart-fill me-2"></i>Distribusi Grade</h3>
                <div class="chart-container">
                    <canvas id="gradeChart"></canvas>
                </div>
                <div class="mt-3 small">
                    <?php 
                    $grade_a_start = $kkm + 17;
                    $grade_b_start = $kkm + 9;
                    $grade_c_start = $kkm;
                    $grade_d_start = $kkm - 15;
                    $grade_e_end = $kkm - 16;
                    ?>
                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <span class="badge bg-success">A (<?= $grade_a_start ?>-100) Sangat Baik</span>
                        <span class="badge bg-primary">B (<?= $grade_b_start ?>-<?= $grade_a_start-1 ?>) Baik</span>
                        <span class="badge bg-warning">C (<?= $grade_c_start ?>-<?= $grade_b_start-1 ?>) Cukup (Tuntas)</span>
                        <span class="badge bg-danger">D (<?= $grade_d_start ?>-<?= $grade_c_start-1 ?>) Perlu Bimbingan</span>
                        <span class="badge bg-dark">E (0-<?= $grade_e_end ?>) Belum Tuntas</span>
                    </div>
                </div>
            </div>
            
            <div class="chart-card">
                <h3><i class="bi bi-bar-chart-fill me-2"></i>Distribusi Skor</h3>
                <div class="chart-container">
                    <canvas id="scoreRangeChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="bi bi-shield-exclamation me-2"></i>Pelanggaran per Jam</h3>
                <div class="chart-container">
                    <canvas id="violationsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3><i class="bi bi-clock-fill me-2"></i>Peak Jam Submit (Puncak: <?= $analytics['peak_hour'] ?>:00)</h3>
                <div class="chart-container">
                    <canvas id="submissionChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3><i class="bi bi-bullseye me-2"></i>Question Difficulty Radar</h3>
                <div class="chart-container">
                    <canvas id="questionRadarChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3><i class="bi bi-trophy-fill me-2"></i>Top 5 Skor Tertinggi</h3>
                <div class="chart-container">
                    <canvas id="topScorersChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="table-section">
            <h3><i class="bi bi-clock-history me-2"></i>Recent Submissions</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NIS</th>
                            <th>Skor</th>
                            <th>Waktu Submit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['recent_submissions'] as $sub): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($sub['nama']) ?></td>
                            <td><?= htmlspecialchars($sub['nis']) ?></td>
                            <td>
                                <span class="badge badge-<?= $sub['total_skor'] >= 70 ? 'success' : ($sub['total_skor'] >= 55 ? 'warning' : 'danger') ?>">
                                    <?= $sub['total_skor'] ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sub['waktu_submit']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="table-section">
            <h3><i class="bi bi-exclamation-circle me-2"></i>Butuh Remedi (Skor < <?= $kkm ?>)</h3>
            <?php if ($analytics['needs_remedi'] > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>NIS</th>
                                <th>Kelas</th>
                                <th>Skor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['needs_remedi_list'] as $siswa): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($siswa['nama']) ?></td>
                                <td><?= htmlspecialchars($siswa['nis']) ?></td>
                                <td><?= htmlspecialchars($siswa['kelas']) ?></td>
                                <td>
                                    <span class="badge badge-danger"><?= $siswa['total_skor'] ?></span>
                                </td>
                                <td>
                                    <?php if (in_array($siswa['nis'], $analytics['remedi_given'])): ?>
                                        <span class="badge badge-success"><i class="bi bi-check-circle"></i> Sudah</span>
                                    <?php else: ?>
                                        <form method="POST" action="rekap_nilai.php?ujian=<?= htmlspecialchars($selected_ujian) ?>" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="id_hasil" value="<?= htmlspecialchars($siswa['id']) ?>">
                                            <input type="hidden" name="id_ujian" value="<?= htmlspecialchars($selected_ujian) ?>">
                                            <button type="submit" name="give_remedi" class="btn btn-sm btn-success">
                                                <i class="bi bi-arrow-repeat"></i> Beri Remedi
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-emoji-smile"></i>
                    <p>Semua siswa sudah mencapai skor minimal!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Question Analysis -->
        <div class="table-section">
            <h3><i class="bi bi-clipboard-data me-2"></i>Analisis Butir Soal (Top 20 Terburuk)</h3>
            <?php if (!empty($analytics['question_analysis'])): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Soal</th>
                                <th>Kategori</th>
                                <th>Pertanyaan</th>
                                <th>Kunci</th>
                                <th>Benar</th>
                                <th>Total</th>
                                <th>% Berhasil</th>
                                <th>Rata-rata Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($analytics['question_analysis'] as $qa): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><span class="badge badge-primary">#<?= $qa['soal_id'] ?></span></td>
                                <td>
                                     <?php
                                     if ($qa['success_rate'] >= 0.7) {
                                         $kat_display = 'Mudah';
                                     } elseif ($qa['success_rate'] >= 0.4) {
                                         $kat_display = 'Sedang';
                                     } else {
                                         $kat_display = 'Sulit';
                                     }
                                     ?>
                                     <span class="badge <?= $kat_display == 'Mudah' ? 'badge-success' : ($kat_display == 'Sedang' ? 'badge-warning' : 'badge-danger') ?>">
                                         <?= $kat_display ?>
                                     </span>
                                 </td>
                                <td class="fw-semibold"><?= htmlspecialchars(substr($qa['pertanyaan'], 0, 80)) ?>...</td>
                                <td><span class="badge badge-success"><?= htmlspecialchars($qa['kunci_jawaban']) ?></span></td>
                                <td>
                                    <span class="badge <?= $qa['correct_count'] > ($qa['total_answers'] / 2) ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $qa['correct_count'] ?>
                                    </span>
                                </td>
                                <td><?= $qa['total_answers'] ?></td>
                                <td>
                                    <?php $percent = round($qa['success_rate'] * 100, 1); ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="background: #e2e8f0; height: 8px; border-radius: 4px; width: 100px; overflow: hidden;">
                                            <div style="background: <?= $percent >= 70 ? '#10b981' : ($percent >= 50 ? '#f59e0b' : '#ef4444') ?>; height: 100%; width: <?= $percent ?>%;"></div>
                                        </div>
                                        <span class="fw-bold" style="color: <?= $percent >= 70 ? '#10b981' : ($percent >= 50 ? '#f59e0b' : '#ef4444') ?>;"><?= $percent ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $qa['avg_poin'] >= 7.5 ? 'badge-success' : ($qa['avg_poin'] >= 5 ? 'badge-warning' : 'badge-danger') ?>">
                                        <?= round($qa['avg_poin'], 1) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-clipboard-x"></i>
                    <p>Belum ada data analisis soal.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Class-wise Performance -->
        <?php if (!empty($analytics['class_stats'])): ?>
        <div class="table-section">
            <h3><i class="bi bi-building me-2"></i>Performa per Kelas</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Total Siswa</th>
                            <th>Rata-rata Skor</th>
                            <th>Nilai Terendah</th>
                            <th>Nilai Tertinggi</th>
                            <th>Pass Rate (≥ <?= $kkm ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['class_stats'] as $cs): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($cs['kelas']) ?></td>
                            <td><?= $cs['total_students'] ?></td>
                            <td>
                                <span class="badge <?= $cs['avg_score'] >= $kkm ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $cs['avg_score'] ?>
                                </span>
                            </td>
                            <td><?= $cs['min_score'] ?></td>
                            <td><?= $cs['max_score'] ?></td>
                            <td>
                                <div class="progress" style="height: 20px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                                    <div style="width: <?= $cs['pass_rate'] ?>%; background: <?= $cs['pass_rate'] >= 70 ? '#10b981' : ($cs['pass_rate'] >= 50 ? '#f59e0b' : '#ef4444') ?>; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 600;">
                                        <?= $cs['pass_rate'] ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-bar-chart"></i>
            <h3>Pilih Ujian untuk Melihat Analytics</h3>
            <p>Silakan pilih ujian dari dropdown di atas untuk melihat data analytics.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($selected_ujian > 0): ?>
    <script>
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    $grade_a = ($kkm+17) . "-100";
                    $grade_b = ($kkm+9) . "-" . ($kkm+16);
                    $grade_c = $kkm . "-" . ($kkm+8);
                    $grade_d = ($kkm-15) . "-" . ($kkm-1);
                    $grade_e = "0-" . ($kkm-16);
                    echo "'$grade_a',\n";
                    echo "'$grade_b',\n";
                    echo "'$grade_c',\n";
                    echo "'$grade_d',\n";
                    echo "'$grade_e'"
                    ?>
                ],
                datasets: [{
                    data: [
                        <?= $analytics['grade_distribution']['A'] ?>,
                        <?= $analytics['grade_distribution']['B'] ?>,
                        <?= $analytics['grade_distribution']['C'] ?>,
                        <?= $analytics['grade_distribution']['D'] ?>,
                        <?= $analytics['grade_distribution']['E'] ?>
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#f97316',
                        '#ef4444'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        const scoreRangeCtx = document.getElementById('scoreRangeChart').getContext('2d');
        new Chart(scoreRangeCtx, {
            type: 'bar',
            data: {
                labels: ['0-20', '21-40', '41-60', '61-80', '81-100'],
                datasets: [{
                    label: 'Jumlah Siswa',
                    data: [
                        <?= $analytics['score_ranges']['0-20'] ?>,
                        <?= $analytics['score_ranges']['21-40'] ?>,
                        <?= $analytics['score_ranges']['41-60'] ?>,
                        <?= $analytics['score_ranges']['61-80'] ?>,
                        <?= $analytics['score_ranges']['81-100'] ?>
                    ],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        const violationsCtx = document.getElementById('violationsChart').getContext('2d');
        new Chart(violationsCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Jumlah Pelanggaran',
                    data: [<?= implode(', ', $analytics['violations_by_hour']) ?>],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
        
        // Peak submission hours chart
        const submissionCtx = document.getElementById('submissionChart').getContext('2d');
        new Chart(submissionCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Jumlah Submit',
                    data: [<?= implode(', ', $analytics['submission_by_hour']) ?>],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        // Question Difficulty Radar Chart (Top 10 worst questions)
        const qLabels = [];
        const qSuccessRate = [];
        const qAvgPoin = [];
        <?php 
        $qCount = 0;
        foreach($analytics['question_analysis'] as $qa) {
            if ($qCount >= 10) break;
            echo "qLabels.push('Q" . $qa['soal_id'] . "');\n";
            echo "qSuccessRate.push(" . round($qa['success_rate'] * 100, 1) . ");\n";
            echo "qAvgPoin.push(" . round($qa['avg_poin'], 1) . ");\n";
            $qCount++;
        }
        ?>
        
        const radarCtx = document.getElementById('questionRadarChart').getContext('2d');
        new Chart(radarCtx, {
            type: 'radar',
            data: {
                labels: qLabels,
                datasets: [{
                    label: 'Success Rate (%)',
                    data: qSuccessRate,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderWidth: 2
                }, {
                    label: 'Avg Poin',
                    data: qAvgPoin,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });
        
        const topScorersCtx = document.getElementById('topScorersChart').getContext('2d');
        new Chart(topScorersCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach($analytics['top_scorers'] as $s) echo "'" . htmlspecialchars($s['nama']) . "',"; ?>],
                datasets: [{
                    label: 'Skor',
                    data: [<?php foreach($analytics['top_scorers'] as $s) echo $s['total_skor'] . ","; ?>],
                    backgroundColor: [
                        '#FFD700',
                        '#C0C0C0',
                        '#CD7F32',
                        '#3b82f6',
                        '#10b981'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
