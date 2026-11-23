<?php
/** Admin: staff members – invite new staff, promote existing accounts, set department and tool access. */
$pageTitle = 'Staff';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/staff.php';
require_once __DIR__ . '/../includes/mailer.php';
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: staff-home'); exit; }
sda_staff_ensure($pdo);

$log = function (string $what) use ($pdo, $user) {
    try { $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'staff', ?, ?)")->execute([$user['id'], $what, $_SERVER['REMOTE_ADDR'] ?? '']); } catch (Throwable $e) {}
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $dept = (string)($_POST['department'] ?? 'general');
    $title = trim((string)($_POST['job_title'] ?? ''));
    $mods = (array)($_POST['modules'] ?? []);
    try {
        if ($act === 'invite') {
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150);
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 20) ?: null;
            if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { ax_flash('err', 'Enter the staff member\'s full name and a valid email address.'); ax_back(); }
            $ex = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
            $ex->execute([$email]);
            if ($row = $ex->fetch()) { ax_flash('err', 'An account with that email already exists' . ($row['role'] === 'staff' ? ' and is already staff.' : '. Use "Promote an existing account" instead.')); ax_back(); }
            $pdo->prepare("INSERT INTO users (name, email, password, role, phone, status, email_verified) VALUES (?, ?, ?, 'staff', ?, 'active', 1)")
                ->execute([$name, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $phone]);
            $uid = (int)$pdo->lastInsertId();
            sda_staff_save($pdo, $uid, $dept, $title, $mods, (int)$user['id']);
            $err = '';
            $ok = sda_staff_invite_email($pdo, ['name' => $name, 'email' => $email], SDA_STAFF_DEPTS[$dept][0] ?? 'General', true, $err);
            $log("Invited staff #$uid ($email) to $dept");
            ax_flash($ok ? 'ok' : 'err', $ok ? "$name was added and emailed a link to set their password (valid 72 hours)." : "$name was added, but the invite email failed: $err. Use \"Send password link\" to try again.");
        } elseif ($act === 'promote') {
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $st = $pdo->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
            $st->execute([$email]);
            $u = $st->fetch();
            if (!$u) { ax_flash('err', 'No account uses that email. Use "Invite new staff" instead.'); ax_back(); }
            if ($u['role'] === 'admin') { ax_flash('err', 'That account is an admin and already has full access.'); ax_back(); }
            if ($u['role'] === 'staff') { ax_flash('err', 'That account is already staff. Edit it in the list below.'); ax_back(); }
            sda_staff_save($pdo, (int)$u['id'], $dept, $title, $mods, (int)$user['id']);
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$u['id']]);
            $err = '';
            $ok = !empty($_POST['notify']) ? sda_staff_invite_email($pdo, $u, SDA_STAFF_DEPTS[$dept][0] ?? 'General', false, $err) : true;
            $log("Promoted #{$u['id']} ({$u['email']}) from {$u['role']} to staff ($dept)");
            ax_flash('ok', $u['name'] . ' is now staff' . (!empty($_POST['notify']) ? ($ok ? ' and has been emailed.' : ', but the email failed: ' . $err) : '.') . ($u['role'] === 'tutor' ? ' Note: they no longer have the tutor portal.' : ''));
        } elseif ($act === 'edit') {
            $uid = (int)($_POST['uid'] ?? 0);
            if (!sda_staff_record($pdo, $uid)) { ax_flash('err', 'Staff member not found.'); ax_back(); }
            sda_staff_save($pdo, $uid, $dept, $title, $mods, (int)$user['id']);
            $log("Updated staff #$uid: $dept, " . count(sda_staff_clean_modules($mods)) . ' tools');
            ax_flash('ok', 'Access updated. Changes apply on their next page load.');
        } elseif ($act === 'status') {
            $uid = (int)($_POST['uid'] ?? 0);
            $to = ($_POST['to'] ?? '') === 'suspended' ? 'suspended' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'staff'")->execute([$to, $uid]);
            $log("Staff #$uid set to $to");
            ax_flash('ok', $to === 'suspended' ? 'Account suspended. They can no longer sign in.' : 'Account reactivated.');
        } elseif ($act === 'link') {
            $r = sda_staff_record($pdo, (int)($_POST['uid'] ?? 0));
            if (!$r) { ax_flash('err', 'Staff member not found.'); ax_back(); }
            $err = '';
            $ok = sda_staff_invite_email($pdo, $r, SDA_STAFF_DEPTS[$r['department']][0] ?? 'General', true, $err);
            ax_flash($ok ? 'ok' : 'err', $ok ? 'Password link emailed to ' . $r['email'] . ' (valid 72 hours).' : 'Email failed: ' . $err);
        } elseif ($act === 'remove') {
            $uid = (int)($_POST['uid'] ?? 0);
            $pdo->prepare("UPDATE users SET role = 'student' WHERE id = ? AND role = 'staff'")->execute([$uid]);
            $pdo->prepare("DELETE FROM staff_members WHERE user_id = ?")->execute([$uid]);
            $log("Removed staff access from #$uid (now a learner account)");
            ax_flash('ok', 'Staff access removed. The account is now an ordinary learner account.');
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        ax_flash('err', 'Could not save: ' . $e->getMessage());
    }
    ax_back();
}

// Clean up: users switched to staff elsewhere get a record; records whose user is no longer staff are dropped
$pdo->exec("INSERT IGNORE INTO staff_members (user_id, department, modules) SELECT id, 'general', '[]' FROM users WHERE role = 'staff'");
$pdo->exec("DELETE s FROM staff_members s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL OR u.role <> 'staff'");
$staff = $pdo->query("SELECT s.*, u.name, u.email, u.phone, u.status, u.last_login, u.avatar FROM staff_members s JOIN users u ON u.id = s.user_id ORDER BY u.status = 'suspended', s.department, u.name")->fetchAll();
foreach ($staff as &$s) $s['mods'] = sda_staff_clean_modules(json_decode((string)$s['modules'], true) ?: []);
unset($s);
$byDept = array_count_values(array_column($staff, 'department'));
$active = count(array_filter($staff, fn($s) => $s['status'] === 'active'));
$never = count(array_filter($staff, fn($s) => !$s['last_login']));

require_once 'includes/sidebar.php';
?>
<style>
  .st-depts { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; }
  .st-dept { border: 1px solid var(--border); border-radius: 14px; padding: 12px 14px; display: flex; gap: 12px; align-items: center; background: var(--surface); }
  .st-dept i { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; color: #fff; flex: 0 0 auto; }
  .st-dept b { display: block; font-size: .84rem; color: var(--text-main); line-height: 1.3; } .st-dept small { color: var(--text-muted); font-size: .76rem; }
  .st-mods { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 6px; }
  .st-mods label { display: flex; gap: 8px; align-items: flex-start; font-size: .82rem; padding: 8px 10px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; line-height: 1.35; }
  .st-mods label:has(input:checked) { border-color: #0ea5e9; background: rgba(14,165,233,.08); }
  .st-mods input { margin-top: 2px; } .st-mods small { display: block; color: var(--text-muted); font-size: .72rem; }
  .st-tags { display: flex; flex-wrap: wrap; gap: 4px; max-width: 360px; }
  .st-tags span { font-size: .7rem; padding: 2px 8px; border-radius: 99px; background: var(--surface-2); border: 1px solid var(--border); color: var(--text-dim); }
  .st-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; vertical-align: 0; }
</style>
<main class="main-content">
    <?php ax_header('Staff', '', 'Team members, departments and what each can access',
        '<button type="button" class="ax-btn ghost" data-open="dlg-promote"><i class="fas fa-user-plus"></i> Promote an existing account</button> <button type="button" class="ax-btn" data-open="dlg-invite"><i class="fas fa-paper-plane"></i> Invite new staff</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('id-badge', count($staff), 'Staff members', $active . ' active', 'violet') ?>
            <?= ax_stat('hourglass-half', $never, 'Not signed in yet', 'Invited but no sign-in so far', $never ? 'amber' : '') ?>
            <?= ax_stat('shield-halved', 'Limited', 'Access', 'Staff see only their tools; never users, settings or money', 'green') ?>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-sitemap"></i> Departments</h2><p>Each department has a dashboard and a default set of tools. You can add or remove tools per person.</p></div></div>
            <div class="ax-panel-b"><div class="st-depts">
                <?php foreach (SDA_STAFF_DEPTS as $k => $d): if ($k === 'general' && empty($byDept['general'])) continue; ?>
                <div class="st-dept"><i class="fas fa-<?= e($d[1]) ?>" style="background:<?= e($d[2]) ?>"></i><div><b><?= e($d[0]) ?></b><small><?= (int)($byDept[$k] ?? 0) ?> member<?= ($byDept[$k] ?? 0) == 1 ? '' : 's' ?> · <?= count($d[4]) ?> tools</small></div></div>
                <?php endforeach; ?>
            </div></div>
        </section>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-users-gear"></i> Team</h2><p>Staff sign in at the normal sign-in page, confirm with an emailed code, and land on their department dashboard.</p></div></div>
            <?php if (!$staff): ?>
                <div class="ax-empty"><i class="fas fa-id-badge"></i><b>No staff yet</b>Invite your new hires, or promote an existing account.<div style="margin-top:12px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap"><button type="button" class="ax-btn sm" data-open="dlg-invite">Invite new staff</button><button type="button" class="ax-btn ghost sm" data-open="dlg-promote">Promote an account</button></div></div>
            <?php else: ?>
            <div class="ax-table-wrap"><table class="ax-table cards">
                <thead><tr><th>Staff member</th><th>Department</th><th>Tools</th><th>Last sign-in</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($staff as $s): $d = SDA_STAFF_DEPTS[$s['department']] ?? SDA_STAFF_DEPTS['general']; ?>
                    <tr>
                        <td data-label="Staff member"><div class="ax-who"><?= ax_avatar($s['name'], $s['avatar']) ?><div><b><?= e($s['name']) ?></b><small><?= e($s['email']) ?></small><?php if ($s['status'] !== 'active'): ?> <span class="ax-pill bad"><?= e(ucfirst($s['status'])) ?></span><?php endif; ?></div></div></td>
                        <td data-label="Department"><span class="st-dot" style="background:<?= e($d[2]) ?>"></span><?= e($d[0]) ?><?php if ($s['job_title']): ?><div style="font-size:.78rem;color:var(--text-muted)"><?= e($s['job_title']) ?></div><?php endif; ?></td>
                        <td data-label="Tools"><div class="st-tags"><?php foreach ($s['mods'] as $m): ?><span><?= e(SDA_STAFF_MODULES[$m][0]) ?></span><?php endforeach; ?><?= $s['mods'] ? '' : '<span style="border-color:#f59e0b;color:#b45309">No tools yet</span>' ?></div></td>
                        <td data-label="Last sign-in" class="num"><?= $s['last_login'] ? e(ax_date($s['last_login'], 'M j, g:i a')) : '<span class="ax-pill warn">Never</span>' ?></td>
                        <td class="actions" data-label="">
                            <button type="button" class="ax-btn ghost sm" data-open="dlg-edit-<?= (int)$s['user_id'] ?>"><i class="fas fa-pen"></i> Access</button>
                            <form method="post" style="display:inline"><?= ax_csrf() ?><input type="hidden" name="action" value="link"><input type="hidden" name="uid" value="<?= (int)$s['user_id'] ?>"><button class="ax-btn ghost sm" title="Email a link to set a new password"><i class="fas fa-key"></i> Password link</button></form>
                            <form method="post" style="display:inline" <?= $s['status'] === 'active' ? 'data-confirm="Suspend ' . e($s['name']) . '? They won\'t be able to sign in until reactivated." data-danger' : '' ?>><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="uid" value="<?= (int)$s['user_id'] ?>"><input type="hidden" name="to" value="<?= $s['status'] === 'active' ? 'suspended' : 'active' ?>"><button class="ax-btn ghost sm"><i class="fas fa-<?= $s['status'] === 'active' ? 'user-lock' : 'user-check' ?>"></i> <?= $s['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
/** Department + title + tools fields, used by every dialog. */
function st_fields(string $id, string $dept, ?string $title, array $mods): void { ?>
    <div class="ax-row">
        <div class="ax-field"><label for="<?= $id ?>-d">Department</label><select id="<?= $id ?>-d" name="department" class="st-dept-sel" data-target="<?= $id ?>-mods"><?php foreach (SDA_STAFF_DEPTS as $k => $d): ?><option value="<?= $k ?>" <?= $dept === $k ? 'selected' : '' ?>><?= e($d[0]) ?></option><?php endforeach; ?></select></div>
        <div class="ax-field"><label for="<?= $id ?>-t">Job title</label><input id="<?= $id ?>-t" name="job_title" maxlength="120" value="<?= e($title ?? '') ?>" placeholder="e.g. Technology & Innovation Officer"></div>
    </div>
    <div class="ax-field"><label>Tools they can use <span style="font-weight:500;color:var(--text-muted)">(choosing a department ticks its defaults)</span></label>
        <div class="st-mods" id="<?= $id ?>-mods"><?php foreach (SDA_STAFF_MODULES as $k => $m): ?>
            <label><input type="checkbox" name="modules[]" value="<?= $k ?>" <?= in_array($k, $mods, true) ? 'checked' : '' ?>><span><i class="fas fa-<?= e($m[1]) ?>" style="width:14px;color:var(--text-muted)"></i> <?= e($m[0]) ?><small><?= e($m[3]) ?></small></span></label>
        <?php endforeach; ?></div>
        <div class="hint" style="margin-top:6px">Never available to staff: users &amp; roles, platform settings, payments, revenue, payroll, the mailbox and the admin dashboard.</div>
    </div>
<?php } ?>

<dialog class="ax-modal" id="dlg-invite" style="width:min(780px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h3><i class="fas fa-paper-plane"></i> Invite new staff</h3><p>Creates their account and emails a link to set a password.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="invite">
            <div class="ax-row">
                <div class="ax-field"><label for="i-n">Full name</label><input id="i-n" name="name" required maxlength="150"></div>
                <div class="ax-field"><label for="i-e">Email</label><input id="i-e" type="email" name="email" required maxlength="191"></div>
                <div class="ax-field"><label for="i-p">Phone (optional)</label><input id="i-p" name="phone" maxlength="20"></div>
            </div>
            <?php st_fields('inv', 'academics', '', SDA_STAFF_DEPTS['academics'][4]); ?>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-paper-plane"></i> Create &amp; send invite</button></div>
        </div>
    </form>
</dialog>

<dialog class="ax-modal" id="dlg-promote" style="width:min(780px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h3><i class="fas fa-user-plus"></i> Promote an existing account</h3><p>For someone who already has a learner or tutor account. They keep their password.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="promote">
            <div class="ax-field"><label for="p-e">Their account email</label><input id="p-e" type="email" name="email" required maxlength="191" placeholder="name@example.com"><div class="hint">A tutor who becomes staff loses the tutor portal.</div></div>
            <?php st_fields('pro', 'academics', '', SDA_STAFF_DEPTS['academics'][4]); ?>
            <label style="display:flex;gap:8px;align-items:center;font-size:.86rem;margin-bottom:12px"><input type="checkbox" name="notify" value="1" checked> Email them that they now have staff access</label>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-user-plus"></i> Make staff</button></div>
        </div>
    </form>
</dialog>

<?php foreach ($staff as $s): $sid = (int)$s['user_id']; ?>
<dialog class="ax-modal" id="dlg-edit-<?= $sid ?>" style="width:min(780px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h3><i class="fas fa-pen"></i> <?= e($s['name']) ?></h3><p><?= e($s['email']) ?></p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="uid" value="<?= $sid ?>">
            <?php st_fields('e' . $sid, $s['department'], $s['job_title'], $s['mods']); ?>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save access</button></div>
        </div>
    </form>
    <form method="post" style="padding:0 22px 20px" data-confirm="Remove staff access from <?= e($s['name']) ?>? Their account becomes an ordinary learner account." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="uid" value="<?= $sid ?>"><button class="ax-btn danger sm"><i class="fas fa-user-minus"></i> Remove staff access</button></form>
</dialog>
<?php endforeach; ?>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    var DEF = <?= json_encode(array_map(fn($d) => $d[4], SDA_STAFF_DEPTS)) ?>;
    document.querySelectorAll('dialog.ax-modal').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });
    document.querySelectorAll('.st-dept-sel').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var box = document.getElementById(sel.dataset.target), want = DEF[sel.value] || [];
            box.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = want.indexOf(c.value) > -1; });
        });
    });
})();
</script>
</body></html>
