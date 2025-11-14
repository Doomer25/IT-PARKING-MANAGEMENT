<?php
require_once __DIR__ . '/../../src/auth.php';
requireLogin();
requireRole('admin');
require_once __DIR__ . '/../../src/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$svg_id = $input['svg_id'] ?? null;
$slot_id = intval($input['slot_id'] ?? 0);
if (!$svg_id || !$slot_id) {
    echo json_encode(['success'=>false,'message'=>'svg_id and slot_id required']);
    exit;
}
$pdo = getPDO();
$stmt = $pdo->prepare("UPDATE parking_slots SET svg_id = ? WHERE id = ?");
$stmt->execute([$svg_id, $slot_id]);
echo json_encode(['success'=>true]);
