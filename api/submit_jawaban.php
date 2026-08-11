<?php
// api/submit_jawaban.php - AJAX API untuk submit jawaban ujian

require_once '../config/security_headers.php';

session_start();

// Log errors instead of suppressing them entirely
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Register error log handler — sends all errors to our logger
set_error_handler(function($severity, $message, $file, $line) {
    $response['success'] = false;
    $response['message'] = 'Server error. Silakan coba lagi.';
    logError("PHP Error [$severity]: $message", ['file' => $file, 'line' => $line]);
    echo json_encode($response);
    exit;
});

// Register fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $response['success'] = false;
        $response['message'] = 'Server error. Silakan coba lagi.';
        logError("Fatal Error [{$error['type']}]: {$error['message']}", ['file' => $error['file'], 'line' => $error['line']]);
        echo json_encode($response);
        exit;
    }
});

header('Content-Type: application/json');

// CORS: Hanya izinkan origin yang sama (same-origin policy)
$allowedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';
require_once '../config/log_helper.php';

function validateUniqueAttempt($conn, $id_ujian, $nis) {
    $stmt = $conn->prepare("SELECT id FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
    $stmt->bind_param("is", $id_ujian, $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_completed = $result->num_rows > 0;
    $stmt->close();
    
    if ($has_completed) {
        $stmt2 = $conn->prepare("SELECT id FROM izin_remedi WHERE id_ujian = ? AND nis = ? LIMIT 1");
        $stmt2->bind_param("is", $id_ujian, $nis);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $has_remedi_permission = $result2->num_rows > 0;
        $stmt2->close();
        
        return $has_remedi_permission;
    }
    
    return true;
}

function validateTemporaryUnique($conn, $id_ujian, $nis) {
    $stmt = $conn->prepare("SELECT id FROM jawaban_sEMENTARA WHERE id_ujian = ? AND nis = ? LIMIT 1");
    $stmt->bind_param("is", $id_ujian, $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_temporary = $result->num_rows > 0;
    $stmt->close();
    
    if ($has_temporary) {
        return true;
    }
    
    $stmt = $conn->prepare("SELECT id FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
    $stmt->bind_param("is", $id_ujian, $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_completed = $result->num_rows > 0;
    $stmt->close();
    
    if ($has_completed) {
        $stmt = $conn->prepare("SELECT id FROM izin_remedi WHERE id_ujian = ? AND nis = ? LIMIT 1");
        $stmt->bind_param("is", $id_ujian, $nis);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_remedi_permission = $result->num_rows > 0;
        $stmt->close();
        
        return $has_remedi_permission;
    }
    
    return true;
}

function verifyCSRF($token, $expected) {
    if (empty($token) || empty($expected)) return false;
    return hash_equals($expected, $token);
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request');
    }

    $action = $input['action'];
    
    // CSRF validation - skip for generate_token and check_exam_code
    $exemptActions = ['generate_token', 'check_exam_code', 'check_ip'];
    if (!in_array($action, $exemptActions)) {
        $csrfToken = $input['csrf_token'] ?? '';
        $expectedToken = $input['expected_token'] ?? '';
        
        if (empty($csrfToken) || empty($expectedToken)) {
            logSecurity('CSRF token missing in API request', ['action' => $action]);
            throw new Exception('CSRF token missing');
        }
        
        if (!hash_equals($expectedToken, $csrfToken)) {
            logSecurity('CSRF token mismatch in API request', ['action' => $action]);
            throw new Exception('Invalid CSRF token');
        }
    }

    switch ($action) {
        case 'generate_token':
            $newToken = bin2hex(random_bytes(32));
            $response['success'] = true;
            $response['csrf_token'] = $newToken;
            break;
            
        case 'check_completion':
            $response = handleCheckCompletion($conn, $input);
            break;
            
        case 'auto_save':
            $response = handleAutoSave($conn, $input);
            break;
            
        case 'submit_final':
            $response = handleSubmitFinal($conn, $input);
            break;
            
        case 'check_session':
            $response = handleCheckSession($conn, $input);
            break;
            
        case 'get_saved':
            $response = handleGetSaved($conn, $input);
            break;
            
        case 'log_violation':
            $response = handleLogViolation($conn, $input);
            break;
            
        case 'get_violations':
            $response = handleGetViolations($conn, $input);
            break;
            
        case 'dismiss_violation':
            $response = handleDismissViolation($conn, $input);
            break;
            
        case 'get_violation_counts':
            $response = handleGetViolationCounts($conn, $input);
            break;
            
        case 'check_exam_code':
            $response = handleCheckExamCode($conn, $input);
            break;
            
        case 'check_ip':
            $response = handleCheckIP($conn, $input);
            break;
            
        default:
            throw new Exception('Unknown action');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
    logError('API Exception: ' . $e->getMessage(), ['action' => $action ?? 'none', 'input' => array_keys($input ?? [])]);
}

echo json_encode($response);
$conn->close();

function handleCheckCompletion($conn, $input) {
    global $db;
    $response = ['success' => true, 'completed' => false, 'has_saved' => false, 'saved_data' => null];
    
    if (!isset($input['id_ujian']) || !isset($input['nis'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = trim($input['nis']);
    
    if (empty($nis)) {
        throw new Exception('NIS is required');
    }
    
    $stmt = $conn->prepare("SELECT id, total_skor, waktu_submit FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
    $stmt->bind_param("is", $id_ujian, $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Cek apakah siswa memiliki izin remedial
        $hasRemedi = false;
        $stmtRemedi = $conn->prepare("SELECT id FROM izin_remedi WHERE id_ujian = ? AND nis = ? LIMIT 1");
        $stmtRemedi->bind_param("is", $id_ujian, $nis);
        $stmtRemedi->execute();
        $resultRemedi = $stmtRemedi->get_result();
        $hasRemedi = $resultRemedi->num_rows > 0;
        $stmtRemedi->close();
        
        if ($hasRemedi) {
            // Siswa remedial: jangan set completed=true, biarkan masuk ujian
            $response['completed'] = false;
            $response['message'] = 'Anda memiliki izin remedial. Silakan kerjakan ulang.';
        } else {
            $response['completed'] = true;
            $response['message'] = 'Anda sudah mengerjakan ujian ini';
            $response['result'] = [
                'skor' => $row['total_skor'],
                'tanggal' => $row['waktu_submit']
            ];
            
            // Check if student can retake (has saved answers in jawaban_sementara)
            if ($db->tableExists('jawaban_sementara')) {
                $stmt2 = $conn->prepare("SELECT answers, nama, kelas FROM jawaban_sementara WHERE id_ujian = ? AND nis = ? LIMIT 1");
                $stmt2->bind_param("is", $id_ujian, $nis);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($row2 = $result2->fetch_assoc()) {
                    $response['can_retake'] = true;
                    $response['message'] = 'Anda dapat mengerjakan ulang dengan jawaban tersimpan';
                }
                $stmt2->close();
            }
        }
    }
    $stmt->close();
    
    if (!$response['completed']) {
        if ($db->tableExists('jawaban_sementara')) {
            $stmt = $conn->prepare("SELECT answers, nama, kelas, updated_at FROM jawaban_sementara WHERE id_ujian = ? AND nis = ?");
            $stmt->bind_param("is", $id_ujian, $nis);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $response['has_saved'] = true;
                $response['saved_data'] = [
                    'answers' => json_decode($row['answers'], true) ?: [],
                    'nama' => $row['nama'],
                    'kelas' => $row['kelas'],
                    'last_update' => $row['updated_at']
                ];
            }
            $stmt->close();
        }
    }
    
    return $response;
}

function handleDismissViolation($conn, $input) {
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($input['violation_id'])) {
        throw new Exception('Missing violation_id');
    }
    
    // Verify admin session
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }
    
    $violation_id = (int)$input['violation_id'];
    $admin_id = (int)$_SESSION['admin_id'];
    
    $stmt = $conn->prepare("UPDATE exam_violations SET status = 'dismissed', dismissed_by = ?, dismissed_at = NOW() WHERE id = ? AND (status IS NULL OR status = 'active')");
    $stmt->bind_param("ii", $admin_id, $violation_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        $response['success'] = true;
        $response['message'] = 'Violasi berhasil dibatalkan';
        logSecurity('Violation dismissed by admin', [
            'violation_id' => $violation_id,
            'admin_id' => $admin_id,
        ]);
    } else {
        $response['message'] = 'Violasi tidak ditemukan atau sudah dibatalkan';
    }
    
    return $response;
}

function handleGetViolationCounts($conn, $input) {
    $response = ['success' => true, 'counts' => []];
    
    if (!isset($input['id_ujian'])) {
        throw new Exception('Missing id_ujian');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    
    // Get counts per NIS for active violations
    $sql = "
        SELECT nis, COUNT(*) as total
        FROM exam_violations
        WHERE id_ujian = ? AND (status IS NULL OR status = 'active')
        GROUP BY nis
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_ujian);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $response['counts'][] = $row;
    }
    $stmt->close();
    
    return $response;
}

function handleAutoSave($conn, $input) {
    global $db;
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($input['id_ujian']) || !isset($input['nis']) || !isset($input['answers'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = trim($input['nis']);
    $answers = $input['answers'];
    $nama = isset($input['nama']) ? trim($input['nama']) : null;
    $kelas = isset($input['kelas']) ? trim($input['kelas']) : null;
    
    if (empty($nis)) {
        throw new Exception('NIS is required');
    }
    
    if (!validateUniqueAttempt($conn, $id_ujian, $nis)) {
        throw new Exception('Anda sudah menyelesaikan ujian ini. Tidak dapat mengubah jawaban.');
    }
    
    $answersJson = json_encode($answers);
    $namaValue = $nama ?? '';
    $kelasValue = $kelas ?? '';
    
    $sql = "INSERT INTO jawaban_sementara (id_ujian, nis, nama, kelas, answers) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nama = VALUES(nama), kelas = VALUES(kelas), answers = VALUES(answers), updated_at = NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $id_ujian, $nis, $namaValue, $kelasValue, $answersJson);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Jawaban tersimpan';
        $response['saved_count'] = is_array($answers) ? count($answers) : 0;
    } else {
        if (!$db->tableExists('jawaban_sementara')) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS `jawaban_sementara` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `id_ujian` int NOT NULL,
                    `nis` varchar(50) NOT NULL,
                    `nama` varchar(100) DEFAULT NULL,
                    `kelas` varchar(50) DEFAULT NULL,
                    `answers` json DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_ujian_nis` (`id_ujian`, `nis`),
                    INDEX `idx_nis` (`nis`),
                    INDEX `idx_ujian` (`id_ujian`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
            ");
            // Re-prepare and execute after table creation
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $id_ujian, $nis, $namaValue, $kelasValue, $answersJson);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Jawaban tersimpan';
                $response['saved_count'] = is_array($answers) ? count($answers) : 0;
            } else {
                throw new Exception('Failed to save after table creation: ' . $stmt->error);
            }
        } else {
            throw new Exception('Failed to save: ' . $stmt->error);
        }
    }
    $stmt->close();
    
    return $response;
}

function handleSubmitFinal($conn, $input) {
    global $db;
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($input['id_ujian']) || !isset($input['nis']) || 
        !isset($input['nama']) || !isset($input['kelas']) || !isset($input['answers'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = trim($input['nis']);
    $nama = trim($input['nama']);
    $kelas = trim($input['kelas']);
    $answers = $input['answers'];
    
    if (empty($nis) || empty($nama) || empty($kelas)) {
        throw new Exception('Identitas tidak lengkap');
    }
    
    // === RACE CONDITION PROTECTION: Advisory Lock + Transaction ===
    $lockName = "submit_lock_{$id_ujian}_{$nis}";
    $lockResult = $conn->query("SELECT GET_LOCK('$lockName', 15) AS lock_result");
    $lockRow = $lockResult->fetch_assoc();
    if (!$lockRow || $lockRow['lock_result'] != 1) {
        throw new Exception('Gagal mendapatkan kunci submit. Silakan coba lagi.');
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // === DUPLICATE CHECK WITH ROW LOCK (FOR UPDATE) ===
        $checkStmt = $conn->prepare("SELECT id FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1 FOR UPDATE");
        $checkStmt->bind_param("is", $id_ujian, $nis);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            // Cek apakah siswa memiliki izin remedial
            $stmtRemedi = $conn->prepare("SELECT id FROM izin_remedi WHERE id_ujian = ? AND nis = ? LIMIT 1");
            $stmtRemedi->bind_param("is", $id_ujian, $nis);
            $stmtRemedi->execute();
            $resultRemedi = $stmtRemedi->get_result();
            $hasRemedi = $resultRemedi->num_rows > 0;
            $stmtRemedi->close();
            
            if ($hasRemedi) {
                // Hapus hasil lama agar bisa diganti dengan yang baru
                $deleteStmt = $conn->prepare("DELETE FROM hasil_ujian WHERE id_ujian = ? AND nis = ?");
                $deleteStmt->bind_param("is", $id_ujian, $nis);
                $deleteStmt->execute();
                $deleteStmt->close();
            } else {
                $checkStmt->close();
                $conn->rollback();
                throw new Exception('Anda sudah menyelesaikan ujian ini. Tidak dapat mengubah jawaban.');
            }
        }
        $checkStmt->close();
        
        $stmt = $conn->prepare("SELECT * FROM soal WHERE id_ujian = ?");
        $stmt->bind_param("i", $id_ujian);
        $stmt->execute();
        $result = $stmt->get_result();
        $soal_list = [];
        while ($row = $result->fetch_assoc()) {
            $soal_list[$row['id']] = $row;
        }
        $stmt->close();
        
        if (empty($soal_list)) {
            $conn->rollback();
            throw new Exception('Soal tidak ditemukan');
        }
        
        logInfo('Exam submission received', ['total_questions' => count($soal_list)]);
        
        $total_skor = 0;
        $detail_jawaban = [];
        
        foreach ($soal_list as $soal_id => $soal) {
            $jawaban = isset($answers[(string)$soal_id]) ? $answers[(string)$soal_id] : (isset($answers[$soal_id]) ? $answers[$soal_id] : '');
            $is_correct = (strtolower($jawaban) === strtolower($soal['kunci_jawaban']));
            
            logInfo('Answer processed', ['soal_id' => $soal_id, 'is_correct' => $is_correct]);
            
            if ($is_correct) {
                $total_skor += $soal['poin'];
            }
            
            $detail_jawaban[] = [
                'soal_id' => $soal_id,
                'pertanyaan' => $soal['pertanyaan'],
                'jawaban_siswa' => $jawaban,
                'kunci_jawaban' => $soal['kunci_jawaban'],
                'is_correct' => $is_correct,
                'poin' => $soal['poin'],
                'poin_diperoleh' => $is_correct ? $soal['poin'] : 0,
                'opsi_a' => $soal['opsi_a'],
                'opsi_b' => $soal['opsi_b'],
                'opsi_c' => $soal['opsi_c'],
                'opsi_d' => $soal['opsi_d'],
                'opsi_e' => $soal['opsi_e']
            ];
        }
        
        // Count violations and apply penalty
        $violation_count = 0;
        $penalty = 0;
        $skor_awal = $total_skor; // Save original score before penalty
        $violation_table = $db->tableExists('exam_violations');
        if ($violation_table) {
            $stmt_v = $conn->prepare("SELECT COUNT(*) as total FROM exam_violations WHERE id_ujian = ? AND nis = ?");
            $stmt_v->bind_param("is", $id_ujian, $nis);
            $stmt_v->execute();
            $result_v = $stmt_v->get_result();
            if ($row_v = $result_v->fetch_assoc()) {
                $violation_count = (int)$row_v['total'];
            }
            $stmt_v->close();
            
            // Apply penalty: 10 points per violation, max 50% of total score
            if ($violation_count > 0) {
                $penalty = min($violation_count * 10, $total_skor * 0.5);
                $total_skor = max(0, $total_skor - $penalty);
            }
        }
        
        $detail_jawaban_json = json_encode($detail_jawaban);
        
        // Check if skor_awal column exists
        if (!$db->columnExists('hasil_ujian', 'skor_awal')) {
            $conn->query("ALTER TABLE hasil_ujian ADD COLUMN skor_awal INT DEFAULT NULL AFTER total_skor");
        }
        
        if ($db->columnExists('hasil_ujian', 'detail_jawaban')) {
            $stmt = $conn->prepare("INSERT INTO hasil_ujian (id_ujian, nis, nama, kelas, total_skor, skor_awal, detail_jawaban) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiis", $id_ujian, $nis, $nama, $kelas, $total_skor, $skor_awal, $detail_jawaban_json);
        } else {
            $stmt = $conn->prepare("INSERT INTO hasil_ujian (id_ujian, nis, nama, kelas, total_skor, skor_awal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssii", $id_ujian, $nis, $nama, $kelas, $total_skor, $skor_awal);
        }
        
        if ($stmt->execute()) {
            $insert_id = $stmt->insert_id;
            
            $conn->query("DELETE FROM jawaban_sementara WHERE id_ujian = $id_ujian AND nis = '$nis'");
            
            // Commit transaction
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Jawaban berhasil disubmit';
            $response['skor'] = $total_skor;
            $response['skor_awal'] = $skor_awal; // Original score before penalty
            $response['penalty'] = $penalty;
            $response['violation_count'] = $violation_count;
            $response['total_soal'] = count($soal_list);
            $response['jawaban_benar'] = count(array_filter($detail_jawaban, fn($d) => $d['is_correct']));
        } else {
            $conn->rollback();
            throw new Exception('Gagal menyimpan jawaban: ' . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        // Rollback if transaction is still active
        try { $conn->rollback(); } catch (Exception $ignored) {}
        throw $e;
    } finally {
        // Release advisory lock
        $conn->query("DO RELEASE_LOCK('$lockName')");
    }
    
    return $response;
}

function handleCheckSession($conn, $input) {
    $response = ['success' => true, 'exists' => false];
    
    if (!isset($input['id_ujian']) || !isset($input['nis'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = $conn->real_escape_string($input['nis']);
    
    $stmt = $conn->prepare("SELECT id, nis, nama, kelas FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
    $stmt->bind_param("is", $id_ujian, $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['exists'] = true;
        $response['message'] = 'Anda sudah mengerjakan ujian ini';
    }
    $stmt->close();
    
    return $response;
}

function handleGetSaved($conn, $input) {
    global $db;
    $response = ['success' => true, 'answers' => []];
    
    if (!isset($input['id_ujian']) || !isset($input['nis'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = $conn->real_escape_string($input['nis']);
    
    global $db;
    if (!$db->tableExists('jawaban_sementara')) {
        return $response;
    }
    
    $stmt = $conn->prepare("SELECT answers, nama, kelas FROM jawaban_sementara WHERE id_ujian = ? AND nis = ?");
    $stmt->bind_param("is", $id_ujian, $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response['answers'] = json_decode($row['answers'], true) ?: [];
        $response['nama'] = $row['nama'];
        $response['kelas'] = $row['kelas'];
    }
    $stmt->close();
    
    return $response;
}

function handleLogViolation($conn, $input) {
    $response = ['success' => true, 'message' => ''];
    
    if (!isset($input['id_ujian']) || !isset($input['nis']) || !isset($input['jenis'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = trim($input['nis']);
    $jenis = trim($input['jenis']);
    $detail = isset($input['detail']) ? trim($input['detail']) : '';
    $device = isset($input['device_fingerprint']) ? trim($input['device_fingerprint']) : '';
    $ip = isset($input['ip_address']) ? trim($input['ip_address']) : '';
    
    $conn->query("
        CREATE TABLE IF NOT EXISTS `exam_violations` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_ujian` INT NOT NULL,
            `nis` VARCHAR(50) NOT NULL,
            `jenis_violation` VARCHAR(50) NOT NULL,
            `detail` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_ujian_nis` (`id_ujian`, `nis`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ");
    
    // MySQL 8 tidak mendukung ADD COLUMN IF NOT EXISTS — cek manual sebelum ALTER
    $colStatus = $conn->query("SHOW COLUMNS FROM exam_violations LIKE 'status'");
    if ($colStatus && $colStatus->num_rows === 0) {
        $conn->query("ALTER TABLE exam_violations ADD COLUMN status ENUM('active','dismissed') DEFAULT 'active' AFTER detail");
    }
    $colDismissedBy = $conn->query("SHOW COLUMNS FROM exam_violations LIKE 'dismissed_by'");
    if ($colDismissedBy && $colDismissedBy->num_rows === 0) {
        $conn->query("ALTER TABLE exam_violations ADD COLUMN dismissed_by INT NULL AFTER status");
    }
    $colDismissedAt = $conn->query("SHOW COLUMNS FROM exam_violations LIKE 'dismissed_at'");
    if ($colDismissedAt && $colDismissedAt->num_rows === 0) {
        $conn->query("ALTER TABLE exam_violations ADD COLUMN dismissed_at TIMESTAMP NULL AFTER dismissed_by");
    }
    
    $stmt = $conn->prepare("INSERT INTO exam_violations (id_ujian, nis, jenis_violation, detail) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $id_ujian, $nis, $jenis, $detail);
    $stmt->execute();
    $stmt->close();

    logSecurity('Exam violation recorded', [
        'aksi' => $jenis,
        'id_ujian' => $id_ujian,
        'nis' => $nis,
        'detail' => $detail,
        'device_fingerprint' => $device,
        'client_ip' => $ip,
    ]);
    
    $result = $conn->query("SELECT COUNT(*) as total FROM exam_violations WHERE id_ujian = $id_ujian AND nis = '$nis'");
    $row = $result->fetch_assoc();
    $response['violation_count'] = (int)$row['total'];
    
    return $response;
}

function handleGetViolations($conn, $input) {
    $response = ['success' => true, 'violations' => [], 'total' => 0];
    
    if (!isset($input['id_ujian']) || !isset($input['nis'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $nis = $conn->real_escape_string($input['nis']);
    
    $result = $conn->query("SELECT * FROM exam_violations WHERE id_ujian = $id_ujian AND nis = '$nis' AND (status IS NULL OR status = 'active') ORDER BY created_at DESC LIMIT 50");
    while ($row = $result->fetch_assoc()) {
        $response['violations'][] = $row;
    }
    $response['total'] = count($response['violations']);
    
    return $response;
}

function handleCheckExamCode($conn, $input) {
    $response = ['success' => true, 'valid' => false, 'message' => ''];
    
    if (!isset($input['id_ujian']) || !isset($input['kode_ujian'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $kode = trim($input['kode_ujian']);
    
    try {
        $stmt = $conn->prepare("SELECT kode_ujian FROM ujian WHERE id = ?");
        $stmt->bind_param("i", $id_ujian);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $kodeDb = $row['kode_ujian'] ?? '';
            
            if (empty($kodeDb)) {
                $response['valid'] = true;
                $_SESSION['exam_code_verified'] = true;
            } elseif (strcasecmp($kodeDb, $kode) === 0) {
                $response['valid'] = true;
                $_SESSION['exam_code_verified'] = true;
            } else {
                $response['message'] = 'Kode ujian salah';
            }
        } else {
            $response['message'] = 'Ujian tidak ditemukan';
        }
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    return $response;
}

function handleCheckIP($conn, $input) {
    $response = ['success' => true, 'allowed' => true, 'message' => ''];
    
    if (!isset($input['id_ujian'])) {
        throw new Exception('Missing required fields');
    }
    
    $id_ujian = (int)$input['id_ujian'];
    $ip = isset($input['ip_address']) ? $input['ip_address'] : '';
    
    $stmt = $conn->prepare("SELECT allow_ip FROM ujian WHERE id = ?");
    $stmt->bind_param("i", $id_ujian);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc() && !empty($row['allow_ip'])) {
        $allowedIPs = json_decode($row['allow_ip'], true);
        if (is_array($allowedIPs) && count($allowedIPs) > 0) {
            $response['allowed'] = in_array($ip, $allowedIPs);
            if (!$response['allowed']) {
                $response['message'] = 'IP Anda tidak diizinkan untuk mengakses ujian ini';
            }
        }
    }
    $stmt->close();
    
    return $response;
}
