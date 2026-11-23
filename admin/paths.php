<?php
/** Admin: learning paths – ordered sets of courses that earn a free path certificate. */
$pageTitle = 'Learning Paths';
require_once 'includes/header.php';

function axp_slug(PDO $pdo, string $text, int $exceptId = 0): string {
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-') ?: 'path';
    $base = substr($base, 0, 70);
    $slug = $base; $n = 2;
    $chk = $pdo->prepare("SELECT 1 FROM learning_paths WHERE slug = ? AND id <> ?");
    while (true) { $chk->execute([$slug, $exceptId]); if (!$chk->fetchColumn()) return $slug; $slug = $base . '-' . $n++; }
}
/** Rewrite positions 1..n in the current order. */
function axp_renumber(PDO $pdo, int $pathId): void {
    $st = $pdo->prepare("SELECT course_slug FROM learning_path_courses WHERE path_id = ? ORDER BY position, course_slug");
    $st->execute([$pathId]);
    $up = $pdo->prepare("UPDATE learning_path_courses SET position = ? WHERE path_id = ? AND course_slug = ?");
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $i => $slug) $up->execute([$i + 1, $pathId, $slug]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $path = null;
    if ($id) {
        $st = $pdo->prepare("SELECT * FROM learning_paths WHERE id = ?");
        $st->execute([$id]);
        $path = $st->fetch() ?: null;
        if (!$path) { ax_flash('err', 'That learning path no longer exists.'); ax_back(); }
    }

    if ($act === 'save') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '' || mb_strlen($title) > 150) { ax_flash('err', 'Give the path a title (up to 150 characters).'); ax_back(); }
        $slugIn = trim($_POST['slug'] ?? '');
        $slug = axp_slug($pdo, $slugIn !== '' ? $slugIn : ($path['slug'] ?? $title), $id);
        if ($slugIn === '' && !$path) $slug = axp_slug($pdo, $title);
        $icon = trim($_POST['icon'] ?? '') ?: 'fa-route';
        if (!preg_match('/^[a-z0-9 -]{3,50}$/i', $icon)) { ax_flash('err', 'Icon must be a Font Awesome name such as "fa-laptop-code".'); ax_back(); }
        $color = preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#0080ff';
        $desc = trim($_POST['description'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($path) {
            $pdo->prepare("UPDATE learning_paths SET title = ?, slug = ?, description = ?, icon = ?, color = ?, sort_order = ? WHERE id = ?")
                ->execute([$title, $slug, $desc, $icon, $color, $sort, $id]);
            ax_flash('ok', "\"$title\" saved.");
        } else {
            if (!$sort) $sort = (int)ax_scalar($pdo, "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM learning_paths");
            $pdo->prepare("INSERT INTO learning_paths (slug, title, description, icon, color, sort_order) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$slug, $title, $desc, $icon, $color, $sort]);
            ax_flash('ok', "Path \"$title\" created. Now add its courses in order.");
        }
        ax_back();
    }
    if ($act === 'delete' && $path) {
        $certs = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE certificate_type = 'path' AND path_id = ?", [$id]);
        if ($certs) { ax_flash('err', "\"{$path['title']}\" can't be deleted: $certs learner(s) hold its certificate, which must stay verifiable. Remove its courses instead to retire it."); ax_back(); }
        $pdo->prepare("DELETE FROM learning_path_courses WHERE path_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM learning_paths WHERE id = ?")->execute([$id]);
        ax_flash('ok', "Path \"{$path['title']}\" deleted."); ax_back();
    }
    if ($act === 'add_course' && $path) {
        $slug = trim($_POST['course_slug'] ?? '');
        $st = $pdo->prepare("SELECT title FROM courses WHERE slug = ? AND status = 'published' LIMIT 1");
        $st->execute([$slug]);
        $ct = $st->fetchColumn();
        if (!$ct) { ax_flash('err', 'Choose a live course to add.'); ax_back(); }
        $dup = $pdo->prepare("SELECT 1 FROM learning_path_courses WHERE path_id = ? AND course_slug = ?");
        $dup->execute([$id, $slug]);
        if ($dup->fetchColumn()) { ax_flash('err', "\"$ct\" is already on this path."); ax_back(); }
        $pos = (int)ax_scalar($pdo, "SELECT COALESCE(MAX(position), 0) + 1 FROM learning_path_courses WHERE path_id = ?", [$id]);
        $pdo->prepare("INSERT INTO learning_path_courses (path_id, course_slug, position) VALUES (?, ?, ?)")->execute([$id, $slug, $pos]);
        ax_flash('ok', "\"$ct\" added to {$path['title']} as step $pos."); ax_back();
    }
    if (in_array($act, ['up', 'down', 'remove'], true) && $path) {
        $slug = (string)($_POST['course_slug'] ?? '');
        axp_renumber($pdo, $id);
        $st = $pdo->prepare("SELECT course_slug, position FROM learning_path_courses WHERE path_id = ? ORDER BY position");
        $st->execute([$id]);
        $list = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!isset($list[$slug])) { ax_flash('err', 'That course is no longer on this path.'); ax_back(); }
        if ($act === 'remove') {
            $pdo->prepare("DELETE FROM learning_path_courses WHERE path_id = ? AND course_slug = ?")->execute([$id, $slug]);
            axp_renumber($pdo, $id);
            ax_flash('ok', 'Course removed from the path.'); ax_back();
        }
        $pos = (int)$list[$slug];
        $swap = array_search($act === 'up' ? $pos - 1 : $pos + 1, $list, false);
        if ($swap !== false) {
            $up = $pdo->prepare("UPDATE learning_path_courses SET position = ? WHERE path_id = ? AND course_slug = ?");
            $up->execute([$list[$swap], $id, $slug]);
            $up->execute([$pos, $id, $swap]);
        }
        ax_back();
    }
    ax_flash('err', 'Unknown action.'); ax_back();
}

/* ── Data ── */
$paths = $pdo->query("SELECT * FROM learning_paths ORDER BY sort_order, id")->fetchAll();
$published = $pdo->query("SELECT id, slug, title, source, total_lessons FROM courses WHERE status = 'published' AND slug IS NOT NULL AND slug <> ''
                          ORDER BY (source = 'tutorial') DESC, (source = 'course') DESC, tutorial_order, title")->fetchAll();
$bySlug = [];
foreach ($pdo->query("SELECT id, slug, title, status, source, (SELECT COUNT(*) FROM lessons l WHERE l.course_id = courses.id) lessons FROM courses")->fetchAll() as $c) {
    if (!isset($bySlug[$c['slug']]) || $c['status'] === 'published') $bySlug[$c['slug']] = $c;
}
$certCounts = array_column($pdo->query("SELECT path_id k, COUNT(*) v FROM certificates WHERE certificate_type = 'path' AND status <> 'revoked' GROUP BY path_id")->fetchAll(), 'v', 'k');

$totalLearners = 0;
foreach ($paths as &$p) {
    $st = $pdo->prepare("SELECT course_slug, position FROM learning_path_courses WHERE path_id = ? ORDER BY position, course_slug");
    $st->execute([$p['id']]);
    $p['steps'] = $st->fetchAll();
    // Counted courses match learning_paths(): only live courses count towards completion.
    $ids = [];
    foreach ($p['steps'] as $s) if (($bySlug[$s['course_slug']]['status'] ?? '') === 'published') $ids[] = (int)$bySlug[$s['course_slug']]['id'];
    $p['live'] = count($ids);
    $p['learners'] = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT e.student_id, u.name, u.avatar, COUNT(*) started,
                                    SUM(e.status = 'completed') done, AVG(e.progress_percent) avgp, MAX(e.enrolled_at) last_enrol
                             FROM enrollments e LEFT JOIN users u ON u.id = e.student_id
                             WHERE e.course_id IN ($in) GROUP BY e.student_id, u.name, u.avatar
                             ORDER BY done DESC, avgp DESC");
        $st->execute($ids);
        $p['learners'] = $st->fetchAll();
    }
    $p['finished'] = count(array_filter($p['learners'], fn($l) => (int)$l['done'] >= $p['live']));
    $p['certs'] = (int)($certCounts[$p['id']] ?? 0);
    $totalLearners += count($p['learners']);
}
unset($p);
$recentCerts = $pdo->query("SELECT c.verification_code, c.issued_at, u.name, p.title FROM certificates c LEFT JOIN users u ON u.id = c.student_id
                            LEFT JOIN learning_paths p ON p.id = c.path_id WHERE c.certificate_type = 'path' AND c.status <> 'revoked' ORDER BY c.issued_at DESC LIMIT 6")->fetchAll();

function axp_icon(?string $i): string { $i = trim((string)$i) ?: 'fa-route'; return str_contains($i, ' ') ? $i : 'fas ' . $i; }

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; }
.axp-head { display: flex; gap: 14px; align-items: center; min-width: 0; flex: 1 1 280px; }
.axp-ic { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem; flex-shrink: 0; }
.axp-head h2 { font: 800 1.08rem 'Poppins', sans-serif; color: var(--text-main); margin: 0; }
.axp-head p { font-size: .82rem; color: var(--text-dim); margin-top: 2px; }
.axp-body { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 24px; }
.axp-steps { list-style: none; counter-reset: step; }
.axp-steps li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.axp-steps .n { width: 28px; height: 28px; border-radius: 50%; color: #fff; font-weight: 800; font-size: .75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.axp-steps .t { flex: 1; min-width: 0; }
.axp-steps .t b { display: block; color: var(--text-main); font-size: .88rem; overflow-wrap: anywhere; }
.axp-steps .t small { color: var(--text-muted); font-size: .74rem; }
.axp-steps .b { display: flex; gap: 4px; flex-shrink: 0; }
.axp-steps form { display: inline; }
.axp-add { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
.axp-add select { flex: 1; min-width: 0; padding: 9px 12px; border-radius: 11px; border: 1.5px solid var(--border); background: var(--surface); color: var(--text-main); font: 500 .86rem 'Inter', sans-serif; }
.axp-mini { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.axp-mini div { background: var(--surface-2); border-radius: 12px; padding: 10px; text-align: center; }
.axp-mini b { display: block; font: 800 1.1rem 'Poppins', sans-serif; color: var(--text-main); }
.axp-mini small { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: var(--text-muted); }
.axp-lr li { padding: 8px 0; }
.axp-lr .ax-bar { width: 90px; flex-shrink: 0; }
.axp-sw { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.axp-sw button { width: 28px; height: 28px; border-radius: 8px; border: 2px solid transparent; cursor: pointer; }
.axp-sw button.on { border-color: var(--text-main); }
@media (max-width: 1000px) { .axp-body { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 640px) { .axp-steps li { flex-wrap: wrap; } .axp-steps .b { width: 100%; justify-content: flex-end; } }
</style>
<main class="main-content">
    <?php ax_header('Learning', 'Paths', 'Career tracks built from courses', '<button class="ax-btn" type="button" data-path-new><i class="fas fa-plus"></i> New path</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('route', count($paths), 'Learning paths', array_sum(array_map(fn($p) => count($p['steps']), $paths)) . ' course steps in total') ?>
            <?= ax_stat('user-graduate', number_format($totalLearners), 'Learners on paths', 'Enrolled in at least one path course', 'green') ?>
            <?= ax_stat('certificate', array_sum($certCounts), 'Path certificates', 'Free, issued when every course is completed', 'violet') ?>
        </div>
        <p class="ax-intro">A learner who completes every live course on a path earns its certificate and XP automatically. Courses are matched by their slug, so a path keeps working when a library course is re-imported. Only live courses count.</p>

        <?php if (!$paths): ?>
            <div class="ax-panel"><div class="ax-empty"><i class="fas fa-route"></i><b>No learning paths yet</b>Create one with “New path”, then add its courses in order.</div></div>
        <?php endif; ?>

        <?php foreach ($paths as $p):
            $color = preg_match('/^#[0-9a-f]{6}$/i', (string)$p['color']) ? $p['color'] : '#0080ff';
            $inPath = array_column($p['steps'], 'course_slug');
            $edit = ['id' => (int)$p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'description' => $p['description'], 'icon' => $p['icon'], 'color' => $color, 'sort_order' => (int)$p['sort_order']]; ?>
        <section class="ax-panel" id="path-<?= (int)$p['id'] ?>">
            <div class="ax-panel-h">
                <div class="axp-head"><span class="axp-ic" style="background:<?= e($color) ?>"><i class="<?= e(axp_icon($p['icon'])) ?>"></i></span>
                    <div style="min-width:0"><h2><?= e($p['title']) ?></h2><p><?= e($p['description'] ?: 'No description yet.') ?></p></div></div>
                <div style="display:flex;gap:6px;align-items:center">
                    <span class="ax-code" title="Sort order <?= (int)$p['sort_order'] ?>"><?= e($p['slug']) ?></span>
                    <button type="button" class="ax-btn ghost sm" data-path-edit="<?= e(json_encode($edit, JSON_UNESCAPED_UNICODE)) ?>"><i class="fas fa-pen"></i> Edit</button>
                    <form method="post" data-confirm="Delete the path &quot;<?= e($p['title']) ?>&quot; and its course list? Courses themselves are not touched." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="ax-btn danger sm icon" title="Delete path" aria-label="Delete path" <?= $p['certs'] ? 'disabled' : '' ?>><i class="fas fa-trash"></i></button></form>
                </div>
            </div>
            <div class="ax-panel-b axp-body">
                <div>
                    <h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:4px">Courses, in order</h3>
                    <?php if (!$p['steps']): ?><div class="ax-empty" style="padding:22px"><i class="fas fa-list-ol"></i>No courses yet – add the first one below.</div><?php endif; ?>
                    <ol class="axp-steps">
                        <?php foreach ($p['steps'] as $i => $s):
                            $c = $bySlug[$s['course_slug']] ?? null;
                            $live = $c && $c['status'] === 'published'; ?>
                            <li>
                                <span class="n" style="background:<?= $live ? e($color) : 'var(--text-muted)' ?>"><?= $i + 1 ?></span>
                                <div class="t"><b><?= e($c['title'] ?? $s['course_slug']) ?></b>
                                    <small><span class="ax-code"><?= e($s['course_slug']) ?></span>
                                    <?php if (!$c): ?> <span class="ax-pill bad">Course not found</span><?php elseif (!$live): ?> <span class="ax-pill warn"><?= e(ucfirst($c['status'])) ?> – not counted</span><?php else: ?> · <?= (int)$c['lessons'] ?> lessons<?php endif; ?></small></div>
                                <div class="b">
                                    <?php foreach (['up' => 'arrow-up', 'down' => 'arrow-down'] as $dir => $ic): ?>
                                        <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="<?= $dir ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="course_slug" value="<?= e($s['course_slug']) ?>">
                                            <button class="ax-btn ghost sm icon" title="Move <?= $dir ?>" aria-label="Move <?= $dir ?>" <?= ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($p['steps']) - 1) ? 'disabled' : '' ?>><i class="fas fa-<?= $ic ?>"></i></button></form>
                                    <?php endforeach; ?>
                                    <?php if ($c): ?><a class="ax-btn ghost sm icon" href="course_details?id=<?= (int)$c['id'] ?>" title="Course details" aria-label="Course details"><i class="fas fa-chart-simple"></i></a><?php endif; ?>
                                    <form method="post" data-confirm="Remove this course from the path?" data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="course_slug" value="<?= e($s['course_slug']) ?>">
                                        <button class="ax-btn danger sm icon" title="Remove from path" aria-label="Remove from path"><i class="fas fa-xmark"></i></button></form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <form method="post" class="axp-add"><?= ax_csrf() ?><input type="hidden" name="action" value="add_course"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <select name="course_slug" required aria-label="Course to add">
                            <option value="">Add a live course…</option>
                            <?php $grp = null; foreach ($published as $c): if (in_array($c['slug'], $inPath, true)) continue;
                                $g = $c['source'] === 'tutorial' ? 'Tutorials' : ($c['source'] === 'course' ? 'Free skills courses' : 'Paid courses');
                                if ($g !== $grp) { echo $grp ? '</optgroup>' : '', '<optgroup label="' . e($g) . '">'; $grp = $g; } ?>
                                <option value="<?= e($c['slug']) ?>"><?= e($c['title']) ?></option>
                            <?php endforeach; echo $grp ? '</optgroup>' : ''; ?>
                        </select>
                        <button class="ax-btn sm"><i class="fas fa-plus"></i> Add</button>
                    </form>
                </div>
                <div>
                    <div class="axp-mini">
                        <div><b><?= count($p['learners']) ?></b><small>Learners</small></div>
                        <div><b><?= $p['finished'] ?></b><small>Finished</small></div>
                        <div><b><?= $p['certs'] ?></b><small>Certificates</small></div>
                    </div>
                    <?php if (!$p['learners']): ?>
                        <div class="ax-empty" style="padding:18px"><i class="fas fa-user-graduate"></i>Nobody has started a course on this path yet.</div>
                    <?php else: ?>
                        <ul class="ax-list axp-lr">
                            <?php foreach (array_slice($p['learners'], 0, 6) as $l): $pct = $p['live'] ? min(100, round((int)$l['done'] / $p['live'] * 100)) : 0; ?>
                                <li><?= ax_avatar($l['name'], $l['avatar']) ?>
                                    <div class="grow"><b style="display:block;color:var(--text-main);font-size:.86rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($l['name'] ?? 'Deleted account') ?></b><small style="color:var(--text-muted);font-size:.74rem"><?= (int)$l['done'] ?>/<?= $p['live'] ?> done · <?= (int)$l['started'] ?> started</small></div>
                                    <div class="ax-bar<?= $pct >= 100 ? ' green' : '' ?>"><i style="width:<?= max(3, $pct) ?>%"></i></div></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($p['learners']) > 6): ?><p style="font-size:.76rem;color:var(--text-muted);margin-top:6px">+ <?= count($p['learners']) - 6 ?> more</p><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endforeach; ?>

        <?php if ($recentCerts): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-certificate"></i> Latest path certificates</h2></div><a class="ax-btn ghost sm" href="certificates">All certificates</a></div>
            <div class="ax-panel-b"><ul class="ax-list">
                <?php foreach ($recentCerts as $c): ?>
                    <li><div class="grow"><b style="color:var(--text-main);font-size:.88rem"><?= e($c['name'] ?? 'Deleted account') ?></b> <small style="color:var(--text-muted)">· <?= e($c['title'] ?? '—') ?> · <?= ax_date($c['issued_at']) ?></small></div>
                        <a class="ax-code" href="../certificate?code=<?= urlencode($c['verification_code']) ?>" target="_blank" rel="noopener"><?= e($c['verification_code']) ?></a></li>
                <?php endforeach; ?>
            </ul></div>
        </section>
        <?php endif; ?>
    </div>
</main>

<dialog class="ax-modal" id="pathModal" style="width:min(560px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h2 id="pm-title"><i class="fas fa-route"></i> New learning path</h2><p>Shown on the learner's Paths page.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="pm-id">
            <div class="ax-field"><label for="pm-t">Title</label><input type="text" id="pm-t" name="title" required maxlength="150" placeholder="e.g. Digital Marketer"></div>
            <div class="ax-row">
                <div class="ax-field"><label for="pm-slug">Slug</label><input type="text" id="pm-slug" name="slug" maxlength="80" placeholder="auto from title"><div class="hint">Leave empty to build it from the title.</div></div>
                <div class="ax-field"><label for="pm-sort">Sort order</label><input type="number" id="pm-sort" name="sort_order" min="0" step="1"><div class="hint">Lower shows first. Empty = last.</div></div>
            </div>
            <div class="ax-field"><label for="pm-d">Description</label><textarea id="pm-d" name="description" maxlength="600" placeholder="One sentence: what the learner can do at the end."></textarea></div>
            <div class="ax-row">
                <div class="ax-field"><label for="pm-icon">Icon</label><input type="text" id="pm-icon" name="icon" maxlength="50" placeholder="fa-laptop-code"><div class="hint">Font Awesome name, e.g. <span class="ax-code">fa-chart-line</span>.</div></div>
                <div class="ax-field"><label for="pm-color">Colour</label><input type="color" id="pm-color" name="color" value="#0080ff" style="width:100%;height:42px;border:1px solid var(--border);border-radius:10px;background:var(--surface);padding:3px">
                    <div class="axp-sw"><?php foreach (['#0080ff', '#10b981', '#8b5cf6', '#ff8c00', '#ec4899', '#14b8a6', '#ef4444', '#f59e0b'] as $s): ?><button type="button" style="background:<?= $s ?>" data-sw="<?= $s ?>" aria-label="Colour <?= $s ?>"></button><?php endforeach; ?></div></div>
            </div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save path</button></div>
        </div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    var d = document.getElementById('pathModal'), $ = function (i) { return document.getElementById(i); };
    function mark() { document.querySelectorAll('#pathModal [data-sw]').forEach(function (b) { b.classList.toggle('on', b.dataset.sw === $('pm-color').value.toLowerCase()); }); }
    function open(p) {
        $('pm-id').value = p ? p.id : '';
        $('pm-t').value = p ? p.title : '';
        $('pm-slug').value = p ? p.slug : '';
        $('pm-sort').value = p ? p.sort_order : '';
        $('pm-d').value = p ? (p.description || '') : '';
        $('pm-icon').value = p ? (p.icon || '') : 'fa-route';
        $('pm-color').value = p ? p.color.toLowerCase() : '#0080ff';
        $('pm-title').innerHTML = p ? '<i class="fas fa-pen"></i> Edit learning path' : '<i class="fas fa-route"></i> New learning path';
        mark(); d.showModal();
    }
    $('pm-color').addEventListener('input', mark);
    document.querySelectorAll('#pathModal [data-sw]').forEach(function (b) { b.addEventListener('click', function () { $('pm-color').value = b.dataset.sw; mark(); }); });
    document.querySelectorAll('[data-path-new]').forEach(function (b) { b.addEventListener('click', function () { open(null); }); });
    document.querySelectorAll('[data-path-edit]').forEach(function (b) { b.addEventListener('click', function () { open(JSON.parse(b.dataset.pathEdit)); }); });
})();
</script>
</body></html>
