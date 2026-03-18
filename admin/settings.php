<?php
$pageTitle = 'Platform Settings';
require_once 'includes/header.php';

// Handle Form Submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // In a real app, you'd update a settings table. For now, we simulate success.
    $message = "System settings updated successfully across all clusters.";
}

?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .settings-grid { display: grid; grid-template-columns: 300px 1fr; gap: 40px; margin-top: 40px; }
    
    .settings-nav { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 12px; height: fit-content; }
    .s-nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 12px; color: var(--text-dim); text-decoration: none; font-weight: 600; transition: 0.3s; margin-bottom: 4px; }
    .s-nav-item:hover { background: var(--bg-light); color: var(--primary); }
    .s-nav-item.active { background: var(--primary-glow); color: var(--primary); }

    .settings-content { background: white; border: 1px solid var(--dark-border); border-radius: 32px; padding: 48px; }
    
    .settings-group { margin-bottom: 40px; padding-bottom: 40px; border-bottom: 1px solid var(--bg-light); }
    .settings-group:last-child { border: none; }
    .s-group-title { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--dark); margin-bottom: 8px; }
    .s-group-desc { font-size: 0.85rem; color: var(--text-dim); margin-bottom: 24px; }

    .s-field { margin-bottom: 24px; }
    .s-field label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .s-input { width: 100%; padding: 14px 20px; border-radius: 12px; border: 1px solid var(--dark-border); background: #f8fafc; font-size: 0.95rem; }
    .s-input:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 4px var(--primary-glow); }

    @media (max-width: 1100px) { .settings-grid { grid-template-columns: 1fr; } }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Platform <span class="text-primary">Configuration</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Adjust global parameters and system-wide operational rules.</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <button type="submit" form="settingsForm" name="save_settings" class="btn btn-primary btn-sm" style="padding: 12px 24px;"><i class="fas fa-save"></i> Commit Changes</button>
        </div>
    </header>


    <div class="settings-grid">
        <aside class="settings-nav">
            <a href="#" class="s-nav-item active"><i class="fas fa-sliders"></i> General Rules</a>
            <a href="#" class="s-nav-item"><i class="fas fa-shield-halved"></i> Security & Auth</a>
            <a href="#" class="s-nav-item"><i class="fas fa-envelope-open"></i> Mail Gateway</a>
            <a href="#" class="s-nav-item"><i class="fas fa-credit-card"></i> Payment Methods</a>
            <a href="#" class="s-nav-item"><i class="fas fa-microchip"></i> AI Model Configuration</a>
        </aside>

        <form id="settingsForm" method="POST" class="settings-content">
            <input type="hidden" name="save_settings" value="1">
            
            <div class="settings-group">
                <h3 class="s-group-title">Academy Identity</h3>
                <p class="s-group-desc">Public branding and contact information displayed across the portal.</p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="s-field">
                        <label>Academy Name</label>
                        <input type="text" class="s-input" value="Skope Digital Academy">
                    </div>
                    <div class="s-field">
                        <label>Support Email</label>
                        <input type="email" class="s-input" value="info@skopedigital.ac.ke">
                    </div>
                </div>

                <div class="s-field">
                    <label>Headquarters Location</label>
                    <input type="text" class="s-input" value="Kisumu, Kenya">
                </div>
            </div>

            <div class="settings-group">
                <h3 class="s-group-title">Monetization & Fees</h3>
                <p class="s-group-desc">Global economic rules for course enrollment and verification.</p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="s-field">
                        <label>Default Currency</label>
                        <select class="s-input">
                            <option value="KES">Kenyan Shilling (KES)</option>
                            <option value="USD">US Dollar (USD)</option>
                        </select>
                    </div>
                    <div class="s-field">
                        <label>Audit Wait Period (Hours)</label>
                        <input type="number" class="s-input" value="24">
                    </div>
                </div>
            </div>

            <div class="settings-group">
                <h3 class="s-group-title">Academic Rules</h3>
                <p class="s-group-desc">Certification standards and quiz passage thresholds.</p>
                
                <div class="s-field">
                    <label>Passing Score Threshold (%)</label>
                    <input type="range" min="50" max="100" class="s-input" value="70">
                    <div style="text-align: right; font-size: 0.75rem; font-weight: 800; color: var(--primary); margin-top: 5px;">Currently: 70%</div>
                </div>
            </div>
        </form>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($message): ?>
            SDA.showToast("<?= $message ?>", "success");
        <?php endif; ?>
    });
</script>
</body>
</html>
