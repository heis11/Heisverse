<?php
/**
 * HEISVERSE — Secure Password Reset Execution
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$success = '';
$validToken = false;
$userId = 0;

$db = Database::getConnection();

if ($db && !empty($token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = $db->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = :h LIMIT 1');
    $stmt->execute([':h' => $tokenHash]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        if ($resetRecord['used_at'] !== null) {
            $error = 'This password reset link has already been used.';
        } elseif (strtotime($resetRecord['expires_at']) < time()) {
            $error = 'This password reset link has expired. Please request a new one.';
        } else {
            $validToken = true;
            $userId = (int)$resetRecord['user_id'];
        }
    } else {
        $error = 'Invalid password reset token.';
    }
} else {
    $error = 'Missing reset token.';
}

// Process new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken && $db) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Session expired. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Update user password securely
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $db->prepare('UPDATE users SET password_hash = :p WHERE id = :u');
            $upd->execute([':p' => $newHash, ':u' => $userId]);

            // Mark token as used
            $mark = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
            $mark->execute([':id' => $resetRecord['id']]);

            $success = 'Your password has been successfully reset! You can now log in.';
            $validToken = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark-cinematic">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set New Password — HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .auth-page {
      min-height: 100vh;
      display: flex;
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
      max-width: 450px;
      padding: 2.5rem;
      text-align: center;
    }
  </style>
</head>
<body class="auth-page">
  <div class="auth-card">
    <div style="margin-bottom:1rem;">
      <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:38px;">
    </div>
    <h2 style="font-size:1.6rem; font-weight:800; margin-bottom:0.4rem;">Choose New Password</h2>
    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">
      Create a secure password with at least 8 characters.
    </p>

    <?php if (!empty($error)): ?>
      <div style="background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); color:#fca5a5; padding:0.85rem; border-radius:8px; margin-bottom:1.2rem; font-size:0.9rem; text-align:left;">
        ⚠️ <?= e($error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; padding:1rem; border-radius:8px; margin-bottom:1.2rem; font-size:0.9rem; text-align:left;">
        ✅ <?= e($success) ?>
        <div style="margin-top:0.8rem;">
          <a href="login.php" class="btn btn-primary" style="width:100%;">Proceed to Login →</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($validToken): ?>
      <form method="POST" action="reset-password.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div style="text-align:left; margin-bottom:1rem;">
          <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
            New Password
          </label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required autofocus>
        </div>

        <div style="text-align:left; margin-bottom:1.4rem;">
          <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
            Confirm New Password
          </label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.8rem;">
          Update Password
        </button>
      </form>
    <?php endif; ?>

    <div style="margin-top:1.5rem; font-size:0.85rem; color:var(--text-muted);">
      <a href="login.php" style="color:var(--accent);">← Back to Login</a>
    </div>
  </div>
</body>
</html>
