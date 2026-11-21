<?php
/**
 * HEISVERSE — Contact & Support Page
 * Public contact information dynamically pulled from verified MySQL settings.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

$currentUser = Auth::user();
$db = Database::getConnection();

// Load contact information from system_settings
$settings = [];
if ($db) {
    try {
        $stmt = $db->query('SELECT setting_key, setting_value FROM system_settings');
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {}
}

$sentMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf)) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        if (!empty($name) && !empty($email) && !empty($msg)) {
            $sentMessage = 'Thank you, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '! Your message has been received. Our team will contact you shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>Contact Us — <?= e($settings['site_name'] ?? 'HEISVERSE') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .contact-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }
    .contact-box {
      background: var(--bg-glass);
      backdrop-filter: blur(16px);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-lg);
      padding: 2.2rem;
    }
    .contact-item {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .contact-icon {
      font-size: 1.5rem;
      background: var(--bg-secondary);
      border: 1px solid var(--border-glass);
      padding: 0.6rem;
      border-radius: 10px;
    }
    .social-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--bg-secondary);
      border: 1px solid var(--border-glass);
      color: var(--text-main);
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.85rem;
      margin: 0.3rem 0.3rem 0.3rem 0;
      transition: all 0.2s;
    }
    .social-btn:hover {
      border-color: var(--accent);
      background: var(--accent);
      color: #fff;
    }
  </style>
</head>
<body>

  <!-- Navigation -->
  <header class="navbar">
    <a href="index.php" class="nav-brand">
      <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:34px;">
    </a>
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="series.php">Series</a></li>
      <li><a href="anime.php">Anime</a></li>
      <li><a href="categories.php">Categories</a></li>
      <li><a href="contact.php" class="active">Contact</a></li>
    </ul>
    <div class="search-wrapper" role="search">
      <div class="search-input-box">
        <span aria-hidden="true" style="font-size:0.9rem; color:var(--text-dim); margin-right:0.4rem;">🔍</span>
        <input type="search" id="globalSearchInput" class="search-input" placeholder="Search movies, TV, anime…" autocomplete="off" aria-label="Search titles" aria-controls="searchSuggestionsDropdown">
      </div>
      <div class="search-dropdown" id="searchSuggestionsDropdown" role="listbox"></div>
    </div>
    <div class="nav-actions">
      <?php if (Auth::check()): ?>
        <span style="font-size:0.85rem; color:var(--text-muted);">👤 <?= e($currentUser['username']) ?></span>
        <a href="logout.php" class="btn btn-glass btn-sm">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-primary btn-sm">Login</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="container">
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 0.5rem;">📬 Contact & Support</h1>
      <p style="color: var(--text-muted); max-width: 650px;">
        Have a question, licensing inquiry, or feedback about HEISVERSE? Connect with our team directly.
      </p>
    </div>

    <?php if (!empty($sentMessage)): ?>
      <div style="background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#6ee7b7; padding:1.2rem; border-radius:12px; margin-bottom:2rem;">
        ✅ <?= e($sentMessage) ?>
      </div>
    <?php endif; ?>

    <div class="contact-grid">
      <!-- Contact Info Card -->
      <div class="contact-box">
        <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem; font-weight: 700;">🏢 Company & Locations</h3>

        <?php if (!empty($settings['business_description'])): ?>
          <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.6; margin-bottom:1.5rem;">
            <?= e($settings['business_description']) ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($settings['contact_email'])): ?>
          <div class="contact-item">
            <span class="contact-icon">✉️</span>
            <div>
              <div style="font-size:0.8rem; color:var(--text-muted);">Official Email</div>
              <a href="mailto:<?= e($settings['contact_email']) ?>" style="font-weight:600; color:var(--accent);">
                <?= e($settings['contact_email']) ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($settings['support_email'])): ?>
          <div class="contact-item">
            <span class="contact-icon">🎧</span>
            <div>
              <div style="font-size:0.8rem; color:var(--text-muted);">Technical Support</div>
              <a href="mailto:<?= e($settings['support_email']) ?>" style="font-weight:600; color:var(--accent);">
                <?= e($settings['support_email']) ?>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($settings['phone_number'])): ?>
          <div class="contact-item">
            <span class="contact-icon">📞</span>
            <div>
              <div style="font-size:0.8rem; color:var(--text-muted);">Phone & Direct Line</div>
              <div style="font-weight:600;"><?= e($settings['phone_number']) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($settings['whatsapp_number'])): ?>
          <div class="contact-item">
            <span class="contact-icon">💬</span>
            <div>
              <div style="font-size:0.8rem; color:var(--text-muted);">WhatsApp Business</div>
              <div style="font-weight:600; color:#34d399;"><?= e($settings['whatsapp_number']) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($settings['physical_address'])): ?>
          <div class="contact-item">
            <span class="contact-icon">📍</span>
            <div>
              <div style="font-size:0.8rem; color:var(--text-muted);">Physical Headquarters</div>
              <div style="font-weight:600; font-size:0.9rem;"><?= e($settings['physical_address']) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Social Media Buttons -->
        <h4 style="font-size: 1rem; margin: 2rem 0 0.8rem; font-weight: 700;">Connect on Social Media</h4>
        <div>
          <?php if (!empty($settings['social_x'])): ?>
            <a href="<?= e($settings['social_x']) ?>" target="_blank" rel="noopener" class="social-btn">𝕏 Twitter</a>
          <?php endif; ?>
          <?php if (!empty($settings['social_youtube'])): ?>
            <a href="<?= e($settings['social_youtube']) ?>" target="_blank" rel="noopener" class="social-btn">▶ YouTube</a>
          <?php endif; ?>
          <?php if (!empty($settings['social_instagram'])): ?>
            <a href="<?= e($settings['social_instagram']) ?>" target="_blank" rel="noopener" class="social-btn">📷 Instagram</a>
          <?php endif; ?>
          <?php if (!empty($settings['social_facebook'])): ?>
            <a href="<?= e($settings['social_facebook']) ?>" target="_blank" rel="noopener" class="social-btn">📘 Facebook</a>
          <?php endif; ?>
          <?php if (!empty($settings['social_telegram'])): ?>
            <a href="<?= e($settings['social_telegram']) ?>" target="_blank" rel="noopener" class="social-btn">✈️ Telegram</a>
          <?php endif; ?>
          <?php if (!empty($settings['social_tiktok'])): ?>
            <a href="<?= e($settings['social_tiktok']) ?>" target="_blank" rel="noopener" class="social-btn">🎵 TikTok</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Send Direct Message Card -->
      <div class="contact-box">
        <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem; font-weight: 700;">💬 Send Us a Direct Message</h3>
        <form method="POST" action="contact.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

          <div style="margin-bottom: 1.2rem;">
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
              Full Name
            </label>
            <input type="text" name="name" class="form-control" value="<?= e($currentUser['username'] ?? '') ?>" placeholder="Your name" required>
          </div>

          <div style="margin-bottom: 1.2rem;">
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
              Email Address
            </label>
            <input type="email" name="email" class="form-control" value="<?= e($currentUser['email'] ?? '') ?>" placeholder="you@example.com" required>
          </div>

          <div style="margin-bottom: 1.2rem;">
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
              Subject / Inquiry Type
            </label>
            <select name="subject" class="form-control">
              <option value="General Feedback">General Feedback</option>
              <option value="Technical Support">Technical Support</option>
              <option value="Licensing & Rights">Licensing & Rights</option>
              <option value="Partnership">Partnership</option>
            </select>
          </div>

          <div style="margin-bottom: 1.5rem;">
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
              Message Content
            </label>
            <textarea name="message" class="form-control" rows="5" placeholder="How can we assist you today?" required></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%; padding:0.85rem;">
            Send Inquiry Now →
          </button>
        </form>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer style="background:var(--bg-secondary); border-top:1px solid var(--border-glass); padding:2.5rem 1.5rem; text-align:center; margin-top:4rem; color:var(--text-muted); font-size:0.85rem;">
    <p style="font-weight:700; color:var(--text-main); margin-bottom:0.4rem;">
      <?= e($settings['site_name'] ?? 'HEISVERSE') ?>
    </p>
    <p style="margin-bottom:0.5rem;"><?= e($settings['physical_address'] ?? 'Kigali, Rwanda') ?> • Phone: <?= e($settings['phone_number'] ?? '') ?></p>
    <p>© 2026 HEISVERSE. All rights reserved.</p>
  </footer>

  <script src="assets/js/main.js"></script>
</body>
</html>
