<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';

Auth::requireLogin();

$currentUser = Auth::user();
if (!$currentUser) {
    header('Location: /login.php');
    exit;
}

$db = Database::getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } elseif (!$db) {
        $error = 'Database connection is unavailable. Please try again later.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'profile') {
                $username = trim((string)($_POST['username'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $bio = trim((string)($_POST['bio'] ?? ''));
                $avatar = trim((string)($_POST['avatar'] ?? ''));
                $isPublic = isset($_POST['is_public']) ? 1 : 0;

                if (strlen($username) < 3 || strlen($username) > 30 ||
                    !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    throw new RuntimeException('Username must be 3–30 characters and use only letters, numbers and underscores.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address.');
                }

                if (strlen($bio) > 255) {
                    throw new RuntimeException('Your bio must be 255 characters or fewer.');
                }

                if ($avatar !== '') {
                    if (!filter_var($avatar, FILTER_VALIDATE_URL) ||
                        !in_array(strtolower((string)parse_url($avatar, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                        throw new RuntimeException('Avatar must be a valid HTTP or HTTPS image URL.');
                    }
                } else {
                    $avatar = 'assets/icons/default-avatar.png';
                }

                $check = $db->prepare(
                    'SELECT id FROM users WHERE (username = :u OR email = :e) AND id <> :id LIMIT 1'
                );
                $check->execute([
                    ':u' => $username,
                    ':e' => $email,
                    ':id' => $currentUser['id']
                ]);

                if ($check->fetch()) {
                    throw new RuntimeException('That username or email address is already in use.');
                }

                $update = $db->prepare(
                    'UPDATE users SET username = :u, email = :e, bio = :b, avatar = :a, is_public = :p WHERE id = :id'
                );
                $update->execute([
                    ':u' => $username,
                    ':e' => $email,
                    ':b' => $bio,
                    ':a' => $avatar,
                    ':p' => $isPublic,
                    ':id' => $currentUser['id']
                ]);

                unset($_SESSION['user_cached']);
                $_SESSION['user_name'] = $username;
                $currentUser = Auth::user();
                $message = 'Your profile has been updated successfully.';
            } elseif ($action === 'password') {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');

                if (strlen($newPassword) < 8) {
                    throw new RuntimeException('Your new password must contain at least 8 characters.');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new RuntimeException('The new passwords do not match.');
                }

                $query = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $query->execute([':id' => $currentUser['id']]);
                $account = $query->fetch();

                if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
                    throw new RuntimeException('Your current password is incorrect.');
                }

                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $db->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
                $update->execute([
                    ':p' => $hash,
                    ':id' => $currentUser['id']
                ]);

                session_regenerate_id(true);
                $message = 'Your password has been changed successfully.';
            } else {
                $error = 'Invalid form submission.';
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('[HEISVERSE PROFILE] ' . $e->getMessage());
            $error = 'Unable to save your changes. Please try again.';
        }
    }
}

$currentUser = Auth::user() ?? $currentUser;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>My Profile — HEISVERSE</title>
    <link rel="stylesheet" href="assets/css/style.css?v=heisverse-profile-1">
    <style>
        .profile-page{max-width:850px;margin:45px auto;padding:0 20px}
        .profile-panel{background:var(--bg-glass);border:1px solid var(--border-glass);border-radius:16px;padding:25px;margin:20px 0}
        .profile-panel h2{margin-top:0}
        .profile-field{margin:17px 0}
        .profile-field label{display:block;margin-bottom:8px;font-weight:600}
        .profile-field textarea{min-height:100px;resize:vertical}
        .profile-check{display:flex;align-items:center;gap:10px;margin:18px 0}
        .profile-message{padding:13px 16px;border-radius:9px;margin:18px 0;background:rgba(34,197,94,.12);color:#86efac}
        .profile-error{padding:13px 16px;border-radius:9px;margin:18px 0;background:rgba(239,68,68,.12);color:#fca5a5}
        .profile-avatar{width:90px;height:90px;border-radius:50%;object-fit:cover;border:2px solid var(--accent)}
        .profile-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
    </style>
</head>
<body>
<?php heis_header('', $currentUser); ?>

<main class="profile-page">
    <h1>My Profile</h1>
    <p style="color:var(--text-muted)">Manage your HEISVERSE account information and security.</p>

    <?php if ($message !== ''): ?>
        <div class="profile-message"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="profile-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="profile-panel">
        <h2>Personal information</h2>
        <img class="profile-avatar"
             src="<?= e($currentUser['avatar'] ?: 'assets/icons/default-avatar.png') ?>"
             alt="Your profile avatar"
             onerror="this.onerror=null;this.src='assets/icons/default-avatar.png';">

        <form method="POST" action="profile.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile">

            <div class="profile-field">
                <label for="username">Username</label>
                <input class="form-control" id="username" name="username"
                       maxlength="30" required value="<?= e($currentUser['username']) ?>">
            </div>

            <div class="profile-field">
                <label for="email">Email address</label>
                <input class="form-control" type="email" id="email" name="email"
                       required value="<?= e($currentUser['email']) ?>">
            </div>

            <div class="profile-field">
                <label for="avatar">Avatar image URL</label>
                <input class="form-control" type="url" id="avatar" name="avatar"
                       placeholder="https://example.com/avatar.jpg"
                       value="<?= e($currentUser['avatar'] ?? '') ?>">
            </div>

            <div class="profile-field">
                <label for="bio">About me</label>
                <textarea class="form-control" id="bio" name="bio"
                          maxlength="255"><?= e($currentUser['bio'] ?? '') ?></textarea>
            </div>

            <label class="profile-check">
                <input type="checkbox" name="is_public" value="1"
                    <?= !empty($currentUser['is_public']) ? 'checked' : '' ?>>
                Allow my profile to be public
            </label>

            <div class="profile-actions">
                <button class="btn btn-primary" type="submit">Save Profile</button>
            </div>
        </form>
    </section>

    <section class="profile-panel">
        <h2>Change password</h2>
        <form method="POST" action="profile.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">

            <div class="profile-field">
                <label for="current_password">Current password</label>
                <input class="form-control" type="password" id="current_password"
                       name="current_password" autocomplete="current-password" required>
            </div>

            <div class="profile-field">
                <label for="new_password">New password</label>
                <input class="form-control" type="password" id="new_password"
                       name="new_password" minlength="8" autocomplete="new-password" required>
            </div>

            <div class="profile-field">
                <label for="confirm_password">Confirm new password</label>
                <input class="form-control" type="password" id="confirm_password"
                       name="confirm_password" minlength="8" autocomplete="new-password" required>
            </div>

            <div class="profile-actions">
                <button class="btn btn-primary" type="submit">Change Password</button>
            </div>
        </form>
    </section>

    <p><a href="index.php">← Back to HEISVERSE</a></p>
</main>
</body>
</html>