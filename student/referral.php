<?php
$pageTitle = 'Institutional Referral Network';
require_once '../includes/header.php';

// Auth: Students only
if ($user['role'] !== 'student') { header('Location: ../login.php'); exit; }

try {
    // 0. Fetch Full User Context (for referral_code, merit_coins, etc.)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user_full = $stmt->fetch();
    if (!$user_full) { header('Location: ../login.php'); exit; }
    // Override local $user with full DB record for consistency
    $user = $user_full;

    // 1. Fetch Referral Stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $stmt->execute([$user['id']]);
    $total_referrals = $stmt->fetchColumn();

    // 2. Fetch Detailed Network (Scholars referred by this user)
    $stmt = $pdo->prepare("SELECT name, email, avatar, created_at, status 
                           FROM users 
                           WHERE referred_by = ? 
                           ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $network = $stmt->fetchAll();

    // 3. Fetch Revenue History (Merit Coins earned via referrals)
    $stmt = $pdo->prepare("SELECT action, description, created_at 
                           FROM audit_log 
                           WHERE user_id = ? AND action = 'referral_earned' 
                           ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $history = $stmt->fetchAll();

    // 4. Global Scholarships for Referral Context
    $stmt = $pdo->query("SELECT * FROM scholarships WHERE (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY created_at DESC LIMIT 3");
    $available_scholarships = $stmt->fetchAll();

} catch (Exception $e) { $total_referrals = 0; $network = $history = $available_scholarships = []; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header" style="background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid var(--dark-border); padding: 0 40px; height: 90px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()" style="background: var(--bg-light); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px;">Network <span style="color: var(--primary);">Growth</span> Hub</h1>
                <p style="color: var(--text-dim); font-size: 0.85rem; font-weight: 500; margin-top: 2px;">Track your impact and accumulate Merit Coins.</p>
            </div>
        </div>
        <div style="display: flex; gap: 16px; align-items: center;">
            <div style="display: flex; align-items: center; gap: 16px; background: white; padding: 10px 20px; border-radius: 16px; border: 1px solid var(--primary); box-shadow: 0 10px 20px rgba(0, 174, 239, 0.1);">
                <div style="width: 40px; height: 40px; background: var(--primary-glow); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.2rem;">
                    <i class="fas fa-coins"></i>
                </div>
                <div>
                    <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Earned Coins</div>
                    <div style="font-size: 1.2rem; font-weight: 900; color: #0f172a;"><?= number_format($user['merit_coins'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Share Card -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; border-radius: 32px; padding: 48px; margin-bottom: 48px; display: flex; align-items: center; justify-content: space-between; gap: 48px; flex-wrap: wrap; position: relative; overflow: hidden; box-shadow: 0 30px 60px rgba(15, 23, 42, 0.15); border: 1px solid rgba(255,255,255,0.05);">
        <div style="position: absolute; right: -50px; top: -50px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(0, 191, 255, 0.15), transparent 70%); border-radius: 50%;"></div>
        <div style="flex: 1; min-width: 320px; position: relative; z-index: 1;">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 2.2rem; font-weight: 900; margin-bottom: 20px; line-height: 1.1; letter-spacing: -1px; color: white !important;">The Power of <span style="color: var(--primary);">Scholarly</span> Network</h2>
            <p style="opacity: 0.8; font-size: 1.1rem; line-height: 1.75; max-width: 580px; font-weight: 500; color: white;">Your influence as a scholar directly contributes to the growth of the academy. Secure <strong>4% institutional credit</strong> for every colleague who launches their career journey via your professional link.</p>
            
            <div style="margin-top: 40px; display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="background: rgba(255,255,255,0.03); padding: 20px 28px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); flex: 1; min-width: 160px;">
                    <div style="font-size: 0.65rem; opacity: 0.5; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; font-weight: 800;">Network Reach</div>
                    <div style="font-size: 1.8rem; font-weight: 900;"><?= $total_referrals ?> <span style="font-size: 0.85rem; font-weight: 500; opacity: 0.6;">Scholars</span></div>
                </div>
                <div style="background: rgba(0, 174, 239, 0.1); padding: 20px 28px; border-radius: 20px; border: 1px solid rgba(0, 174, 239, 0.3); flex: 1.2; min-width: 200px;">
                    <div style="font-size: 0.65rem; color: var(--primary); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; font-weight: 800;">Institutional Code</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: var(--primary); font-family: 'Poppins', sans-serif; letter-spacing: 1px;"><?= $user['referral_code'] ?></div>
                </div>
            </div>
        </div>
        <div style="background: white; border-radius: 28px; padding: 40px; text-align: center; min-width: 320px; box-shadow: 0 40px 80px rgba(0,0,0,0.25); position: relative; z-index: 2;">
            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; font-weight: 800;">Your Referral Registry Link</div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; font-family: monospace; font-size: 0.84rem; margin-bottom: 32px; color: #0f172a; word-break: break-all; min-height: 80px; display: flex; align-items: center; justify-content: center;" id="refURL">
                http://localhost/Skope Digital Academy/register.php?ref=<?= $user['referral_code'] ?>
            </div>
            <button class="btn btn-primary btn-block" onclick="copyRefURL()" style="height: 64px; font-weight: 800; letter-spacing: 1px; border-radius: 18px; box-shadow: 0 15px 30px rgba(0, 174, 239, 0.25);">
                <i class="fas fa-link" style="margin-right: 12px;"></i> Copy Professional Link
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="dash-grid">
        <!-- Scholar Network List -->
        <div class="dash-main-col">
            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-sm);">
                <div style="padding: 24px 32px; border-bottom: 1px solid var(--dark-border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 800;"><i class="fas fa-users-rays text-primary"></i> Scholarly Network</h3>
                    <span style="font-size: 0.75rem; color: var(--text-dim); font-weight: 800; text-transform: uppercase;"><?= count($network) ?> Active Connections</span>
                </div>
                <div style="padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: var(--bg-light); border-bottom: 1px solid var(--dark-border);">
                            <tr>
                                <th style="padding: 16px 32px; text-align: left; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Scholar</th>
                                <th style="padding: 16px 32px; text-align: left; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Connection Date</th>
                                <th style="padding: 16px 32px; text-align: right; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($network as $n): ?>
                            <tr style="border-bottom: 1px solid var(--bg-light);">
                                <td style="padding: 20px 32px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800;">
                                            <?= strtoupper(substr($n['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-size: 0.95rem; font-weight: 700;"><?= htmlspecialchars($n['name']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars(substr($n['email'], 0, 3) . '***@***.com') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 20px 32px; font-size: 0.9rem; color: var(--text-dim);">
                                    <?= date('M j, Y', strtotime($n['created_at'])) ?>
                                </td>
                                <td style="padding: 20px 32px; text-align: right;">
                                    <span style="font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; background: #DCFCE7; color: #166534;"><?= $n['status'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($network)): ?>
                            <tr>
                                <td colspan="3" style="padding: 80px; text-align: center;">
                                    <i class="fas fa-user-plus" style="font-size: 2.5rem; color: var(--dark-border); margin-bottom: 16px;"></i>
                                    <p style="color: var(--text-dim);">Your scholarly network is waiting for its first connection.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Merit Coin History -->
        <aside style="display: flex; flex-direction: column; gap: 24px;">
            <?php if(!empty($available_scholarships)): ?>
            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 28px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 800; margin-bottom: 20px;"><i class="fas fa-hand-holding-heart text-primary"></i> Scholarly Opportunities</h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach($available_scholarships as $as): ?>
                    <a href="../scholarships.php" style="text-decoration: none; display: block; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; transition: 0.3s;" onmouseover="this.style.borderColor='var(--primary)'; this.style.background='white';">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;">Grant Active</span>
                            <i class="fas fa-arrow-right-long" style="font-size: 0.8rem; color: #cbd5e1;"></i>
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;"><?= htmlspecialchars($as['title']) ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-dim); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($as['description']) ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 32px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 800; margin-bottom: 24px;"><i class="fas fa-receipt text-primary"></i> Reward History</h3>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php if(!empty($history)): ?>
                        <?php foreach($history as $h): ?>
                        <div style="border-left: 3px solid var(--primary); padding-left: 16px; margin-bottom: 4px;">
                            <div style="font-size: 0.85rem; font-weight: 700;"><?= $h['description'] ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;"><?= date('M j, Y', strtotime($h['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 0;">
                            <p style="font-size: 0.88rem; color: var(--text-dim);">No rewards have been accumulated yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</main>

<script>
    function copyRefURL() {
        const urlText = document.getElementById('refURL').innerText;
        navigator.clipboard.writeText(urlText).then(() => {
            SDA.showToast("Scholarly Referral Link copied to clipboard!", "success");
        });
    }
</script>

<script src="../assets/js/main.js"></script>
</body>
</html>
