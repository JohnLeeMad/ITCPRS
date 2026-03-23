<?php
/**
 * modules/contracts/contracts.php
 * ─────────────────────────────────────────────────────────────
 * Contracts list page — display only.
 * Admin + secretary can manage. Staff can view.
 * All mutations POST to contracts_handler.php.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary']);

$page_title  = 'Contracts';
$active_menu = 'contracts';

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

function fmt_date(?string $d): string {
  return $d ? date('M j, Y', strtotime($d)) : '—';
}

function days_remaining(?string $end_date): int {
  if (!$end_date) return 0;
  return (int) ceil((strtotime($end_date) - time()) / 86400);
}

// Auto-refresh contract statuses on every page load
// (lightweight — only updates rows where status is stale)
mysqli_query($conn,
  "UPDATE contracts SET status = 'expired'
   WHERE end_date < CURDATE() AND status NOT IN ('cancelled','expired')"
);
mysqli_query($conn,
  "UPDATE contracts SET status = 'expiring'
   WHERE end_date >= CURDATE()
     AND end_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND status = 'active'"
);
mysqli_query($conn,
  "UPDATE contracts SET status = 'active'
   WHERE end_date >= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND status = 'expiring'"
);

// ── Fetch all trucks for the dropdown ─────────────────────────
$all_trucks = [];
$tr = mysqli_query($conn,
  "SELECT id, plate_number, make, model
   FROM trucks
   WHERE status = 'active'
   ORDER BY plate_number ASC"
);
if ($tr) {
  while ($row = mysqli_fetch_assoc($tr)) $all_trucks[] = $row;
  mysqli_free_result($tr);
}

// ── Fetch contracts with truck info ───────────────────────────
$contracts = [];
$result    = mysqli_query($conn,
  "SELECT c.id, c.contract_number, c.client_name, c.destination, c.origin,
          c.start_date, c.end_date, c.rate_per_trip, c.status, c.notes,
          c.created_at, c.updated_at,
          t.id AS truck_id, t.plate_number AS truck_plate,
          t.make AS truck_make, t.model AS truck_model,
          u.full_name AS created_by_name
   FROM contracts c
   LEFT JOIN trucks t ON t.id = c.truck_id
   LEFT JOIN users  u ON u.id = c.created_by
   ORDER BY c.created_at DESC"
);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) $contracts[] = $row;
  mysqli_free_result($result);
}

// Stats
$stat_total     = count($contracts);
$stat_active    = count(array_filter($contracts, fn($c) => $c['status'] === 'active'));
$stat_expiring  = count(array_filter($contracts, fn($c) => $c['status'] === 'expiring'));
$stat_expired   = count(array_filter($contracts, fn($c) => $c['status'] === 'expired'));
$stat_cancelled = count(array_filter($contracts, fn($c) => $c['status'] === 'cancelled'));

// Pagination
$per_page     = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$total_pages  = max(1, (int) ceil($stat_total / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paged        = array_slice($contracts, $offset, $per_page);

$can_manage = has_role(['admin', 'secretary']);

// Status config
$status_config = [
  'active'    => ['pill' => 'pill-green',  'label' => 'Active'],
  'expiring'  => ['pill' => 'pill-amber',  'label' => 'Expiring Soon'],
  'expired'   => ['pill' => 'pill-navy',   'label' => 'Expired'],
  'cancelled' => ['pill' => 'pill-red',    'label' => 'Cancelled'],
];

require_once __DIR__ . '/../../includes/admin_nav.php';
?>

<link rel="stylesheet" href="/assets/css/contracts.css" />

<!-- ── Flash messages ───────────────────────────────────────── -->
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

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-header-eyebrow">Operations</div>
    <h1>Contracts</h1>
    <div class="page-header-sub">
      <?php echo $stat_total; ?> contract<?php echo $stat_total !== 1 ? 's' : ''; ?> total &mdash;
      <?php echo $stat_active; ?> active<?php echo $stat_expiring > 0 ? ', <strong>' . $stat_expiring . ' expiring soon</strong>' : ''; ?>
    </div>
  </div>
  <?php if ($can_manage): ?>
  <div class="page-header-actions">
    <button class="btn btn-gold" id="addContractBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add Contract
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<div class="stats-grid contracts-stats-grid">

  <div class="stat-card stat-navy">
    <div class="stat-header">
      <div class="stat-icon navy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14,2 14,8 20,8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
      </div>
      <div class="stat-trend flat">All</div>
    </div>
    <div class="stat-value"><?php echo $stat_total; ?></div>
    <div class="stat-label">Total Contracts</div>
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
        Running
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_active; ?></div>
    <div class="stat-label">Active</div>
  </div>

  <div class="stat-card stat-gold">
    <div class="stat-header">
      <div class="stat-icon gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12,6 12,12 16,14"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_expiring > 0 ? 'down' : 'flat'; ?>">
        <?php if ($stat_expiring > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
        Within 30 days
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_expiring; ?></div>
    <div class="stat-label">Expiring Soon</div>
  </div>

  <div class="stat-card stat-red">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
      </div>
      <div class="stat-trend flat">Closed</div>
    </div>
    <div class="stat-value"><?php echo $stat_expired + $stat_cancelled; ?></div>
    <div class="stat-label">Expired / Cancelled</div>
  </div>

</div>

<!-- ── Contracts Table ────────────────────────────────────────── -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>Contract Registry</h2>
      <div class="card-head-sub">All delivery contracts with B-Meg plants</div>
    </div>
    <div class="contracts-toolbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="contractSearch"
               placeholder="Search contract no., client, destination…" autocomplete="off" />
      </div>
      <select class="filter-select" id="statusFilter">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="expiring">Expiring Soon</option>
        <option value="expired">Expired</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <table class="data-table contracts-table">
      <thead>
        <tr>
          <th>Contract No.</th>
          <th>Client</th>
          <th>Route</th>
          <th>Assigned Truck</th>
          <th>Period</th>
          <th>Rate / Trip</th>
          <th>Status</th>
          <?php if ($can_manage): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="contractsTableBody">

        <?php if (empty($paged)): ?>
        <tr>
          <td colspan="<?php echo $can_manage ? 8 : 7; ?>" class="contracts-empty-cell">
            <div class="contracts-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14,2 14,8 20,8"/>
              </svg>
              <p>No contracts yet. Click <strong>Add Contract</strong> to create one.</p>
            </div>
          </td>
        </tr>

        <?php else: foreach ($paged as $c):
          $sc  = $status_config[$c['status']] ?? ['pill' => 'pill-navy', 'label' => ucfirst($c['status'])];
          $days= days_remaining($c['end_date']);
        ?>

        <tr data-row
            data-contract="<?php echo e($c['contract_number']); ?>"
            data-client="<?php echo e($c['client_name']); ?>"
            data-destination="<?php echo e($c['destination']); ?>"
            data-status="<?php echo e($c['status']); ?>">

          <!-- Contract number -->
          <td data-label="Contract No.">
            <span class="contract-num"><?php echo e($c['contract_number']); ?></span>
            <?php if ($c['created_by_name']): ?>
            <span class="contract-creator">by <?php echo e($c['created_by_name']); ?></span>
            <?php endif; ?>
          </td>

          <!-- Client -->
          <td data-label="Client">
            <span class="contract-client"><?php echo e($c['client_name']); ?></span>
          </td>

          <!-- Route -->
          <td data-label="Route">
            <div class="contract-route">
              <span class="route-origin"><?php echo e($c['origin']); ?></span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="12" height="12">
                <line x1="5" y1="12" x2="19" y2="12"/>
                <polyline points="12,5 19,12 12,19"/>
              </svg>
              <span class="route-dest"><?php echo e($c['destination']); ?></span>
            </div>
          </td>

          <!-- Truck -->
          <td data-label="Assigned Truck">
            <?php if ($c['truck_plate']): ?>
              <span class="plate-badge"><?php echo e($c['truck_plate']); ?></span>
              <span class="truck-model-small"><?php echo e($c['truck_make'] . ' ' . $c['truck_model']); ?></span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Period -->
          <td data-label="Period">
            <div class="contract-period">
              <span><?php echo fmt_date($c['start_date']); ?></span>
              <span class="period-sep">to</span>
              <span><?php echo fmt_date($c['end_date']); ?></span>
            </div>
            <?php if ($c['status'] === 'expiring' && $days > 0): ?>
              <span class="expiry-warn">⚠ <?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?> left</span>
            <?php elseif ($c['status'] === 'expired'): ?>
              <span class="expiry-past">Ended <?php echo fmt_date($c['end_date']); ?></span>
            <?php endif; ?>
          </td>

          <!-- Rate -->
          <td data-label="Rate / Trip">
            <?php if ($c['rate_per_trip'] !== null): ?>
              <span class="contract-rate">
                ₱<?php echo number_format((float)$c['rate_per_trip'], 2); ?>
              </span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Status -->
          <td data-label="Status">
            <span class="pill <?php echo $sc['pill']; ?>"><?php echo $sc['label']; ?></span>
          </td>

          <?php if ($can_manage): ?>
          <td data-label="Actions">
            <div class="row-actions">

              <!-- Edit -->
              <button type="button" class="btn btn-outline btn-sm btn-edit-contract"
                title="Edit contract"
                data-id="<?php echo (int)$c['id']; ?>"
                data-contract-number="<?php echo e($c['contract_number']); ?>"
                data-client-name="<?php echo e($c['client_name']); ?>"
                data-destination="<?php echo e($c['destination']); ?>"
                data-origin="<?php echo e($c['origin']); ?>"
                data-truck-id="<?php echo (int)($c['truck_id'] ?? 0); ?>"
                data-start-date="<?php echo e($c['start_date']); ?>"
                data-end-date="<?php echo e($c['end_date']); ?>"
                data-rate="<?php echo e($c['rate_per_trip'] ?? ''); ?>"
                data-status="<?php echo e($c['status']); ?>"
                data-notes="<?php echo e($c['notes'] ?? ''); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
              </button>

              <?php if (is_admin() && $c['status'] !== 'cancelled'): ?>
              <!-- Cancel (soft) -->
              <button type="button" class="btn btn-outline btn-sm btn-cancel-contract"
                title="Cancel contract"
                data-id="<?php echo (int)$c['id']; ?>"
                data-contract-number="<?php echo e($c['contract_number']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="15" y1="9" x2="9" y2="15"/>
                  <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                Cancel
              </button>
              <?php endif; ?>

              <?php if (is_admin()): ?>
              <!-- Delete (hard) -->
              <button type="button" class="btn btn-outline btn-sm btn-delete-contract"
                title="Delete contract"
                data-id="<?php echo (int)$c['id']; ?>"
                data-contract-number="<?php echo e($c['contract_number']); ?>">
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

  <!-- Empty state (JS filter) -->
  <div id="emptyState" class="contracts-empty" style="display:none;padding:48px 20px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14,2 14,8 20,8"/>
    </svg>
    <p>No contracts match your search or filter.</p>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1 || $stat_total > 0): ?>
  <div class="card-foot contracts-pagination">
    <span class="pagination-info" id="countInfo">
      <?php if ($stat_total === 0): ?>No contracts found<?php else: ?>
        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $stat_total); ?>
        of <?php echo $stat_total; ?> contract<?php echo $stat_total !== 1 ? 's' : ''; ?>
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


<?php if ($can_manage): ?>
<!-- ── Hidden action forms ────────────────────────────────────── -->
<form id="cancelForm" method="POST" action="contracts_handler.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action"     value="cancel">
  <input type="hidden" name="id"         id="cancelFormId">
</form>

<form id="deleteForm" method="POST" action="contracts_handler.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action"     value="delete">
  <input type="hidden" name="id"         id="deleteFormId">
</form>

<!-- ══════════════════════════════════════════════════════════
     ADD CONTRACT MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="addContractModal">
  <div class="itc-modal itc-modal-lg" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">New Contract</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="contracts_handler.php" id="addContractForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="create">

      <div class="itc-modal-body">
        <div class="form-grid">

          <!-- Contract Number -->
          <div class="form-group">
            <label class="form-label" for="a_contract_number">Contract No. <span class="req">*</span></label>
            <input class="form-control <?php echo ($modal_reopen==='add' && ($form_data['contract_number']??'')==='') ? 'is-error':''; ?>"
                   id="a_contract_number" name="contract_number" type="text"
                   placeholder="e.g. CTR-2026-001" maxlength="60" style="text-transform:uppercase"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['contract_number']??'') : ''; ?>" />
            <span class="form-hint">Unique reference number for this contract</span>
          </div>

          <!-- Status -->
          <div class="form-group">
            <label class="form-label" for="a_status">Status</label>
            <select class="form-control" id="a_status" name="status">
              <?php foreach (['active'=>'Active','expiring'=>'Expiring Soon','expired'=>'Expired','cancelled'=>'Cancelled'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>"
                <?php echo ($modal_reopen==='add' && ($form_data['status']??'active')===$v) ? 'selected':''; ?>>
                <?php echo $l; ?>
              </option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Auto-updated based on end date</span>
          </div>

          <!-- Client Name -->
          <div class="form-group full">
            <label class="form-label" for="a_client_name">Client / Plant Name <span class="req">*</span></label>
            <input class="form-control" id="a_client_name" name="client_name" type="text"
                   placeholder="e.g. B-Meg Feeds Corp. — Tarlac Plant" maxlength="150"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['client_name']??'') : ''; ?>" />
          </div>

          <!-- Origin -->
          <div class="form-group">
            <label class="form-label" for="a_origin">Origin / Pickup <span class="req">*</span></label>
            <input class="form-control" id="a_origin" name="origin" type="text"
                   placeholder="e.g. Bataan" maxlength="150"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['origin']??'Bataan') : 'Bataan'; ?>" />
          </div>

          <!-- Destination -->
          <div class="form-group">
            <label class="form-label" for="a_destination">Destination <span class="req">*</span></label>
            <input class="form-control" id="a_destination" name="destination" type="text"
                   placeholder="e.g. Tarlac" maxlength="150"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['destination']??'') : ''; ?>" />
          </div>

          <!-- Assigned Truck -->
          <div class="form-group">
            <label class="form-label" for="a_truck_id">Assigned Truck</label>
            <select class="form-control" id="a_truck_id" name="truck_id">
              <option value="">— Unassigned —</option>
              <?php foreach ($all_trucks as $tk):
                $sel = ($modal_reopen==='add' && (int)($form_data['truck_id']??0) === (int)$tk['id']) ? 'selected':''; ?>
              <option value="<?php echo (int)$tk['id']; ?>" <?php echo $sel; ?>>
                <?php echo e($tk['plate_number']); ?> — <?php echo e($tk['make'] . ' ' . $tk['model']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Rate per trip -->
          <div class="form-group">
            <label class="form-label" for="a_rate">Rate per Trip (₱)</label>
            <input class="form-control" id="a_rate" name="rate_per_trip" type="number"
                   step="0.01" min="0" placeholder="e.g. 5000.00"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['rate_per_trip']??'') : ''; ?>" />
          </div>

          <!-- Start Date -->
          <div class="form-group">
            <label class="form-label" for="a_start_date">Start Date <span class="req">*</span></label>
            <input class="form-control" id="a_start_date" name="start_date" type="date"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['start_date']??'') : date('Y-m-d'); ?>" />
          </div>

          <!-- End Date -->
          <div class="form-group">
            <label class="form-label" for="a_end_date">End Date <span class="req">*</span></label>
            <input class="form-control" id="a_end_date" name="end_date" type="date"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['end_date']??'') : ''; ?>" />
            <span class="form-hint">Status auto-updates: Expiring = within 30 days</span>
          </div>

          <!-- Notes -->
          <div class="form-group full">
            <label class="form-label" for="a_notes">Notes / Terms</label>
            <textarea class="form-control" id="a_notes" name="notes" rows="3"
                      placeholder="Additional terms, remarks, or delivery conditions…" maxlength="1000"
              ><?php echo $modal_reopen==='add' ? e($form_data['notes']??'') : ''; ?></textarea>
          </div>

        </div>
      </div>

      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Create Contract</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT CONTRACT MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="editContractModal">
  <div class="itc-modal itc-modal-lg" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Edit Contract</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="contracts_handler.php">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="update">
      <input type="hidden" name="id"         id="edit_id"
             value="<?php echo $modal_reopen==='edit' ? (int)($form_data['id']??0) : ''; ?>">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group">
            <label class="form-label" for="edit_contract_number">Contract No. <span class="req">*</span></label>
            <input class="form-control" id="edit_contract_number" name="contract_number" type="text"
                   placeholder="e.g. CTR-2026-001" maxlength="60" style="text-transform:uppercase"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['contract_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_status">Status</label>
            <select class="form-control" id="edit_status" name="status">
              <?php foreach (['active'=>'Active','expiring'=>'Expiring Soon','expired'=>'Expired','cancelled'=>'Cancelled'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>"
                <?php echo ($modal_reopen==='edit' && ($form_data['status']??'')===$v) ? 'selected':''; ?>>
                <?php echo $l; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_client_name">Client / Plant Name <span class="req">*</span></label>
            <input class="form-control" id="edit_client_name" name="client_name" type="text"
                   placeholder="e.g. B-Meg Feeds Corp. — Tarlac Plant" maxlength="150"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['client_name']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_origin">Origin / Pickup <span class="req">*</span></label>
            <input class="form-control" id="edit_origin" name="origin" type="text"
                   placeholder="e.g. Bataan" maxlength="150"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['origin']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_destination">Destination <span class="req">*</span></label>
            <input class="form-control" id="edit_destination" name="destination" type="text"
                   placeholder="e.g. Tarlac" maxlength="150"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['destination']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_truck_id">Assigned Truck</label>
            <select class="form-control" id="edit_truck_id" name="truck_id">
              <option value="">— Unassigned —</option>
              <?php foreach ($all_trucks as $tk):
                $sel = ($modal_reopen==='edit' && (int)($form_data['truck_id']??0) === (int)$tk['id']) ? 'selected':''; ?>
              <option value="<?php echo (int)$tk['id']; ?>" <?php echo $sel; ?>>
                <?php echo e($tk['plate_number']); ?> — <?php echo e($tk['make'] . ' ' . $tk['model']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_rate">Rate per Trip (₱)</label>
            <input class="form-control" id="edit_rate" name="rate_per_trip" type="number"
                   step="0.01" min="0" placeholder="e.g. 5000.00"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['rate_per_trip']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_start_date">Start Date <span class="req">*</span></label>
            <input class="form-control" id="edit_start_date" name="start_date" type="date"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['start_date']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_end_date">End Date <span class="req">*</span></label>
            <input class="form-control" id="edit_end_date" name="end_date" type="date"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['end_date']??'') : ''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_notes">Notes / Terms</label>
            <textarea class="form-control" id="edit_notes" name="notes" rows="3"
                      placeholder="Additional terms, remarks, or delivery conditions…" maxlength="1000"
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

<?php if ($modal_reopen): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('<?php echo $modal_reopen === 'edit' ? 'editContractModal' : 'addContractModal'; ?>')
      ?.classList.add('open');
  });
</script>
<?php endif; ?>

<script src="/assets/js/contracts.js"></script>
<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>