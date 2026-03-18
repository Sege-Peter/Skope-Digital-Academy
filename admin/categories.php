<?php
$pageTitle = 'Curriculum Categories';
require_once '../includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle Add/Edit/Delete Category
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_category'])) {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name']);
        $slug  = strtolower(str_replace(' ', '-', $name));
        $icon  = trim($_POST['icon'] ?? 'fas fa-book');
        $color = $_POST['color'] ?? '#00BFFF';

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, color = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $icon, $color, $id]);
                $message = "Category '{$name}' updated successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, color) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $icon, $color]);
                $message = "New category '{$name}' created successfully.";
            }
        } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
    } elseif (isset($_POST['delete_category'])) {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Category archived and removed from curriculum.";
        } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
    }
}

try {
    // Fetch categories with course counts
    $stmt = $pdo->query("SELECT cat.*, (SELECT COUNT(*) FROM courses WHERE category_id = cat.id) as course_count 
                         FROM categories cat ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) { $categories = []; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px; }
    .cat-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; transition: 0.3s; position: relative; overflow: hidden; }
    .cat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow); border-color: var(--primary); }
    
    .cat-icon-lg { width: 64px; height: 64px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 24px; color: white; box-shadow: 0 8px 16px -4px rgba(0,0,0,0.1); }
    .cat-name { font-family: 'Poppins', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
    .cat-stats { font-size: 0.85rem; color: var(--text-dim); display: flex; gap: 16px; align-items: center; }
    .cat-stats span { display: flex; align-items: center; gap: 6px; }

    .add-cat-card { border: 2px dashed var(--primary); background: #e0f2fe; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: var(--primary); min-height: 220px; border-radius: 32px; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .add-cat-card i { background: var(--primary); color: white; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0, 191, 255, 0.4); }
    .add-cat-card span { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.1rem; }
    .add-cat-card:hover { background: #bae6fd; transform: scale(1.02); box-shadow: 0 10px 25px -5px rgba(0, 191, 255, 0.2); }

    /* Fading Modal System */
    .modal-overlay { 
        position: fixed; inset: 0; 
        background: rgba(15, 23, 42, 0.85); 
        backdrop-filter: blur(12px); 
        z-index: 2000; 
        display: flex; align-items: center; justify-content: center; 
        opacity: 0; visibility: hidden; 
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .modal-overlay.open { opacity: 1; visibility: visible; }
    
    .cat-modal { 
        background: white; width: 95%; max-width: 500px; 
        border-radius: 32px; padding: 48px; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
        transform: scale(0.9) translateY(20px); 
        transition: 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .modal-overlay.open .cat-modal { transform: scale(1) translateY(0); }

    .form-group { margin-bottom: 24px; }
    .form-group label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px; }
    .form-input { width: 100%; padding: 14px 20px; border-radius: 14px; border: 1px solid #e2e8f0; font-family: var(--font); font-size: 0.95rem; transition: 0.3s; background: #f8fafc; }
    .form-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px var(--primary-glow); background: white; }

    .color-swatch-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
    .color-swatch { width: 36px; height: 36px; border-radius: 10px; cursor: pointer; border: 2px solid transparent; transition: 0.2s; box-shadow: var(--shadow-sm); }
    .color-swatch:hover { transform: scale(1.2); z-index: 2; }
    .color-swatch.active { border-color: white; box-shadow: 0 0 0 2px var(--dark); }

    @media (max-width: 768px) { 
        .admin-header { flex-direction: column; align-items: flex-start !important; gap: 24px; }
        .admin-header .btn { width: 100%; justify-content: center; padding: 14px; border-radius: 12px; }
        .cat-grid { grid-template-columns: 1fr; }
        .main-content { padding: 30px 20px; }
        .cat-modal { padding: 32px 24px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Taxonomy <span class="text-primary">Management</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Organize the academy's knowledge repository into curated categories.</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-primary btn-sm" onclick="openModal()"><i class="fas fa-plus"></i> New Category</button>
        </div>
    </header>


    <div class="cat-grid">
        <?php foreach($categories as $c): ?>
        <div class="cat-card">
            <div class="cat-icon-lg" style="background: <?= $c['color'] ?: 'var(--primary)' ?>">
                <i class="<?= $c['icon'] ?: 'fas fa-book' ?>"></i>
            </div>
            <h3 class="cat-name"><?= htmlspecialchars($c['name']) ?></h3>
            <div class="cat-stats">
                <span><i class="fas fa-layer-group"></i> <?= $c['course_count'] ?> Courses</span>
                <div style="display: flex; gap: 12px; margin-left: auto;">
                    <span class="text-primary" style="font-weight: 800; cursor: pointer; font-size: 0.8rem;" onclick='editCategory(<?= json_encode($c) ?>)'>Edit <i class="fas fa-edit"></i></span>
                    <button type="button" onclick="confirmDelete(<?= $c['id'] ?>)" style="background:none; border:none; color:var(--danger); font-weight:800; cursor:pointer; font-size:0.8rem; padding:0;">Delete <i class="fas fa-trash-alt"></i></button>
                </div>
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: <?= $c['color'] ?>; opacity: 0.4;"></div>
        </div>
        <?php endforeach; ?>

        <div class="cat-card add-cat-card" onclick="openModal()">
            <i class="fas fa-plus"></i>
            <span>Add Category</span>
        </div>
    </div>
</main>

<!-- Category Modal -->
<div class="modal-overlay" id="catModalOverlay">
    <form method="POST" action="categories.php" class="cat-modal">
        <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem; margin-bottom: 30px;" id="modalTitle">Create New Category</h2>
        
        <input type="hidden" name="save_category" value="1">
        <input type="hidden" id="catId" name="id">

        <div class="form-group">
            <label>Category Display Name</label>
            <input type="text" name="name" id="catName" class="form-input" placeholder="e.g. Artificial Intelligence" required>
        </div>

        <div class="form-group">
            <label>Icon Class (Font Awesome)</label>
            <div style="position: relative;">
                <input type="text" name="icon" id="catIcon" class="form-input" value="fas fa-rocket">
                <i class="fas fa-search" style="position: absolute; right: 15px; top: 14px; opacity: 0.3;"></i>
            </div>
        </div>

        <div class="form-group">
            <label>Brand Color Theme</label>
            <input type="text" name="color" id="catColorInput" class="form-input" value="#00BFFF">
            <div class="color-swatch-row">
                <?php 
                $swatches = ['#00BFFF', '#FF8C00', '#10B981', '#6366F1', '#EC4899', '#8B5CF6', '#F59E0B', '#EF4444'];
                foreach($swatches as $s): ?>
                    <div class="color-swatch" style="background: <?= $s ?>" onclick="setColor('<?= $s ?>')"></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display: flex; gap: 12px; margin-top: 40px;">
            <button type="button" class="btn btn-ghost" onclick="closeModal()" style="flex: 1;">Cancel</button>
            <button type="submit" class="btn btn-primary" style="flex: 2;">Save Category</button>
        </div>
    </form>
</div>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($message): ?>
            SDA.showToast("<?= $message ?>", "<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>");
        <?php endif; ?>
    });
    function openModal() {
        document.getElementById('catModalOverlay').classList.add('open');
        document.getElementById('modalTitle').innerText = 'Create New Category';
        document.getElementById('catName').value = '';
        document.getElementById('catIcon').value = 'fas fa-rocket';
        document.getElementById('catColorInput').value = '#00BFFF';
        document.getElementById('catId').value = '';
    }

    function closeModal() {
        document.getElementById('catModalOverlay').classList.remove('open');
    }

    function editCategory(cat) {
        document.getElementById('catModalOverlay').classList.add('open');
        document.getElementById('modalTitle').innerText = 'Edit Category';
        document.getElementById('catName').value = cat.name;
        document.getElementById('catIcon').value = cat.icon || 'fas fa-book';
        document.getElementById('catColorInput').value = cat.color || '#00BFFF';
        document.getElementById('catId').value = cat.id;
    }

    function confirmDelete(id) {
        SDA.confirmAction("This will permanently archive this category and detach it from all associated courses. Continue?", () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="delete_category" value="1">`;
            document.body.appendChild(form);
            form.submit();
        });
    }

    function setColor(color) {
        document.getElementById('catColorInput').value = color;
        // visual feedback
        document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
        event.target.classList.add('active');
    }

    // Close on backdrop
    window.onclick = function(e) {
        if(e.target == document.getElementById('catModalOverlay')) closeModal();
    }
</script>

</body>
</html>
