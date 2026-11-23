<?php
$pageTitle = 'Community';
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

<div class="sp-page-head">
    <div><h1>Community</h1><p>Posts and discussions from learners and tutors at the academy.</p></div>
    <div class="sp-actions"><a href="messages" class="sp-btn ghost"><i class="fas fa-comment-dots"></i> Messages</a></div>
</div>

<div class="sp-split">
    <!-- Feed -->
    <div>
        <?php if (empty($posts)): ?>
            <div class="sp-empty">
                <div class="sp-ico"><i class="fas fa-users"></i></div>
                <h3>No posts yet</h3>
                <p>Community posts will show up here. In the meantime, message a tutor or check the latest announcements.</p>
                <div class="sp-row" style="justify-content: center;">
                    <a href="messages" class="sp-btn">Open messages</a>
                    <a href="announcements" class="sp-btn ghost">Announcements</a>
                </div>
            </div>
        <?php else: foreach ($posts as $p):
            $role = strtolower((string)$p['author_role']);
        ?>
            <article class="sp-card">
                <div class="sp-row" style="flex-wrap: nowrap; margin-bottom: 14px;">
                    <span class="sp-avatar" style="width: 44px; height: 44px;">
                        <?php $ini = mb_strtoupper(mb_substr((string)$p['author_name'], 0, 1)) ?: 'S'; ?>
                        <?php if (!empty($p['author_avatar'])): ?><img src="../uploads/avatars/<?= htmlspecialchars($p['author_avatar']) ?>" alt="" onerror="this.replaceWith(<?= htmlspecialchars(json_encode($ini)) ?>)"><?php else: ?><?= htmlspecialchars($ini) ?><?php endif; ?>
                    </span>
                    <div style="min-width: 0;">
                        <strong style="display: block; font-size: .92rem;"><?= htmlspecialchars($p['author_name']) ?></strong>
                        <div class="sp-row" style="gap: 8px; margin-top: 2px;">
                            <span class="sp-badge <?= $role === 'tutor' ? 'violet' : ($role === 'admin' ? 'orange' : '') ?>"><?= htmlspecialchars(ucfirst($role)) ?></span>
                            <span class="sp-muted" style="font-size: .75rem;"><i class="far fa-clock"></i> <?= date('j M Y', strtotime($p['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <h3 style="margin: 0 0 6px; font-size: 1.05rem; font-weight: 800; line-height: 1.4;"><?= htmlspecialchars($p['title']) ?></h3>
                <p class="sp-muted" style="margin: 0; font-size: .92rem; line-height: 1.7;"><?= nl2br(htmlspecialchars($p['content'])) ?></p>

                <div class="sp-row sp-muted" style="gap: 18px; border-top: 1px solid var(--border); padding-top: 12px; margin-top: 16px; font-size: .8rem; font-weight: 700;">
                    <span><i class="far fa-heart"></i> <?= (int)$p['like_count'] ?> like<?= (int)$p['like_count'] == 1 ? '' : 's' ?></span>
                    <span><i class="far fa-comment"></i> <?= (int)$p['comment_count'] ?> comment<?= (int)$p['comment_count'] == 1 ? '' : 's' ?></span>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </div>

    <!-- Side -->
    <aside>
        <div class="sp-card dark" style="margin-bottom: 16px;">
            <h4 style="margin: 0 0 10px; font-size: 1rem; font-weight: 800;">Community guidelines</h4>
            <ul style="padding-left: 18px; margin: 0; font-size: .85rem; line-height: 1.8; color: rgba(255,255,255,.78);">
                <li>Be kind and respectful</li>
                <li>Share learning resources only</li>
                <li>No self-promotion or spam</li>
            </ul>
        </div>
        <div class="sp-card" style="padding: 16px 18px 6px;">
            <div class="sp-card-head" style="margin-bottom: 0;"><h4>Other ways to connect</h4></div>
            <div class="sp-list">
                <a href="messages" class="sp-list-item" style="text-decoration: none;"><span class="sp-ico" style="width: 34px; height: 34px;"><i class="fas fa-comment-dots"></i></span><div class="sp-grow"><h5>Messages</h5><p>Ask your tutor a question</p></div></a>
                <a href="announcements" class="sp-list-item" style="text-decoration: none;"><span class="sp-ico orange" style="width: 34px; height: 34px;"><i class="fas fa-bullhorn"></i></span><div class="sp-grow"><h5>Announcements</h5><p>News from the academy</p></div></a>
                <a href="leaderboard" class="sp-list-item" style="text-decoration: none;"><span class="sp-ico yellow" style="width: 34px; height: 34px;"><i class="fas fa-ranking-star"></i></span><div class="sp-grow"><h5>Leaderboard</h5><p>See who's learning the most</p></div></a>
                <a href="referral" class="sp-list-item" style="text-decoration: none;"><span class="sp-ico green" style="width: 34px; height: 34px;"><i class="fas fa-gift"></i></span><div class="sp-grow"><h5>Refer a friend</h5><p>Learn together and earn coins</p></div></a>
            </div>
        </div>
    </aside>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
