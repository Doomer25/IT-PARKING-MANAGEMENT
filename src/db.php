<?php
// src/db.php
// Central database connection file for IT-PARKING-MANAGEMENT.

// XAMPP default MySQL settings
$db_host = '127.0.0.1';
$db_name = 'parking_db';
$db_user = 'root';
$db_pass = ''; // default empty for XAMPP

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Exception $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'error'   => 'DB connection failed',
        'message' => $e->getMessage(),
    ]);
    exit;
}

/**
 * Preferred helper: db()
 * Use: $pdo = db();
 */
function db() {
    global $pdo;
    return $pdo;
}

/**
 * Backwards-compatible helper: getPDO()
 * Some older files (like register.php) call getPDO().
 * This wrapper simply returns the same PDO instance as db().
 */
if (!function_exists('getPDO')) {
    function getPDO() {
        return db();
    }
}

/**
 * Log activity to activity_logs table
 * @param int|null $user_id User ID (null for system/admin actions)
 * @param string $action Action description (e.g., "reserved slot", "logged in")
 * @param string|array|null $details Additional details (will be JSON encoded if array)
 * @return bool Success status
 */
function log_activity($user_id, $action, $details = null) {
    try {
        $pdo = db();
        
        // Convert details to JSON string if array
        $details_json = null;
        if ($details !== null) {
            if (is_array($details)) {
                $details_json = json_encode($details);
            } else {
                $details_json = (string)$details;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $details_json]);
        return true;
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}