<?php
// public/logout.php

require_once __DIR__ . '/../src/auth.php';

// Destroy the session securely
logout();

// Redirect to login page
header('Location: /IT-PARKING-MANAGEMENT/public/login.php');
exit;
