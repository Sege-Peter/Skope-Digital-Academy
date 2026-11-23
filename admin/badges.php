<?php
/** Admin: badges & XP – every badge, manual badges, awarding/revoking, and an XP overview. */
$pageTitle = 'Badges & XP';
require_once 'includes/header.php';

$uid = (int)$user['id'];

const BX_ICONS = ['fa-medal', 'fa-trophy', 'fa-award', 'fa-star', 'fa-crown', 'fa-gem', 'fa-rocket', 'fa-bolt', 'fa-fire', 'fa-heart',
                  'fa-hand-holding-heart', 'fa-lightbulb', 'fa-code', 'fa-users', 'fa-handshake', 'fa-thumbs-up', 'fa-graduation-cap',
                  'fa-book-open', 'fa-certificate', 'fa-flag-checkered', 'fa-shield-halved', 'fa-seedling', 'fa-palette', 'fa-microphone'];

function bx_badge(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM badges WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
/** System badges are seeded by ensure_badges() and have a code; custom/manual ones have none. */
function bx_is_system(array $b): bool { return $b['code'] !== null && $b['code'] !== ''; }

function bx_icon(array $b, string $size = ''): string {
    $icon = (string)$b['icon'];
    $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string)$b['color']) ? $b['color'] : '#0080ff';
    $inner = preg_match('/^fa-[a-z0-9-]+$/', $icon) ? '<i class="fas ' . e($icon) . '"></i>'
           : (trim($icon, '? ') === '' ? '<i class="fas fa-medal"></i>' : e($icon));
    return '<span class="ax-icon-badge bx-ic ' . $size . '" style="background:color-mix(in srgb,' . $color . ' 16%,transparent);color:' . $color . '">' . $inner . '</span>';
}

function bx_criteria(array $b, array $courseTitles, array $pathTitles): string {
    if (!bx_is_system($b) || $b['kind'] === 'manual') return 'Awarded by an admin or tutor';
    $n = (int)$b['criteria_value'];
    $p = fn(string $one, string $many) => number_format($n) . ' ' . ($n === 1 ? $one : $many);
    return match ($b['kind']) {
        'skill' => 'Complete the course “' . ($courseTitles[(int)$b['course_id']] ?? 'course #' . $b['course_id']) . '”',
        'path'  => 'Complete every course in the “' . ($pathTitles[(int)$b['path_id']] ?? 'path #' . $b['path_id']) . '” path',
        default => match ($b['criteria_type']) {
            'lessons'   => 'Complete ' . $p('lesson', 'lessons'),
            'exercises' => 'Solve ' . $p('exercise', 'exercises'),
            'quizzes'   => 'Pass ' . $p('quiz', 'quizzes'),
            'perfect'   => 'Score 100% on ' . $p('quiz', 'quizzes'),
            'streak'    => 'Learn ' . $p('day', 'days') . ' in a row',
            'courses'   => 'Complete ' . $p('course', 'courses'),
            'xp'        => 'Earn ' . number_format($n) . ' XP',
            'reviews'   => 'Review ' . $p('course', 'courses'),
            'peer_reviews' => 'Give feedback on ' . $p("classmate's project", "classmates' projects"),
            'projects'  => 'Pass ' . $p('peer-graded project', 'peer-graded projects'),
            default     => 'Reach ' . number_format($n) . ' ' . str_replace('_', ' ', (string)$b['criteria_type']),
        },
    };
}

function bx_fields(): array {
    $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 100));
    $desc = trim(mb_substr((string)($_POST['description'] ?? ''), 0, 500));
    $icon = (string)($_POST['icon'] ?? 'fa-medal');
    if (!preg_match('/^fa-[a-z0-9-]{1,40}$/', $icon)) $icon = 'fa-medal';
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#0080ff';
    $xp = max(0, min(5000, (int)($_POST['xp_reward'] ?? 0)));
    return [$name, $desc, $icon, $color, $xp];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $bid = (int)($_POST['badge_id'] ?? 0);

    if ($act === 'create') {
        [$name, $desc, $icon, $color, $xp] = bx_fields();
        if ($name === '') { ax_flash('err', 'Give the badge a name.'); ax_back(); }
        $pdo->prepare("INSERT INTO badges (name, description, icon, color, kind, criteria_type, criteria_value, xp_reward, sort_order) VALUES (?, ?, ?, ?, 'manual', NULL, 1, ?, 300)")
            ->execute([$name, $desc ?: null, $icon, $color, $xp]);
        ax_flash('ok', "Badge “{$name}” created. You can award it now."); ax_back('badges?tab=manual');
    }

    if ($act === 'update') {
        $b = bx_badge($pdo, $bid);
        if (!$b) { ax_flash('err', 'Badge not found.'); ax_back(); }
        [$name, $desc, $icon, $color, $xp] = bx_fields();
        if ($name === '') { ax_flash('err', 'Give the badge a name.'); ax_back(); }
        if (bx_is_system($b)) {
            // Rules and code stay as seeded; only the look, wording and XP can change.
            $pdo->prepare("UPDATE badges SET name = ?, description = ?, icon = ?, color = ?, xp_reward = ? WHERE id = ?")->execute([$name, $desc ?: null, $icon, $color, $xp, $bid]);
        } else {
            $pdo->prepare("UPDATE badges SET name = ?, description = ?, icon = ?, color = ?, xp_reward = ?, kind = 'manual' WHERE id = ?")->execute([$name, $desc ?: null, $icon, $color, $xp, $bid]);
        }
        $note = bx_is_system($b) && in_array($b['kind'], ['achievement', 'skill', 'path'], true)
            ? ' Note: built-in badges are re-seeded from the platform defaults when the learning engine refreshes, so wording/XP edits may be reset.' : '';
        ax_flash('ok', 'Badge updated.' . $note); ax_back();
    }

    if ($act === 'delete') {
        $b = bx_badge($pdo, $bid);
        if (!$b || bx_is_system($b)) { ax_flash('err', 'Built-in badges cannot be deleted.'); ax_back(); }
        $pdo->prepare("DELETE FROM student_badges WHERE badge_id = ?")->execute([$bid]);
        $pdo->prepare("DELETE FROM badges WHERE id = ?")->execute([$bid]);
        ax_flash('ok', "Badge “{$b['name']}” deleted (and removed from learners who held it). XP already given is kept."); ax_back();
    }

    if ($act === 'award') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $b = bx_badge($pdo, $bid);
        $st = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'student'");
        $st->execute([$sid]);
        $stu = $st->fetch();
        if (!$b || !$stu) { ax_flash('err', 'Choose a learner and a badge.'); ax_back(); }
        $notes = trim(mb_substr((string)($_POST['notes'] ?? ''), 0, 255));
        $ins = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_by, awarded_by_role, notes) VALUES (?, ?, ?, 'admin', ?)");
        $ins->execute([$sid, $bid, $uid, $notes ?: 'Awarded by ' . $user['name']]);
        if (!$ins->rowCount()) { ax_flash('err', "{$stu['name']} already has the “{$b['name']}” badge."); ax_back(); }
        $gained = 0;
        if (!empty($_POST['give_xp']) && (int)$b['xp_reward'] > 0) {
            $gained = record_event($pdo, $sid, 'badge', 'badge:' . $bid, (int)$b['xp_reward'], $b['course_id'] ? (int)$b['course_id'] : null, null, 'Awarded by admin');
        }
        notify($pdo, $sid, 'New badge: ' . $b['name'] . ' 🏅', ($b['description'] ?: 'You were awarded a badge.') . ($notes !== '' ? " – $notes" : '') . ($gained ? " (+$gained XP)" : ''));
        ax_flash('ok', "“{$b['name']}” awarded to {$stu['name']}" . ($gained ? " with +$gained XP" : '') . '. They were notified.'); ax_back();
    }

    if ($act === 'revoke_award') {
        $st = $pdo->prepare("SELECT sb.*, b.name AS badge_name, u.name AS student_name FROM student_badges sb JOIN badges b ON b.id = sb.badge_id LEFT JOIN users u ON u.id = sb.student_id WHERE sb.id = ?");
        $st->execute([(int)($_POST['award_id'] ?? 0)]);
        $a = $st->fetch();
        if (!$a) { ax_flash('err', 'That award no longer exists.'); ax_back(); }
        $pdo->prepare("DELETE FROM student_badges WHERE id = ?")->execute([$a['id']]);
        // Take back the badge's XP so users.xp stays the sum of their events.
        $ev = $pdo->prepare("SELECT id, xp FROM learning_events WHERE user_id = ? AND event_key = ?");
        $ev->execute([$a['student_id'], 'badge:' . $a['badge_id']]);
        if ($row = $ev->fetch()) {
            $pdo->prepare("DELETE FROM learning_events WHERE id = ?")->execute([$row['id']]);
            $pdo->prepare("UPDATE users SET xp = GREATEST(0, xp - ?) WHERE id = ?")->execute([(int)$row['xp'], $a['student_id']]);
        }
        ax_flash('ok', "Removed “{$a['badge_name']}” from " . ($a['student_name'] ?? 'the learner') . ($row ? " and took back {$row['xp']} XP" : '') . '.'); ax_back();
    }
    ax_back();
}

/* ── Data ── */
$courseTitles = $pdo->query("SELECT id, title FROM courses")->fetchAll(PDO::FETCH_KEY_PAIR);
$pathTitles = $pdo->query("SELECT id, title FROM learning_paths")->fetchAll(PDO::FETCH_KEY_PAIR);
$badges = $pdo->query("SELECT b.*, (SELECT COUNT(*) FROM student_badges sb WHERE sb.badge_id = b.id) AS holders
                       FROM badges b ORDER BY b.sort_order, b.id")->fetchAll();
$groups = ['achievement' => [], 'skill' => [], 'path' => [], 'manual' => []];
foreach ($badges as $b) $groups[bx_is_system($b) ? $b['kind'] : 'manual'][] = $b;
$groupInfo = [
    'achievement' => ['Achievements', 'Earned automatically for milestones: lessons, quizzes, streaks, XP.', 'trophy'],
    'skill'       => ['Skill badges', 'One per course – earned by completing it.', 'certificate'],
    'path'        => ['Path badges', 'Earned by completing every course in a learning path.', 'route'],
    'manual'      => ['Manual badges', 'Created here and awarded by hand (never automatic).', 'hand-holding-heart'],
];
$tab = isset($groups[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'achievement';

$students = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student' AND status = 'active' ORDER BY name")->fetchAll();
$recent = $pdo->query("SELECT sb.id, sb.awarded_at, sb.awarded_by_role, sb.notes, b.name AS badge_name, b.icon, b.color, b.kind, b.code, u.name AS student_name, u.avatar, a.name AS awarder
                       FROM student_badges sb JOIN badges b ON b.id = sb.badge_id LEFT JOIN users u ON u.id = sb.student_id LEFT JOIN users a ON a.id = sb.awarded_by
                       ORDER BY sb.awarded_at DESC, sb.id DESC LIMIT 15")->fetchAll();

$totalAwards = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM student_badges");
$manualAwards = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM student_badges WHERE awarded_by_role IN ('admin','tutor')");
$totalXp = (int)ax_scalar($pdo, "SELECT SUM(xp) FROM users WHERE role = 'student'");
$weekXp = (int)ax_scalar($pdo, "SELECT SUM(le.xp) FROM learning_events le JOIN users u ON u.id = le.user_id AND u.role = 'student' WHERE le.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$learners = $pdo->query("SELECT id, name, avatar, xp, streak_days, best_streak FROM users WHERE role = 'student' ORDER BY xp DESC, name")->fetchAll();
$levels = [];
foreach ($learners as $l) { $lv = xp_level((int)$l['xp']); $levels[$lv['level']] ??= ['title' => $lv['title'], 'n' => 0]; $levels[$lv['level']]['n']++; }
ksort($levels);
$maxLevelN = max([1, ...array_column($levels, 'n')]);
$top = array_slice(array_filter($learners, fn($l) => (int)$l['xp'] > 0), 0, 6);
$ruleLabels = [
    'lesson_view' => ['Open a lesson (first time)', 'eye'], 'lesson_complete' => ['Complete a lesson', 'circle-check'],
    'exercise_pass' => ['Solve an exercise', 'keyboard'], 'quiz_pass' => ['Pass a quiz (' . QUIZ_PASS_PCT . '%+)', 'clipboard-check'],
    'quiz_perfect' => ['Perfect quiz score (bonus)', 'bullseye'], 'course_complete' => ['Complete a course', 'award'],
    'path_complete' => ['Complete a learning path', 'route'], 'daily_streak' => ['Learn on a new day (streak)', 'fire'],
    'review' => ['Review a course', 'comment-dots'],
    'project_submit' => ['Submit a course project', 'upload'], 'project_review' => ["Review a classmate's project", 'handshake-angle'],
    'project_pass' => ['Pass a peer-graded project', 'diagram-project'],
];

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; } /* keep wide tables scrolling inside their panel */
.bx-ic { width: 42px; height: 42px; font-size: 1.05rem; }
.bx-ic.lg { width: 56px; height: 56px; font-size: 1.4rem; border-radius: 16px; }
.bx-name { font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.bx-desc { font-size: .78rem; color: var(--text-muted); margin-top: 2px; line-height: 1.45; }
.bx-badge-cell { display: flex; gap: 12px; align-items: center; min-width: 0; text-align: left; }
.bx-xp { font-weight: 800; color: var(--warning); white-space: nowrap; }
.bx-levels .row { display: grid; grid-template-columns: 110px 1fr 34px; gap: 10px; align-items: center; margin-bottom: 9px; font-size: .8rem; }
.bx-levels .row b { color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bx-levels .row span:last-child { text-align: right; font-weight: 800; color: var(--text-dim); }
.bx-iconpick { display: grid; grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); gap: 6px; }
.bx-iconpick label { cursor: pointer; }
.bx-iconpick input { position: absolute; opacity: 0; pointer-events: none; }
.bx-iconpick span { display: flex; align-items: center; justify-content: center; height: 40px; border: 1.5px solid var(--border); border-radius: 10px; color: var(--text-dim); }
.bx-iconpick input:checked + span { border-color: var(--primary); background: var(--primary-glow); color: var(--primary); }
.bx-iconpick input:focus-visible + span { box-shadow: 0 0 0 3px var(--primary-glow); }
.bx-form input[type=color] { width: 100%; height: 42px; border: 1.5px solid var(--border); border-radius: 11px; padding: 3px; background: var(--surface); }
.bx-preview { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px dashed var(--border); border-radius: 14px; margin-bottom: 14px; }
@media (max-width: 760px) {
  #badgeTable td[data-label="Badge"] { justify-content: flex-start; } #badgeTable td[data-label="Badge"]::before { display: none; }
  #badgeTable td[data-label="Criteria"] { text-align: right; }
}
</style>
<main class="main-content">
    <?php ax_header('Badges', '& XP', 'Rewards & progress', '<a class="ax-btn ghost" href="leaderboard"><i class="fas fa-ranking-star"></i> Leaderboards</a><button class="ax-btn ghost" type="button" data-open="badgeDlg" data-new><i class="fas fa-plus"></i> New badge</button><button class="ax-btn" type="button" data-open="awardDlg"><i class="fas fa-medal"></i> Award badge</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('medal', number_format(count($badges)), 'Badges', count($groups['manual']) . ' manual · ' . (count($badges) - count($groups['manual'])) . ' automatic', 'violet') ?>
            <?= ax_stat('user-check', number_format($totalAwards), 'Badges earned', number_format($manualAwards) . ' awarded by hand', 'green') ?>
            <?= ax_stat('bolt', number_format($totalXp), 'Total learner XP', number_format(count($learners)) . ' learner' . (count($learners) === 1 ? '' : 's'), 'amber') ?>
            <?= ax_stat('calendar-week', number_format($weekXp), 'XP this week', 'Earned in the last 7 days', '') ?>
        </div>

        <div class="ax-grid">
            <div class="ax-span-8 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-<?= $groupInfo[$tab][2] ?>"></i> <?= e($groupInfo[$tab][0]) ?></h2><p><?= e($groupInfo[$tab][1]) ?></p></div>
                        <div class="ax-search" style="flex-basis:240px"><i class="fas fa-search"></i><input type="search" placeholder="Filter badges…" data-filter-table="badgeTable" aria-label="Filter badges"></div></div>
                    <div class="ax-panel-b" style="padding-bottom:4px">
                        <nav class="ax-tabs" aria-label="Badge kinds">
                            <?php foreach ($groupInfo as $k => $gi): ?><a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($gi[0]) ?> <span class="n"><?= count($groups[$k]) ?></span></a><?php endforeach; ?>
                        </nav>
                    </div>
                    <div class="ax-table-wrap">
                        <table class="ax-table cards" id="badgeTable">
                            <thead><tr><th>Badge</th><th>How it's earned</th><th>XP reward</th><th>Holders</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($groups[$tab] as $b): $sys = bx_is_system($b); ?>
                                <tr>
                                    <td data-label="Badge"><div class="bx-badge-cell"><?= bx_icon($b) ?><div style="min-width:0">
                                        <div class="bx-name"><?= e($b['name']) ?><?= $sys ? '' : ($b['kind'] !== 'manual' ? ' <span class="ax-pill">Legacy</span>' : '') ?></div>
                                        <div class="bx-desc"><?= e($b['description'] ?: '—') ?></div></div></div></td>
                                    <td data-label="Criteria" style="font-size:.82rem"><?= e(bx_criteria($b, $courseTitles, $pathTitles)) ?></td>
                                    <td data-label="XP reward" class="num"><?= (int)$b['xp_reward'] ? '<span class="bx-xp">+' . number_format((int)$b['xp_reward']) . ' XP</span>' : '<span class="muted">—</span>' ?></td>
                                    <td data-label="Holders" class="num"><span><b style="color:var(--text-main)"><?= number_format((int)$b['holders']) ?></b> <span class="muted">learner<?= (int)$b['holders'] === 1 ? '' : 's' ?></span></span></td>
                                    <td data-label="" class="actions">
                                        <button type="button" class="ax-btn sm ghost" data-edit='<?= e(json_encode(['id' => (int)$b['id'], 'name' => $b['name'], 'description' => $b['description'], 'icon' => $b['icon'], 'color' => $b['color'], 'xp' => (int)$b['xp_reward'], 'system' => $sys])) ?>'><i class="fas fa-pen"></i> Edit</button>
                                        <?php if (!$sys): ?>
                                            <form method="post" data-confirm="Delete the “<?= e($b['name']) ?>” badge? It is removed from <?= (int)$b['holders'] ?> learner(s) who hold it." data-danger>
                                                <?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="badge_id" value="<?= (int)$b['id'] ?>">
                                                <button class="ax-btn sm danger icon" aria-label="Delete badge" title="Delete"><i class="fas fa-trash"></i></button></form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!$groups[$tab]): ?><div class="ax-empty"><i class="fas fa-medal"></i><b>No <?= e(strtolower($groupInfo[$tab][0])) ?> yet</b><?= $tab === 'manual' ? 'Create one with “New badge” to reward things the platform can’t track, like a great project or helping peers.' : '' ?></div><?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> Recent awards</h2><p>Latest badges earned or given. Removing one also takes back its XP.</p></div></div>
                    <div class="ax-table-wrap">
                        <table class="ax-table cards">
                            <thead><tr><th>Learner</th><th>Badge</th><th>How</th><th>When</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td data-label="Learner"><div class="ax-who"><?= ax_avatar($r['student_name'] ?? '?', $r['avatar']) ?><div><b><?= e($r['student_name'] ?? 'Deleted learner') ?></b></div></div></td>
                                    <td data-label="Badge"><div class="bx-badge-cell"><?= str_replace('bx-ic', 'bx-ic" style="width:32px;height:32px;font-size:.8rem', bx_icon($r)) ?><b style="color:var(--text-main);font-size:.86rem"><?= e($r['badge_name']) ?></b></div></td>
                                    <td data-label="How"><?= $r['awarded_by_role'] === 'system' ? '<span class="ax-pill info">Automatic</span>' : '<span class="ax-pill violet">By ' . e($r['awarder'] ?? $r['awarded_by_role']) . '</span>' ?><?= $r['notes'] && $r['awarded_by_role'] !== 'system' ? '<div class="muted" style="margin-top:3px">' . e($r['notes']) . '</div>' : '' ?></td>
                                    <td data-label="When" class="num"><?= ax_ago($r['awarded_at']) ?></td>
                                    <td data-label="" class="actions">
                                        <form method="post" data-confirm="Remove “<?= e($r['badge_name']) ?>” from <?= e($r['student_name'] ?? 'this learner') ?>? Any XP the badge gave is taken back.<?= $r['code'] ? ' Automatic badges are earned again if the learner still qualifies.' : '' ?>" data-danger>
                                            <?= ax_csrf() ?><input type="hidden" name="action" value="revoke_award"><input type="hidden" name="award_id" value="<?= (int)$r['id'] ?>">
                                            <button class="ax-btn sm danger"><i class="fas fa-xmark"></i> Remove</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (!$recent): ?><div class="ax-empty"><i class="fas fa-medal"></i><b>No badges earned yet</b></div><?php endif; ?>
                    </div>
                </section>
            </div>

            <aside class="ax-span-4 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-layer-group"></i> Learners by level</h2><p>Levels come from total XP.</p></div></div>
                    <div class="ax-panel-b bx-levels">
                        <?php foreach ($levels as $lv => $info): ?>
                            <div class="row"><b>Lv <?= $lv ?> · <?= e($info['title']) ?></b><div class="ax-bar"><i style="width:<?= round($info['n'] / $maxLevelN * 100) ?>%"></i></div><span><?= $info['n'] ?></span></div>
                        <?php endforeach; ?>
                        <?php if (!$levels): ?><div class="ax-empty" style="padding:20px"><b>No learners yet</b></div><?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-ranking-star"></i> Top earners</h2><p>All-time XP.</p></div><a class="ax-btn sm ghost" href="leaderboard">Full board</a></div>
                    <div class="ax-panel-b">
                        <?php if ($top): ?><ul class="ax-list">
                            <?php foreach ($top as $i => $l): $lv = xp_level((int)$l['xp']); ?>
                                <li><b style="width:22px;color:var(--text-muted);font-size:.85rem">#<?= $i + 1 ?></b><?= ax_avatar($l['name'], $l['avatar']) ?>
                                    <div class="grow"><b style="display:block;font-size:.86rem;color:var(--text-main);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($l['name']) ?></b>
                                        <small style="color:var(--text-muted);font-size:.74rem">Level <?= $lv['level'] ?> · <?= e($lv['title']) ?> · <i class="fas fa-fire" style="color:var(--secondary)"></i> <?= (int)$l['streak_days'] ?>d</small></div>
                                    <span class="bx-xp"><?= number_format((int)$l['xp']) ?> XP</span></li>
                            <?php endforeach; ?></ul>
                        <?php else: ?><div class="ax-empty" style="padding:20px"><b>No XP earned yet</b></div><?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-bolt"></i> How learners earn XP</h2><p>Each action counts once. Set in the learning engine.</p></div></div>
                    <div class="ax-panel-b">
                        <ul class="ax-list">
                            <?php foreach (XP_RULES as $k => $xp): [$lab, $ic] = $ruleLabels[$k] ?? [ucfirst(str_replace('_', ' ', $k)), 'bolt']; ?>
                                <li style="padding:9px 0"><i class="fas fa-<?= $ic ?>" style="width:18px;color:var(--primary)"></i><span class="grow" style="font-size:.85rem;color:var(--text-main)"><?= e($lab) ?></span><span class="bx-xp">+<?= $xp ?></span></li>
                            <?php endforeach; ?>
                            <li style="padding:9px 0"><i class="fas fa-medal" style="width:18px;color:var(--primary)"></i><span class="grow" style="font-size:.85rem;color:var(--text-main)">Earn a badge</span><span class="muted" style="font-size:.78rem">its XP reward</span></li>
                        </ul>
                        <p style="font-size:.76rem;color:var(--text-muted);margin-top:10px">Levels: level <i>n</i> needs 50 × n × (n − 1) XP (100, 300, 600, 1,000 …).</p>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</main>

<dialog class="ax-modal" id="awardDlg" aria-labelledby="awardH" style="width:min(520px,calc(100vw - 32px))">
    <div class="ax-panel-h"><div><h2 id="awardH"><i class="fas fa-medal"></i> Award a badge</h2><p>The learner is notified straight away.</p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <form class="ax-panel-b ax-form" method="post">
        <?= ax_csrf() ?><input type="hidden" name="action" value="award">
        <div class="ax-field"><label for="aw-s">Learner</label>
            <select id="aw-s" name="student_id" required><option value="">Choose a learner…</option>
                <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['email']) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="ax-field"><label for="aw-b">Badge</label>
            <select id="aw-b" name="badge_id" required><option value="">Choose a badge…</option>
                <?php foreach (['manual', 'achievement', 'skill', 'path'] as $k): if (!$groups[$k]) continue; ?>
                    <optgroup label="<?= e($groupInfo[$k][0]) ?>">
                        <?php foreach ($groups[$k] as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?><?= (int)$b['xp_reward'] ? ' (+' . (int)$b['xp_reward'] . ' XP)' : '' ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select></div>
        <div class="ax-field"><label for="aw-n">Reason (shown to the learner)</label><input id="aw-n" type="text" name="notes" maxlength="255" placeholder="e.g. Outstanding final project"></div>
        <div class="ax-field"><label class="ax-check"><input type="checkbox" name="give_xp" value="1" checked> Also give the badge's XP reward</label>
            <div class="hint">XP for a badge is only ever given once per learner.</div></div>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-medal"></i> Award badge</button></div>
    </form>
</dialog>

<dialog class="ax-modal" id="badgeDlg" aria-labelledby="badgeH">
    <div class="ax-panel-h"><div><h2 id="badgeH"><i class="fas fa-medal"></i> <span data-title>New badge</span></h2><p data-sub>Manual badges are awarded by hand.</p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <form class="ax-panel-b ax-form bx-form" method="post">
        <?= ax_csrf() ?><input type="hidden" name="action" value="create"><input type="hidden" name="badge_id" value="">
        <div class="bx-preview"><span class="ax-icon-badge bx-ic lg" data-prev-ic><i class="fas fa-medal"></i></span><div><b data-prev-name style="color:var(--text-main)">Badge name</b><div class="bx-desc" data-prev-desc>Description</div></div></div>
        <div class="ax-row">
            <div class="ax-field"><label for="bd-n">Name</label><input id="bd-n" type="text" name="name" maxlength="100" required></div>
            <div class="ax-field" style="max-width:none"><label for="bd-x">XP reward</label><input id="bd-x" type="number" name="xp_reward" min="0" max="5000" value="0"><div class="hint">0 – 5,000 XP, given once.</div></div>
        </div>
        <div class="ax-field"><label for="bd-d">Description</label><textarea id="bd-d" name="description" maxlength="500" placeholder="What is this badge for?"></textarea></div>
        <div class="ax-field"><label>Icon</label>
            <div class="bx-iconpick" role="radiogroup" aria-label="Icon">
                <?php foreach (BX_ICONS as $ic): ?><label title="<?= e($ic) ?>"><input type="radio" name="icon" value="<?= e($ic) ?>"<?= $ic === 'fa-medal' ? ' checked' : '' ?>><span><i class="fas <?= e($ic) ?>"></i></span></label><?php endforeach; ?>
            </div></div>
        <div class="ax-field" style="max-width:160px"><label for="bd-c">Colour</label><input id="bd-c" type="color" name="color" value="#0080ff"></div>
        <div class="ax-alert info" data-sys-note hidden style="margin-bottom:12px"><i class="fas fa-circle-info"></i><div>This is a built-in badge: its rule and code are fixed. You can change its name, description, look and XP reward, but the learning engine currently re-applies the platform defaults when it refreshes badges, so these edits may be reset.</div></div>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> <span data-save>Create badge</span></button></div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
  var d = document.getElementById('badgeDlg'), f = d.querySelector('form');
  var icons = Array.prototype.map.call(f.querySelectorAll('[name=icon]'), function (r) { return r.value; });
  var pick = f.querySelector('.bx-iconpick');
  function preview() {
    var el = f.elements, ic = (f.querySelector('[name=icon]:checked') || {}).value || 'fa-medal', c = el.color.value;
    var box = d.querySelector('[data-prev-ic]');
    box.innerHTML = '<i class="fas ' + ic + '"></i>';
    box.style.color = c; box.style.background = 'color-mix(in srgb,' + c + ' 16%,transparent)';
    d.querySelector('[data-prev-name]').textContent = el.name.value || 'Badge name';
    d.querySelector('[data-prev-desc]').textContent = el.description.value || 'Description';
  }
  function setIcon(ic) {
    if (!/^fa-[a-z0-9-]+$/.test(ic || '')) ic = 'fa-medal';
    if (icons.indexOf(ic) < 0) {   // keep a badge's own icon selectable
      var l = document.createElement('label'); l.title = ic;
      l.innerHTML = '<input type="radio" name="icon"><span><i class="fas"></i></span>';
      l.querySelector('input').value = ic; l.querySelector('i').classList.add(ic);
      pick.insertBefore(l, pick.firstChild); icons.push(ic);
    }
    f.querySelectorAll('[name=icon]').forEach(function (r) { r.checked = r.value === ic; });
  }
  function open(b) {
    var el = f.elements;
    el.action.value = b ? 'update' : 'create';
    el.badge_id.value = b ? b.id : '';
    el.name.value = b ? b.name : ''; el.description.value = b ? (b.description || '') : '';
    el.xp_reward.value = b ? b.xp : 0; el.color.value = b && /^#[0-9a-f]{6}$/i.test(b.color) ? b.color : '#0080ff';
    setIcon(b ? b.icon : 'fa-medal');
    d.querySelector('[data-title]').textContent = b ? 'Edit badge' : 'New badge';
    d.querySelector('[data-save]').textContent = b ? 'Save changes' : 'Create badge';
    d.querySelector('[data-sub]').textContent = b && b.system ? 'Built-in badge' : 'Manual badges are awarded by hand.';
    d.querySelector('[data-sys-note]').hidden = !(b && b.system);
    preview();
    if (!d.open) d.showModal();
  }
  document.querySelector('[data-new]').addEventListener('click', function () { open(null); });
  document.querySelectorAll('[data-edit]').forEach(function (btn) { btn.addEventListener('click', function () { open(JSON.parse(btn.dataset.edit)); }); });
  f.addEventListener('input', preview); f.addEventListener('change', preview);
})();
</script>
</body></html>
