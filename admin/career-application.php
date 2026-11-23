<?php
/** Admin: one application – CV, assessment answers, integrity log, grading of written answers, final score and response. */
$pageTitle = 'Application';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/careers.php';
sda_careers_ensure($pdo);
sda_ax_close_overdue($pdo);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$load = function () use ($pdo, $id) {
    $st = $pdo->prepare("SELECT a.*, j.title AS job_title, j.assess_minutes, j.assess_on FROM job_applications a JOIN job_vacancies j ON j.id = a.job_id WHERE a.id = ?");
    $st->execute([$id]);
    return $st->fetch();
};
$app = $load();
if (!$app) { ax_flash('err', 'Application not found.'); ax_back('careers'); }
$self = 'career-application?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'grade') {
            $qs = [];
            foreach (sda_ax_questions($pdo, (int)$app['job_id']) as $q) if ($q['qtype'] === 'written') $qs[(int)$q['id']] = $q;
            $up = $pdo->prepare("INSERT INTO job_answers (application_id, question_id, answer, marks_awarded, feedback) VALUES (?, ?, NULL, ?, ?)
                                 ON DUPLICATE KEY UPDATE marks_awarded = VALUES(marks_awarded), feedback = VALUES(feedback)");
            foreach ((array)($_POST['marks'] ?? []) as $qid => $m) {
                $qid = (int)$qid;
                if (!isset($qs[$qid])) continue;
                $m = trim((string)$m);
                $val = $m === '' ? null : max(0, min((float)$qs[$qid]['marks'], (float)$m));
                $up->execute([$id, $qid, $val, mb_substr(trim((string)($_POST['feedback'][$qid] ?? '')), 0, 500) ?: null]);
            }
            sda_ax_score($pdo, $id);
            $pdo->prepare("UPDATE job_applications SET graded_at = NOW(), graded_by = ? WHERE id = ?")->execute([$user['id'], $id]);
            $now = $load();
            if ($now['final_score'] !== null && $now['status'] === 'submitted') $pdo->prepare("UPDATE job_applications SET status = 'evaluated' WHERE id = ?")->execute([$id]);
            sda_app_history($pdo, $id, 'Written answers marked by ' . $user['name'] . ($now['final_score'] !== null ? ' – final score ' . (float)$now['final_score'] . '%' : ' (some still unmarked)'));
            ax_flash('ok', $now['final_score'] !== null ? 'Marks saved. Final score: ' . rtrim(rtrim(number_format((float)$now['final_score'], 1), '0'), '.') . '%.' : 'Marks saved. Mark every written answer to get the final score.');
        } elseif ($act === 'respond') {
            $status = $_POST['new_status'] ?? '';
            if (!in_array($status, ['evaluated', 'shortlisted', 'interview', 'offered', 'rejected'], true)) { ax_flash('err', 'Choose a decision.'); ax_back($self); }
            $subject = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 200);
            $msg = trim((string)($_POST['message'] ?? ''));
            $send = !empty($_POST['send']);
            if ($send && ($subject === '' || mb_strlen($msg) < 10)) { ax_flash('err', 'Write a subject and a message, or untick "Email the applicant".'); ax_back($self); }
            if ($send && !empty($_POST['include_score']) && $app['final_score'] !== null)
                $msg .= "\n\nYour assessment score: " . rtrim(rtrim(number_format((float)$app['final_score'], 1), '0'), '.') . '%.';
            $pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?")->execute([$status, $id]);
            $line = 'Status set to "' . sda_app_status_label($status)[0] . '" by ' . $user['name'];
            if ($send) {
                $err = '';
                $ok = sda_app_respond($pdo, $app, $subject, $msg, $err);
                sda_app_history($pdo, $id, $line . ($ok ? '; emailed: "' . $subject . '"' : '; email FAILED: ' . $err));
                ax_flash($ok ? 'ok' : 'err', $ok ? 'Decision saved and emailed to the applicant.' : 'Decision saved, but the email failed: ' . $err);
            } else {
                sda_app_history($pdo, $id, $line . ' (no email)');
                ax_flash('ok', 'Status updated.');
            }
        } elseif ($act === 'notes') {
            $pdo->prepare("UPDATE job_applications SET staff_notes = ? WHERE id = ?")->execute([mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 5000), $id]);
            ax_flash('ok', 'Notes saved.');
        } elseif ($act === 'resend') {
            if ($app['started_at']) { ax_flash('err', 'The assessment has already been started.'); ax_back($self); }
            $err = '';
            $ok = sda_app_send_invite($pdo, $app, $app['job_title'], (int)$app['assess_minutes'], $err);
            ax_flash($ok ? 'ok' : 'err', $ok ? 'A new assessment link was emailed. Earlier links no longer work.' : 'Email failed: ' . $err);
        } elseif ($act === 'reset') {
            $pdo->prepare("DELETE FROM job_answers WHERE application_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE job_applications SET status = 'invited', started_at = NULL, deadline_at = NULL, submitted_at = NULL, end_reason = NULL, device_hash = NULL, opens = 0, qorder = NULL,
                           violations = 0, auto_score = NULL, manual_score = NULL, max_score = NULL, final_score = NULL,
                           integrity_log = CONCAT(COALESCE(integrity_log, ''), ?) WHERE id = ?")
                ->execute([date('Y-m-d H:i:s') . ' --- Attempt reset by ' . $user['name'] . " (previous answers deleted) ---\n", $id]);
            sda_app_history($pdo, $id, 'Assessment reset by ' . $user['name']);
            $err = '';
            $ok = sda_app_send_invite($pdo, $load(), $app['job_title'], (int)$app['assess_minutes'], $err);
            ax_flash($ok ? 'ok' : 'err', $ok ? 'Assessment reset and a new link emailed.' : 'Assessment reset, but the email failed: ' . $err);
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        ax_flash('err', 'Could not save: ' . $e->getMessage());
    }
    ax_back($self);
}

$questions = sda_ax_questions($pdo, (int)$app['job_id']);
$ans = $pdo->prepare("SELECT * FROM job_answers WHERE application_id = ?");
$ans->execute([$id]);
$ans = array_column($ans->fetchAll(), null, 'question_id');
$pct = $app['final_score'] !== null ? (float)$app['final_score'] : null;
[$rec, $recC] = sda_ax_recommend($pct);
[$sl, $sc] = sda_app_status_label($app['status']);
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 1), '0'), '.');

// Per-section breakdown
$secs = [];
foreach ($questions as $q) {
    $s = &$secs[$q['section']];
    $s['max'] = ($s['max'] ?? 0) + (float)$q['marks'];
    $a = $ans[$q['id']] ?? null;
    $got = $q['qtype'] === 'mcq' ? ($a && $a['answer'] !== null && $a['answer'] !== '' && (int)$a['answer'] === (int)$q['correct'] ? (float)$q['marks'] : 0) : ($a['marks_awarded'] ?? null);
    $s['got'] = ($s['got'] ?? 0) + (float)$got;
    if ($q['qtype'] === 'written' && $got === null && $a && trim((string)$a['answer']) !== '') $s['pending'] = true;
    unset($s);
}
$started = $app['started_at'] ? strtotime($app['started_at']) : null;
$used = $started && $app['submitted_at'] ? max(1, (int)round((strtotime($app['submitted_at']) - $started) / 60)) : null;
$endLbl = ['submitted' => ['Submitted by the applicant', 'ok'], 'time_up' => ['Time ran out', 'warn'], 'violations' => ['Auto-submitted after ' . SDA_AX_MAX_STRIKES . ' warnings', 'bad']];
$logLines = array_reverse(sda_lines($app['integrity_log']));
$flags = count(array_filter($logLines, fn($l) => !str_contains($l, '[STRIKE]') && preg_match('/Tried|shortcut|large block|another device/i', $l)));

$cvPath = $app['cv_file'] ? sda_careers_dir() . '/' . basename($app['cv_file']) : null;
$first = strtok($app['name'], ' ');
$tpl = [
    'shortlisted' => ['Shortlisted – ' . $app['job_title'], "Congratulations! Following your application and online assessment for the {$app['job_title']} position, you have been shortlisted for the next stage.\n\nWe will contact you shortly with interview details."],
    'interview'   => ['Interview invitation – ' . $app['job_title'], "Thank you for your application for the {$app['job_title']} position. We are pleased to invite you to an interview.\n\nDate: \nTime: \nVenue / video link: \n\nPlease reply to confirm your attendance and bring copies of your academic and professional certificates."],
    'offered'     => ['Offer – ' . $app['job_title'], "We are delighted to offer you the {$app['job_title']} position at Skope Digital Academy.\n\nYour offer letter with the terms of engagement will follow. Please reply to confirm that you accept."],
    'rejected'    => ['Your application – ' . $app['job_title'], "Thank you for your interest in the {$app['job_title']} position and for the time you spent on your application and assessment.\n\nAfter careful review, we will not be taking your application further at this time. We encourage you to apply for future openings at Skope Digital Academy."],
    'evaluated'   => ['Update on your application – ' . $app['job_title'], "Thank you for completing the assessment for the {$app['job_title']} position. Your application is under review and we will update you soon."],
];

require_once 'includes/sidebar.php';
?>
<style>
  .ca-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 18px; align-items: start; }
  .ca-score { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
  .ca-score div { border: 1px solid var(--border); border-radius: 14px; padding: 12px; background: var(--surface-2); font-size: .76rem; color: var(--text-muted); }
  .ca-score b { display: block; font-size: 1.25rem; color: var(--text-main); }
  .ca-big b { font-size: 2rem; }
  .ca-bar { height: 7px; border-radius: 9px; background: var(--border); overflow: hidden; margin-top: 6px; } .ca-bar i { display: block; height: 100%; background: linear-gradient(90deg, #00BFFF, #0080FF); }
  .ca-q { border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; background: var(--surface); }
  .ca-q h4 { margin: 0 0 8px; font-size: .9rem; color: var(--text-main); line-height: 1.5; white-space: pre-wrap; }
  .ca-q .meta { display: flex; justify-content: space-between; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); margin-bottom: 6px; }
  .ca-opt { font-size: .84rem; padding: 6px 10px; border-radius: 9px; margin-bottom: 4px; color: var(--text-dim); }
  .ca-opt.pick { outline: 2px solid #ef4444; color: var(--text-main); } .ca-opt.right { background: rgba(16,185,129,.12); color: var(--text-main); } .ca-opt.pick.right { outline-color: #10b981; }
  .ca-ans { white-space: pre-wrap; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; font-size: .88rem; line-height: 1.65; color: var(--text-main); max-height: 420px; overflow-y: auto; overflow-wrap: anywhere; }
  .ca-guide { font-size: .8rem; color: var(--text-dim); background: rgba(0,191,255,.07); border: 1px dashed rgba(0,191,255,.35); border-radius: 10px; padding: 8px 10px; margin: 10px 0; }
  .ca-mark { display: grid; grid-template-columns: 130px 1fr; gap: 10px; }
  .ca-log { font: .76rem/1.6 ui-monospace, Consolas, monospace; max-height: 300px; overflow-y: auto; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; white-space: pre-wrap; }
  .ca-log .s { color: #f97316; font-weight: 700; }
  .ca-sec { margin: 22px 0 10px; font: 800 .95rem 'Poppins', sans-serif; color: var(--text-main); display: flex; justify-content: space-between; }
  @media (max-width: 1100px) { .ca-grid { grid-template-columns: 1fr; } }
  @media (max-width: 560px) { .ca-mark { grid-template-columns: 1fr; } }
</style>
<main class="main-content">
    <?php ax_header($app['name'], '', $app['job_title'] . ' · applied ' . ax_date($app['created_at']),
        '<a class="ax-btn ghost" href="career-applications?job=' . (int)$app['job_id'] . '"><i class="fas fa-arrow-left"></i> All applicants</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <section class="ax-panel">
            <div class="ax-panel-b">
                <div class="ca-score">
                    <div class="ca-big"><b><?= $pct !== null ? $fmt($pct) . '%' : '—' ?></b>Final score</div>
                    <div><b><span class="ax-pill <?= $recC ?>" style="font-size:.8rem"><?= e($rec) ?></span></b>Recommendation</div>
                    <div><b><?= $app['auto_score'] !== null ? $fmt($app['auto_score']) : '—' ?></b>Auto-marked</div>
                    <div><b><?= $app['manual_score'] !== null ? $fmt($app['manual_score']) : ($app['submitted_at'] ? 'To mark' : '—') ?></b>Written (staff)</div>
                    <div><b><?= $app['max_score'] !== null ? $fmt($app['max_score']) : '—' ?></b>Out of</div>
                    <div><b><span class="ax-pill <?= $sc ?>"><?= e($sl) ?></span></b>Status</div>
                </div>
                <?php if ($secs && $app['submitted_at']): ?>
                <div class="ca-score" style="margin-top:10px">
                    <?php foreach (SDA_AX_SECTIONS as $k => $lbl): if (empty($secs[$k])) continue; $s = $secs[$k]; ?>
                        <div><b><?= $fmt($s['got']) ?> / <?= $fmt($s['max']) ?><?= !empty($s['pending']) ? ' <small style="font-size:.7rem;color:#f59e0b">+ unmarked</small>' : '' ?></b><?= e($lbl) ?><div class="ca-bar"><i style="width:<?= $s['max'] ? round($s['got'] / $s['max'] * 100) : 0 ?>%"></i></div></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="ca-grid">
            <div>
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-clipboard-check"></i> Assessment answers</h2><p><?= $app['submitted_at'] ? 'Multiple-choice answers are marked automatically. Mark each written answer against its guide, then save.' : ($app['started_at'] ? 'The applicant is taking the assessment now.' : 'Not started yet.') ?></p></div></div>
                    <div class="ax-panel-b">
                    <?php if (!$app['started_at']): ?>
                        <div class="ax-empty" style="padding:20px"><i class="fas fa-hourglass-half"></i><b>No answers yet</b><?= $app['invite_sent_at'] ? 'Link sent ' . e(ax_date($app['invite_sent_at'], 'M j, g:i a')) . ', expires ' . e(ax_date($app['invite_expires_at'], 'M j, g:i a')) . '.' : 'No link sent.' ?></div>
                    <?php else: ?>
                        <form method="post" class="ax-form">
                            <?= ax_csrf() ?><input type="hidden" name="action" value="grade"><input type="hidden" name="id" value="<?= $id ?>">
                            <?php $cur = ''; $n = 0; foreach ($questions as $q): $n++; $a = $ans[$q['id']] ?? null;
                                if ($q['section'] !== $cur): $cur = $q['section']; ?><div class="ca-sec"><span><?= e(SDA_AX_SECTIONS[$cur] ?? $cur) ?></span><span style="font-size:.8rem;color:var(--text-muted)"><?= !empty($secs[$cur]) ? $fmt($secs[$cur]['got']) . ' / ' . $fmt($secs[$cur]['max']) : '' ?></span></div><?php endif; ?>
                                <div class="ca-q">
                                    <?php if ($q['qtype'] === 'mcq'): $opts = json_decode((string)$q['options'], true) ?: []; $pick = $a && $a['answer'] !== null && $a['answer'] !== '' ? (int)$a['answer'] : null; $ok = $pick !== null && $pick === (int)$q['correct']; ?>
                                        <div class="meta"><span>Q<?= $n ?> · multiple choice</span><span style="color:<?= $ok ? '#10b981' : '#ef4444' ?>"><?= $ok ? $fmt($q['marks']) : 0 ?> / <?= $fmt($q['marks']) ?> <?= $pick === null ? '· not answered' : '' ?></span></div>
                                        <h4><?= e($q['prompt']) ?></h4>
                                        <?php foreach ($opts as $i => $o): ?><div class="ca-opt<?= $i === $pick ? ' pick' : '' ?><?= $i === (int)$q['correct'] ? ' right' : '' ?>"><?= $i === (int)$q['correct'] ? '<i class="fas fa-check" style="color:#10b981"></i> ' : '' ?><?= e($o) ?></div><?php endforeach; ?>
                                    <?php else: $txt = trim((string)($a['answer'] ?? '')); $words = $txt === '' ? 0 : count(preg_split('/\s+/', $txt)); ?>
                                        <div class="meta"><span>Q<?= $n ?> · written · <?= $words ?> words</span><span><?= $fmt($q['marks']) ?> marks</span></div>
                                        <h4><?= e($q['prompt']) ?></h4>
                                        <div class="ca-ans"><?= $txt === '' ? '<i style="color:var(--text-muted)">No answer (scores 0).</i>' : e($txt) ?></div>
                                        <?php if ($q['guide']): ?><div class="ca-guide"><b>Marking guide:</b> <?= e($q['guide']) ?></div><?php endif; ?>
                                        <?php if ($app['submitted_at'] && $txt !== ''): ?>
                                        <div class="ca-mark">
                                            <div class="ax-field" style="margin:0"><label>Marks (0–<?= $fmt($q['marks']) ?>)</label><input type="number" name="marks[<?= (int)$q['id'] ?>]" min="0" max="<?= (float)$q['marks'] ?>" step="0.5" value="<?= $a && $a['marks_awarded'] !== null ? e((string)(float)$a['marks_awarded']) : '' ?>"></div>
                                            <div class="ax-field" style="margin:0"><label>Reviewer note (internal)</label><input type="text" name="feedback[<?= (int)$q['id'] ?>]" maxlength="500" value="<?= e($a['feedback'] ?? '') ?>" placeholder="Why this mark"></div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($app['submitted_at']): ?><div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save marks &amp; calculate score</button></div><?php endif; ?>
                        </form>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-envelope-open-text"></i> Cover letter</h2></div></div>
                    <div class="ax-panel-b"><div class="ca-ans"><?= e($app['cover_letter']) ?></div></div>
                </section>
            </div>

            <div>
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-id-card"></i> Applicant</h2></div></div>
                    <div class="ax-panel-b">
                        <dl class="ax-kv">
                            <dt>Email</dt><dd><a href="mailto:<?= e($app['email']) ?>"><?= e($app['email']) ?></a></dd>
                            <dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/\s+/', '', $app['phone'])) ?>"><?= e($app['phone']) ?></a></dd>
                            <dt>Town</dt><dd><?= e($app['town'] ?: '—') ?></dd>
                            <dt>CV</dt><dd><?= $cvPath && is_file($cvPath) ? '<a class="ax-btn ghost sm" href="career-cv?id=' . $id . '"><i class="fas fa-file-arrow-down"></i> ' . e($app['cv_name'] ?: 'Download CV') . '</a>' : '<span style="color:var(--text-muted)">Missing</span>' ?></dd>
                            <dt>Portfolio</dt><dd><?= $app['portfolio_url'] ? '<a href="' . e($app['portfolio_url']) . '" target="_blank" rel="noopener noreferrer">' . e(mb_strimwidth(preg_replace('#^https?://#', '', $app['portfolio_url']), 0, 40, '…')) . '</a>' : '—' ?></dd>
                            <dt>Applied from</dt><dd><?= e($app['ip'] ?: '—') ?></dd>
                        </dl>
                        <?php if (!$app['started_at'] && $app['assess_on']): ?>
                        <form method="post" style="margin-top:14px"><?= ax_csrf() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= $id ?>">
                            <button class="ax-btn ghost sm"><i class="fas fa-paper-plane"></i> <?= $app['invite_sent_at'] ? 'Send a new assessment link' : 'Send assessment link' ?></button></form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-shield-halved"></i> Integrity</h2><p>Strikes end the test at <?= SDA_AX_MAX_STRIKES ?>. Other events are recorded for your judgement.</p></div></div>
                    <div class="ax-panel-b">
                        <?php if (!$app['started_at']): ?><p style="color:var(--text-muted);font-size:.85rem;margin:0">Nothing yet – the assessment hasn't been started.</p>
                        <?php else: [$el, $ec] = $endLbl[$app['end_reason'] ?? ''] ?? [$app['status'] === 'in_progress' ? 'In progress' : '—', 'info']; ?>
                        <div class="ca-score" style="margin-bottom:12px">
                            <div><b class="<?= (int)$app['violations'] ? '' : '' ?>" style="color:<?= (int)$app['violations'] >= SDA_AX_MAX_STRIKES ? '#ef4444' : ((int)$app['violations'] ? '#f59e0b' : '#10b981') ?>"><?= (int)$app['violations'] ?> / <?= SDA_AX_MAX_STRIKES ?></b>Strikes</div>
                            <div><b><?= $flags ?></b>Other flags</div>
                            <div><b><?= $used !== null ? $used . ' / ' . (int)round((strtotime($app['deadline_at']) - $started) / 60) : '—' ?></b>Minutes used</div>
                        </div>
                        <p style="font-size:.84rem;margin:0 0 10px"><span class="ax-pill <?= $ec ?>"><?= e($el) ?></span> <?= $app['started_at'] ? 'Started ' . e(ax_date($app['started_at'], 'M j, g:i a')) : '' ?></p>
                        <div class="ca-log"><?php foreach ($logLines as $l): ?><div class="<?= str_contains($l, '[STRIKE]') ? 's' : '' ?>"><?= e($l) ?></div><?php endforeach; ?></div>
                        <form method="post" style="margin-top:12px" data-confirm="Reset this assessment? The applicant's answers and score are deleted and they get a new link to take it again. Use only for a genuine technical problem." data-danger>
                            <?= ax_csrf() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $id ?>">
                            <button class="ax-btn danger sm"><i class="fas fa-rotate-left"></i> Reset &amp; allow a retake</button></form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-reply"></i> Decision &amp; response</h2><p>Update the status and, optionally, email the applicant.</p></div></div>
                    <div class="ax-panel-b">
                        <form method="post" class="ax-form" id="respForm">
                            <?= ax_csrf() ?><input type="hidden" name="action" value="respond"><input type="hidden" name="id" value="<?= $id ?>">
                            <div class="ax-field"><label for="ns">Decision</label><select id="ns" name="new_status" required>
                                <option value="">Choose…</option>
                                <?php foreach (['evaluated' => 'Under review (update)', 'shortlisted' => 'Shortlist', 'interview' => 'Invite to interview', 'offered' => 'Make an offer', 'rejected' => 'Not successful'] as $k => $l): ?><option value="<?= $k ?>" <?= $app['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="ax-field"><label for="subj">Email subject</label><input id="subj" name="subject" maxlength="200"></div>
                            <div class="ax-field"><label for="msg">Message</label><textarea id="msg" name="message" rows="8"></textarea><div class="hint">Starts with "Dear <?= e($app['name']) ?>," and ends with the recruitment team's sign-off.</div></div>
                            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:6px"><input type="checkbox" name="send" value="1" checked> Email the applicant</label>
                            <label style="display:flex;gap:8px;align-items:center;font-size:.85rem;margin-bottom:12px"><input type="checkbox" name="include_score" value="1" <?= $pct === null ? 'disabled' : '' ?>> Include their assessment score<?= $pct === null ? ' (after marking)' : '' ?></label>
                            <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-paper-plane"></i> Save decision</button></div>
                        </form>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-note-sticky"></i> Staff notes &amp; history</h2></div></div>
                    <div class="ax-panel-b">
                        <form method="post" class="ax-form"><?= ax_csrf() ?><input type="hidden" name="action" value="notes"><input type="hidden" name="id" value="<?= $id ?>">
                            <div class="ax-field"><textarea name="notes" rows="4" placeholder="Interview notes, references, panel comments… (never shown to the applicant)"><?= e($app['staff_notes'] ?? '') ?></textarea></div>
                            <div class="ax-form-actions"><button class="ax-btn ghost sm"><i class="fas fa-floppy-disk"></i> Save notes</button></div></form>
                        <div class="ca-log" style="margin-top:10px"><?php foreach (array_reverse(sda_lines($app['history'])) as $l): ?><div><?= e($l) ?></div><?php endforeach; ?></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    var T = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var ns = document.getElementById('ns'), s = document.getElementById('subj'), m = document.getElementById('msg'), touched = false;
    m.addEventListener('input', function () { touched = true; });
    function fill() { var t = T[ns.value]; if (!t) return; if (!touched || !m.value.trim()) { s.value = t[0]; m.value = t[1]; touched = false; } }
    ns.addEventListener('change', fill);
})();
</script>
</body></html>
