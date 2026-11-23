<?php
/**
 * Tutor dashboard. Every figure comes from includes/tutor_data.php (no invented numbers):
 * learners/enrolments → td_learner_summary(), courses/lessons → td_courses(), earnings → td_month_compare(),
 * grading queue → td_pending_submissions(), schedule → td_upcoming(), reviews → td_recent_reviews(),
 * messages → td_unread_messages().
 */
$pageTitle = 'Dashboard';
$hideHead = true;
require_once 'includes/header.php';

try {
    $sum      = td_learner_summary($pdo, $tid);
    $courses  = td_courses($pdo, $tid);
    $money    = td_month_compare($pdo, $tid);
    $grading  = td_pending_submissions($pdo, $tid, 6);
    $upcoming = td_upcoming($pdo, $tid, 14);
    $recent   = td_enrolments($pdo, $tid, ['statuses' => ['active', 'completed', 'pending'], 'limit' => 6]);
    $reviews  = td_recent_reviews($pdo, $tid, 4);
    $inbox    = td_unread_messages($pdo, $tid, 4);
    $nextCohort = $upcoming ? null : td_next_cohort($pdo, $tid);
} catch (Throwable $e) {
    error_log('tutor dashboard: ' . $e->getMessage());
    $sum = ['learners' => 0, 'enrolments' => 0, 'active' => 0, 'completed' => 0, 'pending' => 0, 'orphaned' => 0, 'avg_progress' => null];
    $courses = $grading = $upcoming = $recent = $reviews = [];
    $money = ['this' => ['share' => 0, 'gross' => 0, 'payments' => 0], 'last' => ['share' => 0], 'change' => null];
    $inbox = ['count' => 0, 'items' => []];
    $nextCohort = null;
}

$live    = count(array_filter($courses, fn($c) => $c['status'] === 'published'));
$drafts  = count(array_filter($courses, fn($c) => $c['status'] === 'draft'));
$review  = count(array_filter($courses, fn($c) => $c['status'] === 'pending'));
$lessons = array_sum(array_map(fn($c) => (int)$c['lesson_count'], $courses));
$gradingCount = $navCounts['grading'];
$firstCourse = $courses[0]['id'] ?? null;
$soloCourse  = count($courses) === 1 ? (int)$firstCourse : null;

$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = explode(' ', trim($tutor['name'] ?: 'Tutor'))[0];

// One plain summary line built from real counts
$todo = [];
if ($gradingCount) $todo[] = '<b>' . $gradingCount . '</b> submission' . ($gradingCount === 1 ? '' : 's') . ' to grade';
if ($inbox['count']) $todo[] = '<b>' . $inbox['count'] . '</b> unread message' . ($inbox['count'] === 1 ? '' : 's');
if ($upcoming) $todo[] = '<b>' . count($upcoming) . '</b> thing' . (count($upcoming) === 1 ? '' : 's') . ' coming up in the next 14 days';
$learnerLine = $sum['learners'] === 1 ? '1 learner' : $sum['learners'] . ' learners';
$courseLine  = $live === 1 ? '1 live course' : $live . ' live courses';
$summary = 'You teach <b>' . $learnerLine . '</b> across <b>' . $courseLine . '</b>. '
         . ($todo ? 'Today: ' . implode(' · ', $todo) . '.' : 'Nothing needs your attention right now.');

function tp_stars(float $r): string {
    $out = '<span class="tp-stars" aria-label="' . e(number_format($r, 1)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) $out .= $i <= round($r) ? '★' : '<span class="off">★</span>';
    return $out . '</span>';
}
?>
<section class="tp-hero" aria-label="Summary">
    <div>
        <span class="tp-hero-date"><i class="far fa-calendar"></i> <?= e(date('l, j F Y')) ?></span>
        <h1><?= e($greet) ?>, <?= e($first) ?></h1>
        <p><?= $summary ?></p>
    </div>
    <div class="tp-actions">
        <a class="tp-btn primary" href="courses?action=new"><i class="fas fa-plus"></i> New course</a>
        <a class="tp-btn" href="messages"><i class="fas fa-comments"></i> Message learners</a>
    </div>
</section>

<div class="tp-section-label">Key numbers</div>
<div class="tp-stats">
    <a class="tp-stat" href="students">
        <span class="tp-stat-icon"><i class="fas fa-user-graduate"></i></span>
        <div>
            <div class="tp-stat-value"><?= number_format($sum['learners']) ?></div>
            <div class="tp-stat-label">Learners</div>
            <div class="tp-stat-sub"><?= $sum['active'] ?> active · <?= $sum['completed'] ?> completed<?= $sum['pending'] ? ' · ' . $sum['pending'] . ' pending' : '' ?></div>
        </div>
    </a>
    <a class="tp-stat" href="courses">
        <span class="tp-stat-icon violet"><i class="fas fa-book-open"></i></span>
        <div>
            <div class="tp-stat-value"><?= $live ?></div>
            <div class="tp-stat-label">Live courses</div>
            <div class="tp-stat-sub"><?= count($courses) ?> in total<?= $drafts ? ' · ' . $drafts . ' draft' . ($drafts === 1 ? '' : 's') : '' ?><?= $review ? ' · ' . $review . ' in review' : '' ?></div>
        </div>
    </a>
    <a class="tp-stat" href="lessons">
        <span class="tp-stat-icon accent"><i class="fas fa-layer-group"></i></span>
        <div>
            <div class="tp-stat-value"><?= number_format($lessons) ?></div>
            <div class="tp-stat-label">Lessons</div>
            <div class="tp-stat-sub">Across all your courses</div>
        </div>
    </a>
    <a class="tp-stat" href="analytics">
        <span class="tp-stat-icon ok"><i class="fas fa-chart-line"></i></span>
        <div>
            <div class="tp-stat-value"><?= $sum['avg_progress'] === null ? '—' : round($sum['avg_progress']) . '%' ?></div>
            <div class="tp-stat-label">Average completion</div>
            <div class="tp-stat-sub"><?= $sum['avg_progress'] === null ? 'Shows once you have learners' : 'Across ' . $sum['enrolments'] . ' enrolment' . ($sum['enrolments'] === 1 ? '' : 's') ?></div>
        </div>
    </a>
    <a class="tp-stat" href="revenue">
        <span class="tp-stat-icon warn"><i class="fas fa-wallet"></i></span>
        <div>
            <div class="tp-stat-value"><?= e(td_money($money['this']['share'])) ?></div>
            <div class="tp-stat-label">Earned in <?= e(date('F')) ?></div>
            <div class="tp-stat-sub">
                <?php if ($money['change'] !== null): $up = $money['change'] >= 0; ?>
                    <span class="tp-trend <?= $up ? 'up' : 'down' ?>"><i class="fas fa-arrow-<?= $up ? 'up' : 'down' ?>"></i><?= e(number_format(abs($money['change']), 0)) ?>%</span> vs <?= e(td_money($money['last']['share'])) ?> last month
                <?php else: ?>
                    Your <?= e(td_share_pct()) ?>% share · <?= $money['last']['share'] > 0 ? '' : 'nothing earned last month' ?>
                <?php endif; ?>
            </div>
        </div>
    </a>
</div>
<?php if ($note = td_orphan_note($sum)): ?>
    <p class="tp-note" style="margin-top:10px"><i class="fas fa-circle-info"></i> <?= e($note) ?></p>
<?php endif; ?>

<div class="tp-grid main-side" style="margin-top:22px">
    <div class="tp-stack">
        <!-- Needs grading -->
        <section class="tp-card">
            <div class="tp-card-head">
                <div>
                    <h2><i class="fas fa-clipboard-check"></i> Needs grading</h2>
                    <p>Assignment submissions waiting for a score, oldest first.</p>
                </div>
                <?php if ($gradingCount): ?><span class="tp-pill warn"><?= $gradingCount ?> waiting</span><?php endif; ?>
            </div>
            <?php if ($grading): ?>
                <ul class="tp-list">
                    <?php foreach ($grading as $s): $late = $s['due_date'] && strtotime($s['submitted_at']) > strtotime($s['due_date']); ?>
                        <li><a class="tp-row" href="assignments?grade=<?= (int)$s['id'] ?>">
                            <span class="tp-row-icon warn"><i class="fas fa-file-lines"></i></span>
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= e($s['student_name']) ?> · <?= e($s['assignment_title']) ?></span>
                                <span class="tp-row-meta"><?= e($s['course_title']) ?> · submitted <?= e(td_ago($s['submitted_at'])) ?><?= $late ? ' · after the deadline' : '' ?></span>
                            </span>
                            <span class="tp-row-side"><span class="tp-btn sm">Grade</span></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($gradingCount > count($grading)): ?>
                    <div class="tp-card-foot"><a href="assignments?view=pending">See all <?= $gradingCount ?> waiting submissions <i class="fas fa-arrow-right"></i></a></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="tp-empty">
                    <i class="fas fa-check-double"></i>
                    <h3>You're all caught up</h3>
                    <p>No submissions are waiting for a grade. When learners hand in work for your assignments it will appear here.</p>
                    <div class="tp-actions"><a class="tp-btn sm" href="assignments?new=1"><i class="fas fa-plus"></i> Post an assignment</a></div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Coming up -->
        <section class="tp-card">
            <div class="tp-card-head">
                <div>
                    <h2><i class="fas fa-calendar-days"></i> Coming up</h2>
                    <p>Deadlines, cohort starts and weekly lesson releases in the next 14 days.</p>
                </div>
            </div>
            <?php if ($upcoming): ?>
                <ul class="tp-list">
                    <?php foreach (array_slice($upcoming, 0, 8) as $u): ?>
                        <li><a class="tp-row" href="<?= e($u['link']) ?>">
                            <span class="tp-date-chip"><small><?= e(date('M', $u['at'])) ?></small><b><?= e(date('j', $u['at'])) ?></b></span>
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= e($u['title']) ?></span>
                                <span class="tp-row-meta"><?= e($u['detail']) ?></span>
                            </span>
                            <span class="tp-row-side"><?= e(date('G:i', $u['at']) === '0:00' ? date('l', $u['at']) : date('D, g:i a', $u['at'])) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($upcoming) > 8): ?><div class="tp-card-foot tp-note">and <?= count($upcoming) - 8 ?> more in the next 14 days.</div><?php endif; ?>
            <?php else: ?>
                <div class="tp-empty">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Nothing scheduled in the next 14 days</h3>
                    <p>
                        <?php if ($nextCohort): ?>
                            Next up: <b><?= e($nextCohort['name']) ?></b> of <?= e($nextCohort['course_title']) ?> starts on <?= e(date('l, j F Y', strtotime($nextCohort['start_date']))) ?>.
                        <?php else: ?>
                            Give your assignments a due date and they will show here, together with cohort start dates and weekly lesson releases.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent enrolments -->
        <section class="tp-card">
            <div class="tp-card-head">
                <div>
                    <h2><i class="fas fa-user-plus"></i> Recent enrolments</h2>
                    <p>The newest learners in your courses and how far they have got.</p>
                </div>
                <a class="tp-btn sm" href="students">All learners</a>
            </div>
            <?php if ($recent): ?>
                <div class="tp-table-wrap">
                    <table class="tp-table stack">
                        <thead><tr><th>Learner</th><th>Course</th><th>Progress</th><th>Status</th><th class="num">Enrolled</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): [$sl, $sc] = td_enrol_status($r['status']); ?>
                            <tr>
                                <td data-label=""><a class="tp-who" href="student_profile?id=<?= (int)$r['student_id'] ?>" style="color:inherit"><?= td_avatar($r['avatar'], $r['student_name']) ?><span><b><?= e($r['student_name']) ?></b><small><?= e(mode_label($r['learning_mode'] ?: 'self_paced')) ?><?= $r['cohort_name'] ? ' · ' . e($r['cohort_name']) : '' ?></small></span></a></td>
                                <td data-label="Course"><?= e($r['course_title']) ?></td>
                                <td data-label="Progress"><div class="tp-progress"><div class="tp-progress-bar"><span style="width:<?= max(0, min(100, (float)$r['progress_percent'])) ?>%"></span></div><b><?= round((float)$r['progress_percent']) ?>%</b></div></td>
                                <td data-label="Status"><span class="tp-pill <?= $sc ?>"><?= $sl ?></span></td>
                                <td data-label="Enrolled" class="num"><?= e(date('j M Y', strtotime($r['enrolled_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tp-empty">
                    <i class="fas fa-users"></i>
                    <h3>No learners yet</h3>
                    <p><?= $live ? 'Your live courses are open for enrolment. Share the course page, or add learners yourself from the Learners page.' : 'Learners can enrol once a course is live. Finish a course and send it for review so the academy can publish it.' ?></p>
                    <div class="tp-actions">
                        <a class="tp-btn sm" href="students?enrol=1"><i class="fas fa-user-plus"></i> Add a learner</a>
                        <?php if (!$live): ?><a class="tp-btn sm" href="courses"><i class="fas fa-book-open"></i> My courses</a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="tp-stack">
        <!-- Quick actions -->
        <section class="tp-card">
            <div class="tp-card-head"><h2><i class="fas fa-bolt"></i> Quick actions</h2></div>
            <div class="tp-card-body">
                <div class="tp-quick">
                    <a href="courses?action=new"><i class="fas fa-plus"></i> New course</a>
                    <a href="<?= $soloCourse ? 'lessons?course_id=' . $soloCourse . '#lesson-form' : 'lessons' ?>"><i class="fas fa-layer-group"></i> Add lesson</a>
                    <a href="<?= $soloCourse ? 'quizzes?course_id=' . $soloCourse . '&new=1' : 'quizzes' ?>"><i class="fas fa-circle-question"></i> Create quiz</a>
                    <a href="assignments?new=1"><i class="fas fa-clipboard-list"></i> Post assignment</a>
                    <a href="messages"><i class="fas fa-paper-plane"></i> Message learners</a>
                    <a href="revenue"><i class="fas fa-wallet"></i> Earnings</a>
                </div>
            </div>
        </section>

        <!-- Messages -->
        <section class="tp-card">
            <div class="tp-card-head">
                <div><h2><i class="fas fa-comments"></i> Unread messages</h2></div>
                <?php if ($inbox['count']): ?><span class="tp-pill warn"><?= $inbox['count'] ?> unread</span><?php endif; ?>
            </div>
            <?php if ($inbox['items']): ?>
                <ul class="tp-list">
                    <?php foreach ($inbox['items'] as $m): ?>
                        <li><a class="tp-row" href="messages?to=<?= (int)$m['sender_id'] ?>">
                            <?= td_avatar($m['avatar'], $m['sender_name'] ?? 'Former user') ?>
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= e($m['sender_name'] ?? 'Former user') ?></span>
                                <span class="tp-row-meta"><?= e(mb_strimwidth((string)$m['content'], 0, 90, '…')) ?></span>
                            </span>
                            <span class="tp-row-side"><?= e(td_ago($m['created_at'])) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tp-empty">
                    <i class="fas fa-inbox"></i>
                    <h3>No unread messages</h3>
                    <p>Learners can message you from their portal. New messages show up here and in Messages.</p>
                    <div class="tp-actions"><a class="tp-btn sm" href="messages">Open messages</a></div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Reviews -->
        <section class="tp-card">
            <div class="tp-card-head"><div><h2><i class="fas fa-star"></i> Recent reviews</h2><p>What learners say about your courses.</p></div></div>
            <?php if ($reviews): ?>
                <ul class="tp-list">
                    <?php foreach ($reviews as $rv): ?>
                        <li><div class="tp-row no-indent">
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= tp_stars((float)$rv['rating']) ?> <span style="font-weight:500;color:var(--tp-muted)"><?= e($rv['student_name'] ?? 'Former learner') ?></span></span>
                                <?php if (trim((string)$rv['review']) !== ''): ?><span class="tp-row-meta" style="color:var(--tp-body)">“<?= e(mb_strimwidth((string)$rv['review'], 0, 160, '…')) ?>”</span><?php endif; ?>
                                <span class="tp-row-meta"><?= e($rv['course_title']) ?> · <?= e(td_ago($rv['at'])) ?></span>
                            </span>
                        </div></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tp-empty">
                    <i class="far fa-star"></i>
                    <h3>No reviews yet</h3>
                    <p>Learners can rate a course once they are enrolled. Their reviews will appear here.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Courses at a glance -->
        <section class="tp-card">
            <div class="tp-card-head"><div><h2><i class="fas fa-book-open"></i> Your courses</h2></div><a class="tp-btn sm" href="courses">Manage</a></div>
            <?php if ($courses): ?>
                <ul class="tp-list">
                    <?php foreach (array_slice($courses, 0, 5) as $c): [$cl, $cc] = td_course_status($c['status']); ?>
                        <li><a class="tp-row" href="lessons?course_id=<?= (int)$c['id'] ?>">
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= e($c['title']) ?></span>
                                <span class="tp-row-meta"><?= (int)$c['learner_count'] ?> learner<?= (int)$c['learner_count'] === 1 ? '' : 's' ?> · <?= (int)$c['lesson_count'] ?> lesson<?= (int)$c['lesson_count'] === 1 ? '' : 's' ?><?= $c['avg_progress'] !== null ? ' · ' . round((float)$c['avg_progress']) . '% average progress' : '' ?></span>
                            </span>
                            <span class="tp-row-side"><span class="tp-pill <?= $cc ?>"><?= $cl ?></span></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tp-empty">
                    <i class="fas fa-book"></i>
                    <h3>Create your first course</h3>
                    <p>Add a title and description, then build lessons, quizzes and assignments.</p>
                    <div class="tp-actions"><a class="tp-btn sm primary" href="courses?action=new"><i class="fas fa-plus"></i> New course</a></div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
