<?php
$pageTitle = 'Announcements';
require_once 'includes/header.php';

try {
    $stmt = $pdo->prepare("
        SELECT * FROM announcements
        WHERE start_date <= CURDATE()
        AND (end_date IS NULL OR end_date >= CURDATE())
        ORDER BY is_pinned DESC, created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $announcements = [];
}

require_once 'includes/layout-top.php';
?>

<style>
.pg-news { max-width: 860px; }
.pg-news .an-card { padding: 22px; }
.pg-news .an-card.pinned { border-color: #F7D9A0; }
.pg-news .an-card h3 { margin: 0; font-size: 1.1rem; font-weight: 800; line-height: 1.35; }
.pg-news .an-meta { display: flex; gap: 14px; flex-wrap: wrap; font-size: .78rem; color: var(--text-dim); margin: 6px 0 14px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
.pg-news .an-body { color: #334155; font-size: .93rem; line-height: 1.7; overflow-wrap: anywhere; }
</style>

<div class="pg-news">
    <div class="sp-page-head">
        <div><h1>Announcements</h1><p>News, important dates and platform updates from the academy.</p></div>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="sp-empty">
            <div class="sp-ico"><i class="far fa-newspaper"></i></div>
            <h3>No announcements right now</h3>
            <p>When the academy shares news or important dates, you'll find them here.</p>
            <a href="./" class="sp-btn ghost">Back to dashboard</a>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <article class="sp-card an-card<?= !empty($a['is_pinned']) ? ' pinned' : '' ?>">
                <div class="sp-between" style="align-items: flex-start;">
                    <h3><?= htmlspecialchars($a['title']) ?></h3>
                    <?php if (!empty($a['is_pinned'])): ?>
                        <span class="sp-badge yellow" style="flex-shrink: 0;"><i class="fas fa-thumbtack"></i> Pinned</span>
                    <?php endif; ?>
                </div>
                <div class="an-meta">
                    <span><i class="far fa-calendar"></i> <?= date('j F Y, g:i a', strtotime($a['created_at'])) ?></span>
                    <span><i class="fas fa-user-shield"></i> Academy team</span>
                </div>
                <div class="an-body"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
