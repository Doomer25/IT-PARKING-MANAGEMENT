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
      max-width: 1000px;
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

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(102, 126, 234, 1), 0 0 60px rgba(102, 126, 234, 0.6), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(102, 126, 234, 0.5) !important;
      outline-offset: 4px !important;
    }

    .btn-secondary {
      background: rgba(255,255,255,0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }

    .btn-secondary:hover {
      background: rgba(255,255,255,0.3) !important;
      box-shadow: 0 0 30px rgba(255, 255, 255, 0.8), 0 0 60px rgba(255, 255, 255, 0.5), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(255, 255, 255, 0.6) !important;
      outline-offset: 4px !important;
      transform: translateY(-2px);
    }

    .btn-danger {
      background: #ef4444;
      color: #ffffff;
    }

    .btn-danger:hover {
      background: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(239, 68, 68, 1), 0 0 60px rgba(239, 68, 68, 0.6), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(239, 68, 68, 0.6) !important;
      outline-offset: 4px !important;
    }

    /* Modern Modal Styles */
    .modern-modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      animation: fadeIn 0.2s ease;
      backdrop-filter: blur(4px);
    }

    .modern-modal {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 480px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      animation: slideUp 0.3s ease;
    }

    .modern-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid #e2e8f0;
    }

    .modern-modal-header h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
    }

    .modern-modal-close {
      background: none;
      border: none;
      font-size: 28px;
      color: #64748b;
      cursor: pointer;
      padding: 0;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: all 0.2s;
    }

    .modern-modal-close:hover {
      background: #f1f5f9;
      color: #1e293b;
    }

    .modern-modal-body {
      padding: 24px;
    }

    .modern-modal-body p {
      margin: 0;
      font-size: 15px;
      line-height: 1.6;
      color: #334155;
      white-space: pre-line;
    }

    .modern-modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      padding: 16px 24px;
      border-top: 1px solid #e2e8f0;
    }

    .modern-modal-btn {
      padding: 10px 24px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }

    .modern-modal-btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #ffffff;
    }

    .modern-modal-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(102, 126, 234, 1), 0 0 60px rgba(102, 126, 234, 0.6), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(102, 126, 234, 0.5) !important;
      outline-offset: 4px !important;
    }

    .modern-modal-btn-secondary {
      background: #f1f5f9;
      color: #475569;
    }

    .modern-modal-btn-secondary:hover {
      background: #e2e8f0;
      transform: translateY(-2px);
      box-shadow: 0 0 20px rgba(148, 163, 184, 0.8), 0 0 40px rgba(148, 163, 184, 0.4), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(148, 163, 184, 0.5) !important;
      outline-offset: 4px !important;
    }

    .modern-modal-btn-danger {
      background: #ef4444;
      color: #ffffff;
    }

    .modern-modal-btn-danger:hover {
      background: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(239, 68, 68, 1), 0 0 60px rgba(239, 68, 68, 0.6), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(239, 68, 68, 0.6) !important;
      outline-offset: 4px !important;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card {
      background: #ffffff;
      padding: 32px;
      border-radius: 16px;
      max-width: 1000px;
      margin: 0 auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      transition: background 0.3s ease, box-shadow 0.3s ease;
    }

    body.dark-mode .card {
      background: #1e293b;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
      padding-bottom: 24px;
      border-bottom: 2px solid #e2e8f0;
    }

    .card-header h2 {
      font-size: 24px;
      font-weight: 600;
      color: #1e293b;
      transition: color 0.3s ease;
    }

    body.dark-mode .card-header h2 {
      color: #f1f5f9;
    }

    .status-card {
      background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
      padding: 24px;
      border-radius: 12px;
      border: 2px solid #e2e8f0;
      margin-top: 24px;
      transition: background 0.3s ease, border-color 0.3s ease;
    }

    body.dark-mode .status-card {
      background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
      border-color: #475569;
    }

    .status-card h3 {
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: color 0.3s ease;
    }

    body.dark-mode .status-card h3 {
      color: #f1f5f9;
    }

    .status-card.empty {
      text-align: center;
      padding: 48px 24px;
    }

    .status-card.empty h3 {
      justify-content: center;
      color: #64748b;
    }

    .status-card.empty p {
      color: #94a3b8;
      margin-bottom: 24px;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .info-label {
      font-size: 12px;
      font-weight: 500;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: color 0.3s ease;
    }

    body.dark-mode .info-label {
      color: #94a3b8;
    }

    .info-value {
      font-size: 16px;
      font-weight: 600;
      color: #1e293b;
      transition: color 0.3s ease;
    }

    body.dark-mode .info-value {
      color: #f1f5f9;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      gap: 6px;
    }

    .badge::before {
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: currentColor;
    }

    .badge.reserved {
      background: #fef3c7;
      color: #92400e;
    }

    .badge.checked_in {
      background: #d1fae5;
      color: #065f46;
    }

    .btn-action {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #ffffff;
      padding: 12px 24px;
      border-radius: 8px;
      border: none;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(102, 126, 234, 1), 0 0 60px rgba(102, 126, 234, 0.6), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(102, 126, 234, 0.5) !important;
      outline-offset: 4px !important;
    }

    .btn-action:active {
      transform: translateY(0);
    }

    .btn-ghost {
      background: #e2e8f0;
      color: #475569;
      padding: 12px 24px;
      border-radius: 8px;
      border: none;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-ghost:hover {
      background: #cbd5e1;
      transform: translateY(-2px);
      box-shadow: 0 0 20px rgba(148, 163, 184, 0.8), 0 0 40px rgba(148, 163, 184, 0.4), 0 4px 12px rgba(0,0,0,0.15) !important;
      outline: 2px solid rgba(148, 163, 184, 0.5) !important;
      outline-offset: 4px !important;
    }

    body.dark-mode .btn-ghost {
      background: #475569;
      color: #f1f5f9;
    }

    body.dark-mode .btn-ghost:hover {
      background: #64748b;
    }

    /* Dark Mode Toggle Switch */
    .dark-mode-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #cbd5e1;
      transition: 0.3s;
      border-radius: 28px;
    }

    .dark-mode-slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: 0.3s;
      border-radius: 50%;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    #darkModeToggle:checked + .dark-mode-slider {
      background-color: #667eea;
    }

    #darkModeToggle:checked + .dark-mode-slider:before {
      transform: translateX(24px);
    }

    #darkModeToggle:focus + .dark-mode-slider {
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
    }

    /* Dark Mode Modal Styles */
    body.dark-mode .modern-modal {
      background: #1e293b;
      color: #f1f5f9;
    }

    body.dark-mode .modern-modal-header {
      border-bottom-color: #475569;
    }

    body.dark-mode .modern-modal-header h3 {
      color: #f1f5f9;
    }

    body.dark-mode .modern-modal-body p {
      color: #cbd5e1;
    }

    body.dark-mode .modern-modal-footer {
      border-top-color: #475569;
    }

    body.dark-mode .modern-modal-btn-secondary {
      background: #475569;
      color: #f1f5f9;
    }

    body.dark-mode .modern-modal-btn-secondary:hover {
      background: #64748b;
    }

    body.dark-mode .status-card.empty p {
      color: #94a3b8;
    }

    body.dark-mode #vehicles-section h3,
    body.dark-mode #vehicles-section h4 {
      color: #f1f5f9;
    }

    body.dark-mode #add-vehicle-form {
      background: #334155;
      border-color: #475569;
    }

    body.dark-mode #add-vehicle-form input {
      background: #1e293b;
      border-color: #475569;
      color: #f1f5f9;
    }

    body.dark-mode #add-vehicle-form input::placeholder {
      color: #64748b;
    }

    body.dark-mode #vehicles-list > div {
      background: #334155;
      border-color: #475569;
    }

    body.dark-mode #vehicles-list > div > div > div:first-child {
      color: #f1f5f9;
    }

    body.dark-mode #vehicles-list > div > div > div:last-child {
      color: #cbd5e1;
    }

    body.dark-mode .info-item p {
      color: #94a3b8;
    }

    body.dark-mode .dark-mode-toggle-container {
      background: #334155;
      border-color: #475569;
    }

    body.dark-mode .dark-mode-title {
      color: #f1f5f9;
    }

    body.dark-mode .dark-mode-desc {
      color: #cbd5e1;
    }

    body.dark-mode .appearance-section {
      border-top-color: #475569;
    }

    body.dark-mode .appearance-section-title {
      color: #f1f5f9;
    }

    body.dark-mode .status-card.empty h3 {
      color: #cbd5e1;
    }

    body.dark-mode .account-section {
      border-top-color: #475569;
    }

    body.dark-mode .account-section-title {
      color: #f1f5f9;
    }

    /* Statistics Dashboard Dark Mode */
    body.dark-mode .stats-dashboard {
      background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
      border-color: #475569;
    }

    body.dark-mode .stats-dashboard h3 {
      color: #f1f5f9;
    }

    body.dark-mode .stats-dashboard > div > div {
      background: #334155;
      border-color: #475569;
      color: #f1f5f9;
    }

    body.dark-mode .stats-dashboard > div > div > div:first-child {
      color: #f1f5f9;
    }

    body.dark-mode .stats-dashboard > div > div > div:last-child {
      color: #cbd5e1;
    }

    /* Reservation Calendar Dark Mode */
    body.dark-mode .reservation-calendar {
      background: transparent;
    }

    body.dark-mode .reservation-calendar h3 {
      color: #f1f5f9;
    }

    body.dark-mode #current-month-year {
      color: #f1f5f9;
    }

    body.dark-mode .calendar-day {
      background: #334155;
      border-color: #475569;
      color: #f1f5f9;
    }

    body.dark-mode .calendar-day:hover {
      background: #475569;
    }

    body.dark-mode .calendar-day.other-month {
      color: #64748b;
    }

    body.dark-mode .calendar-day.has-reservation {
      background: #667eea;
      color: white;
    }

    body.dark-mode .calendar-day.today {
      border-color: #667eea;
    }

    .calendar-day {
      aspect-ratio: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 8px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      background: #ffffff;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 14px;
      position: relative;
    }

    .calendar-day:hover {
      background: #f8fafc;
      transform: translateY(-2px);
    }

    .calendar-day.other-month {
      opacity: 0.4;
      color: #94a3b8;
    }

    .calendar-day.today {
      border-color: #667eea;
      font-weight: 600;
    }

    .calendar-day.has-reservation {
      background: #667eea;
      color: white;
      font-weight: 600;
    }

    .calendar-day-header {
      font-weight: 600;
      color: #64748b;
      text-align: center;
      padding: 8px;
      font-size: 12px;
      text-transform: uppercase;
    }

    body.dark-mode .calendar-day-header {
      color: #94a3b8;
    }

    @media (max-width: 640px) {
      .card {
        padding: 20px;
      }

      .header h1 {
        font-size: 22px;
      }

      .info-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <div class="header">
    <h1>👋 Welcome, <?= htmlspecialchars($username) ?></h1>
    <div class="header-actions">
      <button id="calendarBtn" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color: #ffffff; border: none; cursor: pointer; backdrop-filter: blur(10px);">📅 Reservation Calendar</button>
      <a class="btn btn-secondary" href="/IT-PARKING-MANAGEMENT/public/map.php">🗺️ Open Map</a>
      <button id="settingsBtn" class="btn btn-secondary" style="background: rgba(255,255,255,0.2); color: #ffffff; border: none; cursor: pointer; backdrop-filter: blur(10px);">⚙️ Settings</button>
    </div>
  </div>

    <div class="card">
      <div class="card-header">
        <h2>Dashboard</h2>
      </div>

      <!-- Settings Modal -->
      <div id="settings-modal-overlay" class="modern-modal-overlay" style="display: none;">
        <div class="modern-modal" style="max-width: 600px;">
          <div class="modern-modal-header">
            <h3>⚙️ Settings</h3>
            <button class="modern-modal-close" id="settings-modal-close">×</button>
          </div>
          <div class="modern-modal-body" style="max-height: 70vh; overflow-y: auto;">
            
            <!-- Vehicle Management Section -->
            <div id="vehicles-section" style="margin-bottom: 32px;">
              <h3 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 0 0 20px 0;">🚗 My Vehicles</h3>
              <div id="vehicles-list" style="margin-bottom: 20px;">
                <!-- Vehicles will be loaded here -->
              </div>
              <div id="add-vehicle-form" style="padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #334155;">Add New Vehicle</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: end;">
                  <div>
                    <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Name</label>
                    <input type="text" id="new-vehicle-name" placeholder="e.g., My Car" style="width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                  </div>
                  <div>
                    <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Number</label>
                    <input type="text" id="new-vehicle-no" placeholder="e.g., ABC-1234" style="width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                  </div>
                  <button id="add-vehicle-submit" class="btn-action" style="padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 500;">Add</button>
                </div>
              </div>
            </div>
            
            <!-- Appearance Section -->
            <div style="margin-top: 32px; padding-top: 24px; border-top: 2px solid #e2e8f0; transition: border-color 0.3s ease;" class="appearance-section">
              <h3 style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 16px; transition: color 0.3s ease;" class="appearance-section-title">Appearance</h3>
              <div class="dark-mode-toggle-container" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; transition: background 0.3s ease, border-color 0.3s ease;">
                <div>
                  <div class="dark-mode-title" style="font-weight: 600; color: #1e293b; margin-bottom: 4px; transition: color 0.3s ease;">Dark Mode</div>
                  <div class="dark-mode-desc" style="font-size: 13px; color: #64748b; transition: color 0.3s ease;">Toggle between light and dark theme</div>
                </div>
                <label style="position: relative; display: inline-block; width: 52px; height: 28px;">
                  <input type="checkbox" id="darkModeToggle" style="opacity: 0; width: 0; height: 0;">
                  <span class="dark-mode-slider"></span>
                </label>
              </div>
            </div>
            
            <!-- Account Section -->
            <div style="margin-top: 32px; padding-top: 24px; border-top: 2px solid #e2e8f0; transition: border-color 0.3s ease;" class="account-section">
              <h3 style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 16px; transition: color 0.3s ease;" class="account-section-title">Account</h3>
              <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="/IT-PARKING-MANAGEMENT/public/logout.php" class="modern-modal-btn modern-modal-btn-secondary" style="text-decoration: none; display: inline-block; text-align: center;">
                  Logout
                </a>
                <button id="deleteAccountSettings" class="modern-modal-btn modern-modal-btn-danger" style="width: 100%;">
                  🗑️ Delete Account
                </button>
              </div>
            </div>
          </div>
          <div class="modern-modal-footer">
            <button id="settings-close-btn" class="modern-modal-btn modern-modal-btn-secondary">Close</button>
          </div>
        </div>
      </div>

      <div class="status-card <?= $reservedSlot ? '' : 'empty' ?>">
      <?php if ($reservedSlot): ?>
        <h3>📍 Your Current Reservation</h3>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Parking Slot</span>
            <span class="info-value"><?= htmlspecialchars($reservedSlot['slot_number'] ?? $reservedSlot['svg_id']) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Name</span>
            <span class="info-value"><?= htmlspecialchars($reservedSlot['reservation_name']) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Vehicle Number</span>
            <span class="info-value"><?= htmlspecialchars($reservedSlot['vehicle_no']) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Status</span>
            <span class="info-value">
              <?php if ($reservedSlot['status'] === 'checked_in'): ?>
                <span class="badge checked_in">Checked In</span>
              <?php else: ?>
                <span class="badge reserved">Reserved</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="info-item">
            <span class="info-label">Reserved At</span>
            <span class="info-value"><?= htmlspecialchars($reservedSlot['reserved_at']); ?></span>
          </div>
        </div>
        <div style="margin-top: 24px;">
          <button id="cancel" class="btn-ghost">Release Slot</button>
        </div>
      <?php else: ?>
        <h3>🚗 No Active Reservation</h3>
        <p>You currently do not have a reservation.</p>
        <div style="margin-top: 24px;">
          <a class="btn-action" href="/IT-PARKING-MANAGEMENT/public/map.php" style="text-decoration: none; display: inline-block;">Reserve a Slot</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard" style="margin-top: 32px; padding: 24px; background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border-radius: 12px; border: 2px solid #e2e8f0; transition: background 0.3s ease, border-color 0.3s ease;">
      <h3 style="font-size: 20px; font-weight: 600; color: #1e293b; margin-bottom: 24px; transition: color 0.3s ease;">📊 Statistics Dashboard</h3>
      <div id="stats-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
        <!-- Stats will be loaded here -->
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
          <div style="font-size: 32px; font-weight: 700; color: #667eea; margin-bottom: 8px;" id="total-reservations">0</div>
          <div style="font-size: 14px; color: #64748b;">Total Reservations</div>
        </div>
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
          <div style="font-size: 32px; font-weight: 700; color: #10b981; margin-bottom: 8px;" id="current-month-reservations">0</div>
          <div style="font-size: 14px; color: #64748b;">This Month</div>
        </div>
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
          <div style="font-size: 24px; font-weight: 600; color: #f59e0b; margin-bottom: 8px;" id="most-used-vehicle">-</div>
          <div style="font-size: 14px; color: #64748b;">Most Used Vehicle</div>
        </div>
        <div style="text-align: center; padding: 20px; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
          <div style="font-size: 32px; font-weight: 700; color: #8b5cf6; margin-bottom: 8px;" id="average-duration">-</div>
          <div style="font-size: 14px; color: #64748b;">Avg. Duration (hrs)</div>
        </div>
      </div>
    </div>

  </div>

  <!-- Reservation Calendar Modal -->
  <div id="calendar-modal-overlay" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal" style="max-width: 900px; width: 95%;">
      <div class="modern-modal-header">
        <h3>📅 Reservation Calendar</h3>
        <button class="modern-modal-close" id="calendar-modal-close">×</button>
      </div>
      <div class="modern-modal-body" style="max-height: 80vh; overflow-y: auto;">
        <div class="reservation-calendar" style="padding: 0;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div></div>
            <div style="display: flex; gap: 12px; align-items: center;">
              <button id="prev-month" class="btn-ghost" style="padding: 8px 16px; font-size: 14px;">← Prev</button>
              <span id="current-month-year" style="font-weight: 600; color: #1e293b; min-width: 200px; text-align: center; transition: color 0.3s ease; font-size: 18px;"></span>
              <button id="next-month" class="btn-ghost" style="padding: 8px 16px; font-size: 14px;">Next →</button>
            </div>
            <div></div>
          </div>
          <div id="calendar-container" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;">
            <!-- Calendar will be generated here -->
          </div>
        </div>
      </div>
      <div class="modern-modal-footer">
        <button id="calendar-close-btn" class="modern-modal-btn modern-modal-btn-secondary">Close</button>
      </div>
    </div>
  </div>

  <!-- Modern Modal Dialogs -->
  <div id="modern-modal-overlay" class="modern-modal-overlay" style="display: none;">
    <div class="modern-modal">
      <div class="modern-modal-header">
        <h3 id="modern-modal-title">Title</h3>
        <button class="modern-modal-close" id="modern-modal-close">×</button>
      </div>
      <div class="modern-modal-body">
        <p id="modern-modal-message"></p>
        <input type="text" id="modern-modal-input" style="display: none; width: 100%; padding: 10px 12px; margin-top: 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;" placeholder="">
      </div>
      <div class="modern-modal-footer">
        <button id="modern-modal-cancel" class="modern-modal-btn modern-modal-btn-secondary" style="display: none;">Cancel</button>
        <button id="modern-modal-confirm" class="modern-modal-btn modern-modal-btn-primary">OK</button>
      </div>
    </div>
  </div>

<script>
// Dark Mode Toggle
const darkModeToggle = document.getElementById("darkModeToggle");
const body = document.body;

// Load dark mode preference
function loadDarkMode() {
  const darkMode = localStorage.getItem("darkMode") === "true";
  if (darkModeToggle) {
    darkModeToggle.checked = darkMode;
  }
  if (darkMode) {
    body.classList.add("dark-mode");
  } else {
    body.classList.remove("dark-mode");
  }
}

// Toggle dark mode
function toggleDarkMode() {
  const isDark = darkModeToggle?.checked || false;
  localStorage.setItem("darkMode", isDark);
  if (isDark) {
    body.classList.add("dark-mode");
  } else {
    body.classList.remove("dark-mode");
  }
}

// Initialize dark mode on page load
loadDarkMode();

// Listen for toggle changes
darkModeToggle?.addEventListener("change", toggleDarkMode);

// Modern Modal System
function showModal(options) {
  return new Promise((resolve) => {
    const overlay = document.getElementById("modern-modal-overlay");
    const title = document.getElementById("modern-modal-title");
    const message = document.getElementById("modern-modal-message");
    const input = document.getElementById("modern-modal-input");
    const confirmBtn = document.getElementById("modern-modal-confirm");
    const cancelBtn = document.getElementById("modern-modal-cancel");
    const closeBtn = document.getElementById("modern-modal-close");

    title.textContent = options.title || "Alert";
    message.textContent = options.message || "";
    
    // Show/hide input for prompt
    if (options.type === "prompt") {
      input.style.display = "block";
      input.value = options.defaultValue || "";
      input.placeholder = options.placeholder || "";
    } else {
      input.style.display = "none";
    }

    // Show/hide cancel button
    cancelBtn.style.display = options.type === "confirm" || options.type === "prompt" ? "inline-block" : "none";

    // Update button text and style
    if (options.type === "confirm" && options.danger) {
      confirmBtn.textContent = options.confirmText || "Delete";
      confirmBtn.className = "modern-modal-btn modern-modal-btn-danger";
    } else {
      confirmBtn.textContent = options.confirmText || "OK";
      confirmBtn.className = "modern-modal-btn modern-modal-btn-primary";
    }

    const handleConfirm = () => {
      const result = options.type === "prompt" ? input.value : true;
      overlay.style.display = "none";
      cleanup();
      resolve(result);
    };

    const handleCancel = () => {
      const result = options.type === "prompt" ? null : false;
      overlay.style.display = "none";
      cleanup();
      resolve(result);
    };

    const handleConfirmClick = () => {
      handleConfirm();
    };

    const handleCancelClick = () => {
      handleCancel();
    };

    // Enter/Escape key handling
    const handleKeyPress = (e) => {
      if (overlay.style.display === "none") return;
      if (e.key === "Enter" && (options.type !== "prompt" || document.activeElement === input)) {
        e.preventDefault();
        if (options.type === "prompt" || document.activeElement === confirmBtn || document.activeElement === cancelBtn) {
          handleConfirm();
        }
      } else if (e.key === "Escape") {
        e.preventDefault();
        handleCancel();
      }
    };

    const cleanup = () => {
      confirmBtn.removeEventListener("click", handleConfirmClick);
      cancelBtn.removeEventListener("click", handleCancelClick);
      closeBtn.removeEventListener("click", handleCancelClick);
      overlay.removeEventListener("click", handleOverlayClick);
      window.removeEventListener("keydown", handleKeyPress);
    };

    const handleOverlayClick = (e) => {
      if (e.target === overlay) {
        handleCancel();
      }
    };

    confirmBtn.addEventListener("click", handleConfirmClick);
    cancelBtn.addEventListener("click", handleCancelClick);
    closeBtn.addEventListener("click", handleCancelClick);
    overlay.addEventListener("click", handleOverlayClick);
    window.addEventListener("keydown", handleKeyPress);

    overlay.style.display = "flex";
    if (options.type === "prompt") {
      setTimeout(() => input.focus(), 100);
    }
  });
}

// Wrapper functions
async function modernAlert(message, title = "Alert") {
  await showModal({ type: "alert", title, message });
}

async function modernConfirm(message, title = "Confirm", danger = false) {
  return await showModal({ type: "confirm", title, message, danger });
}

async function modernPrompt(message, defaultValue = "", title = "Input", placeholder = "") {
  return await showModal({ type: "prompt", title, message, defaultValue, placeholder });
}

document.getElementById("cancel")?.addEventListener("click", async () => {
  const confirmed = await modernConfirm(
    "Are you sure you want to release your reserved slot?",
    "Release Slot"
  );
  if (!confirmed) return;
  
  const btn = document.getElementById("cancel");
  const originalText = btn.textContent;
  btn.textContent = "Releasing...";
  btn.disabled = true;
  
  try {
    const r = await fetch("/IT-PARKING-MANAGEMENT/public/api.php?action=cancel", {method:"POST"});
    if (r.ok) {
      location.reload();
    } else {
      await modernAlert("Failed to release slot. Please try again.", "Error");
      btn.textContent = originalText;
      btn.disabled = false;
    }
  } catch (err) {
    await modernAlert("Network error. Please try again.", "Error");
    btn.textContent = originalText;
    btn.disabled = false;
  }
});

const deleteAccountHandler = async (buttonElement) => {
  const confirmed = await modernConfirm(
    "⚠️ WARNING: Are you sure you want to delete your account?\n\nThis will:\n- Delete your account permanently\n- Cancel all your reservations\n- This action cannot be undone!",
    "Delete Account",
    true
  );
  if (!confirmed) {
    return;
  }
  
  const confirmation = await modernPrompt(
    "Type 'DELETE' to confirm account deletion:",
    "",
    "Confirm Deletion",
    "Type DELETE here"
  );
  if (confirmation !== 'DELETE') {
    await modernAlert("Account deletion cancelled.", "Cancelled");
    return;
  }
  
  const originalText = buttonElement.textContent;
  buttonElement.textContent = "Deleting...";
  buttonElement.disabled = true;
  
  try {
    const r = await fetch("/IT-PARKING-MANAGEMENT/public/api.php?action=delete_account", {
      method: "POST",
      credentials: "include"
    });
    const data = await r.json();
    
    if (r.ok) {
      await modernAlert("Account deleted successfully. Redirecting to login...", "Success");
      window.location.href = data.redirect || "/IT-PARKING-MANAGEMENT/public/login.php";
    } else {
      await modernAlert("Failed to delete account: " + (data.error || "Unknown error"), "Error");
      buttonElement.textContent = originalText;
      buttonElement.disabled = false;
    }
  } catch (err) {
    await modernAlert("Network error. Please try again.", "Error");
    buttonElement.textContent = originalText;
    buttonElement.disabled = false;
  }
};

document.getElementById("deleteAccountSettings")?.addEventListener("click", () => deleteAccountHandler(document.getElementById("deleteAccountSettings")));

// Vehicle Management
const API = "/IT-PARKING-MANAGEMENT/public/api.php";
const vehiclesSection = document.getElementById("vehicles-section");
const vehiclesList = document.getElementById("vehicles-list");
const addVehicleForm = document.getElementById("add-vehicle-form");
const newVehicleName = document.getElementById("new-vehicle-name");
const newVehicleNo = document.getElementById("new-vehicle-no");
const addVehicleSubmit = document.getElementById("add-vehicle-submit");

async function loadVehicles() {
  try {
    const res = await fetch(`${API}?action=vehicles`, { credentials: "include" });
    if (!res.ok) return;
    const vehicles = await res.json();
    
    if (vehicles.length === 0) {
      vehiclesList.innerHTML = '<p style="color: #64748b; text-align: center; padding: 20px;">No vehicles added yet.</p>';
      return;
    }
    
    vehiclesList.innerHTML = vehicles.map(v => `
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: white; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 8px;">
        <div>
          <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;">${escapeHtml(v.vehicle_name)}</div>
          <div style="font-size: 13px; color: #64748b;">${escapeHtml(v.vehicle_no)}</div>
        </div>
        <button onclick="deleteVehicle(${v.id}, '${escapeHtml(v.vehicle_name, true)}')" 
                style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500;">
          Delete
        </button>
      </div>
    `).join('');
    
    // Hide add form if at max (3 vehicles)
    if (vehicles.length >= 3) {
      addVehicleForm.style.display = 'none';
    } else {
      addVehicleForm.style.display = 'block';
    }
  } catch (err) {
    console.error("Failed to load vehicles:", err);
    vehiclesList.innerHTML = '<p style="color: #ef4444; text-align: center; padding: 20px;">Failed to load vehicles.</p>';
  }
}

async function addVehicle() {
  const name = newVehicleName.value.trim();
  const no = newVehicleNo.value.trim();
  
  if (!name || !no) {
    await modernAlert("Please enter vehicle name and number.", "Required Field");
    return;
  }
  
  addVehicleSubmit.disabled = true;
  addVehicleSubmit.textContent = "Adding...";
  
  try {
    const res = await fetch(`${API}?action=add_vehicle`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ vehicle_name: name, vehicle_no: no })
    });
    const data = await res.json();
    
    if (res.ok) {
      newVehicleName.value = "";
      newVehicleNo.value = "";
      await loadVehicles();
      await modernAlert("Vehicle added successfully!", "Success");
    } else {
      await modernAlert("Error: " + (data.error || "Failed to add vehicle"), "Error");
    }
  } catch (err) {
    await modernAlert("Network error. Please try again.", "Error");
  } finally {
    addVehicleSubmit.disabled = false;
    addVehicleSubmit.textContent = "Add";
  }
}

async function deleteVehicle(vehicleId, vehicleName) {
  const confirmed = await modernConfirm(
    `Are you sure you want to delete vehicle "${vehicleName}"?`,
    "Delete Vehicle",
    true
  );
  if (!confirmed) {
    return;
  }
  
  try {
    const res = await fetch(`${API}?action=delete_vehicle`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ vehicle_id: vehicleId })
    });
    const data = await res.json();
    
    if (res.ok) {
      await loadVehicles();
      await modernAlert("Vehicle deleted successfully!", "Success");
    } else {
      await modernAlert("Error: " + (data.error || "Failed to delete vehicle"), "Error");
    }
  } catch (err) {
    await modernAlert("Network error. Please try again.", "Error");
  }
}

function escapeHtml(text, doubleEncode = false) {
  const div = document.createElement('div');
  div.textContent = text;
  const result = div.innerHTML;
  return doubleEncode ? result.replace(/'/g, "&#39;") : result;
}

// Settings Modal
const settingsBtn = document.getElementById("settingsBtn");
const settingsModal = document.getElementById("settings-modal-overlay");
const settingsCloseBtn = document.getElementById("settings-close-btn");
const settingsModalCloseBtn = document.getElementById("settings-modal-close");

function openSettings() {
  settingsModal.style.display = "flex";
  loadVehicles(); // Load vehicles when opening settings
}

function closeSettings() {
  settingsModal.style.display = "none";
}

settingsBtn?.addEventListener("click", openSettings);
settingsCloseBtn?.addEventListener("click", closeSettings);
settingsModalCloseBtn?.addEventListener("click", closeSettings);

// Close settings when clicking on overlay
settingsModal?.addEventListener("click", (e) => {
  if (e.target === settingsModal) {
    closeSettings();
  }
});

addVehicleSubmit?.addEventListener("click", addVehicle);

// Allow Enter key to submit add vehicle form
newVehicleNo?.addEventListener("keypress", (e) => {
  if (e.key === 'Enter') {
    addVehicle();
  }
});

// Statistics Dashboard
async function loadStatistics() {
  try {
    const res = await fetch(`${API}?action=stats`, { credentials: "include" });
    if (!res.ok) return;
    const stats = await res.json();
    
    document.getElementById("total-reservations").textContent = stats.total_reservations || 0;
    document.getElementById("current-month-reservations").textContent = stats.current_month || 0;
    document.getElementById("most-used-vehicle").textContent = stats.most_used_vehicle || '-';
    document.getElementById("average-duration").textContent = stats.average_duration || '-';
  } catch (err) {
    console.error("Failed to load statistics:", err);
  }
}

// Reservation Calendar
let currentDate = new Date();
let currentYear = currentDate.getFullYear();
let currentMonth = currentDate.getMonth();

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
  'July', 'August', 'September', 'October', 'November', 'December'];

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

async function loadCalendar() {
  try {
    const res = await fetch(`${API}?action=calendar&year=${currentYear}&month=${currentMonth + 1}`, { credentials: "include" });
    if (!res.ok) return;
    const data = await res.json();
    
    generateCalendar(data);
  } catch (err) {
    console.error("Failed to load calendar:", err);
  }
}

function generateCalendar(data) {
  const container = document.getElementById("calendar-container");
  const monthYearDisplay = document.getElementById("current-month-year");
  
  monthYearDisplay.textContent = `${monthNames[currentMonth]} ${currentYear}`;
  
  // Clear container
  container.innerHTML = '';
  
  // Add day headers
  dayNames.forEach(day => {
    const header = document.createElement("div");
    header.className = "calendar-day-header";
    header.textContent = day;
    container.appendChild(header);
  });
  
  // Get first day of month and days in month
  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
  
  // Add empty cells for days before month starts
  for (let i = 0; i < firstDay; i++) {
    const day = document.createElement("div");
    day.className = "calendar-day other-month";
    day.textContent = daysInPrevMonth - firstDay + i + 1;
    container.appendChild(day);
  }
  
  // Add days of current month
  for (let day = 1; day <= daysInMonth; day++) {
    const dayEl = document.createElement("div");
    dayEl.className = "calendar-day";
    
    // Check if today
    const today = new Date();
    if (day === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear()) {
      dayEl.classList.add("today");
    }
    
    // Check if has reservation
    if (data.reservations && data.reservations[day]) {
      dayEl.classList.add("has-reservation");
      dayEl.innerHTML = `
        <div style="font-weight: 600;">${day}</div>
        <div style="font-size: 10px; margin-top: 4px;">${data.reservations[day].length}</div>
      `;
      dayEl.title = `${data.reservations[day].length} reservation(s) on ${monthNames[currentMonth]} ${day}`;
    } else {
      dayEl.textContent = day;
    }
    
    container.appendChild(dayEl);
  }
  
  // Fill remaining cells to complete 7-day week
  const totalCells = container.children.length;
  const remainingCells = 42 - totalCells; // 6 rows × 7 days
  for (let i = 1; i <= remainingCells; i++) {
    const day = document.createElement("div");
    day.className = "calendar-day other-month";
    day.textContent = i;
    container.appendChild(day);
  }
}

document.getElementById("prev-month")?.addEventListener("click", () => {
  currentMonth--;
  if (currentMonth < 0) {
    currentMonth = 11;
    currentYear--;
  }
  loadCalendar();
});

document.getElementById("next-month")?.addEventListener("click", () => {
  currentMonth++;
  if (currentMonth > 11) {
    currentMonth = 0;
    currentYear++;
  }
  loadCalendar();
});

// Calendar Modal
const calendarBtn = document.getElementById("calendarBtn");
const calendarModal = document.getElementById("calendar-modal-overlay");
const calendarCloseBtn = document.getElementById("calendar-close-btn");
const calendarModalCloseBtn = document.getElementById("calendar-modal-close");

function openCalendar() {
  calendarModal.style.display = "flex";
  loadCalendar(); // Load calendar when opening modal
}

function closeCalendar() {
  calendarModal.style.display = "none";
}

calendarBtn?.addEventListener("click", openCalendar);
calendarCloseBtn?.addEventListener("click", closeCalendar);
calendarModalCloseBtn?.addEventListener("click", closeCalendar);

// Close calendar when clicking on overlay
calendarModal?.addEventListener("click", (e) => {
  if (e.target === calendarModal) {
    closeCalendar();
  }
});

// Load statistics on page load (calendar loads when modal opens)
loadStatistics();
</script>

</body>
</html>
