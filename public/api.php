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
                    r.vehicle_id,
                    r.vehicle_no,
                    r.reserved_at,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.user_type AS user_type,
                    v.vehicle_name
                FROM parking_slots p
                LEFT JOIN reservations r ON r.slot_id = p.id
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN vehicles v ON v.id = r.vehicle_id
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
        $reservation_name = trim($data['name'] ?? ''); // Optional, will use user's name if not provided
        $vehicle_id = intval($data['vehicle_id'] ?? 0);
        $reservation_start_time = trim($data['reservation_start_time'] ?? '');
        $reservation_end_time = trim($data['reservation_end_time'] ?? '');
        $vehicle_no = trim($data['vehicle_no'] ?? ''); // Fallback for backwards compatibility

        if ($slot_svg_id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Slot is required']);
            exit;
        }
        
        // If reservation_name is not provided, use user's name
        if ($reservation_name === '') {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $reservation_name = $user['name'] ?? 'User';
        }

        // If vehicle_id is provided, use it; otherwise fallback to vehicle_no
        $vehicle_name = null;
        $vehicle_image = null;
        if ($vehicle_id > 0) {
            // Verify vehicle belongs to user and get vehicle details
            $stmt = $pdo->prepare("SELECT vehicle_no, vehicle_name, vehicle_image FROM vehicles WHERE id = ? AND user_id = ?");
            $stmt->execute([$vehicle_id, $userId]);
            $vehicle = $stmt->fetch();
            if (!$vehicle) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid vehicle selected']);
                exit;
            }
            $vehicle_no = $vehicle['vehicle_no'];
            $vehicle_name = $vehicle['vehicle_name'];
            $vehicle_image = $vehicle['vehicle_image'];
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

        // Validate timing
        if (empty($reservation_start_time) || empty($reservation_end_time)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Reservation start and end times are required']);
            exit;
        }
        
        // Validate that end time is after start time
        $startDateTime = new DateTime($reservation_start_time);
        $endDateTime = new DateTime($reservation_end_time);
        if ($endDateTime <= $startDateTime) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Check-out time must be after check-in time']);
            exit;
        }
        
        // Validate that start time is not in the past
        $now = new DateTime();
        if ($startDateTime < $now) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Check-in time cannot be in the past']);
            exit;
        }

        // Create Reservation (use vehicle_id if available)
        $reservationId = null;
        if ($vehicle_id > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (slot_id, reservation_name, user_id, vehicle_id, vehicle_no, status, reservation_start_time, reservation_end_time, reserved_at)
                VALUES (?, ?, ?, ?, ?, 'reserved', ?, ?, NOW())
            ");
            $stmt->execute([$slotId, $reservation_name, $userId, $vehicle_id, $vehicle_no, $reservation_start_time, $reservation_end_time]);
            $reservationId = $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (slot_id, reservation_name, user_id, vehicle_no, status, reservation_start_time, reservation_end_time, reserved_at)
                VALUES (?, ?, ?, ?, 'reserved', ?, ?, NOW())
            ");
            $stmt->execute([$slotId, $reservation_name, $userId, $vehicle_no, $reservation_start_time, $reservation_end_time]);
            $reservationId = $pdo->lastInsertId();
        }

        // Also insert into reservation_history for PERMANENT tracking
        if ($reservationId) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reservation_history (reservation_id, user_id, slot_id, vehicle_id, vehicle_no, vehicle_name, vehicle_image, reservation_name, status, reserved_at, reservation_start_time, reservation_end_time, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'reserved', NOW(), ?, ?, NOW())
                ");
                $vehicleIdForHistory = $vehicle_id > 0 ? $vehicle_id : null;
                $stmt->execute([$reservationId, $userId, $slotId, $vehicleIdForHistory, $vehicle_no, $vehicle_name, $vehicle_image, $reservation_name, $reservation_start_time, $reservation_end_time]);
            } catch (Exception $e) {
                // If reservation_history table doesn't exist yet, continue (will be created by SQL)
                // Log error but don't fail the reservation
                error_log("Failed to create reservation history: " . $e->getMessage());
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // CHECK-IN
    // ============================
    if ($action === 'checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = current_user_id();
        
        // Get reservation ID before updating
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ?");
        $stmt->execute([$uid]);
        $reservation = $stmt->fetch();
        
        // Update active reservation with check-in time (try with checked_in_at column first)
        try {
            $stmt = $pdo->prepare("UPDATE reservations SET status='checked_in', checked_in_at=NOW() WHERE user_id=? AND status='reserved'");
            $stmt->execute([$uid]);
        } catch (Exception $e) {
            // Column doesn't exist yet, update without checked_in_at
            $stmt = $pdo->prepare("UPDATE reservations SET status='checked_in' WHERE user_id=? AND status='reserved'");
            $stmt->execute([$uid]);
        }
        
        // Update reservation_history
        if ($reservation) {
            $stmt = $pdo->prepare("
                UPDATE reservation_history 
                SET status = 'checked_in', checked_in_at = NOW()
                WHERE reservation_id = ? AND status != 'completed' AND checked_in_at IS NULL
            ");
            $stmt->execute([$reservation['id']]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================
    // CANCEL RESERVATION
    // ============================
    if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = current_user_id();
        
        $pdo->beginTransaction();
        try {
            // Get reservation details before deleting
            $stmt = $pdo->prepare("SELECT id, slot_id, vehicle_id, vehicle_no, reservation_name, status, reserved_at, checked_in_at FROM reservations WHERE user_id = ?");
            $stmt->execute([$uid]);
            $reservation = $stmt->fetch();
            
            if ($reservation) {
                $reservationId = $reservation['id'];
                
                // Get vehicle name and image if vehicle_id exists
                $vehicle_name = null;
                $vehicle_image = null;
                if ($reservation['vehicle_id']) {
                    $stmt = $pdo->prepare("SELECT vehicle_name, vehicle_image FROM vehicles WHERE id = ?");
                    $stmt->execute([$reservation['vehicle_id']]);
                    $vehicle = $stmt->fetch();
                    if ($vehicle) {
                        $vehicle_name = $vehicle['vehicle_name'];
                        $vehicle_image = $vehicle['vehicle_image'];
                    }
                }
                
                // Ensure reservation_history entry exists (create if not exists, update if exists)
                try {
                    // Check if history entry exists
                    $stmt = $pdo->prepare("SELECT id FROM reservation_history WHERE reservation_id = ?");
                    $stmt->execute([$reservationId]);
                    $historyExists = $stmt->fetch();
                    
                    if ($historyExists) {
                        // Update existing history entry with check-out time
                        $stmt = $pdo->prepare("
                            UPDATE reservation_history 
                            SET checked_out_at = NOW(), status = 'cancelled'
                            WHERE reservation_id = ? AND checked_out_at IS NULL
                        ");
                        $stmt->execute([$reservationId]);
                    } else {
                        // Create history entry if it doesn't exist (for old reservations made before history tracking)
                        // IMPORTANT: Use the actual reserved_at from reservation, not NOW()
                        $stmt = $pdo->prepare("
                            INSERT INTO reservation_history 
                            (reservation_id, user_id, slot_id, vehicle_id, vehicle_no, vehicle_name, vehicle_image, reservation_name, status, reserved_at, checked_in_at, checked_out_at, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cancelled', ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $reservationId,
                            $uid,
                            $reservation['slot_id'],
                            $reservation['vehicle_id'],
                            $reservation['vehicle_no'],
                            $vehicle_name,
                            $vehicle_image,
                            $reservation['reservation_name'],
                            $reservation['reserved_at'],
                            $reservation['checked_in_at']
                        ]);
                    }
                } catch (Exception $e) {
                    // If reservation_history table doesn't exist, log error but continue
                    error_log("Failed to save reservation history on cancel: " . $e->getMessage());
                    // Don't fail the cancel operation - history is important but shouldn't block cancellation
                }
            }
            
            // Delete from active reservations (after saving to history)
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id=?");
            $stmt->execute([$uid]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to cancel reservation: ' . $e->getMessage()]);
        }
        exit;
    }

    // ============================
    // CHECK-OUT
    // ============================
    if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = current_user_id();
        
        $pdo->beginTransaction();
        try {
            // Get reservation details before deleting
            $stmt = $pdo->prepare("SELECT id, slot_id, vehicle_id, vehicle_no, reservation_name, status, reserved_at, checked_in_at FROM reservations WHERE user_id = ?");
            $stmt->execute([$uid]);
            $reservation = $stmt->fetch();
            
            if (!$reservation) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'No active reservation found']);
                exit;
            }
            
            $reservationId = $reservation['id'];
            
            // Get vehicle name and image if vehicle_id exists
            $vehicle_name = null;
            $vehicle_image = null;
            if ($reservation['vehicle_id']) {
                $stmt = $pdo->prepare("SELECT vehicle_name, vehicle_image FROM vehicles WHERE id = ?");
                $stmt->execute([$reservation['vehicle_id']]);
                $vehicle = $stmt->fetch();
                if ($vehicle) {
                    $vehicle_name = $vehicle['vehicle_name'];
                    $vehicle_image = $vehicle['vehicle_image'];
                }
            }
            
            // Ensure reservation_history entry exists and update with check-out time
            try {
                // Check if history entry exists
                $stmt = $pdo->prepare("SELECT id FROM reservation_history WHERE reservation_id = ?");
                $stmt->execute([$reservationId]);
                $historyExists = $stmt->fetch();
                
                if ($historyExists) {
                    // Update existing history entry with check-out time and mark as completed
                    $stmt = $pdo->prepare("
                        UPDATE reservation_history 
                        SET checked_out_at = NOW(), status = 'completed'
                        WHERE reservation_id = ? AND checked_out_at IS NULL
                    ");
                    $stmt->execute([$reservationId]);
                } else {
                    // Create history entry if it doesn't exist
                    $stmt = $pdo->prepare("
                        INSERT INTO reservation_history 
                        (reservation_id, user_id, slot_id, vehicle_id, vehicle_no, vehicle_name, vehicle_image, reservation_name, status, reserved_at, checked_in_at, checked_out_at, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $reservationId,
                        $uid,
                        $reservation['slot_id'],
                        $reservation['vehicle_id'],
                        $reservation['vehicle_no'],
                        $vehicle_name,
                        $vehicle_image,
                        $reservation['reservation_name'],
                        $reservation['reserved_at'],
                        $reservation['checked_in_at']
                    ]);
                }
            } catch (Exception $e) {
                // If reservation_history table doesn't exist, log error but continue
                error_log("Failed to save reservation history on checkout: " . $e->getMessage());
            }
            
            // Delete from active reservations (after saving to history)
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id=?");
            $stmt->execute([$uid]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to check out: ' . $e->getMessage()]);
        }
        exit;
    }

    // ============================
    // AUTO-RELEASE EXPIRED RESERVATIONS
    // ============================
    if ($action === 'auto_release' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // This endpoint can be called without authentication (for cron jobs)
        // But we'll still check if user is authenticated for security
        $uid = current_user_id();
        
        $pdo->beginTransaction();
        try {
            // Find all reservations where reservation_end_time has passed
            // Only process reservations that are still active (not already checked out)
            $stmt = $pdo->prepare("
                SELECT id, user_id, slot_id, vehicle_id, vehicle_no, reservation_name, status, reserved_at, checked_in_at, reservation_end_time
                FROM reservations
                WHERE reservation_end_time IS NOT NULL 
                AND reservation_end_time <= NOW()
                AND status IN ('reserved', 'checked_in')
            ");
            $stmt->execute();
            $expiredReservations = $stmt->fetchAll();
            
            $releasedCount = 0;
            
            foreach ($expiredReservations as $reservation) {
                $reservationId = $reservation['id'];
                $userId = $reservation['user_id'];
                
                // Get vehicle name and image if vehicle_id exists
                $vehicle_name = null;
                $vehicle_image = null;
                if ($reservation['vehicle_id']) {
                    $stmt = $pdo->prepare("SELECT vehicle_name, vehicle_image FROM vehicles WHERE id = ?");
                    $stmt->execute([$reservation['vehicle_id']]);
                    $vehicle = $stmt->fetch();
                    if ($vehicle) {
                        $vehicle_name = $vehicle['vehicle_name'];
                        $vehicle_image = $vehicle['vehicle_image'];
                    }
                }
                
                // Ensure reservation_history entry exists and update with check-out time
                try {
                    // Check if history entry exists
                    $stmt = $pdo->prepare("SELECT id FROM reservation_history WHERE reservation_id = ?");
                    $stmt->execute([$reservationId]);
                    $historyExists = $stmt->fetch();
                    
                    if ($historyExists) {
                        // Update existing history entry with check-out time and mark as completed
                        $stmt = $pdo->prepare("
                            UPDATE reservation_history 
                            SET checked_out_at = NOW(), status = 'completed'
                            WHERE reservation_id = ? AND checked_out_at IS NULL
                        ");
                        $stmt->execute([$reservationId]);
                    } else {
                        // Create history entry if it doesn't exist
                        $stmt = $pdo->prepare("
                            INSERT INTO reservation_history 
                            (reservation_id, user_id, slot_id, vehicle_id, vehicle_no, vehicle_name, vehicle_image, reservation_name, status, reserved_at, checked_in_at, checked_out_at, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $reservationId,
                            $userId,
                            $reservation['slot_id'],
                            $reservation['vehicle_id'],
                            $reservation['vehicle_no'],
                            $vehicle_name,
                            $vehicle_image,
                            $reservation['reservation_name'],
                            $reservation['reserved_at'],
                            $reservation['checked_in_at']
                        ]);
                    }
                } catch (Exception $e) {
                    // If reservation_history table doesn't exist, log error but continue
                    error_log("Failed to save reservation history on auto-release: " . $e->getMessage());
                }
                
                // Delete from active reservations (after saving to history)
                $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
                $stmt->execute([$reservationId]);
                
                $releasedCount++;
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'released_count' => $releasedCount]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to auto-release: ' . $e->getMessage()]);
        }
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

        $stmt = $pdo->prepare("SELECT id, vehicle_name, vehicle_no, vehicle_image, created_at FROM vehicles WHERE user_id = ? ORDER BY created_at ASC");
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

        // Handle file upload if present
        $vehicle_image = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/assets/vehicle_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $file = $_FILES['vehicle_image'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP']);
                exit;
            }
            
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'Image too large. Maximum 5MB']);
                exit;
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'vehicle_' . $uid . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $vehicle_image = $filename;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload image']);
                exit;
            }
        }
        
        // Get data from POST or JSON
        if (isset($_FILES['vehicle_image']) || isset($_POST['vehicle_name'])) {
            $data = $_POST;
        } else {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
        }
        
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
            INSERT INTO vehicles (user_id, vehicle_name, vehicle_no, vehicle_image, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uid, $vehicle_name, $vehicle_no, $vehicle_image]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // ============================
    // UPDATE VEHICLE IMAGE
    // ============================
    if ($action === 'update_vehicle_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        if ($vehicle_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid vehicle ID']);
            exit;
        }

        // Verify vehicle belongs to user
        $stmt = $pdo->prepare("SELECT vehicle_image FROM vehicles WHERE id = ? AND user_id = ?");
        $stmt->execute([$vehicle_id, $uid]);
        $vehicle = $stmt->fetch();
        if (!$vehicle) {
            http_response_code(404);
            echo json_encode(['error' => 'Vehicle not found']);
            exit;
        }

        // Check if this is a delete request
        $delete_image = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';
        
        if ($delete_image) {
            // Check if image is used in reservation_history before deleting
            if ($vehicle['vehicle_image']) {
                $uploadDir = __DIR__ . '/assets/vehicle_images/';
                $oldImagePath = $uploadDir . $vehicle['vehicle_image'];
                
                // Check if this image is referenced in any reservation_history entries
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservation_history WHERE vehicle_image = ?");
                $stmt->execute([$vehicle['vehicle_image']]);
                $historyCount = $stmt->fetch();
                
                // Only delete file if not used in history (preserve for historical records)
                if ($historyCount['count'] == 0 && file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            // Update vehicle to remove image (but keep file if used in history)
            $stmt = $pdo->prepare("UPDATE vehicles SET vehicle_image = NULL WHERE id = ? AND user_id = ?");
            $stmt->execute([$vehicle_id, $uid]);
            
            echo json_encode(['success' => true, 'image' => null]);
            exit;
        }

        // Handle file upload
        $vehicle_image = null;
        if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/assets/vehicle_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $file = $_FILES['vehicle_image'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP']);
                exit;
            }
            
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'Image too large. Maximum 5MB']);
                exit;
            }
            
            // Check if old image is used in reservation_history before deleting
            if ($vehicle['vehicle_image']) {
                $oldImagePath = $uploadDir . $vehicle['vehicle_image'];
                // Check if this image is referenced in any reservation_history entries
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservation_history WHERE vehicle_image = ?");
                $stmt->execute([$vehicle['vehicle_image']]);
                $historyCount = $stmt->fetch();
                
                // Only delete if not used in history (preserve for historical records)
                if ($historyCount['count'] == 0 && file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'vehicle_' . $uid . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $vehicle_image = $filename;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload image']);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No image file provided']);
            exit;
        }

        // Update vehicle image
        $stmt = $pdo->prepare("UPDATE vehicles SET vehicle_image = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$vehicle_image, $vehicle_id, $uid]);
        
        echo json_encode(['success' => true, 'image' => $vehicle_image]);
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
        $slotId = intval($data['slot_id'] ?? 0);
        
        if (!$slotId) {
            http_response_code(400);
            echo json_encode(['error' => 'slot_id required']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Get reservation details before deleting
            $stmt = $pdo->prepare("SELECT id, user_id, slot_id, vehicle_id, vehicle_no, reservation_name, status, reserved_at, checked_in_at FROM reservations WHERE slot_id = ?");
            $stmt->execute([$slotId]);
            $reservation = $stmt->fetch();
            
            if ($reservation) {
                $reservationId = $reservation['id'];
                $userId = $reservation['user_id'];
                
                // Get vehicle name and image if vehicle_id exists
                $vehicle_name = null;
                $vehicle_image = null;
                if ($reservation['vehicle_id']) {
                    $stmt = $pdo->prepare("SELECT vehicle_name, vehicle_image FROM vehicles WHERE id = ?");
                    $stmt->execute([$reservation['vehicle_id']]);
                    $vehicle = $stmt->fetch();
                    if ($vehicle) {
                        $vehicle_name = $vehicle['vehicle_name'];
                        $vehicle_image = $vehicle['vehicle_image'];
                    }
                }
                
                // Ensure reservation_history entry exists (create if not exists, update if exists)
                try {
                    // Check if history entry exists
                    $stmt = $pdo->prepare("SELECT id FROM reservation_history WHERE reservation_id = ?");
                    $stmt->execute([$reservationId]);
                    $historyExists = $stmt->fetch();
                    
                    if ($historyExists) {
                        // Update existing history entry with check-out time
                        $stmt = $pdo->prepare("
                            UPDATE reservation_history 
                            SET checked_out_at = NOW(), status = 'cancelled'
                            WHERE reservation_id = ? AND checked_out_at IS NULL
                        ");
                        $stmt->execute([$reservationId]);
                    } else {
                        // Create history entry if it doesn't exist
                        $stmt = $pdo->prepare("
                            INSERT INTO reservation_history 
                            (reservation_id, user_id, slot_id, vehicle_id, vehicle_no, vehicle_name, vehicle_image, reservation_name, status, reserved_at, checked_in_at, checked_out_at, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cancelled', ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $reservationId,
                            $userId,
                            $reservation['slot_id'],
                            $reservation['vehicle_id'],
                            $reservation['vehicle_no'],
                            $vehicle_name,
                            $vehicle_image,
                            $reservation['reservation_name'],
                            $reservation['reserved_at'],
                            $reservation['checked_in_at']
                        ]);
                    }
                } catch (Exception $e) {
                    // If reservation_history table doesn't exist, log error but continue
                    error_log("Failed to save reservation history on admin free slot: " . $e->getMessage());
                    // Don't fail the free slot operation - history is important but shouldn't block it
                }
            }
            
            // Delete from active reservations (after saving to history)
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE slot_id = ?");
            $stmt->execute([$slotId]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to free slot: ' . $e->getMessage()]);
        }
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
    // GET RESERVATION STATISTICS
    // ============================
    if ($action === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        // Total reservations (all time) - from PERMANENT history table first, then add active reservations
        $totalReservations = 0;
        try {
            // Count from permanent history
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation_history WHERE user_id = ?");
            $stmt->execute([$uid]);
            $totalReservations = (int)$stmt->fetchColumn();
            
            // Also count active reservations that might not be in history yet (for backward compatibility)
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM reservations r
                    LEFT JOIN reservation_history rh ON rh.reservation_id = r.id
                    WHERE r.user_id = ? AND rh.id IS NULL
                ");
                $stmt->execute([$uid]);
                $notInHistory = (int)$stmt->fetchColumn();
                $totalReservations += $notInHistory;
            } catch (Exception $e2) {
                // If join fails, just count all active reservations
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ?");
                $stmt->execute([$uid]);
                $totalReservations += (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            // If reservation_history table doesn't exist yet, use only active reservations
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ?");
                $stmt->execute([$uid]);
                $totalReservations = (int)$stmt->fetchColumn();
            } catch (Exception $e2) {
                $totalReservations = 0;
            }
        }

        // Current month reservations - from PERMANENT history first
        $currentMonthReservations = 0;
        try {
            // Count from permanent history for current month
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reservation_history
                WHERE user_id = ? 
                AND MONTH(reserved_at) = MONTH(CURRENT_DATE())
                AND YEAR(reserved_at) = YEAR(CURRENT_DATE())
            ");
            $stmt->execute([$uid]);
            $currentMonthReservations = (int)$stmt->fetchColumn();
            
            // Also count active reservations for current month that might not be in history
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reservations r
                LEFT JOIN reservation_history rh ON rh.reservation_id = r.id
                WHERE r.user_id = ? 
                AND MONTH(r.reserved_at) = MONTH(CURRENT_DATE())
                AND YEAR(r.reserved_at) = YEAR(CURRENT_DATE())
                AND rh.id IS NULL
            ");
            $stmt->execute([$uid]);
            $notInHistory = (int)$stmt->fetchColumn();
            $currentMonthReservations += $notInHistory;
        } catch (Exception $e) {
            // If reservation_history table doesn't exist, use only active reservations
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM reservations 
                    WHERE user_id = ? 
                    AND MONTH(reserved_at) = MONTH(CURRENT_DATE())
                    AND YEAR(reserved_at) = YEAR(CURRENT_DATE())
                ");
                $stmt->execute([$uid]);
                $currentMonthReservations = (int)$stmt->fetchColumn();
            } catch (Exception $e2) {
                $currentMonthReservations = 0;
            }
        }

        // Most used vehicle
        $stmt = $pdo->prepare("
            SELECT v.vehicle_name, v.vehicle_no, COUNT(*) as usage_count
            FROM reservations r
            LEFT JOIN vehicles v ON v.id = r.vehicle_id
            WHERE r.user_id = ? AND r.vehicle_id IS NOT NULL
            GROUP BY v.id, v.vehicle_name, v.vehicle_no
            ORDER BY usage_count DESC
            LIMIT 1
        ");
        $stmt->execute([$uid]);
        $mostUsedVehicle = $stmt->fetch();
        
        // If no vehicle-based reservation, try vehicle_no
        if (!$mostUsedVehicle) {
            $stmt = $pdo->prepare("
                SELECT vehicle_no, COUNT(*) as usage_count
                FROM reservations
                WHERE user_id = ? AND vehicle_no IS NOT NULL
                GROUP BY vehicle_no
                ORDER BY usage_count DESC
                LIMIT 1
            ");
            $stmt->execute([$uid]);
            $vehicleNo = $stmt->fetch();
            if ($vehicleNo) {
                $mostUsedVehicle = ['vehicle_name' => '', 'vehicle_no' => $vehicleNo['vehicle_no']];
            }
        }

        // Average duration (if we track check-out times in history table)
        try {
            $stmt = $pdo->prepare("
                SELECT AVG(TIMESTAMPDIFF(HOUR, reserved_at, checked_out_at)) as avg_hours
                FROM reservation_history
                WHERE user_id = ? AND checked_out_at IS NOT NULL
            ");
            $stmt->execute([$uid]);
            $avgDuration = $stmt->fetchColumn();
        } catch (Exception $e) {
            $avgDuration = null;
        }

        $vehicleDisplay = '-';
        if ($mostUsedVehicle) {
            if (!empty($mostUsedVehicle['vehicle_name'])) {
                $vehicleDisplay = $mostUsedVehicle['vehicle_name'] . ' (' . $mostUsedVehicle['vehicle_no'] . ')';
            } else {
                $vehicleDisplay = $mostUsedVehicle['vehicle_no'];
            }
        }

        echo json_encode([
            'total_reservations' => (int)$totalReservations,
            'current_month' => (int)$currentMonthReservations,
            'most_used_vehicle' => $vehicleDisplay,
            'average_duration' => $avgDuration ? round($avgDuration, 1) . ' hrs' : '-'
        ]);
        exit;
    }

    // ============================
    // GET VEHICLE PARKING HISTORY
    // ============================
    if ($action === 'vehicle_history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $vehicleId = intval($_GET['vehicle_id'] ?? 0);
        if (!$vehicleId) {
            http_response_code(400);
            echo json_encode(['error' => 'vehicle_id required']);
            exit;
        }

        // Verify vehicle belongs to user and get vehicle_no
        $stmt = $pdo->prepare("SELECT id, vehicle_no FROM vehicles WHERE id = ? AND user_id = ?");
        $stmt->execute([$vehicleId, $uid]);
        $vehicle = $stmt->fetch();
        if (!$vehicle) {
            http_response_code(403);
            echo json_encode(['error' => 'Vehicle not found or access denied']);
            exit;
        }
        $vehicleNo = $vehicle['vehicle_no'] ?? null;

        // Get vehicle parking history - ALWAYS use PERMANENT history first
        // Search by BOTH vehicle_id AND vehicle_no (for old reservations that might not have vehicle_id)
        $history = [];
        try {
            // Primary source: PERMANENT reservation_history (includes released/cancelled slots)
            // Search by vehicle_id OR vehicle_no (for backward compatibility)
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(rh.reserved_at) as date,
                    p.slot_number,
                    p.label,
                    rh.status,
                    rh.reserved_at,
                    rh.checked_in_at,
                    rh.checked_out_at,
                    rh.vehicle_name,
                    rh.vehicle_no,
                    rh.vehicle_image
                FROM reservation_history rh
                LEFT JOIN parking_slots p ON p.id = rh.slot_id
                WHERE rh.user_id = ? 
                AND (rh.vehicle_id = ? OR rh.vehicle_no = ?)
                ORDER BY rh.reserved_at DESC
            ");
            $stmt->execute([$uid, $vehicleId, $vehicleNo]);
            $historyFromTable = $stmt->fetchAll();
            
            // Also get active reservations that might not be in history yet (backward compatibility)
            // Search by BOTH vehicle_id AND vehicle_no
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(r.reserved_at) as date,
                        p.slot_number,
                        p.label,
                        r.status,
                        r.reserved_at,
                        r.checked_in_at,
                        NULL as checked_out_at
                    FROM reservations r
                    LEFT JOIN parking_slots p ON p.id = r.slot_id
                    LEFT JOIN reservation_history rh ON rh.reservation_id = r.id
                    WHERE r.user_id = ? 
                    AND (r.vehicle_id = ? OR r.vehicle_no = ?)
                    AND rh.id IS NULL
                    ORDER BY r.reserved_at DESC
                ");
                $stmt->execute([$uid, $vehicleId, $vehicleNo]);
                $activeReservations = $stmt->fetchAll();
                
                // Combine: history first, then active that aren't in history
                $history = array_merge($historyFromTable, $activeReservations);
            } catch (Exception $e) {
                // If join fails, just use history
                $history = $historyFromTable;
            }
        } catch (Exception $e) {
            // If reservation_history table doesn't exist yet, fallback to active reservations
        }

        // If still no history, fallback to active reservations only
        // Search by BOTH vehicle_id AND vehicle_no for maximum compatibility
        if (empty($history)) {
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(r.reserved_at) as date,
                        p.slot_number,
                        p.label,
                        r.status,
                        r.reserved_at,
                        r.checked_in_at,
                        NULL as checked_out_at
                    FROM reservations r
                    LEFT JOIN parking_slots p ON p.id = r.slot_id
                    WHERE r.user_id = ? 
                    AND (r.vehicle_id = ? OR r.vehicle_no = ?)
                    ORDER BY r.reserved_at DESC
                ");
                $stmt->execute([$uid, $vehicleId, $vehicleNo]);
                $history = $stmt->fetchAll();
            } catch (Exception $e) {
                // Column might not exist, try without checked_in_at
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(r.reserved_at) as date,
                        p.slot_number,
                        p.label,
                        r.status,
                        r.reserved_at,
                        NULL as checked_in_at,
                        NULL as checked_out_at
                    FROM reservations r
                    LEFT JOIN parking_slots p ON p.id = r.slot_id
                    WHERE r.user_id = ? 
                    AND (r.vehicle_id = ? OR r.vehicle_no = ?)
                    ORDER BY r.reserved_at DESC
                ");
                $stmt->execute([$uid, $vehicleId, $vehicleNo]);
                $history = $stmt->fetchAll();
            }
        }

        // Format history data
        $formattedHistory = [];
        foreach ($history as $item) {
            $reservedTime = $item['reserved_at'] ? date('H:i', strtotime($item['reserved_at'])) : null;
            $checkedInTime = $item['checked_in_at'] ? date('H:i', strtotime($item['checked_in_at'])) : null;
            $checkedOutTime = $item['checked_out_at'] ? date('H:i', strtotime($item['checked_out_at'])) : null;
            
            $formattedHistory[] = [
                'date' => date('F j, Y', strtotime($item['date'])),
                'slot' => $item['slot_number'] ?? $item['label'] ?? 'N/A',
                'status' => $item['status'],
                'reserved_time' => $reservedTime,
                'checked_in_time' => $checkedInTime,
                'checked_out_time' => $checkedOutTime,
                'vehicle_name' => $item['vehicle_name'] ?? null,
                'vehicle_no' => $item['vehicle_no'] ?? null,
                'vehicle_image' => $item['vehicle_image'] ?? null
            ];
        }

        echo json_encode([
            'vehicle_id' => $vehicleId,
            'history' => $formattedHistory
        ]);
        exit;
    }

    // ============================
    // GET RESERVATION CALENDAR DATA
    // ============================
    if ($action === 'calendar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $uid = current_user_id();
        if (!$uid) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('m'));

        // Get all reservations for the month - ALWAYS use PERMANENT history first
        $reservations = [];
        try {
            // Primary source: PERMANENT reservation_history (includes released/cancelled slots)
            $stmt = $pdo->prepare("
                SELECT 
                    DAY(rh.reserved_at) as day,
                    p.slot_number,
                    p.label,
                    v.vehicle_name,
                    rh.status,
                    rh.reserved_at,
                    rh.checked_in_at,
                    rh.checked_out_at
                FROM reservation_history rh
                LEFT JOIN parking_slots p ON p.id = rh.slot_id
                LEFT JOIN vehicles v ON v.id = rh.vehicle_id
                WHERE rh.user_id = ?
                AND MONTH(rh.reserved_at) = ?
                AND YEAR(rh.reserved_at) = ?
                ORDER BY day ASC, rh.reserved_at ASC
            ");
            $stmt->execute([$uid, $month, $year]);
            $historyReservations = $stmt->fetchAll();
            
            // Also get active reservations that might not be in history yet (backward compatibility)
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        DAY(r.reserved_at) as day,
                        p.slot_number,
                        p.label,
                        v.vehicle_name,
                        r.status,
                        r.reserved_at,
                        r.checked_in_at,
                        NULL as checked_out_at
                    FROM reservations r
                    LEFT JOIN parking_slots p ON p.id = r.slot_id
                    LEFT JOIN vehicles v ON v.id = r.vehicle_id
                    LEFT JOIN reservation_history rh ON rh.reservation_id = r.id
                    WHERE r.user_id = ?
                    AND MONTH(r.reserved_at) = ?
                    AND YEAR(r.reserved_at) = ?
                    AND rh.id IS NULL
                    ORDER BY day ASC, r.reserved_at ASC
                ");
                $stmt->execute([$uid, $month, $year]);
                $activeReservations = $stmt->fetchAll();
                
                // Combine history and active (history takes precedence)
                $reservationIdsInHistory = array_column($historyReservations, 'reservation_id');
                foreach ($activeReservations as $active) {
                    // Only add if not already in history
                    if (!in_array($active['id'] ?? null, $reservationIdsInHistory)) {
                        $reservations[] = $active;
                    }
                }
            } catch (Exception $e) {
                // If join fails, just use history
            }
            
            // Add history reservations
            $reservations = array_merge($historyReservations, $reservations);
        } catch (Exception $e) {
            // If reservation_history table doesn't exist yet, fallback to active reservations
        }
        
        // If no history found, fallback to active reservations (with checked_in_at if column exists)
        if (empty($reservations)) {
            try {
                // Try with checked_in_at column first
                $stmt = $pdo->prepare("
                    SELECT 
                        DAY(r.reserved_at) as day,
                        p.slot_number,
                        p.label,
                        v.vehicle_name,
                        r.status,
                        r.reserved_at,
                        r.checked_in_at,
                        NULL as checked_out_at
                    FROM reservations r
                    LEFT JOIN parking_slots p ON p.id = r.slot_id
                    LEFT JOIN vehicles v ON v.id = r.vehicle_id
                    WHERE r.user_id = ?
                    AND MONTH(r.reserved_at) = ?
                    AND YEAR(r.reserved_at) = ?
                    ORDER BY day ASC, r.reserved_at ASC
                ");
                $stmt->execute([$uid, $month, $year]);
                $reservations = $stmt->fetchAll();
            } catch (Exception $e) {
                // Column doesn't exist yet, use without checked_in_at
                $stmt = $pdo->prepare("
                    SELECT 
                        DAY(r.reserved_at) as day,
                        p.slot_number,
                        p.label,
                        v.vehicle_name,
                        r.status,
                        r.reserved_at,
                        NULL as checked_in_at,
                        NULL as checked_out_at
                    FROM reservations r
                    LEFT JOIN parking_slots p ON p.id = r.slot_id
                    LEFT JOIN vehicles v ON v.id = r.vehicle_id
                    WHERE r.user_id = ?
                    AND MONTH(r.reserved_at) = ?
                    AND YEAR(r.reserved_at) = ?
                    ORDER BY day ASC, r.reserved_at ASC
                ");
                $stmt->execute([$uid, $month, $year]);
                $reservations = $stmt->fetchAll();
            }
        }

        // Format reservations by day
        $calendarData = [];
        foreach ($reservations as $res) {
            $day = (int)$res['day'];
            if (!isset($calendarData[$day])) {
                $calendarData[$day] = [];
            }
            
            // Format times
            $reservedTime = $res['reserved_at'] ? date('H:i', strtotime($res['reserved_at'])) : null;
            $checkedInTime = $res['checked_in_at'] ? date('H:i', strtotime($res['checked_in_at'])) : null;
            $checkedOutTime = $res['checked_out_at'] ? date('H:i', strtotime($res['checked_out_at'])) : null;
            
            $calendarData[$day][] = [
                'slot' => $res['slot_number'] ?? $res['label'] ?? 'N/A',
                'vehicle' => $res['vehicle_name'] ?? 'N/A',
                'status' => $res['status'],
                'reserved_at' => $res['reserved_at'],
                'reserved_time' => $reservedTime,
                'checked_in_at' => $res['checked_in_at'],
                'checked_in_time' => $checkedInTime,
                'checked_out_at' => $res['checked_out_at'],
                'checked_out_time' => $checkedOutTime
            ];
        }

        echo json_encode([
            'year' => $year,
            'month' => $month,
            'reservations' => $calendarData
        ]);
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
