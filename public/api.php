<?php
// public/api.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../src/db.php';

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'slots';

try {

    // --------------------------------------------------------
    //  GET USER INFO
    // --------------------------------------------------------
    if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, user_type, role_id FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetch());
        exit;
    }

    // --------------------------------------------------------
    //  GET SLOT STATUS + RESERVATIONS
    // --------------------------------------------------------
    if ($action === 'slots' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("
            SELECT 
                p.id AS slot_db_id,
                p.svg_id,
                COALESCE(p.label, p.slot_number, p.svg_id) AS label,
                p.access_group,
                r.user_id,
                r.status,
                r.reservation_name,
                r.vehicle_no,
                r.reserved_at
            FROM parking_slots p
            LEFT JOIN reservations r ON r.slot_id = p.id
            ORDER BY p.id
        ");

        echo json_encode($stmt->fetchAll());
        exit;
    }

    // --------------------------------------------------------
    //  RESERVE SLOT + APPLY ROLE PERMISSIONS
    // --------------------------------------------------------
    if ($action === 'reserve' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $userId = current_user_id();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $slot_svg_id      = trim($data['slot_svg_id'] ?? '');
        $reservation_name = trim($data['name'] ?? '');
        $vehicle_no       = trim($data['vehicle_no'] ?? '');

        if ($slot_svg_id === '' || $reservation_name === '' || $vehicle_no === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name, Vehicle No & Slot required']);
            exit;
        }

        $pdo->beginTransaction();

        // Fetch slot info
        $stmt = $pdo->prepare("SELECT id, access_group, label, is_active FROM parking_slots WHERE svg_id = ? FOR UPDATE");
        $stmt->execute([$slot_svg_id]);
        $slot = $stmt->fetch();

        if (!$slot) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Slot not found']);
            exit;
        }

        if (!$slot['is_active']) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Slot inactive']);
            exit;
        }

        $slotId = (int)$slot['id'];
        $slotLabel = strtoupper($slot['label']);
        $slotGroup = strtolower($slot['access_group']);

        // Fetch user role
        $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $userRole = strtolower($stmt->fetchColumn());

        // ---------------- PERMISSION RULES ----------------
        $allowed = false;

        if ($userRole === 'hod') {
            $allowed = true; // HOD gets all slots

        } elseif ($userRole === 'faculty') {
            // Faculty → everything except HOD slot
            if ($slotLabel !== 'HOD') {
                $allowed = true;
            }

        } elseif ($userRole === 'normal') {
            // Normal users → ONLY R slots
            if (preg_match("/^R[0-9]+$/", $slotLabel)) {
                $allowed = true;
            }
        }

        if (!$allowed) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode([
                'error' => 'You are not allowed to reserve this parking slot'
            ]);
            exit;
        }

        // Ensure user has no existing reservation
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'You already have a reservation']);
            exit;
        }

        // Ensure slot is not already taken
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE slot_id = ?");
        $stmt->execute([$slotId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Slot already reserved']);
            exit;
        }

        // Create reservation
        $stmt = $pdo->prepare("
            INSERT INTO reservations (slot_id, reservation_name, user_id, vehicle_no, status, reserved_at)
            VALUES (?, ?, ?, ?, 'reserved', NOW())
        ");
        $stmt->execute([$slotId, $reservation_name, $userId, $vehicle_no]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // --------------------------------------------------------
    //  CHECK-IN
    // --------------------------------------------------------
    if ($action === 'checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = current_user_id();
        if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }

        $stmt = $pdo->prepare("UPDATE reservations SET status = 'checked_in' WHERE user_id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // --------------------------------------------------------
    //  CANCEL RESERVATION
    // --------------------------------------------------------
    if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = current_user_id();
        if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }

        $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
