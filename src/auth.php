<?php
// src/auth.php
require_once __DIR__ . '/db.php';

// start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Backwards-compatible DB accessor:
 * src/db.php exposes db() helper and $pdo; some older code may call getPDO().
 * Provide getPDO() wrapper for compatibility.
 */
if (!function_exists('getPDO')) {
    function getPDO() {
        return db(); // db() defined in src/db.php
    }
}

/**
 * Attempt login. Returns user row on success, false on failure.
 * @param string $email
 * @param string $password
 * @return array|false
 */
function attempt_login(string $email, string $password) {
    $pdo = getPDO();
    // include role_id because other pages (dashboard) check $_SESSION['role_id']
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash, user_type, role_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        // successful — set consistent session keys used across the app
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_type']  = $user['user_type'] ?? 'normal'; // 'normal'|'faculty'|'hod'
        $_SESSION['role_id']    = isset($user['role_id']) ? (int)$user['role_id'] : 2; // default 2 = user

        // return user (without the password hash for safety)
        unset($user['password_hash']);
        return $user;
    }
    return false;
}

/**
 * Require login — redirect to login page if missing
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /IT-PARKING-MANAGEMENT/public/login.php');
        exit;
    }
}

/**
 * Logout — destroy session and cookie
 */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Backwards-compatible wrapper: allow either require_login() or requireLogin()
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        return require_login();
    }
}
