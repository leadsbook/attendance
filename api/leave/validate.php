<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth(getDBConnection());
$auth->requireAuth();

if (!validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $db = getDBConnection();
    
    $leaveType = filter_input(INPUT_POST, 'leave_type', FILTER_SANITIZE_STRING);
    $startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $endDate = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);

    if (!$leaveType || !$startDate || !$endDate) {
        throw new Exception('All fields are required');
    }

    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    // Calculate leave days
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

    // Check balance
    if ($leaveType === 'emergency' && $leaveDays > $balance['emergency_balance']) {
        throw new Exception(sprintf(
            'Insufficient emergency leave balance. Available: %d, Required: %d',
            $balance['emergency_balance'],
            $leaveDays
        ));
    }
    
    if ($leaveType === 'privilege' && $leaveDays > $balance['privilege_balance']) {
        throw new Exception(sprintf(
            'Insufficient privilege leave balance. Available: %d, Required: %d',
            $balance['privilege_balance'],
            $leaveDays
        ));
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

    echo json_encode([
        'success' => true,
        'days' => $leaveDays,
        'message' => sprintf(
            'Leave request valid for %d days. Available balance: %d',
            $leaveDays,
            $leaveType === 'emergency' ? $balance['emergency_balance'] : $balance['privilege_balance']
        )
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}