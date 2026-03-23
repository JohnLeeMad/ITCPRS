/**
 * assets/js/alerts.js
 * ─────────────────────────────────────────────────────────────
 * Reusable alert / dialog system styled for ITCPRS.
 * Brand: Navy #0f2356 / Gold #c8922a
 *
 * USAGE EXAMPLES:
 * ─────────────────────────────────────────────────────────────
 *
 * // Confirm logout (or any destructive action)
 * ITCAlert.confirm({
 *   title: 'Log Out?',
 *   text: 'You will be returned to the login screen.',
 *   confirmText: 'Yes, Log Out',
 *   cancelText: 'Stay',
 *   type: 'danger',
 *   onConfirm: () => { window.location.href = '/modules/auth/logout.php'; }
 * });
 *
 * // Success toast
 * ITCAlert.toast({ title: 'Changes saved!', type: 'success' });
 *
 * // Error toast
 * ITCAlert.toast({ title: 'Something went wrong.', type: 'error' });
 *
 * // Warning toast
 * ITCAlert.toast({ title: 'Low inventory detected.', type: 'warning' });
 *
 * // Info dialog
 * ITCAlert.show({
 *   title: 'Note',
 *   text: 'This record has been archived.',
 *   type: 'info',
 * });
 *
 * // Delete confirmation
 * ITCAlert.confirmDelete({
 *   label: 'Driver "Juan dela Cruz"',
 *   onConfirm: () => { /* your delete logic *\/ }
 * });
 * ─────────────────────────────────────────────────────────────
 */

const ITCAlert = (() => {

  /* ── Inject styles once ─────────────────────────────────── */
  const STYLE_ID = 'itc-alert-styles';

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;

    const css = `
      /* Overlay */
      .itc-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(3px);
        z-index: 9000;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        opacity: 0;
        transition: opacity 0.2s ease;
      }
      .itc-overlay.itc-show { opacity: 1; }

      /* Dialog box */
      .itc-dialog {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 24px 64px rgba(0,0,0,0.22);
        width: 100%;
        max-width: 420px;
        padding: 36px 32px 28px;
        text-align: center;
        transform: scale(0.88) translateY(16px);
        transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s ease;
        opacity: 0;
        position: relative;
      }
      .itc-overlay.itc-show .itc-dialog {
        transform: scale(1) translateY(0);
        opacity: 1;
      }

      /* Icon circle */
      .itc-icon {
        width: 68px; height: 68px;
        border-radius: 50%;
        margin: 0 auto 20px;
        display: flex; align-items: center; justify-content: center;
      }
      .itc-icon svg { width: 32px; height: 32px; }

      .itc-icon-success { background: #d4edda; color: #28a745; }
      .itc-icon-error   { background: #ffebee; color: #d32f2f; }
      .itc-icon-warning { background: #fff8e1; color: #ffc107; }
      .itc-icon-info    { background: #e9ecef; color: #495057; }
      .itc-icon-danger  { background: #ffebee; color: #d32f2f; }
      .itc-icon-logout  { background: #fff8e1; color: #ffc107; }

      /* Title */
      .itc-title {
        font-family: 'Barlow Condensed', 'Barlow', sans-serif;
        font-size: 22px; font-weight: 700;
        color: #212529; letter-spacing: 0.3px;
        margin-bottom: 8px; line-height: 1.2;
      }

      /* Body text */
      .itc-text {
        font-family: 'Barlow', sans-serif;
        font-size: 14px; color: #6c757d;
        line-height: 1.55; margin-bottom: 28px;
      }

      /* Buttons row */
      .itc-actions {
        display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;
      }

      .itc-btn {
        font-family: 'Barlow', sans-serif;
        font-size: 13.5px; font-weight: 600;
        padding: 10px 24px;
        border-radius: 8px; border: none;
        cursor: pointer; min-width: 110px;
        transition: all 0.15s ease;
        letter-spacing: 0.2px;
      }

      .itc-btn-confirm-danger {
        background: #d32f2f; color: #fff;
      }
      .itc-btn-confirm-danger:hover { background: #ef5350; transform: translateY(-1px); }

      .itc-btn-confirm-success {
        background: #28a745; color: #fff;
      }
      .itc-btn-confirm-success:hover { background: #34ce57; transform: translateY(-1px); }

      .itc-btn-confirm-warning {
        background: #ffc107; color: #212529;
      }
      .itc-btn-confirm-warning:hover { background: #ffd54f; transform: translateY(-1px); }

      .itc-btn-confirm-info {
        background: #495057; color: #fff;
      }
      .itc-btn-confirm-info:hover { background: #6c757d; transform: translateY(-1px); }

      .itc-btn-confirm-logout {
        background: #ffc107; color: #212529; font-weight: 700;
      }
      .itc-btn-confirm-logout:hover { background: #ffd54f; transform: translateY(-1px); }

      .itc-btn-cancel {
        background: #f8f9fa; color: #6c757d;
        border: 1px solid #dee2e6;
      }
      .itc-btn-cancel:hover { background: #e9ecef; color: #212529; }

      /* Close X */
      .itc-close {
        position: absolute; top: 14px; right: 16px;
        background: none; border: none;
        color: #adb5bd; cursor: pointer;
        font-size: 20px; line-height: 1;
        padding: 4px 6px;
        border-radius: 6px;
        transition: background 0.12s, color 0.12s;
      }
      .itc-close:hover { background: #f8f9fa; color: #d32f2f; }

      /* ── TOAST ─────────────────────────────────────────── */
      .itc-toast-wrap {
        position: fixed; bottom: 28px; right: 28px;
        z-index: 9100;
        display: flex; flex-direction: column; gap: 10px;
        pointer-events: none;
      }

      .itc-toast {
        display: flex; align-items: center; gap: 12px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 13px 18px 13px 14px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.14);
        min-width: 280px; max-width: 360px;
        pointer-events: auto;
        transform: translateX(110%);
        opacity: 0;
        transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease;
      }

      .itc-toast.itc-toast-show {
        transform: translateX(0);
        opacity: 1;
      }

      .itc-toast.itc-toast-hide {
        transform: translateX(110%);
        opacity: 0;
      }

      .itc-toast-dot {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
      }
      .itc-toast-dot-success { background: #28a745; box-shadow: 0 0 0 3px #d4edda; }
      .itc-toast-dot-error   { background: #d32f2f; box-shadow: 0 0 0 3px #ffebee; }
      .itc-toast-dot-warning { background: #ffc107; box-shadow: 0 0 0 3px #fff8e1; }
      .itc-toast-dot-info    { background: #495057; box-shadow: 0 0 0 3px #e9ecef; }

      .itc-toast-title {
        font-family: 'Barlow', sans-serif;
        font-size: 13.5px; font-weight: 600;
        color: #212529; flex: 1; line-height: 1.4;
      }

      .itc-toast-sub {
        font-size: 12px; color: #6c757d;
        margin-top: 2px;
      }

      .itc-toast-close {
        background: none; border: none;
        color: #adb5bd; cursor: pointer;
        font-size: 16px; padding: 2px 4px;
        border-radius: 4px; transition: background 0.1s;
        flex-shrink: 0;
      }
      .itc-toast-close:hover { background: #f8f9fa; color: #d32f2f; }

      @media (max-width: 480px) {
        .itc-dialog { padding: 28px 20px 22px; }
        .itc-toast-wrap { bottom: 16px; right: 16px; left: 16px; }
        .itc-toast { min-width: unset; max-width: 100%; }
      }
    `;

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = css;
    document.head.appendChild(style);
  }

  /* ── SVG Icons ──────────────────────────────────────────── */
  const icons = {
    success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
    error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    danger:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    logout:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>`,
    delete:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>`,
  };

  /* ── Ensure toast container exists ─────────────────────── */
  function getToastWrap() {
    let wrap = document.getElementById('itc-toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'itc-toast-wrap';
      wrap.className = 'itc-toast-wrap';
      document.body.appendChild(wrap);
    }
    return wrap;
  }

  /* ── Close + destroy overlay ────────────────────────────── */
  function closeDialog(overlay) {
    overlay.classList.remove('itc-show');
    setTimeout(() => overlay.remove(), 280);
  }

  /* ── Public: show() — simple alert dialog ─────────────── */
  function show({ title = '', text = '', type = 'info', confirmText = 'OK', onConfirm = null } = {}) {
    injectStyles();

    const overlay = document.createElement('div');
    overlay.className = 'itc-overlay';

    overlay.innerHTML = `
      <div class="itc-dialog" role="dialog" aria-modal="true">
        <button class="itc-close" aria-label="Close">&times;</button>
        <div class="itc-icon itc-icon-${type}">${icons[type] || icons.info}</div>
        <div class="itc-title">${title}</div>
        ${text ? `<div class="itc-text">${text}</div>` : ''}
        <div class="itc-actions">
          <button class="itc-btn itc-btn-confirm-${type} itc-ok-btn">${confirmText}</button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('itc-show'));

    const close = () => closeDialog(overlay);

    overlay.querySelector('.itc-close').addEventListener('click', close);
    overlay.querySelector('.itc-ok-btn').addEventListener('click', () => {
      close();
      if (typeof onConfirm === 'function') onConfirm();
    });
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
  }

  /* ── Public: confirm() — two-button confirmation dialog ── */
  function confirm({
    title       = 'Are you sure?',
    text        = '',
    type        = 'danger',
    confirmText = 'Confirm',
    cancelText  = 'Cancel',
    onConfirm   = null,
    onCancel    = null,
  } = {}) {
    injectStyles();

    const overlay = document.createElement('div');
    overlay.className = 'itc-overlay';

    overlay.innerHTML = `
      <div class="itc-dialog" role="alertdialog" aria-modal="true">
        <button class="itc-close" aria-label="Close">&times;</button>
        <div class="itc-icon itc-icon-${type}">${icons[type] || icons.warning}</div>
        <div class="itc-title">${title}</div>
        ${text ? `<div class="itc-text">${text}</div>` : ''}
        <div class="itc-actions">
          <button class="itc-btn itc-btn-cancel itc-cancel-btn">${cancelText}</button>
          <button class="itc-btn itc-btn-confirm-${type} itc-confirm-btn">${confirmText}</button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('itc-show'));

    const close = () => closeDialog(overlay);

    overlay.querySelector('.itc-close').addEventListener('click', () => { close(); if (typeof onCancel === 'function') onCancel(); });
    overlay.querySelector('.itc-cancel-btn').addEventListener('click', () => { close(); if (typeof onCancel === 'function') onCancel(); });
    overlay.querySelector('.itc-confirm-btn').addEventListener('click', () => { close(); if (typeof onConfirm === 'function') onConfirm(); });
    overlay.addEventListener('click', (e) => { if (e.target === overlay) { close(); if (typeof onCancel === 'function') onCancel(); } });

    // Focus confirm button for keyboard accessibility
    setTimeout(() => overlay.querySelector('.itc-confirm-btn')?.focus(), 100);
  }

  /* ── Public: confirmDelete() — preset delete dialog ──── */
  function confirmDelete({ label = 'this record', onConfirm = null } = {}) {
    confirm({
      title:       'Delete ' + label + '?',
      text:        'This action cannot be undone. The record will be permanently removed.',
      type:        'danger',
      confirmText: 'Yes, Delete',
      cancelText:  'Keep It',
      onConfirm,
    });
  }

  /* ── Public: confirmLogout() — preset logout dialog ─────── */
  function confirmLogout({ href = '/modules/auth/logout.php' } = {}) {
    confirm({
      title:       'Log Out?',
      text:        'You will be signed out and returned to the login screen.',
      type:        'logout',
      confirmText: 'Yes, Log Out',
      cancelText:  'Stay',
      onConfirm:   () => { window.location.href = href; },
    });
  }

  /* ── Public: toast() — slide-in toast notification ──────── */
  function toast({
    title    = '',
    subtitle = '',
    type     = 'info',
    duration = 3500,
  } = {}) {
    injectStyles();
    const wrap = getToastWrap();

    const el = document.createElement('div');
    el.className = 'itc-toast';
    el.innerHTML = `
      <span class="itc-toast-dot itc-toast-dot-${type}"></span>
      <div style="flex:1">
        <div class="itc-toast-title">${title}</div>
        ${subtitle ? `<div class="itc-toast-sub">${subtitle}</div>` : ''}
      </div>
      <button class="itc-toast-close" aria-label="Dismiss">&times;</button>
    `;

    wrap.appendChild(el);
    requestAnimationFrame(() => el.classList.add('itc-toast-show'));

    const dismiss = () => {
      el.classList.add('itc-toast-hide');
      el.classList.remove('itc-toast-show');
      setTimeout(() => el.remove(), 350);
    };

    el.querySelector('.itc-toast-close').addEventListener('click', dismiss);

    if (duration > 0) setTimeout(dismiss, duration);

    return { dismiss };
  }

  /* ── Expose public API ──────────────────────────────────── */
  return { show, confirm, confirmDelete, confirmLogout, toast };

})();