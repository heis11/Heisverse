<?php
/**
 * HEISVERSE — Worldwide Discover
 * Real TMDB data (posters, backdrops, trailers) + working filters, sorting and pagination.
 * The original "HEISVERSE Library" filter view (your own database) is kept under its own tab.
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

// ---------------------------------------------------------------------
// Read + validate the query string
// ---------------------------------------------------------------------
$source = ($_GET['src'] ?? 'tmdb') === 'library' ? 'library' : 'tmdb';

$typeOptions = [
    ''            => 'All (browse)',
    'movie'       => 'Movies',
    'tv'          => 'TV Series',
    'anime'       => 'Anime (series)',
    'anime_movie' => 'Anime (films)',
];
$sortOptions = [
    'popularity.desc' => 'Most popular',
    'rating.desc'     => 'Highest rated',
    'latest'          => 'Latest releases',
    'oldest'          => 'Oldest first',
    'popularity.asc'  => 'Least popular',
];
$ratingOptions = [0 => 'Any rating', 5 => '5+ ★', 6 => '6+ ★', 7 => '7+ ★', 8 => '8+ ★', 9 => '9+ ★'];

$type    = (string)($_GET['type'] ?? '');
if (!isset($typeOptions[$type])) $type = '';
$genre   = max(0, (int)($_GET['genre'] ?? 0));
$year    = (int)($_GET['year'] ?? 0);
if ($year !== 0 && ($year < 1900 || $year > (int)date('Y') + 2)) $year = 0;
$country = strtoupper(trim((string)($_GET['country'] ?? '')));
if (!preg_match('/^[A-Z]{2}$/', $country)) $country = '';
$lang    = strtolower(trim((string)($_GET['language'] ?? '')));
if (!preg_match('/^[a-z]{2,3}$/', $lang)) $lang = '';
$minRate = (int)($_GET['rating'] ?? 0);
if (!isset($ratingOptions[$minRate])) $minRate = 0;
$sort    = (string)($_GET['sort'] ?? 'popularity.desc');
if (!isset($sortOptions[$sort])) $sort = 'popularity.desc';
$page    = max(1, min(500, (int)($_GET['page'] ?? 1)));

$isFiltering = $type !== '' || $genre || $year || $country !== '' || $lang !== '' || $minRate || $sort !== 'popularity.desc' || $page > 1;

$wlKeys = heis_watchlist_keys($db, (int)$currentUser['id']);

// ---------------------------------------------------------------------
// TMDB data (every call is guarded: a failing request never breaks the page)
// ---------------------------------------------------------------------
$tmdbError = null;
$hero = null;
$heroTrailer = null;
$rows = [];
$results = ['items' => [], 'page' => 1, 'total_pages' => 0, 'total_results' => 0];
$genreMap = [];
$genreOptions = [];
$countries = [];
$languages = [];
$empty = ['items' => []];

if ($source === 'tmdb') {
    $safe = function (callable $fn, $default = null) use (&$tmdbError) {
        try {
            return $fn();
        } catch (TmdbException $e) {
            $tmdbError = $tmdbError ?? $e->getMessage();
            return $default;
        }
    };

    $genreType = ($type === 'tv' || $type === 'anime') ? 'tv' : 'movie';
    $movieGenres = $safe(fn() => Tmdb::genres('movie'), []);
    $tvGenres    = $safe(fn() => Tmdb::genres('tv'), []);
    $genreMap = $movieGenres + $tvGenres;
    $genreOptions = $genreType === 'tv' ? $tvGenres : $movieGenres;
    $countries = $safe(fn() => Tmdb::countries(), []);
    $languages = $safe(fn() => Tmdb::languages(), []);

    if ($isFiltering) {
        // ---------------- Filtered results: the filters go straight to TMDB /discover ----------------
        $apiType = in_array($type, ['tv', 'anime'], true) ? 'tv' : 'movie';
        $results = $safe(fn() => Tmdb::discover([
            'type'       => $apiType,
            'anime'      => in_array($type, ['anime', 'anime_movie'], true),
            'genre'      => $genre,
            'year'       => $year,
            'country'    => $country,
            'language'   => $lang,
            'min_rating' => $minRate,
            'sort'       => $sort,
            'page'       => $page,
        ]), $results);
    } else {
        // ---------------- Browse mode: featured hero + rows ----------------
        $trending = $safe(fn() => Tmdb::trendingMovies('week'), $empty);
        foreach ($trending['items'] as $cand) {
            if (!empty($cand['backdrop_path']) && $cand['overview'] !== '') { $hero = $cand; break; }
        }
        if ($hero) {
            $heroTrailer = $safe(fn() => Tmdb::trailerFor('movie', $hero['id']));
        }
        $recs = $hero ? $safe(fn() => Tmdb::movieRecommendations($hero['id']), $empty) : $empty;

        $rows = [
            ['Trending Movies',     $trending['items'],                                                  'discover.php?type=movie&sort=popularity.desc', 'trending'],
            ['Popular Movies',      $safe(fn() => Tmdb::popularMovies(), $empty)['items'],               'discover.php?type=movie', 'popular-movies'],
            ['Popular TV Series',   $safe(fn() => Tmdb::popularTv(), $empty)['items'],                   'discover.php?type=tv', 'popular-tv'],
            ['Anime',               $safe(fn() => Tmdb::discover(['type' => 'tv', 'anime' => true, 'sort' => 'popularity.desc']), $empty)['items'],    'discover.php?type=anime', 'anime'],
            ['Anime Films',         $safe(fn() => Tmdb::discover(['type' => 'movie', 'anime' => true, 'sort' => 'popularity.desc']), $empty)['items'], 'discover.php?type=anime_movie', 'anime-films'],
            ['Recently Released',   $safe(fn() => Tmdb::nowPlayingMovies(), $empty)['items'],            'discover.php?type=movie&sort=latest', 'recent'],
            ['Top Rated Movies',    $safe(fn() => Tmdb::topRatedMovies(), $empty)['items'],              'discover.php?type=movie&sort=rating.desc', 'top-movies'],
            ['Top Rated TV Series', $safe(fn() => Tmdb::topRatedTv(), $empty)['items'],                  'discover.php?type=tv&sort=rating.desc', 'top-tv'],
        ];
        if ($hero) {
            array_splice($rows, 3, 0, [['Recommended: if you like ' . $hero['title'], $recs['items'], null, 'recommended']]);
        }
        foreach ([28 => 'Action', 35 => 'Comedy', 18 => 'Drama'] as $gid => $gname) {
            $g = $safe(fn() => Tmdb::discover(['type' => 'movie', 'genre' => $gid, 'sort' => 'popularity.desc']), $empty);
            $rows[] = [$gname . ' Movies', $g['items'], 'discover.php?type=movie&genre=' . $gid, 'genre-' . $gid];
        }
    }
}

// ---------------------------------------------------------------------
// HEISVERSE Library (your own database), the original Discover behaviour
// ---------------------------------------------------------------------
$libMovies = [];
$libError = '';
$libCountry = $libLang = $libType = '';
$libYear = 0;
$libCountries = ['United States', 'Japan', 'South Korea', 'United Kingdom', 'Rwanda', 'Nigeria', 'France', 'India'];
$libLanguages = ['English', 'Japanese', 'Korean', 'Kinyarwanda', 'French', 'Swahili'];
$libTypes = [
    'movies' => 'Movies', 'tv_series' => 'TV Series', 'anime_series' => 'Anime Series',
    'anime_movies' => 'Anime Movies', 'cartoons' => 'Cartoons', 'kdrama' => 'K-Dramas', 'documentaries' => 'Documentaries',
];
if ($source === 'library' && $db) {
    $libCountry = trim((string)($_GET['country'] ?? ''));
    $libLang = trim((string)($_GET['lang'] ?? ''));
    $libType = trim((string)($_GET['ltype'] ?? ''));
    $libYear = (int)($_GET['lyear'] ?? 0);
    try {
        $sql = 'SELECT * FROM movies WHERE is_published = 1';
        $params = [];
        if ($libCountry !== '') { $sql .= ' AND country = :c'; $params[':c'] = $libCountry; }
        if ($libLang !== '')    { $sql .= ' AND original_language = :l'; $params[':l'] = $libLang; }
        if ($libType !== '')    { $sql .= ' AND content_type = :t'; $params[':t'] = $libType; }
        if ($libYear > 0)       { $sql .= ' AND released = :y'; $params[':y'] = $libYear; }
        $sql .= ' ORDER BY id DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $libMovies = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[HEISVERSE DISCOVER LIBRARY] ' . $e->getMessage());
        $libError = 'Your library needs a database update. Run migrations/004b_ensure_global_columns.sql (see README).';
    }
} elseif ($source === 'library' && !$db) {
    $libError = 'The database is not reachable. Check DB_* values in .env and that MySQL is running in XAMPP.';
}

/** Pagination links that keep every active filter. */
function discover_page_url(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return 'discover.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Discover — HEISVERSE</title>
  <?= heis_meta_tags() ?>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <?php heis_header('discover', $currentUser); ?>

  <main class="container">

    <div class="discover-tabs" role="tablist" aria-label="Discover source">
      <a href="discover.php" class="discover-tab <?= $source === 'tmdb' ? 'active' : '' ?>" role="tab" aria-selected="<?= $source === 'tmdb' ? 'true' : 'false' ?>">🌐 Worldwide (TMDB)</a>
      <a href="discover.php?src=library" class="discover-tab <?= $source === 'library' ? 'active' : '' ?>" role="tab" aria-selected="<?= $source === 'library' ? 'true' : 'false' ?>">🎞️ HEISVERSE Library</a>
    </div>

<?php if ($source === 'tmdb'): ?>

    <?php if ($tmdbError !== null): ?>
      <?= heis_error_panel($tmdbError) ?>
    <?php endif; ?>

    <?php if ($hero): ?>
    <!-- Featured title: real TMDB backdrop -->
    <section class="hero-banner hero-tmdb" aria-label="Featured title">
      <img class="hero-bg tmdb-img"
           src="<?= e((string)Tmdb::image($hero['backdrop_path'], 'backdrop', 'w1280')) ?>"
           srcset="<?= e(Tmdb::backdropSrcset($hero['backdrop_path'])) ?>"
           sizes="100vw" alt="" fetchpriority="high" decoding="async">
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <span class="hero-tag">Featured • Trending this week</span>
        <h1 class="hero-title"><?= e($hero['title']) ?></h1>
        <div class="hero-facts">
          <?php if ($hero['rating'] > 0): ?><span class="rating-pill static">★ <?= e(number_format($hero['rating'], 1)) ?></span><?php endif; ?>
          <?php if ($hero['date'] !== ''): ?><span>📅 <?= e(date('M j, Y', (int)strtotime($hero['date']))) ?></span><?php endif; ?>
          <?php $hg = Tmdb::genreLabel($hero, $genreMap); if ($hg !== ''): ?><span><?= e($hg) ?></span><?php endif; ?>
        </div>
        <p class="hero-desc"><?= e(mb_strimwidth($hero['overview'], 0, 320, '…')) ?></p>
        <div class="hero-buttons">
          <?php if ($heroTrailer): ?>
            <button type="button" class="btn btn-primary" data-trailer="<?= e($heroTrailer['key']) ?>" data-trailer-title="<?= e($hero['title']) ?>">▶ Watch Trailer</button>
          <?php endif; ?>
          <a class="btn btn-glass" href="<?= e(Tmdb::url($hero)) ?>">View Details</a>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Filters: every control is sent to TMDB /discover -->
    <form method="GET" action="discover.php" class="filter-panel" id="discoverFilters">
      <div>
        <label for="f-type">Type</label>
        <select name="type" id="f-type" class="form-control">
          <?php foreach ($typeOptions as $k => $l): ?><option value="<?= e((string)$k) ?>" <?= $type === (string)$k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-genre">Genre</label>
        <select name="genre" id="f-genre" class="form-control">
          <option value="0">All genres</option>
          <?php foreach ($genreOptions as $gid => $gn): ?><option value="<?= (int)$gid ?>" <?= $genre === (int)$gid ? 'selected' : '' ?>><?= e($gn) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-year">Release year</label>
        <input type="number" name="year" id="f-year" min="1900" max="<?= (int)date('Y') + 2 ?>" value="<?= $year ?: '' ?>" placeholder="e.g. 2024" class="form-control">
      </div>
      <div>
        <label for="f-country">Country</label>
        <select name="country" id="f-country" class="form-control">
          <option value="">Any country</option>
          <?php foreach ($countries as $code => $name): ?><option value="<?= e((string)$code) ?>" <?= $country === (string)$code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-lang">Original language</label>
        <select name="language" id="f-lang" class="form-control">
          <option value="">Any language</option>
          <?php foreach ($languages as $code => $name): ?><option value="<?= e((string)$code) ?>" <?= $lang === (string)$code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-rating">Minimum rating</label>
        <select name="rating" id="f-rating" class="form-control">
          <?php foreach ($ratingOptions as $v => $l): ?><option value="<?= (int)$v ?>" <?= $minRate === (int)$v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-sort">Sort by</label>
        <select name="sort" id="f-sort" class="form-control">
          <?php foreach ($sortOptions as $k => $l): ?><option value="<?= e((string)$k) ?>" <?= $sort === (string)$k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($isFiltering): ?><a href="discover.php" class="btn btn-glass">Reset</a><?php endif; ?>
      </div>
      <p class="filter-note" id="animeNote" hidden>Anime = Animation genre + Japanese original language. Language is fixed for anime, and series are limited to Japan.</p>
    </form>

    <?php if ($isFiltering): ?>
      <section aria-live="polite">
        <div class="section-header">
          <h2 class="section-title">Results</h2>
          <?php if ($results['total_results'] > 0): ?>
            <span class="results-count"><?= number_format($results['total_results']) ?> titles • page <?= (int)$results['page'] ?> of <?= (int)$results['total_pages'] ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($results['items'])): ?>
          <div class="movies-grid">
            <?php foreach ($results['items'] as $it) echo heis_card($it, $genreMap, $wlKeys); ?>
          </div>
          <?php if ($results['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Pagination">
              <?php $cur = $results['page']; $last = $results['total_pages']; ?>
              <?php if ($cur > 1): ?><a class="btn btn-glass btn-sm" href="<?= e(discover_page_url($cur - 1)) ?>" rel="prev">← Previous</a><?php endif; ?>
              <?php for ($i = max(1, $cur - 2); $i <= min($last, $cur + 2); $i++): ?>
                <a class="btn <?= $i === $cur ? 'btn-primary' : 'btn-glass' ?> btn-sm" href="<?= e(discover_page_url($i)) ?>" <?= $i === $cur ? 'aria-current="page"' : '' ?>><?= $i ?></a>
              <?php endfor; ?>
              <?php if ($cur < $last): ?><a class="btn btn-glass btn-sm" href="<?= e(discover_page_url($cur + 1)) ?>" rel="next">Next →</a><?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php elseif ($tmdbError === null): ?>
          <div class="empty-state">
            <p class="empty-title">No titles match these filters</p>
            <p>Try a wider year, a lower minimum rating, or reset the filters.</p>
            <a href="discover.php" class="btn btn-glass" style="margin-top:1rem;">Reset filters</a>
          </div>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <?php foreach ($rows as [$rowTitle, $rowItems, $rowMore, $rowId]) echo heis_row($rowTitle, $rowItems, $genreMap, $wlKeys, $rowMore, $rowId); ?>
    <?php endif; ?>

<?php else: ?>

    <!-- HEISVERSE Library tab (original local-database discovery) -->
    <div style="margin-bottom: 1.5rem;">
      <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.4rem;">🎞️ HEISVERSE Library</h1>
      <p style="color: var(--text-muted);">Titles stored in your own HEISVERSE database, filtered by territory, language, type and year.</p>
    </div>
    <form method="GET" action="discover.php" class="filter-panel">
      <input type="hidden" name="src" value="library">
      <div>
        <label for="l-country">Country of origin</label>
        <select name="country" id="l-country" class="form-control">
          <option value="">All Countries</option>
          <?php foreach ($libCountries as $c): ?><option value="<?= e($c) ?>" <?= $libCountry === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="l-lang">Audio language</label>
        <select name="lang" id="l-lang" class="form-control">
          <option value="">All Languages</option>
          <?php foreach ($libLanguages as $l): ?><option value="<?= e($l) ?>" <?= $libLang === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="l-type">Content format</label>
        <select name="ltype" id="l-type" class="form-control">
          <option value="">All Content Types</option>
          <?php foreach ($libTypes as $k => $l): ?><option value="<?= e($k) ?>" <?= $libType === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="l-year">Release year</label>
        <input type="number" name="lyear" id="l-year" value="<?= $libYear > 0 ? $libYear : '' ?>" placeholder="e.g. 2024" class="form-control">
      </div>
      <div class="filter-actions"><button type="submit" class="btn btn-primary">Apply Filter</button></div>
    </form>

    <?php if ($libError !== ''): ?>
      <div class="empty-state error" role="alert"><p><?= e($libError) ?></p></div>
    <?php endif; ?>

    <div class="movies-grid">
      <?php foreach ($libMovies as $movie): ?>
        <div class="movie-card">
          <div class="movie-art" style="background: <?= e($movie['bg']) ?>;">
            <?php if (!empty($movie['poster_url'])): ?>
              <img class="tmdb-img" src="<?= e($movie['poster_url']) ?>" alt="<?= e($movie['title']) ?> poster" loading="lazy" decoding="async">
            <?php else: ?>
              <span><?= e($movie['icon']) ?></span>
            <?php endif; ?>
            <span class="movie-badge-category"><?= e($movie['country'] ?? '') ?></span>
          </div>
          <div class="movie-body">
            <h3 class="movie-title"><?= e($movie['title']) ?></h3>
            <div class="movie-meta">
              <span>🗣️ <?= e($movie['original_language'] ?? '') ?></span>
              <span>📅 <?= (int)$movie['released'] ?></span>
            </div>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.4;">
              <?= e(mb_strimwidth((string)$movie['about'], 0, 85, '...')) ?>
            </p>
            <div class="movie-footer">
              <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$movie['id'] ?>)">▶ Watch Stream</button>
              <button class="btn btn-glass btn-sm" onclick="toggleWatchlist(<?= (int)$movie['id'] ?>, this)" aria-label="Toggle watchlist">＋</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (empty($libMovies) && $libError === ''): ?>
      <div class="empty-state">
        <p class="empty-title">No matching titles in your library</p>
        <a href="discover.php?src=library" class="btn btn-glass" style="margin-top:1rem;">Reset Filter</a>
      </div>
    <?php endif; ?>

<?php endif; ?>
  </main>

  <div class="modal-backdrop" id="playerModal">
    <div class="modal-card" style="max-width:850px; width:95%; padding:1.5rem;">
      <button class="modal-close-btn" onclick="closeModals()" aria-label="Close">✕</button>
      <div id="playerContainer"></div>
    </div>
  </div>
  <?= heis_trailer_modal() ?>

  <script src="assets/js/main.js"></script>
</body>
</html>
