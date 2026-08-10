<?php
/**
 * Exam Helper Functions
 * Centralized exam scheduling and status validation logic
 */

require_once __DIR__ . '/log_helper.php';

/**
 * Check if an exam is within its active schedule window.
 *
 * @param mysqli $conn Database connection
 * @param array $ujian  The exam record (must include tanggal_mulai, tanggal_selesai, judul_ujian)
 * @param string|null $now Current timestamp (Y-m-d H:i:s), defaults to now
 * @param string $tz Timezone for date formatting (default Asia/Jakarta)
 * @return array{active: bool, reason: string, display_date: string}
 */
function is_exam_schedule_active($conn, $ujian, $now = null, $tz = 'Asia/Jakarta') {
    $dt = new DateTimeZone($tz);
    if ($now === null) {
        $now = date('Y-m-d H:i:s');
    }

    // Check if scheduling columns exist (backward compatibility)
    $hasScheduling = $conn->columnExists('ujian', 'tanggal_mulai');
    if (!$hasScheduling) {
        return ['active' => true, 'reason' => '', 'display_date' => ''];
    }

    // Check if exam hasn't started yet
    if (!empty($ujian['tanggal_mulai']) && $now < $ujian['tanggal_mulai']) {
        $dtStart = new DateTime($ujian['tanggal_mulai'], $dt);
        $displayDate = $dtStart->format('d M Y H:i');
        return [
            'active' => false,
            'reason' => 'Ujian belum dimulai',
            'display_date' => $displayDate
        ];
    }

    // Check if exam has ended
    if (!empty($ujian['tanggal_selesai']) && $now > $ujian['tanggal_selesai']) {
        $dtEnd = new DateTime($ujian['tanggal_selesai'], $dt);
        $displayDate = $dtEnd->format('d M Y H:i');
        return [
            'active' => false,
            'reason' => 'Ujian telah berakhir',
            'display_date' => $displayDate
        ];
    }

    return ['active' => true, 'reason' => '', 'display_date' => ''];
}
