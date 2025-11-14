<?php
require_once __DIR__ . '/../src/auth.php';
requireLogin();
require_once __DIR__ . '/../src/db.php';

$username = $_SESSION['user_name'] ?? 'User';
$userId = $_SESSION['user_id'];
$role = ($_SESSION['role_id'] ?? 2) == 1 ? 'admin' : 'user';

// --- FIXED QUERY ---
$stmt = $pdo->prepare("
    SELECT 
        r.id AS reservation_id,
        r.status,
        r.reserved_at,
        r.vehicle_no,
        r.reservation_name,
        p.svg_id,
        p.slot_number,
        p.slot_type
    FROM reservations r
    JOIN parking_slots p ON p.id = r.slot_id
    WHERE r.user_id = ?
    ORDER BY r.reserved_at DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$reservedSlot = $stmt->fetch();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard — IT Parking</title>
  <style>
    body{font-family:system-ui,Arial;background:#f8fafc;padding:20px}
    .card{background:#fff;padding:16px;border-radius:8px;max-width:900px;margin:12px auto;box-shadow:0 6px 20px rgba(2,6,23,.06)}
    .muted{color:#64748b}
    .btn{display:inline-block;padding:8px 12px;border-radius:6px;border:0;background:#2563eb;color:#fff;text-decoration:none;cursor:pointer}
    .btn.ghost{background:#e2e8f0;color:#0f172a}
    .status {margin:12px 0;padding:12px;border-radius:8px;background:#f1f5f9}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px}
    .badge.reserved{background:#facc15;color:#854d0e}
    .badge.checked_in{background:#22c55e;color:#065f46}
  </style>
</head>
<body>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Welcome, <?= htmlspecialchars($username) ?></h1>
    <div>
      <a class="btn ghost" href="/IT-PARKING-MANAGEMENT/public/map.php">Open Map</a>
      <a class="btn" href="/IT-PARKING-MANAGEMENT/public/logout.php">Logout</a>
    </div>
  </div>

  <hr>

  <div class="status">
    <?php if ($reservedSlot): ?>
      <h3>Your Current Reservation</h3>
      <p>
        <strong>Slot:</strong> <?= htmlspecialchars($reservedSlot['slot_number'] ?? $reservedSlot['svg_id']) ?><br>
        <strong>Name:</strong> <?= htmlspecialchars($reservedSlot['reservation_name']) ?><br>
        <strong>Vehicle:</strong> <?= htmlspecialchars($reservedSlot['vehicle_no']) ?><br>
        <strong>Status:</strong>
        <?php if ($reservedSlot['status'] === 'checked_in'): ?>
          <span class="badge checked_in">Checked In</span>
        <?php else: ?>
          <span class="badge reserved">Reserved</span>
        <?php endif; ?>
        <br><strong>Reserved at:</strong> <?= htmlspecialchars($reservedSlot['reserved_at']); ?>
      </p>
      <p>
        <button id="cancel" class="btn ghost">Release Slot</button>
      </p>
    <?php else: ?>
      <h3>No Active Reservation</h3>
      <p>You currently do not have a reservation.</p>
      <a class="btn" href="/IT-PARKING-MANAGEMENT/public/map.php">Reserve a Slot</a>
    <?php endif; ?>
  </div>
</div>

<script>
document.getElementById("cancel")?.addEventListener("click", async () => {
  if (!confirm("Release your reserved slot?")) return;
  const r = await fetch("/IT-PARKING-MANAGEMENT/public/api.php?action=cancel", {method:"POST"});
  location.reload();
});
</script>

</body>
</html>
