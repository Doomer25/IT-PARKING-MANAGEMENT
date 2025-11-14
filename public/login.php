<?php
// public/login.php
require_once __DIR__ . '/../src/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Enter both email and password.';
    } else {
        $user = attempt_login($email, $password);
        if ($user) {

            // Redirect to dashboard instead of map after login
            header('Location: /IT-PARKING-MANAGEMENT/public/index.php');
            exit;

        } else {
            $errors[] = 'Invalid credentials.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login — IT Parking</title>
  <style>
    body{font-family:Arial; background:#f5f5f5; padding:30px;}
    .card{background:#fff;padding:18px;border-radius:6px;max-width:420px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.08);}
    input[type=text], input[type=password], select{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:4px}
    button{padding:10px 14px;border:none;background:#0b79d0;color:#fff;border-radius:4px;cursor:pointer}
    .err{color:#b50000}
  </style>
</head>
<body>
  <div class="card">
    <h2>Sign in</h2>

    <?php if(!empty($errors)): ?>
      <div class="err"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Email</label>
      <input type="text" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : '' ?>">

      <label>Password</label>
      <input type="password" name="password" required>

      <div style="margin-top:10px;">
        <button type="submit">Login</button>
        <a href="/IT-PARKING-MANAGEMENT/public/register.php" style="margin-left:12px;">Register</a>
      </div>
    </form>
  </div>
</body>
</html>
