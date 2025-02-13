<?php

require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth(getDBConnection());
$auth->requireAdmin();

$db = getDBConnection();

// Get summary statistics
$stats = [
    'total_employees' => $db->query("SELECT COUNT(*) FROM employee_profiles")->fetchColumn(),
    'present_today' => $db->query("
        SELECT COUNT(*) FROM attendance 
        WHERE date = CURRENT_DATE() AND status = 'present'
    ")->fetchColumn(),
    'on_leave' => $db->query("
        SELECT COUNT(*) FROM leave_applications 
        WHERE status = 'approved' 
        AND CURRENT_DATE() BETWEEN start_date AND end_date
    ")->fetchColumn(),
    'pending_leaves' => $db->query("
        SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'
    ")->fetchColumn()
];

// Get recent activities
$recentActivities = $db->query("
    SELECT 
        al.action,
        al.created_at,
        CONCAT(ep.full_name, ' (', u.employee_id, ')') as employee_name
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    JOIN employee_profiles ep ON u.id = ep.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

// Get leave requests pending approval
$pendingLeaves = $db->query("
    SELECT 
        la.id,
        ep.full_name,
        u.employee_id,
        la.leave_type,
        la.start_date,
        la.end_date,
        la.reason,
        la.created_at
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    JOIN employee_profiles ep ON u.id = ep.user_id
    WHERE la.status = 'pending'
    ORDER BY la.created_at ASC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="admin-dashboard">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Employees</h3>
                    <div class="stat-value"><?php echo $stats['total_employees']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Present Today</h3>
                    <div class="stat-value"><?php echo $stats['present_today']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>On Leave</h3>
                    <div class="stat-value"><?php echo $stats['on_leave']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Leaves</h3>
                    <div class="stat-value"><?php echo $stats['pending_leaves']; ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="employees.php" class="action-btn">
                            <span class="action-icon">👥</span>
                            Manage Employees
                        </a>
                        <a href="attendance.php" class="action-btn">
                            <span class="action-icon">📊</span>
                            View Attendance
                        </a>
                        <a href="leaves.php" class="action-btn">
                            <span class="action-icon">📅</span>
                            Manage Leaves
                        </a>
                        <a href="settings.php" class="action-btn">
                            <span class="action-icon">⚙️</span>
                            Settings
                        </a>
                    </div>
                </div>

                <!-- Pending Leave Requests -->
                <div class="dashboard-card">
                    <h2>Pending Leave Requests</h2>
                    <?php if (empty($pendingLeaves)): ?>
                        <p class="no-data">No pending leave requests</p>
                    <?php else: ?>
                        <div class="leave-requests">
                            <?php foreach ($pendingLeaves as $leave): ?>
                                <div class="leave-request-item">
                                    <div class="leave-request-header">
                                        <span class="employee-name"><?php echo htmlspecialchars($leave['full_name']); ?></span>
                                        <span class="employee-id"><?php echo htmlspecialchars($leave['employee_id']); ?></span>
                                    </div>
                                    <div class="leave-request-details">
                                        <span class="leave-type"><?php echo ucfirst($leave['leave_type']); ?> Leave</span>
                                        <span class="leave-dates">
                                            <?php 
                                            $start = new DateTime($leave['start_date']);
                                            $end = new DateTime($leave['end_date']);
                                            echo $start->format('M d') . ' - ' . $end->format('M d, Y');
                                            ?>
                                        </span>
                                    </div>
                                    <div class="leave-actions">
                                        <button class="btn btn-success btn-sm approve-leave" 
                                                data-id="<?php echo $leave['id']; ?>">
                                            Approve
                                        </button>
                                        <button class="btn btn-danger btn-sm reject-leave" 
                                                data-id="<?php echo $leave['id']; ?>">
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="dashboard-card">
                    <h2>Recent Activities</h2>
                    <?php if (empty($recentActivities)): ?>
                        <p class="no-data">No recent activities</p>
                    <?php else: ?>
                        <div class="activities-list">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <span class="activity-action">
                                        <?php echo formatActivityAction($activity['action']); ?>
                                    </span>
                                    <span class="activity-user">
                                        <?php echo htmlspecialchars($activity['employee_name']); ?>
                                    </span>
                                    <span class="activity-time">
                                        <?php echo formatTimeAgo($activity['created_at']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>

<?php
function formatActivityAction($action) {
    $actions = [
        'mark_attendance' => 'Marked attendance',
        'apply_leave' => 'Applied for leave',
        'approve_leave' => 'Approved leave',
        'reject_leave' => 'Rejected leave',
        'update_profile' => 'Updated profile'
    ];
    return $actions[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

function formatTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>