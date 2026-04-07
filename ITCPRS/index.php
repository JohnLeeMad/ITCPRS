<?php // Homepage ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ITCPRS – Integrated Trucking Contracts &amp; Parts Request Solution</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:wght@300;400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/index.css"/>
</head>
<body>

<!-- NAV -->
<nav>
  <a href="index.php" class="nav-brand">
    <div class="nav-logo-ph"><img src="assets/images/logo.jpg" alt="ITCPRS Logo"/></div>
    <div class="nav-title">ITCPRS <span>Trucking Management</span></div>
  </a>
  <div class="nav-links">
    <a href="#features" class="nav-text-link">Features</a>
    <a href="#how"      class="nav-text-link">How It Works</a>
    <a href="#roles"    class="nav-text-link">Roles</a>
    <a href="modules/auth/login.php"    class="btn-nav-login">Log In</a>
    <!-- <a href="modules/auth/register.php" class="btn-nav-register">Register</a> -->
  </div>
  <button class="hamburger" id="hamburger" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<div class="mobile-menu" id="mobileMenu" role="navigation">
  <a href="#features" class="mob-link">Features</a>
  <a href="#how"      class="mob-link">How It Works</a>
  <a href="#roles"    class="mob-link">Roles</a>
  <div class="mob-divider"></div>
  <a href="modules/auth/login.php"    class="mob-login">Log In</a>
  <a href="modules/auth/register.php" class="mob-register">Register</a>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge">Trucking Management Platform</div>
  <div class="hero-photo-ph">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="14" rx="2"/><path d="M3 9h18M9 21l3-4 3 4"/></svg>
    System Photo<br>Here
  </div>
  <h1>Integrated Trucking<br><em>Contracts</em> &amp; Parts<br>Request Solution</h1>
  <p class="hero-sub">A unified platform for managing trucking contracts, tracking parts requests, and streamlining fleet operations with role-based access control.</p>
  <div class="hero-cta">
    <a href="modules/auth/register.php" class="btn-primary">Get Started</a>
    <a href="modules/auth/login.php"    class="btn-secondary">Sign In to Dashboard</a>
  </div>
  <div class="hero-stats">
    <div class="stat"><div class="stat-num">3</div><div class="stat-label">User Roles</div></div>
    <div class="stat"><div class="stat-num">6</div><div class="stat-label">Core Features</div></div>
    <div class="stat"><div class="stat-num">100%</div><div class="stat-label">Web-Based</div></div>
    <div class="stat"><div class="stat-num">24/7</div><div class="stat-label">Access</div></div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="container">
    <div class="features-header">
      <div class="section-label">What We Offer</div>
      <div class="section-title">Highlight Features</div>
      <p class="section-desc">Everything you need to manage trucking contracts and parts requests in one integrated platform.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div><div class="feature-title">Contract Management</div><div class="feature-desc">Easily create, update, and track trucking contracts. Manage all contract details, expiry dates, and renewal schedules from one place.</div></div>
      <div class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></div><div class="feature-title">Parts Request &amp; Tracking</div><div class="feature-desc">Submit, approve, and monitor parts requests in real time. Keep track of every request from submission to fulfillment.</div></div>
      <div class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div class="feature-title">Access Control</div><div class="feature-desc">Role-based access for managers, drivers, and staff. Each user sees only the information and actions relevant to their role.</div></div>
      <div class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div><div class="feature-title">Alerts &amp; Notifications</div><div class="feature-desc">Automated reminders for contract renewals and parts availability. Never miss a critical deadline or stock update again.</div></div>
      <div class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><div class="feature-title">Reports &amp; Analytics</div><div class="feature-desc">Simple yet comprehensive reports on contracts, parts usage, and request status. Export data for management review anytime.</div></div>
      <div class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><div class="feature-title">User-Friendly Interface</div><div class="feature-desc">An intuitive, easy-to-use system designed for all users regardless of technical background. Clean dashboards and clear navigation.</div></div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how">
  <div class="container">
    <div class="how-inner">
      <div>
        <div class="section-label">Process Flow</div>
        <div class="section-title">How It Works</div>
        <p class="section-desc" style="margin-bottom:36px;">A straightforward workflow that keeps your fleet operations running smoothly.</p>
        <div class="how-steps">
          <div class="how-step"><div class="step-num">1</div><div class="step-content"><h4>Register &amp; Get Assigned a Role</h4><p>Create your account and the administrator assigns you a role — Manager, Driver, or Staff.</p></div></div>
          <div class="how-step"><div class="step-num">2</div><div class="step-content"><h4>Create or Submit Requests</h4><p>Managers create contracts. Drivers or staff submit parts requests tied to their vehicles or routes.</p></div></div>
          <div class="how-step"><div class="step-num">3</div><div class="step-content"><h4>Review &amp; Approve</h4><p>Managers review submissions, approve or reject parts requests, and update contract statuses.</p></div></div>
          <div class="how-step"><div class="step-num">4</div><div class="step-content"><h4>Monitor &amp; Get Notified</h4><p>The system sends automatic alerts for renewals and tracks fulfilment status across all requests.</p></div></div>
        </div>
      </div>
      <div class="how-visual">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
        <span>System Screenshot<br>or Demo Image Here</span>
      </div>
    </div>
  </div>
</section>

<!-- ROLES -->
<section class="roles-section" id="roles">
  <div class="container">
    <div class="roles-header">
      <div class="section-label">User Roles</div>
      <div class="section-title">Who Uses This System?</div>
      <p class="section-desc">Three distinct roles with tailored access and permissions.</p>
    </div>
    <div class="roles-grid">
      <div class="role-card"><div class="role-icon-wrap"><svg viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div><div class="role-name">Manager</div><div class="role-desc">Full system access. Manages contracts, approves parts requests, views all reports, and oversees all users.</div></div>
      <div class="role-card"><div class="role-icon-wrap"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M3 21v-2a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v2"/></svg></div><div class="role-name">Driver</div><div class="role-desc">Submits parts requests for their assigned vehicle. Views contract details relevant to their routes and assignments.</div></div>
      <div class="role-card"><div class="role-icon-wrap"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div class="role-name">Staff</div><div class="role-desc">Assists with data entry, monitors parts availability, and processes approved requests within their department.</div></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <h2>Ready to Get Started?</h2>
  <p>Join the platform and streamline your trucking operations today.</p>
  <a href="modules/auth/register.php" class="btn-dark">Create Your Account</a>
</section>

<!-- FOOTER -->
<?php include 'includes/footer.php'; ?>

<script>
  const hamburger  = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  const toggle = (open) => {
    mobileMenu.classList.toggle('open', open);
    hamburger.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  };
  hamburger.addEventListener('click', () => toggle(!mobileMenu.classList.contains('open')));
  mobileMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => toggle(false)));
  window.addEventListener('resize', () => { if (window.innerWidth > 640) toggle(false); });
</script>
</body>
</html>