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

    // ============================
    // GET LOGGED USER INFO
    // ============================
    if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        require_once __DIR__ . '/../src/auth.php';
        
        // Check if admin
        if (is_admin()) {
            echo json_encode([
                'id' => 0,
                'name' => 'Admin',
                'user_type' => 'admin',
                'is_admin' => true
            ]);
            exit;
        }
        
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, user_type FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        $user['is_admin'] = false;
        echo json_encode($user);
        exit;
    }

    // ============================
    // GET ALL SLOTS + STATUS
    // ============================
    if ($action === 'slots' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        require_once __DIR__ . '/../src/auth.php';
        $isAdmin = is_admin();
        
        // If admin, include user details
        if ($isAdmin) {
            $stmt = $pdo->query("
                SELECT
                    p.id AS slot_db_id,
                    p.svg_id,
                    COALESCE(p.label, p.slot_number, p.svg_id) AS label,
                    r.user_id,
                    r.status,
                    r.reservation_name,
                    r.vehicle_no,
                    r.reserved_at,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.user_type AS user_type
                FROM parking_slots p
                LEFT JOIN reservations r ON r.slot_id = p.id
                LEFT JOIN users u ON u.id = r.user_id
                ORDER BY p.id;
            ");
        } else {
            $stmt = $pdo->query("
                SELECT
                    p.id AS slot_db_id,
                    p.svg_id,
                    COALESCE(p.label, p.slot_number, p.svg_id) AS label,
                    r.user_id,
                    r.status,
                    r.reservation_name,
                    r.vehicle_no,
                    r.reserved_at
                FROM parking_slots p
                LEFT JOIN reservations r ON r.slot_id = p.id
                ORDER BY p.id;
            ");
        }
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // ============================
    // RESERVE SLOT — WITH ROLE RULES
    // ============================
    if ($action === 'reserve' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $userId = current_user_id();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $slot_svg_id = trim($data['slot_svg_id'] ?? '');
        $reservation_name = trim($data['name'] ?? '');
        $vehicle_id = intval($data['vehicle_id'] ?? 0);
        $vehicle_no = trim($data['vehicle_no'] ?? ''); // Fallback for backwards compatibility

        if ($slot_svg_id === '' || $reservation_name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Name and slot are required']);
            exit;
        }

        // If vehicle_id is provided, use it; otherwise fallback to vehicle_no
        if ($vehicle_id > 0) {
            // Verify vehicle belongs to user
            $stmt = $pdo->prepare("SELECT vehicle_no, vehicle_name FROM vehicles WHERE id = ? AND user_id = ?");
            $stmt->execute([$vehicle_id, $userId]);
            $vehicle = $stmt->fetch();
            if (!$vehicle) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid vehicle selected']);
                exit;
            }
            $vehicle_no = $vehicle['vehicle_no'];
        } elseif ($vehicle_no === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Vehicle is required']);
            exit;
        }

        $pdo->beginTransaction();

        // Fetch slot info with lock
        $stmt = $pdo->prepare("SELECT id, label, is_active FROM parking_slots WHERE svg_id = ? FOR UPDATE");
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
            echo json_encode(['error' => 'Slot inactive']);
            exit;
        }

        $slotId = intval($slot['id']);
        $slotLabel = '';

        // Always derive label from svg_id first (svg_id is the source of truth)
        // This ensures correct mapping regardless of what's in the database label field
        if (preg_match("/^slot-(\d+)$/i", $slot_svg_id, $svgMatch)) {
            $slotNum = intval($svgMatch[1]);
            // Map slot numbers to labels based on SVG structure
            if ($slotNum >= 9 && $slotNum <= 14) {
                $slotLabel = "R" . ($slotNum - 8); // slot-9 = R1, slot-10 = R2, etc.
            } else if ($slotNum >= 20 && $slotNum <= 27) {
                $slotLabel = "R" . ($slotNum - 13); // slot-20 = R7, slot-21 = R8, etc.
            } else if ($slotNum >= 15 && $slotNum <= 19) {
                $slotLabel = "R" . $slotNum; // slot-15 = R15, slot-16 = R16, etc.
            } else if ($slotNum === 8) {
                $slotLabel = "HOD";
            } else if ($slotNum >= 1 && $slotNum <= 7) {
                $slotLabel = "G" . $slotNum; // G1-G7
            }
        }
        
        // If we couldn't derive from svg_id, fall back to database label
        if (empty($slotLabel)) {
            $slotLabel = strtoupper(trim($slot['label'] ?? ''));
        }

        // Get user role
        $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $userRole = strtolower($stmt->fetchColumn());

        // ---- Permission Check ----
        $allowed = false;

        if ($userRole === 'hod') {
            // HOD: Can choose from all slots
            $allowed = true;

        } elseif ($userRole === 'faculty') {
            // Faculty: Can choose from all slots except HOD slot
            if ($slotLabel !== 'HOD') {
                $allowed = true;
            }

        } elseif ($userRole === 'normal') {
            // Normal: Can only choose from R1-R19 slots
            // Check if label matches R1, R2, R3, ..., R19 pattern
            if (preg_match("/^R(\d+)$/i", $slotLabel, $match)) {
                $num = intval($match[1]);
                if ($num >= 1 && $num <= 19) {
                    $allowed = true;
                }
            }
        }

        if (!$allowed) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['error' => 'Not allowed for this slot']);
            exit;
        }

        // Prevent double reservation by same user
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE user_id=? LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'You already reserved a slot']);
            exit;
        }

        // Prevent duplicate slot reservation
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE slot_id=? LIMIT 1");
        $stmt->execute([$slotId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Slot already reserved']);
            exit;
        }

        // Create Reservation (use vehicle_id if available)
        if ($vehicle_id > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (slot_id, reservation_name, user_id, vehicle_id, vehicle_no, status, reserved_at)
                VALUES (?, ?, ?, ?, ?, 'reserved', NOW())
            ");
            $stmt->execute([$slotId, $reservation_name, $userId, $vehicle_id, $vehicle_no]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (slot_id, reservation_name, user_id, vehicle_no, status, reserved_at)
                VALUES (?, ?, ?, ?, 'reserved', NOW())
            ");
            $stmt->execute([$slotId, $reservation_name, $userId, $vehicle_no]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // CHECK-IN
    // ============================
    if ($action === 'checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("UPDATE reservations SET status='checked_in' WHERE user_id=?");
        $stmt->execute([current_user_id()]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // CANCEL RESERVATION
    // ============================
    if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id=?");
        $stmt->execute([current_user_id()]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // GET USER VEHICLES
    // ============================
    if ($action === 'vehicles' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, vehicle_name, vehicle_no, created_at FROM vehicles WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // ============================
    // ADD VEHICLE
    // ============================
    if ($action === 'add_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $vehicle_name = trim($data['vehicle_name'] ?? '');
        $vehicle_no = trim($data['vehicle_no'] ?? '');

        if ($vehicle_name === '' || $vehicle_no === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Vehicle name and number are required']);
            exit;
        }

        // Check vehicle limit (max 3)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id = ?");
        $stmt->execute([$uid]);
        $count = $stmt->fetchColumn();
        if ($count >= 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Maximum 3 vehicles allowed']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO vehicles (user_id, vehicle_name, vehicle_no, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$uid, $vehicle_name, $vehicle_no]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // ============================
    // DELETE VEHICLE
    // ============================
    if ($action === 'delete_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $vehicle_id = intval($data['vehicle_id'] ?? 0);

        if (!$vehicle_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Vehicle ID required']);
            exit;
        }

        // Check vehicle belongs to user
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND user_id = ?");
        $stmt->execute([$vehicle_id, $uid]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Vehicle not found or access denied']);
            exit;
        }

        // Check if vehicle has active reservation
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE vehicle_id = ? AND status IN ('reserved', 'checked_in') LIMIT 1");
        $stmt->execute([$vehicle_id]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete vehicle with active reservation']);
            exit;
        }

        // Check vehicle count - must have at least 1
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id = ?");
        $stmt->execute([$uid]);
        $count = $stmt->fetchColumn();
        if ($count <= 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Must have at least one vehicle']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
        $stmt->execute([$vehicle_id, $uid]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // ADMIN: GET SLOT INFO (for hover tooltip)
    // ============================
    if ($action === 'admin_slot_info' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        require_once __DIR__ . '/../src/auth.php';
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }

        $slot_svg_id = $_GET['slot_svg_id'] ?? '';
        if (empty($slot_svg_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'slot_svg_id required']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT 
                r.user_id,
                r.reservation_name,
                r.vehicle_id,
                r.vehicle_no,
                r.status,
                r.reserved_at,
                u.name AS user_name,
                u.email AS user_email,
                u.user_type,
                v.vehicle_name
            FROM parking_slots p
            LEFT JOIN reservations r ON r.slot_id = p.id
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN vehicles v ON v.id = r.vehicle_id
            WHERE p.svg_id = ?
            LIMIT 1
        ");
        $stmt->execute([$slot_svg_id]);
        $info = $stmt->fetch();

        if (!$info || !$info['user_id']) {
            echo json_encode(['reserved' => false]);
            exit;
        }

        echo json_encode([
            'reserved' => true,
            'user_name' => $info['user_name'],
            'user_email' => $info['user_email'],
            'user_type' => $info['user_type'],
            'reservation_name' => $info['reservation_name'],
            'vehicle_no' => $info['vehicle_no'],
            'vehicle_name' => $info['vehicle_name'],
            'status' => $info['status'],
            'reserved_at' => $info['reserved_at']
        ]);
        exit;
    }

    // ============================
    // ADMIN: FREE SLOT
    // ============================
    if ($action === 'admin_free_slot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../src/auth.php';
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $slot_id = intval($data['slot_id'] ?? 0);

        if (!$slot_id) {
            http_response_code(400);
            echo json_encode(['error' => 'slot_id required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM reservations WHERE slot_id = ?");
        $stmt->execute([$slot_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // ADMIN: DELETE USER
    // ============================
    if ($action === 'admin_delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../src/auth.php';
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $user_id = intval($data['user_id'] ?? 0);

        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id required']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Delete user's reservations first
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user's vehicles
            $stmt = $pdo->prepare("DELETE FROM vehicles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
        exit;
    }

    // ============================
    // DELETE OWN ACCOUNT
    // ============================
    if ($action === 'delete_account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../src/auth.php';
        
        // Admin cannot delete their account this way
        if (is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin account cannot be deleted this way']);
            exit;
        }
        
        $user_id = current_user_id();
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Delete user's reservations first
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user's vehicles
            $stmt = $pdo->prepare("DELETE FROM vehicles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            
            // Destroy session and logout properly
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time()-42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            echo json_encode(['success' => true, 'redirect' => '/IT-PARKING-MANAGEMENT/public/login.php']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete account: ' . $e->getMessage()]);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
}
