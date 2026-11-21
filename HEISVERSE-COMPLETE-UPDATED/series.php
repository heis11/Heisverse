<?php
/**
 * HEISVERSE — TV Series & Seasons Hub
 * Complete episodic browser with seasons, episode lists, and continue watching.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();
$db = Database::getConnection();

$seriesList = [];
$featuredSeries = null;

if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM movies WHERE content_type IN ('tv_series', 'kdrama', 'mini_series') AND is_published = 1 ORDER BY is_featured DESC, id DESC");
        $seriesList = $stmt->fetchAll();
        $featuredSeries = $seriesList[0] ?? null;
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>TV Series & Shows — HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .episode-card {
      background: var(--bg-glass);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-sm);
      padding: 1rem;
      margin-bottom: 0.8rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.2s;
    }
    .episode-card:hover {
      border-color: var(--accent);
      background: var(--bg-glass-hover);
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
      <li><a href="series.php" class="active">Series</a></li>
      <li><a href="anime.php">Anime</a></li>
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
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 0.5rem;">📺 Global TV Series & Dramas</h1>
      <p style="color: var(--text-muted);">Explore international television series, acclaimed mini-series, and Korean dramas organized by season.</p>
    </div>

    <!-- Featured Series Banner -->
    <?php if ($featuredSeries): ?>
      <section class="hero-banner" style="background: <?= e($featuredSeries['bg']) ?>;">
        <div class="hero-overlay"></div>
        <div class="hero-content">
          <span class="hero-tag">🔥 FEATURED SERIES • <?= e($featuredSeries['country']) ?></span>
          <h2 class="hero-title"><?= e($featuredSeries['icon']) ?> <?= e($featuredSeries['title']) ?></h2>
          <p class="hero-desc"><?= e($featuredSeries['about']) ?></p>
          <div class="hero-buttons">
            <button class="btn btn-primary" onclick="openSeriesEpisodes(<?= (int)$featuredSeries['id'] ?>, '<?= e(addslashes($featuredSeries['title'])) ?>')">
              ▶ Browse Seasons & Episodes
            </button>
            <button class="btn btn-glass" onclick="toggleWatchlist(<?= (int)$featuredSeries['id'] ?>, this)">
              ＋ Watchlist
            </button>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <!-- Series Grid -->
    <h2 class="section-title">✨ Popular Series & K-Dramas</h2>
    <div class="movies-grid">
      <?php foreach ($seriesList as $series): ?>
        <div class="movie-card">
          <div class="movie-art" style="background: <?= e($series['bg']) ?>;">
            <span><?= e($series['icon']) ?></span>
            <span class="movie-badge-category"><?= strtoupper(str_replace('_', ' ', $series['content_type'])) ?></span>
          </div>
          <div class="movie-body">
            <h3 class="movie-title"><?= e($series['title']) ?></h3>
            <div class="movie-meta">
              <span>🌍 <?= e($series['country']) ?></span>
              <span>📅 <?= (int)$series['released'] ?></span>
            </div>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.4;">
              <?= e(mb_strimwidth($series['about'], 0, 85, '...')) ?>
            </p>
            <div class="movie-footer">
              <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openSeriesEpisodes(<?= (int)$series['id'] ?>, '<?= e(addslashes($series['title'])) ?>')">
                Episodes
              </button>
              <button class="btn btn-glass btn-sm" onclick="toggleWatchlist(<?= (int)$series['id'] ?>, this)">
                ＋
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Episodes Modal -->
  <div class="modal-backdrop" id="episodesModal">
    <div class="modal-card" style="max-width:700px;">
      <button class="modal-close-btn" onclick="closeModals()">✕</button>
      <h3 id="seriesModalTitle" style="font-size:1.4rem; font-weight:800; margin-bottom:0.5rem;"></h3>
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;">Select an episode to begin authorized streaming.</p>

      <div id="episodesContainer">
        <!-- Injected via AJAX -->
      </div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    function openSeriesEpisodes(movieId, title) {
      document.getElementById('seriesModalTitle').innerText = title;
      const container = document.getElementById('episodesContainer');
      container.innerHTML = '<p style="text-align:center; padding:2rem; color:var(--text-muted);">Loading seasons and episodes...</p>';
      document.getElementById('episodesModal').classList.add('active');

      fetch(`api.php?action=get_episodes&movie_id=${movieId}`)
        .then(r => r.json())
        .then(d => {
          if (d.success && d.episodes && d.episodes.length > 0) {
            container.innerHTML = d.episodes.map(ep => `
              <div class="episode-card">
                <div>
                  <div style="font-weight:700; font-size:0.95rem;">Ep ${ep.episode_number}: ${ep.title}</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Runtime: ${ep.runtime} • Quality: ${ep.quality}</div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openPlayerModal(${movieId})">
                  ▶ Play
                </button>
              </div>
            `).join('');
          } else {
            container.innerHTML = `
              <p style="padding:1.5rem; text-align:center; color:var(--text-muted);">
                Season 1 is currently in preparation. Check back soon for episodic streaming.
              </p>
              <button class="btn btn-primary" style="width:100%;" onclick="openPlayerModal(${movieId})">
                ▶ Watch Official Series Trailer
              </button>
            `;
          }
        })
        .catch(() => {
          container.innerHTML = '<p>Error loading episodes.</p>';
        });
    }
  </script>
</body>
</html>
