<?php
/**
 * SEO for public pages: description, canonical, robots, Open Graph, Twitter card, favicon, theme colour and
 * schema.org structured data (EducationalOrganization, WebSite, BreadcrumbList, plus extras per page).
 *
 *   <?php require_once __DIR__ . '/includes/seo.php'; sda_seo([
 *       'title' => 'About Us',                         // page title (site name is appended in og:title)
 *       'description' => '…150–160 characters…',
 *       'path' => 'about',                             // canonical path, relative to the site root
 *       'type' => 'website',                           // og:type (article, website…)
 *       'image' => 'assets/images/og-image.jpg',       // optional share image (relative or absolute)
 *       'noindex' => false,                            // true for sign-in, checkout, thank-you…
 *       'breadcrumbs' => [['Courses', 'courses']],     // optional trail after Home
 *       'schema' => [ … ],                             // optional extra JSON-LD objects (Course, FAQPage…)
 *   ]); ?>
 *
 * The public address is Admin > Learning Settings > site_url, else https://skopedigital.co.ke.
 */
if (!function_exists('setting')) require_once __DIR__ . '/learning.php';

const SDA_SEO_SITE = 'Skope Digital Academy';
const SDA_SEO_EMAILS = ['info@skopedigital.co.ke', 'skopedigitalacademy@gmail.com'];

function sda_seo_base(): string
{
    $u = rtrim(setting('site_url'), '/');
    return $u !== '' ? $u : 'https://skopedigital.co.ke';
}

function sda_seo_abs(string $path): string
{
    if (preg_match('#^https?://#i', $path)) return $path;
    $path = ltrim($path, '/');
    [$p, $q] = array_pad(explode('?', $path, 2), 2, null);
    $enc = implode('/', array_map(fn($x) => rawurlencode(rawurldecode($x)), explode('/', $p)));
    return sda_seo_base() . '/' . $enc . ($q !== null && $q !== '' ? '?' . $q : '');
}

/** The current page's path relative to the site root (for canonical links), keeping only $keepQuery params. */
function sda_seo_current_path(array $keepQuery = []): string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = defined('SDA_BASE') ? SDA_BASE : '';
    if ($base !== '' && stripos($path, $base) === 0) $path = substr($path, strlen($base));
    $path = preg_replace('/\.php$/', '', ltrim($path, '/'));
    if ($path === 'index') $path = '';
    $q = array_intersect_key($_GET, array_flip($keepQuery));
    return $path . ($q ? '?' . http_build_query($q) : '');
}

/** Contact details shared by the footer and structured data. */
function sda_contact(): array
{
    $phone = trim(setting('support_phone')) ?: '0742 380 183';
    $digits = preg_replace('/\D+/', '', $phone);
    $intl = str_starts_with($digits, '0') ? '254' . substr($digits, 1) : $digits;
    return [
        'phone' => $phone, 'phone_intl' => '+' . $intl, 'whatsapp' => $intl,
        'email' => SDA_SEO_EMAILS[0], 'email_alt' => SDA_SEO_EMAILS[1],
        'city' => 'Kisumu', 'country' => 'KE',
    ];
}

/** The organisation as schema.org data (used on every page). */
function sda_seo_org(): array
{
    $c = sda_contact();
    $base = sda_seo_base();
    return [
        '@type' => ['EducationalOrganization', 'OnlineBusiness'],
        '@id' => $base . '/#organization',
        'name' => SDA_SEO_SITE,
        'alternateName' => ['Skope Digital', 'SDA'],
        'url' => $base . '/',
        'logo' => ['@type' => 'ImageObject', 'url' => sda_seo_abs('assets/images/Skope Digital  logo.png'), 'width' => 512, 'height' => 512],
        'image' => sda_seo_abs('assets/images/og-image.jpg'),
        'description' => 'Online academy in Kisumu, Kenya teaching coding and income skills through free tutorials, self-paced and structured courses, with verifiable certificates.',
        'email' => $c['email'],
        'telephone' => $c['phone_intl'],
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => $c['city'], 'addressRegion' => 'Kisumu County', 'addressCountry' => $c['country']],
        'areaServed' => [['@type' => 'Country', 'name' => 'Kenya'], ['@type' => 'Place', 'name' => 'East Africa']],
        'contactPoint' => [
            ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $c['email'], 'telephone' => $c['phone_intl'], 'areaServed' => 'KE', 'availableLanguage' => ['English', 'Swahili']],
            ['@type' => 'ContactPoint', 'contactType' => 'admissions', 'email' => $c['email_alt'], 'telephone' => $c['phone_intl'], 'areaServed' => 'KE', 'availableLanguage' => ['English', 'Swahili']],
        ],
        'sameAs' => ['https://www.facebook.com/profile.php?id=100090014799182', 'https://wa.me/' . $c['whatsapp']],
    ];
}

function sda_seo(array $o): void
{
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $base = sda_seo_base();
    $title = trim((string)($o['title'] ?? SDA_SEO_SITE));
    $fullTitle = str_contains($title, SDA_SEO_SITE) ? $title : $title . ' – ' . SDA_SEO_SITE;
    $desc = trim(preg_replace('/\s+/', ' ', strip_tags((string)($o['description'] ?? ''))));
    if (mb_strlen($desc) > 160) $desc = rtrim(mb_substr($desc, 0, 157), " ,.;:-") . '…';
    $path = ltrim((string)($o['path'] ?? ''), '/');
    $url = $path === '' ? $base . '/' : sda_seo_abs($path);
    $image = sda_seo_abs((string)(!empty($o['image']) ? $o['image'] : 'assets/images/og-image.jpg'));
    $isDefaultImage = empty($o['image']);
    $robots = !empty($o['noindex']) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    echo "\n    <!-- SEO -->\n";
    if ($desc !== '') echo '    <meta name="description" content="' . $h($desc) . "\">\n";
    echo '    <meta name="robots" content="' . $robots . "\">\n";
    echo '    <link rel="canonical" href="' . $h($url) . "\">\n";
    if (!empty($o['keywords'])) echo '    <meta name="keywords" content="' . $h($o['keywords']) . "\">\n";
    echo '    <meta name="author" content="' . SDA_SEO_SITE . "\">\n";
    echo '    <meta name="theme-color" content="#0b1220">' . "\n";
    echo '    <meta name="geo.region" content="KE-17"><meta name="geo.placename" content="Kisumu">' . "\n";
    echo '    <link rel="icon" type="image/png" href="' . $h(sda_seo_abs('assets/images/Skope Digital  logo.png')) . '">' . "\n";
    echo '    <link rel="apple-touch-icon" href="' . $h(sda_seo_abs('assets/images/Skope Digital  logo.png')) . '">' . "\n";
    // Open Graph (Facebook, WhatsApp, LinkedIn)
    echo '    <meta property="og:site_name" content="' . SDA_SEO_SITE . "\">\n";
    echo '    <meta property="og:locale" content="en_KE">' . "\n";
    echo '    <meta property="og:type" content="' . $h($o['type'] ?? 'website') . "\">\n";
    echo '    <meta property="og:title" content="' . $h($fullTitle) . "\">\n";
    if ($desc !== '') echo '    <meta property="og:description" content="' . $h($desc) . "\">\n";
    echo '    <meta property="og:url" content="' . $h($url) . "\">\n";
    echo '    <meta property="og:image" content="' . $h($image) . "\">\n";
    if ($isDefaultImage) echo '    <meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
    echo '    <meta property="og:image:alt" content="' . $h($o['image_alt'] ?? SDA_SEO_SITE . ' – learn digital skills that pay') . "\">\n";
    // Twitter / X
    echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '    <meta name="twitter:title" content="' . $h($fullTitle) . "\">\n";
    if ($desc !== '') echo '    <meta name="twitter:description" content="' . $h($desc) . "\">\n";
    echo '    <meta name="twitter:image" content="' . $h($image) . "\">\n";

    // Structured data
    $graph = [sda_seo_org(), [
        '@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $base . '/', 'name' => SDA_SEO_SITE,
        'publisher' => ['@id' => $base . '/#organization'], 'inLanguage' => 'en-KE',
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base . '/courses?search={search_term_string}'], 'query-input' => 'required name=search_term_string'],
    ], [
        '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $fullTitle, 'description' => $desc,
        'isPartOf' => ['@id' => $base . '/#website'], 'about' => ['@id' => $base . '/#organization'], 'inLanguage' => 'en-KE',
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $image],
    ]];
    if (!empty($o['breadcrumbs'])) {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/']];
        foreach ($o['breadcrumbs'] as $i => [$name, $p]) $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => sda_seo_abs($p)];
        $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items];
    }
    foreach ($o['schema'] ?? [] as $extra) $graph[] = $extra;
    echo '    <script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
}

/** schema.org Course for a course row (course-details page). */
function sda_seo_course(array $c, string $url): array
{
    $base = sda_seo_base();
    $price = (float)($c['price'] ?? 0);
    $course = [
        '@type' => 'Course', '@id' => $url . '#course', 'name' => $c['title'], 'url' => $url,
        'description' => mb_substr(trim(strip_tags((string)($c['description'] ?? $c['title']))), 0, 500) ?: $c['title'],
        'provider' => ['@id' => $base . '/#organization'],
        'inLanguage' => 'en',
        'isAccessibleForFree' => $price <= 0,
        'offers' => ['@type' => 'Offer', 'category' => $price <= 0 ? 'Free' : 'Paid', 'price' => number_format($price, 2, '.', ''), 'priceCurrency' => setting('currency') ?: 'KES', 'availability' => 'https://schema.org/InStock', 'url' => $url],
        'hasCourseInstance' => [array_filter([
            '@type' => 'CourseInstance', 'courseMode' => 'Online',
            'courseWorkload' => (float)($c['duration_hours'] ?? 0) >= 1 ? 'PT' . (int)$c['duration_hours'] . 'H' : null,
            'instructor' => !empty($c['tutor_name']) ? ['@type' => 'Person', 'name' => $c['tutor_name']] : null,
        ])],
    ];
    if (!empty($c['thumbnail']) && is_file(__DIR__ . '/../uploads/courses/' . basename($c['thumbnail']))) $course['image'] = sda_seo_abs('uploads/courses/' . basename($c['thumbnail']));
    if (!empty($c['level'])) $course['educationalLevel'] = ucfirst((string)$c['level']);
    // Real reviews only (course_ratings), never invented numbers
    global $pdo;
    try {
        $r = $pdo->prepare("SELECT COUNT(*) n, AVG(rating) a FROM course_ratings WHERE course_id = ?");
        $r->execute([(int)$c['id']]);
        $r = $r->fetch();
        if ($r && (int)$r['n'] > 0) $course['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float)$r['a'], 1), 'ratingCount' => (int)$r['n'], 'bestRating' => 5, 'worstRating' => 1];
    } catch (Throwable $e) {}
    return $course;
}
