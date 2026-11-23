<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: courses');
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
    
    if (!$course) { header('Location: courses'); exit; }

    // 2. Fetch Lessons (Curriculum)
    $stmt = $pdo->prepare("SELECT id, title, duration_mins, week_num, is_preview, code_example IS NOT NULL AND code_example <> '' AS has_code FROM lessons WHERE course_id = ? ORDER BY week_num ASC, order_num ASC, id ASC");
    $stmt->execute([$id]);
    $lessons = $stmt->fetchAll();

    // 3. Related Courses
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE category_id = ? AND id != ? AND status = 'published' LIMIT 3");
    $stmt->execute([$course['category_id'], $id]);
    $related = $stmt->fetchAll();

} catch (Exception $e) { header('Location: courses'); exit; }

$user = isLoggedIn() ? currentUser() : null;
$tutor_avatar_url = (!empty($course['tutor_avatar']) && is_file(__DIR__ . '/uploads/avatars/' . basename($course['tutor_avatar'])))
    ? 'uploads/avatars/' . basename($course['tutor_avatar']) : 'assets/images/user-placeholder.png';
$is_enrolled = ($user && $id) ? $pdo->query("SELECT 1 FROM enrollments WHERE student_id = ".$user['id']." AND course_id = $id AND status != 'cancelled'")->fetchColumn() : false;

// 4. Global Visibility Guard
$is_admin = ($user && $user['role'] === 'admin');
$is_tutor = ($user && $user['id'] == $course['tutor_id']);

if ($course['status'] === 'archived' && !$is_enrolled && !$is_admin && !$is_tutor) {
    header('Location: courses?error=archived');
    exit;
}

if ($course['status'] !== 'published' && !$is_admin && !$is_tutor && !$is_enrolled) {
    header('Location: courses?error=unavailable');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => $course['title'] . ' – Online Course', 'path' => 'course-details?id=' . (int)$course['id'], 'image' => (!empty($course['thumbnail']) && is_file(__DIR__ . '/uploads/courses/' . basename($course['thumbnail']))) ? 'uploads/courses/' . basename($course['thumbnail']) : '', 'description' => trim(strip_tags((string)($course['description'] ?? ''))) ?: ('Learn ' . $course['title'] . ' online with Skope Digital Academy and earn a certificate.'), 'breadcrumbs' => [['Courses', 'courses'], [$course['title'], 'course-details?id=' . (int)$course['id']]], 'schema' => [sda_seo_course($course, sda_seo_abs('course-details?id=' . (int)$course['id']))]]); ?>
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
        .course-vignette:has(img)::after { display: none; }   /* designed thumbnails carry their own shading */

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
            .purchase-sticky { position: static; margin-top: 40px; padding: 32px; }
            .hero-grid-res { grid-template-columns: 1fr !important; }
            .main-content-grid { grid-template-columns: 1fr !important; }
            .course-vignette { max-width: 500px; margin: 0 auto; }
            .instructor-card { flex-direction: column; text-align: center; align-items: center; }
        }
        @media (max-width: 600px) {
            .lesson-row { padding: 16px 18px; gap: 12px; }
            /* number | title, with the Preview pill and code icon tucked under the title */
            .lesson-row > div:first-child { display: grid !important; grid-template-columns: 24px minmax(0, 1fr); align-items: center; column-gap: 12px !important; row-gap: 6px !important; flex: 1; min-width: 0; }
            .lesson-row > div:first-child > :nth-child(n+3) { grid-column: 2; justify-self: start; }
            .lesson-row > div:first-child > :nth-child(2) { font-size: .98rem !important; line-height: 1.35; }
            .lesson-row > div:last-child { flex-shrink: 0; gap: 6px !important; }
            .lesson-row:hover { transform: none; }
            .course-main-column h2 { font-size: 1.6rem !important; }
        }
        @media (min-width: 993px) {
            .hero-grid-res { grid-template-columns: 1.4fr 1fr !important; gap: 80px !important; }
            .main-content-grid { grid-template-columns: 1.6fr 1fr !important; gap: 80px !important; }
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
            <div class="hero-grid-res" style="display: grid; gap: 40px; align-items: center;">
                <div>
                    <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 24px; color: var(--primary); font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px;">
                        <i class="fas fa-layer-group"></i>
                        <span><?= htmlspecialchars($course['category_name']) ?></span>
                        <span style="opacity: 0.3;">/</span>
                        <span style="color: #fff; opacity: 0.6;">Curriculum Details</span>
                    </div>
                    <h1 style="color: #fff; font-size: clamp(2.2rem, 5vw, 3.8rem); line-height: 1.1; margin-bottom: 32px; font-family: 'Poppins', sans-serif;"><?= htmlspecialchars($course['title']) ?></h1>
                    
                    <div class="hero-meta-row" style="display: flex; gap: 24px; margin-bottom: 40px; flex-wrap: wrap;">
                        <?php if ((float)$course['avg_rating'] > 0): ?>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; background: rgba(255,140,0,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--secondary);">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <div style="color: #fff; font-weight: 800; font-size: 1.1rem;"><?= number_format((float)$course['avg_rating'], 1) ?></div>
                                <div style="color: rgba(255,255,255,0.5); font-size: 0.75rem; text-transform: uppercase;">Rating</div>
                            </div>
                        </div>
                        <?php endif; ?>
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
                        <img src="<?= e($tutor_avatar_url) ?>" alt="<?= e($course['tutor_name']) ?>" onerror="this.src='assets/images/user-placeholder.png'" style="width: 52px; height: 52px; border-radius: 50%; border: 2px solid var(--primary);">
                        <div>
                            <div style="color: #fff; font-weight: 700; font-size: 1rem;"><?= htmlspecialchars($course['tutor_name']) ?></div>
                            <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">Lead Mentor & Curriculum Architect</div>
                        </div>
                    </div>
                </div>

                <div class="course-vignette">
                    <?php if (!empty($course['thumbnail']) && is_file(__DIR__ . '/uploads/courses/' . basename($course['thumbnail']))): ?>
                    <img src="uploads/courses/<?= e(basename($course['thumbnail'])) ?>" alt="<?= e($course['title']) ?>">
                    <?php else: ?>
                    <div style="width:100%;min-width:260px;aspect-ratio:16/9;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:linear-gradient(135deg,rgba(0,191,255,.18),rgba(255,140,0,.12));border-radius:24px;color:#fff;">
                        <i class="fas fa-laptop-code" style="font-size:3.2rem;color:#00BFFF;"></i>
                        <span style="font-weight:800;letter-spacing:1px;text-transform:uppercase;font-size:.8rem;color:rgba(255,255,255,.7)"><?= e($course['category_name'] ?? 'Course') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Grid -->
    <div class="container" style="padding-top: 60px; padding-bottom: 60px;">
        <div class="main-content-grid" style="display: grid; gap: 40px;">
            
            <div class="course-main-column">
                <section style="margin-bottom: 80px;">
                    <?php $outcomes = !empty($course['outcomes']) ? (json_decode($course['outcomes'], true) ?: []) : []; ?>
                    <?php if ($outcomes): ?>
                    <div style="border:1px solid var(--dark-border);border-radius:20px;padding:26px 28px;margin-bottom:40px;background:#fcfdff;">
                        <h2 style="font-family:'Poppins',sans-serif;font-size:1.5rem;margin:0 0 16px;">What you'll <span class="text-primary">learn</span></h2>
                        <ul style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px 24px;">
                            <?php foreach ($outcomes as $o): ?><li style="display:flex;gap:10px;align-items:flex-start;color:var(--text-muted);line-height:1.55;"><i class="fas fa-check" style="color:#10b981;margin-top:5px;"></i><span><?= e($o) ?></span></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <h2 style="font-family: 'Poppins', sans-serif; font-size: 2rem; margin-bottom: 32px;">The <span class="text-primary">Experience</span></h2>
                    <div style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.8; letter-spacing: -0.2px;">
                        <?= nl2br(htmlspecialchars($course['description'])) ?>
                    </div>
                </section>

                <section style="margin-bottom: 80px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 32px;">
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
                                <?php if ($course['is_open_tutorial'] || $lsn['is_preview']): ?>
                                    <a href="learn?course=<?= $id ?>&lesson=<?= (int)$lsn['id'] ?>" style="font-size:.72rem;font-weight:800;color:#075e40;background:#e7f7f0;padding:3px 10px;border-radius:99px;text-decoration:none;white-space:nowrap;"><?= $course['is_open_tutorial'] ? 'Read free' : 'Preview' ?></a>
                                <?php endif; ?>
                                <?php if ($lsn['has_code']): ?><span title="Includes a Try-it code example" style="color:var(--text-dim);font-size:.8rem"><i class="fas fa-code"></i></span><?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px; color: var(--text-dim); font-size: 0.85rem;">
                                <i class="far fa-play-circle"></i>
                                <span><?= $lsn['duration_mins'] ?>m</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php
                  $rv = $pdo->prepare("SELECT r.rating, r.review, r.created_at, u.name FROM course_ratings r JOIN users u ON u.id = r.student_id WHERE r.course_id = ? ORDER BY (r.review IS NOT NULL) DESC, r.created_at DESC LIMIT 6");
                  $rv->execute([$id]);
                  $reviews = $rv->fetchAll();
                  $rs = $pdo->prepare("SELECT ROUND(AVG(rating), 1) a, COUNT(*) n, SUM(rating = 5) s5, SUM(rating = 4) s4, SUM(rating = 3) s3, SUM(rating = 2) s2, SUM(rating = 1) s1 FROM course_ratings WHERE course_id = ?");
                  $rs->execute([$id]);
                  $rs = $rs->fetch();
                ?>
                <?php if ((int)$rs['n'] > 0): ?>
                <section style="margin-bottom: 64px;" id="reviews">
                    <h2 style="font-family: 'Poppins', sans-serif; font-size: 2rem; margin-bottom: 24px;">Learner <span class="text-primary">Reviews</span></h2>
                    <div style="display:grid;grid-template-columns:minmax(150px,200px) 1fr;gap:28px;align-items:center;margin-bottom:28px;">
                        <div style="text-align:center;">
                            <div style="font:800 3.2rem 'Poppins',sans-serif;color:#b45309;line-height:1;"><?= e($rs['a']) ?></div>
                            <div style="color:#f59e0b;margin:6px 0;"><?php for ($i = 1; $i <= 5; $i++) echo '<i class="' . ($i <= round((float)$rs['a']) ? 'fas' : 'far') . ' fa-star"></i>'; ?></div>
                            <div style="color:var(--text-dim);font-size:.85rem;"><?= (int)$rs['n'] ?> rating<?= (int)$rs['n'] === 1 ? '' : 's' ?></div>
                        </div>
                        <div>
                            <?php for ($i = 5; $i >= 1; $i--): $c = (int)$rs['s' . $i]; $w = (int)round($c / max(1, (int)$rs['n']) * 100); ?>
                                <div style="display:flex;align-items:center;gap:10px;font-size:.85rem;color:var(--text-dim);margin:4px 0;">
                                    <span style="width:44px;"><?= $i ?> <i class="fas fa-star" style="color:#f59e0b;font-size:.75rem;"></i></span>
                                    <span style="flex:1;height:8px;border-radius:8px;background:#e2e8f0;overflow:hidden;"><span style="display:block;height:100%;width:<?= $w ?>%;background:#f59e0b;"></span></span>
                                    <span style="width:36px;text-align:right;"><?= $w ?>%</span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div style="display:grid;gap:14px;">
                        <?php foreach ($reviews as $r): if ($r['review'] === null) continue; $parts = preg_split('/\s+/', trim($r['name'])); $nm = $parts[0] . (count($parts) > 1 ? ' ' . mb_substr(end($parts), 0, 1) . '.' : ''); ?>
                            <div style="border:1px solid var(--dark-border);border-radius:16px;padding:18px 20px;">
                                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                                    <span style="width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#0080ff,#00bfff);color:#fff;font-weight:800;"><?= e(mb_strtoupper(mb_substr($nm, 0, 1))) ?></span>
                                    <div><strong style="color:var(--text-main);"><?= e($nm) ?></strong><div style="color:#f59e0b;font-size:.8rem;"><?php for ($i = 1; $i <= 5; $i++) echo '<i class="' . ($i <= (int)$r['rating'] ? 'fas' : 'far') . ' fa-star"></i>'; ?> <span style="color:var(--text-dim);margin-left:6px;"><?= date('M Y', strtotime($r['created_at'])) ?></span></div></div>
                                </div>
                                <p style="margin:0;color:var(--text-muted);line-height:1.7;"><?= nl2br(e($r['review'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <div class="instructor-card">
                    <img src="<?= e($tutor_avatar_url) ?>" alt="<?= e($course['tutor_name']) ?>" onerror="this.src='assets/images/user-placeholder.png'" style="width: 100px; height: 100px; border-radius: 32px; object-fit: cover;">
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
                        <?php $from = course_from_price($course); ?>
                        <div style="font-size: 3rem; font-weight: 900; color: var(--text-primary); line-height: 1;"><?= $from > 0 ? e(money($from)) : 'Free' ?></div>
                        <?php if (sale_active($course)): ?><div style="margin-top:8px;color:var(--text-dim)"><s><?= e(money((float)$course['price'])) ?></s> self-paced sale<?= $course['sale_ends_at'] ? ' until ' . date('M j', strtotime($course['sale_ends_at'])) : '' ?></div><?php endif; ?>
                    </div>

                    <div style="display:grid;gap:10px;margin-bottom:28px;">
                        <?php foreach (course_modes($course) as $m): ?>
                        <div style="border:1px solid var(--dark-border);border-radius:14px;padding:14px 16px;text-align:left;">
                            <div style="display:flex;justify-content:space-between;gap:10px;font-weight:800;color:var(--text-main);">
                                <span><i class="fas <?= $m === 'structured' ? 'fa-calendar-week' : 'fa-person-walking' ?>" style="color:var(--primary);margin-right:6px"></i><?= e(mode_label($m)) ?></span>
                                <span><?= course_price($course, $m) > 0 ? e(money(course_price($course, $m))) : 'Free' ?></span>
                            </div>
                            <div style="font-size:.82rem;color:var(--text-muted);margin-top:6px;line-height:1.5"><?= e(mode_blurb($m)) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 40px;">
                        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--text-muted);">
                            <div style="width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-infinity fa-sm"></i></div>
                            <span><?php $days = (int)$course['access_days'] ?: (int)setting('default_access_days'); echo $days > 0 ? $days . ' days of access' : 'Lifetime access'; ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--text-muted);">
                            <div style="width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-certificate fa-sm"></i></div>
                            <?php $certFee = certificate_fee_for($course); ?>
                            <span><?= $certFee > 0 ? 'Certificate on completion · ' . e(money($certFee)) . ' to unlock' : 'Free certificate on completion' ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--text-muted);">
                            <div style="width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-brain fa-sm"></i></div>
                            <span>Direct AI-Driven Mentorship</span>
                        </div>
                    </div>

                    <?php if($is_enrolled): ?>
                        <a href="student/classroom?id=<?= $id ?>" class="btn btn-secondary btn-block btn-lg" style="height: 72px; font-size: 1.1rem; border-radius: 20px; background: var(--secondary); border: none;">Go to Classroom <i class="fas fa-play-circle" style="margin-left: 12px; font-size: 0.9rem;"></i></a>
                    <?php else: ?>
                        <?php $isFreeCourse = course_from_price($course) <= 0; ?>
                        <?php if (!$user && $isFreeCourse): ?>
                            <a href="register" class="btn btn-primary btn-block btn-lg" style="height: 72px; font-size: 1.1rem; border-radius: 20px;">Register free &amp; start learning <i class="fas fa-arrow-right" style="margin-left: 12px; font-size: 0.9rem;"></i></a>
                            <p style="text-align:center;margin:10px 0 0;font-size:.85rem;color:var(--text-dim);">Already have an account? <a href="login">Sign in</a> to enroll.</p>
                        <?php else: ?>
                            <a href="enroll?id=<?= $id ?>" class="btn btn-primary btn-block btn-lg" style="height: 72px; font-size: 1.1rem; border-radius: 20px;"><?= $isFreeCourse ? 'Start learning free' : 'Choose your track' ?> <i class="fas fa-arrow-right" style="margin-left: 12px; font-size: 0.9rem;"></i></a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php $readable = $course['is_open_tutorial'] || array_filter($lessons, fn($l) => $l['is_preview']); ?>
                    <?php if ($readable): ?>
                        <a href="learn?course=<?= $id ?>" class="btn btn-ghost btn-block" style="margin-top:12px;border-radius:16px;"><i class="fas fa-book-open"></i> <?= $course['is_open_tutorial'] ? 'Read the tutorial free' : 'Read free preview lessons' ?></a>
                    <?php endif; ?>
                    
                    <div style="margin-top: 40px; text-align: center; padding-top: 32px; border-top: 1px solid var(--bg-light);">
                        <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 24px;">Need financial assistance? We offer merit scholarships for eligible candidates.</p>
                        <a href="scholarships" style="font-weight: 800; color: var(--secondary); font-size: 0.9rem; text-decoration: underline;">Inquire About Scholarships</a>
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
