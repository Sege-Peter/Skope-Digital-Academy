<?php
/** Admin-only CV download. CVs live in uploads/careers/, which the web server refuses to serve directly. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/careers.php';
require_once __DIR__ . '/../includes/staff.php';
requireLogin();
if (!sda_staff_can_page($pdo, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''), 'career-cv.php')) { http_response_code(403); exit('Not allowed.'); }

$st = $pdo->prepare("SELECT cv_file, cv_name, name FROM job_applications WHERE id = ?");
$st->execute([(int)($_GET['id'] ?? 0)]);
$a = $st->fetch();
$path = $a && $a['cv_file'] ? sda_careers_dir() . '/' . basename($a['cv_file']) : '';
if (!$path || !is_file($path)) { http_response_code(404); exit('CV not found.'); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$name = preg_replace('/[^A-Za-z0-9._-]+/', '_', 'CV_' . $a['name']) . '.' . $ext;
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
