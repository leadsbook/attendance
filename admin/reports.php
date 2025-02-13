<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth(getDBConnection());
$auth->requireAdmin();

$db = getDBConnection();

// Get all branches for filter
$branches = $db->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();

// Get departments for filter
$departments = $db->query("
    SELECT DISTINCT department 
    FROM employee_profiles 
    WHERE department IS NOT NULL 
    ORDER BY department
")->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="admin-content">
            <div class="page-header">
                <h1>Reports</h1>
            </div>

            <div class="reports-container">
                <!-- Report Type Selection -->
                <div class="report-selection">
                    <div class="report-types">
                        <button class="report-type active" data-type="attendance">
                            Attendance Report
                        </button>
                        <button class="report-type" data-type="leave">
                            Leave Report
                        </button>
                        <button class="report-type" data-type="performance">
                            Performance Report
                        </button>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="report-filters">
                    <form id="reportFilters" class="filters-form">
                        <input type="hidden" name="report_type" id="reportType" value="attendance">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="filters-grid">
                            <!-- Date Range -->
                            <div class="filter-group">
                                <label for="dateRange">Date Range</label>
                                <select id="dateRange" name="date_range">
                                    <option value="current_month">Current Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="last_3_months">Last 3 Months</option>
                                    <option value="last_6_months">Last 6 Months</option>
                                    <option value="current_year">Current Year</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>

                            <!-- Custom Date Range (initially hidden) -->
                            <div class="filter-group custom-dates" style="display: none;">
                                <div class="date-inputs">
                                    <div>
                                        <label for="startDate">Start Date</label>
                                        <input type="date" id="startDate" name="start_date">
                                    </div>
                                    <div>
                                        <label for="endDate">End Date</label>
                                        <input type="date" id="endDate" name="end_date">
                                    </div>
                                </div>
                            </div>

                            <!-- Branch -->
                            <div class="filter-group">
                                <label for="branch">Branch</label>
                                <select id="branch" name="branch_id">
                                    <option value="">All Branches</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>">
                                            <?php echo htmlspecialchars($branch['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Department -->
                            <div class="filter-group">
                                <label for="department">Department</label>
                                <select id="department" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo htmlspecialchars($department); ?>">
                                            <?php echo htmlspecialchars($department); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Employee Search -->
                            <div class="filter-group">
                                <label for="employee">Employee</label>
                                <input type="text" id="employee" name="employee" 
                                       placeholder="Search by name or ID">
                                <div id="employeeResults" class="search-results"></div>
                            </div>
                        </div>

                        <div class="filters-actions">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                            <button type="button" class="btn btn-secondary" id="exportReport">
                                Export to Excel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Report Preview -->
                <div class="report-preview">
                    <!-- Loading Indicator -->
                    <div id="loadingIndicator" class="loading-spinner" style="display: none;">
                        Loading report...
                    </div>

                    <!-- Report Content -->
                    <div id="reportContent"></div>

                    <!-- Charts Container -->
                    <div id="reportCharts" class="charts-container">
                        <div class="chart-grid">
                            <div class="chart-container">
                                <canvas id="mainChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <canvas id="secondaryChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Data Tables -->
                    <div id="reportTables" class="tables-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Templates -->
    <template id="attendanceReportTemplate">
        <div class="report-section">
            <h2>Attendance Summary</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Present</h3>
                    <div class="summary-value">{{present_count}}</div>
                    <div class="summary-percentage">{{present_percentage}}%</div>
                </div>
                <div class="summary-card">
                    <h3>Absent</h3>
                    <div class="summary-value">{{absent_count}}</div>
                    <div class="summary-percentage">{{absent_percentage}}%</div>
                </div>
                <div class="summary-card">
                    <h3>Late Arrivals</h3>
                    <div class="summary-value">{{late_count}}</div>
                    <div class="summary-percentage">{{late_percentage}}%</div>
                </div>
                <div class="summary-card">
                    <h3>Early Departures</h3>
                    <div class="summary-value">{{early_departure_count}}</div>
                    <div class="summary-percentage">{{early_departure_percentage}}%</div>
                </div>
            </div>
        </div>
    </template>

    <template id="leaveReportTemplate">
        <div class="report-section">
            <h2>Leave Summary</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Total Leaves</h3>
                    <div class="summary-value">{{total_leaves}}</div>
                </div>
                <div class="summary-card">
                    <h3>Emergency Leaves</h3>
                    <div class="summary-value">{{emergency_leaves}}</div>
                </div>
                <div class="summary-card">
                    <h3>Privilege Leaves</h3>
                    <div class="summary-value">{{privilege_leaves}}</div>
                </div>
                <div class="summary-card">
                    <h3>Average Duration</h3>
                    <div class="summary-value">{{average_duration}} days</div>
                </div>
            </div>
        </div>
    </template>

    <template id="performanceReportTemplate">
        <div class="report-section">
            <h2>Performance Summary</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Attendance Score</h3>
                    <div class="summary-value">{{attendance_score}}/100</div>
                </div>
                <div class="summary-card">
                    <h3>Punctuality Score</h3>
                    <div class="summary-value">{{punctuality_score}}/100</div>
                </div>
                <div class="summary-card">
                    <h3>Leave Utilization</h3>
                    <div class="summary-value">{{leave_utilization}}%</div>
                </div>
                <div class="summary-card">
                    <h3>Overall Rating</h3>
                    <div class="summary-value">{{overall_rating}}/5</div>
                </div>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin/reports.js"></script>
</body>
</html>