<?php
$pageTitle = 'Refer a friend';
require_once 'includes/header.php';

// Auth: Students only (header.php already requires the student role)
if (($user['role'] ?? 'student') !== 'student') { header('Location: ../login'); exit; }

try {
    // 0. Fetch Full User Context (for referral_code, merit_coins, etc.)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user_full = $stmt->fetch();
    if (!$user_full) { header('Location: ../login'); exit; }
    // Override local $user with full DB record for consistency
    $user = $user_full;

    // 1. Fetch Referral Stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $stmt->execute([$user['id']]);
    $total_referrals = $stmt->fetchColumn();

    // 2. Fetch Detailed Network (people referred by this user)
    $stmt = $pdo->prepare("SELECT name, email, avatar, created_at, status
                           FROM users
                           WHERE referred_by = ?
                           ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $network = $stmt->fetchAll();

    // 3. Fetch Revenue History (Merit Coins earned via referrals)
    $stmt = $pdo->prepare("SELECT action, description, created_at
                           FROM audit_log
                           WHERE user_id = ? AND action = 'referral_earned'
                           ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $history = $stmt->fetchAll();

    // 4. Global Scholarships for Referral Context
    $stmt = $pdo->query("SELECT * FROM scholarships WHERE (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY created_at DESC LIMIT 3");
    $available_scholarships = $stmt->fetchAll();

} catch (Exception $e) { $total_referrals = 0; $network = $history = $available_scholarships = []; }

// Referral link (path segments encoded so a folder name with spaces still makes a clean, shareable link)
$ref_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ref_base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$ref_base = implode('/', array_map('rawurlencode', explode('/', $ref_base)));
$ref_code = (string)($user['referral_code'] ?? '');
$ref_url = $ref_scheme . '://' . $_SERVER['HTTP_HOST'] . $ref_base . '/register?ref=' . rawurlencode($ref_code);
$ref_share = 'Join me on Skope Digital Academy and learn digital skills: ' . $ref_url;

require_once 'includes/layout-top.php';
?>
<style>
  .pg-ref .ref-link { display: flex; gap: 8px; margin-top: 18px; flex-wrap: wrap; }
  .pg-ref .ref-link input { flex: 1 1 240px; min-width: 0; height: 46px; padding: 0 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.08); color: #fff; font: inherit; font-size: .88rem; }
  .pg-ref .ref-link input:focus { outline: 2px solid var(--primary); }
</style>

<div class="pg-ref">
  <div class="sp-page-head">
    <div><h1>Refer a friend</h1><p>Invite friends to learn with you. You earn merit coins when they pay for a course.</p></div>
  </div>

  <!-- Share -->
  <section class="sp-hero">
    <span class="sp-badge dark"><i class="fas fa-gift"></i> Earn 4% in merit coins</span>
    <h2 style="margin: 10px 0 6px; font-size: clamp(1.3rem, 3vw, 1.7rem); font-weight: 800;">Share your link</h2>
    <p>When someone signs up with your link and pays for a course, you get 4% of the course price as merit coins.</p>
    <div class="ref-link">
      <input type="text" id="refURL" value="<?= htmlspecialchars($ref_url) ?>" readonly aria-label="Your referral link" onclick="this.select()">
      <button type="button" class="sp-btn lg" id="copyRefBtn" onclick="copyRefURL()"><i class="fas fa-copy"></i> Copy link</button>
      <a class="sp-btn lg ghost" style="background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.2); color: #fff !important;" href="https://wa.me/?text=<?= rawurlencode($ref_share) ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> WhatsApp</a>
    </div>
    <p style="margin-top: 12px; font-size: .82rem;">Your code: <strong style="color: #fff; letter-spacing: .04em;"><?= htmlspecialchars($ref_code) ?></strong></p>
  </section>

  <!-- Stats -->
  <section class="sp-grid cols-3 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-user-plus"></i></span><div><strong><?= (int)$total_referrals ?></strong><span>Friends who joined</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon yellow"><i class="fas fa-coins"></i></span><div><strong><?= number_format((float)($user['merit_coins'] ?? 0), 2) ?></strong><span>Merit coins earned</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-receipt"></i></span><div><strong><?= count($history) ?></strong><span>Rewards received</span></div></div>
  </section>

  <!-- How it works -->
  <section class="sp-section">
    <div class="sp-section-head"><div><h2>How it works</h2></div></div>
    <div class="sp-grid cols-3">
      <div class="sp-card"><span class="sp-ico">1</span><h3 style="margin: 12px 0 4px; font-size: .98rem; font-weight: 800;">Share your link</h3><p class="sp-muted sp-small" style="margin: 0;">Send it to friends on WhatsApp, email or social media.</p></div>
      <div class="sp-card"><span class="sp-ico orange">2</span><h3 style="margin: 12px 0 4px; font-size: .98rem; font-weight: 800;">They sign up</h3><p class="sp-muted sp-small" style="margin: 0;">Your friend creates a free account using your link.</p></div>
      <div class="sp-card"><span class="sp-ico green">3</span><h3 style="margin: 12px 0 4px; font-size: .98rem; font-weight: 800;">You earn coins</h3><p class="sp-muted sp-small" style="margin: 0;">When they pay for a course, 4% of the price is added to your merit coins.</p></div>
    </div>
  </section>

  <section class="sp-split sp-section">
    <!-- Friends -->
    <div>
      <div class="sp-section-head"><div><h2>Friends you invited</h2><p><?= count($network) ?> so far</p></div></div>
      <?php if (empty($network)): ?>
        <div class="sp-empty">
          <div class="sp-ico"><i class="fas fa-user-plus"></i></div>
          <h3>No friends yet</h3>
          <p>Share your link above. Friends who sign up with it will appear here.</p>
          <button type="button" class="sp-btn" onclick="copyRefURL()"><i class="fas fa-copy"></i> Copy link</button>
        </div>
      <?php else: ?>
      <div class="sp-table-wrap">
        <table class="sp-table">
          <thead><tr><th>Friend</th><th>Joined</th><th style="text-align: right;">Status</th></tr></thead>
          <tbody>
            <?php foreach ($network as $n): $st = strtolower((string)$n['status']); ?>
            <tr>
              <td>
                <div class="sp-row" style="flex-wrap: nowrap;">
                  <span class="sp-avatar" style="width: 36px; height: 36px; font-size: .8rem;"><?= htmlspecialchars(mb_strtoupper(mb_substr((string)$n['name'], 0, 1))) ?></span>
                  <div style="min-width: 0;">
                    <div style="font-weight: 700;"><?= htmlspecialchars($n['name']) ?></div>
                    <div class="sp-muted" style="font-size: .75rem;"><?= htmlspecialchars(substr((string)$n['email'], 0, 3) . '***@***.com') ?></div>
                  </div>
                </div>
              </td>
              <td class="sp-muted" style="white-space: nowrap;"><?= date('j M Y', strtotime($n['created_at'])) ?></td>
              <td style="text-align: right;"><span class="sp-badge <?= $st === 'active' ? 'green' : 'gray' ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Rewards + scholarships -->
    <div>
      <div class="sp-section-head"><div><h2>Reward history</h2></div></div>
      <div class="sp-card" style="padding: 6px 18px; margin-bottom: 16px;">
        <?php if (empty($history)): ?>
          <p class="sp-muted sp-small" style="padding: 14px 0; margin: 0;">No rewards yet. They appear here when a friend pays for a course.</p>
        <?php else: ?>
        <div class="sp-list">
          <?php foreach ($history as $h): ?>
          <div class="sp-list-item">
            <span class="sp-ico yellow" style="width: 34px; height: 34px;"><i class="fas fa-coins"></i></span>
            <div class="sp-grow"><h5><?= htmlspecialchars((string)$h['description']) ?></h5><p><?= date('j M Y', strtotime($h['created_at'])) ?></p></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($available_scholarships)): ?>
      <div class="sp-card" style="padding: 16px 18px 6px;">
        <div class="sp-card-head" style="margin-bottom: 0;"><h4>Scholarships open now</h4><a href="../scholarships" class="sp-link">All</a></div>
        <div class="sp-list">
          <?php foreach ($available_scholarships as $as): ?>
          <a href="../scholarships" class="sp-list-item" style="text-decoration: none;">
            <span class="sp-ico green" style="width: 34px; height: 34px;"><i class="fas fa-hand-holding-heart"></i></span>
            <div class="sp-grow"><h5><?= htmlspecialchars($as['title']) ?></h5><p style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars((string)$as['description']) ?></p></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
function copyRefURL() {
    const input = document.getElementById('refURL');
    const btn = document.getElementById('copyRefBtn');
    const done = () => {
        btn.innerHTML = '<i class="fas fa-check"></i> Copied';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy link'; }, 2000);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done, () => { input.select(); document.execCommand('copy'); done(); });
    } else {
        input.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
    }
}
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
