<?php
/** Admin: applications for one position, ranked by assessment score, with CSV export. */
$pageTitle = 'Applications';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/careers.php';
sda_careers_ensure($pdo);
sda_ax_close_overdue($pdo);

$jid = (int)($_GET['job'] ?? 0);
$st = $pdo->prepare("SELECT * FROM job_vacancies WHERE id = ?");
$st->execute([$jid]);
$job = $st->fetch();
if (!$job) { ax_flash('err', 'Position not found.'); ax_back('careers'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $act = $_POST['bulk'] ?? '';
    if (!$ids) { ax_flash('err', 'Tick at least one applicant first.'); ax_back('career-applications?job=' . $jid . '&status=' . urlencode($_GET['status'] ?? 'all')); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $own = $pdo->prepare("SELECT id FROM job_applications WHERE job_id = ? AND id IN ($in)");
    $own->execute(array_merge([$jid], $ids));
    $ids = array_map('intval', $own->fetchAll(PDO::FETCH_COLUMN));
    if ($act === 'archive' || $act === 'restore') {
        foreach ($ids as $i) { $pdo->prepare("UPDATE job_applications SET archived = ? WHERE id = ?")->execute([$act === 'archive' ? 1 : 0, $i]); sda_app_history($pdo, $i, ($act === 'archive' ? 'Archived' : 'Restored') . ' by ' . $user['name']); }
        ax_flash('ok', count($ids) . ' application' . (count($ids) === 1 ? '' : 's') . ($act === 'archive' ? ' archived.' : ' restored.'));
    } elseif ($act === 'delete') {
        foreach ($ids as $i) sda_app_delete($pdo, $i);
        try { $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'career_delete', ?, ?)")->execute([$user['id'], 'Deleted ' . count($ids) . ' application(s) for job #' . $jid . ': #' . implode(', #', $ids), $_SERVER['REMOTE_ADDR'] ?? '']); } catch (Throwable $e) {}
        ax_flash('ok', count($ids) . ' application' . (count($ids) === 1 ? '' : 's') . ' permanently deleted.');
    }
    ax_back('career-applications?job=' . $jid . '&status=' . urlencode($_GET['status'] ?? 'all'));
}

$filter = $_GET['status'] ?? 'all';
$all = $pdo->prepare("SELECT * FROM job_applications WHERE job_id = ?
                      ORDER BY final_score IS NULL, final_score DESC, (auto_score / NULLIF(max_score, 0)) DESC, submitted_at IS NULL, created_at");
$all->execute([$jid]);
$everything = $all->fetchAll();
$archived = array_values(array_filter($everything, fn($a) => !empty($a['archived'])));
$all = array_values(array_filter($everything, fn($a) => empty($a['archived'])));
$counts = ['all' => count($all), 'pending' => 0, 'submitted' => 0, 'shortlist' => 0, 'rejected' => 0, 'archived' => count($archived)];
foreach ($all as $a) {
    if (in_array($a['status'], ['received', 'invited', 'in_progress'], true)) $counts['pending']++;
    if (in_array($a['status'], ['submitted', 'evaluated'], true)) $counts['submitted']++;
    if (in_array($a['status'], ['shortlisted', 'interview', 'offered'], true)) $counts['shortlist']++;
    if ($a['status'] === 'rejected') $counts['rejected']++;
}
$groups = ['pending' => ['received', 'invited', 'in_progress'], 'submitted' => ['submitted', 'evaluated'], 'shortlist' => ['shortlisted', 'interview', 'offered'], 'rejected' => ['rejected']];
$rows = $filter === 'archived' ? $archived : ($filter === 'all' || !isset($groups[$filter]) ? $all : array_values(array_filter($all, fn($a) => in_array($a['status'], $groups[$filter], true))));

$endLbl = ['submitted' => 'Submitted', 'time_up' => 'Time ran out', 'violations' => 'Auto-submitted (integrity)'];

if (($_GET['export'] ?? '') === 'csv') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="applications-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($job['title'])) . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Rank', 'Name', 'Email', 'Phone', 'Town', 'Status', 'Final score %', 'Auto-marked', 'Written', 'Out of', 'Recommendation', 'Integrity warnings', 'How it ended', 'Started', 'Submitted', 'Applied']);
    $rank = 0;
    foreach ($all as $a) {
        $pct = $a['final_score'] !== null ? (float)$a['final_score'] : null;
        fputcsv($out, [$pct !== null ? ++$rank : '', $a['name'], $a['email'], $a['phone'], $a['town'], sda_app_status_label($a['status'])[0],
            $pct ?? '', $a['auto_score'] ?? '', $a['manual_score'] ?? '', $a['max_score'] ?? '', sda_ax_recommend($pct)[0], (int)$a['violations'],
            $endLbl[$a['end_reason'] ?? ''] ?? '', $a['started_at'] ?? '', $a['submitted_at'] ?? '', $a['created_at']]);
    }
    exit;
}

require_once 'includes/sidebar.php';
?>
<main class="main-content">
    <?php ax_header($job['title'], '', 'Applications ranked by final score',
        '<a class="ax-btn ghost" href="careers"><i class="fas fa-arrow-left"></i> Positions</a> <a class="ax-btn ghost" href="career-assessment?job=' . $jid . '"><i class="fas fa-clipboard-question"></i> Assessment</a> <a class="ax-btn" href="career-applications?job=' . $jid . '&amp;export=csv"><i class="fas fa-file-csv"></i> Export CSV</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('inbox', $counts['all'], 'Applications', $counts['pending'] . ' not yet assessed') ?>
            <?= ax_stat('pen-ruler', $counts['submitted'], 'Assessed', 'Submitted or evaluated', 'amber') ?>
            <?= ax_stat('star', $counts['shortlist'], 'Shortlisted', 'Shortlisted, interview or offer', 'green') ?>
            <?= ax_stat('scale-balanced', '80 / 70 / 60', 'Score bands', 'Strongly rec. / Recommended / Review', 'violet') ?>
        </div>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-ranking-star"></i> Candidates</h2><p>Final scores appear once written answers are marked. Use scores together with the CV, the written work, integrity notes and the interview – never on their own.</p></div></div>
            <div class="ax-panel-b" style="padding-bottom:0">
                <div class="ax-toolbar">
                    <nav class="ax-tabs" aria-label="Filter">
                        <?php foreach (['all' => 'All', 'pending' => 'Not assessed', 'submitted' => 'Assessed', 'shortlist' => 'Shortlisted', 'rejected' => 'Not successful', 'archived' => 'Archived'] as $k => $l): ?>
                            <a href="career-applications?job=<?= $jid ?>&amp;status=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $counts[$k] ?></span></a>
                        <?php endforeach; ?>
                    </nav>
                    <label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search name or email…" data-filter-table="ca-table" aria-label="Search applications"></label>
                </div>
            </div>
            <?php if (!$rows): ?>
                <div class="ax-empty"><i class="fas fa-inbox"></i><b>No applications here yet</b>Applications arrive from the public Careers page.</div>
            <?php else: ?>
            <form method="post" id="bulkForm">
            <?= ax_csrf() ?>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 20px;border-bottom:1px solid var(--border-light)">
                <span style="font-size:.82rem;color:var(--text-muted)"><b id="selN">0</b> selected</span>
                <?php if ($filter === 'archived'): ?><button class="ax-btn ghost sm" name="bulk" value="restore"><i class="fas fa-box-open"></i> Restore</button>
                <?php else: ?><button class="ax-btn ghost sm" name="bulk" value="archive"><i class="fas fa-box-archive"></i> Archive</button><?php endif; ?>
                <button class="ax-btn danger sm" name="bulk" value="delete" data-confirm="Permanently delete the selected applications, including CVs and answers? This can't be undone." data-danger><i class="fas fa-trash"></i> Delete</button>
                <span style="font-size:.76rem;color:var(--text-muted)">Archive keeps the record but hides it. Delete removes it for good (use it to clear test applications).</span>
            </div>
            <div class="ax-table-wrap"><table class="ax-table cards" id="ca-table">
                <thead><tr><th style="width:34px"><input type="checkbox" id="selAll" aria-label="Select all"></th><th>#</th><th>Applicant</th><th>Score</th><th>Recommendation</th><th>Integrity</th><th>Status</th><th>Applied</th><th></th></tr></thead>
                <tbody>
                <?php $rank = 0; foreach ($rows as $a):
                    $pct = $a['final_score'] !== null ? (float)$a['final_score'] : null;
                    [$rec, $recC] = sda_ax_recommend($pct); [$sl, $sc] = sda_app_status_label($a['status']); $v = (int)$a['violations']; ?>
                    <tr>
                        <td data-label=""><input type="checkbox" name="ids[]" value="<?= (int)$a['id'] ?>" class="selOne" aria-label="Select <?= e($a['name']) ?>"></td>
                        <td data-label="Rank" class="num"><?= $pct !== null ? ++$rank : '—' ?></td>
                        <td data-label="Applicant"><div class="ax-who"><?= ax_avatar($a['name']) ?><div><b><?= e($a['name']) ?></b><small><?= e($a['email']) ?></small></div></div></td>
                        <td data-label="Score" class="num">
                            <?php if ($pct !== null): ?><b style="font-size:1.05rem"><?= rtrim(rtrim(number_format($pct, 1), '0'), '.') ?>%</b>
                            <?php elseif ($a['auto_score'] !== null): ?><span title="Auto-marked part only"><?= (float)$a['auto_score'] ?> / <?= (float)$a['max_score'] ?></span><div style="font-size:.72rem;color:var(--text-muted)">written not marked</div>
                            <?php else: ?>—<?php endif; ?></td>
                        <td data-label="Recommendation"><?= $pct !== null ? '<span class="ax-pill ' . $recC . '">' . e($rec) . '</span>' : '<span style="color:var(--text-muted);font-size:.8rem">—</span>' ?></td>
                        <td data-label="Integrity"><?php if (!$a['started_at']): ?><span style="color:var(--text-muted);font-size:.8rem">—</span>
                            <?php else: ?><span class="ax-pill <?= $v >= SDA_AX_MAX_STRIKES ? 'bad' : ($v ? 'warn' : 'ok') ?>"><i class="fas fa-<?= $v ? 'triangle-exclamation' : 'shield-halved' ?>"></i> <?= $v ?> warning<?= $v === 1 ? '' : 's' ?></span><?php endif; ?></td>
                        <td data-label="Status"><span class="ax-pill <?= $sc ?>"><?= e($sl) ?></span></td>
                        <td data-label="Applied" class="num"><?= e(ax_date($a['created_at'])) ?></td>
                        <td class="actions" data-label=""><a class="ax-btn <?= $a['status'] === 'submitted' ? '' : 'ghost' ?> sm" href="career-application?id=<?= (int)$a['id'] ?>"><i class="fas fa-<?= $a['status'] === 'submitted' ? 'pen-ruler' : 'eye' ?>"></i> <?= $a['status'] === 'submitted' ? 'Evaluate' : 'Open' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            </form>
            <?php endif; ?>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    var all = document.getElementById('selAll'); if (!all) return;
    var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.selOne')); };
    function upd() { var n = boxes().filter(function (b) { return b.checked; }).length; document.getElementById('selN').textContent = n; all.checked = n && n === boxes().length; }
    all.addEventListener('change', function () { boxes().forEach(function (b) { if (b.closest('tr').style.display !== 'none') b.checked = all.checked; }); upd(); });
    document.addEventListener('change', function (e) { if (e.target.classList.contains('selOne')) upd(); });
})();
</script>
</body></html>
