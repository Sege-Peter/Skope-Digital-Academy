<?php
$pageTitle = 'News & Announcements';
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
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .news-forum-layout {
        max-width: 900px;
        margin: 0 auto;
        padding-bottom: 60px;
    }

    .forum-header {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 24px;
        box-shadow: var(--shadow-sm);
    }

    .forum-icon {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: var(--primary-glow);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        flex-shrink: 0;
    }

    .announcement-card {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 20px;
        padding: 32px;
        margin-bottom: 24px;
        transition: 0.3s;
        position: relative;
        overflow: hidden;
    }
    .announcement-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-4px); border-color: var(--primary-glow); }

    .pinned-label {
        position: absolute;
        top: 24px;
        right: 24px;
        background: #FEF3C7;
        color: #D97706;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .announcement-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 8px;
        padding-right: 100px; /* space for pinned label */
    }

    .announcement-meta {
        font-size: 0.8rem;
        color: var(--text-dim);
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--bg-light);
    }
    
    .announcement-body {
        font-size: 1rem;
        line-height: 1.7;
        color: #475569;
    }

    @media (max-width: 768px) {
        .forum-header { flex-direction: column; text-align: center; padding: 32px 20px; }
        .announcement-card { padding: 24px 20px; }
        .announcement-title { padding-right: 0; font-size: 1.25rem; }
        .pinned-label { position: static; display: inline-flex; margin-bottom: 12px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Institution <span class="text-primary">Notices</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Official system-wide announcements and updates.</p>
            </div>
        </div>
    </header>

    <div class="news-forum-layout">
        <div class="forum-header">
            <div class="forum-icon"><i class="fas fa-bullhorn"></i></div>
            <div>
                <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--dark); margin-bottom: 4px;">General News Forum</h2>
                <p style="color: var(--text-dim); font-size: 0.95rem; line-height: 1.5;">This forum is used by the Skope Digital Academy administration to broadcast important dates, platform updates, and general information to all students.</p>
            </div>
        </div>

        <?php if(empty($announcements)): ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border: 1px dashed var(--dark-border); border-radius: 24px;">
                <i class="far fa-newspaper" style="font-size: 3rem; color: var(--text-dim); margin-bottom: 16px; opacity: 0.5;"></i>
                <h3 style="font-size: 1.2rem; color: var(--dark); font-weight: 700;">No Recent Announcements</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Check back later for official institutional updates.</p>
            </div>
        <?php else: ?>
            <?php foreach($announcements as $a): ?>
                <div class="announcement-card">
                    <?php if($a['is_pinned']): ?>
                        <div class="pinned-label"><i class="fas fa-thumbtack"></i> Pinned</div>
                    <?php endif; ?>
                    
                    <h3 class="announcement-title"><?= htmlspecialchars($a['title']) ?></h3>
                    <div class="announcement-meta">
                        <span><i class="far fa-calendar-alt"></i> <?= date('F j, Y, g:i a', strtotime($a['created_at'])) ?></span>
                        <span><i class="fas fa-user-shield"></i> System Admin</span>
                    </div>
                    
                    <div class="announcement-body">
                        <?= nl2br(htmlspecialchars($a['content'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
