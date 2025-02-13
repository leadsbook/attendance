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
    $branchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$branchId) {
        throw new Exception('Invalid branch ID');
    }

    $db = getDBConnection();
    
    // Get branch details with settings
    $stmt = $db->prepare("
        SELECT 
            b.*,
            bs.shift_start_time,
            bs.shift_end_time,
            bs.grace_period_minutes,
            bs.half_day_after_minutes
        FROM branches b
        LEFT JOIN branch_settings bs ON b.id = bs.branch_id
        WHERE b.id = ?
    ");
    
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch();

    if (!$branch) {
        throw new Exception('Branch not found');
    }

    echo json_encode([
        'success' => true,
        'branch' => $branch
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}