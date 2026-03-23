<?php
/**
 * auth/login.php
 * Login page – UI + calls auth.php backend
 */
require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();

// Already logged in? Go to dashboard
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error  = '';
$reason = $_GET['reason'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $result = login_user(
        identifier: trim($_POST['identifier'] ?? ''),
        password:   $_POST['password'] ?? '',
        remember:   isset($_POST['remember'])
    );

    if ($result['success']) {
        $redirect = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);

        if (!$redirect) {
            $redirect = match($result['user']['role']) {
                'admin'     => '/modules/dashboard/admin.php',
                'secretary' => '/modules/dashboard/secretary.php',
                'staff'     => '/modules/dashboard/staff.php',
                'driver'    => '/modules/dashboard/driver.php',
                default     => '/modules/dashboard.php',
            };
        }

        header('Location: ' . $redirect);
        exit;
    }

    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Log In – ITCPRS</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/auth.css"/>
</head>
<body>

<div class="topbar">
  <a href="/index.php" class="brand">
    <div class="logo-ph"><img src="/assets/images/logo.jpg" alt="ITCPRS Logo"/></div>
    <div class="brand-name">ITCPRS <span>Trucking Management</span></div>
  </a>
  <a href="/index.php" class="topbar-link">← Back to Home</a>
</div>

<main>
  <div class="card">
    <div class="card-header">
      <div class="card-logo-ph card-logo-ph--lg"><img src="/assets/images/logo.jpg" alt="ITCPRS Logo"/></div>
      <h1>Welcome Back</h1>
      <p>Sign in to your account to continue</p>
    </div>
    <div class="card-body">

      <?php if ($reason === 'timeout'): ?>
        <div class="alert alert-warn">⚠ Your session expired due to inactivity. Please sign in again.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"/>

        <div class="field">
          <label for="identifier">Username or Email</label>
          <input type="text" id="identifier" name="identifier"
            placeholder="Enter your username or email"
            autocomplete="username"
            value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>"
            required autofocus/>
          <div class="field-hint">You can log in with either your username or email address.</div>
        </div>

        <div class="field">
          <div class="field-row">
            <label for="password">Password</label>
            <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
          </div>
          <div class="pw-wrap">
            <input type="password" id="password" name="password"
              placeholder="Enter your password"
              autocomplete="current-password" required/>
            <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Show/hide password">
              <svg id="eyeIcon" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <label class="remember">
          <input type="checkbox" name="remember"/>
          <span>Remember me for 30 days</span>
        </label>

        <button type="submit" class="btn-submit">Sign In</button>
      </form>

      <hr class="divider"/>
      <p class="register-prompt">Don't have an account? <a href="register.php">Create one here</a></p>

    </div>
  </div>
</main>

<!-- FOOTER -->
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function togglePw() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  icon.innerHTML = isText
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
}
</script>
</body>
</html>