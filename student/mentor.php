<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$pageTitle = 'AI Academic Mentor';
require_once '../includes/header.php';

// Auth Check
if ($user['role'] !== 'student') {
    header('Location: /Skope Digital Academy/login.php');
    exit;
}

// Fetch Student Progress for Intelligence
try {
    // Get last studied course
    $stmt = $pdo->prepare("SELECT c.title, c.id, cat.name as category 
                          FROM enrollments e 
                          JOIN courses c ON e.course_id = c.id 
                          LEFT JOIN categories cat ON c.category_id = cat.id
                          WHERE e.student_id = ? 
                          ORDER BY e.enrolled_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $lastCourse = $stmt->fetch();

    // Get average quiz score
    $avgScore = $pdo->prepare("SELECT AVG(score) FROM quiz_attempts WHERE student_id = ?");
    $avgScore->execute([$user['id']]);
    $performance = $avgScore->fetchColumn() ?: 0;

    // Count quizzes taken
    $quizCount = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ?");
    $quizCount->execute([$user['id']]);
    $totalQuizzes = $quizCount->fetchColumn() ?: 0;

    // Count enrolled courses
    $enrollCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
    $enrollCount->execute([$user['id']]);
    $totalEnrolled = $enrollCount->fetchColumn() ?: 0;

} catch (Exception $e) { $lastCourse = null; $performance = 0; $totalQuizzes = 0; $totalEnrolled = 0; }

$isSaturday = (date('w') == 6);
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
/* ── Page Layout ──────────────────────────────────────────── */
.mentor-page { display: grid; grid-template-columns: 1fr 320px; gap: 28px; height: calc(100vh - 180px); min-height: 500px; }

/* ── Chat Container ────────────────────────────────────────── */
.chat-container {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--dark-border);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    position: relative;
}

/* ── Chat Header ───────────────────────────────────────────── */
.chat-header {
    padding: 20px 28px;
    background: #fff;
    border-bottom: 1px solid var(--dark-border);
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}
.mentor-avatar {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--primary), #0062a3);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(0,191,255,0.35);
    position: relative;
}
.mentor-avatar::after {
    content: '';
    position: absolute;
    bottom: -2px; right: -2px;
    width: 14px; height: 14px;
    border-radius: 50%;
    border: 2.5px solid #fff;
    background: #10B981;
}
.mentor-avatar.offline::after { background: #EF4444; }
.chat-header-info { flex: 1; }
.chat-header-name { font-family: 'Poppins', sans-serif; font-size: 1rem; font-weight: 700; color: var(--dark); }
.chat-header-status { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: var(--text-dim); margin-top: 2px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; background: #10B981; animation: pulse-status 2s infinite; flex-shrink: 0; }
.status-dot.offline { background: #EF4444; animation: none; }
@keyframes pulse-status {
    0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
    50% { box-shadow: 0 0 0 5px rgba(16,185,129,0); }
}
.chat-header-actions { display: flex; gap: 8px; }
.chat-action-btn {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: var(--bg-light);
    border: 1px solid var(--dark-border);
    color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}
.chat-action-btn:hover { background: var(--primary-glow); color: var(--primary); border-color: var(--primary); }

/* ── Chat Messages ─────────────────────────────────────────── */
.chat-messages {
    flex: 1;
    padding: 28px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    background:
        radial-gradient(ellipse 80% 50% at 50% 0%, rgba(0,191,255,0.04) 0%, transparent 60%),
        #f8fafc;
    background-image:
        linear-gradient(rgba(226,232,240,0.5) 1px, transparent 1px),
        linear-gradient(90deg, rgba(226,232,240,0.5) 1px, transparent 1px);
    background-size: 32px 32px;
    background-color: #f8fafc;
    scroll-behavior: smooth;
}

/* ── Message Bubbles ───────────────────────────────────────── */
.msg-row { display: flex; gap: 12px; align-items: flex-end; animation: msg-appear 0.35s cubic-bezier(0.34,1.56,0.64,1) both; }
.msg-row.student-row { flex-direction: row-reverse; }
@keyframes msg-appear {
    from { opacity: 0; transform: translateY(12px) scale(0.95); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.msg-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700;
    align-self: flex-end;
    margin-bottom: 4px;
}
.msg-avatar.ai { background: linear-gradient(135deg, var(--primary), #0062a3); color: #fff; }
.msg-avatar.you { background: var(--secondary); color: #fff; }

.msg-bubble {
    max-width: 76%;
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 0.93rem;
    line-height: 1.65;
    position: relative;
    word-wrap: break-word;
}
.msg-bubble.mentor-msg {
    background: #fff;
    border: 1px solid var(--dark-border);
    border-bottom-left-radius: 5px;
    color: var(--text-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.msg-bubble.student-msg {
    background: linear-gradient(135deg, var(--primary), #0088cc);
    color: #fff;
    border-bottom-right-radius: 5px;
    box-shadow: 0 4px 14px rgba(0,191,255,0.3);
}
.msg-time { font-size: 0.7rem; color: var(--text-dim); margin-top: 5px; display: block; }
.student-row .msg-time { text-align: right; }

/* Markdown rendering in mentor messages */
.msg-bubble.mentor-msg strong { font-weight: 700; }
.msg-bubble.mentor-msg em { font-style: italic; }
.msg-bubble.mentor-msg code {
    background: var(--bg-light);
    border: 1px solid var(--dark-border);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.85em;
    font-family: 'Fira Code', monospace;
}
.msg-bubble.mentor-msg pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 0.82em;
    overflow-x: auto;
    margin: 8px 0;
}
.msg-bubble.mentor-msg ul, .msg-bubble.mentor-msg ol {
    padding-left: 20px;
    margin: 6px 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.msg-bubble.mentor-msg li { font-size: 0.92rem; }
.msg-bubble.mentor-msg h1,.msg-bubble.mentor-msg h2,.msg-bubble.mentor-msg h3 {
    font-size: 0.98rem; font-weight: 700; margin: 8px 0 4px;
}
.msg-bubble.mentor-msg p { margin: 4px 0; }

/* ── Typing Indicator ──────────────────────────────────────── */
.typing-bubble {
    background: #fff;
    border: 1px solid var(--dark-border);
    border-radius: 18px;
    border-bottom-left-radius: 5px;
    padding: 14px 20px;
    display: none;
    gap: 5px;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    max-width: 90px;
}
.typing-bubble.show { display: flex; }
.typing-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--primary);
    animation: typing-bounce 1.2s infinite ease-in-out;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-bounce {
    0%,80%,100% { transform: scale(0.7); opacity: 0.5; }
    40% { transform: scale(1); opacity: 1; }
}

/* ── Offline Banner ────────────────────────────────────────── */
.offline-banner {
    margin: 16px 28px;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(239,68,68,0.08), rgba(239,68,68,0.04));
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 14px;
    color: #991b1b;
    flex-shrink: 0;
}
.offline-banner i { font-size: 1.5rem; color: var(--danger); }
.offline-banner strong { display: block; font-size: 0.95rem; margin-bottom: 2px; }
.offline-banner span { font-size: 0.82rem; color: var(--text-muted); }

/* ── Chat Input ────────────────────────────────────────────── */
.chat-input-area {
    padding: 18px 24px;
    border-top: 1px solid var(--dark-border);
    background: #fff;
    flex-shrink: 0;
}
.chat-input-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg-light);
    border: 1.5px solid var(--dark-border);
    border-radius: 16px;
    padding: 8px 8px 8px 18px;
    transition: border-color 0.25s, box-shadow 0.25s;
}
.chat-input-wrap:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-glow);
    background: #fff;
}
#userInput {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.93rem;
    color: var(--text-primary);
    font-family: var(--font);
    resize: none;
    max-height: 120px;
    line-height: 1.5;
}
#userInput::placeholder { color: #94a3b8; }
.send-btn {
    width: 42px; height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary), #0088cc);
    border: none;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(0,191,255,0.35);
}
.send-btn:hover { transform: scale(1.08); box-shadow: 0 5px 16px rgba(0,191,255,0.5); }
.send-btn:active { transform: scale(0.96); }
.send-btn:disabled { opacity: 0.5; pointer-events: none; }

.quick-prompts { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; }
.quick-prompt-btn {
    padding: 6px 14px;
    border-radius: 20px;
    background: var(--bg-light);
    border: 1px solid var(--dark-border);
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.quick-prompt-btn:hover { background: var(--primary-glow); color: var(--primary); border-color: var(--primary); }

/* ── Sidebar Panel ─────────────────────────────────────────── */
.mentor-sidebar { display: flex; flex-direction: column; gap: 20px; overflow-y: auto; }

.info-card {
    background: #fff;
    border: 1px solid var(--dark-border);
    border-radius: 20px;
    padding: 24px;
    box-shadow: var(--shadow-sm);
}
.info-card-title {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--text-dim);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.info-card-title i { color: var(--primary); }

/* Stats chips */
.stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.stat-chip {
    background: var(--bg-light);
    border: 1px solid var(--dark-border);
    border-radius: 12px;
    padding: 14px 12px;
    text-align: center;
}
.stat-chip-val { font-size: 1.4rem; font-weight: 800; color: var(--primary); line-height: 1; }
.stat-chip-lbl { font-size: 0.72rem; color: var(--text-dim); margin-top: 4px; }

/* Current course */
.current-course-box {
    display: flex; gap: 12px; align-items: flex-start;
    background: var(--primary-glow);
    border: 1px solid rgba(0,191,255,0.2);
    border-radius: 12px;
    padding: 14px;
}
.course-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.course-name { font-size: 0.88rem; font-weight: 700; color: var(--dark); margin-bottom: 3px; }
.course-cat { font-size: 0.75rem; color: var(--text-dim); }

/* Suggestions */
.suggestion-list { display: flex; flex-direction: column; gap: 8px; }
.suggestion-item {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 10px 12px;
    background: var(--bg-light);
    border: 1px solid var(--dark-border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
    width: 100%;
}
.suggestion-item:hover { background: var(--primary-glow); border-color: var(--primary); }
.suggestion-item i { color: var(--primary); margin-top: 2px; font-size: 0.85rem; flex-shrink: 0; }
.suggestion-item span { font-size: 0.82rem; color: var(--text-muted); line-height: 1.4; }
.suggestion-item:hover span { color: var(--primary); }

/* Gradient banner card */
.banner-card {
    background: linear-gradient(135deg, #0062a3, var(--primary));
    border-radius: 20px;
    padding: 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.banner-card::before {
    content: '\f544';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: -10px; bottom: -10px;
    font-size: 5rem;
    opacity: 0.15;
    transform: rotate(-10deg);
}
.banner-card h4 { font-family: 'Poppins', sans-serif; font-size: 0.95rem; margin-bottom: 8px; position: relative; }
.banner-card p { font-size: 0.8rem; opacity: 0.85; line-height: 1.5; position: relative; }

/* ── Responsive ────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .mentor-page { grid-template-columns: 1fr; height: auto; }
    .chat-container { height: calc(100vh - 200px); min-height: 480px; }
    .mentor-sidebar { display: none; }
}
@media (max-width: 480px) {
    .chat-container { border-radius: 14px; }
    .chat-messages { padding: 16px; }
    .chat-header { padding: 14px 18px; }
    .chat-input-area { padding: 12px 14px; }
    .msg-bubble { max-width: 88%; font-size: 0.88rem; }
    .quick-prompts { display: none; }
}
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="document.getElementById('dashSidebar').classList.toggle('open'); document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">AI Academic <span class="text-primary">Mentor</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Intelligent guidance powered by SDA Gemini Intelligence.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; font-size: 0.78rem; color: var(--text-dim);">
            <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $isSaturday ? 'var(--danger)' : '#10B981' ?>; display: inline-block;"></span>
            <?= $isSaturday ? 'Offline – Rest Day' : 'Online & Ready' ?>
        </div>
    </header>

    <div class="mentor-page">
        <!-- ── Left: Chat Interface ── -->
        <div class="chat-container">
            <!-- Header -->
            <div class="chat-header">
                <div class="mentor-avatar <?= $isSaturday ? 'offline' : '' ?>">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chat-header-info">
                    <div class="chat-header-name">SDA Official Mentor</div>
                    <div class="chat-header-status">
                        <span class="status-dot <?= $isSaturday ? 'offline' : '' ?>"></span>
                        <?php if ($isSaturday): ?>
                            Offline – Back tomorrow
                        <?php else: ?>
                            Online &amp; Analysing Your Progress
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button class="chat-action-btn" title="Clear conversation" onclick="clearChat()"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatBox">
                <!-- Welcome message -->
                <div class="msg-row">
                    <div class="msg-avatar ai"><i class="fas fa-robot"></i></div>
                    <div>
                        <div class="msg-bubble mentor-msg">
                            <?php if ($isSaturday): ?>
                                Hello <strong><?= htmlspecialchars($user['name'] ?? 'Student') ?></strong>! 🌙<br><br>
                                The SDA Academic Mentor takes Saturdays off for system maintenance. We'll be back online tomorrow — Sunday — fully refreshed and ready to assist you.<br><br>
                                In the meantime, keep reviewing your notes! 📚
                            <?php else: ?>
                                Hello <strong><?= htmlspecialchars($user['name'] ?? 'Student') ?></strong>! 👋 I'm your AI Academic Mentor.<br><br>
                                <?php if ($lastCourse): ?>
                                    I can see you're currently focused on <strong><?= htmlspecialchars($lastCourse['title']) ?></strong><?= $lastCourse['category'] ? ' (' . htmlspecialchars($lastCourse['category']) . ')' : '' ?>.<br><br>
                                    <?php if ($performance > 0): ?>
                                        Your current average quiz performance is <strong><?= round($performance) ?>%</strong>. <?= $performance >= 70 ? "That's great progress — let's push further! 🚀" : "Let's work on strengthening those concepts! 💪" ?>
                                    <?php endif; ?>
                                    <br><br>How can I help you master your curriculum today?
                                <?php else: ?>
                                    You haven't enrolled in a course yet. Would you like me to recommend a learning path based on current industry trends in Kenya? 🇰🇪
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <span class="msg-time"><?= date('g:i A') ?></span>
                    </div>
                </div>
            </div>

            <!-- Offline Banner -->
            <?php if ($isSaturday): ?>
            <div class="offline-banner">
                <i class="fas fa-moon"></i>
                <div>
                    <strong>Mentor is Offline Today</strong>
                    <span>Saturday is our rest day. We return Sunday at 6:00 AM EAT.</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Input Area -->
            <div class="chat-input-area">
                <?php if (!$isSaturday): ?>
                <div class="chat-input-wrap">
                    <textarea id="userInput" rows="1" placeholder="Ask about your course, a topic, or career advice..."></textarea>
                    <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="quick-prompts" id="quickPrompts">
                    <button class="quick-prompt-btn" onclick="usePrompt(this)">📚 Explain my current topic</button>
                    <button class="quick-prompt-btn" onclick="usePrompt(this)">🎯 Give me a study plan</button>
                    <button class="quick-prompt-btn" onclick="usePrompt(this)">💼 Career advice in Kenya</button>
                    <button class="quick-prompt-btn" onclick="usePrompt(this)">🧪 Quiz me on my course</button>
                </div>
                <?php else: ?>
                <div style="text-align: center; color: var(--danger); padding: 8px; font-size: 0.88rem; font-weight: 600;">
                    <i class="fas fa-lock"></i> Chat is locked on Saturdays. Please return tomorrow.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Right: Info Sidebar ── -->
        <div class="mentor-sidebar">
            <!-- Academic Stats -->
            <div class="info-card">
                <div class="info-card-title"><i class="fas fa-chart-pie"></i> Your Progress Snapshot</div>
                <div class="stats-row">
                    <div class="stat-chip">
                        <div class="stat-chip-val"><?= $totalEnrolled ?></div>
                        <div class="stat-chip-lbl">Courses</div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-val"><?= $totalQuizzes ?></div>
                        <div class="stat-chip-lbl">Quizzes</div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-val"><?= $performance > 0 ? round($performance) . '%' : '–' ?></div>
                        <div class="stat-chip-lbl">Avg Score</div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-val"><?= $user['points'] ?? 0 ?></div>
                        <div class="stat-chip-lbl">Points</div>
                    </div>
                </div>

                <?php if ($lastCourse): ?>
                <div style="margin-top: 16px;">
                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 8px;">Currently Studying</div>
                    <div class="current-course-box">
                        <div class="course-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <div class="course-name"><?= htmlspecialchars($lastCourse['title']) ?></div>
                            <?php if ($lastCourse['category']): ?>
                            <div class="course-cat"><?= htmlspecialchars($lastCourse['category']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Suggested Questions -->
            <div class="info-card">
                <div class="info-card-title"><i class="fas fa-lightbulb"></i> Ask the Mentor</div>
                <div class="suggestion-list">
                    <button class="suggestion-item" onclick="usePromptText('How do I improve my quiz performance?')">
                        <i class="fas fa-chart-line"></i>
                        <span>How do I improve my quiz performance?</span>
                    </button>
                    <button class="suggestion-item" onclick="usePromptText('What tech skills are in demand in Kenya?')">
                        <i class="fas fa-briefcase"></i>
                        <span>What tech skills are in demand in Kenya?</span>
                    </button>
                    <button class="suggestion-item" onclick="usePromptText('Create a 4-week study schedule for me')">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Create a 4-week study schedule for me</span>
                    </button>
                    <button class="suggestion-item" onclick="usePromptText('Explain the Pomodoro study technique')">
                        <i class="fas fa-clock"></i>
                        <span>Explain the Pomodoro study technique</span>
                    </button>
                    <button class="suggestion-item" onclick="usePromptText('How do I build a portfolio for a job application?')">
                        <i class="fas fa-folder"></i>
                        <span>How do I build a portfolio for a job application?</span>
                    </button>
                </div>
            </div>

            <!-- Tips Banner -->
            <div class="banner-card">
                <h4><i class="fas fa-robot"></i> About SDA Mentor</h4>
                <p>Powered by Google Gemini. I'm trained to understand your academic context and provide Kenya-focused career guidance. I'm available Sunday–Friday.</p>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
const chatBox  = document.getElementById('chatBox');
const userInput = document.getElementById('userInput');
const sendBtn  = document.getElementById('sendBtn');
const quickPrompts = document.getElementById('quickPrompts');

// Auto-resize textarea
if (userInput) {
    userInput.addEventListener('input', () => {
        userInput.style.height = 'auto';
        userInput.style.height = Math.min(userInput.scrollHeight, 120) + 'px';
    });
    userInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
}

function usePrompt(btn) {
    if (!userInput) return;
    // Strip emoji prefix for actual prompt
    userInput.value = btn.textContent.replace(/^[^\w]+/, '').trim();
    userInput.focus();
    sendMessage();
}

function usePromptText(text) {
    if (!userInput) return;
    userInput.value = text;
    userInput.focus();
    sendMessage();
}

function clearChat() {
    const msgs = chatBox.querySelectorAll('.msg-row');
    if (msgs.length <= 1) return;
    // Keep only first (welcome) message
    while (chatBox.children.length > 1) chatBox.removeChild(chatBox.lastChild);
}

function sendMessage() {
    if (!userInput) return;
    const text = userInput.value.trim();
    if (!text) return;

    appendMessage(text, 'student');
    userInput.value = '';
    userInput.style.height = 'auto';

    // Hide quick prompts after first message
    if (quickPrompts) quickPrompts.style.display = 'none';

    // Disable input while waiting
    sendBtn.disabled = true;

    // Show typing indicator
    const typingRow = createTypingIndicator();
    chatBox.appendChild(typingRow);
    scrollToBottom();

    // AI Context
    const context = "Current Course: <?= addslashes($lastCourse['title'] ?? 'None') ?>. Avg Quiz Score: <?= round($performance) ?>%. Enrolled Courses: <?= $totalEnrolled ?>. Academy Focus: Kenya/East Africa digital skills.";

    const formData = new FormData();
    formData.append('action', 'mentor_chat');
    formData.append('query', text);
    formData.append('context', context);

    fetch('../includes/ai_controller.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            chatBox.removeChild(typingRow);
            if (data.success) {
                appendMessage(data.response, 'mentor');
            } else {
                appendMessage("⚠️ I'm having trouble connecting right now. Please check your connection or try again in a moment.\n\n_Error: " + (data.error || 'Unknown') + "_", 'mentor');
            }
        })
        .catch(() => {
            chatBox.removeChild(typingRow);
            appendMessage("⚠️ Connection error. Please check your internet and try again.", 'mentor');
        })
        .finally(() => {
            sendBtn.disabled = false;
            userInput.focus();
        });
}

function createTypingIndicator() {
    const row = document.createElement('div');
    row.className = 'msg-row';
    row.innerHTML = `
        <div class="msg-avatar ai"><i class="fas fa-robot"></i></div>
        <div>
            <div class="typing-bubble show">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>`;
    return row;
}

function appendMessage(text, role) {
    const now = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    const row = document.createElement('div');
    row.className = `msg-row${role === 'student' ? ' student-row' : ''}`;

    const avatarHtml = role === 'mentor'
        ? `<div class="msg-avatar ai"><i class="fas fa-robot"></i></div>`
        : `<div class="msg-avatar you">${'<?= strtoupper(substr($user["name"], 0, 1)) ?>'}</div>`;

    const bubbleClass = role === 'mentor' ? 'mentor-msg' : 'student-msg';
    const content = role === 'mentor' ? renderMarkdown(text) : escapeHtml(text).replace(/\n/g, '<br>');

    row.innerHTML = `
        ${avatarHtml}
        <div>
            <div class="msg-bubble ${bubbleClass}">${content}</div>
            <span class="msg-time">${now}</span>
        </div>`;

    chatBox.appendChild(row);
    scrollToBottom();
}

function scrollToBottom() {
    chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
}

function escapeHtml(text) {
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Lightweight markdown renderer for mentor messages
function renderMarkdown(md) {
    let html = escapeHtml(md);

    // Code blocks (must be done before inline code)
    html = html.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

    // Inline code
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Bold & italic
    html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/_(.+?)_/g, '<em>$1</em>');

    // Headings
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

    // Unordered list items
    html = html.replace(/^\s*[\-\*] (.+)$/gm, '<li>$1</li>');

    // Numbered list items
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

    // Wrap consecutive <li>s in <ul>
    html = html.replace(/(<li>[\s\S]+?<\/li>(\n|$))+/g, (m) => '<ul>' + m + '</ul>');

    // Paragraphs (double newline)
    html = html.split(/\n{2,}/).map(block => {
        if (/^<(h[1-3]|ul|ol|pre|li)/.test(block.trim())) return block;
        return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
    }).join('');

    // Single newlines not in tags → br
    html = html.replace(/(?<!>)\n(?!<)/g, '<br>');

    return html;
}
</script>
</body>
</html>
