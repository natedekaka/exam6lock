<?php
/**
 * Error & Security Logging Helper
 * Logs to both file (always, as fallback) and database (when available).
 */

/**
 * Get the database connection (global $conn) if available.
 * @return mysqli|null
 */
function getLogDb() {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        return $conn;
    }
    return null;
}

/**
 * Check if the log_entries table exists in the database.
 * @param mysqli $conn
 * @return bool
 */
function logTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'log_entries'");
    return $result && $result->num_rows > 0;
}

/**
 * Insert a log entry into the database.
 *
 * @param string $level      (error|info|warning|security)
 * @param string $message
 * @param array  $context
 * @return bool
 */
function logToDb($level, $message, $context = []) {
    $conn = getLogDb();
    if (!$conn || !logTableExists($conn)) {
        return false;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $uri = substr($_SERVER['REQUEST_URI'] ?? 'cli', 0, 500);
    $contextJson = !empty($context) ? json_encode($context) : null;

    // Try to get user info from session
    $userId = null;
    $userType = null;
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (isset($_SESSION['admin_id'])) {
            $userId = $_SESSION['admin_id'];
            $userType = 'admin';
        } elseif (isset($_SESSION['siswa_id'])) {
            $userId = $_SESSION['siswa_id'];
            $userType = 'siswa';
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO log_entries (level, message, context, ip_address, user_agent, request_uri, user_id, user_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssssis",
        $level,
        $message,
        $contextJson,
        $ip,
        $userAgent,
        $uri,
        $userId,
        $userType
    );
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Log an error-level message.
 *
 * @param string $message
 * @param array  $context
 * @return void
 */
function logError($message, $context = []) {
    _writeLog('error', $message, $context);
}

/**
 * Log an info-level message.
 *
 * @param string $message
 * @param array  $context
 * @return void
 */
function logInfo($message, $context = []) {
    _writeLog('info', $message, $context);
}

/**
 * Log a security alert.
 *
 * @param string $message
 * @param array  $context
 * @return void
 */
function logSecurity($message, $context = []) {
    _writeLog('security', $message, $context);
}

/**
 * Log a warning-level message.
 *
 * @param string $message
 * @param array  $context
 * @return void
 */
function logWarning($message, $context = []) {
    _writeLog('warning', $message, $context);
}

/**
 * Internal: write a log entry to both file and database.
 *
 * @param string $level
 * @param string $message
 * @param array  $context
 * @return void
 */
function _writeLog($level, $message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $uri = $_SERVER['REQUEST_URI'] ?? 'cli';
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';

    // Always write to file as fallback
    $logFile = sys_get_temp_dir() . '/exam6lock_' . $level . '.log';
    $logEntry = "[{$timestamp}] " . strtoupper($level) . " ({$ip}) {$uri} - {$message}{$contextStr}" . PHP_EOL;
    error_log($logEntry, 3, $logFile);

    // Also try to write to database (if available)
    logToDb($level, $message, $context);
}

/**
 * Read log entries from the database.
 *
 * @param int    $limit
 * @param string $level   Filter by level (error|info|warning|security), or null for all
 * @param string $search  Optional search term in message
 * @return array|null
 */
function readLogs($limit = 50, $level = null, $search = null) {
    $conn = getLogDb();
    if (!$conn || !logTableExists($conn)) {
        return null;
    }

    $where = [];
    $params = [];
    $types = '';

    if ($level !== null) {
        $where[] = "level = ?";
        $params[] = $level;
        $types .= 's';
    }

    if ($search !== null && !empty($search)) {
        $where[] = "message LIKE ?";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM log_entries {$whereClause} ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $logs;
}
