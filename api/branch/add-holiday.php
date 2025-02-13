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
    $branchId = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);

    if (!$branchId || !$name || !$date) {
        throw new Exception('All fields are required');
    }

    // Validate date format and ensure it's not in the past
    $holidayDate = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0); // Reset time part for comparison

    if ($holidayDate < $today) {
        throw new Exception('Holiday date cannot be in the past');
    }

    // Check for existing holiday on the same date
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM holidays 
        WHERE branch_id = ? AND date = ?
    ");
    $stmt->execute([$branchId, $date]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('A holiday already exists on this date');
    }

    // Insert holiday
    $stmt = $db->prepare("
        INSERT INTO holidays (
            branch_id, name, date, 
            created_by, created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $branchId,
        $name,
        $date,
        $_SESSION['user_id']
    ]);

    $holidayId = $db->lastInsertId();

    // Log the activity
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, action, table_name, 
            record_id, new_values
        ) VALUES (?, 'add_holiday', 'holidays', ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $holidayId,
        json_encode([
            'name' => $name,
            'date' => $date,
            'branch_id' => $branchId
        ])
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Holiday added successfully',
        'holiday' => [
            'id' => $holidayId,
            'name' => $name,
            'date' => $date
        ]
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
