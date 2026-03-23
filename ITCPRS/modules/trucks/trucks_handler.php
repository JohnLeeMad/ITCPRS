<?php
/**
 * modules/trucks/trucks_handler.php
 * ─────────────────────────────────────────────────────────────
 * Backend handler for all truck CRUD operations.
 * Handles text fields + image uploads (photo_plate, photo_truck).
 * Always redirects back to trucks.php on completion.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: trucks.php');
  exit;
}

verify_csrf();

$action = trim($_POST['action'] ?? '');

// ── Upload directory (web root relative) ──────────────────────
define('TRUCK_UPLOAD_DIR', __DIR__ . '/../../assets/uploads/trucks/');
define('TRUCK_UPLOAD_URL', '/assets/uploads/trucks/');

// Ensure upload folder exists
if (!is_dir(TRUCK_UPLOAD_DIR)) {
  mkdir(TRUCK_UPLOAD_DIR, 0755, true);
}

// ── Helpers ───────────────────────────────────────────────────

function sanitize_truck(array $p): array {
  return [
    'plate_number'   => strtoupper(trim($p['plate_number']   ?? '')),
    'make'           => trim($p['make']           ?? ''),
    'model'          => trim($p['model']          ?? ''),
    'year'           => ($p['year'] ?? '') !== '' ? (int) $p['year']          : null,
    'color'          => trim($p['color']          ?? ''),
    'chassis_number' => trim($p['chassis_number'] ?? ''),
    'engine_number'  => trim($p['engine_number']  ?? ''),
    'capacity_tons'  => ($p['capacity_tons'] ?? '') !== '' ? (float) $p['capacity_tons'] : null,
    'status'         => in_array($p['status'] ?? '', ['active','inactive','repair'], true)
                          ? $p['status'] : 'active',
    'notes'          => trim($p['notes'] ?? ''),
    'driver_id'      => ($p['driver_id'] ?? '') !== '' ? (int) $p['driver_id'] : null,
    'helper_id'      => ($p['helper_id'] ?? '') !== '' ? (int) $p['helper_id'] : null,
  ];
}

function validate_truck(array $d): array {
  $errors = [];
  if ($d['plate_number'] === '')  $errors[] = 'Plate number is required.';
  if ($d['make']         === '')  $errors[] = 'Make is required.';
  if ($d['model']        === '')  $errors[] = 'Model is required.';
  if ($d['year'] !== null && ($d['year'] < 1950 || $d['year'] > (int) date('Y') + 1))
                                  $errors[] = 'Year is out of valid range.';
  if ($d['capacity_tons'] !== null && $d['capacity_tons'] < 0)
                                  $errors[] = 'Capacity cannot be negative.';
  if ($d['driver_id'] !== null && $d['helper_id'] !== null
      && $d['driver_id'] === $d['helper_id'])
                                  $errors[] = 'Driver and Helper cannot be the same person.';
  return $errors;
}

function plate_exists(mysqli $conn, string $plate, int $exclude_id = 0): bool {
  $stmt = mysqli_prepare($conn,
    'SELECT id FROM trucks WHERE plate_number = ? AND id <> ? LIMIT 1'
  );
  mysqli_stmt_bind_param($stmt, 'si', $plate, $exclude_id);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);
  $found = mysqli_stmt_num_rows($stmt) > 0;
  mysqli_stmt_close($stmt);
  return $found;
}

/**
 * Handle a single file upload slot.
 *
 * @param string  $field      $_FILES key
 * @param string  $prefix     filename prefix  e.g. 'plate_' or 'truck_'
 * @param ?string $old_file   existing filename to delete on success (update only)
 * @return array{filename: ?string, error: ?string}
 *   filename = new stored filename (null = nothing uploaded / keep old)
 *   error    = error message (null = ok)
 */
function handle_image_upload(string $field, string $prefix, ?string $old_file = null): array {
  if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
    return ['filename' => null, 'error' => null]; // nothing uploaded — keep existing
  }

  $file = $_FILES[$field];

  if ($file['error'] !== UPLOAD_ERR_OK) {
    return ['filename' => null, 'error' => 'Upload failed for ' . $field . ' (code ' . $file['error'] . ').'];
  }

  // Max 5 MB
  if ($file['size'] > 5 * 1024 * 1024) {
    return ['filename' => null, 'error' => 'Image too large (max 5 MB).'];
  }

  // Validate MIME by reading magic bytes — never trust $_FILES['type']
  $finfo    = finfo_open(FILEINFO_MIME_TYPE);
  $mime     = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  if (!in_array($mime, $allowed, true)) {
    return ['filename' => null, 'error' => 'Only JPG, PNG, WebP or GIF images are allowed.'];
  }

  $ext      = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
  };

  // Unique filename: prefix + truck_id-or-time + random
  $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest     = TRUCK_UPLOAD_DIR . $filename;

  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    return ['filename' => null, 'error' => 'Could not save image. Check folder permissions.'];
  }

  // Delete the old file if replacing
  if ($old_file && file_exists(TRUCK_UPLOAD_DIR . $old_file)) {
    @unlink(TRUCK_UPLOAD_DIR . $old_file);
  }

  return ['filename' => $filename, 'error' => null];
}

function save_crew(mysqli $conn, int $truck_id, ?int $driver_id, ?int $helper_id): void {
  $del = mysqli_prepare($conn, 'DELETE FROM truck_drivers WHERE truck_id = ?');
  mysqli_stmt_bind_param($del, 'i', $truck_id);
  mysqli_stmt_execute($del);
  mysqli_stmt_close($del);

  foreach ([['driver', $driver_id], ['helper', $helper_id]] as [$pos, $uid]) {
    if ($uid !== null) {
      $ins = mysqli_prepare($conn,
        'INSERT INTO truck_drivers (truck_id, user_id, position) VALUES (?, ?, ?)'
      );
      mysqli_stmt_bind_param($ins, 'iis', $truck_id, $uid, $pos);
      mysqli_stmt_execute($ins);
      mysqli_stmt_close($ins);
    }
  }
}

function redirect_success(string $msg): never {
  $_SESSION['flash_type']    = 'success';
  $_SESSION['flash_message'] = $msg;
  header('Location: trucks.php');
  exit;
}

function redirect_error(string $msg, string $modal = '', array $form_data = []): never {
  $_SESSION['flash_type']    = 'error';
  $_SESSION['flash_message'] = $msg;
  if ($modal)     $_SESSION['modal_reopen'] = $modal;
  if ($form_data) $_SESSION['form_data']    = $form_data;
  header('Location: trucks.php');
  exit;
}

// ═══════════════════════════════════════════════════════════════
//  CREATE
// ═══════════════════════════════════════════════════════════════
if ($action === 'create') {
  $d      = sanitize_truck($_POST);
  $errors = validate_truck($d);

  if (empty($errors) && plate_exists($conn, $d['plate_number'])) {
    $errors[] = 'A truck with plate ' . $d['plate_number'] . ' already exists.';
  }

  if (!empty($errors)) {
    redirect_error(implode(' ', $errors), 'add', $d);
  }

  // Handle image uploads
  $up_plate = handle_image_upload('photo_plate', 'plate_');
  $up_truck = handle_image_upload('photo_truck', 'truck_');

  $img_errors = array_filter([$up_plate['error'], $up_truck['error']]);
  if ($img_errors) {
    redirect_error(implode(' ', $img_errors), 'add', $d);
  }

  $photo_plate = $up_plate['filename']; // null if none uploaded
  $photo_truck = $up_truck['filename'];

  $stmt = mysqli_prepare($conn,
    'INSERT INTO trucks
       (plate_number, make, model, year, color, chassis_number, engine_number,
        capacity_tons, status, notes, photo_plate, photo_truck)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  // s s s i s s s d s s s s = 12 params
  mysqli_stmt_bind_param($stmt, 'sssisssdssss',
    $d['plate_number'], $d['make'], $d['model'],
    $d['year'], $d['color'],
    $d['chassis_number'], $d['engine_number'],
    $d['capacity_tons'], $d['status'], $d['notes'],
    $photo_plate, $photo_truck
  );

  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    redirect_error('Database error. Please try again.', 'add', $d);
  }

  $new_id = (int) mysqli_insert_id($conn);
  mysqli_stmt_close($stmt);
  save_crew($conn, $new_id, $d['driver_id'], $d['helper_id']);
  redirect_success('Truck ' . $d['plate_number'] . ' added successfully.');
}

// ═══════════════════════════════════════════════════════════════
//  UPDATE
// ═══════════════════════════════════════════════════════════════
if ($action === 'update') {
  $id = (int) ($_POST['id'] ?? 0);
  if ($id <= 0) redirect_error('Invalid truck ID.');

  $d      = sanitize_truck($_POST);
  $errors = validate_truck($d);

  if (empty($errors) && plate_exists($conn, $d['plate_number'], $id)) {
    $errors[] = 'Another truck with plate ' . $d['plate_number'] . ' already exists.';
  }

  if (!empty($errors)) {
    $d['id'] = $id;
    redirect_error(implode(' ', $errors), 'edit', $d);
  }

  // Fetch existing photo filenames so we can delete old files on replace
  $row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT photo_plate, photo_truck FROM trucks WHERE id = $id LIMIT 1"
  ));
  $old_plate = $row['photo_plate'] ?? null;
  $old_truck = $row['photo_truck'] ?? null;

  $up_plate = handle_image_upload('photo_plate', 'plate_', $old_plate);
  $up_truck = handle_image_upload('photo_truck', 'truck_', $old_truck);

  $img_errors = array_filter([$up_plate['error'], $up_truck['error']]);
  if ($img_errors) {
    $d['id'] = $id;
    redirect_error(implode(' ', $img_errors), 'edit', $d);
  }

  // Keep existing filename if no new upload, use new filename if uploaded
  $photo_plate = $up_plate['filename'] ?? $old_plate;
  $photo_truck = $up_truck['filename'] ?? $old_truck;

  // Handle "remove image" checkboxes
  if (!empty($_POST['remove_photo_plate'])) {
    if ($old_plate && file_exists(TRUCK_UPLOAD_DIR . $old_plate)) @unlink(TRUCK_UPLOAD_DIR . $old_plate);
    $photo_plate = null;
  }
  if (!empty($_POST['remove_photo_truck'])) {
    if ($old_truck && file_exists(TRUCK_UPLOAD_DIR . $old_truck)) @unlink(TRUCK_UPLOAD_DIR . $old_truck);
    $photo_truck = null;
  }

  $stmt = mysqli_prepare($conn,
    'UPDATE trucks SET
       plate_number = ?, make = ?, model = ?, year = ?,
       color = ?, chassis_number = ?, engine_number = ?,
       capacity_tons = ?, status = ?, notes = ?,
       photo_plate = ?, photo_truck = ?,
       updated_at = NOW()
     WHERE id = ?'
  );
  // s s s i s s s d s s s s i = 13 params
  mysqli_stmt_bind_param($stmt, 'sssisssdssssi',
    $d['plate_number'], $d['make'], $d['model'],
    $d['year'], $d['color'],
    $d['chassis_number'], $d['engine_number'],
    $d['capacity_tons'], $d['status'], $d['notes'],
    $photo_plate, $photo_truck, $id
  );

  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $d['id'] = $id;
    redirect_error('Database error. Please try again.', 'edit', $d);
  }

  mysqli_stmt_close($stmt);
  save_crew($conn, $id, $d['driver_id'], $d['helper_id']);
  redirect_success('Truck ' . $d['plate_number'] . ' updated successfully.');
}

// ═══════════════════════════════════════════════════════════════
//  DELETE (admin only)
// ═══════════════════════════════════════════════════════════════
if ($action === 'delete') {
  if (!is_admin()) redirect_error('Only administrators can delete trucks.');

  $id = (int) ($_POST['id'] ?? 0);
  if ($id <= 0) redirect_error('Invalid truck ID.');

  // Fetch photos before deleting so we can clean up the files
  $row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT photo_plate, photo_truck FROM trucks WHERE id = $id LIMIT 1"
  ));

  $stmt = mysqli_prepare($conn, 'DELETE FROM trucks WHERE id = ?');
  mysqli_stmt_bind_param($stmt, 'i', $id);

  if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    // Delete uploaded files
    foreach (['photo_plate', 'photo_truck'] as $col) {
      if (!empty($row[$col]) && file_exists(TRUCK_UPLOAD_DIR . $row[$col])) {
        @unlink(TRUCK_UPLOAD_DIR . $row[$col]);
      }
    }
    redirect_success('Truck removed successfully.');
  }

  mysqli_stmt_close($stmt);
  redirect_error('Could not delete truck. It may have linked trip records.');
}

redirect_error('Unknown action.');