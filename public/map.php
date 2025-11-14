<?php
require_once __DIR__ . '/../src/auth.php';
requireLogin();
require_once __DIR__ . '/../src/db.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>IT Department — Parking Map</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:system-ui,Arial;background:#f8fafc;padding:12px}
    .map-wrap{max-width:1000px;margin:12px auto;background:#fff;padding:12px;border-radius:8px;box-shadow:0 6px 20px rgba(2,6,23,.06)}
    svg{width:100%;height:auto;display:block}
  </style>
</head>
<body>
  <div class="map-wrap">
    <h2>IT Department — Parking Map</h2>

    <?php
    $svg_path = __DIR__ . '/assets/map.svg';
    if (file_exists($svg_path)) {
        echo file_get_contents($svg_path);
    } else {
        echo '<p style="color:red">map.svg not found in public/assets</p>';
    }
    ?>

    <p style="margin-top:8px">
      <a href="/IT-PARKING-MANAGEMENT/public/index.php">Back to dashboard</a>
    </p>
  </div>

<script>
(function(){
  const API = "/IT-PARKING-MANAGEMENT/public/api.php";
  let CURRENT_USER = null;

  async function apiGet(action){
    return fetch(`${API}?action=${action}`, { credentials:"include" });
  }

  async function apiPost(action, body={}){
    return fetch(`${API}?action=${action}`, {
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
  }

  async function refreshSlots(){
    const res = await apiGet("slots");
    if (!res.ok) return;
    const slots = await res.json();

    slots.forEach(s => {
      const node = el(s.svg_id);
      if (!node) return;

      node.dataset.owner = "";
      node.dataset.status = "";

      const status = (s.status || "").toLowerCase();
      const hasReservation = !!s.user_id;

      let title = s.label || s.slot_number || s.svg_id;

      if (!hasReservation) {
        node.style.fill = "#7dd36b"; // GREEN
        node.style.cursor = "pointer";
        node.title = `${title} — Free`;
        return;
      }

      node.dataset.owner = s.user_id;
      node.dataset.status = status;

      // 🔥 Styling for reserved slots (visible to ALL users)
      if (status === "checked_in") {
        node.style.fill = "#ff0000"; // RED
      } else {
        node.style.fill = "#facc15"; // YELLOW
      }

      const mine = CURRENT_USER && Number(s.user_id) === Number(CURRENT_USER.id);

      if (mine) {
        node.style.cursor = "pointer";
        title += " — YOUR reservation";
      } else {
        node.style.cursor = "not-allowed";
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

  async function reserveSlot(svgId){
    const name = prompt("Enter your name:");
    if (!name) return alert("Name required.");

    const vehicle = prompt("Enter vehicle number:");
    if (!vehicle) return alert("Vehicle number required.");

    const res = await apiPost("reserve", { slot_svg_id: svgId, name, vehicle_no: vehicle });
    const body = await res.json();

    if (!res.ok) return alert(body.error || "Reserve failed");
    alert("Slot reserved!");
    await refreshSlots();
  }

  async function checkIn(){
    const res = await apiPost("checkin");
    const body = await res.json();
    if (!res.ok) return alert(body.error || "Failed");
    alert("Checked in!");
    await refreshSlots();
  }

  async function cancelReservation(){
    const res = await apiPost("cancel");
    const body = await res.json();
    if (!res.ok) return alert(body.error || "Failed");
    alert("Reservation released");
    await refreshSlots();
  }

  document.addEventListener("click", async (e) => {
    const t = e.target;
    if (!t.id || !t.id.startsWith("slot-")) return;

    const owner = Number(t.dataset.owner || 0);
    const status = (t.dataset.status || "").toLowerCase();

    if (!owner){
      return reserveSlot(t.id);
    }

    if (Number(owner) !== Number(CURRENT_USER.id)){
      alert("This slot is already reserved.");
      return;
    }

    if (status === "reserved"){
      if (confirm("Reserved.\nOK = Check in\nCancel = Release")) {
        return checkIn();
      } else {
        return cancelReservation();
      }
    }

    if (status === "checked_in"){
      if (confirm("Release slot?")) return cancelReservation();
    }
  });

  function highlightFromHash(){
    const id = location.hash ? location.hash.substring(1) : "";
    const n = el(id);
    if (!n) return;
    n.style.stroke = "#000";
    n.style.strokeWidth = 3;
    setTimeout(()=>{ n.style.stroke=""; n.style.strokeWidth=""; }, 2500);
    n.scrollIntoView({behavior:"smooth",block:"center"});
  }

  (async function init(){
    await loadUser();
    await refreshSlots();
    highlightFromHash();
    setInterval(refreshSlots, 5000);
  })();
})();
</script>
</body>
</html>
