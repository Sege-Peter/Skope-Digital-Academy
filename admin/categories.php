<?php
$pageTitle = 'Curriculum Taxonomy Governance';
require_once 'includes/header.php';

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
                $message = "Knowledge domain '{$name}' synchronized successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, color) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $icon, $color]);
                $message = "New knowledge domain '{$name}' integrated into taxonomy.";
            }
        } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
    } elseif (isset($_POST['delete_category'])) {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Domain archived and removed from institutional taxonomy.";
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

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Institutional <span>Taxonomy</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Knowledge Domain Curation • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <button class="btn-premium" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Create New Domain
            </button>
        </div>
    </header>

    <div class="admin-body">
        <div class="dash-stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
            <?php foreach($categories as $c): ?>
            <div class="premium-card" style="display: flex; flex-direction: column; gap: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="width: 54px; height: 54px; border-radius: 16px; background: <?= $c['color'] ?: 'var(--primary)' ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; box-shadow: 0 8px 20px -4px <?= $c['color'] ?>44;">
                        <i class="<?= $c['icon'] ?: 'fas fa-book' ?>"></i>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button onclick='editCategory(<?= json_encode($c) ?>)' class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border-color: var(--border); color: var(--text-dim); cursor: pointer;" title="Refine Metadata">
                            <i class="fas fa-pen-nib" style="font-size: 0.75rem;"></i>
                        </button>
                        <button onclick="confirmDelete(<?= $c['id'] ?>)" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border-color: var(--border); color: var(--danger); cursor: pointer;" title="Archive Domain">
                            <i class="fas fa-trash-alt" style="font-size: 0.75rem;"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;"><?= htmlspecialchars($c['name']) ?></h3>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="badge-premium" style="background: var(--bg-main); color: var(--text-muted); font-size: 0.65rem; border: 1.5px solid var(--border-light);">
                            <?= $c['course_count'] ?> ACTIVE TRACKS
                        </span>
                    </div>
                </div>

                <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Institutional Reach</div>
                    <div class="velocity-line" style="flex: 1; margin: 0 16px; height: 4px; background: var(--bg-main); border-radius: 2px; overflow: hidden;">
                        <div style="width: <?= min(100, $c['course_count'] * 10) ?>%; height: 100%; background: <?= $c['color'] ?>; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="premium-card" style="border: 2.5px dashed var(--border); background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 20px; min-height: 200px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.borderColor='var(--primary-glow)'; this.style.background='var(--bg-main)'" onmouseout="this.style.borderColor='var(--border)'; this.style.background='transparent'" onclick="openModal()">
                <div style="width: 54px; height: 54px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                    <i class="fas fa-plus"></i>
                </div>
                <div style="text-align: center;">
                    <div style="font-weight: 900; color: var(--text-main); font-size: 1.1rem; letter-spacing: -0.5px;">Expand Taxonomy</div>
                    <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Integrate a new knowledge domain</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Unified Taxonomy Modal -->
<div id="catModalOverlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <div class="premium-card" style="width: 100%; max-width: 540px; padding: 48px; animation: modal-pop 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light);">
            <div>
                <h2 id="modalTitle" style="font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px; margin: 0;">Create New Domain</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; font-weight: 600;">Define a new scholarly classification.</p>
            </div>
            <button onclick="closeModal()" class="avatar-sm" style="width: 40px; height: 40px; background: transparent; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="catForm" method="POST">
            <input type="hidden" name="save_category" value="1">
            <input type="hidden" id="catId" name="id">

            <div style="margin-bottom: 24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Domain Nomenclature</label>
                <input type="text" name="name" id="catName" required placeholder="e.g. Artificial Intelligence" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Symbolic Indicator (Icon)</label>
                <div style="position: relative;">
                    <input type="text" name="icon" id="catIcon" value="fas fa-rocket" style="width:100%;padding:14px 20px 14px 50px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
                    <i class="fas fa-icons" style="position: absolute; left: 20px; top: 16px; color: var(--primary); opacity: 0.5;"></i>
                </div>
            </div>

            <div style="margin-bottom: 32px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Brand Aesthetic Color</label>
                <input type="text" name="color" id="catColorInput" value="#00BFFF" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:700;outline:none;box-sizing:border-box;margin-bottom:16px;">
                <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px;">
                    <?php 
                    $swatches = ['#00BFFF', '#FF8C00', '#10B981', '#6366F1', '#EC4899', '#8B5CF6', '#F59E0B', '#EF4444'];
                    foreach($swatches as $s): ?>
                        <div onclick="setColor('<?= $s ?>', this)" style="aspect-ratio: 1; border-radius: 8px; background: <?= $s ?>; cursor: pointer; transition: 0.2s; border: 2px solid transparent;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 16px;">
                <button type="submit" class="btn-premium" style="flex: 2; justify-content: center; height: 56px; font-size: 1rem;">Execute Integration</button>
                <button type="button" class="btn-premium" onclick="closeModal()" style="flex: 1; background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; height: 56px; justify-content: center;">Abort</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modal-pop { 0% { transform: scale(0.9) translateY(20px); opacity: 0; } 100% { transform: scale(1) translateY(0); opacity: 1; } }
</style>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($message): ?>
            SDA.showToast("<?= $message ?>", "<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>");
        <?php endif; ?>
    });

    function openModal() {
        const modal = document.getElementById('catModalOverlay');
        document.getElementById('modalTitle').innerText = 'Create New Domain';
        document.getElementById('catName').value = '';
        document.getElementById('catIcon').value = 'fas fa-rocket';
        document.getElementById('catColorInput').value = '#00BFFF';
        document.getElementById('catId').value = '';
        modal.style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('catModalOverlay').style.display = 'none';
    }

    function editCategory(cat) {
        const modal = document.getElementById('catModalOverlay');
        document.getElementById('modalTitle').innerText = 'Refine Knowledge Domain';
        document.getElementById('catName').value = cat.name;
        document.getElementById('catIcon').value = cat.icon || 'fas fa-book';
        document.getElementById('catColorInput').value = cat.color || '#00BFFF';
        document.getElementById('catId').value = cat.id;
        modal.style.display = 'flex';
    }

    function confirmDelete(id) {
        SDA.confirmAction("This will permanently archive this domain and detach it from all associated academic tracks. Continue?", () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="delete_category" value="1">`;
            document.body.appendChild(form);
            form.submit();
        });
    }

    function setColor(color, el) {
        document.getElementById('catColorInput').value = color;
        // visual feedback
        el.parentElement.querySelectorAll('div').forEach(s => s.style.borderColor = 'transparent');
        el.style.borderColor = 'var(--text-main)';
    }

    window.onclick = function(e) {
        const modal = document.getElementById('catModalOverlay');
        if(e.target == modal) closeModal();
    }

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
