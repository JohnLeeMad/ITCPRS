/**
 * assets/js/contracts.js
 * Contracts page — modal management, edit populate,
 * cancel + delete confirms, search/filter.
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
    setTimeout(() => el.querySelector('input:not([type=hidden]), select, textarea')?.focus(), 260);
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
     ADD CONTRACT BUTTON
  ═══════════════════════════════════════════════════════ */
  document.getElementById('addContractBtn')?.addEventListener('click', () => {
    document.getElementById('addContractForm')?.reset();
    openModal('addContractModal');
  });

  /* ═══════════════════════════════════════════════════════
     EDIT CONTRACT — populate modal from data-* attributes
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-edit-contract').forEach(btn => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;

      document.getElementById('edit_id').value                = d.id;
      document.getElementById('edit_contract_number').value   = d.contractNumber;
      document.getElementById('edit_client_name').value       = d.clientName;
      document.getElementById('edit_destination').value       = d.destination;
      document.getElementById('edit_origin').value            = d.origin;
      document.getElementById('edit_start_date').value        = d.startDate;
      document.getElementById('edit_end_date').value          = d.endDate;
      document.getElementById('edit_rate').value              = d.rate;
      document.getElementById('edit_notes').value             = d.notes;

      const statusSel = document.getElementById('edit_status');
      if (statusSel) statusSel.value = d.status;

      const truckSel = document.getElementById('edit_truck_id');
      if (truckSel) {
        const tid = d.truckId && d.truckId !== '0' ? d.truckId : '';
        truckSel.value = tid;
      }

      openModal('editContractModal');
    });
  });

  /* ═══════════════════════════════════════════════════════
     CANCEL CONTRACT — soft cancel via ITCAlert confirm
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-cancel-contract').forEach(btn => {
    btn.addEventListener('click', () => {
      ITCAlert.confirm({
        title:       `Cancel Contract "${btn.dataset.contractNumber}"?`,
        text:        'The contract will be marked as Cancelled. This can be undone by editing the contract and changing its status.',
        type:        'warning',
        confirmText: 'Yes, Cancel It',
        cancelText:  'Keep Active',
        onConfirm:   () => {
          document.getElementById('cancelFormId').value = btn.dataset.id;
          document.getElementById('cancelForm').submit();
        },
      });
    });
  });

  /* ═══════════════════════════════════════════════════════
     DELETE CONTRACT — hard delete via ITCAlert confirm
  ═══════════════════════════════════════════════════════ */
  document.querySelectorAll('.btn-delete-contract').forEach(btn => {
    btn.addEventListener('click', () => {
      ITCAlert.confirmDelete({
        label:    `Contract "${btn.dataset.contractNumber}"`,
        onConfirm: () => {
          document.getElementById('deleteFormId').value = btn.dataset.id;
          document.getElementById('deleteForm').submit();
        },
      });
    });
  });

  /* ═══════════════════════════════════════════════════════
     CLIENT-SIDE SEARCH & STATUS FILTER
  ═══════════════════════════════════════════════════════ */
  const searchInput  = document.getElementById('contractSearch');
  const statusFilter = document.getElementById('statusFilter');

  function applyFilters() {
    const query  = (searchInput?.value  || '').toLowerCase().trim();
    const status = (statusFilter?.value || '').toLowerCase().trim();
    const rows   = document.querySelectorAll('#contractsTableBody tr[data-row]');
    let visible  = 0;

    rows.forEach(row => {
      const matchSearch = !query || (
        (row.dataset.contract    || '').toLowerCase().includes(query) ||
        (row.dataset.client      || '').toLowerCase().includes(query) ||
        (row.dataset.destination || '').toLowerCase().includes(query)
      );
      const matchStatus = !status || (row.dataset.status || '') === status;

      const show = matchSearch && matchStatus;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 && rows.length > 0 ? 'flex' : 'none';

    const info = document.getElementById('countInfo');
    if (info && (query || status)) {
      info.textContent = `${visible} of ${rows.length} contract${rows.length !== 1 ? 's' : ''}`;
    }
  }

  searchInput?.addEventListener('input', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);
  applyFilters();

}); // end DOMContentLoaded