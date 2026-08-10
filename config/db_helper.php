<?php
// db_helper.php - Database Helper with Transaction & Locking Support

class DBHelper {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    public function commit() {
        $this->conn->commit();
    }
    
    public function rollback() {
        $this->conn->rollback();
    }
    
    public function lockTable($table, $mode = 'WRITE') {
        $this->conn->query("LOCK TABLES $table $mode");
    }
    
    public function unlockTables() {
        $this->conn->query("UNLOCK TABLES");
    }
    
    public function getLock($lockName, $timeout = 10) {
        $result = $this->conn->query("SELECT GET_LOCK('$lockName', $timeout) AS lock_result");
        $row = $result->fetch_assoc();
        return $row['lock_result'] ?? 0;
    }
    
    public function releaseLock($lockName) {
        $this->conn->query("DO RELEASE_LOCK('$lockName')");
    }
    
    public function checkDuplicateSubmit($id_ujian, $nis) {
        $stmt = $this->conn->prepare("SELECT id FROM hasil_ujian WHERE id_ujian = ? AND nis = ? LIMIT 1");
        $stmt->bind_param("is", $id_ujian, $nis);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    public function insertHasilUjian($id_ujian, $nis, $nama, $kelas, $total_skor, $detail_jawaban_json = null) {
        if ($detail_jawaban_json !== null) {
            $stmt = $this->conn->prepare("INSERT INTO hasil_ujian (id_ujian, nis, nama, kelas, total_skor, detail_jawaban) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssis", $id_ujian, $nis, $nama, $kelas, $total_skor, $detail_jawaban_json);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO hasil_ujian (id_ujian, nis, nama, kelas, total_skor) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $id_ujian, $nis, $nama, $kelas, $total_skor);
        }
        return $stmt->execute();
    }

    public function fetchAll($sql, $params = [], $types = '') {
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function fetchRow($sql, $params = [], $types = '') {
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function execute($sql, $params = [], $types = '') {
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $result = $stmt->execute();
        $insert_id = $stmt->insert_id;
        $stmt->close();
        return $insert_id ?: $result;
    }

    public function logAudit($admin_id, $admin_username, $aksi, $entitas, $entitas_id = null, $detail = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $sql = "INSERT INTO audit_log (admin_id, admin_username, aksi, entitas, entitas_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isssiss", $admin_id, $admin_username, $aksi, $entitas, $entitas_id, $detail, $ip);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function columnExists($table, $column) {
        $result = $this->conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    }

    public function getColumns($table) {
        $result = $this->conn->query("SHOW COLUMNS FROM `$table`");
        $columns = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        return $columns;
    }

    public function tableExists($table) {
        $result = $this->conn->query("SHOW TABLES LIKE '$table'");
        return $result && $result->num_rows > 0;
    }

    /**
     * Ensure a table exists, creating it if necessary.
     * If the table doesn't exist, createMigrationHistory must be handled separately.
     */
    public function ensureTable($table, $schema) {
        if (!$this->tableExists($table)) {
            $this->conn->query("CREATE TABLE `$table` ({$schema})");
        }
    }

    /**
     * Ensure a column exists on a table, adding it if necessary.
     */
    public function ensureColumnExists($table, $column, $definition) {
        if (!$this->columnExists($table, $column)) {
            $this->conn->query("ALTER TABLE `$table` ADD COLUMN {$column} {$definition}");
            return true;
        }
        return false;
    }

    /**
     * Ensure a migration history table exists for tracking applied migrations.
     */
    public function createMigrationHistory() {
        $this->ensureTable('migrations_history', '
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration_name` VARCHAR(255) NOT NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `applied_by` VARCHAR(100) NULL,
            UNIQUE KEY `unique_migration` (`migration_name`)
        ');
    }

    /**
     * Record that a migration has been applied.
     */
    public function recordMigration($name) {
        $this->createMigrationHistory();
        $stmt = $this->conn->prepare("INSERT IGNORE INTO migrations_history (migration_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Check if a migration has already been applied.
     */
    public function isMigrationApplied($name) {
        $stmt = $this->conn->prepare("SELECT id FROM migrations_history WHERE migration_name = ? LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}