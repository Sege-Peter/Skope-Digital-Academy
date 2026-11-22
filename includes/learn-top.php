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
<?php if (!empty($metaDescription)): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
<link rel="stylesheet" href="assets/css/learn.css?v=4">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
</head>
<body class="lr-body<?= empty($tabs) ? ' no-tabs' : '' ?>">
<header class="lr-top">
  <div class="lr-top-row">
    <?php if (!empty($course_id)): ?><button class="lr-menu-btn" id="lrMenu" type="button" aria-label="Show lessons" aria-controls="lrSide"><i class="fas fa-bars"></i></button><?php endif; ?>
    <a class="lr-logo" href="./"><img src="assets/images/Skope Digital  logo.png" alt=""> SKOPE <span>ACADEMY</span></a>
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
  </div>
  <?php if (!empty($tabs)): ?>
  <nav class="lr-tabs" aria-label="Tutorials">
    <a href="learn" class="<?= !$activeTab ? 'on' : '' ?>" aria-label="Tutorials home"><i class="fas fa-house"></i></a>
    <?php foreach ($tabs as $t): ?>
      <a href="<?= e(learn_url($t)) ?>" class="<?= (int)$t['id'] === (int)$activeTab ? 'on' : '' ?>"><?= e($t['tutorial_short'] ?: $t['title']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
</header>
