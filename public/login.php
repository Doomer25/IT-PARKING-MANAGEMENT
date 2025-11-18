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
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
      padding-top: 80px;
      position: relative;
      overflow-x: hidden;
      overflow-y: auto;
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
      animation: backgroundZoom 20s infinite alternate ease-in-out;
    }

    @keyframes backgroundZoom {
      from {
        transform: scale(1.1);
      }
      to {
        transform: scale(1.15);
      }
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

    .header-text {
      text-align: center;
      margin-bottom: 40px;
      margin-top: -120px;
      padding-top: 60px;
      padding-bottom: 20px;
      animation: fadeInDown 1s ease-out;
      overflow: visible;
    }

    .header-text h1 {
      font-size: 120px;
      font-weight: 700;
      margin-bottom: 20px;
      margin-top: 0;
      padding-bottom: 10px;
      letter-spacing: 4px;
      background: linear-gradient(90deg, #6C63FF, #00C2FF);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: fadeInDown 1s ease-out;
      text-shadow: 0 4px 8px rgba(0,0,0,0.3);
      line-height: 1.2;
      overflow: visible;
    }

    .header-text h2 {
      font-size: 72px;
      font-weight: 600;
      margin-top: 0;
      margin-bottom: 0;
      padding-bottom: 10px;
      letter-spacing: 3px;
      color: #B8E6FF;
      animation: fadeInUp 1.2s ease-out 0.3s both;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
      line-height: 1.2;
      overflow: visible;
    }


    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      .header-text {
        margin-top: -80px;
      }
      .header-text h1 {
        font-size: 64px;
        letter-spacing: 2px;
      }
      .header-text h2 {
        font-size: 40px;
        letter-spacing: 1.5px;
      }
    }

    @media (max-width: 480px) {
      .header-text h1 {
        font-size: 48px;
        letter-spacing: 1px;
      }
      .header-text h2 {
        font-size: 32px;
        letter-spacing: 1px;
      }
    }

    .card {
      background: rgb(230, 216, 247);
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
      background: rgb(230, 216, 247);
    }

    .form-group select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.2s;
      font-family: inherit;
      background: #ffffff;
    }

    .form-group input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group select:focus {
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
      background: linear-gradient(135deg, #6C63FF 0%, #5a52e6 100%);
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

    .btn-primary:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    /* Loading Indicator with Car Animation */
    .loading-car-container {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 20px;
    }

    .loading-car-container.active {
      display: flex;
    }

    .car-animation {
      font-size: 48px;
      animation: carMove 1.5s ease-in-out infinite;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
    }

    .smoke-animation {
      font-size: 32px;
      opacity: 0.7;
      animation: smokePuff 1.5s ease-in-out infinite;
      margin-left: -20px;
    }

    @keyframes carMove {
      0%, 100% {
        transform: translateX(0) rotate(0deg);
      }
      25% {
        transform: translateX(10px) rotate(2deg);
      }
      50% {
        transform: translateX(20px) rotate(0deg);
      }
      75% {
        transform: translateX(10px) rotate(-2deg);
      }
    }

    @keyframes smokePuff {
      0%, 100% {
        opacity: 0.5;
        transform: translateX(0) scale(1);
      }
      50% {
        opacity: 0.8;
        transform: translateX(5px) scale(1.2);
      }
    }

    .loading-text {
      color: #ffffff;
      font-size: 16px;
      font-weight: 500;
      margin-top: 10px;
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
  <div class="header-text">
    <h1>Information Technology</h1>
    <h2>Parking Management System</h2>
  </div>
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
        <select id="login_type" name="login_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; margin-bottom: 20px; background: #ffffff;">
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

      <button type="submit" class="btn-primary" id="login-btn">Sign In</button>
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

      // Show loading animation on form submit
      const loginForm = document.getElementById('loginForm');
      const loginBtn = document.getElementById('login-btn');
      const loadingIndicator = document.getElementById('loading-indicator');

      if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
          // Show loading indicator
          loadingIndicator.classList.add('active');
          loginBtn.disabled = true;
          loginBtn.textContent = 'Signing in...';
        });
      }
    </script>

    <div class="form-footer">
      <p>Don't have an account? <a href="/IT-PARKING-MANAGEMENT/public/register.php">Register here</a></p>
    </div>
  </div>

  <!-- Loading Indicator with Car Animation -->
  <div class="loading-car-container" id="loading-indicator">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div class="car-animation">🚗</div>
      <div class="smoke-animation">💨</div>
    </div>
    <div class="loading-text">Signing you in...</div>
  </div>
</body>
</html>
