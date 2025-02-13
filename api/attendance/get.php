<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Initialize authentication
$auth = new Auth(getDBConnection());
$auth->requireAdmin();

// Verify CSRF token from header
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $attendanceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$attendanceId) {
        throw new Exception('Invalid attendance ID');
    }

    $db = getDBConnection();
    
    // Get attendance record with employee details
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.employee_id,
            ep.full_name,
            ep.branch_id,
            b.name as branch_name,
            bs.shift_start_time,
            bs.shift_end_time,
            bs.grace_period_minutes
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN employee_profiles ep ON u.id = ep.user_id
        JOIN branches b ON ep.branch_id = b.id
        JOIN branch_settings bs ON b.id = bs.branch_id
        WHERE a.id = ?
    ");
    
    $stmt->execute([$attendanceId]);
    $attendance = $stmt->fetch();

    if (!$attendance) {
        throw new Exception('Attendance record not found');
    }

    // Format times for frontend
    if ($attendance['check_in']) {
        $attendance['check_in'] = date('H:i', strtotime($attendance['check_in']));
    }
    if ($attendance['check_out']) {
        $attendance['check_out'] = date('H:i', strtotime($attendance['check_out']));
    }

    // Calculate late status
    $shiftStart = new DateTime($attendance['shift_start_time']);
    $graceMinutes = new DateInterval('PT' . $attendance['grace_period_minutes'] . 'M');
    $shiftStart->add($graceMinutes);

    if ($attendance['check_in']) {
        $checkIn = new DateTime($attendance['check_in']);
        $attendance['is_late'] = $checkIn > $shiftStart;
    }

    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}