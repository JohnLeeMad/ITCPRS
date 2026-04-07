<?php
/**
 * dashboard/staff.php
 * Staff (Mekaniko) Dashboard
 *
 * Access: Dashboard, Inventory, Parts Requests, Profile
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['staff']);

$page_title  = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/../../includes/staff_nav.php';

// ── Placeholder data (replace with real DB queries) ──────────
$stats = [
  [
    'label' => 'Open Parts Requests',
    'value' => 5,
    'trend' => '2 urgent',
    'dir'   => 'flat',
    'style' => 'red',
    'icon'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  ],
  [
    'label' => 'Parts Sourcing',
    'value' => 3,
    'trend' => 'In progress',
    'dir'   => 'flat',
    'style' => 'gold',
    'icon'  => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
  ],
  [
    'label' => 'Inventory Items',
    'value' => 142,
    'trend' => '+6 this week',
    'dir'   => 'up',
    'style' => 'navy',
    'icon'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>',
  ],
  [
    'label' => 'Resolved This Month',
    'value' => 18,
    'trend' => '+4 vs last month',
    'dir'   => 'up',
    'style' => 'green',
    'icon'  => '<polyline points="20,6 9,17 4,12"/>',
  ],
];

// Parts requests (staff view — all trucks)
$parts_requests = [
  ['part' => 'Rear Tire (700R)',  'plate' => 'QRS 3456', 'requested_by' => 'Dante Flores',  'status' => 'Urgent',   'date' => 'Mar 11'],
  ['part' => 'Battery 12V',      'plate' => 'DEF 2345', 'requested_by' => 'Staff — Ramon',  'status' => 'Pending',  'date' => 'Mar 10'],
  ['part' => 'Wheel Bearing',    'plate' => 'GHI 6789', 'requested_by' => 'Staff — Lito',   'status' => 'Sourcing', 'date' => 'Mar 09'],
  ['part' => 'Wiring Harness',   'plate' => 'JKL 0123', 'requested_by' => 'Staff — Manny',  'status' => 'Done',     'date' => 'Mar 08'],
  ['part' => 'Air Filter',       'plate' => 'ABC 1234', 'requested_by' => 'Staff — Lito',   'status' => 'Pending',  'date' => 'Mar 07'],
];

// Low-stock inventory items
$low_stock = [
  ['item' => 'Engine Oil (10W-40)', 'qty' => 3,  'unit' => 'liters',  'min' => 10, 'status' => 'Critical'],
  ['item' => 'Brake Fluid',         'qty' => 1,  'unit' => 'bottles', 'min' => 5,  'status' => 'Critical'],
  ['item' => 'Coolant',             'qty' => 5,  'unit' => 'liters',  'min' => 8,  'status' => 'Low'],
  ['item' => 'Fuse Set (Assorted)', 'qty' => 8,  'unit' => 'pcs',     'min' => 10, 'status' => 'Low'],
];

// Recent inventory movements
$inventory_log = [
  ['item' => 'Rear Tire (700R)', 'action' => 'Issued',    'qty' => 1, 'truck' => 'QRS 3456', 'by' => 'Self',     'date' => 'Mar 12'],
  ['item' => 'Engine Oil',       'action' => 'Restocked', 'qty' => 4, 'truck' => '—',        'by' => 'Admin',    'date' => 'Mar 11'],
  ['item' => 'Wheel Bearing',    'action' => 'Issued',    'qty' => 2, 'truck' => 'GHI 6789', 'by' => 'Self',     'date' => 'Mar 09'],
  ['item' => 'Wiring Harness',   'action' => 'Issued',    'qty' => 1, 'truck' => 'JKL 0123', 'by' => 'Self',     'date' => 'Mar 08'],
];

$status_map = [
  'Done'     => 'pill-green',
  'Urgent'   => 'pill-red',
  'Pending'  => 'pill-amber',
  'Sourcing' => 'pill-navy',
  'Critical' => 'pill-red',
  'Low'      => 'pill-amber',
  'Issued'   => 'pill-navy',
  'Restocked'=> 'pill-green',
];
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-header-eyebrow">Overview</div>
    <h1>Dashboard</h1>
    <div class="page-header-sub">
      <?php echo date('l, F j, Y'); ?> &mdash;
      Welcome back, <strong><?php echo htmlspecialchars(explode(' ', $nav_name)[0]); ?></strong>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="/modules/parts/parts.php?action=new" class="btn btn-gold">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      New Parts Request
    </a>
  </div>
</div>

<!-- ── Stat Cards ── -->
<div class="stats-grid">
  <?php foreach ($stats as $s): ?>
  <div class="stat-card stat-<?php echo $s['style']; ?>">
    <div class="stat-header">
      <div class="stat-icon <?php echo $s['style']; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <?php echo $s['icon']; ?>
        </svg>
      </div>
      <div class="stat-trend <?php echo $s['dir']; ?>">
        <?php if ($s['dir'] === 'up'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18,15 12,9 6,15"/></svg>
        <?php elseif ($s['dir'] === 'down'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
        <?php endif; ?>
        <?php echo htmlspecialchars($s['trend']); ?>
      </div>
    </div>
    <div class="stat-value"><?php echo $s['value']; ?></div>
    <div class="stat-label"><?php echo htmlspecialchars($s['label']); ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Main Content Grid ── -->
<div class="dashboard-grid">

  <!-- LEFT: Parts + Inventory Log -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Parts Requests -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Parts Requests</h2>
          <div class="card-head-sub">All pending and recent repair part requests</div>
        </div>
        <a href="/modules/parts/parts.php" class="btn btn-outline" style="font-size:12px;padding:7px 14px;">Manage</a>
      </div>
      <div class="card-body">
        <table class="data-table">
          <thead>
            <tr>
              <th>Part</th>
              <th>Truck</th>
              <th>Requested By</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($parts_requests as $req): ?>
            <tr>
              <td style="font-weight:600;"><?php echo htmlspecialchars($req['part']); ?></td>
              <td><span class="plate-badge"><?php echo htmlspecialchars($req['plate']); ?></span></td>
              <td class="muted"><?php echo htmlspecialchars($req['requested_by']); ?></td>
              <td>
                <span class="pill <?php echo $status_map[$req['status']] ?? 'pill-navy'; ?>">
                  <?php echo htmlspecialchars($req['status']); ?>
                </span>
              </td>
              <td class="muted"><?php echo htmlspecialchars($req['date']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-foot">
        <a href="/modules/parts/parts.php">View all parts requests →</a>
      </div>
    </div>

    <!-- Inventory Movement Log -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Inventory Movements</h2>
          <div class="card-head-sub">Recent issues and restocks</div>
        </div>
        <a href="/modules/inventory/inventory.php" class="btn btn-outline" style="font-size:12px;padding:7px 14px;">View All</a>
      </div>
      <div class="card-body">
        <table class="data-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Action</th>
              <th>Qty</th>
              <th>Truck</th>
              <th>By</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inventory_log as $log): ?>
            <tr>
              <td style="font-weight:600;"><?php echo htmlspecialchars($log['item']); ?></td>
              <td>
                <span class="pill <?php echo $status_map[$log['action']] ?? 'pill-navy'; ?>">
                  <?php echo htmlspecialchars($log['action']); ?>
                </span>
              </td>
              <td><?php echo $log['qty']; ?></td>
              <td>
                <?php if ($log['truck'] !== '—'): ?>
                  <span class="plate-badge"><?php echo htmlspecialchars($log['truck']); ?></span>
                <?php else: echo '<span class="muted">—</span>'; endif; ?>
              </td>
              <td class="muted"><?php echo htmlspecialchars($log['by']); ?></td>
              <td class="muted"><?php echo htmlspecialchars($log['date']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-foot">
        <a href="/modules/inventory/inventory.php">View full inventory →</a>
      </div>
    </div>

  </div><!-- /LEFT -->

  <!-- RIGHT: Side panels -->
  <div class="side-panels">

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-head">
        <div><h2>Quick Actions</h2></div>
      </div>
      <div class="quick-actions">
        <a href="/modules/parts/parts.php?action=new" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
          </svg>
          Request Part
        </a>
        <a href="/modules/inventory/inventory.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27,6.96 12,12.01 20.73,6.96"/>
          </svg>
          Check Inventory
        </a>
        <a href="/modules/parts/parts.php?filter=urgent" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          Urgent Requests
        </a>
        <a href="/modules/profiles/profile.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
          </svg>
          My Profile
        </a>
      </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Low Stock Items</h2>
          <div class="card-head-sub">Inventory items needing restock</div>
        </div>
        <span class="pill pill-red" style="font-size:10px;">
          <?php echo count(array_filter($low_stock, fn($i) => $i['status'] === 'Critical')); ?> critical
        </span>
      </div>
      <div class="alert-list">
        <?php foreach ($low_stock as $item): ?>
        <div class="alert-item">
          <div class="alert-dot <?php echo $item['status'] === 'Critical' ? 'red' : 'amber'; ?>"></div>
          <div style="flex:1;">
            <div class="alert-text"><?php echo htmlspecialchars($item['item']); ?></div>
            <div class="alert-time">
              <?php echo $item['qty']; ?> <?php echo $item['unit']; ?> remaining
              &mdash; min. <?php echo $item['min']; ?>
            </div>
          </div>
          <span class="pill <?php echo $status_map[$item['status']]; ?>" style="font-size:10px;">
            <?php echo $item['status']; ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-foot">
        <a href="/modules/inventory/inventory.php">View full inventory →</a>
      </div>
    </div>

  </div><!-- /RIGHT -->

</div><!-- /.dashboard-grid -->

<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>