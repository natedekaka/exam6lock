<?php
require_once '../config/security_headers.php';
require_once '../config/database.php';
require_once '../config/log_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$rateKey = 'client_error_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateFile = sys_get_temp_dir() . '/' . $rateKey . '.json';
$rateData = ['count' => 0, 'window' => time()];
if (file_exists($rateFile)) {
    $rateData = json_decode(file_get_contents($rateFile), true);
    if ($rateData['window'] < time() - 60) {
        $rateData = ['count' => 0, 'window' => time()];
    }
}
if ($rateData['count'] >= 30) {
    logSecurity('Rate limited client error reports', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limited']);
    exit;
}
$rateData['count']++;
file_put_contents($rateFile, json_encode($rateData));

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['error'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$level = in_array($input['level'] ?? 'error', ['error', 'warning', 'info']) ? $input['level'] : 'error';
$message = substr(trim($input['error']), 0, 1000);

$context = [
    'url' => $input['url'] ?? '',
    'line' => $input['line'] ?? null,
    'column' => $input['column'] ?? null,
    'stack' => isset($input['stack']) ? substr($input['stack'], 0, 2000) : null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'nis' => $_SESSION['siswa_nis'] ?? null,
    'admin' => $_SESSION['admin_id'] ?? null,
];

if ($level === 'security') {
    logSecurity('Client-side security event: ' . $message, $context);
} else {
    logError('Client-side error: ' . $message, $context);
}

http_response_code(200);
echo json_encode(['success' => true]);
