<?php
$pageTitle = 'Contact Inbox';
require_once '../includes/header.php';
requireRole('admin');

// ── Actions ──────────────────────────────────────────────────────────────────
$msg = '';

// Mark as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = NOW() WHERE id = ? AND status = 'unread'")->execute([$_GET['read']]);
    header("Location: messages.php?view=" . $_GET['read']);
    exit;
}

// Mark as replied
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_replied'])) {
    $id = intval($_POST['msg_id']);
    $note = trim($_POST['admin_notes'] ?? '');
    $pdo->prepare("UPDATE contact_messages SET status = 'replied', admin_notes = ? WHERE id = ?")->execute([$note, $id]);
    $msg = "Message marked as replied.";
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg'])) {
    $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([intval($_POST['msg_id'])]);
    header("Location: messages.php?deleted=1");
    exit;
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$view_id = isset($_GET['view']) ? intval($_GET['view']) : null;

$where = $filter !== 'all' ? "WHERE status = " . $pdo->quote($filter) : '';
$messages = $pdo->query("SELECT * FROM contact_messages $where ORDER BY created_at DESC")->fetchAll();

$unread_count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'")->fetchColumn();

// Fetch message being viewed
$viewed = null;
if ($view_id) {
    $viewed = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $viewed->execute([$view_id]);
    $viewed = $viewed->fetch();
    // Auto mark as read
    if ($viewed && $viewed['status'] === 'unread') {
        $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = NOW() WHERE id = ?")->execute([$view_id]);
        $viewed['status'] = 'read';
    }
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .inbox-shell {
        display: grid;
        grid-template-columns: 360px 1fr;
        height: calc(100vh - 0px);
        overflow: hidden;
    }

    /* ── LEFT: Message List ── */
    .inbox-list {
        border-right: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        background: #fff;
        overflow: hidden;
    }
    .inbox-list-header {
        padding: 28px 24px 20px;
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
        position: sticky;
        top: 0;
    }
    .inbox-list-header h1 {
        font-family: 'Poppins', sans-serif;
        font-size: 1.3rem;
        font-weight: 900;
        color: #0f172a;
        margin: 0 0 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .inbox-badge {
        background: #ef4444;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 8px;
        margin-left: 4px;
    }
    .filter-tabs {
        display: flex;
        gap: 6px;
    }
    .filter-tab {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        text-decoration: none;
        border: 1.5px solid #e2e8f0;
        color: #64748b;
        transition: 0.2s;
    }
    .filter-tab.active { background: #0284c7; border-color: #0284c7; color: #fff; }
    .filter-tab:hover:not(.active) { border-color: #0284c7; color: #0284c7; }

    .inbox-messages {
        overflow-y: auto;
        flex: 1;
        scrollbar-width: thin;
        scrollbar-color: #e2e8f0 transparent;
    }
    .msg-row {
        display: flex;
        gap: 14px;
        padding: 18px 24px;
        border-bottom: 1px solid #f8fafc;
        text-decoration: none;
        transition: 0.15s;
        cursor: pointer;
        position: relative;
    }
    .msg-row:hover { background: #f8fafc; }
    .msg-row.active { background: #eff6ff; border-left: 3px solid #0284c7; }
    .msg-row.unread .msg-row-subject { font-weight: 800; color: #0f172a; }
    .msg-row.unread::after {
        content: '';
        position: absolute;
        right: 20px; top: 50%; transform: translateY(-50%);
        width: 8px; height: 8px;
        background: #ef4444;
        border-radius: 50%;
    }
    .msg-avatar {
        width: 42px; height: 42px;
        border-radius: 12px;
        background: #e0f2fe;
        color: #0284c7;
        display: flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: 1rem;
        flex-shrink: 0;
    }
    .msg-row-content { flex: 1; min-width: 0; }
    .msg-row-name { font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-row-subject { font-size: 0.8rem; color: #475569; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-row-time { font-size: 0.68rem; color: #94a3b8; font-weight: 600; }

    .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; }
    .dot-unread { background: #ef4444; }
    .dot-read { background: #94a3b8; }
    .dot-replied { background: #10b981; }

    /* ── RIGHT: View Panel ── */
    .inbox-view {
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        background: #f8fafc;
    }
    .view-header {
        padding: 28px 40px 20px;
        background: #fff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .view-subject {
        font-family: 'Poppins', sans-serif;
        font-size: 1.4rem;
        font-weight: 900;
        color: #0f172a;
        letter-spacing: -0.5px;
        margin: 0 0 8px;
    }
    .sender-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .sender-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        background: #f1f5f9;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        color: #475569;
    }
    .sender-chip i { color: #0284c7; }

    .view-body { padding: 32px 40px; flex: 1; }
    .msg-bubble {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 32px;
        margin-bottom: 28px;
        font-size: 0.95rem;
        color: #334155;
        line-height: 1.8;
        white-space: pre-wrap;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }

    .reply-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .reply-panel h4 {
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .reply-textarea {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        font-size: 0.88rem;
        color: #334155;
        resize: none;
        min-height: 80px;
        outline: none;
        font-family: 'Inter', sans-serif;
        background: #f8fafc;
        box-sizing: border-box;
        transition: 0.2s;
        margin-bottom: 14px;
        display: block;
    }
    .reply-textarea:focus { border-color: #0284c7; background: #fff; box-shadow: 0 0 0 4px rgba(2,132,199,0.08); }

    .empty-inbox {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
        text-align: center;
        padding: 60px;
    }
    .empty-inbox i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
    .empty-inbox h3 { font-family: 'Poppins', sans-serif; font-size: 1.3rem; font-weight: 800; color: #475569; margin-bottom: 8px; }

    /* Status pill */
    .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 20px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .st-unread { background: #fee2e2; color: #991b1b; }
    .st-read { background: #f1f5f9; color: #64748b; }
    .st-replied { background: #d1fae5; color: #065f46; }

    /* Mobile */
    @media (max-width: 900px) {
        .inbox-shell { grid-template-columns: 1fr; }
        .inbox-list { height: 50vh; }
        .inbox-view { min-height: 50vh; }
        .view-header { padding: 20px; }
        .view-body { padding: 20px; }
    }
</style>

<main class="main-content" style="padding: 0; height: 100vh; overflow: hidden;">
    <?php if ($msg): ?>
    <div style="position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 14px 20px; background: #d1fae5; color: #065f46; border-radius: 14px; font-weight: 700; box-shadow: 0 8px 20px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="inbox-shell">
        <!-- ── LEFT: Message List ── -->
        <div class="inbox-list">
            <div class="inbox-list-header">
                <h1>
                    <i class="fas fa-inbox" style="color: #0284c7;"></i>
                    Contact Inbox
                    <?php if ($unread_count > 0): ?>
                        <span class="inbox-badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </h1>
                <div class="filter-tabs">
                    <a href="messages.php" class="filter-tab <?= $filter == 'all' ? 'active' : '' ?>">All</a>
                    <a href="messages.php?filter=unread" class="filter-tab <?= $filter == 'unread' ? 'active' : '' ?>">Unread</a>
                    <a href="messages.php?filter=read" class="filter-tab <?= $filter == 'read' ? 'active' : '' ?>">Read</a>
                    <a href="messages.php?filter=replied" class="filter-tab <?= $filter == 'replied' ? 'active' : '' ?>">Replied</a>
                </div>
            </div>

            <div class="inbox-messages">
                <?php if (empty($messages)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                        <i class="fas fa-envelope-open" style="font-size: 2.5rem; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                        <p style="font-weight: 600; font-size: 0.9rem;">No messages yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                    <a href="messages.php?view=<?= $m['id'] ?>&filter=<?= $filter ?>&read=<?= $m['id'] ?>"
                       class="msg-row <?= $m['status'] ?> <?= ($view_id == $m['id']) ? 'active' : '' ?>"
                       style="display: flex;">
                        <div class="msg-avatar"><?= strtoupper(substr($m['name'], 0, 1)) ?></div>
                        <div class="msg-row-content">
                            <div class="msg-row-name"><?= htmlspecialchars($m['name']) ?></div>
                            <div class="msg-row-subject"><?= htmlspecialchars($m['subject']) ?></div>
                            <div class="msg-row-time">
                                <span class="status-dot dot-<?= $m['status'] ?>"></span>
                                <?= date('M j, g:i a', strtotime($m['created_at'])) ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── RIGHT: View Panel ── -->
        <div class="inbox-view">
            <?php if ($viewed): ?>
                <div class="view-header">
                    <div>
                        <h2 class="view-subject"><?= htmlspecialchars($viewed['subject']) ?></h2>
                        <div class="sender-meta">
                            <span class="sender-chip"><i class="fas fa-user"></i> <?= htmlspecialchars($viewed['name']) ?></span>
                            <a href="mailto:<?= htmlspecialchars($viewed['email']) ?>" class="sender-chip" style="text-decoration: none; color: #0284c7;">
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($viewed['email']) ?>
                            </a>
                            <?php if ($viewed['phone']): ?>
                            <a href="tel:<?= htmlspecialchars($viewed['phone']) ?>" class="sender-chip" style="text-decoration: none;">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($viewed['phone']) ?>
                            </a>
                            <?php endif; ?>
                            <span class="status-pill st-<?= $viewed['status'] ?>"><?= $viewed['status'] ?></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-shrink: 0;">
                        <a href="mailto:<?= urlencode($viewed['email']) ?>?subject=Re: <?= urlencode($viewed['subject']) ?>"
                           class="btn btn-primary btn-sm" style="border-radius: 12px; padding: 10px 20px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; height: 42px;">
                            <i class="fas fa-paper-plane"></i> Reply via Email
                        </a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?')">
                            <input type="hidden" name="msg_id" value="<?= $viewed['id'] ?>">
                            <button type="submit" name="delete_msg" class="btn btn-ghost btn-sm" style="border-radius: 12px; padding: 10px 16px; border-color: #fee2e2; color: #ef4444; height: 42px; font-weight: 700;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="view-body">
                    <!-- Timestamp -->
                    <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clock"></i>
                        Received: <?= date('l, F j, Y \a\t g:i A', strtotime($viewed['created_at'])) ?>
                        <?php if ($viewed['read_at']): ?>
                        &nbsp;·&nbsp; <i class="fas fa-check-double" style="color: #0284c7;"></i> Read: <?= date('M j g:i A', strtotime($viewed['read_at'])) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Message body -->
                    <div class="msg-bubble"><?= nl2br(htmlspecialchars($viewed['message'])) ?></div>

                    <!-- Admin reply panel -->
                    <div class="reply-panel">
                        <h4><i class="fas fa-sticky-note" style="color: #f59e0b;"></i> Admin Notes & Actions</h4>
                        <form method="POST">
                            <input type="hidden" name="msg_id" value="<?= $viewed['id'] ?>">
                            <textarea name="admin_notes" class="reply-textarea" placeholder="Add internal notes about this inquiry (not sent to visitor)..."><?= htmlspecialchars($viewed['admin_notes'] ?? '') ?></textarea>
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <button type="submit" name="mark_replied" class="btn btn-primary btn-sm" style="border-radius: 12px; padding: 10px 24px; font-weight: 800; height: 42px;">
                                    <i class="fas fa-check"></i> Save Notes & Mark Replied
                                </button>
                                <a href="mailto:<?= urlencode($viewed['email']) ?>?subject=Re: <?= urlencode($viewed['subject']) ?>"
                                   class="btn btn-ghost btn-sm" style="border-radius: 12px; padding: 10px 24px; color: #0284c7; background: #e0f2fe; border: none; font-weight: 700; height: 42px; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-external-link-alt"></i> Open in Mail Client
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <div class="empty-inbox">
                    <i class="fas fa-inbox"></i>
                    <h3>Select a message to read</h3>
                    <p style="font-size: 0.9rem; max-width: 300px; margin: 0;">
                        <?php if (empty($messages)): ?>
                            No contact form submissions yet. They'll appear here when visitors use the website's contact form.
                        <?php else: ?>
                            Click a message from the list to view its full content and reply.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
