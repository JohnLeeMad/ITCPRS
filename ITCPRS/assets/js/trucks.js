/**
 * assets/js/trucks.js
 * Trucks page — custom crew picker, peek popups, file upload,
 * modal management, ITCAlert delete, search/filter.
 *
 * All in DOMContentLoaded — never touches DOM before nav scripts.
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  /* ═══════════════════════════════════════════════════════
     CREW PICKER
     Custom searchable dropdown with driver photo + name.
     Replaces native <select> for crew assignment slots.
     Uses window.CREW_DRIVERS (set by trucks.php inline script).
  ═══════════════════════════════════════════════════════ */
  const ALL_DRIVERS = window.CREW_DRIVERS || [];

  // Track each picker's state
  const pickers = new Map();

  // Stub — replaced after cpPreview element is created below
  let hideCpPreview = () => {};
  let showCpPreview = () => {};

  function getInitials(name) {
    return (name || '').trim().split(/\s+/).slice(0, 2)
      .map(w => w[0] || '').join('').toUpperCase();
  }

  function avatarHTML(driver, size = 28) {
    if (driver.photo) {
      return `<img class="cp-avatar-img" src="${escAttr(driver.photo)}"
                   alt="${escAttr(driver.name)}"
                   style="width:${size}px;height:${size}px;" />`;
    }
    return `<div class="cp-avatar-initials" style="width:${size}px;height:${size}px;">
              ${escHTML(getInitials(driver.name))}
            </div>`;
  }

  function escHTML(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function escAttr(s) { return escHTML(s); }

  function buildPicker(el) {
    const inputId    = el.dataset.input;
    const selectedId = parseInt(el.dataset.selected || '0', 10) || 0;

    const state = { el, inputId, selectedId, open: false, siblingPickerId: null };
    pickers.set(el, state);

    // Build DOM
    el.innerHTML = `
      <div class="cp-trigger" tabindex="0" role="combobox" aria-haspopup="listbox" aria-expanded="false">
        <div class="cp-value">
          <div class="cp-value-avatar"></div>
          <span class="cp-value-text">— Unassigned —</span>
        </div>
        <svg class="cp-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6,9 12,15 18,9"/>
        </svg>
      </div>
      <div class="cp-dropdown" role="listbox">
        <div class="cp-search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input class="cp-search" type="text" placeholder="Search driver…" autocomplete="off" />
        </div>
        <div class="cp-list"></div>
      </div>
    `;

    setPickerValue(el, selectedId, false);
    attachPickerEvents(el, state);
  }

  function setPickerValue(el, driverId, notifySibling = true) {
    const state    = pickers.get(el);
    if (!state) return;
    state.selectedId = driverId;

    const input    = document.getElementById(state.inputId);
    if (input) input.value = driverId || '';

    const trigger  = el.querySelector('.cp-trigger');
    const valAvatar= el.querySelector('.cp-value-avatar');
    const valText  = el.querySelector('.cp-value-text');

    const driver   = ALL_DRIVERS.find(d => d.id === driverId);

    if (driver) {
      valAvatar.innerHTML  = avatarHTML(driver, 24);
      valAvatar.style.display = 'block';
      valText.textContent  = driver.name;
      trigger.classList.add('cp-has-value');
    } else {
      valAvatar.innerHTML  = '';
      valAvatar.style.display = 'none';
      valText.textContent  = '— Unassigned —';
      trigger.classList.remove('cp-has-value');
    }

    // Notify sibling to refresh disabled states
    if (notifySibling && state.siblingPickerId) {
      const sibEl = document.getElementById(state.siblingPickerId);
      if (sibEl) renderPickerList(sibEl);
    }
  }

  function renderPickerList(el, query = '') {
    const state   = pickers.get(el);
    if (!state) return;

    // Find sibling's selected id to grey it out
    let siblingSelectedId = 0;
    if (state.siblingPickerId) {
      const sib = document.getElementById(state.siblingPickerId);
      if (sib) siblingSelectedId = pickers.get(sib)?.selectedId || 0;
    }

    const q     = query.toLowerCase().trim();
    const list  = el.querySelector('.cp-list');
    if (!list) return;

    const filtered = ALL_DRIVERS.filter(d =>
      !q || d.name.toLowerCase().includes(q)
    );

    // Unassign option
    let html = `
      <div class="cp-option cp-option-clear ${state.selectedId === 0 ? 'cp-selected' : ''}"
           data-id="0">
        <div class="cp-opt-avatar cp-opt-avatar-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" width="16" height="16">
            <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
          </svg>
        </div>
        <span class="cp-opt-name">— Unassigned —</span>
      </div>`;

    if (filtered.length === 0) {
      html += `<div class="cp-no-results">No drivers match "${escHTML(query)}"</div>`;
    } else {
      filtered.forEach(d => {
        const isSelected  = d.id === state.selectedId;
        const isDisabled  = d.id === siblingSelectedId && siblingSelectedId !== 0;
        html += `
          <div class="cp-option ${isSelected ? 'cp-selected' : ''} ${isDisabled ? 'cp-disabled' : ''}"
               data-id="${d.id}"
               data-photo="${escAttr(d.photo)}"
               data-name="${escAttr(d.name)}">
            ${avatarHTML(d, 42)}
            <div class="cp-opt-info">
              <span class="cp-opt-name">${escHTML(d.name)}</span>
              ${isDisabled ? '<span class="cp-opt-tag">Assigned to other slot</span>' : ''}
            </div>
            ${isSelected ? `<svg class="cp-opt-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>` : ''}
          </div>`;
      });
    }

    list.innerHTML = html;

    // Click handlers
    list.querySelectorAll('.cp-option:not(.cp-disabled)').forEach(opt => {
      opt.addEventListener('click', e => {
        e.stopPropagation();
        const id = parseInt(opt.dataset.id, 10) || 0;
        setPickerValue(el, id);
        renderPickerList(el, '');
        closePicker(el);
        hideCpPreview();
      });
    });

    // Hover photo preview on driver options (not the clear option)
    list.querySelectorAll('.cp-option:not(.cp-option-clear):not(.cp-disabled)').forEach(opt => {
      opt.addEventListener('mouseenter', () => showCpPreview(opt));
      opt.addEventListener('mouseleave', hideCpPreview);
    });
  }

  function openPicker(el) {
    const state   = pickers.get(el);
    if (!state) return;
    state.open = true;

    el.querySelector('.cp-trigger')?.setAttribute('aria-expanded', 'true');
    el.classList.add('cp-open');

    const search = el.querySelector('.cp-search');
    if (search) { search.value = ''; search.focus(); }
    renderPickerList(el, '');
  }

  function closePicker(el) {
    const state = pickers.get(el);
    if (!state) return;
    state.open = false;
    el.querySelector('.cp-trigger')?.setAttribute('aria-expanded', 'false');
    el.classList.remove('cp-open');
    hideCpPreview();
  }

  function closeAllPickers() {
    pickers.forEach((state, el) => closePicker(el));
  }

  function attachPickerEvents(el, state) {
    const trigger = el.querySelector('.cp-trigger');
    const search  = el.querySelector('.cp-search');

    trigger?.addEventListener('click', e => {
      e.stopPropagation();
      if (state.open) { closePicker(el); } else { openPicker(el); }
    });

    trigger?.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(el); }
      if (e.key === 'Escape') closePicker(el);
    });

    search?.addEventListener('input', () => renderPickerList(el, search.value));
    search?.addEventListener('keydown', e => {
      if (e.key === 'Escape') closePicker(el);
    });

    // Stop dropdown clicks from bubbling to document close handler
    el.querySelector('.cp-dropdown')?.addEventListener('click', e => e.stopPropagation());
  }

  // Link two pickers as siblings (they can't share the same driver)
  function linkPickers(pickerAId, pickerBId) {
    const a = document.getElementById(pickerAId);
    const b = document.getElementById(pickerBId);
    if (!a || !b) return;
    const sa = pickers.get(a);
    const sb = pickers.get(b);
    if (sa) sa.siblingPickerId = pickerBId;
    if (sb) sb.siblingPickerId = pickerAId;
  }

  // Reset a picker to unassigned and clear its search
  function resetPicker(el) {
    if (!el) return;
    setPickerValue(el, 0, false);
    const search = el.querySelector('.cp-search');
    if (search) search.value = '';
    closePicker(el);
  }

  // Init all pickers on the page
  document.querySelectorAll('.crew-picker').forEach(el => buildPicker(el));

  // Link sibling pairs so they block each other
  linkPickers('a_driver_picker',    'a_helper_picker');
  linkPickers('edit_driver_picker', 'edit_helper_picker');

  // Close all pickers when clicking outside
  document.addEventListener('click', closeAllPickers);


  /* ═══════════════════════════════════════════════════════
     CREW PICKER — HOVER PHOTO PREVIEW
     A floating card that appears to the right of the
     dropdown when hovering a driver option.
  ═══════════════════════════════════════════════════════ */
  const cpPreview = document.createElement('div');
  cpPreview.id        = 'cpPhotoPreview';
  cpPreview.className = 'cp-photo-preview';
  cpPreview.innerHTML = `
    <div class="cp-photo-preview-img-wrap">
      <img id="cpPreviewImg" src="" alt="" />
      <div id="cpPreviewInitials" class="cp-photo-preview-initials"></div>
    </div>
    <div class="cp-photo-preview-name" id="cpPreviewName"></div>
  `;
  document.body.appendChild(cpPreview);

  showCpPreview = function(optEl) {
    const photo = optEl.dataset.photo || '';
    const name  = optEl.dataset.name  || '';

    document.getElementById('cpPreviewName').textContent = name;

    const img      = document.getElementById('cpPreviewImg');
    const initials = document.getElementById('cpPreviewInitials');

    if (photo) {
      img.src = photo;
      img.style.display      = 'block';
      initials.style.display = 'none';
    } else {
      img.src = '';
      img.style.display      = 'none';
      initials.textContent   = getInitials(name);
      initials.style.display = 'flex';
    }

    // Position: to the right of the option row, vertically centred on it
    const rect   = optEl.getBoundingClientRect();
    const pw     = 180;
    const ph     = 220;
    const GAP    = 10;
    let   left   = rect.right + GAP;
    let   top    = rect.top + rect.height / 2 - ph / 2;

    if (left + pw > window.innerWidth - 8) {
      left = rect.left - pw - GAP;
    }
    top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));

    cpPreview.style.left    = left + 'px';
    cpPreview.style.top     = top  + 'px';
    cpPreview.classList.add('cp-photo-preview-visible');
  };

  hideCpPreview = function() {
    cpPreview.classList.remove('cp-photo-preview-visible');
  };


  /* ═══════════════════════════════════════════════════════
     PEEK POPUP
  ═══════════════════════════════════════════════════════ */
  const peek     = document.getElementById('truckPeek');
  const peekImg  = document.getElementById('truckPeekImg');
  const peekNoImg= document.getElementById('truckPeekNoImg');
  const peekLbl  = document.getElementById('truckPeekLabel');
  const peekSub  = document.getElementById('truckPeekSub');

  let peekTimeout = null;
  let peekVisible = false;

  function showPeek(trigger, x, y) {
    const type  = trigger.dataset.peekType;
    const img   = trigger.dataset.peekImg;
    const label = trigger.dataset.peekLabel || '';
    const sub   = trigger.dataset.peekSub   || '';

    peekLbl.textContent = label;
    peekSub.textContent = sub;

    if (type === 'crew') {
      const role = trigger.dataset.peekRole || '';
      if (img) {
        peekImg.src = img;
        peekImg.style.display = 'block';
        peekNoImg.style.display = 'none';
      } else {
        peekImg.src = '';
        peekImg.style.display = 'none';
        peekNoImg.innerHTML = `
          <div class="peek-crew-avatar peek-crew-${role.toLowerCase()}">
            ${label.charAt(0).toUpperCase()}
          </div>
          <span>${label}</span>
          <small>${role}</small>`;
        peekNoImg.style.display = 'flex';
      }
    } else {
      if (img) {
        peekImg.src = img;
        peekImg.style.display = 'block';
        peekNoImg.style.display = 'none';
      } else {
        peekImg.src = '';
        peekImg.style.display = 'none';
        peekNoImg.style.display = 'flex';
      }
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
    const pw = peek.offsetWidth || 220, ph = peek.offsetHeight || 160;
    const GAP = 12;
    let left = x + GAP, top = y - ph / 2;
    if (left + pw > window.innerWidth - 8) left = x - pw - GAP;
    top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));
    peek.style.left = left + 'px';
    peek.style.top  = top  + 'px';
  }

  document.querySelectorAll('.peek-trigger').forEach(trigger => {
    trigger.addEventListener('mouseenter', e => {
      clearTimeout(peekTimeout);
      peekTimeout = setTimeout(() => showPeek(trigger, e.clientX, e.clientY), 120);
    });
    trigger.addEventListener('mousemove', e => { if (peekVisible) positionPeek(e.clientX, e.clientY); });
    trigger.addEventListener('mouseleave', () => { clearTimeout(peekTimeout); peekTimeout = setTimeout(hidePeek, 80); });
    trigger.addEventListener('click', e => {
      if (peekVisible) { hidePeek(); }
      else { const r = trigger.getBoundingClientRect(); showPeek(trigger, r.right, r.top + r.height / 2); e.stopPropagation(); }
    });
  });

  document.addEventListener('click', () => { if (peekVisible) hidePeek(); });
  peek?.addEventListener('mouseenter', () => clearTimeout(peekTimeout));
  peek?.addEventListener('mouseleave', () => { peekTimeout = setTimeout(hidePeek, 80); });


  /* ═══════════════════════════════════════════════════════
     FILE UPLOAD — live preview
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.file-upload-input').forEach(input => {
    input.addEventListener('change', () => {
      const file    = input.files[0];
      const labelEl = document.getElementById(input.dataset.labelId);
      const prevEl  = document.getElementById(input.dataset.previewId);
      if (!file) return;
      if (labelEl) labelEl.textContent = file.name;
      if (prevEl && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { prevEl.src = e.target.result; prevEl.style.display = 'block'; };
        reader.readAsDataURL(file);
      }
    });
  });


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
    document.querySelectorAll('.itc-modal-overlay.open').forEach(m => m.classList.remove('open'));
    closeAllPickers();
  }


  /* ═══════════════════════════════════════════════════════
     ADD TRUCK BUTTON
  ═══════════════════════════════════════════════════════ */
  document.getElementById('addTruckBtn')?.addEventListener('click', () => {
    document.getElementById('addTruckForm')?.reset();

    // Clear file previews
    ['a_photo_plate_preview', 'a_photo_truck_preview'].forEach(id => {
      const el = document.getElementById(id);
      if (el) { el.src = ''; el.style.display = 'none'; }
    });
    document.getElementById('a_photo_plate_label').textContent = 'Choose image…';
    document.getElementById('a_photo_truck_label').textContent  = 'Choose image…';

    // Reset pickers
    resetPicker(document.getElementById('a_driver_picker'));
    resetPicker(document.getElementById('a_helper_picker'));

    openModal('addTruckModal');
  });


  /* ═══════════════════════════════════════════════════════
     EDIT TRUCK — populate modal from row data-* attributes
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-edit-truck').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;

      document.getElementById('edit_id').value       = d.id;
      document.getElementById('edit_plate').value    = d.plate;
      document.getElementById('edit_make').value     = d.make;
      document.getElementById('edit_model').value    = d.model;
      document.getElementById('edit_year').value     = d.year;
      document.getElementById('edit_color').value    = d.color;
      document.getElementById('edit_chassis').value  = d.chassis;
      document.getElementById('edit_engine').value   = d.engine;
      document.getElementById('edit_capacity').value = d.capacity;
      document.getElementById('edit_notes').value    = d.notes;

      const statusSel = document.getElementById('edit_status');
      if (statusSel) statusSel.value = d.status;

      // Set crew pickers
      const driverId = parseInt(d.driverId || '0', 10) || 0;
      const helperId = parseInt(d.helperId || '0', 10) || 0;

      const driverPicker = document.getElementById('edit_driver_picker');
      const helperPicker = document.getElementById('edit_helper_picker');

      resetPicker(driverPicker);
      resetPicker(helperPicker);
      setPickerValue(driverPicker, driverId);
      setPickerValue(helperPicker, helperId);

      // Reset file inputs + previews
      ['edit_photo_plate', 'edit_photo_truck'].forEach(id => {
        const inp = document.getElementById(id);
        if (inp) inp.value = '';
      });
      document.getElementById('edit_photo_plate_preview').style.display = 'none';
      document.getElementById('edit_photo_truck_preview').style.display = 'none';
      document.getElementById('edit_photo_plate_label').textContent = 'Replace image…';
      document.getElementById('edit_photo_truck_label').textContent  = 'Replace image…';

      // Remove checkboxes
      const rmPlate = document.getElementById('edit_remove_plate');
      const rmTruck = document.getElementById('edit_remove_truck');
      if (rmPlate) rmPlate.checked = false;
      if (rmTruck) rmTruck.checked = false;

      // Current photos
      const plateWrap = document.getElementById('edit_plate_current_wrap');
      const truckWrap = document.getElementById('edit_truck_current_wrap');
      const plateCurr = document.getElementById('edit_plate_current');
      const truckCurr = document.getElementById('edit_truck_current');

      if (d.photoPlate) { plateCurr.src = d.photoPlate; plateWrap.style.display = 'flex'; }
      else              { plateWrap.style.display = 'none'; plateCurr.src = ''; }

      if (d.photoTruck) { truckCurr.src = d.photoTruck; truckWrap.style.display = 'flex'; }
      else              { truckWrap.style.display = 'none'; truckCurr.src = ''; }

      openModal('editTruckModal');
    });
  });


  /* ═══════════════════════════════════════════════════════
     DELETE — ITCAlert confirm
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-delete-truck').forEach(btn => {
    btn.addEventListener('click', () => {
      ITCAlert.confirmDelete({
        label: `Truck "${btn.dataset.plate}"`,
        onConfirm: () => {
          document.getElementById('deleteFormId').value = btn.dataset.id;
          document.getElementById('deleteForm').submit();
        },
      });
    });
  });


  /* ═══════════════════════════════════════════════════════
     CLOSE BUTTONS & OVERLAY CLICK
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.itc-modal-overlay [data-close-modal]').forEach(btn => {
    btn.addEventListener('click', closeAllModals);
  });
  document.querySelectorAll('.itc-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeAllModals(); });
  });


  /* ═══════════════════════════════════════════════════════
     TABLE SEARCH & STATUS FILTER
  ═══════════════════════════════════════════════════════ */
  const searchInput  = document.getElementById('truckSearch');
  const statusFilter = document.getElementById('statusFilter');

  function applyFilters() {
    const query  = (searchInput?.value  || '').toLowerCase().trim();
    const status = (statusFilter?.value || '').toLowerCase().trim();
    const rows   = document.querySelectorAll('#trucksTableBody tr[data-row]');
    let visible  = 0;

    rows.forEach(row => {
      const match =
        (!query || ['plate','make','model','driver','helper'].some(
          k => (row.dataset[k] || '').toLowerCase().includes(query)
        )) &&
        (!status || (row.dataset.status || '') === status);

      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 && rows.length > 0 ? 'flex' : 'none';

    const info = document.getElementById('countInfo');
    if (info && (query || status)) {
      info.textContent = `${visible} of ${rows.length} truck${rows.length !== 1 ? 's' : ''}`;
    }
  }

  searchInput?.addEventListener('input', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);
  applyFilters();

}); // end DOMContentLoaded