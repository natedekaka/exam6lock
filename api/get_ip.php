<?php
require_once '../config/security_headers.php';
header('Content-Type: application/json');
require_once '../config/log_helper.php';

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

$rateKey = 'get_ip_' . $_SERVER['REMOTE_ADDR'];
$rateWindow = 60;
$rateMaxAttempts = 30;

if (class_exists('RedisHelper')) {
    $redis = new RedisHelper();
    $redis->init();
    if ($redis->isconnected()) {
        $count = (int)$redis->get($rateKey);
        if ($count >= $rateMaxAttempts) {
            logSecurity('Rate limited on get_ip.php', ['ip' => $_SERVER['REMOTE_ADDR']]);
            http_response_code(429);
            echo json_encode(['ip' => $ip, 'rate_limited' => true]);
            exit;
        }
        $redis->incr($rateKey);
        $redis->expire($rateKey, $rateWindow);
    }
}

echo json_encode(['ip' => $ip]);
