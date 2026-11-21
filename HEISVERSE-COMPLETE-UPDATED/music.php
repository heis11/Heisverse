<?php
/**
 * HEISVERSE — YouTube & Music Experience
 * Dedicated music video discovery, categorized playlists, and authorized YouTube embeds.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();

// Curated Initial Music Videos (Official Embeds)
$curatedMusic = [
    [
        'id' => 'Andy_BUMUNTU_-_IGITEGO',
        'yt_id' => 'kJQP7kiw5Fk',
        'title' => 'Igitego (Official Video)',
        'artist' => 'Andy Bumuntu',
        'category' => 'Rwandan Music',
        'thumbnail' => 'https://img.youtube.com/vi/kJQP7kiw5Fk/hqdefault.jpg'
    ],
    [
        'id' => 'Yverry_Amabanga',
        'yt_id' => 'ScMzIvxBSi4',
        'title' => 'Amabanga (Official Music Video)',
        'artist' => 'Yverry',
        'category' => 'Rwandan Music',
        'thumbnail' => 'https://img.youtube.com/vi/ScMzIvxBSi4/hqdefault.jpg'
    ],
    [
        'id' => 'Burna_Boy_City_Boys',
        'yt_id' => 'd9MyW72ELq0',
        'title' => 'City Boys (Live Performance)',
        'artist' => 'Burna Boy',
        'category' => 'African Music',
        'thumbnail' => 'https://img.youtube.com/vi/d9MyW72ELq0/hqdefault.jpg'
    ],
    [
        'id' => 'The_Weeknd_Blinding_Lights',
        'yt_id' => 'fJ9rUzIMcZQ',
        'title' => 'Blinding Lights (Official Music Video)',
        'artist' => 'The Weeknd',
        'category' => 'International Music',
        'thumbnail' => 'https://img.youtube.com/vi/fJ9rUzIMcZQ/hqdefault.jpg'
    ]
];
?>
<!DOCTYPE html>
<html lang="<?= e(Lang::current()) ?>" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>Music & Video Discovery — <?= e(__('app_name')) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .music-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1.5rem;
      margin-top: 1.5rem;
    }
    .music-card {
      background: var(--bg-glass);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-md);
      overflow: hidden;
      cursor: pointer;
      transition: all var(--transition-speed);
    }
    .music-card:hover {
      transform: translateY(-5px);
      border-color: var(--accent);
      box-shadow: 0 10px 25px var(--accent-glow);
    }
    .music-thumb {
      width: 100%;
      height: 160px;
      object-fit: cover;
      background: #000;
    }
    .music-info {
      padding: 1rem;
    }
    .music-title {
      font-weight: 700;
      font-size: 0.95rem;
      margin-bottom: 0.2rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .music-artist {
      font-size: 0.8rem;
      color: var(--text-muted);
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <header class="navbar">
    <div class="nav-brand">
      <span>🎵</span>
      <span><?= e(__('app_name')) ?> Music</span>
    </div>
    <ul class="nav-links">
      <li><a href="index.php">← Back to Movies</a></li>
      <li><a href="music.php" class="active">Discover Music</a></li>
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
    </div>
  </header>

  <main class="container">
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem;">🎧 YouTube & Music Lounge</h1>
      <p style="color: var(--text-muted);">Stream authorized music videos, Rwandan hits, African rhythms, and international charts.</p>
    </div>

    <!-- Active Music Player Container -->
    <div id="musicPlayerWrapper" style="display:none; margin-bottom: 2.5rem;">
      <div class="heisverse-player-wrapper">
        <div class="video-container responsive-embed">
          <iframe id="musicIframe" src="" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
        </div>
        <div class="player-controls-bar">
          <span id="playingTitle" style="font-weight:700;"></span>
          <button class="btn btn-glass btn-sm" onclick="closeMusicPlayer()">Close Player</button>
        </div>
      </div>
    </div>

    <!-- Music Categories -->
    <h3 class="section-title">🔥 Featured & Trending Music</h3>
    <div class="music-grid">
      <?php foreach ($curatedMusic as $track): ?>
        <div class="music-card" onclick="playMusic('<?= e($track['yt_id']) ?>', '<?= e(addslashes($track['title'] . ' — ' . $track['artist'])) ?>')">
          <img src="<?= e($track['thumbnail']) ?>" alt="<?= e($track['title']) ?>" class="music-thumb" onerror="this.src='https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500&q=80'">
          <div class="music-info">
            <span style="font-size:0.7rem; background:rgba(255,255,255,0.1); padding:0.2rem 0.5rem; border-radius:4px; color:var(--accent);">
              <?= e($track['category']) ?>
            </span>
            <h4 class="music-title" style="margin-top:0.4rem;"><?= e($track['title']) ?></h4>
            <p class="music-artist">🎤 <?= e($track['artist']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <script src="assets/js/main.js"></script>
  <script>
    function playMusic(ytId, title) {
      const wrapper = document.getElementById('musicPlayerWrapper');
      const iframe = document.getElementById('musicIframe');
      const titleEl = document.getElementById('playingTitle');

      iframe.src = 'https://www.youtube.com/embed/' + ytId + '?autoplay=1';
      titleEl.innerText = 'Now Playing: ' + title;
      wrapper.style.display = 'block';
      wrapper.scrollIntoView({ behavior: 'smooth' });
    }

    function closeMusicPlayer() {
      const wrapper = document.getElementById('musicPlayerWrapper');
      const iframe = document.getElementById('musicIframe');
      iframe.src = '';
      wrapper.style.display = 'none';
    }
  </script>
</body>
</html>
