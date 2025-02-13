<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Initialize authentication
$auth = new Auth(getDBConnection());
$auth->requireAuth();

// Verify CSRF token
if (!validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $db = getDBConnection();
    
    // Validate input
    $leaveType = filter_input(INPUT_POST, 'leaveType', FILTER_SANITIZE_STRING);
    $startDate = filter_input(INPUT_POST, 'startDate', FILTER_SANITIZE_STRING);
    $endDate = filter_input(INPUT_POST, 'endDate', FILTER_SANITIZE_STRING);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);

    if (!$leaveType || !$startDate || !$endDate || !$reason) {
        throw new Exception('All fields are required');
    }

    // Validate dates
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $today = new DateTime();

    if ($start < $today) {
        throw new Exception('Start date cannot be in the past');
    }

    if ($end < $start) {
        throw new Exception('End date cannot be before start date');
    }

    // Calculate number of leave days (excluding weekends and holidays)
    $leaveDays = calculateLeaveDays($db, $_SESSION['user_id'], $start, $end);

    // Get current leave balance
    $stmt = $db->prepare("
        SELECT SUM(emergency_leaves) as emergency_balance,
               SUM(privilege_leaves) as privilege_balance
        FROM leave_balance
        WHERE user_id = ?
        AND year = YEAR(CURRENT_DATE())
        AND month >= MONTH(CURRENT_DATE())
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetch();

    // Validate leave balance
    if ($leaveType === 'emergency' && $leaveDays > $balance['emergency_balance']) {
        throw new Exception('Insufficient emergency leave balance');
    }
    if ($leaveType === 'privilege' && $leaveDays > $balance['privilege_balance']) {
        throw new Exception('Insufficient privilege leave balance');
    }

    // Check for overlapping leaves
    $stmt = $db->prepare("
        SELECT COUNT(*) as overlap
        FROM leave_applications
        WHERE user_id = ?
        AND status != 'rejected'
        AND (
            (start_date BETWEEN ? AND ?) OR
            (end_date BETWEEN ? AND ?) OR
            (start_date <= ? AND end_date >= ?)
        )
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $startDate, $endDate,
        $startDate, $endDate,
        $startDate, $endDate
    ]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('You already have a leave application for these dates');
    }

    // Insert leave application
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO leave_applications (
            user_id,
            leave_type,
            start_date,
            end_date,
            reason,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $leaveType,
        $startDate,
        $endDate,
        $reason
    ]);

    // Log the activity
    $leaveId = $db->lastInsertId();
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id,
            action,
            table_name,
            record_id,
            new_values
        ) VALUES (?, 'apply_leave', 'leave_applications', ?, ?)
    ");

    $logData = json_encode([
        'leave_type' => $leaveType,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'days' => $leaveDays
    ]);

    $stmt->execute([$_SESSION['user_id'], $leaveId, $logData]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Leave application submitted successfully'
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

/**
 * Calculate number of leave days excluding weekends and holidays
 */
function calculateLeaveDays($db, $userId, DateTime $start, DateTime $end) {
    // Get user's branch ID
    $stmt = $db->prepare("
        SELECT branch_id 
        FROM employee_profiles 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $branchId = $stmt->fetchColumn();

    // Get branch holidays
    $stmt = $db->prepare("
        SELECT date 
        FROM holidays 
        WHERE branch_id = ? 
        AND date BETWEEN ? AND ?
    ");
    $stmt->execute([
        $branchId,
        $start->format('Y-m-d'),
        $end->format('Y-m-d')
    ]);
    $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get weekly offs
    $stmt = $db->prepare("
        SELECT day_of_week 
        FROM weekly_offs 
        WHERE branch_id = ?
    ");
    $stmt->execute([$branchId]);
    $weeklyOffs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $days = 0;
    $current = clone $start;

    while ($current <= $end) {
        // Skip if it's a holiday
        if (in_array($current->format('Y-m-d'), $holidays)) {
            $current->modify('+1 day');
            continue;
        }

        // Skip if it's a weekly off
        if (in_array($current->format('w'), $weeklyOffs)) {
            $current->modify('+1 day');
            continue;
        }

        $days++;
        $current->modify('+1 day');
    }

    return $days;
}
