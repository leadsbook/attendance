<?php

require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth(getDBConnection());
$auth->requireAdmin();

$db = getDBConnection();

// Get all branches for the dropdown
$branches = $db->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

// Get search parameters
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$branch = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);

// Build query
$query = "
    SELECT 
        u.id,
        u.employee_id,
        u.username,
        u.role,
        ep.full_name,
        ep.designation,
        ep.department,
        ep.phone,
        ep.email,
        ep.date_of_joining,
        b.name as branch_name,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM attendance 
                WHERE user_id = u.id 
                AND date = CURRENT_DATE()
                AND status = 'present'
            ) THEN 'Present'
            WHEN EXISTS (
                SELECT 1 FROM leave_applications 
                WHERE user_id = u.id 
                AND status = 'approved'
                AND CURRENT_DATE() BETWEEN start_date AND end_date
            ) THEN 'On Leave'
            ELSE 'Absent'
        END as today_status
    FROM users u
    JOIN employee_profiles ep ON u.id = ep.user_id
    JOIN branches b ON ep.branch_id = b.id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (
        u.employee_id LIKE ? OR 
        ep.full_name LIKE ? OR 
        ep.email LIKE ? OR 
        ep.phone LIKE ?
    )";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($branch) {
    $query .= " AND ep.branch_id = ?";
    $params[] = $branch;
}

if ($status) {
    switch ($status) {
        case 'present':
            $query .= " AND EXISTS (
                SELECT 1 FROM attendance 
                WHERE user_id = u.id 
                AND date = CURRENT_DATE()
                AND status = 'present'
            )";
            break;
        case 'absent':
            $query .= " AND NOT EXISTS (
                SELECT 1 FROM attendance 
                WHERE user_id = u.id 
                AND date = CURRENT_DATE()
            ) AND NOT EXISTS (
                SELECT 1 FROM leave_applications 
                WHERE user_id = u.id 
                AND status = 'approved'
                AND CURRENT_DATE() BETWEEN start_date AND end_date
            )";
            break;
        case 'on_leave':
            $query .= " AND EXISTS (
                SELECT 1 FROM leave_applications 
                WHERE user_id = u.id 
                AND status = 'approved'
                AND CURRENT_DATE() BETWEEN start_date AND end_date
            )";
            break;
    }
}

$query .= " ORDER BY ep.full_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="admin-content">
            <div class="page-header">
                <h1>Employee Management</h1>
                <button class="btn btn-primary" id="addEmployeeBtn">Add Employee</button>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="search" 
                               placeholder="Search employees..." 
                               value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <select name="branch">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?php echo $b['id']; ?>" 
                                    <?php echo ($branch == $b['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="present" <?php echo ($status === 'present') ? 'selected' : ''; ?>>
                                Present Today
                            </option>
                            <option value="absent" <?php echo ($status === 'absent') ? 'selected' : ''; ?>>
                                Absent Today
                            </option>
                            <option value="on_leave" <?php echo ($status === 'on_leave') ? 'selected' : ''; ?>>
                                On Leave
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-secondary">Apply Filters</button>
                </form>
            </div>

            <!-- Employees Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Department</th>
                            <th>Branch</th>
                            <th>Contact</th>
                            <th>Today's Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                <td>
                                    <div class="employee-name">
                                        <?php echo htmlspecialchars($employee['full_name']); ?>
                                        <?php if ($employee['role'] === 'admin'): ?>
                                            <span class="admin-badge">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($employee['designation']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td><?php echo htmlspecialchars($employee['branch_name']); ?></td>
                                <td>
                                    <div class="contact-info">
                                        <div><?php echo htmlspecialchars($employee['email']); ?></div>
                                        <div><?php echo htmlspecialchars($employee['phone']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($employee['today_status']); ?>">
                                        <?php echo $employee['today_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-icon edit-employee" 
                                                data-id="<?php echo $employee['id']; ?>"
                                                title="Edit Employee">
                                            ✏️
                                        </button>
                                        <button class="btn btn-icon view-attendance" 
                                                data-id="<?php echo $employee['id']; ?>"
                                                title="View Attendance">
                                            📊
                                        </button>
                                        <button class="btn btn-icon view-leaves" 
                                                data-id="<?php echo $employee['id']; ?>"
                                                title="View Leaves">
                                            📅
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Employee</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="employeeForm">
                <input type="hidden" name="employee_id" id="employeeId">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3>Personal Information</h3>
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label for="empId">Employee ID</label>
                            <input type="text" id="empId" name="employee_id" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                    </div>

                    <!-- Work Information -->
                    <div class="form-section">
                        <h3>Work Information</h3>
                        <div class="form-group">
                            <label for="designation">Designation</label>
                            <input type="text" id="designation" name="designation" required>
                        </div>
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" required>
                        </div>
                        <div class="form-group">
                            <label for="branch">Branch</label>
                            <select id="branch" name="branch_id" required>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>">
                                        <?php echo htmlspecialchars($b['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="joiningDate">Date of Joining</label>
                            <input type="date" id="joiningDate" name="date_of_joining" required>
                        </div>
                    </div>

                    <!-- Account Settings -->
                    <div class="form-section">
                        <h3>Account Settings</h3>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password">
                            <small class="form-help">Leave blank to keep existing password</small>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="employee">Employee</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin/employees.js"></script>
</body>
</html>