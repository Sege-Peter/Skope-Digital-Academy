/**
 * Skope Digital Academy - Gmail-style admin webmail (admin/mail). API: admin/api-mailbox.php.
 * Ported from the HAMASTA MEDIA admin mailbox (js/hm-mailbox.js) for one academy mailbox.
 * Calendar, AI help and the cPanel forwarding/auto-reply/filter panes are not part of this port; on Gmail those
 * settings are links to Gmail's own settings (see the Mailbox connection panel, #settings).
 *
 * Renders into #mxRoot, keeps listeners on the document (delegation), and puts floating elements
 * (compose window, menus, dialogs, toasts, phone action bar) in a host appended to <body>.
 */
(function () {
    'use strict';
    const ROOT = document.getElementById('mxRoot');
    if (!ROOT) return;
    const API = ROOT.dataset.api || 'api-mailbox';
    const CSRF = ROOT.dataset.csrf || '';
    const BRAND = ROOT.dataset.brand || 'Skope Digital Academy';
    const AV = ['#0a6cf0', '#8b5cf6', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#6366f1', '#14b8a6', '#ff9b26', '#64748b'];
    const $ = (s, r) => (r || document).querySelector(s);
    const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const isPhone = () => window.matchMedia('(max-width: 700px)').matches;
    const hash = (s) => { let h = 0; for (const c of String(s)) h = (h * 31 + c.charCodeAt(0)) | 0; return Math.abs(h); };
    const colorOf = (s) => AV[hash(String(s).toLowerCase()) % AV.length];
    const kb = (n) => n < 1024 ? n + ' B' : n < 1048576 ? Math.round(n / 1024) + ' KB' : (n / 1048576).toFixed(1) + ' MB';
    const pad = (n) => String(n).padStart(2, '0');

    /** "Name <a@b>" / "a@b" lists -> [{name, email}] (commas inside quotes kept) */
    function parseAddrs(str) {
        const out = [];
        String(str || '').split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/).forEach((part) => {
            const p = part.trim(); if (!p) return;
            const m = p.match(/^\s*"?([^"<]*?)"?\s*<([^>]+)>\s*$/);
            if (m) out.push({ name: m[1].trim(), email: m[2].trim().toLowerCase() });
            else if (/@/.test(p)) out.push({ name: '', email: p.replace(/[<>]/g, '').trim().toLowerCase() });
        });
        return out;
    }
    const nameOf = (a) => (a && (a.name || a.email.split('@')[0])) || '';
    const initials = (s) => { const w = String(s || '?').replace(/[^\p{L}\p{N} ]/gu, ' ').trim().split(/\s+/); return ((w[0] || '?')[0] + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase(); };
    const avatar = (label, key, cls, style) => `<div class="${cls || 'mx-av'}" style="background:${colorOf(key || label)};${style || ''}"><span>${esc(initials(label))}</span></div>`;

    function parseDate(s) { return s ? new Date(s) : null; }
    function shortDate(s) {
        const d = parseDate(s); if (!d || isNaN(d)) return '';
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (d.getFullYear() === now.getFullYear()) return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
        return d.toLocaleDateString([], { day: 'numeric', month: 'numeric', year: '2-digit' });
    }
    function longDate(s) {
        const d = parseDate(s); if (!d || isNaN(d)) return '';
        const ago = Math.round((Date.now() - d) / 60000);
        const rel = ago < 1 ? 'just now' : ago < 60 ? ago + ' min ago' : ago < 1440 ? Math.round(ago / 60) + ' hours ago' : ago < 2880 ? 'yesterday' : Math.round(ago / 1440) + ' days ago';
        return d.toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }) + ` (${rel})`;
    }
    const localInput = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    const sqlDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const sqlStamp = (d) => `${sqlDate(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;

    async function api(action, params, post) {
        params = params || {};
        let res;
        try {
            if (post) {
                res = await fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(Object.assign({ action }, params)) });
            } else {
                const q = new URLSearchParams(Object.assign({ action }, params));
                res = await fetch(API + '?' + q, { credentials: 'same-origin', cache: 'no-store' });
            }
        } catch (e) { throw Object.assign(new Error('No connection. Check your internet and try again.'), { code: 0 }); }
        let d = null;
        try { d = await res.json(); } catch (e) { /* not JSON (e.g. redirected to the sign-in page) */ }
        if (!d) throw Object.assign(new Error(res.redirected || res.status === 401 || res.status === 403 ? 'Your session has ended. Please sign in again.' : 'The server sent an unexpected reply (' + res.status + ').'), { code: res.redirected ? 401 : res.status });
        if (!d.success) throw Object.assign(new Error(d.message || 'Something went wrong.'), { code: res.status, data: d });
        return d;
    }

    /** Reading a conversation (the phone layout hides the list and the Compose button) */
    function setReading(on) { const mx = document.getElementById('mx'); if (mx) mx.classList.toggle('reading', on); document.body.classList.toggle('mx-reading', on); }

    // ── Floating UI host, toasts, menus, dialogs ─────────────────────────────────────
    let host;
    function toast(html, opts) {
        opts = opts || {};
        $$('.mx-toast', host).forEach((t) => t.remove());
        const t = document.createElement('div');
        t.className = 'mx-toast';
        t.setAttribute('role', 'status');
        t.innerHTML = `<span>${html}</span>` + (opts.actions || []).map((a, i) => `<button type="button" data-i="${i}">${esc(a.label)}</button>`).join('') + '<button type="button" data-x aria-label="Close">&times;</button>';
        host.appendChild(t);
        t.addEventListener('click', (e) => {
            const b = e.target.closest('button'); if (!b) return;
            if (b.dataset.i != null) (opts.actions[+b.dataset.i].run || (() => {}))();
            t.remove();
            if (opts.onClose) opts.onClose();
        });
        const ms = opts.ms == null ? 5000 : opts.ms;
        if (ms) setTimeout(() => { if (t.isConnected) { t.remove(); if (opts.onTimeout) opts.onTimeout(); } }, ms);
        return t;
    }
    const err = (e) => toast('<i class="fas fa-circle-exclamation" style="color:#fca5a5"></i> ' + esc(e && e.message ? e.message : e), { ms: 7000 });

    let menuEl = null;
    function closeMenu() { if (menuEl) { menuEl.remove(); menuEl = null; } }
    /** items: [{icon, label, run, small, on, hd, hr, html}] anchored to an element */
    function menu(anchor, items, opts) {
        closeMenu();
        const m = document.createElement('div');
        m.className = 'mx-menu';
        m.innerHTML = items.map((it, i) => it.hr ? '<hr>' : it.hd ? `<div class="hd">${esc(it.hd)}</div>` : it.html ? `<div data-html="${i}">${it.html}</div>`
            : `<button type="button" class="it${it.on ? ' on' : ''}" data-i="${i}">${it.icon ? `<i class="${it.icon}"></i>` : ''}<span>${esc(it.label)}</span>${it.small ? `<small>${esc(it.small)}</small>` : ''}</button>`).join('');
        host.appendChild(m);
        const r = anchor.getBoundingClientRect();
        const mw = m.offsetWidth, mh = m.offsetHeight;
        let left = Math.min(r.left, window.innerWidth - mw - 8), top = r.bottom + 4;
        if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 4);
        m.style.left = Math.max(8, left) + 'px'; m.style.top = top + 'px';
        m.addEventListener('click', (e) => {
            const b = e.target.closest('button.it'); if (!b) return;
            const it = items[+b.dataset.i];
            if (!it.keep) closeMenu();
            if (it.run) it.run(e, b);
        });
        menuEl = m;
        if (opts && opts.onOpen) opts.onOpen(m);
        return m;
    }
    document.addEventListener('mousedown', (e) => { if (menuEl && !menuEl.contains(e.target) && !e.target.closest('[data-menu]')) closeMenu(); }, true);

    /** Modal dialog; body is HTML; buttons [{label, cls, run(close, el) -> false keeps it open}] */
    function modal(title, body, buttons, opts) {
        opts = opts || {};
        const bg = document.createElement('div');
        bg.className = 'mx-modal-bg';
        bg.innerHTML = `<div class="mx-modal${opts.wide ? ' wide' : ''}" role="dialog" aria-modal="true"><div class="mx-mhd"><h3>${title}</h3><button type="button" class="mx-ib" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
            ${opts.tabs ? `<div class="mx-tabs2">${opts.tabs}</div>` : ''}<div class="mx-mbd">${body}</div>
            ${(buttons || []).length ? `<div class="mx-mft">${opts.footLeft || ''}<span class="sp"></span>${buttons.map((b, i) => `<button type="button" class="mx-btn ${b.cls || ''}" data-b="${i}">${b.label}</button>`).join('')}</div>` : ''}</div>`;
        host.appendChild(bg);
        let closed = false;
        const close = () => { if (closed) return; closed = true; if (opts.beforeClose) opts.beforeClose(bg); bg.remove(); if (opts.onClose) opts.onClose(); };
        bg.addEventListener('mousedown', (e) => { if (e.target === bg) close(); });
        bg.addEventListener('click', async (e) => {
            if (e.target.closest('[data-close]')) return close();
            const b = e.target.closest('[data-b]'); if (!b || !bg.contains(b)) return;
            const btn = buttons[+b.dataset.b];
            if (!btn.run) return close();
            b.disabled = true;
            try { if ((await btn.run(close, bg)) !== false) close(); } catch (ex) { err(ex); }
            if (b.isConnected) b.disabled = false;
        });
        bg.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.stopPropagation(); close(); }
            if (e.key === 'Enter' && opts.enter && e.target.matches('input')) { e.preventDefault(); const b = $('[data-b].pri', bg); if (b) b.click(); }
        });
        const first = bg.querySelector('.mx-mbd input:not([type=hidden]), .mx-mbd textarea, .mx-mbd select');
        if (first && !opts.noFocus) setTimeout(() => first.focus(), 30);
        return { el: bg, close };
    }
    const confirmBox = (msg, ok, danger) => new Promise((resolve) => {
        let v = false;
        modal('Please confirm', `<p style="margin:0;line-height:1.55">${esc(msg)}</p>`, [{ label: 'Cancel' }, { label: esc(ok || 'OK'), cls: danger ? 'pri dng-pri' : 'pri', run: () => { v = true; } }], { onClose: () => resolve(v) });
    });
    const promptBox = (msg, def) => new Promise((resolve) => {
        let v = null;
        const d = modal(esc(msg), `<input class="mx-in" data-pv value="${esc(def || '')}" maxlength="200">`, [{ label: 'Cancel' }, { label: 'OK', cls: 'pri', run: (c, el) => { v = $('[data-pv]', el).value.trim(); } }], { onClose: () => resolve(v), enter: true });
        setTimeout(() => { const i = $('[data-pv]', d.el); if (i) i.select(); }, 40);
    });

    // ── State ────────────────────────────────────────────────────────────────────────
    const S = {
        boxes: [], box: 'main', me: {}, maxAttach: 8 << 20, colors: [], pageSize: 50, gmail: ROOT.dataset.gmail === '1',
        folders: [], roles: {}, labels: [], scheduledCount: 0,
        view: { type: 'folder', folder: 'INBOX', nav: 'inbox' }, q: '', page: 1,
        list: [], groups: [], total: 0, pages: 1, sel: new Set(), cur: -1,
        reading: null, loading: false, lastUid: 0, contacts: new Map(),
    };
    const SYS = [
        { nav: 'inbox', icon: 'fas fa-inbox', label: 'Inbox', role: 'inbox' },
        { nav: 'starred', icon: 'fas fa-star', label: 'Starred', type: 'starred', srole: 'starred' },
        { nav: 'important', icon: 'fas fa-bookmark', label: 'Important', role: 'important', gmail: true },
        { nav: 'snoozed', icon: 'far fa-clock', label: 'Snoozed', role: 'snoozed' },
        { nav: 'sent', icon: 'far fa-paper-plane', label: 'Sent', role: 'sent' },
        { nav: 'drafts', icon: 'far fa-file', label: 'Drafts', role: 'drafts' },
        { nav: 'scheduled', icon: 'far fa-calendar-check', label: 'Scheduled', type: 'scheduled' },
        { nav: 'all', icon: 'far fa-envelope', label: 'All Mail', type: 'all', srole: 'archive' },
        { nav: 'archive', icon: 'fas fa-box-archive', label: 'Archive', role: 'archive', notGmail: true },
        { nav: 'spam', icon: 'fas fa-circle-exclamation', label: 'Spam', role: 'spam' },
        { nav: 'trash', icon: 'far fa-trash-can', label: 'Trash', role: 'trash' },
    ];
    const navItems = () => SYS.filter((it) => !(it.gmail && !S.gmail) && !(it.notGmail && S.gmail) && !(it.role && !S.roles[it.role]));
    const roleOf = (f) => { for (const r in S.roles) if (S.roles[r] === f) return r; return 'folder'; };
    const roleLabel = (role) => { const it = SYS.find((x) => x.role === role || x.srole === role); return it ? it.label : ''; };
    const curRole = () => S.view.type === 'folder' ? roleOf(S.view.folder) : S.view.type;
    const boxInfo = () => S.boxes[0] || { address: ROOT.dataset.address || '', name: ROOT.dataset.name || '' };
    const labelById = (id) => S.labels.find((l) => l.id === id);
    const isMine = (email) => S.boxes.some((b) => b.address.toLowerCase() === String(email).toLowerCase());
    const customFolders = () => S.gmail ? [] : S.folders.filter((f) => f.role === 'folder');

    // ── Shell ────────────────────────────────────────────────────────────────────────
    function shell() {
        ROOT.innerHTML = `<div class="mx" id="mx">
            <div class="mx-top">
                <button type="button" class="mx-ib mx-navbtn" data-a="nav" aria-label="Folders"><i class="fas fa-bars"></i></button>
                <div class="mx-search" id="mxSearch">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" id="mxQ" placeholder="Search mail" autocomplete="off" aria-label="Search mail">
                    <button type="button" class="mx-sbtn clear" data-a="qclear" aria-label="Clear search" hidden><i class="fas fa-xmark"></i></button>
                    <button type="button" class="mx-sbtn opts" data-a="qadv" aria-label="Search options"><i class="fas fa-sliders"></i></button>
                    <div class="mx-sadv" id="mxAdv" hidden></div>
                </div>
                <div class="mx-right">
                    <button type="button" class="mx-ib" data-a="keys" data-tip="Keyboard shortcuts (?)"><i class="far fa-keyboard"></i></button>
                    <button type="button" class="mx-ib" data-a="settings" data-tip="Webmail settings"><i class="fas fa-gear"></i></button>
                </div>
            </div>
            <div class="mx-body" id="mxMail"><div class="mx-nav" id="mxNav"></div><div class="mx-main" id="mxMain"><div class="mx-loading"></div></div></div>
        </div>`;
        host = document.createElement('div');
        host.id = 'mxFloat';
        document.body.appendChild(host);
        host.innerHTML = '<button type="button" class="mx-fab" data-a="compose" aria-label="Compose"><i class="fas fa-pen"></i></button>';
    }

    function renderNav() {
        const n = $('#mxNav'); if (!n) return;
        const fold = Object.fromEntries(S.folders.map((f) => [f.name, f]));
        const count = (it) => {
            if (it.type === 'scheduled') return S.scheduledCount || '';
            const f = fold[S.roles[it.role]]; if (!f) return '';
            if (it.role === 'drafts' || it.role === 'snoozed') return f.total || '';
            if (it.role === 'inbox' || it.role === 'spam') return f.unseen || '';
            return '';
        };
        const navOn = (k) => S.view.nav === k ? ' on' : '';
        const b = boxInfo();
        n.innerHTML = `<button type="button" class="mx-compose-btn" data-a="compose"><i class="fas fa-pen"></i> Compose</button>
            <div class="mx-acctchip" title="${esc(b.address)}"><span class="av"><i class="${S.gmail ? 'fab fa-google' : 'fas fa-envelope'}"></i></span><span class="tx"><b>${esc(b.name || 'Mailbox')}</b><small>${esc(b.address)}</small></span></div>
            <div class="mx-folders">${navItems().map((it) => { const c = count(it); return `<button type="button" class="mx-fi${navOn(it.nav)}${it.role === 'inbox' && c ? ' bold' : ''}" data-nav="${it.nav}"><i class="${it.icon}"></i><span>${it.label}</span><em>${c}</em></button>`; }).join('')}</div>
            <div class="mx-sec">Labels <button type="button" data-a="label-new" aria-label="New label"><i class="fas fa-plus"></i></button></div>
            <div class="mx-folders">${S.labels.map((l) => `<button type="button" class="mx-fi${navOn('label:' + l.id)}" data-nav="label:${esc(l.id)}"><span class="dot" style="background:${l.color}"></span><span>${esc(l.name)}</span><span class="mx-more" data-menu data-a="label-menu" data-id="${esc(l.id)}" title="Label options"><i class="fas fa-ellipsis-vertical"></i></span></button>`).join('') || '<div class="mx-note" style="padding:2px 16px">No labels yet</div>'}</div>
            ${S.gmail ? '' : `<div class="mx-sec">Folders <button type="button" data-a="folder-new" aria-label="New folder"><i class="fas fa-plus"></i></button></div>
            <div class="mx-folders">${customFolders().map((f) => `<button type="button" class="mx-fi${navOn('f:' + f.name)}" data-nav="f:${esc(f.name)}"><i class="far fa-folder"></i><span>${esc(f.label)}</span><em>${f.unseen || ''}</em><span class="mx-more" data-menu data-a="folder-menu" data-f="${esc(f.name)}" title="Folder options"><i class="fas fa-ellipsis-vertical"></i></span></button>`).join('') || '<div class="mx-note" style="padding:2px 16px">No folders yet</div>'}</div>`}`;
        const inbox = S.folders.find((f) => f.role === 'inbox');
        document.title = (inbox && inbox.unseen ? '(' + inbox.unseen + ') ' : '') + document.title.replace(/^\(\d+\) /, '');
    }

    // ── Loading data ─────────────────────────────────────────────────────────────────
    async function loadBoxes() {
        const d = await api('boxes');
        S.boxes = d.boxes; S.me = d.me; S.maxAttach = d.max_attach; S.colors = d.label_colors; S.pageSize = d.page_size;
    }

    async function loadFolders() {
        const d = await api('folders');
        S.gmail = !!d.gmail; S.folders = d.folders; S.roles = d.roles; S.labels = d.labels; S.scheduledCount = d.scheduled;
        const inbox = S.folders.find((f) => f.role === 'inbox');
        if (S.boxes[0] && inbox) S.boxes[0].unseen = inbox.unseen;
        renderNav();
    }

    function setView(nav, keepPage) {
        let v;
        if (nav.startsWith('label:')) v = { type: 'label', label: nav.slice(6), nav };
        else if (nav.startsWith('f:')) v = { type: 'folder', folder: nav.slice(2), nav };
        else {
            const it = SYS.find((x) => x.nav === nav) || SYS[0];
            v = it.type ? { type: it.type, nav: it.nav } : { type: 'folder', folder: S.roles[it.role] || 'INBOX', nav: it.nav };
        }
        S.view = v; if (!keepPage) S.page = 1;
        S.sel.clear(); S.cur = -1; S.reading = null;
        setReading(false); $('#mx').classList.remove('navopen');
        renderNav();
        loadList();
    }

    let listReq = 0;
    async function loadList(silent) {
        const my = ++listReq;
        const main = $('#mxMain');
        if (!silent) { S.loading = true; if (!S.reading) renderList(); }
        try {
            const p = { view: S.view.type, page: S.page };
            if (S.view.type === 'folder') p.folder = S.view.folder;
            if (S.view.type === 'label') p.label = S.view.label;
            if (S.q) p.q = S.q;
            const d = await api('list', p);
            if (my !== listReq) return;
            S.list = d.messages || []; S.total = d.total; S.pages = d.pages; S.scheduled = d.scheduled || null;
            if (S.view.type === 'folder' && S.view.folder === S.roles.inbox && S.page === 1 && !S.q) {
                S.lastUid = Math.max(S.lastUid, ...S.list.map((m) => m.uid), 0);
            }
            S.list.forEach((m) => parseAddrs(m.from).concat(parseAddrs(m.to)).forEach((a) => { if (!S.contacts.has(a.email)) S.contacts.set(a.email, a.name); }));
            group();
        } catch (e) {
            if (my !== listReq) return;
            S.list = []; S.groups = []; S.listError = e;
        } finally {
            if (my === listReq) { S.loading = false; if (!S.reading) renderList(); }
        }
        if (main && !silent) main.scrollTop = 0;
    }

    /** Conversation rows: messages on this page that share a thread key (Gmail: X-GM-THRID) */
    function group() {
        S.listError = null;
        const by = new Map();
        S.list.forEach((m) => {
            const k = (S.view.type === 'scheduled' || curRole() === 'drafts') ? m.f + '\n' + m.uid : m.thread;
            if (!by.has(k)) by.set(k, []);
            by.get(k).push(m);
        });
        S.groups = Array.from(by.values()).map((ms, i) => ({ id: i, ms, rep: ms[0], unread: ms.some((m) => !m.seen), star: ms.some((m) => m.flagged), important: ms.some((m) => m.important), items: ms.map((m) => ({ f: m.f, u: m.uid })) }));
        S.sel.clear();
    }

    // ── List view ────────────────────────────────────────────────────────────────────
    function listToolbar() {
        const role = curRole(), any = S.sel.size > 0, allSel = any && S.sel.size === S.groups.length;
        const from = S.total ? (S.page - 1) * S.pageSize + 1 : 0, to = Math.min(S.total, S.page * S.pageSize);
        let acts = '';
        if (any) {
            const inTrash = role === 'trash', inSpam = role === 'spam';
            acts = (['inbox', 'starred', 'label', 'folder', 'all', 'important'].includes(role) ? `<button type="button" class="mx-ib" data-a="op" data-op="archive" data-tip="Archive"><i class="fas fa-box-archive"></i></button>` : '')
                + (['archive', 'trash', 'spam', 'snoozed', 'folder', 'sent', 'all', 'label'].includes(role) ? `<button type="button" class="mx-ib" data-a="op" data-op="${inSpam ? 'notspam' : 'inbox'}" data-tip="${inSpam ? 'Not spam' : 'Move to Inbox'}"><i class="fas fa-inbox"></i></button>` : '')
                + (!inSpam ? `<button type="button" class="mx-ib" data-a="op" data-op="spam" data-tip="Report spam"><i class="fas fa-circle-exclamation"></i></button>` : '')
                + (inTrash || inSpam || role === 'drafts' ? `<button type="button" class="mx-ib" data-a="op" data-op="delete_forever" data-tip="Delete forever"><i class="fas fa-trash-can"></i></button>` : `<button type="button" class="mx-ib" data-a="op" data-op="trash" data-tip="Delete"><i class="far fa-trash-can"></i></button>`)
                + `<button type="button" class="mx-ib" data-a="op" data-op="read" data-tip="Mark as read"><i class="far fa-envelope-open"></i></button>`
                + `<button type="button" class="mx-ib" data-a="op" data-op="unread" data-tip="Mark as unread"><i class="fas fa-envelope"></i></button>`
                + (!inTrash && !inSpam ? `<button type="button" class="mx-ib" data-menu data-a="snooze" data-tip="Snooze"><i class="far fa-clock"></i></button>` : '')
                + `<button type="button" class="mx-ib" data-menu data-a="move" data-tip="Move to"><i class="far fa-folder-open"></i></button>`
                + `<button type="button" class="mx-ib" data-menu data-a="labels" data-tip="Labels"><i class="fas fa-tag"></i></button>`
                + `<button type="button" class="mx-ib" data-menu data-a="more-sel" data-tip="More"><i class="fas fa-ellipsis-vertical"></i></button>`;
        } else {
            acts = `<button type="button" class="mx-ib" data-a="refresh" data-tip="Refresh"><i class="fas fa-rotate-right"></i></button>`
                + (role === 'trash' || role === 'spam' ? `<button type="button" class="mx-ib" data-a="empty" data-role="${role}"><i class="fas fa-trash-can"></i><span>Empty ${role === 'trash' ? 'Trash' : 'Spam'} now</span></button>` : '')
                + `<button type="button" class="mx-ib" data-menu data-a="more-list" data-tip="More"><i class="fas fa-ellipsis-vertical"></i></button>`;
        }
        return `<div class="mx-bar">
            ${S.view.type === 'scheduled' ? '' : `<input type="checkbox" class="mx-chk" data-a="selall" ${allSel ? 'checked' : ''} aria-label="Select all"><button type="button" class="mx-ib" data-menu data-a="selmenu" style="min-width:20px;padding:0 2px" aria-label="Select"><i class="fas fa-caret-down"></i></button>`}
            ${acts}<span class="sp"></span>
            ${S.total ? `<span class="mx-pg">${from}–${to} of ${S.total.toLocaleString()}</span>` : ''}
            <button type="button" class="mx-ib" data-a="page" data-d="-1" ${S.page <= 1 ? 'disabled' : ''} aria-label="Newer"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="mx-ib" data-a="page" data-d="1" ${S.page >= S.pages ? 'disabled' : ''} aria-label="Older"><i class="fas fa-chevron-right"></i></button>
        </div>`;
    }

    function rowHtml(g, i) {
        const m = g.rep, role = roleOf(m.f), outgoing = role === 'sent' || role === 'drafts';
        const peopleSrc = outgoing ? parseAddrs(m.to).concat(parseAddrs(m.cc)) : parseAddrs(m.from);
        let who = outgoing ? 'To: ' + (peopleSrc.map(nameOf).join(', ') || '(no recipients)') : (peopleSrc.map(nameOf).join(', ') || m.from || '(unknown)');
        if (!outgoing && g.ms.length > 1) {
            const names = []; g.ms.slice().reverse().forEach((x) => { const a = parseAddrs(x.from)[0]; const nm = a ? (isMine(a.email) ? 'me' : nameOf(a).split(' ')[0]) : ''; if (nm && !names.includes(nm)) names.push(nm); });
            who = names.slice(-3).join(', ') || who;
        }
        const avKey = (peopleSrc[0] && peopleSrc[0].email) || who;
        const labels = (m.keywords || []).map(labelById).filter((l) => l && !(S.view.type === 'label' && S.view.label === l.id));
        // Gmail lists one folder at a time, so the useful hint is "still in the Inbox"; other servers merge folders (Starred, All mail)
        const where = S.gmail ? (m.inbox && role !== 'inbox' ? 'Inbox' : '')
            : (S.view.type !== 'folder' || S.q) && !['inbox', 'folder', curRole()].includes(role) ? roleLabel(role) : '';
        const chips = (m.draft ? '<span class="mx-tag draft">Draft</span>' : '')
            + (m.snoozed_until ? `<span class="mx-tag snz"><i class="far fa-clock"></i> ${esc(shortDate(m.snoozed_until))}</span>` : '')
            + (where ? `<span class="mx-tag">${esc(where)}</span>` : '')
            + labels.map((l) => `<span class="mx-lbl" style="background:${l.color}">${esc(l.name)}</span>`).join('');
        return `<div class="mx-row${g.unread ? ' unread' : ''}${S.sel.has(i) ? ' sel' : ''}${S.cur === i ? ' cur' : ''}" data-row="${i}">
            <span class="cb"><input type="checkbox" class="mx-chk" data-a="sel" ${S.sel.has(i) ? 'checked' : ''} aria-label="Select">${isPhone() ? avatar(who, avKey) : ''}</span>
            <button type="button" class="mx-star${g.star ? ' on' : ''}" data-a="star" aria-label="Star"><i class="${g.star ? 'fas' : 'far'} fa-star"></i></button>
            <span class="who">${g.important ? '<i class="fas fa-bookmark mx-imp" title="Important"></i>' : ''}${esc(who)}${g.ms.length > 1 ? `<small>${g.ms.length}</small>` : ''}</span>
            <span class="line">${chips}<span class="subj">${esc(m.subject || '(no subject)')}</span><span class="pre">${m.preview ? '- ' + esc(m.preview) : ''}</span></span>
            <span class="when">${m.attach ? '<i class="fas fa-paperclip"></i>' : ''}<span class="d">${esc(shortDate(m.date))}</span><span class="acts">
                ${role === 'inbox' ? '<button type="button" class="mx-ib" data-a="rowop" data-op="archive" data-tip="Archive"><i class="fas fa-box-archive"></i></button>' : ''}
                <button type="button" class="mx-ib" data-a="rowop" data-op="${['trash', 'spam'].includes(role) ? 'delete_forever' : 'trash'}" data-tip="Delete"><i class="far fa-trash-can"></i></button>
                <button type="button" class="mx-ib" data-a="rowop" data-op="${g.unread ? 'read' : 'unread'}" data-tip="${g.unread ? 'Mark as read' : 'Mark as unread'}"><i class="${g.unread ? 'far fa-envelope-open' : 'fas fa-envelope'}"></i></button>
                ${['trash', 'spam'].includes(role) ? '' : '<button type="button" class="mx-ib" data-menu data-a="rowsnooze" data-tip="Snooze"><i class="far fa-clock"></i></button>'}</span></span>
        </div>`;
    }

    function renderList() {
        const main = $('#mxMain'); if (!main) return;
        setReading(false);
        removeRbar();
        if (S.view.type === 'scheduled') return renderScheduled();
        const navLabel = S.view.type === 'label' ? (labelById(S.view.label) || {}).name : S.view.type === 'folder' && S.view.nav.startsWith('f:') ? (S.folders.find((f) => f.name === S.view.folder) || {}).label : (SYS.find((x) => x.nav === S.view.nav) || {}).label;
        let body;
        if (S.listError) {
            const e = S.listError;
            const out = e.code === 401 || e.code === 403;
            if (out) body = `<div class="mx-empty warn"><i class="fas fa-right-to-bracket"></i><h3>You were signed out</h3><p>${esc(e.message)}</p>
                <p><a class="mx-go" href="../login"><i class="fas fa-right-to-bracket"></i> Sign in again</a></p></div>`;
            else body = `<div class="mx-empty warn"><i class="fas fa-plug-circle-xmark"></i><h3>${e.code === 409 ? 'The mailbox is not connected' : 'Could not load the mailbox'}</h3><p>${esc(e.message)}</p>
                ${e.data && e.data.setup ? '<p><button type="button" class="mx-pill pri" data-a="settings-account"><i class="fas fa-plug"></i> Open mailbox connection</button></p>' : '<p><button type="button" class="mx-pill" data-a="refresh"><i class="fas fa-rotate-right"></i> Try again</button></p>'}</div>`;
        } else if (!S.groups.length && !S.loading) {
            const role = curRole();
            const msg = S.q ? ['fa-magnifying-glass', 'No messages matched your search', S.view.type !== 'all' ? '<p><button type="button" class="mx-pill" data-a="searchall">Search all mail</button></p>' : '']
                : role === 'inbox' ? ['fa-mug-hot', "You're all caught up", 'No new mail in ' + esc(boxInfo().address || 'this mailbox') + '.']
                : role === 'snoozed' ? ['fa-clock', 'Nothing snoozed', 'Snooze a conversation to have it come back to the Inbox later.']
                : role === 'starred' ? ['fa-star', 'No starred messages', 'Stars let you give messages a special status to make them easier to find.']
                : ['fa-envelope-open', 'Nothing in ' + esc(navLabel || 'this folder'), ''];
            body = `<div class="mx-empty"><i class="fas ${msg[0]}"></i><h3>${msg[1]}</h3><p>${msg[2]}</p></div>`;
        } else {
            body = S.groups.map(rowHtml).join('');
        }
        const banner = S.q ? `<div class="mx-banner" style="margin:10px 14px"><i class="fas fa-magnifying-glass"></i> Results for <b>${esc(S.q)}</b> in ${esc(navLabel || '')}${S.view.type !== 'all' ? ' &middot; <a href="#" data-a="searchall">Search all mail</a>' : ''} &middot; <a href="#" data-a="qclear">Clear</a></div>` : '';
        main.innerHTML = listToolbar() + (S.loading ? '<div class="mx-loading"></div>' : '') + `<div class="mx-list" id="mxList">${banner}${body}</div>`;
    }

    function renderScheduled() {
        const main = $('#mxMain');
        const rows = (S.scheduled || []).map((s) => `<div class="mx-row mx-srow">
            <span class="who">To: ${esc(s.recipients || '')}</span>
            <span class="line"><span class="mx-tag ${s.status === 'failed' ? 'draft' : 'snz'}">${s.status === 'failed' ? 'Not sent' : s.status === 'sending' ? 'Sending' : 'Scheduled'}</span><span class="subj">${esc(s.subject || '(no subject)')}</span>${s.error ? `<span class="pre" style="color:var(--danger)">${esc(s.error)}</span>` : ''}</span>
            <span class="when"><i class="far fa-calendar"></i> ${esc(new Date(s.send_at.replace(' ', 'T')).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }))}</span>
            <span>${s.status === 'sending' ? '' : `<button type="button" class="mx-pill" data-a="sched-cancel" data-id="${s.id}" style="height:30px">Cancel send</button>`}</span></div>`).join('');
        main.innerHTML = `<div class="mx-bar"><button type="button" class="mx-ib" data-a="refresh" data-tip="Refresh"><i class="fas fa-rotate-right"></i></button><span class="sp"></span></div>`
            + (S.loading ? '<div class="mx-loading"></div>' : '')
            + `<div class="mx-list">${rows || `<div class="mx-empty"><i class="far fa-calendar-check"></i><h3>No scheduled messages</h3><p>Use the arrow next to Send to schedule a message.</p></div>`}
               <div class="mx-note" style="padding:12px 16px">Scheduled messages are sent within a minute of their time while someone has Webmail open (or by the cron job if one is set up). "Cancel send" puts the message back into a new draft window.</div></div>`;
    }

    // ── Reading view ─────────────────────────────────────────────────────────────────
    async function openThread(i) {
        const g = S.groups[i]; if (!g) return;
        const m = g.rep;
        if (roleOf(m.f) === 'drafts' && m.draft !== false) return openDraft(m);
        S.cur = i;
        S.reading = { loading: true, g };
        setReading(true);
        const main = $('#mxMain');
        main.innerHTML = readToolbar() + '<div class="mx-loading"></div><div class="mx-read"></div>';
        try {
            const d = await api('read', { f: m.f, uid: m.uid });
            if (S.reading && S.reading.g === g) {
                S.reading = Object.assign(d, { g });
                g.ms.forEach((x) => { x.seen = true; }); g.unread = false;
                renderRead();
                loadFolders().catch(() => {});
            }
        } catch (e) {
            main.innerHTML = readToolbar() + `<div class="mx-empty"><i class="fas fa-triangle-exclamation"></i><h3>Could not open this message</h3><p>${esc(e.message)}</p></div>`;
        }
    }

    function readToolbar() {
        const g = S.reading && S.reading.g, role = g ? roleOf(g.rep.f) : curRole();
        const i = S.cur;
        return `<div class="mx-bar">
            <button type="button" class="mx-ib" data-a="back" data-tip="Back (u)"><i class="fas fa-arrow-left"></i></button>
            ${['inbox', 'folder', 'starred', 'important', 'all'].includes(role) ? '<button type="button" class="mx-ib" data-a="rop" data-op="archive" data-tip="Archive (e)"><i class="fas fa-box-archive"></i></button>' : ''}
            ${['archive', 'trash', 'spam', 'snoozed', 'sent', 'all', 'folder'].includes(role) ? `<button type="button" class="mx-ib" data-a="rop" data-op="${role === 'spam' ? 'notspam' : 'inbox'}" data-tip="${role === 'spam' ? 'Not spam' : 'Move to Inbox'}"><i class="fas fa-inbox"></i></button>` : ''}
            ${role !== 'spam' ? '<button type="button" class="mx-ib" data-a="rop" data-op="spam" data-tip="Report spam (!)"><i class="fas fa-circle-exclamation"></i></button>' : ''}
            ${['trash', 'spam'].includes(role) ? '<button type="button" class="mx-ib" data-a="rop" data-op="delete_forever" data-tip="Delete forever"><i class="fas fa-trash-can"></i></button>' : '<button type="button" class="mx-ib" data-a="rop" data-op="trash" data-tip="Delete (#)"><i class="far fa-trash-can"></i></button>'}
            <button type="button" class="mx-ib" data-a="rop" data-op="unread" data-tip="Mark as unread (U)"><i class="fas fa-envelope"></i></button>
            ${['trash', 'spam'].includes(role) ? '' : '<button type="button" class="mx-ib" data-menu data-a="snooze" data-tip="Snooze (b)"><i class="far fa-clock"></i></button>'}
            <button type="button" class="mx-ib" data-menu data-a="move" data-tip="Move to (v)"><i class="far fa-folder-open"></i></button>
            <button type="button" class="mx-ib" data-menu data-a="labels" data-tip="Labels (l)"><i class="fas fa-tag"></i></button>
            <button type="button" class="mx-ib" data-menu data-a="more-read" data-tip="More"><i class="fas fa-ellipsis-vertical"></i></button>
            <span class="sp"></span>
            ${S.groups.length ? `<span class="mx-pg">${i + 1} of ${S.groups.length}</span>` : ''}
            <button type="button" class="mx-ib" data-a="nav-msg" data-d="-1" ${i <= 0 ? 'disabled' : ''} aria-label="Newer (k)"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="mx-ib" data-a="nav-msg" data-d="1" ${i >= S.groups.length - 1 ? 'disabled' : ''} aria-label="Older (j)"><i class="fas fa-chevron-right"></i></button>
        </div>`;
    }

    function frameDoc(html) {
        return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https: http: data:; style-src \'unsafe-inline\' https:; font-src https: data:; media-src https:">'
            + '<base target="_blank"><style>html,body{margin:0;padding:0}body{padding:12px 14px;font:14px/1.6 Arial,Helvetica,sans-serif;color:#1e293b;background:#fff;word-wrap:break-word;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{max-width:100%!important}pre{white-space:pre-wrap}blockquote{margin:0 0 0 .8ex;border-left:2px solid #cbd5e1;padding-left:1ex;color:#475569}a{color:#0a6cf0}</style></head><body>' + html + '</body></html>';
    }
    const linkify = (t) => esc(t).replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');

    function attUrl(m, a, inline) { return API + '?' + new URLSearchParams({ action: 'attachment', f: m.f, uid: m.uid, i: a.i }) + (inline ? '&inline=1' : ''); }

    function msgHtml(m, i, last) {
        const from = parseAddrs(m.from)[0] || { name: m.from, email: '' };
        const collapsed = !last && m.seen && S.reading.messages.length > 1;
        const recips = parseAddrs(m.to).concat(parseAddrs(m.cc)).map((a) => isMine(a.email) ? 'me' : nameOf(a));
        const att = (m.attachments || []).map((a) => {
            const url = attUrl(m, a), img = /^image\//.test(a.type) && a.preview;
            return `<div class="mx-att"><div class="th">${img ? `<img src="${esc(attUrl(m, a, true))}" alt="" loading="lazy">` : `<i class="${/pdf/.test(a.type) ? 'far fa-file-pdf' : /zip|rar|7z/.test(a.type) ? 'far fa-file-zipper' : /word|document/.test(a.type) ? 'far fa-file-word' : /sheet|excel|csv/.test(a.type) ? 'far fa-file-excel' : /audio/.test(a.type) ? 'far fa-file-audio' : /video/.test(a.type) ? 'far fa-file-video' : 'far fa-file'}"></i>`}</div>
                <div class="nm"><span title="${esc(a.name)}">${esc(a.name)}</span><small>${kb(a.size)}</small></div>
                <div class="ov">${a.preview ? `<button type="button" data-a="preview" data-url="${esc(attUrl(m, a, true))}" data-type="${esc(a.type)}" data-name="${esc(a.name)}" title="Preview"><i class="far fa-eye"></i></button>` : ''}<a href="${esc(url)}" title="Download" download><i class="fas fa-download"></i></a></div></div>`;
        }).join('');
        return `<div class="mx-msg${collapsed ? ' mx-collapsed' : ''}" data-m="${i}">
            <div class="mx-mh" data-a="toggle-msg">${avatar(from.name || from.email, from.email)}
                <div class="who"><b>${esc(from.name || from.email)}</b>${from.name ? `<span class="addr">&lt;${esc(from.email)}&gt;</span>` : ''}
                    <div class="to">to ${esc(recips.join(', ') || '(hidden recipients)')} <button type="button" class="mx-ib" data-a="details" style="height:20px;min-width:20px;padding:0" aria-label="Details"><i class="fas fa-caret-down"></i></button></div>
                    <div class="snip">${esc((m.text || (m.html || '').replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').slice(0, 140))}</div></div>
                <div class="meta">${(m.attachments || []).length ? '<i class="fas fa-paperclip" style="margin-right:6px"></i>' : ''}<span title="${esc(longDate(m.date))}">${esc(shortDate(m.date))}</span>
                    <button type="button" class="mx-star${m.flagged ? ' on' : ''}" data-a="mstar" aria-label="Star"><i class="${m.flagged ? 'fas' : 'far'} fa-star"></i></button>
                    <button type="button" class="mx-ib" data-a="reply" data-m="${i}" data-tip="Reply"><i class="fas fa-reply"></i></button>
                    <button type="button" class="mx-ib" data-menu data-a="msg-more" data-m="${i}" data-tip="More"><i class="fas fa-ellipsis-vertical"></i></button></div></div>
            <div class="mx-det" hidden><span>from:</span><b>${esc(m.from)}</b>${m.reply_to ? `<span>reply-to:</span><b>${esc(m.reply_to)}</b>` : ''}<span>to:</span><b>${esc(m.to)}</b>${m.cc ? `<span>cc:</span><b>${esc(m.cc)}</b>` : ''}${m.bcc ? `<span>bcc:</span><b>${esc(m.bcc)}</b>` : ''}<span>date:</span><b>${esc(longDate(m.date))}</b><span>subject:</span><b>${esc(m.subject)}</b><span>mailbox:</span><b>${esc(boxInfo().address || '')}</b></div>
            <div class="mx-mb">${m.invite ? `<div class="mx-banner" style="margin-left:0"><i class="far fa-calendar"></i> Calendar invitation: <b>${esc(m.invite)}</b></div>` : ''}
                ${m.html ? `<iframe class="mx-frame" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" data-fi="${i}" title="Message"></iframe>` : `<div class="mx-text">${linkify(m.text || '')}</div>`}
                ${att ? `<div class="mx-atts">${att}</div>` : ''}</div>
        </div>`;
    }

    function renderRead() {
        const R = S.reading, main = $('#mxMain');
        if (!R || !R.messages) return;
        const labels = (R.labels || []).map(labelById).filter(Boolean);
        const role = roleOf(R.folder);
        const last = R.messages[R.messages.length - 1];
        main.innerHTML = readToolbar() + `<div class="mx-read" id="mxRead">
            <div class="mx-rsubj"><h2>${esc(R.subject || '(no subject)')}</h2>${R.important ? '<span class="mx-tag imp"><i class="fas fa-bookmark"></i> Important</span>' : ''}${role !== 'folder' && roleLabel(role) ? `<span class="mx-tag">${esc(roleLabel(role))}</span>` : ''}${labels.map((l) => `<span class="mx-lbl" style="background:${l.color}">${esc(l.name)} <i class="fas fa-xmark" data-a="unlabel" data-id="${esc(l.id)}" style="margin-left:5px;cursor:pointer;opacity:.8" title="Remove label"></i></span>`).join('')}</div>
            ${R.messages.map((m, i) => msgHtml(m, i, i === R.messages.length - 1)).join('')}
            <div class="mx-rbtns"><button type="button" class="mx-pill" data-a="reply" data-m="${R.messages.length - 1}"><i class="fas fa-reply"></i> Reply</button>
                <button type="button" class="mx-pill" data-a="replyall" data-m="${R.messages.length - 1}"><i class="fas fa-reply-all"></i> Reply all</button>
                <button type="button" class="mx-pill" data-a="forward" data-m="${R.messages.length - 1}"><i class="fas fa-share"></i> Forward</button></div>
            ${last.unsubscribe ? `<div class="mx-note" style="margin:16px 0 0 52px">Mailing list message &middot; <a href="${esc(last.unsubscribe)}" target="_blank" rel="noopener noreferrer" style="color:var(--primary)">Unsubscribe</a></div>` : ''}
        </div>`;
        $$('iframe.mx-frame', main).forEach((fr) => {
            const m = R.messages[+fr.dataset.fi];
            fr.addEventListener('load', () => fitFrame(fr));
            fr.srcdoc = frameDoc(m.html);
        });
        renderRbar();
        const lastEl = $$('.mx-msg', main).pop();
        if (lastEl && R.messages.length > 2) setTimeout(() => lastEl.scrollIntoView({ block: 'start' }), 60);
    }
    function fitFrame(fr) {
        try {
            const doc = fr.contentDocument; if (!doc) return;
            const h = () => { fr.style.height = Math.min(Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0, 60), 20000) + 'px'; };
            h(); setTimeout(h, 300); setTimeout(h, 1500);
            $$('img', doc).forEach((im) => im.addEventListener('load', h));
        } catch (e) { fr.style.height = '600px'; }
    }
    function renderRbar() {
        removeRbar();
        if (!S.reading || !S.reading.messages) return;
        const b = document.createElement('div');
        b.className = 'mx-rbar mx-rbar-only';
        const last = S.reading.messages.length - 1;
        b.innerHTML = `<button type="button" class="mx-ib" data-a="reply" data-m="${last}" aria-label="Reply"><i class="fas fa-reply"></i></button><button type="button" class="mx-ib" data-a="replyall" data-m="${last}" aria-label="Reply all"><i class="fas fa-reply-all"></i></button><button type="button" class="mx-ib" data-a="forward" data-m="${last}" aria-label="Forward"><i class="fas fa-share"></i></button><button type="button" class="mx-ib" data-a="rop" data-op="archive" aria-label="Archive"><i class="fas fa-box-archive"></i></button><button type="button" class="mx-ib" data-a="rop" data-op="trash" aria-label="Delete"><i class="far fa-trash-can"></i></button>`;
        host.appendChild(b);
    }
    function removeRbar() { if (host) $$('.mx-rbar', host).forEach((x) => x.remove()); }

    function back() {
        S.reading = null;
        setReading(false);
        renderList();
        const row = $(`.mx-row[data-row="${S.cur}"]`); if (row) row.scrollIntoView({ block: 'nearest' });
    }

    // ── Message operations ───────────────────────────────────────────────────────────
    async function op(name, items, extra) {
        if (!items.length) return;
        if (name === 'delete_forever' && !(await confirmBox(`Delete ${items.length === 1 ? 'this message' : items.length + ' messages'} forever? This cannot be undone.`, 'Delete forever', true))) return false;
        try {
            await api('op', Object.assign({ items, op: name }, extra || {}), true);
            const words = { archive: 'archived', trash: 'moved to Trash', spam: 'reported as spam', notspam: 'moved to Inbox', inbox: 'moved to Inbox', delete_forever: 'deleted forever', move: 'moved', snooze: 'snoozed', unread: 'marked as unread', important: 'marked as important', unimportant: 'marked as not important' };
            const n = items.length;
            const where = { archive: S.gmail ? 'all' : 'archive', trash: 'trash', spam: 'spam', snooze: 'snoozed' }[name];
            if (words[name]) toast(`${n === 1 ? 'Message' : n + ' messages'} ${words[name]}.`, where ? { actions: [{ label: 'Open ' + (where === 'all' ? 'All Mail' : where), run: () => setView(where) }] } : {});
        } catch (e) { err(e); return false; }
        loadFolders().catch(() => {});
        return true;
    }
    const selItems = () => { const out = []; S.sel.forEach((i) => out.push(...S.groups[i].items)); return out; };
    const readItems = () => S.reading && S.reading.messages ? S.reading.messages.map((m) => ({ f: m.f, u: m.uid })) : S.reading && S.reading.g ? S.reading.g.items : [];
    const targetItems = () => S.reading ? readItems() : selItems();

    async function doOp(name, items, extra) {
        const removing = ['archive', 'trash', 'spam', 'notspam', 'inbox', 'delete_forever', 'move', 'snooze'].includes(name);
        const ok = await op(name, items, extra);
        if (ok === false) return;
        if (removing && S.reading) {
            const next = S.cur;
            S.reading = null;
            await loadList(true);
            if (S.groups[next] && !isPhone()) openThread(next); else renderList();
            return;
        }
        if (S.reading && name === 'unread') back();
        await loadList(true);
    }

    // ── Compose ──────────────────────────────────────────────────────────────────────
    let CW = null;   // current compose state
    const FONTS = ['Arial', 'Georgia', 'Tahoma', 'Times New Roman', 'Trebuchet MS', 'Verdana', 'Courier New'];

    function quoteOf(m) {
        const body = m.html || '<div style="white-space:pre-wrap">' + linkify(m.text || '') + '</div>';
        return `<div>On ${esc(longDate(m.date).replace(/ \(.*\)$/, ''))}, ${esc(m.from)} wrote:</div><blockquote style="margin:0 0 0 .8ex;border-left:1px solid #cbd5e1;padding-left:1ex">${body}</blockquote>`;
    }
    function forwardOf(m) {
        const body = m.html || '<div style="white-space:pre-wrap">' + linkify(m.text || '') + '</div>';
        return `<div>---------- Forwarded message ---------<br>From: ${esc(m.from)}<br>Date: ${esc(longDate(m.date).replace(/ \(.*\)$/, ''))}<br>Subject: ${esc(m.subject)}<br>To: ${esc(m.to)}${m.cc ? '<br>Cc: ' + esc(m.cc) : ''}</div><br>${body}`;
    }
    const prefix = (p, s) => new RegExp('^' + p + ':', 'i').test(s || '') ? s : p + ': ' + (s || '');

    async function compose(o) {
        o = o || {};
        if (CW && (!CW.el || !CW.el.isConnected)) CW = null;
        if (CW) await closeCompose();   // saves the open message as a draft first
        CW = {
            to: o.to || [], cc: o.cc || [], bcc: o.bcc || [], subject: o.subject || '', html: o.html || '',
            quoted: o.quoted || '', refs: o.refs || [], files: o.files || [], draft: o.draft || 0, reply: o.reply || null,
            showCc: !!(o.cc && o.cc.length), showBcc: !!(o.bcc && o.bcc.length), dirty: false, savedAt: null, saving: false,
            sig: o.sig !== false, max: false, min: false, title: o.title || 'New Message',
        };
        renderCompose();
        const target = CW.to.length ? $('.mx-ed', CW.el) : $('[data-chips="to"] input', CW.el);
        setTimeout(() => { if (target) { target.focus(); if (target.classList.contains('mx-ed')) placeCaretStart(target); } }, 40);
        CW.timer = setInterval(autosave, 10000);
    }
    function placeCaretStart(el) { const r = document.createRange(); r.setStart(el, 0); r.collapse(true); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }

    function chipsHtml(kind) {
        return `<div class="mx-chips" data-chips="${kind}">${CW[kind].map((a, i) => `<span class="mx-chip${/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(a.email) ? '' : ' bad'}" title="${esc(a.email)}"><span>${esc(a.name || a.email)}</span><button type="button" data-a="chip-x" data-k="${kind}" data-i="${i}" aria-label="Remove">&times;</button></span>`).join('')}<input type="text" autocomplete="off" data-k="${kind}" aria-label="${kind}"></div>`;
    }
    function renderCompose() {
        if (CW.el) CW.el.remove();
        if (CW.back) { CW.back.remove(); CW.back = null; }
        const el = document.createElement('div');
        el.className = 'mx-cw' + (CW.max ? ' max' : '') + (CW.min ? ' min' : '');
        const b = boxInfo();
        const totalAtt = CW.files.reduce((a, f) => a + f.size, 0) + CW.refs.reduce((a, r) => a + (r.size || 0), 0);
        el.innerHTML = `<div class="mx-ch" data-a="cw-min"><b>${esc(CW.subject || CW.title)}</b>
                <button type="button" data-a="cw-min" aria-label="Minimise"><i class="fas fa-minus"></i></button>
                <button type="button" data-a="cw-max" aria-label="Full screen"><i class="fas ${CW.max ? 'fa-down-left-and-up-right-to-center' : 'fa-up-right-and-down-left-from-center'}"></i></button>
                <button type="button" data-a="cw-close" aria-label="Save and close"><i class="fas fa-xmark"></i></button></div>
            <div class="mx-cf"><label>From</label><span class="mx-from">${esc(b.name)} &lt;${esc(b.address)}&gt;</span></div>
            <div class="mx-cf"><label>To</label>${chipsHtml('to')}<button type="button" class="tog" data-a="cw-cc" ${CW.showCc ? 'hidden' : ''}>Cc</button><button type="button" class="tog" data-a="cw-bcc" ${CW.showBcc ? 'hidden' : ''}>Bcc</button></div>
            <div class="mx-cf" ${CW.showCc ? '' : 'hidden'}><label>Cc</label>${chipsHtml('cc')}</div>
            <div class="mx-cf" ${CW.showBcc ? '' : 'hidden'}><label>Bcc</label>${chipsHtml('bcc')}</div>
            <div class="mx-cf"><input type="text" data-f="subject" placeholder="Subject" value="${esc(CW.subject)}" maxlength="250" style="padding-left:0" aria-label="Subject"></div>
            <div class="mx-cbody">
                <div class="mx-ed" contenteditable="true" data-ph="Write your message" spellcheck="true" role="textbox" aria-multiline="true" aria-label="Message"></div>
                ${CW.quoted ? `<div class="mx-qt"><button type="button" data-a="cw-quote" title="Show trimmed content">&middot;&middot;&middot;</button><div class="q" hidden></div></div>` : ''}
                <div class="mx-catts">${CW.files.map((f, i) => `<span class="mx-catt"><i class="fas fa-paperclip"></i><span>${esc(f.name)}</span><small>${kb(f.size)}</small><button type="button" data-a="att-x" data-t="file" data-i="${i}" aria-label="Remove">&times;</button></span>`).join('')}${CW.refs.map((r, i) => `<span class="mx-catt"><i class="fas fa-paperclip"></i><span>${esc(r.name)}</span>${r.size ? `<small>${kb(r.size)}</small>` : ''}<button type="button" data-a="att-x" data-t="ref" data-i="${i}" aria-label="Remove">&times;</button></span>`).join('')}</div>
            </div>
            <div class="mx-tb">
                <button type="button" data-cmd="undo" title="Undo"><i class="fas fa-rotate-left"></i></button><button type="button" data-cmd="redo" title="Redo"><i class="fas fa-rotate-right"></i></button>
                <select data-cmd="fontName" title="Font"><option value="">Sans Serif</option>${FONTS.map((f) => `<option value="${f}">${f}</option>`).join('')}</select>
                <select data-cmd="fontSize" title="Size"><option value="">Size</option><option value="2">Small</option><option value="3">Normal</option><option value="5">Large</option><option value="6">Huge</option></select>
                <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><i class="fas fa-bold"></i></button><button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i class="fas fa-italic"></i></button><button type="button" data-cmd="underline" title="Underline (Ctrl+U)"><i class="fas fa-underline"></i></button><button type="button" data-cmd="strikeThrough" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                <input type="color" data-cmd="foreColor" value="#0a6cf0" title="Text colour">
                <button type="button" data-cmd="justifyLeft" title="Align left"><i class="fas fa-align-left"></i></button><button type="button" data-cmd="justifyCenter" title="Centre"><i class="fas fa-align-center"></i></button>
                <button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button><button type="button" data-cmd="insertUnorderedList" title="Bulleted list"><i class="fas fa-list-ul"></i></button>
                <button type="button" data-cmd="outdent" title="Less indent"><i class="fas fa-outdent"></i></button><button type="button" data-cmd="indent" title="More indent"><i class="fas fa-indent"></i></button>
                <button type="button" data-cmd="blockquote" title="Quote"><i class="fas fa-quote-right"></i></button><button type="button" data-cmd="link" title="Insert link (Ctrl+K)"><i class="fas fa-link"></i></button>
                <button type="button" data-cmd="removeFormat" title="Remove formatting"><i class="fas fa-text-slash"></i></button>
            </div>
            <div class="mx-cfoot">
                <span class="mx-send"><button type="button" data-a="cw-send">Send</button><button type="button" data-menu data-a="cw-schedule" aria-label="Schedule send"><i class="fas fa-caret-down"></i></button></span>
                <label class="mx-ib" title="Attach files (or drop them here)" style="cursor:pointer"><i class="fas fa-paperclip"></i><input type="file" multiple hidden data-f="files"></label>
                <button type="button" class="mx-ib${CW.sig ? ' on' : ''}" data-a="cw-sig" data-tip="${CW.sig ? 'Signature on' : 'Signature off'}"><i class="fas fa-signature"></i></button>
                <span class="st" data-st>${totalAtt ? kb(totalAtt) + ' of ' + kb(S.maxAttach) + ' attachments' : ''}</span>
                <span class="sp"></span>
                <button type="button" class="mx-ib" data-a="cw-discard" data-tip="Discard draft"><i class="far fa-trash-can"></i></button>
            </div>`;
        host.appendChild(el);
        CW.el = el;
        document.body.classList.add('mx-composing');
        const ed = $('.mx-ed', el);
        ed.innerHTML = CW.html;
        if (CW.quoted) $('.mx-qt .q', el).innerHTML = CW.quoted;
        if (CW.max && !isPhone()) { const bk = document.createElement('div'); bk.className = 'mx-cback'; host.insertBefore(bk, el); CW.back = bk; }
    }

    function syncCompose() {
        if (!CW || !CW.el) return;
        const ed = $('.mx-ed', CW.el);
        CW.html = ed.innerHTML;
        CW.subject = $('[data-f="subject"]', CW.el).value;
        ['to', 'cc', 'bcc'].forEach((k) => { const inp = $(`[data-chips="${k}"] input`, CW.el); if (inp && inp.value.trim()) { addChips(k, inp.value); inp.value = ''; } });
    }
    function addChips(kind, text) {
        parseAddrs(text.replace(/\s+(?=[^,]*@)/g, ',')).forEach((a) => { if (!CW[kind].some((x) => x.email === a.email)) CW[kind].push(a); });
        const raw = text.split(/[,;\s]+/).filter((t) => t && !/@/.test(t));
        raw.forEach((t) => CW[kind].push({ name: '', email: t }));
        CW.dirty = true;
    }
    function payload() {
        syncCompose();
        return {
            to: CW.to, cc: CW.cc, bcc: CW.bcc, subject: CW.subject, html: CW.html, quoted_html: CW.quoted,
            signature: CW.sig, attachments: CW.files.map((f) => ({ name: f.name, type: f.type, data: f.data })),
            refs: CW.refs.map((r) => ({ f: r.f, u: r.u, i: r.i })), reply: CW.reply, draft: CW.draft,
        };
    }
    function hasContent() { syncCompose(); return CW.to.length || CW.cc.length || CW.bcc.length || CW.subject.trim() || CW.html.replace(/<[^>]+>|&nbsp;/g, '').trim() || CW.files.length; }

    async function autosave(force) {
        if (!CW || CW.saving || CW.sending || (!CW.dirty && !force) || !hasContent()) return;
        if (CW.to.concat(CW.cc, CW.bcc).some((a) => !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(a.email))) return;   // server rejects bad addresses
        CW.saving = true; CW.dirty = false;
        const c = CW;
        const st = $('[data-st]', c.el); if (st) st.textContent = 'Saving…';
        try {
            const d = await api('draft_save', payload(), true);
            if (d.draft) {
                // Everything is in the new draft now, in the same order: keep references, drop the uploaded data
                const all = c.files.map((f) => ({ name: f.name, size: f.size })).concat(c.refs.map((r) => ({ name: r.name, size: r.size })));
                c.refs = all.map((a, i) => ({ f: d.folder, u: d.draft, i, name: a.name, size: a.size }));
                c.files = [];
                c.draft = d.draft;
            }
            c.savedAt = new Date();
            if (c.el && $('[data-st]', c.el)) $('[data-st]', c.el).textContent = 'Draft saved';
        } catch (e) {
            c.dirty = true; const s2 = c.el && $('[data-st]', c.el); if (s2) s2.textContent = 'Draft not saved';
        } finally { c.saving = false; }
    }

    async function closeCompose(silent) {
        if (!CW) return;
        const c = CW;
        clearInterval(c.timer);
        if (!silent && c.el && hasContent() && (c.dirty || !c.draft)) { c.dirty = true; await autosave(true); toast(c.draft ? 'Draft saved.' : 'Draft not saved (check the addresses).'); }
        if (c.el) c.el.remove();
        if (c.back) c.back.remove();
        if (CW === c) { CW = null; document.body.classList.remove('mx-composing'); }
        if (curRole() === 'drafts' && !S.reading) loadList(true);
    }

    async function discardCompose() {
        if (!CW) return;
        const c = CW; clearInterval(c.timer); CW = null;
        c.el.remove(); if (c.back) c.back.remove();
        document.body.classList.remove('mx-composing');
        if (c.draft) { try { await api('draft_delete', { draft: c.draft }, true); } catch (e) { /* ignore */ } }
        toast('Draft discarded.');
        if (curRole() === 'drafts') loadList(true);
    }

    async function send(scheduleAt) {
        syncCompose();
        const all = CW.to.concat(CW.cc, CW.bcc);
        if (!all.length) return err('Please specify at least one recipient.');
        const bad = all.find((a) => !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(a.email));
        if (bad) return err('The address "' + bad.email + '" is not valid.');
        const c = CW;
        if (!c.subject.trim() && !(await confirmBox('Send this message without a subject?', 'Send'))) return;
        if (CW !== c) return;
        const p = payload();
        if (scheduleAt) p.schedule_at = scheduleAt;
        c.sending = true;
        clearInterval(c.timer);
        c.el.hidden = true; if (c.back) c.back.hidden = true; document.body.classList.remove('mx-composing');
        const reopen = () => { c.sending = false; c.el.hidden = false; if (c.back) c.back.hidden = false; CW = c; document.body.classList.add('mx-composing'); c.timer = setInterval(autosave, 10000); };
        const go = async () => {
            const t = toast(scheduleAt ? 'Scheduling…' : 'Sending…', { ms: 0 });
            try {
                const d = await api('send', p, true);
                t.remove();
                c.el.remove(); if (c.back) c.back.remove();
                if (CW === c) CW = null;
                toast(scheduleAt ? 'Send scheduled for ' + esc(new Date(d.scheduled).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })) + '.' : 'Message sent.',
                    scheduleAt ? { actions: [{ label: 'View', run: () => setView('scheduled') }] } : { actions: [{ label: 'View message', run: () => setView('sent') }] });
                if (['sent', 'drafts', 'scheduled'].includes(curRole()) && !S.reading) loadList(true);
                loadFolders().catch(() => {});
            } catch (e) { t.remove(); reopen(); err(e); }
        };
        if (scheduleAt) return go();
        // Undo send: wait a few seconds before anything leaves
        let undone = false;
        CW = null;
        toast('Sending… <b style="margin-left:6px">Undo</b> available for 7 seconds', {
            ms: 7000, actions: [{ label: 'Undo', run: () => { undone = true; reopen(); toast('Sending undone.'); } }],
            onTimeout: () => { if (!undone) go(); },
        });
    }

    function scheduleMenu(btn) {
        const at = (d, h, m) => { const x = new Date(); x.setDate(x.getDate() + d); x.setHours(h, m || 0, 0, 0); return x; };
        const mon = () => { const x = new Date(); x.setDate(x.getDate() + ((8 - x.getDay()) % 7 || 7)); x.setHours(8, 0, 0, 0); return x; };
        const opts = [['Tomorrow morning', at(1, 8)], ['Tomorrow afternoon', at(1, 13)], ['Monday morning', mon()]];
        const fmt = (d) => d.toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
        const m = menu(btn, [{ hd: 'Schedule send' }].concat(opts.map(([l, d]) => ({ icon: 'far fa-clock', label: l, small: fmt(d), run: () => send(sqlStamp(d)) })),
            [{ hr: true }, { html: `<div class="in"><input type="datetime-local" data-sch min="${localInput(new Date(Date.now() + 120000))}" value="${localInput(at(1, 8))}"><button type="button" class="mx-btn pri" data-a="sch-go" style="height:34px">Schedule</button></div>` }]));
        m.addEventListener('click', (e) => { if (e.target.closest('[data-a="sch-go"]')) { const v = $('[data-sch]', m).value; if (v) { closeMenu(); send(v.replace('T', ' ') + ':00'); } } });
    }

    async function addFiles(files) {
        if (!CW) return;
        syncCompose();
        let total = CW.files.reduce((a, f) => a + f.size, 0) + CW.refs.reduce((a, r) => a + (r.size || 0), 0);
        for (const f of files) {
            if (total + f.size > S.maxAttach) { err(`"${f.name}" is too large. Attachments can be up to ${kb(S.maxAttach)} per message in total.`); continue; }
            const data = await new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(String(r.result).split(',')[1] || ''); r.onerror = rej; r.readAsDataURL(f); });
            CW.files.push({ name: f.name, type: f.type || 'application/octet-stream', size: f.size, data });
            total += f.size;
        }
        CW.dirty = true;
        renderCompose();
    }

    function replyTo(mode, m) {
        const own = boxInfo().address;
        const from = parseAddrs(m.reply_to || m.from);
        const subj = mode === 'forward' ? prefix('Fwd', m.subject) : prefix('Re', m.subject);
        const o = { title: subj, subject: subj, reply: { f: m.f, u: m.uid } };
        if (mode === 'forward') {
            o.reply = null;
            o.quoted = forwardOf(m);
            o.refs = (m.attachments || []).map((a) => ({ f: m.f, u: m.uid, i: a.i, name: a.name, size: a.size }));
        } else {
            const sentByUs = isMine((parseAddrs(m.from)[0] || {}).email || '');
            o.to = sentByUs ? parseAddrs(m.to) : from;
            if (mode === 'replyall') {
                const seen = new Set(o.to.map((a) => a.email).concat([String(own).toLowerCase()]));
                o.cc = parseAddrs(m.to).concat(parseAddrs(m.cc)).filter((a) => !seen.has(a.email) && !isMine(a.email) && seen.add(a.email));
            }
            o.quoted = quoteOf(m);
        }
        compose(o);
    }

    async function openDraft(m) {
        try {
            const d = await api('read', { f: m.f, uid: m.uid, single: 1 });
            const x = d.messages[0];
            compose({
                title: x.subject || 'Draft', subject: x.subject, to: parseAddrs(x.to), cc: parseAddrs(x.cc), bcc: parseAddrs(x.bcc),
                html: x.html ? x.html.replace(/^<div style="font-family:Arial[^"]*">([\s\S]*)<\/div>\s*$/, '$1') : linkify(x.text || '').replace(/\n/g, '<br>'),
                refs: (x.attachments || []).map((a) => ({ f: x.f, u: x.uid, i: a.i, name: a.name, size: a.size })), draft: x.uid, sig: false,
            });
        } catch (e) { err(e); }
    }

    // Address autocomplete (the academy's users + people we have mailed)
    let acTimer = 0, acEl = null, acList = [], acIdx = 0;
    function closeAc() { if (acEl) { acEl.remove(); acEl = null; } }
    function showAc(input) {
        clearTimeout(acTimer);
        const q = input.value.trim().toLowerCase();
        if (!q) return closeAc();
        acTimer = setTimeout(async () => {
            const local = [];
            S.contacts.forEach((name, email) => { if (email.includes(q) || String(name).toLowerCase().includes(q)) local.push({ email, name, role: '' }); });
            let remote = [];
            try { remote = (await api('contacts', { q })).contacts; } catch (e) { /* offline */ }
            const seen = new Set(); acList = remote.concat(local).filter((c) => !seen.has(c.email) && seen.add(c.email)).slice(0, 8);
            closeAc();
            if (!acList.length || !input.isConnected || input.value.trim().toLowerCase() !== q) return;
            acIdx = 0;
            acEl = document.createElement('div');
            acEl.className = 'mx-ac';
            acEl.innerHTML = acList.map((c, i) => `<div data-ac="${i}" class="${i === 0 ? 'on' : ''}">${avatar(c.name || c.email, c.email, 'mx-av', 'width:28px;height:28px;font-size:11px')}<span>${esc(c.name || c.email)} <small>${c.name ? esc(c.email) : ''}</small></span>${c.role ? `<em class="mx-role">${esc(c.role)}</em>` : ''}</div>`).join('');
            input.closest('.mx-cf').appendChild(acEl);
            acEl.addEventListener('mousedown', (e) => { const r = e.target.closest('[data-ac]'); if (r) { e.preventDefault(); pickAc(input, +r.dataset.ac); } });
        }, 160);
    }
    function pickAc(input, i) {
        const c = acList[i]; closeAc(); if (!c || !CW) return;
        const kind = input.dataset.k;
        input.value = '';   // before syncCompose(), or the typed fragment would become a recipient too
        syncCompose();
        if (!CW[kind].some((x) => x.email === c.email)) CW[kind].push({ name: c.name || '', email: c.email });
        CW.dirty = true;
        const wrap = input.closest('.mx-cf');
        wrap.querySelector('.mx-chips').outerHTML = chipsHtml(kind);
        $(`[data-chips="${kind}"] input`, CW.el).focus();
    }

    // ── Menus: snooze / move / labels / selection / more ─────────────────────────────
    function snoozeMenu(btn, items) {
        const at = (d, h) => { const x = new Date(); x.setDate(x.getDate() + d); x.setHours(h, 0, 0, 0); return x; };
        const later = new Date(Date.now() + 3 * 3600000); later.setMinutes(0, 0, 0);
        const sat = () => { const x = new Date(); x.setDate(x.getDate() + ((6 - x.getDay() + 7) % 7 || 7)); x.setHours(8, 0, 0, 0); return x; };
        const mon = () => { const x = new Date(); x.setDate(x.getDate() + ((8 - x.getDay()) % 7 || 7)); x.setHours(8, 0, 0, 0); return x; };
        const fmt = (d) => d.toLocaleString([], { weekday: 'short', hour: 'numeric', minute: '2-digit' });
        const list = [['Later today', later], ['Tomorrow', at(1, 8)], ['This weekend', sat()], ['Next week', mon()]].filter(([, d]) => d > Date.now() + 60000);
        const m = menu(btn, [{ hd: 'Snooze until…' }].concat(list.map(([l, d]) => ({ label: l, small: fmt(d), run: () => doOp('snooze', items, { until: sqlStamp(d) }) })),
            [{ hr: true }, { html: `<div class="in"><input type="datetime-local" data-snz min="${localInput(new Date(Date.now() + 120000))}" value="${localInput(at(1, 8))}"><button type="button" class="mx-btn pri" data-a="snz-go" style="height:34px">Save</button></div>` }]));
        m.addEventListener('click', (e) => { if (e.target.closest('[data-a="snz-go"]')) { const v = $('[data-snz]', m).value; if (v) { closeMenu(); doOp('snooze', items, { until: v.replace('T', ' ') + ':00' }); } } });
    }
    function moveMenu(btn, items) {
        const sys = [['inbox', 'Inbox', 'fas fa-inbox'], ['archive', S.gmail ? 'Archive (All Mail)' : 'Archive', 'fas fa-box-archive'], ['spam', 'Spam', 'fas fa-circle-exclamation'], ['trash', 'Trash', 'far fa-trash-can']];
        const targets = S.gmail ? S.labels.map((l) => ({ icon: 'fas fa-tag', label: l.name, run: () => doOp('move', items, { target: l.id }) }))
            : customFolders().map((f) => ({ icon: 'far fa-folder', label: f.label, run: () => doOp('move', items, { target: f.name }) }));
        const m = menu(btn, [{ hd: 'Move to' }, { html: '<div class="in"><input type="text" data-flt placeholder="Filter"></div>' }]
            .concat(sys.filter(([r]) => S.roles[r]).map(([r, l, ic]) => ({ icon: ic, label: l, run: () => doOp(r === 'archive' ? 'archive' : 'move', items, r === 'archive' ? {} : { target: S.roles[r] }) })))
            .concat(targets)
            .concat([{ hr: true }, S.gmail ? { icon: 'fas fa-plus', label: 'Create new label', run: () => newLabel(items, true) } : { icon: 'fas fa-plus', label: 'Create new folder', run: () => newFolder(items) }]));
        const flt = $('[data-flt]', m); flt.focus();
        flt.addEventListener('input', () => { const q = flt.value.toLowerCase(); $$('.it', m).forEach((b) => { b.hidden = q && !b.textContent.toLowerCase().includes(q); }); });
    }
    function labelsMenu(btn, items) {
        const have = new Set();
        const kw = S.reading && S.reading.labels ? S.reading.labels : [].concat(...Array.from(S.sel).map((i) => [].concat(...S.groups[i].ms.map((m) => m.keywords || []))));
        kw.forEach((k) => have.add(k));
        const m = menu(btn, [{ hd: 'Label as' }].concat(S.labels.map((l) => ({ html: `<label class="it" style="cursor:pointer"><input type="checkbox" data-lbl="${esc(l.id)}" ${have.has(l.id) ? 'checked' : ''} style="accent-color:${l.color}"><span class="dot" style="width:10px;height:10px;border-radius:3px;background:${l.color};display:inline-block"></span><span>${esc(l.name)}</span></label>` })))
            .concat([{ hr: true }, { icon: 'fas fa-plus', label: 'Create new label', run: () => newLabel(items) }]));
        m.addEventListener('change', async (e) => {
            const c = e.target.closest('[data-lbl]'); if (!c) return;
            const ok = await op(c.checked ? 'label' : 'unlabel', items, { label: c.dataset.lbl });
            if (ok === false) { c.checked = !c.checked; return; }
            if (S.reading) { const set = new Set(S.reading.labels || []); c.checked ? set.add(c.dataset.lbl) : set.delete(c.dataset.lbl); S.reading.labels = Array.from(set); renderRead(); }
            else loadList(true);
        });
    }
    async function newLabel(items, move) {
        const name = await promptBox('New label name', ''); if (!name) return;
        try {
            const d = await api('label_save', { name, color: S.colors[S.labels.length % S.colors.length] }, true);
            S.labels = d.labels; renderNav();
            if (items && items.length && d.id) {
                if (move) await doOp('move', items, { target: d.id });
                else { await op('label', items, { label: d.id }); if (S.reading) { S.reading.labels = (S.reading.labels || []).concat(d.id); renderRead(); } else loadList(true); }
            }
        } catch (e) { err(e); }
    }
    async function newFolder(items) {
        const name = await promptBox('New folder name', ''); if (!name) return;
        try {
            const d = await api('folder_create', { name }, true);
            await loadFolders();
            if (items && items.length) doOp('move', items, { target: d.folder });
        } catch (e) { err(e); }
    }

    // ── Settings ─────────────────────────────────────────────────────────────────────
    function settings(tab) {
        closeMenu();
        const tabs = [['account', 'Mailbox connection'], ['sig', 'Signature'], ['lbl', S.gmail ? 'Labels' : 'Labels & folders'], ['keys', 'Shortcuts']];
        const box = boxInfo();
        const acct = document.getElementById('settings');
        const home = acct ? { parent: acct.parentNode, next: acct.nextSibling } : null;
        const dlg = modal(`<i class="fas fa-gear"></i> Settings &middot; <span style="color:var(--text-dim);font-weight:600">${esc(box.address || '')}</span>`, '<div data-pane></div>', [], {
            wide: true, noFocus: true, tabs: tabs.map(([k, l]) => `<button type="button" data-tab="${k}" class="${k === (tab || 'account') ? 'on' : ''}">${l}</button>`).join(''),
            beforeClose: () => { if (acct && home) { acct.hidden = true; home.parent.insertBefore(acct, home.next); } if (location.hash === '#settings') history.replaceState(null, '', location.pathname + location.search); },
        });
        const pane = $('[data-pane]', dlg.el);
        const show = (k) => {
            $$('[data-tab]', dlg.el).forEach((b) => b.classList.toggle('on', b.dataset.tab === k));
            if (acct && acct.parentNode === pane) { acct.hidden = true; home.parent.insertBefore(acct, home.next); }
            pane.onclick = pane.oninput = pane.onchange = null;
            PANES[k](pane, dlg, acct);
        };
        dlg.el.addEventListener('click', (e) => { const t = e.target.closest('[data-tab]'); if (t) show(t.dataset.tab); });
        show(tab || 'account');
    }
    const loadingPane = (p) => { p.innerHTML = '<div class="mx-loading"></div>'; };
    const PANES = {
        account(p, dlg, acct) {
            // The server-rendered "Mailbox connection" panel (forms post normally) is moved in here while the dialog is open
            p.innerHTML = '';
            if (!acct) { p.innerHTML = '<p class="mx-note">Open <a href="mail#settings">Mailbox connection</a>.</p>'; return; }
            acct.hidden = false;
            p.appendChild(acct);
        },
        async sig(p) {
            // Signature designer: fields + template + colour on the left, live preview (desktop / phone) on the right
            loadingPane(p);
            let d;
            try { d = await api('signature'); } catch (e) { p.innerHTML = `<div class="mx-err">${esc(e.message)}</div>`; return; }
            const s = d.signature;
            let tpl = s.template, color = s.color, image = s.image, timer = null;
            const fld = (k, label, max, ph) => `<label class="mx-fld"><span>${label}</span><input class="mx-in" data-s="${k}" value="${esc(s[k] || '')}" maxlength="${max}" placeholder="${ph || ''}"></label>`;
            p.innerHTML = `<div class="mx-sigd">
                <div class="form">
                    <label class="mx-sw main"><input type="checkbox" data-s="enabled" ${s.enabled ? 'checked' : ''}><span><b>Add my signature to emails I send from ${esc(boxInfo().address)}</b><small>Turn it off for one message with the <i class="fas fa-signature"></i> button in the compose window. Each admin has their own signature.</small></span></label>
                    <div class="mx-fld"><span>Layout</span><div class="mx-tpls">${Object.entries(d.templates).map(([k, l]) => `<button type="button" data-tpl="${k}" class="${k === tpl ? 'on' : ''}"><i class="tp tp-${k}"></i>${esc(l)}</button>`).join('')}</div></div>
                    <div class="mx-fld"><span>Accent colour</span><div class="mx-swatches">${d.colors.map((c) => `<button type="button" data-col="${c}" class="${c === color ? 'on' : ''}" style="background:${c}" aria-label="${c}"></button>`).join('')}<input type="color" data-colpick value="${esc(color)}" title="Any colour" style="width:30px;height:26px;padding:0;border:0;background:none"></div></div>
                    <div class="mx-g2">${fld('name', 'Full name', 100)}${fld('title', 'Job title', 100)}${fld('department', 'Department', 100, 'e.g. Admissions')}${fld('email', 'Email shown', 120)}${fld('phone', 'Phone', 40, '0712 345 678')}${fld('phone2', 'Second phone (optional)', 40)}${fld('website', 'Website', 120)}${fld('address', 'Address', 160)}</div>
                    <div class="mx-fld"><span>Picture</span><div class="mx-imgpick">${[['logo', 'Academy logo'], ['none', 'No picture']].map(([k, l]) => `<label><input type="radio" name="sigimg" data-img="${k}" ${k === image ? 'checked' : ''}> ${l}</label>`).join('')}</div><small class="mx-note">Compact and Minimal layouts show no picture. The logo loads from the site address set in Learning Settings.</small></div>
                    ${fld('tagline', 'Tagline or quote', 160)}
                    ${fld('extra', 'Extra line (optional)', 240, 'e.g. Office hours: Mon–Fri 8am–5pm')}
                    <details class="mx-dt"><summary>Social links</summary><div class="mx-g2">${Object.entries(d.socials).map(([k, l]) => `<label class="mx-fld"><span>${esc(l)}</span><input class="mx-in" data-soc="${k}" value="${esc((s.social || {})[k] || '')}" maxlength="200" placeholder="https://"></label>`).join('')}</div></details>
                    <details class="mx-dt" ${s.disclaimer ? 'open' : ''}><summary>Confidentiality note</summary><label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" data-s="disclaimer" ${s.disclaimer ? 'checked' : ''}> Add this small note under my signature</label><textarea class="mx-in" data-s="disclaimer_text" rows="3" maxlength="500">${esc(s.disclaimer_text || '')}</textarea></details>
                    <label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" data-s="skip_replies" ${s.skip_replies ? 'checked' : ''}> Leave my signature off replies and forwards</label>
                </div>
                <div class="side">
                    <div class="mx-fld"><span>Preview <span class="mx-seg"><button type="button" data-view="desktop" class="on"><i class="fas fa-desktop"></i> Desktop</button><button type="button" data-view="phone"><i class="fas fa-mobile-screen"></i> Phone</button></span></span>
                        <div class="mx-sigframe" data-frame><iframe title="Signature preview" sandbox="" data-prev></iframe></div></div>
                    <div><button type="button" class="mx-btn pri" data-a="sig-save"><i class="fas fa-floppy-disk"></i> Save signature</button></div>
                </div></div>`;
            const read = () => {
                const o = { template: tpl, color, image, social: {} };
                $$('[data-s]', p).forEach((i) => { o[i.dataset.s] = i.type === 'checkbox' ? i.checked : i.value; });
                $$('[data-soc]', p).forEach((i) => { o.social[i.dataset.soc] = i.value.trim(); });
                return o;
            };
            const show = (html) => { const f = $('[data-prev]', p); if (f) f.srcdoc = '<!doctype html><meta charset="utf-8"><body style="margin:0;padding:10px 12px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1e293b"><p style="margin:0;color:#94a3b8">Kind regards,</p>' + html + '</body>'; };
            const refresh = () => { clearTimeout(timer); timer = setTimeout(async () => { try { show((await api('signature_preview', { signature: read() }, true)).preview); } catch (e) { /* keep the last preview */ } }, 350); };
            show(d.preview);
            p.oninput = (e) => { if (e.target.matches('[data-colpick]')) { color = e.target.value; $$('[data-col]', p).forEach((x) => x.classList.remove('on')); } if (e.target.matches('[data-s], [data-soc], [data-colpick]')) refresh(); };
            p.onchange = (e) => {
                const t = e.target;
                if (t.matches('[data-img]')) { image = t.dataset.img; refresh(); }
                if (t.matches('[data-s]')) refresh();
            };
            p.onclick = async (e) => {
                const t = e.target.closest('[data-tpl], [data-col], [data-view], [data-a]'); if (!t) return;
                if (t.dataset.tpl) { tpl = t.dataset.tpl; $$('[data-tpl]', p).forEach((x) => x.classList.toggle('on', x === t)); return refresh(); }
                if (t.dataset.col) { color = t.dataset.col; $$('[data-col]', p).forEach((x) => x.classList.toggle('on', x === t)); $('[data-colpick]', p).value = color; return refresh(); }
                if (t.dataset.view) { $$('[data-view]', p).forEach((x) => x.classList.toggle('on', x === t)); $('[data-frame]', p).classList.toggle('phone', t.dataset.view === 'phone'); return; }
                if (t.dataset.a === 'sig-save') {
                    try { const r = await api('signature_save', { signature: read() }, true); show(r.preview); toast('Signature saved.'); } catch (ex) { err(ex); }
                }
            };
        },
        lbl(p) {
            const draw = () => {
                p.innerHTML = `<div class="mx-fld"><span>Labels${S.gmail ? ' (your Gmail labels)' : ''}</span><div class="mx-list2">${S.labels.map((l) => `<div><span class="dot" style="width:14px;height:14px;border-radius:4px;background:${l.color};flex:none"></span><span>${esc(l.name)}</span><button type="button" class="mx-btn" data-a="lbl-edit" data-id="${esc(l.id)}" style="height:32px">Edit</button><button type="button" class="mx-btn dng" data-a="lbl-del" data-id="${esc(l.id)}" style="height:32px">Delete</button></div>`).join('') || '<div><span class="mx-note">No labels yet.</span></div>'}</div></div>
                    <div><button type="button" class="mx-btn" data-a="lbl-new"><i class="fas fa-plus"></i> New label</button></div>
                    ${S.gmail ? '<p class="mx-note">Gmail labels are shared with Gmail itself: a label made here shows up in Gmail and the other way round. Deleting a label keeps its messages (they stay in All Mail). Use "/" for a nested label, e.g. <b>Courses/Python</b>.</p>'
                        : `<div class="mx-fld"><span>Folders</span><div class="mx-list2">${customFolders().map((f) => `<div><i class="far fa-folder"></i><span>${esc(f.label)} <small class="mx-note">${f.total == null ? '' : f.total + ' messages'}</small></span><button type="button" class="mx-btn" data-a="fld-ren" data-f="${esc(f.name)}" style="height:32px">Rename</button><button type="button" class="mx-btn dng" data-a="fld-del" data-f="${esc(f.name)}" style="height:32px">Delete</button></div>`).join('') || '<div><span class="mx-note">No folders yet.</span></div>'}</div></div>
                    <div><button type="button" class="mx-btn" data-a="fld-new"><i class="fas fa-plus"></i> New folder</button></div>`}`;
            };
            draw();
            p.onclick = async (e) => {
                const a = e.target.closest('[data-a]'); if (!a) return;
                try {
                    if (a.dataset.a === 'lbl-new') { await newLabel(); draw(); }
                    if (a.dataset.a === 'lbl-edit') { await editLabel(a.dataset.id); draw(); }
                    if (a.dataset.a === 'lbl-del') { await deleteLabel(a.dataset.id); draw(); }
                    if (a.dataset.a === 'fld-new') { await newFolder(); draw(); }
                    if (a.dataset.a === 'fld-ren') { await renameFolder(a.dataset.f); draw(); }
                    if (a.dataset.a === 'fld-del') { await deleteFolder(a.dataset.f); draw(); }
                } catch (ex) { err(ex); }
            };
        },
        keys(p) {
            const K = [['c', 'Compose'], ['/', 'Search'], ['j / k', 'Older / newer conversation'], ['o or Enter', 'Open'], ['u', 'Back to list'], ['e', 'Archive'], ['#', 'Delete'], ['!', 'Report spam'],
                ['r', 'Reply'], ['a', 'Reply all'], ['f', 'Forward'], ['s', 'Star / unstar'], ['x', 'Select conversation'], ['Shift + I', 'Mark as read'], ['Shift + U', 'Mark as unread'],
                ['b', 'Snooze'], ['v', 'Move to'], ['l', 'Label'], ['g then i', 'Go to Inbox'], ['g then s', 'Go to Starred'], ['g then t', 'Go to Sent'], ['g then d', 'Go to Drafts'], ['g then a', 'Go to All Mail'], ['Ctrl + Enter', 'Send (in compose)'], ['?', 'This list']];
            p.innerHTML = `<div class="mx-keys">${K.map(([k, l]) => `<div><span>${l}</span><span>${k.split(' ').map((x) => ['/', 'or', 'then', '+'].includes(x) && k !== '/' ? ` ${x} ` : `<kbd>${esc(x)}</kbd>`).join('')}</span></div>`).join('')}</div>
                <p class="mx-note">Search understands Gmail operators: <code>from:</code> <code>to:</code> <code>subject:</code> <code>has:attachment</code> <code>is:unread</code> <code>is:starred</code> <code>after:2026/11/01</code> <code>before:</code> <code>older_than:7d</code> <code>newer_than:2m</code> <code>larger:5M</code> <code>-word</code>${S.gmail ? ' and every other Gmail search operator.' : '.'}</p>`;
        },
    };
    async function editLabel(id) {
        const l = labelById(id); if (!l) return;
        await new Promise((resolve) => {
            let color = l.color;
            const d = modal('Edit label', `<label class="mx-fld"><span>Name</span><input class="mx-in" data-n value="${esc(l.name)}" maxlength="60"></label><div class="mx-fld"><span>Colour</span><div class="mx-swatches">${S.colors.map((c) => `<button type="button" data-c="${c}" class="${c === color ? 'on' : ''}" style="background:${c}" aria-label="${c}"></button>`).join('')}</div></div>`,
                [{ label: 'Cancel' }, { label: 'Save', cls: 'pri', run: async (close, el) => { const r = await api('label_save', { id, name: $('[data-n]', el).value, color }, true); S.labels = r.labels; if (S.view.type === 'label' && S.view.label === id && r.id && r.id !== id) { S.view.label = r.id; S.view.nav = 'label:' + r.id; } renderNav(); if (!S.reading) renderList(); } }], { onClose: resolve, enter: true });
            d.el.addEventListener('click', (e) => { const b = e.target.closest('[data-c]'); if (b) { color = b.dataset.c; $$('[data-c]', d.el).forEach((x) => x.classList.toggle('on', x === b)); } });
        });
    }
    async function deleteLabel(id) {
        const l = labelById(id); if (!l || !(await confirmBox(`Delete the label "${l.name}"? ${S.gmail ? 'Its messages stay in All Mail.' : 'Messages keep everything else; only the label is removed.'}`, 'Delete', true))) return;
        const r = await api('label_delete', { id }, true); S.labels = r.labels;
        if (S.view.type === 'label' && S.view.label === id) setView('inbox'); else renderNav();
    }
    async function renameFolder(f) {
        const cur = (S.folders.find((x) => x.name === f) || {}).label || '';
        const name = await promptBox('Rename folder', cur); if (!name || name === cur) return;
        const r = await api('folder_rename', { folder: f, name }, true);
        await loadFolders(); if (S.view.folder === f) setView('f:' + r.folder);
    }
    async function deleteFolder(f) {
        if (!(await confirmBox('Delete this folder? It must be empty.', 'Delete', true))) return;
        await api('folder_delete', { folder: f }, true);
        await loadFolders(); if (S.view.folder === f) setView('inbox');
    }

    // ── Search ───────────────────────────────────────────────────────────────────────
    function runSearch(q) {
        S.q = q.trim(); S.page = 1; S.reading = null;
        const clr = $('[data-a="qclear"]', $('#mxSearch')); if (clr) clr.hidden = !S.q;
        $('#mxAdv').hidden = true;
        loadList();
    }
    function advSearch() {
        const a = $('#mxAdv');
        if (!a.hidden) { a.hidden = true; return; }
        a.innerHTML = `<label>From</label><input class="mx-in" data-q="from"><label>To</label><input class="mx-in" data-q="to"><label>Subject</label><input class="mx-in" data-q="subject">
            <label>Has the words</label><input class="mx-in" data-q="has"><label>Doesn't have</label><input class="mx-in" data-q="not">
            <label>After</label><input class="mx-in" type="date" data-q="after"><label>Before</label><input class="mx-in" type="date" data-q="before">
            <div class="mx-advrow"><label><input type="checkbox" data-q="att"> Has attachment</label><label><input type="checkbox" data-q="unread"> Unread only</label><label><input type="checkbox" data-q="all"> Search all mail</label><span style="flex:1"></span><button type="button" class="mx-btn pri" data-a="adv-go">Search</button></div>
            <div class="mx-hint">You can also type operators: <code>from:</code> <code>to:</code> <code>subject:</code> <code>has:attachment</code> <code>is:unread</code> <code>is:starred</code> <code>after:2026/11/01</code> <code>older_than:7d</code> <code>larger:2M</code> <code>-word</code></div>`;
        a.hidden = false;
        $('[data-q="from"]', a).focus();
    }
    function advGo() {
        const a = $('#mxAdv'), v = (k) => ($(`[data-q="${k}"]`, a).value || '').trim(), c = (k) => $(`[data-q="${k}"]`, a).checked;
        const qv = (s) => /\s/.test(s) ? `"${s.replace(/"/g, '')}"` : s;
        const parts = [];
        if (v('from')) parts.push('from:' + qv(v('from')));
        if (v('to')) parts.push('to:' + qv(v('to')));
        if (v('subject')) parts.push('subject:' + qv(v('subject')));
        if (v('has')) parts.push(v('has'));
        if (v('not')) v('not').split(/\s+/).forEach((w) => parts.push('-' + w));
        if (v('after')) parts.push('after:' + v('after').replace(/-/g, '/'));
        if (v('before')) parts.push('before:' + v('before').replace(/-/g, '/'));
        if (c('att')) parts.push('has:attachment');
        if (c('unread')) parts.push('is:unread');
        const q = parts.join(' ');
        $('#mxQ').value = q;
        if (c('all')) { S.view = { type: 'all', nav: 'all' }; renderNav(); }
        runSearch(q);
    }

    // ── Polling for new mail (and scheduled / snoozed processing) ─────────────────────
    async function poll() {
        if (document.hidden) return;
        try {
            const d = await api('poll', { since: S.lastUid || 0 });
            if (S.boxes[0]) S.boxes[0].unseen = d.unseen;
            const inboxF = S.folders.find((f) => f.role === 'inbox'); if (inboxF) inboxF.unseen = d.unseen;
            renderNav();
            if (d.new && d.new.length) {
                S.lastUid = Math.max(S.lastUid, ...d.new.map((n) => n.uid));
                const n = d.new[0], a = parseAddrs(n.from)[0];
                toast(`<i class="fas fa-envelope" style="color:#7dd3fc"></i> New message from <b>${esc(a ? nameOf(a) : n.from)}</b>: ${esc(n.subject || '(no subject)')}`, { actions: [{ label: 'Inbox', run: () => setView('inbox') }] });
                if (S.view.nav === 'inbox' && S.page === 1 && !S.q && !S.reading) loadList(true);
            }
            if (d.tick && (d.tick.sent || d.tick.unsnoozed || d.tick.failed)) { loadFolders().catch(() => {}); if (curRole() === 'scheduled' || curRole() === 'inbox') loadList(true); }
        } catch (e) { /* quiet: next poll will retry */ }
    }

    // ── Events (all delegated on the document) ───────────────────────────────────────
    function onClick(e) {
        if (!document.getElementById('mx')) return;
        const t = e.target;
        const a = t.closest('[data-a]');
        const act = a && a.dataset.a;

        if (t.closest('[data-mx-settings]')) { e.preventDefault(); return settings('account'); }
        if (t.closest('#settings')) return;   // the connection panel's own links and forms
        const navB = t.closest('[data-nav]');
        if (navB && !t.closest('.mx-more')) { S.q = ''; $('#mxQ').value = ''; const c = $('[data-a="qclear"]'); if (c) c.hidden = true; return setView(navB.dataset.nav); }

        // Compose window
        if (CW && CW.el && CW.el.contains(t)) {
            const cmd = t.closest('[data-cmd]');
            if (cmd && cmd.tagName === 'BUTTON') {
                e.preventDefault();
                const ed = $('.mx-ed', CW.el); ed.focus();
                const c = cmd.dataset.cmd;
                if (c === 'link') {
                    const sel = getSelection(); const range = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
                    promptBox('Link address (https://…)', 'https://').then((u) => {
                        if (!u || !CW) return; ed.focus(); if (range) { sel.removeAllRanges(); sel.addRange(range); }
                        if (/^(https?:|mailto:)/i.test(u)) document.execCommand(range && !range.collapsed ? 'createLink' : 'insertHTML', false, range && !range.collapsed ? u : `<a href="${esc(u)}">${esc(u)}</a>`);
                    });
                }
                else if (c === 'blockquote') document.execCommand('formatBlock', false, 'blockquote');
                else document.execCommand(c, false, null);
                CW.dirty = true;
                return;
            }
            if (act === 'chip-x') { syncCompose(); CW[a.dataset.k].splice(+a.dataset.i, 1); CW.dirty = true; a.closest('.mx-chips').outerHTML = chipsHtml(a.dataset.k); return; }
            if (act === 'att-x') { syncCompose(); (a.dataset.t === 'file' ? CW.files : CW.refs).splice(+a.dataset.i, 1); CW.dirty = true; return renderCompose(); }
            if (act === 'cw-cc' || act === 'cw-bcc') { syncCompose(); CW[act === 'cw-cc' ? 'showCc' : 'showBcc'] = true; renderCompose(); $(`[data-chips="${act === 'cw-cc' ? 'cc' : 'bcc'}"] input`, CW.el).focus(); return; }
            if (act === 'cw-min') { e.stopPropagation(); syncCompose(); CW.min = !CW.min; if (CW.min) CW.max = false; return renderCompose(); }
            if (act === 'cw-max') { e.stopPropagation(); syncCompose(); CW.max = !CW.max; CW.min = false; return renderCompose(); }
            if (act === 'cw-close') { e.stopPropagation(); return closeCompose(); }
            if (act === 'cw-discard') return discardCompose();
            if (act === 'cw-send') return send();
            if (act === 'cw-schedule') return scheduleMenu(a);
            if (act === 'cw-sig') { CW.sig = !CW.sig; a.classList.toggle('on', CW.sig); a.dataset.tip = CW.sig ? 'Signature on' : 'Signature off'; CW.dirty = true; return; }
            if (act === 'cw-quote') { const q = $('.mx-qt .q', CW.el); q.hidden = !q.hidden; return; }
            if (t.closest('.mx-chips') && t.tagName !== 'INPUT') { const i = $('input', t.closest('.mx-chips')); if (i) i.focus(); }
            return;
        }
        if (t.closest('.mx-cback')) return closeCompose();

        // Rows
        const row = t.closest('.mx-row[data-row]');
        if (row && !a) {
            const i = +row.dataset.row;
            if (isPhone() && t.closest('.cb')) return toggleSel(i);
            if (S.sel.size && (e.ctrlKey || e.metaKey)) return toggleSel(i);
            return openThread(i);
        }
        if (!act) return;
        switch (act) {
            case 'nav': return $('#mx').classList.toggle('navopen');
            case 'compose': return compose();
            case 'sel': return toggleSel(+row.dataset.row, e.shiftKey);
            case 'selall': { if (S.sel.size === S.groups.length && S.groups.length) S.sel.clear(); else S.groups.forEach((g, i) => S.sel.add(i)); return renderList(); }
            case 'selmenu': return menu(a, [['All', () => true], ['None', () => false], ['Read', (g) => !g.unread], ['Unread', (g) => g.unread], ['Starred', (g) => g.star], ['Unstarred', (g) => !g.star]].map(([l, fn]) => ({ label: l, run: () => { S.sel.clear(); S.groups.forEach((g, i) => { if (fn(g)) S.sel.add(i); }); renderList(); } })));
            case 'star': { const i = +row.dataset.row, g = S.groups[i]; g.star = !g.star; g.ms.forEach((m) => { m.flagged = g.star; }); renderList(); return op(g.star ? 'star' : 'unstar', g.star ? g.items.slice(0, 1) : g.items).then((ok) => { if (curRole() === 'starred' && !g.star && ok) loadList(true); }); }
            case 'mstar': { const i = +a.closest('[data-m]').dataset.m, m = S.reading.messages[i]; m.flagged = !m.flagged; a.classList.toggle('on', m.flagged); a.innerHTML = `<i class="${m.flagged ? 'fas' : 'far'} fa-star"></i>`; return op(m.flagged ? 'star' : 'unstar', [{ f: m.f, u: m.uid }]); }
            case 'rowop': { const g = S.groups[+row.dataset.row]; return doOp(a.dataset.op, g.items); }
            case 'rowsnooze': { const g = S.groups[+row.dataset.row]; return snoozeMenu(a, g.items); }
            case 'op': return doOp(a.dataset.op, selItems());
            case 'rop': return doOp(a.dataset.op, readItems());
            case 'snooze': return snoozeMenu(a, targetItems());
            case 'move': return moveMenu(a, targetItems());
            case 'labels': return labelsMenu(a, targetItems());
            case 'unlabel': return op('unlabel', readItems(), { label: a.dataset.id }).then((ok) => { if (ok === false || !S.reading) return; S.reading.labels = (S.reading.labels || []).filter((x) => x !== a.dataset.id); renderRead(); });
            case 'refresh': loadFolders().catch(() => {}); return loadList();
            case 'page': S.page = Math.max(1, Math.min(S.pages, S.page + +a.dataset.d)); return loadList();
            case 'empty': return confirmBox(`Delete all messages in ${a.dataset.role === 'trash' ? 'Trash' : 'Spam'} forever?`, 'Empty now', true).then(async (ok) => { if (!ok) return; try { const d = await api('empty', { role: a.dataset.role }, true); toast(d.count + ' messages deleted forever.'); loadFolders(); loadList(); } catch (ex) { err(ex); } });
            case 'back': return back();
            case 'nav-msg': { const i = S.cur + +a.dataset.d; if (S.groups[i]) openThread(i); return; }
            case 'toggle-msg': { if (t.closest('button')) return; const m = a.closest('.mx-msg'); m.classList.toggle('mx-collapsed'); const fr = $('iframe', m); if (fr) fitFrame(fr); return; }
            case 'details': { e.stopPropagation(); const d = $('.mx-det', a.closest('.mx-msg')); d.hidden = !d.hidden; return; }
            case 'reply': case 'replyall': case 'forward': { const m = S.reading.messages[+a.dataset.m]; return replyTo(act, m); }
            case 'msg-more': {
                const m = S.reading.messages[+a.dataset.m];
                return menu(a, [{ icon: 'fas fa-reply', label: 'Reply', run: () => replyTo('reply', m) }, { icon: 'fas fa-reply-all', label: 'Reply all', run: () => replyTo('replyall', m) }, { icon: 'fas fa-share', label: 'Forward', run: () => replyTo('forward', m) },
                    { hr: true }, { icon: 'fas fa-print', label: 'Print', run: () => printMsgs([m]) },
                    { icon: 'fas fa-envelope', label: 'Mark unread from here', run: () => doOp('unread', [{ f: m.f, u: m.uid }]) }]);
            }
            case 'more-read': return menu(a, [{ icon: 'fas fa-print', label: 'Print all', run: () => printMsgs(S.reading.messages) },
                { icon: 'fas fa-star', label: 'Star', run: () => op('star', readItems().slice(-1)) }, { icon: 'far fa-star', label: 'Remove star', run: () => op('unstar', readItems()) }]
                .concat(S.gmail ? [{ icon: 'fas fa-bookmark', label: S.reading.important ? 'Mark as not important' : 'Mark as important', run: () => op(S.reading.important ? 'unimportant' : 'important', readItems()).then((ok) => { if (ok !== false && S.reading) { S.reading.important = !S.reading.important; renderRead(); } }) }] : []));
            case 'more-sel': return menu(a, [{ icon: 'fas fa-star', label: 'Add star', run: () => op('star', selItems()).then(() => loadList(true)) }, { icon: 'far fa-star', label: 'Remove star', run: () => op('unstar', selItems()).then(() => loadList(true)) }]
                .concat(S.gmail ? [{ icon: 'fas fa-bookmark', label: 'Mark as important', run: () => op('important', selItems()).then(() => loadList(true)) }, { icon: 'far fa-bookmark', label: 'Mark as not important', run: () => op('unimportant', selItems()).then(() => loadList(true)) }] : []));
            case 'more-list': return menu(a, [{ icon: 'far fa-envelope-open', label: 'Mark all on this page as read', run: () => op('read', [].concat(...S.groups.map((g) => g.items))).then(() => loadList(true)) }, { icon: 'fas fa-gear', label: 'Settings', run: () => settings('account') }]);
            case 'preview': return previewAtt(a.dataset.url, a.dataset.type, a.dataset.name);
            case 'sched-cancel': return api('scheduled_cancel', { id: +a.dataset.id }, true).then((d) => { toast('Send cancelled. The message is back in a draft window.'); const m = d.message; compose({ to: m.to, cc: m.cc, bcc: m.bcc, subject: m.subject, html: m.html, files: (m.attachments || []).map((x) => ({ name: x.name, type: x.type, data: x.data, size: Math.round(x.data.length * 0.75) })) }).then(() => { if (CW) CW.dirty = true; }); loadList(); loadFolders(); }).catch(err);
            case 'qclear': e.preventDefault(); $('#mxQ').value = ''; return runSearch('');
            case 'qadv': return advSearch();
            case 'adv-go': return advGo();
            case 'searchall': e.preventDefault(); S.view = { type: 'all', nav: 'all' }; renderNav(); return runSearch(S.q);
            case 'settings': return settings('account');
            case 'settings-account': return settings('account');
            case 'keys': return settings('keys');
            case 'label-new': return newLabel();
            case 'folder-new': return newFolder();
            case 'label-menu': e.stopPropagation(); return menu(a, [{ icon: 'fas fa-pen', label: 'Edit label', run: () => editLabel(a.dataset.id).catch(err) }, { icon: 'far fa-trash-can', label: 'Remove label', run: () => deleteLabel(a.dataset.id).catch(err) }]);
            case 'folder-menu': e.stopPropagation(); return menu(a, [{ icon: 'fas fa-pen', label: 'Rename', run: () => renameFolder(a.dataset.f).catch(err) }, { icon: 'far fa-trash-can', label: 'Delete', run: () => deleteFolder(a.dataset.f).catch(err) }]);
        }
    }

    function toggleSel(i, range) {
        if (range && S.lastSel != null) { const [a, b] = [Math.min(S.lastSel, i), Math.max(S.lastSel, i)]; for (let x = a; x <= b; x++) S.sel.add(x); }
        else if (S.sel.has(i)) S.sel.delete(i); else S.sel.add(i);
        S.lastSel = i;
        renderList();
    }

    function printMsgs(list) {
        const fr = document.createElement('iframe');
        fr.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
        host.appendChild(fr);
        const html = list.map((m) => `<div style="border-bottom:1px solid #ccc;padding:10px 0 16px;margin-bottom:16px"><div><b>${esc(m.from)}</b></div><div style="color:#555;font-size:12px">${esc(longDate(m.date))}<br>To: ${esc(m.to)}${m.cc ? '<br>Cc: ' + esc(m.cc) : ''}</div><div style="margin-top:10px">${m.html || '<pre style="white-space:pre-wrap;font-family:inherit">' + esc(m.text) + '</pre>'}</div></div>`).join('');
        fr.srcdoc = frameDoc(`<div style="display:flex;justify-content:space-between;border-bottom:2px solid #0a6cf0;padding-bottom:8px;margin-bottom:12px"><b style="color:#0a6cf0">${esc(BRAND)}</b><span>${esc(boxInfo().address || '')}</span></div><h2 style="font-weight:600">${esc(list[0] ? list[0].subject : '')}</h2>` + html);
        fr.onload = () => { try { fr.contentWindow.focus(); fr.contentWindow.print(); } catch (e) { err('Printing is blocked in this browser.'); } setTimeout(() => fr.remove(), 60000); };
    }

    function previewAtt(url, type, name) {
        const body = /^image\//.test(type) ? `<img src="${esc(url)}" alt="" style="max-width:100%;max-height:72vh;display:block;margin:auto;border-radius:8px">` : `<iframe src="${esc(url)}" style="width:100%;height:72vh;border:0;border-radius:8px;background:#fff" title="${esc(name)}"></iframe>`;
        modal(esc(name), body, [{ label: '<i class="fas fa-download"></i> Download', run: () => { const l = document.createElement('a'); l.href = url.replace('&inline=1', ''); l.download = name; document.body.appendChild(l); l.click(); l.remove(); } }], { wide: true });
    }

    // Keyboard: compose fields, search, and Gmail shortcuts
    let gPrefix = 0;
    function onKey(e) {
        if (!document.getElementById('mx')) return;
        const t = e.target;
        if (t.id === 'mxQ' && e.key === 'Enter') { e.preventDefault(); return runSearch(t.value); }
        if (t.id === 'mxQ' && e.key === 'Escape') { t.blur(); return; }
        if (t.closest && t.closest('#mxAdv') && e.key === 'Enter' && t.tagName === 'INPUT') { e.preventDefault(); return advGo(); }
        if (CW && CW.el && CW.el.contains(t)) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); return send(); }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && t.classList.contains('mx-ed')) { e.preventDefault(); return $('[data-cmd="link"]', CW.el).click(); }
            if (e.key === 'Escape') { if (acEl) return closeAc(); return closeCompose(); }
            if (t.dataset.k && ['to', 'cc', 'bcc'].includes(t.dataset.k)) {
                if (acEl && ['ArrowDown', 'ArrowUp'].includes(e.key)) { e.preventDefault(); acIdx = (acIdx + (e.key === 'ArrowDown' ? 1 : -1) + acList.length) % acList.length; $$('[data-ac]', acEl).forEach((r, i) => r.classList.toggle('on', i === acIdx)); return; }
                if ((['Enter', ',', ';', 'Tab'].includes(e.key) && t.value.trim()) || (e.key === 'Enter' && acEl)) {
                    if (acEl) { e.preventDefault(); return pickAc(t, acIdx); }
                    e.preventDefault(); syncCompose(); closeAc();
                    const k = t.dataset.k; t.closest('.mx-chips').outerHTML = chipsHtml(k); $(`[data-chips="${k}"] input`, CW.el).focus(); return;
                }
                if (e.key === 'Backspace' && !t.value && CW[t.dataset.k].length) { const k = t.dataset.k; CW[k].pop(); t.closest('.mx-chips').outerHTML = chipsHtml(k); $(`[data-chips="${k}"] input`, CW.el).focus(); }
            }
            return;
        }
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || (e.key === 'Enter' && /^(BUTTON|A)$/.test(t.tagName)) || t.isContentEditable || document.querySelector('.mx-modal-bg, dialog[open]')) return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        const k = e.key;
        if (gPrefix && Date.now() - gPrefix < 1200) {
            gPrefix = 0;
            const go = { i: 'inbox', s: 'starred', t: 'sent', d: 'drafts', a: 'all' }[k];
            if (go) { e.preventDefault(); return setView(go); }
        }
        const items = targetItems();
        const map = {
            c: () => compose(), '/': () => $('#mxQ').focus(), '?': () => settings('keys'), g: () => { gPrefix = Date.now(); },
            j: () => { if (S.reading) { if (S.groups[S.cur + 1]) openThread(S.cur + 1); } else { S.cur = Math.min(S.groups.length - 1, S.cur + 1); renderList(); scrollCur(); } },
            k: () => { if (S.reading) { if (S.cur > 0) openThread(S.cur - 1); } else { S.cur = Math.max(0, S.cur - 1); renderList(); scrollCur(); } },
            o: () => { if (!S.reading && S.groups[S.cur]) openThread(S.cur); }, Enter: () => { if (!S.reading && S.groups[S.cur]) openThread(S.cur); },
            u: () => { if (S.reading) back(); }, Escape: () => { if (S.reading) back(); else { S.sel.clear(); renderList(); } },
            x: () => { if (!S.reading && S.groups[S.cur]) toggleSel(S.cur); },
            e: () => items.length && doOp('archive', items), '#': () => items.length && doOp('trash', items), '!': () => items.length && doOp('spam', items),
            r: () => S.reading && S.reading.messages && replyTo('reply', S.reading.messages[S.reading.messages.length - 1]),
            a: () => S.reading && S.reading.messages && replyTo('replyall', S.reading.messages[S.reading.messages.length - 1]),
            f: () => S.reading && S.reading.messages && replyTo('forward', S.reading.messages[S.reading.messages.length - 1]),
            s: () => { if (S.reading && S.reading.messages) { const b = $$('.mx-msg [data-a="mstar"]').pop(); if (b) b.click(); } else if (S.groups[S.cur]) { const b = $(`.mx-row[data-row="${S.cur}"] [data-a="star"]`); if (b) b.click(); } },
            I: () => items.length && doOp('read', items), U: () => items.length && doOp('unread', items),
            b: () => { const b = $('#mxMain [data-a="snooze"]'); if (b && items.length) b.click(); }, v: () => { const b = $('#mxMain [data-a="move"]'); if (b && items.length) b.click(); }, l: () => { const b = $('#mxMain [data-a="labels"]'); if (b && items.length) b.click(); },
        };
        const fn = map[k];
        if (fn) { e.preventDefault(); fn(); }
    }
    function scrollCur() { const r = $(`.mx-row[data-row="${S.cur}"]`); if (r) r.scrollIntoView({ block: 'nearest' }); }
    function onInput(e) {
        const t = e.target;
        if (CW && CW.el && CW.el.contains(t)) {
            CW.dirty = true;
            if (t.dataset.k && ['to', 'cc', 'bcc'].includes(t.dataset.k)) showAc(t);
            if (t.dataset.f === 'subject') { const b = $('.mx-ch b', CW.el); if (b) b.textContent = t.value || CW.title; }
        }
        if (t.id === 'mxQ') { const c = $('[data-a="qclear"]'); if (c) c.hidden = !t.value; }
    }
    function onChange(e) {
        const t = e.target;
        if (CW && CW.el && CW.el.contains(t)) {
            if (t.dataset.f === 'files' && t.files.length) { addFiles(Array.from(t.files)); t.value = ''; return; }
            const cmd = t.dataset.cmd;
            if (cmd) { const ed = $('.mx-ed', CW.el); ed.focus(); if (t.value) document.execCommand(cmd, false, t.value); if (t.tagName === 'SELECT') t.value = ''; CW.dirty = true; }
        }
    }
    function onBlur(e) { if (CW && CW.el && e.target.dataset && ['to', 'cc', 'bcc'].includes(e.target.dataset.k)) setTimeout(() => { closeAc(); if (CW && e.target.isConnected && e.target.value.trim() && document.activeElement !== e.target) { syncCompose(); const k = e.target.dataset.k; e.target.closest('.mx-chips').outerHTML = chipsHtml(k); } }, 150); }
    function onDrag(e) {
        if (!CW || !CW.el || !e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
        if (!CW.el.contains(e.target)) return;
        e.preventDefault();
        let d = $('.mx-drop', CW.el);
        if (e.type === 'dragover' && !d) { d = document.createElement('div'); d.className = 'mx-drop'; d.textContent = 'Drop files here'; CW.el.appendChild(d); }
        if (e.type === 'dragleave' && d && !CW.el.contains(e.relatedTarget)) d.remove();
        if (e.type === 'drop') { if (d) d.remove(); addFiles(Array.from(e.dataTransfer.files)); }
    }

    // ── Start ────────────────────────────────────────────────────────────────────────
    async function start() {
        shell();
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
        document.addEventListener('input', onInput);
        document.addEventListener('change', onChange);
        document.addEventListener('focusout', onBlur);
        ['dragover', 'dragleave', 'drop'].forEach((ev) => document.addEventListener(ev, onDrag));
        window.addEventListener('beforeunload', (e) => { if (CW && (CW.dirty || CW.sending)) { autosave(true); e.preventDefault(); e.returnValue = ''; } });
        window.addEventListener('hashchange', () => { if (location.hash === '#settings' && !$('.mx-modal-bg', host)) settings('account'); });
        try { await loadBoxes(); } catch (e) { $('#mxMain').innerHTML = `<div class="mx-empty warn"><i class="fas fa-triangle-exclamation"></i><h3>Could not open the mailbox</h3><p>${esc(e.message)}</p></div>`; return; }
        renderNav();
        const params = new URLSearchParams(location.search);
        if (location.hash === '#settings') settings('account');
        try { await loadFolders(); setView('inbox'); }
        catch (e) { S.listError = e; S.groups = []; renderList(); }
        if (params.get('q')) { $('#mxQ').value = params.get('q'); runSearch(params.get('q')); }
        if (params.get('compose') || params.get('to')) {
            compose({ to: parseAddrs(params.get('to') || ''), subject: params.get('subject') || '', html: params.get('body') ? esc(params.get('body')).replace(/\n/g, '<br>') : '' });
        }
        setInterval(poll, 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
        window.sdaMailbox = { S, compose };   // for debugging in the console
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => setTimeout(start, 0));
    else setTimeout(start, 0);
})();
