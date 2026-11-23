<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';

// ── Filter inputs ─────────────────────────────────────────────
$cat_id = $_GET['category'] ?? 'all';
$level  = $_GET['level']    ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort']     ?? 'newest';
$price_f = in_array($_GET['price'] ?? '', ['free', 'paid'], true) ? $_GET['price'] : 'all';
$mode_f  = in_array($_GET['mode'] ?? '', ['self_paced', 'structured', 'tutorial'], true) ? $_GET['mode'] : 'all';

// ── Build Query ───────────────────────────────────────────────
$params = [];
$query  = "SELECT c.*, u.name AS tutor_name, cat.name AS category_name
           FROM courses c
           JOIN users u ON c.tutor_id = u.id
           LEFT JOIN categories cat ON c.category_id = cat.id
           WHERE c.status = 'published'";

if ($cat_id !== 'all') { $query .= " AND c.category_id = ?"; $params[] = $cat_id; }
if ($level  !== 'all') { $query .= " AND c.level = ?";       $params[] = $level; }
if ($search !== '')    { $query .= " AND (c.title LIKE ? OR c.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($mode_f === 'tutorial') { $query .= " AND c.is_open_tutorial = 1"; }
elseif ($mode_f !== 'all')  { $query .= " AND c.learning_modes IN (?, 'both')"; $params[] = $mode_f; }

$query .= match($sort) {
    'price_asc'  => " ORDER BY c.price ASC",
    'price_desc' => " ORDER BY c.price DESC",
    'rating'     => " ORDER BY c.avg_rating DESC",
    default      => " ORDER BY c.created_at DESC",
};

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    // Price filter/sort use the live price (sale, cheapest track), which SQL can't see.
    if ($price_f !== 'all') {
        $courses = array_values(array_filter($courses, fn($c) => ($price_f === 'free') === (course_from_price($c) <= 0)));
    }
    if ($sort === 'price_asc' || $sort === 'price_desc') {
        usort($courses, fn($a, $b) => ($sort === 'price_asc' ? 1 : -1) * (course_from_price($a) <=> course_from_price($b)));
    }
    if ($sort === 'newest') {
        usort($courses, fn($a, $b) => [$b['is_featured'], $b['created_at']] <=> [$a['is_featured'], $a['created_at']]);
    }
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

    // Count per category
    $cat_counts = [];
    foreach ($pdo->query("SELECT category_id, COUNT(*) AS cnt FROM courses WHERE status='published' GROUP BY category_id")->fetchAll() as $r) {
        $cat_counts[$r['category_id']] = $r['cnt'];
    }
    $total_published = array_sum($cat_counts);

} catch (Exception $e) {
    $courses = []; $categories = []; $cat_counts = []; $total_published = 0;
}

$user = isLoggedIn() ? currentUser() : null;
$enrolled_ids = $user ? $pdo->query("SELECT course_id FROM enrollments WHERE student_id = ".$user['id']." AND status != 'cancelled'")->fetchAll(PDO::FETCH_COLUMN) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Catalog – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Online Courses', 'path' => 'courses', 'description' => 'Browse Skope Digital Academy courses in coding, design, data, marketing and business skills. Learn self-paced or in a weekly class and earn a certificate.', 'breadcrumbs' => [['Courses', 'courses']]]); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
<style>
/* ═══ PAGE BASE ═══ */
*, *::before, *::after { box-sizing: border-box; }
body { background: #F8FAFC; color: #1E293B; font-family: 'Inter', sans-serif; }

/* ═══ HERO ═══ */
.page-hero {
  background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 60%, #fff7ed 100%);
  padding: 72px 0 80px;
  border-bottom: 1px solid #E2E8F0;
  position: relative; overflow: hidden;
}
.page-hero::before {
  content: ''; position: absolute; top: -80px; right: -60px;
  width: 500px; height: 500px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,191,255,0.07) 0%, transparent 65%);
  pointer-events: none;
}
.page-hero h1 {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(2rem, 4.5vw, 3.2rem);
  font-weight: 900; color: #0F172A;
  margin-bottom: 14px; line-height: 1.1;
}
.page-hero h1 span { color: #00BFFF; }
.page-hero p { color: #475569; font-size: 1.05rem; line-height: 1.65; max-width: 560px; margin: 0 auto 36px; }

/* ═══ SEARCH BAR ═══ */
.search-wrap {
  position: relative; max-width: 580px; margin: 0 auto;
}
.search-wrap .search-icon {
  position: absolute; left: 22px; top: 50%;
  transform: translateY(-50%); color: #94a3b8; font-size: 1rem;
}
.search-field {
  width: 100%; height: 58px;
  padding: 0 20px 0 54px;
  border: 2px solid #E2E8F0; border-radius: 14px;
  font-size: 0.97rem; font-family: 'Inter', sans-serif;
  background: #fff; color: #0F172A;
  box-shadow: 0 4px 16px rgba(0,0,0,0.05);
  transition: 0.3s; outline: none;
}
.search-field:focus { border-color: #00BFFF; box-shadow: 0 0 0 4px rgba(0,191,255,0.1); }

/* ═══ LAYOUT ═══ */
.catalog-wrap {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 36px;
  padding: 48px 0 80px;
  align-items: start;
}

/* ═══ FILTER PANEL ═══ */
.filter-panel {
  background: #fff;
  border: 1px solid #E2E8F0;
  border-radius: 20px;
  padding: 32px 28px;
  position: sticky; top: 100px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}
.filter-section { margin-bottom: 36px; }
.filter-section:last-child { margin-bottom: 0; }
.filter-label {
  font-family: 'Poppins', sans-serif;
  font-size: 0.78rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 1.5px;
  color: #94a3b8; margin-bottom: 18px; display: block;
  border-bottom: 1.5px solid #E2E8F0; padding-bottom: 10px;
}
.filter-radio { display: flex; flex-direction: column; gap: 4px; }
.radio-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 10px;
  cursor: pointer; transition: 0.2s; font-size: 0.9rem;
  color: #475569; font-weight: 500;
}
.radio-item:hover { background: rgba(0,191,255,0.05); color: #00BFFF; }
.radio-item input[type=radio] { accent-color: #00BFFF; width: 16px; height: 16px; }

.cat-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px; border-radius: 10px;
  text-decoration: none; transition: 0.2s;
  font-size: 0.88rem; font-weight: 600; color: #475569;
  margin-bottom: 2px;
}
.cat-item:hover, .cat-item.active {
  background: rgba(0,191,255,0.08); color: #00BFFF;
}
.cat-count {
  font-size: 0.72rem; background: #F1F5F9; color: #94a3b8;
  padding: 2px 8px; border-radius: 20px; font-weight: 700;
}
.cat-item.active .cat-count { background: rgba(0,191,255,0.15); color: #00BFFF; }

.btn-clear {
  width: 100%; padding: 11px; border-radius: 10px;
  border: 1.5px solid #E2E8F0; background: transparent;
  color: #64748B; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif;
}
.btn-clear:hover { border-color: #FF8C00; color: #FF8C00; background: rgba(255,140,0,0.05); }

/* ═══ MOBILE FILTER TOGGLE ═══ */
.mobile-filter-btn {
  display: none;
  align-items: center; gap: 10px;
  background: #fff; border: 1.5px solid #E2E8F0;
  border-radius: 12px; padding: 12px 20px;
  font-family: 'Poppins', sans-serif; font-weight: 700;
  font-size: 0.88rem; color: #0F172A;
  cursor: pointer; transition: 0.2s;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.mobile-filter-btn:hover { border-color: #00BFFF; color: #00BFFF; }
.mobile-filter-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.5); z-index: 900;
}
.mobile-filter-overlay.open { display: block; }
.mobile-filter-drawer {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: #fff; border-radius: 24px 24px 0 0;
  padding: 32px 24px 48px; z-index: 901;
  max-height: 85vh; overflow-y: auto;
  transform: translateY(100%); transition: 0.35s ease;
  box-shadow: 0 -8px 40px rgba(0,0,0,0.12);
}
.mobile-filter-drawer.open { transform: translateY(0); }
.drawer-handle { width: 44px; height: 4px; background: #E2E8F0; border-radius: 4px; margin: 0 auto 24px; }

/* ═══ MAIN RESULTS AREA ═══ */
.results-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 28px; flex-wrap: wrap; gap: 14px;
}
.results-count {
  font-family: 'Poppins', sans-serif; font-size: 1.05rem; font-weight: 700; color: #0F172A;
}
.results-count span { color: #00BFFF; }

/* Sort Dropdown */
.sort-select {
  padding: 10px 16px; border-radius: 10px;
  border: 1.5px solid #E2E8F0; background: #fff;
  font-size: 0.85rem; font-family: 'Inter', sans-serif;
  font-weight: 600; color: #475569; cursor: pointer;
  outline: none; transition: 0.2s; min-width: 180px;
}
.sort-select:focus { border-color: #00BFFF; }

/* ═══ COURSE GRID ═══ */
.course-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

/* ═══ COURSE CARD ═══ */
.c-card {
  background: #fff; border: 1px solid #E2E8F0;
  border-radius: 20px; overflow: hidden;
  transition: 0.3s; display: flex; flex-direction: column;
}
.c-card:hover { transform: translateY(-8px); border-color: #00BFFF; box-shadow: 0 20px 50px rgba(0,191,255,0.1); }
.c-thumb { height: 186px; position: relative; overflow: hidden; background: #f1f5f9; }
.c-thumb img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
.c-card:hover .c-thumb img { transform: scale(1.06); }
.c-thumb-empty {
  width: 100%; height: 100%; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
}
.c-cat {
  position: absolute; top: 12px; left: 12px;
  background: rgba(255,255,255,0.95); color: #00BFFF;
  padding: 4px 12px; border-radius: 7px;
  font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
  backdrop-filter: blur(4px);
}
.c-level {
  position: absolute; top: 12px; right: 12px;
  padding: 4px 10px; border-radius: 7px;
  font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
}
.level-beginner     { background: #ecfdf5; color: #047857; }
.level-intermediate { background: #fffbeb; color: #b45309; }
.level-advanced     { background: #fef2f2; color: #b91c1c; }
.c-cat, .c-level { box-shadow: 0 4px 12px -4px rgba(0,0,0,.35); }

.c-body { padding: 22px; flex-grow: 1; display: flex; flex-direction: column; }
.c-title {
  font-family: 'Poppins', sans-serif; font-size: 1.02rem;
  font-weight: 700; color: #0F172A; margin-bottom: 8px;
  line-height: 1.35; display: -webkit-box;
  -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.c-tutor { font-size: 0.8rem; color: #64748B; margin-bottom: 12px; }
.c-stats { display: flex; gap: 16px; font-size: 0.76rem; color: #94a3b8; margin-bottom: auto; padding-bottom: 16px; }
.c-footer {
  display: flex; justify-content: space-between; align-items: center;
  padding-top: 16px; border-top: 1px solid #E2E8F0;
}
.c-price { font-size: 1.15rem; font-weight: 800; color: #FF8C00; }
.btn-enroll {
  display: inline-flex; align-items: center; gap: 7px;
  background: #00BFFF; color: #fff; padding: 10px 18px;
  border-radius: 10px; font-size: 0.82rem; font-weight: 700;
  text-decoration: none; transition: 0.25s;
  border: none; cursor: pointer; font-family: 'Inter',sans-serif;
}
.btn-enroll:hover { background: #009ACD; transform: translateY(-2px); color: #fff; }
.c-footer .btn-enroll { background: linear-gradient(135deg,#FF8C00,#FF6A00); box-shadow: 0 8px 18px -10px rgba(255,120,0,.8); white-space: nowrap; }
.c-footer .btn-enroll:hover { background: linear-gradient(135deg,#ff7a00,#f05a00); }
.c-footer .btn-enroll.is-enrolled { background: #10b981; box-shadow: none; }

/* ═══ EMPTY STATE ═══ */
.empty-state {
  grid-column: 1/-1; text-align: center; padding: 80px 40px;
  background: #fff; border-radius: 20px; border: 2px dashed #E2E8F0;
}

/* ═══ PAGINATION ═══ */
.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 48px; flex-wrap: wrap; }
.page-btn {
  width: 40px; height: 40px; border-radius: 10px;
  border: 1.5px solid #E2E8F0; background: #fff;
  font-weight: 700; font-size: 0.88rem; color: #475569;
  cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;
}
.page-btn:hover, .page-btn.active { background: #00BFFF; border-color: #00BFFF; color: #fff; }

/* ═══ RESPONSIVE ═══ */
@media (max-width: 1200px) {
  .catalog-wrap { grid-template-columns: 260px 1fr; gap: 28px; }
  .course-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 991px) {
  .catalog-wrap { grid-template-columns: 1fr; }
  .filter-panel { display: none; }
  .mobile-filter-btn { display: flex; }
  .results-header { flex-direction: column; align-items: flex-start; }
  .desktop-sort { display: none !important; }
}

@media (max-width: 768px) {
  .page-hero { padding: 84px 0 40px; text-align: center; }
  .page-hero p { margin: 0 auto 24px; font-size: 0.95rem; }
  .search-field { height: 50px; font-size: 0.9rem; padding-left: 48px; }
  .search-wrap .search-icon { left: 18px; }
}

@media (max-width: 480px) {
  .course-grid { grid-template-columns: 1fr; }
  .c-stats { flex-wrap: wrap; gap: 8px; }
  .c-footer { flex-direction: column; gap: 12px; align-items: flex-start; }
  .btn-enroll { width: 100%; justify-content: center; }
  .stat-pod-elite .value { font-size: 1.3rem !important; }
}
  .c-tracks { display:flex; flex-wrap:wrap; gap:6px; margin:10px 0 2px; }
  .c-track { font-size:.68rem; font-weight:800; padding:3px 9px; border-radius:99px; text-decoration:none; }
  .c-track.self { background:#e6f3ff; color:#08427a; }
  .c-track.struct { background:#fff4e5; color:#7a4300; }
  .c-track.free { background:#e7f7f0; color:#075e40; }
  .c-track.cert { background:#fef3c7; color:#92400e; }
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<!-- ════════════════ PAGE HERO ════════════════ -->
<section class="page-hero">
  <div class="container" style="text-align:center; position:relative; z-index:1;">
    <h1>Find Your Next <span>Skill</span></h1>
    <p>Explore <?= number_format($total_published) ?> courses across Technology, Business, Design, and more — built for real-world careers.</p>

    <!-- Search Bar -->
    <form action="courses" method="GET" class="search-wrap" id="heroSearch">
      <i class="fas fa-search search-icon"></i>
      <input type="text" name="search" class="search-field"
             placeholder="Search courses, topics, or skills…"
             value="<?= htmlspecialchars($search) ?>">
      <?php if($cat_id !== 'all'): ?>
        <input type="hidden" name="category" value="<?= htmlspecialchars($cat_id) ?>">
      <?php endif; ?>
      <?php if($level !== 'all'): ?>
        <input type="hidden" name="level" value="<?= htmlspecialchars($level) ?>">
      <?php endif; ?>
    </form>

    <!-- Active Filters Row -->
    <?php if ($search || $cat_id !== 'all' || $level !== 'all'): ?>
    <div style="display:flex;justify-content:center;gap:10px;margin-top:20px;flex-wrap:wrap;">
      <?php if($search): ?>
        <span style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #E2E8F0;border-radius:8px;padding:5px 12px;font-size:0.78rem;font-weight:600;color:#475569;">
          <i class="fas fa-search" style="color:#00BFFF;"></i> "<?= htmlspecialchars($search) ?>"
          <a href="courses?category=<?= $cat_id ?>&level=<?= $level ?>" style="color:#ef4444;margin-left:4px;"><i class="fas fa-times"></i></a>
        </span>
      <?php endif; ?>
      <?php if($level !== 'all'): ?>
        <span style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #E2E8F0;border-radius:8px;padding:5px 12px;font-size:0.78rem;font-weight:600;color:#475569;">
          <i class="fas fa-signal" style="color:#FF8C00;"></i> <?= ucfirst($level) ?>
          <a href="courses?category=<?= $cat_id ?>&search=<?= urlencode($search) ?>" style="color:#ef4444;margin-left:4px;"><i class="fas fa-times"></i></a>
        </span>
      <?php endif; ?>
      <a href="courses" style="display:inline-flex;align-items:center;gap:6px;color:#64748B;text-decoration:none;font-size:0.78rem;font-weight:600;padding:5px 12px;">
        Clear all <i class="fas fa-times-circle"></i>
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ════════════════ CATALOG ════════════════ -->
<div class="container">
  <div class="catalog-wrap">

    <!-- ── SIDEBAR (Desktop) ── -->
    <aside>
      <div class="filter-panel">
        <form id="filterForm" action="courses" method="GET">
          <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
          <input type="hidden" name="category" value="<?= htmlspecialchars((string)$cat_id) ?>">

          <!-- How you learn -->
          <div class="filter-section">
            <span class="filter-label">How you learn</span>
            <div class="filter-radio">
              <?php foreach(['all'=>'Any track','self_paced'=>'Self-paced','structured'=>'Structured (weekly)','tutorial'=>'Free tutorials'] as $val=>$lbl): ?>
              <label class="radio-item">
                <input type="radio" name="mode" value="<?= $val ?>" <?= $mode_f === $val ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                <?= $lbl ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Price -->
          <div class="filter-section">
            <span class="filter-label">Price</span>
            <div class="filter-radio">
              <?php foreach(['all'=>'All','free'=>'Free','paid'=>'Paid'] as $val=>$lbl): ?>
              <label class="radio-item">
                <input type="radio" name="price" value="<?= $val ?>" <?= $price_f === $val ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                <?= $lbl ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Level -->
          <div class="filter-section">
            <span class="filter-label">Academic Level</span>
            <div class="filter-radio">
              <?php foreach(['all'=>'All Levels','beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $val=>$lbl): ?>
              <label class="radio-item">
                <input type="radio" name="level" value="<?= $val ?>" <?= $level === $val ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                <?= $lbl ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Categories -->
          <div class="filter-section">
            <span class="filter-label">Category</span>
            <a href="courses?level=<?= urlencode($level) ?>&mode=<?= $mode_f ?>&price=<?= $price_f ?>&search=<?= urlencode($search) ?>" class="cat-item <?= $cat_id === 'all' ? 'active' : '' ?>">
              <span><i class="fas fa-th-large" style="margin-right:8px;font-size:0.8rem;opacity:0.5;"></i>All Programs</span>
              <span class="cat-count"><?= number_format($total_published) ?></span>
            </a>
            <?php foreach($categories as $cat): ?>
            <a href="courses?category=<?= $cat['id'] ?>&level=<?= urlencode($level) ?>&mode=<?= $mode_f ?>&price=<?= $price_f ?>&search=<?= urlencode($search) ?>" class="cat-item <?= $cat_id == $cat['id'] ? 'active' : '' ?>">
              <span><?= htmlspecialchars($cat['name']) ?></span>
              <span class="cat-count"><?= $cat_counts[$cat['id']] ?? 0 ?></span>
            </a>
            <?php endforeach; ?>
          </div>

          <!-- Price -->
          <div class="filter-section">
            <span class="filter-label">Sort By</span>
            <select name="sort" class="sort-select" style="width:100%;margin-top:0;" onchange="document.getElementById('filterForm').submit()">
              <option value="newest"    <?= $sort==='newest'    ?'selected':'' ?>>Newest First</option>
              <option value="price_asc" <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low to High</option>
              <option value="price_desc"<?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
              <option value="rating"    <?= $sort==='rating'    ?'selected':'' ?>>Highest Rated</option>
            </select>
          </div>

          <button type="button" class="btn-clear" onclick="window.location.href='courses'">
            <i class="fas fa-redo-alt"></i> Reset All Filters
          </button>
        </form>
      </div>
    </aside>

    <!-- ── MAIN ── -->
    <main>
      <?php if (!$user): ?>
      <a class="courses-promo" href="register" aria-label="Join Skope Digital Academy today"><picture><source type="image/webp" srcset="assets/images/promo/courses-banner-1600.webp 1600w, assets/images/promo/courses-banner-900.webp 900w" sizes="(max-width: 900px) 100vw, 1200px"><img src="assets/images/promo/courses-banner-1600.jpg" alt="Build skills for a digital future at Skope Digital Academy: expert mentors, quality courses, certified and flexible online learning. Join today." class="" loading="lazy" decoding="async"></picture></a>
      <style>.courses-promo{display:block;margin:0 0 22px;border-radius:20px;overflow:hidden;box-shadow:0 20px 44px -30px rgba(15,23,42,.45);transition:transform .2s}.courses-promo:hover{transform:translateY(-2px)}.courses-promo img{display:block;width:100%;height:auto;aspect-ratio:16/9}@media(max-width:600px){.courses-promo{border-radius:14px}}</style>
      <?php endif; ?>
      <!-- Mobile Filter + Result Count Row -->
      <div class="results-header">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <button class="mobile-filter-btn" id="mobileFilterBtn">
            <i class="fas fa-sliders-h" style="color:#00BFFF;"></i>
            Filter & Sort
            <?php if($cat_id!=='all'||$level!=='all'||$mode_f!=='all'||$price_f!=='all'): ?>
              <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:#00BFFF;color:#fff;border-radius:50%;font-size:0.7rem;font-weight:800;">!</span>
            <?php endif; ?>
          </button>
          <div class="results-count">
            Showing <span><?= count($courses) ?></span> result<?= count($courses) !== 1 ? 's' : '' ?>
            <?= $search ? ' for "<em>' . htmlspecialchars($search) . '</em>"' : '' ?>
          </div>
        </div>
        <div class="desktop-sort" style="display:flex;align-items:center;gap:10px;">
          <label style="font-size:0.82rem;color:#94a3b8;font-weight:600;white-space:nowrap;">Sort by:</label>
          <select class="sort-select" onchange="window.location.href='courses?category=<?= urlencode((string)$cat_id) ?>&level=<?= urlencode($level) ?>&mode=<?= $mode_f ?>&price=<?= $price_f ?>&search=<?= urlencode($search) ?>&sort='+this.value">
            <option value="newest"    <?= $sort==='newest'    ?'selected':'' ?>>Newest</option>
            <option value="price_asc" <?= $sort==='price_asc' ?'selected':'' ?>>Price ↑</option>
            <option value="price_desc"<?= $sort==='price_desc'?'selected':'' ?>>Price ↓</option>
            <option value="rating"    <?= $sort==='rating'    ?'selected':'' ?>>Top Rated</option>
          </select>
        </div>
      </div>

      <!-- Course Grid -->
      <div class="course-grid">
        <?php if(!empty($courses)): ?>
          <?php foreach($courses as $c): ?>
          <article class="c-card">
            <div class="c-thumb">
              <?php if($c['thumbnail']): ?>
                <img src="uploads/courses/<?= htmlspecialchars($c['thumbnail']) ?>" alt="<?= htmlspecialchars($c['title']) ?>" loading="lazy">
              <?php else: ?>
                <div class="c-thumb-empty">
                  <i class="fas fa-laptop-code" style="font-size:2.4rem;color:#00BFFF;opacity:0.25;"></i>
                  <span style="font-size:0.7rem;color:#94a3b8;margin-top:10px;"><?= htmlspecialchars($c['category_name'] ?? 'General') ?></span>
                </div>
              <?php endif; ?>
              <div class="c-cat"><?= htmlspecialchars($c['category_name'] ?? 'General') ?></div>
              <div class="c-level level-<?= strtolower($c['level'] ?? 'beginner') ?>"><?= ucfirst($c['level'] ?? 'Beginner') ?></div>
            </div>
            <div class="c-body">
              <h3 class="c-title"><?= htmlspecialchars($c['title']) ?></h3>
              <div class="c-tutor">
                <i class="fas fa-user-circle" style="color:#00BFFF;"></i>
                <?= htmlspecialchars($c['tutor_name']) ?>
              </div>
              <div class="c-stats">
                <?php if ((float)$c['avg_rating'] > 0): ?><span><i class="fas fa-star" style="color:#f59e0b;"></i> <?= number_format((float)$c['avg_rating'], 1) ?></span><?php endif; ?>
                <?php if ((int)$c['enrolled_count'] > 0): ?><span><i class="fas fa-users" style="color:#00BFFF;"></i> <?= (int)$c['enrolled_count'] ?> enrolled</span><?php endif; ?>
                <?php if ((float)$c['duration_hours'] > 0): ?><span><i class="fas fa-clock" style="color:#94a3b8;"></i> <?= (float)$c['duration_hours'] ?>h</span><?php endif; ?>
                <span><i class="fas fa-award" style="color:#10b981;"></i> Certified</span>
              </div>
              <div class="c-tracks">
                <?php foreach (course_modes($c) as $m): ?><span class="c-track <?= $m === 'structured' ? 'struct' : 'self' ?>"><?= e(mode_label($m)) ?></span><?php endforeach; ?>
                <?php if ($c['is_open_tutorial']): ?><a class="c-track free" href="learn?course=<?= (int)$c['id'] ?>">Read free</a><?php endif; ?>
                <?php if (!$c['is_open_tutorial'] && course_from_price($c) <= 0): $cf = certificate_fee_for($c); ?><span class="c-track cert" title="Learning is free. The certificate is unlocked after you complete the course."><?= $cf > 0 ? 'Certificate ' . e(money($cf)) : 'Free certificate' ?></span><?php endif; ?>
              </div>
              <div class="c-footer">
                <?php $from = course_from_price($c); $modes_c = course_modes($c); ?>
                <span class="c-price"><?php if ($from <= 0): ?>Free<?php else: ?><?= count($modes_c) > 1 && course_price($c, $modes_c[0]) != course_price($c, $modes_c[1]) ? '<small style="font-size:.7em;font-weight:600;color:#94a3b8">from</small> ' : '' ?><?= e(money($from)) ?><?php if (sale_active($c)): ?> <s style="font-size:.7em;color:#94a3b8;font-weight:600"><?= e(money((float)$c['price'])) ?></s><?php endif; ?><?php endif; ?></span>
                <?= sda_enroll_button($c, in_array($c['id'], $enrolled_ids)) ?>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-search" style="font-size:3rem;color:#E2E8F0;margin-bottom:20px;display:block;"></i>
            <h3 style="color:#64748B;margin-bottom:10px;">No Courses Found</h3>
            <p style="color:#94a3b8;font-size:0.9rem;max-width:340px;margin:0 auto 28px;">Try adjusting your search terms or clearing some filters.</p>
            <a href="courses" class="btn-enroll">View All Courses</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Simple pagination placeholder -->
      <?php if(count($courses) > 12): ?>
      <div class="pagination">
        <button class="page-btn active">1</button>
        <button class="page-btn">2</button>
        <button class="page-btn">3</button>
        <button class="page-btn">›</button>
      </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- ════════════════ MOBILE FILTER DRAWER ════════════════ -->
<div class="mobile-filter-overlay" id="mobileOverlay"></div>
<div class="mobile-filter-drawer" id="mobileDrawer">
  <div class="drawer-handle"></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;">
    <h3 style="font-family:'Poppins',sans-serif;font-size:1.15rem;font-weight:800;">Filter & Sort</h3>
    <button onclick="closeDrawer()" style="border:none;background:none;font-size:1.4rem;color:#94a3b8;cursor:pointer;"><i class="fas fa-times"></i></button>
  </div>

  <form action="courses" method="GET" id="drawerForm">
    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
    <input type="hidden" name="category" value="<?= htmlspecialchars((string)$cat_id) ?>">

    <div class="filter-section">
      <span class="filter-label">How you learn</span>
      <div class="filter-radio">
        <?php foreach(['all'=>'Any track','self_paced'=>'Self-paced','structured'=>'Structured (weekly)','tutorial'=>'Free tutorials'] as $val=>$lbl): ?>
        <label class="radio-item"><input type="radio" name="mode" value="<?= $val ?>" <?= $mode_f === $val ? 'checked' : '' ?>> <?= $lbl ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="filter-section">
      <span class="filter-label">Price</span>
      <div class="filter-radio">
        <?php foreach(['all'=>'All','free'=>'Free','paid'=>'Paid'] as $val=>$lbl): ?>
        <label class="radio-item"><input type="radio" name="price" value="<?= $val ?>" <?= $price_f === $val ? 'checked' : '' ?>> <?= $lbl ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="filter-section">
      <span class="filter-label">Sort By</span>
      <select name="sort" class="sort-select" style="width:100%;">
        <option value="newest"    <?= $sort==='newest'    ?'selected':'' ?>>Newest First</option>
        <option value="price_asc" <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
        <option value="price_desc"<?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
        <option value="rating"    <?= $sort==='rating'    ?'selected':'' ?>>Top Rated</option>
      </select>
    </div>

    <div class="filter-section">
      <span class="filter-label">Academic Level</span>
      <div class="filter-radio">
        <?php foreach(['all'=>'All Levels','beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $val=>$lbl): ?>
        <label class="radio-item">
          <input type="radio" name="level" value="<?= $val ?>" <?= $level === $val ? 'checked' : '' ?>>
          <?= $lbl ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="filter-section">
      <span class="filter-label">Category</span>
      <div class="filter-radio">
        <label class="radio-item">
          <input type="radio" name="category" value="all" <?= $cat_id === 'all' ? 'checked' : '' ?>>
          All Programs
        </label>
        <?php foreach($categories as $cat): ?>
        <label class="radio-item">
          <input type="radio" name="category" value="<?= $cat['id'] ?>" <?= $cat_id == $cat['id'] ? 'checked' : '' ?>>
          <?= htmlspecialchars($cat['name']) ?>
          <span class="cat-count" style="margin-left:auto;"><?= $cat_counts[$cat['id']] ?? 0 ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:8px;">
      <button type="button" class="btn-clear" onclick="window.location.href='courses'" style="flex:1;">Reset</button>
      <button type="submit" class="btn-enroll" style="flex:2;justify-content:center;border-radius:12px;font-size:0.95rem;padding:14px;">
        Apply Filters <i class="fas fa-check"></i>
      </button>
    </div>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
/* ══ Mobile Drawer ══ */
const drawerBtn     = document.getElementById('mobileFilterBtn');
const drawer        = document.getElementById('mobileDrawer');
const overlay       = document.getElementById('mobileOverlay');

function openDrawer() {
  drawer.classList.add('open');
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDrawer() {
  drawer.classList.remove('open');
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}

if (drawerBtn) drawerBtn.addEventListener('click', openDrawer);
if (overlay)   overlay.addEventListener('click', closeDrawer);

/* ══ Live Search ══ */
const searchField = document.querySelector('.search-field');
let searchTimer;
if (searchField) {
  searchField.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      document.getElementById('heroSearch').submit();
    }, 600);
  });
}

/* ══ Desktop Sort select hides on mobile ══ */
function handleResize() {
  const ds = document.querySelector('.desktop-sort');
  if (ds) ds.style.display = window.innerWidth < 900 ? 'none' : 'flex';
}
handleResize();
window.addEventListener('resize', handleResize);
</script>
</body>
</html>
