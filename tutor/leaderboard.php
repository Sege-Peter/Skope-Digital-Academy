<?php
$pageTitle = 'Institutional Leaderboard';
require_once 'includes/header.php';
require_once '../includes/leaderboard_logic.php';
?>
<?php require_once 'includes/sidebar.php'; ?>

<style>
    @media (max-width: 768px) {
        .podium-section { 
            flex-direction: column !important; 
            align-items: center !important; 
            gap: 40px !important; 
            padding: 20px 0 !important;
        }
        .podium-spot { 
            order: initial !important; 
            transform: none !important;
            width: 100% !important;
        }
        .podium-spot.first { order: -1 !important; }
    }
</style>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Merit Hall of Fame<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Directing <span style="color: var(--primary); font-weight: 700;">scholastic elite records</span> and performance rankings.</p>
    </div>
    <div style="display: flex; gap: 16px; align-items: center;">
        <span style="font-size: 0.75rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Institutional Track:</span>
        <select onchange="window.location.href='?course_id='+this.value" style="padding: 12px 24px; border-radius: 14px; border: 1px solid #E2E8F0; font-weight: 800; outline: none; background: white; color: var(--text-main); font-size: 0.9rem; min-width: 250px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.borderColor='var(--primary)'">
            <option value="">— Select Track —</option>
            <?php foreach($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $course_id ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</header>

<?php if($course_id && !empty($leaderboard)): ?>
    <div class="leaderboard-container">
        <!-- Podium for top 3 -->
        <div class="podium-section" style="display: flex; align-items: flex-end; justify-content: center; gap: 40px; margin-bottom: 80px; padding: 40px 0;">
            <?php 
            $top3 = array_slice($leaderboard, 0, 3);
            $podium_ranks = [
                0 => 'first',
                1 => 'second',
                2 => 'third'
            ];
            foreach($top3 as $idx => $p):
                $rank_class = $podium_ranks[$idx];
                $isFirst = ($idx == 0);
                $isSecond = ($idx == 1);
                $isThird = ($idx == 2);
            ?>
            <div class="podium-spot <?= $rank_class ?>" style="display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 180px; order: <?= $isFirst ? 2 : ($isSecond ? 1 : 3) ?>; transform: <?= $isFirst ? 'translateY(-40px)' : 'none' ?>;">
                <div style="position: relative; margin-bottom: 24px;">
                    <div style="width: <?= $isFirst ? '160px' : '120px' ?>; height: <?= $isFirst ? '160px' : '120px' ?>; border-radius: 40px; border: 6px solid <?= $isFirst ? '#FFD700' : ($isSecond ? '#CBD5E1' : '#CD7F32') ?>; background: white; box-shadow: var(--shadow-premium); overflow: hidden; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
                        <?php if($p['avatar']): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($p['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#94a3b8; font-weight:950; font-size:<?= $isFirst ? '3rem' : '2rem' ?>;"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="position: absolute; bottom: -12px; left: 50%; transform: translateX(-50%); width: 44px; height: 44px; border-radius: 50%; background: <?= $isFirst ? '#FFD700' : ($isSecond ? '#CBD5E1' : '#CD7F32') ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 950; font-size: 1.2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 4px solid white;">
                        <?= $idx + 1 ?>
                    </div>
                </div>
                <div style="font-weight: 950; color: var(--text-main); font-size: 1.2rem; letter-spacing: -0.5px; margin-bottom: 4px;"><?= htmlspecialchars($p['name']) ?></div>
                <div style="font-weight: 850; color: var(--primary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><?= $p['avg_quiz_score'] ?? 0 ?>% ACCURACY</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Full List -->
        <div class="premium-card" style="padding: 0; overflow: hidden; margin-bottom: 60px;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 1.5px solid #F1F5F9;">
                            <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Rank</th>
                            <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Scholarly Identity</th>
                            <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Performance</th>
                            <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Velocity</th>
                            <th style="padding: 24px 32px; text-align: right; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Accreditations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leaderboard as $idx => $l): 
                            $isUser = ($l['id'] == $user['id']);
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: 0.2s; <?= $isUser ? 'background: #E0F7FF;' : '' ?>" onmouseover="this.style.background='<?= $isUser ? '#E0F7FF' : '#F9FAFB' ?>'" onmouseout="this.style.background='<?= $isUser ? '#E0F7FF' : 'transparent' ?>'">
                            <td style="padding: 24px 32px; font-weight: 950; color: #94A3B8; font-size: 1.1rem; letter-spacing: -0.5px;">#<?= $idx + 1 ?></td>
                            <td style="padding: 24px 32px;">
                                <div style="display: flex; align-items: center; gap: 20px;">
                                    <div style="width: 52px; height: 52px; border-radius: 16px; background: white; border: 1px solid #E2E8F0; overflow: hidden; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                        <?php if($l['avatar']): ?>
                                            <img src="../uploads/avatars/<?= htmlspecialchars($l['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-weight:950; font-size:1.2rem;"><?= strtoupper(substr($l['name'], 0, 1)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 900; color: var(--text-main); font-size: 1rem; letter-spacing: -0.3px;"><?= htmlspecialchars($l['name']) ?></div>
                                        <div style="font-size: 0.7rem; color: #94A3B8; font-weight: 850; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Institutionalized <?= date('M Y', strtotime($l['enrolled_at'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 24px 32px;">
                                <div style="font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Evaluation Mean</div>
                                <div style="font-weight: 950; color: var(--primary); font-size: 1.1rem;"><?= $l['avg_quiz_score'] ?? '0.00' ?>%</div>
                            </td>
                            <td style="padding: 24px 32px;">
                                <div style="font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Completion</div>
                                <div style="font-weight: 950; color: var(--text-main); font-size: 1.1rem;"><?= (int)$l['progress_percent'] ?>%</div>
                            </td>
                            <td style="padding: 24px 32px; text-align: right;">
                                <div style="display: inline-flex; gap: 8px; background: #E0F7FF; color: var(--primary); padding: 8px 16px; border-radius: 12px; border: 1px solid #E0F7FF; font-size: 0.8rem; font-weight: 900;">
                                    <i class="fas fa-medal" style="margin-top: 2px;"></i> <?= $l['course_badges'] ?> MERITS
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div style="text-align:center; padding: 120px 40px; background: white; border-radius: 32px; border: 1px solid #F1F5F9; box-shadow: var(--shadow-soft);">
        <div style="width: 80px; height: 80px; background: #F8FAFC; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; color: #CBD5E1;"><i class="fas <?= !$course_id ? 'fa-map-marked-alt' : 'fa-users-slash' ?>"></i></div>
        <h3 style="margin: 0 0 12px; font-weight: 950; font-size: 1.4rem; letter-spacing: -0.5px;"><?= !$course_id ? 'No Tracks Selected' : 'Hall of Fame Empty' ?></h3>
        <p style="color: var(--text-dim); font-weight: 600; max-width: 400px; margin: 0 auto;"><?= !$course_id ? 'Please select an institutional track to view scholarly rankings.' : 'No enrollment data has been validated for this track yet.' ?></p>
    </div>
<?php endif; ?>
</main>
</body>
</html>
