<?php
/**
 * Download a peer-project file: project-file?id=<submission id>.
 * Files live in uploads/projects under random names (direct access is denied by .htaccess);
 * only the author, staff, the course tutor, its reviewers and learners it can be handed to may open it.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/projects.php';

if (!isLoggedIn()) { header('Location: ' . sda_base_href() . 'login'); exit; }
$user = currentUser();
$st = $pdo->prepare("SELECT * FROM project_submissions WHERE id = ?");
$st->execute([(int)($_GET['id'] ?? 0)]);
$sub = $st->fetch();
$path = $sub && $sub['file'] && preg_match('/^[a-f0-9]{32}\.(pdf|png|jpg|docx)$/', $sub['file']) ? project_upload_dir() . '/' . $sub['file'] : null;
if (!$sub || !$path || !is_file($path) || !project_can_view_submission($pdo, $sub, $user)) {
    http_response_code(404);
    exit('File not found.');
}
$ext = pathinfo($path, PATHINFO_EXTENSION);
$types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$name = preg_replace('/[^\w .()-]+/', '_', (string)($sub['file_name'] ?: 'project.' . $ext));
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($ext === 'docx' ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
