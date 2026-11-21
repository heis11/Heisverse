<?php
/**
 * HEISVERSE — Secure Owner Initial Administrator Setup
 * One-time setup utility. Permanently locks itself upon completion.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

$db = Database::getConnection();
$error = '';
$success = '';

// Check if setup was already completed
$isLocked = false;
if ($db) {
    try {
        $check = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'initial_setup_completed'")->fetchColumn();
        if ($check === '1') {
            $isLocked = true;
        }
    } catch (PDOException $e) {}
}

// Extra security: initial setup is unavailable once ANY administrator exists.
if ($db) {
    try {
        $existingAdminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($existingAdminCount > 0) {
            $isLocked = true;
        }
    } catch (PDOException $e) {
        $isLocked = true;
        error_log('[HEISVERSE SETUP] Could not verify administrator count: ' . $e->getMessage());
    }
}
if ($isLocked) {
    die('<!DOCTYPE html><html><head><title>Setup Disabled</title><style>body{background:#07090e;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{text-align:center;padding:2rem;background:rgba(255,255,255,0.05);border-radius:12px;border:1px solid rgba(255,255,255,0.1);max-width:480px;}</style></head><body><div><h2>🔒 Initial Setup Already Completed</h2><p>For system security, initial setup is permanently locked. To manage administrators, log in as an existing administrator or contact server owner.</p><a href="login.php" style="color:#38bdf8;font-weight:700;">Proceed to Login →</a></div></body></html>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked && $db) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Invalid username.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Create admin user or elevate existing
            $checkUser = $db->prepare('SELECT id FROM users WHERE email = :e OR username = :u LIMIT 1');
            $checkUser->execute([':e' => $email, ':u' => $username]);
            $existingId = $checkUser->fetchColumn();

            if ($existingId) {
                $upd = $db->prepare("UPDATE users SET password_hash = :p, role = 'admin', is_active = 1 WHERE id = :id");
                $upd->execute([':p' => $hash, ':id' => $existingId]);
            } else {
                $ins = $db->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (:u, :e, :p, 'admin', 1)");
                $ins->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
            }

            // Permanently lock initial setup
            $lock = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('initial_setup_completed', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
            $lock->execute();

            $success = "Initial administrator '{$username}' successfully created and initial setup is now permanently locked!";
            $isLocked = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark-cinematic">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Owner Initial Setup — HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .setup-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .setup-card {
      background: var(--bg-glass);
      backdrop-filter: blur(20px);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-lg);
      width: 100%;
      max-width: 480px;
      padding: 2.5rem;
      text-align: center;
    }
  </style>
</head>
<body class="setup-page">
  <div class="setup-card">
    <div style="margin-bottom:1rem;">
      <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:36px;">
    </div>
    <h2 style="font-size:1.6rem; font-weight:800; margin-bottom:0.4rem;">Owner Initial Admin Setup</h2>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;">
      Create your master administrator account. This utility will automatically disable itself after successful completion.
    </p>

    <?php if (!empty($error)): ?>
      <div style="background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); color:#fca5a5; padding:0.85rem; border-radius:8px; margin-bottom:1.2rem; font-size:0.9rem; text-align:left;">
        ⚠️ <?= e($error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; padding:1.2rem; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:left;">
        ✅ <?= e($success) ?>
        <div style="margin-top:1rem;">
          <a href="login.php" class="btn btn-primary" style="width:100%;">Proceed to Admin Login →</a>
        </div>
      </div>
    <?php else: ?>
      <form method="POST" action="setup-admin.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div style="text-align:left; margin-bottom:1rem;">
          <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.3rem;">Owner Username</label>
          <input type="text" name="username" class="form-control" placeholder="master_admin" required autofocus>
        </div>

        <div style="text-align:left; margin-bottom:1rem;">
          <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.3rem;">Owner Email</label>
          <input type="email" name="email" class="form-control" placeholder="owner@heisverse.com" required>
        </div>

        <div style="text-align:left; margin-bottom:1rem;">
          <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.3rem;">Password (min 8 characters)</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <div style="text-align:left; margin-bottom:1.5rem;">
          <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.3rem;">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.85rem;">
          Create Administrator & Lock Setup
        </button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
