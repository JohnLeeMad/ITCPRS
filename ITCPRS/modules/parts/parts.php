<?php
/**
 * modules/parts/parts.php
 * ─────────────────────────────────────────────────────────────
 * Parts Requests — display page.
 * Admin, secretary, staff can view + manage.
 * All mutations POST to parts_handler.php.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary', 'staff']);

$page_title  = 'Parts Requests';
$active_menu = 'parts';

// ── Consume session flash ─────────────────────────────────────
$flash_type    = $_SESSION['flash_type']    ?? '';
$flash_message = $_SESSION['flash_message'] ?? '';
$modal_reopen  = $_SESSION['modal_reopen']  ?? '';
$form_data     = $_SESSION['form_data']     ?? [];
unset($_SESSION['flash_type'], $_SESSION['flash_message'],
      $_SESSION['modal_reopen'], $_SESSION['form_data']);

$current_uid = (int) ($_SESSION['user']['id'] ?? 0);

// ── Helpers ───────────────────────────────────────────────────
function e(mixed $v): string {
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function fmt_dt(?string $dt): string {
  return $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
}
function fmt_date(?string $dt): string {
  return $dt ? date('M j, Y', strtotime($dt)) : '—';
}

// ── Fetch trucks (for dropdown) ────────────────────────────────
$all_trucks = [];
$tr = mysqli_query($conn,
  "SELECT id, plate_number, make, model FROM trucks ORDER BY plate_number ASC"
);
if ($tr) { while ($r = mysqli_fetch_assoc($tr)) $all_trucks[] = $r; mysqli_free_result($tr); }

// ── Fetch inventory items (for optional link dropdown) ─────────
$all_inventory = [];
$inv = mysqli_query($conn,
  "SELECT id, part_name, part_number, unit FROM inventory_items ORDER BY part_name ASC"
);
if ($inv) { while ($r = mysqli_fetch_assoc($inv)) $all_inventory[] = $r; mysqli_free_result($inv); }

// ── Fetch all requests ─────────────────────────────────────────
$requests = [];
$result   = mysqli_query($conn,
  "SELECT pr.id, pr.part_name, pr.quantity, pr.unit,
          pr.urgency, pr.status, pr.notes,
          pr.created_at, pr.updated_at, pr.resolved_at,
          t.plate_number AS truck_plate, t.make AS truck_make, t.model AS truck_model,
          u1.full_name AS requested_by_name,
          u2.full_name AS resolved_by_name,
          ii.part_name AS inv_part_name
   FROM parts_requests pr
   LEFT JOIN trucks          t   ON t.id   = pr.truck_id
   LEFT JOIN users           u1  ON u1.id  = pr.requested_by
   LEFT JOIN users           u2  ON u2.id  = pr.resolved_by
   LEFT JOIN inventory_items ii  ON ii.id  = pr.inventory_item_id
   ORDER BY
     FIELD(pr.urgency,'urgent','normal','low'),
     FIELD(pr.status,'pending','sourcing','ordered','installed','cancelled'),
     pr.created_at DESC"
);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) $requests[] = $row;
  mysqli_free_result($result);
}

// Stats
$stat_total     = count($requests);
$stat_pending   = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$stat_sourcing  = count(array_filter($requests, fn($r) => $r['status'] === 'sourcing'));
$stat_ordered   = count(array_filter($requests, fn($r) => $r['status'] === 'ordered'));
$stat_installed = count(array_filter($requests, fn($r) => $r['status'] === 'installed'));
$stat_urgent    = count(array_filter($requests, fn($r) => $r['urgency'] === 'urgent' && $r['status'] !== 'installed' && $r['status'] !== 'cancelled'));

// Pagination
$per_page     = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_pages  = max(1, (int)ceil($stat_total / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paged        = array_slice($requests, $offset, $per_page);

// Config maps
$status_cfg = [
  'pending'   => ['pill' => 'pill-amber', 'label' => 'Pending'],
  'sourcing'  => ['pill' => 'pill-blue',  'label' => 'Sourcing'],
  'ordered'   => ['pill' => 'pill-navy',  'label' => 'Ordered'],
  'installed' => ['pill' => 'pill-green', 'label' => 'Installed'],
  'cancelled' => ['pill' => 'pill-red',   'label' => 'Cancelled'],
];
$urgency_cfg = [
  'low'    => ['cls' => 'urgency-low',    'label' => 'Low'],
  'normal' => ['cls' => 'urgency-normal', 'label' => 'Normal'],
  'urgent' => ['cls' => 'urgency-urgent', 'label' => 'Urgent'],
];

require_once __DIR__ . '/../../includes/admin_nav.php';
?>

<link rel="stylesheet" href="/assets/css/parts.css" />

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
    <div class="page-header-eyebrow">Maintenance</div>
    <h1>Parts Requests</h1>
    <div class="page-header-sub">
      <?php echo $stat_total; ?> request<?php echo $stat_total !== 1 ? 's' : ''; ?> total
      <?php if ($stat_urgent > 0): ?>&mdash; <strong class="urgent-count"><?php echo $stat_urgent; ?> urgent</strong><?php endif; ?>
    </div>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-gold" id="addPartsBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      New Request
    </button>
  </div>
</div>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<div class="stats-grid parts-stats-grid">

  <div class="stat-card stat-navy">
    <div class="stat-header">
      <div class="stat-icon navy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
      </div>
      <div class="stat-trend flat">All</div>
    </div>
    <div class="stat-value"><?php echo $stat_total; ?></div>
    <div class="stat-label">Total Requests</div>
  </div>

  <div class="stat-card stat-red">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_urgent > 0 ? 'down' : 'flat'; ?>">
        <?php if ($stat_urgent > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
        Needs attention
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_urgent; ?></div>
    <div class="stat-label">Urgent</div>
  </div>

  <div class="stat-card stat-gold">
    <div class="stat-header">
      <div class="stat-icon gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12,6 12,12 16,14"/>
        </svg>
      </div>
      <div class="stat-trend flat">In progress</div>
    </div>
    <div class="stat-value"><?php echo $stat_pending + $stat_sourcing + $stat_ordered; ?></div>
    <div class="stat-label">Open</div>
  </div>

  <div class="stat-card stat-green">
    <div class="stat-header">
      <div class="stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22,4 12,14.01 9,11.01"/>
        </svg>
      </div>
      <div class="stat-trend up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18,15 12,9 6,15"/></svg> Done</div>
    </div>
    <div class="stat-value"><?php echo $stat_installed; ?></div>
    <div class="stat-label">Installed</div>
  </div>

</div>

<!-- ── Table ──────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>Request Log</h2>
      <div class="card-head-sub">Sorted by urgency then status</div>
    </div>
    <div class="parts-toolbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="partsSearch" placeholder="Search part, truck…" autocomplete="off" />
      </div>
      <select class="filter-select" id="urgencyFilter">
        <option value="">All Urgencies</option>
        <option value="urgent">Urgent</option>
        <option value="normal">Normal</option>
        <option value="low">Low</option>
      </select>
      <select class="filter-select" id="statusFilter">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="sourcing">Sourcing</option>
        <option value="ordered">Ordered</option>
        <option value="installed">Installed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <table class="data-table parts-table">
      <thead>
        <tr>
          <th>Part</th>
          <th>Qty</th>
          <th>Truck</th>
          <th>Requested By</th>
          <th>Urgency</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="partsTableBody">

        <?php if (empty($paged)): ?>
        <tr><td colspan="8" class="parts-empty-cell">
          <div class="parts-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
              <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
            <p>No parts requests yet. Click <strong>New Request</strong> to add one.</p>
          </div>
        </td></tr>

        <?php else: foreach ($paged as $r):
          $sc = $status_cfg[$r['status']]   ?? ['pill'=>'pill-navy','label'=>ucfirst($r['status'])];
          $uc = $urgency_cfg[$r['urgency']] ?? ['cls'=>'urgency-normal','label'=>ucfirst($r['urgency'])];
        ?>

        <tr data-row
            data-part="<?php echo e($r['part_name']); ?>"
            data-truck="<?php echo e($r['truck_plate'] ?? ''); ?>"
            data-urgency="<?php echo e($r['urgency']); ?>"
            data-status="<?php echo e($r['status']); ?>">

          <td data-label="Part">
            <span class="part-name"><?php echo e($r['part_name']); ?></span>
            <?php if ($r['inv_part_name']): ?>
            <span class="part-inv-link">↔ <?php echo e($r['inv_part_name']); ?></span>
            <?php endif; ?>
            <?php if ($r['notes']): ?>
            <span class="part-notes"><?php echo e(mb_strimwidth($r['notes'], 0, 60, '…')); ?></span>
            <?php endif; ?>
          </td>

          <td data-label="Qty">
            <span class="part-qty"><?php echo e(number_format((float)$r['quantity'], 0)); ?></span>
            <span class="part-unit"><?php echo e($r['unit']); ?></span>
          </td>

          <td data-label="Truck">
            <?php if ($r['truck_plate']): ?>
              <span class="plate-badge"><?php echo e($r['truck_plate']); ?></span>
              <span class="truck-model-small"><?php echo e($r['truck_make'] . ' ' . $r['truck_model']); ?></span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <td data-label="Requested By">
            <span class="req-by"><?php echo $r['requested_by_name'] ? e($r['requested_by_name']) : '<span class="muted">—</span>'; ?></span>
          </td>

          <td data-label="Urgency">
            <span class="urgency-badge <?php echo $uc['cls']; ?>"><?php echo $uc['label']; ?></span>
          </td>

          <td data-label="Status">
            <span class="pill <?php echo $sc['pill']; ?>"><?php echo $sc['label']; ?></span>
            <?php if ($r['status'] === 'installed' && $r['resolved_by_name']): ?>
            <span class="resolved-by">by <?php echo e($r['resolved_by_name']); ?></span>
            <?php endif; ?>
          </td>

          <td data-label="Date" class="date-cell">
            <?php echo fmt_date($r['created_at']); ?>
          </td>

          <td data-label="Actions">
            <div class="row-actions">
              <button type="button" class="btn btn-outline btn-sm btn-edit-parts"
                title="Edit request"
                data-id="<?php echo (int)$r['id']; ?>"
                data-part-name="<?php echo e($r['part_name']); ?>"
                data-quantity="<?php echo e($r['quantity']); ?>"
                data-unit="<?php echo e($r['unit']); ?>"
                data-inventory-item-id="<?php echo (int)($r['inventory_item_id'] ?? 0); ?>"
                data-truck-id="<?php echo (int)($r['truck_id'] ?? 0); ?>"
                data-urgency="<?php echo e($r['urgency']); ?>"
                data-status="<?php echo e($r['status']); ?>"
                data-notes="<?php echo e($r['notes'] ?? ''); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
              </button>

              <?php if (is_admin()): ?>
              <button type="button" class="btn btn-outline btn-sm btn-delete-parts"
                title="Delete request"
                data-id="<?php echo (int)$r['id']; ?>"
                data-part-name="<?php echo e($r['part_name']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <polyline points="3,6 5,6 21,6"/><path d="M19 6l-1 14H6L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
                Delete
              </button>
              <?php endif; ?>
            </div>
          </td>

        </tr>
        <?php endforeach; endif; ?>

      </tbody>
    </table>
  </div>

  <div id="emptyState" class="parts-empty" style="display:none;padding:48px 20px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
      <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
    </svg>
    <p>No requests match your search or filter.</p>
  </div>

  <?php if ($total_pages > 1 || $stat_total > 0): ?>
  <div class="card-foot parts-pagination">
    <span class="pagination-info" id="countInfo">
      <?php if ($stat_total === 0): ?>No requests<?php else: ?>
        Showing <?php echo $offset+1; ?>–<?php echo min($offset+$per_page,$stat_total); ?>
        of <?php echo $stat_total; ?> request<?php echo $stat_total!==1?'s':''; ?>
      <?php endif; ?>
    </span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination-controls">
      <?php if ($current_page > 1): ?>
        <a class="page-btn" href="?page=<?php echo $current_page-1; ?>"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg></a>
      <?php else: ?><span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg></span><?php endif; ?>
      <?php for ($p=1;$p<=$total_pages;$p++): ?>
        <a class="page-btn <?php echo $p===$current_page?'active':''; ?>" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
      <?php if ($current_page < $total_pages): ?>
        <a class="page-btn" href="?page=<?php echo $current_page+1; ?>"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg></a>
      <?php else: ?><span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg></span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Hidden delete form -->
<form id="deleteForm" method="POST" action="parts_handler.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteFormId">
</form>

<!-- ══════════════════════════════════════════════════════════
     ADD REQUEST MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="addPartsModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">New Parts Request</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="parts_handler.php" id="addPartsForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="create">
      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="a_part_name">Part Name <span class="req">*</span></label>
            <input class="form-control <?php echo ($modal_reopen==='add'&&($form_data['part_name']??'')==='')? 'is-error':''; ?>"
                   id="a_part_name" name="part_name" type="text"
                   placeholder="e.g. Rear Tire 700R, Battery 12V" maxlength="150"
                   value="<?php echo $modal_reopen==='add'?e($form_data['part_name']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_quantity">Quantity <span class="req">*</span></label>
            <input class="form-control" id="a_quantity" name="quantity" type="number"
                   step="1" min="1" placeholder="1"
                   value="<?php echo $modal_reopen==='add'?e($form_data['quantity']??'1'):'1'; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_unit">Unit</label>
            <input class="form-control" id="a_unit" name="unit" type="text"
                   placeholder="pcs, sets, liters…" maxlength="30"
                   value="<?php echo $modal_reopen==='add'?e($form_data['unit']??'pcs'):'pcs'; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_truck_id">Truck</label>
            <select class="form-control" id="a_truck_id" name="truck_id">
              <option value="">— Not truck-specific —</option>
              <?php foreach ($all_trucks as $tk):
                $sel = ($modal_reopen==='add' && (int)($form_data['truck_id']??0)===(int)$tk['id'])?'selected':''; ?>
              <option value="<?php echo (int)$tk['id']; ?>" <?php echo $sel; ?>>
                <?php echo e($tk['plate_number']); ?> — <?php echo e($tk['make'].' '.$tk['model']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_urgency">Urgency</label>
            <select class="form-control" id="a_urgency" name="urgency">
              <?php foreach (['low'=>'Low','normal'=>'Normal','urgent'=>'Urgent'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='add'&&($form_data['urgency']??'normal')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_status">Status</label>
            <select class="form-control" id="a_status" name="status">
              <?php foreach (['pending'=>'Pending','sourcing'=>'Sourcing','ordered'=>'Ordered','installed'=>'Installed','cancelled'=>'Cancelled'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='add'&&($form_data['status']??'pending')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if (!empty($all_inventory)): ?>
          <div class="form-group full">
            <label class="form-label" for="a_inv_item">Link to Inventory Item</label>
            <select class="form-control" id="a_inv_item" name="inventory_item_id">
              <option value="">— None —</option>
              <?php foreach ($all_inventory as $ii):
                $sel = ($modal_reopen==='add' && (int)($form_data['inventory_item_id']??0)===(int)$ii['id'])?'selected':''; ?>
              <option value="<?php echo (int)$ii['id']; ?>" <?php echo $sel; ?>>
                <?php echo e($ii['part_name']); ?><?php echo $ii['part_number']?' ['.e($ii['part_number']).']':''; ?>
              </option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Optional — link to a matching inventory item</span>
          </div>
          <?php endif; ?>

          <div class="form-group full">
            <label class="form-label" for="a_notes">Notes</label>
            <textarea class="form-control" id="a_notes" name="notes" rows="3"
                      placeholder="Describe the issue, where the part is needed, urgency details…" maxlength="1000"
              ><?php echo $modal_reopen==='add'?e($form_data['notes']??''):''; ?></textarea>
          </div>

        </div>
      </div>
      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT REQUEST MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="editPartsModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Edit Request</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="parts_handler.php">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="update">
      <input type="hidden" name="id"         id="edit_id"
             value="<?php echo $modal_reopen==='edit'?(int)($form_data['id']??0):''; ?>">
      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="edit_part_name">Part Name <span class="req">*</span></label>
            <input class="form-control" id="edit_part_name" name="part_name" type="text"
                   placeholder="e.g. Rear Tire 700R" maxlength="150"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['part_name']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_quantity">Quantity <span class="req">*</span></label>
            <input class="form-control" id="edit_quantity" name="quantity" type="number"
                   step="1" min="1"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['quantity']??'1'):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_unit">Unit</label>
            <input class="form-control" id="edit_unit" name="unit" type="text"
                   placeholder="pcs, sets, liters…" maxlength="30"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['unit']??'pcs'):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_truck_id">Truck</label>
            <select class="form-control" id="edit_truck_id" name="truck_id">
              <option value="">— Not truck-specific —</option>
              <?php foreach ($all_trucks as $tk):
                $sel = ($modal_reopen==='edit' && (int)($form_data['truck_id']??0)===(int)$tk['id'])?'selected':''; ?>
              <option value="<?php echo (int)$tk['id']; ?>" <?php echo $sel; ?>>
                <?php echo e($tk['plate_number']); ?> — <?php echo e($tk['make'].' '.$tk['model']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_urgency">Urgency</label>
            <select class="form-control" id="edit_urgency" name="urgency">
              <?php foreach (['low'=>'Low','normal'=>'Normal','urgent'=>'Urgent'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='edit'&&($form_data['urgency']??'')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_status">Status</label>
            <select class="form-control" id="edit_status" name="status">
              <?php foreach (['pending'=>'Pending','sourcing'=>'Sourcing','ordered'=>'Ordered','installed'=>'Installed','cancelled'=>'Cancelled'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='edit'&&($form_data['status']??'')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if (!empty($all_inventory)): ?>
          <div class="form-group full">
            <label class="form-label" for="edit_inv_item">Link to Inventory Item</label>
            <select class="form-control" id="edit_inv_item" name="inventory_item_id">
              <option value="">— None —</option>
              <?php foreach ($all_inventory as $ii):
                $sel = ($modal_reopen==='edit' && (int)($form_data['inventory_item_id']??0)===(int)$ii['id'])?'selected':''; ?>
              <option value="<?php echo (int)$ii['id']; ?>" <?php echo $sel; ?>>
                <?php echo e($ii['part_name']); ?><?php echo $ii['part_number']?' ['.e($ii['part_number']).']':''; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="form-group full">
            <label class="form-label" for="edit_notes">Notes</label>
            <textarea class="form-control" id="edit_notes" name="notes" rows="3"
                      placeholder="Describe the issue, updates, resolution details…" maxlength="1000"
              ><?php echo $modal_reopen==='edit'?e($form_data['notes']??''):''; ?></textarea>
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

<?php if ($modal_reopen): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('<?php echo $modal_reopen==='edit'?'editPartsModal':'addPartsModal'; ?>')?.classList.add('open');
});
</script>
<?php endif; ?>

<script src="/assets/js/parts.js"></script>
<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>