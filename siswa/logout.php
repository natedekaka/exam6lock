<?php
session_start();

require_once '../config/security_headers.php';

if (isset($_COOKIE['student_remember'])) {
    require_once '../config/database.php';
    $token = $_COOKIE['student_remember'];
    $stmt = $conn->prepare("UPDATE siswa SET remember_token = NULL WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    setcookie('student_remember', '', time() - 3600, '/');
}

session_destroy();
header('Location: ../index.php');
exit;
