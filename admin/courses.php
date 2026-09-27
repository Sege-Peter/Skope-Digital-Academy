<?php
$pageTitle = 'Course Repository Management';
require_once '../includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c_id = $_POST['id'] ?? null;
    try {
        if (isset($_POST['approve_course'])) {
            $stmt = $pdo->prepare("UPDATE courses SET status = 'published' WHERE id = ?");
            $stmt->execute([$c_id]);
            $success_msg = "Academic track approved and published globally.";
        } elseif (isset($_POST['decline_course'])) {
            $stmt = $pdo->prepare("UPDATE courses SET status = 'draft' WHERE id = ?");
            $stmt->execute([$c_id]);
            $success_msg = "Track declined and sent back for scholarly revisions.";
        } elseif (isset($_POST['archive_course'])) {
            $stmt = $pdo->prepare("UPDATE courses SET status = 'archived' WHERE id = ?");
            $stmt->execute([$c_id]);
            $success_msg = "Track archived and removed from the global repository.";
        } elseif (isset($_POST['save_course'])) {
            $title = trim($_POST['title']);
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $desc = trim($_POST['description']);
            $price = (float)$_POST['price'];
            $level = $_POST['level'];
            $cat_id = (int)$_POST['category_id'];
            $tutor_id = (int)$_POST['tutor_id'];
            $status = $_POST['status'] ?? 'draft';

            // Handle Thumbnail Upload
            $thumb = null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
                $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $thumb = "course_" . time() . "_" . rand(100, 999) . "." . $ext;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], "../uploads/courses/" . $thumb);
            }

            if ($c_id) {
                // UPDATE
                $sql = "UPDATE courses SET title=?, slug=?, description=?, price=?, level=?, category_id=?, tutor_id=?, status=?";
                $params = [$title, $slug, $desc, $price, $level, $cat_id, $tutor_id, $status];
                if ($thumb) {
                    $sql .= ", thumbnail=?";
                    $params[] = $thumb;
                }
                $sql .= " WHERE id=?";
                $params[] = $c_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success_msg = "Academic track synchronized successfully!";
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO courses (title, slug, description, price, level, category_id, tutor_id, status, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $desc, $price, $level, $cat_id, $tutor_id, $status, $thumb]);
                $success_msg = "New academic track launched successfully!";
            }
        }
    } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
}

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Global Course Query
    $query = "SELECT c.*, u.name as tutor_name, cat.name as category_name,
                     (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as actual_enrolled,
                     (SELECT SUM(amount) FROM payments WHERE course_id = c.id AND status = 'verified') as revenue
              FROM courses c
              JOIN users u ON c.tutor_id = u.id
              LEFT JOIN categories cat ON c.category_id = cat.id
              WHERE 1=1";
    
    $params = [];
    if ($status_filter) {
        $query .= " AND c.status = ?";
        $params[] = $status_filter;
    }
    if ($search) {
        $query .= " AND (c.title LIKE ? OR u.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();

    // Stats
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $pending_review = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'pending'")->fetchColumn();
    $all_tutors = $pdo->query("SELECT id, name FROM users WHERE role = 'tutor' OR role = 'admin' ORDER BY name")->fetchAll();
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

} catch (Exception $e) { $courses = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Global <span>Curriculum</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Academic Repository • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <form action="courses.php" method="GET" class="header-search" style="display: flex; gap: 10px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" placeholder="Filter Curriculum..." value="<?= htmlspecialchars($search) ?>" style="padding-right: 48px;">
                <i class="fas fa-search"></i>
            </form>
            <button class="btn-premium" onclick="openCourseModal()">
                <i class="fas fa-plus-circle"></i> Launch New Track
            </button>
        </div>
    </header>

    <div class="admin-body">
        <!-- Curriculum Analytics -->
        <div class="dash-stats-grid">
            <div class="premium-card">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Catalog Range</div>
                <div style="font-size: 2.2rem; font-weight: 900; letter-spacing: -1px;"><?= number_format($total_courses) ?></div>
                <div class="badge-premium" style="background: rgba(16, 185, 129, 0.1); color: var(--success); margin-top: 12px; display: inline-flex;">Active Streams</div>
            </div>
            <div class="premium-card" style="border-left: 4px solid var(--warning);">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Pending Audit</div>
                <div style="font-size: 2.2rem; font-weight: 900; letter-spacing: -1px; color: var(--warning);"><?= number_format($pending_review) ?></div>
                <div style="font-size: 0.65rem; color: var(--text-dim); margin-top: 8px; font-weight: 700;">Requires Quality Review</div>
            </div>
            <div class="premium-card" style="grid-column: span 2;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px;">Instructional Velocity</div>
                        <div style="font-size: 0.65rem; color: var(--text-dim); font-weight: 700;">Enrollment patterns suggest 8.2% growth</div>
                    </div>
                    <i class="fas fa-bolt" style="color: var(--primary); opacity: 0.3;"></i>
                </div>
                <div class="bar-chart-institutional" style="height: 60px; gap: 8px;">
                    <div class="chart-column" style="height: 45%;"></div>
                    <div class="chart-column" style="height: 75%;"></div>
                    <div class="chart-column" style="height: 60%;"></div>
                    <div class="chart-column" style="height: 90%;"></div>
                    <div class="chart-column" style="height: 70%;"></div>
                    <div class="chart-column" style="height: 85%;"></div>
                    <div class="chart-column" style="height: 55%;"></div>
                    <div class="chart-column" style="height: 80%;"></div>
                </div>
            </div>
        </div>

        <!-- Catalog Filtering -->
        <div class="premium-card glass-effect" style="padding: 16px 24px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-right: 12px;">Lifecycle Status:</span>
                <a href="courses.php" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= !$status_filter ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= !$status_filter ? 'white' : '#64748b' ?>;">ALL</a>
                <a href="courses.php?status=published" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'published' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'published' ? 'white' : '#64748b' ?>;">LIVE</a>
                <a href="courses.php?status=pending" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'pending' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'pending' ? 'white' : '#64748b' ?>;">PENDING</a>
                <a href="courses.php?status=draft" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'draft' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'draft' ? 'white' : '#64748b' ?>;">DRAFTS</a>
            </div>
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                Showing <span style="color: var(--text-main);"><?= count($courses) ?></span> Curriculum Tracks
            </div>
        </div>

        <div class="dash-stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));">
            <?php foreach($courses as $c): ?>
            <div class="premium-card" style="padding: 0; display: flex; flex-direction: column;">
                <div style="position: relative; height: 160px; overflow: hidden; background: #0f172a;">
                    <?php if($c['thumbnail']): ?>
                        <img src="../uploads/courses/<?= $c['thumbnail'] ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.6;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.1);">
                            <i class="fas fa-image" style="font-size: 3rem;"></i>
                        </div>
                    <?php endif; ?>
                    <div style="position: absolute; top: 16px; left: 16px;">
                        <span class="badge-premium" style="background: <?= $c['status'] === 'published' ? 'var(--success)' : ($c['status'] === 'pending' ? 'var(--warning)' : '#64748b') ?>; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                            <?= strtoupper($c['status']) ?>
                        </span>
                    </div>
                    <div style="position: absolute; bottom: 16px; right: 16px; font-size: 1.1rem; font-weight: 900; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                        KES <?= number_format($c['price']) ?>
                    </div>
                </div>
                
                <div style="padding: 24px; flex-grow: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div class="avatar-sm" style="width: 32px; height: 32px; font-size: 0.75rem; background: var(--bg-main); border-color: var(--border); color: var(--text-dim);">
                            <?= strtoupper(substr($c['tutor_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($c['category_name']) ?></div>
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($c['tutor_name']) ?></div>
                        </div>
                    </div>

                    <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0 0 20px 0; line-height: 1.4;"><?= htmlspecialchars($c['title']) ?></h3>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 16px 0; border-top: 1px solid var(--border-light); margin-top: auto;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.9rem; font-weight: 900; color: var(--text-main);"><?= number_format($c['actual_enrolled']) ?></div>
                            <div style="font-size: 0.55rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Scholars</div>
                        </div>
                        <div style="text-align: center; border-left: 1px solid var(--border-light); border-right: 1px solid var(--border-light);">
                            <div style="font-size: 0.9rem; font-weight: 900; color: var(--text-main);"><?= number_format($c['revenue'] ?: 0) ?></div>
                            <div style="font-size: 0.55rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Revenue</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.9rem; font-weight: 900; color: var(--secondary);">4.9 <i class="fas fa-star" style="font-size: 0.6rem;"></i></div>
                            <div style="font-size: 0.55rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Rating</div>
                        </div>
                    </div>
                </div>

                <div style="padding: 16px 24px; background: var(--bg-main); border-top: 1px solid var(--border-light); display: flex; gap: 12px; align-items: center;">
                    <div style="display: flex; gap: 8px; flex-grow: 1;">
                        <?php if($c['status'] === 'pending'): ?>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button name="approve_course" class="btn-premium" style="width: 100%; justify-content: center; height: 38px; font-size: 0.75rem;">Authorize</button>
                            </form>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button name="decline_course" class="btn-premium" style="width: 100%; justify-content: center; height: 38px; font-size: 0.75rem; background: var(--bg-main); color: var(--danger); border-color: var(--danger); box-shadow: none;">Reject</button>
                            </form>
                        <?php else: ?>
                            <button onclick="openCourseModal(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn-premium" style="flex: 1; justify-content: center; height: 38px; font-size: 0.75rem; background: transparent; border-color: var(--border); color: var(--text-dim); box-shadow: none;">
                                <i class="fas fa-edit"></i> Adjust Registry
                            </button>
                            <?php if($c['status'] !== 'archived'): ?>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button name="archive_course" class="avatar-sm" style="width: 38px; height: 38px; background: transparent; color: var(--text-muted); border-color: var(--border); cursor: pointer;" title="Archive Heritage">
                                    <i class="fas fa-archive" style="font-size: 0.8rem;"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <a href="course_details.php?id=<?= $c['id'] ?>" class="avatar-sm" style="width: 38px; height: 38px; background: var(--primary-glow); color: var(--primary); border: none; text-decoration: none;" title="Performance Intelligence">
                        <i class="fas fa-chart-bar" style="font-size: 0.8rem;"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- Unified Course Master Modal -->
<div id="adminCourseModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <div class="premium-card" style="width: 100%; max-width: 680px; padding: 48px; animation: modal-pop 0.4s cubic-bezier(0.4, 0, 0.2, 1); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light);">
            <div>
                <h2 id="modalTitle" style="font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px; margin: 0;">Launch New Track</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; font-weight: 600;">Synchronize global curriculum settings.</p>
            </div>
            <button onclick="document.getElementById('adminCourseModal').style.display='none'" class="avatar-sm" style="width: 40px; height: 40px; background: transparent; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="courseForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_course" value="1">
            <input type="hidden" name="id" id="course_id">
            
            <div style="margin-bottom: 24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Institutional Track Identity</label>
                <input type="text" name="title" id="course_title" required placeholder="e.g. Advanced AI Governance" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Designated Faculty</label>
                    <select name="tutor_id" id="course_tutor" required style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;background:white;cursor:pointer;">
                        <?php foreach($all_tutors as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Knowledge Classification</label>
                    <select name="category_id" id="course_cat" required style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;background:white;cursor:pointer;">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Instructional Complexity</label>
                    <select name="level" id="course_level" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;background:white;cursor:pointer;">
                        <option value="beginner">Entry Level Foundation</option>
                        <option value="intermediate">Intermediate Professional</option>
                        <option value="advanced">Advanced Executive</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Market Valuation (KES)</label>
                    <input type="number" name="price" id="course_price" required style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:700;outline:none;box-sizing:border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Lifecycle Status</label>
                    <select name="status" id="course_status" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;background:white;cursor:pointer;">
                        <option value="published">Global Active</option>
                        <option value="draft">Curriculum Draft</option>
                        <option value="pending">Faculty Audit</option>
                        <option value="archived">Archived Heritage</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Curriculum Asset (Cover)</label>
                    <input type="file" name="thumbnail" accept="image/*" style="width:100%;padding:11px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.85rem;font-weight:600;outline:none;box-sizing:border-box;background:white;">
                </div>
            </div>

            <div style="margin-bottom: 32px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Executive Intelligence Outline</label>
                <textarea name="description" id="course_desc" rows="4" placeholder="Outline the scholarly objectives and professional outcomes..." style="width:100%;padding:16px 20px;border-radius:16px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;resize:none;box-sizing:border-box;"></textarea>
            </div>

            <div style="display: flex; gap: 16px;">
                <button type="submit" id="submitBtn" class="btn-premium" style="flex: 2; justify-content: center; height: 56px; font-size: 1rem;">Execute Track Launch</button>
                <button type="button" class="btn-premium" onclick="document.getElementById('adminCourseModal').style.display='none'" style="flex: 1; background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; height: 56px; justify-content: center;">Abort</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modal-pop { 0% { transform: scale(0.9) translateY(20px); opacity: 0; } 100% { transform: scale(1) translateY(0); opacity: 1; } }
</style>

<script src="../assets/js/main.js"></script>
<script>
    function openCourseModal(course = null) {
        const modal = document.getElementById('adminCourseModal');
        const titleEl = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('courseForm');

        if (course) {
            titleEl.textContent = 'Synchronize Registry';
            submitBtn.textContent = 'Save Changes';
            document.getElementById('course_id').value = course.id;
            document.getElementById('course_title').value = course.title;
            document.getElementById('course_tutor').value = course.tutor_id;
            document.getElementById('course_cat').value = course.category_id;
            document.getElementById('course_price').value = course.price;
            document.getElementById('course_level').value = course.level;
            document.getElementById('course_status').value = course.status;
            document.getElementById('course_desc').value = course.description;
        } else {
            titleEl.textContent = 'Launch New Track';
            submitBtn.textContent = 'Execute Track Launch';
            form.reset();
            document.getElementById('course_id').value = '';
            document.getElementById('course_price').value = '1500';
            document.getElementById('course_level').value = 'beginner';
            document.getElementById('course_status').value = 'pending';
        }
        modal.style.display = 'flex';
    }

    document.addEventListener('DOMContentLoaded', () => {
        <?php if($success_msg): ?>
            SDA.showToast("<?= $success_msg ?>", "success");
        <?php endif; ?>
        <?php if($error_msg): ?>
            SDA.showToast("<?= $error_msg ?>", "danger");
        <?php endif; ?>

        window.addEventListener('click', (e) => {
            const modal = document.getElementById('adminCourseModal');
            if (e.target === modal) modal.style.display = 'none';
        });
    });

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
