<?php
/** Admin: course categories – name, icon and colour, with course counts. */
$pageTitle = 'Categories';
require_once 'includes/header.php';

function axcat_slug(PDO $pdo, string $name, int $exceptId = 0): string {
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'category';
    $slug = $base; $n = 2;
    $chk = $pdo->prepare("SELECT 1 FROM categories WHERE slug = ? AND id <> ?");
    while (true) { $chk->execute([$slug, $exceptId]); if (!$chk->fetchColumn()) return $slug; $slug = $base . '-' . $n++; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $name  = trim($_POST['name'] ?? '');
        $icon  = trim($_POST['icon'] ?? '') ?: 'fas fa-book';
        $color = trim($_POST['color'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) { ax_flash('err', 'Enter a category name (up to 100 characters).'); ax_back(); }
        if (!preg_match('/^[a-z0-9 -]{3,50}$/i', $icon)) { ax_flash('err', 'Icon must be a Font Awesome class such as "fas fa-laptop-code".'); ax_back(); }
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#00BFFF';
        $dup = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ?");
        $dup->execute([$name, $id]);
        if ($dup->fetch()) { ax_flash('err', "A category called \"$name\" already exists."); ax_back(); }
        if ($id) {
            $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, color = ? WHERE id = ?")->execute([$name, axcat_slug($pdo, $name, $id), $icon, $color, $id]);
            ax_flash('ok', "\"$name\" saved.");
        } else {
            $pdo->prepare("INSERT INTO categories (name, slug, icon, color) VALUES (?, ?, ?, ?)")->execute([$name, axcat_slug($pdo, $name), $icon, $color]);
            ax_flash('ok', "Category \"$name\" added.");
        }
        ax_back();
    }
    if ($act === 'delete' && $id) {
        $st = $pdo->prepare("SELECT name, (SELECT COUNT(*) FROM courses WHERE category_id = categories.id) n FROM categories WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) { ax_flash('err', 'That category no longer exists.'); ax_back(); }
        if ((int)$row['n'] > 0) {
            ax_flash('err', "\"{$row['name']}\" still has {$row['n']} course" . ($row['n'] == 1 ? '' : 's') . ". Move them to another category first (edit each course), then delete it.");
            ax_back();
        }
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        ax_flash('ok', "Category \"{$row['name']}\" deleted."); ax_back();
    }
    ax_flash('err', 'Unknown action.'); ax_back();
}

$cats = $pdo->query("SELECT cat.*,
        (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id) total,
        (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id AND c.status = 'published') live,
        (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id AND c.source IS NULL) paid,
        (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id AND c.source = 'course') free,
        (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id AND c.source = 'tutorial') tut,
        (SELECT COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.category_id = cat.id) learners
        FROM categories cat ORDER BY cat.name")->fetchAll();
$uncat = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM courses c LEFT JOIN categories k ON k.id = c.category_id WHERE k.id IS NULL");
$maxTotal = max(1, ...array_map(fn($c) => (int)$c['total'], $cats ?: [['total' => 1]]));
$swatches = ['#00BFFF', '#0080FF', '#FF8C00', '#10B981', '#6366F1', '#EC4899', '#8B5CF6', '#F59E0B', '#EF4444', '#14B8A6'];

function axcat_icon(?string $i): string { $i = trim((string)$i); return $i === '' ? 'fas fa-book' : (str_contains($i, ' ') ? $i : 'fas ' . $i); }

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; }
.axk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 290px), 1fr)); gap: 20px; }
.axk-card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 20px; display: flex; flex-direction: column; gap: 14px; box-shadow: var(--shadow-sm); min-width: 0; }
.axk-top { display: flex; gap: 14px; align-items: center; }
.axk-ic { width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.25rem; flex-shrink: 0; }
.axk-top h3 { font: 800 1.05rem 'Poppins', sans-serif; color: var(--text-main); margin: 0; overflow-wrap: anywhere; }
.axk-top small { color: var(--text-muted); font-size: .74rem; }
.axk-split { display: flex; flex-wrap: wrap; gap: 6px; }
.axk-f { display: flex; gap: 8px; align-items: center; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
.axk-f .sp { flex: 1; }
.axk-sw { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.axk-sw button { width: 28px; height: 28px; border-radius: 8px; border: 2px solid transparent; cursor: pointer; }
.axk-sw button.on { border-color: var(--text-main); }
.axk-prev { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 14px; background: var(--surface-2); margin-bottom: 14px; }
.axk-prev b { color: var(--text-main); }
.axk-color { display: flex; gap: 10px; align-items: center; }
.axk-color input[type=color] { width: 46px; height: 42px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); padding: 3px; cursor: pointer; }
</style>
<main class="main-content">
    <?php ax_header('Course', 'Categories', 'Group courses by subject', '<button class="ax-btn" type="button" data-cat-new><i class="fas fa-plus"></i> New category</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('tags', count($cats), 'Categories') ?>
            <?= ax_stat('book', array_sum(array_column($cats, 'total')), 'Courses in a category', array_sum(array_column($cats, 'live')) . ' live', 'green') ?>
            <?= ax_stat('circle-question', $uncat, 'Uncategorised courses', $uncat ? 'Give them a category on the Courses page' : 'Every course has a category', $uncat ? 'amber' : 'slate') ?>
        </div>
        <p class="ax-intro">Categories group courses on the catalogue and filters. A category that still has courses can't be deleted – move its courses first. Library courses get their category from the <span class="ax-code">category</span> field in their JSON file, so keep names spelled the same.</p>

        <?php if (!$cats): ?>
            <div class="ax-panel"><div class="ax-empty"><i class="fas fa-tags"></i><b>No categories yet</b>Add your first one with “New category”.</div></div>
        <?php else: ?>
        <div class="axk-grid">
            <?php foreach ($cats as $c): $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string)$c['color']) ? $c['color'] : '#00BFFF'; ?>
            <article class="axk-card">
                <div class="axk-top">
                    <span class="axk-ic" style="background:<?= e($color) ?>"><i class="<?= e(axcat_icon($c['icon'])) ?>"></i></span>
                    <div style="min-width:0"><h3><?= e($c['name']) ?></h3><small><span class="ax-code"><?= e($c['slug']) ?></span></small></div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:6px"><b style="color:var(--text-main)"><?= (int)$c['total'] ?> course<?= (int)$c['total'] === 1 ? '' : 's' ?></b><span style="color:var(--text-muted)"><?= (int)$c['live'] ?> live · <?= (int)$c['learners'] ?> learners</span></div>
                    <div class="ax-bar"><i style="width:<?= round((int)$c['total'] / $maxTotal * 100) ?>%;background:<?= e($color) ?>"></i></div>
                </div>
                <div class="axk-split">
                    <?php if ($c['paid']): ?><span class="ax-pill info"><?= (int)$c['paid'] ?> paid</span><?php endif; ?>
                    <?php if ($c['free']): ?><span class="ax-pill violet"><?= (int)$c['free'] ?> free skills</span><?php endif; ?>
                    <?php if ($c['tut']): ?><span class="ax-pill ok"><?= (int)$c['tut'] ?> tutorials</span><?php endif; ?>
                    <?php if (!$c['total']): ?><span class="ax-pill">Empty</span><?php endif; ?>
                </div>
                <div class="axk-f">
                    <a class="ax-btn ghost sm" href="courses?cat=<?= (int)$c['id'] ?>"><i class="fas fa-book"></i> View courses</a>
                    <span class="sp"></span>
                    <button type="button" class="ax-btn ghost sm icon" title="Edit" aria-label="Edit <?= e($c['name']) ?>" data-cat-edit="<?= e(json_encode(['id' => (int)$c['id'], 'name' => $c['name'], 'icon' => axcat_icon($c['icon']), 'color' => $color])) ?>"><i class="fas fa-pen"></i></button>
                    <?php if ((int)$c['total'] > 0): ?>
                        <button type="button" class="ax-btn danger sm icon" disabled title="Has <?= (int)$c['total'] ?> course(s) – move them first" aria-label="Can't delete: category has courses"><i class="fas fa-trash"></i></button>
                    <?php else: ?>
                        <form method="post" data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot;? This can't be undone." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="ax-btn danger sm icon" title="Delete" aria-label="Delete <?= e($c['name']) ?>"><i class="fas fa-trash"></i></button></form>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<dialog class="ax-modal" id="catModal" style="width:min(520px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h2 id="ck-title"><i class="fas fa-tag"></i> New category</h2><p>Shown on the catalogue and in course filters.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="ck-id">
            <div class="axk-prev"><span class="axk-ic" id="ck-prev-ic"><i class="fas fa-book"></i></span><div><b id="ck-prev-name">Category name</b><div style="font-size:.76rem;color:var(--text-muted)">Preview</div></div></div>
            <div class="ax-field"><label for="ck-name">Name</label><input type="text" id="ck-name" name="name" required maxlength="100" placeholder="e.g. Digital Marketing"></div>
            <div class="ax-field"><label for="ck-icon">Icon</label><input type="text" id="ck-icon" name="icon" maxlength="50" placeholder="fas fa-laptop-code"><div class="hint">A Font Awesome 6 class, e.g. <span class="ax-code">fas fa-palette</span>, <span class="ax-code">fas fa-briefcase</span>.</div></div>
            <div class="ax-field"><label for="ck-color">Colour</label>
                <div class="axk-color"><input type="color" id="ck-color" name="color" value="#00BFFF"><span class="ax-code" id="ck-color-v">#00BFFF</span></div>
                <div class="axk-sw"><?php foreach ($swatches as $s): ?><button type="button" style="background:<?= $s ?>" data-sw="<?= $s ?>" aria-label="Colour <?= $s ?>"></button><?php endforeach; ?></div>
            </div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn" id="ck-submit"><i class="fas fa-floppy-disk"></i> Save category</button></div>
        </div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    var d = document.getElementById('catModal'), $ = function (i) { return document.getElementById(i); };
    function paint() {
        var c = $('ck-color').value;
        $('ck-color-v').textContent = c.toUpperCase();
        $('ck-prev-ic').style.background = c;
        $('ck-prev-ic').innerHTML = '<i class="' + ($('ck-icon').value.replace(/[^a-z0-9 -]/gi, '') || 'fas fa-book') + '"></i>';
        $('ck-prev-name').textContent = $('ck-name').value || 'Category name';
        document.querySelectorAll('[data-sw]').forEach(function (b) { b.classList.toggle('on', b.dataset.sw.toLowerCase() === c.toLowerCase()); });
    }
    function open(c) {
        $('ck-id').value = c ? c.id : '';
        $('ck-name').value = c ? c.name : '';
        $('ck-icon').value = c ? c.icon : 'fas fa-book';
        $('ck-color').value = (c && /^#[0-9a-f]{6}/i.test(c.color)) ? c.color.slice(0, 7).toLowerCase() : '#00bfff';
        $('ck-title').innerHTML = c ? '<i class="fas fa-pen"></i> Edit category' : '<i class="fas fa-tag"></i> New category';
        paint(); d.showModal();
    }
    ['ck-name', 'ck-icon', 'ck-color'].forEach(function (i) { $(i).addEventListener('input', paint); });
    document.querySelectorAll('[data-sw]').forEach(function (b) { b.addEventListener('click', function () { $('ck-color').value = b.dataset.sw.toLowerCase(); paint(); }); });
    document.querySelectorAll('[data-cat-new]').forEach(function (b) { b.addEventListener('click', function () { open(null); }); });
    document.querySelectorAll('[data-cat-edit]').forEach(function (b) { b.addEventListener('click', function () { open(JSON.parse(b.dataset.catEdit)); }); });
})();
</script>
</body></html>
