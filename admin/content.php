<?php
/** Admin: the Markdown content library (content/tutorials + content/courses) and its importer. */
$pageTitle = 'Content Library';
require_once 'includes/header.php';

$root = realpath(__DIR__ . '/..');

/** Run import_tutorials.php in-process (its variables stay inside this function) and return its output. */
function axl_run_import(bool $dry): string {
    $pdo = $GLOBALS['pdo'];
    $argv = ['import_tutorials.php'];
    if ($dry) $argv[] = '--dry';
    @set_time_limit(180);
    ob_start();
    try {
        include __DIR__ . '/../import_tutorials.php';
    } catch (Throwable $e) {
        echo "\nStopped with an error: " . $e->getMessage() . "\n";
    }
    $out = (string)ob_get_clean();
    header('Content-Type: text/html; charset=UTF-8');   // the importer switches to text/plain for browser runs
    return trim($out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    if ($act === 'check' || $act === 'import') {
        $dry = $act === 'check';
        $out = axl_run_import($dry);
        $lines = array_filter(explode("\n", $out), fn($l) => trim($l) !== '');
        $changed = count(array_filter($lines, fn($l) => preg_match('/\b(new|updated|added|removed)\b/', $l)));
        $_SESSION['axl_import'] = ['dry' => $dry, 'out' => $out, 'at' => date('Y-m-d H:i:s'), 'changed' => $changed];
        if (!$dry) {
            try { $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('content_last_import', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([date('Y-m-d H:i:s')]); }
            catch (Throwable $e) { error_log($e->getMessage()); }
        }
        ax_flash('ok', $dry
            ? ($changed ? "$changed item(s) would change. Review the result below, then press “Import now”." : 'Everything is up to date – the files and the database match.')
            : ($changed ? "Import finished: $changed item(s) changed." : 'Import finished – nothing needed changing.'));
        ax_back();
    }
    ax_flash('err', 'Unknown action.'); ax_back();
}
$result = $_SESSION['axl_import'] ?? null;
unset($_SESSION['axl_import']);

/* ── What is on disk ── */
$lib = [];
foreach (['tutorial' => 'content/tutorials', 'course' => 'content/courses'] as $kind => $dir) {
    foreach (glob("$root/$dir/*", GLOB_ONLYDIR) ?: [] as $d) {
        $slug = basename($d);
        $json = "$d/" . ($kind === 'course' ? 'course.json' : 'tutorial.json');
        $meta = is_file($json) ? (json_decode((string)file_get_contents($json), true) ?: null) : null;
        $files = glob("$d/[0-9]*.md") ?: [];
        $mtime = 0;
        foreach (array_merge($files, is_file($json) ? [$json] : []) as $f) $mtime = max($mtime, (int)filemtime($f));
        $lib[$kind . ':' . $slug] = ['kind' => $kind, 'slug' => $slug, 'path' => "$dir/$slug", 'meta' => $meta, 'json_ok' => is_array($meta) && !empty($meta['title']),
                                       'files' => count($files), 'mtime' => $mtime, 'course' => null];
    }
}
/* ── What is in the database ── */
$dbRows = $pdo->query("SELECT c.id, c.slug, c.title, c.source, c.status, c.is_open_tutorial, c.tutorial_order,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) lessons,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) learners,
        (SELECT COUNT(DISTINCT x.student_id) FROM (SELECT student_id, course_id FROM enrollments WHERE progress_percent >= 100 OR status = 'completed'
              UNION SELECT student_id, course_id FROM certificates WHERE certificate_type = 'course' AND status <> 'revoked') x WHERE x.course_id = c.id) done,
        (SELECT COUNT(DISTINCT le.user_id) FROM learning_events le WHERE le.course_id = c.id) readers
        FROM courses c WHERE c.source IN ('tutorial', 'course')")->fetchAll();
foreach ($dbRows as $r) {
    $k = $r['source'] . ':' . $r['slug'];
    if (isset($lib[$k])) $lib[$k]['course'] = $r;
    else $lib[$k] = ['kind' => $r['source'], 'slug' => $r['slug'], 'path' => null, 'meta' => null, 'json_ok' => false, 'files' => 0, 'mtime' => 0, 'course' => $r];
}
uasort($lib, fn($a, $b) => [$a['kind'] === 'course', (int)($a['meta']['order'] ?? $a['course']['tutorial_order'] ?? 99), $a['slug']]
                          <=> [$b['kind'] === 'course', (int)($b['meta']['order'] ?? $b['course']['tutorial_order'] ?? 99), $b['slug']]);

$kindTab = in_array($_GET['kind'] ?? '', ['tutorial', 'course'], true) ? $_GET['kind'] : 'all';
$counts = ['all' => count($lib), 'tutorial' => 0, 'course' => 0];
$outOfSync = 0;
foreach ($lib as $it) {
    $counts[$it['kind']]++;
    if (!$it['course'] || !$it['path'] || (int)$it['course']['lessons'] !== $it['files']) $outOfSync++;
}
$lastImport = setting('content_last_import', '');
$newestFile = max(array_column($lib, 'mtime') ?: [0]);
$view = array_filter($lib, fn($it) => $kindTab === 'all' || $it['kind'] === $kindTab);

require_once 'includes/sidebar.php';
$actions = '<form method="post" data-confirm="Compare the files with the database? Nothing is changed.">' . ax_csrf() . '<input type="hidden" name="action" value="check"><button class="ax-btn ghost"><i class="fas fa-magnifying-glass"></i> Check for changes</button></form>'
         . '<form method="post" data-confirm="Import the library now? New lessons are added, changed ones updated, and lessons whose file was deleted are removed. Learner progress on unchanged lessons is kept.">' . ax_csrf() . '<input type="hidden" name="action" value="import"><button class="ax-btn"><i class="fas fa-cloud-arrow-down"></i> Import now</button></form>';
?>
<style>
.main-content { min-width: 0; }
.ax-toolbar > .ax-tabs { min-width: 0; max-width: 100%; }
.axl-out { background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; font: 500 .8rem/1.6 'JetBrains Mono', ui-monospace, monospace; color: var(--text-main); white-space: pre-wrap; overflow-wrap: anywhere; max-height: 360px; overflow: auto; }
.axl-out .chg { color: var(--success); font-weight: 700; }
.axl-out .skp { color: var(--warning); }
.axl-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 14px; }
.axl-steps > div { display: flex; gap: 12px; align-items: flex-start; font-size: .84rem; color: var(--text-dim); line-height: 1.5; }
.axl-steps b { color: var(--text-main); display: block; }
.axl-steps .ax-icon-badge { background: var(--primary-glow); color: var(--primary); font-size: .9rem; width: 34px; height: 34px; }
#libTable .ax-code { white-space: normal; overflow-wrap: anywhere; }
.axl-mis { color: var(--warning); font-weight: 800; }
</style>
<main class="main-content">
    <?php ax_header('Content', 'Library', 'Markdown tutorials & free courses', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <p class="ax-intro">Authors add or edit content as Markdown files – one folder per tutorial in <span class="ax-code">content/tutorials/</span> or per free course in <span class="ax-code">content/courses/</span> (format in <span class="ax-code">content/README.md</span>) – then press <b>Import now</b> to publish the changes; learners keep their progress.</p>

        <?php if ($result): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-terminal"></i> <?= $result['dry'] ? 'Check result (nothing was written)' : 'Import result' ?></h2><p><?= ax_date($result['at'], 'M j, Y H:i') ?> · <?= (int)$result['changed'] ?> item(s) <?= $result['dry'] ? 'would change' : 'changed' ?></p></div>
                <?php if ($result['dry'] && $result['changed']): ?>
                    <form method="post" data-confirm="Import these changes now?"><?= ax_csrf() ?><input type="hidden" name="action" value="import"><button class="ax-btn sm"><i class="fas fa-cloud-arrow-down"></i> Import these changes</button></form>
                <?php endif; ?></div>
            <div class="ax-panel-b"><div class="axl-out"><?php
                foreach (explode("\n", $result['out']) as $line) {
                    $cls = preg_match('/\b(new|updated|added|removed)\b/', $line) ? 'chg' : (str_starts_with($line, 'skip') || str_contains($line, 'error') ? 'skp' : '');
                    echo $cls ? '<span class="' . $cls . '">' . e($line) . '</span>' : e($line), "\n";
                } ?></div></div>
        </section>
        <?php endif; ?>

        <div class="ax-stats">
            <?= ax_stat('book-open-reader', $counts['tutorial'], 'Open tutorials', 'content/tutorials') ?>
            <?= ax_stat('graduation-cap', $counts['course'], 'Free skills courses', 'content/courses', 'violet') ?>
            <?= ax_stat('file-lines', number_format(array_sum(array_column($lib, 'files'))), 'Lesson files', number_format(array_sum(array_map(fn($i) => (int)($i['course']['lessons'] ?? 0), $lib))) . ' lessons in the database', 'green') ?>
            <?= ax_stat($outOfSync ? 'triangle-exclamation' : 'circle-check', $outOfSync, 'Out of sync', $outOfSync ? 'Lesson counts differ – run a check' : 'Files and database match', $outOfSync ? 'amber' : 'slate') ?>
            <?= ax_stat('clock-rotate-left', $lastImport ? ax_ago($lastImport) : '—', 'Last import here', 'Newest file ' . ($newestFile ? ax_ago(date('Y-m-d H:i:s', $newestFile)) : '—'), 'orange') ?>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h">
                <nav class="ax-tabs" aria-label="Kind">
                    <?php foreach (['all' => 'All', 'tutorial' => 'Tutorials', 'course' => 'Free courses'] as $k => $label): ?>
                        <a href="content<?= $k === 'all' ? '' : '?kind=' . $k ?>" class="<?= $kindTab === $k ? 'on' : '' ?>"><?= $label ?> <span class="n"><?= $counts[$k] ?></span></a>
                    <?php endforeach; ?>
                </nav>
                <div class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Filter by title or folder…" data-filter-table="libTable" aria-label="Filter library"></div>
            </div>
            <div class="ax-table-wrap"><table class="ax-table cards" id="libTable">
                <thead><tr><th>Title</th><th>Type</th><th>Lessons (files / live)</th><th>Last file change</th><th>Learners</th><th>Completed</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($view as $it):
                    $c = $it['course'];
                    $title = $it['meta']['title'] ?? $c['title'] ?? $it['slug'];
                    $dbL = (int)($c['lessons'] ?? 0);
                    $sync = $c && $it['path'] && $dbL === $it['files']; ?>
                    <tr>
                        <td data-label="Title"><b class="strong"><?= e($title) ?></b><div class="muted"><span class="ax-code"><?= e($it['path'] ?? 'folder missing') ?></span></div></td>
                        <td data-label="Type"><?= $it['kind'] === 'tutorial' ? '<span class="ax-pill ok">Tutorial</span>' : '<span class="ax-pill violet">Free course</span>' ?></td>
                        <td data-label="Lessons" class="num"><?= $it['path'] ? (int)$it['files'] : '—' ?> / <?= $c ? $dbL : '—' ?>
                            <?php if (!$sync): ?> <span class="axl-mis" title="Run “Check for changes” to see what differs"><i class="fas fa-triangle-exclamation"></i></span><?php endif; ?></td>
                        <td data-label="Last file change" class="num"><?= $it['mtime'] ? ax_ago(date('Y-m-d H:i:s', $it['mtime'])) : '—' ?></td>
                        <td data-label="Learners" class="num"><?= $c ? number_format((int)$c['learners']) . ((int)$c['readers'] > (int)$c['learners'] ? ' <span class="muted">(' . (int)$c['readers'] . ' active)</span>' : '') : '—' ?></td>
                        <td data-label="Completed" class="num"><?= $c ? number_format((int)$c['done']) : '—' ?></td>
                        <td data-label="Status">
                            <?php if (!$c): ?><span class="ax-pill warn">Not imported</span>
                            <?php elseif (!$it['path']): ?><span class="ax-pill bad">Folder missing</span>
                            <?php elseif (!$it['json_ok']): ?><span class="ax-pill bad">Bad JSON</span>
                            <?php else: ?>
                                <span class="ax-pill <?= ['published' => 'ok', 'archived' => 'dark', 'pending' => 'warn'][$c['status']] ?? '' ?>"><?= $c['status'] === 'published' ? 'Live' : e(ucfirst($c['status'])) ?></span>
                                <?php if ($it['kind'] === 'tutorial' && $c['is_open_tutorial']): ?><span class="ax-pill info" title="Readable without an account">Open</span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="" class="actions">
                            <?php if ($c): ?>
                                <a class="ax-btn ghost sm icon" href="../<?= e(learn_url($c)) ?>" target="_blank" rel="noopener" title="Open in the reader" aria-label="Reader"><i class="fas fa-book-open-reader"></i></a>
                                <a class="ax-btn ghost sm icon" href="course_details?id=<?= (int)$c['id'] ?>" title="Details" aria-label="Details"><i class="fas fa-chart-simple"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$view): ?><tr><td colspan="8"><div class="ax-empty"><i class="fas fa-folder-open"></i><b>Nothing here yet</b>Add a folder under content/ and import it.</div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </section>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-pen-nib"></i> How content gets published</h2></div></div>
            <div class="ax-panel-b axl-steps">
                <div><span class="ax-icon-badge"><i class="fas fa-folder-plus"></i></span><div><b>1. Add a folder</b><span class="ax-code">tutorials/&lt;slug&gt;</span> with <span class="ax-code">tutorial.json</span>, or <span class="ax-code">courses/&lt;slug&gt;</span> with <span class="ax-code">course.json</span>.</div></div>
                <div><span class="ax-icon-badge"><i class="fab fa-markdown"></i></span><div><b>2. Write lessons</b>One <span class="ax-code">NN-lesson-slug.md</span> file per lesson with front matter (title, module, minutes).</div></div>
                <div><span class="ax-icon-badge"><i class="fas fa-magnifying-glass"></i></span><div><b>3. Check</b>“Check for changes” lists what would be added, updated or removed – nothing is written.</div></div>
                <div><span class="ax-icon-badge"><i class="fas fa-cloud-arrow-down"></i></span><div><b>4. Import</b>“Import now” publishes it. Tutorials are free and open; free courses unlock a paid certificate.</div></div>
            </div>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
