<?php
/**
 * HEISVERSE — Secure User Registration
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (isset($_GET['lang'])) {
    Lang::setLanguage($_GET['lang']);
    header('Location: register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $accept = !empty($_POST['terms']);

        $res = Auth::register($username, $email, $password, $confirm, $accept);
        if ($res['success']) {
            header('Location: index.php');
            exit;
        } else {
            $error = $res['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(Lang::current()) ?>" data-theme="cine-01">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(__('register')) ?> — <?= e(__('app_name')) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="assets/css/style.css">
  <meta name="theme-color" content="#07090e">
  <style>
    .auth-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .auth-card {
      background: var(--bg-glass);
      backdrop-filter: blur(20px);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-lg);
      width: 100%;
      max-width: 460px;
      padding: 2.5rem;
      box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.9);
      text-align: center;
    }
    .auth-alert {
      padding: 0.75rem 1rem;
      border-radius: var(--border-radius-sm);
      font-size: 0.85rem;
      margin-bottom: 1.2rem;
      text-align: left;
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
    }
  </style>
</head>
<body class="auth-page">

  <div class="auth-card">
    <div style="margin-bottom:1.2rem;">
      <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:42px;">
    </div>
    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">
      Create your profile to save watchlists, explore anime, TV series, and access 300 themes.
    </p>

    <?php if (!empty($error)): ?>
      <div class="auth-alert">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div style="text-align:left; margin-bottom:1rem;">
        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">Username</label>
        <input type="text" name="username" class="form-control" placeholder="cinematic_fan" required autofocus>
      </div>

      <div style="text-align:left; margin-bottom:1rem;">
        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
      </div>

      <div style="text-align:left; margin-bottom:1rem;">
        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">Password (min 8 characters)</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <div style="text-align:left; margin-bottom:1rem;">
        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
      </div>

      <div style="text-align:left; margin-bottom:1.2rem; font-size:0.85rem;">
        <label style="display:flex; align-items:flex-start; gap:0.5rem; cursor:pointer;">
          <input type="checkbox" name="terms" value="1" required style="margin-top:0.2rem;">
          <span>I accept the Terms of Service and Privacy Policy.</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.8rem;"><?= e(__('register')) ?></button>
    </form>

    <div style="display:flex; justify-content:space-between; margin-top:1.5rem; font-size:0.85rem; color:var(--text-muted);">
      <span>Already have an account?</span>
      <a href="login.php" style="color:var(--accent); font-weight:700;">← <?= e(__('login')) ?></a>
    </div>

    <!-- Language Selector -->
    <div style="margin-top:1.8rem; display:flex; gap:0.8rem; justify-content:center; font-size:0.8rem;">
      <?php foreach (Lang::getSupported() as $code => $info): ?>
        <a href="?lang=<?= e($code) ?>" style="opacity: <?= Lang::current() === $code ? '1' : '0.5' ?>; font-weight: <?= Lang::current() === $code ? '700' : '400' ?>;">
          <?= $info['flag'] ?> <?= $info['name'] ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
