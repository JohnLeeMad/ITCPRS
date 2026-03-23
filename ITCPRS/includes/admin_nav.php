<?php

/**
 * includes/admin_nav.php
 * ─────────────────────────────────────────────────────────────
 * Shared sidebar + topbar for ALL admin/dashboard pages.
 *
 * HOW TO USE — at the top of every admin page:
 * ─────────────────────────────────────────────
 *   <?php
 *     require_once __DIR__ . '/../../includes/auth.php';
 *     auth_session_start();
 *     require_login();
 *     require_role(['admin']); // or ['admin','secretary'] etc.
 *
 *     $page_title  = 'Dashboard';   // shown in topbar + <title>
 *     $active_menu = 'dashboard';   // matches keys in $nav_items below
 *     require_once __DIR__ . '/../../includes/admin_nav.php';
 *   ?>
 *
 *   ... your page HTML here ...
 *
 *   <?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>
 * ─────────────────────────────────────────────────────────────
 */

// Fallback guard
if (empty($_SESSION['user'])) {
  header('Location: /modules/auth/login.php');
  exit;
}

$nav_user     = $_SESSION['user'];
$nav_role     = $nav_user['role'];
$nav_name     = $nav_user['full_name'];
$nav_initials = strtoupper(implode('', array_map(
  fn($w) => $w[0],
  array_slice(explode(' ', trim($nav_name)), 0, 2)
)));
// Fetch photo fresh from DB — session doesn't always carry it
$nav_photo = null;
if (!empty($nav_user['id'])) {
  global $conn;
  $_nav_ps = mysqli_prepare($conn, "SELECT photo FROM users WHERE id = ? LIMIT 1");
  if ($_nav_ps) {
    mysqli_stmt_bind_param($_nav_ps, 'i', $nav_user['id']);
    mysqli_stmt_execute($_nav_ps);
    $nav_photo = mysqli_fetch_assoc(mysqli_stmt_get_result($_nav_ps))['photo'] ?? null;
    mysqli_stmt_close($_nav_ps);
  }
}
// Build avatar HTML: photo if available, else initials
$_nav_photo_url = $nav_photo
  ? '/assets/uploads/users/' . rawurlencode($nav_photo)
  : null;
$nav_avatar_topbar = $_nav_photo_url
  ? '<img src="' . htmlspecialchars($_nav_photo_url) . '" alt="' . htmlspecialchars($nav_name) . '" />'
  : htmlspecialchars($nav_initials);

$active_menu = $active_menu ?? 'dashboard';
$page_title  = $page_title  ?? 'Admin Panel';

// ─────────────────────────────────────────────────────────────
//  NAV MENU DEFINITION
//  key => [label, href, icon_svg_inner_html, roles_allowed]
// ─────────────────────────────────────────────────────────────
$nav_items = [
  // ── Main
  'dashboard' => [
    'label' => 'Dashboard',
    'href'  => '/modules/dashboard/admin.php',
    'roles' => ['admin', 'secretary', 'staff', 'driver'],
    'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  ],
  'contracts' => [
    'label' => 'Contracts',
    'href'  => '/modules/contracts/contracts.php',
    'roles' => ['admin', 'secretary'],
    'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
  ],
  'trucks' => [
    'label' => 'Trucks',
    'href'  => '/modules/trucks/trucks.php',
    'roles' => ['admin', 'secretary', 'staff'],
    'icon'  => '<rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
  ],
  'trips' => [
    'label' => 'Trip Records',
    'href'  => '/modules/trips/trips.php',
    'roles' => ['admin', 'secretary', 'staff', 'driver'],
    'icon'  => '<path d="M3 12h18M3 6h18M3 18h12"/><circle cx="19" cy="18" r="2"/>',
  ],
  'parts' => [
    'label' => 'Parts Requests',
    'href'  => '/modules/parts/parts.php',
    'roles' => ['admin', 'secretary', 'staff'],
    'icon'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  ],
  'inventory' => [
    'label' => 'Inventory',
    'href'  => '/modules/inventory/inventory.php',
    'roles' => ['admin', 'secretary', 'staff'],
    'icon'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27,6.96 12,12.01 20.73,6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
  ],
  // ── People
  'drivers' => [
    'label' => 'Drivers',
    'href'  => '/modules/drivers/drivers.php',
    'roles' => ['admin', 'secretary'],
    'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  ],
  'users' => [
    'label' => 'User Management',
    'href'  => '/modules/users/users.php',
    'roles' => ['admin'],
    'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
  ],
  // ── Reports
  'reports' => [
    'label' => 'Reports',
    'href'  => '/modules/reports/reports.php',
    'roles' => ['admin', 'secretary'],
    'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
  ],
];

// Group sections for visual separation
$nav_sections = [
  'Main'    => ['dashboard', 'contracts', 'trucks', 'trips', 'parts', 'inventory'],
  'People'  => ['drivers', 'users'],
  'Reports' => ['reports'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($page_title); ?> – ITCPRS</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/sidenav.css" />
  <link rel="stylesheet" href="/assets/css/admin.css" />
  <script src="/assets/js/alerts.js" defer></script>

  <script>
    (function() {
      if (localStorage.getItem('sidebarCollapsed') === '1') {
        // Add both classes synchronously before paint.
        // sidebar-no-transition suppresses the CSS animation
        // so the sidebar doesn't slide in from the left.
        // admin_nav_end.php removes sidebar-no-transition after
        // the first frame so future toggle clicks still animate.
        document.documentElement.classList.add('sidebar-collapsed', 'sidebar-no-transition');
      }
    })();
  </script>
</head>

<body>

  <!-- ═══════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
  <aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="sidebar-logo-wrap"><img src="/assets/images/logo.jpg" alt="ITCPRS Logo" /></div>
      <div class="sidebar-brand-text">
        <span class="sidebar-brand-name">ITCPRS</span>
        <span class="sidebar-brand-sub">Admin Panel</span>
      </div>
    </div>

    <!-- Nav items -->
    <nav class="sidebar-nav">
      <?php foreach ($nav_sections as $section_label => $section_keys): ?>

        <?php
        // Check if this section has any visible items for this role
        $visible = array_filter(
          $section_keys,
          fn($k) =>
          isset($nav_items[$k]) && in_array($nav_role, $nav_items[$k]['roles'], true)
        );
        if (empty($visible)) continue;
        ?>

        <div class="sidebar-nav-section"><?php echo $section_label; ?></div>

        <?php foreach ($section_keys as $key):
          if (!isset($nav_items[$key])) continue;
          $item = $nav_items[$key];
          if (!in_array($nav_role, $item['roles'], true)) continue;
          $is_active = ($active_menu === $key);
        ?>
          <a href="<?php echo htmlspecialchars($item['href']); ?>"
            class="sidebar-nav-item <?php echo $is_active ? 'active' : ''; ?>"
            data-label="<?php echo htmlspecialchars($item['label']); ?>"
            title="<?php echo htmlspecialchars($item['label']); ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
              <?php echo $item['icon']; ?>
            </svg>
            <span class="nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
          </a>
        <?php endforeach; ?>

      <?php endforeach; ?>

      <!-- Account section -->
      <div class="sidebar-nav-section" style="margin-top:8px;">Account</div>

      <a href="/modules/profiles/profile.php"
        class="sidebar-nav-item <?php echo $active_menu === 'profile' ? 'active' : ''; ?>"
        data-label="My Profile" title="My Profile">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="4" />
          <path d="M4 20v-2a8 8 0 0 1 16 0v2" />
        </svg>
        <span class="nav-label">My Profile</span>
      </a>

      <a href="#"
        class="sidebar-nav-item sidebar-nav-logout"
        data-label="Log Out" title="Log Out"
        onclick="ITCAlert.confirmLogout(); return false;">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16,17 21,12 16,7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
        <span class="nav-label">Log Out</span>
      </a>
    </nav>

    <!-- Collapse toggle -->
    <button class="sidebar-collapse-btn" id="collapseBtn" title="Collapse sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <polyline points="15,18 9,12 15,6" />
      </svg>
    </button>
  </aside>

  <!-- ═══════════════════════════════════════
     TOPBAR
════════════════════════════════════════ -->
  <header class="topbar" id="topbar">

    <!-- Mobile hamburger -->
    <button class="topbar-hamburger" id="hamburger" aria-label="Open navigation">
      <span></span><span></span><span></span>
    </button>

    <!-- Breadcrumb / Page title -->
    <div class="topbar-title">
      <span class="topbar-breadcrumb">Admin</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-light)">
        <polyline points="9,18 15,12 9,6" />
      </svg>
      <span class="page-title"><?php echo htmlspecialchars($page_title); ?></span>
    </div>

    <!-- Right side actions -->
    <div class="topbar-actions">

      <!-- Notifications button -->
      <button class="topbar-icon-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        <span class="topbar-notif-badge">3</span>
      </button>

      <!-- User dropdown -->
      <div class="topbar-user" id="topbarUser">
        <div class="topbar-avatar <?php echo $nav_photo ? 'has-photo' : ''; ?>"><?php echo $nav_avatar_topbar; ?></div>
        <div class="topbar-user-info">
          <span class="topbar-user-name"><?php echo htmlspecialchars(explode(' ', $nav_name)[0]); ?></span>
          <span class="topbar-user-role"><?php echo ucfirst($nav_role); ?></span>
        </div>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14">
          <polyline points="6,9 12,15 18,9" />
        </svg>

        <div class="topbar-dropdown" id="topbarDropdown">
          <a href="/modules/profiles/profile.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <circle cx="12" cy="8" r="4" />
              <path d="M4 20v-2a8 8 0 0 1 16 0v2" />
            </svg>
            My Profile
          </a>
          <a href="/modules/settings/settings.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <circle cx="12" cy="12" r="3" />
              <path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2m0 16v2m7.07-4.93-1.41-1.41M4.93 19.07l1.41-1.41M22 12h-2M4 12H2" />
            </svg>
            Settings
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-logout" onclick="ITCAlert.confirmLogout(); return false;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16,17 21,12 16,7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Log Out
          </a>
        </div>
      </div>

    </div>
  </header>

  <!-- Mobile overlay -->
  <div class="sidebar-overlay" id="overlay"></div>

  <!-- ═══════════════════════════════════════
     PAGE CONTENT WRAPPER OPENS HERE
     (closed by admin_nav_end.php)
════════════════════════════════════════ -->
  <main class="admin-body" id="adminBody">