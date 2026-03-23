<?php
/**
 * profiles/profile.php
 * ─────────────────────────────────────────────────────────────
 * My Profile page — available to ALL roles.
 * Users can edit: Full Name, Contact Number.
 * Users can change their password (requires current password).
 * Read-only: Username, Email, Role, Status, Member Since.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();

global $conn;

// ── Load fresh user data from DB ─────────────────────────────
$uid  = $_SESSION['user']['id'];
$stmt = mysqli_prepare($conn,
    "SELECT id, full_name, username, email, contact_number, photo, role, status, created_at, last_login
     FROM users WHERE id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    // Shouldn't happen, but just in case
    session_destroy();
    header('Location: /modules/auth/login.php');
    exit;
}

// ── Handle POST submissions ───────────────────────────────────
$info_success  = false;
$info_errors   = [];
$pw_success    = false;
$pw_errors     = [];
$active_tab    = 'info'; // which tab to show after POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ════════════════════════
    //  Tab: Edit Profile Info
    // ════════════════════════
    if (isset($_POST['action']) && $_POST['action'] === 'update_info') {
        $active_tab = 'info';

        $new_name    = trim($_POST['full_name']      ?? '');
        $new_contact = trim($_POST['contact_number'] ?? '');

        // Validate
        if (empty($new_name)) {
            $info_errors[] = 'Full name is required.';
        } elseif (mb_strlen($new_name) > 150) {
            $info_errors[] = 'Full name must not exceed 150 characters.';
        }

        if (empty($new_contact)) {
            $info_errors[] = 'Contact number is required.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $new_contact)) {
            $info_errors[] = 'Enter a valid contact number.';
        }

        if (empty($info_errors)) {
            $upd = mysqli_prepare($conn,
                "UPDATE users SET full_name = ?, contact_number = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($upd, 'ssi', $new_name, $new_contact, $uid);

            if (mysqli_stmt_execute($upd)) {
                // Refresh session with new name
                $_SESSION['user']['full_name']      = $new_name;
                $_SESSION['user']['contact_number'] = $new_contact;
                // Reload $user for display
                $user['full_name']      = $new_name;
                $user['contact_number'] = $new_contact;
                $info_success = true;
            } else {
                $info_errors[] = 'Failed to update profile. Please try again.';
            }
            mysqli_stmt_close($upd);
        }
    }

    // ════════════════════════
    //  Tab: Change Password
    // ════════════════════════
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $active_tab   = 'password';
        $current_pw   = $_POST['current_password'] ?? '';
        $new_pw       = $_POST['new_password']     ?? '';
        $confirm_pw   = $_POST['confirm_password'] ?? '';

        // Fetch current hash
        $hstmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($hstmt, 'i', $uid);
        mysqli_stmt_execute($hstmt);
        $hres  = mysqli_stmt_get_result($hstmt);
        $hrow  = mysqli_fetch_assoc($hres);
        mysqli_stmt_close($hstmt);

        // Validate
        if (empty($current_pw)) {
            $pw_errors[] = 'Current password is required.';
        } elseif (!password_verify($current_pw, $hrow['password_hash'])) {
            $pw_errors[] = 'Current password is incorrect.';
        }

        if (strlen($new_pw) < 8) {
            $pw_errors[] = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new_pw)) {
            $pw_errors[] = 'New password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $new_pw)) {
            $pw_errors[] = 'New password must contain at least one number.';
        }

        if (!empty($new_pw) && $new_pw !== $confirm_pw) {
            $pw_errors[] = 'New passwords do not match.';
        }

        if (!empty($new_pw) && $new_pw === $current_pw) {
            $pw_errors[] = 'New password must be different from your current password.';
        }

        if (empty($pw_errors)) {
            $new_hash = password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd = mysqli_prepare($conn,
                "UPDATE users SET password_hash = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($upd, 'si', $new_hash, $uid);
            if (mysqli_stmt_execute($upd)) {
                $pw_success = true;
            } else {
                $pw_errors[] = 'Failed to update password. Please try again.';
            }
            mysqli_stmt_close($upd);
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────
$role_labels = [
    'admin'     => ['Admin',     'pill-navy'],
    'secretary' => ['Secretary', 'pill-blue'],
    'staff'     => ['Staff',     'pill-green'],
    'driver'    => ['Driver',    'pill-amber'],
];
$status_labels = [
    'active'    => ['Active',    'pill-green'],
    'inactive'  => ['Inactive',  'pill-amber'],
    'suspended' => ['Suspended', 'pill-red'],
];

$initials = strtoupper(implode('', array_map(
    fn($w) => $w[0],
    array_slice(explode(' ', trim($user['full_name'])), 0, 2)
)));

$member_since = $user['created_at']
    ? date('F j, Y', strtotime($user['created_at']))
    : '—';

$last_login = $user['last_login']
    ? date('M j, Y \a\t g:i A', strtotime($user['last_login']))
    : 'Never';

// ── Nav setup ─────────────────────────────────────────────────
$page_title  = 'My Profile';
$active_menu = 'profile';
require_once __DIR__ . '/../../includes/admin_nav.php';
?>

<link rel="stylesheet" href="/assets/css/profile.css"/>

<!-- ── Profile Content ──────────────────────────────────────── -->
<div class="profile-wrap">

  <!-- Hero -->
  <div class="profile-hero">
    <div class="profile-hero-avatar <?php echo !empty($user['photo']) ? 'has-photo' : ''; ?>">
      <?php if (!empty($user['photo'])): ?>
        <img src="/assets/uploads/users/<?php echo rawurlencode($user['photo']); ?>"
             alt="<?php echo htmlspecialchars($user['full_name']); ?>" />
      <?php else: ?>
        <?php echo $initials; ?>
      <?php endif; ?>
    </div>
    <div class="profile-hero-info">
      <div class="profile-hero-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
      <div class="profile-hero-meta">
        <?php
          [$rl, $rc] = $role_labels[$user['role']]   ?? [$user['role'],   'pill-navy'];
          [$sl, $sc] = $status_labels[$user['status']] ?? [$user['status'], 'pill-navy'];
        ?>
        <span class="pill <?php echo $rc; ?>"><?php echo $rl; ?></span>
        <span class="pill <?php echo $sc; ?>"><?php echo $sl; ?></span>
      </div>
      <div class="profile-hero-sub">
        <span>
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          @<?php echo htmlspecialchars($user['username']); ?>
        </span>
        <span>
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <?php echo htmlspecialchars($user['email']); ?>
        </span>
        <span>
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Member since <?php echo $member_since; ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="profile-tabs" id="profileTabs">
    <button class="profile-tab <?php echo $active_tab === 'info' ? 'active' : ''; ?>"
            data-tab="info" type="button">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profile Info
    </button>
    <button class="profile-tab <?php echo $active_tab === 'password' ? 'active' : ''; ?>"
            data-tab="password" type="button">
      <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Change Password
    </button>
    <button class="profile-tab <?php echo $active_tab === 'account' ? 'active' : ''; ?>"
            data-tab="account" type="button">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2m0 16v2m7.07-4.93-1.41-1.41M4.93 19.07l1.41-1.41M22 12h-2M4 12H2"/></svg>
      Account Details
    </button>
  </div>

  <!-- ══════════════════════════
       TAB 1: Profile Info
  ══════════════════════════ -->
  <div class="tab-panel <?php echo $active_tab === 'info' ? 'active' : ''; ?>" id="tab-info">
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Edit Profile</h2>
          <div class="card-head-sub">Update your personal information</div>
        </div>
      </div>

      <?php if ($info_success): ?>
        <div class="msg msg-success msg-spacer">
          <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
          <span>Profile updated successfully.</span>
        </div>
      <?php elseif (!empty($info_errors)): ?>
        <div class="msg msg-error msg-spacer">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>
            <?php if (count($info_errors) === 1): ?>
              <?php echo htmlspecialchars($info_errors[0]); ?>
            <?php else: ?>
              <strong>Please fix the following:</strong>
              <ul><?php foreach ($info_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" action="profile.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="update_info"/>

        <div class="info-grid">

          <!-- Full Name — EDITABLE -->
          <div class="info-col-full">
            <div class="field-label">
              <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Full Name <span style="color:var(--red);margin-left:2px;">*</span>
            </div>
            <input type="text" name="full_name" class="profile-input"
              placeholder="e.g. Juan dela Cruz"
              value="<?php echo htmlspecialchars($user['full_name']); ?>"
              autocomplete="name" required/>
          </div>

          <!-- Contact Number — EDITABLE -->
          <div>
            <div class="field-label">
              <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.8 19.8 0 0 1 1.62 3.38 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6.29 6.29l.96-.96a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21.92 16.92z"/></svg>
              Contact Number <span style="color:var(--red);margin-left:2px;">*</span>
            </div>
            <input type="tel" name="contact_number" class="profile-input"
              placeholder="e.g. 09171234567"
              value="<?php echo htmlspecialchars($user['contact_number']); ?>"
              autocomplete="tel" required/>
          </div>

          <!-- Username — READ ONLY -->
          <div>
            <div class="field-label">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>
              Username
            </div>
            <div class="readonly-field">
              @<?php echo htmlspecialchars($user['username']); ?>
              <span class="readonly-badge">Locked</span>
            </div>
            <div style="font-size:11px;color:var(--text-light);margin-top:5px;">
              Contact an admin to change your username.
            </div>
          </div>

          <!-- Email — READ ONLY -->
          <div class="info-col-full">
            <div class="field-label">
              <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              Email Address
            </div>
            <div class="readonly-field">
              <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <?php echo htmlspecialchars($user['email']); ?>
              <span class="readonly-badge">Locked</span>
            </div>
            <div style="font-size:11px;color:var(--text-light);margin-top:5px;">
              Email changes require administrator approval.
            </div>
          </div>

        </div><!-- /.info-grid -->

        <div class="form-foot">
          <button type="reset" class="btn btn-outline">Discard Changes</button>
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17,21 17,13 7,13 7,21"/>
              <polyline points="7,3 7,8 15,8"/>
            </svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════
       TAB 2: Change Password
  ══════════════════════════ -->
  <div class="tab-panel <?php echo $active_tab === 'password' ? 'active' : ''; ?>" id="tab-password">
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Change Password</h2>
          <div class="card-head-sub">Choose a strong password you haven't used before</div>
        </div>
      </div>

      <?php if ($pw_success): ?>
        <div class="msg msg-success msg-spacer">
          <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
          <span>Password changed successfully. Please use your new password next time you log in.</span>
        </div>
      <?php elseif (!empty($pw_errors)): ?>
        <div class="msg msg-error msg-spacer">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>
            <?php if (count($pw_errors) === 1): ?>
              <?php echo htmlspecialchars($pw_errors[0]); ?>
            <?php else: ?>
              <strong>Please fix the following:</strong>
              <ul><?php foreach ($pw_errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" action="profile.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="change_password"/>

        <div class="info-grid">

          <!-- Current Password -->
          <div class="info-col-full">
            <div class="field-label">
              <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Current Password <span style="color:var(--red);margin-left:2px;">*</span>
            </div>
            <div class="pw-wrap-inline">
              <input type="password" id="cpw" name="current_password" class="profile-input"
                placeholder="Enter your current password"
                autocomplete="current-password" required/>
              <button type="button" class="pw-toggle-inline" onclick="togglePw('cpw',this)" aria-label="Show/hide">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <!-- New Password -->
          <div>
            <div class="field-label">
              <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              New Password <span style="color:var(--red);margin-left:2px;">*</span>
            </div>
            <div class="pw-wrap-inline">
              <input type="password" id="npw" name="new_password" class="profile-input"
                placeholder="Min. 8 chars, 1 uppercase, 1 number"
                autocomplete="new-password"
                oninput="checkStrength(this.value)"
                required/>
              <button type="button" class="pw-toggle-inline" onclick="togglePw('npw',this)" aria-label="Show/hide">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
            <div class="pw-hint" id="pwHint">Use uppercase letters, numbers, and symbols.</div>
          </div>

          <!-- Confirm New Password -->
          <div>
            <div class="field-label">
              <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Confirm New Password <span style="color:var(--red);margin-left:2px;">*</span>
            </div>
            <div class="pw-wrap-inline">
              <input type="password" id="cpw2" name="confirm_password" class="profile-input"
                placeholder="Re-enter new password"
                autocomplete="new-password" required/>
              <button type="button" class="pw-toggle-inline" onclick="togglePw('cpw2',this)" aria-label="Show/hide">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <!-- Password requirements hint -->
          <div class="info-col-full">
            <div style="background:var(--bg);border:1px solid var(--border-soft);border-radius:var(--radius-sm);padding:14px 16px;font-size:12.5px;color:var(--text-muted);line-height:1.7;">
              <strong style="color:var(--text);display:block;margin-bottom:4px;">Password requirements:</strong>
              At least 8 characters &nbsp;·&nbsp; At least one uppercase letter &nbsp;·&nbsp; At least one number
            </div>
          </div>

        </div><!-- /.info-grid -->

        <div class="form-foot">
          <button type="reset" class="btn btn-outline">Clear</button>
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Update Password
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════
       TAB 3: Account Details
  ══════════════════════════ -->
  <div class="tab-panel <?php echo $active_tab === 'account' ? 'active' : ''; ?>" id="tab-account">
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Account Details</h2>
          <div class="card-head-sub">System-managed information — contact an admin to make changes</div>
        </div>
      </div>

      <div class="info-grid">

        <div>
          <div class="field-label">Username</div>
          <div class="readonly-field">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>
            @<?php echo htmlspecialchars($user['username']); ?>
            <span class="readonly-badge">Locked</span>
          </div>
        </div>

        <div>
          <div class="field-label">Email Address</div>
          <div class="readonly-field">
            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?php echo htmlspecialchars($user['email']); ?>
            <span class="readonly-badge">Locked</span>
          </div>
        </div>

        <div>
          <div class="field-label">Role</div>
          <div class="readonly-field">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <?php echo $rl; ?>
            <span class="readonly-badge">Admin-set</span>
          </div>
        </div>

        <div>
          <div class="field-label">Account Status</div>
          <div class="readonly-field">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            <span class="pill <?php echo $sc; ?>" style="margin:0;"><?php echo $sl; ?></span>
            <span class="readonly-badge">System</span>
          </div>
        </div>

        <div>
          <div class="field-label">Member Since</div>
          <div class="readonly-field">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?php echo $member_since; ?>
          </div>
        </div>

        <div>
          <div class="field-label">Last Login</div>
          <div class="readonly-field">
            <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10,17 15,12 10,7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            <?php echo $last_login; ?>
          </div>
        </div>

      </div>

      <div class="account-summary">
        <div class="account-summary-item">
          <span class="lbl">User ID</span>
          <span class="val">#<?php echo str_pad($user['id'], 5, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="account-summary-item">
          <span class="lbl">Role</span>
          <span class="val"><?php echo $rl; ?></span>
        </div>
        <div class="account-summary-item">
          <span class="lbl">Status</span>
          <span class="val"><?php echo $sl; ?></span>
        </div>
        <div class="account-summary-item">
          <span class="lbl">Account Age</span>
          <span class="val">
            <?php
              if ($user['created_at']) {
                  $days = (int) ((time() - strtotime($user['created_at'])) / 86400);
                  echo $days === 0 ? 'Today' : ($days === 1 ? '1 day' : "{$days} days");
              } else {
                  echo '—';
              }
            ?>
          </span>
        </div>
      </div>

    </div>
  </div>

</div><!-- /.profile-wrap -->

<script>
// ── Tab switching ────────────────────────────────────────────
document.querySelectorAll('.profile-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = btn.dataset.tab;
    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + target).classList.add('active');
  });
});

// ── Password show/hide toggle ────────────────────────────────
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.querySelector('svg').innerHTML = isText
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
}

// ── Password strength meter ──────────────────────────────────
function checkStrength(val) {
  const bar  = document.getElementById('pwBar');
  const hint = document.getElementById('pwHint');
  if (!bar) return;
  let s = 0;
  if (val.length >= 8)          s++;
  if (/[A-Z]/.test(val))        s++;
  if (/[0-9]/.test(val))        s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const l = [
    { pct:'0%',   bg:'#e0e0e0', txt:'Use uppercase letters, numbers, and symbols.' },
    { pct:'25%',  bg:'#e74c3c', txt:'Weak – add numbers or symbols.' },
    { pct:'50%',  bg:'#e67e22', txt:'Fair – add uppercase or symbols.' },
    { pct:'75%',  bg:'#f1c40f', txt:'Good – almost there!' },
    { pct:'100%', bg:'#27ae60', txt:'Strong password!' },
  ];
  bar.style.width      = l[s].pct;
  bar.style.background = l[s].bg;
  hint.textContent     = l[s].txt;
  hint.style.color     = s === 0 ? 'var(--text-muted)' : l[s].bg;
}
</script>

<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>