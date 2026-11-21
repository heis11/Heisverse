<?php
/**
 * HEISVERSE — Anime Universe
 * Dedicated portal for anime series, anime films, and Japanese animation.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();
$db = Database::getConnection();

$animeList = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM movies WHERE content_type IN ('anime_movies', 'anime_series') AND is_published = 1 ORDER BY is_featured DESC, id DESC");
        $animeList = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>Anime Universe — HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
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
      <li><a href="anime.php" class="active">Anime</a></li>
      <li><a href="cartoons.php">Cartoons</a></li>
      <li><a href="categories.php">Categories</a></li>
      <li><a href="discover.php">Discover</a></li>
      <li><a href="watchlist.php">Watchlist</a></li>
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
      <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 0.5rem;">⛩️ Anime Universe</h1>
      <p style="color: var(--text-muted);">Top-tier anime series, feature films, and legendary studios (Ghibli, Ufotable, MAPPA, Wit Studio).</p>
    </div>

    <!-- Anime Grid -->
    <div class="movies-grid">
      <?php foreach ($animeList as $anime): ?>
        <div class="movie-card">
          <div class="movie-art" style="background: <?= e($anime['bg']) ?>;">
            <span><?= e($anime['icon']) ?></span>
            <span class="movie-badge-category">🇯🇵 <?= strtoupper(str_replace('_', ' ', $anime['content_type'])) ?></span>
          </div>
          <div class="movie-body">
            <h3 class="movie-title"><?= e($anime['title']) ?></h3>
            <div class="movie-meta">
              <span>📅 <?= (int)$anime['released'] ?></span>
              <span>🎙️ Japanese / Sub</span>
            </div>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.4;">
              <?= e(mb_strimwidth($anime['about'], 0, 85, '...')) ?>
            </p>
            <div class="movie-footer">
              <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$anime['id'] ?>)">
                ▶ Watch Stream
              </button>
              <button class="btn btn-glass btn-sm" onclick="toggleWatchlist(<?= (int)$anime['id'] ?>, this)">
                ＋
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Player Modal -->
  <div class="modal-backdrop" id="playerModal">
    <div class="modal-card" style="max-width:850px; width:95%; padding:1.5rem;">
      <button class="modal-close-btn" onclick="closeModals()">✕</button>
      <div id="playerContainer"></div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
