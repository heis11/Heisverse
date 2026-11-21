<?php
/**
 * HEISVERSE — Authentication & Authorization Guard
 * Secure user sessions, password hashing, role-based access, and login protection.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class Auth {
    /**
     * Check if a user is currently authenticated
     */
    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get the currently authenticated user's data
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        $db = Database::getConnection();
        if (!$db) {
            return $_SESSION['user_cached'] ?? null;
        }

        try {
            $stmt = $db->prepare('SELECT id, username, email, role, avatar, bio, theme_pref, accent_pref, language_pref, is_active FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || (int)$user['is_active'] !== 1) {
                self::logout();
                return null;
            }

            $_SESSION['user_cached'] = $user;
            return $user;
        } catch (PDOException $e) {
            return $_SESSION['user_cached'] ?? null;
        }
    }

    /**
     * Check if current user has administrator privileges
     */
    public static function isAdmin(): bool {
        $user = self::user();
        return $user !== null && ($user['role'] === 'admin');
    }

    /**
     * Enforce login: redirect unauthenticated visitors to login screen
     */
    public static function requireLogin(): void {
        if (!self::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Enforce admin access: deny non-admin users
     */
    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('<!DOCTYPE html><html><head><title>403 Forbidden</title><style>body{background:#0b0f19;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{text-align:center;padding:2rem;background:rgba(255,255,255,0.05);border-radius:12px;border:1px solid rgba(255,255,255,0.1);}</style></head><body><div><h1>403 Forbidden</h1><p>You do not have administrative privileges to access this area.</p><a href="../index.php" style="color:#60a5fa;">Return to Home</a></div></body></html>');
        }
    }

    /**
     * Attempt to log in a user with email/username and password
     */
    public static function attempt(string $identifier, string $password): array {
        $db = Database::getConnection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection unavailable. Start MySQL in the XAMPP Control Panel and check the DB_* values in .env.'];
        }

        $identifier = trim($identifier);
        if (empty($identifier) || empty($password)) {
            return ['success' => false, 'message' => 'Please provide both your username/email and password.'];
        }

        try {
            $stmt = $db->prepare('SELECT id, username, email, password_hash, role, is_active FROM users WHERE email = :id1 OR username = :id2 LIMIT 1');
            $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials. Please verify your details and try again.'];
            }

            if ((int)$user['is_active'] !== 1) {
                return ['success' => false, 'message' => 'This account has been deactivated. Please contact support.'];
            }

            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'];

            return ['success' => true, 'user' => $user];
        } catch (PDOException $e) {
            error_log('[HEISVERSE AUTH] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Sign-in is temporarily unavailable. Please try again.'];
        }
    }

    /**
     * Register a new user securely
     */
    public static function register(string $username, string $email, string $password, string $confirmPassword, bool $acceptTerms): array {
        if (!$acceptTerms) {
            return ['success' => false, 'message' => 'You must accept the terms of service and privacy policy.'];
        }

        $username = trim($username);
        $email = trim(strtolower($email));

        if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => 'Username must be between 3 and 30 alphanumeric characters (letters, numbers, underscore only).'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }

        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        $db = Database::getConnection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection unavailable.'];
        }

        try {
            // Check if username or email is already taken
            $checkStmt = $db->prepare('SELECT id, username, email FROM users WHERE username = :u OR email = :e LIMIT 1');
            $checkStmt->execute([':u' => $username, ':e' => $email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if (strcasecmp($existing['username'], $username) === 0) {
                    return ['success' => false, 'message' => 'This username is already taken.'];
                }
                return ['success' => false, 'message' => 'An account with this email address already exists.'];
            }

            // Hash password securely
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Public registration must NEVER create administrators.
            $role = 'user';

            $insertStmt = $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :p, :r)');
            $insertStmt->execute([
                ':u' => $username,
                ':e' => $email,
                ':p' => $passwordHash,
                ':r' => $role
            ]);

            $newUserId = (int)$db->lastInsertId();

            // Auto-login the new user
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_name'] = $username;

            return ['success' => true, 'role' => $role, 'user_id' => $newUserId];
        } catch (PDOException $e) {
            error_log('[HEISVERSE REGISTER] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Registration is temporarily unavailable. Please try again.'];
        }
    }

    /**
     * Terminate user session and clear authentication cookies
     */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}
