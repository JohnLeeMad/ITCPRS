<?php

/**
 * includes/admin_nav_end.php
 * Closes the <main> tag and outputs shared JS.
 * Include at the BOTTOM of every admin page.
 */
?>
</main><!-- /.admin-body -->

<script>
  (function() {
    // html element — same target as the <head> anti-flash script in admin_nav.php.
    // Using documentElement (not body) means the collapsed class is set before
    // the sidebar ever paints, with zero risk of a null-body race condition.
    const html = document.documentElement;
    const body = document.body;

    // ── Remove no-transition after first frame so toggle clicks animate ──
    // The <head> script already stamped sidebar-collapsed + sidebar-no-transition
    // on <html> before paint. Now that the page is fully rendered we just need
    // to lift the suppression flag.
    requestAnimationFrame(() => {
      html.classList.remove('sidebar-no-transition');
    });

    // ── Sidebar collapse (desktop) ──────────────────────────────
    const collapseBtn = document.getElementById('collapseBtn');

    if (collapseBtn) {
      collapseBtn.addEventListener('click', () => {
        html.classList.remove('sidebar-no-transition');
        html.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed',
          html.classList.contains('sidebar-collapsed') ? '1' : '0');
      });
    }

    // ── Mobile sidebar toggle ───────────────────────────────────
    const hamburger = document.getElementById('hamburger');
    const overlay   = document.getElementById('overlay');

    function openMobileSidebar() {
      html.classList.remove('sidebar-no-transition');
      body.classList.add('sidebar-open');
      html.classList.remove('sidebar-collapsed');
    }

    function closeMobileSidebar() {
      body.classList.remove('sidebar-open');
    }

    if (hamburger) hamburger.addEventListener('click', openMobileSidebar);
    if (overlay)   overlay.addEventListener('click', closeMobileSidebar);

    // ── Topbar user dropdown ────────────────────────────────────
    const topbarUser = document.getElementById('topbarUser');
    if (topbarUser) {
      topbarUser.addEventListener('click', (e) => {
        e.stopPropagation();
        topbarUser.classList.toggle('open');
      });
      document.addEventListener('click', () => {
        topbarUser.classList.remove('open');
      });
    }

    // ── Close mobile sidebar on resize to desktop ───────────────
    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) closeMobileSidebar();
    });
  })();
</script>
</body>

</html>