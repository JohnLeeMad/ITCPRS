/**
 * assets/js/drivers.js
 * Drivers page — modal management, photo upload preview,
 * password toggle, ITCAlert delete, search/filter.
 *
 * Wrapped in DOMContentLoaded — never touches DOM before
 * admin_nav_end.php runs (prevents sidebar flash glitch).
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  /* ═══════════════════════════════════════════════════════
     DRIVER PEEK POPUP
     Appears on hover over the driver identity cell.
     Shows photo (or initials fallback) + name + contact.
  ═══════════════════════════════════════════════════════ */
  const peek         = document.getElementById('driverPeek');
  const peekImg      = document.getElementById('driverPeekImg');
  const peekInitials = document.getElementById('driverPeekInitials');
  const peekName     = document.getElementById('driverPeekName');
  const peekUsername = document.getElementById('driverPeekUsername');
  const peekSub      = document.getElementById('driverPeekSub');

  let peekTimeout = null;
  let peekVisible = false;

  function getInitials(name) {
    const words = name.trim().split(/\s+/).slice(0, 2);
    return words.map(w => w[0] || '').join('').toUpperCase();
  }

  function showPeek(trigger, x, y) {
    const img      = trigger.dataset.peekImg      || '';
    const label    = trigger.dataset.peekLabel    || '';
    const username = trigger.dataset.peekUsername || '';
    const sub      = trigger.dataset.peekSub      || '';

    peekName.textContent     = label;
    peekUsername.textContent = username;
    peekSub.textContent      = sub;

    if (img) {
      peekImg.src = img;
      peekImg.style.display      = 'block';
      peekInitials.style.display = 'none';
    } else {
      peekImg.src = '';
      peekImg.style.display      = 'none';
      peekInitials.textContent   = getInitials(label);
      peekInitials.style.display = 'flex';
    }

    peek.setAttribute('aria-hidden', 'false');
    peek.classList.add('visible');
    peekVisible = true;
    positionPeek(x, y);
  }

  function hidePeek() {
    peek.classList.remove('visible');
    peek.setAttribute('aria-hidden', 'true');
    peekVisible = false;
  }

  function positionPeek(x, y) {
    if (!peekVisible) return;
    const pw  = peek.offsetWidth  || 200;
    const ph  = peek.offsetHeight || 120;
    const GAP = 14;
    let left  = x + GAP;
    let top   = y - ph / 2;
    if (left + pw > window.innerWidth  - 8) left = x - pw - GAP;
    top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));
    peek.style.left = left + 'px';
    peek.style.top  = top  + 'px';
  }

  document.querySelectorAll('.peek-trigger').forEach(trigger => {
    trigger.addEventListener('mouseenter', e => {
      clearTimeout(peekTimeout);
      peekTimeout = setTimeout(() => showPeek(trigger, e.clientX, e.clientY), 120);
    });
    trigger.addEventListener('mousemove', e => {
      if (peekVisible) positionPeek(e.clientX, e.clientY);
    });
    trigger.addEventListener('mouseleave', () => {
      clearTimeout(peekTimeout);
      peekTimeout = setTimeout(hidePeek, 80);
    });
    // Mobile tap toggle
    trigger.addEventListener('click', e => {
      if (peekVisible) { hidePeek(); }
      else {
        const r = trigger.getBoundingClientRect();
        showPeek(trigger, r.right, r.top + r.height / 2);
        e.stopPropagation();
      }
    });
  });

  document.addEventListener('click', () => { if (peekVisible) hidePeek(); });
  peek?.addEventListener('mouseenter', () => clearTimeout(peekTimeout));
  peek?.addEventListener('mouseleave', () => { peekTimeout = setTimeout(hidePeek, 80); });

  /* ═══════════════════════════════════════════════════════
     MODAL HELPERS
  ═══════════════════════════════════════════════════════ */
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    setTimeout(() => el.querySelector('input:not([type=file]), select')?.focus(), 260);
  }

  function closeAllModals() {
    document.querySelectorAll('.itc-modal-overlay.open')
      .forEach(m => m.classList.remove('open'));
  }

  document.querySelectorAll('.itc-modal-overlay [data-close-modal]').forEach(btn => {
    btn.addEventListener('click', closeAllModals);
  });

  document.querySelectorAll('.itc-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeAllModals();
    });
  });

  /* ═══════════════════════════════════════════════════════
     PHOTO UPLOAD — live preview
  ═══════════════════════════════════════════════════════ */
  function setupFileInput(input) {
    if (!input) return;

    input.addEventListener('change', () => {
      const file        = input.files[0];
      const labelEl     = document.getElementById(input.dataset.labelId);
      const previewEl   = document.getElementById(input.dataset.previewId);
      const placeholderEl = document.getElementById(input.dataset.placeholderId);

      if (!file) return;

      if (labelEl) labelEl.textContent = file.name;

      if (previewEl && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
          // Hide placeholder and any current-photo preview
          if (placeholderEl) placeholderEl.style.display = 'none';
          // Hide current photo if in edit modal
          const currImg = document.getElementById('edit_photo_preview_current');
          if (currImg) currImg.style.display = 'none';

          previewEl.src = e.target.result;
          previewEl.style.display = 'block';
        };
        reader.readAsDataURL(file);
      }
    });
  }

  setupFileInput(document.getElementById('a_photo'));
  setupFileInput(document.getElementById('edit_photo'));

  /* ═══════════════════════════════════════════════════════
     PASSWORD SHOW / HIDE TOGGLE
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.classList.toggle('active');
    });
  });

  /* ═══════════════════════════════════════════════════════
     ADD DRIVER BUTTON
  ═══════════════════════════════════════════════════════ */
  document.getElementById('addDriverBtn')?.addEventListener('click', () => {
    document.getElementById('addDriverForm')?.reset();

    // Reset photo preview
    const preview     = document.getElementById('a_photo_preview');
    const placeholder = document.getElementById('a_photo_placeholder');
    const label       = document.getElementById('a_photo_label');
    if (preview)     { preview.src = ''; preview.style.display = 'none'; }
    if (placeholder)   placeholder.style.display = 'flex';
    if (label)         label.textContent = 'Upload photo…';

    openModal('addDriverModal');
  });

  /* ═══════════════════════════════════════════════════════
     EDIT DRIVER — populate modal from data-* attributes
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-edit-driver').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;

      document.getElementById('edit_id').value         = d.id;
      document.getElementById('edit_full_name').value  = d.fullName;
      document.getElementById('edit_username').value   = d.username;
      document.getElementById('edit_email').value      = d.email;
      document.getElementById('edit_contact').value    = d.contact;
      document.getElementById('edit_new_password').value = '';

      const statusSel = document.getElementById('edit_status');
      if (statusSel) statusSel.value = d.status;

      // Reset new file input
      const fileInput  = document.getElementById('edit_photo');
      const newPreview = document.getElementById('edit_photo_preview_new');
      const editLabel  = document.getElementById('edit_photo_label');
      const removeChk  = document.getElementById('edit_remove_photo');
      const removeWrap = document.getElementById('edit_remove_wrap');

      if (fileInput)  fileInput.value = '';
      if (newPreview) { newPreview.src = ''; newPreview.style.display = 'none'; }
      if (editLabel)    editLabel.textContent = 'Replace photo…';
      if (removeChk)    removeChk.checked = false;

      // Show current photo if it exists
      const currPreview = document.getElementById('edit_photo_preview_current');
      const placeholder = document.getElementById('edit_photo_placeholder');

      if (d.photo) {
        if (currPreview) { currPreview.src = d.photo; currPreview.style.display = 'block'; }
        if (placeholder)   placeholder.style.display = 'none';
        if (removeWrap)    removeWrap.style.display = 'flex';
      } else {
        if (currPreview) { currPreview.src = ''; currPreview.style.display = 'none'; }
        if (placeholder)   placeholder.style.display = 'flex';
        if (removeWrap)    removeWrap.style.display = 'none';
      }

      openModal('editDriverModal');
    });
  });

  /* ═══════════════════════════════════════════════════════
     DELETE — ITCAlert confirm → submit hidden form
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-delete-driver').forEach(btn => {
    btn.addEventListener('click', () => {
      ITCAlert.confirmDelete({
        label: `Driver "${btn.dataset.name}"`,
        onConfirm: () => {
          document.getElementById('deleteFormId').value = btn.dataset.id;
          document.getElementById('deleteForm').submit();
        },
      });
    });
  });

  /* ═══════════════════════════════════════════════════════
     CLIENT-SIDE SEARCH, STATUS & ASSIGNMENT FILTER
  ═══════════════════════════════════════════════════════ */
  const searchInput  = document.getElementById('driverSearch');
  const statusFilter = document.getElementById('statusFilter');
  const assignFilter = document.getElementById('assignFilter');

  function applyFilters() {
    const query    = (searchInput?.value  || '').toLowerCase().trim();
    const status   = (statusFilter?.value || '').toLowerCase().trim();
    const assigned = (assignFilter?.value || '').toLowerCase().trim();

    const rows = document.querySelectorAll('#driversTableBody tr[data-row]');
    let visible = 0;

    rows.forEach(row => {
      const name   = (row.dataset.name     || '').toLowerCase();
      const uname  = (row.dataset.username || '').toLowerCase();
      const plate  = (row.dataset.plate    || '').toLowerCase();
      const st     = (row.dataset.status   || '').toLowerCase();
      const asgn   = (row.dataset.assigned || '').toLowerCase();

      const matchSearch = !query  || name.includes(query) || uname.includes(query) || plate.includes(query);
      const matchStatus = !status || st   === status;
      const matchAssign = !assigned || asgn === assigned;

      const show = matchSearch && matchStatus && matchAssign;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 && rows.length > 0 ? 'flex' : 'none';

    const info = document.getElementById('countInfo');
    if (info && (query || status || assigned)) {
      info.textContent = `${visible} of ${rows.length} driver${rows.length !== 1 ? 's' : ''}`;
    }
  }

  searchInput?.addEventListener('input', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);
  assignFilter?.addEventListener('change', applyFilters);
  applyFilters();

}); // end DOMContentLoaded