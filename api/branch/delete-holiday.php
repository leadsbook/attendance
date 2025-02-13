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

    $holidayId = filter_input(INPUT_POST, 'holiday_id', FILTER_VALIDATE_INT);

    if (!$holidayId) {
        throw new Exception('Invalid holiday ID');
    }

    // Get holiday details before deletion for logging
    $stmt = $db->prepare("
        SELECT * FROM holidays WHERE id = ?
    ");
    $stmt->execute([$holidayId]);
    $holiday = $stmt->fetch();

    if (!$holiday) {
        throw new Exception('Holiday not found');
    }

    // Check if holiday is in the future
    $holidayDate = new DateTime($holiday['date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    if ($holidayDate < $today) {
        throw new Exception('Cannot delete past holidays');
    }

    // Delete the holiday
    $stmt = $db->prepare("DELETE FROM holidays WHERE id = ?");
    $stmt->execute([$holidayId]);

    // Log the deletion
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, action, table_name, 
            record_id, old_values
        ) VALUES (?, 'delete_holiday', 'holidays', ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $holidayId,
        json_encode([
            'name' => $holiday['name'],
            'date' => $holiday['date'],
            'branch_id' => $holiday['branch_id']
        ])
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Holiday deleted successfully'
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