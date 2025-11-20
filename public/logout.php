<?php
// public/logout.php

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

// Log logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'logged out');
}

// Destroy the session securely
logout();

// Redirect to login page
header('Location: /IT-PARKING-MANAGEMENT/public/login.php');
exit;
