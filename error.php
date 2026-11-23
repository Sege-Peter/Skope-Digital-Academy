<?php
/**
 * Error pages (404, 403, 401, 429, 500, 503). Self-contained: no database, inline styles, so it still
 * renders when the rest of the site is down. Used by Apache (ErrorDocument in .htaccess), by PHP fatal
 * errors (shutdown handler in includes/db.php) and directly as error.php?code=404.
 */
$code = (int)($sda_error_code ?? ($_SERVER['REDIRECT_STATUS'] ?? 0));
if (!in_array($code, [400, 401, 403, 404, 429, 500, 503], true)) $code = (int)($_GET['code'] ?? 404);
if (!in_array($code, [400, 401, 403, 404, 429, 500, 503], true)) $code = 404;
if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if ($code === 503) header('Retry-After: 300');
}

// Where the site lives (works locally in a sub-folder and live at the root)
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$appRoot = realpath(__DIR__) ?: '';
$base = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? str_replace('\\', '/', substr($appRoot, strlen($docRoot))) : '';
$base = implode('/', array_map('rawurlencode', explode('/', rtrim($base, '/')))) . '/';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$path = (string)($_SERVER['REDIRECT_URL'] ?? parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$method = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST', 'HEAD'], true) ? $_SERVER['REQUEST_METHOD'] : 'GET';

$pages = [
    400 => ['Bad Request', 'That request was not quite right', 'The link may be broken or incomplete. Try going back, or start again from the home page.', 'compass'],
    404 => ['Not Found', 'This page took a different path', 'The address may be mistyped, or the page has moved. Let\'s get you back to learning.', 'compass'],
    403 => ['Forbidden', 'This area is private', 'You don\'t have access to this page. If you think you should, sign in with the right account.', 'lock'],
    401 => ['Unauthorized', 'Please sign in first', 'This page is for signed-in learners, tutors and admins.', 'lock'],
    429 => ['Too Many Requests', 'Let\'s take a short breather', 'Too many requests came in at once. Wait a minute, then try again.', 'clock'],
    500 => ['Internal Server Error', 'Something broke on our side', 'It\'s not you, it\'s us. The team has been notified in the logs. Please try again in a moment.', 'plug'],
    503 => ['Service Unavailable', 'Back in a moment', 'We\'re doing a quick update to make the academy better. Please check back in a few minutes.', 'gear'],
];
[$status, $title, $lead, $art] = $pages[$code];
$retry = in_array($code, [429, 500, 503], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0b1220">
<title><?= $code ?> · <?= $h($title) ?> – Skope Digital Academy</title>
<link rel="icon" href="<?= $base ?>favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --cyan: #00BFFF; --blue: #0080FF; --orange: #FF8C00; }
  * { box-sizing: border-box; }
  html, body { margin: 0; min-height: 100%; }
  body { min-height: 100vh; display: flex; flex-direction: column; font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #334155;
         background: radial-gradient(800px 480px at 15% -10%, rgba(0,191,255,.14), transparent 60%), radial-gradient(700px 480px at 110% 110%, rgba(255,140,0,.12), transparent 60%), #f7f9fc; }
  body::before { content: ''; position: fixed; inset: 0; pointer-events: none; opacity: .5;
                 background-image: radial-gradient(rgba(15,23,42,.08) 1px, transparent 1px); background-size: 22px 22px; mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%); }
  header { position: relative; display: flex; justify-content: space-between; align-items: center; padding: 20px clamp(16px, 4vw, 40px); }
  .brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; font: 800 1rem Poppins, sans-serif; color: var(--ink); }
  .brand img { width: 36px; height: 36px; border-radius: 10px; background: #fff; box-shadow: 0 6px 14px -8px rgba(15,23,42,.4); }
  .brand em { font-style: normal; color: var(--orange); }
  header a.small { font-weight: 600; font-size: .9rem; color: var(--muted); text-decoration: none; }
  main { position: relative; flex: 1; display: grid; place-items: center; grid-template-columns: minmax(0, 1fr); padding: 10px 16px 40px; }
  .wrap { width: 100%; max-width: 560px; text-align: center; }
  .art { width: 200px; height: 200px; margin: 0 auto 6px; }
  .code { font: 900 clamp(4.5rem, 16vw, 7.5rem)/1 Poppins, sans-serif; letter-spacing: -4px; margin: 0;
          background: linear-gradient(120deg, var(--cyan), var(--blue) 50%, var(--orange)); -webkit-background-clip: text; background-clip: text; color: transparent; }
  h1 { font: 800 clamp(1.35rem, 4.5vw, 1.8rem)/1.25 Poppins, sans-serif; color: var(--ink); margin: 10px 0 10px; }
  .lead { margin: 0 auto 22px; max-width: 430px; line-height: 1.65; color: var(--muted); }
  .term { display: inline-flex; align-items: center; gap: 10px; max-width: 100%; margin: 0 0 26px; padding: 9px 14px; border-radius: 10px; background: #0b1220; color: #cbd5e1;
          font: 500 .8rem 'JetBrains Mono', ui-monospace, monospace; box-shadow: 0 10px 24px -14px rgba(11,18,32,.6); }
  .term .dots { display: inline-flex; gap: 4px; } .term .dots i { width: 7px; height: 7px; border-radius: 50%; background: #334155; display: block; }
  .term .dots i:nth-child(1) { background: #f87171; } .term .dots i:nth-child(2) { background: #fbbf24; } .term .dots i:nth-child(3) { background: #34d399; }
  .term .p { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left; }
  .term .dots, .term .cur { flex: none; }
  .wrap { min-width: 0; }
  .term .m { color: #7dd3fc; } .term .s { color: #fb923c; font-weight: 600; }
  .term .cur { width: 7px; height: 14px; background: #7dd3fc; display: inline-block; animation: blink 1s steps(1) infinite; }
  @keyframes blink { 50% { opacity: 0 } }
  form.search { display: flex; gap: 8px; max-width: 420px; margin: 0 auto 18px; }
  form.search input { flex: 1; min-width: 0; height: 48px; padding: 0 16px; border: 1.5px solid #cbd5e1; border-radius: 12px; font: 500 16px Inter, sans-serif; color: var(--ink); background: #fff; outline: none; }
  form.search input:focus { border-color: var(--blue); box-shadow: 0 0 0 4px rgba(0,128,255,.14); }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 48px; padding: 0 20px; border-radius: 12px; border: 0; cursor: pointer;
         font: 700 .95rem Inter, sans-serif; text-decoration: none; transition: transform .15s, box-shadow .15s; }
  .btn:hover { transform: translateY(-1px); }
  .btn.primary { background: linear-gradient(135deg, var(--cyan), var(--blue)); color: #fff; box-shadow: 0 12px 24px -12px rgba(0,128,255,.8); }
  .btn.ghost { background: #fff; color: var(--ink); border: 1.5px solid var(--line); }
  .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
  .links { display: flex; gap: 8px 18px; justify-content: center; flex-wrap: wrap; margin-top: 22px; font-size: .9rem; }
  .links a { color: var(--blue); text-decoration: none; font-weight: 600; }
  .links a:hover { text-decoration: underline; }
  footer { position: relative; text-align: center; padding: 18px 16px 24px; font-size: .8rem; color: #94a3b8; }
  footer a { color: var(--muted); text-decoration: none; font-weight: 600; }
  /* illustration motion */
  .spin { transform-origin: 100px 100px; animation: spin 14s linear infinite; }
  .needle { transform-origin: 100px 100px; animation: wobble 3.2s ease-in-out infinite; }
  .float { animation: float 3.6s ease-in-out infinite; }
  .shake { transform-origin: 100px 120px; animation: shake 2.8s ease-in-out infinite; }
  .gear { transform-origin: 84px 104px; animation: spin 6s linear infinite; }
  .gear2 { transform-origin: 134px 80px; animation: spin 4s linear infinite reverse; }
  .spark { animation: spark 1.4s ease-in-out infinite; }
  .hand { transform-origin: 100px 104px; animation: spin 2.4s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg) } }
  @keyframes wobble { 0%,100% { transform: rotate(-28deg) } 50% { transform: rotate(32deg) } }
  @keyframes float { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-6px) } }
  @keyframes shake { 0%,86%,100% { transform: rotate(0) } 90% { transform: rotate(-6deg) } 94% { transform: rotate(6deg) } }
  @keyframes spark { 0%,100% { opacity: .2 } 50% { opacity: 1 } }
  @media (prefers-reduced-motion: reduce) { * { animation: none !important; } }
  @media (max-width: 480px) { .art { width: 160px; height: 160px; } form.search { flex-direction: column; } .btn { width: 100%; } .actions { flex-direction: column; } }
</style>
</head>
<body>
<header>
  <a class="brand" href="<?= $base ?>"><img src="<?= $base ?>assets/pwa/icon-192.png" alt=""> SKOPE <em>DIGITAL</em></a>
  <a class="small" href="<?= $base ?>contact">Need help?</a>
</header>

<main>
  <div class="wrap">
    <svg class="art" viewBox="0 0 200 200" aria-hidden="true">
      <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#00BFFF"/><stop offset=".55" stop-color="#0080FF"/><stop offset="1" stop-color="#FF8C00"/></linearGradient></defs>
      <circle cx="100" cy="100" r="78" fill="#fff" stroke="#e2e8f0" stroke-width="2"/>
      <?php if ($art === 'compass'): ?>
        <g class="spin"><circle cx="100" cy="100" r="66" fill="none" stroke="url(#g)" stroke-width="3" stroke-dasharray="4 10" stroke-linecap="round"/></g>
        <text x="100" y="44" text-anchor="middle" font-family="Poppins" font-weight="800" font-size="13" fill="#94a3b8">N</text>
        <g class="needle"><path d="M100 52 L110 100 L100 148 L90 100 Z" fill="url(#g)"/><path d="M100 100 L110 100 L100 148 L90 100 Z" fill="#0f172a" opacity=".85"/></g>
        <circle cx="100" cy="100" r="7" fill="#fff" stroke="#0f172a" stroke-width="3"/>
      <?php elseif ($art === 'lock'): ?>
        <g class="float"><path d="M74 92 V76 a26 26 0 0 1 52 0 V92" fill="none" stroke="#0f172a" stroke-width="9" stroke-linecap="round"/>
        <rect x="62" y="90" width="76" height="62" rx="14" fill="url(#g)"/><circle cx="100" cy="116" r="8" fill="#fff"/><rect x="96" y="120" width="8" height="16" rx="4" fill="#fff"/></g>
      <?php elseif ($art === 'clock'): ?>
        <circle cx="100" cy="104" r="50" fill="none" stroke="url(#g)" stroke-width="8"/>
        <rect x="88" y="44" width="24" height="12" rx="4" fill="#0f172a"/>
        <g class="hand"><line x1="100" y1="104" x2="100" y2="72" stroke="#0f172a" stroke-width="6" stroke-linecap="round"/></g>
        <circle cx="100" cy="104" r="6" fill="#FF8C00"/>
      <?php elseif ($art === 'plug'): ?>
        <g class="shake">
          <path d="M30 120 C 55 120, 55 104, 78 104" fill="none" stroke="#0f172a" stroke-width="6" stroke-linecap="round"/>
          <rect x="76" y="90" width="26" height="30" rx="7" fill="url(#g)"/><rect x="102" y="96" width="10" height="4" rx="2" fill="#0f172a"/><rect x="102" y="110" width="10" height="4" rx="2" fill="#0f172a"/>
          <rect x="132" y="88" width="26" height="34" rx="7" fill="#0f172a"/><path d="M170 105 C 178 105, 178 80, 170 80" fill="none" stroke="#0f172a" stroke-width="6" stroke-linecap="round"/>
        </g>
        <g class="spark" stroke="#FF8C00" stroke-width="3.5" stroke-linecap="round"><line x1="120" y1="86" x2="124" y2="76"/><line x1="122" y1="104" x2="130" y2="104"/><line x1="120" y1="122" x2="124" y2="132"/></g>
      <?php else: ?>
        <g class="gear"><circle cx="84" cy="104" r="26" fill="none" stroke="url(#g)" stroke-width="12" stroke-dasharray="10 6"/><circle cx="84" cy="104" r="14" fill="#fff" stroke="#0f172a" stroke-width="5"/></g>
        <g class="gear2"><circle cx="134" cy="80" r="16" fill="none" stroke="#0f172a" stroke-width="9" stroke-dasharray="7 5"/><circle cx="134" cy="80" r="7" fill="#fff" stroke="#FF8C00" stroke-width="4"/></g>
      <?php endif; ?>
    </svg>

    <p class="code"><?= $code ?></p>
    <h1><?= $h($title) ?></h1>
    <p class="lead"><?= $h($lead) ?></p>
    <div class="term" aria-label="Request details"><span class="dots"><i></i><i></i><i></i></span><span class="p"><span class="m"><?= $h($method) ?></span> <?= $h(mb_substr($path, 0, 48)) ?> → <span class="s"><?= $code ?> <?= $h($status) ?></span></span><span class="cur"></span></div>

    <?php if ($code === 404): ?>
      <form class="search" action="<?= $base ?>learn" method="get" role="search">
        <input type="search" name="q" placeholder="Search tutorials, e.g. “flexbox”" aria-label="Search tutorials">
        <button class="btn primary" type="submit">Search</button>
      </form>
      <div class="actions"><a class="btn ghost" href="<?= $base ?>">Go home</a><a class="btn ghost" href="<?= $base ?>courses">Browse courses</a></div>
    <?php elseif ($code === 403 || $code === 401): ?>
      <div class="actions"><a class="btn primary" href="<?= $base ?>login">Sign in</a><a class="btn ghost" href="<?= $base ?>">Go home</a></div>
    <?php else: ?>
      <div class="actions"><?php if ($retry): ?><button class="btn primary" type="button" onclick="location.reload()">Try again</button><?php endif; ?><a class="btn ghost" href="<?= $base ?>">Go home</a></div>
    <?php endif; ?>

    <div class="links"><a href="<?= $base ?>learn">Free tutorials</a><a href="<?= $base ?>courses">Courses</a><a href="<?= $base ?>contact">Contact us</a></div>
  </div>
</main>

<footer>© <?= date('Y') ?> Skope Digital Academy · Kisumu, Kenya · Powered by <a href="https://skopedesigns.co.ke">Skope Designs</a></footer>
<?php if ($code === 503): ?><script>setTimeout(function () { location.reload(); }, 60000);</script><?php endif; ?>
</body>
</html>
