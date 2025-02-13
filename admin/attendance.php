<?php

require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth(getDBConnection());
$auth->requireAdmin();

$db = getDBConnection();

// Get filter parameters
$employeeId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$branchId = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?? date('Y-m-01'); // Default to first day of current month
$endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?? date('Y-m-d');

// Get all branches for filter
$branches = $db->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

// Build query for attendance records
$query = "
    SELECT 
        a.id,
        a.date,
        a.check_in,
        a.check_out,
        a.status,
        a.location_lat,
        a.location_long,
        a.selfie_path,
        u.employee_id,
        ep.full_name,
        ep.designation,
        b.name as branch_name,
        CASE 
            WHEN TIME(a.check_in) > ADDTIME(bs.shift_start_time, SEC_TO_TIME(bs.grace_period_minutes * 60))
            THEN 'Late'
            ELSE 'On Time'
        END as arrival_status
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    JOIN employee_profiles ep ON u.id = ep.user_id
    JOIN branches b ON ep.branch_id = b.id
    JOIN branch_settings bs ON b.id = bs.branch_id
    WHERE a.date BETWEEN ? AND ?
";

$params = [$startDate, $endDate];

if ($employeeId) {
    $query .= " AND u.id = ?";
    $params[] = $employeeId;
}

if ($branchId) {
    $query .= " AND b.id = ?";
    $params[] = $branchId;
}

if ($status) {
    $query .= " AND a.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY a.date DESC, a.check_in DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll();

// Calculate statistics
$stats = [
    'total_records' => count($attendanceRecords),
    'present_count' => 0,
    'absent_count' => 0,
    'late_count' => 0
];

foreach ($attendanceRecords as $record) {
    if ($record['status'] === 'present') {
        $stats['present_count']++;
        if ($record['arrival_status'] === 'Late') {
            $stats['late_count']++;
        }
    } else {
        $stats['absent_count']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="admin-content">
            <div class="page-header">
                <h1>Attendance Records</h1>
                <button class="btn btn-primary" id="exportBtn">Export to Excel</button>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Records</h3>
                    <div class="stat-value"><?php echo $stats['total_records']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Present</h3>
                    <div class="stat-value"><?php echo $stats['present_count']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Absent</h3>
                    <div class="stat-value"><?php echo $stats['absent_count']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Late Arrivals</h3>
                    <div class="stat-value"><?php echo $stats['late_count']; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <?php if ($employeeId): ?>
                        <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">
                    <?php endif; ?>

                    <div class="filter-group">
                        <label for="startDate">Start Date</label>
                        <input type="date" id="startDate" name="start_date" 
                               value="<?php echo $startDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="endDate">End Date</label>
                        <input type="date" id="endDate" name="end_date" 
                               value="<?php echo $endDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <?php if (!$employeeId): ?>
                        <div class="filter-group">
                            <label for="branch">Branch</label>
                            <select id="branch" name="branch_id">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>" 
                                        <?php echo ($branchId == $branch['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="present" <?php echo ($status === 'present') ? 'selected' : ''; ?>>
                                Present
                            </option>
                            <option value="absent" <?php echo ($status === 'absent') ? 'selected' : ''; ?>>
                                Absent
                            </option>
                            <option value="half-day" <?php echo ($status === 'half-day') ? 'selected' : ''; ?>>
                                Half Day
                            </option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-secondary">Apply Filters</button>
                        <a href="?<?php echo $employeeId ? 'employee_id=' . $employeeId : ''; ?>" 
                           class="btn btn-link">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Attendance Records Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <?php if (!$employeeId): ?>
                                <th>Employee</th>
                                <th>Branch</th>
                            <?php endif; ?>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                            <th>Arrival</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <?php if (!$employeeId): ?>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($record['full_name']); ?>
                                            </div>
                                            <div class="employee-id">
                                                <?php echo htmlspecialchars($record['employee_id']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['branch_name']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($record['check_in']): ?>
                                        <?php echo date('h:i A', strtotime($record['check_in'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['check_out']): ?>
                                        <?php echo date('h:i A', strtotime($record['check_out'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="arrival-status <?php echo strtolower($record['arrival_status']); ?>">
                                        <?php echo $record['arrival_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($record['selfie_path']): ?>
                                            <button class="btn btn-icon view-selfie" 
                                                    data-path="<?php echo htmlspecialchars($record['selfie_path']); ?>"
                                                    title="View Selfie">
                                                📷
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($record['location_lat'] && $record['location_long']): ?>
                                            <button class="btn btn-icon view-location"
                                                    data-lat="<?php echo $record['location_lat']; ?>"
                                                    data-long="<?php echo $record['location_long']; ?>"
                                                    title="View Location">
                                                📍
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-icon edit-attendance"
                                                data-id="<?php echo $record['id']; ?>"
                                                title="Edit Attendance">
                                            ✏️
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

    <!-- Selfie Modal -->
    <div id="selfieModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Attendance Selfie</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <img id="selfieImage" src="" alt="Attendance Selfie">
            </div>
        </div>
    </div>

    <!-- Location Modal -->
    <div id="locationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Check-in Location</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="locationMap"></div>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div id="editAttendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Attendance</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="editAttendanceForm">
                <input type="hidden" name="attendance_id" id="attendanceId">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="attendanceStatus">Status</label>
                    <select id="attendanceStatus" name="status" required>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="half-day">Half Day</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="checkInTime">Check In Time</label>
                    <input type="time" id="checkInTime" name="check_in">
                </div>

                <div class="form-group">
                    <label for="checkOutTime">Check Out Time</label>
                    <input type="time" id="checkOutTime" name="check_out">
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editAttendanceModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin/attendance.js"></script>
</body>
</html>
