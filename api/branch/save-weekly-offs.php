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

    $branchId = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $weeklyOffs = $_POST['weekly_offs'] ?? [];

    if (!$branchId) {
        throw new Exception('Invalid branch ID');
    }

    // Validate weekly offs
    foreach ($weeklyOffs as $day) {
        if (!is_numeric($day) || $day < 0 || $day > 6) {
            throw new Exception('Invalid day value');
        }
    }

    // Delete existing weekly offs
    $stmt = $db->prepare("DELETE FROM weekly_offs WHERE branch_id = ?");
    $stmt->execute([$branchId]);

    // Insert new weekly offs
    if (!empty($weeklyOffs)) {
        $values = array_fill(0, count($weeklyOffs), "(?, ?)");
        $sql = "INSERT INTO weekly_offs (branch_id, day_of_week) VALUES " . implode(", ", $values);
        
        $params = [];
        foreach ($weeklyOffs as $day) {
            $params[] = $branchId;
            $params[] = $day;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    // Log the update
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, action, table_name, 
            record_id, new_values
        ) VALUES (?, 'update_weekly_offs', 'branches', ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $branchId,
        json_encode(['weekly_offs' => $weeklyOffs])
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Weekly offs updated successfully'
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