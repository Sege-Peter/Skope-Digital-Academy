<?php
/**
 * Tutor: direct messages with learners (same `messages` table the learner portal uses at student/messages).
 * A tutor can write to learners enrolled in their courses, and reply to anyone already in a conversation.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

// Learners of my courses
$st = $pdo->prepare("SELECT u.id, u.name, u.avatar, GROUP_CONCAT(DISTINCT c.title ORDER BY c.title SEPARATOR ', ') AS courses
                     FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN users u ON u.id = e.student_id AND u.role = 'student'
                     WHERE c.tutor_id = ? AND e.status IN ('active','completed','pending')
                     GROUP BY u.id, u.name, u.avatar ORDER BY u.name");
$st->execute([$tid]);
$learners = $st->fetchAll();

// Conversations
$st = $pdo->prepare("SELECT u.id, u.name, u.avatar, u.role,
                            (SELECT content FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC, id DESC LIMIT 1) AS last_msg,
                            (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC, id DESC LIMIT 1) AS last_time,
                            (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) AS unread
                     FROM users u
                     WHERE u.id IN (SELECT sender_id FROM messages WHERE receiver_id = ? UNION SELECT receiver_id FROM messages WHERE sender_id = ?)
                     ORDER BY last_time DESC");
$st->execute(array_fill(0, 7, $tid));
$threads = $st->fetchAll();

$allowed = array_map('intval', array_merge(array_column($learners, 'id'), array_column($threads, 'id')));
$to = (int)($_GET['to'] ?? 0);
$receiver = null;
if ($to && $to !== $tid && in_array($to, $allowed, true)) {
    $r = $pdo->prepare("SELECT id, name, avatar, role, email FROM users WHERE id = ?");
    $r->execute([$to]);
    $receiver = $r->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postTo = (int)($_POST['to_id'] ?? 0);
    $text = trim((string)($_POST['message'] ?? ''));
    if (!verifyCsrf()) td_flash('err', 'Your session expired. Please try again.');
    elseif (!$postTo || $postTo === $tid || !in_array($postTo, $allowed, true)) td_flash('err', 'You can only message learners in your courses.');
    elseif ($text === '') td_flash('err', 'Type a message first.');
    else {
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_read) VALUES (?, ?, ?, 0)")->execute([$tid, $postTo, mb_substr($text, 0, 5000)]);
        try {
            require_once __DIR__ . '/../includes/notify.php';
            sda_notify('direct_message', $postTo, ['sender' => $tutor['name'], 'excerpt' => mb_strimwidth($text, 0, 300, '…')],
                ['dedupe' => 'dm:' . $tid . ':' . $postTo . ':' . date('YmdH')]);
        } catch (Throwable $e) { error_log('tutor dm notify: ' . $e->getMessage()); }
    }
    td_redirect('messages' . ($postTo ? '?to=' . $postTo : ''));
}

$thread = [];
if ($receiver) {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")->execute([$to, $tid]);
    $m = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC, id ASC LIMIT 300");
    $m->execute([$tid, $to, $to, $tid]);
    $thread = $m->fetchAll();
    foreach ($threads as &$t) if ((int)$t['id'] === $to) $t['unread'] = 0;
    unset($t);
}

$pageTitle = 'Messages';
$pageSub = 'Private conversations with your learners. They are emailed when you send a message.';
require_once 'includes/header.php';
$csrf = csrfToken();
$threadIds = array_map('intval', array_column($threads, 'id'));
$newPeople = array_values(array_filter($learners, fn($l) => !in_array((int)$l['id'], $threadIds, true)));
?>
<div class="tp-grid side-main">
    <section class="tp-card" id="convos">
        <div class="tp-card-head"><div><h2><i class="fas fa-inbox"></i> Conversations</h2><p><?= count($threads) ?> conversation<?= count($threads) === 1 ? '' : 's' ?></p></div></div>
        <?php if ($threads): ?>
            <ul class="tp-list">
                <?php foreach ($threads as $t): $on = $receiver && (int)$t['id'] === (int)$receiver['id']; ?>
                    <li><a class="tp-row no-indent" href="messages?to=<?= (int)$t['id'] ?>" style="<?= $on ? 'background:var(--tp-primary-soft)' : '' ?>">
                        <?= td_avatar($t['avatar'], $t['name']) ?>
                        <span class="tp-row-main"><span class="tp-row-title"><?= e($t['name']) ?> <?php if ((int)$t['unread']): ?><span class="tp-count"><?= (int)$t['unread'] ?></span><?php endif; ?></span>
                            <span class="tp-row-meta"><?= e(mb_strimwidth((string)$t['last_msg'], 0, 70, '…')) ?> · <?= e(td_ago($t['last_time'])) ?></span></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="tp-empty"><i class="fas fa-comments"></i><h3>No conversations yet</h3><p>Start one below, or wait for a learner to write to you.</p></div>
        <?php endif; ?>
        <?php if ($newPeople): ?>
            <div class="tp-card-foot">
                <form method="get" action="messages" class="tp-filter">
                    <div class="tp-field"><label for="m-new">Start a conversation</label>
                        <select id="m-new" name="to" required><option value="">Choose a learner…</option>
                            <?php foreach ($newPeople as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?> — <?= e($l['courses']) ?></option><?php endforeach; ?></select></div>
                    <button class="tp-btn" type="submit">Open</button>
                </form>
            </div>
        <?php elseif (!$learners && !$threads): ?>
            <div class="tp-card-foot tp-note">You can message learners once someone enrols in one of your courses.</div>
        <?php endif; ?>
    </section>

    <section class="tp-card">
        <?php if ($receiver): ?>
            <div class="tp-card-head">
                <div class="tp-who"><?= td_avatar($receiver['avatar'], $receiver['name']) ?><span><b><?= e($receiver['name']) ?></b><small><?= e(ucfirst($receiver['role'])) ?></small></span></div>
                <div class="tp-actions"><?php if ($receiver['role'] === 'student'): ?><a class="tp-btn sm" href="student_profile?id=<?= (int)$receiver['id'] ?>">Profile</a><?php endif; ?><a class="tp-btn sm" href="messages">All conversations</a></div>
            </div>
            <div class="tp-chat" id="chat">
                <?php if ($thread): foreach ($thread as $m): $me = (int)$m['sender_id'] === $tid; ?>
                    <div class="tp-bubble<?= $me ? ' me' : '' ?>"><?= e($m['content']) ?><small><?= $me ? 'You' : e($receiver['name']) ?> · <?= e(date('j M, g:i a', strtotime($m['created_at']))) ?></small></div>
                <?php endforeach; else: ?>
                    <p class="tp-note" style="text-align:center">No messages yet. Say hello.</p>
                <?php endif; ?>
            </div>
            <div class="tp-card-body">
                <form method="post" action="messages?to=<?= (int)$receiver['id'] ?>" class="tp-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="to_id" value="<?= (int)$receiver['id'] ?>">
                    <div class="tp-field"><label for="m-text" class="tp-sr">Message</label><textarea id="m-text" name="message" style="min-height:90px" maxlength="5000" placeholder="Write a message…" required></textarea></div>
                    <div class="tp-modal-foot"><button class="tp-btn primary" type="submit"><i class="fas fa-paper-plane"></i> Send</button></div>
                </form>
            </div>
            <script>document.addEventListener('DOMContentLoaded', function () { var c = document.getElementById('chat'); if (c) c.scrollTop = c.scrollHeight; });</script>
        <?php else: ?>
            <div class="tp-empty" style="padding:56px 22px"><i class="fas fa-comment-dots"></i><h3>Pick a conversation</h3><p>Choose someone on the left to read and reply.</p></div>
        <?php endif; ?>
    </section>
</div>
<?php require 'includes/footer.php'; ?>
