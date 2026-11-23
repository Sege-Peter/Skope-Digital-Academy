<?php
/** Admin: announcements on the students' News page – create, edit, schedule, pin, delete. */
$pageTitle = 'Announcements';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        $title   = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? $d : null;
        $start = $valid($_POST['start_date'] ?? '') ?? date('Y-m-d');
        $end   = $valid($_POST['end_date'] ?? '');
        $pin   = isset($_POST['is_pinned']) ? 1 : 0;
        if ($title === '' || $content === '') { ax_flash('err', 'Add a title and the announcement text.'); ax_back(); }
        if ($end && $end < $start) { ax_flash('err', 'The end date must be on or after the start date.'); ax_back(); }
        if ($id) {
            $pdo->prepare("UPDATE announcements SET title = ?, content = ?, start_date = ?, end_date = ?, is_pinned = ? WHERE id = ?")
                ->execute([mb_substr($title, 0, 255), $content, $start, $end, $pin, $id]);
            ax_flash('ok', 'Announcement updated.');
        } else {
            $pdo->prepare("INSERT INTO announcements (title, content, start_date, end_date, is_pinned, created_by) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([mb_substr($title, 0, 255), $content, $start, $end, $pin, (int)$user['id']]);
            $annMailed = 0;
            if (!empty($_POST['email_learners']) && $start <= date('Y-m-d')) {
                require_once __DIR__ . '/../includes/notify.php';
                $annId = (int)$pdo->lastInsertId();
                $annHtml = nl2br(preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', htmlspecialchars($content, ENT_QUOTES, 'UTF-8')));
                foreach ($pdo->query("SELECT id FROM users WHERE role = 'student' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                    if (sda_notify('announcement', (int)$sid, ['title' => $title, 'content' => '<p>' . $annHtml . '</p>'], ['inapp' => false, 'dedupe' => "ann:$annId:$sid"])) $annMailed++;
                }
            }
            ax_flash('ok', ($start > date('Y-m-d') ? 'Announcement scheduled for ' . ax_date($start) . '.' : 'Announcement posted.') . ($annMailed ? " Emailing $annMailed learners." : ''));
        }
        ax_back();
    }
    if ($act === 'pin') {
        $pdo->prepare("UPDATE announcements SET is_pinned = 1 - is_pinned WHERE id = ?")->execute([$id]);
        ax_flash('ok', 'Pin updated.'); ax_back();
    }
    if ($act === 'end') {
        $pdo->prepare("UPDATE announcements SET end_date = ? WHERE id = ? AND start_date <= CURDATE()")->execute([date('Y-m-d', strtotime('-1 day')), $id]);
        ax_flash('ok', 'Announcement taken down. It stays in the history below.'); ax_back();
    }
    if ($act === 'delete') {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        ax_flash('ok', 'Announcement deleted.'); ax_back();
    }
    ax_back();
}

$today = date('Y-m-d');
$rows = $pdo->query("SELECT a.*, u.name AS author FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.is_pinned DESC, a.created_at DESC")->fetchAll();
$stateOf = fn($a) => $a['start_date'] > $today ? 'scheduled' : (($a['end_date'] && $a['end_date'] < $today) ? 'ended' : 'live');
$counts = ['all' => count($rows), 'live' => 0, 'scheduled' => 0, 'ended' => 0];
foreach ($rows as &$a) { $a['state'] = $stateOf($a); $counts[$a['state']]++; } unset($a);
$pinned = count(array_filter($rows, fn($a) => $a['is_pinned'] && $a['state'] === 'live'));
$tab = array_key_exists($_GET['tab'] ?? '', $counts) ? $_GET['tab'] : 'all';
$list = $tab === 'all' ? $rows : array_values(array_filter($rows, fn($a) => $a['state'] === $tab));
$statePill = ['live' => ['ok', 'Live'], 'scheduled' => ['info', 'Scheduled'], 'ended' => ['dark', 'Ended']];
$students = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");

require_once 'includes/sidebar.php';
$actions = '<a class="ax-btn ghost" href="notifications"><i class="fas fa-bell"></i> Send a notification</a><button class="ax-btn" type="button" data-new><i class="fas fa-plus"></i> New announcement</button>';
?>
<style>
  .main-content { min-width: 0; }
  .an-text { font-size: .8rem; color: var(--text-dim); line-height: 1.5; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 520px; }
  .an-acts { display: inline-flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
  @media (max-width: 760px) { .an-text { max-width: none; text-align: left; } #anTable td[data-label=""] > div { width: 100%; } }
</style>
<main class="main-content">
    <?php ax_header('Announcements', '', 'News shown on every student dashboard', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <p class="ax-intro">Announcements appear on the students' <b>News &amp; Announcements</b> page between their start and end dates. Pinned ones stay at the top. For a message to one person or a single role, use <a href="notifications">Notifications</a>.</p>
        <div class="ax-stats">
            <?= ax_stat('bullhorn', $counts['live'], 'Live now', $pinned . ' pinned', 'green') ?>
            <?= ax_stat('calendar-plus', $counts['scheduled'], 'Scheduled', 'Start in the future') ?>
            <?= ax_stat('box-archive', $counts['ended'], 'Ended', 'Past their end date', 'slate') ?>
            <?= ax_stat('user-graduate', number_format($students), 'Active students', 'Who can see live news', 'violet') ?>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h">
                <nav class="ax-tabs" aria-label="Filter">
                    <?php foreach (['all' => 'All', 'live' => 'Live', 'scheduled' => 'Scheduled', 'ended' => 'Ended'] as $k => $l): ?>
                    <a href="announcements<?= $k === 'all' ? '' : '?tab=' . $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $counts[$k] ?></span></a><?php endforeach; ?>
                </nav>
                <div class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Filter this list…" data-filter-table="anTable" aria-label="Filter announcements"></div>
            </div>
            <?php if (!$list): ?>
                <div class="ax-empty"><i class="fas fa-bullhorn"></i><b>No announcements here</b>Use “New announcement” to post one.</div>
            <?php else: ?>
            <div class="ax-table-wrap"><table class="ax-table cards" id="anTable">
                <thead><tr><th>Announcement</th><th>Status</th><th>Audience</th><th>Shows</th><th>Posted</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody><?php foreach ($list as $a): [$pc, $pl] = $statePill[$a['state']];
                    $data = e(json_encode(['id' => (int)$a['id'], 'title' => $a['title'], 'content' => $a['content'], 'start' => $a['start_date'], 'end' => (string)$a['end_date'], 'pin' => (int)$a['is_pinned']])); ?>
                <tr>
                    <td data-label=""><div><b class="strong"><?php if ($a['is_pinned']): ?><i class="fas fa-thumbtack" style="color:var(--secondary)" title="Pinned"></i> <?php endif; ?><?= e($a['title']) ?></b><div class="an-text"><?= e($a['content']) ?></div></div></td>
                    <td data-label="Status"><span class="ax-pill <?= $pc ?>"><?= $pl ?></span></td>
                    <td data-label="Audience"><span class="muted">All students</span></td>
                    <td data-label="Shows" class="num"><?= ax_date($a['start_date']) ?> → <?= $a['end_date'] ? ax_date($a['end_date']) : 'no end' ?></td>
                    <td data-label="Posted" class="num"><div><?= ax_date($a['created_at']) ?><div class="muted"><?= e($a['author'] ?? '—') ?></div></div></td>
                    <td data-label="" class="actions"><div class="an-acts">
                        <button type="button" class="ax-btn ghost sm icon" title="Edit" aria-label="Edit" data-edit="<?= $data ?>"><i class="fas fa-pen"></i></button>
                        <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="pin"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="ax-btn ghost sm icon" title="<?= $a['is_pinned'] ? 'Unpin' : 'Pin to top' ?>" aria-label="<?= $a['is_pinned'] ? 'Unpin' : 'Pin' ?>"><i class="fas fa-thumbtack" <?= $a['is_pinned'] ? 'style="color:var(--secondary)"' : '' ?>></i></button></form>
                        <?php if ($a['state'] === 'live'): ?>
                        <form method="post" data-confirm="Take “<?= e($a['title']) ?>” down now? It moves to Ended and can be edited back."><?= ax_csrf() ?><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="ax-btn ghost sm icon" title="End now" aria-label="End now"><i class="fas fa-stop"></i></button></form>
                        <?php endif; ?>
                        <form method="post" data-confirm="Delete “<?= e($a['title']) ?>” permanently?" data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="ax-btn danger sm icon" title="Delete" aria-label="Delete"><i class="fas fa-trash"></i></button></form>
                    </div></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </section>
        <p class="ax-intro" style="margin:0">Reads aren't tracked for announcements – they're a public notice board for all students.</p>
    </div>
</main>

<dialog class="ax-modal" id="dlgAn" aria-labelledby="dlgAnT">
    <div class="ax-panel-h"><h2 id="dlgAnT"><i class="fas fa-bullhorn"></i> <span data-f="h">New announcement</span></h2><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <form method="post" class="ax-panel-b ax-form">
        <?= ax_csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="an-id" value="">
        <div class="ax-field"><label for="an-title">Title</label><input id="an-title" type="text" name="title" required maxlength="255" placeholder="e.g. New Python course starts Monday"></div>
        <div class="ax-field"><label for="an-content">Message</label><textarea id="an-content" name="content" required rows="7" placeholder="What do students need to know?"></textarea></div>
        <div class="ax-row">
            <div class="ax-field"><label for="an-start">Show from</label><input id="an-start" type="date" name="start_date" value="<?= $today ?>"></div>
            <div class="ax-field"><label for="an-end">Show until (optional)</label><input id="an-end" type="date" name="end_date"><div class="hint">Leave empty to keep it up until you end it.</div></div>
        </div>
        <label class="ax-check" style="margin-bottom:14px"><input type="checkbox" name="is_pinned" id="an-pin"> Pin to the top of the news page</label>
        <label class="ax-check" style="margin-bottom:14px"><input type="checkbox" name="email_learners" value="1"> Also email it to all active learners (new announcements starting today; learners who opted out are skipped)</label>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-paper-plane"></i> <span data-f="btn">Post announcement</span></button></div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function(){
  var d = document.getElementById('dlgAn'), $ = function(s){ return d.querySelector(s); };
  function open(a){
    $('#an-id').value = a ? a.id : ''; $('#an-title').value = a ? a.title : ''; $('#an-content').value = a ? a.content : '';
    $('#an-start').value = a ? a.start : '<?= $today ?>'; $('#an-end').value = a ? a.end : ''; $('#an-pin').checked = a ? !!a.pin : false;
    $('[data-f=h]').textContent = a ? 'Edit announcement' : 'New announcement';
    $('[data-f=btn]').textContent = a ? 'Save changes' : 'Post announcement';
    d.showModal();
  }
  document.querySelectorAll('[data-new]').forEach(function(b){ b.addEventListener('click', function(){ open(null); }); });
  document.querySelectorAll('[data-edit]').forEach(function(b){ b.addEventListener('click', function(){ open(JSON.parse(b.dataset.edit)); }); });
})();
</script>
</body></html>
