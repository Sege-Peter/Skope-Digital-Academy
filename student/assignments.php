<?php
$pageTitle = 'Assignments';
require_once 'includes/header.php';

try {
    // Assignments for enrolled courses, with this student's submission (if any)
    $stmt = $pdo->prepare("SELECT a.*, c.title as course_title,
                           (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_status,
                           (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_score,
                           (SELECT notes FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_notes,
                           (SELECT feedback FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_feedback,
                           (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_date
                           FROM assignments a
                           JOIN courses c ON a.course_id = c.id
                           JOIN enrollments e ON e.course_id = c.id
                           WHERE e.student_id = ? AND e.status = 'active'
                           ORDER BY a.due_date ASC");
    $stmt->execute(array_fill(0, 6, $student['id']));
    $assignments = $stmt->fetchAll();
} catch (Exception $e) { error_log('student assignments: ' . $e->getMessage()); $assignments = []; }

// Sort into pending / submitted (waiting for a grade) / graded
$groups = ['pending' => [], 'submitted' => [], 'graded' => []];
foreach ($assignments as $a) {
    if ($a['sub_status'] === null) $groups['pending'][] = $a;
    elseif ($a['sub_status'] === 'graded' || $a['sub_score'] !== null) $groups['graded'][] = $a;
    else $groups['submitted'][] = $a;
}

require_once 'includes/layout-top.php';

$msg = (string)($_GET['msg'] ?? '');
$msg_text = [
    'upload_error'      => 'The file did not upload. Please choose it again and retry.',
    'invalid_file_type' => 'That file type is not allowed. Use PDF, Word (DOC/DOCX), ZIP, RAR, JPG or PNG.',
    'upload_failed'     => 'We could not save your file. Please try again.',
    'db_error'          => 'Something went wrong while saving your submission. Please try again.',
][$msg] ?? 'Something went wrong (' . str_replace('_', ' ', $msg) . '). Please try again.';

$fmt_score = fn($s) => rtrim(rtrim(number_format((float)$s, 2, '.', ''), '0'), '.');
?>

<div class="sp-page-head pg-assign">
    <div>
        <h1>Assignments</h1>
        <p>Hand in your work before the due date and see your grades and feedback here.</p>
    </div>
</div>

<?php if ($msg === 'success'): ?>
    <div class="sp-alert success"><i class="fas fa-circle-check" style="margin-top: 3px;"></i><span>Your work has been submitted. Your tutor will grade it soon.</span></div>
<?php elseif ($msg !== ''): ?>
    <div class="sp-alert error"><i class="fas fa-circle-exclamation" style="margin-top: 3px;"></i><span><?= htmlspecialchars($msg_text) ?></span></div>
<?php endif; ?>

<?php if (empty($assignments)): ?>
    <div class="sp-empty">
        <div class="sp-ico green"><i class="fas fa-clipboard-check"></i></div>
        <h3>No assignments yet</h3>
        <p>When a tutor sets an assignment in one of your courses, it will show up here.</p>
        <a href="./" class="sp-btn">Back to dashboard</a>
    </div>
<?php else: ?>

<section class="sp-grid cols-3 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon orange"><i class="fas fa-file-pen"></i></span><div><strong><?= count($groups['pending']) ?></strong><span>To submit</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon yellow"><i class="fas fa-hourglass-half"></i></span><div><strong><?= count($groups['submitted']) ?></strong><span>Waiting for a grade</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= count($groups['graded']) ?></strong><span>Graded</span></div></div>
</section>

<div class="sp-section-head">
    <div class="sp-tabs" role="tablist">
        <button type="button" class="sp-tab active" onclick="assignTab(this, 'pending')">To submit (<?= count($groups['pending']) ?>)</button>
        <button type="button" class="sp-tab" onclick="assignTab(this, 'submitted')">Submitted (<?= count($groups['submitted']) ?>)</button>
        <button type="button" class="sp-tab" onclick="assignTab(this, 'graded')">Graded (<?= count($groups['graded']) ?>)</button>
    </div>
</div>

<?php foreach ($groups as $key => $list): ?>
<div class="pg-assign-tab" id="tab-<?= $key ?>" <?= $key !== 'pending' ? 'style="display: none;"' : '' ?>>
    <?php if (empty($list)): ?>
        <div class="sp-empty" style="padding: 32px 24px;">
            <?php if ($key === 'pending'): ?>
                <div class="sp-ico green"><i class="fas fa-check"></i></div><h3>All caught up</h3><p>You have nothing left to hand in right now.</p>
            <?php elseif ($key === 'submitted'): ?>
                <div class="sp-ico"><i class="fas fa-paper-plane"></i></div><h3>Nothing waiting for a grade</h3><p>Work you submit stays here until your tutor grades it.</p>
            <?php else: ?>
                <div class="sp-ico"><i class="fas fa-star"></i></div><h3>No grades yet</h3><p>Graded work and your tutor's feedback will show up here.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($list as $a):
        $has_due = !empty($a['due_date']);
        $due_ts = $has_due ? strtotime($a['due_date']) : null;
        $is_overdue = $key === 'pending' && $has_due && $due_ts < time();
        $days_left = $has_due ? (int)floor(($due_ts - time()) / 86400) : null;
        $max = (int)($a['max_score'] ?: 100);
    ?>
    <article class="sp-card pg-assign-card <?= $is_overdue ? 'is-overdue' : '' ?>">
        <div class="sp-between" style="align-items: flex-start; flex-wrap: wrap;">
            <div style="min-width: 0; flex: 1;">
                <span class="sp-small" style="color: var(--primary-dark); font-weight: 700;"><?= htmlspecialchars($a['course_title']) ?></span>
                <h3 style="margin: 4px 0 0; font-size: 1.08rem; font-weight: 800;"><?= htmlspecialchars($a['title']) ?></h3>
            </div>
            <?php if ($key === 'pending'): ?>
                <?php if (!$has_due): ?>
                    <span class="sp-badge gray"><i class="far fa-calendar"></i> No due date</span>
                <?php elseif ($is_overdue): ?>
                    <span class="sp-badge red"><i class="fas fa-calendar-xmark"></i> Overdue · was due <?= date('j M Y', $due_ts) ?></span>
                <?php else: ?>
                    <span class="sp-badge <?= $days_left <= 2 ? 'orange' : 'yellow' ?>"><i class="far fa-calendar"></i> Due <?= date('j M Y, g:ia', $due_ts) ?></span>
                <?php endif; ?>
            <?php elseif ($key === 'submitted'): ?>
                <span class="sp-badge yellow"><i class="fas fa-hourglass-half"></i> Waiting for a grade</span>
            <?php else: ?>
                <span class="sp-badge green"><i class="fas fa-circle-check"></i> Graded</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($a['description'])): ?>
            <p class="pg-assign-desc"><?= nl2br(htmlspecialchars($a['description'])) ?></p>
        <?php endif; ?>

        <?php if ($key === 'pending'): ?>
            <form action="submit-assignment" method="POST" enctype="multipart/form-data" class="sp-form pg-assign-form">
                <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                <div class="sp-field">
                    <label for="file-<?= (int)$a['id'] ?>">Your file</label>
                    <label class="pg-file" for="file-<?= (int)$a['id'] ?>">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <span class="pg-file-name">Choose a file</span>
                        <input type="file" id="file-<?= (int)$a['id'] ?>" name="submission_file" required accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.png" onchange="showFileName(this)">
                    </label>
                    <div class="sp-help">PDF, Word, ZIP, RAR, JPG or PNG.</div>
                </div>
                <div class="sp-field">
                    <label for="notes-<?= (int)$a['id'] ?>">Note to your tutor (optional)</label>
                    <input type="text" id="notes-<?= (int)$a['id'] ?>" name="notes" placeholder="e.g. I focused on question 3">
                </div>
                <button type="submit" class="sp-btn <?= $is_overdue ? 'orange' : '' ?>"><i class="fas fa-paper-plane"></i> Submit work</button>
            </form>
        <?php else: ?>
            <div class="pg-assign-result">
                <div>
                    <span class="sp-muted sp-small">Grade</span>
                    <div><strong class="pg-score"><?= $a['sub_score'] !== null ? $fmt_score($a['sub_score']) : '–' ?></strong> <span class="sp-muted">/ <?= $max ?></span></div>
                </div>
                <div>
                    <span class="sp-muted sp-small">Submitted</span>
                    <div style="font-weight: 700;"><?= !empty($a['sub_date']) ? date('j M Y', strtotime($a['sub_date'])) : '–' ?></div>
                </div>
                <?php if ($has_due): ?>
                <div>
                    <span class="sp-muted sp-small">Due date</span>
                    <div style="font-weight: 700;"><?= date('j M Y', $due_ts) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($a['sub_feedback'])): ?>
                <div class="sp-alert success" style="margin: 14px 0 0;"><i class="fas fa-comment-dots" style="margin-top: 3px;"></i><div><strong>Tutor feedback</strong><br><?= nl2br(htmlspecialchars($a['sub_feedback'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($a['sub_notes'])): ?>
                <p class="sp-small sp-muted" style="margin: 12px 0 0;"><strong>Your note:</strong> <?= htmlspecialchars($a['sub_notes']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

<style>
.pg-assign-card { border-left: 4px solid var(--primary); }
.pg-assign-card.is-overdue { border-left-color: var(--danger); }
.pg-assign-desc { margin: 12px 0 0; color: var(--text-dim); font-size: .9rem; line-height: 1.65; }
.pg-assign-form { margin-top: 16px; padding: 16px; border-radius: var(--radius-md); background: var(--surface-2); border: 1px dashed var(--border-strong); }
.pg-assign-form .pg-file { position: relative; display: flex; align-items: center; gap: 10px; height: 44px; padding: 0 14px; margin: 0; border-radius: 12px; border: 1px solid var(--border-strong); background: var(--surface); cursor: pointer; font-size: .88rem; font-weight: 600; color: var(--text-dim); min-width: 0; }
.pg-assign-form .pg-file i { color: var(--primary); }
.pg-assign-form .pg-file .pg-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.pg-assign-form .pg-file.has-file { color: var(--text-main); border-color: var(--primary); }
.pg-assign-form .pg-file input[type=file] { position: absolute; inset: 0; opacity: 0; width: 100%; cursor: pointer; }
.pg-assign-form .pg-file:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.pg-assign-result { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 12px 32px; padding: 14px 16px; border-radius: var(--radius-md); background: var(--surface-2); }
.pg-score { font-size: 1.5rem; font-weight: 800; }
</style>

<script>
function assignTab(btn, key) {
    btn.parentElement.querySelectorAll('.sp-tab').forEach(b => b.classList.toggle('active', b === btn));
    document.querySelectorAll('.pg-assign-tab').forEach(t => t.style.display = t.id === 'tab-' + key ? '' : 'none');
}
function showFileName(input) {
    const box = input.closest('.pg-file');
    box.querySelector('.pg-file-name').textContent = input.files.length ? input.files[0].name : 'Choose a file';
    box.classList.toggle('has-file', input.files.length > 0);
}
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
