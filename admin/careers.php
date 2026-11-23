<?php
/**
 * Admin: Careers – vacancies shown on the public Careers page (careers.php),
 * plus where applications go (email or an online form link).
 */
$pageTitle = 'Careers';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/careers.php';
sda_careers_ensure($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save_job') {
            $jid = (int)($_POST['job_id'] ?? 0);
            $f = fn(string $k, int $max) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
            $title = $f('title', 160);
            $summary = $f('summary', 2000);
            $dept = $f('department', 80) ?: null;
            $loc = $f('location', 120) ?: null;
            $resp = $f('responsibilities', 6000);
            $reqs = $f('requirements', 6000);
            $type = in_array($_POST['job_type'] ?? '', SDA_JOB_TYPES, true) ? $_POST['job_type'] : 'Full-time';
            $deadline = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['deadline'] ?? '') ? $_POST['deadline'] : null;
            $status = ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open';
            $order = (int)($_POST['sort_order'] ?? 0);
            $mins = max(10, min(240, (int)($_POST['assess_minutes'] ?? 90)));
            $assessOn = empty($_POST['assess_on']) ? 0 : 1;
            if ($title === '' || $summary === '') { ax_flash('err', 'Give the position a title and a short summary.'); ax_back(); }
            $vals = [$title, $dept, $summary, $resp, $reqs, $type, $loc, $deadline, $status, $order, $mins, $assessOn];
            if ($jid) {
                $pdo->prepare("UPDATE job_vacancies SET title = ?, department = ?, summary = ?, responsibilities = ?, requirements = ?, job_type = ?, location = ?, deadline = ?, status = ?, sort_order = ?, assess_minutes = ?, assess_on = ? WHERE id = ?")
                    ->execute([...$vals, $jid]);
                ax_flash('ok', 'Position updated.');
            } else {
                if (!$order) $vals[9] = 10 + (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM job_vacancies")->fetchColumn();
                $pdo->prepare("INSERT INTO job_vacancies (title, department, summary, responsibilities, requirements, job_type, location, deadline, status, sort_order, assess_minutes, assess_on) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute($vals);
                ax_flash('ok', $status === 'open' ? 'Position posted. It now shows on the Careers page.' : 'Position saved as closed.');
            }
        } elseif ($act === 'job_status') {
            $status = ($_POST['status'] ?? '') === 'closed' ? 'closed' : 'open';
            $pdo->prepare("UPDATE job_vacancies SET status = ? WHERE id = ?")->execute([$status, (int)$_POST['job_id']]);
            ax_flash('ok', $status === 'closed' ? 'Position closed and hidden from the Careers page.' : 'Position reopened.');
        } elseif ($act === 'delete_job') {
            $n = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE job_id = ?", [(int)$_POST['job_id']]);
            if ($n) { ax_flash('err', "This position has $n application(s), so it can't be deleted. Close it instead."); ax_back(); }
            $pdo->prepare("DELETE FROM job_questions WHERE job_id = ?")->execute([(int)$_POST['job_id']]);
            $pdo->prepare("DELETE FROM job_vacancies WHERE id = ?")->execute([(int)$_POST['job_id']]);
            ax_flash('ok', 'Position deleted.');
        } elseif ($act === 'apply_channel') {
            $email = trim((string)($_POST['careers_apply_email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { ax_flash('err', 'Enter a valid email address.'); ax_back(); }
            $notify = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string)($_POST['careers_notify_emails'] ?? ''))), 'strlen');
            foreach ($notify as $n) if (!filter_var($n, FILTER_VALIDATE_EMAIL)) { ax_flash('err', '"' . $n . '" is not a valid email address.'); ax_back(); }
            save_setting($pdo, 'careers_apply_email', mb_substr($email, 0, 190));
            save_setting($pdo, 'careers_notify_emails', mb_substr(implode(', ', array_unique($notify)), 0, 1000));
            save_setting($pdo, 'careers_alerts', empty($_POST['careers_alerts']) ? '0' : '1');
            save_setting($pdo, 'careers_send_score', empty($_POST['careers_send_score']) ? '0' : '1');
            ax_flash('ok', 'Recruitment email settings saved.');
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        ax_flash('err', 'Could not save: ' . $e->getMessage());
    }
    ax_back();
}

sda_ax_close_overdue($pdo);
$jobs = $pdo->query("SELECT j.*, (SELECT COUNT(*) FROM job_applications a WHERE a.job_id = j.id AND a.archived = 0) AS apps,
                            (SELECT COUNT(*) FROM job_applications a WHERE a.job_id = j.id AND a.status = 'submitted' AND a.archived = 0) AS to_grade,
                            (SELECT COUNT(*) FROM job_applications a WHERE a.job_id = j.id) AS apps_all,
                            (SELECT COUNT(*) FROM job_questions q WHERE q.job_id = j.id) AS n_q,
                            (SELECT COALESCE(SUM(q.marks), 0) FROM job_questions q WHERE q.job_id = j.id) AS q_marks
                     FROM job_vacancies j ORDER BY j.status = 'closed', j.sort_order, j.id")->fetchAll();
$totalApps = array_sum(array_column($jobs, 'apps'));
$toGrade = array_sum(array_column($jobs, 'to_grade'));
$today = date('Y-m-d');
$live = count(array_filter($jobs, fn($j) => $j['status'] === 'open' && (!$j['deadline'] || $j['deadline'] >= $today)));
$expired = count(array_filter($jobs, fn($j) => $j['status'] === 'open' && $j['deadline'] && $j['deadline'] < $today));
$applyEmail = sda_careers_email();
$interviews = $pdo->query("SELECT a.id, a.name, a.email, a.phone, a.interview_at, a.interview_mins, a.interview_mode, a.interview_place, j.title AS job_title
                           FROM job_applications a JOIN job_vacancies j ON j.id = a.job_id
                           WHERE a.interview_at >= NOW() - INTERVAL 2 HOUR AND a.archived = 0 ORDER BY a.interview_at LIMIT 30")->fetchAll();
$alertTo = sda_careers_alert_recipients($pdo);

require_once 'includes/sidebar.php';
?>
<style>
  .cj-list { display: grid; gap: 14px; }
  .cj-card { border: 1px solid var(--border); border-radius: 18px; padding: 18px; background: var(--surface); display: grid; gap: 10px; }
  .cj-card.closed { background: var(--surface-2); opacity: .85; }
  .cj-top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
  .cj-card h3 { font: 800 1rem 'Poppins', sans-serif; color: var(--text-main); line-height: 1.35; margin: 0; }
  .cj-card p { font-size: .85rem; color: var(--text-dim); line-height: 1.55; margin: 0; }
  .cj-meta { display: flex; gap: 6px 14px; flex-wrap: wrap; font-size: .78rem; color: var(--text-dim); }
  .cj-meta i { margin-right: 4px; }
  .cj-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .cj-actions form { display: inline; }
</style>
<main class="main-content">
    <?php ax_header('Careers', '', 'Vacancies on the public Careers page',
        '<a class="ax-btn ghost" href="../careers" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View page</a> <button type="button" class="ax-btn" data-open="job-new"><i class="fas fa-plus"></i> New position</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('briefcase', number_format($live), 'Live on the site', 'Open and before their deadline', 'green') ?>
            <?= ax_stat('inbox', number_format($totalApps), 'Applications', 'All positions', 'violet') ?>
            <?= ax_stat('pen-ruler', number_format($toGrade), 'To evaluate', 'Written answers waiting for marks', $toGrade ? 'amber' : '') ?>
            <?= ax_stat('calendar-check', number_format(count($interviews)), 'Upcoming interviews', $interviews ? 'Next: ' . date('D j M, g:i a', strtotime($interviews[0]['interview_at'])) : 'None scheduled', $interviews ? 'green' : '') ?>
        </div>

        <?php if ($interviews): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-calendar-check"></i> Upcoming interviews</h2><p>Across all positions. Open a candidate to reschedule or cancel.</p></div></div>
            <div class="ax-table-wrap"><table class="ax-table cards">
                <thead><tr><th>When</th><th>Candidate</th><th>Position</th><th>Format</th><th></th></tr></thead>
                <tbody>
                <?php $lastDay = ''; foreach ($interviews as $iv): $ts = strtotime($iv['interview_at']); $day = date('Y-m-d', $ts); ?>
                    <tr>
                        <td data-label="When"><b><?= $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : e(date('D j M', $ts))) ?></b><div style="font-size:.8rem;color:var(--text-muted)"><?= e(date('g:i a', $ts)) ?> · <?= (int)$iv['interview_mins'] ?> min</div></td>
                        <td data-label="Candidate"><b><?= e($iv['name']) ?></b><div style="font-size:.78rem;color:var(--text-muted)"><?= e($iv['phone']) ?></div></td>
                        <td data-label="Position"><?= e($iv['job_title']) ?></td>
                        <td data-label="Format"><?= e(SDA_INTERVIEW_MODES[$iv['interview_mode']] ?? '') ?><?php if ($iv['interview_place']): ?><div style="font-size:.78rem;color:var(--text-muted)"><?= preg_match('#^https?://#', $iv['interview_place']) ? '<a href="' . e($iv['interview_place']) . '" target="_blank" rel="noopener">Join link</a>' : e(mb_strimwidth($iv['interview_place'], 0, 40, '…')) ?></div><?php endif; ?></td>
                        <td class="actions" data-label=""><a class="ax-btn ghost sm" href="career-application?id=<?= (int)$iv['id'] ?>#interview"><i class="fas fa-eye"></i> Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
        <?php endif; ?>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-briefcase"></i> Positions</h2><p>Open positions show on the Careers page in this order. A position hides itself after its deadline; close it to hide it sooner.</p></div>
                <button type="button" class="ax-btn ghost sm" data-open="job-new"><i class="fas fa-plus"></i> New position</button></div>
            <div class="ax-panel-b">
                <?php if (!$jobs): ?>
                    <div class="ax-empty"><i class="fas fa-briefcase"></i><b>No positions yet</b><button type="button" class="ax-btn sm" data-open="job-new" style="margin-top:12px">Post the first position</button></div>
                <?php else: ?>
                <div class="cj-list">
                    <?php foreach ($jobs as $j): $past = $j['deadline'] && $j['deadline'] < $today; $n = count(sda_lines($j['responsibilities'])); ?>
                    <article class="cj-card <?= $j['status'] === 'closed' ? 'closed' : '' ?>">
                        <div class="cj-top">
                            <h3><?= e($j['title']) ?></h3>
                            <?= $j['status'] === 'closed' ? '<span class="ax-pill">Closed</span>' : ($past ? '<span class="ax-pill warn">Deadline passed</span>' : '<span class="ax-pill ok">Live</span>') ?>
                        </div>
                        <p><?= e($j['summary']) ?></p>
                        <div class="cj-meta">
                            <?php if ($j['department']): ?><span><i class="fas fa-tag"></i><?= e($j['department']) ?></span><?php endif; ?>
                            <span><i class="fas fa-briefcase"></i><?= e($j['job_type']) ?></span>
                            <?php if ($j['location']): ?><span><i class="fas fa-location-dot"></i><?= e($j['location']) ?></span><?php endif; ?>
                            <span><i class="fas fa-calendar-day"></i><?= $j['deadline'] ? 'Apply by ' . e(ax_date($j['deadline'])) : 'No deadline set' ?></span>
                            <span><i class="fas fa-list-check"></i><?= $n ?> responsibilit<?= $n === 1 ? 'y' : 'ies' ?></span>
                            <span><i class="fas fa-stopwatch"></i><?= $j['assess_on'] ? (int)$j['n_q'] . ' questions · ' . (float)$j['q_marks'] . ' marks · ' . (int)$j['assess_minutes'] . ' min' : 'No assessment' ?></span>
                        </div>
                        <div class="cj-actions">
                            <a class="ax-btn sm" href="career-applications?job=<?= (int)$j['id'] ?>"><i class="fas fa-users"></i> Applications (<?= (int)$j['apps'] ?>)<?= $j['to_grade'] ? ' · ' . (int)$j['to_grade'] . ' to evaluate' : '' ?></a>
                            <a class="ax-btn ghost sm" href="career-assessment?job=<?= (int)$j['id'] ?>"><i class="fas fa-clipboard-question"></i> Assessment</a>
                            <button type="button" class="ax-btn ghost sm" data-open="job-<?= (int)$j['id'] ?>"><i class="fas fa-pen"></i> Edit</button>
                            <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="job_status"><input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>"><input type="hidden" name="status" value="<?= $j['status'] === 'closed' ? 'open' : 'closed' ?>">
                                <button class="ax-btn ghost sm"><i class="fas fa-<?= $j['status'] === 'closed' ? 'lock-open' : 'lock' ?>"></i> <?= $j['status'] === 'closed' ? 'Reopen' : 'Close' ?></button></form>
                            <?php if (!$j['apps_all']): ?><form method="post" data-confirm="Delete the position &quot;<?= e($j['title']) ?>&quot;? This can't be undone." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete_job"><input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                                <button class="ax-btn danger sm"><i class="fas fa-trash"></i> Delete</button></form><?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-paper-plane"></i> Recruitment email &amp; alerts</h2><p>Candidates' replies go to the recruitment email, which is also shown on the Careers page. Staff alerts tell your team when an application arrives and when an assessment is ready to mark.</p></div></div>
            <div class="ax-panel-b">
                <form method="post" class="ax-form">
                    <?= ax_csrf() ?><input type="hidden" name="action" value="apply_channel">
                    <div class="ax-row">
                        <div class="ax-field"><label for="c-email">Recruitment email (reply-to)</label><input id="c-email" type="email" name="careers_apply_email" value="<?= e($applyEmail) ?>" required maxlength="190"></div>
                        <div class="ax-field"><label for="c-notify">Send staff alerts to</label><input id="c-notify" type="text" name="careers_notify_emails" value="<?= e(setting('careers_notify_emails', '')) ?>" maxlength="1000" placeholder="Leave blank to alert every admin">
                            <div class="hint">Separate several addresses with commas. Now: <?= e(implode(', ', $alertTo) ?: 'nobody') ?></div></div>
                    </div>
                    <label style="display:flex;gap:10px;align-items:center;font-size:.88rem;margin:0 0 8px"><input type="checkbox" name="careers_alerts" value="1" <?= setting('careers_alerts', '1') !== '0' ? 'checked' : '' ?>> Email staff when an application arrives and when an assessment is finished</label>
                    <label style="display:flex;gap:10px;align-items:center;font-size:.88rem;margin:0 0 14px"><input type="checkbox" name="careers_send_score" value="1" <?= setting('careers_send_score', '1') !== '0' ? 'checked' : '' ?>> Email applicants their score (%) as soon as they finish the assessment</label>
                    <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save</button></div>
                </form>
            </div>
        </section>
    </div>
</main>

<?php foreach (array_merge([['id' => 0, 'title' => '', 'department' => '', 'summary' => '', 'responsibilities' => '', 'requirements' => '', 'job_type' => 'Full-time', 'location' => '', 'deadline' => null, 'status' => 'open', 'sort_order' => 0, 'assess_minutes' => 90, 'assess_on' => 1]], $jobs) as $j): $jid = (int)$j['id']; ?>
<dialog class="ax-modal" id="job-<?= $jid ?: 'new' ?>" aria-label="<?= $jid ? 'Edit position' : 'New position' ?>" style="width:min(720px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h3><i class="fas fa-<?= $jid ? 'pen' : 'plus' ?>"></i> <?= $jid ? 'Edit position' : 'New position' ?></h3><p>Shown on the public Careers page while open.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save_job"><input type="hidden" name="job_id" value="<?= $jid ?>">
            <div class="ax-field"><label for="j-t-<?= $jid ?>">Position title</label><input id="j-t-<?= $jid ?>" type="text" name="title" value="<?= e($j['title']) ?>" required maxlength="160" placeholder="e.g. Technology & Innovation Officer"></div>
            <div class="ax-field"><label for="j-s-<?= $jid ?>">Short summary</label><textarea id="j-s-<?= $jid ?>" name="summary" required rows="3" maxlength="2000" placeholder="One or two sentences about the role."><?= e($j['summary']) ?></textarea></div>
            <div class="ax-field"><label for="j-r-<?= $jid ?>">Key responsibilities</label><textarea id="j-r-<?= $jid ?>" name="responsibilities" rows="6" placeholder="One per line"><?= e($j['responsibilities']) ?></textarea><div class="hint">One responsibility per line.</div></div>
            <div class="ax-field"><label for="j-q-<?= $jid ?>">Requirements (optional)</label><textarea id="j-q-<?= $jid ?>" name="requirements" rows="4" placeholder="One per line, e.g. Diploma or degree in a related field"><?= e($j['requirements'] ?? '') ?></textarea></div>
            <div class="ax-row">
                <div class="ax-field"><label for="j-d-<?= $jid ?>">Department</label><input id="j-d-<?= $jid ?>" type="text" name="department" value="<?= e($j['department'] ?? '') ?>" maxlength="80" placeholder="e.g. Technology"></div>
                <div class="ax-field"><label for="j-y-<?= $jid ?>">Type</label><select id="j-y-<?= $jid ?>" name="job_type"><?php foreach (SDA_JOB_TYPES as $t): ?><option <?= $j['job_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
                <div class="ax-field"><label for="j-l-<?= $jid ?>">Location</label><input id="j-l-<?= $jid ?>" type="text" name="location" value="<?= e($j['location'] ?? '') ?>" maxlength="120" placeholder="e.g. Kisumu / Remote"></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="j-e-<?= $jid ?>">Application deadline</label><input id="j-e-<?= $jid ?>" type="date" name="deadline" value="<?= e($j['deadline'] ?? '') ?>"><div class="hint">Hidden from the site after this day. Blank = no deadline.</div></div>
                <div class="ax-field"><label for="j-st-<?= $jid ?>">Status</label><select id="j-st-<?= $jid ?>" name="status"><option value="open" <?= $j['status'] !== 'closed' ? 'selected' : '' ?>>Open – show on site</option><option value="closed" <?= $j['status'] === 'closed' ? 'selected' : '' ?>>Closed – hidden</option></select></div>
                <div class="ax-field"><label for="j-m-<?= $jid ?>">Assessment time (min)</label><input id="j-m-<?= $jid ?>" type="number" name="assess_minutes" min="10" max="240" value="<?= (int)$j['assess_minutes'] ?>"></div>
            </div>
            <label style="display:flex;gap:10px;align-items:center;font-size:.88rem;margin:-4px 0 14px"><input type="checkbox" name="assess_on" value="1" <?= $j['assess_on'] ? 'checked' : '' ?>> Send applicants the online assessment (edit the questions under "Assessment")</label>
            <div class="ax-row">
                <div class="ax-field"><label for="j-o-<?= $jid ?>">Order</label><input id="j-o-<?= $jid ?>" type="number" name="sort_order" value="<?= (int)$j['sort_order'] ?>" step="1"><div class="hint">Lower shows first.</div></div>
            </div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> <?= $jid ? 'Save changes' : 'Post position' ?></button></div>
        </div>
    </form>
</dialog>
<?php endforeach; ?>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>document.querySelectorAll('dialog.ax-modal').forEach(function(d){d.addEventListener('click',function(e){if(e.target===d)d.close();});});</script>
</body></html>
