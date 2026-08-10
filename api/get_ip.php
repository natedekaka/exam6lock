<?php
require_once '../config/security_headers.php';
require_once '../config/log_helper.php';
header('Content-Type: application/json');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

$rateKey = 'get_ip_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateFile = sys_get_temp_dir() . '/' . $rateKey . '.json';
$rateData = ['count' => 0, 'window' => time()];
if (file_exists($rateFile)) {
    $rateData = json_decode(file_get_contents($rateFile), true);
    if ($rateData['window'] < time() - 60) {
        $rateData = ['count' => 0, 'window' => time()];
    }
}
if ($rateData['count'] >= 30) {
    logSecurity('Rate limited on get_ip.php', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(429);
    echo json_encode(['ip' => $ip, 'rate_limited' => true]);
    exit;
}
$rateData['count']++;
file_put_contents($rateFile, json_encode($rateData));

echo json_encode(['ip' => $ip]);
