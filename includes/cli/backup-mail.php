<?php
/**
 * Off-server backup copy: emails an encrypted backup file to the academy mailbox.
 *   php includes/cli/backup-mail.php /path/to/file.enc   (run by /usr/local/sbin/sda-backup as www-data)
 * Also records the time of the last good backup (setting backup_last_ok) for the health check.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../mailer.php';

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) { fwrite(STDERR, "backup file missing\n"); exit(1); }
save_setting($pdo, 'backup_last_ok', (string)time());
$to = (sda_mail_account()['address'] ?? '') ?: 'skopedigitalacademy@gmail.com';
$size = filesize($file);
$name = basename($file);
$html = '<h2 style="margin:0 0 12px">Daily backup: ' . date('j M Y') . '</h2>'
      . '<p>The encrypted database backup is attached (' . number_format($size / 1048576, 2) . ' MB). Keep this email; the server keeps its own copies for 14 days.</p>'
      . '<p>To restore it you need the backup passphrase. See <b>SECURITY.md</b> in the project for the steps.</p>';
$opts = [];
if ($size <= 18 * 1048576) $opts['attachments'] = [['name' => $name, 'type' => 'application/octet-stream', 'data' => file_get_contents($file)]];
else $html .= '<p><b>The file is too big to attach</b>; it is on the server in /var/backups/sda.</p>';
$err = '';
$ok = sda_mail($to, 'Backup ' . date('Y-m-d') . ' – Skope Digital Academy', $html, '', $err, $opts);
echo $ok ? "mailed $name to $to\n" : "mail failed: $err\n";
exit($ok ? 0 : 2);
