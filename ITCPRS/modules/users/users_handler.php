<?php
/**
 * modules/users/users_handler.php
 * ─────────────────────────────────────────────────────────────
 * Backend handler for User Management CRUD.
 * Admin-only. Manages roles: admin, secretary, staff.
 * Drivers are managed separately via the Drivers module.
 * Always redirects back to users.php on completion.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: users.php');
  exit;
}

verify_csrf();

$action     = trim($_POST['action'] ?? '');
$current_id = (int) ($_SESSION['user']['id'] ?? 0);

// ── Upload directory ──────────────────────────────────────────
define('USER_UPLOAD_DIR', __DIR__ . '/../../assets/uploads/users/');

if (!is_dir(USER_UPLOAD_DIR)) {
  mkdir(USER_UPLOAD_DIR, 0755, true);
}

// ── Allowed roles for this page (NOT driver) ──────────────────
const ALLOWED_ROLES = ['admin', 'secretary', 'staff'];

// ── Helpers ───────────────────────────────────────────────────

function sanitize_user(array $p): array {
  return [
    'full_name'      => trim($p['full_name']      ?? ''),
    'username'       => trim($p['username']       ?? ''),
    'email'          => strtolower(trim($p['email'] ?? '')),
    'contact_number' => trim($p['contact_number'] ?? ''),
    'role'           => in_array($p['role'] ?? '', ALLOWED_ROLES, true)
                          ? $p['role'] : 'staff',
    'status'         => in_array($p['status'] ?? '', ['active','inactive','suspended'], true)
                          ? $p['status'] : 'active',
  ];
}

function validate_user(array $d, int $exclude_id = 0): array {
  global $conn;
  $errors = [];

  if ($d['full_name'] === '')
    $errors[] = 'Full name is required.';
  if (mb_strlen($d['full_name']) > 150)
    $errors[] = 'Full name must not exceed 150 characters.';

  if ($d['username'] === '') {
    $errors[] = 'Username is required.';
  } elseif (!preg_match('/^[a-zA-Z0-9._\-]{3,60}$/', $d['username'])) {
    $errors[] = 'Username: 3–60 chars, letters/numbers/dots/hyphens/underscores only.';
  } else {
    $stmt = mysqli_prepare($conn,
      'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'si', $d['username'], $exclude_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = 'That username is already taken.';
    mysqli_stmt_close($stmt);
  }

  if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
    $errors[] = 'Enter a valid email address.';

  if ($d['email'] !== '') {
    $stmt = mysqli_prepare($conn,
      'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'si', $d['email'], $exclude_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = 'An account with that email already exists.';
    mysqli_stmt_close($stmt);
  }

  if ($d['contact_number'] !== '' &&
      !preg_match('/^[0-9+\-\s()]{7,20}$/', $d['contact_number']))
    $errors[] = 'Enter a valid contact number.';

  return $errors;
}

function handle_photo_upload(string $field, ?string $old_file = null): array {
  if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE)
    return ['filename' => null, 'error' => null];

  $file = $_FILES[$field];

  if ($file['error'] !== UPLOAD_ERR_OK)
    return ['filename' => null, 'error' => 'Upload failed (code ' . $file['error'] . ').'];

  if ($file['size'] > 5 * 1024 * 1024)
    return ['filename' => null, 'error' => 'Photo too large (max 5 MB).'];

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
  if (!in_array($mime, $allowed, true))
    return ['filename' => null, 'error' => 'Only JPG, PNG, WebP or GIF images are allowed.'];

  $ext = match($mime) {
    'image/jpeg' => 'jpg', 'image/png'  => 'png',
    'image/webp' => 'webp','image/gif'  => 'gif', default => 'jpg',
  };

  $filename = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest     = USER_UPLOAD_DIR . $filename;

  if (!move_uploaded_file($file['tmp_name'], $dest))
    return ['filename' => null, 'error' => 'Could not save photo. Check folder permissions.'];

  if ($old_file && file_exists(USER_UPLOAD_DIR . $old_file))
    @unlink(USER_UPLOAD_DIR . $old_file);

  return ['filename' => $filename, 'error' => null];
}

function redirect_success(string $msg): never {
  $_SESSION['flash_type']    = 'success';
  $_SESSION['flash_message'] = $msg;
  header('Location: users.php');
  exit;
}

function redirect_error(string $msg, string $modal = '', array $form_data = []): never {
  $_SESSION['flash_type']    = 'error';
  $_SESSION['flash_message'] = $msg;
  if ($modal)     $_SESSION['modal_reopen'] = $modal;
  if ($form_data) $_SESSION['form_data']    = $form_data;
  header('Location: users.php');
  exit;
}

// ═══════════════════════════════════════════════════════════════
//  CREATE
// ═══════════════════════════════════════════════════════════════
if ($action === 'create') {
  $d      = sanitize_user($_POST);
  $errors = validate_user($d);

  $password = $_POST['password'] ?? '';
  if (strlen($password) < 8)
    $errors[] = 'Password must be at least 8 characters.';

  if (!empty($errors))
    redirect_error(implode(' ', $errors), 'add', $d);

  $up = handle_photo_upload('photo');
  if ($up['error']) redirect_error($up['error'], 'add', $d);

  $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
  $photo = $up['filename'];

  $stmt = mysqli_prepare($conn,
    'INSERT INTO users
       (full_name, username, email, contact_number, photo, password_hash, role, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
  );
  // s s s s s s s s = 8 params
  mysqli_stmt_bind_param($stmt, 'ssssssss',
    $d['full_name'], $d['username'], $d['email'],
    $d['contact_number'], $photo, $hash, $d['role'], $d['status']
  );

  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    redirect_error('Database error. Please try again.', 'add', $d);
  }

  mysqli_stmt_close($stmt);
  redirect_success($d['full_name'] . ' added successfully.');
}

// ═══════════════════════════════════════════════════════════════
//  UPDATE
// ═══════════════════════════════════════════════════════════════
if ($action === 'update') {
  $id = (int) ($_POST['id'] ?? 0);
  if ($id <= 0) redirect_error('Invalid user ID.');

  $d      = sanitize_user($_POST);
  $errors = validate_user($d, $id);

  // Safety: cannot change own role or deactivate own account
  if ($id === $current_id && $d['role'] !== 'admin')
    $errors[] = 'You cannot change your own role.';
  if ($id === $current_id && $d['status'] !== 'active')
    $errors[] = 'You cannot deactivate your own account.';

  if (!empty($errors)) {
    $d['id'] = $id;
    redirect_error(implode(' ', $errors), 'edit', $d);
  }

  // Fetch existing photo
  $row       = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT photo FROM users WHERE id = $id LIMIT 1"
  ));
  $old_photo = $row['photo'] ?? null;

  $up = handle_photo_upload('photo', $old_photo);
  if ($up['error']) {
    $d['id'] = $id;
    redirect_error($up['error'], 'edit', $d);
  }

  $photo = $up['filename'] ?? $old_photo;

  if (!empty($_POST['remove_photo'])) {
    if ($old_photo && file_exists(USER_UPLOAD_DIR . $old_photo))
      @unlink(USER_UPLOAD_DIR . $old_photo);
    $photo = null;
  }

  $new_password = trim($_POST['new_password'] ?? '');
  if ($new_password !== '') {
    if (strlen($new_password) < 8) {
      $d['id'] = $id;
      redirect_error('New password must be at least 8 characters.', 'edit', $d);
    }
    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = mysqli_prepare($conn,
      'UPDATE users SET
         full_name = ?, username = ?, email = ?, contact_number = ?,
         photo = ?, role = ?, status = ?, password_hash = ?,
         updated_at = NOW()
       WHERE id = ?'
    );
    // s s s s s s s s i = 9 params
    mysqli_stmt_bind_param($stmt, 'ssssssssi',
      $d['full_name'], $d['username'], $d['email'],
      $d['contact_number'], $photo, $d['role'], $d['status'],
      $hash, $id
    );
  } else {
    $stmt = mysqli_prepare($conn,
      'UPDATE users SET
         full_name = ?, username = ?, email = ?, contact_number = ?,
         photo = ?, role = ?, status = ?,
         updated_at = NOW()
       WHERE id = ?'
    );
    // s s s s s s s i = 8 params
    mysqli_stmt_bind_param($stmt, 'sssssssi',
      $d['full_name'], $d['username'], $d['email'],
      $d['contact_number'], $photo, $d['role'], $d['status'], $id
    );
  }

  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $d['id'] = $id;
    redirect_error('Database error. Please try again.', 'edit', $d);
  }

  mysqli_stmt_close($stmt);

  // If admin updated their own account, refresh the session immediately
  if ($id === $current_id) {
    $_SESSION['user']['full_name'] = $d['full_name'];
    $_SESSION['user']['username']  = $d['username'];
    $_SESSION['user']['email']     = $d['email'];
  }

  redirect_success($d['full_name'] . ' updated successfully.');
}

// ═══════════════════════════════════════════════════════════════
//  DELETE (admin only, with safety guards)
// ═══════════════════════════════════════════════════════════════
if ($action === 'delete') {
  $id = (int) ($_POST['id'] ?? 0);
  if ($id <= 0) redirect_error('Invalid user ID.');

  // Cannot delete yourself
  if ($id === $current_id)
    redirect_error('You cannot delete your own account.');

  // Fetch the target user
  $target = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT role, photo FROM users WHERE id = $id LIMIT 1"
  ));

  if (!$target) redirect_error('User not found.');

  // Cannot delete the last admin
  if ($target['role'] === 'admin') {
    $admin_count = (int) mysqli_fetch_row(mysqli_query($conn,
      "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'"
    ))[0];
    if ($admin_count <= 1)
      redirect_error('Cannot delete the last administrator account.');
  }

  $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
  mysqli_stmt_bind_param($stmt, 'i', $id);

  if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    if (!empty($target['photo']) && file_exists(USER_UPLOAD_DIR . $target['photo']))
      @unlink(USER_UPLOAD_DIR . $target['photo']);
    redirect_success('User removed successfully.');
  }

  mysqli_stmt_close($stmt);
  redirect_error('Could not delete user.');
}

redirect_error('Unknown action.');