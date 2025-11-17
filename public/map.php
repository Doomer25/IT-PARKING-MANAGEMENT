<?php
require_once __DIR__ . '/../src/auth.php';
// Allow admin or logged in users
if (!is_admin() && !isset($_SESSION['user_id'])) {
    header('Location: /IT-PARKING-MANAGEMENT/public/login.php');
    exit;
}
require_once __DIR__ . '/../src/db.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>IT Department — Parking Map</title>
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
      max-width: 1200px;
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
      justify-content: center;
      padding: 10px 20px;
      border-radius: 8px;
      border: none;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: box-shadow 0.3s ease, background 0.3s ease;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      position: relative;
    }

    .btn-primary {
      background: #ffffff;
      color: #667eea;
    }

    .btn-primary:hover {
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
    }

    .map-wrap {
      max-width: 1200px;
      margin: 0 auto;
      background: #ffffff;
      padding: 32px;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .map-header {
      margin-bottom: 24px;
      padding-bottom: 20px;
      border-bottom: 2px solid #e2e8f0;
    }

    .map-header h2 {
      font-size: 24px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 16px;
    }

    .legend {
      display: flex;
      gap: 24px;
      flex-wrap: wrap;
      margin-top: 16px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: #64748b;
    }

    .legend-color {
      width: 24px;
      height: 24px;
      border-radius: 4px;
      border: 2px solid rgba(0,0,0,0.1);
    }

    .legend-color.free { background: #7dd36b; }
    .legend-color.reserved { background: #facc15; }
    .legend-color.occupied { background: #ef4444; }

    svg {
      width: 100%;
      height: auto;
      display: block;
      border-radius: 8px;
    }

    /* Loading Spinner */
    .loading {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 100;
    }

    .loading.active {
      display: block;
    }

    .spinner {
      width: 40px;
      height: 40px;
      border: 4px solid rgba(102, 126, 234, 0.2);
      border-top-color: #667eea;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Toast */
    #toast {
      position: fixed;
      left: 50%;
      bottom: 24px;
      transform: translateX(-50%);
      background: #1e293b;
      color: #f9fafb;
      padding: 12px 20px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 500;
      opacity: 0;
      pointer-events: none;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 60;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    #toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(-8px);
    }

    #toast::before {
      content: '✓';
      background: #22c55e;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }

    #toast.error::before {
      background: #ef4444;
      content: '✕';
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>🚗 IT Department Parking</h1>
    <div class="header-actions">
      <a href="/IT-PARKING-MANAGEMENT/public/index.php" class="btn btn-secondary">Dashboard</a>
      <a href="/IT-PARKING-MANAGEMENT/public/logout.php" class="btn btn-primary">Logout</a>
    </div>
  </div>

  <div class="map-wrap">
    <div class="map-header">
      <h2>Parking Map</h2>
      <div class="legend">
        <div class="legend-item">
          <div class="legend-color free"></div>
          <span>Available</span>
        </div>
        <div class="legend-item">
          <div class="legend-color reserved"></div>
          <span>Reserved</span>
        </div>
        <div class="legend-item">
          <div class="legend-color occupied"></div>
          <span>Occupied</span>
        </div>
      </div>
    </div>

    <?php
    $svg_path = __DIR__ . '/assets/map.svg';
    if (file_exists($svg_path)) {
        echo file_get_contents($svg_path);
    } else {
        echo '<p style="color:red">map.svg not found in public/assets</p>';
    }
    ?>

    <!-- Reservation Modal -->
    <div id="reserve-modal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Reserve Parking Slot</h3>
          <button class="modal-close" id="reserve-modal-close">×</button>
        </div>
        <p id="reserve-slot-label" class="modal-subtitle"></p>
        <form id="reserve-form">
          <div class="form-group">
            <label for="reserve-name">Your Name</label>
            <input type="text" id="reserve-name" required placeholder="Enter your full name" autocomplete="name">
          </div>
          <div class="form-group">
            <label for="reserve-vehicle">Select Vehicle</label>
            <select id="reserve-vehicle" required style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; background: #ffffff;">
              <option value="">Loading vehicles...</option>
            </select>
          </div>
          <div class="modal-actions">
            <button type="button" id="reserve-cancel" class="btn-modal btn-cancel">Cancel</button>
            <button type="submit" class="btn-modal btn-confirm">
              <span>Confirm Reservation</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Action Modal (Check-in / Release) -->
    <div id="action-modal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Slot Action</h3>
          <button class="modal-close" id="action-modal-close">×</button>
        </div>
        <p id="action-text" class="modal-subtitle"></p>
        <div class="modal-actions">
          <button type="button" id="action-secondary" class="btn-modal btn-cancel">Release</button>
          <button type="button" id="action-primary" class="btn-modal btn-confirm">Check in</button>
        </div>
      </div>
    </div>

    <!-- Admin Slot Details Modal -->
    <div id="admin-slot-modal" class="modal-overlay">
      <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
          <h3>Slot Reservation Details</h3>
          <button class="modal-close" id="admin-slot-modal-close">×</button>
        </div>
        <div id="admin-slot-content" style="padding: 24px;">
          <!-- Content will be populated by JavaScript -->
        </div>
        <div class="modal-actions">
          <button type="button" id="admin-slot-close-btn" class="btn-modal btn-cancel">Close</button>
          <button type="button" id="admin-slot-release-btn" class="btn-modal btn-confirm" style="background: #ef4444; color: #ffffff;">
            Release Slot
          </button>
        </div>
      </div>
    </div>

  </div>

  <div class="loading" id="loading">
    <div class="spinner"></div>
  </div>

  <div id="toast"></div>

  <style>
    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 50;
      animation: fadeIn 0.2s ease;
    }

    .modal-overlay.show {
      display: flex;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background: #ffffff;
      padding: 0;
      border-radius: 16px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 25px 50px rgba(0,0,0,0.25);
      animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
    }

    @keyframes slideUp {
      from {
        transform: translateY(20px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px 24px 16px;
      border-bottom: 1px solid #e2e8f0;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 28px;
      color: #94a3b8;
      cursor: pointer;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: all 0.2s;
      line-height: 1;
    }

    .modal-close:hover {
      background: #f1f5f9;
      color: #64748b;
    }

    .modal-subtitle {
      margin: 0;
      padding: 16px 24px;
      font-size: 14px;
      color: #64748b;
      line-height: 1.6;
    }

    .form-group {
      margin-bottom: 20px;
      padding: 0 24px;
    }

    .form-group:first-of-type {
      padding-top: 8px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #334155;
      margin-bottom: 8px;
    }

    .form-group input {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.2s;
      font-family: inherit;
    }

    .form-group input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group input::placeholder {
      color: #cbd5e1;
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      padding: 20px 24px 24px;
      border-top: 1px solid #e2e8f0;
      background: #f8fafc;
    }

    .btn-modal {
      padding: 10px 20px;
      border-radius: 8px;
      border: none;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: box-shadow 0.3s ease, background 0.3s ease, outline 0.3s ease;
      font-family: inherit;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      position: relative;
    }


    .btn-cancel {
      background: #e2e8f0;
      color: #475569;
    }

    .btn-modal.btn-cancel:hover,
    .btn-cancel:hover {
      background: #cbd5e1 !important;
      box-shadow: 0 0 25px rgba(71, 85, 105, 0.8), 0 0 50px rgba(71, 85, 105, 0.5), 0 4px 12px rgba(0,0,0,0.1) !important;
      outline: 2px solid rgba(71, 85, 105, 0.5) !important;
      outline-offset: 4px !important;
    }

    .btn-confirm {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
      position: relative;
    }

    .btn-modal.btn-confirm:hover,
    .btn-confirm:hover {
      box-shadow: 0 0 35px rgba(102, 126, 234, 1), 0 0 70px rgba(102, 126, 234, 0.7), 0 4px 12px rgba(102, 126, 234, 0.4) !important;
      outline: 2px solid rgba(102, 126, 234, 0.7) !important;
      outline-offset: 4px !important;
    }

    .btn-confirm:active {
      box-shadow: 0 0 15px rgba(102, 126, 234, 0.5), 0 2px 6px rgba(102, 126, 234, 0.3);
    }

    @media (max-width: 640px) {
      .map-wrap {
        padding: 20px;
      }

      .header h1 {
        font-size: 22px;
      }

      .modal-content {
        max-width: 90%;
        margin: 20px;
      }
    }
  </style>

<script>
(function(){
  const API = "/IT-PARKING-MANAGEMENT/public/api.php";
  let CURRENT_USER = null;
  let IS_ADMIN = false;
  
  // Admin tooltip element
  let adminTooltip = null;

  // track which slot we're reserving
  let CURRENT_SLOT_ID = null;
  let CURRENT_SLOT_LABEL = null;

  // track action mode: 'reserved' or 'checked_in'
  let ACTION_MODE = null;

  // modal elements
  const reserveModal      = document.getElementById("reserve-modal");
  const reserveLabel      = document.getElementById("reserve-slot-label");
  const inputName         = document.getElementById("reserve-name");
  const inputVehicle      = document.getElementById("reserve-vehicle"); // This is now a select dropdown
  const formReserve       = document.getElementById("reserve-form");
  const btnReserveCancel  = document.getElementById("reserve-cancel");

  const actionModal       = document.getElementById("action-modal");
  const actionText        = document.getElementById("action-text");
  const btnActionPrimary  = document.getElementById("action-primary");
  const btnActionSecondary= document.getElementById("action-secondary");

  const adminSlotModal    = document.getElementById("admin-slot-modal");
  const adminSlotContent  = document.getElementById("admin-slot-content");
  const adminSlotReleaseBtn = document.getElementById("admin-slot-release-btn");
  const adminSlotCloseBtn = document.getElementById("admin-slot-close-btn");

  const toast             = document.getElementById("toast");

  function showToast(msg, isError = false) {
    if (!toast) return;
    toast.textContent = msg;
    toast.className = isError ? "error" : "";
    toast.classList.add("show");
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(() => {
      toast.classList.remove("show");
    }, 3000);
  }

  function showLoading(show = true) {
    const loading = document.getElementById("loading");
    if (loading) {
      loading.classList.toggle("active", show);
    }
  }

  async function apiGet(action){
    return fetch(`${API}?action=${encodeURIComponent(action)}`, {
      credentials:"include"
    });
  }

  async function apiPost(action, body = {}){
    return fetch(`${API}?action=${encodeURIComponent(action)}`, {
      method:"POST",
      headers:{ "Content-Type":"application/json" },
      credentials:"include",
      body:JSON.stringify(body)
    });
  }

  function el(id){ return document.getElementById(id); }

  async function loadUser(){
    const res = await apiGet("me");
    if (!res.ok) return;
    CURRENT_USER = await res.json();
    IS_ADMIN = CURRENT_USER && CURRENT_USER.is_admin === true;
    
    // Create admin tooltip element
    if (IS_ADMIN && !adminTooltip) {
      adminTooltip = document.createElement('div');
      adminTooltip.id = 'admin-tooltip';
      adminTooltip.style.cssText = 'position: fixed; background: #1e293b; color: #fff; padding: 12px 16px; border-radius: 8px; font-size: 13px; z-index: 1000; pointer-events: none; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 300px;';
      document.body.appendChild(adminTooltip);
    }
    
    // Add admin dashboard link to header
    if (IS_ADMIN) {
      const headerActions = document.querySelector('.header-actions');
      if (headerActions && !document.querySelector('.btn-admin-dashboard')) {
        const adminLink = document.createElement('a');
        adminLink.href = '/IT-PARKING-MANAGEMENT/public/admin/dashboard.php';
        adminLink.className = 'btn btn-secondary btn-admin-dashboard';
        adminLink.textContent = '⚙️ Admin Panel';
        headerActions.insertBefore(adminLink, headerActions.firstChild);
      }
    }
  }

  async function refreshSlots(){
    const res = await apiGet("slots");
    if (!res.ok) {
      console.error("slots failed");
      return;
    }
    const slots = await res.json();
    console.log("Slots loaded:", slots.length, "slots");
    if (slots.length > 0) {
      console.log("First slot example:", slots[0]);
    }

    slots.forEach(s => {
      const node = el(s.svg_id);
      if (!node) return;

      node.dataset.owner = "";
      node.dataset.status = "";

      const status = (s.status || "").toLowerCase();
      const hasReservation = !!s.user_id;

      let title = s.label || s.svg_id;
      // Always extract label from svg_id as it's the source of truth
      const svgId = (s.svg_id || "").trim();
      let slotLabel = "";
      
      if (svgId.startsWith("slot-")) {
        const slotNum = parseInt(svgId.replace("slot-", ""));
        if (!isNaN(slotNum)) {
          // Map slot numbers to labels based on SVG structure
          if (slotNum >= 9 && slotNum <= 14) {
            slotLabel = "R" + (slotNum - 8); // slot-9 = R1, slot-10 = R2, etc.
          } else if (slotNum >= 20 && slotNum <= 27) {
            slotLabel = "R" + (slotNum - 13); // slot-20 = R7, slot-21 = R8, etc.
          } else if (slotNum >= 15 && slotNum <= 19) {
            slotLabel = "R" + slotNum; // slot-15 = R15, slot-16 = R16, etc.
          } else if (slotNum === 8) {
            slotLabel = "HOD";
          } else if (slotNum >= 1 && slotNum <= 7) {
            slotLabel = "G" + slotNum; // G1-G7
          }
        }
      }
      
      // If we couldn't extract from svg_id, try using the label from database
      if (!slotLabel) {
        slotLabel = (s.label || "").toUpperCase().trim();
      }
      
      // Debug: log slot label extraction for R slots
      if (slotLabel && slotLabel.startsWith('R')) {
        console.log(`Slot ${svgId}: extracted label="${slotLabel}"`);
      }
      
      // Store label in data attribute for easy access
      node.dataset.slotLabel = slotLabel;

      // Check if user can reserve this slot based on their role
      let canReserve = false;
      
      // If no user loaded yet, allow all (will be updated when user loads)
      if (!CURRENT_USER) {
        canReserve = true;
      } else {
        const userType = (CURRENT_USER.user_type || "").toLowerCase();
        
        if (userType === 'hod') {
          // HOD: Can reserve all slots
          canReserve = true;
        } else if (userType === 'faculty') {
          // Faculty: Can reserve all except HOD
          canReserve = (slotLabel !== 'HOD' && slotLabel !== '');
        } else if (userType === 'normal') {
          // Normal: Can only reserve R1-R19
          if (slotLabel && slotLabel.length > 0) {
            const rMatch = slotLabel.match(/^R(\d+)$/);
            if (rMatch) {
              const num = parseInt(rMatch[1]);
              canReserve = (num >= 1 && num <= 19);
              // Debug log for first few slots
              if (slotLabel === 'R1' || slotLabel === 'R2' || slotLabel === 'R15' || slotLabel === 'R19') {
                console.log(`Slot ${slotLabel}: canReserve=${canReserve}, num=${num}`);
              }
            } else {
              // Debug: log non-matching labels
              if (slotLabel.startsWith('R') || slotLabel.startsWith('G') || slotLabel === 'HOD') {
                console.log(`Normal user - Slot label "${slotLabel}" did not match R1-R19 pattern`);
              }
            }
          } else {
            console.log(`Normal user - Empty slotLabel for svg_id: ${s.svg_id}`);
          }
        }
      }

      if (!hasReservation) {
        // Reset opacity first
        node.style.opacity = "1";
        if (canReserve || IS_ADMIN) {
          // Admin can reserve any slot, regular users only allowed ones
          node.style.fill = "#7dd36b"; // GREEN
          node.style.cursor = "pointer";
          node.title = `${title} — Free`;
        } else {
          node.style.fill = "#94a3b8"; // GRAY - not allowed
          node.style.cursor = "not-allowed";
          node.style.opacity = "0.6";
          node.title = `${title} — Not available for your account type`;
        }
        return;
      }

      node.dataset.owner = s.user_id;
      node.dataset.status = status;
      // Store label for reserved slots too
      node.dataset.slotLabel = slotLabel;

      // Reset opacity for reserved slots
      node.style.opacity = "1";

      if (status === "checked_in") {
        node.style.fill = "#ef4444"; // RED
      } else {
        node.style.fill = "#facc15"; // YELLOW
      }

      const mine = CURRENT_USER && !IS_ADMIN && Number(s.user_id) === Number(CURRENT_USER.id);

      if (IS_ADMIN && hasReservation) {
        // Admin can see info and free slots
        node.style.cursor = "pointer";
        node.style.opacity = "1";
        title += " — Reserved";
        // Store slot_id and user info for admin modal
        node.dataset.slotDbId = s.slot_db_id;
        if (s.user_name) node.dataset.userName = s.user_name;
        if (s.user_email) node.dataset.userEmail = s.user_email;
        if (s.user_type) node.dataset.userType = s.user_type;
        if (s.reservation_name) node.dataset.reservationName = s.reservation_name;
        if (s.vehicle_no) node.dataset.vehicleNo = s.vehicle_no;
        if (s.vehicle_name) node.dataset.vehicleName = s.vehicle_name;
        if (s.reserved_at) node.dataset.reservedAt = s.reserved_at;
        if (s.status) node.dataset.resStatus = s.status;
      } else if (mine) {
        node.style.cursor = "pointer";
        node.style.opacity = "1";
        title += " — YOUR reservation";
      } else {
        node.style.cursor = "not-allowed";
        node.style.opacity = "0.8";
        title += " — Reserved";
      }

      if (s.reservation_name) {
        title += ` (${s.reservation_name}`;
        if (s.vehicle_no) title += `, ${s.vehicle_no}`;
        title += `)`;
      }

      node.title = title;
    });
  }

  // ------- Reservation Modal Helpers -------
  async function openReserveModal(svgId, labelText) {
    CURRENT_SLOT_ID = svgId;
    CURRENT_SLOT_LABEL = labelText || svgId;

    reserveLabel.textContent = "You are reserving: " + CURRENT_SLOT_LABEL;
    inputName.value = "";
    
    // Load vehicles for dropdown
    inputVehicle.innerHTML = '<option value="">Loading...</option>';
    
    try {
      const res = await apiGet("vehicles");
      if (res.ok) {
        const vehicles = await res.json();
        if (vehicles.length === 0) {
          inputVehicle.innerHTML = '<option value="">No vehicles found. Please add a vehicle in dashboard.</option>';
          inputVehicle.disabled = true;
        } else {
          inputVehicle.disabled = false;
          inputVehicle.innerHTML = vehicles.map(v => 
            `<option value="${v.id}">${escapeHtml(v.vehicle_name)} - ${escapeHtml(v.vehicle_no)}</option>`
          ).join('');
          // Select first vehicle by default
          if (vehicles.length > 0) {
            inputVehicle.value = vehicles[0].id;
          }
        }
      } else {
        inputVehicle.innerHTML = '<option value="">Failed to load vehicles</option>';
        inputVehicle.disabled = true;
      }
    } catch (err) {
      inputVehicle.innerHTML = '<option value="">Error loading vehicles</option>';
      inputVehicle.disabled = true;
    }
    
    reserveModal.classList.add("show");
    setTimeout(() => inputName.focus(), 100);
  }

  function closeReserveModal() {
    reserveModal.classList.remove("show");
    CURRENT_SLOT_ID = null;
    CURRENT_SLOT_LABEL = null;
  }

  if (formReserve) {
    formReserve.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!CURRENT_SLOT_ID) {
        showToast("No slot selected.");
        return;
      }
      const name = inputName.value.trim();
      const vehicleId = inputVehicle.value;
      if (!name || !vehicleId) {
        showToast("Please enter name & select a vehicle.");
        return;
      }

      showLoading(true);
      try {
        const res = await apiPost("reserve", {
          slot_svg_id: CURRENT_SLOT_ID,
          name: name,
          vehicle_id: parseInt(vehicleId)
        });
        const body = await res.json();
        if (!res.ok) {
          showToast(body.error || "Could not reserve", true);
          return;
        }
        showToast("✓ Slot reserved successfully");
        closeReserveModal();
        await refreshSlots();
      } catch (err) {
        console.error(err);
        showToast("Network error while reserving", true);
      } finally {
        showLoading(false);
      }
    });
  }

  if (btnReserveCancel) {
    btnReserveCancel.addEventListener("click", () => {
      closeReserveModal();
    });
  }

  const reserveModalClose = document.getElementById("reserve-modal-close");
  if (reserveModalClose) {
    reserveModalClose.addEventListener("click", () => {
      closeReserveModal();
    });
  }

  reserveModal.addEventListener("click", (e) => {
    if (e.target === reserveModal) {
      closeReserveModal();
    }
  });

  // ------- Action Modal Helpers (Check-in / Release) -------
  function openActionModal(mode) {
    ACTION_MODE = mode;

    if (mode === "reserved") {
      actionText.textContent = "You already reserved this slot. What do you want to do?";
      btnActionPrimary.textContent = "Check in";
      btnActionSecondary.textContent = "Release";
      btnActionSecondary.style.display = "inline-flex";
    } else if (mode === "checked_in") {
      actionText.textContent = "You are checked in here. Do you want to release this slot?";
      btnActionPrimary.textContent = "Release";
      btnActionSecondary.style.display = "none";
    } else {
      actionText.textContent = "";
    }

    actionModal.classList.add("show");
  }

  function closeActionModal() {
    actionModal.classList.remove("show");
    ACTION_MODE = null;
  }

  async function checkIn(){
    showLoading(true);
    try {
      const res = await apiPost("checkin");
      const body = await res.json();
      if (!res.ok){
        showToast(body.error || "Check-in failed", true);
        return;
      }
      showToast("✓ Checked in successfully");
      await refreshSlots();
    } catch (err) {
      showToast("Network error", true);
    } finally {
      showLoading(false);
    }
  }

  async function cancelReservation(){
    showLoading(true);
    try {
      const res = await apiPost("cancel");
      const body = await res.json();
      if (!res.ok){
        showToast(body.error || "Release failed", true);
        return;
      }
      showToast("✓ Reservation released");
      await refreshSlots();
    } catch (err) {
      showToast("Network error", true);
    } finally {
      showLoading(false);
    }
  }

  if (btnActionPrimary) {
    btnActionPrimary.addEventListener("click", async () => {
      if (ACTION_MODE === "reserved") {
        await checkIn();
      } else if (ACTION_MODE === "checked_in") {
        await cancelReservation();
      }
      closeActionModal();
    });
  }

  if (btnActionSecondary) {
    btnActionSecondary.addEventListener("click", async () => {
      if (ACTION_MODE === "reserved") {
        await cancelReservation();
      }
      closeActionModal();
    });
  }

  const actionModalClose = document.getElementById("action-modal-close");
  if (actionModalClose) {
    actionModalClose.addEventListener("click", () => {
      closeActionModal();
    });
  }

  actionModal.addEventListener("click", (e) => {
    if (e.target === actionModal) {
      closeActionModal();
    }
  });

  // ------- Admin Slot Modal Helpers -------
  function closeAdminSlotModal() {
    adminSlotModal.classList.remove("show");
    adminSlotReleaseBtn.dataset.slotId = "";
    adminSlotReleaseBtn.dataset.slotLabel = "";
  }

  const adminSlotModalClose = document.getElementById("admin-slot-modal-close");
  if (adminSlotModalClose) {
    adminSlotModalClose.addEventListener("click", () => {
      closeAdminSlotModal();
    });
  }

  if (adminSlotCloseBtn) {
    adminSlotCloseBtn.addEventListener("click", () => {
      closeAdminSlotModal();
    });
  }

  if (adminSlotReleaseBtn) {
    adminSlotReleaseBtn.addEventListener("click", async () => {
      const slotId = Number(adminSlotReleaseBtn.dataset.slotId || 0);
      const slotLabel = adminSlotReleaseBtn.dataset.slotLabel || "this slot";
      
      if (!slotId) return;
      
      if (!confirm(`Admin: Are you sure you want to free slot "${slotLabel}"?\n\nThis will cancel the reservation.`)) {
        return;
      }
      
      showLoading(true);
      try {
        const res = await apiPost("admin_free_slot", { slot_id: slotId });
        const data = await res.json();
        if (res.ok) {
          showToast("✓ Slot freed successfully");
          closeAdminSlotModal();
          await refreshSlots();
        } else {
          showToast(data.error || "Failed to free slot", true);
        }
      } catch (err) {
        showToast("Network error", true);
      } finally {
        showLoading(false);
      }
    });
  }

  adminSlotModal.addEventListener("click", (e) => {
    if (e.target === adminSlotModal) {
      closeAdminSlotModal();
    }
  });
           
  // ------- Click Handler on Slots -------
  document.addEventListener("click", async (e) => {
    const t = e.target;
    if (!t.id || !t.id.startsWith("slot-")) return;

    const owner = Number(t.dataset.owner || 0);
    const status = (t.dataset.status || "").toLowerCase();

    // free slot → check permissions and open modal
    if (!owner){
      // Get slot label from data attribute or extract from svg_id
      let slotLabel = (t.dataset.slotLabel || "").toUpperCase();
      
      // If no label in dataset, extract from svg_id
      if (!slotLabel && t.id && t.id.startsWith("slot-")) {
        const slotNum = parseInt(t.id.replace("slot-", ""));
        if (!isNaN(slotNum)) {
          if (slotNum >= 9 && slotNum <= 14) {
            slotLabel = "R" + (slotNum - 8);
          } else if (slotNum >= 20 && slotNum <= 27) {
            slotLabel = "R" + (slotNum - 13);
          } else if (slotNum >= 15 && slotNum <= 19) {
            slotLabel = "R" + slotNum;
          } else if (slotNum === 8) {
            slotLabel = "HOD";
          } else if (slotNum >= 1 && slotNum <= 7) {
            slotLabel = "G" + slotNum;
          }
        }
      }
      
      // If still no label, try from title
      if (!slotLabel) {
        slotLabel = (t.title || "").split(" —")[0].trim().toUpperCase();
      }
      
      let canReserve = false;
      
      if (CURRENT_USER) {
        const userType = (CURRENT_USER.user_type || "").toLowerCase();
        
        if (userType === 'hod') {
          canReserve = true;
        } else if (userType === 'faculty') {
          canReserve = (slotLabel !== 'HOD' && slotLabel !== '');
        } else if (userType === 'normal') {
          const rMatch = slotLabel.match(/^R(\d+)$/);
          if (rMatch) {
            const num = parseInt(rMatch[1]);
            canReserve = (num >= 1 && num <= 19);
          }
        }
      } else {
        // If no user loaded yet, allow (will be checked on server)
        canReserve = true;
      }
      
      if (!canReserve) {
        showToast("You don't have permission to reserve this slot.", true);
        return;
      }
      
      // Get label for display
      const titleText = t.title || "";
      const label = titleText.split(" —")[0].trim() || slotLabel || t.id;
      openReserveModal(t.id, label);
      return;
    }

    // Admin handling for reserved slots - show modal with details
    if (IS_ADMIN && owner) {
      const slotDbId = Number(t.dataset.slotDbId || 0);
      const slotLabel = (t.dataset.slotLabel || "").toUpperCase() || t.title.split(" —")[0].trim();
      
      if (slotDbId && adminSlotModal && adminSlotContent) {
        // Populate admin modal with slot details
        const userName = t.dataset.userName || "Unknown";
        const userEmail = t.dataset.userEmail || "N/A";
        const userType = t.dataset.userType || "N/A";
        const reservationName = t.dataset.reservationName || "N/A";
        const vehicleNo = t.dataset.vehicleNo || "N/A";
        const vehicleName = t.dataset.vehicleName || null;
        const reservedAt = t.dataset.reservedAt || "N/A";
        const status = t.dataset.resStatus || "reserved";
        
        const vehicleDisplay = vehicleName ? `${escapeHtml(vehicleName)} (${escapeHtml(vehicleNo)})` : escapeHtml(vehicleNo);
        
        adminSlotContent.innerHTML = `
          <div style="margin-bottom: 20px;">
            <div style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 16px;">
              Slot: ${escapeHtml(slotLabel)}
            </div>
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">User Name</div>
                <div style="font-size: 14px; color: #1e293b; font-weight: 500;">${escapeHtml(userName)}</div>
              </div>
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">User Email</div>
                <div style="font-size: 14px; color: #1e293b;">${escapeHtml(userEmail)}</div>
              </div>
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">User Type</div>
                <div style="font-size: 14px; color: #1e293b;">${escapeHtml(userType.toUpperCase())}</div>
              </div>
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Reservation Name</div>
                <div style="font-size: 14px; color: #1e293b;">${escapeHtml(reservationName)}</div>
              </div>
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Vehicle</div>
                <div style="font-size: 14px; color: #1e293b;">
                  ${vehicleName ? escapeHtml(vehicleName) + ' (' + escapeHtml(vehicleNo) + ')' : escapeHtml(vehicleNo)}
                </div>
              </div>
              <div style="margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Status</div>
                <div style="font-size: 14px; color: #1e293b;">
                  <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: ${status === 'checked_in' ? '#d1fae5' : '#fef3c7'}; color: ${status === 'checked_in' ? '#065f46' : '#92400e'};">
                    ${status === 'checked_in' ? 'CHECKED IN' : 'RESERVED'}
                  </span>
                </div>
              </div>
              <div>
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Reserved At</div>
                <div style="font-size: 14px; color: #1e293b;">${escapeHtml(reservedAt)}</div>
              </div>
            </div>
          </div>
        `;
        
        // Store slot info for release button
        if (adminSlotReleaseBtn) {
          adminSlotReleaseBtn.dataset.slotId = slotDbId;
          adminSlotReleaseBtn.dataset.slotLabel = slotLabel;
        }
        
        // Show modal
        adminSlotModal.classList.add("show");
      }
      return;
    }

    if (!CURRENT_USER || Number(owner) !== Number(CURRENT_USER.id)){
      showToast("This slot is already reserved.", true);
      return;
    }

    if (status === "reserved"){
      openActionModal("reserved");
      return;
    }

    if (status === "checked_in"){
      openActionModal("checked_in");
      return;
    }
  });

  // Admin hover tooltip for reserved slots
  document.addEventListener("mouseover", async (e) => {
    if (!IS_ADMIN || !adminTooltip) return;
    const t = e.target;
    if (!t.id || !t.id.startsWith("slot-")) {
      adminTooltip.style.display = 'none';
      return;
    }

    const owner = Number(t.dataset.owner || 0);
    if (!owner) {
      adminTooltip.style.display = 'none';
      return;
    }

    // Show loading tooltip
    adminTooltip.style.display = 'block';
    adminTooltip.innerHTML = 'Loading...';
    adminTooltip.style.left = (e.pageX + 15) + 'px';
    adminTooltip.style.top = (e.pageY + 15) + 'px';

    try {
      const res = await apiGet(`admin_slot_info?slot_svg_id=${encodeURIComponent(t.id)}`);
      const data = await res.json();
      if (res.ok && data.reserved) {
        adminTooltip.innerHTML = `
          <div style="font-weight: 600; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 6px;">
            Slot Reserved by:
          </div>
          <div style="line-height: 1.6;">
            <div><strong>Name:</strong> ${escapeHtml(data.user_name)}</div>
            <div><strong>Email:</strong> ${escapeHtml(data.user_email)}</div>
            <div><strong>Type:</strong> ${escapeHtml(data.user_type)}</div>
            <div><strong>Reservation:</strong> ${escapeHtml(data.reservation_name)}</div>
            <div><strong>Vehicle:</strong> ${escapeHtml(data.vehicle_no)}</div>
            <div><strong>Status:</strong> ${escapeHtml(data.status)}</div>
            <div style="font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 8px;">
              Click to free this slot
            </div>
          </div>
        `;
        adminTooltip.style.display = 'block';
      } else {
        adminTooltip.style.display = 'none';
      }
    } catch (err) {
      adminTooltip.style.display = 'none';
    }
  });

  document.addEventListener("mousemove", (e) => {
    if (!IS_ADMIN || !adminTooltip) return;
    if (adminTooltip.style.display === 'block') {
      adminTooltip.style.left = (e.pageX + 15) + 'px';
      adminTooltip.style.top = (e.pageY + 15) + 'px';
    }
  });

  document.addEventListener("mouseout", (e) => {
    if (!IS_ADMIN || !adminTooltip) return;
    const t = e.target;
    if (t.id && t.id.startsWith("slot-")) {
      adminTooltip.style.display = 'none';
    }
  });

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function highlightFromHash(){
    const id = location.hash ? location.hash.substring(1) : "";
    const n = el(id);
    if (!n) return;
    n.style.stroke = "#667eea";
    n.style.strokeWidth = 4;
    n.style.filter = "drop-shadow(0 0 8px rgba(102, 126, 234, 0.6))";
    setTimeout(()=>{ 
      n.style.stroke=""; 
      n.style.strokeWidth=""; 
      n.style.filter="";
    }, 3000);
    n.scrollIntoView({behavior:"smooth",block:"center"});
  }

  // Add glow effect to slots on hover (only for reservable slots) - but not for admin on reserved slots
  document.addEventListener("mouseover", (e) => {
    if (e.target.id && e.target.id.startsWith("slot-")) {
      // Only add glow if cursor is pointer (meaning it's clickable/reservable)
      // Don't add glow if admin is hovering over reserved slots (they have their own tooltip)
      if (e.target.style.cursor === "pointer" && !e.target.dataset.owner && !IS_ADMIN) {
        // Only add glow effect, no transform or scale
        e.target.style.filter = "drop-shadow(0 0 15px rgba(125, 211, 107, 0.8))";
      } else if (e.target.style.cursor === "pointer" && !e.target.dataset.owner && IS_ADMIN) {
        // Admin can reserve any slot
        e.target.style.filter = "drop-shadow(0 0 15px rgba(125, 211, 107, 0.8))";
      }
    }
  });

  document.addEventListener("mouseout", (e) => {
    if (e.target.id && e.target.id.startsWith("slot-")) {
      // Don't remove glow if admin tooltip is showing
      if (!IS_ADMIN || !adminTooltip || adminTooltip.style.display === 'none') {
        // Remove glow effect on mouseout
        e.target.style.filter = "";
      }
    }
  });

  (async function init(){
    await loadUser();
    console.log("User loaded:", CURRENT_USER);
    await refreshSlots();
    highlightFromHash();
    setInterval(refreshSlots, 5000);
  })();

})();
</script>
</body>
</html>
