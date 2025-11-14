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
