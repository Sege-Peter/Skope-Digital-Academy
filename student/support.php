<?php
$pageTitle = 'Help Desk & Support';
require_once 'includes/header.php';

// Handle Ticket Submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $content = trim($_POST['message']);

    try {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status, created_at) VALUES (?, ?, ?, 'open', NOW())");
        $stmt->execute([$student['id'], $subject, $content]);
        $message = "Your support request has been logged. Ticket #SK-" . $pdo->lastInsertId();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

try {
    // Fetch student's open/recent tickets
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$student['id']]);
    $tickets = $stmt->fetchAll();
} catch (Exception $e) { $tickets = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .support-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
    
    .faq-item { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 24px; margin-bottom: 20px; transition: 0.3s; }
    .faq-item:hover { border-color: var(--primary); }
    .faq-q { font-family: 'Poppins', sans-serif; font-weight: 800; color: var(--dark); font-size: 1.05rem; margin-bottom: 12px; display: flex; align-items: center; gap: 12px; }
    .faq-a { color: var(--text-dim); font-size: 0.92rem; line-height: 1.6; }

    .support-sidebar { display: flex; flex-direction: column; gap: 32px; }
    
    .ticket-list-card { background: var(--bg-light); border-radius: 24px; padding: 32px; border: 1px solid var(--dark-border); }
    .ticket-mini { padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .ticket-mini:last-child { border: none; padding-bottom: 0; margin-bottom: 0; }
    
    .ticket-status { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 8px; float: right; }
    .status-open { background: #DCFCE7; color: #166534; }
    .status-closed { background: #F1F5F9; color: #475569; }

    .contact-card { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-radius: 24px; padding: 32px; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 191, 255, 0.2); }
    .contact-card::after { content: '\f1d8'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.15; }

    @media (max-width: 1100px) { .support-grid { grid-template-columns: 1fr; } }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Academy <span class="text-primary">Concierge</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Direct access to our specialized support and technical assistance team.</p>
            </div>
        </div>
    </header>

    <?php if($message): ?>
        <div style="padding: 16px 24px; background: var(--primary-glow); color: var(--primary); border-radius: 16px; margin-bottom: 32px; font-weight: 600; border: 1px solid var(--primary);">
            <i class="fas fa-paper-plane"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="support-grid">
        <div class="support-main">
            <!-- New Ticket Form -->
            <div class="card" style="padding: 40px; margin-bottom: 48px; border-radius: 32px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.4rem; margin-bottom: 24px;">Open New Support Ticket</h3>
                <form method="POST">
                    <input type="hidden" name="submit_ticket" value="1">
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px;">Subject Area</label>
                        <select name="subject" class="form-control" style="border-radius: 12px; height: 50px;" required>
                            <option value="Course Access Issue">Course Access Issue</option>
                            <option value="Billing & Payment Verification">Billing & Payment Verification</option>
                            <option value="Technical Bug / Platform Error">Technical Bug / Platform Error</option>
                            <option value="Certificate Verification">Certificate Verification</option>
                            <option value="General Inquiry">General Inquiry</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 32px;">
                        <label style="display: block; font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px;">Detailed Message</label>
                        <textarea name="message" class="form-control" style="min-height: 180px; border-radius: 16px; padding: 20px;" placeholder="Describe your issue in detail so we can assist you faster..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 16px 40px; border-radius: 14px; font-weight: 800;">
                        Initialize Case <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                    </button>
                </form>
            </div>

            <h3 style="font-family: 'Poppins', sans-serif; font-weight: 800; margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-lightbulb text-secondary"></i> Knowledge Base & FAQ
            </h3>
            
            <div class="faq-item">
                <div class="faq-q"><i class="fas fa-circle-question text-primary"></i> When will my account be verified?</div>
                <div class="faq-a">Identity verification and manual payment audits typically take between 2 to 6 business hours. High-volume periods may slightly extend this window.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q"><i class="fas fa-circle-question text-primary"></i> Can I change my course enrollment?</div>
                <div class="faq-a">For certified tracks, course swaps are only permitted within the first 24 hours of enrollment, provided no more than 10% of the lessons have been consumed.</div>
            </div>
            <div class="faq-item">
                <div class="faq-q"><i class="fas fa-circle-question text-primary"></i> How do I download my certificate?</div>
                <div class="faq-a">Certificates are automatically generated in PDF format once you achieve 100% curriculum completion and pass all required memory challenges.</div>
            </div>
        </div>

        <aside class="support-sidebar">
            <div class="contact-card">
                <h4 style="font-family: 'Poppins', sans-serif; font-weight: 800; margin-bottom: 12px; font-size: 1.1rem;">Instant Assistance?</h4>
                <p style="font-size: 0.82rem; opacity: 0.9; line-height: 1.5; margin-bottom: 24px;">For urgent certification issues, our rapid response team is available via email.</p>
                <div style="font-weight: 800; font-size: 0.9rem;"><i class="fas fa-envelope-open-text"></i> support@skopedigital.ac.ke</div>
            </div>

            <div class="ticket-list-card">
                <h4 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1rem; margin-bottom: 20px;">Your Active Cases</h4>
                <?php if(!empty($tickets)): ?>
                    <?php foreach($tickets as $t): ?>
                    <div class="ticket-mini">
                        <span class="ticket-status status-<?= $t['status'] ?>"><?= $t['status'] ?></span>
                        <div style="font-weight: 700; font-size: 0.85rem; color: var(--dark); margin-bottom: 4px;"><?= htmlspecialchars($t['subject']) ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-dim);"><?= date('M j, Y', strtotime($t['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size: 0.82rem; color: var(--text-dim); font-style: italic;">No active support tickets found.</p>
                <?php endif; ?>
            </div>

            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 32px; text-align: center;">
                <i class="fas fa-book-bookmark" style="font-size: 2.5rem; color: var(--primary-glow); margin-bottom: 16px;"></i>
                <h5 style="font-weight: 800; margin-bottom: 8px;">Operating Hours</h5>
                <p style="font-size: 0.75rem; color: var(--text-dim);">Mon - Fri: 8:00 AM - 6:00 PM<br>Sat: 9:00 AM - 1:00 PM<br>(GMT+3)</p>
            </div>
        </aside>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
