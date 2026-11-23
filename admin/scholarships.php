<?php
/**
 * Admin: Scholarships – funding schemes and the applications learners send.
 * An approved application lets the learner enrol free in up to 5 courses (see enroll.php).
 */
$pageTitle = 'Scholarships';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save_scheme') {
            $sid = (int)($_POST['scholarship_id'] ?? 0);
            $title = mb_substr(trim($_POST['title'] ?? ''), 0, 255);
            $desc = trim($_POST['description'] ?? '');
            $amount = max(0, (float)($_POST['amount'] ?? 0));
            $expiry = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['expiry_date'] ?? '') ? $_POST['expiry_date'] : null;
            $status = ($_POST['status'] ?? 'active') === 'closed' ? 'closed' : 'active';
            if ($title === '' || $desc === '') { ax_flash('err', 'Give the scheme a name and a description.'); ax_back(); }
            if ($sid) {
                $pdo->prepare("UPDATE scholarships SET title = ?, description = ?, amount = ?, expiry_date = ?, status = ? WHERE id = ?")
                    ->execute([$title, $desc, $amount, $expiry, $status, $sid]);
                ax_flash('ok', 'Scheme updated.');
            } else {
                $pdo->prepare("INSERT INTO scholarships (title, description, amount, expiry_date, status) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$title, $desc, $amount, $expiry, $status]);
                ax_flash('ok', 'New scheme posted. Learners can apply from the Scholarships page.');
            }
        } elseif ($act === 'scheme_status') {
            $status = ($_POST['status'] ?? '') === 'closed' ? 'closed' : 'active';
            $pdo->prepare("UPDATE scholarships SET status = ? WHERE id = ?")->execute([$status, (int)$_POST['scholarship_id']]);
            ax_flash('ok', $status === 'closed' ? 'Scheme closed to new applications.' : 'Scheme reopened.');
        } elseif ($act === 'delete_scheme') {
            $sid = (int)$_POST['scholarship_id'];
            $n = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM scholarship_applications WHERE scholarship_id = ?", [$sid]);
            if ($n) { ax_flash('err', "This scheme has $n application(s), so it can't be deleted. Close it instead."); ax_back(); }
            $pdo->prepare("DELETE FROM scholarships WHERE id = ?")->execute([$sid]);
            ax_flash('ok', 'Scheme deleted.');
        } elseif ($act === 'decide') {
            $aid = (int)($_POST['aid'] ?? 0);
            $status = ($_POST['decision'] ?? '') === 'approve' ? 'approved' : 'rejected';
            $note = mb_substr(trim($_POST['note'] ?? ''), 0, 400);
            $a = $pdo->prepare("SELECT sa.*, s.title FROM scholarship_applications sa LEFT JOIN scholarships s ON s.id = sa.scholarship_id WHERE sa.id = ?");
            $a->execute([$aid]);
            $app = $a->fetch();
            if (!$app) { ax_flash('err', 'That application no longer exists.'); ax_back(); }
            $pdo->prepare("UPDATE scholarship_applications SET status = ? WHERE id = ?")->execute([$status, $aid]);
            $scheme = $app['title'] ? '"' . $app['title'] . '"' : 'the scholarship';
            notify($pdo, (int)$app['user_id'],
                $status === 'approved' ? 'Scholarship approved 🎉' : 'Scholarship decision',
                $status === 'approved'
                    ? "Your application for $scheme was approved. You can now enrol free in up to 5 courses from the course pages." . ($note !== '' ? " Note: $note" : '')
                    : "Your application for $scheme was not approved this time." . ($note !== '' ? " Reason: $note" : '') . ' You are welcome to apply again for future schemes.',
                'scholarship_decision', ['scholarship' => $app['title'] ?: 'the scholarship', 'status' => $status === 'approved' ? 'approved 🎉' : 'not approved this time',
                    'note' => $status === 'approved' ? 'You can now enrol free in up to 5 courses from the course pages.' . ($note !== '' ? " $note" : '') : ($note !== '' ? "Reason: $note. " : '') . 'You are welcome to apply again for future schemes.']);
            try {
                $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'scholarship_decision', ?, ?)")
                    ->execute([$user['id'], "Application #$aid $status" . ($note !== '' ? ": $note" : ''), $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Throwable $e) { error_log($e->getMessage()); }
            ax_flash('ok', 'Application ' . $status . ' and the learner was notified.');
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        ax_flash('err', 'Could not save: ' . $e->getMessage());
    }
    ax_back();
}

$schemes = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM scholarship_applications a WHERE a.scholarship_id = s.id) AS apps,
                               (SELECT COUNT(*) FROM scholarship_applications a WHERE a.scholarship_id = s.id AND a.status = 'pending') AS pending,
                               (SELECT COUNT(*) FROM scholarship_applications a WHERE a.scholarship_id = s.id AND a.status = 'approved') AS approved
                        FROM scholarships s ORDER BY s.status = 'closed', s.created_at DESC")->fetchAll();

$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) FROM scholarship_applications GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $n) {
    if (isset($counts[$k])) $counts[$k] = (int)$n;
    $counts['all'] += (int)$n;
}
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'all'], true) ? $_GET['status'] : ($counts['pending'] ? 'pending' : 'all');
$st = $pdo->prepare("SELECT sa.*, u.name AS student_name, u.email AS student_email, u.phone AS student_phone, u.avatar, u.created_at AS member_since,
                            s.title AS scheme_title, s.amount AS scheme_amount,
                            (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = sa.user_id AND e.status IN ('active','completed')) AS enrolments,
                            (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = sa.user_id AND e.status = 'completed') AS completed,
                            (SELECT COUNT(*) FROM scholarship_applications o WHERE o.user_id = sa.user_id AND o.id <> sa.id) AS other_apps
                     FROM scholarship_applications sa
                     LEFT JOIN users u ON u.id = sa.user_id
                     LEFT JOIN scholarships s ON s.id = sa.scholarship_id
                     " . ($status !== 'all' ? 'WHERE sa.status = ?' : '') . "
                     ORDER BY sa.status = 'pending' DESC, sa.created_at DESC");
$st->execute($status !== 'all' ? [$status] : []);
$apps = $st->fetchAll();

$activeSchemes = count(array_filter($schemes, fn($s) => $s['status'] === 'active' && (!$s['expiry_date'] || $s['expiry_date'] >= date('Y-m-d'))));
$awardValue = array_sum(array_map(fn($s) => (float)$s['amount'] * (int)$s['approved'], $schemes));

function sch_pill(string $s): string {
    return ['pending' => '<span class="ax-pill warn">Pending</span>', 'approved' => '<span class="ax-pill ok">Approved</span>', 'rejected' => '<span class="ax-pill bad">Rejected</span>'][$s] ?? '<span class="ax-pill">' . e($s) . '</span>';
}
function sch_doc(?string $f): ?string {
    if (!$f) return null;
    $f = basename($f);
    return is_file(__DIR__ . '/../uploads/scholarships/' . $f) ? '../uploads/scholarships/' . rawurlencode($f) : null;
}

require_once 'includes/sidebar.php';
?>
<style>
  .sc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
  .sc-card { border: 1px solid var(--border); border-radius: 18px; padding: 18px; display: flex; flex-direction: column; gap: 10px; background: var(--surface); }
  .sc-card.closed { background: var(--surface-2); }
  .sc-card h3 { font: 800 .98rem 'Poppins', sans-serif; color: var(--text-main); line-height: 1.35; }
  .sc-card p { font-size: .84rem; color: var(--text-dim); line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .sc-meta { display: flex; justify-content: space-between; gap: 10px; border-top: 1px solid var(--border-light); padding-top: 12px; margin-top: auto; font-size: .8rem; color: var(--text-dim); flex-wrap: wrap; }
  .sc-meta b { display: block; color: var(--text-main); font-size: .95rem; }
  .sc-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .sc-actions form { display: inline; }
  .sc-text { white-space: pre-wrap; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; font-size: .88rem; color: var(--text-main); line-height: 1.6; overflow-wrap: anywhere; max-height: 260px; overflow-y: auto; }
  .sc-doc img { max-width: 100%; max-height: 300px; border-radius: 12px; border: 1px solid var(--border); }
  .sc-decide { display: grid; gap: 10px; border-top: 1px solid var(--border-light); padding-top: 16px; margin-top: 6px; }
</style>
<main class="main-content">
    <?php ax_header('Scholarships', '', 'Schemes and applications',
        '<button type="button" class="ax-btn" data-open="scheme-new"><i class="fas fa-plus"></i> New scheme</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('inbox', number_format($counts['pending']), 'Applications to review', $counts['all'] . ' received in total', 'amber') ?>
            <?= ax_stat('user-graduate', number_format($counts['approved']), 'Approved', 'Each covers up to 5 free enrolments', 'green') ?>
            <?= ax_stat('graduation-cap', number_format($activeSchemes), 'Open schemes', count($schemes) . ' scheme' . (count($schemes) == 1 ? '' : 's') . ' in total') ?>
            <?= ax_stat('coins', ax_money($awardValue), 'Awarded value', 'Scheme value × approved applications', 'violet') ?>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-graduation-cap"></i> Applications</h2><p>Review each application, then approve or reject — the learner gets a notification either way.</p></div></div>
            <div class="ax-panel-b" style="padding-bottom:0">
                <div class="ax-toolbar">
                    <nav class="ax-tabs" aria-label="Application status">
                        <?php foreach (['pending' => 'To review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $l): ?>
                            <a href="scholarships?status=<?= $k ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $counts[$k] ?></span></a>
                        <?php endforeach; ?>
                    </nav>
                    <label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search applicant or scheme…" data-filter-table="sc-table" aria-label="Search applications"></label>
                </div>
            </div>
            <?php if (!$apps): ?>
                <div class="ax-empty"><i class="fas fa-inbox"></i><b><?= $status === 'pending' ? 'No applications waiting' : 'No applications here' ?></b>Learners apply from the public Scholarships page.</div>
            <?php else: ?>
            <div class="ax-table-wrap"><table class="ax-table cards" id="sc-table">
                <thead><tr><th>Applicant</th><th>Scheme</th><th>Status</th><th>Sent</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($apps as $a): ?>
                    <tr>
                        <td data-label="Applicant"><div class="ax-who"><?= ax_avatar($a['student_name'] ?? '?', $a['avatar'] ?? null) ?><div><b><?= e($a['student_name'] ?? 'Deleted account') ?></b><small><?= e($a['student_email'] ?? 'User #' . $a['user_id']) ?></small></div></div></td>
                        <td data-label="Scheme"><span class="strong"><?= e($a['scheme_title'] ?? 'Removed scheme') ?></span></td>
                        <td data-label="Status"><?= sch_pill((string)$a['status']) ?></td>
                        <td data-label="Sent" class="num"><?= e(ax_date($a['created_at'])) ?></td>
                        <td class="actions" data-label=""><button type="button" class="ax-btn <?= $a['status'] === 'pending' ? '' : 'ghost' ?> sm" data-open="app-<?= (int)$a['id'] ?>"><i class="fas fa-eye"></i> <?= $a['status'] === 'pending' ? 'Review' : 'View' ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-hand-holding-heart"></i> Schemes</h2><p>Open schemes are listed on the public Scholarships page. Close a scheme to stop new applications.</p></div>
                <button type="button" class="ax-btn ghost sm" data-open="scheme-new"><i class="fas fa-plus"></i> New scheme</button></div>
            <div class="ax-panel-b">
                <?php if (!$schemes): ?>
                    <div class="ax-empty"><i class="fas fa-graduation-cap"></i><b>No schemes yet</b><button type="button" class="ax-btn sm" data-open="scheme-new" style="margin-top:12px">Create the first scheme</button></div>
                <?php else: ?>
                <div class="sc-grid">
                    <?php foreach ($schemes as $s): $expired = $s['expiry_date'] && $s['expiry_date'] < date('Y-m-d'); ?>
                    <article class="sc-card <?= $s['status'] === 'closed' ? 'closed' : '' ?>">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                            <h3><?= e($s['title']) ?></h3>
                            <?= $s['status'] === 'closed' ? '<span class="ax-pill">Closed</span>' : ($expired ? '<span class="ax-pill warn">Deadline passed</span>' : '<span class="ax-pill ok">Open</span>') ?>
                        </div>
                        <p><?= e($s['description']) ?></p>
                        <div class="sc-meta">
                            <div><b><?= e(ax_money($s['amount'])) ?></b>Value</div>
                            <div><b><?= $s['expiry_date'] ? e(ax_date($s['expiry_date'])) : 'No deadline' ?></b>Deadline</div>
                            <div><b><?= (int)$s['apps'] ?></b><?= (int)$s['pending'] ?> pending · <?= (int)$s['approved'] ?> approved</div>
                        </div>
                        <div class="sc-actions">
                            <button type="button" class="ax-btn ghost sm" data-open="scheme-<?= (int)$s['id'] ?>"><i class="fas fa-pen"></i> Edit</button>
                            <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="scheme_status"><input type="hidden" name="scholarship_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="<?= $s['status'] === 'closed' ? 'active' : 'closed' ?>">
                                <button class="ax-btn ghost sm"><i class="fas fa-<?= $s['status'] === 'closed' ? 'lock-open' : 'lock' ?>"></i> <?= $s['status'] === 'closed' ? 'Reopen' : 'Close' ?></button></form>
                            <?php if (!$s['apps']): ?>
                            <form method="post" data-confirm="Delete the scheme &quot;<?= e($s['title']) ?>&quot;? This can't be undone." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete_scheme"><input type="hidden" name="scholarship_id" value="<?= (int)$s['id'] ?>">
                                <button class="ax-btn danger sm"><i class="fas fa-trash"></i> Delete</button></form>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php foreach (array_merge([['id' => 0, 'title' => '', 'description' => '', 'amount' => 0, 'expiry_date' => null, 'status' => 'active']], $schemes) as $s): $sid = (int)$s['id']; ?>
<dialog class="ax-modal" id="scheme-<?= $sid ?: 'new' ?>" aria-label="<?= $sid ? 'Edit scheme' : 'New scheme' ?>">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h3><i class="fas fa-<?= $sid ? 'pen' : 'plus' ?>"></i> <?= $sid ? 'Edit scheme' : 'New scheme' ?></h3><p>Shown to learners on the Scholarships page.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save_scheme"><input type="hidden" name="scholarship_id" value="<?= $sid ?>">
            <div class="ax-field"><label for="s-t-<?= $sid ?>">Name</label><input id="s-t-<?= $sid ?>" type="text" name="title" value="<?= e($s['title']) ?>" required maxlength="255" placeholder="e.g. Women in Tech Grant 2027"></div>
            <div class="ax-field"><label for="s-d-<?= $sid ?>">Description</label><textarea id="s-d-<?= $sid ?>" name="description" required rows="5" placeholder="Who can apply, what it covers and how you choose."><?= e($s['description']) ?></textarea></div>
            <div class="ax-row">
                <div class="ax-field"><label for="s-a-<?= $sid ?>">Value (<?= e(setting('currency')) ?>)</label><input id="s-a-<?= $sid ?>" type="number" name="amount" min="0" step="1" value="<?= e((string)(float)$s['amount']) ?>"><div class="hint">For your records; approval gives up to 5 free course enrolments.</div></div>
                <div class="ax-field"><label for="s-e-<?= $sid ?>">Application deadline</label><input id="s-e-<?= $sid ?>" type="date" name="expiry_date" value="<?= e($s['expiry_date'] ?? '') ?>"><div class="hint">Leave blank for no deadline.</div></div>
                <div class="ax-field"><label for="s-s-<?= $sid ?>">Status</label><select id="s-s-<?= $sid ?>" name="status"><option value="active" <?= $s['status'] !== 'closed' ? 'selected' : '' ?>>Open for applications</option><option value="closed" <?= $s['status'] === 'closed' ? 'selected' : '' ?>>Closed</option></select></div>
            </div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> <?= $sid ? 'Save changes' : 'Post scheme' ?></button></div>
        </div>
    </form>
</dialog>
<?php endforeach; ?>

<?php foreach ($apps as $a): $doc = sch_doc($a['document_file']); $isImg = $doc && in_array(strtolower(pathinfo($a['document_file'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true); ?>
<dialog class="ax-modal" id="app-<?= (int)$a['id'] ?>" aria-label="Application #<?= (int)$a['id'] ?>" style="width:min(720px,calc(100vw - 32px))">
    <div class="ax-panel-h"><div><h3><i class="fas fa-user-graduate"></i> <?= e($a['student_name'] ?? 'Deleted account') ?></h3><p><?= e($a['scheme_title'] ?? 'Removed scheme') ?> · <?= sch_pill((string)$a['status']) ?></p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <div class="ax-panel-b">
        <dl class="ax-kv" style="margin-bottom:18px">
            <dt>Email</dt><dd><?= e($a['student_email'] ?? '—') ?></dd>
            <dt>Phone</dt><dd><?= e($a['student_phone'] ?: '—') ?></dd>
            <dt>Member since</dt><dd><?= e(ax_date($a['member_since'])) ?></dd>
            <dt>Learning so far</dt><dd><?= (int)$a['enrolments'] ?> course<?= $a['enrolments'] == 1 ? '' : 's' ?> enrolled, <?= (int)$a['completed'] ?> completed</dd>
            <dt>Other applications</dt><dd><?= (int)$a['other_apps'] ?></dd>
            <dt>Applied</dt><dd><?= e(ax_date($a['created_at'], 'M j, Y g:i a')) ?></dd>
            <?php if ($a['student_name']): ?><dt>Profile</dt><dd><a href="user-profile?id=<?= (int)$a['user_id'] ?>">Open learner profile</a></dd><?php endif; ?>
        </dl>
        <div class="ax-field"><label>Why they are applying</label><div class="sc-text"><?= e($a['sop']) ?></div></div>
        <div class="ax-field"><label>Education background</label><div class="sc-text"><?= e($a['academic_background']) ?></div></div>
        <?php if ($doc): ?><div class="ax-field sc-doc"><label>Supporting document</label>
            <?= $isImg ? '<a href="' . e($doc) . '" target="_blank" rel="noopener"><img src="' . e($doc) . '" alt="Supporting document" loading="lazy"></a>' : '<a class="ax-btn ghost sm" href="' . e($doc) . '" target="_blank" rel="noopener"><i class="fas fa-file-lines"></i> Open document</a>' ?></div>
        <?php elseif ($a['document_file']): ?><p class="muted" style="font-size:.8rem;color:var(--text-muted)">Document file missing (<?= e($a['document_file']) ?>).</p><?php endif; ?>

        <form method="post" class="ax-form sc-decide">
            <?= ax_csrf() ?><input type="hidden" name="action" value="decide"><input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
            <div class="ax-field" style="margin:0"><label for="n-<?= (int)$a['id'] ?>">Message to the learner (optional)</label><input id="n-<?= (int)$a['id'] ?>" type="text" name="note" maxlength="400" placeholder="Added to the notification"></div>
            <div class="ax-form-actions">
                <?php if ($a['status'] !== 'rejected'): ?><button class="ax-btn danger" name="decision" value="reject" data-confirm="Reject this application? The learner is notified." data-danger><i class="fas fa-xmark"></i> Reject</button><?php endif; ?>
                <?php if ($a['status'] !== 'approved'): ?><button class="ax-btn ok" name="decision" value="approve" data-confirm="Approve this application? The learner can then enrol free in up to 5 courses."><i class="fas fa-check"></i> Approve</button><?php endif; ?>
            </div>
            <?php if ($a['status'] !== 'pending'): ?><div class="hint" style="font-size:.76rem;color:var(--text-muted);text-align:right">Already <?= e($a['status']) ?> — you can change the decision.</div><?php endif; ?>
        </form>
    </div>
</dialog>
<?php endforeach; ?>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>document.querySelectorAll('dialog.ax-modal').forEach(function(d){d.addEventListener('click',function(e){if(e.target===d)d.close();});});</script>
</body></html>
