<?php
require_once __DIR__ . '/../../src/auth.php';
require_admin();
require_once __DIR__ . '/../../src/db.php';

// Get activity logs with user information
$stmt = $pdo->query("
    SELECT 
        al.id,
        al.user_id,
        al.action,
        al.details,
        al.created_at,
        u.name AS user_name,
        u.email AS user_email
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 500
");
$activities = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Activity Logs — Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      min-height: 100vh;
      padding: 20px;
      color: #1e293b;
      position: relative;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('/IT-PARKING-MANAGEMENT/public/assets/building-background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      filter: blur(8px);
      transform: scale(1.1);
      z-index: -2;
    }

    body::after {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.1);
      z-index: -1;
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
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(102, 126, 234, 1), 0 0 60px rgba(102, 126, 234, 0.6), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(102, 126, 234, 0.5) !important;
      outline-offset: 4px !important;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
    }

    .card {
      background: rgb(230, 216, 247);
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
      position: sticky;
      top: 0;
      background: rgb(230, 216, 247);
      z-index: 10;
    }

    td {
      color: #1e293b;
      font-size: 14px;
    }

    tr:hover {
      background: #f8fafc;
    }

    .details-cell {
      max-width: 300px;
      word-wrap: break-word;
      font-family: 'Courier New', monospace;
      font-size: 12px;
      color: #475569;
    }

    .details-json {
      background: #f1f5f9;
      padding: 8px;
      border-radius: 4px;
      white-space: pre-wrap;
      max-height: 150px;
      overflow-y: auto;
    }

    .user-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .user-name {
      font-weight: 500;
      color: #1e293b;
    }

    .user-email {
      font-size: 12px;
      color: #64748b;
    }

    .system-action {
      color: #64748b;
      font-style: italic;
    }

    .action-badge {
      display: inline-flex;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      background: #e0e7ff;
      color: #4338ca;
    }

    .scrollable-table {
      max-height: 70vh;
      overflow-y: auto;
    }

    @media (max-width: 768px) {
      .container {
        padding: 0;
      }
      
      table {
        font-size: 12px;
      }
      
      th, td {
        padding: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>📋 Activity Logs</h1>
    <div class="header-actions">
      <a class="btn btn-secondary" href="/IT-PARKING-MANAGEMENT/public/admin/dashboard.php">← Dashboard</a>
      <a class="btn btn-secondary" href="/IT-PARKING-MANAGEMENT/public/map.php">🗺️ View Map</a>
      <a class="btn btn-primary" href="/IT-PARKING-MANAGEMENT/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="container">
    <div class="card">
      <div class="card-header">
        <h2>System Activities (<?= count($activities) ?>)</h2>
      </div>
      <div class="scrollable-table" style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Action</th>
              <th>Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activities as $activity): ?>
              <tr>
                <td><?= htmlspecialchars($activity['id']) ?></td>
                <td>
                  <?php if ($activity['user_id']): ?>
                    <div class="user-info">
                      <span class="user-name"><?= htmlspecialchars($activity['user_name'] ?? 'Unknown') ?></span>
                      <span class="user-email"><?= htmlspecialchars($activity['user_email'] ?? '') ?></span>
                    </div>
                  <?php else: ?>
                    <span class="system-action">System/Admin</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="action-badge"><?= htmlspecialchars($activity['action']) ?></span>
                </td>
                <td class="details-cell">
                  <?php if ($activity['details']): ?>
                    <?php
                      $details = json_decode($activity['details'], true);
                      if (json_last_error() === JSON_ERROR_NONE && is_array($details)) {
                        echo '<div class="details-json">' . htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)) . '</div>';
                      } else {
                        echo '<div class="details-json">' . htmlspecialchars($activity['details']) . '</div>';
                      }
                    ?>
                  <?php else: ?>
                    <span style="color: #94a3b8;">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($activity['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($activities)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: #64748b; padding: 32px;">
                  No activity logs found
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>

