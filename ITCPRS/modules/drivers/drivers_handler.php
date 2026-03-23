<?php
/**
 * modules/drivers/drivers_handler.php
 * ─────────────────────────────────────────────────────────────
 * Backend handler for driver CRUD operations.
 * Manages users with role = 'driver'.
 * Always redirects back to drivers.php on completion.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: drivers.php');
  exit;
}

verify_csrf();

$action = trim($_POST['action'] ?? '');

// ── Upload directory ───────────────────────────────────────────
define('DRIVER_UPLOAD_DIR', __DIR__ . '/../../assets/uploads/drivers/');
define('DRIVER_UPLOAD_URL', '/assets/uploads/drivers/');

if (!is_dir(DRIVER_UPLOAD_DIR)) {
  mkdir(DRIVER_UPLOAD_DIR, 0755, true);
}

// ── Helpers ───────────────────────────────────────────────────

function sanitize_driver(array $p): array {
  return [
    'full_name'      => trim($p['full_name']      ?? ''),
    'username'       => trim($p['username']       ?? ''),
    'email'          => strtolower(trim($p['email'] ?? '')),
    'contact_number' => trim($p['contact_number'] ?? ''),
    'status'         => in_array($p['status'] ?? '', ['active','inactive','suspended'], true)
                          ? $p['status'] : 'active',
    'notes'          => trim($p['notes'] ?? ''),
  ];
}

function validate_driver(array $d, bool $is_new = true, int $exclude_id = 0): array {
  global $conn;
  $errors = [];

  if ($d['full_name'] === '')     $errors[] = 'Full name is required.';
  if (mb_strlen($d['full_name']) > 150) $errors[] = 'Full name must not exceed 150 characters.';

  if ($d['username'] === '') {
    $errors[] = 'Username is required.';
  } elseif (!preg_match('/^[a-zA-Z0-9._\-]{3,60}$/', $d['username'])) {
    $errors[] = 'Username must be 3–60 characters: letters, numbers, dots, hyphens, or underscores.';
  } else {
    // Uniqueness check
    $stmt = mysqli_prepare($conn,
      'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'si', $d['username'], $exclude_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) $errors[] = 'That username is already taken.';
    mysqli_stmt_close($stmt);
  }

  if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
  }

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
      !preg_match('/^[0-9+\-\s()]{7,20}$/', $d['contact_number'])) {
    $errors[] = 'Enter a valid contact number.';
  }

  return $errors;
}

function handle_photo_upload(string $field, string $prefix, ?string $old_file = null): array {
  if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
    return ['filename' => null, 'error' => null];
  }

  $file = $_FILES[$field];

  if ($file['error'] !== UPLOAD_ERR_OK) {
    return ['filename' => null, 'error' => 'Upload failed (code ' . $file['error'] . ').'];
  }

  if ($file['size'] > 5 * 1024 * 1024) {
    return ['filename' => null, 'error' => 'Photo too large (max 5 MB).'];
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime  = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  if (!in_array($mime, $allowed, true)) {
    return ['filename' => null, 'error' => 'Only JPG, PNG, WebP or GIF images are allowed.'];
  }

  $ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
  };

  $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest     = DRIVER_UPLOAD_DIR . $filename;

  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    return ['filename' => null, 'error' => 'Could not save photo. Check folder permissions.'];
  }

  if ($old_file && file_exists(DRIVER_UPLOAD_DIR . $old_file)) {
    @unlink(DRIVER_UPLOAD_DIR . $old_file);
  }

  return ['filename' => $filename, 'error' => null];
}

function redirect_success(string $msg): never {
  $_SESSION['flash_type']    = 'success';
  $_SESSION['flash_message'] = $msg;
  header('Location: drivers.php');
  exit;
}

function redirect_error(string $msg, string $modal = '', array $form_data = []): never {
  $_SESSION['flash_type']    = 'error';
  $_SESSION['flash_message'] = $msg;
  if ($modal)     $_SESSION['modal_reopen'] = $modal;
  if ($form_data) $_SESSION['form_data']    = $form_data;
  header('Location: drivers.php');
  exit;
}

// ═══════════════════════════════════════════════════════════════
//  CREATE
// ═══════════════════════════════════════════════════════════════
if ($action === 'create') {
  $d      = sanitize_driver($_POST);
  $errors = validate_driver($d, true);

  // Password is required on create
  $password = $_POST['password'] ?? '';
  if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

  if (!empty($errors)) {
    redirect_error(implode(' ', $errors), 'add', $d);
  }

  $up = handle_photo_upload('photo', 'driver_');
  if ($up['error']) redirect_error($up['error'], 'add', $d);

  $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
  $role  = 'driver';
  $photo = $up['filename'];

  $stmt = mysqli_prepare($conn,
    'INSERT INTO users
       (full_name, username, email, contact_number, photo, password_hash, role, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
  );
  // s s s s s s s s = 8 params
  mysqli_stmt_bind_param($stmt, 'ssssssss',
    $d['full_name'], $d['username'], $d['email'],
    $d['contact_number'], $photo, $hash, $role, $d['status']
  );

  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    redirect_error('Database error. Please try again.', 'add', $d);
  }

  mysqli_stmt_close($stmt);
  redirect_success('Driver ' . $d['full_name'] . ' added successfully.');
}

// ═══════════════════════════════════════════════════════════════
//  UPDATE
// ═══════════════════════════════════════════════════════════════
if ($action === 'update') {
  $id = (int) ($_POST['id'] ?? 0);
  if ($id <= 0) redirect_error('Invalid driver ID.');

  $d      = sanitize_driver($_POST);
  $errors = validate_driver($d, false, $id);

  if (!empty($errors)) {
    $d['id'] = $id;
    redirect_error(implode(' ', $errors), 'edit', $d);
  }

  // Fetch existing photo
  $row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT photo FROM users WHERE id = $id LIMIT 1"
  ));
  $old_photo = $row['photo'] ?? null;

  $up = handle_photo_upload('photo', 'driver_', $old_photo);
  if ($up['error']) {
    $d['id'] = $id;
    redirect_error($up['error'], 'edit', $d);
  }

  $photo = $up['filename'] ?? $old_photo;

  // Handle remove photo checkbox
  if (!empty($_POST['remove_photo'])) {
    if ($old_photo && file_exists(DRIVER_UPLOAD_DIR . $old_photo)) {
      @unlink(DRIVER_UPLOAD_DIR . $old_photo);
    }
    $photo = null;
  }

  // Optional password change
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
         photo = ?, status = ?, password_hash = ?,
         updated_at = NOW()
       WHERE id = ? AND role = ?'
    );
    $role = 'driver';
    // s×7 + i + s = 9 params: full_name,username,email,contact,photo,status,hash,id,role
    mysqli_stmt_bind_param($stmt, 'sssssssis',
      $d['full_name'], $d['username'], $d['email'],
      $d['contact_number'], $photo, $d['status'],
      $hash, $id, $role
    );
  } else {
    $stmt = mysqli_prepare($conn,
      'UPDATE users SET
         full_name = ?, username = ?, email = ?, contact_number = ?,
         photo = ?, status = ?,
         updated_at = NOW()
       WHERE id = ? AND role = ?'
    );
    $role = 'driver';
    // s s s s s s i s = 8 params: full_name,username,email,contact,photo,status,id,role
    mysqli_stmt_bind_param($stmt, 'ssssssis',
      $d['full_name'], $d['username'], $d['email'],
      $d['contact_number'], $photo, $d['status'],
      $id, $role
    );
  }

  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $d['id'] = $id;
    redirect_error('Database error. Please try again.', 'edit', $d);
  }

  mysqli_stmt_close($stmt);
  redirect_success('Driver ' . $d['full_name'] . ' updated successfully.');
}

// ═══════════════════════════════════════════════════════════════
//  DELETE (admin only)
// ═══════════════════════════════════════════════════════════════
if ($action === 'delete') {
  if (!is_admin()) redirect_error('Only administrators can delete drivers.');

  $id = (int) ($_POST['id'] ?? 0);
  if ($id <= 0) redirect_error('Invalid driver ID.');

  // Get photo before deleting
  $row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT photo FROM users WHERE id = $id AND role = 'driver' LIMIT 1"
  ));

  $stmt = mysqli_prepare($conn,
    "DELETE FROM users WHERE id = ? AND role = 'driver'"
  );
  mysqli_stmt_bind_param($stmt, 'i', $id);

  if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    if (!empty($row['photo']) && file_exists(DRIVER_UPLOAD_DIR . $row['photo'])) {
      @unlink(DRIVER_UPLOAD_DIR . $row['photo']);
    }
    redirect_success('Driver removed successfully.');
  }

  mysqli_stmt_close($stmt);
  redirect_error('Could not delete driver. They may have linked trip records.');
}

redirect_error('Unknown action.');