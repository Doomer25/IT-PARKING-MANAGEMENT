<?php
// public/register.php
require_once __DIR__ . '/../src/db.php';
session_start();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'normal'; // normal|faculty|hod

    // Collect vehicles (up to 3)
    $vehicles = [];
    $uploadDir = __DIR__ . '/assets/vehicle_images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    for ($i = 1; $i <= 3; $i++) {
        $vehicle_name = trim($_POST["vehicle_name_$i"] ?? '');
        $vehicle_no = trim($_POST["vehicle_no_$i"] ?? '');
        if ($vehicle_name !== '' && $vehicle_no !== '') {
            $vehicle_image = null;
            
            // Handle image upload for this vehicle
            if (isset($_FILES["vehicle_image_$i"]) && $_FILES["vehicle_image_$i"]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES["vehicle_image_$i"];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'vehicle_' . time() . '_' . $i . '_' . uniqid() . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $vehicle_image = $filename;
                    }
                }
            }
            
            $vehicles[] = ['name' => $vehicle_name, 'number' => $vehicle_no, 'image' => $vehicle_image];
        }
    }

    // Basic validation
    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'All fields required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    } elseif (count($vehicles) === 0) {
        $errors[] = 'Please add at least one vehicle.';
    } elseif (count($vehicles) > 3) {
        $errors[] = 'Maximum 3 vehicles allowed.';
    } else {
        // ✅ use db() instead of undefined getPDO()
        $pdo = db();

        // prevent duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered.';
        } else {
            // Start transaction
            $pdo->beginTransaction();
            try {
                // In production you should NOT let users self-select faculty/hod.
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, user_type, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $email, $hash, $user_type]);
                $user_id = $pdo->lastInsertId();

                // Insert vehicles
                $stmt = $pdo->prepare("
                    INSERT INTO vehicles (user_id, vehicle_name, vehicle_no, vehicle_image, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                foreach ($vehicles as $vehicle) {
                    $stmt->execute([$user_id, $vehicle['name'], $vehicle['number'], $vehicle['image']]);
                }

                $pdo->commit();
                
                // Log registration activity
                log_activity($user_id, 'registered account', [
                    'name' => $name,
                    'email' => $email,
                    'user_type' => $user_type,
                    'vehicles_count' => count($vehicles)
                ]);
                
                $success = 'Registered. You can now <a href="login.php">login</a>.';

                // Optional: clear form values after success
                $name = $email = '';
                $user_type = 'normal';
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register — IT Parking</title>
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
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
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

    .card {
      background: rgb(230, 216, 247);
      padding: 40px;
      border-radius: 16px;
      max-width: 520px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .card-header {
      text-align: center;
      margin-bottom: 32px;
    }

    .card-header h1 {
      font-size: 28px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 8px;
    }

    .card-header p {
      color: #64748b;
      font-size: 14px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #334155;
      margin-bottom: 8px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.2s;
      font-family: inherit;
      background: rgb(230, 216, 247);
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group input::placeholder {
      color: #cbd5e1;
    }

    .success-box {
      background: #d1fae5;
      border: 1px solid #a7f3d0;
      color: #065f46;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      line-height: 1.5;
    }

    .success-box a {
      color: #059669;
      font-weight: 600;
      text-decoration: none;
    }

    .success-box a:hover {
      text-decoration: underline;
    }

    .error-box {
      background: #fee2e2;
      border: 1px solid #fecaca;
      color: #991b1b;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      line-height: 1.5;
    }

    .btn-primary {
      width: 100%;
      padding: 12px 24px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
      margin-top: 8px;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .form-footer {
      text-align: center;
      margin-top: 24px;
      padding-top: 24px;
      border-top: 1px solid #e2e8f0;
    }

    .form-footer a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
      font-size: 14px;
    }

    .form-footer a:hover {
      text-decoration: underline;
    }

    @media (max-width: 640px) {
      .card {
        padding: 24px;
      }
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header">
      <h1>🚗 Create Account</h1>
      <p>Register for IT Parking Management</p>
    </div>

    <?php if ($success): ?>
      <div class="success-box">
        <?php echo $success; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="error-box">
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required 
               placeholder="Enter your full name"
               value="<?php echo htmlspecialchars($name ?? '') ?>"
               autocomplete="name">
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required 
               placeholder="you@example.com"
               value="<?php echo htmlspecialchars($email ?? '') ?>"
               autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required 
               placeholder="Create a secure password"
               autocomplete="new-password">
      </div>

      <div class="form-group">
        <label for="user_type">User Type</label>
        <select id="user_type" name="user_type">
          <option value="normal"  <?php if(($user_type ?? '')==='normal')  echo 'selected'; ?>>Normal User</option>
          <option value="faculty" <?php if(($user_type ?? '')==='faculty') echo 'selected'; ?>>Faculty</option>
          <option value="hod"     <?php if(($user_type ?? '')==='hod')     echo 'selected'; ?>>HOD</option>
        </select>
      </div>

      <div class="form-group" style="margin-top: 32px; padding-top: 24px; border-top: 2px solid #e2e8f0;">
        <label style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Vehicles (Add up to 3)</label>
        <div id="vehicles-container">
          <div class="vehicle-item" style="margin-bottom: 16px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
              <div>
                <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Name</label>
                <input type="text" name="vehicle_name_1" placeholder="e.g., My Car" style="width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
              </div>
              <div>
                <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Number</label>
                <input type="text" name="vehicle_no_1" placeholder="e.g., ABC-1234" style="width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
              </div>
            </div>
            <div>
              <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Image (Optional)</label>
              <div style="display: flex; gap: 8px; align-items: center;">
                <input type="file" name="vehicle_image_1" accept="image/*" id="vehicle_image_1" style="flex: 1; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                <button type="button" onclick="clearImage('vehicle_image_1')" style="padding: 8px 12px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; white-space: nowrap;">Clear</button>
              </div>
              <p style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Max 5MB. Formats: JPEG, PNG, GIF, WebP</p>
            </div>
          </div>
        </div>
        <button type="button" id="add-vehicle-btn" style="margin-top: 12px; padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500;">
          + Add Another Vehicle
        </button>
        <p style="font-size: 12px; color: #64748b; margin-top: 8px;">At least one vehicle is required. Maximum 3 vehicles.</p>
      </div>

      <button type="submit" class="btn-primary">Create Account</button>

      <script>
        let vehicleCount = 1;
        const maxVehicles = 3;
        const container = document.getElementById('vehicles-container');
        const addBtn = document.getElementById('add-vehicle-btn');

        addBtn.addEventListener('click', () => {
          if (vehicleCount >= maxVehicles) {
            alert('Maximum 3 vehicles allowed.');
            return;
          }
          vehicleCount++;
          
          const item = document.createElement('div');
          item.className = 'vehicle-item';
          item.style.cssText = 'margin-bottom: 16px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; position: relative;';
          item.innerHTML = `
            <button type="button" class="remove-vehicle" style="position: absolute; top: 8px; right: 8px; background: #ef4444; color: white; border: none; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; font-size: 16px; line-height: 1;">×</button>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
              <div>
                <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Name</label>
                <input type="text" name="vehicle_name_${vehicleCount}" placeholder="e.g., My Car" style="width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
              </div>
              <div>
                <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Number</label>
                <input type="text" name="vehicle_no_${vehicleCount}" placeholder="e.g., ABC-1234" style="width: 100%; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
              </div>
            </div>
            <div>
              <label style="font-size: 12px; color: #64748b; margin-bottom: 4px; display: block;">Vehicle Image (Optional)</label>
              <div style="display: flex; gap: 8px; align-items: center;">
                <input type="file" name="vehicle_image_${vehicleCount}" accept="image/*" id="vehicle_image_${vehicleCount}" style="flex: 1; padding: 8px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                <button type="button" onclick="clearImage('vehicle_image_${vehicleCount}')" style="padding: 8px 12px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; white-space: nowrap;">Clear</button>
              </div>
              <p style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Max 5MB. Formats: JPEG, PNG, GIF, WebP</p>
            </div>
          `;
          
          container.appendChild(item);
          
          item.querySelector('.remove-vehicle').addEventListener('click', () => {
            item.remove();
            vehicleCount--;
            if (vehicleCount < maxVehicles) {
              addBtn.style.display = 'inline-block';
            }
          });

          if (vehicleCount >= maxVehicles) {
            addBtn.style.display = 'none';
          }
        });

        // Allow removing first vehicle if there are multiple
        document.addEventListener('click', (e) => {
          if (e.target.classList.contains('remove-vehicle') && vehicleCount > 1) {
            e.target.closest('.vehicle-item').remove();
            vehicleCount--;
            if (vehicleCount < maxVehicles) {
              addBtn.style.display = 'inline-block';
            }
          }
        });
        
        // Function to clear image input
        window.clearImage = function(inputId) {
          const input = document.getElementById(inputId);
          if (input) {
            input.value = '';
          }
        };
      </script>
    </form>

    <div class="form-footer">
      <p>Already have an account? <a href="/IT-PARKING-MANAGEMENT/public/login.php">Sign in here</a></p>
    </div>
  </div>
</body>
</html>
