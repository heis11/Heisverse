<?php
declare(strict_types=1);

/**
 * HEISVERSE — Core Configuration & Security Bootstrap
 */
/*
|--------------------------------------------------------------------------
| Environment
|--------------------------------------------------------------------------
*/

$appEnv = getenv('APP_ENV') ?: 'development';

define('APP_ENV', $appEnv);

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

ini_set('log_errors', '1');

if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0775, true);
}

ini_set('error_log', __DIR__ . '/logs/php-error.log');

/*
|--------------------------------------------------------------------------
| Load private .env file
|--------------------------------------------------------------------------
*/

$envFile = __DIR__ . '/.env';

if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if (
            $line === ''
            || str_starts_with($line, '#')
            || !str_contains($line, '=')
        ) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($name !== '' && getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Application settings
|--------------------------------------------------------------------------
*/

define('APP_NAME', getenv('APP_NAME') ?: 'HEISVERSE');

/*
|--------------------------------------------------------------------------
| Application secret
|--------------------------------------------------------------------------
*/

$appSecret = getenv('APP_SECRET') ?: '';

if (APP_ENV === 'production' && strlen($appSecret) < 32) {
    error_log('HEISVERSE: APP_SECRET is missing or too short.');
    http_response_code(500);
    exit('Server configuration error.');
}

if ($appSecret === '') {
    $appSecret = 'local-development-only-secret-change-this';
}

define('APP_SECRET', $appSecret);

/*
|--------------------------------------------------------------------------
| Database configuration
|--------------------------------------------------------------------------
*/

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'heisverse');
define('DB_USER', getenv('DB_USER') ?: 'heisverse_user');
define('DB_PASS', getenv('DB_PASS') !== false
    ? (string)getenv('DB_PASS')
    : ''
);

/*
|--------------------------------------------------------------------------
| TMDB configuration
|--------------------------------------------------------------------------
*/

define('TMDB_API_KEY', getenv('TMDB_API_KEY') ?: '');
define('TMDB_LANGUAGE', getenv('TMDB_LANGUAGE') ?: 'en-US');
define('TMDB_CACHE_TTL', (int)(getenv('TMDB_CACHE_TTL') ?: 3600));
define('TMDB_TIMEOUT', (int)(getenv('TMDB_TIMEOUT') ?: 8));

/*
|--------------------------------------------------------------------------
| Other API keys
|--------------------------------------------------------------------------
*/

define('YOUTUBE_API_KEY', getenv('YOUTUBE_API_KEY') ?: '');
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');

/*
|--------------------------------------------------------------------------
| Application directories
|--------------------------------------------------------------------------
*/

define('CACHE_DIR', __DIR__ . '/cache');
define('LOG_DIR', __DIR__ . '/logs');

/*
|--------------------------------------------------------------------------
| Secure sessions
|--------------------------------------------------------------------------
*/

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (
        isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
    )
);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

/*
|--------------------------------------------------------------------------
| XSS protection
|--------------------------------------------------------------------------
*/

function e(?string $string): string
{
    return htmlspecialchars(
        $string ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/*
|--------------------------------------------------------------------------
| JSON API response
|--------------------------------------------------------------------------
*/

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF request validation
|--------------------------------------------------------------------------
*/

function require_csrf_header(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ($_POST['csrf_token'] ?? null);

    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        json_response([
            'success' => false,
            'message' => 'Security token expired. Please refresh the page.'
        ], 419);
    }
}

/*
|--------------------------------------------------------------------------
| Session rate limiter
|--------------------------------------------------------------------------
*/

function rate_limit(string $bucket, int $limit, int $seconds): bool
{
    $now = time();

    $hits = $_SESSION['rl'][$bucket] ?? [];

    $hits = array_values(array_filter(
        $hits,
        fn($timestamp) => $timestamp > $now - $seconds
    ));

    if (count($hits) >= $limit) {
        $_SESSION['rl'][$bucket] = $hits;
        return false;
    }

    $hits[] = $now;

    $_SESSION['rl'][$bucket] = $hits;

    return true;
}
