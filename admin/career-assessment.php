<?php
/** Admin: a position's assessment – questions by section, marks, correct answers and marking guides. */
$pageTitle = 'Assessment';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/careers.php';
sda_careers_ensure($pdo);

$jid = (int)($_GET['job'] ?? $_POST['job'] ?? 0);
$st = $pdo->prepare("SELECT * FROM job_vacancies WHERE id = ?");
$st->execute([$jid]);
$job = $st->fetch();
if (!$job) { ax_flash('err', 'Position not found.'); ax_back('careers'); }
$self = 'career-assessment?job=' . $jid;
$started = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE job_id = ? AND started_at IS NOT NULL", [$jid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save_q') {
            $qid = (int)($_POST['qid'] ?? 0);
            $sec = array_key_exists($_POST['section'] ?? '', SDA_AX_SECTIONS) ? $_POST['section'] : 'knowledge';
            $type = ($_POST['qtype'] ?? '') === 'written' ? 'written' : 'mcq';
            $prompt = trim((string)($_POST['prompt'] ?? ''));
            $marks = max(0.5, min(100, (float)($_POST['marks'] ?? 4)));
            $guide = mb_substr(trim((string)($_POST['guide'] ?? '')), 0, 3000) ?: null;
            $order = (int)($_POST['sort_order'] ?? 0);
            $opts = null; $correct = null;
            if ($prompt === '') { ax_flash('err', 'Write the question.'); ax_back($self); }
            if ($type === 'mcq') {
                $opts = array_values(array_filter(array_map(fn($o) => mb_substr(trim((string)$o), 0, 500), (array)($_POST['opt'] ?? [])), 'strlen'));
                $c = (int)($_POST['correct'] ?? -1);
                $raw = array_map(fn($o) => trim((string)$o), (array)($_POST['opt'] ?? []));
                // map the ticked box (index among all boxes) to its index among the filled-in options
                $map = []; $k = 0; foreach ($raw as $i => $o) if ($o !== '') $map[$i] = $k++;
                if (count($opts) < 2) { ax_flash('err', 'A multiple-choice question needs at least two options.'); ax_back($self); }
                if (!isset($map[$c])) { ax_flash('err', 'Tick the correct option (it must not be blank).'); ax_back($self); }
                $correct = $map[$c];
                $opts = json_encode($opts, JSON_UNESCAPED_UNICODE);
            }
            if (!$order) $order = 10 + (int)ax_scalar($pdo, "SELECT COALESCE(MAX(sort_order), 0) FROM job_questions WHERE job_id = ?", [$jid]);
            if ($qid) {
                $pdo->prepare("UPDATE job_questions SET section = ?, qtype = ?, prompt = ?, options = ?, correct = ?, marks = ?, guide = ?, sort_order = ? WHERE id = ? AND job_id = ?")
                    ->execute([$sec, $type, $prompt, $opts, $correct, $marks, $guide, $order, $qid, $jid]);
                ax_flash('ok', 'Question updated.' . ($started ? ' Scores for submitted assessments are recalculated.' : ''));
            } else {
                $pdo->prepare("INSERT INTO job_questions (job_id, section, qtype, prompt, options, correct, marks, guide, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$jid, $sec, $type, $prompt, $opts, $correct, $marks, $guide, $order]);
                ax_flash('ok', 'Question added.');
            }
        } elseif ($act === 'load_standard') {
            $bank = require __DIR__ . '/../includes/careers_seed.php';
            $set = $bank[$job['title']] ?? null;
            if (!$set) { ax_flash('err', 'There is no standard test for this position title.'); ax_back($self); }
            if ($started) { ax_flash('err', 'Someone has already taken this test. Delete or archive those applications first (deleting removes their answers), then try again.'); ax_back($self); }
            $pdo->prepare("DELETE FROM job_questions WHERE job_id = ?")->execute([$jid]);
            $ins = $pdo->prepare("INSERT INTO job_questions (job_id, section, qtype, prompt, options, correct, marks, guide, sort_order) VALUES (?, ?, 'mcq', ?, ?, ?, 1, NULL, ?)");
            $n = 0;
            foreach (['knowledge', 'situational'] as $sec) foreach ($set[$sec] as $q) $ins->execute([$jid, $sec, $q[0], json_encode($q[1], JSON_UNESCAPED_UNICODE), $q[2], $n += 10]);
            $pdo->prepare("UPDATE job_vacancies SET assess_minutes = 20 WHERE id = ?")->execute([$jid]);
            ax_flash('ok', 'Loaded the standard test: ' . $n / 10 . ' multiple-choice questions, 20 minutes.');
        } elseif ($act === 'delete_q') {
            if ($started) { ax_flash('err', 'Applicants have already taken this assessment, so questions can\'t be deleted. Edit them instead.'); ax_back($self); }
            $pdo->prepare("DELETE FROM job_questions WHERE id = ? AND job_id = ?")->execute([(int)$_POST['qid'], $jid]);
            ax_flash('ok', 'Question deleted.');
        }
        // Keep stored scores in line with the current answer key and marks
        foreach ($pdo->query("SELECT id FROM job_applications WHERE job_id = " . $jid . " AND submitted_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $aid) sda_ax_score($pdo, (int)$aid);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        ax_flash('err', 'Could not save: ' . $e->getMessage());
    }
    ax_back($self);
}

$questions = sda_ax_questions($pdo, $jid);
$total = array_sum(array_map(fn($q) => (float)$q['marks'], $questions));
$bySec = [];
foreach ($questions as $q) $bySec[$q['section']][] = $q;
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 1), '0'), '.');

require_once 'includes/sidebar.php';
?>
<style>
  .aq { border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; margin-bottom: 10px; background: var(--surface); display: grid; gap: 8px; }
  .aq-top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
  .aq p { margin: 0; font-size: .88rem; color: var(--text-main); line-height: 1.55; white-space: pre-wrap; }
  .aq ol { margin: 0; padding-left: 20px; font-size: .82rem; color: var(--text-dim); } .aq ol li.c { color: #10b981; font-weight: 700; }
  .aq small { color: var(--text-muted); font-size: .78rem; }
  .opt-row { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; } .opt-row input[type=text] { flex: 1; }
</style>
<main class="main-content">
    <?php ax_header('Assessment: ' . $job['title'], '', count($questions) . ' questions · ' . $fmt($total) . ' marks · ' . (int)$job['assess_minutes'] . ' minutes' . ($job['assess_on'] ? '' : ' · switched off'),
        '<a class="ax-btn ghost" href="careers"><i class="fas fa-arrow-left"></i> Positions</a> '
        . '<form method="post" style="display:inline" data-confirm="Replace all questions for this position with the standard test (15 multiple-choice questions, 20 minutes)? Your custom questions will be removed.">' . ax_csrf() . '<input type="hidden" name="action" value="load_standard"><input type="hidden" name="job" value="' . $jid . '"><button class="ax-btn ghost"' . ($started ? ' disabled title="Someone has already taken this test"' : '') . '><i class="fas fa-rotate"></i> Use the standard 15-question test</button></form> '
        . '<button type="button" class="ax-btn" data-open="q-new"><i class="fas fa-plus"></i> New question</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <?php if ($started): ?><div class="ax-panel" style="padding:12px 16px;border-color:#f59e0b;font-size:.86rem"><i class="fas fa-triangle-exclamation" style="color:#f59e0b"></i> <?= $started ?> applicant<?= $started === 1 ? ' has' : 's have' ?> already taken this assessment. Changing answers or marks recalculates their scores; keep changes to fixing mistakes so everyone is assessed fairly.</div><?php endif; ?>
        <?php if (abs($total - 100) > 0.01 && $questions): ?><div class="ax-panel" style="padding:12px 16px;font-size:.86rem"><i class="fas fa-circle-info" style="color:#0ea5e9"></i> Marks add up to <?= $fmt($total) ?>. Scores are shown as a percentage, so this is fine, but 100 is easiest to read.</div><?php endif; ?>
        <?php foreach (SDA_AX_SECTIONS as $sec => $label): $list = $bySec[$sec] ?? []; $sm = array_sum(array_map(fn($q) => (float)$q['marks'], $list)); ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><?= e($label) ?></h2><p><?= count($list) ?> question<?= count($list) === 1 ? '' : 's' ?> · <?= $fmt($sm) ?> marks. Order is shuffled for each applicant.</p></div></div>
            <div class="ax-panel-b">
                <?php if (!$list): ?><p style="color:var(--text-muted);font-size:.85rem;margin:0">No questions in this part.</p><?php endif; ?>
                <?php foreach ($list as $q): ?>
                <div class="aq">
                    <div class="aq-top"><span class="ax-pill <?= $q['qtype'] === 'mcq' ? 'info' : 'violet' ?>"><?= $q['qtype'] === 'mcq' ? 'Multiple choice · auto-marked' : 'Written · staff-marked' ?></span><b><?= $fmt($q['marks']) ?> marks</b></div>
                    <p><?= e($q['prompt']) ?></p>
                    <?php if ($q['qtype'] === 'mcq'): ?><ol><?php foreach (json_decode((string)$q['options'], true) ?: [] as $i => $o): ?><li class="<?= $i === (int)$q['correct'] ? 'c' : '' ?>"><?= e($o) ?><?= $i === (int)$q['correct'] ? ' ✓' : '' ?></li><?php endforeach; ?></ol>
                    <?php elseif ($q['guide']): ?><small><b>Marking guide:</b> <?= e($q['guide']) ?></small><?php endif; ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <button type="button" class="ax-btn ghost sm" data-open="q-<?= (int)$q['id'] ?>"><i class="fas fa-pen"></i> Edit</button>
                        <?php if (!$started): ?><form method="post" data-confirm="Delete this question?" data-danger style="display:inline"><?= ax_csrf() ?><input type="hidden" name="action" value="delete_q"><input type="hidden" name="job" value="<?= $jid ?>"><input type="hidden" name="qid" value="<?= (int)$q['id'] ?>"><button class="ax-btn danger sm"><i class="fas fa-trash"></i> Delete</button></form><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
</main>

<?php foreach (array_merge([['id' => 0, 'section' => 'knowledge', 'qtype' => 'mcq', 'prompt' => '', 'options' => '[]', 'correct' => 0, 'marks' => 4, 'guide' => '', 'sort_order' => 0]], $questions) as $q):
    $qid = (int)$q['id']; $opts = json_decode((string)$q['options'], true) ?: []; $opts = array_pad($opts, max(4, count($opts)), ''); ?>
<dialog class="ax-modal" id="q-<?= $qid ?: 'new' ?>" style="width:min(720px,calc(100vw - 32px))">
    <form method="post" class="ax-form qform">
        <div class="ax-panel-h"><div><h3><?= $qid ? 'Edit question' : 'New question' ?></h3></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save_q"><input type="hidden" name="job" value="<?= $jid ?>"><input type="hidden" name="qid" value="<?= $qid ?>">
            <div class="ax-row">
                <div class="ax-field"><label>Part</label><select name="section"><?php foreach (SDA_AX_SECTIONS as $k => $l): ?><option value="<?= $k ?>" <?= $q['section'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="ax-field"><label>Type</label><select name="qtype" class="qtype"><option value="mcq" <?= $q['qtype'] === 'mcq' ? 'selected' : '' ?>>Multiple choice (auto-marked)</option><option value="written" <?= $q['qtype'] === 'written' ? 'selected' : '' ?>>Written answer (staff-marked)</option></select></div>
                <div class="ax-field"><label>Marks</label><input type="number" name="marks" min="0.5" max="100" step="0.5" value="<?= e((string)(float)$q['marks']) ?>"></div>
            </div>
            <div class="ax-field"><label>Question</label><textarea name="prompt" rows="4" required><?= e($q['prompt']) ?></textarea></div>
            <div class="mcq-only">
                <label style="font-weight:700;font-size:.82rem">Options – tick the correct one</label>
                <?php foreach ($opts as $i => $o): ?><div class="opt-row"><input type="radio" name="correct" value="<?= $i ?>" <?= $q['qtype'] === 'mcq' && $i === (int)$q['correct'] ? 'checked' : '' ?> aria-label="Correct"><input type="text" name="opt[]" value="<?= e($o) ?>" maxlength="500" placeholder="Option <?= $i + 1 ?>"></div><?php endforeach; ?>
            </div>
            <div class="ax-field written-only"><label>Marking guide (only staff see this)</label><textarea name="guide" rows="3" placeholder="What earns the marks, e.g. Clear outcomes (8) · Practical activities (10) …"><?= e($q['guide'] ?? '') ?></textarea></div>
            <div class="ax-field" style="max-width:160px"><label>Order</label><input type="number" name="sort_order" value="<?= (int)$q['sort_order'] ?>"></div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save</button></div>
        </div>
    </form>
</dialog>
<?php endforeach; ?>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
document.querySelectorAll('dialog.ax-modal').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });
document.querySelectorAll('.qform').forEach(function (f) {
    var sel = f.querySelector('.qtype');
    function sync() { var m = sel.value === 'mcq'; f.querySelector('.mcq-only').style.display = m ? '' : 'none'; f.querySelector('.written-only').style.display = m ? 'none' : ''; }
    sel.addEventListener('change', sync); sync();
});
</script>
</body></html>
