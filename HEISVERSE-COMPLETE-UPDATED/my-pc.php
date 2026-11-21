<?php
/**
 * HEISVERSE — My PC (Local Video Library Player)
 * Plays user-selected local media client-side with zero cloud upload for privacy and instant speed.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/languages.php';

Auth::requireLogin();
$currentUser = Auth::user();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme_pref'] ?? 'dark-cinematic') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>My PC Local Player — HEISVERSE</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .drop-zone {
      border: 2px dashed var(--border-glass);
      border-radius: var(--border-radius-lg);
      background: var(--bg-glass);
      padding: 3.5rem 2rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 2.5rem;
    }
    .drop-zone:hover, .drop-zone.dragover {
      border-color: var(--accent);
      background: var(--bg-glass-hover);
      box-shadow: 0 0 30px var(--accent-glow);
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
      <li><a href="categories.php">Categories</a></li>
      <li><a href="my-pc.php" class="active">My PC</a></li>
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
      <h1 style="font-size: 2.4rem; font-weight: 800; margin-bottom: 0.5rem;">💻 My PC Local Video Player</h1>
      <p style="color: var(--text-muted); max-width: 680px;">
        Select video files directly from your computer (MP4, WebM, MKV). Videos are streamed directly in your browser using local HTML5 playback with zero file uploads.
      </p>
    </div>

    <!-- Drop Zone -->
    <div class="drop-zone" id="localDropZone" onclick="document.getElementById('localVideoInput').click()">
      <div style="font-size: 3.5rem; margin-bottom: 1rem;">📁</div>
      <h3 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 0.4rem;">Choose a video file from your computer</h3>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
        Click to browse or drag and drop your local movie files here.
      </p>
      <button type="button" class="btn btn-primary" onclick="event.stopPropagation(); document.getElementById('localVideoInput').click();">
        Browse Files
      </button>
      <input type="file" id="localVideoInput" accept="video/mp4,video/webm,video/ogg,video/quicktime" style="display:none;" onchange="handleLocalFile(this.files[0])">
    </div>

    <!-- Local Video Player Container -->
    <div id="localPlayerWrapper" style="display:none;">
      <div class="heisverse-player-wrapper">
        <div class="video-container html5-player">
          <video id="localVideoPlayer" controls autoplay style="width:100%; max-height:75vh; background:#000;">
            Your browser does not support HTML5 video streaming.
          </video>
        </div>
        <div class="player-controls-bar">
          <div>
            <span id="localFileName" style="font-weight:700; font-size:0.95rem;"></span>
            <div id="localFileMeta" style="font-size:0.8rem; color:var(--text-muted);"></div>
          </div>
          <button class="btn btn-glass btn-sm" onclick="closeLocalPlayer()">Close File</button>
        </div>
      </div>
    </div>

    <div style="background:var(--bg-glass); border:1px solid var(--border-glass); border-radius:12px; padding:1.5rem; color:var(--text-muted); font-size:0.85rem; line-height:1.6;">
      <b>🛡️ Privacy & Performance Notice:</b><br>
      Files played through <i>My PC</i> never leave your computer and are never transferred to our server or database. Playback occurs locally via the browser's secure Blob Object URL standard.
    </div>
  </main>

  <script src="assets/js/main.js"></script>
  <script>
    function handleLocalFile(file) {
      if (!file) return;
      const playerWrapper = document.getElementById('localPlayerWrapper');
      const video = document.getElementById('localVideoPlayer');
      const titleEl = document.getElementById('localFileName');
      const metaEl = document.getElementById('localFileMeta');

      const url = URL.createObjectURL(file);
      video.src = url;
      titleEl.innerText = file.name;
      metaEl.innerText = `Size: ${(file.size / (1024*1024)).toFixed(1)} MB • Type: ${file.type || 'video'}`;

      playerWrapper.style.display = 'block';
      playerWrapper.scrollIntoView({ behavior: 'smooth' });
    }

    function closeLocalPlayer() {
      const video = document.getElementById('localVideoPlayer');
      video.pause();
      video.src = '';
      document.getElementById('localPlayerWrapper').style.display = 'none';
    }

    // Drag and drop events
    const dropZone = document.getElementById('localDropZone');
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      });
    });
    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
      });
    });
    dropZone.addEventListener('drop', (e) => {
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        handleLocalFile(e.dataTransfer.files[0]);
      }
    });
  </script>
</body>
</html>
