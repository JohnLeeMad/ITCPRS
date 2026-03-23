/**
 * assets/js/users.js
 * User Management page — modal management, photo upload preview,
 * password toggle, ITCAlert delete, search/filter.
 *
 * Wrapped in DOMContentLoaded — never touches DOM before
 * admin_nav_end.php runs (prevents sidebar flash glitch).
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  /* ═══════════════════════════════════════════════════════
     MODAL HELPERS
  ═══════════════════════════════════════════════════════ */
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    setTimeout(() => el.querySelector('input:not([type=hidden]):not([type=file]), select')?.focus(), 260);
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
     PHOTO UPLOAD — live circular preview
  ═══════════════════════════════════════════════════════ */
  function setupFileInput(input) {
    if (!input) return;
    input.addEventListener('change', () => {
      const file          = input.files[0];
      const labelEl       = document.getElementById(input.dataset.labelId);
      const previewEl     = document.getElementById(input.dataset.previewId);
      const placeholderEl = document.getElementById(input.dataset.placeholderId);

      if (!file) return;
      if (labelEl) labelEl.textContent = file.name;

      if (previewEl && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
          if (placeholderEl) placeholderEl.style.display = 'none';
          const curr = document.getElementById('edit_photo_current');
          if (curr) curr.style.display = 'none';
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
     ADD USER BUTTON
  ═══════════════════════════════════════════════════════ */
  document.getElementById('addUserBtn')?.addEventListener('click', () => {
    document.getElementById('addUserForm')?.reset();

    const preview     = document.getElementById('a_photo_preview');
    const placeholder = document.getElementById('a_photo_placeholder');
    const label       = document.getElementById('a_photo_label');
    if (preview)     { preview.src = ''; preview.style.display = 'none'; }
    if (placeholder)   placeholder.style.display = 'flex';
    if (label)         label.textContent = 'Upload photo…';

    openModal('addUserModal');
  });

  /* ═══════════════════════════════════════════════════════
     EDIT USER — populate modal from data-* attributes
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', () => {
      const d    = btn.dataset;
      const isMe = d.isMe === '1';

      // Update modal title to reflect editing self
      const title = document.getElementById('editModalTitle');
      if (title) title.textContent = isMe ? 'Edit My Account' : 'Edit User';

      // Populate fields
      document.getElementById('edit_id').value           = d.id;
      document.getElementById('edit_full_name').value    = d.fullName;
      document.getElementById('edit_username').value     = d.username;
      document.getElementById('edit_email').value        = d.email;
      document.getElementById('edit_contact').value      = d.contact;
      document.getElementById('edit_new_password').value = '';

      const roleSel   = document.getElementById('edit_role');
      const statusSel = document.getElementById('edit_status');
      if (roleSel)   roleSel.value   = d.role;
      if (statusSel) statusSel.value = d.status;

      // Role lock — self cannot change own role
      const roleNote = document.getElementById('edit_role_locked_note');
      if (roleSel) {
        roleSel.disabled = isMe;
        if (roleNote) roleNote.style.display = isMe ? 'block' : 'none';
      }

      // Status lock — self cannot deactivate own account
      const statusNote = document.getElementById('edit_status_locked_note');
      if (statusSel) {
        statusSel.disabled = isMe;
        if (statusNote) statusNote.style.display = isMe ? 'block' : 'none';
      }

      // Reset file inputs
      const fileInput  = document.getElementById('edit_photo');
      const newPreview = document.getElementById('edit_photo_new');
      const editLabel  = document.getElementById('edit_photo_label');
      const removeChk  = document.getElementById('edit_remove_photo');
      const removeWrap = document.getElementById('edit_remove_wrap');

      if (fileInput)  fileInput.value = '';
      if (newPreview) { newPreview.src = ''; newPreview.style.display = 'none'; }
      if (editLabel)    editLabel.textContent = 'Replace photo…';
      if (removeChk)    removeChk.checked = false;

      // Show current photo
      const curr        = document.getElementById('edit_photo_current');
      const placeholder = document.getElementById('edit_photo_placeholder');

      if (d.photo) {
        if (curr)        { curr.src = d.photo; curr.style.display = 'block'; }
        if (placeholder)   placeholder.style.display = 'none';
        if (removeWrap)    removeWrap.style.display = 'flex';
      } else {
        if (curr)        { curr.src = ''; curr.style.display = 'none'; }
        if (placeholder)   placeholder.style.display = 'flex';
        if (removeWrap)    removeWrap.style.display = 'none';
      }

      openModal('editUserModal');
    });
  });

  /* ═══════════════════════════════════════════════════════
     DELETE — ITCAlert confirm → submit hidden form
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-delete-user').forEach(btn => {
    btn.addEventListener('click', () => {
      const name = btn.dataset.name;
      const role = btn.dataset.role;

      let warningText = 'This action cannot be undone. The record will be permanently removed.';
      if (role === 'admin') {
        warningText = 'Warning: this is an administrator account. Make sure at least one admin remains. ' + warningText;
      }

      ITCAlert.confirm({
        title:       `Delete "${name}"?`,
        text:        warningText,
        type:        'danger',
        confirmText: 'Yes, Delete',
        cancelText:  'Keep It',
        onConfirm:   () => {
          document.getElementById('deleteFormId').value = btn.dataset.id;
          document.getElementById('deleteForm').submit();
        },
      });
    });
  });

  /* ═══════════════════════════════════════════════════════
     CLIENT-SIDE SEARCH, ROLE & STATUS FILTER
  ═══════════════════════════════════════════════════════ */
  const searchInput  = document.getElementById('userSearch');
  const roleFilter   = document.getElementById('roleFilter');
  const statusFilter = document.getElementById('statusFilter');

  function applyFilters() {
    const query  = (searchInput?.value  || '').toLowerCase().trim();
    const role   = (roleFilter?.value   || '').toLowerCase().trim();
    const status = (statusFilter?.value || '').toLowerCase().trim();

    const rows = document.querySelectorAll('#usersTableBody tr[data-row]');
    let visible = 0;

    rows.forEach(row => {
      const name   = (row.dataset.name     || '').toLowerCase();
      const uname  = (row.dataset.username || '').toLowerCase();
      const email  = (row.dataset.email    || '').toLowerCase();
      const rl     = (row.dataset.role     || '').toLowerCase();
      const st     = (row.dataset.status   || '').toLowerCase();

      const matchSearch = !query  || name.includes(query) || uname.includes(query) || email.includes(query);
      const matchRole   = !role   || rl === role;
      const matchStatus = !status || st === status;

      const show = matchSearch && matchRole && matchStatus;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 && rows.length > 0 ? 'flex' : 'none';

    const info = document.getElementById('countInfo');
    if (info && (query || role || status)) {
      info.textContent = `${visible} of ${rows.length} user${rows.length !== 1 ? 's' : ''}`;
    }
  }

  searchInput?.addEventListener('input', applyFilters);
  roleFilter?.addEventListener('change', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);
  applyFilters();

}); // end DOMContentLoaded