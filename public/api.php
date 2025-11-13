<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/db.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_slot_status') {
    $pdo = getPDO();
    // load all slots
    $all = $pdo->query("SELECT id, svg_id FROM parking_slots")->fetchAll();
    $map = [];
    foreach ($all as $s) {
        $key = $s['svg_id'] ?: 'slot-'.$s['id'];
        $map[$key] = ['id'=>$s['id'],'status'=>'available','vehicle_number'=>null];
    }
    // load active reservations
    $stmt = $pdo->query("SELECT r.parking_slot_id, r.status, r.vehicle_number, s.svg_id
                         FROM reservations r
                         JOIN parking_slots s ON r.parking_slot_id = s.id
                         WHERE r.status IN ('booked','checked_in')");
    foreach ($stmt->fetchAll() as $r) {
        $key = $r['svg_id'] ?: 'slot-'.$r['parking_slot_id'];
        $map[$key] = ['id'=>$r['parking_slot_id'],'status'=>$r['status'],'vehicle_number'=>$r['vehicle_number']];
    }
    echo json_encode(['slots'=>$map]);
    exit;
}

if ($action === 'reserve_slot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $svgId = $input['svg_id'] ?? null;
    $vehicle = $input['vehicle_number'] ?? 'UNKNOWN';
    if (!$svgId) { echo json_encode(['success'=>false,'message'=>'svg_id required']); exit; }
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM parking_slots WHERE svg_id = ?");
    $stmt->execute([$svgId]);
    $slot = $stmt->fetch();
    if (!$slot) { echo json_encode(['success'=>false,'message'=>'slot not found']); exit; }
    $slotId = $slot['id'];

    try {
        $pdo->beginTransaction();
        // lock any reservations for this slot
        $check = $pdo->prepare("SELECT id FROM reservations WHERE parking_slot_id = ? AND status IN ('booked','checked_in') FOR UPDATE");
        $check->execute([$slotId]);
        if ($check->fetch()) { $pdo->rollBack(); echo json_encode(['success'=>false,'message'=>'already reserved']); exit; }

        $now = (new DateTime())->format('Y-m-d H:i:s');
        $end = (new DateTime('+2 hours'))->format('Y-m-d H:i:s');
        $ins = $pdo->prepare("INSERT INTO reservations (user_id, vehicle_number, parking_slot_id, parking_lot_id, start_time, end_time, status, created_at)
                              VALUES (NULL, ?, ?, 1, ?, ?, 'booked', NOW())");
        $ins->execute([$vehicle, $slotId, $now, $end]);
        $id = $pdo->lastInsertId();
        $pdo->commit();
        echo json_encode(['success'=>true,'reservation_id'=>$id]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'checkin_slot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $svgId = $input['svg_id'] ?? null;
    $vehicle = $input['vehicle_number'] ?? 'UNKNOWN';
    if (!$svgId) { echo json_encode(['success'=>false,'message'=>'svg_id required']); exit; }
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM parking_slots WHERE svg_id = ?");
    $stmt->execute([$svgId]);
    $s = $stmt->fetch(); if (!$s) { echo json_encode(['success'=>false,'message'=>'slot not found']); exit; }
    $slotId = $s['id'];

    try {
        $pdo->beginTransaction();
        $sel = $pdo->prepare("SELECT id FROM reservations WHERE parking_slot_id = ? AND status IN ('booked','checked_in') LIMIT 1 FOR UPDATE");
        $sel->execute([$slotId]);
        $r = $sel->fetch();
        if ($r) {
            $upd = $pdo->prepare("UPDATE reservations SET status='checked_in', vehicle_number = ? WHERE id = ?");
            $upd->execute([$vehicle, $r['id']]);
            $pdo->commit();
            echo json_encode(['success'=>true,'reservation_id'=>$r['id']]);
        } else {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $end = (new DateTime('+2 hours'))->format('Y-m-d H:i:s');
            $ins = $pdo->prepare("INSERT INTO reservations (user_id, vehicle_number, parking_slot_id, parking_lot_id, start_time, end_time, status, created_at)
                                  VALUES (NULL, ?, ?, 1, ?, ?, 'checked_in', NOW())");
            $ins->execute([$vehicle, $slotId, $now, $end]);
            $id = $pdo->lastInsertId();
            $pdo->commit();
            echo json_encode(['success'=>true,'reservation_id'=>$id]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'release_slot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $svgId = $input['svg_id'] ?? null;
    if (!$svgId) { echo json_encode(['success'=>false,'message'=>'svg_id required']); exit; }
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM parking_slots WHERE svg_id = ?");
    $stmt->execute([$svgId]);
    $s = $stmt->fetch(); if (!$s) { echo json_encode(['success'=>false,'message'=>'slot not found']); exit; }
    $slotId = $s['id'];
    $upd = $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE parking_slot_id = ? AND status IN ('booked','checked_in')");
    $upd->execute([$slotId]);
    echo json_encode(['success'=>true,'affected'=>$upd->rowCount()]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'unknown action']);
