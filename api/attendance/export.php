<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Initialize authentication
$auth = new Auth(getDBConnection());
$auth->requireAdmin();

try {
    $db = getDBConnection();

    // Get filter parameters
    $employeeId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
    $branchId = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
    $startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?? date('Y-m-01');
    $endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?? date('Y-m-d');

    // Build query
    $query = "
        SELECT 
            a.date,
            u.employee_id,
            ep.full_name,
            ep.designation,
            b.name as branch_name,
            a.check_in,
            a.check_out,
            a.status,
            CASE 
                WHEN TIME(a.check_in) > ADDTIME(bs.shift_start_time, SEC_TO_TIME(bs.grace_period_minutes * 60))
                THEN 'Late'
                ELSE 'On Time'
            END as arrival_status,
            a.remarks
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

    $query .= " ORDER BY a.date DESC, ep.full_name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="attendance_report.xls"');
    header('Cache-Control: max-age=0');

    // Create Excel file
    echo "
        <table border='1'>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Branch</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                    <th>Arrival</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
    ";

    foreach ($records as $record) {
        echo "<tr>";
        echo "<td>" . date('Y-m-d', strtotime($record['date'])) . "</td>";
        echo "<td>" . htmlspecialchars($record['employee_id']) . "</td>";
        echo "<td>" . htmlspecialchars($record['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($record['designation']) . "</td>";
        echo "<td>" . htmlspecialchars($record['branch_name']) . "</td>";
        echo "<td>" . ($record['check_in'] ? date('H:i', strtotime($record['check_in'])) : '-') . "</td>";
        echo "<td>" . ($record['check_out'] ? date('H:i', strtotime($record['check_out'])) : '-') . "</td>";
        echo "<td>" . ucfirst($record['status']) . "</td>";
        echo "<td>" . $record['arrival_status'] . "</td>";
        echo "<td>" . htmlspecialchars($record['remarks'] ?? '') . "</td>";
        echo "</tr>";
    }

    echo "
            </tbody>
        </table>
    ";

} catch (Exception $e) {
    // Log error and show generic message
    error_log("Export error: " . $e->getMessage());
    die("Failed to generate report. Please try again later.");
}
