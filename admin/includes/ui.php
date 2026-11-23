<?php
/**
 * Admin UI helpers – shared by every admin page (pairs with assets/css/admin-ui.css).
 *
 *   ax_post_guard();                       // at the top of POST handling: rejects bad CSRF tokens
 *   ax_flash('ok', 'Saved.'); ax_back();    // flash + redirect (Post/Redirect/Get)
 *   ax_header('Learning', 'Paths', 'Sub', '<a class="ax-btn" ...>');
 *   ax_alerts();                            // prints the flash message
 *   echo ax_csrf();                         // hidden token inside every POST form
 */
require_once __DIR__ . '/../../includes/auth.php';

require_once __DIR__ . '/../../includes/learning.php';   // e(), setting(), money(), learn_url() …
require_once __DIR__ . '/../../includes/progress.php';   // learning engine (XP, badges, certificates)

function ax_csrf(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Stop a POST that has no valid CSRF token. */
function ax_post_guard(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        ax_flash('err', 'Your session expired or the form was out of date. Please try again.');
        ax_back();
    }
}

function ax_flash(string $type, string $msg): void { $_SESSION['ax_flash'] = [$type, $msg]; }

/** Redirect to the same page (keeps the query string unless $to is given). */
function ax_back(?string $to = null): never {
    $to ??= strtok(basename($_SERVER['REQUEST_URI']), '#');
    header('Location: ' . ($to === '' ? './' : $to));
    exit;
}

function ax_alerts(): void {
    if (empty($_SESSION['ax_flash'])) return;
    [$t, $m] = $_SESSION['ax_flash'];
    unset($_SESSION['ax_flash']);
    $icon = $t === 'ok' ? 'circle-check' : ($t === 'err' ? 'circle-exclamation' : 'circle-info');
    echo '<div class="ax-alert ' . e($t) . '" role="status"><i class="fas fa-' . $icon . '"></i><div>' . e($m) . '</div></div>';
}

/** Sticky page header with the mobile menu toggle. $actions is trusted HTML. */
function ax_header(string $title, string $accent = '', string $sub = '', string $actions = ''): void {
    echo '<header class="admin-header"><div class="ax-head-l">'
       . '<button class="nav-toggle" type="button" onclick="toggleSidebar()" aria-label="Open menu"><i class="fas fa-bars"></i></button>'
       . '<div><h1 class="ax-h1">' . e($title) . ($accent !== '' ? ' <span>' . e($accent) . '</span>' : '') . '</h1>'
       . ($sub !== '' ? '<div class="ax-sub">' . e($sub) . '</div>' : '') . '</div></div>'
       . '<div class="ax-head-r">' . $actions . ax_topbar_tools() . '</div>'
       . '</header>';
}

/** Date pill, light/dark switch and the admin's avatar (right side of every top bar). */
function ax_topbar_tools(): string {
    global $user;
    $name = $user['name'] ?? 'Admin';
    $av = !empty($user['avatar'])
        ? '<img src="../uploads/avatars/' . e($user['avatar']) . '" alt="" onerror="this.remove()">'
        : ax_initial($name);
    return '<div class="ax-tools">'
         . '<span class="ax-date"><i class="far fa-calendar"></i>' . e(date('D, j M Y')) . '</span>'
         . ((($_SESSION['role'] ?? '') === 'admin') ? '<button type="button" class="ax-btn ghost sm ax-switch-btn" data-open="dlgSwitchPortal" title="Open the tutor or student portal as one of your users"><i class="fas fa-repeat"></i><span> Switch portal</span></button>' : '')
         . '<button type="button" class="ax-theme" id="axTheme" title="Switch light / dark" aria-label="Switch light or dark theme"><span class="k"><i class="fas fa-moon"></i></span></button>'
         . '<a class="ax-topav" href="profile" title="' . e($name) . ' – my profile">' . $av . '</a>'
         . '</div>' . ((($_SESSION['role'] ?? '') === 'admin') ? ax_switch_dialog() : '');
}

/** "Switch portal" dialog: pick a tutor or learner and open their portal (support / checking what they see). */
function ax_switch_dialog(): string {
    global $pdo;
    static $done = false;
    if ($done || !$pdo) return '';
    $done = true;
    try {
        $rows = $pdo->query("SELECT id, name, email, role FROM users WHERE role IN ('tutor','student') AND status = 'active' ORDER BY role = 'tutor' DESC, name LIMIT 600")->fetchAll();
    } catch (Throwable $e) { return ''; }
    $back = e(strtok(basename($_SERVER['REQUEST_URI'] ?? 'index.php'), '#'));
    $list = '';
    foreach ($rows as $r) {
        $tutor = $r['role'] === 'tutor';
        $list .= '<li data-role="' . e($r['role']) . '" data-q="' . e(mb_strtolower($r['name'] . ' ' . $r['email'])) . '">'
               . '<form method="post" action="users">' . ax_csrf() . '<input type="hidden" name="action" value="impersonate"><input type="hidden" name="uid" value="' . (int)$r['id'] . '"><input type="hidden" name="back" value="' . $back . '">'
               . '<button type="submit"><span class="ax-sw-av">' . ax_initial($r['name']) . '</span><span class="ax-sw-t"><b>' . e($r['name']) . '</b><small>' . e($r['email']) . '</small></span>'
               . '<span class="ax-pill ' . ($tutor ? 'violet' : 'info') . '">' . ($tutor ? 'Tutor portal' : 'Student portal') . '</span></button></form></li>';
    }
    $js = <<<'JS'
<script>(function(){var q=document.getElementById("axSwQ"),l=document.getElementById("axSwList"),em=document.getElementById("axSwEmpty"),role="all";if(!l)return;
function f(){var t=(q.value||"").toLowerCase().trim(),n=0;l.querySelectorAll("li").forEach(function(li){var ok=(role==="all"||li.dataset.role===role)&&(!t||li.dataset.q.indexOf(t)>-1);li.hidden=!ok;if(ok)n++;});em.hidden=n>0;}
q.addEventListener("input",f);
document.querySelectorAll("[data-sw]").forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();role=a.dataset.sw;document.querySelectorAll("[data-sw]").forEach(function(x){x.classList.toggle("active",x===a);});f();});});
document.getElementById("dlgSwitchPortal").addEventListener("close",function(){q.value="";role="all";f();});
})();</script>
JS;
    $css = <<<'CSS'
<style>
.ax-switch{width:min(560px,calc(100vw - 32px))}
.ax-sw-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px}.ax-sw-bar .ax-search{flex:1;min-width:180px}
.ax-sw-list{list-style:none;margin:0;padding:0;max-height:min(52vh,460px);overflow-y:auto;display:grid;gap:6px}
.ax-sw-list li[hidden]{display:none}.ax-sw-list form{margin:0}
.ax-sw-list button{width:100%;display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--border);background:var(--surface-2);border-radius:12px;cursor:pointer;color:var(--text-main);text-align:left;font:inherit}
.ax-sw-list button:hover,.ax-sw-list button:focus-visible{border-color:var(--primary);outline:none}
.ax-sw-av{flex:none;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--primary-glow,rgba(0,191,255,.15));color:var(--primary);font-weight:800}
.ax-sw-t{flex:1;min-width:0}.ax-sw-t b{display:block;font-size:.88rem}.ax-sw-t small{display:block;color:var(--text-dim);font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ax-sw-empty{color:var(--text-dim);font-size:.85rem;text-align:center;margin:14px 0 4px}
@media (max-width:700px){.ax-switch-btn span{display:none}}
</style>
CSS;
    return '<dialog class="ax-modal ax-switch" id="dlgSwitchPortal" aria-labelledby="dlgSwitchT">'
        . '<div class="ax-panel-h"><div><h2 id="dlgSwitchT"><i class="fas fa-repeat"></i> Switch portal</h2><p>Open the tutor or student portal signed in as that person. A red banner takes you back to admin.</p></div>'
        . '<button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>'
        . '<div class="ax-panel-b">'
        . '<div class="ax-sw-bar"><nav class="ax-tabs"><a href="#" class="active" data-sw="all">All</a><a href="#" data-sw="tutor">Tutors</a><a href="#" data-sw="student">Students</a></nav>'
        . '<label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search name or email…" id="axSwQ" aria-label="Search people"></label></div>'
        . ($list ? '<ul class="ax-sw-list" id="axSwList">' . $list . '</ul><p class="ax-sw-empty" id="axSwEmpty" hidden>No one matches.</p>'
                 : '<div class="ax-empty"><i class="fas fa-users"></i><b>No active tutors or learners yet</b></div>')
        . '</div></dialog>' . $css . $js;
}

function ax_money($n): string { return 'KES ' . number_format((float)$n, 0); }

function ax_initial(?string $name): string { return e(strtoupper(mb_substr(trim((string)$name) ?: '?', 0, 1))); }

function ax_avatar(?string $name, ?string $avatar = null): string {
    if ($avatar) return '<span class="ax-avatar"><img src="../uploads/avatars/' . e($avatar) . '" alt="" onerror="this.remove()"></span>';
    return '<span class="ax-avatar">' . ax_initial($name) . '</span>';
}

function ax_ago(?string $dt): string {
    if (!$dt) return '—';
    $s = time() - strtotime($dt);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 86400 * 7) return floor($s / 86400) . ' d ago';
    return date('M j, Y', strtotime($dt));
}

function ax_date(?string $dt, string $fmt = 'M j, Y'): string { return $dt ? date($fmt, strtotime($dt)) : '—'; }

/** A stat tile. $color: '' (blue) | green | orange | red | violet | amber | slate */
function ax_stat(string $icon, $value, string $label, string $foot = '', string $color = ''): string {
    return '<div class="ax-stat ' . e($color) . '"><div class="ic"><i class="fas fa-' . e($icon) . '"></i></div><div>'
         . '<div class="v">' . e($value) . '</div><div class="l">' . e($label) . '</div>'
         . ($foot !== '' ? '<div class="f">' . e($foot) . '</div>' : '') . '</div></div>';
}

/** Safe COUNT/SUM helper: returns 0 if the table or column is missing. */
function ax_scalar(PDO $pdo, string $sql, array $p = []) {
    try { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchColumn() ?: 0; }
    catch (Throwable $e) { return 0; }
}

/**
 * Record an admin action in the activity log (admin_activity). Never throws.
 *   ax_log('certificate.waive', 'certificate', $id, 'Waived KES 500 fee for Jane');
 */
function ax_log(string $action, string $entity = '', ?int $entityId = null, string $summary = ''): void {
    global $pdo, $user;
    try {
        $pdo->prepare("INSERT INTO admin_activity (admin_id, action, entity, entity_id, summary, ip) VALUES (?,?,?,?,?,?)")
            ->execute([(int)($user['id'] ?? 0), $action, $entity, $entityId, mb_substr($summary, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* logging must never break an admin action */ }
}

/** Shared confirm-before-submit: add data-confirm="Text" to a <form> or <button>. */
function ax_footer_js(): void { ?>
<script>
(function(){
  var btn=document.getElementById('axTheme');
  function icon(){var i=btn&&btn.querySelector('i');if(i)i.className='fas fa-'+(document.documentElement.classList.contains('ax-light')?'sun':'moon');}
  icon();
  if(btn)btn.addEventListener('click',function(){var l=document.documentElement.classList.toggle('ax-light');try{localStorage.setItem('sda_admin_theme',l?'light':'dark');}catch(e){}icon();});
})();
(function(){
  var dlg;
  function ask(msg, danger){
    if(!dlg){dlg=document.createElement('dialog');dlg.className='ax-modal';dlg.style.width='min(420px,calc(100vw - 32px))';
      dlg.innerHTML='<div class="ax-panel-b" style="padding:26px"><div style="display:flex;gap:14px;align-items:flex-start"><span class="ax-icon-badge" data-ic style="background:var(--secondary-glow);color:var(--secondary)"><i class="fas fa-circle-question"></i></span><div><h3 style="font:800 1rem Inter,sans-serif;color:var(--text-main);margin-bottom:6px">Please confirm</h3><p data-msg style="color:var(--text-dim);font-size:.88rem;line-height:1.55"></p></div></div><div class="ax-form-actions" style="margin-top:22px"><button type="button" class="ax-btn ghost" data-no>Cancel</button><button type="button" class="ax-btn" data-yes>Confirm</button></div></div>';
      document.body.appendChild(dlg);}
    dlg.querySelector('[data-msg]').textContent=msg;
    var yes=dlg.querySelector('[data-yes]');yes.className='ax-btn'+(danger?' warn':'');yes.textContent=danger?'Yes, continue':'Confirm';
    return new Promise(function(res){
      function done(v){dlg.close();yes.onclick=null;dlg.querySelector('[data-no]').onclick=null;res(v);}
      yes.onclick=function(){done(true)};dlg.querySelector('[data-no]').onclick=function(){done(false)};
      dlg.oncancel=function(ev){ev.preventDefault();done(false)};
      dlg.showModal();yes.focus();
    });
  }
  document.addEventListener('submit',function(e){
    var f=e.target,b=e.submitter,m=(b&&b.dataset.confirm)||f.dataset.confirm;
    if(!m||f.dataset.confirmed)return;
    e.preventDefault();
    ask(m,!!((b&&b.dataset.danger!==undefined)||f.dataset.danger!==undefined)).then(function(ok){
      if(!ok)return;f.dataset.confirmed='1';
      if(b&&b.name){var h=document.createElement('input');h.type='hidden';h.name=b.name;h.value=b.value;f.appendChild(h);}
      f.submit();
    });
  },true);
})();
document.querySelectorAll('[data-filter-table]').forEach(function(inp){var t=document.getElementById(inp.dataset.filterTable);if(!t)return;inp.addEventListener('input',function(){var q=inp.value.toLowerCase();t.querySelectorAll('tbody tr').forEach(function(r){r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});});});
document.querySelectorAll('[data-open]').forEach(function(b){b.addEventListener('click',function(){var d=document.getElementById(b.dataset.open);if(d&&d.showModal)d.showModal();});});
document.querySelectorAll('dialog.ax-modal [data-close]').forEach(function(b){b.addEventListener('click',function(){b.closest('dialog').close();});});
</script>
<?php }
