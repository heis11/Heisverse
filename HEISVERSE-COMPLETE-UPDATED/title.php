<?php
/**
 * HEISVERSE — Title details (movie or TV/anime) powered by TMDB.
 * URL: title.php?type=movie|tv&id=123
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

$type = ($_GET['type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
$id = (int)($_GET['id'] ?? 0);

$t = null;
$error = null;
$notFound = false;
if ($id <= 0) {
    $notFound = true;
} else {
    try {
        $t = Tmdb::details($type, $id);
    } catch (TmdbException $e) {
        if ($e->httpStatus === 404) $notFound = true; else $error = $e->getMessage();
    }
}

$genreMap = [];
$wlKeys = heis_watchlist_keys($db, (int)$currentUser['id']);
$inList = isset($wlKeys[$type . ':' . $id]);

// Is this title also in the HEISVERSE library (with an authorized source)?
$localId = null;
if ($t && $db) {
    try {
        $st = $db->prepare('SELECT m.id FROM movies m WHERE m.tmdb_id = :t AND m.is_published = 1
                            AND EXISTS (SELECT 1 FROM movie_sources s WHERE s.movie_id = m.id AND s.is_active = 1) LIMIT 1');
        $st->execute([':t' => $id]);
        $localId = $st->fetchColumn() ?: null;
    } catch (PDOException $e) { /* library table optional */ }
}

function fmt_runtime(?int $m): string {
    if (!$m || $m <= 0) return '';
    return $m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . 'm';
}
$pageTitle = $t ? $t['title'] . ' — HEISVERSE' : 'Title — HEISVERSE';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <?= heis_meta_tags() ?>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <?php heis_header($type === 'tv' ? 'series' : 'movies', $currentUser); ?>

  <main class="container">
<?php if ($notFound): ?>
    <div class="empty-state"><p class="empty-title">We couldn’t find that title</p>
      <a href="discover.php" class="btn btn-glass" style="margin-top:1rem;">Back to Discover</a></div>
<?php elseif ($error !== null || !$t): ?>
    <?= heis_error_panel($error ?? 'Unknown error.') ?>
<?php else:
    $genreMap = Tmdb::genres('movie') + Tmdb::genres('tv');
    $label = $t['is_anime'] ? 'Anime' : ($type === 'tv' ? 'TV Series' : 'Movie');
?>
    <section class="hero-banner hero-tmdb hero-detail" aria-label="<?= e($t['title']) ?>">
      <?php if ($t['backdrop_path']): ?>
        <img class="hero-bg tmdb-img" src="<?= e((string)Tmdb::image($t['backdrop_path'], 'backdrop', 'w1280')) ?>"
             srcset="<?= e(Tmdb::backdropSrcset($t['backdrop_path'])) ?>" sizes="100vw" alt="" fetchpriority="high" decoding="async">
      <?php else: ?>
        <div class="hero-bg hero-bg-fallback"></div>
      <?php endif; ?>
      <div class="hero-overlay"></div>
      <div class="detail-layout">
        <div class="detail-poster"><?= heis_poster($t) ?></div>
        <div class="hero-content">
          <span class="hero-tag"><?= e(strtoupper($label)) ?></span>
          <h1 class="hero-title"><?= e($t['title']) ?></h1>
          <?php if ($t['tagline'] !== ''): ?><p class="detail-tagline">“<?= e($t['tagline']) ?>”</p><?php endif; ?>
          <div class="hero-facts">
            <?php if ($t['rating'] > 0): ?><span class="rating-pill static" title="<?= (int)$t['votes'] ?> votes">★ <?= e(number_format($t['rating'], 1)) ?></span><?php endif; ?>
            <?php if ($t['date'] !== ''): ?><span>📅 <?= e(date('M j, Y', (int)strtotime($t['date']))) ?></span><?php endif; ?>
            <?php if (fmt_runtime($t['runtime']) !== ''): ?><span>⏱️ <?= e(fmt_runtime($t['runtime'])) ?><?= $type === 'tv' ? ' / episode' : '' ?></span><?php endif; ?>
            <?php if ($type === 'tv' && $t['seasons']): ?><span>📺 <?= (int)$t['seasons'] ?> season<?= $t['seasons'] > 1 ? 's' : '' ?> • <?= (int)$t['episodes'] ?> episodes</span><?php endif; ?>
            <?php if ($t['status'] !== ''): ?><span><?= e($t['status']) ?></span><?php endif; ?>
          </div>
          <?php if ($t['genres']): ?>
            <div class="chip-row"><?php foreach ($t['genres'] as $g): ?><span class="chip"><?= e($g) ?></span><?php endforeach; ?></div>
          <?php endif; ?>
          <p class="hero-desc"><?= $t['overview'] !== '' ? e($t['overview']) : 'No overview is available for this title yet.' ?></p>
          <div class="hero-buttons">
            <?php if ($t['trailer']): ?>
              <button type="button" class="btn btn-primary" data-trailer="<?= e($t['trailer']['key']) ?>" data-trailer-title="<?= e($t['title']) ?>">▶ Watch Trailer</button>
            <?php endif; ?>
            <?php if ($localId): ?>
              <button type="button" class="btn btn-primary" onclick="openPlayerModal(<?= (int)$localId ?>)">▶ Watch (HEISVERSE library)</button>
            <?php endif; ?>
            <button type="button" class="btn <?= $inList ? 'btn-primary' : 'btn-glass' ?> tmdb-wl-btn tmdb-wl-full"
                    data-tmdb-id="<?= $id ?>" data-media-type="<?= e($type) ?>" data-title="<?= e($t['title']) ?>"
                    data-poster="<?= e((string)($t['poster_path'] ?? '')) ?>" data-year="<?= e($t['year']) ?>"
                    aria-pressed="<?= $inList ? 'true' : 'false' ?>"><?= $inList ? '✓ In Watchlist' : '＋ Add to Watchlist' ?></button>
            <button type="button" class="btn btn-glass" data-share data-share-title="<?= e($t['title']) ?>">🔗 Share</button>
          </div>
          <?php if (!$localId): ?>
            <p class="source-note">No authorized full-length stream is linked to this title in HEISVERSE. Official trailers are provided by TMDB.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="detail-facts">
      <?php if ($t['directors']): ?><div><span>Director / Creator</span><strong><?= e(implode(', ', array_slice($t['directors'], 0, 3))) ?></strong></div><?php endif; ?>
      <?php if ($t['countries']): ?><div><span>Country</span><strong><?= e(implode(', ', $t['countries'])) ?></strong></div><?php endif; ?>
      <?php if ($t['language'] !== ''): ?><div><span>Original language</span><strong><?= e(strtoupper($t['language'])) ?></strong></div><?php endif; ?>
      <?php if ($t['homepage'] !== '' && preg_match('#^https?://#i', $t['homepage'])): ?><div><span>Official site</span><strong><a href="<?= e($t['homepage']) ?>" target="_blank" rel="noopener noreferrer">Visit ↗</a></strong></div><?php endif; ?>
    </section>

    <?php if ($t['cast']): ?>
    <section class="discover-row">
      <div class="section-header"><h2 class="section-title">Cast</h2></div>
      <div class="card-row cast-row" tabindex="0" aria-label="Cast">
        <?php foreach ($t['cast'] as $c): $pic = Tmdb::image($c['profile_path'], 'profile', 'w185'); ?>
          <div class="cast-card">
            <?php if ($pic): ?><img class="tmdb-img" src="<?= e($pic) ?>" alt="<?= e($c['name']) ?>" loading="lazy" decoding="async" width="185" height="278">
            <?php else: ?><div class="poster-fallback" aria-hidden="true"><span><?= e($c['name']) ?></span></div><?php endif; ?>
            <strong><?= e($c['name']) ?></strong>
            <small><?= e($c['character']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?= heis_row('Recommendations', $t['recommendations'], $genreMap, $wlKeys) ?>
    <?= heis_row('Similar Titles', $t['similar'], $genreMap, $wlKeys) ?>
<?php endif; ?>
  </main>

  <div class="modal-backdrop" id="playerModal">
    <div class="modal-card" style="max-width:860px; width:95%; padding:1.5rem;">
      <button class="modal-close-btn" onclick="closeModals()" aria-label="Close">✕</button>
      <div id="playerContainer"></div>
    </div>
  </div>
  <?= heis_trailer_modal() ?>
  <script src="assets/js/main.js"></script>
</body>
</html>
