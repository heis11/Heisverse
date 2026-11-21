<?php
/**
 * HEISVERSE â€” Category & Genre Navigation
 * Browse all 16+ cinematic genres with sorting, filtering, and live counts.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();
$db = Database::getConnection();

$allGenres = [
    'Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary',
    'Drama', 'Family', 'Fantasy', 'Horror', 'Mystery', 'Romance',
    'Science Fiction', 'Thriller', 'War', 'Western'
];

$selectedGenre = trim($_GET['genre'] ?? '');
$sortBy = trim($_GET['sort'] ?? 'newest');
$search = trim($_GET['q'] ?? '');

$movies = [];
$counts = [];

if ($db) {
    try {
        // Fetch genre counts
        $countQuery = $db->query("SELECT category, COUNT(*) as cnt FROM movies WHERE is_published = 1 GROUP BY category");
        while ($r = $countQuery->fetch()) {
            $counts[$r['category']] = (int)$r['cnt'];
        }

        // Build dynamic filter query
        $sql = "SELECT * FROM movies WHERE is_published = 1";
        $params = [];

        if (!empty($selectedGenre)) {
            $sql .= " AND category = :cat";
            $params[':cat'] = $selectedGenre;
        }

        if (!empty($search)) {
            $sql .= " AND (title LIKE :s OR about LIKE :s2)";
            $params[':s'] = "%{$search}%";
            $params[':s2'] = "%{$search}%";
        }

        if ($sortBy === 'title') {
            $sql .= " ORDER BY title ASC";
        } elseif ($sortBy === 'year') {
            $sql .= " ORDER BY released DESC";
        } elseif ($sortBy === 'rating') {
            $sql .= " ORDER BY rating_avg DESC";
        } else {
            $sql .= " ORDER BY id DESC";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $movies = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>Genres & Categories â€” HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .genre-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      margin-bottom: 2rem;
    }
    .genre-chip {
      background: var(--bg-glass);
      border: 1px solid var(--border-glass);
      padding: 0.5rem 1rem;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.2s;
    }
    .genre-chip:hover, .genre-chip.active {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
      transform: translateY(-2px);
    }
    .filter-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
      background: var(--bg-glass);
      border: 1px solid var(--border-glass);
      padding: 1rem 1.5rem;
      border-radius: var(--border-radius-md);
      margin-bottom: 2rem;
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
      <li><a href="categories.php" class="active">Categories</a></li>
      <li><a href="discover.php">Discover</a></li>
      <li><a href="watchlist.php">Watchlist</a></li>
    </ul>
    <div class="search-wrapper" role="search">
      <div class="search-input-box">
        <span aria-hidden="true" style="font-size:0.9rem; color:var(--text-dim); margin-right:0.4rem;">ðŸ”</span>
        <input type="search" id="globalSearchInput" class="search-input" placeholder="Search movies, TV, animeâ€¦" autocomplete="off" aria-label="Search titles" aria-controls="searchSuggestionsDropdown">
      </div>
      <div class="search-dropdown" id="searchSuggestionsDropdown" role="listbox"></div>
    </div>
    <div class="nav-actions">
      <span style="font-size:0.85rem; color:var(--text-muted);">ðŸ‘¤ <?= e($currentUser['username']) ?></span>
      <a href="logout.php" class="btn btn-glass btn-sm">Logout</a>
    </div>
  </header>

  <main class="container">
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 0.5rem;">ðŸŽ­ Categories & Genres</h1>
      <p style="color: var(--text-muted);">Browse all 16 cinematic genres, sort by release year or rating, and discover films.</p>
    </div>

    <!-- Genre Chips List -->
    <div class="genre-chips">
      <a href="categories.php" class="genre-chip <?= empty($selectedGenre) ? 'active' : '' ?>">
        All Categories (<?= count($movies) ?>)
      </a>
      <?php foreach ($allGenres as $g): ?>
        <?php $count = $counts[$g] ?? 0; ?>
        <a href="categories.php?genre=<?= urlencode($g) ?>&sort=<?= urlencode($sortBy) ?>" class="genre-chip <?= $selectedGenre === $g ? 'active' : '' ?>">
          <?= e($g) ?> (<?= $count ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Filter & Sort Bar -->
    <div class="filter-bar">
      <div>
        <span style="font-size:0.9rem; font-weight:700;">
          Showing: <?= empty($selectedGenre) ? 'All Content' : e($selectedGenre) ?> (<?= count($movies) ?> titles)
        </span>
      </div>

      <form method="GET" action="categories.php" style="display:flex; gap:0.8rem; align-items:center;">
        <?php if (!empty($selectedGenre)): ?>
          <input type="hidden" name="genre" value="<?= e($selectedGenre) ?>">
        <?php endif; ?>

        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search in genre..." class="search-input" style="min-width:180px;">

        <select name="sort" class="btn btn-glass btn-sm" onchange="this.form.submit()">
          <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Recently Added</option>
          <option value="year" <?= $sortBy === 'year' ? 'selected' : '' ?>>Release Year</option>
          <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title (A-Z)</option>
          <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Rating</option>
        </select>
      </form>
    </div>

    <!-- Movie Cards Grid -->
    <div class="movies-grid">
      <?php foreach ($movies as $movie): ?>
        <div class="movie-card">
          <div class="movie-art category-poster" style="background: <?= e($movie['bg'] ?? '#151b2b') ?>;">
            <?php if (!empty($movie['poster_url'])): ?>
              <img
                class="tmdb-img"
                src="<?= e($movie['poster_url']) ?>"
                alt="<?= e($movie['title']) ?> poster"
                loading="lazy"
                decoding="async">
            <?php else: ?>
              <div class="category-poster-fallback">
                <span><?= e($movie['icon'] ?? '🎬') ?></span>
                <small>Poster unavailable</small>
              </div>
            <?php endif; ?>
            <span class="movie-badge-category"><?= e($movie['category']) ?></span>
          </div>
          <div class="movie-body">
            <h3 class="movie-title"><?= e($movie['title']) ?></h3>
            <div class="movie-meta">
              <span>ðŸ“… <?= (int)$movie['released'] ?></span>
              <span>â±ï¸ <?= e($movie['runtime']) ?></span>
            </div>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.4;">
              <?= e(mb_strimwidth($movie['about'], 0, 85, '...')) ?>
            </p>
            <div class="movie-footer">
              <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$movie['id'] ?>)">
                â–¶ Watch Stream
              </button>
              <button class="btn btn-glass btn-sm" onclick="toggleWatchlist(<?= (int)$movie['id'] ?>, this)">
                ï¼‹
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($movies)): ?>
      <div style="text-align:center; padding:4rem; color:var(--text-muted);">
        <p style="font-size:1.2rem;">ðŸŽ¬ No titles found in this category yet.</p>
        <a href="categories.php" class="btn btn-primary" style="margin-top:1rem;">Clear Filters</a>
      </div>
    <?php endif; ?>
  </main>

  <div class="modal-backdrop" id="playerModal">
    <div class="modal-card" style="max-width:850px; width:95%; padding:1.5rem;">
      <button class="modal-close-btn" onclick="closeModals()">âœ•</button>
      <div id="playerContainer"></div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>

