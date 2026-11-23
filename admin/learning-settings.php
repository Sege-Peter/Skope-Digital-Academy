<?php
/** Admin: platform-wide learning & pricing settings (stored in platform_settings). */
$pageTitle = 'Learning Settings';
require_once 'includes/header.php';

$sections = [
    'learning' => ['Learning', 'graduation-cap', 'How courses open up for learners.', [
        'default_access_days'   => ['Default access period (days)', 'number', 'Used when a course has no access period of its own. 0 = lifetime access.', 'days'],
        'structured_week_days'  => ['Days between structured weeks', 'number', 'How often a structured track releases the next week of lessons. Minimum 1.', 'days'],
        'structured_pass_score' => ['Quiz score to move on', 'number', 'On structured tracks, a lesson quiz must reach this score before the next lesson opens. 0–100.', '%'],
        'sequential_lessons'    => ['Lessons unlock in order (self-paced)', 'bool', 'On: a self-paced lesson opens once the one before it is finished. Off: every lesson opens at once. Free previews and open tutorials are never locked.'],
        'allow_mode_switch'     => ['Learners may switch tracks', 'bool', 'Switching is free when what they paid already covers the new track; otherwise they pay the difference.'],
    ]],
    'fees' => ['Certificates & fees', 'award', 'What learners pay and how course income is shared.', [
        'currency'            => ['Currency code', 'text', 'Shown on every price, e.g. KES. Paystack must support it.'],
        'certificate_fee'     => ['Default certificate fee', 'number', 'Learners pay this to unlock a course certificate after completing it, unless the course sets its own fee (Pricing & Tracks). 0 = certificates are free.', 'money'],
        'registration_fee'    => ['Registration fee', 'number', 'Charged once when a student account is activated. 0 = free sign-up.', 'money'],
        'tutor_revenue_share' => ['Tutor share of course fees', 'number', 'Used by Tutor Payroll. The academy keeps the rest. Certificate fees always stay with the academy. 0–100.', '%'],
    ]],
    'site' => ['Site', 'globe', 'Public wording and links.', [
        'tutorials_title' => ['Tutorials page title', 'text', 'Heading of the public free-tutorials page.'],
        'site_url'        => ['Public site URL', 'url', 'Full address of this site, e.g. https://academy.example.com. Used in password-reset and other emailed links. Leave blank to work it out automatically.'],
    ]],
];
$defaults = ['tutor_revenue_share' => '80'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    foreach ($sections as [, , , $fields]) {
        foreach ($fields as $key => $f) {
            $type = $f[1];
            $v = $_POST[$key] ?? '';
            if ($type === 'bool') $v = isset($_POST[$key]) ? '1' : '0';
            elseif ($type === 'number') $v = (string)max(0, (float)$v);
            elseif ($type === 'url') $v = (filter_var(trim($v), FILTER_VALIDATE_URL) && preg_match('~^https?://~i', trim($v))) ? rtrim(trim($v), '/') : '';
            else $v = mb_substr(trim($v), 0, 120);
            if ($key === 'currency') $v = strtoupper(preg_replace('/[^A-Za-z]/', '', $v)) ?: 'KES';
            if ($key === 'structured_week_days') $v = (string)max(1, (int)$v);
            if ($key === 'structured_pass_score') $v = (string)min(100, (int)$v);
            if ($key === 'tutor_revenue_share') $v = (string)min(100, round((float)$v, 2));
            if ($key === 'tutorials_title' && $v === '') $v = SDA_SETTING_DEFAULTS['tutorials_title'];
            save_setting($pdo, $key, $v);
        }
    }
    try {
        $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'learning_settings', 'Learning settings saved', ?)")
            ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) { error_log($e->getMessage()); }
    ax_flash('ok', 'Learning settings saved.');
    ax_back('learning-settings');
}

$cur = setting('currency');
$val = fn($k) => setting($k, $defaults[$k] ?? null);
$xpLabels = ['lesson_view' => 'Opening a lesson', 'lesson_complete' => 'Finishing a lesson', 'exercise_pass' => 'Solving an exercise',
             'quiz_pass' => 'Passing a quiz (' . QUIZ_PASS_PCT . '%+)', 'quiz_perfect' => 'Bonus for 100% on a quiz', 'course_complete' => 'Completing a course',
             'path_complete' => 'Completing a learning path', 'daily_streak' => 'Learning on a new day (streak)', 'review' => 'Reviewing a course'];
$onBoard = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student' AND show_on_leaderboard = 1");
$students = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student'");
require_once 'includes/sidebar.php';
?>
<style>
  .ls-row { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 18px; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--border-light); }
  .ls-row:last-child { border-bottom: 0; padding-bottom: 4px; }
  .ls-row:first-child { padding-top: 4px; }
  .ls-row label b { display: block; color: var(--text-main); font-size: .92rem; }
  .ls-row label .hint { font-size: .78rem; color: var(--text-muted); margin-top: 3px; line-height: 1.5; }
  .ls-in { position: relative; }
  .ls-in .u { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: .78rem; font-weight: 700; color: var(--text-muted); pointer-events: none; }
  .ls-in .u.pre { right: auto; left: 12px; }
  .ls-in input.pre { padding-left: 50px; }
  .ls-in input.post { padding-right: 52px; }
  .ls-toggle { justify-self: start; }
  .ls-nav { position: sticky; top: 90px; }
  .ls-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: var(--text-dim); font-weight: 700; font-size: .86rem; text-decoration: none; }
  .ls-nav a:hover { background: var(--surface-2); color: var(--primary); }
  .ls-save { position: sticky; bottom: 0; background: linear-gradient(180deg, transparent, var(--bg-main) 40%); padding: 18px 0 6px; display: flex; justify-content: flex-end; z-index: 2; }
  @media (max-width: 760px) { .ls-row { grid-template-columns: 1fr; gap: 10px; } }
  @media (max-width: 1200px) { .ls-nav-wrap { display: none; } }
</style>
<main class="main-content">
    <?php ax_header('Learning', 'Settings', 'Fees, access and how tracks run',
        '<a class="ax-btn ghost" href="course-pricing"><i class="fas fa-tags"></i> Course pricing</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-grid">
            <div class="ax-span-8">
                <form method="post" class="ax-form" id="ls-form">
                    <?= ax_csrf() ?>
                    <?php foreach ($sections as $sid => [$title, $icon, $sub, $fields]): ?>
                    <section class="ax-panel" id="sec-<?= $sid ?>">
                        <div class="ax-panel-h"><div><h2><i class="fas fa-<?= $icon ?>"></i> <?= e($title) ?></h2><p><?= e($sub) ?></p></div></div>
                        <div class="ax-panel-b">
                            <?php foreach ($fields as $key => $f): [$label, $type, $help] = $f; $unit = $f[3] ?? ''; ?>
                            <div class="ls-row">
                                <label for="f_<?= $key ?>"><b><?= e($label) ?></b><div class="hint"><?= e($help) ?></div></label>
                                <?php if ($type === 'bool'): ?>
                                    <label class="ax-check ls-toggle"><input type="checkbox" id="f_<?= $key ?>" name="<?= $key ?>" <?= $val($key) === '1' ? 'checked' : '' ?>> Allowed</label>
                                <?php else: ?>
                                    <div class="ls-in">
                                        <?php if ($unit === 'money'): ?><span class="u pre"><?= e($cur) ?></span><?php endif; ?>
                                        <input type="<?= $type ?>" id="f_<?= $key ?>" name="<?= $key ?>" value="<?= e($val($key)) ?>"
                                            class="<?= $unit === 'money' ? 'pre' : ($unit ? 'post' : '') ?>"
                                            <?= $type === 'number' ? 'min="0" step="any"' . ($unit === '%' ? ' max="100"' : '') : '' ?>
                                            <?= $key === 'currency' ? 'maxlength="5" style="text-transform:uppercase"' : '' ?>
                                            <?= $type === 'url' ? 'placeholder="https://"' : '' ?>>
                                        <?php if ($unit && $unit !== 'money'): ?><span class="u"><?= e($unit) ?></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                    <div class="ls-save"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save settings</button></div>
                </form>

                <section class="ax-panel" id="sec-xp">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-ranking-star"></i> Leaderboards &amp; XP</h2><p>How learners earn XP. These rules are built into the learning engine and apply to every course.</p></div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="ax-btn ghost sm" href="leaderboard"><i class="fas fa-ranking-star"></i> Leaderboards</a><a class="ax-btn ghost sm" href="badges"><i class="fas fa-medal"></i> Badges</a></div></div>
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Action</th><th>XP</th></tr></thead>
                        <tbody><?php foreach (XP_RULES as $k => $xp): ?>
                            <tr><td data-label="Action"><?= e($xpLabels[$k] ?? $k) ?></td><td data-label="XP" class="num strong">+<?= (int)$xp ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                    <div class="ax-panel-b">
                        <dl class="ax-kv">
                            <dt>Course completion</dt><dd>Every lesson done<?= ' + final quiz at ' . QUIZ_PASS_PCT . '% or more when the course has one' ?>.</dd>
                            <dt>Levels</dt><dd>Level 2 at 100 XP, 3 at 300, 4 at 600, 5 at 1,000 … (each level needs 100 XP more than the last).</dd>
                            <dt>On leaderboards</dt><dd><?= number_format($onBoard) ?> of <?= number_format($students) ?> students (learners can hide themselves from their profile). Public boards show first name + initial only.</dd>
                        </dl>
                    </div>
                </section>
            </div>

            <aside class="ax-span-4 ls-nav-wrap">
                <section class="ax-panel ls-nav">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-list"></i> Sections</h2></div></div>
                    <div class="ax-panel-b" style="padding:10px">
                        <?php foreach ($sections as $sid => [$title, $icon]): ?><a href="#sec-<?= $sid ?>"><i class="fas fa-<?= $icon ?>"></i> <?= e($title) ?></a><?php endforeach; ?>
                        <a href="#sec-xp"><i class="fas fa-ranking-star"></i> Leaderboards &amp; XP</a>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
