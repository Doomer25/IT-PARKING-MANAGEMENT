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
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-secondary {
      background: rgba(255,255,255,0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }

    .btn-secondary:hover {
      background: rgba(255,255,255,0.3);
    }

    .btn-danger {
      background: #ef4444;
      color: #ffffff;
    }

    .btn-danger:hover {
      background: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    .card {
      background: #ffffff;
      padding: 32px;
      border-radius: 16px;
      max-width: 1000px;
      margin: 0 auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
    }

    .status-card {
      background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
      padding: 24px;
      border-radius: 12px;
      border: 2px solid #e2e8f0;
      margin-top: 24px;
    }

    .status-card h3 {
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
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
    }

    .info-value {
      font-size: 16px;
      font-weight: 600;
      color: #1e293b;
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
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
      <a class="btn btn-secondary" href="/IT-PARKING-MANAGEMENT/public/map.php">🗺️ Open Map</a>
      <button id="deleteAccountHeader" class="btn btn-danger" style="background: #ef4444; color: #ffffff; border: none; cursor: pointer;">🗑️ Delete Account</button>
      <a class="btn btn-primary" href="/IT-PARKING-MANAGEMENT/public/logout.php">Logout</a>
    </div>
  </div>

    <div class="card">
      <div class="card-header">
        <h2>Dashboard</h2>
        <button id="manageVehiclesBtn" class="btn-action" style="padding: 10px 20px; font-size: 14px;">🚗 Manage Vehicles</button>
      </div>

      <!-- Vehicle Management Section -->
      <div id="vehicles-section" style="display: none; margin-bottom: 32px; padding: 24px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 style="font-size: 18px; font-weight: 600; color: #1e293b;">My Vehicles</h3>
          <button id="closeVehiclesBtn" style="background: #e2e8f0; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 14px;">Close</button>
        </div>
        <div id="vehicles-list" style="margin-bottom: 20px;">
          <!-- Vehicles will be loaded here -->
        </div>
        <div id="add-vehicle-form" style="padding: 16px; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
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
            <button id="add-vehicle-submit" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">Add</button>
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
        <div style="margin-top: 24px; display: flex; gap: 12px;">
          <button id="cancel" class="btn-ghost">Release Slot</button>
          <button id="deleteAccount" class="btn-danger" style="background: #ef4444; color: #ffffff; padding: 12px 24px; border-radius: 8px; border: none; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
            Delete My Account
          </button>
        </div>
      <?php else: ?>
        <h3>🚗 No Active Reservation</h3>
        <p>You currently do not have a reservation.</p>
        <div style="margin-top: 24px;">
          <a class="btn-action" href="/IT-PARKING-MANAGEMENT/public/map.php" style="text-decoration: none; display: inline-block;">Reserve a Slot</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

<script>
document.getElementById("cancel")?.addEventListener("click", async () => {
  if (!confirm("Are you sure you want to release your reserved slot?")) return;
  
  const btn = document.getElementById("cancel");
  const originalText = btn.textContent;
  btn.textContent = "Releasing...";
  btn.disabled = true;
  
  try {
    const r = await fetch("/IT-PARKING-MANAGEMENT/public/api.php?action=cancel", {method:"POST"});
    if (r.ok) {
      location.reload();
    } else {
      alert("Failed to release slot. Please try again.");
      btn.textContent = originalText;
      btn.disabled = false;
    }
  } catch (err) {
    alert("Network error. Please try again.");
    btn.textContent = originalText;
    btn.disabled = false;
  }
});

const deleteAccountHandler = async (buttonElement) => {
  if (!confirm("⚠️ WARNING: Are you sure you want to delete your account?\n\nThis will:\n- Delete your account permanently\n- Cancel all your reservations\n- This action cannot be undone!\n\nType 'DELETE' to confirm.")) {
    return;
  }
  
  const confirmation = prompt("Type 'DELETE' to confirm account deletion:");
  if (confirmation !== 'DELETE') {
    alert("Account deletion cancelled.");
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
      alert("Account deleted successfully. Redirecting to login...");
      window.location.href = data.redirect || "/IT-PARKING-MANAGEMENT/public/login.php";
    } else {
      alert("Failed to delete account: " + (data.error || "Unknown error"));
      buttonElement.textContent = originalText;
      buttonElement.disabled = false;
    }
  } catch (err) {
    alert("Network error. Please try again.");
    buttonElement.textContent = originalText;
    buttonElement.disabled = false;
  }
};

document.getElementById("deleteAccount")?.addEventListener("click", () => deleteAccountHandler(document.getElementById("deleteAccount")));
document.getElementById("deleteAccountHeader")?.addEventListener("click", () => deleteAccountHandler(document.getElementById("deleteAccountHeader")));

// Vehicle Management
const API = "/IT-PARKING-MANAGEMENT/public/api.php";
const vehiclesSection = document.getElementById("vehicles-section");
const manageVehiclesBtn = document.getElementById("manageVehiclesBtn");
const closeVehiclesBtn = document.getElementById("closeVehiclesBtn");
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
    alert("Please enter vehicle name and number.");
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
      alert("Vehicle added successfully!");
    } else {
      alert("Error: " + (data.error || "Failed to add vehicle"));
    }
  } catch (err) {
    alert("Network error. Please try again.");
  } finally {
    addVehicleSubmit.disabled = false;
    addVehicleSubmit.textContent = "Add";
  }
}

async function deleteVehicle(vehicleId, vehicleName) {
  if (!confirm(`Are you sure you want to delete vehicle "${vehicleName}"?`)) {
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
      alert("Vehicle deleted successfully!");
    } else {
      alert("Error: " + (data.error || "Failed to delete vehicle"));
    }
  } catch (err) {
    alert("Network error. Please try again.");
  }
}

function escapeHtml(text, doubleEncode = false) {
  const div = document.createElement('div');
  div.textContent = text;
  const result = div.innerHTML;
  return doubleEncode ? result.replace(/'/g, "&#39;") : result;
}

manageVehiclesBtn?.addEventListener("click", () => {
  vehiclesSection.style.display = vehiclesSection.style.display === 'none' ? 'block' : 'none';
  if (vehiclesSection.style.display === 'block') {
    loadVehicles();
  }
});

closeVehiclesBtn?.addEventListener("click", () => {
  vehiclesSection.style.display = 'none';
});

addVehicleSubmit?.addEventListener("click", addVehicle);

// Allow Enter key to submit add vehicle form
newVehicleNo?.addEventListener("keypress", (e) => {
  if (e.key === 'Enter') {
    addVehicle();
  }
});
</script>

</body>
</html>
