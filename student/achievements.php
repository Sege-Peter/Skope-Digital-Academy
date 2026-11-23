<?php
$pageTitle = 'Achievements';
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
$kindInfo = [
    'skill'       => ['Skill badges', 'Earned by finishing a course'],
    'path'        => ['Path badges', 'Earned by finishing every course in a learning path'],
    'achievement' => ['Milestones', 'Earned along the way as you keep learning'],
    'manual'      => ['Special awards', 'Given by your tutors and the academy'],
];
$totalShown = array_sum(array_map('count', $byKind));
$earnedShown = 0;
foreach ($byKind as $list) foreach ($list as $b) if ($b['awarded_at']) $earnedShown++;
$xpToNext = max(0, (int)$lv['to'] - (int)$lv['xp']);
$bestStreak = max((int)$me['best_streak'], $streak);
$timeLabel = $hours >= 1 ? number_format($hours, 1) . 'h' : (int)round($stats['seconds'] / 60) . 'm';

require_once 'includes/layout-top.php';
?>
<style>
  .pg-ach .ach-hero-grid { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 28px; align-items: center; }
  .pg-ach .ach-ring { --p: 0; width: 132px; height: 132px; border-radius: 50%; display: grid; place-items: center;
      background: conic-gradient(#FFC53D calc(var(--p) * 1%), rgba(255,255,255,.12) 0); }
  .pg-ach .ach-ring > div { width: 108px; height: 108px; border-radius: 50%; background: #111C33; display: grid; place-items: center; text-align: center; }
  .pg-ach .ach-ring b { display: block; font-size: 2.3rem; font-weight: 800; line-height: 1; color: #fff; }
  .pg-ach .ach-ring small { font-size: .72rem; color: rgba(255,255,255,.65); font-weight: 600; }
  .pg-ach .ach-hero h2 { margin: 10px 0 4px; font-size: clamp(1.3rem, 3vw, 1.7rem); font-weight: 800; }
  .pg-ach .ach-hero .sp-between { font-size: .78rem; color: rgba(255,255,255,.75); }
  .pg-ach .ach-badges { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 200px), 1fr)); }
  .pg-ach .bdg { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center;
      padding: 20px 16px 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); }
  .pg-ach .bdg.earned { border-color: color-mix(in srgb, var(--bc) 35%, var(--border));
      background: linear-gradient(180deg, color-mix(in srgb, var(--bc) 9%, #fff), #fff 65%); }
  .pg-ach .bdg-icon { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; font-size: 1.5rem; color: #fff;
      background: radial-gradient(circle at 30% 25%, color-mix(in srgb, var(--bc) 55%, #fff), var(--bc));
      box-shadow: 0 0 0 6px color-mix(in srgb, var(--bc) 14%, transparent); margin-bottom: 6px; }
  .pg-ach .bdg.skill .bdg-icon { border-radius: 18px; }
  .pg-ach .bdg.locked .bdg-icon { background: #EEF2F6; color: #A0AEC0; box-shadow: none; }
  .pg-ach .bdg.locked h4 { color: var(--text-dim); }
  .pg-ach .bdg h4 { margin: 0; font-size: .92rem; font-weight: 800; }
  .pg-ach .bdg p { margin: 0; font-size: .78rem; color: var(--text-dim); line-height: 1.4; }
  .pg-ach .bdg .sp-progress { width: 100%; height: 6px; margin-top: 4px; }
  .pg-ach .bdg-lock { position: absolute; top: 10px; right: 10px; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center;
      background: var(--surface-2); border: 1px solid var(--border); color: var(--text-muted); font-size: .68rem; }
  .pg-ach .bdg-when { margin-top: auto; padding-top: 6px; }
  .pg-ach .chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
  .pg-ach .chips span { font-size: .74rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: #EEF2F6; color: var(--text-dim); }
  .pg-ach .chips span.done { background: var(--success-soft); color: #0B8A4F; }
  .pg-ach .rank { font-size: 1.3rem; font-weight: 800; color: #B7791F; text-align: right; line-height: 1.1; }
  .pg-ach .rank small { display: block; font-size: .7rem; font-weight: 600; color: var(--text-dim); }
  .pg-ach .xp-plus { font-weight: 800; color: #B7791F; white-space: nowrap; font-size: .85rem; }
  @media (max-width: 640px) {
    .pg-ach .ach-hero-grid { grid-template-columns: 1fr; text-align: center; }
    .pg-ach .ach-ring { margin: 0 auto; }
    .pg-ach .ach-hero .sp-row { justify-content: center; }
  }
</style>

<div class="pg-ach">
  <div class="sp-page-head">
    <div><h1>Achievements</h1><p>Your level, badges, learning paths and certificates, all in one place.</p></div>
    <div class="sp-actions"><a href="leaderboard" class="sp-btn ghost"><i class="fas fa-ranking-star"></i> Leaderboard</a></div>
  </div>

  <!-- Level -->
  <section class="sp-hero ach-hero">
    <div class="ach-hero-grid">
      <div class="ach-ring" style="--p: <?= max(0, min(100, (int)$lv['pct'])) ?>" role="img" aria-label="Level <?= (int)$lv['level'] ?>, <?= (int)$lv['pct'] ?>% of the way to the next level">
        <div><span><b><?= (int)$lv['level'] ?></b><small>Level</small></span></div>
      </div>
      <div>
        <div class="sp-row">
          <span class="sp-badge dark"><i class="fas fa-medal" style="color: #FFC53D;"></i> <?= e($lv['title']) ?></span>
          <?php if ($streak > 0): ?><span class="sp-badge dark">🔥 <?= $streak ?>-day streak</span><?php endif; ?>
        </div>
        <h2><?= number_format((int)$lv['xp']) ?> XP earned</h2>
        <p><?= $xpToNext > 0 ? 'Earn ' . number_format($xpToNext) . ' more XP to reach level ' . ((int)$lv['level'] + 1) . '. Every lesson, exercise and quiz counts.' : 'You have reached the next level. Keep going!' ?></p>
        <div class="sp-progress dark" style="margin: 16px 0 8px; height: 10px;"><span style="width: <?= max(3, (int)$lv['pct']) ?>%;"></span></div>
        <div class="sp-between">
          <span>Level <?= (int)$lv['level'] ?> · <?= number_format((int)$lv['from']) ?> XP</span>
          <span>Level <?= (int)$lv['level'] + 1 ?> · <?= number_format((int)$lv['to']) ?> XP</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats -->
  <section class="sp-grid cols-4 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon orange"><i class="fas fa-fire"></i></span><div><strong><?= $streak ?></strong><span>Day streak · best <?= $bestStreak ?></span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= (int)$stats['lessons'] ?></strong><span>Lessons completed</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-clock"></i></span><div><strong><?= $timeLabel ?></strong><span>Time learning</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon violet"><i class="fas fa-medal"></i></span><div><strong><?= $earnedCount ?></strong><span>Badges earned</span></div></div>
    <a href="certificates" class="sp-stat" style="text-decoration: none;"><span class="sp-stat-icon yellow"><i class="fas fa-certificate"></i></span><div><strong><?= count($certs) ?></strong><span>Certificates</span></div></a>
  </section>

  <!-- Badges -->
  <?php foreach ($kindInfo as $kind => [$label, $hint]):
      if (!$byKind[$kind]) continue;
      $got = count(array_filter($byKind[$kind], fn($b) => $b['awarded_at']));
      // earned first, then locked ones closest to done
      usort($byKind[$kind], function ($a, $b) {
          if ((bool)$a['awarded_at'] !== (bool)$b['awarded_at']) return $a['awarded_at'] ? -1 : 1;
          return 0;
      });
  ?>
  <section class="sp-section">
    <div class="sp-section-head">
      <div><h2><?= $label ?></h2><p><?= $hint ?></p></div>
      <span class="sp-badge <?= $got ? 'green' : 'gray' ?>"><?= $got ?> of <?= count($byKind[$kind]) ?> earned</span>
    </div>
    <div class="ach-badges">
      <?php foreach ($byKind[$kind] as $b):
          $earned = (bool)$b['awarded_at'];
          $icon = str_starts_with((string)$b['icon'], 'fa-') ? $b['icon'] : 'fa-award';
      ?>
      <div class="bdg <?= $earned ? 'earned' : 'locked' ?> <?= $kind === 'skill' ? 'skill' : '' ?>" style="--bc: <?= e($b['color'] ?: '#0080ff') ?>">
        <?php if (!$earned): ?><span class="bdg-lock" title="Locked"><i class="fas fa-lock"></i></span><?php endif; ?>
        <div class="bdg-icon"><i class="fas <?= e($icon) ?>"></i></div>
        <h4><?= e($b['name']) ?></h4>
        <p><?= e($b['description']) ?></p>
        <?php if ($earned): ?>
          <span class="sp-badge green bdg-when"><i class="fas fa-check"></i> Earned <?= date('j M Y', strtotime($b['awarded_at'])) ?></span>
        <?php elseif ($kind === 'achievement' && isset($stats[$b['criteria_type']])): [$h, $n, $pc] = badge_progress($b, $stats); ?>
          <div class="sp-progress" title="<?= $pc ?>%"><span style="width: <?= max(2, $pc) ?>%;"></span></div>
          <span class="sp-small sp-muted"><?= number_format($h) ?> / <?= number_format($n) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
  <?php if (!$totalShown): ?>
    <div class="sp-empty sp-section">
      <div class="sp-ico violet"><i class="fas fa-medal"></i></div>
      <h3>No badges yet</h3>
      <p>Finish your first lesson to earn the First Steps badge.</p>
      <a href="../learn" class="sp-btn">Start a free tutorial</a>
    </div>
  <?php endif; ?>

  <!-- Learning paths -->
  <?php if ($paths): ?>
  <section class="sp-section">
    <div class="sp-section-head"><div><h2>Learning paths</h2><p>Finish every course in a path to earn its badge and certificate</p></div></div>
    <div class="sp-grid cols-3">
      <?php foreach ($paths as $p): $pc = (int)$p['pct']; ?>
      <div class="sp-card">
        <div class="sp-between">
          <div class="sp-row" style="flex-wrap: nowrap; min-width: 0;">
            <span class="sp-ico" style="background: color-mix(in srgb, <?= e($p['color'] ?: '#00A3E0') ?> 14%, #fff); color: <?= e($p['color'] ?: '#00A3E0') ?>;"><i class="fas <?= e($p['icon'] ?: 'fa-route') ?>"></i></span>
            <h3 style="margin: 0; font-size: .98rem; font-weight: 800;"><?= e($p['title']) ?></h3>
          </div>
          <span class="sp-badge <?= $pc >= 100 ? 'green' : ($pc > 0 ? '' : 'gray') ?>"><?= $pc ?>%</span>
        </div>
        <div class="chips">
          <?php foreach ($p['courses'] as $c): ?><span class="<?= !empty($c['completed']) ? 'done' : '' ?>"><?= !empty($c['completed']) ? '✓ ' : '' ?><?= e($c['tutorial_short'] ?: $c['title']) ?></span><?php endforeach; ?>
        </div>
        <div class="sp-progress <?= $pc >= 100 ? 'green' : '' ?>"><span style="width: <?= max(2, $pc) ?>%;"></span></div>
        <div class="sp-between sp-small sp-muted" style="margin-top: 8px;">
          <span><?= (int)$p['done'] ?> of <?= count($p['courses']) ?> courses done</span>
          <span><i class="fas fa-award"></i> Badge + certificate</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Courses & ranks -->
  <section class="sp-section">
    <div class="sp-section-head"><div><h2>My courses and ranks</h2><p>Your place on each course leaderboard, by XP earned in that course</p></div></div>
    <?php if (!$courses): ?>
      <div class="sp-empty">
        <div class="sp-ico"><i class="fas fa-ranking-star"></i></div>
        <h3>You're not taking any courses yet</h3>
        <p>Start a free tutorial. Progress, XP and badges are tracked automatically.</p>
        <a href="../learn" class="sp-btn">Free tutorials</a>
      </div>
    <?php else: ?>
    <div class="sp-grid cols-3">
      <?php foreach ($courses as $c): $cp = (int)round((float)$c['progress_percent']); $done = $c['status'] === 'completed'; ?>
      <div class="sp-card">
        <div class="sp-between" style="align-items: flex-start;">
          <h3 style="margin: 0; font-size: .95rem; font-weight: 800; min-width: 0;"><?= e($c['title']) ?></h3>
          <div class="rank"><?= $c['rank'] ? '#' . (int)$c['rank'] : '–' ?><small>of <?= (int)$c['learners'] ?></small></div>
        </div>
        <div class="sp-progress <?= $done ? 'green' : '' ?>" style="margin: 12px 0 8px;"><span style="width: <?= max(2, $cp) ?>%;"></span></div>
        <div class="sp-between sp-small">
          <span class="sp-muted"><?= $cp ?>% · <?= number_format($c['xp']) ?> XP<?= $done ? ' · <i class="fas fa-check" style="color: var(--success);"></i> Done' : '' ?></span>
          <?php if ($c['slug']): ?><a class="sp-link" href="../learn/<?= e($c['slug']) ?>/leaderboard">Leaderboard</a><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- Certificates -->
  <section class="sp-section">
    <div class="sp-section-head">
      <div><h2>Certificates</h2><p>Issued automatically when you finish a course or path</p></div>
      <a href="certificates" class="sp-link">All certificates <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if (!$certs): ?>
      <div class="sp-empty" style="padding: 28px;">
        <p style="margin: 0;">Complete every lesson of a course (and pass its final quiz) to receive a certificate you can download and share.</p>
      </div>
    <?php else: ?>
    <div class="sp-grid cols-3">
      <?php foreach ($certs as $c): $t = $c['certificate_type'] === 'path' ? $c['path_title'] : $c['course_title']; $pending = $c['status'] === 'pending'; ?>
      <div class="sp-card" style="border-left: 4px solid #C9A227;">
        <span class="sp-small sp-muted"><?= $c['certificate_type'] === 'path' ? 'Learning path' : 'Course' ?> · <?= date('j M Y', strtotime($c['issued_at'])) ?></span>
        <h3 style="margin: 4px 0; font-size: .98rem; font-weight: 800;"><?= e($t) ?></h3>
        <p class="sp-small sp-muted" style="margin: 0 0 12px;"><?= $pending ? '<i class="fas fa-lock"></i> Ready to unlock' : 'Code ' . e($c['verification_code']) ?><?= $c['score'] !== null ? ' · Score ' . (int)$c['score'] . '%' : '' ?></p>
        <div class="sp-row">
          <?php if ($pending): ?>
            <a class="sp-btn sm orange" href="../certificate-pay?code=<?= e(rawurlencode($c['verification_code'])) ?>"><i class="fas fa-lock-open"></i> Unlock · <?= e(money((float)$c['fee'])) ?></a>
          <?php else: ?>
            <a class="sp-btn sm" href="../certificate?code=<?= e(rawurlencode($c['verification_code'])) ?>" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a>
            <button type="button" class="sp-btn sm ghost" data-copy="<?= e(rawurlencode($c['verification_code'])) ?>"><i class="fas fa-link"></i> Copy link</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- Activity + privacy -->
  <section class="sp-split sp-section">
    <div>
      <div class="sp-section-head"><div><h2>Recent XP</h2><p>The last things that earned you XP</p></div></div>
      <div class="sp-card" style="padding: 6px 18px;">
        <?php if (!$events): ?>
          <p class="sp-muted sp-small" style="padding: 14px 0; margin: 0;">Your learning activity will appear here. Finish a lesson to earn your first XP.</p>
        <?php else: ?>
        <div class="sp-list">
          <?php foreach ($events as $ev): [$ic, $lbl] = $eventLabels[$ev['event_type']] ?? ['fa-bolt', 'Activity'];
              $what = $ev['lesson_title'] ?: ($ev['course_title'] ?: ''); ?>
          <div class="sp-list-item">
            <span class="sp-ico yellow" style="width: 34px; height: 34px;"><i class="fas <?= e($ic) ?>"></i></span>
            <div class="sp-grow">
              <h5 class="sp-truncate"><?= e($lbl) ?><?= $what !== '' ? ' ' . e($what) : '' ?></h5>
              <p><time datetime="<?= e($ev['created_at']) ?>"><?= date('j M, H:i', strtotime($ev['created_at'])) ?></time></p>
            </div>
            <span class="xp-plus">+<?= (int)$ev['xp'] ?> XP</span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="sp-section-head"><div><h2>How to earn XP</h2></div></div>
      <div class="sp-card" style="padding: 6px 18px; margin-bottom: 16px;">
        <div class="sp-list">
          <?php foreach ([['Finish a lesson', 'lesson_complete'], ['Solve an exercise', 'exercise_pass'], ['Pass a quiz', 'quiz_pass'], ['Finish a course', 'course_complete'], ['Finish a learning path', 'path_complete']] as [$t, $k]): ?>
          <div class="sp-list-item" style="padding: 10px 0;"><div class="sp-grow"><h5><?= $t ?></h5></div><span class="xp-plus">+<?= (int)(XP_RULES[$k] ?? 0) ?> XP</span></div>
          <?php endforeach; ?>
        </div>
      </div>

      <form method="post" class="sp-card" id="privacy">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="leaderboard_visibility" value="1">
        <div class="sp-card-head" style="margin-bottom: 10px;"><h4><i class="fas fa-user-shield" style="color: var(--text-dim);"></i> Leaderboard privacy</h4></div>
        <label style="display: flex; gap: 10px; align-items: flex-start; cursor: pointer; font-size: .88rem;">
          <input type="checkbox" name="show" value="1" <?= $me['show_on_leaderboard'] ? 'checked' : '' ?> style="width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0;">
          <span>Show me on course leaderboards (first name and last initial only)</span>
        </label>
        <div class="sp-row" style="margin-top: 14px;">
          <button class="sp-btn sm" type="submit">Save</button>
          <?php if (isset($_GET['saved'])): ?><span class="sp-badge green"><i class="fas fa-check"></i> Saved</span><?php endif; ?>
        </div>
      </form>
    </div>
  </section>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', () => {
  const url = new URL('../certificate?code=' + btn.dataset.copy, location.href).href;
  (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(
    () => { btn.innerHTML = '<i class="fas fa-check"></i> Copied'; },
    () => { window.open(url, '_blank', 'noopener'); });
}));
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
