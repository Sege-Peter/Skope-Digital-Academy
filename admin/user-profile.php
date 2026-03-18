<?php
$pageTitle = 'Institutional Identity Audit';
require_once 'includes/header.php';

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

<style>
    /* Neutralize the dark fallback from admin.css for a clean light layout */
    .admin-header {
        background: white !important;
        border-bottom: 2px solid var(--dark-border) !important;
        backdrop-filter: none !important;
        padding-top: 24px !important;
        padding-bottom: 24px !important;
    }
    .admin-header h1 { color: var(--dark) !important; }
    .admin-header .nav-toggle { margin-right: 20px; }
    
    /* Layout Grid */
    .profile-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 40px;
        align-items: start;
        margin-top: 32px;
    }
    .profile-main {
        min-width: 0; /* Prevents CSS grid blowout from long text */
    }

    @media (max-width: 1024px) {
        .profile-layout { grid-template-columns: 1fr; gap: 24px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Identity <span class="text-primary">Audit</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Comprehensive oversight of <?= htmlspecialchars($u['name']) ?>'s academic and biographical record.</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="users.php" class="btn btn-ghost btn-sm" style="border: 1px solid var(--dark-border);"><i class="fas fa-arrow-left"></i> Return to Registry</a>
            <?php if ($u['status'] === 'pending'): ?>
                <a href="users.php?action=approve&uid=<?= $u['id'] ?>" class="btn btn-primary btn-sm" style="background: #10B981; border:none;">Verify Account</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="profile-layout">
        <!-- Left: Basic Stats & Avatar -->
        <div class="profile-aside">
            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 32px; padding: 40px; text-align: center;">
                <div style="width: 140px; height: 140px; border-radius: 48px; background: var(--primary-glow); margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 3px solid #fff; box-shadow: var(--shadow-md);">
                    <?php if($u['avatar']): ?>
                        <img src="../uploads/avatars/<?= $u['avatar'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span style="font-size: 4rem; font-weight: 900; color: var(--primary);"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                
                <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--dark);"><?= htmlspecialchars($u['name']) ?></h2>
                <div style="margin: 8px 0 24px;">
                    <span class="badge <?= $u['role'] == 'tutor' ? 'badge-primary' : 'badge-ghost' ?>" style="padding: 6px 16px; border-radius: 12px; font-weight: 800; font-size: 0.7rem;">
                        <?= strtoupper($u['role']) ?>
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding-top: 24px; border-top: 1px solid var(--bg-light);">
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 900; color: var(--dark);"><?= $u['points'] ?></div>
                        <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Merit Points</div>
                    </div>
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 900; color: var(--primary);"><?= number_format($u['merit_coins'], 2) ?></div>
                        <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Merit Coins</div>
                    </div>
                </div>
            </div>

            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 24px; margin-top: 24px;">
                <h4 style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 20px;">Institutional History</h4>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: #FFF7ED; color: #EA580C; display: flex; align-items: center; justify-content: center;"><i class="fas fa-calendar-plus"></i></div>
                    <div>
                        <div style="font-size: 0.84rem; font-weight: 700;">Account Created</div>
                        <div style="font-size: 0.72rem; color: var(--text-dim);"><?= date('M j, Y, g:i a', strtotime($u['created_at'])) ?></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: #F0F9FF; color: #0369A1; display: flex; align-items: center; justify-content: center;"><i class="fas fa-sign-in-alt"></i></div>
                    <div>
                        <div style="font-size: 0.84rem; font-weight: 700;">Last Active Session</div>
                        <div style="font-size: 0.72rem; color: var(--text-dim);"><?= $u['last_login'] ? date('M j, Y, g:i a', strtotime($u['last_login'])) : 'Never active' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Expanded Biographical Identity -->
        <div class="profile-main">
            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 32px; padding: 40px; margin-bottom: 32px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--dark); margin-bottom: 32px; border-bottom: 2px solid var(--bg-light); padding-bottom: 15px;">Demographic & Biological Identity</h3>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px 24px;">
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Admission Number</label>
                        <div style="font-weight: 800; color: var(--primary); font-family: 'Courier New', monospace; font-size: 1.1rem; background: var(--primary-glow); padding: 4px 10px; border-radius: 8px; display: inline-block;">
                            <?= htmlspecialchars($u['admission_number'] ?: 'N/A') ?>
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Official Email</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Contact Phone</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($u['phone'] ?: 'Not Provided') ?></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">National ID / Passport</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($u['national_id'] ?: 'Not Audited') ?></div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Date of Birth</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= $u['dob'] ? date('M j, Y', strtotime($u['dob'])) : 'Not Provided' ?></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Gender Profile</label>
                        <div style="font-weight: 600; color: var(--dark); text-transform: capitalize;"><?= htmlspecialchars($u['gender'] ?: 'Unspecified') ?></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Education Level</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($u['education_level'] ?: 'Not Provided') ?></div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Nationality</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($u['nationality'] ?: 'Kenyan') ?></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Regional County</label>
                        <div style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($u['county'] ?: 'Not Provided') ?></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px;">Referral Logic</label>
                        <div style="font-weight: 600; color: var(--primary); font-family: monospace;"><?= $u['referral_code'] ?></div>
                    </div>
                </div>

                <div style="margin-top: 32px; padding: 24px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="font-size: 0.85rem; font-weight: 800; color: var(--dark); margin-bottom: 4px;">Institutional ID Document</h4>
                            <p style="font-size: 0.72rem; color: var(--text-dim);">Required for high-level certification verification.</p>
                        </div>
                        <?php if($u['id_document']): ?>
                            <a href="../uploads/id_docs/<?= $u['id_document'] ?>" target="_blank" class="btn btn-primary btn-sm" style="font-size: 0.75rem;">
                                <i class="fas fa-file-shield"></i> View / Audit Document
                            </a>
                        <?php else: ?>
                            <span style="font-size: 0.75rem; color: #ef4444; font-weight: 700; background: #fee2e2; padding: 6px 14px; border-radius: 10px;">
                                <i class="fas fa-triangle-exclamation"></i> No Document Uploaded
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 48px; padding-top: 32px; border-top: 1px solid var(--bg-light);">
                    <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 12px; letter-spacing: 1px;">Professional Narrative / Bio</label>
                    <div style="font-size: 1rem; color: var(--text-dim); line-height: 1.8; background: var(--bg-light); padding: 24px; border-radius: 16px;">
                        <?= $u['bio'] ? nl2br(htmlspecialchars($u['bio'])) : 'No professional narrative has been submitted by this stakeholder.' ?>
                    </div>
                </div>
            </div>

            <?php if($u['role'] === 'student'): ?>
                <div style="background: white; border: 1px solid var(--dark-border); border-radius: 32px; padding: 40px;">
                    <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--dark); margin-bottom: 24px;">Academic Record Summary</h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
                        <div style="background: var(--bg-light); padding: 20px; border-radius: 20px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: 900; color: var(--dark);"><?= $enrollments ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Enrolled Courses</div>
                        </div>
                        <div style="background: #FFF7ED; padding: 20px; border-radius: 20px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: 900; color: #EA580C;"><?= $completed ?></div>
                            <div style="font-size: 0.65rem; color: #EA580C; text-transform: uppercase; font-weight: 800;">Completed Paths</div>
                        </div>
                        <div style="background: #F0FDF4; padding: 20px; border-radius: 20px; text-align: center;">
                            <div style="font-size: 1.8rem; font-weight: 900; color: #166534;"><?= $enrollments > 0 ? round(($completed/$enrollments)*100) : 0 ?>%</div>
                            <div style="font-size: 0.65rem; color: #166534; text-transform: uppercase; font-weight: 800;">Success Velocity</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
