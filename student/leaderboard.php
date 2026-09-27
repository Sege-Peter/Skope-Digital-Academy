<?php
$pageTitle = 'Institutional Leaderboard';
require_once 'includes/header.php';
require_once '../includes/leaderboard_logic.php';

require_once 'includes/layout-top.php';
?>

<header class="portal-header" style="margin-bottom: 24px;">
    <div class="greeting">
        <h1 style="font-size: 1.85rem; font-weight: 800; margin: 0;">Merit Hall of Fame</h1>
        <p style="color: var(--text-dim); margin-top: 4px;">Performance rankings across the Skope Digital Academy scholars.</p>
    </div>
    <div style="display:flex; gap:16px; align-items:center;">
        <span style="font-size:0.75rem; font-weight:800; color:#94a3b8; text-transform:uppercase;">Track:</span>
        <select onchange="window.location.href='?course_id='+this.value" style="padding: 10px 20px; border-radius: 12px; border: 1px solid var(--border); font-weight: 700; outline: none; background: white;">
            <?php foreach($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $course_id ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</header>

<?php if($course_id && !empty($leaderboard)): ?>
    <div class="podium-section" style="display: flex; align-items: flex-end; justify-content: center; gap: clamp(12px, 3vw, 40px); margin-bottom: 60px; padding: 40px 0; overflow-x: auto;">
        <?php 
        $top3 = array_slice($leaderboard, 0, 3);
        $podium_order = [1, 0, 2]; // 2nd, 1st, 3rd for visual podium
        foreach($podium_order as $order_idx => $pos):
            if(!isset($top3[$pos])) continue;
            $p = $top3[$pos];
            $is_first = ($pos == 0);
        ?>
        <div class="podium-spot" style="display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 160px; <?= $is_first ? 'transform: translateY(-40px);' : '' ?> transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
            <div style="width: <?= $is_first ? '110px' : '90px' ?>; height: <?= $is_first ? '110px' : '90px' ?>; border-radius: 30px; border: 4px solid <?= $is_first ? '#FFD700' : ($pos == 1 ? '#cbd5e1' : '#cd7f32') ?>; overflow: hidden; margin-bottom: 16px; background: #fff; box-shadow: 0 <?= $is_first ? '30px 60px rgba(255, 215, 0, 0.25)' : '20px 40px rgba(0,0,0,0.1)' ?>; position: relative; padding: 4px;">
                <div style="width: 100%; height: 100%; border-radius: 24px; overflow: hidden; background: #f8fafc; border: 1px solid #f1f5f9;">
                    <?php if($p['avatar']): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($p['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:#94a3b8; font-weight:950; font-size:1.5rem;"><?= strtoupper(substr($p['name'] ?? 'S', 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="width: 38px; height: 38px; border-radius: 14px; background: <?= $is_first ? 'linear-gradient(135deg, #FFD700, #FBBF24)' : ($pos == 1 ? 'linear-gradient(135deg, #cbd5e1, #94a3b8)' : 'linear-gradient(135deg, #CD7F32, #92400E)') ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 950; font-size: 1rem; margin-top: -24px; position: relative; z-index: 5; box-shadow: 0 10px 20px rgba(0,0,0,0.15); transform: rotate(-5deg);"><?= $pos + 1 ?></div>
            <div style="font-weight: 950; font-size: 1.1rem; margin-top: 12px; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($p['name']) ?></div>
            <div style="font-weight: 800; color: var(--primary); font-size: 0.8rem; margin-top: 4px;"><?= $p['avg_quiz_score'] ?? 0 ?>% MASTERED</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card" style="padding: 0; border-radius: 20px; overflow: hidden; border: 1px solid var(--border);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                <thead style="background: #f8fafc; border-bottom: 1px solid var(--border);">
                    <tr>
                        <th style="padding: 16px 24px; text-align: left; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Rank</th>
                        <th style="padding: 16px 24px; text-align: left; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Scholar</th>
                        <th style="padding: 16px 24px; text-align: left; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Accuracy</th>
                        <th style="padding: 16px 24px; text-align: left; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($leaderboard as $idx => $l): ?>
                    <tr style="<?= $l['id'] == $student['id'] ? 'background: #f0f9ff; border-left: 4px solid var(--primary);' : '' ?>">
                        <td style="padding: 16px 24px; font-weight: 900; color: <?= $idx < 3 ? 'var(--primary)' : '#94a3b8' ?>; font-size: 1.1rem;">#<?= $idx + 1 ?></td>
                        <td style="padding: 16px 24px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: #e2e8f0; overflow: hidden; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #94a3b8;">
                                    <?php if($l['avatar']): ?><img src="../uploads/avatars/<?= htmlspecialchars($l['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;"><?php else: ?><?= strtoupper(substr($l['name'], 0, 1)) ?><?php endif; ?>
                                </div>
                                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);"><?= htmlspecialchars($l['name']) ?></div>
                            </div>
                        </td>
                        <td style="padding: 16px 24px; font-weight: 800; color: var(--primary);"><?= $l['avg_quiz_score'] ?? '0.00' ?>%</td>
                        <td style="padding: 16px 24px; font-weight: 700; color: var(--text-main);"><?= (int)$l['progress_percent'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 80px 40px; border-style: dashed;">
        <i class="fas fa-medal" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 20px; display: block;"></i>
        <h3 style="font-weight: 800;">No Rankings Available</h3>
        <p style="color: var(--text-dim);">Records for this track are currently empty.</p>
    </div>
<?php endif; ?>

<?php require_once 'includes/layout-bottom.php'; ?>
