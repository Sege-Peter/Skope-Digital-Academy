<?php
$pageTitle = 'Student Community Hub';
require_once 'includes/header.php';

?>
<?php require_once 'includes/sidebar.php'; ?>

<style>
    .community-layout {
        max-width: 1000px;
        margin: 0 auto;
        padding-bottom: 60px;
    }

    .community-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        border-radius: 32px;
        padding: 60px 40px;
        color: white;
        text-align: center;
        margin-bottom: 48px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(49, 46, 129, 0.5);
    }
    
    .hero-bg-shapes {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: radial-gradient(circle at 20% 150%, rgba(139, 92, 246, 0.4) 0%, transparent 50%),
                    radial-gradient(circle at 80% -50%, rgba(99, 102, 241, 0.4) 0%, transparent 50%);
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 24px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .community-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        margin-bottom: 48px;
    }

    .platform-card {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 24px;
        padding: 40px;
        text-align: center;
        transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .platform-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1);
        border-color: var(--primary-glow);
    }

    .platform-icon {
        width: 80px;
        height: 80px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 24px;
        color: white;
    }

    .discord-theme .platform-icon { background: linear-gradient(135deg, #5865F2, #4752C4); box-shadow: 0 10px 20px rgba(88,101,242,0.3); }
    .whatsapp-theme .platform-icon { background: linear-gradient(135deg, #25D366, #128C7E); box-shadow: 0 10px 20px rgba(37,211,102,0.3); }
    .linkedin-theme .platform-icon { background: linear-gradient(135deg, #0A66C2, #004182); box-shadow: 0 10px 20px rgba(10,102,194,0.3); }
    .native-theme .platform-icon { background: linear-gradient(135deg, var(--primary), var(--secondary)); box-shadow: 0 10px 20px var(--primary-glow); }

    .platform-title {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 12px;
    }

    .platform-desc {
        color: var(--text-dim);
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 32px;
    }

    .platform-btn {
        margin-top: auto;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        width: 100%;
        transition: 0.2s;
    }

    .discord-theme .platform-btn { background: #5865F2; color: white; border: none; }
    .discord-theme:hover .platform-btn { background: #4752C4; box-shadow: 0 10px 20px rgba(88,101,242,0.3); }

    .whatsapp-theme .platform-btn { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
    .whatsapp-theme:hover .platform-btn { background: #25D366; color: white; border-color: #25D366; box-shadow: 0 10px 20px rgba(37,211,102,0.3); }

    .native-btn { background: var(--bg-light); color: var(--text-dim); border: 1px solid var(--dark-border); cursor: not-allowed; }

    @media (max-width: 768px) {
        .community-grid { grid-template-columns: 1fr; }
        .community-hero { padding: 40px 20px; border-radius: 24px; }
        .community-hero h1 { font-size: 2rem !important; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Global <span class="text-primary">Community</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Connect, collaborate, and grow with peers worldwide.</p>
            </div>
        </div>
    </header>

    <div class="community-layout">
        
        <div class="community-hero">
            <div class="hero-bg-shapes"></div>
            <div style="position: relative; z-index: 2;">
                <div class="hero-badge"><i class="fas fa-globe-africa"></i> 10,000+ Active Learners</div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 3.5rem; font-weight: 900; line-height: 1.2; margin-bottom: 20px;">
                    Never Learn <span style="color: #8b5cf6;">Alone.</span>
                </h1>
                <p style="font-size: 1.1rem; opacity: 0.9; max-width: 600px; margin: 0 auto; line-height: 1.6;">
                    Join the Skope Digital Academy community networks. Share your projects, request peer reviews, and network with instructors and alumni in real-time.
                </p>
            </div>
        </div>

        <div class="community-grid">
            <!-- Discord -->
            <a href="#" class="platform-card discord-theme" onclick="alert('Discord server invitation will be sent to your email soon!')">
                <div class="platform-icon"><i class="fab fa-discord"></i></div>
                <h3 class="platform-title">Skope Official Discord</h3>
                <p class="platform-desc">Join our massive real-time chat server. Participate in voice study rooms, daily UI/UX critiques, and code-review threads.</p>
                <div class="platform-btn">Join Discord Server</div>
            </a>

            <!-- WhatsApp -->
            <a href="https://chat.whatsapp.com/IT9spiypi6q3VJX2hn4hhT" target="_blank" class="platform-card whatsapp-theme">
                <div class="platform-icon"><i class="fab fa-whatsapp"></i></div>
                <h3 class="platform-title">WhatsApp Broadcast</h3>
                <p class="platform-desc">Get instant notifications on your phone for hackathons, flash scholarships, and critical academy announcements.</p>
                <div class="platform-btn">Subscribe on WhatsApp</div>
            </a>

            <!-- LinkedIn -->
            <a href="#" class="platform-card linkedin-theme" onclick="alert('LinkedIn Alumni group is available for verified graduates.')">
                <div class="platform-icon"><i class="fab fa-linkedin-in"></i></div>
                <h3 class="platform-title">Alumni Network</h3>
                <p class="platform-desc">Reserved for graduates. Connect with past students and share job opportunities across global tech hubs.</p>
                <div class="platform-btn" style="background: transparent; border: 2px solid #0A66C2; color: #0A66C2;">Request Access</div>
            </a>

            <!-- Native Native (Coming Soon) -->
            <div class="platform-card native-theme">
                <div style="position: absolute; top: 16px; right: 20px; font-size: 0.7rem; font-weight: 800; color: var(--primary); background: var(--primary-glow); padding: 4px 12px; border-radius: 12px; text-transform: uppercase;">In Development</div>
                <div class="platform-icon"><i class="fas fa-comments"></i></div>
                <h3 class="platform-title">Native Study Forums</h3>
                <p class="platform-desc">Our integrated, threaded discussion boards directly tied to specific course modules and lessons are being built right now.</p>
                <button class="platform-btn native-btn" disabled>Coming Q3 2026</button>
            </div>
        </div>

    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
