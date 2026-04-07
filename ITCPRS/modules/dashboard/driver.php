<?php
/**
 * dashboard/driver.php
 * Driver (Trucker) Dashboard
 *
 * Access: Dashboard, Trip Records, Parts Requests, Contracts, Inventory, Profile
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['driver']);

$page_title  = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/../../includes/driver_nav.php';

// ── Placeholder data (replace with real DB queries for the logged-in driver) ──
$stats = [
  [
    'label' => 'My Trips This Month',
    'value' => 12,
    'trend' => '+3 vs last month',
    'dir'   => 'up',
    'style' => 'navy',
    'icon'  => '<rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
  ],
  [
    'label' => 'Completed Deliveries',
    'value' => 11,
    'trend' => 'This month',
    'dir'   => 'up',
    'style' => 'green',
    'icon'  => '<polyline points="20,6 9,17 4,12"/>',
  ],
  [
    'label' => 'Open Parts Requests',
    'value' => 2,
    'trend' => '1 urgent',
    'dir'   => 'flat',
    'style' => 'red',
    'icon'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  ],
  [
    'label' => 'Active Contracts',
    'value' => 3,
    'trend' => '1 expiring soon',
    'dir'   => 'flat',
    'style' => 'gold',
    'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/>',
  ],
];

// Driver's own recent trips
$my_trips = [
  ['plate' => 'ABC 1234', 'from' => 'Bataan', 'to' => 'Tarlac',     'status' => 'Delivered', 'date' => 'Mar 12'],
  ['plate' => 'ABC 1234', 'from' => 'Bataan', 'to' => 'Pangasinan', 'status' => 'Delivered', 'date' => 'Mar 10'],
  ['plate' => 'ABC 1234', 'from' => 'Bataan', 'to' => 'Isabela',    'status' => 'Delivered', 'date' => 'Mar 07'],
  ['plate' => 'ABC 1234', 'from' => 'Bataan', 'to' => 'Tarlac',     'status' => 'Delivered', 'date' => 'Mar 05'],
  ['plate' => 'ABC 1234', 'from' => 'Bataan', 'to' => 'Pangasinan', 'status' => 'Breakdown', 'date' => 'Mar 03'],
];

// Driver's own parts requests
$my_parts = [
  ['part' => 'Rear Tire (700R)', 'plate' => 'ABC 1234', 'status' => 'Urgent',   'date' => 'Mar 11'],
  ['part' => 'Air Filter',       'plate' => 'ABC 1234', 'status' => 'Sourcing', 'date' => 'Mar 07'],
  ['part' => 'Windshield Wiper', 'plate' => 'ABC 1234', 'status' => 'Done',     'date' => 'Feb 28'],
];

// Active contracts (read-only view)
$contracts = [
  ['client' => 'B-Meg Tarlac',      'route' => 'Bataan → Tarlac',     'status' => 'Active',  'expires' => 'Jun 30, 2025'],
  ['client' => 'B-Meg Pangasinan',  'route' => 'Bataan → Pangasinan', 'status' => 'Active',  'expires' => 'Mar 25, 2025'],
  ['client' => 'B-Meg Isabela',     'route' => 'Bataan → Isabela',    'status' => 'Active',  'expires' => 'Dec 31, 2025'],
];

$status_map = [
  'Delivered' => 'pill-green',
  'En Route'  => 'pill-blue',
  'Breakdown' => 'pill-red',
  'Urgent'    => 'pill-red',
  'Pending'   => 'pill-amber',
  'Sourcing'  => 'pill-navy',
  'Done'      => 'pill-green',
  'Active'    => 'pill-green',
  'Expiring'  => 'pill-amber',
  'Expired'   => 'pill-red',
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

  <!-- LEFT: My Trips + My Parts Requests -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- My Trips -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>My Trip Records</h2>
          <div class="card-head-sub">Your recent deliveries</div>
        </div>
        <a href="/modules/trips/trips.php" class="btn btn-outline" style="font-size:12px;padding:7px 14px;">View All</a>
      </div>
      <div class="card-body">
        <table class="data-table">
          <thead>
            <tr>
              <th>Plate No.</th>
              <th>Destination</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($my_trips as $trip): ?>
            <tr>
              <td><span class="plate-badge"><?php echo htmlspecialchars($trip['plate']); ?></span></td>
              <td>
                <span style="font-size:11px;color:var(--text-muted);">Bataan →</span>
                <strong><?php echo htmlspecialchars($trip['to']); ?></strong>
              </td>
              <td>
                <span class="pill <?php echo $status_map[$trip['status']] ?? 'pill-navy'; ?>">
                  <?php echo htmlspecialchars($trip['status']); ?>
                </span>
              </td>
              <td class="muted"><?php echo htmlspecialchars($trip['date']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-foot">
        <a href="/modules/trips/trips.php">View all trip records →</a>
      </div>
    </div>

    <!-- My Parts Requests -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>My Parts Requests</h2>
          <div class="card-head-sub">Parts you've requested for your truck</div>
        </div>
        <a href="/modules/parts/parts.php?action=new" class="btn btn-gold" style="font-size:12px;padding:7px 14px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="width:13px;height:13px;">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Request
        </a>
      </div>
      <div class="card-body">
        <table class="data-table">
          <thead>
            <tr>
              <th>Part</th>
              <th>Truck</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($my_parts as $req): ?>
            <tr>
              <td style="font-weight:600;"><?php echo htmlspecialchars($req['part']); ?></td>
              <td><span class="plate-badge"><?php echo htmlspecialchars($req['plate']); ?></span></td>
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
        <a href="/modules/parts/parts.php">View all my parts requests →</a>
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
        <a href="/modules/trips/trips.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12h18M3 6h18M3 18h12"/><circle cx="19" cy="18" r="2"/>
          </svg>
          My Trips
        </a>
        <a href="/modules/contracts/contracts.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/>
          </svg>
          Contracts
        </a>
        <a href="/modules/inventory/inventory.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          </svg>
          Inventory
        </a>
        <a href="/modules/profiles/profile.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
          </svg>
          My Profile
        </a>
      </div>
    </div>

    <!-- Contracts -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Active Contracts</h2>
          <div class="card-head-sub">Your assigned delivery contracts</div>
        </div>
      </div>
      <div class="alert-list">
        <?php foreach ($contracts as $c): ?>
        <div class="alert-item">
          <div class="alert-dot <?php echo $c['status'] === 'Active' ? 'green' : 'amber'; ?>"></div>
          <div style="flex:1;">
            <div class="alert-text" style="font-weight:600;"><?php echo htmlspecialchars($c['client']); ?></div>
            <div class="alert-time"><?php echo htmlspecialchars($c['route']); ?> &mdash; expires <?php echo $c['expires']; ?></div>
          </div>
          <span class="pill <?php echo $status_map[$c['status']]; ?>" style="font-size:10px;"><?php echo $c['status']; ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-foot">
        <a href="/modules/contracts/contracts.php">View all contracts →</a>
      </div>
    </div>

  </div><!-- /RIGHT -->

</div><!-- /.dashboard-grid -->

<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>