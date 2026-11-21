<?php
/**
 * HEISVERSE — Setup self-test (command line only).
 *
 * Run it from the project folder in a Windows terminal:
 *   C:\xampp\php\php.exe scripts\check_setup.php
 *
 * It checks PHP extensions, .env, the database, TMDB and the image URLs.
 * It never prints your API key or database password.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/tmdb.php';

$fails = 0;
function ok(string $m): void   { echo "  [ OK ] {$m}\n"; }
function bad(string $m): void  { global $fails; $fails++; echo "  [FAIL] {$m}\n"; }
function warn(string $m): void { echo "  [WARN] {$m}\n"; }

echo "HEISVERSE setup check\n=====================\n\n";

echo "1) PHP\n";
version_compare(PHP_VERSION, '8.0.0', '>=') ? ok('PHP ' . PHP_VERSION) : bad('PHP 8.0+ required, found ' . PHP_VERSION);
foreach (['pdo_mysql', 'curl', 'mbstring', 'json', 'openssl'] as $ext) {
    extension_loaded($ext) ? ok("extension {$ext}") : bad("extension {$ext} is missing (enable it in C:\\xampp\\php\\php.ini)");
}

echo "\n2) .env\n";
is_file(__DIR__ . '/../.env') ? ok('.env found') : bad('.env not found. Copy .env.example to .env');
Tmdb::isConfigured() ? ok('TMDB_API_KEY is set (hidden)') : bad('TMDB_API_KEY is empty');
DB_PASS !== '' ? ok('DB_PASS is set (hidden)') : warn('DB_PASS is empty. Fine only if your MySQL user has no password.');

echo "\n3) Database " . DB_NAME . " @ " . DB_HOST . ":" . DB_PORT . "\n";
$db = Database::getConnection();
if (!$db) {
    bad('Cannot connect: ' . Database::getLastError());
} else {
    ok('connected as ' . DB_USER);
    $need = ['users', 'movies', 'watchlists', 'movie_sources', 'system_settings', 'tmdb_watchlist'];
    foreach ($need as $t) {
        $st = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t');
        $st->execute([':s' => DB_NAME, ':t' => $t]);
        if ((int)$st->fetchColumn() > 0) {
            ok("table {$t}");
        } else {
            $t === 'tmdb_watchlist' ? bad("table {$t} missing: run migrations/004_tmdb_integration.sql") : bad("table {$t} missing");
        }
    }
    foreach (['content_type', 'country', 'original_language'] as $col) {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = 'movies' AND COLUMN_NAME = :c");
        $st->execute([':s' => DB_NAME, ':c' => $col]);
        (int)$st->fetchColumn() > 0 ? ok("movies.{$col}") : bad("movies.{$col} missing: run migrations/004b_ensure_global_columns.sql");
    }
}

echo "\n4) TMDB\n";
$checks = [
    'popular movies'      => fn() => Tmdb::popularMovies(),
    'trending movies'     => fn() => Tmdb::trendingMovies('week'),
    'popular TV series'   => fn() => Tmdb::popularTv(),
    'anime (discover)'    => fn() => Tmdb::discover(['type' => 'tv', 'anime' => true]),
    'search "naruto"'     => fn() => Tmdb::search('naruto', 'anime'),
    'discover filtered'   => fn() => Tmdb::discover(['type' => 'movie', 'genre' => 28, 'year' => 2023, 'min_rating' => 6, 'sort' => 'rating.desc']),
];
$first = null;
foreach ($checks as $name => $fn) {
    try {
        $r = $fn();
        $n = count($r['items']);
        $n > 0 ? ok("{$name}: {$n} results") : warn("{$name}: 0 results");
        $first = $first ?? ($r['items'][0] ?? null);
    } catch (TmdbException $e) {
        bad("{$name}: " . $e->getMessage());
        if ($e->httpStatus === 401 || $e->httpStatus === 0) break;
    }
}
try {
    $d = Tmdb::details('movie', 550);
    ok('movie details: ' . $d['title'] . ($d['trailer'] ? ' (trailer found)' : ' (no trailer)'));
} catch (TmdbException $e) {
    bad('movie details: ' . $e->getMessage());
}

echo "\n5) Images\n";
if ($first) {
    echo '  poster   : ' . (Tmdb::image($first['poster_path'], 'poster', 'w342') ?? '(none)') . "\n";
    echo '  backdrop : ' . (Tmdb::image($first['backdrop_path'], 'backdrop', 'w1280') ?? '(none)') . "\n";
    echo "  Paste those two links into your browser: you should see real artwork.\n";
}

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "{$fails} CHECK(S) FAILED. Read the [FAIL] lines above.\n");
exit($fails === 0 ? 0 : 1);
