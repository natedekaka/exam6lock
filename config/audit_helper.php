<?php
function logAudit($conn, $admin_id, $admin_username, $aksi, $entitas, $entitas_id = null, $detail = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO audit_log (admin_id, admin_username, aksi, entitas, entitas_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiss", $admin_id, $admin_username, $aksi, $entitas, $entitas_id, $detail, $ip);
    $stmt->execute();
    $stmt->close();
}
