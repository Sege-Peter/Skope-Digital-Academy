<?php
/** Head + top bar for the public tutorial reader. Expects $pageTitle, $tabs, optional $activeTab, $user, $course_id, $q. */
$activeTab = $activeTab ?? 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= e(sda_base_href()) ?>">
<title><?= e($pageTitle ?? 'Tutorials') ?> – Skope Digital Academy</title>
<?php require_once __DIR__ . '/seo.php'; sda_seo(['title' => ($pageTitle ?? 'Tutorials'), 'path' => sda_seo_current_path(), 'description' => $metaDescription ?? 'Free interactive tutorials in HTML, CSS, JavaScript, Python, SQL and PHP with examples you can run in your browser.', 'type' => 'article', 'breadcrumbs' => [['Tutorials', 'learn']], 'noindex' => !empty($q)]); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
<link rel="stylesheet" href="assets/css/learn.css?v=9">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
</head>
<body class="lr-body<?= empty($tabs) ? ' no-tabs' : '' ?>">
<header class="lr-top">
  <div class="lr-top-row">
    <?php if (!empty($course_id)): ?><button class="lr-menu-btn" id="lrMenu" type="button" aria-label="Show lessons" aria-controls="lrSide"><i class="fas fa-bars"></i></button><?php endif; ?>
    <a class="lr-logo" href="./"><img src="assets/images/skope-logo-512.png" alt=""> SKOPE <span>ACADEMY</span></a>
    <form class="lr-search-top" action="learn" method="get" role="search">
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="Search tutorials…" aria-label="Search tutorials">
    </form>
    <nav class="lr-top-links" aria-label="Site">
      <a href="learn"><i class="fas fa-book-open"></i> Tutorials</a>
      <a href="courses"><i class="fas fa-graduation-cap"></i> Courses</a>
      <a href="tryit?lang=html"><i class="fas fa-code"></i> Editor</a>
      <?php if (!empty($meLevel)): ?>
        <a class="lr-level" href="student/achievements" id="levelChip" title="Level <?= (int)$meLevel['level'] ?> <?= e($meLevel['title']) ?> · <?= number_format($meLevel['xp']) ?> XP – see your achievements">
          <span class="lv">Lv <b id="lvNum"><?= (int)$meLevel['level'] ?></b></span>
          <span class="lv-bar"><span id="lvBar" style="width:<?= (int)$meLevel['pct'] ?>%"></span></span>
          <span class="lv-xp"><b id="lvXp"><?= number_format($meLevel['xp']) ?></b> XP</span>
        </a>
      <?php endif; ?>
      <?php if (!empty($user)): ?>
        <a class="lr-cta" href="<?= e($user['role']) ?>/">My dashboard</a>
      <?php else: ?>
        <a href="login">Sign in</a>
        <a class="lr-cta" href="register">Join free</a>
      <?php endif; ?>
    </nav>
    <button class="lr-site-btn" id="lrSiteBtn" type="button" aria-label="Open site menu" aria-controls="lrSiteMenu" aria-expanded="false"><span></span><span></span><span></span></button>
  </div>
  <div class="lr-site-menu" id="lrSiteMenu" hidden>
    <form class="lr-site-search" action="learn" method="get" role="search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" name="q" placeholder="Search tutorials…" aria-label="Search tutorials"></form>
    <a href="learn"><i class="fas fa-book-open"></i> Coding tutorials</a>
    <a href="lab"><i class="fas fa-flask"></i> Learning Lab</a>
    <a href="courses"><i class="fas fa-graduation-cap"></i> Courses</a>
    <a href="tryit?lang=html"><i class="fas fa-code"></i> Code editor</a>
    <a href="./"><i class="fas fa-house"></i> Academy home</a>
    <a href="about"><i class="fas fa-circle-info"></i> About us</a>
    <a href="contact"><i class="fas fa-envelope"></i> Contact us</a>
    <div class="lr-site-cta">
      <?php if (!empty($user)): ?>
        <a class="lr-cta" href="<?= e($user['role']) ?>/"><i class="fas fa-gauge-high"></i> My dashboard</a>
      <?php else: ?>
        <a class="lr-ghost" href="login"><i class="fas fa-right-to-bracket"></i> Sign in</a>
        <a class="lr-cta" href="register"><i class="fas fa-user-plus"></i> Join free</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($tabs)): ?>
  <nav class="lr-tabs" aria-label="Tutorials">
    <a href="learn" class="<?= !$activeTab ? 'on' : '' ?>" aria-label="Tutorials home"><i class="fas fa-house"></i></a>
    <?php foreach ($tabs as $t): ?>
      <a href="<?= e(learn_url($t)) ?>" class="<?= (int)$t['id'] === (int)$activeTab ? 'on' : '' ?>"><?= e($t['tutorial_short'] ?: $t['title']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <script>
  (function () {
    var b = document.getElementById('lrSiteBtn'), m = document.getElementById('lrSiteMenu'), lb = document.getElementById('lrMenu'), S = function () { return document.getElementById('lrSide'); };
    function site(open) { m.hidden = !open; b.classList.toggle('open', open); b.setAttribute('aria-expanded', String(open)); b.setAttribute('aria-label', open ? 'Close site menu' : 'Open site menu'); }
    function lessons(open) { var side = S(); if (!side || !lb) return; side.classList.toggle('open', open); document.body.classList.toggle('lr-menu-open', open); lb.setAttribute('aria-expanded', String(open)); lb.innerHTML = open ? '<i class="fas fa-xmark"></i>' : '<i class="fas fa-bars"></i>'; }
    b.addEventListener('click', function () { var o = m.hidden; site(o); if (o) lessons(false); });
    if (lb) lb.addEventListener('click', function (e) { var o = !(S() && S().classList.contains('open')); lessons(o); if (o) site(false); });
    document.addEventListener('click', function (e) { if (S() && S().contains(e.target) && e.target.closest('a')) lessons(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { site(false); lessons(false); } });
    document.addEventListener('click', function (e) { if (!m.hidden && !m.contains(e.target) && !b.contains(e.target)) site(false); });
    window.addEventListener('resize', function () { if (innerWidth > 900) { site(false); lessons(false); } });
  })();
  </script>
</header>
