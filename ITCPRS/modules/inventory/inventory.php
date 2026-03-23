<?php
/**
 * modules/inventory/inventory.php
 * ─────────────────────────────────────────────────────────────
 * Inventory — garage spare parts stock management.
 * Admin + secretary + staff can view/add/edit.
 * All mutations POST to inventory_handler.php.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary', 'staff']);

$page_title  = 'Inventory';
$active_menu = 'inventory';

// ── Consume session flash ─────────────────────────────────────
$flash_type    = $_SESSION['flash_type']    ?? '';
$flash_message = $_SESSION['flash_message'] ?? '';
$modal_reopen  = $_SESSION['modal_reopen']  ?? '';
$form_data     = $_SESSION['form_data']     ?? [];
unset($_SESSION['flash_type'], $_SESSION['flash_message'],
      $_SESSION['modal_reopen'], $_SESSION['form_data']);

// ── Helpers ───────────────────────────────────────────────────
function e(mixed $v): string {
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function fmt_qty(mixed $qty): string {
  $f = (float)$qty;
  return $f == (int)$f ? number_format((int)$f) : number_format($f, 2);
}

// ── Category config ───────────────────────────────────────────
$categories = [
  'tires'        => 'Tires',
  'batteries'    => 'Batteries',
  'engine'       => 'Engine',
  'transmission' => 'Transmission',
  'electrical'   => 'Electrical',
  'brakes'       => 'Brakes',
  'suspension'   => 'Suspension',
  'filters'      => 'Filters',
  'fluids'       => 'Fluids',
  'body'         => 'Body Parts',
  'tools'        => 'Tools',
  'other'        => 'Other',
];

// ── Fetch all inventory items ──────────────────────────────────
$items  = [];
$result = mysqli_query($conn,
  "SELECT id, part_name, part_number, category, unit,
          quantity, reorder_point, unit_cost, supplier, location, notes,
          updated_at
   FROM inventory_items
   ORDER BY
     CASE WHEN quantity <= reorder_point AND reorder_point > 0 THEN 0 ELSE 1 END,
     category, part_name ASC"
);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
  mysqli_free_result($result);
}

// Stats
$stat_total      = count($items);
$stat_low_stock  = count(array_filter($items, fn($i) => (float)$i['reorder_point'] > 0 && (float)$i['quantity'] <= (float)$i['reorder_point']));
$stat_zero       = count(array_filter($items, fn($i) => (float)$i['quantity'] == 0));
$stat_categories = count(array_unique(array_column($items, 'category')));

// Total inventory value
$stat_value = array_reduce($items, fn($carry, $i) =>
  $carry + ((float)($i['unit_cost'] ?? 0) * (float)$i['quantity']), 0.0
);

// Pagination
$per_page     = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_pages  = max(1, (int)ceil($stat_total / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paged        = array_slice($items, $offset, $per_page);

require_once __DIR__ . '/../../includes/admin_nav.php';
?>

<link rel="stylesheet" href="/assets/css/inventory.css" />

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
    <h1>Inventory</h1>
    <div class="page-header-sub">
      <?php echo $stat_total; ?> item<?php echo $stat_total !== 1 ? 's' : ''; ?> in stock
      <?php if ($stat_low_stock > 0): ?>&mdash; <strong class="low-stock-count"><?php echo $stat_low_stock; ?> low stock</strong><?php endif; ?>
    </div>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-gold" id="addItemBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add Item
    </button>
  </div>
</div>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<div class="stats-grid inv-stats-grid">

  <div class="stat-card stat-navy">
    <div class="stat-header">
      <div class="stat-icon navy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          <polyline points="3.27,6.96 12,12.01 20.73,6.96"/>
          <line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
      </div>
      <div class="stat-trend flat"><?php echo $stat_categories; ?> categor<?php echo $stat_categories !== 1 ? 'ies' : 'y'; ?></div>
    </div>
    <div class="stat-value"><?php echo $stat_total; ?></div>
    <div class="stat-label">Total Items</div>
  </div>

  <div class="stat-card stat-red">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <polyline points="23,18 13.5,8.5 8.5,13.5 1,6"/>
          <polyline points="17,18 23,18 23,12"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_low_stock > 0 ? 'down' : 'flat'; ?>">
        <?php if ($stat_low_stock > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
        Needs reorder
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_low_stock; ?></div>
    <div class="stat-label">Low Stock</div>
  </div>

  <div class="stat-card stat-gold">
    <div class="stat-header">
      <div class="stat-icon gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_zero > 0 ? 'down' : 'flat'; ?>">
        <?php if ($stat_zero > 0): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg><?php endif; ?>
        Out of stock
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_zero; ?></div>
    <div class="stat-label">Zero Stock</div>
  </div>

  <div class="stat-card stat-green">
    <div class="stat-header">
      <div class="stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
      </div>
      <div class="stat-trend flat">Est. value</div>
    </div>
    <div class="stat-value inv-value">₱<?php echo number_format($stat_value, 0); ?></div>
    <div class="stat-label">Stock Value</div>
  </div>

</div>

<!-- ── Inventory Table ────────────────────────────────────────── -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>Stock Registry</h2>
      <div class="card-head-sub">Items sorted by low-stock first, then category</div>
    </div>
    <div class="inv-toolbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="invSearch" placeholder="Search part name, number, supplier…" autocomplete="off" />
      </div>
      <select class="filter-select" id="categoryFilter">
        <option value="">All Categories</option>
        <?php foreach ($categories as $v => $l): ?>
        <option value="<?php echo $v; ?>"><?php echo $l; ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" id="stockFilter">
        <option value="">All Stock Levels</option>
        <option value="low">Low / Zero Stock</option>
        <option value="ok">Sufficient Stock</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <table class="data-table inv-table">
      <thead>
        <tr>
          <th>Part</th>
          <th>Category</th>
          <th>Stock</th>
          <th>Reorder At</th>
          <th>Unit Cost</th>
          <th>Supplier</th>
          <th>Location</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="invTableBody">

        <?php if (empty($paged)): ?>
        <tr><td colspan="9" class="inv-empty-cell">
          <div class="inv-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
              <polyline points="3.27,6.96 12,12.01 20.73,6.96"/>
              <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
            <p>No inventory items yet. Click <strong>Add Item</strong> to get started.</p>
          </div>
        </td></tr>

        <?php else: foreach ($paged as $item):
          $qty       = (float)$item['quantity'];
          $reorder   = (float)$item['reorder_point'];
          $is_low    = $reorder > 0 && $qty <= $reorder;
          $is_zero   = $qty == 0;
          $stock_cls = $is_zero ? 'stock-zero' : ($is_low ? 'stock-low' : 'stock-ok');
          $cat_label = $categories[$item['category']] ?? ucfirst($item['category']);
          $updated   = $item['updated_at'] ? date('M j, Y', strtotime($item['updated_at'])) : '—';
        ?>

        <tr data-row
            data-part="<?php echo e($item['part_name']); ?>"
            data-partnum="<?php echo e($item['part_number'] ?? ''); ?>"
            data-supplier="<?php echo e($item['supplier'] ?? ''); ?>"
            data-category="<?php echo e($item['category']); ?>"
            data-stock="<?php echo $is_low || $is_zero ? 'low' : 'ok'; ?>"
            <?php if ($is_low): ?>class="row-low-stock"<?php endif; ?>>

          <!-- Part name + number -->
          <td data-label="Part">
            <span class="inv-part-name"><?php echo e($item['part_name']); ?></span>
            <?php if ($item['part_number']): ?>
            <span class="inv-part-num"><?php echo e($item['part_number']); ?></span>
            <?php endif; ?>
            <?php if ($item['notes']): ?>
            <span class="inv-notes"><?php echo e(mb_strimwidth($item['notes'], 0, 50, '…')); ?></span>
            <?php endif; ?>
          </td>

          <!-- Category -->
          <td data-label="Category">
            <span class="cat-badge cat-<?php echo e($item['category']); ?>"><?php echo e($cat_label); ?></span>
          </td>

          <!-- Stock level with quick adjust buttons -->
          <td data-label="Stock">
            <div class="stock-cell">
              <div class="stock-adjust-wrap">
                <form method="POST" action="inventory_handler.php" class="stock-adjust-form">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action"     value="adjust_stock">
                  <input type="hidden" name="id"         value="<?php echo (int)$item['id']; ?>">
                  <input type="hidden" name="delta"      value="-1">
                  <button type="submit" class="stock-btn stock-minus" title="Remove 1">−</button>
                </form>
                <span class="stock-qty <?php echo $stock_cls; ?>">
                  <?php echo fmt_qty($item['quantity']); ?>
                  <span class="stock-unit"><?php echo e($item['unit']); ?></span>
                </span>
                <form method="POST" action="inventory_handler.php" class="stock-adjust-form">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action"     value="adjust_stock">
                  <input type="hidden" name="id"         value="<?php echo (int)$item['id']; ?>">
                  <input type="hidden" name="delta"      value="1">
                  <button type="submit" class="stock-btn stock-plus" title="Add 1">+</button>
                </form>
              </div>
              <?php if ($is_zero): ?>
                <span class="stock-warning stock-warning-zero">Out of stock</span>
              <?php elseif ($is_low): ?>
                <span class="stock-warning stock-warning-low">Low — reorder</span>
              <?php endif; ?>
            </div>
          </td>

          <!-- Reorder point -->
          <td data-label="Reorder At">
            <?php if ($reorder > 0): ?>
              <span class="reorder-pt"><?php echo fmt_qty($item['reorder_point']); ?> <?php echo e($item['unit']); ?></span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Unit cost -->
          <td data-label="Unit Cost">
            <?php if ($item['unit_cost'] !== null && $item['unit_cost'] !== ''): ?>
              <span class="inv-cost">₱<?php echo number_format((float)$item['unit_cost'], 2); ?></span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Supplier -->
          <td data-label="Supplier">
            <?php echo $item['supplier'] ? e($item['supplier']) : '<span class="muted">—</span>'; ?>
          </td>

          <!-- Location -->
          <td data-label="Location">
            <?php if ($item['location']): ?>
              <span class="inv-location">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="11" height="11">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                <?php echo e($item['location']); ?>
              </span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Last updated -->
          <td data-label="Updated" class="date-cell"><?php echo $updated; ?></td>

          <!-- Actions -->
          <td data-label="Actions">
            <div class="row-actions">
              <button type="button" class="btn btn-outline btn-sm btn-edit-item"
                title="Edit item"
                data-id="<?php echo (int)$item['id']; ?>"
                data-part-name="<?php echo e($item['part_name']); ?>"
                data-part-number="<?php echo e($item['part_number'] ?? ''); ?>"
                data-category="<?php echo e($item['category']); ?>"
                data-unit="<?php echo e($item['unit']); ?>"
                data-quantity="<?php echo e($item['quantity']); ?>"
                data-reorder-point="<?php echo e($item['reorder_point']); ?>"
                data-unit-cost="<?php echo e($item['unit_cost'] ?? ''); ?>"
                data-supplier="<?php echo e($item['supplier'] ?? ''); ?>"
                data-location="<?php echo e($item['location'] ?? ''); ?>"
                data-notes="<?php echo e($item['notes'] ?? ''); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
              </button>

              <?php if (is_admin()): ?>
              <button type="button" class="btn btn-outline btn-sm btn-delete-item"
                title="Delete item"
                data-id="<?php echo (int)$item['id']; ?>"
                data-part-name="<?php echo e($item['part_name']); ?>">
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

  <div id="emptyState" class="inv-empty" style="display:none;padding:48px 20px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
    </svg>
    <p>No items match your search or filter.</p>
  </div>

  <?php if ($total_pages > 1 || $stat_total > 0): ?>
  <div class="card-foot inv-pagination">
    <span class="pagination-info" id="countInfo">
      <?php if ($stat_total === 0): ?>No items<?php else: ?>
        Showing <?php echo $offset+1; ?>–<?php echo min($offset+$per_page,$stat_total); ?>
        of <?php echo $stat_total; ?> item<?php echo $stat_total!==1?'s':''; ?>
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
<form id="deleteForm" method="POST" action="inventory_handler.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteFormId">
</form>

<!-- ══════════════════════════════════════════════════════════
     ADD ITEM MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="addItemModal">
  <div class="itc-modal itc-modal-lg" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Add Inventory Item</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="inventory_handler.php" id="addItemForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="create">
      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="a_part_name">Part Name <span class="req">*</span></label>
            <input class="form-control <?php echo ($modal_reopen==='add'&&($form_data['part_name']??'')==='')? 'is-error':''; ?>"
                   id="a_part_name" name="part_name" type="text"
                   placeholder="e.g. Rear Tire 700R" maxlength="150"
                   value="<?php echo $modal_reopen==='add'?e($form_data['part_name']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_part_number">Part Number / SKU</label>
            <input class="form-control" id="a_part_number" name="part_number" type="text"
                   placeholder="e.g. TY-700R-14" maxlength="80"
                   value="<?php echo $modal_reopen==='add'?e($form_data['part_number']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_category">Category</label>
            <select class="form-control" id="a_category" name="category">
              <?php foreach ($categories as $v => $l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='add'&&($form_data['category']??'other')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_unit">Unit</label>
            <input class="form-control" id="a_unit" name="unit" type="text"
                   placeholder="pcs, liters, sets, rolls…" maxlength="30"
                   value="<?php echo $modal_reopen==='add'?e($form_data['unit']??'pcs'):'pcs'; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_quantity">Current Stock <span class="req">*</span></label>
            <input class="form-control" id="a_quantity" name="quantity" type="number"
                   step="1" min="0" placeholder="0"
                   value="<?php echo $modal_reopen==='add'?e($form_data['quantity']??'0'):'0'; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_reorder_point">Reorder At</label>
            <input class="form-control" id="a_reorder_point" name="reorder_point" type="number"
                   step="1" min="0" placeholder="0"
                   value="<?php echo $modal_reopen==='add'?e($form_data['reorder_point']??'0'):'0'; ?>" />
            <span class="form-hint">Alert when stock drops to this level (0 = no alert)</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_unit_cost">Unit Cost (₱)</label>
            <input class="form-control" id="a_unit_cost" name="unit_cost" type="number"
                   step="0.01" min="0" placeholder="e.g. 850.00"
                   value="<?php echo $modal_reopen==='add'?e($form_data['unit_cost']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_supplier">Supplier</label>
            <input class="form-control" id="a_supplier" name="supplier" type="text"
                   placeholder="e.g. Petron Parts Supply" maxlength="150"
                   value="<?php echo $modal_reopen==='add'?e($form_data['supplier']??''):''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="a_location">Shelf / Bin Location</label>
            <input class="form-control" id="a_location" name="location" type="text"
                   placeholder="e.g. Shelf A-3, Bin 12" maxlength="100"
                   value="<?php echo $modal_reopen==='add'?e($form_data['location']??''):''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="a_notes">Notes</label>
            <textarea class="form-control" id="a_notes" name="notes" rows="2"
                      placeholder="Compatibility notes, specifications, usage remarks…" maxlength="1000"
              ><?php echo $modal_reopen==='add'?e($form_data['notes']??''):''; ?></textarea>
          </div>

        </div>
      </div>
      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Add Item</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT ITEM MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="editItemModal">
  <div class="itc-modal itc-modal-lg" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Edit Inventory Item</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="inventory_handler.php">
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
            <label class="form-label" for="edit_part_number">Part Number / SKU</label>
            <input class="form-control" id="edit_part_number" name="part_number" type="text"
                   placeholder="e.g. TY-700R-14" maxlength="80"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['part_number']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_category">Category</label>
            <select class="form-control" id="edit_category" name="category">
              <?php foreach ($categories as $v => $l): ?>
              <option value="<?php echo $v; ?>" <?php echo ($modal_reopen==='edit'&&($form_data['category']??'')===$v)?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_unit">Unit</label>
            <input class="form-control" id="edit_unit" name="unit" type="text"
                   placeholder="pcs, liters, sets…" maxlength="30"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['unit']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_quantity">Current Stock <span class="req">*</span></label>
            <input class="form-control" id="edit_quantity" name="quantity" type="number"
                   step="1" min="0"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['quantity']??'0'):''; ?>" />
            <span class="form-hint">Use the ± buttons on the table for quick adjustments</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_reorder_point">Reorder At</label>
            <input class="form-control" id="edit_reorder_point" name="reorder_point" type="number"
                   step="1" min="0"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['reorder_point']??'0'):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_unit_cost">Unit Cost (₱)</label>
            <input class="form-control" id="edit_unit_cost" name="unit_cost" type="number"
                   step="0.01" min="0" placeholder="e.g. 850.00"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['unit_cost']??''):''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_supplier">Supplier</label>
            <input class="form-control" id="edit_supplier" name="supplier" type="text"
                   placeholder="e.g. Petron Parts Supply" maxlength="150"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['supplier']??''):''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_location">Shelf / Bin Location</label>
            <input class="form-control" id="edit_location" name="location" type="text"
                   placeholder="e.g. Shelf A-3, Bin 12" maxlength="100"
                   value="<?php echo $modal_reopen==='edit'?e($form_data['location']??''):''; ?>" />
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_notes">Notes</label>
            <textarea class="form-control" id="edit_notes" name="notes" rows="2"
                      placeholder="Compatibility notes, specifications, usage remarks…" maxlength="1000"
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
  document.getElementById('<?php echo $modal_reopen==='edit'?'editItemModal':'addItemModal'; ?>')?.classList.add('open');
});
</script>
<?php endif; ?>

<script src="/assets/js/inventory.js"></script>
<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>