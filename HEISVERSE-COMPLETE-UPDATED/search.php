<?php
/**
 * HEISVERSE — Global Search page
 * Searches real TMDB results (movies, TV, anime). Logic lives in main.js (initSearchPage) + api.php?action=tmdb_search.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';
require_once __DIR__ . '/includes/tmdb.php';
require_once __DIR__ . '/includes/ui.php';

Auth::requireLogin();
$currentUser = Auth::user();
$db = Database::getConnection();

$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'movie', 'tv', 'anime'], true)) $filter = 'all';

$wl = array_keys(heis_watchlist_keys($db, (int)$currentUser['id']));
$filters = ['all' => 'All', 'movie' => 'Movies', 'tv' => 'TV', 'anime' => 'Anime'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $q !== '' ? e($q) . ' — Search — HEISVERSE' : 'Search — HEISVERSE' ?></title>
  <?= heis_meta_tags() ?>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <?php heis_header('', $currentUser); ?>

  <main class="container">
    <div style="margin-bottom:1.5rem;">
      <h1 style="font-size:2rem; font-weight:800; margin-bottom:.4rem;">🔍 Search</h1>
      <p style="color:var(--text-muted);">Find movies, TV series and anime from around the world.</p>
    </div>

    <form id="searchPageForm" class="search-page-form" role="search" action="search.php" method="GET">
      <input type="search" id="searchPageInput" name="q" class="form-control" value="<?= e($q) ?>"
             placeholder="Search by title, original title or partial name…" autocomplete="off" aria-label="Search titles">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>

    <div class="chip-row filter-chips" id="searchFilters" role="group" aria-label="Filter results">
      <?php foreach ($filters as $k => $l): ?>
        <button type="button" class="chip chip-btn <?= $filter === $k ? 'active' : '' ?>" data-filter="<?= e($k) ?>" aria-pressed="<?= $filter === $k ? 'true' : 'false' ?>"><?= e($l) ?></button>
      <?php endforeach; ?>
    </div>

    <p class="results-count" id="searchStatus" role="status" aria-live="polite"></p>
    <div class="movies-grid" id="searchResults" data-wl='<?= e((string)json_encode($wl)) ?>'></div>
    <div id="searchMessage"></div>
    <div style="text-align:center; margin-bottom:3rem;">
      <button type="button" class="btn btn-glass" id="searchLoadMore" hidden>Load more</button>
    </div>
  </main>

  <script>window.HEIS_SEARCH = { q: <?= json_encode($q, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, filter: <?= json_encode($filter) ?> };</script>
  <script src="assets/js/main.js"></script>
</body>
</html>
