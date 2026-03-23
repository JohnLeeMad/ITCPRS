/**
 * assets/js/inventory.js
 * Inventory page — modal, edit populate, delete confirm, filter.
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
  document.getElementById('addItemBtn')?.addEventListener('click', () => {
    document.getElementById('addItemForm')?.reset();
    openModal('addItemModal');
  });

  /* ── Edit populate ─────────────────────────────────────────── */
  document.querySelectorAll('.btn-edit-item').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;
      document.getElementById('edit_id').value             = d.id;
      document.getElementById('edit_part_name').value      = d.partName;
      document.getElementById('edit_part_number').value    = d.partNumber;
      document.getElementById('edit_unit').value           = d.unit;
      document.getElementById('edit_quantity').value       = d.quantity;
      document.getElementById('edit_reorder_point').value  = d.reorderPoint;
      document.getElementById('edit_unit_cost').value      = d.unitCost;
      document.getElementById('edit_supplier').value       = d.supplier;
      document.getElementById('edit_location').value       = d.location;
      document.getElementById('edit_notes').value          = d.notes;

      const catSel = document.getElementById('edit_category');
      if (catSel) catSel.value = d.category;

      openModal('editItemModal');
    });
  });

  /* ── Delete confirm ────────────────────────────────────────── */
  document.querySelectorAll('.btn-delete-item').forEach(btn => {
    btn.addEventListener('click', () => {
      ITCAlert.confirmDelete({
        label: `"${btn.dataset.partName}"`,
        onConfirm: () => {
          document.getElementById('deleteFormId').value = btn.dataset.id;
          document.getElementById('deleteForm').submit();
        },
      });
    });
  });

  /* ── Search + filter ───────────────────────────────────────── */
  const searchInput    = document.getElementById('invSearch');
  const categoryFilter = document.getElementById('categoryFilter');
  const stockFilter    = document.getElementById('stockFilter');

  function applyFilters() {
    const q    = (searchInput?.value    || '').toLowerCase().trim();
    const cat  = (categoryFilter?.value || '').toLowerCase();
    const stk  = (stockFilter?.value    || '').toLowerCase();
    const rows = document.querySelectorAll('#invTableBody tr[data-row]');
    let visible = 0;

    rows.forEach(row => {
      const matchQ   = !q   || (row.dataset.part||'').toLowerCase().includes(q)
                            || (row.dataset.partnum||'').toLowerCase().includes(q)
                            || (row.dataset.supplier||'').toLowerCase().includes(q);
      const matchCat = !cat || row.dataset.category === cat;
      const matchStk = !stk || row.dataset.stock     === stk;
      const show = matchQ && matchCat && matchStk;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 && rows.length > 0 ? 'flex' : 'none';

    const info = document.getElementById('countInfo');
    if (info && (q || cat || stk))
      info.textContent = `${visible} of ${rows.length} item${rows.length !== 1 ? 's' : ''}`;
  }

  searchInput?.addEventListener('input', applyFilters);
  categoryFilter?.addEventListener('change', applyFilters);
  stockFilter?.addEventListener('change', applyFilters);
  applyFilters();
});