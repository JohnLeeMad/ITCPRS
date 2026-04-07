<?php

/**
 * includes/driver_nav.php
 * ─────────────────────────────────────────────────────────────
 * Shared sidebar + topbar for Driver (Trucker) pages.
 *
 * HOW TO USE — at the top of every driver page:
 * ─────────────────────────────────────────────
 *   <?php
 *     require_once __DIR__ . '/../../includes/auth.php';
 *     auth_session_start();
 *     require_login();
 *     require_role(['driver']);
 *
 *     $page_title  = 'Dashboard';
 *     $active_menu = 'dashboard';
 *     require_once __DIR__ . '/../../includes/driver_nav.php';
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

// Fetch photo fresh from DB
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

$_nav_photo_url = $nav_photo
  ? '/assets/uploads/users/' . rawurlencode($nav_photo)
  : null;
$nav_avatar_topbar = $_nav_photo_url
  ? '<img src="' . htmlspecialchars($_nav_photo_url) . '" alt="' . htmlspecialchars($nav_name) . '" />'
  : htmlspecialchars($nav_initials);

$active_menu = $active_menu ?? 'dashboard';
$page_title  = $page_title  ?? 'Driver Panel';

// ─────────────────────────────────────────────────────────────
//  NAV MENU — Driver: Dashboard, Parts Requests, Contracts,
//             Trip Records, Inventory
// ─────────────────────────────────────────────────────────────
$nav_items = [
  'dashboard' => [
    'label' => 'Dashboard',
    'href'  => '/modules/dashboard/driver.php',
    'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  ],
  'trips' => [
    'label' => 'Trip Records',
    'href'  => '/modules/trips/trips.php',
    'icon'  => '<path d="M3 12h18M3 6h18M3 18h12"/><circle cx="19" cy="18" r="2"/>',
  ],
  'parts' => [
    'label' => 'Parts Requests',
    'href'  => '/modules/parts/parts.php',
    'icon'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  ],
  'contracts' => [
    'label' => 'Contracts',
    'href'  => '/modules/contracts/contracts.php',
    'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
  ],
  'inventory' => [
    'label' => 'Inventory',
    'href'  => '/modules/inventory/inventory.php',
    'icon'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27,6.96 12,12.01 20.73,6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
  ],
];

$nav_sections = [
  'Main' => ['dashboard', 'trips', 'parts', 'contracts', 'inventory'],
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
        <span class="sidebar-brand-sub">Driver Panel</span>
      </div>
    </div>

    <!-- Nav items -->
    <nav class="sidebar-nav">
      <?php foreach ($nav_sections as $section_label => $section_keys): ?>

        <div class="sidebar-nav-section"><?php echo $section_label; ?></div>

        <?php foreach ($section_keys as $key):
          if (!isset($nav_items[$key])) continue;
          $item = $nav_items[$key];
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
      <span class="topbar-breadcrumb">Driver</span>
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

  <!-- PAGE CONTENT WRAPPER OPENS HERE (closed by admin_nav_end.php) -->
  <main class="admin-body" id="adminBody">