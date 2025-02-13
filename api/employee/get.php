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
    $employeeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$employeeId) {
        throw new Exception('Invalid employee ID');
    }

    $db = getDBConnection();
    
    // Get employee details with branch information
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.employee_id,
            u.username,
            u.role,
            ep.full_name,
            ep.designation,
            ep.department,
            ep.branch_id,
            ep.phone,
            ep.email,
            ep.date_of_joining,
            b.name as branch_name
        FROM users u
        JOIN employee_profiles ep ON u.id = ep.user_id
        JOIN branches b ON ep.branch_id = b.id
        WHERE u.id = ?
    ");
    
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        throw new Exception('Employee not found');
    }

    // Get additional employee statistics
    $stats = [
        'attendance_rate' => calculateAttendanceRate($db, $employeeId),
        'leave_count' => getLeaveCount($db, $employeeId),
        'late_count' => getLateCount($db, $employeeId)
    ];

    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate employee's attendance rate for the current month
 */
function calculateAttendanceRate($db, $employeeId) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
        FROM attendance
        WHERE user_id = ?
        AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch();
    
    return $result['total_days'] > 0 
        ? round(($result['present_days'] / $result['total_days']) * 100, 2)
        : 0;
}

/**
 * Get employee's leave count for the current year
 */
function getLeaveCount($db, $employeeId) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_leaves,
            SUM(CASE WHEN leave_type = 'emergency' THEN 1 ELSE 0 END) as emergency_leaves,
            SUM(CASE WHEN leave_type = 'privilege' THEN 1 ELSE 0 END) as privilege_leaves
        FROM leave_applications
        WHERE user_id = ?
        AND status = 'approved'
        AND YEAR(start_date) = YEAR(CURRENT_DATE())
    ");
    
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

/**
 * Get employee's late arrival count for the current month
 */
function getLateCount($db, $employeeId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM attendance a
        JOIN employee_profiles ep ON a.user_id = ep.user_id
        JOIN branch_settings bs ON ep.branch_id = bs.branch_id
        WHERE a.user_id = ?
        AND a.date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        AND TIME(a.check_in) > ADDTIME(bs.shift_start_time, SEC_TO_TIME(bs.grace_period_minutes * 60))
    ");
    
    $stmt->execute([$employeeId]);
    return $stmt->fetchColumn();
}
