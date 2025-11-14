<?php
require_once __DIR__ . '/../src/db.php';
$pdo = getPDO();
$name='Admin';
$email='admin@local';
$pass='admin123';
$hash = password_hash($pass, PASSWORD_DEFAULT);
// role_id = 1 => admin
$stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role_id,user_type,created_at) VALUES (?, ?, ?, 1, 'hod', NOW())");
$stmt->execute([$name,$email,$hash]);
echo "Admin created: $email / $pass\n";
