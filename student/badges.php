<?php
$pageTitle = 'Badges';
require_once 'includes/header.php';

try {
    // 1. Calculate student progress for various criteria
    $progress_data = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND progress_percent >= 100");
    $stmt->execute([$student['id']]);
    $progress_data['courses_completed'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = ? AND status = 'completed'");
    $stmt->execute([$student['id']]);
    $progress_data['lessons_completed'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ? AND passed = 1");
    $stmt->execute([$student['id']]);
    $progress_data['quizzes_passed'] = (int)$stmt->fetchColumn();

    $progress_data['points_earned'] = (int)($student['merit_points'] ?? 0);

    // 2. Fetch available badges to check for unlocks
    $stmt = $pdo->prepare("SELECT * FROM badges 
                           WHERE id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?)
                           ORDER BY criteria_value ASC");
    $stmt->execute([$student['id']]);
    $available_badges = $stmt->fetchAll();

    // 3. Auto-Unlock newly achieved badges
    $newly_awarded = 0;
    foreach ($available_badges as $b) {
        $current_val = $progress_data[$b['criteria_type']] ?? 0;
        if ($current_val >= $b['criteria_value']) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id) VALUES (?, ?)");
            $stmt->execute([$student['id'], $b['id']]);
            $newly_awarded++;
        }
    }

    if ($newly_awarded > 0) {
        header("Location: badges?awarded=" . $newly_awarded);
        exit;
    }

    // 4. Fetch earned badges (after possible unlocks)
    $stmt = $pdo->prepare("SELECT b.*, sb.awarded_at 
                           FROM student_badges sb
                           JOIN badges b ON sb.badge_id = b.id
                           WHERE sb.student_id = ?
                           ORDER BY sb.awarded_at DESC");
    $stmt->execute([$student['id']]);
    $earned_badges = $stmt->fetchAll();

    // 5. Re-fetch locked badges
    $stmt = $pdo->prepare("SELECT * FROM badges 
                           WHERE id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?)
                           ORDER BY criteria_value ASC");
    $stmt->execute([$student['id']]);
    $available_badges = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $earned_badges = $available_badges = [];
    $progress_data = [];
}

// Display only: progress numbers for the newer badge rules (lessons, streak, xp...) come from the XP engine.
$display_stats = $progress_data;
try {
    require_once __DIR__ . '/../includes/progress.php';
    $display_stats += user_stats($pdo, (int)$student['id']);
} catch (Throwable $e) { error_log('badges stats: ' . $e->getMessage()); }

// Badge icons are either Font Awesome names (fa-star) or an emoji/character.
function badge_icon_html(?string $icon): string {
    $icon = trim((string)$icon);
    if ($icon === '' || trim($icon, '?') === '') return '<i class="fas fa-award"></i>'; // blank or lost emoji
    if (str_starts_with($icon, 'fa-')) return '<i class="fas ' . htmlspecialchars($icon) . '"></i>';
    return '<span aria-hidden="true">' . htmlspecialchars($icon) . '</span>';
}
$awarded_now = max(0, (int)($_GET['awarded'] ?? 0));

require_once 'includes/layout-top.php';
?>
<style>
  .pg-badges .ach-badges { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 200px), 1fr)); }
  .pg-badges .bdg { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center;
      padding: 20px 16px 16px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); }
  .pg-badges .bdg.earned { border-color: color-mix(in srgb, var(--bc) 35%, var(--border));
      background: linear-gradient(180deg, color-mix(in srgb, var(--bc) 9%, #fff), #fff 65%); }
  .pg-badges .bdg-icon { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; font-size: 1.5rem; color: #fff;
      background: radial-gradient(circle at 30% 25%, color-mix(in srgb, var(--bc) 55%, #fff), var(--bc));
      box-shadow: 0 0 0 6px color-mix(in srgb, var(--bc) 14%, transparent); margin-bottom: 6px; }
  .pg-badges .bdg.locked .bdg-icon { background: #EEF2F6; color: #A0AEC0; box-shadow: none; filter: grayscale(1); }
  .pg-badges .bdg.locked h4 { color: var(--text-dim); }
  .pg-badges .bdg h4 { margin: 0; font-size: .92rem; font-weight: 800; }
  .pg-badges .bdg p { margin: 0; font-size: .78rem; color: var(--text-dim); line-height: 1.4; }
  .pg-badges .bdg .sp-progress { width: 100%; height: 6px; margin-top: 4px; }
  .pg-badges .bdg-lock { position: absolute; top: 10px; right: 10px; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center;
      background: var(--surface-2); border: 1px solid var(--border); color: var(--text-muted); font-size: .68rem; }
  .pg-badges .bdg-when { margin-top: auto; padding-top: 6px; }
</style>

<div class="pg-badges">
  <div class="sp-page-head">
    <div><h1>Badges</h1><p>Badges you have earned, and the ones you can unlock next.</p></div>
    <div class="sp-actions"><a href="achievements" class="sp-btn ghost"><i class="fas fa-trophy"></i> Level and learning paths</a></div>
  </div>

  <?php if ($awarded_now > 0): ?>
    <div class="sp-alert success"><i class="fas fa-medal"></i> <div>Congratulations! You just unlocked <?= $awarded_now ?> new badge<?= $awarded_now == 1 ? '' : 's' ?>.</div></div>
  <?php endif; ?>

  <section class="sp-grid cols-3 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon violet"><i class="fas fa-medal"></i></span><div><strong><?= count($earned_badges) ?></strong><span>Badges earned</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon" style="background: #EEF2F6; color: var(--text-dim);"><i class="fas fa-lock"></i></span><div><strong><?= count($available_badges) ?></strong><span>Still to unlock</span></div></div>
    <?php $all_badges = count($earned_badges) + count($available_badges); ?>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-chart-simple"></i></span><div><strong><?= $all_badges ? (int)round(count($earned_badges) / $all_badges * 100) : 0 ?>%</strong><span>Collection complete</span></div></div>
  </section>

  <!-- Earned -->
  <section class="sp-section">
    <div class="sp-section-head"><div><h2>Earned</h2><p>Newest first</p></div></div>
    <?php if (empty($earned_badges)): ?>
      <div class="sp-empty" style="padding: 28px;">
        <div class="sp-ico violet"><i class="fas fa-medal"></i></div>
        <h3>No badges yet</h3>
        <p>Finish your first lesson to earn your first badge.</p>
        <a href="courses" class="sp-btn">Go to my courses</a>
      </div>
    <?php else: ?>
    <div class="ach-badges">
      <?php foreach ($earned_badges as $b): ?>
      <div class="bdg earned" style="--bc: <?= htmlspecialchars($b['color'] ?: '#0080ff') ?>">
        <div class="bdg-icon"><?= badge_icon_html($b['icon']) ?></div>
        <h4><?= htmlspecialchars($b['name']) ?></h4>
        <p><?= htmlspecialchars((string)$b['description']) ?></p>
        <span class="sp-badge green bdg-when"><i class="fas fa-check"></i> Earned <?= date('j M Y', strtotime($b['awarded_at'])) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- Locked -->
  <?php if (!empty($available_badges)): ?>
  <section class="sp-section">
    <div class="sp-section-head"><div><h2>Up next</h2><p>Keep learning to unlock these</p></div></div>
    <div class="ach-badges">
      <?php foreach ($available_badges as $b):
          $target = (int)$b['criteria_value'];
          $has_rule = $b['criteria_type'] !== '' && $b['criteria_type'] !== null && isset($display_stats[$b['criteria_type']]);
          $current_val = $has_rule ? (int)$display_stats[$b['criteria_type']] : 0;
          $percent = $target > 0 ? (int)min(100, floor($current_val / $target * 100)) : 0;
      ?>
      <div class="bdg locked" style="--bc: <?= htmlspecialchars($b['color'] ?: '#0080ff') ?>">
        <span class="bdg-lock" title="Locked"><i class="fas fa-lock"></i></span>
        <div class="bdg-icon"><?= badge_icon_html($b['icon']) ?></div>
        <h4><?= htmlspecialchars($b['name']) ?></h4>
        <p><?= htmlspecialchars((string)$b['description']) ?></p>
        <?php if ($has_rule && $target > 1): ?>
          <div class="sp-progress" title="<?= $percent ?>%"><span style="width: <?= max(2, $percent) ?>%;"></span></div>
          <span class="sp-small sp-muted"><?= number_format(min($current_val, $target)) ?> / <?= number_format($target) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<script>
if (new URLSearchParams(location.search).get('awarded')) history.replaceState({}, document.title, 'badges');
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
