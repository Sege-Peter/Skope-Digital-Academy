<?php
/** Admin: platform-wide learning & pricing settings (stored in platform_settings). */
$pageTitle = 'Learning Settings';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/learning.php';

$fields = [
    'currency'              => ['Currency code', 'text', 'Shown on every price, e.g. KES. Paystack must support it.'],
    'certificate_fee'       => ['Default certificate fee', 'number', 'Learners pay this to unlock a course certificate after completing it, unless the course sets its own fee. 0 = certificates are free.'],
    'registration_fee'      => ['Registration fee', 'number', 'Charged once when a student account is activated. 0 = free sign-up.'],
    'default_access_days'   => ['Default access period (days)', 'number', 'Used when a course has no access period of its own. 0 = lifetime.'],
    'structured_week_days'  => ['Days between structured weeks', 'number', 'How often a structured track releases the next week of lessons.'],
    'structured_pass_score' => ['Quiz score to move on (%)', 'number', 'On structured tracks, a lesson quiz must reach this score before the next lesson opens.'],
    'allow_mode_switch'     => ['Learners may switch tracks', 'bool', 'Switching is free when what they paid already covers the new track; otherwise they pay the difference.'],
    'tutorials_title'       => ['Tutorials page title', 'text', 'Heading of the public free-tutorials page.'],
    'site_url'              => ['Public site URL', 'url', 'Full address of this site, e.g. https://academy.example.com. Used in password-reset emails.'],
];

$flash = getFlash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Your session expired. Please try again.');
    } else {
        foreach ($fields as $key => [$label, $type]) {
            $v = $_POST[$key] ?? '';
            if ($type === 'bool') $v = isset($_POST[$key]) ? '1' : '0';
            elseif ($type === 'number') $v = (string)max(0, (float)$v);
            elseif ($type === 'url') $v = (filter_var(trim($v), FILTER_VALIDATE_URL) && preg_match('~^https?://~i', trim($v))) ? rtrim(trim($v), '/') : '';
            else $v = mb_substr(trim($v), 0, 120);
            if ($key === 'currency') $v = strtoupper(preg_replace('/[^A-Za-z]/', '', $v)) ?: 'KES';
            if ($key === 'structured_week_days') $v = (string)max(1, (int)$v);
            if ($key === 'structured_pass_score') $v = (string)min(100, (int)$v);
            save_setting($pdo, $key, $v);
        }
        setFlash('success', 'Learning settings saved.');
    }
    header('Location: learning-settings');
    exit;
}
?>
<?php require_once 'includes/sidebar.php'; ?>
<style>
  .ls-card { background:#fff; border:1px solid #e2e8f0; border-radius:18px; padding:26px; max-width:860px; }
  .ls-row { display:grid; grid-template-columns: 1fr 260px; gap:18px; align-items:center; padding:16px 0; border-bottom:1px solid #f1f5f9; }
  .ls-row:last-of-type { border-bottom:0; }
  .ls-row strong { display:block; } .ls-row small { color:#64748b; }
  .ls-row input[type=text], .ls-row input[type=number], .ls-row input[type=url] { width:100%; padding:11px 13px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; }
  .ls-flash { padding:12px 16px; border-radius:12px; font-weight:700; margin-bottom:18px; max-width:860px; }
  .ls-flash.success { background:#dcfce7; color:#166534; } .ls-flash.danger { background:#fee2e2; color:#991b1b; }
  @media (max-width: 700px) { .ls-row { grid-template-columns: 1fr; } }
</style>
<main class="main-content">
  <header class="admin-header">
    <div style="display:flex;align-items:center;gap:20px;">
      <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
      <div>
        <h1 style="font-size:1.5rem;font-weight:900;letter-spacing:-.5px;">Learning <span>Settings</span></h1>
        <p style="font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:1px;">Fees, access and how structured tracks run</p>
      </div>
    </div>
    <a class="btn-premium" href="course-pricing" style="text-decoration:none"><i class="fas fa-tags"></i> Course pricing</a>
  </header>
  <div class="admin-body">
    <?php if ($flash): ?><div class="ls-flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <form method="post" class="ls-card">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <?php foreach ($fields as $key => [$label, $type, $help]): ?>
        <div class="ls-row">
          <label for="f_<?= $key ?>"><strong><?= e($label) ?></strong><small><?= e($help) ?></small></label>
          <?php if ($type === 'bool'): ?>
            <input type="checkbox" id="f_<?= $key ?>" name="<?= $key ?>" <?= setting($key) === '1' ? 'checked' : '' ?> style="width:22px;height:22px">
          <?php else: ?>
            <input type="<?= $type ?>" id="f_<?= $key ?>" name="<?= $key ?>" value="<?= e(setting($key)) ?>" <?= $type === 'number' ? 'min="0" step="any"' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div style="text-align:right;margin-top:16px"><button class="btn-premium" type="submit"><i class="fas fa-floppy-disk"></i> Save settings</button></div>
    </form>
  </div>
</main>
<script src="../assets/js/main.js"></script>
</body>
</html>
