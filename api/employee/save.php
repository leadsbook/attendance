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
    $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $empCode = filter_input(INPUT_POST, 'emp_id', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? null;
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $designation = filter_input(INPUT_POST, 'designation', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $branchId = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $joiningDate = filter_input(INPUT_POST, 'date_of_joining', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (!$username || !$fullName || !$email || !$phone || !$designation || 
        !$department || !$branchId || !$joiningDate || !$empCode) {
        throw new Exception('All fields are required');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate phone format (customize as per your requirements)
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        throw new Exception('Invalid phone number format');
    }

    // Check unique constraints
    $stmt = $db->prepare("
        SELECT id FROM users 
        WHERE (username = ? OR employee_id = ?) 
        AND id != ?
    ");
    $stmt->execute([$username, $empCode, $employeeId ?? 0]);
    if ($stmt->fetch()) {
        throw new Exception('Username or Employee ID already exists');
    }

    if ($employeeId) {
        // Update existing employee
        $stmt = $db->prepare("
            UPDATE users 
            SET username = ?,
                employee_id = ?,
                role = ?
            WHERE id = ?
        ");
        $stmt->execute([$username, $empCode, $role, $employeeId]);

        if ($password) {
            $hashedPassword = hashPassword($password);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $employeeId]);
        }

        $stmt = $db->prepare("
            UPDATE employee_profiles 
            SET full_name = ?,
                designation = ?,
                department = ?,
                branch_id = ?,
                phone = ?,
                email = ?,
                date_of_joining = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $fullName,
            $designation,
            $department,
            $branchId,
            $phone,
            $email,
            $joiningDate,
            $employeeId
        ]);

        // Log the update
        logActivity($db, $employeeId, 'update_employee', $_SESSION['user_id']);

    } else {
        // Create new employee
        if (!$password) {
            throw new Exception('Password is required for new employees');
        }

        $hashedPassword = hashPassword($password);

        $stmt = $db->prepare("
            INSERT INTO users (username, employee_id, password, role)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $empCode, $hashedPassword, $role]);
        
        $newUserId = $db->lastInsertId();

        $stmt = $db->prepare("
            INSERT INTO employee_profiles (
                user_id, full_name, designation, department, 
                branch_id, phone, email, date_of_joining
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $newUserId,
            $fullName,
            $designation,
            $department,
            $branchId,
            $phone,
            $email,
            $joiningDate
        ]);

        // Initialize leave balance for new employee
        initializeLeaveBalance($db, $newUserId);

        // Log the creation
        logActivity($db, $newUserId, 'create_employee', $_SESSION['user_id']);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Employee ' . ($employeeId ? 'updated' : 'created') . ' successfully'
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
 * Initialize leave balance for new employee
 */
function initializeLeaveBalance($db, $userId) {
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    // Add leave balance for remaining months of the year
    for ($month = $currentMonth; $month <= 12; $month++) {
        $stmt = $db->prepare("
            INSERT INTO leave_balance (
                user_id, year, month, 
                emergency_leaves, privilege_leaves
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $currentYear,
            $month,
            EMERGENCY_LEAVES_PER_MONTH,
            PRIVILEGE_LEAVES_PER_MONTH
        ]);
    }
}

/**
 * Log employee-related activity
 */
function logActivity($db, $employeeId, $action, $performedBy) {
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, action, table_name, 
            record_id, created_at
        ) VALUES (?, ?, 'users', ?, NOW())
    ");
    $stmt->execute([$performedBy, $action, $employeeId]);
}