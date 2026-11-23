/* Tutor portal behaviour: drawer sidebar, modals, toasts, confirmations. No dependencies. */
(function () {
    'use strict';
    var body = document.body;

    /* ── Sidebar drawer (≤1080px) ── */
    var side = document.getElementById('tpSide');
    var overlay = document.getElementById('tpOverlay');
    function setSide(open) {
        if (!side) return;
        side.classList.toggle('open', open);
        if (overlay) overlay.classList.toggle('open', open);
        body.classList.toggle('tp-lock', open);
        document.querySelectorAll('[data-tp-menu]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        if (open) { var first = side.querySelector('a, button'); if (first) first.focus(); }
    }
    window.toggleSidebar = function () { setSide(!(side && side.classList.contains('open'))); };
    document.querySelectorAll('[data-tp-menu]').forEach(function (b) { b.addEventListener('click', function () { setSide(true); }); });
    document.querySelectorAll('[data-tp-close-menu]').forEach(function (b) { b.addEventListener('click', function () { setSide(false); }); });
    if (overlay) overlay.addEventListener('click', function () { setSide(false); });

    /* ── Modals ── */
    var lastFocus = null;
    function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        lastFocus = document.activeElement;
        m.classList.add('open');
        body.classList.add('tp-lock');
        var f = m.querySelector('input:not([type=hidden]):not([readonly]), select, textarea, button');
        if (f) setTimeout(function () { f.focus(); }, 30);
    }
    function closeModal(m) {
        if (!m) return;
        m.classList.remove('open');
        if (!document.querySelector('.tp-modal.open') && !(side && side.classList.contains('open'))) body.classList.remove('tp-lock');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    window.tpOpenModal = openModal;
    window.tpCloseModal = function (id) { closeModal(document.getElementById(id)); };
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-open]');
        if (opener) { e.preventDefault(); openModal(opener.getAttribute('data-open')); return; }
        var closer = e.target.closest('[data-close]');
        if (closer) { e.preventDefault(); closeModal(closer.closest('.tp-modal')); return; }
        if (e.target.classList && e.target.classList.contains('tp-modal')) closeModal(e.target);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = document.querySelectorAll('.tp-modal.open');
        if (open.length) { closeModal(open[open.length - 1]); return; }
        if (side && side.classList.contains('open')) setSide(false);
    });
    document.querySelectorAll('.tp-modal[data-autoopen]').forEach(function (m) { openModal(m.id); });

    /* ── Confirmations ── */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.hasAttribute('data-confirm') && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    }, true);
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-confirm]');
        if (a && !window.confirm(a.getAttribute('data-confirm'))) e.preventDefault();
    });

    /* ── Toasts (text only, never HTML) ── */
    window.SDAC = window.SDAC || {};
    window.SDAC.showToast = function (msg, type) {
        var c = document.getElementById('toast-container');
        if (!c) return;
        var t = document.createElement('div');
        t.className = 'sdac-toast ' + (type || 'success');
        t.setAttribute('role', 'status');
        var i = document.createElement('i');
        i.className = 'fas ' + (type === 'error' || type === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check');
        var s = document.createElement('span');
        s.textContent = String(msg);
        t.appendChild(i); t.appendChild(s);
        c.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('visible'); });
        setTimeout(function () { t.classList.add('fade-out'); setTimeout(function () { t.remove(); }, 400); }, 4500);
    };
})();
