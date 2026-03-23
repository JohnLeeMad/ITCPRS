<?php
/**
 * dashboard/admin.php
 * Admin Dashboard — reference template for all other admin pages.
 *
 * To create a new admin page, copy the pattern:
 *   1. Require auth + session
 *   2. Set $page_title and $active_menu
 *   3. Include admin_nav.php  (opens <html>, <body>, sidebar, topbar, <main>)
 *   4. Write your page content
 *   5. Include admin_nav_end.php (closes <main>, JS, </body>, </html>)
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin', 'secretary']); // adjust per page

$page_title  = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/../../includes/admin_nav.php';

// ── Placeholder data (replace with real DB queries) ──────────
$stats = [
    [
        'label'  => 'Active Trucks',
        'value'  => 14,
        'trend'  => '+2 this month',
        'dir'    => 'up',
        'style'  => 'navy',
        'icon'   => '<rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    ],
    [
        'label'  => 'Trips This Month',
        'value'  => 87,
        'trend'  => '+12 vs last month',
        'dir'    => 'up',
        'style'  => 'gold',
        'icon'   => '<circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>',
    ],
    [
        'label'  => 'Open Parts Requests',
        'value'  => 5,
        'trend'  => '2 urgent',
        'dir'    => 'flat',
        'style'  => 'red',
        'icon'   => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    ],
    [
        'label'  => 'Active Contracts',
        'value'  => 3,
        'trend'  => '1 expiring soon',
        'dir'    => 'flat',
        'style'  => 'green',
        'icon'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/>',
    ],
];

// Recent trips
$recent_trips = [
    ['plate'=>'ABC 1234','driver'=>'Rolando Cruz',   'from'=>'Bataan',  'to'=>'Tarlac',    'status'=>'Delivered', 'date'=>'Mar 12'],
    ['plate'=>'XYZ 5678','driver'=>'Jose Reyes',     'from'=>'Bataan',  'to'=>'Pangasinan','status'=>'En Route',  'date'=>'Mar 12'],
    ['plate'=>'MNO 9012','driver'=>'Mario Santos',   'from'=>'Bataan',  'to'=>'Isabela',   'status'=>'Delivered', 'date'=>'Mar 11'],
    ['plate'=>'QRS 3456','driver'=>'Dante Flores',   'from'=>'Bataan',  'to'=>'Tarlac',    'status'=>'Breakdown', 'date'=>'Mar 11'],
    ['plate'=>'TUV 7890','driver'=>'Antonio Ramos',  'from'=>'Bataan',  'to'=>'Pangasinan','status'=>'Delivered', 'date'=>'Mar 10'],
];

// Parts requests
$parts_requests = [
    ['part'=>'Rear Tire (700R)','plate'=>'QRS 3456','requested_by'=>'Dante Flores',  'status'=>'Urgent',  'date'=>'Mar 11'],
    ['part'=>'Battery 12V',     'plate'=>'DEF 2345','requested_by'=>'Staff — Ramon', 'status'=>'Pending', 'date'=>'Mar 10'],
    ['part'=>'Wheel Bearing',   'plate'=>'GHI 6789','requested_by'=>'Staff — Lito',  'status'=>'Sourcing','date'=>'Mar 09'],
    ['part'=>'Wiring Harness',  'plate'=>'JKL 0123','requested_by'=>'Staff — Manny', 'status'=>'Done',    'date'=>'Mar 08'],
];

// Alerts
$alerts = [
    ['dot'=>'red',   'text'=>'QRS 3456 reported breakdown — rear tire needed.',          'time'=>'2 hours ago'],
    ['dot'=>'amber', 'text'=>'Contract with B-Meg Tarlac expires in 14 days.',           'time'=>'Today'],
    ['dot'=>'amber', 'text'=>'Parts request for DEF 2345 battery still pending.',         'time'=>'Yesterday'],
    ['dot'=>'green', 'text'=>'MNO 9012 completed Isabela delivery successfully.',         'time'=>'Mar 11'],
    ['dot'=>'green', 'text'=>'Wiring harness for JKL 0123 installed and resolved.',      'time'=>'Mar 8'],
];

// Trip destinations breakdown
$destinations = [
    ['name'=>'Tarlac',    'trips'=>34, 'pct'=>39],
    ['name'=>'Pangasinan','trips'=>28, 'pct'=>32],
    ['name'=>'Isabela',   'trips'=>18, 'pct'=>21],
    ['name'=>'Others',    'trips'=>7,  'pct'=>8],
];

$status_map = [
    'Delivered' => 'pill-green',
    'En Route'  => 'pill-blue',
    'Breakdown' => 'pill-red',
    'Urgent'    => 'pill-red',
    'Pending'   => 'pill-amber',
    'Sourcing'  => 'pill-navy',
    'Done'      => 'pill-green',
];
?>

<!-- ═══════════════════════════════════════
     PAGE CONTENT
════════════════════════════════════════ -->

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
    <a href="/dashboard/reports.php" class="btn btn-outline">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
      </svg>
      View Reports
    </a>
    <a href="/dashboard/parts.php" class="btn btn-gold">
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

  <!-- LEFT: Recent Trips + Parts Requests -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Recent Trips -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Recent Trips</h2>
          <div class="card-head-sub">Latest deliveries — Bataan to B-Meg plants</div>
        </div>
        <a href="/dashboard/trips.php" class="btn btn-outline" style="font-size:12px;padding:7px 14px;">View All</a>
      </div>
      <div class="card-body">
        <table class="data-table">
          <thead>
            <tr>
              <th>Plate No.</th>
              <th>Driver</th>
              <th>Destination</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_trips as $trip): ?>
            <tr>
              <td><span class="plate-badge"><?php echo htmlspecialchars($trip['plate']); ?></span></td>
              <td><?php echo htmlspecialchars($trip['driver']); ?></td>
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
        <a href="/dashboard/trips.php">View all trip records →</a>
      </div>
    </div>

    <!-- Parts Requests -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Parts Requests</h2>
          <div class="card-head-sub">Pending and recent repair part requests</div>
        </div>
        <a href="/dashboard/parts.php" class="btn btn-outline" style="font-size:12px;padding:7px 14px;">Manage</a>
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
        <a href="/dashboard/parts.php">View all parts requests →</a>
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
        <a href="/dashboard/trips.php?action=new" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
          </svg>
          Log New Trip
        </a>
        <a href="/dashboard/parts.php?action=new" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
          </svg>
          Request Part
        </a>
        <a href="/dashboard/trucks.php?action=new" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
          Add Truck
        </a>
        <a href="/dashboard/drivers.php?action=new" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
          </svg>
          Add Driver
        </a>
        <a href="/dashboard/contracts.php?action=new" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/>
          </svg>
          New Contract
        </a>
        <a href="/dashboard/reports.php" class="quick-action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
          Generate Report
        </a>
      </div>
    </div>

    <!-- Alerts -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Alerts</h2>
          <div class="card-head-sub">Breakdowns, contracts & requests</div>
        </div>
        <span class="pill pill-red" style="font-size:10px;">
          <?php echo count(array_filter($alerts, fn($a) => $a['dot'] === 'red')); ?> urgent
        </span>
      </div>
      <div class="alert-list">
        <?php foreach ($alerts as $alert): ?>
        <div class="alert-item">
          <div class="alert-dot <?php echo $alert['dot']; ?>"></div>
          <div>
            <div class="alert-text"><?php echo htmlspecialchars($alert['text']); ?></div>
            <div class="alert-time"><?php echo htmlspecialchars($alert['time']); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Delivery Destinations -->
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Top Destinations</h2>
          <div class="card-head-sub">Trip distribution this month</div>
        </div>
      </div>
      <div class="dest-list">
        <?php foreach ($destinations as $d): ?>
        <div class="dest-item">
          <div>
            <div class="dest-name"><?php echo htmlspecialchars($d['name']); ?></div>
            <div class="dest-count"><?php echo $d['trips']; ?> trips</div>
          </div>
          <div class="dest-bar-wrap">
            <div class="dest-bar" style="width:<?php echo $d['pct']; ?>%"></div>
          </div>
          <div class="dest-pct"><?php echo $d['pct']; ?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div><!-- /RIGHT -->

</div><!-- /.dashboard-grid -->

<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>