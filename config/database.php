<?php
// koneksi.php - Koneksi Database MySQL

date_default_timezone_set('Asia/Jakarta');

$host = getenv('DB_HOST') ?: 'db:3306';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: 'rootpass';
$database = getenv('DB_NAME') ?: 'ujian_online';
$port = getenv('DB_PORT') ?: '3306';

require_once __DIR__ . '/log_helper.php';

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    logError('Database connection failed: ' . $conn->connect_error, ['host' => $host, 'database' => $database]);
    die("Koneksi gagal. Silakan hubungi administrator.");
}

$conn->query("SET time_zone = '+07:00'");

$conn->set_charset("utf8mb4");

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

require_once __DIR__ . '/db_helper.php';
$db = new DBHelper($conn);

require_once __DIR__ . '/redis_helper.php';
$redis = new RedisHelper();
?>
