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

    // Basic validation
    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'All fields required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    } else {
        // ✅ use db() instead of undefined getPDO()
        $pdo = db();

        // prevent duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered.';
        } else {
            // In production you should NOT let users self-select faculty/hod.
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, user_type, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $hash, $user_type]);
            $success = 'Registered. You can now <a href="login.php">login</a>.';

            // Optional: clear form values after success
            $name = $email = '';
            $user_type = 'normal';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register — IT Parking</title>
  <style>
    body{font-family:Arial;background:#f5f5f5;padding:30px}
    .card{background:#fff;padding:18px;border-radius:6px;max-width:520px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.08)}
    input, select{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:4px}
    button{padding:10px 14px;border:none;background:#0b79d0;color:#fff;border-radius:4px;cursor:pointer}
    .err{color:#b50000}
  </style>
</head>
<body>
  <div class="card">
    <h2>Register</h2>

    <?php if ($success): ?>
      <div style="color:green"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="err"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Name</label>
      <input type="text" name="name" required value="<?php echo htmlspecialchars($name ?? '') ?>">

      <label>Email</label>
      <input type="text" name="email" required value="<?php echo htmlspecialchars($email ?? '') ?>">

      <label>Password</label>
      <input type="password" name="password" required>

      <label>User type</label>
      <select name="user_type">
        <option value="normal"  <?php if(($user_type ?? '')==='normal')  echo 'selected'; ?>>Normal</option>
        <option value="faculty" <?php if(($user_type ?? '')==='faculty') echo 'selected'; ?>>Faculty</option>
        <option value="hod"     <?php if(($user_type ?? '')==='hod')     echo 'selected'; ?>>HOD</option>
      </select>

      <div style="margin-top:10px;">
        <button type="submit">Create account</button>
        <a href="/IT-PARKING-MANAGEMENT/public/login.php" style="margin-left:12px;">Back to login</a>
      </div>
    </form>
  </div>
</body>
</html>
