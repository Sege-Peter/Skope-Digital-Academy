<?php
$pageTitle = 'My Merit Badges';
require_once 'includes/header.php';

try {
    // 1. Calculate student progress for various criteria
    $progress_data = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND progress_percent >= 100");
    $stmt->execute([$student['id']]);
    $progress_data['courses_completed'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = ? AND status = 'completed'");
    $stmt->execute([$student['id']]);
    $progress_data['lessons_completed'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ? AND passed = 1");
    $stmt->execute([$student['id']]);
    $progress_data['quizzes_passed'] = (int)$stmt->fetchColumn();

    $progress_data['points_earned'] = (int)($student['points'] ?? 0);

    // 2. Fetch available badges to check for unlocks
    $stmt = $pdo->prepare("SELECT * FROM badges 
                           WHERE id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?)
                           ORDER BY criteria_value ASC");
    $stmt->execute([$student['id']]);
    $available_badges = $stmt->fetchAll();

    // 3. Auto-Unlock newly achieved badges
    $newly_awarded = 0;
    foreach ($available_badges as $b) {
        $current_val = $progress_data[$b['criteria_type']] ?? 0;
        if ($current_val >= $b['criteria_value']) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id) VALUES (?, ?)");
            $stmt->execute([$student['id'], $b['id']]);
            $newly_awarded++;
        }
    }

    if ($newly_awarded > 0) {
        header("Location: badges.php?awarded=" . $newly_awarded);
        exit;
    }

    // 4. Fetch earned badges (after possible unlocks)
    $stmt = $pdo->prepare("SELECT b.*, sb.awarded_at 
                           FROM student_badges sb
                           JOIN badges b ON sb.badge_id = b.id
                           WHERE sb.student_id = ?
                           ORDER BY sb.awarded_at DESC");
    $stmt->execute([$student['id']]);
    $earned_badges = $stmt->fetchAll();

    // 5. Re-fetch locked badges
    $stmt = $pdo->prepare("SELECT * FROM badges 
                           WHERE id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?)
                           ORDER BY criteria_value ASC");
    $stmt->execute([$student['id']]);
    $available_badges = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $earned_badges = $available_badges = [];
    $progress_data = [];
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .badges-hero {
        background: linear-gradient(135deg, var(--studio-dark, #0f172a) 0%, #1e293b 100%);
        border-radius: 32px;
        padding: 60px;
        color: white;
        margin-bottom: 48px;
        text-align: center;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }
    .badges-hero::before {
        content: '\f559';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        font-size: 18rem;
        opacity: 0.04;
        top: -40px;
        right: -40px;
        transform: rotate(15deg);
        pointer-events: none;
    }
    .badges-hero::after {
        content: '';
        position: absolute;
        bottom: -50px;
        left: -50px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(0,191,255,0.15), transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .badge-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 32px;
        margin-bottom: 60px;
    }

    .badge-card {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 32px;
        padding: 40px 24px;
        text-align: center;
        transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        display: flex;
        flex-direction: column;
    }
    .badge-card:hover { transform: translateY(-10px); box-shadow: 0 24px 48px rgba(0,0,0,0.08); border-color: var(--primary); }

    .badge-icon-wrap {
        width: 104px;
        height: 104px;
        border-radius: 35px;
        margin: 0 auto 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        position: relative;
        transition: 0.5s;
        box-shadow: inset 0 0 20px rgba(255,255,255,0.5);
    }
    .badge-card:hover .badge-icon-wrap { transform: rotate(8deg) scale(1.1); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }

    .badge-locked { filter: grayscale(0.8); background: #fafafa; border: 1px dashed var(--dark-border); opacity: 0.85; }
    .badge-locked:hover { filter: grayscale(0); opacity: 1; border-style: solid; }
    
    .locked-overlay {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        color: #94a3b8;
        box-shadow: var(--shadow-sm);
        z-index: 2;
        border: 1px solid var(--dark-border);
    }

    .badge-name { font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; color: var(--dark); line-height: 1.3; }
    .badge-desc { font-size: 0.88rem; color: var(--text-dim); line-height: 1.5; margin-bottom: auto; }
    
    .criteria-box {
        background: var(--bg-light);
        padding: 10px 16px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-muted);
        display: inline-block;
        margin-top: 20px;
    }

    .progress-container {
        margin-top: 24px;
        text-align: left;
        background: white;
        padding: 16px;
        border-radius: 16px;
        border: 1px solid var(--dark-border);
    }
    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-dim);
        margin-bottom: 8px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        border-radius: 4px;
        transition: 1s ease-in-out;
    }

    .section-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.6rem;
        font-weight: 800;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 16px;
        color: var(--dark);
    }
    .section-title::after { content: ''; flex: 1; height: 2px; background: linear-gradient(90deg, var(--dark-border), transparent); }
    .section-title i { color: var(--primary); }

    @media (max-width: 768px) { .badges-hero { padding: 40px 20px; } }
</style>

<main class="main-content" style="padding-bottom: 100px;">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Recognition <span class="text-secondary">Vault</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Exclusive merits earned through academic excellence and platform engagement.</p>
            </div>
        </div>
    </header>

    <div class="badges-hero">
        <h2 style="font-family: 'Poppins', sans-serif; font-size: 2.22rem; margin-bottom: 16px; font-weight: 900; color: #fff;">The Hallmark of <span style="color:var(--primary);">Excellence</span></h2>
        <p style="opacity: 0.85; font-size: 1.05rem; max-width: 600px; margin: 0 auto; line-height: 1.6; color: #fff;">Build your professional legacy. Earn premium merit badges by reaching significant milestones natively across the academy.</p>
        
        <div style="display: flex; justify-content: center; gap: 60px; margin-top: 48px; position: relative; z-index: 2;">
            <div style="text-align: center;">
                <div style="font-size: 3rem; font-weight: 900; line-height: 1; text-shadow: 0 4px 20px rgba(0,0,0,0.3);"><?= count($earned_badges) ?></div>
                <div style="font-size: 0.8rem; font-weight: 800; opacity: 0.7; text-transform: uppercase; letter-spacing: 2px; margin-top: 8px;">Badges Earned</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 3rem; font-weight: 900; line-height: 1; opacity: 0.5;"><?= count($available_badges) ?></div>
                <div style="font-size: 0.8rem; font-weight: 800; opacity: 0.4; text-transform: uppercase; letter-spacing: 2px; margin-top: 8px;">Unlocked Next</div>
            </div>
        </div>
    </div>

    <!-- Earned Badges -->
    <?php if(!empty($earned_badges)): ?>
    <h3 class="section-title"><i class="fas fa-trophy"></i> Earned Accomplishments</h3>
    <div class="badge-grid">
        <?php foreach($earned_badges as $b): ?>
        <div class="badge-card">
            <div style="position:absolute; top: -15px; left: 50%; transform: translateX(-50%); background: linear-gradient(90deg, #F59E0B, #FCD34D); color: #78350F; padding: 4px 16px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);">Achieved</div>
            
            <div class="badge-icon-wrap" style="background: <?= $b['color'] ?>15; color: <?= $b['color'] ?>;">
                <?= $b['icon'] ?>
            </div>
            <h4 class="badge-name"><?= htmlspecialchars($b['name']) ?></h4>
            <p class="badge-desc"><?= htmlspecialchars($b['description']) ?></p>
            <div class="criteria-box" style="color: <?= $b['color'] ?>; background: <?= $b['color'] ?>10;">
                Awarded on <?= date('M j, Y', strtotime($b['awarded_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Available Badges -->
    <h3 class="section-title" style="margin-top: 20px;"><i class="fas fa-lock-open"></i> Hall of Challenges</h3>
    <div class="badge-grid">
        <?php foreach($available_badges as $b): 
            $current_val = $progress_data[$b['criteria_type']] ?? 0;
            $target = $b['criteria_value'];
            $percent = min(100, ($target > 0 ? ($current_val / $target) * 100 : 0));
        ?>
        <div class="badge-card badge-locked">
            <div class="locked-overlay"><i class="fas fa-lock"></i></div>
            <div class="badge-icon-wrap" style="background: #f1f5f9; color: #64748b;">
                <?= $b['icon'] ?>
            </div>
            <h4 class="badge-name"><?= htmlspecialchars($b['name']) ?></h4>
            <p class="badge-desc"><?= htmlspecialchars($b['description']) ?></p>
            
            <div class="progress-container">
                <div class="progress-labels">
                    <span><?= str_replace('_', ' ', $b['criteria_type']) ?></span>
                    <span><?= $current_val ?> / <?= $target ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $percent ?>%; <?= $percent == 0 ? 'background: transparent;' : '' ?>"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const awarded = urlParams.get('awarded');
        if(awarded && parseInt(awarded) > 0) {
            SDA.showToast(`Congratulations! You just unlocked ${awarded} new merit badge(s)!`, 'success');
            // Clean URL
            window.history.replaceState({}, document.title, "badges.php");
        }
    });
</script>
</body>
</html>
