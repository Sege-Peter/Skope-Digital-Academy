/*
 * Admin dropdowns: replaces the browser's own option list with a styled, accessible one.
 * The real <select> stays in the page (hidden) and keeps the value, so forms, validation and
 * existing "change" listeners work unchanged. Opt out with <select data-native>.
 * An option text "Title – description" shows as a title with a smaller description line.
 */
(function () {
    'use strict';
    var SEP = /\s+[–—]\s+/;
    var openOne = null;

    function parts(text) {
        var m = text.split(SEP);
        return m.length > 1 ? { t: m[0], d: m.slice(1).join(' – ') } : { t: text, d: '' };
    }

    function enhance(sel) {
        if (sel.dataset.axs || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native')) return;
        sel.dataset.axs = '1';
        var wrap = document.createElement('div');
        wrap.className = 'axs';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('axs-native');
        sel.tabIndex = -1;
        sel.setAttribute('aria-hidden', 'true');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'axs-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        var lab = sel.id && document.querySelector('label[for="' + sel.id + '"]');
        if (lab) { lab.addEventListener('click', function (e) { e.preventDefault(); btn.focus(); }); btn.setAttribute('aria-label', lab.textContent.trim()); }
        btn.innerHTML = '<span class="axs-val"></span><i class="fas fa-chevron-down axs-car" aria-hidden="true"></i>';
        wrap.appendChild(btn);

        var list = document.createElement('ul');
        list.className = 'axs-list';
        list.setAttribute('role', 'listbox');
        list.tabIndex = -1;
        list.hidden = true;
        var host = sel.closest('dialog') || document.body;
        host.appendChild(list);

        var active = -1, typed = '', typedT;

        function opts() { return Array.prototype.slice.call(sel.options); }
        function label() {
            var o = sel.options[sel.selectedIndex];
            btn.querySelector('.axs-val').textContent = o ? parts(o.text).t : '';
            btn.disabled = sel.disabled;
            wrap.classList.toggle('is-disabled', sel.disabled);
        }
        function build() {
            list.innerHTML = '';
            opts().forEach(function (o, i) {
                if (o.hidden) return;
                var p = parts(o.text), li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.i = i;
                li.id = (sel.id || sel.name || 'axs') + '-o' + i + '-' + Math.random().toString(36).slice(2, 6);
                if (o.disabled) li.setAttribute('aria-disabled', 'true');
                li.innerHTML = '<span class="axs-t"></span>' + (p.d ? '<span class="axs-d"></span>' : '') + '<i class="fas fa-check axs-tick" aria-hidden="true"></i>';
                li.querySelector('.axs-t').textContent = p.t;
                if (p.d) li.querySelector('.axs-d').textContent = p.d;
                li.addEventListener('mousedown', function (e) { e.preventDefault(); });
                li.addEventListener('click', function () { choose(i); });
                li.addEventListener('mousemove', function () { setActive(i); });
                list.appendChild(li);
            });
        }
        function items() { return Array.prototype.slice.call(list.children); }
        function setActive(i) {
            active = i;
            items().forEach(function (li) { li.classList.toggle('is-active', +li.dataset.i === i); });
            var cur = list.querySelector('[data-i="' + i + '"]');
            if (cur) { btn.setAttribute('aria-activedescendant', cur.id); cur.scrollIntoView({ block: 'nearest' }); }
        }
        function place() {
            var r = btn.getBoundingClientRect(), vh = window.innerHeight, below = vh - r.bottom - 12, above = r.top - 12;
            var fixedHost = host === document.body ? null : host.getBoundingClientRect();
            list.style.minWidth = r.width + 'px';
            list.style.maxWidth = Math.max(r.width, Math.min(520, window.innerWidth - 24)) + 'px';
            var up = below < 220 && above > below;
            list.style.maxHeight = Math.min(320, up ? above : below) + 'px';
            var left = Math.min(r.left, window.innerWidth - list.offsetWidth - 12);
            var top = up ? r.top - list.offsetHeight - 6 : r.bottom + 6;
            if (fixedHost && getComputedStyle(host).transform !== 'none') { left -= fixedHost.left; top -= fixedHost.top; }
            list.style.left = Math.max(12, left) + 'px';
            list.style.top = Math.max(8, top) + 'px';
            list.classList.toggle('is-up', up);
        }
        function open() {
            if (sel.disabled || !list.hidden) return;
            if (openOne && openOne !== close) openOne();
            build();
            list.hidden = false;
            wrap.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            items().forEach(function (li) { li.setAttribute('aria-selected', +li.dataset.i === sel.selectedIndex ? 'true' : 'false'); });
            place();
            setActive(sel.selectedIndex);
            openOne = close;
        }
        function close() {
            if (list.hidden) return;
            list.hidden = true;
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.removeAttribute('aria-activedescendant');
            if (openOne === close) openOne = null;
        }
        function choose(i) {
            var o = sel.options[i];
            if (!o || o.disabled) return;
            var changed = sel.selectedIndex !== i;
            sel.selectedIndex = i;
            label();
            close();
            btn.focus();
            if (changed) {
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        function move(step) {
            var all = opts(), i = active;
            for (var n = 0; n < all.length; n++) {
                i = (i + step + all.length) % all.length;
                if (!all[i].disabled && !all[i].hidden) break;
            }
            if (list.hidden) choose(i); else setActive(i);
        }

        btn.addEventListener('click', function () { list.hidden ? open() : close(); });
        btn.addEventListener('keydown', function (e) {
            var k = e.key;
            if (k === 'ArrowDown' || k === 'ArrowUp') { e.preventDefault(); if (list.hidden && e.altKey) open(); else move(k === 'ArrowDown' ? 1 : -1); }
            else if (k === 'Enter' || k === ' ') { e.preventDefault(); if (list.hidden) open(); else choose(active); }
            else if (k === 'Escape') { if (!list.hidden) { e.preventDefault(); e.stopPropagation(); close(); } }
            else if (k === 'Tab') close();
            else if (k === 'Home' || k === 'End') { e.preventDefault(); setActive(k === 'Home' ? 0 : sel.options.length - 1); if (list.hidden) choose(active); }
            else if (k.length === 1 && /\S/.test(k)) {
                typed += k.toLowerCase(); clearTimeout(typedT); typedT = setTimeout(function () { typed = ''; }, 600);
                var hit = opts().findIndex(function (o) { return !o.disabled && o.text.toLowerCase().indexOf(typed) === 0; });
                if (hit > -1) { if (list.hidden) choose(hit); else setActive(hit); }
            }
        });
        btn.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== list) close(); }, 120); });

        // Keep in step with the real select (programmatic changes, new options, disabled state, form reset)
        sel.addEventListener('change', label);
        new MutationObserver(function () { label(); if (!list.hidden) build(); }).observe(sel, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
        var form = sel.form;
        if (form) form.addEventListener('reset', function () { setTimeout(label, 0); });
        sel.addEventListener('invalid', function () { wrap.classList.add('is-invalid'); btn.focus(); });
        sel.addEventListener('change', function () { wrap.classList.remove('is-invalid'); });
        var desc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
        try { Object.defineProperty(sel, 'value', { get: function () { return desc.get.call(this); }, set: function (v) { desc.set.call(this, v); label(); } }); } catch (e) {}
        label();
    }

    function closeAll(e) {
        if (!openOne) return;
        if (e && e.target && e.target.closest && (e.target.closest('.axs-list') || e.target.closest('.axs.is-open'))) return;
        openOne();
    }
    document.addEventListener('mousedown', closeAll);
    window.addEventListener('resize', function () { closeAll(); });
    document.addEventListener('scroll', function (e) { if (!(e.target.closest && e.target.closest('.axs-list'))) closeAll(); }, true);

    function scan(root) { (root || document).querySelectorAll('select').forEach(enhance); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(); }); else scan();
    new MutationObserver(function (ms) {
        ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType !== 1) return; if (n.tagName === 'SELECT') enhance(n); else if (n.querySelectorAll) scan(n); }); });
    }).observe(document.documentElement, { childList: true, subtree: true });
    window.axSelectScan = scan;
})();
