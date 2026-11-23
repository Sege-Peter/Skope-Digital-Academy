<?php
/**
 * XML sitemap (served at /sitemap.xml via .htaccess): public pages, published courses and tutorial lessons.
 * Submit https://skopedigital.co.ke/sitemap.xml in Google Search Console.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/learning.php';
require_once __DIR__ . '/includes/seo.php';

$urls = [];
$add = function (string $path, ?string $lastmod = null, string $freq = 'weekly', string $prio = '0.6') use (&$urls) {
    $urls[] = [sda_seo_abs($path), $lastmod ? date('Y-m-d', strtotime($lastmod)) : null, $freq, $prio];
};
foreach ([['', 'daily', '1.0'], ['courses', 'daily', '0.9'], ['learn', 'weekly', '0.9'], ['about', 'monthly', '0.6'], ['scholarships', 'monthly', '0.7'],
          ['contact', 'yearly', '0.5'], ['careers', 'monthly', '0.4'], ['donate', 'monthly', '0.4'], ['register', 'yearly', '0.5'],
          ['verify', 'yearly', '0.3'], ['privacy', 'yearly', '0.2'], ['terms', 'yearly', '0.2']] as [$p, $f, $pr]) {
    $add($p, null, $f, $pr);
}
try {
    foreach ($pdo->query("SELECT id, created_at, pricing_updated_at FROM courses WHERE status = 'published' ORDER BY id")->fetchAll() as $c) {
        $add('course-details?id=' . (int)$c['id'], $c['pricing_updated_at'] ?: $c['created_at'], 'weekly', '0.8');
    }
    // Free tutorials: /learn/<tutorial> and /learn/<tutorial>/<lesson>
    $t = $pdo->query("SELECT c.id, c.slug, l.slug AS lslug, l.created_at FROM courses c JOIN lessons l ON l.course_id = c.id
                      WHERE c.is_open_tutorial = 1 AND c.status = 'published' AND c.slug <> '' ORDER BY c.tutorial_order, c.id, l.order_num, l.id")->fetchAll();
    $seen = [];
    foreach ($t as $r) {
        if (!isset($seen[$r['slug']])) { $seen[$r['slug']] = 1; $add('learn/' . $r['slug'], null, 'weekly', '0.8'); }
        if ($r['lslug']) $add('learn/' . $r['slug'] . '/' . $r['lslug'], $r['created_at'], 'monthly', '0.6');
    }
} catch (Throwable $e) { /* pages above still listed */ }

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $freq, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? "<lastmod>$mod</lastmod>" : '') . "<changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
