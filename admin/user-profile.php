<?php
$pageTitle = 'Institutional Identity Audit';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$uid = (int)($_GET['id'] ?? 0);
if (!$uid) { header('Location: users.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();

    if (!$u) { header('Location: users.php'); exit; }

    // Fetch Academic Progress
    if ($u['role'] === 'student') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
        $stmt->execute([$u['id']]);
        $enrollments = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'completed'");
        $stmt->execute([$u['id']]);
        $completed = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE tutor_id = ?");
        $stmt->execute([$u['id']]);
        $courses_published = $stmt->fetchColumn();
    }

} catch (Exception $e) { $u = null; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Identity <span>Audit</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Stakeholder Governance • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <a href="users.php" class="btn-premium" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Return to Registry
            </a>
            <?php if ($u['status'] === 'pending'): ?>
                <button onclick="SDA.confirmAction('Authorize this stakeholder account?', () => { window.location.href='users.php?action=approve&uid=<?= $u['id'] ?>' })" class="btn-premium">
                    <i class="fas fa-user-check"></i> Authorize Identity
                </button>
            <?php endif; ?>
        </div>
    </header>

    <div class="admin-body">
        <div style="display: grid; grid-template-columns: 320px 1fr; gap: 32px; align-items: start;">
            <!-- Profile Column -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div class="premium-card" style="text-align: center; padding: 40px 24px;">
                    <div style="width: 120px; height: 120px; border-radius: 32px; background: var(--bg-main); border: 2px solid var(--border); margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 900; color: var(--primary); overflow: hidden;">
                        <?php if($u['avatar']): ?>
                            <img src="../uploads/avatars/<?= $u['avatar'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h2 style="font-size: 1.3rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($u['name']) ?></h2>
                    <div style="margin-top: 12px;">
                        <span class="badge-premium" style="background: var(--grad-primary); color: white; border: none; font-weight: 900; font-size: 0.65rem;">
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </div>
                    
                    <div style="margin-top: 40px; padding-top: 32px; border-top: 1px solid var(--border-light); display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <div style="font-size: 1.4rem; font-weight: 900; color: var(--text-main);"><?= $u['merit_points'] ?></div>
                            <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Merit Points</div>
                        </div>
                        <div>
                            <div style="font-size: 1.4rem; font-weight: 900; color: var(--primary);"><?= number_format($u['merit_coins'], 1) ?></div>
                            <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Merit Coins</div>
                        </div>
                    </div>
                </div>

                <div class="premium-card" style="padding: 24px;">
                    <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;">Institutional Engagement</div>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="avatar-sm" style="width: 32px; height: 32px; background: var(--bg-main); border: none; color: var(--primary);"><i class="fas fa-calendar-alt"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">REGISTRATION DATE</div>
                                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main);"><?= date('M j, Y', strtotime($u['created_at'])) ?></div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="avatar-sm" style="width: 32px; height: 32px; background: var(--bg-main); border: none; color: var(--primary);"><i class="fas fa-sign-in-alt"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">LAST SYNCHRONIZATION</div>
                                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main);"><?= $u['last_login'] ? date('M j, g:i a', strtotime($u['last_login'])) : 'N/A' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Audit -->
            <div style="display: flex; flex-direction: column; gap: 32px;">
                <div class="premium-card" style="padding: 48px;">
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1.5px solid var(--border-light);">Demographic & Biological Identity</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px 24px;">
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Institutional ID</label>
                            <div style="font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; font-weight: 900; color: var(--primary); background: var(--primary-glow); padding: 6px 12px; border-radius: 8px; display: inline-block;">
                                <?= htmlspecialchars($u['admission_number'] ?: 'GUEST_000') ?>
                            </div>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Official Communication</label>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Contact Telephony</label>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></div>
                        </div>
                        
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">National Audit ID</label>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($u['national_id'] ?: 'PENDING_VERIFICATION') ?></div>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Scholarly Level</label>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($u['education_level'] ?: 'UNSPECIFIED') ?></div>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Geographical Hub</label>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($u['county'] ?: 'Kenya') ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 48px; padding: 24px; background: var(--bg-main); border-radius: 20px; border: 1.5px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <div class="avatar-sm" style="width: 48px; height: 48px; background: white; border-color: var(--border); color: var(--primary); font-size: 1.2rem;"><i class="fas fa-file-contract"></i></div>
                            <div>
                                <h4 style="font-size: 0.95rem; font-weight: 900; color: var(--text-main);">Institutional Identification Artifact</h4>
                                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Encrypted document for high-fidelity verification.</p>
                            </div>
                        </div>
                        <?php if($u['id_document']): ?>
                            <a href="../uploads/id_docs/<?= $u['id_document'] ?>" target="_blank" class="btn-premium" style="padding: 0 24px; height: 46px; font-size: 0.8rem;">
                                <i class="fas fa-external-link-alt"></i> AUDIT ARTIFACT
                            </a>
                        <?php else: ?>
                            <div class="badge-premium" style="background: var(--danger-glow); color: var(--danger); border: none;">MISSING ARTIFACT</div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 48px; padding-top: 32px; border-top: 1.5px solid var(--border-light);">
                        <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Professional Scholarly Narrative</label>
                        <div style="font-size: 1rem; line-height: 1.8; color: var(--text-main); background: var(--bg-main); padding: 32px; border-radius: 20px; border: 1.5px solid var(--border-light);">
                            <?= $u['bio'] ? nl2br(htmlspecialchars($u['bio'])) : '<span style="color: var(--text-muted); font-style: italic;">No professional narrative archived for this stakeholder.</span>' ?>
                        </div>
                    </div>
                </div>

                <?php if($u['role'] === 'student'): ?>
                <div class="premium-card" style="padding: 40px;">
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px; margin-bottom: 24px;">Academic Velocity Record</h3>
                    <div class="dash-stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <div style="background: var(--bg-main); padding: 24px; border-radius: 20px; text-align: center; border: 1.5px solid var(--border-light);">
                            <div style="font-size: 1.8rem; font-weight: 900; color: var(--text-main);"><?= $enrollments ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">ACTIVE TRACKS</div>
                        </div>
                        <div style="background: var(--bg-main); padding: 24px; border-radius: 20px; text-align: center; border: 1.5px solid var(--border-light);">
                            <div style="font-size: 1.8rem; font-weight: 900; color: var(--success);"><?= $completed ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">COMPLETED PATHS</div>
                        </div>
                        <div style="background: var(--bg-main); padding: 24px; border-radius: 20px; text-align: center; border: 1.5px solid var(--border-light);">
                            <div style="font-size: 1.8rem; font-weight: 900; color: var(--primary);"><?= $enrollments > 0 ? round(($completed/$enrollments)*100) : 0 ?>%</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">COMPLETION VELOCITY</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
