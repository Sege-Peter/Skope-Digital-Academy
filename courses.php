<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ── Filter inputs ─────────────────────────────────────────────
$cat_id = $_GET['category'] ?? 'all';
$level  = $_GET['level']    ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort']     ?? 'newest';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Browse the full Skope Digital Academy course catalog. Filter by category, level, and price. Start learning today.">
<title>Course Catalog – Skope Digital Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
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
.level-beginner     { background: rgba(16,185,129,0.15); color: #10b981; }
.level-intermediate { background: rgba(245,158,11,0.15); color: #f59e0b; }
.level-advanced     { background: rgba(239,68,68,0.15);  color: #ef4444; }

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
@media (max-width: 1100px) {
  .catalog-wrap { grid-template-columns: 260px 1fr; gap: 28px; }
}

@media (max-width: 900px) {
  .catalog-wrap { grid-template-columns: 1fr; }
  .filter-panel  { display: none; } /* hidden on mobile — use drawer */
  .mobile-filter-btn { display: flex; }
  .course-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
  .page-hero { padding: 56px 0 64px; text-align: center; }
  .page-hero p { margin: 0 auto 28px; }
  .course-grid { grid-template-columns: 1fr; }
  .results-header { flex-direction: column; align-items: flex-start; }
  .sort-select { width: 100%; }
}

@media (max-width: 400px) {
  .c-stats { flex-wrap: wrap; gap: 8px; }
  .c-footer { flex-direction: column; gap: 12px; align-items: flex-start; }
  .btn-enroll { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<!-- ════════════════ PAGE HERO ════════════════ -->
<section class="page-hero">
  <div class="container" style="text-align:center; position:relative; z-index:1;">
    <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(0,191,255,0.09);color:#00BFFF;border:1px solid rgba(0,191,255,0.2);padding:6px 18px;border-radius:999px;font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:20px;">
      <i class="fas fa-book-open"></i> Official Course Catalog
    </div>
    <h1>Find Your Next <span>Skill</span></h1>
    <p>Explore <?= number_format($total_published + 118) ?>+ professional courses across Technology, Business, Design, and more — built for real-world careers.</p>

    <!-- Search Bar -->
    <form action="courses.php" method="GET" class="search-wrap" id="heroSearch">
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
          <a href="courses.php?category=<?= $cat_id ?>&level=<?= $level ?>" style="color:#ef4444;margin-left:4px;"><i class="fas fa-times"></i></a>
        </span>
      <?php endif; ?>
      <?php if($level !== 'all'): ?>
        <span style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #E2E8F0;border-radius:8px;padding:5px 12px;font-size:0.78rem;font-weight:600;color:#475569;">
          <i class="fas fa-signal" style="color:#FF8C00;"></i> <?= ucfirst($level) ?>
          <a href="courses.php?category=<?= $cat_id ?>&search=<?= urlencode($search) ?>" style="color:#ef4444;margin-left:4px;"><i class="fas fa-times"></i></a>
        </span>
      <?php endif; ?>
      <a href="courses.php" style="display:inline-flex;align-items:center;gap:6px;color:#64748B;text-decoration:none;font-size:0.78rem;font-weight:600;padding:5px 12px;">
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
        <form id="filterForm" action="courses.php" method="GET">
          <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

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
            <a href="courses.php?level=<?= $level ?>&search=<?= urlencode($search) ?>" class="cat-item <?= $cat_id === 'all' ? 'active' : '' ?>">
              <span><i class="fas fa-th-large" style="margin-right:8px;font-size:0.8rem;opacity:0.5;"></i>All Programs</span>
              <span class="cat-count"><?= number_format($total_published) ?></span>
            </a>
            <?php foreach($categories as $cat): ?>
            <a href="courses.php?category=<?= $cat['id'] ?>&level=<?= $level ?>&search=<?= urlencode($search) ?>" class="cat-item <?= $cat_id == $cat['id'] ? 'active' : '' ?>">
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

          <button type="button" class="btn-clear" onclick="window.location.href='courses.php'">
            <i class="fas fa-redo-alt"></i> Reset All Filters
          </button>
        </form>
      </div>
    </aside>

    <!-- ── MAIN ── -->
    <main>
      <!-- Mobile Filter + Result Count Row -->
      <div class="results-header">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <button class="mobile-filter-btn" id="mobileFilterBtn">
            <i class="fas fa-sliders-h" style="color:#00BFFF;"></i>
            Filter & Sort
            <?php if($cat_id!=='all'||$level!=='all'): ?>
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
          <select class="sort-select" onchange="window.location.href='courses.php?category=<?= $cat_id ?>&level=<?= $level ?>&search=<?= urlencode($search) ?>&sort='+this.value">
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
                <span><i class="fas fa-star" style="color:#f59e0b;"></i> 4.9</span>
                <span><i class="fas fa-users" style="color:#00BFFF;"></i> <?= rand(200,900) ?></span>
                <span><i class="fas fa-clock" style="color:#94a3b8;"></i> <?= $c['duration_hours'] ?? rand(8,40) ?>h</span>
                <span><i class="fas fa-award" style="color:#10b981;"></i> Certified</span>
              </div>
              <div class="c-footer">
                <span class="c-price">KES <?= number_format($c['price']) ?></span>
                <a href="course-details.php?id=<?= $c['id'] ?>" class="btn-enroll">
                  Enroll Now <i class="fas fa-arrow-right"></i>
                </a>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-search" style="font-size:3rem;color:#E2E8F0;margin-bottom:20px;display:block;"></i>
            <h3 style="color:#64748B;margin-bottom:10px;">No Courses Found</h3>
            <p style="color:#94a3b8;font-size:0.9rem;max-width:340px;margin:0 auto 28px;">Try adjusting your search terms or clearing some filters.</p>
            <a href="courses.php" class="btn-enroll">View All Courses</a>
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

  <form action="courses.php" method="GET" id="drawerForm">
    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

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
      <button type="button" class="btn-clear" onclick="window.location.href='courses.php'" style="flex:1;">Reset</button>
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
