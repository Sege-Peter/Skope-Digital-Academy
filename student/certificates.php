<?php
$pageTitle = 'Certificates';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/learning.php'; // money()

try {
    // All of the student's certificates: course and learning-path ones, unlocked or awaiting the fee
    $stmt = $pdo->prepare("SELECT c.*, co.title as course_title, co.thumbnail, co.level, u.name as tutor_name, p.title AS path_title
                           FROM certificates c
                           LEFT JOIN courses co ON c.course_id = co.id
                           LEFT JOIN users u ON co.tutor_id = u.id
                           LEFT JOIN learning_paths p ON p.id = c.path_id
                           WHERE c.student_id = ?
                           ORDER BY c.status = 'pending' DESC, c.issued_at DESC");
    $stmt->execute([$student['id']]);
    $certs = $stmt->fetchAll();
} catch (Exception $e) { error_log($e->getMessage()); $certs = []; }

$count_unlocked = count(array_filter($certs, fn($c) => $c['status'] === 'approved'));
$count_pending  = count(array_filter($certs, fn($c) => $c['status'] === 'pending'));

require_once 'includes/layout-top.php';
?>

<style>
.pg-certs .cert-thumb { position: relative; aspect-ratio: 16 / 8; background: var(--grad-dark); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.pg-certs .cert-thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .3; }
.pg-certs .cert-thumb > i { position: relative; font-size: 2.6rem; color: #FFC53D; }
.pg-certs .cert-thumb.locked > i { color: rgba(255,255,255,.75); }
.pg-certs .cert-thumb .sp-badge { position: absolute; top: 10px; left: 10px; background: rgba(255,255,255,.92); }
.pg-certs .cert-code { font-size: .74rem; color: var(--text-dim); font-family: ui-monospace, Consolas, monospace; }
.pg-certs .cert-foot { margin-top: auto; display: flex; gap: 8px; flex-wrap: wrap; }
.pg-certs .cert-foot .sp-btn:first-child { flex: 1; }
</style>

<div class="pg-certs">

<div class="sp-page-head">
    <div>
        <h1>Certificates</h1>
        <p>Certificates you earn by finishing courses and learning paths. View, print or share them here.</p>
    </div>
    <div class="sp-actions">
        <a href="transcript" class="sp-btn ghost"><i class="fas fa-file-lines"></i> Transcript</a>
    </div>
</div>

<section class="sp-grid cols-3 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon violet"><i class="fas fa-certificate"></i></span><div><strong><?= count($certs) ?></strong><span>Certificates earned</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-lock-open"></i></span><div><strong><?= $count_unlocked ?></strong><span>Ready to view</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon yellow"><i class="fas fa-lock"></i></span><div><strong><?= $count_pending ?></strong><span>Waiting for the fee</span></div></div>
</section>

<?php if ($count_pending): ?>
<div class="sp-alert warning">
    <i class="fas fa-lock" style="margin-top: 3px;"></i>
    <div><strong><?= $count_pending === 1 ? 'One certificate is' : $count_pending . ' certificates are' ?> waiting for the certificate fee.</strong>
    Pay the fee to unlock <?= $count_pending === 1 ? 'it' : 'them' ?>. If you have already paid, it will unlock as soon as we confirm your payment.</div>
</div>
<?php endif; ?>

<?php if (!empty($certs)): ?>
    <div class="sp-grid cols-3">
    <?php foreach ($certs as $c):
        $isPath    = ($c['certificate_type'] ?? 'course') === 'path';
        $title     = $isPath ? ($c['path_title'] ?: 'Learning path') : ($c['course_title'] ?: 'Course');
        $status    = $c['status'];
        $code      = (string)$c['verification_code'];
        $codeQ     = rawurlencode($code);
        // view-certificate only knows course certificates; path ones use the public page
        $viewHref  = ($isPath || empty($c['course_id'])) ? '../certificate?code=' . $codeQ : 'view-certificate?id=' . (int)$c['id'];
    ?>
    <div class="sp-card flush" style="display: flex; flex-direction: column;">
        <div class="cert-thumb<?= $status !== 'approved' ? ' locked' : '' ?>">
            <?php if (!empty($c['thumbnail'])): ?><img src="../uploads/courses/<?= htmlspecialchars($c['thumbnail']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
            <i class="fas <?= $status === 'approved' ? 'fa-award' : ($status === 'pending' ? 'fa-lock' : 'fa-ban') ?>"></i>
            <?php if ($status === 'approved'): ?>
                <span class="sp-badge green"><i class="fas fa-check"></i> Unlocked</span>
            <?php elseif ($status === 'pending'): ?>
                <span class="sp-badge yellow"><i class="fas fa-lock"></i> Fee not paid</span>
            <?php else: ?>
                <span class="sp-badge red">Revoked</span>
            <?php endif; ?>
        </div>
        <div class="sp-course-body">
            <div>
                <span class="sp-muted sp-small"><?= $isPath ? 'Learning path' : 'Course' ?> certificate</span>
                <h3><?= htmlspecialchars($title) ?></h3>
            </div>
            <div class="sp-course-meta">
                <?php if (!empty($c['tutor_name'])): ?><span><i class="fas fa-chalkboard-user"></i> <?= htmlspecialchars($c['tutor_name']) ?></span><?php endif; ?>
                <span><i class="far fa-calendar"></i> <?= !empty($c['issued_at']) ? date('j M Y', strtotime($c['issued_at'])) : '—' ?></span>
                <?php if (isset($c['score']) && $c['score'] !== null): ?><span><i class="fas fa-percent"></i> Score <?= (int)$c['score'] ?>%</span><?php endif; ?>
            </div>

            <?php if ($status === 'approved'): ?>
                <div class="cert-code">Code: <?= htmlspecialchars($code) ?></div>
                <div class="cert-foot">
                    <a href="<?= htmlspecialchars($viewHref) ?>" class="sp-btn sm"><i class="fas fa-eye"></i> View</a>
                    <?php if (!$isPath && !empty($c['course_id'])): ?>
                    <a href="view-certificate?id=<?= (int)$c['id'] ?>&amp;print=1" class="sp-btn sm ghost" title="Print or save as PDF" aria-label="Print or save as PDF"><i class="fas fa-download"></i></a>
                    <?php endif; ?>
                    <button type="button" class="sp-btn sm ghost" data-copy="<?= htmlspecialchars($codeQ) ?>" title="Copy share link" aria-label="Copy share link"><i class="fas fa-link"></i></button>
                </div>
            <?php elseif ($status === 'pending'): ?>
                <p class="sp-small sp-muted" style="margin: 0;">You finished this <?= $isPath ? 'path' : 'course' ?>. Pay the certificate fee<?= (float)$c['fee'] > 0 ? ' of <strong style="color: var(--text-main);">' . htmlspecialchars(money((float)$c['fee'])) . '</strong>' : '' ?> to unlock it.</p>
                <div class="cert-foot">
                    <a href="../certificate-pay?code=<?= htmlspecialchars($codeQ) ?>" class="sp-btn sm orange"><i class="fas fa-lock-open"></i> Pay<?= (float)$c['fee'] > 0 ? ' ' . htmlspecialchars(money((float)$c['fee'])) : '' ?> to unlock</a>
                </div>
            <?php else: ?>
                <p class="sp-small sp-muted" style="margin: 0;">This certificate was revoked and is no longer valid. Contact support if you think this is a mistake.</p>
                <div class="cert-foot"><a href="support" class="sp-btn sm ghost">Contact support</a></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="sp-empty">
        <div class="sp-ico"><i class="fas fa-award"></i></div>
        <h3>No certificates yet</h3>
        <p>Finish every lesson in a course (and pass its final quiz) to earn a certificate you can print and share.</p>
        <a href="courses" class="sp-btn">Continue learning</a>
    </div>
<?php endif; ?>

</div><!-- /.pg-certs -->

<script>
document.querySelectorAll('.pg-certs [data-copy]').forEach(btn => btn.addEventListener('click', async () => {
    const url = new URL('../certificate?code=' + btn.dataset.copy, location.href).href;
    try {
        await navigator.clipboard.writeText(url);
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-link"></i>'; }, 1600);
    } catch (e) { prompt('Copy this link:', url); }
}));
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
