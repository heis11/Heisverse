<?php
/**
 * HEISVERSE — Shared UI helpers
 * Used by Discover, Title details and Search so they match the rest of the site.
 * They reuse the existing classes (navbar, movie-card, movie-art, hero-banner, section-title ...).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/tmdb.php';
require_once __DIR__ . '/auth.php';

/** <meta> tags every page needs (CSRF token for JS requests + theme colour). */
function heis_meta_tags(): string {
    return '<meta name="csrf-token" content="' . e(csrf_token()) . '">' . "\n  "
         . '<meta name="theme-color" content="#07090e">' . "\n  "
         . '<link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">' . "\n  "
         . '<link rel="manifest" href="manifest.json">';
}

/** Same navigation as index.php, plus the shared global search box. */
function heis_header(string $active, ?array $user): void {
    $links = [
        'home'       => ['index.php', 'Home', '⌂'],
        'movies'     => ['index.php#catalog', 'Movies', '🎬'],
        'series'     => ['series.php', 'Series', '📺'],
        'anime'      => ['anime.php', 'Anime', '🎌'],
        'cartoons'   => ['cartoons.php', 'Cartoons', '🎨'],
        'categories' => ['categories.php', 'Categories', '📂'],
        'discover'   => ['discover.php', 'Discover', '🔎'],
        'watchlist'  => ['watchlist.php', 'Watchlist', '♥'],
        'profile'    => ['profile.php', 'My Profile', '👤'],
        'mypc'       => ['my-pc.php', 'My PC', '💻'],
        'contact'    => ['contact.php', 'Contact', '✉'],
    ];
    ?>
    <header class="navbar heis-topbar">
      <a href="index.php" class="nav-brand">
        <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:34px;">
      </a>
      <?php heis_search_box(); ?>
      <div class="nav-actions">
        <span>👤 <?= e($user['username'] ?? '') ?></span>
        <a href="profile.php" class="btn btn-glass btn-sm">Profile</a>
        <a href="logout.php" class="btn btn-glass btn-sm">Logout</a>
      </div>
    </header>

    <aside class="sidebar" id="siteSidebar">
      <div class="sidebar-heading">MAIN MENU</div>
      <nav aria-label="Main sidebar navigation">
        <ul class="sidebar-links">
          <?php foreach ($links as $key => [$href, $label, $icon]): ?>
            <li>
              <a href="<?= e($href) ?>" class="<?= $key === $active ? 'active' : '' ?>">
                <span class="side-icon"><?= $icon ?></span>
                <span class="side-label"><?= e($label) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
          <?php if (Auth::isAdmin()): ?>
            <li>
              <a href="admin/index.php">
                <span class="side-icon">⚙</span>
                <span class="side-label">Admin</span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    </aside>
    <?php
}
/** The global search widget. main.js (initLiveSearch) wires it up on every page that has it. */
function heis_search_box(): void { ?>
    <div class="search-wrapper" role="search">
      <div class="search-input-box">
        <span aria-hidden="true" style="font-size:0.9rem; color:var(--text-dim); margin-right:0.4rem;">🔍</span>
        <input type="search" id="globalSearchInput" class="search-input" placeholder="Search movies, TV, anime…" autocomplete="off" aria-label="Search titles" aria-controls="searchSuggestionsDropdown">
      </div>
      <div class="search-dropdown" id="searchSuggestionsDropdown" role="listbox"></div>
    </div>
<?php }

/** Poster markup with graceful fallback when TMDB has no image. */
function heis_poster(array $item, string $alt = ''): string {
    $url = Tmdb::image($item['poster_path'] ?? null, 'poster', 'w342');
    if ($url === null) {
        return '<div class="poster-fallback" aria-hidden="true"><span>' . e($item['title'] ?? '') . '</span></div>';
    }
    return '<img class="tmdb-img" src="' . e($url) . '" srcset="' . e(Tmdb::posterSrcset($item['poster_path'])) . '"'
         . ' sizes="(max-width:600px) 45vw, 230px" alt="' . e($alt !== '' ? $alt : ($item['title'] ?? 'Poster')) . '"'
         . ' loading="lazy" decoding="async" width="342" height="513">';
}

/**
 * One TMDB title card.
 * @param array<string,mixed> $item normalised item
 * @param array<int,string> $genreMap
 * @param array<string,bool> $wl keys like "movie:123"
 */
function heis_card(array $item, array $genreMap, array $wl): string {
    $key = $item['media_type'] . ':' . $item['id'];
    $inList = isset($wl[$key]);
    $label = $item['is_anime'] ? 'ANIME' : ($item['media_type'] === 'tv' ? 'TV' : 'MOVIE');
    $genre = Tmdb::genreLabel($item, $genreMap);
    $url = Tmdb::url($item);

    ob_start(); ?>
<article class="movie-card tmdb-card">
  <a class="movie-art poster" href="<?= e($url) ?>" aria-label="<?= e($item['title']) ?> — details">
    <?= heis_poster($item) ?>
    <span class="movie-badge-category"><?= e($label) ?></span>
    <?php if ($item['rating'] > 0): ?><span class="rating-pill" title="TMDB rating">★ <?= e(number_format($item['rating'], 1)) ?></span><?php endif; ?>
  </a>
  <div class="movie-body">
    <h3 class="movie-title"><a href="<?= e($url) ?>"><?= e($item['title']) ?></a></h3>
    <div class="movie-meta">
      <span><?= $item['year'] !== '' ? '📅 ' . e($item['year']) : 'TBA' ?></span>
      <?php if ($genre !== ''): ?><span><?= e($genre) ?></span><?php endif; ?>
    </div>
    <div class="movie-footer">
      <a href="<?= e($url) ?>" class="btn btn-primary btn-sm" style="flex:1; text-align:center;">Details</a>
      <button type="button" class="btn <?= $inList ? 'btn-primary' : 'btn-glass' ?> btn-sm tmdb-wl-btn"
              data-tmdb-id="<?= (int)$item['id'] ?>" data-media-type="<?= e($item['media_type']) ?>"
              data-title="<?= e($item['title']) ?>" data-poster="<?= e((string)($item['poster_path'] ?? '')) ?>"
              data-year="<?= e($item['year']) ?>" aria-pressed="<?= $inList ? 'true' : 'false' ?>"
              aria-label="<?= $inList ? 'Remove from watchlist' : 'Add to watchlist' ?>"><?= $inList ? '✓' : '＋' ?></button>
    </div>
  </div>
</article>
<?php
    return (string)ob_get_clean();
}

/** A horizontally scrolling row of cards with a section title. */
function heis_row(string $title, array $items, array $genreMap, array $wl, ?string $moreUrl = null, string $id = ''): string {
    if (!$items) return '';
    ob_start(); ?>
<section class="discover-row"<?= $id ? ' id="' . e($id) . '"' : '' ?>>
  <div class="section-header">
    <h2 class="section-title"><?= e($title) ?></h2>
    <?php if ($moreUrl): ?><a href="<?= e($moreUrl) ?>" class="btn btn-glass btn-sm">See all →</a><?php endif; ?>
  </div>
  <div class="card-row" tabindex="0" aria-label="<?= e($title) ?>">
    <?php foreach (array_slice($items, 0, 20) as $it) echo heis_card($it, $genreMap, $wl); ?>
  </div>
</section>
<?php
    return (string)ob_get_clean();
}

/** Skeleton cards shown while client-side requests are loading. */
function heis_skeletons(int $n = 8): string {
    return str_repeat('<div class="movie-card skeleton-card" aria-hidden="true"><div class="skeleton skeleton-poster"></div><div class="movie-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div></div></div>', $n);
}

/** The user's TMDB watchlist keys ("movie:123") for button state. */
function heis_watchlist_keys(?PDO $db, int $userId): array {
    $keys = [];
    if (!$db) return $keys;
    try {
        $st = $db->prepare('SELECT tmdb_id, media_type FROM tmdb_watchlist WHERE user_id = :u');
        $st->execute([':u' => $userId]);
        foreach ($st->fetchAll() as $r) $keys[$r['media_type'] . ':' . $r['tmdb_id']] = true;
    } catch (PDOException $e) {
        // Migration 004 not run yet: buttons simply start un-selected.
    }
    return $keys;
}

/** Friendly error panel (used when TMDB is down or the key is wrong). */
function heis_error_panel(string $message): string {
    return '<div class="empty-state error" role="alert"><p class="empty-title">We couldn’t load this from TMDB</p><p>'
         . e($message) . '</p><a href="" class="btn btn-glass" style="margin-top:1rem;">Try again</a></div>';
}

/** Modal used by every page for official trailers (YouTube, taken from TMDB's own video list). */
function heis_trailer_modal(): string {
    return '<div class="modal-backdrop" id="trailerModal" role="dialog" aria-modal="true" aria-label="Trailer">'
         . '<div class="modal-card" style="max-width:900px; width:95%; padding:1.2rem;">'
         . '<button class="modal-close-btn" type="button" onclick="closeModals()" aria-label="Close trailer">✕</button>'
         . '<div id="trailerContainer"></div></div></div>';
}
