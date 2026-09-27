<?php
$pageTitle = 'Institutional Community';
require_once 'includes/header.php';

try {
    // Fetch community posts/announcements
    $stmt = $pdo->prepare("SELECT p.*, u.name as author_name, u.avatar as author_avatar, u.role as author_role,
                           (SELECT COUNT(*) FROM community_comments WHERE post_id = p.id) as comment_count,
                           (SELECT COUNT(*) FROM community_likes WHERE post_id = p.id) as like_count
                           FROM community_posts p
                           JOIN users u ON p.author_id = u.id
                           ORDER BY p.created_at DESC LIMIT 20");
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (Exception $e) { $posts = []; }

require_once 'includes/layout-top.php';
?>

<header class="portal-header" style="margin-bottom: 32px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
        <div class="greeting">
            <h1 style="font-size: 1.85rem; font-weight: 950; margin: 0; letter-spacing: -1.5px;">Institutional Network</h1>
            <p style="color: var(--text-dim); margin-top: 4px; font-weight: 600;">Collaborate, share knowledge, and grow with 4,000+ scholars.</p>
        </div>
        <button class="btn-premium" onclick="document.getElementById('postModal').style.display='flex'">
            <i class="fas fa-plus"></i> NEW COLLABORATION
        </button>
    </div>
</header>

<div class="community-layout" style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px;">
    <!-- Main Feed -->
    <div class="feed-stack" style="display: flex; flex-direction: column; gap: 24px;">
        <?php foreach($posts as $p): ?>
        <div class="premium-card" style="padding: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div style="display: flex; gap: 14px; align-items: center;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; overflow: hidden; background: #f1f5f9; border: 1px solid var(--border);">
                        <?php if($p['author_avatar']): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($p['author_avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: 950; color: #94a3b8;"><?= strtoupper(substr($p['author_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 style="margin: 0; font-size: 0.95rem; font-weight: 800;"><?= htmlspecialchars($p['author_name']) ?></h5>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                            <span class="p-badge" style="font-size: 0.55rem; padding: 2px 8px;"><?= strtoupper($p['author_role']) ?></span>
                            <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;"><i class="far fa-clock"></i> <?= date('M j, Y', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <button style="background: none; border: none; color: #cbd5e1; cursor: pointer;"><i class="fas fa-ellipsis-v"></i></button>
            </div>

            <div style="margin-bottom: 24px;">
                <h4 style="margin: 0 0 10px; font-size: 1.15rem; font-weight: 900; line-height: 1.4; color: var(--text-main);"><?= htmlspecialchars($p['title']) ?></h4>
                <p style="font-size: 0.92rem; line-height: 1.7; color: var(--text-dim);"><?= nl2br(htmlspecialchars($p['content'])) ?></p>
            </div>

            <div style="display: flex; gap: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <button style="background: none; border: none; font-size: 0.75rem; font-weight: 800; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; gap: 8px;"><i class="far fa-heart"></i> <?= $p['like_count'] ?> Likes</button>
                <button style="background: none; border: none; font-size: 0.75rem; font-weight: 800; color: var(--text-dim); cursor: pointer; display: flex; align-items: center; gap: 8px;"><i class="far fa-comment"></i> <?= $p['comment_count'] ?> Comments</button>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($posts)): ?>
        <div class="premium-card" style="text-align: center; padding: 60px;">
            <i class="fas fa-users-viewfinder fa-3x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
            <h4 style="font-weight: 900;">Network Initializing</h4>
            <p style="color: #94a3b8; max-width: 300px; margin: 0 auto;">Our community is preparing new academic threads. Start the first collaboration!</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Info -->
    <div class="side-panel-alt">
        <div class="premium-card" style="padding: 24px; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: white; border: none; margin-bottom: 24px;">
            <h5 style="margin: 0 0 12px; font-weight: 900; color: white;">Global Lounge Rules</h5>
            <ul style="padding-left: 18px; font-size: 0.8rem; margin: 0; color: rgba(255,255,255,0.7); line-height: 1.8;">
                <li>Be constructive & respectful</li>
                <li>Share only academic resources</li>
                <li>No self-promotion</li>
            </ul>
        </div>
        <div class="premium-card" style="padding: 24px;">
            <h5 style="margin: 0 0 16px; font-weight: 900;">Trending Scholars</h5>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size:0.75rem;">JD</div>
                    <span style="font-size: 0.85rem; font-weight: 700;">John Doe (Scholar)</span>
                </div>
                <!-- Add more as needed -->
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 1000px) {
    .community-layout { grid-template-columns: 1fr; }
}
</style>

<?php require_once 'includes/layout-bottom.php'; ?>
