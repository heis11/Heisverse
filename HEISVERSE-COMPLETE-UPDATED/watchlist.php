<?php
/**
 * HEISVERSE — User Watchlist & Continue Watching Hub
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();
$db = Database::getConnection();

$watchlistMovies = [];
$continueWatching = [];
$tmdbSaved = [];

if ($db) {
    try {
        // Fetch saved watchlist
        $stmt = $db->prepare('
            SELECT m.*, w.added_at 
            FROM watchlists w 
            JOIN movies m ON w.movie_id = m.id 
            WHERE w.user_id = :u 
            ORDER BY w.added_at DESC
        ');
        $stmt->execute([':u' => $currentUser['id']]);
        $watchlistMovies = $stmt->fetchAll();

        // Fetch continue watching
        $cStmt = $db->prepare('
            SELECT m.*, h.last_position, h.duration, h.watched_at 
            FROM watch_history h 
            JOIN movies m ON h.movie_id = m.id 
            WHERE h.user_id = :u AND h.completed = 0 AND h.last_position > 10
            ORDER BY h.watched_at DESC
        ');
        $cStmt->execute([':u' => $currentUser['id']]);
        $continueWatching = $cStmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[HEISVERSE WATCHLIST] ' . $e->getMessage());
    }

    // Titles saved from Discover / Search / Details (TMDB). Table comes from migration 004.
    try {
        $tStmt = $db->prepare('SELECT tmdb_id, media_type, title, poster_path, year FROM tmdb_watchlist WHERE user_id = :u ORDER BY added_at DESC');
        $tStmt->execute([':u' => $currentUser['id']]);
        $tmdbSaved = $tStmt->fetchAll();
    } catch (PDOException $e) {
        // migration 004 not applied yet: simply no TMDB titles to show
    }
}
require_once __DIR__ . '/includes/tmdb.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>My Watchlist & Activity — HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .progress-bar-bg {
      width: 100%;
      height: 6px;
      background: rgba(255, 255, 255, 0.15);
      border-radius: 999px;
      overflow: hidden;
      margin: 0.8rem 0;
    }
    .progress-bar-fill {
      height: 100%;
      background: var(--accent);
      border-radius: 999px;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <header class="navbar">
    <a href="index.php" class="nav-brand">
      <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:34px;">
    </a>
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="index.php#catalog">Movies</a></li>
      <li><a href="series.php">Series</a></li>
      <li><a href="anime.php">Anime</a></li>
      <li><a href="cartoons.php">Cartoons</a></li>
      <li><a href="categories.php">Categories</a></li>
      <li><a href="discover.php">Discover</a></li>
      <li><a href="watchlist.php" class="active">Watchlist</a></li>
    </ul>
    <div class="search-wrapper" role="search">
      <div class="search-input-box">
        <span aria-hidden="true" style="font-size:0.9rem; color:var(--text-dim); margin-right:0.4rem;">🔍</span>
        <input type="search" id="globalSearchInput" class="search-input" placeholder="Search movies, TV, anime…" autocomplete="off" aria-label="Search titles" aria-controls="searchSuggestionsDropdown">
      </div>
      <div class="search-dropdown" id="searchSuggestionsDropdown" role="listbox"></div>
    </div>
    <div class="nav-actions">
      <span style="font-size:0.85rem; color:var(--text-muted);">👤 <?= e($currentUser['username']) ?></span>
      <a href="logout.php" class="btn btn-glass btn-sm">Logout</a>
    </div>
  </header>

  <main class="container">
    <div style="margin-bottom: 2.5rem;">
      <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 0.5rem;">🔖 My Watchlist & History</h1>
      <p style="color: var(--text-muted);">Manage your saved cinematic watchlist and resume playback right where you left off.</p>
    </div>

    <!-- Continue Watching Section -->
    <?php if (!empty($continueWatching)): ?>
      <section style="margin-bottom: 3.5rem;">
        <h2 class="section-title">⏱️ Continue Watching</h2>
        <div class="movies-grid">
          <?php foreach ($continueWatching as $cw): ?>
            <?php 
              $pct = ($cw['duration'] > 0) ? min(100, round(($cw['last_position'] / $cw['duration']) * 100)) : 35;
            ?>
            <div class="movie-card">
              <div class="movie-art" style="background: <?= e($cw['bg']) ?>;">
                <span><?= e($cw['icon']) ?></span>
              </div>
              <div class="movie-body">
                <h3 class="movie-title"><?= e($cw['title']) ?></h3>
                <div class="progress-bar-bg">
                  <div class="progress-bar-fill" style="width: <?= (int)$pct ?>%;"></div>
                </div>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.8rem;">
                  <?= (int)$pct ?>% completed (<?= round($cw['last_position'] / 60) ?>m watched)
                </div>
                <button class="btn btn-primary btn-sm" style="width:100%;" onclick="openPlayerModal(<?= (int)$cw['id'] ?>)">
                  ▶ Resume Watching
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- Saved from Discover / Search (TMDB) -->
    <?php if (!empty($tmdbSaved)): ?>
      <section style="margin-bottom: 3.5rem;">
        <h2 class="section-title">🌐 Saved from Discover (<?= count($tmdbSaved) ?>)</h2>
        <div class="movies-grid">
          <?php foreach ($tmdbSaved as $t): ?>
            <?php $tUrl = 'title.php?type=' . ($t['media_type'] === 'tv' ? 'tv' : 'movie') . '&id=' . (int)$t['tmdb_id']; $tImg = Tmdb::image($t['poster_path'] ?: null, 'poster', 'w342'); ?>
            <article class="movie-card tmdb-card">
              <a class="movie-art poster" href="<?= e($tUrl) ?>" aria-label="<?= e($t['title']) ?> — details">
                <?php if ($tImg): ?>
                  <img class="tmdb-img" src="<?= e($tImg) ?>" alt="<?= e($t['title']) ?>" loading="lazy" decoding="async" width="342" height="513">
                <?php else: ?>
                  <div class="poster-fallback" aria-hidden="true"><span><?= e($t['title']) ?></span></div>
                <?php endif; ?>
                <span class="movie-badge-category"><?= $t['media_type'] === 'tv' ? 'TV' : 'MOVIE' ?></span>
              </a>
              <div class="movie-body">
                <h3 class="movie-title"><a href="<?= e($tUrl) ?>"><?= e($t['title']) ?></a></h3>
                <div class="movie-meta"><span><?= $t['year'] !== '' ? '📅 ' . e($t['year']) : 'TBA' ?></span></div>
                <div class="movie-footer">
                  <a href="<?= e($tUrl) ?>" class="btn btn-primary btn-sm" style="flex:1; text-align:center;">Details</a>
                  <button type="button" class="btn btn-glass btn-sm tmdb-wl-btn" data-remove-card="1"
                          data-tmdb-id="<?= (int)$t['tmdb_id'] ?>" data-media-type="<?= e($t['media_type']) ?>"
                          data-title="<?= e($t['title']) ?>" data-poster="<?= e((string)$t['poster_path']) ?>" data-year="<?= e($t['year']) ?>">✕ Remove</button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- Watchlist Section -->
    <section>
      <h2 class="section-title">⭐ Saved Watchlist (<?= count($watchlistMovies) ?> titles)</h2>
      <div class="movies-grid">
        <?php foreach ($watchlistMovies as $movie): ?>
          <div class="movie-card" id="wl-card-<?= (int)$movie['id'] ?>">
            <div class="movie-art" style="background: <?= e($movie['bg']) ?>;">
              <span><?= e($movie['icon']) ?></span>
              <span class="movie-badge-category"><?= e($movie['category']) ?></span>
            </div>
            <div class="movie-body">
              <h3 class="movie-title"><?= e($movie['title']) ?></h3>
              <div class="movie-meta">
                <span>📅 <?= (int)$movie['released'] ?></span>
                <span>⏱️ <?= e($movie['runtime']) ?></span>
              </div>
              <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.4;">
                <?= e(mb_strimwidth($movie['about'], 0, 85, '...')) ?>
              </p>
              <div class="movie-footer">
                <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$movie['id'] ?>)">
                  ▶ Watch Stream
                </button>
                <button class="btn btn-glass btn-sm" onclick="toggleWatchlist(<?= (int)$movie['id'] ?>, this); document.getElementById('wl-card-<?= (int)$movie['id'] ?>').remove();">
                  ✕ Remove
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (empty($watchlistMovies) && empty($tmdbSaved)): ?>
        <div style="text-align:center; padding:4rem; color:var(--text-muted);">
          <p style="font-size:1.2rem;">Your watchlist is currently empty.</p>
          <a href="discover.php" class="btn btn-primary" style="margin-top:1rem;">Explore Movies & Series →</a>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <div class="modal-backdrop" id="playerModal">
    <div class="modal-card" style="max-width:850px; width:95%; padding:1.5rem;">
      <button class="modal-close-btn" onclick="closeModals()">✕</button>
      <div id="playerContainer"></div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
