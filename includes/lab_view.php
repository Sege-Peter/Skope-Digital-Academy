<?php
/**
 * Learning Lab: the reader for non-programming skills courses (/lab, /lab/<course>[/<lesson>]).
 * Included by learn.php, which has already resolved the course, lessons, locks, enrollment and progress.
 *   $labMode = 'home'   -> list of Lab courses
 *   $labMode = 'course' -> lesson / leaderboard / project / workbook views (uses learn.php's variables)
 * Keeps the element ids and classes assets/js/learn.js relies on (#lrSide, #lrMenu, .lr-done-row, #review, …).
 * Styles: assets/css/learn.css (lesson widgets) + assets/css/lab.css (Lab layout).
 */
$labUser = $user ?? (isLoggedIn() ? currentUser() : null);
$labSid = ($labUser && $labUser['role'] === 'student') ? (int)$labUser['id'] : null;
if (!isset($meLevel) && $labSid) {
    $x = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $x->execute([$labSid]);
    $meLevel = xp_level((int)$x->fetchColumn());
}
$catStyle = [
    'Career & Personal Skills' => ['#7c5cfc', 'fa-user-tie'],
    'Business'                 => ['#ff6a2b', 'fa-briefcase'],
    'Financial Literacy'       => ['#12b76a', 'fa-coins'],
    'Design'                   => ['#ec4899', 'fa-palette'],
    'Artificial Inteligence'   => ['#0ea5e9', 'fa-robot'],
    'Technology'               => ['#0077c2', 'fa-microchip'],
];
$catOf = fn($name) => $catStyle[$name ?? ''] ?? ['#00a3e0', 'fa-flask'];

$labTitle = $labMode === 'home' ? 'Learning Lab' : ($pageTitle ?? 'Learning Lab');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= e(sda_base_href()) ?>">
<title><?= e($labTitle) ?> – Skope Learning Lab</title>
<?php if (!empty($metaDescription)): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
<link rel="stylesheet" href="assets/css/learn.css?v=9">
<link rel="stylesheet" href="assets/css/lab.css?v=<?= @filemtime(__DIR__ . '/../assets/css/lab.css') ?>">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
<?php require_once __DIR__ . '/pwa.php'; sda_pwa_head(); ?>
</head>
<body class="lab-body lab-mode-<?= e($labMode) ?>">

<header class="lab-top">
  <?php if ($labMode === 'course'): ?><button class="lab-icon-btn lab-only-mobile" id="lrMenu" type="button" aria-label="Show lessons" aria-controls="lrSide"><i class="fas fa-bars"></i></button><?php endif; ?>
  <a class="lab-brand" href="lab">
    <span class="lab-brand-logo"><img src="assets/images/skope-logo-512.png" alt=""></span>
    <span class="lab-brand-text"><strong>Learning Lab</strong><small>Skope Digital Academy</small></span>
  </a>
  <?php if ($labMode === 'course'): ?>
    <div class="lab-top-course lab-hide-mobile">
      <span class="lab-top-sep"></span>
      <a href="<?= e(learn_url($course)) ?>" class="lab-truncate"><?= e($course['title']) ?></a>
      <?php if ($enrolled): ?><span class="lab-chip"><?= $pct ?>% done</span><?php endif; ?>
    </div>
  <?php endif; ?>
  <nav class="lab-top-links">
    <a href="lab" class="lab-hide-mobile"><i class="fas fa-flask"></i> All Lab courses</a>
    <a href="learn" class="lab-hide-mobile"><i class="fas fa-code"></i> Coding tutorials</a>
    <?php if (!empty($meLevel)): ?>
      <a class="lab-level" href="student/achievements" id="levelChip" title="Level <?= (int)$meLevel['level'] ?> <?= e($meLevel['title']) ?> · <?= number_format($meLevel['xp']) ?> XP">
        <span>Lv <b id="lvNum"><?= (int)$meLevel['level'] ?></b></span>
        <span class="lab-level-bar"><span id="lvBar" style="width:<?= (int)$meLevel['pct'] ?>%"></span></span>
        <span class="lab-hide-mobile"><b id="lvXp"><?= number_format($meLevel['xp']) ?></b> XP</span>
      </a>
    <?php endif; ?>
    <?php if ($labUser): ?>
      <a class="lab-btn sm" href="<?= e($labUser['role']) ?>/"><i class="fas fa-house"></i><span class="lab-hide-mobile"> Dashboard</span></a>
    <?php else: ?>
      <a href="login">Sign in</a>
      <a class="lab-btn sm" href="register">Join free</a>
    <?php endif; ?>
  </nav>
</header>

<?php if ($labMode === 'home'):
    /* ── Lab home: every published non-programming skills course ── */
    $rows = $pdo->query("SELECT c.*, cat.name AS category_name, (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
                         FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id
                         WHERE c.status = 'published' AND c.source = 'course' ORDER BY c.tutorial_order, c.title")->fetchAll();
    $labCourses = array_values(array_filter($rows, fn($c) => $c['lesson_count'] > 0 && is_lab_course($c)));
    $mine = [];
    if ($labSid) {
        $s = $pdo->prepare("SELECT course_id, status, progress_percent FROM enrollments WHERE student_id = ?");
        $s->execute([$labSid]);
        foreach ($s->fetchAll() as $r) $mine[(int)$r['course_id']] = $r;
    }
    $cats = array_values(array_unique(array_filter(array_map(fn($c) => $c['category_name'], $labCourses))));
    $going = array_values(array_filter($labCourses, fn($c) => isset($mine[(int)$c['id']]) && $mine[(int)$c['id']]['status'] === 'active'));
?>
<main class="lab-home">
  <section class="lab-hero lab-hero-has-art">
    <div>
      <span class="lab-kicker"><i class="fas fa-flask"></i> Free · Practical · Certificate</span>
      <h1>Skills you can use <span>this week.</span></h1>
      <p>Short, hands-on courses for work, business and everyday life. Each lesson ends with a practice task, and your workbook keeps your notes and answers in one place.</p>
      <div class="lab-hero-points">
        <span><i class="fas fa-list-check"></i> Practice tasks</span>
        <span><i class="fas fa-book"></i> Personal workbook</span>
        <span><i class="fas fa-users"></i> Peer-graded project</span>
        <span><i class="fas fa-award"></i> Certificate</span>
      </div>
    </div>
    <a class="lab-hero-art" href="<?= !empty($labUser) ? 'courses' : 'register' ?>" aria-label="Join Skope Digital Academy"><picture><source type="image/webp" srcset="assets/images/promo/lab-promo.webp"><img src="assets/images/promo/lab-promo.jpg" alt="Build skills for a digital future with Skope Digital Academy: expert mentors, quality courses, certified and flexible online learning." class="" loading="lazy" decoding="async"></picture></a>
  </section>

  <?php if ($going): ?>
  <section class="lab-section">
    <h2>Continue where you left off</h2>
    <div class="lab-grid">
      <?php foreach ($going as $c): [$col, $ic] = $catOf($c['category_name']); $p = (int)round($mine[(int)$c['id']]['progress_percent'] ?? 0); ?>
        <a class="lab-card" href="<?= e(learn_url($c)) ?>" style="--cc: <?= e($col) ?>">
          <span class="lab-card-ico"><i class="fas <?= e($ic) ?>"></i></span>
          <h3><?= e($c['title']) ?></h3>
          <div class="lab-bar"><span style="width: <?= max(2, $p) ?>%"></span></div>
          <div class="lab-card-foot"><span><?= $p ?>% complete</span><strong>Continue <i class="fas fa-arrow-right"></i></strong></div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="lab-section">
    <div class="lab-section-head">
      <h2>All Lab courses <small><?= count($labCourses) ?></small></h2>
      <div class="lab-filters" role="tablist">
        <button class="on" data-cat="">All</button>
        <?php foreach ($cats as $ct): ?><button data-cat="<?= e($ct) ?>"><?= e($ct === 'Artificial Inteligence' ? 'AI' : $ct) ?></button><?php endforeach; ?>
      </div>
    </div>
    <div class="lab-grid" id="labGrid">
      <?php foreach ($labCourses as $c): [$col, $ic] = $catOf($c['category_name']); $m = $mine[(int)$c['id']] ?? null; $fee = certificate_fee_for($c); ?>
        <a class="lab-card" href="<?= e($m ? learn_url($c) : 'course-details?id=' . (int)$c['id']) ?>" data-cat="<?= e($c['category_name'] ?? '') ?>" style="--cc: <?= e($col) ?>">
          <span class="lab-card-ico"><i class="fas <?= e($ic) ?>"></i></span>
          <span class="lab-card-cat"><?= e($c['category_name'] ?: 'Skills') ?></span>
          <h3><?= e($c['title']) ?></h3>
          <p><?= e(mb_strimwidth(strip_tags((string)$c['description']), 0, 125, '…')) ?></p>
          <?php if ($m): ?>
            <div class="lab-bar"><span style="width: <?= max(2, (int)round($m['progress_percent'] ?? 0)) ?>%"></span></div>
          <?php endif; ?>
          <div class="lab-card-foot">
            <span><?= (int)$c['lesson_count'] ?> lessons · <?= e(ucfirst($c['level'])) ?></span>
            <strong><?= $m ? ($m['status'] === 'completed' ? '✓ Finished' : 'Continue') : ($fee > 0 ? 'Free · cert ' . e(money($fee)) : 'Free') ?></strong>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<script>
document.querySelectorAll('.lab-filters button').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.lab-filters button').forEach(x => x.classList.toggle('on', x === b));
  document.querySelectorAll('#labGrid .lab-card').forEach(c => { c.hidden = !!b.dataset.cat && c.dataset.cat !== b.dataset.cat; });
}));
</script>
</body></html>
<?php return; endif; ?>

<?php
/* ── Course mode ── */
[$ccol, $cico] = $catOf($course['category_name'] ?? null);
$outcomes = json_decode((string)($course['outcomes'] ?? ''), true);
if (!is_array($outcomes)) $outcomes = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)($course['outcomes'] ?? '')))));
$wantWorkbook = isset($_GET['workbook']) && $enrolled;

// Workbook data for this learner (notes + practice tasks)
$wb = [];
if ($student_id) {
    lab_workbook_ensure($pdo);
    $s = $pdo->prepare("SELECT lesson_id, note, task_done, updated_at FROM lab_workbook WHERE user_id = ? AND course_id = ?");
    $s->execute([$student_id, $course_id]);
    foreach ($s->fetchAll() as $r) $wb[(int)$r['lesson_id']] = $r;
}
$tasksDone = count(array_filter($wb, fn($r) => !empty($r['task_done'])));
$moduleOf = [];
$wk = null; $mod = '';
foreach ($lessons as $l) {
    if ($wk !== (int)$l['week_num']) { $wk = (int)$l['week_num']; $mod = $l['module_title'] ?: (($enrolled && $enrollment['learning_mode'] === 'structured' ? 'Week ' : 'Module ') . $wk); }
    $moduleOf[(int)$l['id']] = $mod;
}
$canWrite = $student_id && $active && $active['can_read'] && ($enrolled || !empty($active['is_preview']));
?>
<div class="lab-wrap" style="--cc: <?= e($ccol) ?>">
  <!-- Course outline -->
  <nav class="lab-side" id="lrSide" aria-label="Lessons">
    <div class="lab-course-card">
      <span class="lab-card-ico"><i class="fas <?= e($cico) ?>"></i></span>
      <div>
        <h2><?= e($course['title']) ?></h2>
        <span class="lab-muted"><?= count($lessons) ?> lessons<?= $course['category_name'] ? ' · ' . e($course['category_name']) : '' ?></span>
      </div>
    </div>
    <?php if ($enrolled): ?>
      <div class="lab-side-progress">
        <div class="lab-between"><span><?= e(mode_label($enrollment['learning_mode'])) ?></span><strong><?= $pct ?>%</strong></div>
        <div class="lab-bar lr-progress"><span style="width: <?= $pct ?>%"></span></div>
        <div class="lab-side-stats"><span><i class="fas fa-circle-check"></i> <?= $done ?>/<?= count($lessons) ?> lessons</span><span><i class="fas fa-list-check"></i> <?= $tasksDone ?> tasks</span></div>
      </div>
    <?php endif; ?>

    <?php $wk = null; foreach ($lessons as $n => $l):
        if ($wk !== (int)$l['week_num']) { $wk = (int)$l['week_num']; echo '<div class="lab-module">' . e($moduleOf[(int)$l['id']]) . '</div>'; }
        $isDone = ($l['progress_status'] ?? '') === 'completed';
        $isOn = $view === 'lesson' && !$wantWorkbook && $active && $l['id'] == $active['id'];
    ?>
      <a class="lab-item lr-item<?= $isOn ? ' on' : '' ?><?= $l['can_read'] ? '' : ' locked' ?><?= $isDone ? ' done' : '' ?>" href="<?= e(learn_url($course, $l)) ?>" <?= $l['can_read'] ? '' : 'title="' . e($l['why']) . '"' ?><?= $isOn ? ' aria-current="page"' : '' ?>>
        <span class="lab-step"><?= $isDone ? '<i class="fas fa-check"></i>' : ($l['can_read'] ? $n + 1 : '<i class="fas fa-lock"></i>') ?></span>
        <span class="lab-item-text"><?= e($l['title']) ?><?php if (!empty($l['is_preview']) && !$enrolled): ?> <em class="lab-tag">Preview</em><?php endif; ?></span>
        <?php if (!empty($wb[(int)$l['id']]['task_done'])): ?><i class="fas fa-list-check lab-item-task" title="Practice task done"></i><?php endif; ?>
      </a>
    <?php endforeach; ?>

    <?php if ($project): $chip = $pstate['chip'] ?? ['Peer graded', '']; ?>
      <div class="lab-module">Final project</div>
      <a class="lab-item lr-item<?= $view === 'project' ? ' on' : '' ?>" href="<?= e($projUrl) ?>"><span class="lab-step"><i class="fas fa-diagram-project"></i></span><span class="lab-item-text">Course project <em class="lab-tag"><?= e($chip[0]) ?></em></span></a>
    <?php endif; ?>

    <div class="lab-module">Your tools</div>
    <?php if ($enrolled): ?><a class="lab-item lr-item<?= $wantWorkbook ? ' on' : '' ?>" href="<?= e(learn_url($course, null, 'workbook=1')) ?>"><span class="lab-step"><i class="fas fa-book"></i></span><span class="lab-item-text">My workbook</span></a><?php endif; ?>
    <a class="lab-item lr-item<?= $view === 'leaderboard' ? ' on' : '' ?>" href="<?= e($root . '/' . $course['slug'] . '/leaderboard') ?>"><span class="lab-step"><i class="fas fa-ranking-star"></i></span><span class="lab-item-text">Leaderboard</span></a>
    <?php if ($labSid): ?><a class="lab-item lr-item" href="student/mentor"><span class="lab-step"><i class="fas fa-robot"></i></span><span class="lab-item-text">Ask the AI mentor</span></a><?php endif; ?>
  </nav>

  <main class="lab-main">
    <div class="lab-crumbs"><a href="lab">Learning Lab</a> › <a href="<?= e(learn_url($course)) ?>"><?= e($course['title']) ?></a><?= $wantWorkbook ? ' › My workbook' : ($view === 'leaderboard' ? ' › Leaderboard' : ($view === 'project' ? ' › Course project' : ($active ? ' › ' . e($active['title']) : ''))) ?></div>

  <?php if ($wantWorkbook): ?>
    <!-- Workbook: every note and practice task in one printable page -->
    <div class="lab-page-head">
      <div><h1>My workbook</h1><p class="lab-muted">Your notes and practice tasks for <?= e($course['title']) ?>. Print it or save it as a PDF to keep.</p></div>
      <button class="lab-btn ghost" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / save PDF</button>
    </div>
    <div class="lab-wb-stats">
      <div><strong><?= $done ?>/<?= count($lessons) ?></strong><span>Lessons done</span></div>
      <div><strong><?= $tasksDone ?></strong><span>Practice tasks done</span></div>
      <div><strong><?= count(array_filter($wb, fn($r) => trim((string)$r['note']) !== '')) ?></strong><span>Lessons with notes</span></div>
    </div>
    <?php $wk = null; foreach ($lessons as $n => $l): $r = $wb[(int)$l['id']] ?? null; ?>
      <?php if ($wk !== (int)$l['week_num']) { $wk = (int)$l['week_num']; echo '<h2 class="lab-wb-module">' . e($moduleOf[(int)$l['id']]) . '</h2>'; } ?>
      <section class="lab-wb-entry">
        <div class="lab-between">
          <h3><?= $n + 1 ?>. <?= e($l['title']) ?></h3>
          <span class="lab-chip <?= !empty($r['task_done']) ? 'green' : '' ?>"><?= !empty($r['task_done']) ? '✓ Task done' : 'Task not done' ?></span>
        </div>
        <?php if ($r && trim((string)$r['note']) !== ''): ?>
          <div class="lab-wb-note"><?= nl2br(e($r['note'])) ?></div>
        <?php else: ?>
          <p class="lab-muted lab-small">No notes yet.<?= $l['can_read'] ? ' <a href="' . e(learn_url($course, $l)) . '">Open the lesson</a> to add some.' : '' ?></p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

  <?php elseif ($view === 'leaderboard'): $period = ($_GET['period'] ?? '') === 'week' ? 'week' : 'all'; ?>
    <div class="lab-page-head"><div><h1>Leaderboard</h1><p class="lab-muted">Everyone learning <?= e($course['title']) ?>, ranked by XP earned in this course.</p></div></div>
    <div class="lab-tabs">
      <a class="<?= $period === 'all' ? 'on' : '' ?>" href="<?= e($root . '/' . $course['slug']) ?>/leaderboard">All time</a>
      <a class="<?= $period === 'week' ? 'on' : '' ?>" href="<?= e($root . '/' . $course['slug']) ?>/leaderboard?period=week">This week</a>
    </div>
    <?php if (!$user): ?>
      <div class="lab-note"><i class="fas fa-user-lock"></i><div><a href="login">Sign in</a> or <a href="register">create a free account</a> to see and join the leaderboard.</div></div>
    <?php elseif (!$board): ?>
      <p class="lab-muted">No activity <?= $period === 'week' ? 'this week' : 'yet' ?>. Finish a lesson to claim the top spot!</p>
    <?php else: ?>
      <div class="lab-board">
        <?php foreach ($board as $b): $me = $student_id && (int)$b['id'] === $student_id; ?>
          <div class="lab-board-row<?= $me ? ' me' : '' ?>">
            <span class="lab-rank"><?= $b['rank'] <= 3 ? '<i class="fas fa-medal m' . (int)$b['rank'] . '"></i>' : '#' . (int)$b['rank'] ?></span>
            <span class="lab-av"><?= e(mb_strtoupper(mb_substr($b['display_name'], 0, 1))) ?></span>
            <span class="lab-grow"><strong><?= e($me ? 'You' : $b['display_name']) ?></strong><span class="lab-bar thin"><span style="width: <?= (int)round($b['progress_percent']) ?>%"></span></span></span>
            <span class="lab-xp"><?= number_format((int)$b['course_xp']) ?> XP</span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="lab-muted lab-small">Names show as first name and last initial. You can hide yourself on your achievements page.</p>
    <?php endif; ?>

  <?php elseif ($view === 'project'): ?>
    <div class="lab-project"><?php include __DIR__ . '/project_view.php'; ?></div>

  <?php elseif (!$active): ?>
    <h1><?= e($course['title']) ?></h1>
    <p>Lessons for this course are on the way.</p>

  <?php else: ?>
    <!-- Lesson -->
    <div class="lab-lesson-head">
      <span class="lab-eyebrow"><?= e($moduleOf[(int)$active['id']] ?? '') ?> · Lesson <?= $idx + 1 ?> of <?= count($lessons) ?></span>
      <h1><?= e($active['title']) ?></h1>
      <div class="lab-meta">
        <?php if ($active['duration_mins']): ?><span><i class="far fa-clock"></i> <?= (int)$active['duration_mins'] ?> min</span><?php endif; ?>
        <?php if (($active['progress_status'] ?? '') === 'completed'): ?><span class="lab-chip green"><i class="fas fa-check"></i> Completed</span><?php endif; ?>
        <?php if (!empty($active['due_at'])): ?><span><i class="far fa-calendar"></i> Due <?= date('D, j M', $active['due_at']) ?></span><?php endif; ?>
      </div>
    </div>

    <?php if (isset($_GET['tracking'])): ?>
      <div class="lab-note ok"><i class="fas fa-circle-check"></i><div><strong>You're in!</strong> Your progress, notes and tasks are now saved.</div></div>
    <?php endif; ?>
    <?php if ($myCert && (isset($_GET['finished']) || !$next)): ?>
      <div class="lab-cert">
        <i class="fas fa-award"></i>
        <?php if ($myCertRow['status'] === 'pending'): ?>
          <div><strong>You completed <?= e($course['title']) ?>!</strong><span>Your certificate is ready. Unlock it for <?= e(money((float)$myCertRow['fee'])) ?> to download and share it.</span></div>
          <a class="lab-btn orange" href="certificate-pay?code=<?= e(rawurlencode($myCert)) ?>"><i class="fas fa-lock-open"></i> Unlock certificate</a>
        <?php else: ?>
          <div><strong>You completed <?= e($course['title']) ?>!</strong><span>Your certificate is ready to view, download and share.</span></div>
          <a class="lab-btn orange" href="certificate?code=<?= e(rawurlencode($myCert)) ?>" target="_blank" rel="noopener"><i class="fas fa-certificate"></i> View certificate</a>
        <?php endif; ?>
      </div>
    <?php elseif ($enrolled && !$courseDone && $done === count($lessons) && count($lessons) > 0 && $project && $pstate && (!course_has_quiz($pdo, $course_id) || (int)best_quiz_pct($pdo, $student_id, $course_id) >= QUIZ_PASS_PCT)): ?>
      <div class="lab-note"><i class="fas fa-diagram-project"></i><div><strong>Almost there!</strong> Every lesson is done. Pass the <a href="<?= e($projUrl) ?>">course project</a> to complete the course and earn your certificate.</div></div>
    <?php elseif ($enrolled && !$courseDone && $done === count($lessons) && count($lessons) > 0 && course_has_quiz($pdo, $course_id)): ?>
      <div class="lab-note"><i class="fas fa-flag-checkered"></i><div><strong>Almost there!</strong> Score <?= QUIZ_PASS_PCT ?>% or more on the final quiz to earn your certificate.</div></div>
    <?php endif; ?>

    <?php if ($idx === 0 && $outcomes): ?>
      <section class="lab-outcomes">
        <h2><i class="fas fa-bullseye"></i> By the end of this course you will be able to</h2>
        <ul><?php foreach (array_slice($outcomes, 0, 6) as $o): ?><li><i class="fas fa-check"></i> <?= e($o) ?></li><?php endforeach; ?></ul>
      </section>
    <?php endif; ?>

    <?php if (!$active['can_read']): ?>
      <div class="lab-note lock"><i class="fas fa-lock"></i><div>
        <strong><?= e($active['why']) ?></strong>
        <?php if (!$enrolled): ?>
          <p><?= course_from_price($course) <= 0 ? 'This course is free.' : '' ?> <?= $user ? 'Join the course' : 'Create a free account' ?> to open every lesson, save your workbook and earn a certificate.</p>
          <a class="lab-btn" href="<?= $user ? 'enroll?id=' . $course_id : 'register' ?>"><?= $user ? 'Start learning' : 'Register free' ?></a>
          <?php if (!$user): ?> <a class="lab-btn ghost" href="login">Sign in</a><?php endif; ?>
        <?php elseif ($prev): ?>
          <p>Lessons open one at a time so each skill builds on the last.</p>
          <a class="lab-btn" href="<?= e(learn_url($course, $prev)) ?>"><i class="fas fa-arrow-left"></i> Go to the previous lesson</a>
        <?php endif; ?>
      </div></div>
    <?php else: ?>
      <div class="lab-read">
        <div class="lab-read-main">
          <?php if ($embed): ?>
            <div class="lab-video"><iframe src="<?= e($embed) ?>" title="<?= e($active['title']) ?> video" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div>
          <?php endif; ?>
          <article class="lr-content lab-content" id="labContent"><?= $isLibrary ? render_lesson((string)$active['content'], $ctx) : render_lesson_text($active['content']) ?></article>

          <?php if (!empty($active['pdf_resource']) || ($active['lesson_type'] === 'pdf' && !empty($active['file_url']))): ?>
            <p><a class="lab-btn ghost" href="uploads/lessons/<?= e(basename($active['pdf_resource'] ?: $active['file_url'])) ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Lesson PDF</a></p>
          <?php endif; ?>

          <div class="lab-done lr-done-row">
            <?php if ($enrolled): ?>
              <?php if (($active['progress_status'] ?? '') === 'completed'): ?>
                <span class="lab-chip green big"><i class="fas fa-check"></i> Lesson completed</span>
                <?php if ($next && $next['can_read']): ?><a class="lab-btn" href="<?= e(learn_url($course, $next)) ?>">Next lesson <i class="fas fa-arrow-right"></i></a><?php endif; ?>
              <?php else: ?>
                <?php if (!empty($blockReason)): ?>
                  <div class="lr-gate" id="lrGate" role="status"><i class="fas fa-lock"></i><span><?= e($blockReason) ?></span></div>
                <?php endif; ?>
                <form method="post" action="<?= e($here) ?>" id="lrCompleteForm"<?= !empty($blockReason) ? ' hidden' : '' ?>><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                  <button class="lab-btn lg" name="complete" value="1"><i class="fas fa-check"></i> Mark complete<?= $next ? ' &amp; continue' : '' ?></button></form>
                <span class="lab-muted lab-small"><?= $next ? 'You can read ahead any time; finish this lesson\'s tests to move on.' : 'This is the last lesson.' ?></span>
              <?php endif; ?>
              <?php if (!empty($active['attached_quiz_id'])): ?>
                <a class="lab-btn ghost" href="student/take-quiz?id=<?= (int)$active['attached_quiz_id'] ?>"><i class="fas fa-circle-question"></i> Lesson quiz</a>
              <?php endif; ?>
            <?php elseif (!$user): ?>
              <span class="lab-muted"><a href="login">Sign in</a> or <a href="register">create a free account</a> to save progress, notes and earn a certificate.</span>
            <?php elseif (!$is_staff): ?>
              <a class="lab-btn orange" href="enroll?id=<?= $course_id ?>">Join this course free</a>
            <?php endif; ?>
          </div>

          <?php if ($project && !$next): $pc = $pstate['chip'] ?? null; ?>
            <aside class="lab-note"><i class="fas fa-diagram-project"></i><div>
              <strong>Final step: the course project</strong> <?= e($project['title']) ?>. Submit your work, review <?= (int)$project['reviews_required'] ?> classmates and score <?= (int)$project['pass_pct'] ?>% or more to complete the course.
              <div style="margin-top: 10px;"><a class="lab-btn" href="<?= e($projUrl) ?>">Open the project <i class="fas fa-arrow-right"></i></a></div>
            </div></aside>
          <?php endif; ?>

          <?php if ($canReview && (!$next || $courseDone || isset($_GET['review']))): ?>
            <section class="lr-review lab-review" id="review" data-course="<?= $course_id ?>" data-csrf="<?= e(csrfToken()) ?>">
              <h3><?= $myReview ? 'Your review' : 'How was this course?' ?></h3>
              <p class="lab-muted"><?= $ratingStats['n'] ? 'Rated <strong>' . e($ratingStats['avg_r']) . ' / 5</strong> by ' . (int)$ratingStats['n'] . ' learner' . ((int)$ratingStats['n'] === 1 ? '' : 's') . '.' : 'Be the first to rate it.' ?></p>
              <div class="lr-stars" role="radiogroup" aria-label="Your rating">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <button type="button" role="radio" data-v="<?= $s ?>" aria-checked="<?= (int)($myReview['rating'] ?? 0) === $s ? 'true' : 'false' ?>" aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>" class="<?= $s <= (int)($myReview['rating'] ?? 0) ? 'on' : '' ?>"><i class="fas fa-star"></i></button>
                <?php endfor; ?>
              </div>
              <textarea maxlength="1500" rows="3" placeholder="What did you like? What could be better? (optional)" aria-label="Your review"><?= e($myReview['review'] ?? '') ?></textarea>
              <div class="lr-ex-actions"><button type="button" class="lab-btn" data-act="save-review"><?= $myReview ? 'Update review' : 'Submit review' ?></button><span class="lr-ex-result" role="status"></span></div>
            </section>
          <?php endif; ?>
        </div>

        <!-- Lab tools beside the lesson -->
        <aside class="lab-tools">
          <?php if ($canWrite): $myNote = (string)($wb[(int)$active['id']]['note'] ?? ''); ?>
            <section class="lab-tool">
              <div class="lab-between"><h3><i class="fas fa-pen-to-square"></i> My notes</h3><span class="lab-muted lab-small" id="noteState"><?= $myNote !== '' ? 'Saved' : '' ?></span></div>
              <textarea id="labNote" rows="7" maxlength="20000" placeholder="Write key points, ideas or your answers to the practice task. Notes save automatically."><?= e($myNote) ?></textarea>
              <?php if ($enrolled): ?><a class="lab-link" href="<?= e(learn_url($course, null, 'workbook=1')) ?>"><i class="fas fa-book"></i> Open my workbook</a><?php endif; ?>
            </section>
          <?php endif; ?>
          <section class="lab-tool">
            <h3><i class="fas fa-route"></i> In this module</h3>
            <ol class="lab-mini-steps">
              <?php foreach ($lessons as $n => $l): if (($moduleOf[(int)$l['id']] ?? '') !== ($moduleOf[(int)$active['id']] ?? '')) continue; $d = ($l['progress_status'] ?? '') === 'completed'; ?>
                <li class="<?= $l['id'] == $active['id'] ? 'on' : '' ?><?= $d ? ' done' : '' ?>"><?= $d ? '<i class="fas fa-check"></i>' : '<span>' . ($n + 1) . '</span>' ?> <?= e($l['title']) ?></li>
              <?php endforeach; ?>
            </ol>
          </section>
          <?php if ($labSid): ?>
          <section class="lab-tool help">
            <h3><i class="fas fa-life-ring"></i> Stuck?</h3>
            <p class="lab-muted lab-small">Ask the AI mentor to explain this lesson in simpler words, or with a Kenyan example.</p>
            <a class="lab-btn ghost block" href="student/mentor"><i class="fas fa-robot"></i> Ask the AI mentor</a>
          </section>
          <?php endif; ?>
        </aside>
      </div>
    <?php endif; ?>

    <div class="lab-nav lr-nav-bottom">
      <?php if ($prev): ?><a class="lab-nav-btn" href="<?= e(learn_url($course, $prev)) ?>"><i class="fas fa-chevron-left"></i><span><small>Previous</small><?= e($prev['title']) ?></span></a><?php else: ?><span></span><?php endif; ?>
      <?php if ($next): ?><a class="lab-nav-btn next<?= $next['can_read'] ? '' : ' locked' ?>" href="<?= e(learn_url($course, $next)) ?>"><span><small>Next<?= $next['can_read'] ? '' : ' · locked' ?></small><?= e($next['title']) ?></span><i class="fas <?= $next['can_read'] ? 'fa-chevron-right' : 'fa-lock' ?>"></i></a><?php endif; ?>
    </div>
  <?php endif; ?>
  </main>
</div>
<div class="lab-scrim" id="labScrim"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
<div class="lr-celebrate" id="celebrate" hidden></div>
<div class="lr-xp-toast" id="xpToast" role="status" aria-live="polite"></div>
<script>window.SDA_TRACK = <?= json_encode($track, JSON_HEX_TAG | JSON_HEX_AMP) ?>; window.SDA_FLASH = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP) ?>; window.SDA_LEVEL = <?= json_encode($meLevel) ?>;
window.LAB = <?= json_encode($canWrite && $view === 'lesson' && !$wantWorkbook ? ['endpoint' => 'api/lab', 'csrf' => csrfToken(), 'lesson' => (int)$active['id'], 'taskDone' => !empty($wb[(int)$active['id']]['task_done'])] : null, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/js/learn.js?v=9"></script>
<script src="assets/js/lab.js?v=<?= @filemtime(__DIR__ . '/../assets/js/lab.js') ?>"></script>
</body>
</html>
