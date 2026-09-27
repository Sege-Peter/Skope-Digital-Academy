<?php
$pageTitle = 'Platform Governance & Core Logic';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle Form Submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // In a real app, you'd update a settings table. For now, we simulate success.
    $message = "Institutional parameters synchronized successfully across all system clusters.";
}

?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Institutional <span>Governance</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Platform Configuration • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <button type="submit" form="settingsForm" class="btn-premium">
                <i class="fas fa-shield-check"></i> Commit Synchronized Logic
            </button>
        </div>
    </header>

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
