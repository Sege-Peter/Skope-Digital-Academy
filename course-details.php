<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: courses.php');
    exit;
}

try {
    // 1. Fetch Course Info
    $stmt = $pdo->prepare("SELECT c.*, u.name AS tutor_name, u.avatar AS tutor_avatar, u.bio AS tutor_bio, cat.name AS category_name 
                           FROM courses c 
                           JOIN users u ON c.tutor_id = u.id 
                           LEFT JOIN categories cat ON c.category_id = cat.id 
                           WHERE c.id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    
    if (!$course) { header('Location: courses.php'); exit; }

    // 2. Fetch Lessons (Curriculum)
    $stmt = $pdo->prepare("SELECT title, duration_mins FROM lessons WHERE course_id = ? ORDER BY order_num ASC");
    $stmt->execute([$id]);
    $lessons = $stmt->fetchAll();

    // 3. Related Courses
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE category_id = ? AND id != ? LIMIT 3");
    $stmt->execute([$course['category_id'], $id]);
    $related = $stmt->fetchAll();

} catch (Exception $e) { header('Location: courses.php'); exit; }

$user = isLoggedIn() ? currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> – Skope Digital Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .course-page-hero { 
            background: linear-gradient(135deg, #0d1117 0%, #001e3c 100%); 
            padding: 140px 0 100px; 
            position: relative; 
            overflow: hidden; 
        }
        .hero-backdrop {
            position: absolute;
            top: -20%; right: -10%;
            width: 60%; height: 140%;
            background: radial-gradient(circle, rgba(0, 191, 255, 0.12) 0%, transparent 70%);
            filter: blur(120px);
            z-index: 0;
            transform: rotate(-15deg);
        }
        
        .course-vignette {
            position: relative;
            z-index: 2;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .course-vignette img { width: 100%; display: block; }
        .course-vignette::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(13, 17, 23, 0.8), transparent 40%);
        }

        .purchase-sticky {
            background: #fff;
            border: 1px solid var(--dark-border);
            border-radius: 32px;
            padding: 48px;
            position: sticky;
            top: 110px;
            box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.12);
            border-top: 6px solid var(--primary);
        }

        .curriculum-accordion { display: flex; flex-direction: column; gap: 12px; }
        .lesson-row {
            padding: 24px 32px;
            background: #fff;
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
        }
        .lesson-row:hover {
            transform: translateX(12px);
            border-color: var(--primary);
            background: var(--primary-glow);
        }

        .instructor-card {
            background: var(--bg-light);
            border-radius: 24px;
            padding: 32px;
            display: flex;
            gap: 24px;
            align-items: flex-start;
            margin-top: 64px;
        }
        
        @media (max-width: 992px) {
            .course-page-hero { padding: 100px 0 60px; text-align: center; }
            .hero-meta-row { justify-content: center; }
            .purchase-sticky { position: static; margin-top: 40px; }
        }
    </style>
</head>
<body style="background: #fff;">

<?php require_once 'includes/nav.php'; ?>

<main>
    <!-- Cinematic Hero -->
    <header class="course-page-hero">
        <div class="hero-backdrop"></div>
        <div class="container relative" style="z-index: 2;">
            <div class="grid-2" style="grid-template-columns: 1.4fr 1fr; align-items: center; gap: 80px;">
                <div>
                    <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 24px; color: var(--primary); font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px;">
                        <i class="fas fa-layer-group"></i>
                        <span><?= htmlspecialchars($course['category_name']) ?></span>
                        <span style="opacity: 0.3;">/</span>
                        <span style="color: #fff; opacity: 0.6;">Curriculum Details</span>
                    </div>
                    <h1 style="color: #fff; font-size: clamp(2.2rem, 5vw, 3.8rem); line-height: 1.1; margin-bottom: 32px; font-family: 'Poppins', sans-serif;"><?= htmlspecialchars($course['title']) ?></h1>
                    
                    <div class="hero-meta-row" style="display: flex; gap: 48px; margin-bottom: 48px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; background: rgba(255,140,0,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--secondary);">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <div style="color: #fff; font-weight: 800; font-size: 1.1rem;">4.9</div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.75rem; text-transform: uppercase;">Rating</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; background: rgba(0,191,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div style="color: #fff; font-weight: 800; font-size: 1.1rem;"><?= $course['duration_hours'] ?>h</div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.75rem; text-transform: uppercase;">Content</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; background: rgba(16,185,129,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--success);">
                                <i class="fas fa-signal"></i>
                            </div>
                            <div>
                                <div style="color: #fff; font-weight: 800; font-size: 1.1rem;"><?= ucfirst($course['level']) ?></div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.75rem; text-transform: uppercase;">Level</div>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px;">
                        <img src="uploads/avatars/<?= $course['tutor_avatar'] ?: 'default.png' ?>" style="width: 52px; height: 52px; border-radius: 50%; border: 2px solid var(--primary);">
                        <div>
                            <div style="color: #fff; font-weight: 700; font-size: 1rem;"><?= htmlspecialchars($course['tutor_name']) ?></div>
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Lead Mentor & Curriculum Architect</div>
                        </div>
                    </div>
                </div>

                <div class="course-vignette">
                    <img src="uploads/courses/<?= $course['thumbnail'] ?>" alt="Course Graphics">
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Grid -->
    <div class="container" style="padding: 100px 0;">
        <div class="grid-2" style="grid-template-columns: 1.6fr 1fr; gap: 80px;">
            
            <div class="course-main-column">
                <section style="margin-bottom: 80px;">
                    <h2 style="font-family: 'Poppins', sans-serif; font-size: 2rem; margin-bottom: 32px;">The <span class="text-primary">Experience</span></h2>
                    <div style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.8; letter-spacing: -0.2px;">
                        <?= nl2br(htmlspecialchars($course['description'])) ?>
                    </div>
                </section>

                <section style="margin-bottom: 80px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
                        <div>
                            <h2 style="font-family: 'Poppins', sans-serif; font-size: 2rem; margin-bottom: 8px;">Learning <span class="text-secondary">Path</span></h2>
                            <p style="color: var(--text-dim);">Detailed module-by-module curriculum breakdown.</p>
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 800; color: var(--primary); text-transform: uppercase;"><?= count($lessons) ?> Modules</div>
                    </div>

                    <div class="curriculum-accordion">
                        <?php foreach($lessons as $idx => $lsn): ?>
                        <div class="lesson-row">
                            <div style="display: flex; align-items: center; gap: 24px;">
                                <div style="font-size: 0.8rem; font-weight: 900; color: var(--text-dim); width: 24px;"><?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></div>
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 1.05rem;"><?= htmlspecialchars($lsn['title']) ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px; color: var(--text-dim); font-size: 0.85rem;">
                                <i class="far fa-play-circle"></i>
                                <span><?= $lsn['duration_mins'] ?>m</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="instructor-card">
                    <img src="uploads/avatars/<?= $course['tutor_avatar'] ?: 'default.png' ?>" style="width: 100px; height: 100px; border-radius: 32px; object-fit: cover;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px;">Your Instructor</div>
                        <h3 style="font-size: 1.6rem; margin-bottom: 12px;"><?= htmlspecialchars($course['tutor_name']) ?></h3>
                        <p style="color: var(--text-muted); line-height: 1.7; font-size: 0.95rem;"><?= htmlspecialchars($course['tutor_bio']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Enrollment Sidebar -->
            <div class="course-sidebar-column">
                <aside class="purchase-sticky">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h4 style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px;">Enrollment Access</h4>
                        <div style="font-size: 3.5rem; font-weight: 900; color: var(--text-primary); line-height: 1;">KES <?= number_format($course['price']) ?></div>
                        <div style="margin-top: 12px; display: inline-flex; align-items: center; gap: 8px; color: var(--success); font-weight: 700; font-size: 0.85rem; background: rgba(16,185,129,0.1); padding: 6px 16px; border-radius: 99px;">
                            <i class="fas fa-shield-check"></i> Authentic Mentorship
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 40px;">
                        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--text-muted);">
                            <div style="width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-infinity fa-sm"></i></div>
                            <span>Full Lifetime Repository Access</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--text-muted);">
                            <div style="width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-file-certificate fa-sm"></i></div>
                            <span>Professional Merit Certification</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--text-muted);">
                            <div style="width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-brain fa-sm"></i></div>
                            <span>Direct AI-Driven Mentorship</span>
                        </div>
                    </div>

                    <a href="enroll.php?id=<?= $id ?>" class="btn btn-primary btn-block btn-lg" style="height: 72px; font-size: 1.1rem; border-radius: 20px;">Secure Spot Now <i class="fas fa-arrow-right" style="margin-left: 12px; font-size: 0.9rem;"></i></a>
                    
                    <div style="margin-top: 40px; text-align: center; padding-top: 32px; border-top: 1px solid var(--bg-light);">
                        <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 24px;">Need financial assistance? We offer merit scholarships for eligible candidates.</p>
                        <a href="scholarships.php" style="font-weight: 800; color: var(--secondary); font-size: 0.9rem; text-decoration: underline;">Inquire About Scholarships</a>
                    </div>
                </aside>
            </div>

        </div>
    </div>
</main>

<script src="assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('msg') === 'not_enrolled') {
            SDA.showToast('You must be enrolled to access the classroom.', 'warning');
        }
    });
</script>
</body>
</html>
