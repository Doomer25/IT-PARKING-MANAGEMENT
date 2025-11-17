<?php
require_once __DIR__ . '/../../src/auth.php';
require_admin();
require_once __DIR__ . '/../../src/db.php';

// Get all users
$stmt = $pdo->query("SELECT id, name, email, user_type, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get all reservations with user info
$stmt = $pdo->query("
    SELECT 
        r.id,
        r.slot_id,
        r.user_id,
        r.reservation_name,
        r.vehicle_no,
        r.status,
        r.reserved_at,
        p.svg_id,
        p.slot_number,
        u.name AS user_name,
        u.email AS user_email
    FROM reservations r
    JOIN parking_slots p ON p.id = r.slot_id
    JOIN users u ON u.id = r.user_id
    ORDER BY r.reserved_at DESC
");
$reservations = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard — IT Parking</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
      color: #1e293b;
    }

    .header {
      max-width: 1400px;
      margin: 0 auto 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .header h1 {
      color: #ffffff;
      font-size: 28px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      padding: 10px 20px;
      border-radius: 8px;
      border: none;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .btn-primary {
      background: #ffffff;
      color: #667eea;
    }

    .btn-secondary {
      background: rgba(255,255,255,0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }

    .btn-danger {
      background: #ef4444;
      color: #ffffff;
    }

    .btn-danger:hover {
      background: #dc2626;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
      gap: 24px;
    }

    .card {
      background: #ffffff;
      padding: 32px;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 20px;
      border-bottom: 2px solid #e2e8f0;
    }

    .card-header h2 {
      font-size: 22px;
      font-weight: 600;
      color: #1e293b;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }

    th {
      font-weight: 600;
      color: #64748b;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td {
      color: #1e293b;
      font-size: 14px;
    }

    tr:hover {
      background: #f8fafc;
    }

    .badge {
      display: inline-flex;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
    }

    .badge.hod {
      background: #fef3c7;
      color: #92400e;
    }

    .badge.faculty {
      background: #dbeafe;
      color: #1e40af;
    }

    .badge.normal {
      background: #e2e8f0;
      color: #475569;
    }

    .badge.reserved {
      background: #fef3c7;
      color: #92400e;
    }

    .badge.checked_in {
      background: #d1fae5;
      color: #065f46;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
      border-radius: 6px;
    }

    .btn-sm.danger {
      background: #ef4444;
      color: #ffffff;
      border: none;
      cursor: pointer;
    }

    .btn-sm.danger:hover {
      background: #dc2626;
    }

    @media (max-width: 768px) {
      .container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>⚙️ Admin Dashboard</h1>
    <div class="header-actions">
      <a class="btn btn-secondary" href="/IT-PARKING-MANAGEMENT/public/map.php">🗺️ View Map</a>
      <a class="btn btn-primary" href="/IT-PARKING-MANAGEMENT/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="container">
    <div class="card">
      <div class="card-header">
        <h2>All Users (<?= count($users) ?>)</h2>
      </div>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Type</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                  <span class="badge <?= htmlspecialchars($user['user_type']) ?>">
                    <?= htmlspecialchars(ucfirst($user['user_type'])) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($user['created_at']) ?></td>
                <td>
                  <button class="btn-sm danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>')">
                    Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Active Reservations (<?= count($reservations) ?>)</h2>
      </div>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Slot</th>
              <th>User</th>
              <th>Name</th>
              <th>Vehicle</th>
              <th>Status</th>
              <th>Reserved At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reservations as $res): ?>
              <tr>
                <td><?= htmlspecialchars($res['slot_number'] ?? $res['svg_id']) ?></td>
                <td>
                  <div><?= htmlspecialchars($res['user_name']) ?></div>
                  <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($res['user_email']) ?></div>
                </td>
                <td><?= htmlspecialchars($res['reservation_name']) ?></td>
                <td><?= htmlspecialchars($res['vehicle_no']) ?></td>
                <td>
                  <span class="badge <?= htmlspecialchars($res['status']) ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $res['status']))) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($res['reserved_at']) ?></td>
                <td>
                  <button class="btn-sm danger" onclick="freeSlot(<?= $res['slot_id'] ?>, '<?= htmlspecialchars($res['slot_number'] ?? $res['svg_id'], ENT_QUOTES) ?>')">
                    Free Slot
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($reservations)): ?>
              <tr>
                <td colspan="7" style="text-align: center; color: #64748b; padding: 32px;">
                  No active reservations
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    async function deleteUser(userId, userName) {
      if (!confirm(`Are you sure you want to delete user "${userName}"?\n\nThis will also delete their reservations.`)) {
        return;
      }

      try {
        const res = await fetch('/IT-PARKING-MANAGEMENT/public/api.php?action=admin_delete_user', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ user_id: userId })
        });

        const data = await res.json();
        if (res.ok) {
          alert('User deleted successfully');
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to delete user'));
        }
      } catch (err) {
        alert('Network error. Please try again.');
      }
    }

    async function freeSlot(slotId, slotName) {
      if (!confirm(`Are you sure you want to free slot "${slotName}"?\n\nThis will cancel the reservation.`)) {
        return;
      }

      try {
        const res = await fetch('/IT-PARKING-MANAGEMENT/public/api.php?action=admin_free_slot', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ slot_id: slotId })
        });

        const data = await res.json();
        if (res.ok) {
          alert('Slot freed successfully');
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Failed to free slot'));
        }
      } catch (err) {
        alert('Network error. Please try again.');
      }
    }
  </script>
</body>
</html>

