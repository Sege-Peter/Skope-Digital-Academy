<?php
$pageTitle = 'My Achievements';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/progress.php';

$uid = (int)$student['id'];

// Leaderboard visibility preference
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leaderboard_visibility'])) {
    if (verifyCsrf()) {
        $pdo->prepare("UPDATE users SET show_on_leaderboard = ? WHERE id = ?")->execute([!empty($_POST['show']) ? 1 : 0, $uid]);
    }
    header('Location: achievements?saved=1#privacy');
    exit;
}

check_badges($pdo, $uid);   // catch up on anything earned before the engine existed
$u = $pdo->prepare("SELECT xp, streak_days, best_streak, last_streak_date, show_on_leaderboard FROM users WHERE id = ?");
$u->execute([$uid]);
$me = $u->fetch();
$lv = xp_level((int)$me['xp']);
$stats = user_stats($pdo, $uid);
$streakAlive = in_array($me['last_streak_date'], [date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))], true);
$streak = $streakAlive ? (int)$me['streak_days'] : 0;

$badges = $pdo->prepare("SELECT b.*, sb.awarded_at FROM badges b LEFT JOIN student_badges sb ON sb.badge_id = b.id AND sb.student_id = ?
                         WHERE b.code IS NOT NULL OR sb.id IS NOT NULL ORDER BY b.kind, b.sort_order, b.id");
$badges->execute([$uid]);
$badges = $badges->fetchAll();
$byKind = ['skill' => [], 'path' => [], 'achievement' => [], 'manual' => []];
foreach ($badges as $b) {
    if ($b['kind'] === 'skill' && !$b['awarded_at']) {
        // Only show locked skill badges for courses this learner is taking.
        $e = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? AND status IN ('active','completed')");
        $e->execute([$uid, $b['course_id']]);
        if (!$e->fetchColumn()) continue;
    }
    $byKind[$b['kind']][] = $b;
}
$earnedCount = count(array_filter($badges, fn($b) => $b['awarded_at']));

$certs = $pdo->prepare("SELECT c.verification_code, c.issued_at, c.certificate_type, c.score, c.status, c.fee, co.title AS course_title, p.title AS path_title
                        FROM certificates c LEFT JOIN courses co ON co.id = c.course_id LEFT JOIN learning_paths p ON p.id = c.path_id
                        WHERE c.student_id = ? AND c.status IN ('approved','pending') ORDER BY c.status = 'pending' DESC, c.issued_at DESC");
$certs->execute([$uid]);
$certs = $certs->fetchAll();

$courses = $pdo->prepare("SELECT c.id, c.title, c.slug, e.progress_percent, e.status FROM enrollments e JOIN courses c ON c.id = e.course_id
                          WHERE e.student_id = ? AND e.status IN ('active','completed') ORDER BY e.status = 'completed', e.progress_percent DESC");
$courses->execute([$uid]);
$courses = $courses->fetchAll();
foreach ($courses as &$c) {
    $r = user_rank_in_course($pdo, (int)$c['id'], $uid);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status IN ('active','completed')");
    $cnt->execute([$c['id']]);
    $c['rank'] = $r['rank'] ?? null;
    $c['xp'] = (int)($r['course_xp'] ?? 0);
    $c['learners'] = (int)$cnt->fetchColumn();
}
unset($c);
$paths = learning_paths($pdo, $uid);

$events = $pdo->prepare("SELECT le.*, c.title AS course_title, l.title AS lesson_title FROM learning_events le
                         LEFT JOIN courses c ON c.id = le.course_id LEFT JOIN lessons l ON l.id = le.lesson_id
                         WHERE le.user_id = ? AND le.xp > 0 ORDER BY le.created_at DESC, le.id DESC LIMIT 15");
$events->execute([$uid]);
$events = $events->fetchAll();
$eventLabels = [
    'lesson_view' => ['fa-eye', 'Started'], 'lesson_complete' => ['fa-circle-check', 'Completed'], 'exercise_pass' => ['fa-keyboard', 'Solved an exercise in'],
    'quiz_pass' => ['fa-clipboard-check', 'Passed the quiz in'], 'quiz_perfect' => ['fa-bullseye', 'Perfect score in'], 'course_complete' => ['fa-award', 'Completed the course'],
    'path_complete' => ['fa-route', 'Completed a learning path'], 'daily_streak' => ['fa-fire', 'Daily learning streak'], 'badge' => ['fa-medal', 'Badge bonus'], 'review' => ['fa-comment-dots', 'Reviewed'],
];
$hours = $stats['seconds'] / 3600;

function badge_progress(array $b, array $stats): array {
    $have = (int)($stats[$b['criteria_type']] ?? 0);
    $need = max(1, (int)$b['criteria_value']);
    return [min($have, $need), $need, (int)floor(min(1, $have / $need) * 100)];
}
?>
<?php require_once 'includes/sidebar.php'; ?>
<style>
  .ach { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --blue: #0080ff; --gold: #f59e0b; font-family: 'Inter', sans-serif; }
  .ach h2 { font: 800 1.2rem 'Poppins', 'Inter', sans-serif; color: var(--ink); margin: 34px 0 14px; display: flex; align-items: center; gap: 10px; }
  .ach h2 small { font: 500 .85rem 'Inter', sans-serif; color: var(--muted); }
  .ach-hero { display: grid; grid-template-columns: auto 1fr; gap: 24px; align-items: center; padding: 26px; border-radius: 22px; color: #e2e8f0;
              background: radial-gradient(circle at 100% 0, rgba(0,191,255,.3), transparent 45%), radial-gradient(circle at 0 120%, rgba(255,140,0,.25), transparent 45%), #0b1324; }
  .ach-ring { --p: 0; width: 120px; height: 120px; border-radius: 50%; display: grid; place-items: center;
              background: conic-gradient(#fbbf24 calc(var(--p) * 1%), rgba(255,255,255,.1) 0); }
  .ach-ring div { width: 100px; height: 100px; border-radius: 50%; background: #0b1324; display: grid; place-items: center; text-align: center; }
  .ach-ring b { display: block; font: 800 2rem 'Poppins', sans-serif; color: #fff; line-height: 1; }
  .ach-ring small { font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; color: #94a3b8; }
  .ach-hero h1 { margin: 0; font: 800 1.7rem 'Poppins', sans-serif; color: #fff; }
  .ach-hero .title { color: #fbbf24; font-weight: 700; }
  .ach-hero .next { margin: 6px 0 16px; color: #94a3b8; font-size: .9rem; }
  .ach-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
  .ach-stat { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 12px 14px; }
  .ach-stat b { display: block; font: 800 1.35rem 'Poppins', sans-serif; color: #fff; }
  .ach-stat span { font-size: .78rem; color: #94a3b8; }
  .ach-stat i { color: #fbbf24; margin-right: 4px; }
  .ach-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(230px, 100%), 1fr)); gap: 14px; }
  .ach-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 16px; }
  .bdg { display: flex; gap: 14px; align-items: center; }
  .bdg-icon { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; flex: 0 0 56px; font-size: 1.35rem; color: #fff;
              background: radial-gradient(circle at 30% 25%, color-mix(in srgb, var(--bc) 55%, #fff), var(--bc)); box-shadow: 0 0 0 5px color-mix(in srgb, var(--bc) 15%, transparent); }
  .bdg.locked .bdg-icon { background: #e2e8f0; color: #94a3b8; box-shadow: none; }
  .bdg strong { display: block; color: var(--ink); font-size: .95rem; }
  .bdg span { display: block; color: var(--muted); font-size: .8rem; line-height: 1.4; }
  .bdg .when { color: #0f9d6b; font-weight: 600; margin-top: 3px; }
  .bdg .prog { height: 5px; border-radius: 5px; background: var(--line); margin-top: 7px; overflow: hidden; }
  .bdg .prog i { display: block; height: 100%; background: var(--blue); }
  .skill .bdg-icon { border-radius: 16px; }
  .cert-card { display: flex; flex-direction: column; gap: 6px; border-left: 4px solid #c9a227; }
  .cert-card strong { color: var(--ink); }
  .cert-card small { color: var(--muted); }
  .cert-card .acts { display: flex; gap: 8px; margin-top: 6px; }
  .btn-s { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 9px; font-size: .8rem; font-weight: 700; text-decoration: none; border: 1px solid var(--line); color: var(--ink); background: #fff; }
  .btn-s.primary { background: var(--blue); border-color: var(--blue); color: #fff; }
  .crs { display: grid; grid-template-columns: 1fr auto; gap: 6px 12px; align-items: center; }
  .crs strong { color: var(--ink); }
  .crs .rank { font: 800 1.3rem 'Poppins', sans-serif; color: var(--gold); text-align: right; }
  .crs .rank small { display: block; font: 600 .7rem 'Inter', sans-serif; color: var(--muted); }
  .bar { height: 7px; border-radius: 7px; background: var(--line); overflow: hidden; grid-column: 1 / -1; }
  .bar i { display: block; height: 100%; background: linear-gradient(90deg, #00bfff, var(--blue)); }
  .crs-foot { grid-column: 1 / -1; display: flex; justify-content: space-between; font-size: .8rem; color: var(--muted); }
  .crs-foot a { font-weight: 700; color: var(--blue); text-decoration: none; }
  .path-steps { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
  .path-steps span { font-size: .76rem; font-weight: 700; padding: 4px 10px; border-radius: 99px; background: #f1f5f9; color: #475569; }
  .path-steps span.done { background: #e7f7f0; color: #0f7a54; }
  .feed { list-style: none; margin: 0; padding: 0; background: #fff; border: 1px solid var(--line); border-radius: 16px; }
  .feed li { display: flex; align-items: center; gap: 12px; padding: 11px 16px; border-top: 1px solid #f1f5f9; font-size: .88rem; color: #334155; }
  .feed li:first-child { border-top: 0; }
  .feed i { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: #eff6ff; color: var(--blue); flex: 0 0 30px; }
  .feed .xp { margin-left: auto; font-weight: 800; color: #b45309; white-space: nowrap; }
  .feed time { color: #94a3b8; font-size: .76rem; white-space: nowrap; }
  .privacy { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
  .empty { color: var(--muted); background: #fff; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 18px; }
  @media (max-width: 640px) { .ach-hero { grid-template-columns: 1fr; text-align: center; } .ach-ring { margin: 0 auto; } }
</style>
<main class="main-content ach" style="padding-bottom: 80px;">
  <section class="ach-hero">
    <div class="ach-ring" style="--p: <?= (int)$lv['pct'] ?>"><div><span><b><?= (int)$lv['level'] ?></b><small>Level</small></span></div></div>
    <div>
      <h1><?= e($student['name']) ?></h1>
      <div class="title"><?= e($lv['title']) ?> · <?= number_format($lv['xp']) ?> XP</div>
      <div class="next"><?= number_format(max(0, $lv['to'] - $lv['xp'])) ?> XP to level <?= (int)$lv['level'] + 1 ?></div>
      <div class="ach-stats">
        <div class="ach-stat"><b><i class="fas fa-fire"></i><?= $streak ?></b><span>Day streak · best <?= max((int)$me['best_streak'], $streak) ?></span></div>
        <div class="ach-stat"><b><?= (int)$stats['lessons'] ?></b><span>Lessons completed</span></div>
        <div class="ach-stat"><b><?= $hours >= 1 ? number_format($hours, 1) . 'h' : (int)round($stats['seconds'] / 60) . 'm' ?></b><span>Time learning</span></div>
        <div class="ach-stat"><b><?= $earnedCount ?></b><span>Badges earned</span></div>
        <div class="ach-stat"><b><?= count($certs) ?></b><span>Certificates</span></div>
      </div>
    </div>
  </section>

  <h2><i class="fas fa-ranking-star" style="color:#f59e0b"></i> My courses &amp; ranks</h2>
  <?php if (!$courses): ?>
    <div class="empty">You're not taking any courses yet. <a href="../learn">Start a free tutorial</a> – progress, XP and badges are tracked automatically.</div>
  <?php else: ?>
  <div class="ach-grid">
    <?php foreach ($courses as $c): ?>
      <div class="ach-card crs">
        <strong><?= e($c['title']) ?></strong>
        <div class="rank"><?= $c['rank'] ? '#' . (int)$c['rank'] : '–' ?><small>of <?= (int)$c['learners'] ?></small></div>
        <div class="bar"><i style="width:<?= (int)round($c['progress_percent']) ?>%"></i></div>
        <div class="crs-foot"><span><?= (int)round($c['progress_percent']) ?>% · <?= number_format($c['xp']) ?> XP<?= $c['status'] === 'completed' ? ' · <i class="fas fa-check" style="color:#0f9d6b"></i> done' : '' ?></span>
          <?php if ($c['slug']): ?><a href="../learn/<?= e($c['slug']) ?>/leaderboard">Leaderboard</a><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2><i class="fas fa-certificate" style="color:#c9a227"></i> Certificates <small>issued automatically when you finish</small></h2>
  <?php if (!$certs): ?>
    <div class="empty">Complete every lesson of a course (and pass its final quiz) to receive a certificate you can download and share.</div>
  <?php else: ?>
  <div class="ach-grid">
    <?php foreach ($certs as $c): $t = $c['certificate_type'] === 'path' ? $c['path_title'] : $c['course_title']; ?>
      <div class="ach-card cert-card">
        <small><?= $c['certificate_type'] === 'path' ? 'Learning path' : 'Course' ?> · <?= date('j M Y', strtotime($c['issued_at'])) ?></small>
        <strong><?= e($t) ?></strong>
        <small><?= $c['status'] === 'pending' ? '<i class="fas fa-lock"></i> Ready to unlock' : 'Code ' . e($c['verification_code']) ?><?= $c['score'] !== null ? ' · Score ' . (int)$c['score'] . '%' : '' ?></small>
        <?php if ($c['status'] === 'pending'): ?>
        <div class="acts"><a class="btn-s primary" href="../certificate-pay?code=<?= e(rawurlencode($c['verification_code'])) ?>" style="background:#f59e0b;border-color:#f59e0b"><i class="fas fa-lock-open"></i> Unlock · <?= e(money((float)$c['fee'])) ?></a></div>
        <?php else: ?>
        <div class="acts"><a class="btn-s primary" href="../certificate?code=<?= e(rawurlencode($c['verification_code'])) ?>" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a>
          <button type="button" class="btn-s" data-copy="<?= e(rawurlencode($c['verification_code'])) ?>"><i class="fas fa-link"></i> Copy link</button></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2><i class="fas fa-route" style="color:#0080ff"></i> Learning paths</h2>
  <div class="ach-grid">
    <?php foreach ($paths as $p): ?>
      <div class="ach-card">
        <strong style="color:var(--ink)"><i class="fas <?= e($p['icon']) ?>" style="color:<?= e($p['color']) ?>"></i> <?= e($p['title']) ?></strong>
        <div class="path-steps"><?php foreach ($p['courses'] as $c): ?><span class="<?= !empty($c['completed']) ? 'done' : '' ?>"><?= !empty($c['completed']) ? '✓ ' : '' ?><?= e($c['tutorial_short'] ?: $c['title']) ?></span><?php endforeach; ?></div>
        <div class="bar"><i style="width:<?= (int)$p['pct'] ?>%"></i></div>
        <div class="crs-foot" style="margin-top:6px"><span><?= (int)$p['done'] ?>/<?= count($p['courses']) ?> courses</span><span><i class="fas fa-award"></i> Badge + certificate</span></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php foreach (['skill' => ['Skill badges', 'fa-certificate', 'earned by completing a course'], 'path' => ['Path badges', 'fa-route', 'earned by completing a learning path'], 'achievement' => ['Achievements', 'fa-medal', 'milestones along the way'], 'manual' => ['Special awards', 'fa-gift', 'from your tutors and the academy']] as $kind => [$label, $icon, $hint]):
      if (!$byKind[$kind]) continue; ?>
    <h2><i class="fas <?= $icon ?>" style="color:#8b5cf6"></i> <?= $label ?> <small><?= $hint ?></small></h2>
    <div class="ach-grid">
      <?php foreach ($byKind[$kind] as $b): $earned = (bool)$b['awarded_at']; ?>
        <div class="ach-card <?= $kind === 'skill' ? 'skill' : '' ?>">
          <div class="bdg <?= $earned ? '' : 'locked' ?>" style="--bc: <?= e($b['color'] ?: '#0080ff') ?>">
            <div class="bdg-icon"><i class="fas <?= e(str_starts_with((string)$b['icon'], 'fa-') ? $b['icon'] : 'fa-award') ?>"></i></div>
            <div style="min-width:0">
              <strong><?= e($b['name']) ?></strong>
              <span><?= e($b['description']) ?></span>
              <?php if ($earned): ?>
                <span class="when"><i class="fas fa-check"></i> Earned <?= date('j M Y', strtotime($b['awarded_at'])) ?></span>
              <?php elseif ($kind === 'achievement' && isset($stats[$b['criteria_type']])): [$h, $n, $pc] = badge_progress($b, $stats); ?>
                <div class="prog"><i style="width:<?= $pc ?>%"></i></div><span><?= number_format($h) ?> / <?= number_format($n) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <h2><i class="fas fa-clock-rotate-left" style="color:#0f9d6b"></i> Recent activity</h2>
  <?php if (!$events): ?>
    <div class="empty">Your learning activity will appear here.</div>
  <?php else: ?>
  <ul class="feed">
    <?php foreach ($events as $ev): [$ic, $lbl] = $eventLabels[$ev['event_type']] ?? ['fa-bolt', 'Activity']; ?>
      <li><i class="fas <?= $ic ?>"></i><span><?= e($lbl) ?> <?= $ev['lesson_title'] ? '<b>' . e($ev['lesson_title']) . '</b>' : ($ev['course_title'] ? '<b>' . e($ev['course_title']) . '</b>' : '') ?></span>
        <span class="xp">+<?= (int)$ev['xp'] ?> XP</span><time datetime="<?= e($ev['created_at']) ?>"><?= date('j M, H:i', strtotime($ev['created_at'])) ?></time></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <h2 id="privacy"><i class="fas fa-user-shield" style="color:#64748b"></i> Leaderboard privacy</h2>
  <form method="post" class="ach-card privacy">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="leaderboard_visibility" value="1">
    <label style="display:flex;gap:10px;align-items:center;cursor:pointer"><input type="checkbox" name="show" value="1" <?= $me['show_on_leaderboard'] ? 'checked' : '' ?> style="width:18px;height:18px">
      <span>Show me on course leaderboards (as first name and last initial)</span></label>
    <button class="btn-s primary" type="submit">Save</button>
    <?php if (isset($_GET['saved'])): ?><span style="color:#0f9d6b;font-weight:600"><i class="fas fa-check"></i> Saved</span><?php endif; ?>
  </form>
</main>
<script src="../assets/js/main.js"></script>
<script>
document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', () => {
  const url = new URL('../certificate?code=' + btn.dataset.copy, location.href).href;
  (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(
    () => { btn.innerHTML = '<i class="fas fa-check"></i> Copied'; },
    () => { window.open(url, '_blank', 'noopener'); });
}));
</script>
</body>
</html>
