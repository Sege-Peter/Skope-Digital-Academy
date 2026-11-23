<?php
/**
 * Progressive Web App head tags: manifest, icons, theme colour, service worker and the "Install app" chip.
 * Call sda_pwa_head() inside <head> on every page (sda_seo() already does it for public pages).
 */
/** URL path of a file in this app (no session side effects, so it is safe to include after output has started). */
function sda_pwa_url(string $path): string
{
    static $base = null;
    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $appRoot = realpath(__DIR__ . '/..') ?: '';
        $rel = ($docRoot !== '' && PHP_SAPI !== 'cli' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
        $base = rtrim(str_replace('\\', '/', $rel), '/');
    }
    return implode('/', array_map('rawurlencode', explode('/', $base . '/' . ltrim($path, '/'))));
}

function sda_pwa_head(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $v = '1';
    $u = fn($p) => htmlspecialchars(sda_pwa_url($p), ENT_QUOTES);
    $scope = sda_pwa_url('');
    ?>
    <!-- App (PWA) -->
    <link rel="manifest" href="<?= $u('manifest.webmanifest') ?>?v=<?= $v ?>">
    <meta name="theme-color" content="#0b1220">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Skope Academy">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="application-name" content="Skope Academy">
    <link rel="apple-touch-icon" href="<?= $u('assets/pwa/apple-touch-icon.png') ?>">
    <link rel="icon" href="<?= $u('favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $u('assets/pwa/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $u('assets/pwa/icon-192.png') ?>">
    <link rel="stylesheet" href="<?= $u('assets/css/select.css') ?>?v=2">  <!-- one look for every dropdown -->

    <script>addEventListener('beforeinstallprompt',function(e){e.preventDefault();window.__sdaBip=e;});
    (function(){var app=(matchMedia&&matchMedia('(display-mode: standalone)').matches)||navigator.standalone===true,pv=/[?&]splash=1/.test(location.search),k='sdaSplash';
    try{if(!pv&&(!app||sessionStorage.getItem(k)))return;sessionStorage.setItem(k,'1');}catch(e){if(!pv)return;}
    var r=document.documentElement;r.classList.add('sda-splash');document.write('<style>html.sda-splash{background:#0b1220}html.sda-splash body{visibility:hidden}</style>');
    var s=document.createElement('script');s.src=<?= json_encode(sda_pwa_url('assets/js/splash.js') . '?v=1') ?>;s.dataset.logo=<?= json_encode(sda_pwa_url('assets/pwa/icon-192.png')) ?>;document.head.appendChild(s);
    setTimeout(function(){r.classList.remove('sda-splash')},5000);})();</script>
    <script src="<?= $u('assets/js/pwa.js') ?>?v=2" data-scope="<?= htmlspecialchars($scope, ENT_QUOTES) ?>" data-sw="<?= $u('sw.js') ?>" data-icon="<?= $u('assets/pwa/icon-192.png') ?>" defer></script>
    <script src="<?= $u('assets/js/stats.js') ?>?v=1" data-hit="<?= $u('hit') ?>" defer></script>
    <?php
}
