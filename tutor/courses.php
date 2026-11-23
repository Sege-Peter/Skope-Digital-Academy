<?php
/**
 * Tutor: my courses. Create/edit title, description, category, level, thumbnail and draft/review status.
 * Pricing and publishing belong to the admin (admin/course-pricing.php); a live course stays live.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    $id = (int)($_POST['id'] ?? 0) ?: null;
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $level = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $_POST['level'] : 'beginner';
    $cat_id = (int)($_POST['category_id'] ?? 0);

    if (!verifyCsrf()) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif ($title === '') {
        $error = 'Give the course a title.';
    } else {
        $current_status = null;
        if ($id) {
            $cs = $pdo->prepare("SELECT status FROM courses WHERE id = ? AND tutor_id = ?");
            $cs->execute([$id, $tid]);
            $current_status = $cs->fetchColumn() ?: null;
            if ($current_status === null) { td_flash('err', 'That course was not found.'); td_redirect('courses'); }
        }
        $allowed = ['draft', 'pending'];
        if ($current_status === 'published') $allowed[] = 'published';
        $status = in_array($_POST['status'] ?? '', $allowed, true) ? $_POST['status'] : 'draft';
        $slug = trim(strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)), '-');

        try {
            if ($id) {
                $pdo->prepare("UPDATE courses SET title=?, slug=?, description=?, level=?, category_id=?, status=? WHERE id=? AND tutor_id=?")
                    ->execute([$title, $slug, $desc, $level, $cat_id ?: null, $status, $id, $tid]);
                $msg = 'Course saved.';
            } else {
                $pdo->prepare("INSERT INTO courses (title, slug, description, price, level, category_id, tutor_id, status) VALUES (?, ?, ?, 0, ?, ?, ?, ?)")
                    ->execute([$title, $slug, $desc, $level, $cat_id ?: null, $tid, $status]);
                $id = (int)$pdo->lastInsertId();
                $msg = 'Course created. Next, add some lessons.';
            }

            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['thumbnail'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $info = @getimagesize($file['tmp_name']);
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !$info) {
                    $msg .= ' The cover image was not saved: use a JPG, PNG or WebP picture.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $msg .= ' The cover image was not saved: keep it under 5 MB.';
                } else {
                    $dir = __DIR__ . '/../uploads/courses/';
                    if (!is_dir($dir)) mkdir($dir, 0775, true);
                    $filename = 'COURSE_' . $id . '.' . $ext;
                    foreach (glob($dir . 'COURSE_' . $id . '.*') ?: [] as $old) @unlink($old);
                    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                        $pdo->prepare("UPDATE courses SET thumbnail=? WHERE id=? AND tutor_id=?")->execute([$filename, $id, $tid]);
                    }
                }
            }
            td_flash('ok', $msg);
            td_redirect('courses');
        } catch (Throwable $e) {
            error_log('tutor course save: ' . $e->getMessage());
            $error = 'The course could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Courses';
$pageSub = 'Build and manage your courses. Prices and publishing are set by the academy admin.';
$pageActions = '<button type="button" class="tp-btn primary" data-open="courseModal"><i class="fas fa-plus"></i> New course</button>';
require_once 'includes/header.php';

try {
    $my_courses = td_courses($pdo, $tid);
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
} catch (Throwable $e) { $my_courses = []; $categories = []; }

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tutor_id = ?");
    $stmt->execute([(int)$_GET['edit_id'], $tid]);
    $edit_data = $stmt->fetch() ?: null;
}
$openModal = $edit_data || ($_GET['action'] ?? '') === 'new' || $error;
if ($error && !$edit_data && !empty($_POST)) $edit_data = ['id' => $_POST['id'] ?? '', 'title' => $_POST['title'] ?? '', 'description' => $_POST['description'] ?? '', 'category_id' => $_POST['category_id'] ?? '', 'level' => $_POST['level'] ?? '', 'status' => $_POST['status'] ?? 'draft', 'price' => 0];

$counts = ['published' => 0, 'pending' => 0, 'draft' => 0, 'archived' => 0];
foreach ($my_courses as $c) $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
?>
<?php if ($error): ?><div class="tp-alert err"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div><?php endif; ?>

<?php if ($my_courses): ?>
    <p class="tp-note" style="margin-bottom:14px"><?= count($my_courses) ?> course<?= count($my_courses) === 1 ? '' : 's' ?>: <?= $counts['published'] ?> live, <?= $counts['pending'] ?> in review, <?= $counts['draft'] ?> draft<?= $counts['archived'] ? ', ' . $counts['archived'] . ' archived' : '' ?>.</p>
    <div class="tp-grid cards">
        <?php foreach ($my_courses as $c):
            [$sl, $sc] = td_course_status($c['status']);
            $thumb = td_upload_url('courses', $c['thumbnail']);
            $initials = td_initials($c['title']);
        ?>
        <article class="tp-card tp-course">
            <div class="tp-cover">
                <?php if ($thumb): ?>
                    <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <div class="tp-cover-gen" style="<?= td_cover_style($c['category_name'], (int)$c['id']) ?>"><b><?= e($initials) ?></b><span><?= e($c['category_name'] ?: 'Course') ?></span></div>
                <?php endif; ?>
                <span class="tp-pill <?= $sc ?>"><?= $sl ?></span>
            </div>
            <div class="tp-course-body">
                <div>
                    <h3><?= e($c['title']) ?></h3>
                    <div class="tp-course-meta" style="margin-top:6px">
                        <span><?= e(ucfirst($c['level'] ?? 'beginner')) ?></span>
                        <span><?= (float)$c['price'] > 0 ? e(td_money((float)$c['price'])) : 'Free' ?></span>
                        <?php if ((int)$c['rating_count']): ?><span><i class="fas fa-star" style="color:#f59e0b"></i> <?= number_format((float)$c['rating_avg'], 1) ?> (<?= (int)$c['rating_count'] ?>)</span><?php endif; ?>
                        <?php if ($c['status'] === 'published'): ?><a href="../course-details?id=<?= (int)$c['id'] ?>" target="_blank" rel="noopener">Public page <i class="fas fa-arrow-up-right-from-square" style="font-size:.7em"></i></a><?php endif; ?>
                    </div>
                </div>
                <div class="tp-mini-stats">
                    <div><b><?= (int)$c['learner_count'] ?></b><small>Learners</small></div>
                    <div><b><?= (int)$c['lesson_count'] ?></b><small>Lessons</small></div>
                    <div><b><?= $c['avg_progress'] === null ? '—' : round((float)$c['avg_progress']) . '%' ?></b><small>Avg. progress</small></div>
                </div>
                <div class="tp-course-actions">
                    <a class="tp-btn sm" href="courses?edit_id=<?= (int)$c['id'] ?>" aria-label="Edit details of <?= e($c['title']) ?>"><i class="fas fa-pen"></i> Edit</a>
                    <a class="tp-btn sm primary" href="lessons?course_id=<?= (int)$c['id'] ?>" aria-label="Manage lessons of <?= e($c['title']) ?>"><i class="fas fa-layer-group"></i> Lessons</a>
                    <a class="tp-btn sm" href="analytics?course_id=<?= (int)$c['id'] ?>" aria-label="Insights for <?= e($c['title']) ?>"><i class="fas fa-chart-column"></i> Insights</a>
                </div>
                <div class="tp-course-meta">
                    <a href="quizzes?course_id=<?= (int)$c['id'] ?>"><?= (int)$c['quiz_count'] ?> quiz<?= (int)$c['quiz_count'] === 1 ? '' : 'zes' ?></a>
                    <a href="assignments?course_id=<?= (int)$c['id'] ?>"><?= (int)$c['assignment_count'] ?> assignment<?= (int)$c['assignment_count'] === 1 ? '' : 's' ?></a>
                    <?php if ((int)$c['pending_count']): ?><span><?= (int)$c['pending_count'] ?> pending enrolment<?= (int)$c['pending_count'] === 1 ? '' : 's' ?></span><?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="tp-card"><div class="tp-empty" style="padding:56px 22px">
        <i class="fas fa-book-open"></i>
        <h3>You have no courses yet</h3>
        <p>Start with a title and a short description. You can add lessons, quizzes and assignments next, then send the course for review.</p>
        <div class="tp-actions"><button type="button" class="tp-btn primary" data-open="courseModal"><i class="fas fa-plus"></i> New course</button></div>
    </div></div>
<?php endif; ?>

<div class="tp-modal" id="courseModal" role="dialog" aria-modal="true" aria-labelledby="courseModalTitle"<?= $openModal ? ' data-autoopen' : '' ?>>
    <div class="tp-modal-box">
        <div class="tp-modal-head">
            <div>
                <h2 id="courseModalTitle"><?= !empty($edit_data['id']) ? 'Edit course' : 'New course' ?></h2>
                <p>The title, description and cover image are what learners see first.</p>
            </div>
            <button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="tp-modal-body">
            <form method="post" action="courses" enctype="multipart/form-data" class="tp-form">
                <input type="hidden" name="id" value="<?= e((string)($edit_data['id'] ?? '')) ?>">
                <input type="hidden" name="save_course" value="1">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="tp-field">
                    <label for="c-title">Course title</label>
                    <input id="c-title" type="text" name="title" maxlength="255" placeholder="e.g. JavaScript Foundations" value="<?= e($edit_data['title'] ?? '') ?>" required>
                </div>
                <div class="tp-field">
                    <label for="c-desc">Description</label>
                    <textarea id="c-desc" name="description" placeholder="What will learners be able to do after this course?"><?= e($edit_data['description'] ?? '') ?></textarea>
                </div>
                <div class="tp-form-row">
                    <div class="tp-field">
                        <label for="c-cat">Category</label>
                        <select id="c-cat" name="category_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= (string)($edit_data['category_id'] ?? '') === (string)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tp-field">
                        <label for="c-level">Level</label>
                        <select id="c-level" name="level">
                            <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($edit_data['level'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="tp-form-row">
                    <div class="tp-field">
                        <label>Price</label>
                        <input type="text" readonly value="<?= !empty($edit_data['id']) ? ((float)$edit_data['price'] > 0 ? e(td_money((float)$edit_data['price'])) . ' · ' : 'Free · ') : '' ?>set by the admin">
                    </div>
                    <div class="tp-field">
                        <label for="c-status">Status</label>
                        <select id="c-status" name="status">
                            <option value="draft" <?= ($edit_data['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (only you can see it)</option>
                            <option value="pending" <?= ($edit_data['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Send for review</option>
                            <?php if (($edit_data['status'] ?? '') === 'published'): ?><option value="published" selected>Live</option><?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="tp-field">
                    <label for="c-thumb">Cover image <span class="tp-hint">(optional · JPG, PNG or WebP, up to 5 MB)</span></label>
                    <input id="c-thumb" type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <span class="tp-hint">Without one, a coloured cover with the course initials is shown.</span>
                </div>
                <div class="tp-modal-foot">
                    <a class="tp-btn" href="courses" data-close>Cancel</a>
                    <button type="submit" class="tp-btn primary"><i class="fas fa-check"></i> <?= !empty($edit_data['id']) ? 'Save changes' : 'Create course' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
