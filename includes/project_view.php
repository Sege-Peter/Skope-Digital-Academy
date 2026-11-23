<?php
/**
 * Course project page inside the reader (learn/<slug>/project). Included by learn.php.
 * Uses: $pdo, $course, $project, $pstate (null unless an enrolled student), $student_id, $enrolled, $user, $is_staff, $projUrl.
 */
$pm = $_SESSION['proj_msg'] ?? null;      unset($_SESSION['proj_msg']);
$old = $_SESSION['proj_old'] ?? null;     unset($_SESSION['proj_old']);
$oldRv = $_SESSION['proj_old_review'] ?? null; unset($_SESSION['proj_old_review']);
$csrf = csrfToken();
$csrfField = '<input type="hidden" name="csrf_token" value="' . e($csrf) . '">';
$pjText = fn(?string $t) => nl2br(e((string)$t), false);
?>
<?php if (!$project): ?>
  <h1>Course project</h1>
  <p class="lr-muted">This course doesn't have a project yet.</p>
<?php else:
  $N = $project['reviews_required'];
  $ps = $pstate;
  $latest = $ps['latest'] ?? null;
  $canSubmit = $ps && $project['status'] === 'active' && (!$latest || $latest['status'] === 'needs_work');
  $owed = $ps['owed'] ?? null;
  $nRecv = $ps ? count($ps['received']) : 0;
?>
  <div class="pj-head">
    <span class="pj-kicker"><i class="fas fa-diagram-project"></i> Peer-graded course project</span>
    <h1><?= e($project['title']) ?></h1>
    <div class="pj-meta">
      <span><i class="fas fa-bullseye"></i> Pass mark <?= (int)$project['pass_pct'] ?>%</span>
      <span><i class="fas fa-users"></i> Review <?= $N ?> classmate<?= $N === 1 ? '' : 's' ?></span>
      <span><i class="fas fa-list-check"></i> <?= count($project['rubric_list']) ?>-part rubric · <?= (int)$project['max_points'] ?> points</span>
      <?php if ($ps): ?><span class="pj-chip <?= e($ps['chip'][1]) ?>"><?= e($ps['chip'][0]) ?></span><?php endif; ?>
    </div>
  </div>

  <?php if ($pm): ?>
    <div class="lr-notice <?= $pm[0] === 'ok' ? 'ok' : 'lock' ?>" role="status"><i class="fas fa-<?= $pm[0] === 'ok' ? 'circle-check' : 'circle-exclamation' ?>"></i><div><?= e($pm[1]) ?></div></div>
  <?php endif; ?>
  <?php if ($project['status'] !== 'active'): ?>
    <div class="lr-notice lock"><i class="fas fa-pause"></i><div><strong>This project is paused.</strong>Submissions are closed for now<?= $is_staff ? ' (staff preview)' : '' ?>.</div></div>
  <?php endif; ?>

  <?php $stepTwo = $latest && ($owed['owed'] === 0 || !empty($ps['released'])); ?>
  <ol class="pj-steps" aria-label="How the project works">
    <li class="<?= $latest ? 'done' : 'now' ?>"><span class="n"><?= $latest ? '<i class="fas fa-check"></i>' : '1' ?></span><div><b>Submit your work</b><small><?= $latest ? 'Attempt ' . (int)$latest['attempt'] . ' · ' . e(date('M j', strtotime($latest['created_at']))) : 'Text, a link or a file' ?></small></div></li>
    <li class="<?= $stepTwo ? 'done' : ($latest ? 'now' : '') ?>"><span class="n"><?= $stepTwo ? '<i class="fas fa-check"></i>' : '2' ?></span><div><b>Review <?= $N ?> classmates</b><small><?= $latest ? (int)$owed['given'] . ' of ' . (int)$owed['required'] . ' done' : 'Unlocks after you submit' ?></small></div></li>
    <li class="<?= !empty($ps['released']) ? ($latest['status'] === 'passed' ? 'done' : 'bad') : '' ?>"><span class="n"><?= !empty($ps['released']) && $latest['status'] === 'passed' ? '<i class="fas fa-check"></i>' : '3' ?></span><div><b>Get your grade</b><small><?= !empty($ps['released']) ? round((float)$latest['score_pct']) . '% · ' . ($latest['status'] === 'passed' ? 'passed' : 'needs work') : 'Average of peer scores' ?></small></div></li>
  </ol>

  <?php if (!$user): ?>
    <div class="lr-notice info"><i class="fas fa-user-lock"></i><div><strong>Sign in to take part.</strong><a href="login">Sign in</a> or <a href="register">create a free account</a>, enroll in this course and submit your project.</div></div>
  <?php elseif ($student_id && !$enrolled): ?>
    <div class="lr-notice info"><i class="fas fa-door-open"></i><div><strong>Enroll to submit this project.</strong>Only learners enrolled in <?= e($course['title']) ?> can submit and review projects.
      <div style="margin-top:10px"><a class="lr-btn lr-btn-accent" href="enroll?id=<?= (int)$course['id'] ?>">Enroll<?= course_from_price($course) <= 0 ? ' free' : '' ?></a></div></div></div>
  <?php elseif (!$student_id): ?>
    <div class="lr-notice info"><i class="fas fa-user-shield"></i><div><strong>Staff preview.</strong>Learners submit and review here. Grade submissions that need staff review in <?= $user['role'] === 'admin' ? '<a href="admin/projects">Admin → Peer Projects</a>' : 'the admin panel' ?>.</div></div>
  <?php endif; ?>

  <?php if ($latest): $s = $latest; ?>
    <!-- Status -->
    <section class="pj-card pj-status <?= e($ps['chip'][1]) ?>" id="status">
      <?php if ($s['status'] === 'flagged'): ?>
        <div class="pj-status-ic"><i class="fas fa-flag"></i></div>
        <div><h2>On hold while our team checks a flag</h2><p>A flag was raised on this submission or one of its reviews. A tutor will look at it and either clear it or grade your work. You'll get a notification.</p></div>
      <?php elseif ($s['status'] === 'staff_review'): ?>
        <div class="pj-status-ic"><i class="fas fa-chalkboard-user"></i></div>
        <div><h2>A tutor will grade your project</h2><p>Not enough classmates reviewed it within <?= PROJECT_STAFF_DAYS ?> days, so it has gone to our tutors. <?= $nRecv ?> of <?= $N ?> peer reviews so far – classmates can still review it meanwhile.</p></div>
      <?php elseif (!empty($ps['released'])): $passed = $s['status'] === 'passed'; ?>
        <div class="pj-score <?= $passed ? 'ok' : 'bad' ?>"><b><?= round((float)$s['score_pct']) ?>%</b><span><?= $passed ? 'Passed' : 'Needs work' ?></span></div>
        <div>
          <h2><?= $passed ? 'You passed the project 🎉' : 'Not quite there yet' ?></h2>
          <p><?= $passed ? 'Great work! Your grade is the average of ' . ($s['graded_by_staff'] ? 'your tutor\'s grade' : $nRecv . ' peer reviews') . '. ' : 'You need ' . (int)$project['pass_pct'] . '% to pass. Read the feedback below, improve your work and resubmit – you can try again as many times as you need.' ?></p>
          <?php if ($passed):
            $cs = $pdo->prepare("SELECT verification_code, status FROM certificates WHERE student_id = ? AND course_id = ? AND certificate_type = 'course' AND status IN ('approved','pending') LIMIT 1");
            $cs->execute([$student_id, $course['id']]);
            $cert = $cs->fetch(); ?>
            <?php if ($cert): ?>
              <a class="lr-btn lr-btn-accent" href="<?= $cert['status'] === 'pending' ? 'certificate-pay' : 'certificate' ?>?code=<?= e(rawurlencode($cert['verification_code'])) ?>"><i class="fas fa-<?= $cert['status'] === 'pending' ? 'lock-open' : 'certificate' ?>"></i> <?= $cert['status'] === 'pending' ? 'Unlock your certificate' : 'View your certificate' ?></a>
            <?php else: ?>
              <p class="lr-muted" style="margin:0">Finish every lesson<?= course_has_quiz($pdo, (int)$course['id']) ? ' and pass the final quiz' : '' ?> to complete the course and get your certificate.</p>
            <?php endif; ?>
          <?php else: ?><a class="lr-btn lr-btn-primary" href="<?= e($projUrl) ?>#submit"><i class="fas fa-rotate-right"></i> Resubmit</a><?php endif; ?>
        </div>
      <?php elseif ($nRecv >= $N && $owed['owed'] > 0): ?>
        <div class="pj-status-ic"><i class="fas fa-lock"></i></div>
        <div><h2>Your grade is ready</h2><p>Review <b><?= $owed['owed'] ?> more classmate<?= $owed['owed'] === 1 ? '' : 's' ?></b> to see it. Everyone reviews <?= $N ?> – it keeps grading fair and fast.</p><a class="lr-btn lr-btn-primary" href="<?= e($projUrl) ?>#review">Review a classmate</a></div>
      <?php else: ?>
        <div class="pj-status-ic"><i class="fas fa-hourglass-half"></i></div>
        <div><h2>Waiting for reviews</h2>
          <p><b><?= $nRecv ?> of <?= $N ?></b> reviews received.<?= $owed['owed'] > 0 ? ' Meanwhile, you still owe <b>' . $owed['owed'] . ' review' . ($owed['owed'] === 1 ? '' : 's') . '</b> – your grade is released once you have done them.' : ' You have done your reviews – your grade appears as soon as your classmates finish theirs.' ?></p>
          <div class="pj-bar" aria-hidden="true"><span style="width:<?= min(100, round($nRecv / $N * 100)) ?>%"></span></div>
          <small class="lr-muted">If too few classmates take part, a tutor grades it after <?= PROJECT_STAFF_DAYS ?> days.</small>
        </div>
      <?php endif; ?>
    </section>

    <?php if (!empty($ps['released'])): ?>
      <!-- Feedback -->
      <section class="pj-card" id="feedback">
        <h2><i class="fas fa-comments"></i> Your feedback</h2>
        <?php if ($ps['crit']): ?>
          <div class="pj-crit-avg">
            <?php foreach ($ps['crit'] as $name => $c): $avg = $c['sum'] / max(1, $c['n']); ?>
              <div><span><?= e($name) ?></span><span class="pj-bar"><span style="width:<?= round($avg / max(1, $c['max']) * 100) ?>%"></span></span><b><?= rtrim(rtrim(number_format($avg, 1), '0'), '.') ?> / <?= (int)$c['max'] ?></b></div>
            <?php endforeach; ?>
          </div>
          <p class="lr-muted" style="font-size:.82rem">Average peer score per part of the rubric.</p>
        <?php endif; ?>
        <?php if ($s['graded_by_staff']): $ss = json_decode((string)$s['staff_scores'], true) ?: []; ?>
          <article class="pj-review staff">
            <header><span class="pj-av"><i class="fas fa-chalkboard-user"></i></span><b>Tutor grade</b><span class="pj-chip info"><?= round((float)$s['score_pct']) ?>%</span></header>
            <ul class="pj-scores"><?php foreach ($ss as $x): ?><li><?= e($x['c']) ?> <b><?= (int)$x['s'] ?>/<?= (int)$x['m'] ?></b></li><?php endforeach; ?></ul>
            <p><?= $pjText($s['staff_feedback']) ?></p>
          </article>
        <?php endif; ?>
        <?php foreach ($ps['received'] as $i => $rv): ?>
          <article class="pj-review">
            <header><span class="pj-av"><?= $i + 1 ?></span><b>Classmate <?= $i + 1 ?></b><span class="pj-chip"><?= round((float)$rv['total_pct']) ?>%</span><small class="lr-muted"><?= e(date('M j', strtotime($rv['created_at']))) ?></small></header>
            <ul class="pj-scores"><?php foreach ($rv['score_list'] as $x): ?><li><?= e($x['c']) ?> <b><?= (int)$x['s'] ?>/<?= (int)$x['m'] ?></b></li><?php endforeach; ?></ul>
            <p><?= $pjText($rv['feedback']) ?></p>
            <?php if ($rv['flagged']): ?>
              <span class="pj-chip warn"><i class="fas fa-flag"></i> Flagged – our team is checking it</span>
            <?php else: ?>
              <details class="pj-flag"><summary><i class="far fa-flag"></i> Report this review as unfair or abusive</summary>
                <form method="post" action="<?= e($projUrl) ?>"><?= $csrfField ?><input type="hidden" name="act" value="flag_review"><input type="hidden" name="review_id" value="<?= (int)$rv['id'] ?>">
                  <select name="reason" aria-label="Reason"><option>Unfair score – doesn't match my work</option><option>Rude or abusive language</option><option>Didn't look at my work</option><option>Other</option></select>
                  <input type="text" name="details" maxlength="180" placeholder="Tell us more (optional)">
                  <button class="lr-btn lr-btn-ghost" type="submit">Send report</button></form>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <!-- Review a classmate -->
    <section class="pj-card" id="review">
      <h2><i class="fas fa-user-check"></i> Review a classmate <span class="pj-count"><?= (int)$owed['given'] ?> of <?= $N ?> done</span></h2>
      <?php $nx = $ps['next']; if (!$nx): ?>
        <p class="lr-muted"><?= $owed['given'] >= $N ? 'You have done your reviews – thank you! ' : '' ?>There's nothing to review right now. New submissions appear here as classmates submit – check back later.</p>
      <?php else:
        $keep = $oldRv && (int)$oldRv['sub'] === (int)$nx['id'] ? $oldRv : null; ?>
        <?php if ($owed['given'] >= $N || !empty($ps['released'])): ?><p class="lr-notice ok" style="margin:0 0 12px;padding:10px 14px"><i class="fas fa-heart"></i><span>Your reviews are done. Extra reviews are optional – they help classmates get their grade sooner (+<?= XP_RULES['project_review'] ?> XP each).</span></p><?php endif; ?>
        <p class="lr-muted">Read the work, score each part of the rubric honestly and leave kind, specific feedback: one thing that works well and one thing to improve. Classmates are anonymous to each other.</p>
        <div class="pj-their">
          <div class="pj-their-h"><i class="fas fa-user-secret"></i> A classmate's submission <small>· submitted <?= e(date('M j', strtotime($nx['created_at']))) ?><?= (int)$nx['attempt'] > 1 ? ' · attempt ' . (int)$nx['attempt'] : '' ?></small></div>
          <?php if ($nx['text']): ?><div class="pj-text"><?= $pjText($nx['text']) ?></div><?php endif; ?>
          <div class="pj-attach">
            <?php if ($nx['link']): ?><a href="<?= e($nx['link']) ?>" target="_blank" rel="noopener noreferrer nofollow ugc"><i class="fas fa-link"></i> <?= e(mb_strimwidth($nx['link'], 0, 60, '…')) ?></a><?php endif; ?>
            <?php if ($nx['file']): ?><a href="project-file?id=<?= (int)$nx['id'] ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> <?= e($nx['file_name'] ?: 'Attached file') ?></a><?php endif; ?>
          </div>
        </div>
        <form method="post" action="<?= e($projUrl) ?>" class="pj-form"><?= $csrfField ?>
          <input type="hidden" name="act" value="review"><input type="hidden" name="submission_id" value="<?= (int)$nx['id'] ?>">
          <?php foreach ($project['rubric_list'] as $i => $c): ?>
            <fieldset class="pj-crit">
              <legend><?= e($c['criterion']) ?> <small>/ <?= (int)$c['points'] ?></small></legend>
              <p><?= e($c['description']) ?></p>
              <div class="pj-pts" role="radiogroup" aria-label="<?= e($c['criterion']) ?> score">
                <?php for ($p = 0; $p <= (int)$c['points']; $p++): ?>
                  <label><input type="radio" name="score[<?= $i ?>]" value="<?= $p ?>" required<?= $keep && ($keep['score'][$i] ?? null) === (string)$p ? ' checked' : '' ?>><span><?= $p ?></span></label>
                <?php endfor; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
          <label class="pj-label" for="pjFeedback">Your feedback <small>(at least <?= PROJECT_FEEDBACK_MIN ?> characters)</small></label>
          <textarea id="pjFeedback" name="feedback" rows="5" minlength="<?= PROJECT_FEEDBACK_MIN ?>" maxlength="3000" required placeholder="What works well? What one thing would make it better? Be specific and kind."><?= e($keep['feedback'] ?? '') ?></textarea>
          <div class="pj-actions"><button class="lr-btn lr-btn-ok" type="submit"><i class="fas fa-paper-plane"></i> Submit review</button><span class="lr-muted">+<?= XP_RULES['project_review'] ?> XP</span></div>
        </form>
        <details class="pj-flag"><summary><i class="far fa-flag"></i> Flag this submission instead (copied, offensive, empty or off-topic)</summary>
          <form method="post" action="<?= e($projUrl) ?>"><?= $csrfField ?><input type="hidden" name="act" value="flag_submission"><input type="hidden" name="submission_id" value="<?= (int)$nx['id'] ?>">
            <select name="reason" aria-label="Reason"><option>Copied or plagiarised work</option><option>Offensive or abusive content</option><option>Empty or off-topic</option><option>Shares someone's private details</option><option>Other</option></select>
            <input type="text" name="details" maxlength="180" placeholder="Tell us more (optional)">
            <button class="lr-btn lr-btn-ghost" type="submit">Flag it</button></form>
        </details>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <!-- Brief & rubric -->
  <?php if ($latest): ?><details class="pj-card pj-brief-toggle"><summary><i class="fas fa-file-lines"></i> The project brief and rubric</summary><?php else: ?><section class="pj-card" id="brief"><?php endif; ?>
    <article class="lr-content"><?= render_lesson((string)$project['brief'], ['lesson_id' => 0, 'link' => $ctx['link']]) ?></article>
    <h2 class="pj-h2"><i class="fas fa-list-check"></i> How it's graded</h2>
    <div class="lr-table-wrap">
      <table class="lr-table pj-rubric">
        <thead><tr><th>Part</th><th>What good work looks like</th><th>Points</th></tr></thead>
        <tbody>
          <?php foreach ($project['rubric_list'] as $c): ?><tr><td data-label="Part"><b><?= e($c['criterion']) ?></b></td><td data-label="Looks like"><?= e($c['description']) ?></td><td data-label="Points"><?= (int)$c['points'] ?></td></tr><?php endforeach; ?>
          <tr class="tot"><td colspan="2">Total – you pass at <?= (int)$project['pass_pct'] ?>% (<?= (int)ceil($project['max_points'] * $project['pass_pct'] / 100) ?> of <?= (int)$project['max_points'] ?> points)</td><td><?= (int)$project['max_points'] ?></td></tr>
        </tbody>
      </table>
    </div>
  <?php if ($latest): ?></details><?php else: ?></section><?php endif; ?>

  <?php if ($canSubmit): $prev = $latest; ?>
    <!-- Submit -->
    <section class="pj-card" id="submit">
      <h2><i class="fas fa-upload"></i> <?= $prev ? 'Resubmit your project (attempt ' . ((int)$prev['attempt'] + 1) . ')' : 'Submit your project' ?></h2>
      <p class="lr-muted">Add your work as text, a link, <?= $project['allow_file'] ? 'a file, ' : '' ?>or a mix. Classmates will see it without your name.
        <b>Use made-up details</b> for anything private – never share your real phone number, ID, M-Pesa or home address.</p>
      <form method="post" action="<?= e($projUrl) ?>" enctype="multipart/form-data" class="pj-form"><?= $csrfField ?>
        <input type="hidden" name="act" value="submit">
        <label class="pj-label" for="pjText">Your work</label>
        <textarea id="pjText" name="text" rows="10" maxlength="20000" placeholder="Paste or type your project here…"><?= e($old['text'] ?? ($prev['text'] ?? '')) ?></textarea>
        <label class="pj-label" for="pjLink">Link <small>(optional – Google Docs/Drive, Canva, GitHub, CodePen…; make sure anyone with the link can view it)</small></label>
        <input id="pjLink" type="url" name="link" maxlength="500" placeholder="https://" value="<?= e($old['link'] ?? ($prev['link'] ?? '')) ?>">
        <?php if ($project['allow_file']): ?>
          <label class="pj-label" for="pjFile">File <small>(optional – PDF, PNG, JPG or Word .docx, up to 5 MB)</small></label>
          <input id="pjFile" type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.docx,application/pdf,image/png,image/jpeg,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
          <?php if ($prev && $prev['file']): ?><small class="lr-muted">Your last attempt's file isn't carried over – upload it again if it's still needed.</small><?php endif; ?>
        <?php endif; ?>
        <label class="pj-check"><input type="checkbox" required> This is my own work and contains no one's real private details.</label>
        <div class="pj-actions"><button class="lr-btn lr-btn-accent" type="submit"><i class="fas fa-paper-plane"></i> <?= $prev ? 'Resubmit' : 'Submit project' ?></button><span class="lr-muted"><?= $prev ? '' : '+' . XP_RULES['project_submit'] . ' XP · ' ?>You can't edit after submitting.</span></div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($ps && $ps['attempts']): ?>
    <!-- My submissions -->
    <section class="pj-card" id="mine">
      <h2><i class="fas fa-folder-open"></i> Your submission<?= count($ps['attempts']) > 1 ? 's' : '' ?></h2>
      <?php foreach ($ps['attempts'] as $a): [$lbl, $cls] = project_status_label($a['status']); $shown = !empty($a['released_at']) && in_array($a['status'], ['passed', 'needs_work'], true); ?>
        <details class="pj-attempt"<?= $a === $ps['attempts'][0] && !$latest['released_at'] ? ' open' : '' ?>>
          <summary><b>Attempt <?= (int)$a['attempt'] ?></b> <small class="lr-muted"><?= e(date('M j, Y', strtotime($a['created_at']))) ?></small>
            <span class="pj-chip <?= e($cls) ?>"><?= e($lbl) ?></span><?= $shown ? ' <b>' . round((float)$a['score_pct']) . '%</b>' : '' ?></summary>
          <?php if ($a['text']): ?><div class="pj-text"><?= $pjText($a['text']) ?></div><?php endif; ?>
          <div class="pj-attach">
            <?php if ($a['link']): ?><a href="<?= e($a['link']) ?>" target="_blank" rel="noopener noreferrer nofollow"><i class="fas fa-link"></i> <?= e(mb_strimwidth($a['link'], 0, 60, '…')) ?></a><?php endif; ?>
            <?php if ($a['file']): ?><a href="project-file?id=<?= (int)$a['id'] ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> <?= e($a['file_name'] ?: 'Attached file') ?></a><?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
