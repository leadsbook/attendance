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
    
    // Get weekly offs for the branch
    $stmt = $db->prepare("
        SELECT day_of_week 
        FROM weekly_offs 
        WHERE branch_id = ?
        ORDER BY day_of_week
    ");
    
    $stmt->execute([$branchId]);
    $weeklyOffs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'weekly_offs' => $weeklyOffs
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}