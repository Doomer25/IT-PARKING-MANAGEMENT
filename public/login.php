<?php
// public/login.php
require_once __DIR__ . '/../src/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'user'; // 'user' or 'admin'

    if ($email === '' || $password === '') {
        $errors[] = 'Enter both ' . ($login_type === 'admin' ? 'username' : 'email') . ' and password.';
    } else {
        if ($login_type === 'admin') {
            // Admin login
            if (attempt_admin_login($email, $password)) {
                header('Location: /IT-PARKING-MANAGEMENT/public/admin/dashboard.php');
                exit;
            } else {
                $errors[] = 'Invalid admin credentials.';
            }
        } else {
            // Regular user login
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
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login — IT Parking</title>
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
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .card {
      background: #ffffff;
      padding: 40px;
      border-radius: 16px;
      max-width: 440px;
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
      <h1>🚗 IT Parking</h1>
      <p>Sign in to your account</p>
    </div>

    <?php if(!empty($errors)): ?>
      <div class="error-box">
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
      </div>
    <?php endif; ?>

    <form method="post" id="loginForm">
      <div class="form-group">
        <label for="login_type">Login As</label>
        <select id="login_type" name="login_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; margin-bottom: 20px;">
          <option value="user" selected>User</option>
          <option value="admin">Admin</option>
        </select>
      </div>

      <div class="form-group">
        <label for="email" id="emailLabel">Email Address</label>
        <input type="text" id="email" name="email" required 
               placeholder="you@example.com"
               value="<?php echo isset($email) ? htmlspecialchars($email) : '' ?>"
               autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required 
               placeholder="Enter your password"
               autocomplete="current-password">
      </div>

      <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <script>
      document.getElementById('login_type').addEventListener('change', function() {
        const emailInput = document.getElementById('email');
        const emailLabel = document.getElementById('emailLabel');
        if (this.value === 'admin') {
          emailLabel.textContent = 'Admin Username';
          emailInput.type = 'text';
          emailInput.placeholder = 'Enter admin username';
          emailInput.autocomplete = 'username';
        } else {
          emailLabel.textContent = 'Email Address';
          emailInput.type = 'email';
          emailInput.placeholder = 'you@example.com';
          emailInput.autocomplete = 'email';
        }
      });
    </script>

    <div class="form-footer">
      <p>Don't have an account? <a href="/IT-PARKING-MANAGEMENT/public/register.php">Register here</a></p>
    </div>
  </div>
</body>
</html>
