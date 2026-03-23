<?php
/**
 * modules/trucks/trucks.php
 * Display page — all mutations POST to trucks_handler.php.
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary']);

$page_title  = 'Trucks';
$active_menu = 'trucks';

$flash_type    = $_SESSION['flash_type']    ?? '';
$flash_message = $_SESSION['flash_message'] ?? '';
$modal_reopen  = $_SESSION['modal_reopen']  ?? '';
$form_data     = $_SESSION['form_data']     ?? [];
unset($_SESSION['flash_type'], $_SESSION['flash_message'],
      $_SESSION['modal_reopen'], $_SESSION['form_data']);

function e(mixed $v): string {
  return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function truck_img_url(?string $filename): string {
  if (!$filename) return '';
  return '/assets/uploads/trucks/' . rawurlencode($filename);
}

function driver_img_url(?string $filename): string {
  if (!$filename) return '';
  return '/assets/uploads/drivers/' . rawurlencode($filename);
}

function driver_options(array $drivers, ?int $selected): string {
  $html = '<option value="">— Unassigned —</option>';
  foreach ($drivers as $d) {
    $sel       = ((int) $d['id'] === $selected) ? ' selected' : '';
    $photo_url = $d['photo'] ? '/assets/uploads/drivers/' . rawurlencode($d['photo']) : '';
    $html .= '<option value="' . (int) $d['id'] . '"'
           . $sel
           . ' data-photo="' . htmlspecialchars($photo_url, ENT_QUOTES) . '"'
           . '>' . htmlspecialchars((string)($d['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
  }
  return $html;
}

// ── Fetch drivers ─────────────────────────────────────────────
$all_drivers = [];
$dr = mysqli_query($conn,
  "SELECT id, full_name, photo FROM users
   WHERE role = 'driver' AND status = 'active'
   ORDER BY full_name ASC"
);
if ($dr) {
  while ($row = mysqli_fetch_assoc($dr)) $all_drivers[] = $row;
  mysqli_free_result($dr);
}

// ── Fetch trucks with crew + photos ──────────────────────────
$trucks = [];
$result = mysqli_query($conn,
  "SELECT t.id, t.plate_number, t.make, t.model, t.year, t.color,
          t.chassis_number, t.engine_number, t.capacity_tons,
          t.status, t.notes,
          t.photo_plate, t.photo_truck,
          MAX(CASE WHEN td.position = 'driver' THEN td.user_id   END) AS driver_id,
          MAX(CASE WHEN td.position = 'driver' THEN u1.full_name END) AS driver_name,
          MAX(CASE WHEN td.position = 'driver' THEN u1.photo     END) AS driver_photo,
          MAX(CASE WHEN td.position = 'helper' THEN td.user_id   END) AS helper_id,
          MAX(CASE WHEN td.position = 'helper' THEN u2.full_name END) AS helper_name,
          MAX(CASE WHEN td.position = 'helper' THEN u2.photo     END) AS helper_photo
   FROM trucks t
   LEFT JOIN truck_drivers td ON td.truck_id = t.id
   LEFT JOIN users u1 ON u1.id = td.user_id AND td.position = 'driver'
   LEFT JOIN users u2 ON u2.id = td.user_id AND td.position = 'helper'
   GROUP BY t.id
   ORDER BY t.created_at DESC"
);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) $trucks[] = $row;
  mysqli_free_result($result);
}

$stat_total    = count($trucks);
$stat_active   = count(array_filter($trucks, fn($t) => $t['status'] === 'active'));
$stat_inactive = count(array_filter($trucks, fn($t) => $t['status'] === 'inactive'));
$stat_repair   = count(array_filter($trucks, fn($t) => $t['status'] === 'repair'));

$per_page     = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$total_pages  = max(1, (int) ceil($stat_total / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paged_trucks = array_slice($trucks, $offset, $per_page);

$can_manage   = has_role(['admin', 'secretary']);
$fd_driver_id = (int) ($form_data['driver_id'] ?? 0);
$fd_helper_id = (int) ($form_data['helper_id'] ?? 0);

require_once __DIR__ . '/../../includes/admin_nav.php';

// Pass drivers data to JS for the custom crew picker
$drivers_for_js = array_map(fn($d) => [
  'id'    => (int) $d['id'],
  'name'  => $d['full_name'],
  'photo' => $d['photo'] ? '/assets/uploads/drivers/' . rawurlencode($d['photo']) : '',
], $all_drivers);
?>
<script>window.CREW_DRIVERS = <?php echo json_encode($drivers_for_js, JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>

<link rel="stylesheet" href="/assets/css/trucks.css" />

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-header-eyebrow">Fleet Management</div>
    <h1>Trucks</h1>
    <div class="page-header-sub">
      <?php echo $stat_total; ?> truck<?php echo $stat_total !== 1 ? 's' : ''; ?> registered &mdash;
      <?php echo $stat_active; ?> active, <?php echo $stat_repair; ?> under repair
    </div>
  </div>
  <?php if ($can_manage): ?>
  <div class="page-header-actions">
    <button class="btn btn-gold" id="addTruckBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add Truck
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
          <rect x="1" y="3" width="15" height="13" rx="1"/>
          <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
          <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
      </div>
      <div class="stat-trend flat">Total</div>
    </div>
    <div class="stat-value"><?php echo $stat_total; ?></div>
    <div class="stat-label">Total Trucks</div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-header">
      <div class="stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_active > 0 ? 'up' : 'flat'; ?>">
        <?php if ($stat_active > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18,15 12,9 6,15"/></svg><?php endif; ?>
        On road
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_active; ?></div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card stat-gold">
    <div class="stat-header">
      <div class="stat-icon gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
      </div>
      <div class="stat-trend flat">Standby</div>
    </div>
    <div class="stat-value"><?php echo $stat_inactive; ?></div>
    <div class="stat-label">Inactive</div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_repair > 0 ? 'down' : 'flat'; ?>">
        <?php if ($stat_repair > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
        In garage
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_repair; ?></div>
    <div class="stat-label">Under Repair</div>
  </div>
</div>

<!-- ── Table Card ─────────────────────────────────────────────── -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>Fleet Registry</h2>
      <div class="card-head-sub">Hover over plate, model, or crew to see photos</div>
    </div>
    <div class="trucks-toolbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="truckSearch" placeholder="Search plate, make, driver…" autocomplete="off" />
      </div>
      <select class="filter-select" id="statusFilter">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="repair">Under Repair</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <table class="data-table trucks-table">
      <thead>
        <tr>
          <th>Plate No.</th>
          <th>Make / Model</th>
          <th>Driver</th>
          <th>Helper</th>
          <th>Capacity</th>
          <th>Status</th>
          <?php if ($can_manage): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="trucksTableBody">

        <?php if (empty($paged_trucks)): ?>
        <tr>
          <td colspan="<?php echo $can_manage ? 7 : 6; ?>" class="trucks-empty-cell">
            <div class="trucks-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
                <rect x="1" y="3" width="15" height="13" rx="1"/>
                <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
              </svg>
              <p>No trucks registered yet. Click <strong>Add Truck</strong> to get started.</p>
            </div>
          </td>
        </tr>

        <?php else: foreach ($paged_trucks as $t):
          $plate_url         = truck_img_url($t['photo_plate']);
          $truck_url         = truck_img_url($t['photo_truck']);
          $driver_photo_url  = driver_img_url($t['driver_photo'] ?? null);
          $helper_photo_url  = driver_img_url($t['helper_photo'] ?? null);
        ?>

        <tr data-row
            data-plate="<?php echo e($t['plate_number']); ?>"
            data-make="<?php echo e($t['make']); ?>"
            data-model="<?php echo e($t['model']); ?>"
            data-status="<?php echo e($t['status']); ?>"
            data-driver="<?php echo e($t['driver_name'] ?? ''); ?>"
            data-helper="<?php echo e($t['helper_name'] ?? ''); ?>">

          <!-- ── Plate No. ── -->
          <td data-label="Plate No.">
            <span class="plate-badge peek-trigger"
                  data-peek-type="plate"
                  data-peek-img="<?php echo e($plate_url); ?>"
                  data-peek-label="<?php echo e($t['plate_number']); ?>">
              <?php echo e($t['plate_number']); ?>
              <?php if ($plate_url): ?>
              <svg class="peek-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="11" height="11">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
              <?php endif; ?>
            </span>
          </td>

          <!-- ── Make / Model ── -->
          <td data-label="Make / Model">
            <span class="peek-trigger truck-nameplate"
                  data-peek-type="truck"
                  data-peek-img="<?php echo e($truck_url); ?>"
                  data-peek-label="<?php echo e($t['make'] . ' ' . $t['model']); ?>"
                  data-peek-sub="<?php echo e($t['year'] ? $t['year'] . ($t['color'] ? ' · ' . $t['color'] : '') : ($t['color'] ?? '')); ?>">
              <span class="truck-make"><?php echo e($t['make']); ?> <?php echo e($t['model']); ?></span>
              <span class="truck-year">
                <?php echo $t['year'] ? e($t['year']) : '—'; ?>
                <?php if ($truck_url): ?>
                <svg class="peek-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="10" height="10">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                <?php endif; ?>
              </span>
            </span>
          </td>

          <!-- ── Driver ── -->
          <td data-label="Driver">
            <?php if ($t['driver_name']): ?>
            <div class="crew-cell peek-trigger"
                 data-peek-type="crew"
                 data-peek-img="<?php echo e($driver_photo_url); ?>"
                 data-peek-label="<?php echo e($t['driver_name']); ?>"
                 data-peek-sub="Driver"
                 data-peek-role="Driver">
              <?php if ($driver_photo_url): ?>
                <img class="crew-avatar-img" src="<?php echo e($driver_photo_url); ?>"
                     alt="<?php echo e($t['driver_name']); ?>" />
              <?php else: ?>
                <div class="crew-avatar crew-driver"><?php echo e(mb_strtoupper(mb_substr($t['driver_name'], 0, 1))); ?></div>
              <?php endif; ?>
              <span><?php echo e($t['driver_name']); ?></span>
            </div>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <!-- ── Helper ── -->
          <td data-label="Helper">
            <?php if ($t['helper_name']): ?>
            <div class="crew-cell peek-trigger"
                 data-peek-type="crew"
                 data-peek-img="<?php echo e($helper_photo_url); ?>"
                 data-peek-label="<?php echo e($t['helper_name']); ?>"
                 data-peek-sub="Helper"
                 data-peek-role="Helper">
              <?php if ($helper_photo_url): ?>
                <img class="crew-avatar-img" src="<?php echo e($helper_photo_url); ?>"
                     alt="<?php echo e($t['helper_name']); ?>" />
              <?php else: ?>
                <div class="crew-avatar crew-helper"><?php echo e(mb_strtoupper(mb_substr($t['helper_name'], 0, 1))); ?></div>
              <?php endif; ?>
              <span><?php echo e($t['helper_name']); ?></span>
            </div>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <td data-label="Capacity">
            <?php echo $t['capacity_tons'] !== null
              ? e(number_format((float)$t['capacity_tons'], 1)) . ' t'
              : '<span class="muted">—</span>'; ?>
          </td>

          <td data-label="Status">
            <?php
              $pill_map = ['active'=>'pill-green','inactive'=>'pill-amber','repair'=>'pill-red'];
              $pill_cls = $pill_map[$t['status']] ?? 'pill-navy';
              $pill_lbl = $t['status'] === 'repair' ? 'Under Repair' : ucfirst($t['status']);
            ?>
            <span class="pill <?php echo $pill_cls; ?>"><?php echo $pill_lbl; ?></span>
          </td>

          <?php if ($can_manage): ?>
          <td data-label="Actions">
            <div class="row-actions">
              <button type="button" class="btn btn-outline btn-sm btn-edit-truck" title="Edit"
                data-id="<?php echo (int)$t['id']; ?>"
                data-plate="<?php echo e($t['plate_number']); ?>"
                data-make="<?php echo e($t['make']); ?>"
                data-model="<?php echo e($t['model']); ?>"
                data-year="<?php echo e($t['year'] ?? ''); ?>"
                data-color="<?php echo e($t['color'] ?? ''); ?>"
                data-chassis="<?php echo e($t['chassis_number'] ?? ''); ?>"
                data-engine="<?php echo e($t['engine_number'] ?? ''); ?>"
                data-capacity="<?php echo e($t['capacity_tons'] ?? ''); ?>"
                data-status="<?php echo e($t['status']); ?>"
                data-notes="<?php echo e($t['notes'] ?? ''); ?>"
                data-driver-id="<?php echo (int)($t['driver_id'] ?? 0); ?>"
                data-helper-id="<?php echo (int)($t['helper_id'] ?? 0); ?>"
                data-photo-plate="<?php echo e($plate_url); ?>"
                data-photo-truck="<?php echo e($truck_url); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
              </button>

              <?php if (is_admin()): ?>
              <button type="button" class="btn btn-outline btn-sm btn-delete-truck" title="Delete"
                data-id="<?php echo (int)$t['id']; ?>"
                data-plate="<?php echo e($t['plate_number']); ?>">
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

  <!-- Empty state (JS search) -->
  <div id="emptyState" class="trucks-empty" style="display:none;padding:48px 20px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
      <rect x="1" y="3" width="15" height="13" rx="1"/>
      <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
      <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
    </svg>
    <p>No trucks match your search or filter.</p>
  </div>

  <?php if ($total_pages > 1 || $stat_total > 0): ?>
  <div class="card-foot trucks-pagination">
    <span class="pagination-info" id="countInfo">
      <?php if ($stat_total === 0): ?>No trucks found<?php else: ?>
        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $stat_total); ?>
        of <?php echo $stat_total; ?> truck<?php echo $stat_total !== 1 ? 's' : ''; ?>
      <?php endif; ?>
    </span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination-controls">
      <?php if ($current_page > 1): ?>
        <a class="page-btn" href="?page=<?php echo $current_page - 1; ?>"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg></a>
      <?php else: ?>
        <span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg></span>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a class="page-btn <?php echo $p === $current_page ? 'active' : ''; ?>" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
      <?php if ($current_page < $total_pages): ?>
        <a class="page-btn" href="?page=<?php echo $current_page + 1; ?>"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg></a>
      <?php else: ?>
        <span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Hover Peek Popup (single shared element, positioned by JS) -->
<div id="truckPeek" class="truck-peek" aria-hidden="true">
  <div class="truck-peek-img-wrap">
    <img id="truckPeekImg" src="" alt="" />
    <div id="truckPeekNoImg" class="truck-peek-no-img">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <circle cx="8.5" cy="8.5" r="1.5"/>
        <polyline points="21,15 16,10 5,21"/>
      </svg>
      <span>No photo uploaded</span>
    </div>
  </div>
  <div class="truck-peek-info">
    <div id="truckPeekLabel" class="truck-peek-label"></div>
    <div id="truckPeekSub"   class="truck-peek-sub"></div>
  </div>
</div>


<?php if ($can_manage): ?>
<!-- ── Hidden delete form ─────────────────────────────────────── -->
<form id="deleteForm" method="POST" action="trucks_handler.php" style="display:none;" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action"     value="delete">
  <input type="hidden" name="id"         id="deleteFormId">
</form>

<!-- ══════════════════════════════════════════════════════════
     ADD TRUCK MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="addTruckModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Add New Truck</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="trucks_handler.php" enctype="multipart/form-data" id="addTruckForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="create">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group">
            <label class="form-label" for="a_plate">Plate Number <span class="req">*</span></label>
            <input class="form-control <?php echo ($modal_reopen==='add' && ($form_data['plate_number']??'')==='') ? 'is-error':''; ?>"
                   id="a_plate" name="plate_number" type="text"
                   placeholder="e.g. ABC 1234" maxlength="20" style="text-transform:uppercase"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['plate_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_status">Status <span class="req">*</span></label>
            <select class="form-control" id="a_status" name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','repair'=>'Under Repair'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='add' && ($form_data['status']??'active')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_make">Make <span class="req">*</span></label>
            <input class="form-control" id="a_make" name="make" type="text" placeholder="e.g. Isuzu" maxlength="80"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['make']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_model">Model <span class="req">*</span></label>
            <input class="form-control" id="a_model" name="model" type="text" placeholder="e.g. Elf NHR" maxlength="80"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['model']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_year">Year</label>
            <input class="form-control" id="a_year" name="year" type="number"
                   placeholder="<?php echo date('Y'); ?>" min="1950" max="<?php echo date('Y')+1; ?>"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['year']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_color">Color</label>
            <input class="form-control" id="a_color" name="color" type="text" placeholder="e.g. White" maxlength="40"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['color']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_chassis">Chassis No.</label>
            <input class="form-control" id="a_chassis" name="chassis_number" type="text" placeholder="Optional" maxlength="50"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['chassis_number']??'') : ''; ?>" />
            <span class="form-hint">Used by garage custodian for parts tracking</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_engine">Engine No.</label>
            <input class="form-control" id="a_engine" name="engine_number" type="text" placeholder="Optional" maxlength="50"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['engine_number']??'') : ''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="a_capacity">Capacity (metric tons)</label>
            <input class="form-control" id="a_capacity" name="capacity_tons" type="number" step="0.5" min="0" placeholder="e.g. 5"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['capacity_tons']??'') : ''; ?>" />
          </div>

          <!-- ── Photos ──────────────────────────────────────── -->
          <div class="form-divider full"><span>Photos</span></div>

          <div class="form-group">
            <label class="form-label" for="a_photo_plate">Plate Photo</label>
            <label class="file-upload-btn" for="a_photo_plate">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="16" height="16">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/>
              </svg>
              <span id="a_photo_plate_label">Choose image…</span>
            </label>
            <input type="file" id="a_photo_plate" name="photo_plate" accept="image/*" class="file-upload-input"
                   data-label-id="a_photo_plate_label" data-preview-id="a_photo_plate_preview" />
            <div class="file-preview-wrap">
              <img id="a_photo_plate_preview" class="file-preview-img" src="" alt="" style="display:none;" />
            </div>
            <span class="form-hint">JPG, PNG, WebP — max 5 MB</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_photo_truck">Truck Photo</label>
            <label class="file-upload-btn" for="a_photo_truck">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="16" height="16">
                <rect x="1" y="3" width="15" height="13" rx="1"/>
                <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
              </svg>
              <span id="a_photo_truck_label">Choose image…</span>
            </label>
            <input type="file" id="a_photo_truck" name="photo_truck" accept="image/*" class="file-upload-input"
                   data-label-id="a_photo_truck_label" data-preview-id="a_photo_truck_preview" />
            <div class="file-preview-wrap">
              <img id="a_photo_truck_preview" class="file-preview-img" src="" alt="" style="display:none;" />
            </div>
            <span class="form-hint">JPG, PNG, WebP — max 5 MB</span>
          </div>

          <!-- ── Crew ────────────────────────────────────────── -->
          <div class="form-divider full"><span>Crew Assignment</span></div>

          <div class="form-group">
            <label class="form-label">Driver</label>
            <input type="hidden" id="a_driver_id" name="driver_id"
                   value="<?php echo $modal_reopen==='add' ? (int)($form_data['driver_id'] ?? 0) ?: '' : ''; ?>" />
            <div class="crew-picker" id="a_driver_picker"
                 data-input="a_driver_id"
                 data-selected="<?php echo $modal_reopen==='add' ? (int)($form_data['driver_id'] ?? 0) ?: '' : ''; ?>">
            </div>
            <span class="form-hint">Primary driver of this truck</span>
          </div>

          <div class="form-group">
            <label class="form-label">Helper</label>
            <input type="hidden" id="a_helper_id" name="helper_id"
                   value="<?php echo $modal_reopen==='add' ? (int)($form_data['helper_id'] ?? 0) ?: '' : ''; ?>" />
            <div class="crew-picker" id="a_helper_picker"
                 data-input="a_helper_id"
                 data-selected="<?php echo $modal_reopen==='add' ? (int)($form_data['helper_id'] ?? 0) ?: '' : ''; ?>">
            </div>
            <span class="form-hint">Secondary crew member</span>
          </div>

          <?php if (empty($all_drivers)): ?>
          <div class="full"><p class="crew-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            No active drivers found. Add driver accounts first.
          </p></div>
          <?php endif; ?>

          <div class="form-group full">
            <label class="form-label" for="a_notes">Notes / Remarks</label>
            <textarea class="form-control" id="a_notes" name="notes" rows="3"
                      placeholder="Common issues, garage remarks, etc." maxlength="500"
              ><?php echo $modal_reopen==='add' ? e($form_data['notes']??'') : ''; ?></textarea>
          </div>

        </div>
      </div>
      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Add Truck</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT TRUCK MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="editTruckModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Edit Truck</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="trucks_handler.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="update">
      <input type="hidden" name="id"         id="edit_id"
             value="<?php echo $modal_reopen==='edit' ? (int)($form_data['id']??0) : ''; ?>">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group">
            <label class="form-label" for="edit_plate">Plate Number <span class="req">*</span></label>
            <input class="form-control" id="edit_plate" name="plate_number" type="text"
                   placeholder="e.g. ABC 1234" maxlength="20" style="text-transform:uppercase"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['plate_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_status">Status <span class="req">*</span></label>
            <select class="form-control" id="edit_status" name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','repair'=>'Under Repair'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='edit' && ($form_data['status']??'')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_make">Make <span class="req">*</span></label>
            <input class="form-control" id="edit_make" name="make" type="text" placeholder="e.g. Isuzu" maxlength="80"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['make']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_model">Model <span class="req">*</span></label>
            <input class="form-control" id="edit_model" name="model" type="text" placeholder="e.g. Elf NHR" maxlength="80"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['model']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_year">Year</label>
            <input class="form-control" id="edit_year" name="year" type="number"
                   placeholder="<?php echo date('Y'); ?>" min="1950" max="<?php echo date('Y')+1; ?>"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['year']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_color">Color</label>
            <input class="form-control" id="edit_color" name="color" type="text" placeholder="e.g. White" maxlength="40"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['color']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_chassis">Chassis No.</label>
            <input class="form-control" id="edit_chassis" name="chassis_number" type="text" placeholder="Optional" maxlength="50"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['chassis_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_engine">Engine No.</label>
            <input class="form-control" id="edit_engine" name="engine_number" type="text" placeholder="Optional" maxlength="50"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['engine_number']??'') : ''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_capacity">Capacity (metric tons)</label>
            <input class="form-control" id="edit_capacity" name="capacity_tons" type="number" step="0.5" min="0" placeholder="e.g. 5"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['capacity_tons']??'') : ''; ?>" />
          </div>

          <!-- ── Photos ──────────────────────────────────────── -->
          <div class="form-divider full"><span>Photos</span></div>

          <!-- Plate photo -->
          <div class="form-group">
            <label class="form-label" for="edit_photo_plate">Plate Photo</label>
            <!-- Current image preview -->
            <div class="current-photo-wrap" id="edit_plate_current_wrap" style="display:none;">
              <img id="edit_plate_current" class="current-photo-thumb" src="" alt="Current plate photo" />
              <label class="current-photo-remove">
                <input type="checkbox" name="remove_photo_plate" value="1" id="edit_remove_plate" />
                Remove photo
              </label>
            </div>
            <label class="file-upload-btn" for="edit_photo_plate">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="16" height="16">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/>
              </svg>
              <span id="edit_photo_plate_label">Replace image…</span>
            </label>
            <input type="file" id="edit_photo_plate" name="photo_plate" accept="image/*" class="file-upload-input"
                   data-label-id="edit_photo_plate_label" data-preview-id="edit_photo_plate_preview" />
            <div class="file-preview-wrap">
              <img id="edit_photo_plate_preview" class="file-preview-img" src="" alt="" style="display:none;" />
            </div>
            <span class="form-hint">Leave empty to keep existing photo</span>
          </div>

          <!-- Truck photo -->
          <div class="form-group">
            <label class="form-label" for="edit_photo_truck">Truck Photo</label>
            <div class="current-photo-wrap" id="edit_truck_current_wrap" style="display:none;">
              <img id="edit_truck_current" class="current-photo-thumb" src="" alt="Current truck photo" />
              <label class="current-photo-remove">
                <input type="checkbox" name="remove_photo_truck" value="1" id="edit_remove_truck" />
                Remove photo
              </label>
            </div>
            <label class="file-upload-btn" for="edit_photo_truck">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="16" height="16">
                <rect x="1" y="3" width="15" height="13" rx="1"/>
                <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
              </svg>
              <span id="edit_photo_truck_label">Replace image…</span>
            </label>
            <input type="file" id="edit_photo_truck" name="photo_truck" accept="image/*" class="file-upload-input"
                   data-label-id="edit_photo_truck_label" data-preview-id="edit_photo_truck_preview" />
            <div class="file-preview-wrap">
              <img id="edit_photo_truck_preview" class="file-preview-img" src="" alt="" style="display:none;" />
            </div>
            <span class="form-hint">Leave empty to keep existing photo</span>
          </div>

          <!-- ── Crew ────────────────────────────────────────── -->
          <div class="form-divider full"><span>Crew Assignment</span></div>

          <div class="form-group">
            <label class="form-label">Driver</label>
            <input type="hidden" id="edit_driver_id" name="driver_id"
                   value="<?php echo $modal_reopen==='edit' ? (int)($form_data['driver_id'] ?? 0) ?: '' : ''; ?>" />
            <div class="crew-picker" id="edit_driver_picker"
                 data-input="edit_driver_id"
                 data-selected="<?php echo $modal_reopen==='edit' ? (int)($form_data['driver_id'] ?? 0) ?: '' : ''; ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Helper</label>
            <input type="hidden" id="edit_helper_id" name="helper_id"
                   value="<?php echo $modal_reopen==='edit' ? (int)($form_data['helper_id'] ?? 0) ?: '' : ''; ?>" />
            <div class="crew-picker" id="edit_helper_picker"
                 data-input="edit_helper_id"
                 data-selected="<?php echo $modal_reopen==='edit' ? (int)($form_data['helper_id'] ?? 0) ?: '' : ''; ?>">
            </div>
          </div>

          <?php if (empty($all_drivers)): ?>
          <div class="full"><p class="crew-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            No active drivers in the system.
          </p></div>
          <?php endif; ?>

          <div class="form-group full">
            <label class="form-label" for="edit_notes">Notes / Remarks</label>
            <textarea class="form-control" id="edit_notes" name="notes" rows="3"
                      placeholder="Common issues, garage remarks, etc." maxlength="500"
              ><?php echo $modal_reopen==='edit' ? e($form_data['notes']??'') : ''; ?></textarea>
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
    document.getElementById('<?php echo $modal_reopen === 'edit' ? 'editTruckModal' : 'addTruckModal'; ?>')
      ?.classList.add('open');
  });
</script>
<?php endif; ?>

<script src="/assets/js/trucks.js"></script>
<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>