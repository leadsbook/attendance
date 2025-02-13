<?php

require_once '../../config/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Initialize authentication
$auth = new Auth(getDBConnection());
$auth->requireAuth();

// Verify CSRF token
if (!validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $db = getDBConnection();
    
    // Get user's branch settings
    $stmt = $db->prepare("
        SELECT bs.*, b.latitude as branch_lat, b.longitude as branch_long 
        FROM branch_settings bs 
        JOIN branches b ON b.id = bs.branch_id 
        JOIN employee_profiles ep ON ep.branch_id = bs.branch_id 
        WHERE ep.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $branchSettings = $stmt->fetch();

    // Validate attendance timing
    $currentTime = new DateTime('now', new DateTimeZone(TIMEZONE));
    $shiftStartTime = new DateTime($branchSettings['shift_start_time'], new DateTimeZone(TIMEZONE));
    $gracePeriod = new DateInterval('PT' . $branchSettings['grace_period_minutes'] . 'M');
    $shiftStartTime->add($gracePeriod);

    // Check if attendance already marked
    $stmt = $db->prepare("
        SELECT id FROM attendance 
        WHERE user_id = ? AND date = CURRENT_DATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('Attendance already marked for today');
    }

    // Validate location
    $userLat = floatval($_POST['latitude']);
    $userLong = floatval($_POST['longitude']);
    $accuracy = floatval($_POST['accuracy']);
    
    if ($accuracy > LOCATION_ACCURACY) {
        throw new Exception('Location accuracy is too low. Please try again with better GPS signal');
    }

    // Calculate distance from branch (using Haversine formula)
    $distance = calculateDistance(
        $userLat, 
        $userLong, 
        $branchSettings['branch_lat'], 
        $branchSettings['branch_long']
    );

    if ($distance > 100) { // 100 meters radius
        throw new Exception('You are too far from the office to mark attendance');
    }

    // Process and save photo
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Photo is required for attendance');
    }

    $photo = $_FILES['photo'];
    if (!in_array($photo['type'], ALLOWED_IMAGE_TYPES)) {
        throw new Exception('Invalid image format');
    }

    if ($photo['size'] > MAX_FILE_SIZE) {
        throw new Exception('Photo size is too large');
    }

    // Generate unique filename
    $photoFilename = uniqid() . '_' . $_SESSION['user_id'] . '.jpg';
    $photoPath = UPLOAD_DIR . 'attendance/' . $photoFilename;

    if (!move_uploaded_file($photo['tmp_name'], $photoPath)) {
        throw new Exception('Failed to save photo');
    }

    // Determine attendance status
    $status = 'present';
    if ($currentTime > $shiftStartTime) {
        $lateBy = $currentTime->diff($shiftStartTime);
        $lateMinutes = ($lateBy->h * 60) + $lateBy->i;
        
        if ($lateMinutes > $branchSettings['half_day_after_minutes']) {
            $status = 'half-day';
        }
    }

    // Record attendance
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO attendance (
            user_id, 
            date, 
            check_in, 
            status, 
            selfie_path, 
            location_lat, 
            location_long
        ) VALUES (?, CURRENT_DATE(), NOW(), ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $status,
        $photoFilename,
        $userLat,
        $userLong
    ]);

    // Log the activity
    $stmt = $db->prepare("
        INSERT INTO audit_logs (
            user_id, 
            action, 
            table_name, 
            record_id
        ) VALUES (?, 'mark_attendance', 'attendance', LAST_INSERT_ID())
    ");
    $stmt->execute([$_SESSION['user_id']]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Attendance marked successfully',
        'status' => $status
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Delete uploaded photo if it exists
    if (isset($photoPath) && file_exists($photoPath)) {
        unlink($photoPath);
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper function to calculate distance between two points
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth's radius in meters

    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $latDelta = $lat2 - $lat1;
    $lonDelta = $lon2 - $lon1;

    $a = sin($latDelta/2) * sin($latDelta/2) +
         cos($lat1) * cos($lat2) *
         sin($lonDelta/2) * sin($lonDelta/2);
         
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earthRadius * $c; // Distance in meters
}