<?php
require_once __DIR__ . '/../../src/auth.php';
requireLogin();
requireRole('admin'); // only admin
require_once __DIR__ . '/../../src/db.php';

$pdo = getPDO();
$msg = '';
// handle SVG upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['map_svg'])) {
    $f = $_FILES['map_svg'];
    if ($f['error'] === 0 && pathinfo($f['name'], PATHINFO_EXTENSION) === 'svg') {
        $destDir = __DIR__ . '/../assets';
        if (!is_dir($destDir)) mkdir($destDir, 0777, true);
        $dest = $destDir . '/map.svg';
        move_uploaded_file($f['tmp_name'], $dest);
        $msg = 'SVG uploaded.';
    } else $msg = 'Upload failed or not an SVG.';
}

// load current SVG contents
$svgPath = __DIR__ . '/../assets/map.svg';
$svgContent = file_exists($svgPath) ? file_get_contents($svgPath) : '';

// get list of slots to map
$slots = $pdo->query("SELECT id, slot_number, svg_id FROM parking_slots ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Map Editor</title>
  <style>body{font-family:system-ui; padding:12px} textarea{width:100%;height:220px}</style>
</head><body>
  <h2>Map Editor (Admin)</h2>
  <p><a href="/index.php">Back to Menu</a></p>
  <?php if($msg): ?><div style="color:green"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <h3>1) Upload new SVG</h3>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="map_svg" accept=".svg">
    <button>Upload</button>
  </form>

  <h3>2) SVG preview / edit</h3>
  <p>If you want to tweak raw SVG, edit below and click Save.</p>
  <form method="post" action="save_svg.php">
    <textarea name="svg_text"><?php echo htmlspecialchars($svgContent); ?></textarea>
    <div style="margin-top:8px"><button type="submit">Save SVG</button></div>
  </form>

  <h3>3) Map shapes to DB slots</h3>
  <p>Click a shape in the preview to copy its id into the "SVG id" input for a chosen slot.</p>

  <div style="display:flex;gap:12px;">
    <div style="flex:1">
      <h4>Preview</h4>
      <div id="preview" style="border:1px solid #ddd;padding:6px;background:#fff"><?php echo $svgContent; ?></div>
    </div>
    <div style="width:320px">
      <h4>Slots</h4>
      <form id="mapForm">
        <label>Selected shape id: <input id="selectedShape" name="selectedShape" readonly style="width:100%"></label>
        <label>Slot: <select id="slotSelect" name="slot_id" style="width:100%;margin-top:8px">
          <?php foreach($slots as $s): ?>
            <option value="<?php echo $s['id']; ?>"><?php echo $s['slot_number']; ?> (current: <?php echo $s['svg_id'] ?: '-'; ?>)</option>
          <?php endforeach; ?>
        </select></label>
        <div style="margin-top:8px">
          <button type="button" id="bindBtn">Bind shape → slot</button>
        </div>
      </form>
      <div id="bindMsg" style="margin-top:8px;color:green"></div>
    </div>
  </div>

<script>
document.getElementById('preview').addEventListener('click', function(e){
  var t = e.target;
  if (t.id) {
    document.getElementById('selectedShape').value = t.id;
  }
});
document.getElementById('bindBtn').addEventListener('click', async function(){
  var shape = document.getElementById('selectedShape').value;
  var slotId = document.getElementById('slotSelect').value;
  if (!shape) { alert('Select a shape in preview by clicking it'); return; }
  const res = await fetch('/admin/bind_shape.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ svg_id: shape, slot_id: slotId })
  });
  const d = await res.json();
  document.getElementById('bindMsg').innerText = d.success ? 'Bound.' : ('Error: '+d.message);
  setTimeout(()=> location.reload(), 700);
});
</script>
</body></html>
