<?php
/**
 * modules/drivers/drivers.php
 * ─────────────────────────────────────────────────────────────
 * Drivers list page — display only.
 * All mutations POST to drivers_handler.php.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary']);

$page_title  = 'Drivers';
$active_menu = 'drivers';

// ── Consume session flash ─────────────────────────────────────
$flash_type    = $_SESSION['flash_type']    ?? '';
$flash_message = $_SESSION['flash_message'] ?? '';
$modal_reopen  = $_SESSION['modal_reopen']  ?? '';
$form_data     = $_SESSION['form_data']     ?? [];
unset($_SESSION['flash_type'], $_SESSION['flash_message'],
      $_SESSION['modal_reopen'], $_SESSION['form_data']);

// ── Helpers ───────────────────────────────────────────────────
function e(mixed $v): string {
  return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function driver_photo_url(?string $filename): string {
  if (!$filename) return '';
  return '/assets/uploads/drivers/' . rawurlencode($filename);
}

function initials(string $name): string {
  $words = array_filter(explode(' ', trim($name)));
  $parts = array_slice($words, 0, 2);
  return strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), $parts)));
}

// ── Fetch drivers with their assigned truck ───────────────────
$drivers = [];
$result  = mysqli_query($conn,
  "SELECT u.id, u.full_name, u.username, u.email, u.contact_number,
          u.photo, u.status, u.created_at, u.last_login,
          td.position,
          t.id          AS truck_id,
          t.plate_number AS truck_plate,
          t.make         AS truck_make,
          t.model        AS truck_model
   FROM users u
   LEFT JOIN truck_drivers td ON td.user_id = u.id
   LEFT JOIN trucks t         ON t.id = td.truck_id
   WHERE u.role = 'driver'
   ORDER BY u.full_name ASC"
);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) $drivers[] = $row;
  mysqli_free_result($result);
}

// Stats
$stat_total     = count($drivers);
$stat_active    = count(array_filter($drivers, fn($d) => $d['status'] === 'active'));
$stat_inactive  = count(array_filter($drivers, fn($d) => $d['status'] === 'inactive'));
$stat_suspended = count(array_filter($drivers, fn($d) => $d['status'] === 'suspended'));
$stat_assigned  = count(array_filter($drivers, fn($d) => !empty($d['truck_id'])));

// Pagination
$per_page     = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$total_pages  = max(1, (int) ceil($stat_total / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paged        = array_slice($drivers, $offset, $per_page);

$can_manage = has_role(['admin', 'secretary']);

require_once __DIR__ . '/../../includes/admin_nav.php';
?>

<link rel="stylesheet" href="/assets/css/drivers.css" />

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-header-eyebrow">People</div>
    <h1>Drivers</h1>
    <div class="page-header-sub">
      <?php echo $stat_total; ?> driver<?php echo $stat_total !== 1 ? 's' : ''; ?> registered
      &mdash; <?php echo $stat_active; ?> active, <?php echo $stat_assigned; ?> assigned to trucks
    </div>
  </div>
  <?php if ($can_manage): ?>
  <div class="page-header-actions">
    <button class="btn btn-gold" id="addDriverBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add Driver
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<div class="stats-grid">

  <div class="stat-card stat-navy">
    <div class="stat-header">
      <div class="stat-icon navy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div class="stat-trend flat">Total</div>
    </div>
    <div class="stat-value"><?php echo $stat_total; ?></div>
    <div class="stat-label">Total Drivers</div>
  </div>

  <div class="stat-card stat-green">
    <div class="stat-header">
      <div class="stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22,4 12,14.01 9,11.01"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_active > 0 ? 'up' : 'flat'; ?>">
        <?php if ($stat_active > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18,15 12,9 6,15"/></svg><?php endif; ?>
        Available
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_active; ?></div>
    <div class="stat-label">Active</div>
  </div>

  <div class="stat-card stat-gold">
    <div class="stat-header">
      <div class="stat-icon gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <rect x="1" y="3" width="15" height="13" rx="1"/>
          <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
          <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
      </div>
      <div class="stat-trend flat">On trucks</div>
    </div>
    <div class="stat-value"><?php echo $stat_assigned; ?></div>
    <div class="stat-label">Assigned</div>
  </div>

  <div class="stat-card stat-red">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo ($stat_inactive + $stat_suspended) > 0 ? 'down' : 'flat'; ?>">
        <?php if (($stat_inactive + $stat_suspended) > 0): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
        <?php endif; ?>
        Off duty
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_inactive + $stat_suspended; ?></div>
    <div class="stat-label">Inactive / Suspended</div>
  </div>

</div>

<!-- ── Drivers Table Card ─────────────────────────────────────── -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>Driver Roster</h2>
      <div class="card-head-sub">All registered drivers and their current truck assignments</div>
    </div>
    <div class="drivers-toolbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="driverSearch" placeholder="Search name, username, plate…" autocomplete="off" />
      </div>
      <select class="filter-select" id="statusFilter">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="suspended">Suspended</option>
      </select>
      <select class="filter-select" id="assignFilter">
        <option value="">All Assignments</option>
        <option value="assigned">Assigned</option>
        <option value="unassigned">Unassigned</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <table class="data-table drivers-table">
      <thead>
        <tr>
          <th>Driver</th>
          <th>Username</th>
          <th>Contact</th>
          <th>Assigned Truck</th>
          <th>Status</th>
          <?php if ($can_manage): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="driversTableBody">

        <?php if (empty($paged)): ?>
        <tr>
          <td colspan="<?php echo $can_manage ? 6 : 5; ?>" class="drivers-empty-cell">
            <div class="drivers-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
              </svg>
              <p>No drivers registered yet. Click <strong>Add Driver</strong> to get started.</p>
            </div>
          </td>
        </tr>

        <?php else: foreach ($paged as $d):
          $photo_url   = driver_photo_url($d['photo']);
          $ini         = initials($d['full_name']);
          $plate       = $d['truck_plate'] ?? '';
          $is_assigned = !empty($d['truck_id']);
          $position    = $d['position'] ?? '';

          $pill_map = ['active' => 'pill-green', 'inactive' => 'pill-amber', 'suspended' => 'pill-red'];
          $pill_cls = $pill_map[$d['status']] ?? 'pill-navy';
        ?>

        <tr data-row
            data-name="<?php echo e($d['full_name']); ?>"
            data-username="<?php echo e($d['username']); ?>"
            data-plate="<?php echo e($plate); ?>"
            data-status="<?php echo e($d['status']); ?>"
            data-assigned="<?php echo $is_assigned ? 'assigned' : 'unassigned'; ?>">

          <!-- Driver identity with hover peek -->
          <td data-label="Driver">
            <div class="driver-identity peek-trigger"
                 data-peek-img="<?php echo e($photo_url); ?>"
                 data-peek-label="<?php echo e($d['full_name']); ?>"
                 data-peek-sub="<?php echo e($d['contact_number'] ? $d['contact_number'] : ($d['email'] ?: 'No contact info')); ?>"
                 data-peek-username="<?php echo e('@' . $d['username']); ?>">
              <?php if ($photo_url): ?>
                <img class="driver-avatar-img" src="<?php echo e($photo_url); ?>"
                     alt="<?php echo e($d['full_name']); ?>" />
              <?php else: ?>
                <div class="driver-avatar-initials"><?php echo e($ini); ?></div>
              <?php endif; ?>
              <div class="driver-name-wrap">
                <span class="driver-fullname"><?php echo e($d['full_name']); ?></span>
                <?php if ($d['email']): ?>
                <span class="driver-email"><?php echo e($d['email']); ?></span>
                <?php endif; ?>
              </div>
            </div>
          </td>

          <td data-label="Username">
            <span class="driver-username">@<?php echo e($d['username']); ?></span>
          </td>

          <td data-label="Contact">
            <?php echo $d['contact_number'] ? e($d['contact_number']) : '<span class="muted">—</span>'; ?>
          </td>

          <!-- Assigned truck -->
          <td data-label="Assigned Truck">
            <?php if ($is_assigned): ?>
              <span class="plate-badge"><?php echo e($plate); ?></span>
              <span class="truck-model-small"><?php echo e($d['truck_make'] . ' ' . $d['truck_model']); ?></span>
            <?php else: ?>
              <span class="unassigned-badge">Unassigned</span>
            <?php endif; ?>
          </td>

          <!-- Status -->
          <td data-label="Status">
            <span class="pill <?php echo $pill_cls; ?>"><?php echo ucfirst(e($d['status'])); ?></span>
          </td>

          <?php if ($can_manage): ?>
          <td data-label="Actions">
            <div class="row-actions">
              <button type="button" class="btn btn-outline btn-sm btn-edit-driver" title="Edit driver"
                data-id="<?php echo (int)$d['id']; ?>"
                data-full-name="<?php echo e($d['full_name']); ?>"
                data-username="<?php echo e($d['username']); ?>"
                data-email="<?php echo e($d['email'] ?? ''); ?>"
                data-contact="<?php echo e($d['contact_number'] ?? ''); ?>"
                data-status="<?php echo e($d['status']); ?>"
                data-photo="<?php echo e($photo_url); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
              </button>

              <?php if (is_admin()): ?>
              <button type="button" class="btn btn-outline btn-sm btn-delete-driver" title="Delete driver"
                data-id="<?php echo (int)$d['id']; ?>"
                data-name="<?php echo e($d['full_name']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <polyline points="3,6 5,6 21,6"/>
                  <path d="M19 6l-1 14H6L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/>
                  <path d="M9 6V4h6v2"/>
                </svg>
                Delete
              </button>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>

        </tr>
        <?php endforeach; endif; ?>

      </tbody>
    </table>
  </div>

  <!-- Empty state for JS filter -->
  <div id="emptyState" class="drivers-empty" style="display:none;padding:48px 20px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
    </svg>
    <p>No drivers match your search or filter.</p>
  </div>

  <?php if ($total_pages > 1 || $stat_total > 0): ?>
  <div class="card-foot drivers-pagination">
    <span class="pagination-info" id="countInfo">
      <?php if ($stat_total === 0): ?>No drivers found<?php else: ?>
        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $stat_total); ?>
        of <?php echo $stat_total; ?> driver<?php echo $stat_total !== 1 ? 's' : ''; ?>
      <?php endif; ?>
    </span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination-controls">
      <?php if ($current_page > 1): ?>
        <a class="page-btn" href="?page=<?php echo $current_page - 1; ?>">
          <svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg>
        </a>
      <?php else: ?>
        <span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg></span>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a class="page-btn <?php echo $p === $current_page ? 'active' : ''; ?>" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
      <?php if ($current_page < $total_pages): ?>
        <a class="page-btn" href="?page=<?php echo $current_page + 1; ?>">
          <svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
        </a>
      <?php else: ?>
        <span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>


<?php if ($can_manage): ?>
<!-- ── Hidden delete form ─────────────────────────────────────── -->
<form id="deleteForm" method="POST" action="drivers_handler.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action"     value="delete">
  <input type="hidden" name="id"         id="deleteFormId">
</form>

<!-- ── Driver Peek Popup (single shared element, positioned by JS) -->
<div id="driverPeek" class="driver-peek" aria-hidden="true">
  <div class="driver-peek-photo-wrap">
    <img id="driverPeekImg" src="" alt="" />
    <div id="driverPeekInitials" class="driver-peek-initials"></div>
  </div>
  <div class="driver-peek-info">
    <div id="driverPeekName"     class="driver-peek-name"></div>
    <div id="driverPeekUsername" class="driver-peek-username"></div>
    <div id="driverPeekSub"      class="driver-peek-sub"></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     ADD DRIVER MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="addDriverModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Add New Driver</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="drivers_handler.php" enctype="multipart/form-data" id="addDriverForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="create">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="a_full_name">Full Name <span class="req">*</span></label>
            <input class="form-control <?php echo ($modal_reopen==='add' && ($form_data['full_name']??'')==='') ? 'is-error':''; ?>"
                   id="a_full_name" name="full_name" type="text"
                   placeholder="e.g. Juan dela Cruz" maxlength="150"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['full_name']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_username">Username <span class="req">*</span></label>
            <input class="form-control" id="a_username" name="username" type="text"
                   placeholder="e.g. juan_dc" maxlength="60" autocomplete="off"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['username']??'') : ''; ?>" />
            <span class="form-hint">3–60 chars: letters, numbers, dots, hyphens, underscores</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_contact">Contact Number</label>
            <input class="form-control" id="a_contact" name="contact_number" type="tel"
                   placeholder="e.g. 09171234567" maxlength="20"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['contact_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_email">Email Address</label>
            <input class="form-control" id="a_email" name="email" type="email"
                   placeholder="Optional" maxlength="180"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['email']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_password">Password <span class="req">*</span></label>
            <div class="password-wrap">
              <input class="form-control" id="a_password" name="password" type="password"
                     placeholder="Minimum 8 characters" maxlength="100" autocomplete="new-password" />
              <button type="button" class="password-toggle" data-target="a_password" title="Show/hide password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_status">Status</label>
            <select class="form-control" id="a_status" name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='add' && ($form_data['status']??'active')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-divider full"><span>Profile Photo</span></div>

          <div class="form-group full">
            <div class="photo-upload-row">
              <div class="photo-preview-wrap">
                <div class="photo-preview-placeholder" id="a_photo_placeholder">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
                  </svg>
                </div>
                <img id="a_photo_preview" class="photo-preview-img" src="" alt="" style="display:none;" />
              </div>
              <div class="photo-upload-controls">
                <label class="file-upload-btn" for="a_photo">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="15" height="15">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/>
                  </svg>
                  <span id="a_photo_label">Upload photo…</span>
                </label>
                <input type="file" id="a_photo" name="photo" accept="image/*"
                       class="file-upload-input"
                       data-label-id="a_photo_label"
                       data-preview-id="a_photo_preview"
                       data-placeholder-id="a_photo_placeholder" />
                <span class="form-hint">JPG, PNG, WebP — max 5 MB. Optional.</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Add Driver</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT DRIVER MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="editDriverModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Edit Driver</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="drivers_handler.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="update">
      <input type="hidden" name="id"         id="edit_id"
             value="<?php echo $modal_reopen==='edit' ? (int)($form_data['id']??0) : ''; ?>">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="edit_full_name">Full Name <span class="req">*</span></label>
            <input class="form-control" id="edit_full_name" name="full_name" type="text"
                   placeholder="e.g. Juan dela Cruz" maxlength="150"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['full_name']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_username">Username <span class="req">*</span></label>
            <input class="form-control" id="edit_username" name="username" type="text"
                   placeholder="e.g. juan_dc" maxlength="60" autocomplete="off"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['username']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_contact">Contact Number</label>
            <input class="form-control" id="edit_contact" name="contact_number" type="tel"
                   placeholder="e.g. 09171234567" maxlength="20"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['contact_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_email">Email Address</label>
            <input class="form-control" id="edit_email" name="email" type="email"
                   placeholder="Optional" maxlength="180"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['email']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_status">Status</label>
            <select class="form-control" id="edit_status" name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='edit' && ($form_data['status']??'')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_new_password">New Password</label>
            <div class="password-wrap">
              <input class="form-control" id="edit_new_password" name="new_password" type="password"
                     placeholder="Leave blank to keep current" maxlength="100" autocomplete="new-password" />
              <button type="button" class="password-toggle" data-target="edit_new_password" title="Show/hide password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
            <span class="form-hint">Min 8 characters. Leave blank to keep existing password.</span>
          </div>

          <div class="form-divider full"><span>Profile Photo</span></div>

          <div class="form-group full">
            <div class="photo-upload-row">
              <div class="photo-preview-wrap">
                <div class="photo-preview-placeholder" id="edit_photo_placeholder">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
                  </svg>
                </div>
                <img id="edit_photo_preview_current" class="photo-preview-img" src="" alt="" style="display:none;" />
                <img id="edit_photo_preview_new"     class="photo-preview-img" src="" alt="" style="display:none;" />
              </div>
              <div class="photo-upload-controls">
                <!-- Remove existing photo -->
                <label class="current-photo-remove" id="edit_remove_wrap" style="display:none;">
                  <input type="checkbox" name="remove_photo" value="1" id="edit_remove_photo" />
                  Remove current photo
                </label>
                <label class="file-upload-btn" for="edit_photo">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="15" height="15">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/>
                  </svg>
                  <span id="edit_photo_label">Replace photo…</span>
                </label>
                <input type="file" id="edit_photo" name="photo" accept="image/*"
                       class="file-upload-input"
                       data-label-id="edit_photo_label"
                       data-preview-id="edit_photo_preview_new"
                       data-placeholder-id="edit_photo_placeholder" />
                <span class="form-hint">Leave empty to keep existing photo.</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php endif; // $can_manage ?>

<!-- ── Scripts ───────────────────────────────────────────────── -->
<?php if ($flash_message): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    <?php if ($flash_type === 'success'): ?>
    ITCAlert.toast({ title: <?php echo json_encode($flash_message); ?>, type: 'success' });
    <?php else: ?>
    ITCAlert.show({ title: 'Error', text: <?php echo json_encode($flash_message); ?>, type: 'error' });
    <?php endif; ?>
  });
</script>
<?php endif; ?>

<?php if ($modal_reopen): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('<?php echo $modal_reopen === 'edit' ? 'editDriverModal' : 'addDriverModal'; ?>')
      ?.classList.add('open');
  });
</script>
<?php endif; ?>

<script src="/assets/js/drivers.js"></script>
<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>