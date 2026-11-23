<?php
/** Admin: every account – create, edit, approve/suspend, change role, reset password, view as student, delete. */
$pageTitle = 'Users';
require_once 'includes/header.php';

$me = (int)$user['id'];

/** Where to go after a POST: the profile page that sent it, or back to this list. */
function usr_return(): ?string {
    $b = (string)($_POST['back'] ?? '');
    return preg_match('~^user-profile\?id=\d+$~', $b) ? $b : null;
}
function usr_active_admins(PDO $pdo): int {
    return (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
}
function usr_temp_password(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < 10; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p . random_int(10, 99);
}
function usr_log(PDO $pdo, int $uid, string $action, string $desc): void {
    try {
        $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)")
            ->execute([$uid, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* logging must never block the action */ }
}

/* ── CSV export (read-only GET) ─────────────────────────────────────── */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query("SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.xp, u.created_at, u.last_login,
            (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id) enrolments,
            (SELECT COUNT(*) FROM certificates c WHERE c.student_id = u.id) certificates
        FROM users u ORDER BY u.created_at DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'XP', 'Level', 'Enrolments', 'Certificates', 'Joined', 'Last sign-in']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['name'], $r['email'], $r['phone'], $r['role'], $r['status'], (int)$r['xp'],
            xp_level((int)$r['xp'])['level'], $r['enrolments'], $r['certificates'], $r['created_at'], $r['last_login']]);
    }
    exit;
}

/* ── Actions ────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act  = $_POST['action'] ?? '';
    $back = usr_return();

    if ($act === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role  = in_array($_POST['role'] ?? '', ['student', 'tutor', 'admin'], true) ? $_POST['role'] : 'student';
        $status = in_array($_POST['status'] ?? '', ['active', 'pending'], true) ? $_POST['status'] : 'active';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { ax_flash('err', 'Enter a name and a valid email address.'); ax_back($back); }
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) { ax_flash('err', 'An account with that email already exists.'); ax_back($back); }
        $pw = usr_temp_password();
        $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status, requires_password_change, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())")
            ->execute([mb_substr($name, 0, 150), $email, $phone !== '' ? mb_substr($phone, 0, 20) : null, password_hash($pw, PASSWORD_DEFAULT), $role, $status]);
        $nid = (int)$pdo->lastInsertId();
        usr_log($pdo, $me, 'user_create', "Created $role account #$nid ($email)");
        require_once __DIR__ . '/../includes/notify.php';
        if (!empty($_POST['email_login'])) sda_notify('account_created_by_admin', $nid, ['role' => $role, 'temp_password' => $pw], ['inapp' => false]);
        $_SESSION['usr_temp_pw'] = ['name' => $name, 'email' => $email, 'pw' => $pw, 'new' => true];
        ax_flash('ok', ucfirst($role) . ' account created for ' . $name . '.');
        ax_back($back);
    }

    $uid = (int)($_POST['uid'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$uid]);
    $target = $st->fetch();
    if (!$target) { ax_flash('err', 'That account no longer exists.'); ax_back('users'); }
    $isSelf = $uid === $me;
    $lastAdmin = $target['role'] === 'admin' && $target['status'] === 'active' && usr_active_admins($pdo) <= 1;

    if ($act === 'status') {
        $new = $_POST['status'] ?? '';
        if (!in_array($new, ['active', 'pending', 'suspended'], true)) ax_back($back);
        if ($new !== 'active' && ($isSelf || $lastAdmin)) {
            ax_flash('err', $isSelf ? 'You cannot suspend your own account.' : 'This is the last active admin, so it cannot be suspended.'); ax_back($back);
        }
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new, $uid]);
        usr_log($pdo, $me, 'user_status', "Set #$uid status to $new");
        if ($new !== $target['status']) {
            require_once __DIR__ . '/../includes/notify.php';
            if ($new === 'suspended') sda_notify('account_suspended', $uid, [], ['inapp' => false]);
            elseif ($new === 'active' && $target['status'] === 'suspended') sda_notify('account_reactivated', $uid);
            elseif ($new === 'active' && $target['role'] === 'tutor') sda_notify('tutor_approved', $uid);
            elseif ($new === 'active') sda_notify('account_activated', $uid, [], ['dedupe' => "activated:$uid"]);
        }
        $word = ['active' => 'activated', 'pending' => 'set to pending', 'suspended' => 'suspended'][$new];
        ax_flash('ok', $target['name'] . ' ' . $word . '.');
        ax_back($back);
    }

    if ($act === 'role') {
        $new = $_POST['role'] ?? '';
        if (!in_array($new, ['student', 'tutor', 'admin', 'staff'], true) || $new === $target['role']) ax_back($back);
        if ($target['role'] === 'admin' && ($isSelf || $lastAdmin)) {
            ax_flash('err', $isSelf ? 'You cannot change your own role.' : 'This is the last active admin, so its role cannot change.'); ax_back($back);
        }
        require_once __DIR__ . '/../includes/staff.php';
        sda_staff_ensure($pdo);   // adds the 'staff' role to the users table first
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new, $uid]);
        if ($new === 'staff') sda_staff_save($pdo, $uid, 'general', '', [], $me);
        elseif ($target['role'] === 'staff') $pdo->prepare("DELETE FROM staff_members WHERE user_id = ?")->execute([$uid]);
        usr_log($pdo, $me, 'user_role', "Changed #$uid role from {$target['role']} to $new");
        ax_flash('ok', $target['name'] . ' is now ' . ($new === 'admin' ? 'an admin' : 'a ' . $new) . '.');
        ax_back($back);
    }

    if ($act === 'edit') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { ax_flash('err', 'Enter a name and a valid email address.'); ax_back($back); }
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $dup->execute([$email, $uid]);
        if ($dup->fetch()) { ax_flash('err', 'Another account already uses that email.'); ax_back($back); }
        $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, show_on_leaderboard = ? WHERE id = ?")
            ->execute([mb_substr($name, 0, 150), $email, $phone !== '' ? mb_substr($phone, 0, 20) : null, isset($_POST['show_on_leaderboard']) ? 1 : 0, $uid]);
        usr_log($pdo, $me, 'user_edit', "Edited details of #$uid");
        ax_flash('ok', 'Details saved for ' . $name . '.');
        ax_back($back);
    }

    if ($act === 'reset_password') {
        $pw = usr_temp_password();
        $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 1 WHERE id = ?")->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
        usr_log($pdo, $me, 'user_password_reset', "Reset password of #$uid");
        if (!empty($_POST['email_login'])) { require_once __DIR__ . '/../includes/notify.php'; sda_notify('password_reset_by_admin', $uid, ['temp_password' => $pw], ['inapp' => false]); }
        $_SESSION['usr_temp_pw'] = ['name' => $target['name'], 'email' => $target['email'], 'pw' => $pw, 'new' => false];
        ax_flash('ok', 'Password reset for ' . $target['name'] . '.');
        ax_back($back);
    }

    if ($act === 'impersonate') {
        if (!in_array($target['role'], ['student', 'tutor'], true)) { ax_flash('err', 'You can open the student or tutor portal, not another admin account.'); ax_back($back); }
        if ($target['status'] !== 'active') { ax_flash('err', 'Activate this account before viewing the site as them.'); ax_back($back); }
        if (isset($_SESSION['admin_impersonator_id'])) { ax_flash('err', 'Stop the current portal view first.'); ax_back($back); }
        usr_log($pdo, $me, 'impersonate', "Opened the {$target['role']} portal as #$uid ({$target['email']})");
        $_SESSION['admin_impersonator_id'] = $me;
        $_SESSION['admin_impersonator_role'] = 'admin';
        // Come back to the admin page the switch started from
        $_SESSION['admin_impersonator_back'] = preg_match('/^[a-z0-9_-]+(\.php)?(\?[^#]*)?$/i', (string)($_POST['back'] ?? '')) ? 'admin/' . $_POST['back'] : 'admin/';
        loginUser($target);
        redirectByRole($target['role']);
    }

    if ($act === 'delete') {
        if ($target['role'] === 'admin' || $isSelf) { ax_flash('err', 'Admin accounts cannot be deleted here. Change the role first.'); ax_back($back); }
        if (strcasecmp(trim($_POST['confirm_email'] ?? ''), $target['email']) !== 0) {
            ax_flash('err', 'Type the account\'s email address exactly to confirm the deletion.'); ax_back($back);
        }
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'")->execute([$uid]);
        } catch (Throwable $e) {
            ax_flash('err', 'This account is linked to records (payments, courses…) and could not be deleted. Suspend it instead.'); ax_back($back);
        }
        usr_log($pdo, $me, 'user_delete', "Deleted #$uid ({$target['email']})");
        ax_flash('ok', $target['name'] . '\'s account was deleted.');
        ax_back('users');
    }
    ax_back($back);
}

/* ── Data ───────────────────────────────────────────────────────────── */
$role   = in_array($_GET['role'] ?? '', ['student', 'tutor', 'admin'], true) ? $_GET['role'] : 'all';
$status = in_array($_GET['status'] ?? '', ['active', 'pending', 'suspended'], true) ? $_GET['status'] : 'all';
$q      = trim((string)($_GET['q'] ?? ''));

$roleCounts = ['all' => 0, 'student' => 0, 'tutor' => 0, 'admin' => 0];
foreach ($pdo->query("SELECT role, COUNT(*) n FROM users GROUP BY role")->fetchAll() as $r) { $roleCounts[$r['role']] = (int)$r['n']; $roleCounts['all'] += (int)$r['n']; }
$sc = $pdo->prepare("SELECT status, COUNT(*) n FROM users WHERE (? = 'all' OR role = ?) GROUP BY status");
$sc->execute([$role, $role]);
$statusCounts = ['all' => 0, 'active' => 0, 'pending' => 0, 'suspended' => 0];
foreach ($sc->fetchAll() as $r) { $statusCounts[$r['status']] = (int)$r['n']; $statusCounts['all'] += (int)$r['n']; }

$where = []; $p = [];
if ($role !== 'all')   { $where[] = 'u.role = ?';   $p[] = $role; }
if ($status !== 'all') { $where[] = 'u.status = ?'; $p[] = $status; }
if ($q !== '')         { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.admission_number LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id) enrolments,
        (SELECT COUNT(*) FROM certificates c WHERE c.student_id = u.id) certs,
        (SELECT COUNT(*) FROM courses c2 WHERE c2.tutor_id = u.id) courses_owned
        FROM users u" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY u.created_at DESC LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($p);
$users = $st->fetchAll();

$stats = [
    'total'   => $roleCounts['all'],
    'new30'   => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 30 DAY"),
    'active7' => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE last_login >= NOW() - INTERVAL 7 DAY"),
    'pending' => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'pending'"),
    'susp'    => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'suspended'"),
];
$activeAdmins = usr_active_admins($pdo);
$tempPw = $_SESSION['usr_temp_pw'] ?? null;
unset($_SESSION['usr_temp_pw']);

function usr_qs(array $over): string {
    global $role, $status, $q;
    $a = array_merge(['role' => $role, 'status' => $status, 'q' => $q], $over);
    $a = array_filter($a, fn($v) => $v !== '' && $v !== 'all');
    return 'users' . ($a ? '?' . http_build_query($a) : '');
}
$rolePill = ['admin' => 'bad', 'tutor' => 'info', 'student' => '', 'staff' => 'violet'];
$statusPill = ['active' => 'ok', 'pending' => 'warn', 'suspended' => 'bad'];

require_once 'includes/sidebar.php';
$actions = '<a class="ax-btn ghost" href="users?export=csv"><i class="fas fa-file-csv"></i> Export CSV</a>'
         . '<button class="ax-btn" type="button" data-open="dlgCreate"><i class="fas fa-user-plus"></i> Add user</button>';
?>
<style>
  .main-content { min-width: 0; }
  .ax-stats { grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); }
  #usersTable th, #usersTable td { padding-left: 12px; padding-right: 12px; }
  #usersTable th:first-child, #usersTable td:first-child { padding-left: 20px; }
  #usersTable .ax-who b, #usersTable .ax-who small { max-width: 210px; }
  .usr-actions { display: inline-flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
  .usr-lvl { display: flex; flex-direction: column; gap: 4px; min-width: 110px; }
  .usr-lvl .ax-bar { height: 6px; }
  .usr-temp { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; background: color-mix(in srgb, var(--warning) 10%, var(--surface)); border: 1px solid color-mix(in srgb, var(--warning) 40%, transparent); border-radius: 16px; padding: 16px 18px; margin-bottom: 22px; }
  .usr-temp .ax-code { font-size: 1rem; padding: 6px 12px; user-select: all; }
  .usr-temp p { font-size: .84rem; color: var(--text-dim); margin: 0; flex: 1 1 260px; line-height: 1.5; }
  .usr-filter { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
  .ax-toolbar form.ax-search { margin: 0; }
  @media (max-width: 760px) { .usr-actions { justify-content: flex-end; } }
</style>
<main class="main-content">
    <?php ax_header('Users', '', 'Students, tutors and admins', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <?php if ($tempPw): ?>
        <div class="usr-temp" role="status">
            <span class="ax-icon-badge" style="background:color-mix(in srgb, var(--warning) 16%, transparent);color:var(--warning)"><i class="fas fa-key"></i></span>
            <div style="min-width:0">
                <div style="font-weight:800;color:var(--text-main);font-size:.9rem"><?= $tempPw['new'] ? 'Temporary password for the new account' : 'New temporary password' ?> – <?= e($tempPw['name']) ?> (<?= e($tempPw['email']) ?>)</div>
                <div style="margin-top:8px"><span class="ax-code" id="tempPw"><?= e($tempPw['pw']) ?></span>
                    <button type="button" class="ax-btn ghost sm" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('tempPw').textContent);this.innerHTML='<i class=\'fas fa-check\'></i> Copied'"><i class="fas fa-copy"></i> Copy</button></div>
            </div>
            <p>This is shown only once. Share it privately – they will be asked to choose a new password when they sign in.</p>
        </div>
        <?php endif; ?>

        <div class="ax-stats">
            <?= ax_stat('users', number_format($stats['total']), 'All accounts', $roleCounts['student'] . ' student' . ($roleCounts['student'] === 1 ? '' : 's') . ' · ' . $roleCounts['tutor'] . ' tutor' . ($roleCounts['tutor'] === 1 ? '' : 's') . ' · ' . $roleCounts['admin'] . ' admin' . ($roleCounts['admin'] === 1 ? '' : 's')) ?>
            <?= ax_stat('user-plus', number_format($stats['new30']), 'Joined in 30 days', '', 'green') ?>
            <?= ax_stat('right-to-bracket', number_format($stats['active7']), 'Signed in this week', '', 'violet') ?>
            <?= ax_stat('hourglass-half', number_format($stats['pending']), 'Waiting for approval', '', 'amber') ?>
            <?= ax_stat('user-slash', number_format($stats['susp']), 'Suspended', '', 'red') ?>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h" style="align-items:flex-start">
                <div class="usr-filter">
                    <nav class="ax-tabs" aria-label="Role">
                        <?php foreach (['all' => 'Everyone', 'student' => 'Students', 'tutor' => 'Tutors', 'admin' => 'Admins'] as $k => $l): ?>
                        <a href="<?= e(usr_qs(['role' => $k, 'status' => 'all'])) ?>" class="<?= $role === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $roleCounts[$k] ?></span></a>
                        <?php endforeach; ?>
                    </nav>
                    <nav class="ax-tabs" aria-label="Status">
                        <?php foreach (['all' => 'Any status', 'active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'] as $k => $l): ?>
                        <a href="<?= e(usr_qs(['status' => $k])) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $statusCounts[$k] ?></span></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <form class="ax-search" method="get" action="users" role="search">
                    <?php if ($role !== 'all'): ?><input type="hidden" name="role" value="<?= e($role) ?>"><?php endif; ?>
                    <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                    <i class="fas fa-search"></i>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone…" aria-label="Search users">
                </form>
            </div>
            <?php if (!$users): ?>
                <div class="ax-empty"><i class="fas fa-user-group"></i><b>No accounts match</b><?= $q !== '' ? 'Try a different search.' : 'Nothing in this group yet.' ?></div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards" id="usersTable">
                    <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>XP &amp; level</th><th>Learning</th><th>Joined</th><th>Last sign-in</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $lv = xp_level((int)$u['xp']);
                        $self = (int)$u['id'] === $me;
                        $protect = $u['role'] === 'admin' && ($self || ($u['status'] === 'active' && $activeAdmins <= 1));
                        $data = e(json_encode(['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'phone' => (string)$u['phone'], 'role' => $u['role'], 'lb' => (int)$u['show_on_leaderboard'], 'protect' => $protect]));
                    ?>
                    <tr>
                        <td data-label=""><a href="user-profile?id=<?= (int)$u['id'] ?>" class="ax-who" style="text-decoration:none"><?= ax_avatar($u['name'], $u['avatar']) ?><div><b><?= e($u['name']) ?><?= $self ? ' <span class="ax-pill info" style="margin-left:4px">You</span>' : '' ?></b><small><?= e($u['email']) ?></small></div></a></td>
                        <td data-label="Role"><div><span class="ax-pill <?= $rolePill[$u['role']] ?>"><?= e(ucfirst($u['role'])) ?></span><?= $u['role'] === 'tutor' ? '<div class="muted" style="margin-top:4px">' . (int)$u['courses_owned'] . ' course' . ((int)$u['courses_owned'] === 1 ? '' : 's') . '</div>' : '' ?></div></td>
                        <td data-label="Status"><div><span class="ax-pill <?= $statusPill[$u['status']] ?? '' ?>"><?= e(ucfirst($u['status'])) ?></span><?= $u['requires_password_change'] ? '<div class="muted" style="margin-top:4px" title="Must choose a new password at next sign-in"><i class="fas fa-key"></i> temp password</div>' : '' ?></div></td>
                        <td data-label="XP &amp; level"><div class="usr-lvl"><span class="strong num"><?= number_format((int)$u['xp']) ?> XP · L<?= $lv['level'] ?></span><div class="ax-bar" title="<?= $lv['pct'] ?>% to level <?= $lv['level'] + 1 ?>"><i style="width:<?= $lv['pct'] ?>%"></i></div><span class="muted"><?= e($lv['title']) ?></span></div></td>
                        <td data-label="Learning" class="num"><div><span class="strong"><?= (int)$u['enrolments'] ?></span> enrolment<?= (int)$u['enrolments'] === 1 ? '' : 's' ?><div class="muted"><?= (int)$u['certs'] ?> certificate<?= (int)$u['certs'] === 1 ? '' : 's' ?></div></div></td>
                        <td data-label="Joined" class="num"><?= ax_date($u['created_at']) ?></td>
                        <td data-label="Last sign-in" class="num"><?= ax_ago($u['last_login']) ?></td>
                        <td data-label="" class="actions"><div class="usr-actions">
                            <?php if ($u['status'] !== 'active'): ?>
                            <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><input type="hidden" name="status" value="active">
                                <button class="ax-btn ok sm" title="Activate"><i class="fas fa-check"></i> <?= $u['status'] === 'pending' ? 'Approve' : 'Activate' ?></button></form>
                            <?php elseif (!$protect): ?>
                            <form method="post" data-confirm="Suspend <?= e($u['name']) ?>? They won't be able to sign in until you activate the account again." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><input type="hidden" name="status" value="suspended">
                                <button class="ax-btn danger sm icon" title="Suspend" aria-label="Suspend <?= e($u['name']) ?>"><i class="fas fa-user-slash"></i></button></form>
                            <?php endif; ?>
                            <button type="button" class="ax-btn ghost sm icon" title="Edit, role &amp; password" aria-label="Manage <?= e($u['name']) ?>" data-manage="<?= $data ?>"><i class="fas fa-pen"></i></button>
                            <a href="user-profile?id=<?= (int)$u['id'] ?>" class="ax-btn ghost sm icon" title="Open profile" aria-label="Open profile"><i class="fas fa-arrow-right"></i></a>
                        </div></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <p class="ax-intro" style="margin:0">Showing <?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?><?= count($users) >= 500 ? ' (first 500 – narrow with search)' : '' ?>. Suspending blocks sign-in; nothing is deleted. Your own account and the last active admin are protected.</p>
    </div>
</main>

<!-- Add user -->
<dialog class="ax-modal" id="dlgCreate" aria-labelledby="dlgCreateT">
    <div class="ax-panel-h"><div><h2 id="dlgCreateT"><i class="fas fa-user-plus"></i> Add a user</h2><p>A temporary password is generated and shown once after saving.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <form method="post" class="ax-panel-b ax-form">
        <?= ax_csrf() ?><input type="hidden" name="action" value="create">
        <div class="ax-row">
            <div class="ax-field"><label for="c-name">Full name</label><input id="c-name" type="text" name="name" required maxlength="150"></div>
            <div class="ax-field"><label for="c-email">Email</label><input id="c-email" type="email" name="email" required></div>
        </div>
        <div class="ax-row">
            <div class="ax-field"><label for="c-phone">Phone (optional)</label><input id="c-phone" type="text" name="phone" maxlength="20" placeholder="07XX XXX XXX"></div>
            <div class="ax-field"><label for="c-role">Role</label><select id="c-role" name="role"><option value="student">Student</option><option value="tutor" selected>Tutor</option><option value="admin">Admin</option></select></div>
            <div class="ax-field"><label for="c-status">Status</label><select id="c-status" name="status"><option value="active">Active</option><option value="pending">Pending approval</option></select></div>
        </div>
        <label class="ax-check" style="margin:4px 0 12px"><input type="checkbox" name="email_login" value="1" checked> Email the sign-in details (temporary password) to this person</label>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-check"></i> Create account</button></div>
    </form>
</dialog>

<!-- Manage user (filled by JS) -->
<dialog class="ax-modal" id="dlgManage" aria-labelledby="dlgManageT">
    <div class="ax-panel-h"><div><h2 id="dlgManageT"><i class="fas fa-user-gear"></i> <span data-f="title">Manage user</span></h2><p data-f="email"></p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <div class="ax-panel-b">
        <form method="post" class="ax-form">
            <?= ax_csrf() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="uid" data-f="uid">
            <div class="ax-row">
                <div class="ax-field"><label for="m-name">Full name</label><input id="m-name" type="text" name="name" required maxlength="150"></div>
                <div class="ax-field"><label for="m-email">Email</label><input id="m-email" type="email" name="email" required></div>
                <div class="ax-field"><label for="m-phone">Phone</label><input id="m-phone" type="text" name="phone" maxlength="20"></div>
            </div>
            <label class="ax-check" style="margin-bottom:12px"><input type="checkbox" name="show_on_leaderboard" id="m-lb"> Show on leaderboards</label>
            <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save details</button></div>
        </form>
        <hr style="border:0;border-top:1px solid var(--border-light);margin:18px 0">
        <div class="ax-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
            <form method="post" class="ax-form" data-role-form>
                <?= ax_csrf() ?><input type="hidden" name="action" value="role"><input type="hidden" name="uid" data-f="uid">
                <div class="ax-field"><label for="m-role">Role</label><select id="m-role" name="role"><option value="student">Student</option><option value="tutor">Tutor</option><option value="admin">Admin</option></select>
                    <div class="hint" data-f="protect" hidden>Your own account and the last active admin keep their role.</div></div>
                <button class="ax-btn ghost block" data-confirm="Change this person's role? Admins get full control of the platform." data-danger><i class="fas fa-user-tag"></i> Change role</button>
            </form>
            <form method="post" class="ax-form">
                <?= ax_csrf() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="uid" data-f="uid">
                <div class="ax-field"><label>Password</label><div class="hint" style="margin:0 0 10px">Creates a temporary password, shown to you once. They must change it at next sign-in.</div></div>
                <label class="ax-check" style="margin:0 0 10px;font-size:.8rem"><input type="checkbox" name="email_login" value="1" checked> Also email it to them</label>
                <button class="ax-btn warn block" data-confirm="Reset this person's password? Their current password stops working immediately." data-danger><i class="fas fa-key"></i> Reset password</button>
            </form>
        </div>
    </div>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function(){
  var d = document.getElementById('dlgManage');
  document.querySelectorAll('[data-manage]').forEach(function(b){
    b.addEventListener('click', function(){
      var u = JSON.parse(b.dataset.manage);
      d.querySelector('[data-f=title]').textContent = u.name;
      d.querySelector('[data-f=email]').textContent = u.email + ' · ' + u.role;
      d.querySelectorAll('[data-f=uid]').forEach(function(i){ i.value = u.id; });
      d.querySelector('#m-name').value = u.name; d.querySelector('#m-email').value = u.email; d.querySelector('#m-phone').value = u.phone;
      d.querySelector('#m-lb').checked = !!u.lb;
      var r = d.querySelector('#m-role'); r.value = u.role; r.disabled = !!u.protect;
      d.querySelector('[data-role-form] button').disabled = !!u.protect;
      d.querySelector('[data-f=protect]').hidden = !u.protect;
      d.showModal();
    });
  });
})();
</script>
</body></html>
