<?php
/**
 * HEISVERSE &mdash; Flagship Cinematic Homepage
 * Global entertainment platform featuring Movies, Series, Anime, Cartoons, 
 * Expandable Voice Search, 300 Themes, and AI Assistant.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/movie-sources.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();

if (isset($_GET['lang'])) {
    Lang::setLanguage($_GET['lang']);
    header('Location: index.php');
    exit;
}

$db = Database::getConnection();
$featuredMovie = null;
$trendingMovies = [];
$seriesList = [];
$animeList = [];
$cartoonsList = [];
$userWatchlist = [];
$settings = [];

if ($db) {
    try {
        // Load settings
        $sQuery = $db->query('SELECT setting_key, setting_value FROM system_settings');
        while ($row = $sQuery->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Featured & Trending Movies
        $mStmt = $db->query("SELECT * FROM movies WHERE content_type = 'movies' AND is_published = 1 ORDER BY is_featured DESC, id ASC");
        $trendingMovies = $mStmt->fetchAll();
        $featuredMovie = $trendingMovies[0] ?? null;

        // Series & K-Dramas
        $sStmt = $db->query("SELECT * FROM movies WHERE content_type IN ('tv_series', 'kdrama') AND is_published = 1 ORDER BY id DESC LIMIT 6");
        $seriesList = $sStmt->fetchAll();

        // Anime
        $aStmt = $db->query("SELECT * FROM movies WHERE content_type IN ('anime_movies', 'anime_series') AND is_published = 1 ORDER BY id DESC LIMIT 6");
        $animeList = $aStmt->fetchAll();

        // Cartoons
        $cStmt = $db->query("SELECT * FROM movies WHERE content_type IN ('cartoons', 'animated_movies') AND is_published = 1 ORDER BY id DESC LIMIT 6");
        $cartoonsList = $cStmt->fetchAll();

        // Watchlist IDs
        $wlStmt = $db->prepare('SELECT movie_id FROM watchlists WHERE user_id = :u');
        $wlStmt->execute([':u' => $currentUser['id']]);
        $userWatchlist = $wlStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="<?= e(Lang::current()) ?>" data-theme="<?= e($currentUser['theme_pref'] ?? 'cine-01') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e(__('app_name')) ?> &mdash; <?= e(__('tagline')) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="assets/css/style.css?v=heisverse-nav-20261120">
  <meta name="theme-color" content="#07090e">
</head>
<body>

    <!-- HEISVERSE Horizontal Top Navigation -->
  <header class="navbar">
    <a href="index.php" class="nav-brand">
      <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:32px;">
    </a>

    

    <div class="search-wrapper">
      <div class="search-input-box">
        <span aria-hidden="true">🔍</span>
        <input type="search" id="globalSearchInput"
               class="search-input"
               placeholder="<?= e(__('search_placeholder')) ?>"
               autocomplete="off">
        <button type="button" class="voice-mic-btn"
                onclick="VoiceDictation.toggle('globalSearchInput', this)"
                title="Voice Search">🎙️</button>
      </div>
      <div class="search-dropdown" id="searchSuggestionsDropdown"></div>
    </div>

    <div class="nav-actions">
      <button class="btn btn-glass btn-sm"
              onclick="openThemeGallery()" title="300 Themes Gallery">
        🎨 Themes
      </button>

      <select onchange="window.location.href='?lang='+this.value"
              class="btn btn-glass btn-sm" aria-label="Select Language">
        <?php foreach (Lang::getSupported() as $code => $info): ?>
          <option value="<?= e($code) ?>" <?= Lang::current() === $code ? 'selected' : '' ?>>
            <?= $info['flag'] ?> <?= $info['name'] ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="nav-user">
        <span>👤 <?= e($currentUser['username']) ?></span>
        <a href="profile.php" class="btn btn-glass btn-sm">Profile</a>
        <a href="logout.php" class="btn btn-glass btn-sm" style="color:#f87171;">Logout</a>
      </div>
    </div>
  </header>
  <!-- HEISVERSE LEFT SIDEBAR -->
  <aside class="sidebar" id="siteSidebar">
    <div class="sidebar-heading">MAIN MENU</div>
    <nav aria-label="Sidebar navigation">
      <ul class="sidebar-links">
        <li><a href="index.php"><span class="side-icon">⌂</span><span class="side-label">Home</span></a></li>
        <li><a href="index.php#catalog"><span class="side-icon">🎬</span><span class="side-label">Catalog</span></a></li>
        <li><a href="series.php"><span class="side-icon">📺</span><span class="side-label">Series</span></a></li>
        <li><a href="anime.php"><span class="side-icon">🎌</span><span class="side-label">Anime</span></a></li>
        <li><a href="cartoons.php"><span class="side-icon">🎨</span><span class="side-label">Cartoons</span></a></li>
        <li><a href="categories.php"><span class="side-icon">📂</span><span class="side-label">Categories</span></a></li>
        <li><a href="discover.php"><span class="side-icon">🔎</span><span class="side-label">Discover</span></a></li>
      </ul>

      <div class="sidebar-heading">YOUR LIBRARY</div>
      <ul class="sidebar-links">
        <li><a href="watchlist.php"><span class="side-icon">♥</span><span class="side-label">Watchlist</span></a></li>
        <li><a href="my-pc.php"><span class="side-icon">💻</span><span class="side-label">My PC</span></a></li>
        <li><a href="contact.php"><span class="side-icon">✉</span><span class="side-label">Contact</span></a></li>
        <?php if (Auth::isAdmin()): ?>
        <li><a href="admin/index.php"><span class="side-icon">⚙</span><span class="side-label">Admin</span></a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </aside>
<!-- Main Container -->
  <main class="container">

    <!-- Featured Hero Banner -->
    <?php if ($featuredMovie): ?>
      <section class="hero-banner hero-tmdb" style="background: <?= e($featuredMovie['bg']) ?>;">
        <?php if (!empty($featuredMovie['backdrop_url'])): ?><img class="hero-bg tmdb-img" src="<?= e($featuredMovie['backdrop_url']) ?>" alt="" fetchpriority="high" decoding="async"><?php endif; ?>
        <div class="hero-overlay"></div>
        <div class="hero-content">
          <span class="hero-tag"><?= e(__('featured_movie')) ?> &bull; <?= e($featuredMovie['category']) ?></span>
          <h1 class="hero-title"><?= e($featuredMovie['icon']) ?> <?= e($featuredMovie['title']) ?></h1>
          <p class="hero-desc" id="heroSynopsisText"><?= e($featuredMovie['about']) ?></p>
          <div class="hero-buttons">
            <button class="btn btn-primary" onclick="openPlayerModal(<?= (int)$featuredMovie['id'] ?>)">
              <?= e(__('watch_now')) ?>
            </button>
            <button class="btn btn-glass" onclick="toggleWatchlist(<?= (int)$featuredMovie['id'] ?>, this)">
              <?= in_array($featuredMovie['id'], $userWatchlist) ? '&#10003; In Watchlist' : '+ Add to Watchlist' ?>
            </button>
            <button class="btn btn-glass" onclick="TextToSpeech.speak(document.getElementById('heroSynopsisText').innerText, document.getElementById('heroSynopsisText'))" title="Listen to Synopsis">
              &#128269;Š Listen
            </button>
            <button class="btn btn-glass" onclick="openAiAdvisor(<?= (int)$featuredMovie['id'] ?>, '<?= e(addslashes($featuredMovie['title'])) ?>')">
              &#129302; AI Insights
            </button>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <!-- Section 1: Trending & Popular Movies -->
    <section id="catalog" style="margin-bottom:3.5rem;">
      <div class="section-header">
        <h2 class="section-title">Trending Movies Trending Movies</h2>
        <a href="categories.php" class="btn btn-glass btn-sm">View All Movies &#8594;</a>
      </div>
      <div class="card-row home-card-row" tabindex="0">
        <?php foreach ($trendingMovies as $m): ?>
          <div class="movie-card">
            <div class="movie-art" style="background: <?= e($m['bg']) ?>;">
              <?php if (!empty($m['poster_url'])): ?><img class="tmdb-img" src="<?= e($m['poster_url']) ?>" alt="<?= e($m['title']) ?> poster" loading="lazy" decoding="async"><?php else: ?><span><?= e($m['icon']) ?></span><?php endif; ?>
              <span class="movie-badge-category"><?= e($m['category']) ?></span>
            </div>
            <div class="movie-body">
              <h3 class="movie-title"><?= e($m['title']) ?></h3>
              <div class="movie-meta">
                <span>&#128197; <?= (int)$m['released'] ?></span>
                <span><?= e($m['runtime']) ?></span>
              </div>
              <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.4;">
                <?= e(mb_strimwidth($m['about'], 0, 85, '...')) ?>
              </p>
              <div class="movie-footer">
                <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$m['id'] ?>)">
                  &#9654; Watch
                </button>
                <button class="btn <?= in_array($m['id'], $userWatchlist) ? 'btn-primary' : 'btn-glass' ?> btn-sm" onclick="toggleWatchlist(<?= (int)$m['id'] ?>, this)">
                  <?= in_array($m['id'], $userWatchlist) ? '&#10003;' : '+' ?>
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Section 2: Global TV Series & K-Dramas -->
    <?php if (!empty($seriesList)): ?>
      <section style="margin-bottom:3.5rem;">
        <div class="section-header">
          <h2 class="section-title">&#128250; Global TV Series & Dramas</h2>
          <a href="series.php" class="btn btn-glass btn-sm">Explore Series &#8594;</a>
        </div>
        <div class="card-row home-card-row" tabindex="0">
          <?php foreach ($seriesList as $s): ?>
            <div class="movie-card">
              <div class="movie-art" style="background: <?= e($s['bg']) ?>;">
              <?php if (!empty($s['poster_url'])): ?><img class="tmdb-img" src="<?= e($s['poster_url']) ?>" alt="<?= e($s['title']) ?> poster" loading="lazy" decoding="async"><?php else: ?><span><?= e($s['icon']) ?></span><?php endif; ?>
                <span class="movie-badge-category"><?= strtoupper(str_replace('_', ' ', $s['content_type'])) ?></span>
              </div>
              <div class="movie-body">
                <h3 class="movie-title"><?= e($s['title']) ?></h3>
                <div class="movie-meta">
                  <span>&#128205; <?= e($s['country']) ?></span>
                  <span>&#128197; <?= (int)$s['released'] ?></span>
                </div>
                <div class="movie-footer">
                  <a href="series.php" class="btn btn-primary btn-sm" style="flex:1; text-align:center;">
                    View Seasons & Episodes
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- Section 3: Anime Universe -->
    <?php if (!empty($animeList)): ?>
      <section style="margin-bottom:3.5rem;">
        <div class="section-header">
          <h2 class="section-title">Anime Universe Anime Universe</h2>
          <a href="anime.php" class="btn btn-glass btn-sm">More Anime &#8594;</a>
        </div>
        <div class="card-row home-card-row" tabindex="0">
          <?php foreach ($animeList as $a): ?>
            <div class="movie-card">
              <div class="movie-art" style="background: <?= e($a['bg']) ?>;">
              <?php if (!empty($a['poster_url'])): ?><img class="tmdb-img" src="<?= e($a['poster_url']) ?>" alt="<?= e($a['title']) ?> poster" loading="lazy" decoding="async"><?php else: ?><span><?= e($a['icon']) ?></span><?php endif; ?>
                <span class="movie-badge-category">&#127471;&#127477; ANIME</span>
              </div>
              <div class="movie-body">
                <h3 class="movie-title"><?= e($a['title']) ?></h3>
                <div class="movie-meta">
                  <span>&#128172; Japanese</span>
                  <span>&#128197; <?= (int)$a['released'] ?></span>
                </div>
                <div class="movie-footer">
                  <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$a['id'] ?>)">
                    &#9654; Watch Anime
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- Section 4: Cartoons & Animation -->
    <?php if (!empty($cartoonsList)): ?>
      <section style="margin-bottom:3.5rem;">
        <div class="section-header">
          <h2 class="section-title">&#127912; Cartoons & Animation</h2>
          <a href="cartoons.php" class="btn btn-glass btn-sm">More Cartoons &#8594;</a>
        </div>
        <div class="card-row home-card-row" tabindex="0">
          <?php foreach ($cartoonsList as $c): ?>
            <div class="movie-card">
              <div class="movie-art" style="background: <?= e($c['bg']) ?>;">
              <?php if (!empty($c['poster_url'])): ?><img class="tmdb-img" src="<?= e($c['poster_url']) ?>" alt="<?= e($c['title']) ?> poster" loading="lazy" decoding="async"><?php else: ?><span><?= e($c['icon']) ?></span><?php endif; ?>
                <span class="movie-badge-category">CARTOON</span>
              </div>
              <div class="movie-body">
                <h3 class="movie-title"><?= e($c['title']) ?></h3>
                <div class="movie-meta">
                  <span><?= e($c['runtime']) ?></span>
                  <span>&#128197; <?= (int)$c['released'] ?></span>
                </div>
                <div class="movie-footer">
                  <button class="btn btn-primary btn-sm" style="flex:1;" onclick="openPlayerModal(<?= (int)$c['id'] ?>)">
                    &#9654; Watch
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </main>

  <!-- Video Player Modal -->
  <div class="modal-backdrop" id="playerModal">
    <div class="modal-card" style="max-width:860px; width:95%; padding:1.5rem;">
      <button class="modal-close-btn" onclick="closeModals()">&#10005;</button>
      <div id="playerContainer"></div>
    </div>
  </div>

  <!-- 300 Themes Gallery Modal -->
  <div class="modal-backdrop" id="themeGalleryModal">
    <div class="modal-card" style="max-width:960px; width:95%; max-height:90vh;">
      <button class="modal-close-btn" onclick="closeModals()">&#10005;</button>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; flex-wrap:wrap; gap:0.5rem;">
        <div>
          <h2 style="font-size:1.6rem; font-weight:800;">&#127912; Exactly 300 Themes Gallery</h2>
          <p style="color:var(--text-muted); font-size:0.85rem;">20 Categories with 15 unique palettes each. Click any theme to apply it instantly.</p>
        </div>
        <span class="btn btn-glass btn-sm" id="themeCountBadge" style="font-weight:700;">300 Themes</span>
      </div>

      <!-- Controls -->
      <div style="display:flex; gap:1rem; margin-bottom:1.2rem; flex-wrap:wrap;">
        <input type="text" id="themeSearchInput" class="form-control" placeholder="Search by theme name or keyword..." style="flex:1; min-width:200px;">
        <select id="themeCategorySelect" class="form-control" style="width:auto; min-width:180px;">
          <option value="All">All 20 Categories</option>
          <option value="Cinematic">Cinematic</option>
          <option value="Dark Premium">Dark Premium</option>
          <option value="Galaxy">Galaxy</option>
          <option value="Space">Space</option>
          <option value="Nature">Nature</option>
          <option value="Sky and Clouds">Sky and Clouds</option>
          <option value="Ocean">Ocean</option>
          <option value="Neon">Neon</option>
          <option value="Cyberpunk">Cyberpunk</option>
          <option value="Luxury">Luxury</option>
          <option value="Minimal">Minimal</option>
          <option value="Modern">Modern</option>
          <option value="Light">Light</option>
          <option value="Purple">Purple</option>
          <option value="Blue">Blue</option>
          <option value="Red">Red</option>
          <option value="Green">Green</option>
          <option value="Sunset">Sunset</option>
          <option value="Aurora">Aurora</option>
          <option value="Retro">Retro</option>
        </select>
        <div style="display:flex; align-items:center; gap:0.4rem;">
          <label style="font-size:0.8rem; color:var(--text-muted);">Accent:</label>
          <input type="color" id="themeCustomAccentInput" value="#3b82f6" style="border:none; width:34px; height:34px; border-radius:6px; cursor:pointer;">
        </div>
      </div>

      <!-- Theme Cards Container -->
      <div class="theme-gallery-grid" id="themeCardsContainer"></div>
    </div>
  </div>

  <!-- Floating AI Movie Assistant Button -->
  <button class="ai-fab-btn" onclick="openAiAdvisor(0, 'HEISVERSE Catalog')" title="AI Movie Assistant">
    <span>&#129302;</span>
    <span>AI Movie Assistant</span>
  </button>

  <!-- Redesigned AI Movie Assistant Modal -->
  <div class="modal-backdrop" id="aiModal">
    <div class="modal-card" style="max-width:580px;">
      <button class="modal-close-btn" onclick="closeModals()">&#10005;</button>
      <div style="display:flex; align-items:center; gap:0.8rem; margin-bottom:1rem;">
        <span style="font-size:2rem; background:linear-gradient(135deg,var(--accent),#8b5cf6); padding:0.4rem; border-radius:12px;">&#129302;</span>
        <div>
          <h3 style="font-size:1.3rem; font-weight:800;">HEISVERSE AI Movie Assistant</h3>
          <p style="font-size:0.8rem; color:var(--text-muted);">Spoiler-free recommendations, character lore, and cinematic insights.</p>
        </div>
      </div>

      <div id="aiChatOutput" style="background:var(--bg-primary); padding:1.2rem; border-radius:var(--border-radius-sm); border:1px solid var(--border-glass); min-height:160px; max-height:300px; overflow-y:auto; margin-bottom:1rem; font-size:0.9rem; line-height:1.6;">
        <p style="color:var(--text-dim);">Ask anything about films, anime, and series, or use the microphone to speak!</p>
      </div>

      <!-- Quick Suggestion Chips -->
      <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:1rem;">
        <button class="btn btn-glass btn-sm" onclick="setAiQuery('Recommend top rated sci-fi movies')">&#11088; Top Sci-Fi</button>
        <button class="btn btn-glass btn-sm" onclick="setAiQuery('Recommend best anime series')">Best Anime Best Anime</button>
        <button class="btn btn-glass btn-sm" onclick="setAiQuery('Give a spoiler-free plot summary')">&#128220; Spoiler-Free Plot</button>
      </div>

      <!-- Input Bar with Voice Dictation & Text-to-Speech -->
      <div style="display:flex; gap:0.5rem; align-items:center;">
        <input type="text" id="aiUserInput" class="form-control" placeholder="Ask AI Assistant..." style="flex:1;" onkeydown="if(event.key==='Enter')submitAiQuery()">
        <button type="button" class="voice-mic-btn" onclick="VoiceDictation.toggle('aiUserInput', this)" title="Speak your question">
          &#127908;
        </button>
        <button class="btn btn-primary" onclick="submitAiQuery()">Send</button>
      </div>
    </div>
  </div>

  <!-- Dynamic Footer with Verified Contact Info -->
  <footer style="background:var(--bg-secondary); border-top:1px solid var(--border-glass); padding:3rem 1.8rem; margin-top:5rem; color:var(--text-muted); font-size:0.88rem;">
    <div style="max-width:1400px; margin:0 auto; display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:2.5rem; margin-bottom:2.5rem;">
      <div>
        <img src="assets/images/logo.svg" alt="HEISVERSE" style="height:36px; margin-bottom:0.8rem;">
        <p style="line-height:1.6; font-size:0.85rem;">
          <?= e($settings['business_description'] ?? 'HEISVERSE is a premier worldwide cinematic universe and media platform.') ?>
        </p>
      </div>

      <div>
        <h4 style="color:var(--text-main); font-weight:700; margin-bottom:0.8rem;">Quick Navigation</h4>
        <ul style="list-style:none; line-height:2;">
          <li><a href="index.php">Home Catalog</a></li>
          <li><a href="series.php">TV Series & Shows</a></li>
          <li><a href="anime.php">Anime Universe</a></li>
          <li><a href="cartoons.php">Cartoons & Animation</a></li>
          <li><a href="categories.php">All 16 Categories</a></li>
        </ul>
      </div>

      <div>
        <h4 style="color:var(--text-main); font-weight:700; margin-bottom:0.8rem;">Features & Tools</h4>
        <ul style="list-style:none; line-height:2;">
          <li><a href="discover.php">Worldwide Discovery</a></li>
          <li><a href="watchlist.php">My Saved Watchlist</a></li>
          <li><a href="my-pc.php">My PC Local Player</a></li>
          <li><a href="javascript:void(0)" onclick="openThemeGallery()">300 Themes Gallery</a></li>
          <li><a href="contact.php">Contact & Support</a></li>
        </ul>
      </div>

      <div>
        <h4 style="color:var(--text-main); font-weight:700; margin-bottom:0.8rem;">Official Contact</h4>
        <p>&#9993; <a href="mailto:<?= e($settings['contact_email'] ?? 'contact@heisverse.com') ?>" style="color:var(--accent);"><?= e($settings['contact_email'] ?? 'contact@heisverse.com') ?></a></p>
        <p>&#128222; <?= e($settings['phone_number'] ?? '+250 788 123 456') ?></p>
        <p>&#128205; <?= e($settings['physical_address'] ?? 'Kigali, Rwanda') ?></p>
      </div>
    </div>

    <div style="border-top:1px solid var(--border-glass); padding-top:1.5rem; text-align:center;">
      <p>&copy; 2026 HEISVERSE. Authorized Media Platform. All rights reserved.</p>
    </div>
  </footer>

  <script src="assets/vendor/hls.min.js?v=1.7.4"></script>
  <script src="assets/vendor/dash.all.min.js?v=5.3.0"></script>
  <script src="assets/js/main.js?v=heisverse-nav-20261120"></script>
  <script src="assets/js/theme-engine-300.js"></script>
  <script src="assets/js/voice-dictation.js"></script>
  <script src="assets/js/text-to-speech.js"></script>
  <script>
    let activeAiMovieId = 0;

    function appendAiMessage(role, message, readAloud = false) {
      const chat = document.getElementById('aiChatOutput');
      const row = document.createElement('div');
      row.style.display = 'flex';
      row.style.justifyContent = role === 'user' ? 'flex-end' : 'flex-start';
      row.style.margin = '0.7rem 0';

      const bubble = document.createElement('div');
      bubble.style.maxWidth = '88%';
      bubble.style.padding = '0.85rem 1rem';
      bubble.style.borderRadius = '14px';
      bubble.style.background = role === 'user'
        ? 'rgba(139,92,246,0.18)'
        : 'rgba(255,255,255,0.055)';
      bubble.style.border = role === 'user'
        ? '1px solid rgba(139,92,246,0.35)'
        : '1px solid var(--border-glass)';
      bubble.style.color = 'var(--text-main)';
      bubble.style.overflowWrap = 'anywhere';

      const label = document.createElement('div');
      label.style.fontWeight = '800';
      label.style.fontSize = '0.78rem';
      label.style.marginBottom = '0.35rem';
      label.style.color = role === 'user' ? 'var(--accent)' : 'var(--text-muted)';
      label.textContent = role === 'user' ? 'You' : '🤖 HEISVERSE';

      const body = document.createElement('div');
      body.style.whiteSpace = 'pre-wrap';
      body.style.lineHeight = '1.6';
      body.textContent = String(message ?? '');

      bubble.appendChild(label);
      bubble.appendChild(body);

      if (readAloud && role === 'assistant') {
        const speakButton = document.createElement('button');
        speakButton.type = 'button';
        speakButton.className = 'btn btn-glass btn-sm';
        speakButton.style.marginTop = '0.65rem';
        speakButton.textContent = '🔊 Read Aloud';
        speakButton.addEventListener('click', function () {
          if (window.TextToSpeech && typeof TextToSpeech.speak === 'function') {
            TextToSpeech.speak(String(message ?? ''));
          } else {
            speakButton.textContent = 'Voice playback unavailable';
          }
        });
        bubble.appendChild(speakButton);
      }

      row.appendChild(bubble);
      chat.appendChild(row);
      chat.scrollTop = chat.scrollHeight;
      return row;
    }

    function openAiAdvisor(movieId, title) {
      activeAiMovieId = Number(movieId) || 0;

      const modal = document.getElementById('aiModal');
      const chat = document.getElementById('aiChatOutput');

      chat.replaceChildren();
      appendAiMessage(
        'assistant',
        'Welcome to HEISVERSE! Ask me to find movies, explore genres, or learn about a title. You can type your question or use the microphone.'
      );

      if (title && activeAiMovieId > 0) {
        appendAiMessage('assistant', 'You opened the assistant for: ' + title);
      }

      modal.classList.add('active');
      document.getElementById('aiUserInput').focus();
    }

    function setAiQuery(text) {
      const input = document.getElementById('aiUserInput');
      input.value = text;
      submitAiQuery();
    }

    async function submitAiQuery() {
      const input = document.getElementById('aiUserInput');
      const sendButton = document.querySelector('#aiModal .btn-primary');
      const text = input.value.trim();

      if (!text || input.disabled) return;

      appendAiMessage('user', text);
      input.value = '';
      input.disabled = true;

      if (sendButton) sendButton.disabled = true;

      const loading = appendAiMessage('assistant', 'Thinking...');

      try {
        const response = await fetch('api.php?action=ai_assistant', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            prompt: text,
            movie_id: activeAiMovieId
          })
        });

        const data = await response.json();

        if (!response.ok || !data || data.success !== true) {
          throw new Error(data?.message || 'The assistant could not answer that question.');
        }

        const reply = String(data.reply || 'I could not prepare a response.');
        loading.remove();
        appendAiMessage('assistant', reply, true);

      } catch (error) {
        loading.remove();
        appendAiMessage(
          'assistant',
          error.message || 'Could not reach the HEISVERSE assistant. Please try again.'
        );
      } finally {
        input.disabled = false;
        if (sendButton) sendButton.disabled = false;
        input.focus();
      }
    }
  </script>
</body>
</html>

