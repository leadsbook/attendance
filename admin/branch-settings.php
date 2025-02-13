<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth(getDBConnection());
$auth->requireAdmin();

$db = getDBConnection();

// Get all branches with their settings
$branches = $db->query("
    SELECT 
        b.*,
        bs.shift_start_time,
        bs.shift_end_time,
        bs.grace_period_minutes,
        bs.half_day_after_minutes,
        (
            SELECT COUNT(*)
            FROM weekly_offs wo
            WHERE wo.branch_id = b.id
        ) as weekly_offs_count,
        (
            SELECT COUNT(*)
            FROM holidays h
            WHERE h.branch_id = b.id
            AND h.date >= CURRENT_DATE()
        ) as upcoming_holidays_count
    FROM branches b
    LEFT JOIN branch_settings bs ON b.id = bs.branch_id
    ORDER BY b.name ASC
")->fetchAll();

// Get time zones for dropdown
$timezones = DateTimeZone::listIdentifiers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Settings - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="admin-content">
            <div class="page-header">
                <h1>Branch Settings</h1>
                <button class="btn btn-primary" id="addBranchBtn">Add Branch</button>
            </div>

            <div class="branches-grid">
                <?php foreach ($branches as $branch): ?>
                    <div class="branch-card" data-id="<?php echo $branch['id']; ?>">
                        <div class="branch-header">
                            <h2><?php echo htmlspecialchars($branch['name']); ?></h2>
                            <div class="branch-actions">
                                <button class="btn btn-icon edit-branch" title="Edit Branch">✏️</button>
                                <button class="btn btn-icon manage-holidays" title="Manage Holidays">📅</button>
                                <button class="btn btn-icon manage-weekly-offs" title="Manage Weekly Offs">📆</button>
                            </div>
                        </div>

                        <div class="branch-info">
                            <div class="info-item">
                                <span class="info-label">Location:</span>
                                <span class="info-value"><?php echo htmlspecialchars($branch['location']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Timezone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($branch['timezone']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Working Hours:</span>
                                <span class="info-value">
                                    <?php 
                                    echo $branch['shift_start_time'] ? 
                                        date('h:i A', strtotime($branch['shift_start_time'])) . ' - ' . 
                                        date('h:i A', strtotime($branch['shift_end_time'])) : 
                                        'Not set';
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Grace Period:</span>
                                <span class="info-value">
                                    <?php echo $branch['grace_period_minutes'] ?? 0; ?> minutes
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Half Day After:</span>
                                <span class="info-value">
                                    <?php echo $branch['half_day_after_minutes'] ?? 0; ?> minutes
                                </span>
                            </div>
                        </div>

                        <div class="branch-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $branch['weekly_offs_count']; ?></span>
                                <span class="stat-label">Weekly Offs</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $branch['upcoming_holidays_count']; ?></span>
                                <span class="stat-label">Upcoming Holidays</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Branch Modal -->
    <div id="branchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Branch</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="branchForm">
                <input type="hidden" name="branch_id" id="branchId">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h3>Basic Information</h3>
                        <div class="form-group">
                            <label for="branchName">Branch Name</label>
                            <input type="text" id="branchName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" required>
                        </div>
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone" required>
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo $tz; ?>">
                                        <?php echo $tz; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Work Schedule -->
                    <div class="form-section">
                        <h3>Work Schedule</h3>
                        <div class="form-group">
                            <label for="shiftStart">Shift Start Time</label>
                            <input type="time" id="shiftStart" name="shift_start_time" required>
                        </div>
                        <div class="form-group">
                            <label for="shiftEnd">Shift End Time</label>
                            <input type="time" id="shiftEnd" name="shift_end_time" required>
                        </div>
                        <div class="form-group">
                            <label for="gracePeriod">Grace Period (minutes)</label>
                            <input type="number" id="gracePeriod" name="grace_period_minutes" 
                                   min="0" max="120" required>
                        </div>
                        <div class="form-group">
                            <label for="halfDayMinutes">Half Day After (minutes)</label>
                            <input type="number" id="halfDayMinutes" name="half_day_after_minutes" 
                                   min="0" max="480" required>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="branchManager.closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">Save Branch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Weekly Offs Modal -->
    <div id="weeklyOffsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Weekly Offs</h2>
                <button class="close-modal">&times;</button>
            </div>
            <form id="weeklyOffsForm">
                <input type="hidden" name="branch_id" id="weeklyOffsBranchId">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="weekly-offs-grid">
                    <?php
                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $index => $day):
                    ?>
                        <div class="day-item">
                            <label>
                                <input type="checkbox" name="weekly_offs[]" value="<?php echo $index; ?>">
                                <?php echo $day; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="branchManager.closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">Save Weekly Offs</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Holidays Modal -->
    <div id="holidaysModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Holidays</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="holidays-container">
                <form id="addHolidayForm">
                    <input type="hidden" name="branch_id" id="holidaysBranchId">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="holidayName">Holiday Name</label>
                            <input type="text" id="holidayName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="holidayDate">Date</label>
                            <input type="date" id="holidayDate" name="date" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Holiday</button>
                    </div>
                </form>

                <div class="holidays-list">
                    <!-- Holidays will be loaded here dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin/branch-settings.js"></script>
</body>
</html>