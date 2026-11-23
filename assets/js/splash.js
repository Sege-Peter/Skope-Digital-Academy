/* Skope Digital Academy app: launch animation ("Powered by Skope Designs").
 * Loaded by includes/pwa.php only when the installed app starts (or with ?splash=1 to preview).
 * The page stays hidden (html.sda-splash) until the animation hands over. */
(function () {
  var root = document.documentElement;
  var me = document.currentScript;
  var logo = (me && me.dataset.logo) || 'assets/pwa/icon-192.png';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var HOLD = reduce ? 700 : 1850;

  var css = ''
    + '#sda-splash{position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;justify-content:center;'
    + 'background:radial-gradient(120% 80% at 50% 35%,#13284a 0%,#0b1220 60%,#070c16 100%);color:#fff;'
    + "font-family:Poppins,'Segoe UI',Roboto,system-ui,sans-serif;overflow:hidden;visibility:visible!important;opacity:1;transition:opacity .45s ease}"
    + '#sda-splash.out{opacity:0}'
    + '#sda-splash .sp-glow{position:absolute;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle,rgba(0,191,255,.22),rgba(0,191,255,0) 62%);animation:spGlow 2.4s ease-in-out infinite}'
    + '#sda-splash .sp-tile{position:relative;width:104px;height:104px;border-radius:28px;background:#fff;display:grid;place-items:center;'
    + 'box-shadow:0 18px 50px -12px rgba(0,191,255,.55),0 0 0 1px rgba(255,255,255,.08);animation:spPop .7s cubic-bezier(.2,1.4,.4,1) both}'
    + '#sda-splash .sp-tile img{width:84px;height:84px;border-radius:20px}'
    + '#sda-splash .sp-ring{position:absolute;inset:-14px;border-radius:36px;border:2px solid rgba(0,191,255,.55);animation:spRing 1.6s .35s ease-out infinite}'
    + '#sda-splash .sp-name{margin-top:26px;font-weight:800;font-size:1.45rem;letter-spacing:.4px;animation:spUp .6s .35s ease-out both}'
    + '#sda-splash .sp-name b{color:#FF8C00}'
    + '#sda-splash .sp-sub{margin-top:4px;font-weight:700;font-size:.72rem;letter-spacing:6px;color:#7dd3fc;animation:spUp .6s .5s ease-out both}'
    + '#sda-splash .sp-bar{margin-top:28px;width:150px;height:4px;border-radius:4px;background:rgba(255,255,255,.1);overflow:hidden;animation:spUp .5s .6s ease-out both}'
    + '#sda-splash .sp-bar i{display:block;height:100%;width:0;border-radius:4px;background:linear-gradient(90deg,#00BFFF,#0080FF 55%,#FF8C00);animation:spLoad ' + (HOLD - 500) + 'ms .7s cubic-bezier(.4,0,.2,1) forwards}'
    + '#sda-splash .sp-by{position:absolute;bottom:calc(34px + env(safe-area-inset-bottom));font-family:Inter,system-ui,sans-serif;font-size:.8rem;color:#94a3b8;letter-spacing:.3px;animation:spFade .7s .9s ease-out both}'
    + '#sda-splash .sp-by b{color:#fff;font-weight:700}'
    + '#sda-splash .sp-by b span{color:#00BFFF}'
    + '@keyframes spPop{0%{transform:scale(.3) rotate(-12deg);opacity:0}100%{transform:none;opacity:1}}'
    + '@keyframes spRing{0%{transform:scale(.85);opacity:.9}100%{transform:scale(1.35);opacity:0}}'
    + '@keyframes spGlow{0%,100%{transform:scale(.9);opacity:.7}50%{transform:scale(1.08);opacity:1}}'
    + '@keyframes spUp{0%{transform:translateY(14px);opacity:0}100%{transform:none;opacity:1}}'
    + '@keyframes spFade{0%{opacity:0}100%{opacity:1}}'
    + '@keyframes spLoad{0%{width:0}100%{width:100%}}'
    + '@media (prefers-reduced-motion:reduce){#sda-splash *{animation-duration:.01ms!important;animation-delay:0s!important;animation-iteration-count:1!important}#sda-splash .sp-bar i{width:100%}}';

  var st = document.createElement('style');
  st.textContent = css;
  (document.head || root).appendChild(st);

  var d = document.createElement('div');
  d.id = 'sda-splash';
  d.setAttribute('role', 'presentation');
  d.innerHTML = '<div class="sp-glow"></div>'
    + '<div class="sp-tile"><span class="sp-ring"></span><img alt="" src="' + logo + '"></div>'
    + '<div class="sp-name">SKOPE <b>DIGITAL</b></div>'
    + '<div class="sp-sub">ACADEMY</div>'
    + '<div class="sp-bar"><i></i></div>'
    + '<div class="sp-by">Powered by <b>Skope <span>Designs</span></b></div>';
  root.appendChild(d);

  var t0 = Date.now();
  function done() {
    var wait = Math.max(0, HOLD - (Date.now() - t0));
    setTimeout(function () {
      root.classList.remove('sda-splash');
      d.classList.add('out');
      setTimeout(function () { d.remove(); st.remove(); }, 480);
    }, wait);
  }
  if (document.readyState === 'complete') done(); else addEventListener('load', done);
  setTimeout(done, 4000);   // never hold the app longer than this, even if a page loads slowly
})();
