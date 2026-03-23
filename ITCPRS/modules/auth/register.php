<?php
/**
 * auth/register.php
 * Registration page – UI + calls auth.php backend
 */
require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();

// Already logged in? Go to dashboard
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old = [
        'full_name'      => trim($_POST['full_name']      ?? ''),
        'username'       => trim($_POST['username']       ?? ''),
        'email'          => trim($_POST['email']          ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'role'           => $_POST['role']                ?? '',
    ];

    $result = register_user([
        ...$old,
        'password'         => $_POST['password']         ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
    ]);

    if ($result['success']) {
        $success = true;
        $old = [];
    } else {
        $errors = $result['errors'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register – ITCPRS</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/auth.css"/>
</head>
<body>

<div class="topbar">
  <a href="/index.php" class="brand">
    <div class="logo-ph">LOGO</div>
    <div class="brand-name">ITCPRS <span>Trucking Management</span></div>
  </a>
  <a href="login.php" class="topbar-link">Already registered? Sign in →</a>
</div>

<main class="main--top">
  <div class="card card--wide">
    <div class="card-header">
      <div class="card-logo-ph">LOGO<br>HERE</div>
      <h1>Create Account</h1>
      <p>Register to access the system</p>
    </div>
    <div class="card-body">

      <?php if ($success): ?>
        <div class="msg-box msg-success">
          ✓ Account created successfully! <a href="login.php" style="color:var(--success);font-weight:700;">Sign in here</a>.
        </div>
      <?php elseif (!empty($errors)): ?>
        <div class="msg-box msg-error">
          <?php if (count($errors) === 1): ?>
            <?php echo htmlspecialchars($errors[0]); ?>
          <?php else: ?>
            <strong>Please fix the following:</strong>
            <ul>
              <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"/>

        <!-- Full Name -->
        <div class="field">
          <label for="full_name">Full Name <span class="req">*</span></label>
          <input type="text" id="full_name" name="full_name"
            placeholder="e.g. Juan dela Cruz"
            autocomplete="name"
            value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>"
            required/>
        </div>

        <!-- Username + Email -->
        <div class="row-2">
          <div class="field">
            <label for="username">Username <span class="req">*</span></label>
            <input type="text" id="username" name="username"
              placeholder="e.g. jdelacruz"
              autocomplete="username"
              value="<?php echo htmlspecialchars($old['username'] ?? ''); ?>"
              required/>
          </div>
          <div class="field">
            <label for="email">Email Address <span class="req">*</span></label>
            <input type="email" id="email" name="email"
              placeholder="you@example.com"
              autocomplete="email"
              value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>"
              required/>
          </div>
        </div>

        <!-- Contact Number -->
        <div class="field">
          <label for="contact_number">Contact Number <span class="req">*</span></label>
          <input type="tel" id="contact_number" name="contact_number"
            placeholder="e.g. 09171234567"
            autocomplete="tel"
            value="<?php echo htmlspecialchars($old['contact_number'] ?? ''); ?>"
            required/>
        </div>

        <!-- Role -->
        <div class="field">
          <div class="role-lbl">Role <span class="req">*</span></div>
          <div class="role-cards">

            <div class="role-opt">
              <input type="radio" id="r_admin" name="role" value="admin"
                <?php echo (($old['role'] ?? '') === 'admin') ? 'checked' : ''; ?>/>
              <label class="role-tile" for="r_admin">
                <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Admin</span>
              </label>
            </div>

            <div class="role-opt">
              <input type="radio" id="r_secretary" name="role" value="secretary"
                <?php echo (($old['role'] ?? '') === 'secretary') ? 'checked' : ''; ?>/>
              <label class="role-tile" for="r_secretary">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>Secretary</span>
              </label>
            </div>

            <div class="role-opt">
              <input type="radio" id="r_staff" name="role" value="staff"
                <?php echo (($old['role'] ?? '') === 'staff') ? 'checked' : ''; ?>/>
              <label class="role-tile" for="r_staff">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Staff</span>
              </label>
            </div>

            <div class="role-opt">
              <input type="radio" id="r_driver" name="role" value="driver"
                <?php echo (($old['role'] ?? '') === 'driver') ? 'checked' : ''; ?>/>
              <label class="role-tile" for="r_driver">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M3 21v-2a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v2"/></svg>
                <span>Driver</span>
              </label>
            </div>

          </div>
        </div>

        <!-- Password -->
        <div class="field">
          <label for="password">Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password"
              placeholder="Min. 8 chars, 1 uppercase, 1 number"
              autocomplete="new-password"
              oninput="checkStrength(this.value)"
              required/>
            <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Toggle password visibility">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
          <div class="pw-hint" id="pwHint">Use uppercase letters, numbers, and symbols.</div>
        </div>

        <!-- Confirm Password -->
        <div class="field">
          <label for="confirm_password">Confirm Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" name="confirm_password"
              placeholder="Re-enter your password"
              autocomplete="new-password"
              required/>
            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)" aria-label="Toggle password visibility">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">Create Account</button>
      </form>

      <hr class="divider"/>
      <p class="login-prompt">Already have an account? <a href="login.php">Sign in here</a></p>

    </div>
  </div>
</main>

<!-- FOOTER -->
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function checkStrength(val) {
  const bar  = document.getElementById('pwBar');
  const hint = document.getElementById('pwHint');
  let s = 0;
  if (val.length >= 8)          s++;
  if (/[A-Z]/.test(val))        s++;
  if (/[0-9]/.test(val))        s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const l = [
    { pct: '0%',   bg: '#e0e0e0', txt: 'Use uppercase letters, numbers, and symbols.' },
    { pct: '25%',  bg: '#e74c3c', txt: 'Weak – add numbers or symbols.' },
    { pct: '50%',  bg: '#e67e22', txt: 'Fair – add uppercase or symbols.' },
    { pct: '75%',  bg: '#f1c40f', txt: 'Good – almost there!' },
    { pct: '100%', bg: '#27ae60', txt: 'Strong password!' },
  ];
  bar.style.width      = l[s].pct;
  bar.style.background = l[s].bg;
  hint.textContent     = l[s].txt;
  hint.style.color     = s === 0 ? '#6b7a99' : l[s].bg;
}

function togglePw(id, btn) {
  const inp    = document.getElementById(id);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.querySelector('svg').innerHTML = isText
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
}
</script>
</body>
</html>