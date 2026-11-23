<?php
$pageTitle = 'Leaderboard';
require_once 'includes/header.php';
require_once '../includes/leaderboard_logic.php';

$period = ($_GET['period'] ?? '') === 'week' ? 'week' : 'all';
$course_title = '';
foreach ($courses as $c) if ((int)$c['id'] === (int)$course_id) $course_title = $c['title'];
$my_row = null;
foreach ($leaderboard as $l) if ((int)$l['id'] === (int)$student['id']) $my_row = $l;

require_once 'includes/layout-top.php';
?>
<style>
  .pg-lb .lb-row { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
  .pg-lb .lb-row:last-child { border-bottom: 0; }
  .pg-lb .lb-row.me { background: var(--primary-soft); box-shadow: inset 4px 0 0 var(--primary); }
  .pg-lb .lb-rank { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: grid; place-items: center;
      font-weight: 800; font-size: .88rem; color: var(--text-dim); background: var(--surface-2); }
  .pg-lb .lb-rank.r1 { background: #FEF3C7; color: #A16207; }
  .pg-lb .lb-rank.r2 { background: #EEF2F6; color: #475569; }
  .pg-lb .lb-rank.r3 { background: #FDEBDD; color: #9A4A12; }
  .pg-lb .lb-avatar { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; overflow: hidden; display: grid; place-items: center;
      background: #E2E8F0; color: var(--text-dim); font-weight: 800; }
  .pg-lb .lb-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .pg-lb .lb-name { flex: 1; min-width: 0; }
  .pg-lb .lb-name strong { display: block; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .pg-lb .lb-name .sp-progress { height: 5px; margin-top: 6px; max-width: 220px; }
  .pg-lb .lb-num { text-align: right; min-width: 64px; }
  .pg-lb .lb-num strong { display: block; font-size: .95rem; }
  .pg-lb .lb-num span { font-size: .72rem; color: var(--text-dim); font-weight: 600; }
  .pg-lb .lb-head { display: flex; gap: 14px; padding: 10px 16px; background: var(--surface-2); border-bottom: 1px solid var(--border);
      font-size: .72rem; font-weight: 700; color: var(--text-dim); }
  .pg-lb .lb-filter select { height: 42px; padding: 0 36px 0 14px; border-radius: 12px; border: 1px solid var(--border-strong);
      background: var(--surface); font: inherit; font-size: .88rem; font-weight: 600; max-width: min(320px, 100%); }
</style>

<div class="pg-lb">
  <div class="sp-page-head">
    <div><h1>Leaderboard</h1><p>See how you rank against other learners in each of your courses, by XP earned.</p></div>
    <?php if ($courses): ?>
    <form method="GET" class="sp-actions lb-filter">
      <label for="lbCourse" class="sp-hide-sm" style="align-self: center; font-size: .85rem; font-weight: 700; color: var(--text-dim);">Course</label>
      <select id="lbCourse" name="course_id" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $course_id ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($period === 'week'): ?><input type="hidden" name="period" value="week"><?php endif; ?>
      <noscript><button class="sp-btn" type="submit">Show</button></noscript>
    </form>
    <?php endif; ?>
  </div>

  <?php if (!$courses): ?>
    <div class="sp-empty">
      <div class="sp-ico"><i class="fas fa-ranking-star"></i></div>
      <h3>Join a course to see its leaderboard</h3>
      <p>Each course has its own leaderboard. Learners are ranked by the XP they earn in that course.</p>
      <a href="../courses" class="sp-btn">Browse courses</a>
    </div>
  <?php else: ?>

  <div class="sp-section-head">
    <div><h2><?= htmlspecialchars($course_title) ?></h2><p><?= count($leaderboard) ?> learner<?= count($leaderboard) == 1 ? '' : 's' ?> on the board</p></div>
    <div class="sp-tabs">
      <a class="sp-tab <?= $period === 'all' ? 'active' : '' ?>" href="?course_id=<?= (int)$course_id ?>">All time</a>
      <a class="sp-tab <?= $period === 'week' ? 'active' : '' ?>" href="?course_id=<?= (int)$course_id ?>&amp;period=week">This week</a>
    </div>
  </div>

  <?php if ($my_row): ?>
    <div class="sp-grid cols-3 sp-mb">
      <div class="sp-stat"><span class="sp-stat-icon yellow"><i class="fas fa-trophy"></i></span><div><strong>#<?= (int)$my_row['rank'] ?></strong><span>Your rank of <?= count($leaderboard) ?></span></div></div>
      <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-bolt"></i></span><div><strong><?= number_format((int)$my_row['course_xp']) ?></strong><span>XP <?= $period === 'week' ? 'this week' : 'in this course' ?></span></div></div>
      <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-chart-line"></i></span><div><strong><?= (int)round((float)$my_row['progress_percent']) ?>%</strong><span>Course progress</span></div></div>
    </div>
  <?php elseif (empty($student['show_on_leaderboard'])): ?>
    <div class="sp-alert"><i class="fas fa-user-shield"></i><div>You're hidden from leaderboards. <a href="achievements#privacy" class="sp-link">Change this in Achievements</a></div></div>
  <?php endif; ?>

  <?php if (empty($leaderboard)): ?>
    <div class="sp-empty">
      <div class="sp-ico gray"><i class="fas fa-medal"></i></div>
      <h3>No rankings yet</h3>
      <p><?= $period === 'week' ? 'Nobody has earned XP in this course this week. Finish a lesson to take first place.' : 'Nobody is on this leaderboard yet. Finish a lesson to take first place.' ?></p>
      <a href="classroom?id=<?= (int)$course_id ?>" class="sp-btn">Go to course</a>
    </div>
  <?php else: ?>
    <div class="sp-card flush">
      <div class="lb-head"><span style="width: 34px;">Rank</span><span style="flex: 1;">Learner</span><span class="sp-hide-sm lb-num">Best quiz</span><span class="lb-num">XP</span></div>
      <?php foreach ($leaderboard as $idx => $l):
          $is_me = (int)$l['id'] === (int)$student['id'];
          $rank = $idx + 1;
          $prog = (int)round((float)$l['progress_percent']);
      ?>
      <div class="lb-row<?= $is_me ? ' me' : '' ?>">
        <span class="lb-rank<?= $rank <= 3 ? ' r' . $rank : '' ?>"><?= $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank - 1] : $rank ?></span>
        <span class="lb-avatar">
          <?php $ini = mb_strtoupper(mb_substr((string)$l['name'], 0, 1)) ?: 'S'; ?>
          <?php if (!empty($l['avatar'])): ?><img src="../uploads/avatars/<?= htmlspecialchars($l['avatar']) ?>" alt="" onerror="this.replaceWith(<?= htmlspecialchars(json_encode($ini)) ?>)"><?php else: ?><?= htmlspecialchars($ini) ?><?php endif; ?>
        </span>
        <div class="lb-name">
          <strong><?= htmlspecialchars($l['name']) ?><?php if ($is_me): ?> <span class="sp-badge" style="margin-left: 4px;">You</span><?php endif; ?></strong>
          <div class="sp-progress <?= $prog >= 100 ? 'green' : '' ?>" title="<?= $prog ?>% of the course done"><span style="width: <?= max(2, $prog) ?>%;"></span></div>
        </div>
        <div class="lb-num sp-hide-sm"><strong><?= (int)$l['avg_quiz_score'] ?>%</strong><span>best quiz</span></div>
        <div class="lb-num"><strong><?= number_format((int)$l['course_xp']) ?></strong><span>XP</span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="sp-muted sp-small sp-mt">Names show first name and last initial. Learners who hide themselves in their privacy settings are not listed.</p>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
