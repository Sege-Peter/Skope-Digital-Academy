<?php
$pageTitle = 'Messages';
require_once 'includes/header.php';

$sid = (int)$student['id'];
$to_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;
$receiver = null;
$thread = [];
$send_error = '';

// Tutors of the student's courses (people they can start a conversation with)
try {
    $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.name, u.avatar, u.role, GROUP_CONCAT(DISTINCT c.title ORDER BY c.title SEPARATOR ', ') as courses
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           JOIN users u ON c.tutor_id = u.id
                           WHERE e.student_id = ? AND e.status IN ('active', 'completed')
                           GROUP BY u.id, u.name, u.avatar, u.role
                           ORDER BY u.name");
    $stmt->execute([$sid]);
    $tutors = $stmt->fetchAll();
} catch (Exception $e) { $tutors = []; }

// Recent conversations
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.avatar, u.role,
                           (SELECT content FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC, id DESC LIMIT 1) as last_msg,
                           (SELECT created_at FROM messages WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) ORDER BY created_at DESC, id DESC LIMIT 1) as last_time,
                           (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread
                           FROM users u
                           WHERE u.id IN (SELECT sender_id FROM messages WHERE receiver_id = ? UNION SELECT receiver_id FROM messages WHERE sender_id = ?)
                           ORDER BY last_time DESC");
    $stmt->execute(array_fill(0, 7, $sid));
    $recent = $stmt->fetchAll();
} catch (Exception $e) { $recent = []; }

// Who the student may message: their tutors, or anyone already in a conversation with them
$allowed_ids = array_map('intval', array_merge(array_column($tutors, 'id'), array_column($recent, 'id')));

if ($to_id && $to_id !== $sid && in_array($to_id, $allowed_ids, true)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, avatar, role FROM users WHERE id = ?");
        $stmt->execute([$to_id]);
        $receiver = $stmt->fetch() ?: null;
    } catch (Exception $e) {}
}

// Send a message (the form posts back to this page)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_to = (int)($_POST['to_id'] ?? 0);
    $text = trim((string)($_POST['message'] ?? ''));
    if ($post_to && $post_to !== $sid && in_array($post_to, $allowed_ids, true) && $text !== '') {
        try {
            $ins = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_read) VALUES (?, ?, ?, 0)");
            $ins->execute([$sid, $post_to, mb_substr($text, 0, 5000)]);
            header('Location: messages?to=' . $post_to);
            exit;
        } catch (Exception $e) {
            error_log('student message send: ' . $e->getMessage());
            $send_error = 'Your message could not be sent. Please try again.';
        }
    } elseif ($text === '') {
        $send_error = 'Type a message before sending.';
    } else {
        $send_error = 'You can only message your tutors and people you already talk to.';
    }
}

// Load the open conversation and mark incoming messages as read
if ($receiver) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM (
                                   SELECT id, sender_id, content, created_at FROM messages
                                   WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                                   ORDER BY created_at DESC, id DESC LIMIT 200
                               ) t ORDER BY created_at ASC, id ASC");
        $stmt->execute([$sid, $receiver['id'], $receiver['id'], $sid]);
        $thread = $stmt->fetchAll();

        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
            ->execute([$receiver['id'], $sid]);
        foreach ($recent as &$r) { if ((int)$r['id'] === (int)$receiver['id']) $r['unread'] = 0; }
        unset($r);
    } catch (Exception $e) { error_log('student message thread: ' . $e->getMessage()); }
}

// Tutors with no conversation yet
$recent_ids = array_map('intval', array_column($recent, 'id'));
$new_contacts = array_values(array_filter($tutors, fn($t) => !in_array((int)$t['id'], $recent_ids, true)));

function msg_avatar($person, $size = 44) {
    $name = trim((string)($person['name'] ?? '?'));
    $initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', $name), 0, 2))));
    $img = !empty($person['avatar']) ? '<img src="../uploads/avatars/' . htmlspecialchars($person['avatar']) . '" alt="" onerror="this.remove()">' : '';
    return '<span class="sp-avatar" style="width: ' . $size . 'px; height: ' . $size . 'px;">' . $img . htmlspecialchars($initials ?: '?') . '</span>';
}
function msg_when($ts) {
    if (!$ts) return '';
    $t = strtotime($ts);
    if (date('Y-m-d', $t) === date('Y-m-d')) return date('H:i', $t);
    if (date('Y', $t) === date('Y')) return date('j M', $t);
    return date('j M Y', $t);
}

require_once 'includes/layout-top.php';
?>

<style>
.pg-msg { display: grid; grid-template-columns: 320px minmax(0, 1fr); height: min(760px, calc(100dvh - var(--topbar-h) - 150px)); min-height: 460px; padding: 0; overflow: hidden; }
.pg-msg .ml { border-right: 1px solid var(--border); display: flex; flex-direction: column; min-height: 0; }
.pg-msg .ml-scroll { flex: 1; overflow-y: auto; }
.pg-msg .ml-label { padding: 14px 16px 6px; font-size: .74rem; font-weight: 700; color: var(--text-dim); }
.pg-msg .ml-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-main); border-left: 3px solid transparent; }
.pg-msg .ml-item:hover { background: var(--surface-2); }
.pg-msg .ml-item.active { background: var(--primary-soft); border-left-color: var(--primary); }
.pg-msg .ml-item .sp-grow { flex: 1; min-width: 0; }
.pg-msg .ml-item strong { display: block; font-size: .88rem; }
.pg-msg .ml-item p { margin: 2px 0 0; font-size: .78rem; color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pg-msg .ml-item.unread p { color: var(--text-main); font-weight: 600; }
.pg-msg .ml-time { font-size: .7rem; color: var(--text-muted); white-space: nowrap; }
.pg-msg .ml-count { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; background: var(--secondary); color: #fff; font-size: .68rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
.pg-msg .mt { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
.pg-msg .mt-head { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.pg-msg .mt-back { display: none; }
.pg-msg .mt-body { flex: 1; overflow-y: auto; padding: 20px 18px; background: var(--surface-2); display: flex; flex-direction: column; gap: 8px; overscroll-behavior: contain; }
.pg-msg .bubble { max-width: min(78%, 560px); padding: 10px 14px; border-radius: 16px; font-size: .9rem; line-height: 1.55; overflow-wrap: anywhere; background: var(--surface); border: 1px solid var(--border); border-bottom-left-radius: 4px; align-self: flex-start; }
.pg-msg .bubble.me { align-self: flex-end; background: var(--primary); color: #fff; border-color: var(--primary); border-bottom-left-radius: 16px; border-bottom-right-radius: 4px; }
.pg-msg .bubble time { display: block; margin-top: 4px; font-size: .68rem; opacity: .7; text-align: right; }
.pg-msg .day { align-self: center; margin: 8px 0; padding: 3px 10px; border-radius: 999px; background: #EBF0F6; color: var(--text-dim); font-size: .7rem; font-weight: 700; }
.pg-msg .mt-form { display: flex; align-items: flex-end; gap: 8px; padding: 12px 14px; border-top: 1px solid var(--border); background: var(--surface); }
.pg-msg .mt-form textarea { flex: 1; min-width: 0; min-height: 44px; max-height: 140px; height: 44px; padding: 11px 14px; border-radius: 12px; border: 1px solid var(--border-strong); background: var(--surface); font: inherit; font-size: .9rem; resize: none; outline: none; color: var(--text-main); }
.pg-msg .mt-form textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.pg-msg .mt-form .sp-btn { width: 44px; height: 44px; padding: 0; flex-shrink: 0; }
.pg-msg .mt-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px; }
.pg-msg .mt-empty .sp-empty { border: 0; background: none; }
@media (max-width: 860px) {
    .pg-msg { grid-template-columns: minmax(0, 1fr); height: calc(100dvh - var(--topbar-h) - 130px); min-height: 420px; }
    .pg-msg .ml { border-right: 0; }
    .pg-msg.has-thread .ml { display: none; }
    .pg-msg:not(.has-thread) .mt { display: none; }
    .pg-msg .mt-back { display: inline-flex; }
    .pg-msg .bubble { max-width: 86%; }
}
@media (max-width: 640px) { .pg-msg-head p { display: none; } .pg-msg-head { margin-bottom: 14px; } }
</style>

<div class="sp-page-head pg-msg-head">
    <div><h1>Messages</h1><p>Chat with your tutors about your courses.</p></div>
</div>

<?php if ($send_error): ?>
    <div class="sp-alert error"><i class="fas fa-circle-exclamation" style="margin-top: 3px;"></i><span><?= htmlspecialchars($send_error) ?></span></div>
<?php elseif ($to_id && !$receiver): ?>
    <div class="sp-alert warning"><i class="fas fa-triangle-exclamation" style="margin-top: 3px;"></i><span>That conversation isn't available. You can message the tutors of courses you've joined.</span></div>
<?php endif; ?>

<div class="sp-card pg-msg <?= $receiver ? 'has-thread' : '' ?>">
    <!-- Conversation list -->
    <div class="ml">
        <div class="ml-scroll">
            <?php if (!empty($recent)): ?>
                <div class="ml-label">Conversations</div>
                <?php foreach ($recent as $r): $unread = (int)($r['unread'] ?? 0); ?>
                    <a href="?to=<?= (int)$r['id'] ?>" class="ml-item <?= $to_id == $r['id'] ? 'active' : '' ?> <?= $unread ? 'unread' : '' ?>">
                        <?= msg_avatar($r) ?>
                        <div class="sp-grow">
                            <div class="sp-between"><strong class="sp-truncate"><?= htmlspecialchars($r['name']) ?></strong><span class="ml-time"><?= htmlspecialchars(msg_when($r['last_time'])) ?></span></div>
                            <div class="sp-between"><p class="sp-truncate"><?= htmlspecialchars($r['last_msg'] ?: 'No messages yet') ?></p><?php if ($unread): ?><span class="ml-count"><?= $unread ?></span><?php endif; ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($new_contacts)): ?>
                <div class="ml-label">Your tutors</div>
                <?php foreach ($new_contacts as $t): ?>
                    <a href="?to=<?= (int)$t['id'] ?>" class="ml-item <?= $to_id == $t['id'] ? 'active' : '' ?>">
                        <?= msg_avatar($t) ?>
                        <div class="sp-grow">
                            <strong class="sp-truncate"><?= htmlspecialchars($t['name']) ?></strong>
                            <p class="sp-truncate"><?= htmlspecialchars($t['courses'] ?: 'Tutor') ?></p>
                        </div>
                        <i class="fas fa-pen-to-square sp-muted"></i>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (empty($recent) && empty($new_contacts)): ?>
                <div class="sp-empty" style="border: 0; padding: 36px 20px;">
                    <div class="sp-ico"><i class="far fa-comments"></i></div>
                    <h3>No conversations yet</h3>
                    <p>Join a course and you'll be able to message its tutor here.</p>
                    <a href="../courses" class="sp-btn sm">Browse courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Open conversation -->
    <div class="mt">
        <?php if ($receiver): ?>
            <div class="mt-head">
                <a href="messages" class="sp-icon-btn mt-back" aria-label="Back to conversations"><i class="fas fa-arrow-left"></i></a>
                <?= msg_avatar($receiver, 40) ?>
                <div style="min-width: 0;">
                    <strong class="sp-truncate" style="display: block;"><?= htmlspecialchars($receiver['name']) ?></strong>
                    <span class="sp-muted sp-small"><?= htmlspecialchars(ucfirst((string)$receiver['role'])) ?></span>
                </div>
            </div>

            <div class="mt-body" id="threadBody">
                <?php if (empty($thread)): ?>
                    <div class="sp-muted sp-small" style="margin: auto; text-align: center; max-width: 320px;">
                        <i class="far fa-comment-dots" style="font-size: 1.6rem; display: block; margin-bottom: 8px;"></i>
                        No messages yet. Say hello and ask your question below.
                    </div>
                <?php else: $last_day = ''; foreach ($thread as $m):
                    $t = strtotime($m['created_at']);
                    $day = date('Y-m-d', $t);
                    if ($day !== $last_day): $last_day = $day; ?>
                        <span class="day"><?= $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('j M Y', $t)) ?></span>
                    <?php endif; ?>
                    <div class="bubble <?= (int)$m['sender_id'] === $sid ? 'me' : '' ?>">
                        <?= nl2br(htmlspecialchars($m['content'])) ?>
                        <time><?= date('H:i', $t) ?></time>
                    </div>
                <?php endforeach; endif; ?>
                <span id="end"></span>
            </div>

            <form action="messages?to=<?= (int)$receiver['id'] ?>" method="POST" class="mt-form">
                <input type="hidden" name="to_id" value="<?= (int)$receiver['id'] ?>">
                <textarea name="message" id="msgInput" rows="1" required maxlength="5000" placeholder="Write a message…" aria-label="Message"></textarea>
                <button type="submit" class="sp-btn" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
            </form>
        <?php else: ?>
            <div class="mt-empty">
                <div class="sp-empty">
                    <div class="sp-ico"><i class="fas fa-inbox"></i></div>
                    <h3>Pick a conversation</h3>
                    <p>Choose someone on the left to read and reply to messages.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const body = document.getElementById('threadBody');
    if (body) body.scrollTop = body.scrollHeight;
    const input = document.getElementById('msgInput');
    if (!input) return;
    input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; });
    // Enter sends, Shift+Enter adds a new line
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (input.value.trim()) input.form.submit(); }
    });
})();
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
