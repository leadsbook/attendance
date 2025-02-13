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

    // Validate and sanitize input
    $branchId = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING);
    $shiftStart = filter_input(INPUT_POST, 'shift_start_time', FILTER_SANITIZE_STRING);
    $shiftEnd = filter_input(INPUT_POST, 'shift_end_time', FILTER_SANITIZE_STRING);
    $gracePeriod = filter_input(INPUT_POST, 'grace_period_minutes', FILTER_VALIDATE_INT);
    $halfDayMinutes = filter_input(INPUT_POST, 'half_day_after_minutes', FILTER_VALIDATE_INT);

    // Validate required fields
    if (!$name || !$location || !$timezone || !$shiftStart || !$shiftEnd || 
        !is_numeric($gracePeriod) || !is_numeric($halfDayMinutes)) {
        throw new Exception('All fields are required');
    }

    // Validate timezone
    if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
        throw new Exception('Invalid timezone');
    }

    if ($branchId) {
        // Update existing branch
        $stmt = $db->prepare("
            UPDATE branches 
            SET name = ?, location = ?, timezone = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $location, $timezone, $branchId]);

        $stmt = $db->prepare("
            UPDATE branch_settings 
            SET shift_start_time = ?,
                shift_end_time = ?,
                grace_period_minutes = ?,
                half_day_after_minutes = ?
            WHERE branch_id = ?
        ");
        $stmt->execute([
            $shiftStart,
            $shiftEnd,
            $gracePeriod,
            $halfDayMinutes,
            $branchId
        ]);

        // Log the update
        logActivity($db, $branchId, 'update_branch', $_SESSION['user_id']);

    } else {
        // Create new branch
        $stmt = $db->prepare("
            INSERT INTO branches (name, location, timezone)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$name, $location, $timezone]);
        
        $newBranchId = $db->lastInsertId();

        $stmt = $db->prepare("
            INSERT INTO branch_settings (
                branch_id, shift_start_time, shift_end_time,
                grace_period_minutes, half_day_after_minutes
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $newBranchId,
            $shiftStart,
            $shiftEnd,
            $gracePeriod,
            $halfDayMinutes
        ]);

        // Log the creation
        logActivity($db, $newBranchId, 'create_branch', $_SESSION['user_id']);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Branch ' . ($branchId ? 'updated' : 'created') . ' successfully'
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

function logActivity($db, $branchId, $action, $userId) {
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, action, table_name, 
            record_id, created_at
        ) VALUES (?, ?, 'branches', ?, NOW())
    ");
    $stmt->execute([$userId, $action, $branchId]);
}