<?php
/**
 * HEISVERSE - Administrator Account Creation
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();

$db = Database::getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Refresh and try again.';
    } elseif (!$db) {
        $error = 'Database connection is unavailable.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 30 ||
            !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username must be 3-30 characters and contain only letters, numbers, and underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must contain at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $check = $db->prepare(
                    'SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1'
                );
                $check->execute([':u' => $username, ':e' => $email]);

                if ($check->fetch()) {
                    $error = 'That username or email is already registered.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $insert = $db->prepare(
                        "INSERT INTO users (username, email, password_hash, role, is_active)
                         VALUES (:u, :e, :p, 'admin', 1)"
                    );

                    $insert->execute([
                        ':u' => $username,
                        ':e' => $email,
                        ':p' => $hash
                    ]);

                    $success = 'Administrator account created successfully.';
                }
            } catch (Throwable $e) {
                error_log('[HEISVERSE ADMIN REGISTER] ' . $e->getMessage());
                $error = 'Could not create the administrator account. Check the application log.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark-cinematic">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Administrator - HEISVERSE</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height:100vh; display:grid; place-items:center; padding:24px; background:#07090e; color:#fff; font-family:Arial,sans-serif; }
        .admin-register-card { width:100%; max-width:480px; padding:32px; border:1px solid #303747; border-radius:16px; background:#111722; }
        .admin-register-card h1 { margin:0 0 10px; font-size:25px; }
        .admin-register-card p { color:#aab4c5; line-height:1.6; }
        .admin-register-card label { display:block; margin:16px 0 7px; color:#cbd5e1; }
        .admin-register-card input { width:100%; padding:13px; border-radius:8px; border:1px solid #394456; background:#080d16; color:#fff; box-sizing:border-box; }
        .admin-register-card button { width:100%; padding:14px; margin-top:22px; border:0; border-radius:8px; background:#7c3aed; color:#fff; font-weight:bold; cursor:pointer; }
        .notice { padding:12px; margin:15px 0; border-radius:8px; background:#263247; }
        .error { color:#fecaca; background:#471f29; }
        .success { color:#bbf7d0; background:#123b2b; }
        a { color:#a78bfa; }
    </style>
</head>
<body>
<main class="admin-register-card">
    <h1>Create Administrator</h1>
    <p>This protected page creates administrator accounts. Only an authenticated administrator can access it.</p>

    <?php if ($error): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="notice success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <label for="username">Administrator username</label>
        <input id="username" name="username" minlength="3" maxlength="30"
               pattern="[A-Za-z0-9_]+" required>

        <label for="email">Administrator email</label>
        <input id="email" type="email" name="email" required>

        <label for="password">Password (at least 8 characters)</label>
        <input id="password" type="password" name="password" minlength="8" required>

        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" type="password" name="confirm_password" minlength="8" required>

        <button type="submit">Create Administrator Account</button>
    </form>

    <p><a href="index.php">Back to Admin Dashboard</a></p>
</main>
</body>
</html>