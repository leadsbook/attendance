<?php

require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth(getDBConnection());
$auth->requireAdmin();

$db = getDBConnection();

// Get current settings
$stmt = $db->query("SELECT * FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get SMTP test result if any
$smtpTestResult = $_SESSION['smtp_test_result'] ?? null;
unset($_SESSION['smtp_test_result']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="admin-content">
            <div class="page-header">
                <h1>System Settings</h1>
            </div>

            <div class="settings-container">
                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <button class="nav-item active" data-target="general">
                        General Settings
                    </button>
                    <button class="nav-item" data-target="email">
                        Email Settings
                    </button>
                    <button class="nav-item" data-target="attendance">
                        Attendance Settings
                    </button>
                    <button class="nav-item" data-target="notifications">
                        Notification Settings
                    </button>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- General Settings -->
                    <div class="settings-section active" id="general">
                        <h2>General Settings</h2>
                        <form class="settings-form" data-section="general">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label for="companyName">Company Name</label>
                                <input type="text" id="companyName" name="company_name" 
                                       value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="timezone">Default Timezone</label>
                                <select id="timezone" name="default_timezone" required>
                                    <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                                        <option value="<?php echo $tz; ?>" 
                                            <?php echo ($settings['default_timezone'] ?? '') === $tz ? 'selected' : ''; ?>>
                                            <?php echo $tz; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="dateFormat">Date Format</label>
                                <select id="dateFormat" name="date_format" required>
                                    <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>
                                        YYYY-MM-DD
                                    </option>
                                    <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>
                                        DD/MM/YYYY
                                    </option>
                                    <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>
                                        MM/DD/YYYY
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="timeFormat">Time Format</label>
                                <select id="timeFormat" name="time_format" required>
                                    <option value="H:i" <?php echo ($settings['time_format'] ?? '') === 'H:i' ? 'selected' : ''; ?>>
                                        24 Hour (HH:MM)
                                    </option>
                                    <option value="h:i A" <?php echo ($settings['time_format'] ?? '') === 'h:i A' ? 'selected' : ''; ?>>
                                        12 Hour (HH:MM AM/PM)
                                    </option>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Email Settings -->
                    <div class="settings-section" id="email">
                        <h2>Email Settings</h2>
                        <form class="settings-form" data-section="email">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label for="smtpHost">SMTP Host</label>
                                <input type="text" id="smtpHost" name="smtp_host" 
                                       value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="smtpPort">SMTP Port</label>
                                <input type="number" id="smtpPort" name="smtp_port" 
                                       value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="smtpUsername">SMTP Username</label>
                                <input type="text" id="smtpUsername" name="smtp_username" 
                                       value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="smtpPassword">SMTP Password</label>
                                <input type="password" id="smtpPassword" name="smtp_password" 
                                       value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="fromEmail">From Email</label>
                                <input type="email" id="fromEmail" name="from_email" 
                                       value="<?php echo htmlspecialchars($settings['from_email'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="fromName">From Name</label>
                                <input type="text" id="fromName" name="from_name" 
                                       value="<?php echo htmlspecialchars($settings['from_name'] ?? ''); ?>" required>
                            </div>

                            <?php if ($smtpTestResult): ?>
                                <div class="alert alert-<?php echo $smtpTestResult['success'] ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars($smtpTestResult['message']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <button type="button" class="btn btn-secondary" id="testEmail">Test Email</button>
                            </div>
                        </form>
                    </div>

                    <!-- Attendance Settings -->
                    <div class="settings-section" id="attendance">
                        <h2>Attendance Settings</h2>
                        <form class="settings-form" data-section="attendance">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label for="locationAccuracy">Required Location Accuracy (meters)</label>
                                <input type="number" id="locationAccuracy" name="location_accuracy" 
                                       value="<?php echo htmlspecialchars($settings['location_accuracy'] ?? '100'); ?>" 
                                       min="10" max="1000" required>
                            </div>

                            <div class="form-group">
                                <label for="selfieRequired">
                                    <input type="checkbox" id="selfieRequired" name="selfie_required" 
                                           <?php echo ($settings['selfie_required'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    Require Selfie for Attendance
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="defaultGracePeriod">Default Grace Period (minutes)</label>
                                <input type="number" id="defaultGracePeriod" name="default_grace_period" 
                                       value="<?php echo htmlspecialchars($settings['default_grace_period'] ?? '15'); ?>" 
                                       min="0" max="120" required>
                            </div>

                            <div class="form-group">
                                <label for="defaultHalfDay">Default Half Day Minutes</label>
                                <input type="number" id="defaultHalfDay" name="default_half_day_minutes" 
                                       value="<?php echo htmlspecialchars($settings['default_half_day_minutes'] ?? '240'); ?>" 
                                       min="0" max="480" required>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Notification Settings -->
                    <div class="settings-section" id="notifications">
                        <h2>Notification Settings</h2>
                        <form class="settings-form" data-section="notifications">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label>Email Notifications</label>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="notify_attendance" 
                                               <?php echo ($settings['notify_attendance'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                        Late Attendance
                                    </label>
                                    <label>
                                        <input type="checkbox" name="notify_leave_request" 
                                               <?php echo ($settings['notify_leave_request'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        Leave Requests
                                    </label>
                                    <label>
                                        <input type="checkbox" name="notify_leave_approval" 
                                               <?php echo ($settings['notify_leave_approval'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                        Leave Approvals
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notifyAdmins">Notify Admins</label>
                                <select id="notifyAdmins" name="notify_admins[]" multiple>
                                    <?php
                                    $adminEmails = $db->query("
                                        SELECT u.id, ep.email, ep.full_name 
                                        FROM users u 
                                        JOIN employee_profiles ep ON u.id = ep.user_id 
                                        WHERE u.role = 'admin'
                                        ORDER BY ep.full_name
                                    ")->fetchAll();

                                    $selectedAdmins = explode(',', $settings['notify_admins'] ?? '');
                                    foreach ($adminEmails as $admin):
                                    ?>
                                        <option value="<?php echo $admin['id']; ?>" 
                                            <?php echo in_array($admin['id'], $selectedAdmins) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($admin['full_name'] . ' (' . $admin['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin/settings.js"></script>
</body>
</html>