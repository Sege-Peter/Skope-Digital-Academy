<?php
/**
 * Public tutorial library (W3Schools-style).
 *   /learn                        → tutorials home (+ lesson search with ?q=)
 *   /learn/<tutorial>[/<lesson>]  → reader (learn.php?c=&l=, see .htaccess); legacy ?course=ID&lesson=ID still works
 *
 * Open-tutorial courses are readable by anyone. Other courses show their preview
 * lessons; the rest need an enrollment (and follow the learner's track schedule).
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';
require_once 'includes/lesson_render.php';
require_once 'includes/progress.php';
require_once 'includes/projects.php';

$user = isLoggedIn() ? currentUser() : null;
$q = trim($_GET['q'] ?? '');

// Tutorials across the top bar: every published open tutorial.
$tabs = $pdo->query("SELECT id, title, slug, tutorial_short FROM courses
                     WHERE status = 'published' AND is_open_tutorial = 1
                     ORDER BY (source = 'tutorial') DESC, tutorial_order ASC, is_featured DESC, title ASC")->fetchAll();

/* ── Resolve the course ───────────────────────────────────────────────── */
$course = null;
if (!empty($_GET['c'])) {
    $st = $pdo->prepare("SELECT c.*, cat.name AS category_name FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.slug = ? LIMIT 1");
    $st->execute([$_GET['c']]);
    $course = $st->fetch() ?: null;
    if (!$course) { http_response_code(404); }
} elseif (!empty($_GET['course'])) {
    $st = $pdo->prepare("SELECT c.*, cat.name AS category_name FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?");
    $st->execute([(int)$_GET['course']]);
    $course = $st->fetch() ?: null;
}

/* ── Learning Lab home (/lab): the non-programming skills courses ─────────── */
if (!empty($_GET['labhome'])) {
    $labMode = 'home';
    include 'includes/lab_view.php';
    exit;
}

/* ── Tutorials home / search ──────────────────────────────────────────── */
if (!$course && empty($_GET['c']) && empty($_GET['course'])) {
    $all = $pdo->query("SELECT c.*, cat.name AS category_name,
                               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count,
                               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id AND l.is_preview = 1) AS preview_count
                        FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id
                        WHERE c.status = 'published'
                        ORDER BY (c.source = 'tutorial') DESC, c.tutorial_order, c.is_featured DESC, c.title")->fetchAll();
    $open = array_values(array_filter($all, fn($c) => $c['is_open_tutorial'] && $c['lesson_count'] > 0));
    // Only programming courses here; the other skills courses live in the Learning Lab (/lab)
    $freeCourses = array_values(array_filter($all, fn($c) => $c['source'] === 'course' && $c['lesson_count'] > 0 && !is_lab_course($c)));
    $labCount = count(array_filter($all, fn($c) => $c['source'] === 'course' && $c['lesson_count'] > 0 && is_lab_course($c)));
    usort($freeCourses, fn($a, $b) => $a['tutorial_order'] <=> $b['tutorial_order']);
    $previews = array_values(array_filter($all, fn($c) => !$c['is_open_tutorial'] && $c['source'] !== 'course' && $c['preview_count'] > 0));

    $results = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $st = $pdo->prepare("SELECT l.id, l.title, l.slug, l.content, c.id AS course_id, c.slug AS course_slug, c.title AS course_title, c.tutorial_short,
                                    (l.title LIKE ?) AS in_title
                             FROM lessons l JOIN courses c ON c.id = l.course_id
                             WHERE c.status = 'published' AND (c.is_open_tutorial = 1 OR l.is_preview = 1)
                               AND (l.title LIKE ? OR l.content LIKE ? OR c.title LIKE ?)
                             ORDER BY in_title DESC, c.tutorial_order, l.week_num, l.order_num LIMIT 40");
        $st->execute([$like, $like, $like, $like]);
        foreach ($st->fetchAll() as $r) {
            $text = trim(preg_replace('/\s+/', ' ', preg_replace('/```.*?```|[#*`>|_\[\]()-]/s', ' ', $r['content'])));
            $pos = mb_stripos($text, $q);
            $r['snippet'] = mb_substr($text, max(0, ($pos === false ? 0 : $pos) - 60), 170);
            $results[] = $r;
        }
        require_once __DIR__ . '/includes/site_stats.php';
        sda_stats_search($pdo, $q, 'tutorials', count($results));
    }

    $palette = ['html' => ['#e34c26', 'fa-html5', 'fab'], 'css' => ['#2965f1', 'fa-css3-alt', 'fab'], 'javascript' => ['#d4a900', 'fa-js', 'fab'],
                'python' => ['#3776ab', 'fa-python', 'fab'], 'sql' => ['#00758f', 'fa-database', 'fas'], 'php' => ['#777bb3', 'fa-php', 'fab']];
    if ($user && $user['role'] === 'student') {
        $x = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
        $x->execute([(int)$user['id']]);
        $meLevel = xp_level((int)$x->fetchColumn());
    }
    $pageTitle = $q !== '' ? 'Search: ' . $q : setting('tutorials_title');
    $metaDescription = 'Free HTML, CSS, JavaScript, Python, SQL and PHP tutorials with examples you can edit and run in your browser.';
    include 'includes/learn-top.php';
    ?>
    <main class="lr-home">
      <section class="lr-home-hero">
        <div class="lr-index">
          <span class="lr-kicker"><i class="fas fa-bolt"></i> Free · No sign-up needed</span>
          <h1>Learn to code, <span>one example at a time.</span></h1>
          <p>Step-by-step tutorials with live examples you can edit and run right in your browser. Sign in to track your progress.</p>
          <form class="lr-search" method="get" action="learn" role="search">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search e.g. “flexbox”, “for loop”, “JOIN”…" aria-label="Search tutorials">
            <button class="lr-btn lr-btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
          </form>
        </div>
      </section>

      <div class="lr-index">
      <?php if ($q !== ''): ?>
        <h2 class="lr-section-title"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($q) ?>”</h2>
        <?php if (!$results): ?><p class="lr-muted">Nothing matched. Try a shorter word, or browse the tutorials below.</p><?php endif; ?>
        <div class="lr-results">
          <?php foreach ($results as $r): ?>
            <a class="lr-result" href="<?= e(learn_url(['id' => $r['course_id'], 'slug' => $r['course_slug']], ['id' => $r['id'], 'slug' => $r['slug']])) ?>">
              <span class="lr-result-tut"><?= e($r['tutorial_short'] ?: $r['course_title']) ?></span>
              <strong><?= e($r['title']) ?></strong>
              <span class="lr-muted"><?= e($r['snippet']) ?>…</span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

        <?php if ($freeCourses): $defFee = (float)setting('certificate_fee'); ?>
        <h2 class="lr-section-title">Free coding courses <span class="lr-muted" style="font:500 .95rem Inter,sans-serif">– register free, learn at your own pace or on a weekly track, earn a certificate</span></h2>
        <div class="lr-grid lr-free-grid">
          <?php foreach ($freeCourses as $c): $cf = certificate_fee_for($c); ?>
          <a class="lr-card" href="course-details?id=<?= (int)$c['id'] ?>">
            <span class="lr-pill free"><i class="fas fa-gift"></i> Free course</span>
            <h3><?= e($c['title']) ?></h3>
            <p><?= e(mb_strimwidth(strip_tags((string)$c['description']), 0, 120, '…')) ?></p>
            <div class="lr-card-foot"><span style="white-space:nowrap"><?= (int)$c['lesson_count'] ?> lessons · 4 wks</span><strong style="font-size:.8rem;color:#92400e;white-space:nowrap"><?= $cf > 0 ? 'Certificate ' . e(money($cf)) : 'Free certificate' ?></strong></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($labCount)): ?>
        <a class="lr-card" href="lab" style="display:flex;align-items:center;gap:16px;margin:8px 0 28px;border-left:4px solid #7c5cfc">
          <i class="fas fa-flask" style="font-size:1.6rem;color:#7c5cfc"></i>
          <span style="flex:1"><strong style="display:block">Looking for career, business or design skills?</strong><span class="lr-muted"><?= (int)$labCount ?> free skills courses (CV writing, bookkeeping, digital marketing and more) are in the Learning Lab.</span></span>
          <span class="lr-btn lr-btn-ghost">Open the Learning Lab <i class="fas fa-arrow-right"></i></span>
        </a>
        <?php endif; ?>

        <h2 class="lr-section-title">Tutorials</h2>
        <?php if (!$open): ?><p class="lr-muted">Tutorials are on the way.</p><?php endif; ?>
        <div class="lr-tut-grid">
          <?php foreach ($open as $c):
            [$col, $icon, $fam] = $palette[$c['slug']] ?? ['#0080ff', 'fa-book-open', 'fas']; ?>
            <article class="lr-tut" style="--tut: <?= e($col) ?>">
              <div class="lr-tut-top"><i class="<?= $fam ?> <?= $icon ?>"></i><span><?= e($c['tutorial_short'] ?: $c['title']) ?></span></div>
              <h3><?= e($c['title']) ?></h3>
              <p><?= e(mb_strimwidth((string)$c['description'], 0, 150, '…')) ?></p>
              <div class="lr-tut-meta"><?= (int)$c['lesson_count'] ?> lessons · <?= e(ucfirst($c['level'])) ?></div>
              <div class="lr-tut-actions">
                <a class="lr-btn lr-btn-tut" href="<?= e(learn_url($c)) ?>">Learn <?= e($c['tutorial_short'] ?: '') ?></a>
                <?php $lang = in_array($c['slug'], ['html', 'css', 'javascript', 'python', 'sql'], true) ? $c['slug'] : null; ?>
                <?php if ($lang): ?><a class="lr-btn lr-btn-ghost" href="tryit?lang=<?= e($lang) ?>">Try it</a><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?php
          $homeUid = ($user && $user['role'] === 'student') ? (int)$user['id'] : null;
          $paths = learning_paths($pdo, $homeUid);
          if ($homeUid) { $x = $pdo->prepare("SELECT xp FROM users WHERE id = ?"); $x->execute([$homeUid]); $meLevel = xp_level((int)$x->fetchColumn()); }
        ?>
        <?php if ($paths): ?>
        <h2 class="lr-section-title">Learning paths <span class="lr-muted" style="font:500 .95rem Inter,sans-serif">– finish every course in a path to earn a path badge and a professional certificate</span></h2>
        <div class="lr-paths">
          <?php foreach ($paths as $p): ?>
            <article class="lr-path" style="--pc: <?= e($p['color']) ?>">
              <div class="lr-path-top"><i class="fas <?= e($p['icon']) ?>"></i><div><h3><?= e($p['title']) ?></h3><p><?= e($p['description']) ?></p></div></div>
              <ol class="lr-path-steps">
                <?php foreach ($p['courses'] as $i => $c): ?>
                  <li class="<?= !empty($c['completed']) ? 'done' : (!empty($c['progress']) ? 'doing' : '') ?>">
                    <a href="<?= e(learn_url($c)) ?>"><span class="n"><?= !empty($c['completed']) ? '<i class="fas fa-check"></i>' : $i + 1 ?></span><?= e($c['tutorial_short'] ?: $c['title']) ?></a>
                    <?php if ($homeUid && !empty($c['progress']) && empty($c['completed'])): ?><small><?= (int)$c['progress'] ?>%</small><?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ol>
              <div class="lr-path-foot">
                <?php if ($homeUid): ?><span class="lr-bar"><span style="width:<?= (int)$p['pct'] ?>%"></span></span><small><?= (int)$p['done'] ?>/<?= count($p['courses']) ?> courses</small>
                <?php else: ?><small><i class="fas fa-award"></i> Path badge + certificate</small><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <section class="lr-editor-promo">
          <div>
            <h2>Code editor</h2>
            <p>Write and run code in your browser – no installs. HTML, CSS and JavaScript render live; Python runs real Python 3; SQL runs against our Skope Shop sample database.</p>
          </div>
          <div class="lr-editor-langs">
            <a href="tryit?lang=html"><i class="fab fa-html5"></i> HTML</a>
            <a href="tryit?lang=javascript"><i class="fab fa-js"></i> JavaScript</a>
            <a href="tryit?lang=python"><i class="fab fa-python"></i> Python</a>
            <a href="tryit?lang=sql"><i class="fas fa-database"></i> SQL</a>
          </div>
        </section>

        <?php if ($previews): ?>
        <h2 class="lr-section-title">Full courses with free previews</h2>
        <div class="lr-grid">
          <?php foreach ($previews as $c): $from = course_from_price($c); ?>
          <a class="lr-card" href="<?= e(learn_url($c)) ?>">
            <span class="lr-pill preview"><i class="fas fa-eye"></i> <?= (int)$c['preview_count'] ?> free preview<?= $c['preview_count'] == 1 ? '' : 's' ?></span>
            <h3><?= e($c['title']) ?></h3>
            <p><?= e(mb_strimwidth(strip_tags((string)$c['description']), 0, 130, '…')) ?></p>
            <div class="lr-card-foot"><span><?= (int)$c['lesson_count'] ?> lessons</span><strong><?= $from > 0 ? 'From ' . e(money($from)) : 'Free' ?></strong></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
    </body></html>
    <?php
    exit;
}

/* ── Reader ───────────────────────────────────────────────────────────── */
$is_staff = $user && $course && ($user['role'] === 'admin' || ($user['role'] === 'tutor' && (int)$course['tutor_id'] === (int)$user['id']));
if (!$course || ($course['status'] !== 'published' && !$is_staff)) {
    http_response_code(404);
    $pageTitle = 'Tutorial not found';
    include 'includes/learn-top.php';
    echo '<main class="lr-index"><h1>Tutorial not found</h1><p><a href="learn">Back to tutorials</a></p></main></body></html>';
    exit;
}
$course_id = (int)$course['id'];

// Programming stays in /learn; other skills courses open in the Learning Lab (/lab). Fix the address if needed.
$isLab = is_lab_course($course);
$root = $isLab ? 'lab' : 'learn';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['c']) && (!empty($_GET['lab']) !== $isLab)) {
    $path = $root . '/' . $course['slug']
          . (!empty($_GET['l']) && preg_match('/^[a-z0-9-]+$/', $_GET['l']) ? '/' . $_GET['l'] : '')
          . (in_array($_GET['view'] ?? '', ['leaderboard', 'project'], true) ? '/' . $_GET['view'] : '');
    $keep = array_diff_key($_GET, ['c' => 1, 'l' => 1, 'lab' => 1, 'view' => 1]);
    header('Location: ' . sda_base_href() . $path . ($keep ? '?' . http_build_query($keep) : ''), true, 301);
    exit;
}

$student_id = ($user && $user['role'] === 'student') ? (int)$user['id'] : null;
$enrollment = $student_id ? get_enrollment($pdo, $student_id, $course_id) : null;
$enrolled = $enrollment && in_array($enrollment['status'], ['active', 'completed'], true);
$lessons = load_course_lessons($pdo, $course_id, $student_id);
if ($enrolled) $lessons = apply_lesson_schedule($lessons, $enrollment);

foreach ($lessons as &$l) {
    if ($is_staff) { $l['can_read'] = true; $l['why'] = ''; continue; }
    [$ok, $why] = reader_can_view($course, $l, $enrollment);
    if ($ok && $enrolled && !empty($l['is_locked']) && empty($course['is_open_tutorial']) && empty($l['is_preview'])) {
        $ok = false;
        $why = $l['lock_reason'];
    }
    $l['can_read'] = $ok;
    $l['why'] = $ok ? '' : $why;
}
unset($l);

// Active lesson: ?l=slug, ?lesson=id, else the first readable one.
$active = null; $idx = 0;
$wantSlug = $_GET['l'] ?? null;
$wantId = (int)($_GET['lesson'] ?? 0);
foreach ($lessons as $i => $l) {
    if (($wantSlug !== null && $l['slug'] === $wantSlug) || ($wantId && (int)$l['id'] === $wantId)) { $active = $l; $idx = $i; break; }
}
if (!$active && $wantSlug !== null && $lessons) { http_response_code(404); }
if (!$active && $lessons) {
    foreach ($lessons as $i => $l) { if ($l['can_read']) { $active = $l; $idx = $i; break; } }
    if (!$active) { $active = $lessons[0]; $idx = 0; }
}
$prev = ($active && $idx > 0) ? $lessons[$idx - 1] : null;
$next = ($active && $idx < count($lessons) - 1) ? $lessons[$idx + 1] : null;
// Every lesson can be read, but completing it (and moving on) needs the previous lesson done and this lesson's tests passed.
$blockReason = ($student_id && $active && ($active['progress_status'] ?? '') !== 'completed')
    ? lesson_completion_block($pdo, (int)$student_id, $lessons, (int)$active['id']) : null;
$prevDone = !$prev || ($prev['progress_status'] ?? '') === 'completed';

// Old query-string links to a tutorial that has pretty URLs: send them to the clean address.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['course']) && !empty($course['slug']) && preg_match('/^[a-z0-9-]+$/', $course['slug'])
    && (!$active || preg_match('/^[a-z0-9-]+$/', (string)$active['slug']))) {
    $keep = array_diff_key($_GET, ['course' => 1, 'lesson' => 1]);
    header('Location: ' . sda_base_href() . learn_url($course, $active, http_build_query($keep)), true, 301);
    exit;
}

$free_self_paced = in_array('self_paced', course_modes($course), true) && course_price($course, 'self_paced') <= 0;
$here = $active ? learn_url($course, $active) : learn_url($course);

$view = in_array($_GET['view'] ?? '', ['leaderboard', 'project'], true) ? $_GET['view'] : 'lesson';
$project = project_for_course($pdo, $course_id);
$projUrl = $root . '/' . $course['slug'] . '/project';

/* POST: course project (submit, review, flag) */
if ($view === 'project' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'includes/project_post.php';
    exit;
}

/* POST: mark complete / start tracking a free tutorial */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$student_id || !verifyCsrf()) { header('Location: ' . sda_base_href() . 'login'); exit; }
    if (isset($_POST['track']) && !$enrolled && $free_self_paced) {
        enroll_student($pdo, $student_id, $course, 'self_paced', null, 0);
        header('Location: ' . sda_base_href() . learn_url($course, $active, 'tracking=1'));
        exit;
    }
    if (isset($_POST['complete']) && $blockReason !== null) {
        header('Location: ' . sda_base_href() . learn_url($course, $active, 'blocked=1') . '#lrGate');
        exit;
    }
    if (isset($_POST['complete']) && $enrolled && $active && $active['can_read'] && empty($active['is_locked'])) {
        complete_lesson($pdo, $enrollment, (int)$active['id']);
        header('Location: ' . sda_base_href() . ($next ? learn_url($course, $next) : learn_url($course, $active, 'finished=1')));
        exit;
    }
    header('Location: ' . sda_base_href() . $here);
    exit;
}

$done = count(array_filter($lessons, fn($l) => ($l['progress_status'] ?? '') === 'completed'));
$pct = $lessons ? round($done / count($lessons) * 100) : 0;
$embed = $active ? video_embed_url($active['video_url'] ?: null) : null;
$bySlug = [];
foreach ($lessons as $l) if (!empty($l['slug'])) $bySlug[$l['slug']] = $l;
$ctx = [
    'lesson_id' => (int)($active['id'] ?? 0),
    'link' => fn($slug) => isset($bySlug[$slug]) ? learn_url($course, $bySlug[$slug]) : learn_url($course),
];
$isLibrary = !empty($active['source_key']);

// Learning engine data for signed-in students: level, rank, certificate, review, queued celebrations.
$meLevel = null; $myRank = null; $board = []; $myCert = null; $myReview = null; $flash = null;
if ($student_id) {
    $x = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $x->execute([$student_id]);
    $meLevel = xp_level((int)$x->fetchColumn());
    $flash = progress_flash_take();
    $c = $pdo->prepare("SELECT verification_code, status, fee FROM certificates WHERE student_id = ? AND course_id = ? AND certificate_type = 'course' AND status IN ('approved','pending') LIMIT 1");
    $c->execute([$student_id, $course_id]);
    $myCertRow = $c->fetch() ?: null;
    $myCert = $myCertRow['verification_code'] ?? null;
    $r = $pdo->prepare("SELECT rating, review FROM course_ratings WHERE student_id = ? AND course_id = ?");
    $r->execute([$student_id, $course_id]);
    $myReview = $r->fetch() ?: null;
}
$board = course_leaderboard($pdo, $course_id, ($view === 'leaderboard' && ($_GET['period'] ?? '') === 'week') ? 'week' : 'all', $view === 'leaderboard' ? 50 : 5);
if ($student_id && $enrolled) $myRank = user_rank_in_course($pdo, $course_id, $student_id);
$courseDone = $enrolled && $enrollment['status'] === 'completed';
$canReview = $enrolled && ($courseDone || $done >= max(1, (int)ceil(count($lessons) / 3)));
$ratingStats = $pdo->prepare("SELECT ROUND(AVG(rating), 1) avg_r, COUNT(*) n FROM course_ratings WHERE course_id = ?");
$ratingStats->execute([$course_id]);
$ratingStats = $ratingStats->fetch();
$track = ($student_id && $active && $active['can_read'] && $view === 'lesson' && ($enrolled || $free_self_paced)) ? [
    'endpoint' => 'api/track', 'csrf' => csrfToken(), 'lesson' => (int)$active['id'],
    'minSecs' => max(20, min(120, (int)round((int)($active['duration_mins'] ?: 5) * 60 * 0.3))),
    'completed' => ($active['progress_status'] ?? '') === 'completed', 'next' => $next ? learn_url($course, $next) : null,
    'blocked' => $blockReason !== null, 'prevDone' => $prevDone,
] : null;
$tutName = $course['tutorial_short'] ?: $course['title'];
// Course project: learner state (chip in the sidebar, project page).
$pstate = ($project && $student_id && $enrolled) ? project_learner_state($pdo, $project, $student_id) : null;
if ($view === 'project') project_sweep($pdo);
$pageTitle = $view === 'project' ? 'Course project – ' . $course['title'] : ($active ? $active['title'] . ' – ' . ($course['source'] === 'tutorial' ? $tutName . ' Tutorial' : $course['title']) : $course['title']);
$metaDescription = $course['description'] ? mb_strimwidth($course['description'], 0, 155, '…') : null;
$activeTab = $course_id;
if ($isLab) {   // Learning Lab layout for non-programming courses (same data and engine)
    $labMode = 'course';
    include 'includes/lab_view.php';
    exit;
}
include 'includes/learn-top.php';
?>
<div class="lr-wrap">
  <nav class="lr-side" id="lrSide" aria-label="Lessons">
    <h2><?= e($course['title']) ?></h2>
    <div class="lr-side-meta">
      <?= count($lessons) ?> lessons<?= $course['category_name'] ? ' · ' . e($course['category_name']) : '' ?>
      <?php if ($enrolled): ?> · <span class="lr-pill <?= $enrollment['learning_mode'] === 'structured' ? 'struct' : 'self' ?>"><?= e(mode_label($enrollment['learning_mode'])) ?></span><?php endif; ?>
    </div>
    <?php if ($enrolled): ?>
      <div class="lr-progress" title="<?= $pct ?>% complete"><span style="width:<?= $pct ?>%"></span></div>
    <?php endif; ?>
    <?php $wk = null; foreach ($lessons as $l):
        if ($wk !== (int)$l['week_num']) {
            $wk = (int)$l['week_num'];
            $label = $l['module_title'] ?: (($enrolled && $enrollment['learning_mode'] === 'structured' ? 'Week ' : 'Module ') . $wk);
            echo '<div class="lr-module">' . e($label) . '</div>';
        }
        $icon = ($l['progress_status'] ?? '') === 'completed' ? 'fa-circle-check' : ($l['can_read'] ? '' : 'fa-lock');
    ?>
      <a class="lr-item<?= $view === 'lesson' && $active && $l['id'] == $active['id'] ? ' on' : '' ?><?= $l['can_read'] ? '' : ' locked' ?>" href="<?= e(learn_url($course, $l)) ?>" <?= $l['can_read'] ? '' : 'title="' . e($l['why']) . '"' ?><?= $active && $l['id'] == $active['id'] ? ' aria-current="page"' : '' ?>>
        <span><?= e($l['title']) ?><?= !empty($l['is_preview']) && empty($course['is_open_tutorial']) ? ' <span class="lr-pill preview">Preview</span>' : '' ?></span>
        <?php if ($icon): ?><i class="fas <?= $icon ?>"></i><?php endif; ?>
      </a>
    <?php endforeach; ?>

    <?php if ($project): $chip = $pstate['chip'] ?? ['Peer graded', '']; ?>
      <div class="lr-module"><i class="fas fa-diagram-project"></i> Final project</div>
      <a class="lr-item pj-side<?= $view === 'project' ? ' on' : '' ?>" href="<?= e($projUrl) ?>"<?= $view === 'project' ? ' aria-current="page"' : '' ?>><span>Course project</span><span class="pj-chip <?= e($chip[1]) ?>"><?= e($chip[0]) ?></span></a>
    <?php endif; ?>

    <section class="lr-mini-board" aria-label="Leaderboard">
      <div class="lr-module"><i class="fas fa-ranking-star"></i> Leaderboard</div>
      <?php if (!$board): ?>
        <p class="lr-mini-empty"><?= $user ? 'Be the first on the board – complete a lesson to earn XP.' : '<a href="login">Sign in</a> to join the leaderboard.' ?></p>
      <?php else: ?>
        <ol>
          <?php foreach ($board as $b): $me = $student_id && (int)$b['id'] === $student_id; ?>
            <li class="<?= $me ? 'me' : '' ?>"><span class="rk rk<?= min(4, $b['rank']) ?>"><?= $b['rank'] ?></span><span class="nm"><?= e($me ? 'You' : $b['display_name']) ?></span><span class="px"><?= number_format((int)$b['course_xp']) ?> XP</span></li>
          <?php endforeach; ?>
          <?php if ($myRank && $myRank['rank'] > count($board)): ?>
            <li class="me gap"><span class="rk rk4"><?= $myRank['rank'] ?></span><span class="nm">You</span><span class="px"><?= number_format((int)$myRank['course_xp']) ?> XP</span></li>
          <?php endif; ?>
        </ol>
      <?php endif; ?>
      <a class="lr-mini-link" href="learn/<?= e($course['slug']) ?>/leaderboard">See full leaderboard <i class="fas fa-arrow-right"></i></a>
    </section>
  </nav>

  <main class="lr-main">
    <div class="lr-crumbs"><a href="learn">Tutorials</a> › <a href="<?= e(learn_url($course)) ?>"><?= e($course['title']) ?></a><?= $view === 'leaderboard' ? ' › Leaderboard' : ($view === 'project' ? ' › Course project' : ($active ? ' › ' . e($active['title']) : '')) ?></div>
    <?php if ($view === 'leaderboard'): $period = ($_GET['period'] ?? '') === 'week' ? 'week' : 'all'; ?>
      <h1><i class="fas fa-ranking-star" style="color:#f59e0b"></i> Leaderboard</h1>
      <p class="lr-muted" style="margin-top:-8px">Everyone learning <strong><?= e($course['title']) ?></strong>, ranked by XP earned in this course. Earn XP by reading lessons, solving exercises and passing quizzes.</p>
      <div class="lb-tabs" role="tablist">
        <a role="tab" aria-selected="<?= $period === 'all' ? 'true' : 'false' ?>" class="<?= $period === 'all' ? 'on' : '' ?>" href="learn/<?= e($course['slug']) ?>/leaderboard">All time</a>
        <a role="tab" aria-selected="<?= $period === 'week' ? 'true' : 'false' ?>" class="<?= $period === 'week' ? 'on' : '' ?>" href="learn/<?= e($course['slug']) ?>/leaderboard?period=week">This week</a>
      </div>
      <?php if (!$user): ?>
        <div class="lr-notice info"><i class="fas fa-user-lock"></i><div><strong>Sign in to see the leaderboard.</strong><a href="login">Sign in</a> or <a href="register">create a free account</a> to compete with other learners.</div></div>
      <?php else: ?>
        <?php if ($myRank): $mine = $period === 'week' ? null : $myRank; ?>
          <div class="lb-me">
            <div class="lb-me-rank">#<?= (int)$myRank['rank'] ?></div>
            <div><strong>Your position</strong><span><?= number_format((int)$myRank['course_xp']) ?> XP · <?= (int)$myRank['lessons_done'] ?> lessons · <?= (int)round($myRank['progress_percent']) ?>% complete</span></div>
            <?php if ($myRank['rank'] > 1 && isset($board[$myRank['rank'] - 2]) && $period === 'all'): $ahead = $board[$myRank['rank'] - 2]; ?>
              <div class="lb-me-next"><?= number_format(max(1, (int)$ahead['course_xp'] - (int)$myRank['course_xp'] + 1)) ?> XP to pass <?= e($ahead['display_name']) ?></div>
            <?php endif; ?>
          </div>
        <?php elseif ($student_id): ?>
          <div class="lr-notice info"><i class="fas fa-flag-checkered"></i><div><strong>You're not on this board yet.</strong>Open any lesson in this course – your progress is tracked automatically.</div></div>
        <?php endif; ?>
        <?php if (!$board): ?>
          <p class="lr-muted">No activity <?= $period === 'week' ? 'this week' : 'yet' ?>. Start a lesson to claim the top spot!</p>
        <?php else: ?>
          <div class="lb-table" role="table">
            <div class="lb-row lb-head" role="row"><span role="columnheader">Rank</span><span role="columnheader">Learner</span><span role="columnheader">Progress</span><span role="columnheader">Lessons</span><span role="columnheader">XP</span></div>
            <?php foreach ($board as $b): $me = $student_id && (int)$b['id'] === $student_id; ?>
              <div class="lb-row<?= $me ? ' me' : '' ?>" role="row">
                <span class="lb-rank" role="cell"><?php if ($b['rank'] <= 3): ?><i class="fas fa-medal m<?= $b['rank'] ?>" title="#<?= $b['rank'] ?>"></i><?php else: ?>#<?= $b['rank'] ?><?php endif; ?></span>
                <span class="lb-who" role="cell"><span class="av"><?= e(mb_strtoupper(mb_substr($b['display_name'], 0, 1))) ?></span><?= e($me ? $b['display_name'] . ' (you)' : $b['display_name']) ?><?= $b['status'] === 'completed' ? ' <i class="fas fa-certificate done" title="Completed the course"></i>' : '' ?></span>
                <span role="cell"><span class="lb-bar"><span style="width:<?= (int)round($b['progress_percent']) ?>%"></span></span><small><?= (int)round($b['progress_percent']) ?>%</small></span>
                <span role="cell"><?= (int)$b['lessons_done'] ?></span>
                <span class="lb-xp" role="cell"><?= number_format((int)$b['course_xp']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="lr-muted" style="font-size:.82rem;margin-top:12px">Names are shown as first name and last initial. You can hide yourself from leaderboards on your achievements page.</p>
        <?php endif; ?>
      <?php endif; ?>
    <?php elseif ($view === 'project'): ?>
      <?php include 'includes/project_view.php'; ?>
    <?php elseif (!$active): ?>
      <h1><?= e($course['title']) ?></h1>
      <p>Lessons for this tutorial are on the way.</p>
    <?php else: ?>
      <h1><?= e($active['title']) ?></h1>
      <?php if ($active['duration_mins']): ?><div class="lr-lesson-meta"><i class="far fa-clock"></i> <?= (int)$active['duration_mins'] ?> min read<?= $isLibrary ? ' · Lesson ' . ($idx + 1) . ' of ' . count($lessons) : '' ?></div><?php endif; ?>

      <?php if (isset($_GET['tracking'])): ?>
        <div class="lr-notice ok"><i class="fas fa-circle-check"></i><div><strong>Progress tracking is on.</strong>Mark lessons complete as you go; your progress shows on your dashboard.</div></div>
      <?php endif; ?>
      <?php if ($myCert && (isset($_GET['finished']) || !$next)): ?>
        <div class="lr-cert-banner">
          <i class="fas fa-award"></i>
          <?php if ($myCertRow['status'] === 'pending'): ?>
            <div><strong>You completed <?= e($course['title']) ?>!</strong><span>Your certificate has been generated. Unlock it for <?= e(money((float)$myCertRow['fee'])) ?> to download and share it.</span></div>
            <a class="lr-btn lr-btn-accent" href="certificate-pay?code=<?= e(rawurlencode($myCert)) ?>"><i class="fas fa-lock-open"></i> Unlock certificate</a>
          <?php else: ?>
            <div><strong>You completed <?= e($course['title']) ?>!</strong><span>Your certificate is ready to view, download and share.</span></div>
            <a class="lr-btn lr-btn-accent" href="certificate?code=<?= e(rawurlencode($myCert)) ?>" target="_blank" rel="noopener"><i class="fas fa-certificate"></i> View certificate</a>
          <?php endif; ?>
        </div>
      <?php elseif ($enrolled && !$courseDone && $done === count($lessons) && count($lessons) > 0 && $project && $pstate && (!course_has_quiz($pdo, $course_id) || (int)best_quiz_pct($pdo, $student_id, $course_id) >= QUIZ_PASS_PCT)): ?>
        <div class="lr-notice info"><i class="fas fa-diagram-project"></i><div><strong>Almost there!</strong>You've finished every lesson<?= course_has_quiz($pdo, $course_id) ? ' and passed the quiz' : '' ?>. Pass the <a href="<?= e($projUrl) ?>">course project</a> to complete the course and earn your certificate.</div></div>
      <?php elseif ($enrolled && !$courseDone && $done === count($lessons) && count($lessons) > 0 && course_has_quiz($pdo, $course_id)): ?>
        <div class="lr-notice info"><i class="fas fa-flag-checkered"></i><div><strong>Almost there!</strong>You've finished every lesson. Score <?= QUIZ_PASS_PCT ?>% or more on the final quiz to earn your certificate.</div></div>
      <?php elseif (isset($_GET['finished'])): ?>
        <div class="lr-notice ok"><i class="fas fa-trophy"></i><div><strong>You've reached the end of the <?= e($course['title']) ?>.</strong>Well done! Pick your next tutorial from the bar above.</div></div>
      <?php endif; ?>

      <div class="lr-nav">
        <?php if ($prev): ?><a class="lr-btn lr-btn-primary" href="<?= e(learn_url($course, $prev)) ?>"><i class="fas fa-chevron-left"></i> Previous</a><?php else: ?><span></span><?php endif; ?>
        <?php if ($next): ?><a class="lr-btn lr-btn-primary" href="<?= e(learn_url($course, $next)) ?>">Next <i class="fas fa-chevron-right"></i></a><?php endif; ?>
      </div>

      <?php if (!$active['can_read']): ?>
        <div class="lr-notice lock"><i class="fas fa-lock"></i><div>
          <strong><?= e($active['why']) ?></strong>
          <?php if (!$enrolled): ?>
            <?php if (course_from_price($course) <= 0): ?>
              This course is <b>free</b>. <?= $user ? 'Enroll' : 'Create a free account' ?> and choose self-paced or a structured weekly track to unlock every lesson, quizzes and your certificate.
              <div style="margin-top:12px"><a class="lr-btn lr-btn-accent" href="<?= $user ? 'enroll?id=' . $course_id : 'register' ?>"><?= $user ? 'Start learning free' : 'Register free' ?></a><?php if (!$user): ?> <a class="lr-btn lr-btn-ghost" href="login">Sign in</a><?php endif; ?></div>
            <?php else: ?>
              Choose a self-paced or structured track to unlock every lesson, quizzes and a certificate.
              <div style="margin-top:12px"><a class="lr-btn lr-btn-accent" href="enroll?id=<?= $course_id ?>">See enrollment options</a></div>
            <?php endif; ?>
          <?php elseif ($enrollment['learning_mode'] === 'structured'): ?>
            You're on the structured track, so lessons open week by week. <a href="student/classroom?id=<?= $course_id ?>">Open your classroom schedule</a>.
          <?php endif; ?>
        </div></div>
      <?php else: ?>
        <?php if ($embed): ?>
          <div class="lr-video"><iframe src="<?= e($embed) ?>" title="<?= e($active['title']) ?> video" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div>
        <?php endif; ?>
        <article class="lr-content"><?= $isLibrary ? render_lesson((string)$active['content'], $ctx) : render_lesson_text($active['content']) ?></article>

        <?php if (!$isLibrary && !empty($active['code_example'])): ?>
          <section class="lr-example">
            <div class="lr-ex-head"><i class="fas fa-code"></i> Example</div>
            <pre class="lr-code"><code class="<?= e(lr_prism(lr_lang((string)$active['code_lang']))) ?>"><?= e($active['code_example']) ?></code></pre>
            <a class="lr-btn lr-btn-ok" href="tryit?lesson=<?= (int)$active['id'] ?>" target="_blank" rel="noopener"><?= tryit_runnable($active['code_lang']) || in_array($active['code_lang'], ['python', 'sql'], true) ? 'Try it Yourself &raquo;' : 'Open in editor' ?></a>
          </section>
        <?php endif; ?>

        <?php if (!empty($active['pdf_resource']) || ($active['lesson_type'] === 'pdf' && !empty($active['file_url']))): ?>
          <p><a class="lr-btn lr-btn-ghost" href="uploads/lessons/<?= e(basename($active['pdf_resource'] ?: $active['file_url'])) ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Lesson PDF</a></p>
        <?php endif; ?>

        <div class="lr-done-row">
          <?php if ($enrolled): ?>
            <?php if (($active['progress_status'] ?? '') === 'completed'): ?>
              <span class="lr-pill free"><i class="fas fa-check"></i> Completed</span>
            <?php else: ?>
              <?php if ($blockReason !== null): ?>
                <div class="lr-gate" id="lrGate" role="status"><i class="fas fa-lock"></i><span><?= e($blockReason) ?></span></div>
              <?php endif; ?>
              <form method="post" action="<?= e($here) ?>" id="lrCompleteForm"<?= $blockReason !== null ? ' hidden' : '' ?>><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button class="lr-btn lr-btn-ok" name="complete" value="1"><i class="fas fa-check"></i> Mark complete<?= $next ? ' &amp; continue' : '' ?></button></form>
            <?php endif; ?>
            <?php if (!empty($active['attached_quiz_id'])): ?>
              <a class="lr-btn lr-btn-ghost" href="student/take-quiz?id=<?= (int)$active['attached_quiz_id'] ?>"><i class="fas fa-circle-question"></i> Lesson quiz</a>
            <?php endif; ?>
            <?php if (!empty($active['due_at'])): ?><span class="lr-muted">Due <?= date('D, M j', $active['due_at']) ?></span><?php endif; ?>
          <?php elseif ($student_id && $free_self_paced): ?>
            <span class="lr-pill self"><i class="fas fa-satellite-dish"></i> Your progress is saved automatically</span>
            <?php if ($blockReason !== null): ?><div class="lr-gate" id="lrGate" role="status"><i class="fas fa-lock"></i><span><?= e($blockReason) ?></span></div><?php endif; ?>
          <?php elseif (!$user): ?>
            <span class="lr-muted"><a href="login">Sign in</a> or <a href="register">create a free account</a> to track progress, earn XP, badges and a certificate.</span>
          <?php elseif (!$enrolled && !$is_staff && !$course['is_open_tutorial']): ?>
            <a class="lr-btn lr-btn-accent" href="enroll?id=<?= $course_id ?>">Enroll for quizzes &amp; certificate</a>
          <?php endif; ?>
        </div>

        <?php if ($project && !$next): $pc = $pstate['chip'] ?? null; ?>
          <aside class="pj-callout">
            <i class="fas fa-diagram-project"></i>
            <div><strong>Final step: the course project</strong><span><?= e($project['title']) ?> – submit your work, review <?= (int)$project['reviews_required'] ?> classmates and score <?= (int)$project['pass_pct'] ?>% or more to complete the course and earn your certificate.</span>
              <?php if ($pc): ?><span class="pj-chip <?= e($pc[1]) ?>" style="margin-top:6px"><?= e($pc[0]) ?></span><?php endif; ?></div>
            <a class="lr-btn lr-btn-accent" href="<?= e($projUrl) ?>">Open the project <i class="fas fa-arrow-right"></i></a>
          </aside>
        <?php endif; ?>

        <?php if ($canReview && (!$next || $courseDone || isset($_GET['review']))): ?>
          <section class="lr-review" id="review" data-course="<?= $course_id ?>" data-csrf="<?= e(csrfToken()) ?>">
            <h3><?= $myReview ? 'Your review' : 'How was this course?' ?></h3>
            <p class="lr-muted"><?= $ratingStats['n'] ? 'Rated <strong>' . e($ratingStats['avg_r']) . ' / 5</strong> by ' . (int)$ratingStats['n'] . ' learner' . ((int)$ratingStats['n'] === 1 ? '' : 's') . '.' : 'Be the first to rate it.' ?> Your feedback helps other learners choose.</p>
            <div class="lr-stars" role="radiogroup" aria-label="Your rating">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <button type="button" role="radio" data-v="<?= $s ?>" aria-checked="<?= (int)($myReview['rating'] ?? 0) === $s ? 'true' : 'false' ?>" aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>" class="<?= $s <= (int)($myReview['rating'] ?? 0) ? 'on' : '' ?>"><i class="fas fa-star"></i></button>
              <?php endfor; ?>
            </div>
            <textarea maxlength="1500" rows="3" placeholder="What did you like? What could be better? (optional)" aria-label="Your review"><?= e($myReview['review'] ?? '') ?></textarea>
            <div class="lr-ex-actions"><button type="button" class="lr-btn lr-btn-primary" data-act="save-review"><?= $myReview ? 'Update review' : 'Submit review' ?></button><span class="lr-ex-result" role="status"></span></div>
          </section>
        <?php endif; ?>
      <?php endif; ?>

      <div class="lr-nav lr-nav-bottom">
        <?php if ($prev): ?><a class="lr-btn lr-btn-primary" href="<?= e(learn_url($course, $prev)) ?>"><i class="fas fa-chevron-left"></i> <span><small>Previous</small><?= e($prev['title']) ?></span></a><?php else: ?><span></span><?php endif; ?>
        <?php if ($next): ?><a class="lr-btn lr-btn-primary" href="<?= e(learn_url($course, $next)) ?>"><span><small>Next</small><?= e($next['title']) ?></span> <i class="fas fa-chevron-right"></i></a><?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
<div class="lr-celebrate" id="celebrate" hidden></div>
<div class="lr-xp-toast" id="xpToast" role="status" aria-live="polite"></div>
<script>window.SDA_TRACK = <?= json_encode($track, JSON_HEX_TAG | JSON_HEX_AMP) ?>; window.SDA_FLASH = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP) ?>; window.SDA_LEVEL = <?= json_encode($meLevel) ?>;</script>
<script src="assets/js/learn.js?v=9"></script>
</body>
</html>
