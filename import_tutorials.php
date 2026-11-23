<?php
/**
 * Import the free tutorial library from content/tutorials/ into the database.
 *
 *   php import_tutorials.php          add/update tutorials and lessons
 *   php import_tutorials.php --dry    show what would change, write nothing
 *
 * Each tutorial becomes a free, published "open tutorial" course (courses.source = 'tutorial');
 * lessons are matched by lesson_progress-safe keys (lessons.source_key = "<slug>/<file>"), so
 * re-importing updates text in place and learners keep their progress. Lessons whose file was
 * removed are deleted. Format: content/README.md. Also runs from the browser for admins.
 */
require_once __DIR__ . '/includes/db.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Admins only.'); }
    header('Content-Type: text/plain; charset=utf-8');
}
$dry = in_array('--dry', $argv ?? [], true) || isset($_GET['dry']);
$root = __DIR__ . '/content/tutorials';

function fm_parse(string $raw): array {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $meta = [];
    if (preg_match('/^---\n(.*?)\n---\n?/s', $raw, $m)) {
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^([a-z_]+)\s*:\s*(.*)$/i', trim($line), $kv)) {
                $meta[strtolower($kv[1])] = trim($kv[2], " \t\"'");
            }
        }
        $raw = substr($raw, strlen($m[0]));
    }
    return [$meta, trim($raw)];
}

$owner = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn()
      ?: $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
if (!$owner) exit("No admin user to own the tutorials.\n");
// Library content whose owner account was deleted is handed to the current admin owner.
if (!$dry) {
    $pdo->prepare("UPDATE courses c LEFT JOIN users u ON u.id = c.tutor_id SET c.tutor_id = ?
                   WHERE c.source IN ('tutorial','course') AND u.id IS NULL")->execute([$owner]);
}

$catIds = [];
foreach ($pdo->query("SELECT id, name FROM categories")->fetchAll() as $c) $catIds[strtolower($c['name'])] = (int)$c['id'];

$summary = [];
// Two kinds of content: open tutorials (content/tutorials, free to read without an account) and
// free skills courses (content/courses: register to learn, choose a track, pay to unlock the certificate).
$sources = [];
foreach (glob($root . '/*', GLOB_ONLYDIR) as $d) $sources[] = [$d, 'tutorial'];
foreach (glob(__DIR__ . '/content/courses/*', GLOB_ONLYDIR) as $d) $sources[] = [$d, 'course'];

foreach ($sources as [$dir, $kind]) {
    $slug = basename($dir);
    $jsonName = $kind === 'course' ? 'course.json' : 'tutorial.json';
    $jsonFile = "$dir/$jsonName";
    $files = glob("$dir/[0-9]*.md");
    if (!is_file($jsonFile) || !$files) { echo "skip $slug (no $jsonName or lessons yet)\n"; continue; }
    $t = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($t) || empty($t['title'])) { echo "skip $slug (bad $jsonName)\n"; continue; }
    $catName = trim((string)($t['category'] ?? ''));
    if ($catName !== '' && !isset($catIds[strtolower($catName)]) && !$dry) {
        $catSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($catName)), '-');
        $pdo->prepare("INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)")->execute([$catName, $catSlug]);
        $cs = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $cs->execute([$catSlug]);
        $catIds[strtolower($catName)] = (int)$cs->fetchColumn();
    }
    natsort($files);

    // Course row
    $st = $pdo->prepare("SELECT * FROM courses WHERE slug = ? LIMIT 1");
    $st->execute([$slug]);
    $course = $st->fetch();
    if ($course && $course['source'] !== $kind) { echo "skip $slug (another course already uses that slug)\n"; continue; }
    $fields = [
        'title' => $t['title'],
        'description' => $t['description'] ?? '',
        'level' => in_array($t['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $t['level'] : 'beginner',
        'category_id' => $catIds[strtolower($t['category'] ?? '')] ?? null,
        'tutorial_short' => mb_substr($t['short'] ?? $t['title'], 0, 40),
        'tutorial_order' => (int)($t['order'] ?? 0),
    ];
    if ($kind === 'course') {
        $fields['outcomes'] = !empty($t['outcomes']) && is_array($t['outcomes']) ? json_encode(array_values(array_map('strval', $t['outcomes'])), JSON_UNESCAPED_UNICODE) : null;
    }
    $changes = [];
    if (!$course) {
        $changes[] = 'new ' . $kind;
        if (!$dry) {
            if ($kind === 'course') {
                // Free to learn on either track; certificate fee comes from the admin (course or platform default).
                $pdo->prepare("INSERT INTO courses (tutor_id, title, slug, description, level, category_id, price, status, learning_modes, is_open_tutorial, source, tutorial_short, tutorial_order, outcomes)
                               VALUES (?, ?, ?, ?, ?, ?, 0, 'published', 'both', 0, 'course', ?, ?, ?)")
                    ->execute([$owner, $fields['title'], $slug, $fields['description'], $fields['level'], $fields['category_id'], $fields['tutorial_short'], $fields['tutorial_order'], $fields['outcomes']]);
            } else {
                $pdo->prepare("INSERT INTO courses (tutor_id, title, slug, description, level, category_id, price, status, learning_modes, is_open_tutorial, source, tutorial_short, tutorial_order)
                               VALUES (?, ?, ?, ?, ?, ?, 0, 'published', 'self_paced', 1, 'tutorial', ?, ?)")
                    ->execute([$owner, $fields['title'], $slug, $fields['description'], $fields['level'], $fields['category_id'], $fields['tutorial_short'], $fields['tutorial_order']]);
            }
            $courseId = (int)$pdo->lastInsertId();
        } else {
            $courseId = 0;
        }
    } else {
        $courseId = (int)$course['id'];
        $diff = array_filter($fields, fn($v, $k) => (string)$course[$k] !== (string)$v, ARRAY_FILTER_USE_BOTH);
        if ($diff) {
            $changes[] = 'updated ' . implode(', ', array_keys($diff));
            if (!$dry) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($diff)));
                $pdo->prepare("UPDATE courses SET $set WHERE id = ?")->execute([...array_values($diff), $courseId]);
            }
        }
    }

    // Lessons
    $existing = [];
    if ($courseId) {
        $st = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? AND source_key IS NOT NULL");
        $st->execute([$courseId]);
        foreach ($st->fetchAll() as $l) $existing[$l['source_key']] = $l;
    }
    $seen = [];
    $added = $updated = 0;
    $moduleTitles = [];
    $first = true;
    foreach ($files as $f) {
        $base = basename($f, '.md');
        if (!preg_match('/^(\d+)-(.+)$/', $base, $m)) continue;
        [$meta, $body] = fm_parse(file_get_contents($f));
        $module = max(1, (int)($meta['module'] ?? 1));
        if (!empty($meta['module_title'])) $moduleTitles[$module] = $meta['module_title'];
        $row = [
            'title' => mb_substr($meta['title'] ?? ucwords(str_replace('-', ' ', $m[2])), 0, 255),
            'slug' => $m[2],
            'content' => $body,
            'lesson_type' => 'text',
            'order_num' => (int)$m[1],
            'week_num' => $module,
            'module_title' => $moduleTitles[$module] ?? null,
            'duration_mins' => max(1, (int)($meta['minutes'] ?? 10)),
        ];
        $key = ($kind === 'course' ? 'course:' : '') . "$slug/$base";
        $seen[$key] = true;
        // Tutorials: every lesson is public. Courses: only the first lesson is a free preview.
        $preview = ($kind === 'tutorial' || $first) ? 1 : 0;
        $first = false;
        if (!isset($existing[$key])) {
            $added++;
            if (!$dry) {
                $pdo->prepare("INSERT INTO lessons (course_id, source_key, title, slug, content, lesson_type, order_num, week_num, module_title, duration_mins, is_preview)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$courseId, $key, $row['title'], $row['slug'], $row['content'], $row['lesson_type'], $row['order_num'], $row['week_num'], $row['module_title'], $row['duration_mins'], $preview]);
            }
        } else {
            $old = $existing[$key];
            $diff = array_filter($row, fn($v, $k) => (string)$old[$k] !== (string)$v, ARRAY_FILTER_USE_BOTH);
            if ($diff) {
                $updated++;
                if (!$dry) {
                    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($diff)));
                    $pdo->prepare("UPDATE lessons SET $set WHERE id = ?")->execute([...array_values($diff), $old['id']]);
                }
            }
        }
    }
    $removed = array_diff_key($existing, $seen);
    if ($removed && !$dry) {
        $in = implode(',', array_fill(0, count($removed), '?'));
        $pdo->prepare("DELETE FROM lessons WHERE id IN ($in)")->execute(array_column($removed, 'id'));
    }
    if (!$dry && $courseId) {
        $pdo->prepare("UPDATE courses SET total_lessons = (SELECT COUNT(*) FROM lessons WHERE course_id = ?),
                              duration_hours = ROUND((SELECT COALESCE(SUM(duration_mins),0) FROM lessons WHERE course_id = ?) / 60, 1)
                       WHERE id = ?")->execute([$courseId, $courseId, $courseId]);
    }
    // Peer-graded project (content/courses/<slug>/project.md, format in content/courses/README.md)
    if ($kind === 'course' && is_file("$dir/project.md")) {
        require_once __DIR__ . '/includes/projects.php';
        [$pmeta, $pbody] = fm_parse(file_get_contents("$dir/project.md"));
        $rubric = [];
        if (preg_match('/^```rubric[ \t]*\n(.*?)^```[ \t]*$/ms', $pbody, $rm)) {
            $rubric = project_parse_rubric($rm[1]);
            $pbody = trim(str_replace($rm[0], '', $pbody));
        }
        if (!$rubric || empty($pmeta['title'])) {
            $changes[] = 'project.md skipped (needs a title and a ```rubric block)';
        } elseif ($courseId) {
            $pRow = ['title' => mb_substr($pmeta['title'], 0, 200), 'brief' => $pbody, 'rubric' => json_encode($rubric, JSON_UNESCAPED_UNICODE)];
            $pSet = ['pass_pct' => max(1, min(100, (int)($pmeta['pass_pct'] ?? 70))), 'reviews_required' => max(1, min(10, (int)($pmeta['reviews_required'] ?? 3))),
                     'allow_file' => isset($pmeta['allow_file']) ? (int)!in_array(strtolower($pmeta['allow_file']), ['0', 'no', 'false'], true) : 1];
            $ps = $pdo->prepare("SELECT * FROM course_projects WHERE course_id = ?");
            $ps->execute([$courseId]);
            $old = $ps->fetch();
            if (!$old) {
                $changes[] = 'project added';
                if (!$dry) $pdo->prepare("INSERT INTO course_projects (course_id, title, brief, rubric, pass_pct, reviews_required, allow_file) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$courseId, $pRow['title'], $pRow['brief'], $pRow['rubric'], $pSet['pass_pct'], $pSet['reviews_required'], $pSet['allow_file']]);
            } else {
                // Settings an admin changed in Peer Projects are kept; text and rubric follow the file.
                $want = $old['settings_updated_at'] ? $pRow : $pRow + $pSet;
                $diff = array_filter($want, fn($v, $k) => (string)$old[$k] !== (string)$v, ARRAY_FILTER_USE_BOTH);
                if ($diff) {
                    $changes[] = 'project updated (' . implode(', ', array_keys($diff)) . ')';
                    if (!$dry) {
                        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($diff)));
                        $pdo->prepare("UPDATE course_projects SET $set, updated_at = NOW() WHERE id = ?")->execute([...array_values($diff), $old['id']]);
                    }
                }
            }
        }
    }
    if ($added) $changes[] = "$added lesson(s) added";
    if ($updated) $changes[] = "$updated updated";
    if ($removed) $changes[] = count($removed) . ' removed';
    $summary[] = sprintf("%-12s %s", $slug, $changes ? implode(', ', $changes) : 'no changes');
}

echo implode("\n", $summary) . "\n" . ($dry ? "(dry run – nothing written)\n" : "Done.\n");
