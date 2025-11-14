<?php
require_once __DIR__ . '/../../src/auth.php';
requireLogin();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = $_POST['svg_text'] ?? '';
    $destDir = __DIR__ . '/../assets';
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);
    file_put_contents($destDir . '/map.svg', $text);
}
header('Location: /admin/map_editor.php');
exit;
