/* Skope Digital Academy app (PWA): registers the service worker and shows the "Install app" card.
 * Loaded with defer from includes/pwa.php: <script src=".../pwa.js" data-scope data-sw data-icon defer>.
 * The head keeps a tiny inline listener that stores an early beforeinstallprompt in window.__sdaBip. */
(function () {
  var me = document.currentScript || document.querySelector('script[data-sw]');
  var SCOPE = me.dataset.scope, SW = me.dataset.sw, ICON = me.dataset.icon;
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(SW, { scope: SCOPE }).catch(function () {}); });
  }
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  if (standalone) return;

  var css = '.sda-install{position:fixed;left:16px;bottom:calc(16px + env(safe-area-inset-bottom));z-index:9990;display:flex;align-items:center;gap:10px;'
    + 'max-width:calc(100vw - 32px);padding:10px 10px 10px 12px;border-radius:14px;background:#0f1a30;color:#f8fafc;border:1px solid rgba(0,191,255,.35);'
    + "box-shadow:0 12px 32px rgba(0,0,0,.35);font:600 14px/1.3 system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;animation:sdaIn .25s ease-out}"
    + '.sda-install img{width:34px;height:34px;border-radius:9px;background:#fff;flex:none}'
    + '.sda-install .t{display:flex;flex-direction:column;min-width:0}'
    + '.sda-install .t small{font-weight:400;color:#94a3b8;font-size:12px}'
    + '.sda-install .go{border:0;border-radius:10px;padding:9px 14px;background:linear-gradient(135deg,#00bfff,#0080ff);color:#fff;font:700 13px system-ui,sans-serif;cursor:pointer;flex:none}'
    + '.sda-install .x{border:0;background:transparent;color:#94a3b8;font-size:20px;line-height:1;width:32px;height:32px;cursor:pointer;flex:none}'
    + '@keyframes sdaIn{from{transform:translateY(12px);opacity:0}to{transform:none;opacity:1}}@media print{.sda-install{display:none!important}}';

  function dismissed() { try { return Date.now() < +(localStorage.getItem('sdaInstallHide') || 0); } catch (e) { return false; } }
  function hide(days) { try { localStorage.setItem('sdaInstallHide', String(Date.now() + days * 864e5)); } catch (e) {} }
  function chip(sub, btn, onGo) {
    if (dismissed() || document.getElementById('sda-install')) return;
    if (!document.getElementById('sda-install-css')) {
      var st = document.createElement('style'); st.id = 'sda-install-css'; st.textContent = css; document.head.appendChild(st);
    }
    var d = document.createElement('div');
    d.className = 'sda-install'; d.id = 'sda-install'; d.setAttribute('role', 'dialog'); d.setAttribute('aria-label', 'Install the Skope Academy app');
    d.innerHTML = '<img alt="" src="' + ICON + '"><span class="t">Get the Skope Academy app<small>' + sub + '</small></span>'
      + (btn ? '<button type="button" class="go">' + btn + '</button>' : '') + '<button type="button" class="x" aria-label="Not now">&times;</button>';
    document.body.appendChild(d);
    d.querySelector('.x').onclick = function () { d.remove(); hide(14); };
    if (btn) d.querySelector('.go').onclick = function () { onGo(d); };
  }
  function offer(ev) {
    var show = function () {
      chip('Fast, full screen, works on weak data', 'Install', function (d) {
        d.remove(); ev.prompt();
        ev.userChoice.then(function (c) { if (c.outcome !== 'accepted') hide(14); });
      });
    };
    if (document.readyState === 'complete') setTimeout(show, 2500); else window.addEventListener('load', function () { setTimeout(show, 2500); });
  }
  if (window.__sdaBip) offer(window.__sdaBip);
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); window.__sdaBip = e; offer(e); });
  window.addEventListener('appinstalled', function () { var d = document.getElementById('sda-install'); if (d) d.remove(); hide(3650); });

  // iPhone / iPad Safari has no install prompt: show how to add it once in a while
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (ios && /safari/i.test(navigator.userAgent) && !/crios|fxios|edgios/i.test(navigator.userAgent)) {
    window.addEventListener('load', function () { setTimeout(function () { chip('Tap Share <b>&#x2191;</b> then <b>Add to Home Screen</b>', '', null); }, 4000); });
  }
})();
