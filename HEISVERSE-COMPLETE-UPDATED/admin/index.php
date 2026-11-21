<?php
/**
 * HEISVERSE — Master Administrator Control Suite
 * Complete management of Movies, Series, Anime, Seasons, Episodes, Video Sources,
 * Users, Contact & Social Media Information, and Platform Configuration.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$currentUser = Auth::user();
$db = Database::getConnection();

$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'overview';

// Process Admin Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Add / Publish Title
        if ($action === 'add_title') {
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'Action');
            $contentType = trim($_POST['content_type'] ?? 'movies');
            $country = trim($_POST['country'] ?? 'United States');
            $language = trim($_POST['original_language'] ?? 'English');
            $released = (int)($_POST['released'] ?? 2026);
            $runtime = trim($_POST['runtime'] ?? '2h');
            $icon = trim($_POST['icon'] ?? '🎬');
            $about = trim($_POST['about'] ?? '');
            $bg = trim($_POST['bg'] ?? 'linear-gradient(135deg,#0f2027,#2c5364)');
            $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;
            $trailerUrl = trim($_POST['trailer_url'] ?? '');

            if (!empty($title) && !empty($about)) {
                $stmt = $db->prepare('INSERT INTO movies (title, category, content_type, country, original_language, released, runtime, bg, icon, about, is_featured, is_published) VALUES (:t, :c, :ct, :co, :l, :r, :run, :bg, :icon, :ab, :feat, 1)');
                $stmt->execute([
                    ':t' => $title, ':c' => $category, ':ct' => $contentType,
                    ':co' => $country, ':l' => $language, ':r' => $released,
                    ':run' => $runtime, ':bg' => $bg, ':icon' => $icon,
                    ':ab' => $about, ':feat' => $isFeatured
                ]);
                $newId = (int)$db->lastInsertId();

                if (!empty($trailerUrl)) {
                    $sStmt = $db->prepare('INSERT INTO movie_sources (movie_id, source_type, source_url, label, quality, is_default, rights_info) VALUES (:m, "youtube", :u, "Official Trailer", "1080p", 1, "Official Authorized Trailer")');
                    $sStmt->execute([':m' => $newId, ':u' => $trailerUrl]);
                }
                $message = "Title '{$title}' added successfully!";
            } else {
                $error = 'Please fill out all required fields.';
            }
        }

        // Upload a local MP4 or WebM video.
        if ($action === 'upload_local_video') {
            $movieId = (int)($_POST['movie_id'] ?? 0);
            $file = $_FILES['video_file'] ?? null;
            $savedFile = null;

            try {
                if (!$file) {
                    throw new RuntimeException('PHP did not receive a video file. Please choose a file and try again.');
                }

                $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

                if ($uploadError !== UPLOAD_ERR_OK) {
                    $uploadMessages = [
                        UPLOAD_ERR_INI_SIZE => 'The video exceeds the PHP upload limit.',
                        UPLOAD_ERR_FORM_SIZE => 'The video exceeds the form upload limit.',
                        UPLOAD_ERR_PARTIAL => 'The video upload was interrupted. Please try again.',
                        UPLOAD_ERR_NO_FILE => 'No video file was selected.',
                        UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary folder is missing.',
                        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded video.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the video upload.'
                    ];

                    throw new RuntimeException(
                        $uploadMessages[$uploadError] ?? 'Unknown upload error code: ' . $uploadError
                    );
                }

                if (!is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException('Invalid uploaded file.');
                }

                if ($file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024 * 1024) {
                    throw new RuntimeException('Video must be no larger than 2 GB.');
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $mimeTypes = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];

                if (!isset($mimeTypes[$extension])) {
                    throw new RuntimeException('Only MP4 and WebM files are supported.');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if ($finfo->file($file['tmp_name']) !== $mimeTypes[$extension]) {
                    throw new RuntimeException('The video file type does not match its extension.');
                }

                $movieCheck = $db->prepare('SELECT id FROM movies WHERE id = :id');
                $movieCheck->execute([':id' => $movieId]);
                if (!$movieCheck->fetch()) {
                    throw new RuntimeException('Please select a valid catalog movie.');
                }

                $directory = dirname(__DIR__) . '/uploads/videos';
                if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
                    throw new RuntimeException('Video storage folder could not be created.');
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                $savedFile = $directory . DIRECTORY_SEPARATOR . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $savedFile)) {
                    throw new RuntimeException('Could not save the video file.');
                }

                $db->beginTransaction();

                $reset = $db->prepare(
                    'UPDATE movie_sources SET is_default = 0 WHERE movie_id = :id'
                );
                $reset->execute([':id' => $movieId]);

                $insert = $db->prepare(
                    'INSERT INTO movie_sources
                    (movie_id, source_type, source_url, label, quality, is_default, is_active, rights_info)
                    VALUES (:movie, :type, :url, :label, :quality, 1, 1, :rights)'
                );

                $insert->execute([
                    ':movie' => $movieId,
                    ':type' => $extension,
                    ':url' => '/uploads/videos/' . $fileName,
                    ':label' => substr(trim($_POST['label'] ?? 'Full Movie'), 0, 80),
                    ':quality' => substr(trim($_POST['quality'] ?? '1080p'), 0, 20),
                    ':rights' => substr(trim($_POST['rights_info'] ?? 'Authorized Local File'), 0, 255)
                ]);

                $db->commit();
                $savedFile = null;
                $message = 'Local video uploaded and attached successfully!';

            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($savedFile && is_file($savedFile)) {
                    unlink($savedFile);
                }

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Upload failed. Check the application error log.';
                error_log('[HEISVERSE UPLOAD] ' . $e->getMessage());
            }
        }
        // 2. Delete Title
        if ($action === 'delete_title') {
            $delId = (int)($_POST['title_id'] ?? 0);
            if ($delId > 0) {
                $stmt = $db->prepare('DELETE FROM movies WHERE id = :id');
                $stmt->execute([':id' => $delId]);
                $message = 'Title removed from catalog.';
            }
        }

        // 3. Add Video Source
        if ($action === 'add_source') {
            $movieId = (int)($_POST['movie_id'] ?? 0);
            $type = $_POST['source_type'] ?? 'youtube';
            $url = trim($_POST['source_url'] ?? '');
            $label = trim($_POST['label'] ?? 'Alternative Stream');
            $quality = trim($_POST['quality'] ?? 'HD 1080p');
            $rights = trim($_POST['rights_info'] ?? 'Authorized Stream');

            if ($movieId > 0 && !empty($url)) {
                $stmt = $db->prepare('INSERT INTO movie_sources (movie_id, source_type, source_url, label, quality, rights_info) VALUES (:m, :t, :u, :l, :q, :r)');
                $stmt->execute([':m' => $movieId, ':t' => $type, ':u' => $url, ':l' => $label, ':q' => $quality, ':r' => $rights]);
                $message = 'Video source attached successfully.';
            }
        }

        // 4. Add TV Series Season
        if ($action === 'add_season') {
            $movieId = (int)($_POST['movie_id'] ?? 0);
            $seasonNum = (int)($_POST['season_number'] ?? 1);
            $title = trim($_POST['title'] ?? "Season {$seasonNum}");
            $overview = trim($_POST['overview'] ?? '');

            if ($movieId > 0) {
                $stmt = $db->prepare('INSERT INTO seasons (movie_id, season_number, title, overview) VALUES (:m, :s, :t, :o)');
                $stmt->execute([':m' => $movieId, ':s' => $seasonNum, ':t' => $title, ':o' => $overview]);
                $message = "Season {$seasonNum} added.";
            }
        }

        // 5. Add Episode
        if ($action === 'add_episode') {
            $seasonId = (int)($_POST['season_id'] ?? 0);
            $movieId = (int)($_POST['movie_id'] ?? 0);
            $epNum = (int)($_POST['episode_number'] ?? 1);
            $epTitle = trim($_POST['title'] ?? "Episode {$epNum}");
            $runtime = trim($_POST['runtime'] ?? '24m');
            $sourceUrl = trim($_POST['source_url'] ?? '');
            $quality = trim($_POST['quality'] ?? 'HD 1080p');

            if ($seasonId > 0 && $movieId > 0) {
                $stmt = $db->prepare('INSERT INTO episodes (season_id, movie_id, episode_number, title, runtime, source_url, quality) VALUES (:sid, :mid, :ep, :t, :r, :u, :q)');
                $stmt->execute([':sid' => $seasonId, ':mid' => $movieId, ':ep' => $epNum, ':t' => $epTitle, ':r' => $runtime, ':u' => $sourceUrl, ':q' => $quality]);
                $message = "Episode {$epNum} added.";
            }
        }

        // 6. Update Contact & Social Media Information
        if ($action === 'update_contact') {
            $contactFields = [
                'contact_email', 'support_email', 'phone_number', 'whatsapp_number',
                'country', 'physical_address', 'website_url', 'business_description',
                'social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube',
                'social_telegram', 'social_x'
            ];
            $stmt = $db->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2');
            foreach ($contactFields as $f) {
                $val = trim($_POST[$f] ?? '');
                $stmt->execute([':k' => $f, ':v' => $val, ':v2' => $val]);
            }
            $message = 'Contact and social media details successfully saved!';
        }

        // 7. Update Platform & SMTP Settings
        if ($action === 'update_settings') {
            $settingsFields = [
                'site_name', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption'
            ];
            $stmt = $db->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2');
            foreach ($settingsFields as $f) {
                $val = trim($_POST[$f] ?? '');
                $stmt->execute([':k' => $f, ':v' => $val, ':v2' => $val]);
            }
            $message = 'Platform and SMTP settings successfully updated!';
        }

        // 8. Toggle User Active / Admin Role
        if ($action === 'toggle_user') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid > 0 && $uid !== (int)$currentUser['id']) {
                $stmt = $db->prepare('UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id = :id');
                $stmt->execute([':id' => $uid]);
                $message = 'User status updated.';
            }
        }
    }
}

// Fetch Data for Dashboard Views
$totalTitles = $db ? (int)$db->query('SELECT COUNT(*) FROM movies')->fetchColumn() : 0;
$totalUsers = $db ? (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() : 0;
$totalSources = $db ? (int)$db->query('SELECT COUNT(*) FROM movie_sources')->fetchColumn() : 0;
$totalEpisodes = $db ? (int)$db->query('SELECT COUNT(*) FROM episodes')->fetchColumn() : 0;

$allTitles = $db ? $db->query('SELECT m.*, (SELECT COUNT(*) FROM movie_sources s WHERE s.movie_id = m.id) as sources_cnt FROM movies m ORDER BY m.id DESC')->fetchAll() : [];
$allUsers = $db ? $db->query('SELECT id, username, email, role, is_active, created_at FROM users ORDER BY id DESC')->fetchAll() : [];
$allSeasons = $db ? $db->query('SELECT s.*, m.title as movie_title FROM seasons s JOIN movies m ON s.movie_id = m.id ORDER BY s.id DESC')->fetchAll() : [];

$settings = [];
if ($db) {
    $sq = $db->query('SELECT setting_key, setting_value FROM system_settings');
    while ($r = $sq->fetch()) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark-cinematic">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Suite — HEISVERSE</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .admin-layout {
      display: grid;
      grid-template-columns: 240px 1fr;
      min-height: calc(100vh - var(--header-height));
    }
    .admin-sidebar {
      background: var(--bg-glass);
      border-right: 1px solid var(--border-glass);
      padding: 1.5rem 1rem;
    }
    .sidebar-menu {
      list-style: none;
    }
    .sidebar-item a {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.65rem 0.9rem;
      border-radius: var(--border-radius-sm);
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--text-muted);
      margin-bottom: 0.3rem;
      transition: all 0.2s;
    }
    .sidebar-item a:hover, .sidebar-item a.active {
      background: var(--accent);
      color: #fff;
    }
    .admin-content {
      padding: 2rem;
      overflow-y: auto;
    }
    .admin-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }
    .stat-card {
      background: var(--bg-glass);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-md);
      padding: 1.5rem;
      text-align: center;
    }
    .stat-value {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--accent);
      margin-top: 0.4rem;
    }
    .admin-card {
      background: var(--bg-glass);
      border: 1px solid var(--border-glass);
      border-radius: var(--border-radius-md);
      padding: 2rem;
      margin-bottom: 2rem;
    }
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    .admin-table th, .admin-table td {
      padding: 0.75rem 0.9rem;
      border-bottom: 1px solid var(--border-glass);
      font-size: 0.88rem;
    }
    .admin-table th {
      background: rgba(255,255,255,0.04);
      font-weight: 700;
    }
    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- Top Navbar -->
  <header class="navbar">
    <a href="../index.php" class="nav-brand">
      <img src="../assets/images/logo.svg" alt="HEISVERSE" style="height:34px;">
      <span style="font-size:0.8rem; background:#ef4444; color:#fff; padding:0.2rem 0.5rem; border-radius:4px; font-weight:700;">ADMIN</span>
    </a>
    <div class="nav-actions">
      <a href="../index.php" class="btn btn-glass btn-sm">← Platform Home</a>
      <span style="font-size:0.85rem; color:var(--text-muted);">Admin: <?= e($currentUser['username']) ?></span>
      <a href="../logout.php" class="btn btn-glass btn-sm">Logout</a>
    </div>
  </header>

  <div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
      <ul class="sidebar-menu">
        <li class="sidebar-item"><a href="?tab=overview" class="<?= $activeTab === 'overview' ? 'active' : '' ?>">📊 Overview</a></li>
        <li class="sidebar-item"><a href="?tab=catalog" class="<?= $activeTab === 'catalog' ? 'active' : '' ?>">🎬 Movies & Titles</a></li>
        <li class="sidebar-item"><a href="?tab=sources" class="<?= $activeTab === 'sources' ? 'active' : '' ?>">📡 Video Sources</a></li>
        <li class="sidebar-item"><a href="?tab=seasons" class="<?= $activeTab === 'seasons' ? 'active' : '' ?>">📺 Seasons & Episodes</a></li>
        <li class="sidebar-item"><a href="?tab=users" class="<?= $activeTab === 'users' ? 'active' : '' ?>">👥 User Roles</a></li>
        <li class="sidebar-item"><a href="register.php">+ Create Admin Account</a></li>
        <li class="sidebar-item"><a href="?tab=contact" class="<?= $activeTab === 'contact' ? 'active' : '' ?>">📬 Contact & Social</a></li>
        <li class="sidebar-item"><a href="?tab=settings" class="<?= $activeTab === 'settings' ? 'active' : '' ?>">⚙️ Platform & SMTP</a></li>
      </ul>
    </aside>

    <!-- Main Admin Workspace -->
    <main class="admin-content">

      <?php if (!empty($message)): ?>
        <div style="background:rgba(16,185,129,0.18); border:1px solid #10b981; color:#a7f3d0; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
          ✓ <?= e($message) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div style="background:rgba(239,68,68,0.18); border:1px solid #ef4444; color:#fca5a5; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
          ⚠️ <?= e($error) ?>
        </div>
      <?php endif; ?>

      <!-- TAB 1: OVERVIEW -->
      <?php if ($activeTab === 'overview'): ?>
        <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:1.5rem;">Platform Overview</h2>
        <div class="admin-stats">
          <div class="stat-card">
            <div>Catalog Titles</div>
            <div class="stat-value"><?= $totalTitles ?></div>
          </div>
          <div class="stat-card">
            <div>Registered Users</div>
            <div class="stat-value"><?= $totalUsers ?></div>
          </div>
          <div class="stat-card">
            <div>Active Stream Sources</div>
            <div class="stat-value"><?= $totalSources ?></div>
          </div>
          <div class="stat-card">
            <div>Episodic Content</div>
            <div class="stat-value"><?= $totalEpisodes ?></div>
          </div>
        </div>

        <div class="admin-card">
          <h3 style="font-size:1.2rem; margin-bottom:0.8rem;">🛡️ MySQL 8 Database & Container Health</h3>
          <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.6;">
            Container <b>heisverse_db</b> is healthy. Named volume <b>heisverse_db_data</b> is active and persistent.<br>
            All queries utilize prepared statements. Database charset is set to <code>utf8mb4_unicode_ci</code>.
          </p>
        </div>
      <?php endif; ?>

      <!-- TAB 2: CATALOG MANAGEMENT -->
      <?php if ($activeTab === 'catalog'): ?>

        <style>
          .hv-catalog-heading { margin-bottom: 1.5rem; }
          .hv-catalog-heading h2 { font-size: 1.8rem; font-weight: 800; margin: 0 0 .4rem; }
          .hv-catalog-heading p { color: var(--text-muted); margin: 0; }
          .hv-form-card {
            background: linear-gradient(145deg, rgba(30,35,52,.96), rgba(15,18,29,.98));
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 18px;
            padding: clamp(1rem, 3vw, 2rem);
            margin-bottom: 1.5rem;
            box-shadow: 0 15px 40px rgba(0,0,0,.16);
          }
          .hv-form-title { display:flex; align-items:center; gap:.8rem; margin-bottom:1.4rem; }
          .hv-form-icon {
            width:42px; height:42px; display:grid; place-items:center;
            border-radius:12px; background:rgba(139,92,246,.16); font-size:1.25rem;
          }
          .hv-form-title h3 { margin:0; font-size:1.15rem; font-weight:800; }
          .hv-form-title p { margin:.25rem 0 0; color:var(--text-muted); font-size:.85rem; }
          .hv-form-grid {
            display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
            gap:1rem;
          }
          .hv-field { min-width:0; margin-bottom:1rem; }
          .hv-field label { display:block; margin-bottom:.45rem; font-size:.83rem; font-weight:650; }
          .hv-field .form-control { width:100%; box-sizing:border-box; }
          .hv-field-full { grid-column:1/-1; }
          .hv-form-actions {
            display:flex; justify-content:space-between; align-items:center;
            gap:1rem; flex-wrap:wrap; border-top:1px solid rgba(255,255,255,.08);
            padding-top:1.2rem; margin-top:.5rem;
          }
          .hv-upload-box {
            border:1px dashed rgba(167,139,250,.45);
            border-radius:14px; padding:1.2rem;
            background:rgba(139,92,246,.055);
          }
          .hv-help { display:block; color:var(--text-muted); font-size:.78rem; margin-top:.4rem; }
          @media(max-width:650px) {
            .hv-form-grid { grid-template-columns:1fr; }
            .hv-field-full { grid-column:auto; }
            .hv-form-actions .btn { width:100%; }
          }
        </style>

        <div class="hv-catalog-heading">
          <h2>Catalog &amp; Titles Management</h2>
          <p>Create titles and manage authorized video files in your HEISVERSE library.</p>
        </div>

        <!-- ADD MOVIE FORM -->
        <section class="hv-form-card">
          <div class="hv-form-title">
            <div class="hv-form-icon">&#127916;</div>
            <div>
              <h3>Add New Title</h3>
              <p>Enter the information that will appear on the platform.</p>
            </div>
          </div>

          <form method="POST" action="index.php?tab=catalog">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_title">

            <div class="hv-form-grid">
              <div class="hv-field">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Enter movie or series title">
              </div>

              <div class="hv-field">
                <label>Format / Type</label>
                <select name="content_type" class="form-control">
                  <option value="movies">Movie</option>
                  <option value="tv_series">TV Series</option>
                  <option value="anime_series">Anime Series</option>
                  <option value="anime_movies">Anime Movie</option>
                  <option value="cartoons">Cartoon</option>
                  <option value="kdrama">K-Drama</option>
                  <option value="documentaries">Documentary</option>
                </select>
              </div>

              <div class="hv-field">
                <label>Genre</label>
                <select name="category" class="form-control">
                  <option value="Action">Action</option>
                  <option value="Drama">Drama</option>
                  <option value="Sci-Fi">Sci-Fi</option>
                  <option value="Comedy">Comedy</option>
                  <option value="Adventure">Adventure</option>
                  <option value="Animation">Animation</option>
                  <option value="Thriller">Thriller</option>
                </select>
              </div>

              <div class="hv-field">
                <label>Country of Origin</label>
                <input type="text" name="country" class="form-control" value="United States">
              </div>

              <div class="hv-field">
                <label>Original Language</label>
                <input type="text" name="original_language" class="form-control" value="English">
              </div>

              <div class="hv-field">
                <label>Release Year</label>
                <input type="number" name="released" class="form-control" value="2026" min="1888" max="2100">
              </div>

              <div class="hv-field">
                <label>Runtime / Length</label>
                <input type="text" name="runtime" class="form-control" value="2h 15m" placeholder="e.g. 1h 45m">
              </div>

              <div class="hv-field">
                <label>Icon / Monogram</label>
                <input type="text" name="icon" class="form-control" value="&#127916;" maxlength="20">
              </div>

              <div class="hv-field hv-field-full">
                <label>Official Trailer URL</label>
                <input type="url" name="trailer_url" class="form-control" placeholder="https://www.youtube.com/embed/...">
                <small class="hv-help">Optional. Add an official trailer or authorized embed URL.</small>
              </div>

              <div class="hv-field hv-field-full">
                <label>Spoiler-Free Synopsis *</label>
                <textarea name="about" class="form-control" rows="4" required placeholder="Write a short description of this title..."></textarea>
              </div>
            </div>

            <div class="hv-form-actions">
              <label style="display:flex;align-items:center;gap:.55rem;font-size:.88rem;">
                <input type="checkbox" name="is_featured" value="1">
                Feature on Hero Carousel
              </label>
              <button type="submit" class="btn btn-primary">Save &amp; Publish Title</button>
            </div>
          </form>
        </section>

        <!-- SEPARATE VIDEO UPLOAD FORM -->
        <section class="hv-form-card">
          <div class="hv-form-title">
            <div class="hv-form-icon">&#127909;</div>
            <div>
              <h3>Upload Local Film</h3>
              <p>Attach an MP4 or WebM file you own or are authorized to distribute.</p>
            </div>
          </div>

          <form method="POST" action="index.php?tab=catalog" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload_local_video">

            <div class="hv-field">
              <label>Select Catalog Title *</label>
              <select name="movie_id" class="form-control" required>
                <option value="">Choose a catalog title</option>
                <?php foreach ($allTitles as $t): ?>
                  <option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="hv-upload-box">
              <div class="hv-field" style="margin-bottom:0;">
                <label>Video File (MP4 or WebM) *</label>
                <input type="file" name="video_file" class="form-control"
                       accept=".mp4,.webm,video/mp4,video/webm" required>
                <small class="hv-help">Maximum size: 2 GB. Select a video file from your computer.</small>
              </div>
            </div>

            <div class="hv-form-grid" style="margin-top:1rem;">
              <div class="hv-field">
                <label>Video Label</label>
                <input type="text" name="label" class="form-control" value="Full Movie" maxlength="80" required>
              </div>
              <div class="hv-field">
                <label>Quality</label>
                <select name="quality" class="form-control">
                  <option value="4K UHD">4K UHD</option>
                  <option value="1080p" selected>1080p</option>
                  <option value="720p">720p</option>
                  <option value="480p">480p</option>
                </select>
              </div>
            </div>

            <input type="hidden" name="rights_info" value="Authorized Local File">
            <div class="hv-form-actions">
              <small class="hv-help">The selected title must already exist in your catalog.</small>
              <button type="submit" class="btn btn-primary">Upload &amp; Attach Video</button>
            </div>
          </form>
        </section>
        <div class="admin-card">
          <h3 style="font-size:1.2rem; margin-bottom:1rem;">Titles Directory (<?= count($allTitles) ?>)</h3>
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Type</th>
                <th>Genre</th>
                <th>Country</th>
                <th>Year</th>
                <th>Sources</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allTitles as $t): ?>
                <tr>
                  <td><?= (int)$t['id'] ?></td>
                  <td><b><?= e($t['title']) ?></b></td>
                  <td><span class="movie-badge-category" style="position:static;"><?= e($t['content_type']) ?></span></td>
                  <td><?= e($t['category']) ?></td>
                  <td><?= e($t['country']) ?></td>
                  <td><?= (int)$t['released'] ?></td>
                  <td><?= (int)$t['sources_cnt'] ?> streams</td>
                  <td>
                    <form method="POST" action="index.php?tab=catalog" style="display:inline;" onsubmit="return confirm('Remove this title?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_title">
                      <input type="hidden" name="title_id" value="<?= (int)$t['id'] ?>">
                      <button type="submit" class="btn btn-glass btn-sm" style="color:#ef4444;">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- TAB 3: VIDEO SOURCES MANAGEMENT -->
      <?php if ($activeTab === 'sources'): ?>
        <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:1.5rem;">Authorized Video Sources</h2>
        <div class="admin-card">
          <h3 style="font-size:1.2rem; margin-bottom:1rem;">➕ Attach New Playback Stream</h3>
          <form method="POST" action="index.php?tab=sources">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_source">

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1rem;">
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Attach to Title</label>
                <select name="movie_id" class="form-control" required>
                  <?php foreach ($allTitles as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?> (<?= e($t['content_type']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Stream Provider</label>
                <select name="source_type" class="form-control">
                  <option value="youtube">Official YouTube Embed</option>
                  <option value="archive_org">Internet Archive Public Stream</option>
                  <option value="mp4">Authorized MP4 Direct File</option>
                  <option value="webm">Authorized WebM File</option>
                </select>
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Quality Label</label>
                <select name="quality" class="form-control">
                  <option value="HD 1080p">HD 1080p</option>
                  <option value="4K UHD">4K UHD</option>
                  <option value="720p HD">720p HD</option>
                  <option value="480p">480p</option>
                </select>
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Stream Label</label>
                <input type="text" name="label" class="form-control" value="Official 4K Trailer" required>
              </div>
            </div>

            <div style="margin-bottom:1rem;">
              <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Stream URL</label>
              <input type="url" name="source_url" class="form-control" placeholder="https://..." required>
            </div>

            <div style="margin-bottom:1.2rem;">
              <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Rights / License Information</label>
              <input type="text" name="rights_info" class="form-control" value="Official studio trailer authorized for public streaming">
            </div>

            <button type="submit" class="btn btn-primary">Attach Stream Source</button>
          </form>
        </div>
      <?php endif; ?>

      <!-- TAB 4: SEASONS & EPISODES -->
      <?php if ($activeTab === 'seasons'): ?>
        <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:1.5rem;">TV Series & Anime Episodic Structure</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:2rem;">
          <!-- Add Season -->
          <div class="admin-card">
            <h3 style="font-size:1.1rem; margin-bottom:1rem;">➕ Add Season</h3>
            <form method="POST" action="index.php?tab=seasons">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_season">

              <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Series</label>
                <select name="movie_id" class="form-control" required>
                  <?php foreach ($allTitles as $t): ?>
                    <?php if (in_array($t['content_type'], ['tv_series', 'anime_series', 'kdrama', 'cartoons'])): ?>
                      <option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Season Number</label>
                <input type="number" name="season_number" class="form-control" value="1" required>
              </div>

              <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Season Title</label>
                <input type="text" name="title" class="form-control" value="Season 1">
              </div>

              <button type="submit" class="btn btn-primary" style="width:100%;">Create Season</button>
            </form>
          </div>

          <!-- Add Episode -->
          <div class="admin-card">
            <h3 style="font-size:1.1rem; margin-bottom:1rem;">➕ Add Episode to Season</h3>
            <form method="POST" action="index.php?tab=seasons">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_episode">

              <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Select Season</label>
                <select name="season_id" class="form-control" required>
                  <?php foreach ($allSeasons as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= e($s['movie_title']) ?> — <?= e($s['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Episode # & Title</label>
                <div style="display:flex; gap:0.5rem;">
                  <input type="number" name="episode_number" value="1" class="form-control" style="width:70px;" required>
                  <input type="text" name="title" placeholder="Episode title" class="form-control" required>
                </div>
              </div>

              <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.8rem; margin-bottom:0.3rem;">Source URL</label>
                <input type="url" name="source_url" class="form-control" placeholder="https://..." required>
              </div>

              <input type="hidden" name="movie_id" value="101">
              <button type="submit" class="btn btn-primary" style="width:100%;">Add Episode</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <!-- TAB 5: USER ACCOUNTS & ROLES -->
      <?php if ($activeTab === 'users'): ?>
        <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:1.5rem;">User Accounts & Moderation</h2>
        <div class="admin-card">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allUsers as $u): ?>
                <tr>
                  <td><?= (int)$u['id'] ?></td>
                  <td><b><?= e($u['username']) ?></b></td>
                  <td><?= e($u['email']) ?></td>
                  <td><span class="hero-tag" style="padding:0.1rem 0.5rem;"><?= e($u['role']) ?></span></td>
                  <td><?= (int)$u['is_active'] === 1 ? '<span style="color:#34d399;">Active</span>' : '<span style="color:#ef4444;">Suspended</span>' ?></td>
                  <td>
                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                      <form method="POST" action="index.php?tab=users" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_user">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-glass btn-sm">
                          <?= (int)$u['is_active'] === 1 ? 'Suspend' : 'Reactivate' ?>
                        </button>
                      </form>
                    <?php else: ?>
                      <small style="color:var(--text-dim);">Current User</small>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- TAB 6: CONTACT & SOCIAL MEDIA SETTINGS (PART 8) -->
      <?php if ($activeTab === 'contact'): ?>
        <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:1.5rem;">Contact Information & Social Media</h2>
        <div class="admin-card">
          <form method="POST" action="index.php?tab=contact">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_contact">

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.2rem; margin-bottom:1.5rem;">
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Official Contact Email</label>
                <input type="email" name="contact_email" class="form-control" value="<?= e($settings['contact_email'] ?? '') ?>" required>
              </div>
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Support Email</label>
                <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email'] ?? '') ?>" required>
              </div>
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Phone Number</label>
                <input type="text" name="phone_number" class="form-control" value="<?= e($settings['phone_number'] ?? '') ?>">
              </div>
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">WhatsApp Number</label>
                <input type="text" name="whatsapp_number" class="form-control" value="<?= e($settings['whatsapp_number'] ?? '') ?>">
              </div>
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Country</label>
                <input type="text" name="country" class="form-control" value="<?= e($settings['country'] ?? 'Rwanda') ?>">
              </div>
              <div>
                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Website URL</label>
                <input type="url" name="website_url" class="form-control" value="<?= e($settings['website_url'] ?? 'http://localhost:8080') ?>">
              </div>
            </div>

            <div style="margin-bottom:1.5rem;">
              <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Physical Address</label>
              <input type="text" name="physical_address" class="form-control" value="<?= e($settings['physical_address'] ?? '') ?>">
            </div>

            <div style="margin-bottom:1.5rem;">
              <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Business / Platform Description</label>
              <textarea name="business_description" class="form-control" rows="3"><?= e($settings['business_description'] ?? '') ?></textarea>
            </div>

            <h3 style="font-size:1.1rem; margin-bottom:1rem;">🌐 Social Media Links</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:2rem;">
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Twitter / X</label>
                <input type="url" name="social_x" class="form-control" value="<?= e($settings['social_x'] ?? '') ?>" placeholder="https://x.com/...">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">YouTube Channel</label>
                <input type="url" name="social_youtube" class="form-control" value="<?= e($settings['social_youtube'] ?? '') ?>" placeholder="https://youtube.com/...">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Instagram</label>
                <input type="url" name="social_instagram" class="form-control" value="<?= e($settings['social_instagram'] ?? '') ?>" placeholder="https://instagram.com/...">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Facebook</label>
                <input type="url" name="social_facebook" class="form-control" value="<?= e($settings['social_facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">Telegram</label>
                <input type="url" name="social_telegram" class="form-control" value="<?= e($settings['social_telegram'] ?? '') ?>" placeholder="https://t.me/...">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">TikTok</label>
                <input type="url" name="social_tiktok" class="form-control" value="<?= e($settings['social_tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@...">
              </div>
            </div>

            <button type="submit" class="btn btn-primary" style="padding:0.75rem 2rem;">Save Contact & Social Details</button>
          </form>
        </div>
      <?php endif; ?>

      <!-- TAB 7: SETTINGS & SMTP -->
      <?php if ($activeTab === 'settings'): ?>
        <h2 style="font-size:1.8rem; font-weight:800; margin-bottom:1.5rem;">Platform Configuration & SMTP Email</h2>
        <div class="admin-card">
          <form method="POST" action="index.php?tab=settings">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_settings">

            <div style="margin-bottom:1.5rem;">
              <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.3rem;">Platform Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? 'HEISVERSE') ?>" required>
            </div>

            <h3 style="font-size:1.1rem; margin-bottom:1rem;">📧 SMTP Email Recovery Configuration</h3>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.2rem;">
              Configure SMTP credentials for outgoing password reset emails. If left blank, the system automatically uses safe local development recovery links.
            </p>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.mailgun.org">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= e($settings['smtp_port'] ?? '587') ?>">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">SMTP Username</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= e($settings['smtp_user'] ?? '') ?>">
              </div>
              <div>
                <label style="display:block; font-size:0.8rem; margin-bottom:0.2rem;">SMTP Password</label>
                <input type="password" name="smtp_pass" class="form-control" placeholder="••••••••">
              </div>
            </div>

            <button type="submit" class="btn btn-primary" style="padding:0.75rem 2rem;">Save Platform Settings</button>
          </form>
        </div>
      <?php endif; ?>

    </main>
  </div>

  <script src="../assets/js/main.js"></script>
</body>
</html>
