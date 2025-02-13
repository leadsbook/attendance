<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Initialize authentication
$auth = new Auth(getDBConnection());
$auth->requireAdmin();

// Verify CSRF token
if (!validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $db = getDBConnection();
    $db->beginTransaction();

    // Validate input
    $attendanceId = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $checkIn = filter_input(INPUT_POST, 'check_in', FILTER_SANITIZE_STRING);
    $checkOut = filter_input(INPUT_POST, 'check_out', FILTER_SANITIZE_STRING);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);

    if (!$attendanceId || !$status) {
        throw new Exception('Invalid attendance data');
    }

    // Get current attendance record
    $stmt = $db->prepare("
        SELECT a.*, ep.branch_id
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN employee_profiles ep ON u.id = ep.user_id
        WHERE a.id = ?
    ");
    $stmt->execute([$attendanceId]);
    $currentAttendance = $stmt->fetch();

    if (!$currentAttendance) {
        throw new Exception('Attendance record not found');
    }

    // Get branch settings for validation
    $stmt = $db->prepare("
        SELECT shift_start_time, shift_end_time, grace_period_minutes
        FROM branch_settings
        WHERE branch_id = ?
    ");
    $stmt->execute([$currentAttendance['branch_id']]);
    $branchSettings = $stmt->fetch();

    // Validate check-in time
    if ($checkIn) {
        $checkInTime = new DateTime($currentAttendance['date'] . ' ' . $checkIn);
        $shiftStart = new DateTime($currentAttendance['date'] . ' ' . $branchSettings['shift_start_time']);
        $graceEnd = clone $shiftStart;
        $graceEnd->add(new DateInterval('PT' . $branchSettings['grace_period_minutes'] . 'M'));

        // Determine if it's a late arrival
        $isLate = $checkInTime > $graceEnd;
    }

    // Validate check-out time
    if ($checkOut && $checkIn) {
        $checkOutTime = new DateTime($currentAttendance['date'] . ' ' . $checkOut);
        if ($checkOutTime <= $checkInTime) {
            throw new Exception('Check-out time must be after check-in time');
        }
    }

    // Store old values for audit log
    $oldValues = [
        'status' => $currentAttendance['status'],
        'check_in' => $currentAttendance['check_in'],
        'check_out' => $currentAttendance['check_out']
    ];

    // Update attendance record
    $stmt = $db->prepare("
        UPDATE attendance 
        SET status = ?,
            check_in = ?,
            check_out = ?,
            remarks = ?,
            modified_by = ?,
            modified_at = NOW()
        WHERE id = ?
    ");

    $checkInDateTime = $checkIn ? $currentAttendance['date'] . ' ' . $checkIn : null;
    $checkOutDateTime = $checkOut ? $currentAttendance['date'] . ' ' . $checkOut : null;

    $stmt->execute([
        $status,
        $checkInDateTime,
        $checkOutDateTime,
        $remarks,
        $_SESSION['user_id'],
        $attendanceId
    ]);

    // Log the changes
    $newValues = [
        'status' => $status,
        'check_in' => $checkInDateTime,
        'check_out' => $checkOutDateTime,
        'remarks' => $remarks
    ];

    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, 
            action, 
            table_name, 
            record_id, 
            old_values, 
            new_values
        ) VALUES (?, 'update_attendance', 'attendance', ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $attendanceId,
        json_encode($oldValues),
        json_encode($newValues)
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Attendance updated successfully'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}