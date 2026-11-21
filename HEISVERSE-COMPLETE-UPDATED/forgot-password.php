<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';
require_once __DIR__ . '/includes/mailer.php';

$message = '';
$devResetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Security session expired. Please refresh and try again.';
    } elseif (!rate_limit('password_reset_request', 5, 900)) {
        $message = 'Too many requests. Please wait a little while and try again.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

        // Always use the configured application URL, not the browser Host header.
        $baseUrl = rtrim((string)(getenv('APP_BASE_URL') ?: ''), '/');
        $appEnv = strtolower((string)(getenv('APP_ENV') ?: 'production'));

        $urlParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
        $validBaseUrl = is_array($urlParts)
            && in_array(strtolower($urlParts['scheme'] ?? ''), ['http', 'https'], true)
            && !empty($urlParts['host']);

        if (!$validBaseUrl && $appEnv === 'development') {
            $baseUrl = 'http://localhost:8080';
            $validBaseUrl = true;
        }

        $smtpConfigured =
            (string)getenv('SMTP_HOST') !== ''
            && (string)getenv('SMTP_USERNAME') !== ''
            && (string)getenv('SMTP_PASSWORD') !== ''
            && (string)getenv('MAIL_FROM_ADDRESS') !== '';

        if (!$validBaseUrl) {
            error_log('HEISVERSE password reset: APP_BASE_URL is missing or invalid.');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $db = Database::getConnection();

                if ($db) {
                    $stmt = $db->prepare(
                        'SELECT id, username FROM users WHERE email = :e LIMIT 1'
                    );
                    $stmt->execute([':e' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        // Generate a secure, one-time token. Only its hash is stored.
                        $plainToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $plainToken);
                        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                        $ins = $db->prepare(
                            'INSERT INTO password_resets
                            (user_id, token_hash, expires_at)
                            VALUES (:u, :t, :exp)'
                        );

                        $ins->execute([
                            ':u' => $user['id'],
                            ':t' => $tokenHash,
                            ':exp' => $expiresAt
                        ]);

                        $resetUrl = $baseUrl . '/reset-password.php?token='
                            . rawurlencode($plainToken);

                        if ($smtpConfigured) {
                            $sent = heisverse_send_password_reset_email(
                                $email,
                                (string)$user['username'],
                                $resetUrl
                            );

                            if (!$sent) {
                                error_log('HEISVERSE password reset email delivery failed.');
                            }
                        } elseif ($appEnv === 'development') {
                            // Local-only fallback when SMTP has not been configured.
                            $devResetLink = $resetUrl;
                        }
                    }
                } else {
                    error_log('HEISVERSE password reset: database connection unavailable.');
                }
            } catch (Throwable $exception) {
                error_log('HEISVERSE password reset request could not be processed.');
            }
        }

        // Generic response prevents revealing whether an account exists.
        $message = 'If an active account exists with that email address, password reset instructions have been generated.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark-cinematic">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — HEISVERSE</title>
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
    <h2 style="font-size:1.6rem; font-weight:800; margin-bottom:0.4rem;">Reset Password</h2>
    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">
      Enter your registered email address to receive password recovery instructions.
    </p>

    <?php if (!empty($message)): ?>
      <div style="background:rgba(59,130,246,0.15); border:1px solid rgba(59,130,246,0.3); color:#93c5fd; padding:0.85rem; border-radius:8px; margin-bottom:1.2rem; font-size:0.9rem; text-align:left;">
        ℹ️ <?= e($message) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($devResetLink)): ?>
      <div style="background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; padding:1rem; border-radius:8px; margin-bottom:1.2rem; font-size:0.85rem; text-align:left;">
        <b>🛠️ Local Development Testing Link:</b><br>
        <span style="font-size:0.8rem; word-break:break-all;">SMTP is not configured yet. Click below to test your reset immediately:</span><br>
        <a href="<?= e($devResetLink) ?>" style="color:#38bdf8; font-weight:700; text-decoration:underline; display:inline-block; margin-top:0.4rem;">
          Proceed to Password Reset →
        </a>
      </div>
    <?php endif; ?>

    <form method="POST" action="forgot-password.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <div style="text-align:left; margin-bottom:1.2rem;">
        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:0.4rem;">
          Account Email
        </label>
        <input type="email" name="email" class="form-control" placeholder="user@example.com" required autofocus>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.8rem;">
        Send Recovery Instructions
      </button>
    </form>

    <div style="margin-top:1.5rem; font-size:0.85rem; color:var(--text-muted);">
      <a href="login.php" style="color:var(--accent);">← Back to Login</a>
    </div>
  </div>
</body>
</html>
