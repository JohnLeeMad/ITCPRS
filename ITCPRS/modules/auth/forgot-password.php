<?php
session_start();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if (empty($email)) {
    $error = 'Please enter your email address.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } else {
    // TODO: Check email in DB and send reset link
    $_SESSION['reset_email'] = $email;
    $success = true;
  }
}
$sentEmail = $_SESSION['reset_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password – ITCPRS</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy: #0f2356; --navy-deep: #091840;
      --gold: #c8922a; --gold-light: #e8b84b;
      --white: #fff; --off-white: #f4f6fb;
      --text-muted: #6b7a99; --border: #dce3f0;
      --error: #c0392b; --success: #1e8449;
    }
    body { font-family: 'Barlow', sans-serif; background: linear-gradient(135deg, var(--navy-deep) 0%, #162d6b 100%); min-height: 100vh; display: flex; flex-direction: column; }
    body::before { content: ''; position: fixed; inset: 0; pointer-events: none; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }

    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 14px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); position: relative; z-index: 2; flex-wrap: wrap; gap: 10px; }
    .brand  { display: flex; align-items: center; gap: 12px; text-decoration: none; }
    .logo-ph { width: 38px; height: 38px; border: 2px dashed rgba(200,146,42,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 9px; color: rgba(200,146,42,0.7); text-align: center; flex-shrink: 0; }
    .brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 17px; font-weight: 700; color: var(--white); line-height: 1.2; }
    .brand-name span { color: var(--gold-light); font-weight: 400; font-size: 11px; display: block; letter-spacing: 1.5px; }
    .topbar-link { color: rgba(255,255,255,0.5); font-size: 13px; text-decoration: none; transition: color 0.2s; white-space: nowrap; }
    .topbar-link:hover { color: var(--gold-light); }

    main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 32px 20px; position: relative; z-index: 2; }

    .card { background: var(--white); border-radius: 16px; box-shadow: 0 32px 80px rgba(0,0,0,0.4); width: 100%; max-width: 440px; overflow: hidden; animation: slideUp 0.45s ease both; }
    @keyframes slideUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

    .card-header { background: linear-gradient(135deg, var(--navy), #203580); padding: 32px 36px 28px; text-align: center; border-bottom: 3px solid var(--gold); }
    .card-icon { width: 64px; height: 64px; background: rgba(200,146,42,0.12); border: 1.5px solid rgba(200,146,42,0.35); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
    .card-icon svg { width: 30px; height: 30px; stroke: var(--gold-light); fill: none; stroke-width: 1.6; }
    .card-header h1 { font-family: 'Bebas Neue', sans-serif; font-size: 30px; color: var(--white); letter-spacing: 1px; }
    .card-header p  { font-size: 13px; color: rgba(255,255,255,0.5); margin-top: 4px; }

    .card-body { padding: 32px 36px 36px; }

    /* Step bar */
    .steps { display: flex; align-items: flex-start; justify-content: center; margin-bottom: 28px; }
    .step-item { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .step-circle { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
    .step-circle.active   { background: var(--navy); color: var(--white); }
    .step-circle.done     { background: var(--success); color: var(--white); }
    .step-circle.inactive { background: var(--border); color: var(--text-muted); }
    .step-lbl { font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; white-space: nowrap; }
    .step-lbl.active   { color: var(--navy); }
    .step-lbl.inactive { color: var(--text-muted); }
    .step-line { width: 44px; height: 2px; background: var(--border); margin: 14px 6px 0; flex-shrink: 0; }
    .step-line.done { background: var(--success); }

    .desc-text { font-size: 14px; color: var(--text-muted); line-height: 1.65; margin-bottom: 22px; }
    .desc-text strong { color: var(--navy); }

    .field { margin-bottom: 18px; }
    label { display: block; font-size: 13px; font-weight: 600; color: var(--navy); margin-bottom: 7px; }

    input[type="email"] {
      width: 100%; padding: 13px 16px; border: 1.5px solid var(--border); border-radius: 8px;
      font-family: 'Barlow', sans-serif; font-size: 15px; color: var(--navy);
      background: var(--off-white); transition: border-color 0.2s, box-shadow 0.2s; outline: none;
      -webkit-appearance: none; appearance: none;
    }
    input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(15,35,86,0.08); background: var(--white); }

    .msg-error { background: #fdf0ef; border: 1px solid #f5c6c2; border-left: 4px solid var(--error); color: var(--error); padding: 12px 16px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; }

    .btn-submit { width: 100%; background: var(--navy); color: var(--white); font-family: 'Barlow', sans-serif; font-size: 16px; font-weight: 700; padding: 15px; border: none; border-radius: 8px; cursor: pointer; margin-top: 8px; letter-spacing: 0.5px; transition: all 0.2s; appearance: none; -webkit-appearance: none; }
    .btn-submit:hover { background: var(--gold); color: var(--navy-deep); }
    .btn-submit:active { transform: scale(0.99); }

    /* Success state */
    .success-body { padding: 44px 36px; text-align: center; }
    .success-icon { width: 76px; height: 76px; background: rgba(30,132,73,0.1); border: 2px solid rgba(30,132,73,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 22px; }
    .success-icon svg { width: 34px; height: 34px; stroke: var(--success); fill: none; stroke-width: 2; }
    .success-body h2 { font-family: 'Bebas Neue', sans-serif; font-size: 26px; color: var(--navy); margin-bottom: 12px; }
    .success-body p { font-size: 14px; color: var(--text-muted); line-height: 1.7; }
    .email-badge { display: inline-block; background: var(--off-white); border: 1px solid var(--border); padding: 8px 18px; border-radius: 20px; font-weight: 700; color: var(--navy); margin: 12px 0 18px; font-size: 14px; word-break: break-all; }
    .success-note { font-size: 13px; color: var(--text-muted); margin-top: 8px; }
    .success-note a { color: var(--gold); font-weight: 600; text-decoration: none; }

    .divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
    .back-row { text-align: center; }
    .back-row a { color: var(--navy); font-size: 14px; font-weight: 700; text-decoration: none; }
    .back-row a:hover { color: var(--gold); text-decoration: underline; }

    footer { text-align: center; padding: 18px 20px; font-size: 12px; color: rgba(255,255,255,0.2); position: relative; z-index: 2; line-height: 1.6; }
    footer strong { color: rgba(200,146,42,0.45); }

    /* ═══ MOBILE ≤ 480px ═══ */
    @media (max-width: 480px) {
      .topbar { padding: 12px 16px; }
      .brand-name span { display: none; }
      main { padding: 24px 16px; }
      .card { border-radius: 12px; }
      .card-header { padding: 26px 22px 22px; }
      .card-icon { width: 56px; height: 56px; }
      .card-body { padding: 24px 22px 28px; }
      input[type="email"] { font-size: 16px; padding: 14px 16px; }
      .btn-submit { font-size: 17px; padding: 16px; }
      .success-body { padding: 36px 22px; }
      .step-line { width: 32px; }
    }

    @media (max-width: 360px) {
      .card-header h1 { font-size: 26px; }
      .step-lbl { font-size: 10px; }
      .step-line { width: 24px; }
    }
  </style>
</head>
<body>

<div class="topbar">
  <a href="index.php" class="brand">
    <div class="logo-ph">LOGO</div>
    <div class="brand-name">ITCPRS <span>Trucking Management</span></div>
  </a>
  <a href="login.php" class="topbar-link">← Back to Login</a>
</div>

<main>
  <div class="card">
    <div class="card-header">
      <div class="card-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <h1>Forgot Password</h1>
      <p>We'll help you reset your password</p>
    </div>

    <?php if ($success && !empty($sentEmail)): ?>
    <!-- SUCCESS -->
    <div class="success-body">
      <div class="success-icon">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2>Check Your Email</h2>
      <p>We sent a password reset link to:</p>
      <div class="email-badge"><?php echo htmlspecialchars($sentEmail); ?></div>
      <p>Click the link in the email to reset your password.<br>The link expires in <strong>30 minutes</strong>.</p>
      <p class="success-note">Didn't receive it? Check your spam folder or<br>
        <a href="forgot-password.php">try a different email address</a>.
      </p>
    </div>
    <div style="padding: 0 36px 32px;">
      <hr class="divider" style="margin-top:0;"/>
      <div class="back-row"><a href="login.php">← Return to Login</a></div>
    </div>

    <?php else: ?>
    <!-- FORM -->
    <div class="card-body">

      <div class="steps">
        <div class="step-item">
          <div class="step-circle active">1</div>
          <div class="step-lbl active">Email</div>
        </div>
        <div class="step-line"></div>
        <div class="step-item">
          <div class="step-circle inactive">2</div>
          <div class="step-lbl inactive">Verify</div>
        </div>
        <div class="step-line"></div>
        <div class="step-item">
          <div class="step-circle inactive">3</div>
          <div class="step-lbl inactive">Reset</div>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <p class="desc-text">Enter the <strong>email address</strong> associated with your account and we'll send you a reset link.</p>

      <form method="POST" action="forgot-password.php">
        <div class="field">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email"
            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus/>
        </div>
        <button type="submit" class="btn-submit">Send Reset Link</button>
      </form>

      <hr class="divider"/>
      <div class="back-row"><a href="login.php">← Back to Login</a></div>
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- FOOTER -->
<?php include '../../includes/footer.php'; ?>

</body>
</html>