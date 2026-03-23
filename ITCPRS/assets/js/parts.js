/**
 * assets/js/parts.js
 * Parts Requests page — modal, edit populate, delete confirm, filter.
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  /* ── Modal helpers ─────────────────────────────────────────── */
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    setTimeout(() => el.querySelector('input:not([type=hidden]), select, textarea')?.focus(), 260);
  }
  function closeAllModals() {
    document.querySelectorAll('.itc-modal-overlay.open').forEach(m => m.classList.remove('open'));
  }
  document.querySelectorAll('.itc-modal-overlay [data-close-modal]').forEach(b => b.addEventListener('click', closeAllModals));
  document.querySelectorAll('.itc-modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeAllModals(); }));

  /* ── Add button ────────────────────────────────────────────── */
  document.getElementById('addPartsBtn')?.addEventListener('click', () => {
    document.getElementById('addPartsForm')?.reset();
    openModal('addPartsModal');
  });

  /* ── Edit populate ─────────────────────────────────────────── */
  document.querySelectorAll('.btn-edit-parts').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;
      document.getElementById('edit_id').value        = d.id;
      document.getElementById('edit_part_name').value = d.partName;
      document.getElementById('edit_quantity').value  = d.quantity;
      document.getElementById('edit_unit').value      = d.unit;
      document.getElementById('edit_notes').value     = d.notes;

      const urgSel  = document.getElementById('edit_urgency');
      const statSel = document.getElementById('edit_status');
      const truckSel= document.getElementById('edit_truck_id');
      const invSel  = document.getElementById('edit_inv_item');

      if (urgSel)   urgSel.value   = d.urgency;
      if (statSel)  statSel.value  = d.status;
      if (truckSel) truckSel.value = (d.truckId && d.truckId !== '0') ? d.truckId : '';
      if (invSel)   invSel.value   = (d.inventoryItemId && d.inventoryItemId !== '0') ? d.inventoryItemId : '';

      openModal('editPartsModal');
    });
  });

  /* ── Delete confirm ────────────────────────────────────────── */
  document.querySelectorAll('.btn-delete-parts').forEach(btn => {
    btn.addEventListener('click', () => {
      ITCAlert.confirmDelete({
        label: `Request for "${btn.dataset.partName}"`,
        onConfirm: () => {
          document.getElementById('deleteFormId').value = btn.dataset.id;
          document.getElementById('deleteForm').submit();
        },
      });
    });
  });

  /* ── Search + filter ───────────────────────────────────────── */
  const searchInput  = document.getElementById('partsSearch');
  const urgencyFilter= document.getElementById('urgencyFilter');
  const statusFilter = document.getElementById('statusFilter');

  function applyFilters() {
    const q  = (searchInput?.value  || '').toLowerCase().trim();
    const ug = (urgencyFilter?.value|| '').toLowerCase();
    const st = (statusFilter?.value || '').toLowerCase();
    const rows = document.querySelectorAll('#partsTableBody tr[data-row]');
    let visible = 0;

    rows.forEach(row => {
      const matchQ  = !q  || (row.dataset.part||'').toLowerCase().includes(q) || (row.dataset.truck||'').toLowerCase().includes(q);
      const matchUg = !ug || row.dataset.urgency === ug;
      const matchSt = !st || row.dataset.status  === st;
      const show = matchQ && matchUg && matchSt;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 && rows.length > 0 ? 'flex' : 'none';

    const info = document.getElementById('countInfo');
    if (info && (q || ug || st))
      info.textContent = `${visible} of ${rows.length} request${rows.length !== 1 ? 's' : ''}`;
  }

  searchInput?.addEventListener('input', applyFilters);
  urgencyFilter?.addEventListener('change', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);
  applyFilters();
});