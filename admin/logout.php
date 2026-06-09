<?php
// admin/logout.php - Logout Admin

session_start();

// Clear remember me token
if (isset($_COOKIE['admin_remember'])) {
    require_once '../config/database.php';
    $token = $_COOKIE['admin_remember'];
    $stmt = $conn->prepare("UPDATE admin_users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    setcookie('admin_remember', '', time() - 3600, '/');
}

session_destroy();
header('Location: login.php');
exit;
