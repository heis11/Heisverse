<?php
/**
 * HEISVERSE â€” Central RESTful JSON API Gateway
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/movie-sources.php';
require_once __DIR__ . '/includes/languages.php';
require_once __DIR__ . '/includes/tmdb.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = Database::getConnection();

// 1. Healthcheck Endpoint (Used by Docker & Render)
if ($action === 'health') {
    $dbOk = Database::isConnected();
    json_response([
        'status' => $dbOk ? 'healthy' : 'degraded',
        'app' => APP_NAME,
        'environment' => getenv('APP_ENV') ?: 'development',
        'database' => $dbOk ? 'connected' : 'error',
        'tmdb_key_configured' => Tmdb::isConfigured(),
        'timestamp' => date('c')
    ], $dbOk ? 200 : 503);
}

// 2. Fetch Multi-Source Player
if ($action === 'get_player') {
    $movieId = (int)($_GET['movie_id'] ?? 0);
    $sourceId = !empty($_GET['source_id']) ? (int)$_GET['source_id'] : null;

    if ($movieId <= 0 || !$db) {
        json_response(['success' => false, 'message' => 'Invalid movie ID or database unavailable.'], 400);
    }

    $stmt = $db->prepare('SELECT * FROM movies WHERE id = :id AND is_published = 1 LIMIT 1');
    $stmt->execute([':id' => $movieId]);
    $movie = $stmt->fetch();

    if (!$movie) {
        json_response(['success' => false, 'message' => 'Title not found.'], 404);
    }

    $sources = MovieSources::getSourcesForMovie($movieId);
    $activeSource = null;
    if ($sourceId !== null) {
        foreach ($sources as $s) {
            if ((int)$s['id'] === $sourceId) {
                $activeSource = $s;
                break;
            }
        }
    }

    $resume = 0;
    if (Auth::check()) {
        $histStmt = $db->prepare('SELECT last_position FROM watch_history WHERE user_id = :uid AND movie_id = :mid LIMIT 1');
        $histStmt->execute([':uid' => $_SESSION['user_id'], ':mid' => $movieId]);
        $resume = (int)($histStmt->fetchColumn() ?: 0);
    }

    $playerHtml = MovieSources::renderPlayer($movie, $activeSource, $resume);
    json_response([
        'success' => true,
        'movie_id' => $movieId,
        'title' => $movie['title'],
        'html' => $playerHtml
    ]);
}

// 3. Fetch Seasons & Episodes for TV Series / Anime
if ($action === 'get_episodes') {
    $movieId = (int)($_GET['movie_id'] ?? 0);
    if ($movieId <= 0 || !$db) {
        json_response(['success' => false, 'message' => 'Invalid series ID.'], 400);
    }

    try {
        $epStmt = $db->prepare('SELECT * FROM episodes WHERE movie_id = :mid ORDER BY season_id ASC, episode_number ASC');
        $epStmt->execute([':mid' => $movieId]);
        $episodes = $epStmt->fetchAll();

        json_response([
            'success' => true,
            'movie_id' => $movieId,
            'episodes' => $episodes
        ]);
    } catch (PDOException $e) {
        error_log('[HEISVERSE API] ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Server error. Please try again.'], 500);
    }
}

// 4. Live Search Suggestions (local library + real TMDB results)
if ($action === 'search_suggestions' || $action === 'tmdb_search') {
    if (!Auth::check()) {
        json_response(['success' => false, 'message' => 'Please log in to search.'], 401);
    }
    if (!rate_limit('search', 90, 60)) {
        json_response(['success' => false, 'message' => 'Too many searches. Please wait a moment.'], 429);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $filter = (string)($_GET['filter'] ?? 'all');
    if (!in_array($filter, ['all', 'movie', 'tv', 'anime'], true)) $filter = 'all';
    $page = max(1, min(500, (int)($_GET['page'] ?? 1)));
    $limit = $action === 'search_suggestions' ? 6 : 40;

    if (mb_strlen($q) < 2) {
        json_response(['success' => true, 'local' => [], 'results' => [], 'page' => 1, 'total_pages' => 0, 'total_results' => 0]);
    }

    // a) HEISVERSE library (local database)
    $local = [];
    if ($db && $page === 1 && $action === 'search_suggestions') {
        try {
            $stmt = $db->prepare('
                SELECT id, title, category, released
                FROM movies
                WHERE is_published = 1 AND (title LIKE :q OR category LIKE :q3)
                ORDER BY is_featured DESC, id DESC
                LIMIT 4
            ');
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $stmt->execute([':q' => $like, ':q3' => $like]);
            $local = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[HEISVERSE SEARCH] ' . $e->getMessage());
        }
    }

    // b) TMDB
    $tmdbOut = [];
    $meta = ['page' => 1, 'total_pages' => 0, 'total_results' => 0];
    $error = null;
    try {
        $res = Tmdb::search($q, $filter, $page);
        $genreMap = Tmdb::genres('movie') + Tmdb::genres('tv');
        foreach (array_slice($res['items'], 0, $limit) as $item) {
            $tmdbOut[] = Tmdb::toCard($item, $genreMap);
        }
        $meta = ['page' => $res['page'], 'total_pages' => $res['total_pages'], 'total_results' => $res['total_results']];
    } catch (TmdbException $e) {
        $error = $e->getMessage();
    }

    json_response([
        'success' => $error === null || !empty($local),
        'local'   => $local,
        'results' => $tmdbOut,
        'error'   => $error,
    ] + $meta);
}

// 4b. Add / remove a TMDB title (movie or TV) from the user's watchlist
if ($action === 'toggle_tmdb_watchlist') {
    if (!Auth::check()) {
        json_response(['success' => false, 'message' => 'Please log in to manage your watchlist.'], 401);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(['success' => false, 'message' => 'POST required.'], 405);
    }
    require_csrf_header();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tmdbId = (int)($input['tmdb_id'] ?? 0);
    $type = ($input['media_type'] ?? '') === 'tv' ? 'tv' : 'movie';
    if ($tmdbId <= 0 || !$db) {
        json_response(['success' => false, 'message' => 'Invalid title or database unavailable.'], 400);
    }

    $userId = (int)$_SESSION['user_id'];
    try {
        $check = $db->prepare('SELECT 1 FROM tmdb_watchlist WHERE user_id = :u AND tmdb_id = :t AND media_type = :m');
        $check->execute([':u' => $userId, ':t' => $tmdbId, ':m' => $type]);
        if ($check->fetch()) {
            $del = $db->prepare('DELETE FROM tmdb_watchlist WHERE user_id = :u AND tmdb_id = :t AND media_type = :m');
            $del->execute([':u' => $userId, ':t' => $tmdbId, ':m' => $type]);
            json_response(['success' => true, 'in_list' => false]);
        }
        $title = mb_substr(trim((string)($input['title'] ?? '')), 0, 200);
        $poster = (string)($input['poster_path'] ?? '');
        if ($poster !== '' && !preg_match('#^/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp)$#', $poster)) $poster = '';
        $year = preg_match('/^\d{4}$/', (string)($input['year'] ?? '')) ? (string)$input['year'] : '';
        $ins = $db->prepare('INSERT INTO tmdb_watchlist (user_id, tmdb_id, media_type, title, poster_path, year) VALUES (:u, :t, :m, :ti, :p, :y)');
        $ins->execute([':u' => $userId, ':t' => $tmdbId, ':m' => $type, ':ti' => $title, ':p' => $poster, ':y' => $year]);
        json_response(['success' => true, 'in_list' => true]);
    } catch (PDOException $e) {
        error_log('[HEISVERSE TMDB WATCHLIST] ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Watchlist table missing. Run migrations/004_tmdb_integration.sql (see README).'], 500);
    }
}

// 5. Track Watch Progress
if ($action === 'track_watch') {
    if (!Auth::check()) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $movieId = (int)($input['movie_id'] ?? 0);
    $position = (int)($input['position'] ?? 0);
    $duration = (int)($input['duration'] ?? 0);

    if ($movieId > 0 && $position >= 0) {
        MovieSources::updateWatchProgress((int)$_SESSION['user_id'], $movieId, $position, $duration);
        json_response(['success' => true]);
    }
    json_response(['success' => false, 'message' => 'Invalid parameters.'], 400);
}

// 6. Toggle Watchlist
if ($action === 'toggle_watchlist') {
    if (!Auth::check()) {
        json_response(['success' => false, 'message' => 'Please log in to manage your watchlist.'], 401);
    }
    require_csrf_header();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $movieId = (int)($input['movie_id'] ?? 0);

    if ($movieId <= 0 || !$db) {
        json_response(['success' => false, 'message' => 'Invalid movie request.'], 400);
    }

    $userId = (int)$_SESSION['user_id'];
    $checkStmt = $db->prepare('SELECT 1 FROM watchlists WHERE user_id = :u AND movie_id = :m');
    $checkStmt->execute([':u' => $userId, ':m' => $movieId]);

    if ($checkStmt->fetch()) {
        $del = $db->prepare('DELETE FROM watchlists WHERE user_id = :u AND movie_id = :m');
        $del->execute([':u' => $userId, ':m' => $movieId]);
        json_response(['success' => true, 'in_list' => false]);
    } else {
        $ins = $db->prepare('INSERT INTO watchlists (user_id, movie_id) VALUES (:u, :m)');
        $ins->execute([':u' => $userId, ':m' => $movieId]);
        json_response(['success' => true, 'in_list' => true]);
    }
}

// 7. Save User Theme Preference
if ($action === 'save_theme') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $theme = trim((string)($input['theme'] ?? 'cine-01'));
    if (!preg_match('/^[a-z0-9\-_]{1,30}$/i', $theme)) { $theme = 'cine-01'; }

    if (Auth::check() && $db) {
        $stmt = $db->prepare('UPDATE users SET theme_pref = :t WHERE id = :u');
        $stmt->execute([':t' => $theme, ':u' => $_SESSION['user_id']]);
    }
    json_response(['success' => true]);
}

// 8. Save User Accent Preference
if ($action === 'save_accent') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $accent = trim((string)($input['accent'] ?? '#3b82f6'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) { $accent = '#3b82f6'; }

    if (Auth::check() && $db) {
        $stmt = $db->prepare('UPDATE users SET accent_pref = :a WHERE id = :u');
        $stmt->execute([':a' => $accent, ':u' => $_SESSION['user_id']]);
    }
    json_response(['success' => true]);
}

// 9. HEISVERSE Local Movie Assistant (No external API required)
if ($action === 'ai_assistant') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $prompt = trim((string)($input['prompt'] ?? ''));
    $movieId = (int)($input['movie_id'] ?? 0);

    if ($prompt === '') {
        json_response(['success' => false, 'message' => 'Please enter a question.'], 400);
    }

    if (!$db) {
        json_response(['success' => false, 'message' => 'Movie database is unavailable.'], 503);
    }

    $lower = strtolower($prompt);

    if (preg_match('/\b(hello|hi|hey|good morning|good evening)\b/i', $prompt)) {
        json_response([
            'success' => true,
            'reply' => 'Hello! Welcome to HEISVERSE. I can search movies, explain titles, and recommend films from our catalogue.',
            'suggestions' => ['Recommend action movies', 'Find comedy movies', 'Help me explore HEISVERSE']
        ]);
    }

    if (preg_match('/\b(help|what can you do|how to use)\b/i', $prompt)) {
        json_response([
            'success' => true,
            'reply' => 'I am the HEISVERSE local movie assistant. Ask me to find a movie, search a genre, explore our catalogue, or get recommendations. You can also use the microphone to speak and Read Aloud to hear my answers.',
            'suggestions' => ['Find a movie', 'Recommend action movies', 'Recommend series']
        ]);
    }

    // If a movie card opened the assistant, use its real database record.
    if ($movieId > 0) {
        $stmt = $db->prepare('SELECT title, category, released, about, director, cast_list FROM movies WHERE id = :id AND is_published = 1 LIMIT 1');
        $stmt->execute([':id' => $movieId]);
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($movie) {
            $reply = $movie['title'] . ' (' . $movie['released'] . ') is listed in our ' . $movie['category'] . ' catalogue.';
            if (!empty($movie['about'])) $reply .= ' Overview: ' . $movie['about'];
            if (!empty($movie['director'])) $reply .= ' Director: ' . $movie['director'] . '.';
            json_response(['success' => true, 'reply' => $reply]);
        }
    }

    // Detect a real category already present in the catalogue.
    $category = null;
    $categories = $db->query("SELECT DISTINCT category FROM movies WHERE is_published = 1")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($categories as $item) {
        if ($item !== '' && stripos($prompt, $item) !== false) {
            $category = $item;
            break;
        }
    }

    if ($category !== null) {
        $stmt = $db->prepare('SELECT title, category, released, about FROM movies WHERE is_published = 1 AND category = :category ORDER BY is_featured DESC, rating_avg DESC, released DESC LIMIT 5');
        $stmt->execute([':category' => $category]);
        $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $heading = 'Here are titles in the ' . $category . ' category:';
    } else {
        $term = preg_replace('/\b(please|can you|could you|recommend|suggest|find|search|show me|show|movies|movie|films|film|series|about|tell me|what is|details|information|for me|some|any|the|a|an|of|in|on)\b/i', ' ', $prompt);
        $term = trim(preg_replace('/\s+/', ' ', $term));

        if ($term === '' || preg_match('/\b(recommend|suggest|best|popular)\b/i', $prompt)) {
            $stmt = $db->query('SELECT title, category, released, about FROM movies WHERE is_published = 1 ORDER BY is_featured DESC, rating_avg DESC, released DESC LIMIT 5');
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $heading = 'Here are some titles from the HEISVERSE catalogue:';
        } else {
            $search = '%' . $term . '%';
            $stmt = $db->prepare('SELECT title, category, released, about FROM movies WHERE is_published = 1 AND (title LIKE :t OR category LIKE :c OR about LIKE :a OR director LIKE :d OR cast_list LIKE :cast) ORDER BY is_featured DESC, rating_avg DESC LIMIT 5');
            $stmt->execute([
                ':t' => $search,
                ':c' => $search,
                ':a' => $search,
                ':d' => $search,
                ':cast' => $search
            ]);
            $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $heading = 'I found these matching titles in HEISVERSE:';
        }
    }

    if (!$movies) {
        json_response([
            'success' => true,
            'reply' => 'I could not find a matching title in our current catalogue. Try a movie title or a category that appears on HEISVERSE.'
        ]);
    }

    $reply = $heading . "\n";
    foreach ($movies as $item) {
        $reply .= "\n- " . $item['title'] . ' (' . $item['released'] . ') - ' . $item['category'];
        if (!empty($item['about'])) {
            $reply .= ': ' . substr(strip_tags($item['about']), 0, 180);
        }
        $reply .= "\n";
    }

    json_response([
        'success' => true,
        'reply' => $reply,
        'suggestions' => ['Find another movie', 'Recommend popular titles']
    ]);
}
json_response(['success' => false, 'message' => 'Action not recognized.'], 404);
