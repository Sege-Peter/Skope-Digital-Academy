<?php
$pageTitle = 'Institutional Leaderboard';
require_once '../includes/header.php';
require_once '../includes/leaderboard_logic.php';
require_once '../includes/sidebar.php';
?>

<style>
    .leaderboard-container { max-width: 1000px; margin: 0 auto; width: 100%; }
    .podium-section { display: flex; align-items: flex-end; justify-content: center; gap: 32px; margin-bottom: 60px; padding: 40px 0; overflow-x: auto; }
    
    .podium-spot { display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 160px; }
    .podium-spot.first { order: 2; transform: translateY(-30px); }
    .podium-spot.second { order: 1; }
    .podium-spot.third { order: 3; }
    
    .podium-rank { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; color: white; margin-bottom: 12px; }
    .first .podium-rank { background: #FFD700; box-shadow: 0 0 20px #FFD70080; }
    .second .podium-rank { background: #C0C0C0; }
    .third .podium-rank { background: #CD7F32; }
    
    .podium-avatar { width: 100px; height: 100px; border-radius: 30px; border: 4px solid white; box-shadow: var(--shadow-lg); overflow: hidden; margin-bottom: 16px; position: relative; }
    .first .podium-avatar { width: 140px; height: 140px; border-color: #FFD700; }
    .podium-avatar img { width: 100%; height: 100%; object-fit: cover; }
    
    .podium-name { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--dark); margin-bottom: 4px; }
    .podium-score { font-weight: 700; color: var(--primary); font-size: 0.95rem; }
    
    .merit-table-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 60px; width: 100%; }
    .table-responsive { width: 100%; overflow-x: auto; }
    .merit-table { width: 100%; border-collapse: collapse; min-width: 800px; }
    .merit-table th { background: #fafafa; padding: 20px 24px; text-align: left; font-size: 0.73rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--dark-border); }
    .merit-table td { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .merit-table tr.is-user { background: #f0f9ff; border-left: 4px solid var(--primary); }
    
    .rank-cell { font-family: 'Poppins', sans-serif; font-weight: 900; color: #94a3b8; font-size: 1.2rem; }
    .identity-cell { display: flex; align-items: center; gap: 16px; }
    .mini-avatar { width: 44px; height: 44px; border-radius: 12px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--text-dim); overflow: hidden; }
    .mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
    
    .score-label { font-size: 0.68rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    .score-value { font-weight: 800; color: var(--dark); font-size: 1.1rem; }

    @media (max-width: 1024px) {
        .podium-section { justify-content: flex-start; padding: 20px; }
        .podium-spot.first { transform: none; order: 1; margin: 0; }
        .podium-spot.second { order: 2; }
        .podium-spot.third { order: 3; }
        .podium-avatar { width: 80px; height: 80px; }
        .first .podium-avatar { width: 80px; height: 80px; }
    }
</style>

<main class="main-content">
    <header class="admin-header" style="background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid var(--dark-border); padding: 0 40px; height: 90px; margin-bottom: 32px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()" style="background: var(--bg-light); width: 44px; height: 44px; border-radius: 12px;"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-primary);">Merit <span style="color: var(--primary);">Hall of Fame</span></h1>
                <p style="color: var(--text-dim); font-size: 0.85rem; font-weight: 500;">Directing scholastic elite records and performance rankings.</p>
            </div>
        </div>
        <div style="display:flex; gap:16px; align-items:center;">
            <span style="font-size:0.75rem; font-weight:800; color:#94a3b8; text-transform:uppercase;">Course Track:</span>
            <select onchange="window.location.href='?course_id='+this.value" style="padding: 12px 24px; border-radius: 12px; border: 1px solid var(--dark-border); font-weight: 700; outline: none; background: white; box-shadow: var(--shadow-sm);">
                <?php foreach($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $course_id ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>

    <?php if($course_id && !empty($leaderboard)): ?>
        <div class="leaderboard-container">
            <!-- Podium for top 3 -->
            <div class="podium-section">
                <?php 
                $top3 = array_slice($leaderboard, 0, 3);
                $podium_ranks = [
                    0 => 'first',
                    1 => 'second',
                    2 => 'third'
                ];
                foreach($top3 as $idx => $p):
                    $rank_class = $podium_ranks[$idx];
                ?>
                <div class="podium-spot <?= $rank_class ?>">
                    <div class="podium-avatar">
                        <?php if($p['avatar']): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($p['avatar']) ?>" alt="">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#94a3b8; font-weight:800; font-size:1.8rem;"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="podium-rank"><?= $idx + 1 ?></div>
                    <div class="podium-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="podium-score"><?= $p['avg_quiz_score'] ?? 0 ?>% Accuracy</div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Full List -->
            <div class="merit-table-card">
                <div class="table-responsive">
                    <table class="merit-table">
                        <thead>
                            <tr>
                                <th style="width:100px;">Rank</th>
                                <th>Scholarly Identity</th>
                                <th>Performance Hub</th>
                                <th>Curriculum Path</th>
                                <th>Accreditations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($leaderboard as $idx => $l): ?>
                            <tr>
                                <td class="rank-cell">#<?= $idx + 1 ?></td>
                                <td>
                                    <div class="identity-cell">
                                        <div class="mini-avatar">
                                            <?php if($l['avatar']): ?>
                                                <img src="../uploads/avatars/<?= htmlspecialchars($l['avatar']) ?>" alt="">
                                            <?php else: ?>
                                                <?= strtoupper(substr($l['name'], 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($l['name']) ?></div>
                                            <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase;">Since <?= date('M Y', strtotime($l['enrolled_at'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="score-label">Accuracy Score</div>
                                    <div class="score-value" style="color: var(--primary);"><?= $l['avg_quiz_score'] ?? '0.00' ?>%</div>
                                </td>
                                <td>
                                    <div class="score-label">Completion Velocity</div>
                                    <div class="score-value"><?= (int)$l['progress_percent'] ?>%</div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <span class="badge" style="background: var(--primary-glow); color: var(--primary); border-radius: 8px; font-size: 0.65rem; padding: 6px 12px; font-weight: 800;">
                                            <i class="fas fa-medal" style="margin-right: 4px;"></i> <?= $l['course_badges'] ?> Merits
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif(!$course_id): ?>
        <div style="text-align:center; padding: 120px 40px;">
            <i class="fas fa-map-marked-alt" style="font-size: 4rem; opacity: 0.1; margin-bottom: 24px;"></i>
            <h3 style="font-family: 'Poppins', sans-serif; font-weight: 800; color: #94a3b8;">No course tracks accessible</h3>
            <p style="color: #cbd5e1; max-width: 400px; margin: 0 auto;">Please ensure enrollment records are active to view the institutional hall of fame.</p>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding: 120px 40px;">
            <i class="fas fa-users-slash" style="font-size: 4rem; opacity: 0.1; margin-bottom: 24px;"></i>
            <h3 style="font-family: 'Poppins', sans-serif; font-weight: 800; color: #94a3b8;">Scholarly Record Empty</h3>
            <p style="color: #cbd5e1; max-width: 400px; margin: 0 auto;">No enrollment data has been validated for the track yet.</p>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
