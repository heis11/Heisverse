<?php
/**
 * HEISVERSE - Multi-Source Authorized Video Player Engine
 * Supports YouTube, Internet Archive, MP4, WebM, HLS and DASH.
 */
declare(strict_types=1);

require_once __DIR__ . '/database.php';

class MovieSources {

    private static function escape($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function isLocalVideoPath(string $url): bool {
        return str_starts_with($url, '/uploads/videos/')
            && !str_contains($url, '..')
            && preg_match('#^/uploads/videos/[A-Za-z0-9._/-]+$#', $url) === 1;
    }

    private static function isHttpsUrl(string $url): bool {
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host'])
            && empty($parts['user'])
            && empty($parts['pass']);
    }

    private static function youtubeEmbedUrl(string $url, int $resumeSeconds): ?string {
        if (!self::isHttpsUrl($url)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $id = '';

        if (in_array($host, ['youtu.be'], true)) {
            $id = trim($parts['path'] ?? '', '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            $path = $parts['path'] ?? '';

            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = (string)($query['v'] ?? '');
            } elseif (preg_match('#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})#', $path, $matches)) {
                $id = $matches[1];
            }
        }

        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return null;
        }

        $embed = 'https://www.youtube-nocookie.com/embed/' . $id
            . '?enablejsapi=1&rel=0&modestbranding=1';

        if ($resumeSeconds > 0) {
            $embed .= '&start=' . $resumeSeconds;
        }

        return $embed;
    }

    private static function archiveEmbedUrl(string $url): ?string {
        if (!self::isHttpsUrl($url)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (!in_array($host, ['archive.org', 'www.archive.org'], true)) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if (preg_match('#^/embed/([A-Za-z0-9._-]+)$#', $path, $matches)) {
            return 'https://archive.org/embed/' . $matches[1];
        }

        if (preg_match('#^/details/([A-Za-z0-9._-]+)$#', $path, $matches)) {
            return 'https://archive.org/embed/' . $matches[1];
        }

        return null;
    }

    private static function isApprovedEmbedUrl(string $url): bool {
        if (!self::isHttpsUrl($url)) {
            return false;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        $allowedHosts = [
            'archive.org',
            'www.archive.org',
            'youtube.com',
            'www.youtube.com',
            'youtube-nocookie.com',
            'www.youtube-nocookie.com',
            'player.vimeo.com'
        ];

        return in_array($host, $allowedHosts, true);
    }

    public static function getSourcesForMovie(int $movieId): array {
        $db = Database::getConnection();
        if (!$db) {
            return [];
        }

        try {
            $stmt = $db->prepare('
                SELECT id, movie_id, source_type, source_url, label,
                       quality, is_default, rights_info
                FROM movie_sources
                WHERE movie_id = :mid AND is_active = 1
                ORDER BY is_default DESC, id ASC
            ');
            $stmt->execute([':mid' => $movieId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[HEISVERSE SOURCES ERROR]: ' . $e->getMessage());
            return [];
        }
    }

    public static function renderPlayer(
        array $movie,
        ?array $activeSource = null,
        int $resumeSeconds = 0
    ): string {
        $movieId = (int)($movie['id'] ?? 0);
        $title = self::escape($movie['title'] ?? 'HEISVERSE Player');
        $poster = self::escape($movie['backdrop_url'] ?? '');
        $resumeSeconds = max(0, $resumeSeconds);

        $sources = self::getSourcesForMovie($movieId);

        if (empty($sources)) {
            return '<div class="player-empty-state" style="padding:3rem;text-align:center;">
                <p>No authorized playback streams are currently available for this title.</p>
            </div>';
        }

        if ($activeSource === null) {
            $activeSource = $sources[0];
        }

        $type = strtolower((string)($activeSource['source_type'] ?? ''));
        $url = trim((string)($activeSource['source_url'] ?? ''));
        $label = self::escape($activeSource['label'] ?? 'Video source');
        $rights = self::escape($activeSource['rights_info'] ?? 'Source permissions not specified');
        $quality = self::escape($activeSource['quality'] ?? 'Auto');
        $sourceId = (int)($activeSource['id'] ?? 0);

        $html = '<div class="heisverse-player-wrapper"
            data-movie-id="' . $movieId . '"
            data-source-id="' . $sourceId . '"
            data-resume="' . $resumeSeconds . '">';

        $iframeUrl = null;
        $videoUrl = null;
        $videoMime = '';
        $streamType = '';

        if ($type === 'youtube') {
            $iframeUrl = self::youtubeEmbedUrl($url, $resumeSeconds);
        } elseif ($type === 'archive_org') {
            $parts = parse_url($url);
            $path = $parts['path'] ?? '';
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (self::isHttpsUrl($url)
                && in_array(strtolower($parts['host'] ?? ''), ['archive.org', 'www.archive.org'], true)
                && in_array($extension, ['mp4', 'webm'], true)) {
                $videoUrl = $url;
                $videoMime = $extension === 'webm' ? 'video/webm' : 'video/mp4';
            } else {
                $iframeUrl = self::archiveEmbedUrl($url);
            }
        } elseif ($type === 'mp4' || $type === 'webm') {
            if (self::isHttpsUrl($url) || self::isLocalVideoPath($url)) {
                $videoUrl = $url;
                $videoMime = $type === 'webm' ? 'video/webm' : 'video/mp4';
            }
        } elseif ($type === 'hls' || $type === 'dash') {
            if (self::isHttpsUrl($url)) {
                $videoUrl = $url;
                $streamType = $type;
            }
        } elseif ($type === 'embed') {
            if (self::isApprovedEmbedUrl($url)) {
                $iframeUrl = $url;

                if (str_contains($url, 'youtube.com/watch') || str_contains($url, 'youtu.be/')) {
                    $iframeUrl = self::youtubeEmbedUrl($url, $resumeSeconds);
                } elseif (str_contains($url, 'archive.org/details/')) {
                    $iframeUrl = self::archiveEmbedUrl($url);
                }
            }
        }

        if ($iframeUrl !== null) {
            $html .= '<div class="video-container responsive-embed">
                <iframe id="heisverseIframePlayer"
                    src="' . self::escape($iframeUrl) . '"
                    title="' . $title . '"
                    loading="eager"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen>
                </iframe>
            </div>';
        } elseif ($videoUrl !== null) {
            $html .= '<div class="video-container html5-player" style="position:relative;">
                <video
                    id="heisverseHtml5Player"
                    controls
                    playsinline
                    preload="metadata"
                    poster="' . $poster . '"
                    data-resume="' . $resumeSeconds . '"
                    data-stream-type="' . self::escape($streamType) . '"
                    data-stream-url="' . self::escape($videoUrl) . '"
                    style="width:100%;max-height:70vh;background:#000;">';

            if ($streamType === '') {
                $html .= '<source src="' . self::escape($videoUrl) . '"
                    type="' . self::escape($videoMime) . '">';
            }

            $html .= 'Your browser does not support HTML5 video playback.
                </video>
            </div>';
        } else {
            $html .= '<div class="player-empty-state" style="padding:3rem;text-align:center;">
                <p>This source URL or provider is not supported by the HEISVERSE player.</p>
                <p>Please check the source type and authorized HTTPS URL in the admin dashboard.</p>
            </div>';
        }

        $html .= '<div class="player-controls-bar"
            style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:0.8rem;padding:0.8rem 1.2rem;background:var(--bg-glass);">';

        $html .= '<div class="source-selector-group"
            style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;">
            <label for="streamSourceSelector">Stream:</label>
            <select id="streamSourceSelector" class="form-control"
                style="padding:0.3rem 0.6rem;width:auto;font-size:0.85rem;"
                onchange="switchMovieSource(' . $movieId . ', this.value)">';

        foreach ($sources as $source) {
            $id = (int)$source['id'];
            $selected = ($id === $sourceId) ? ' selected' : '';

            $optionLabel = self::escape(
                ($source['label'] ?? 'Source') . ' (' .
                strtoupper((string)$source['source_type']) . ')'
            );

            $html .= '<option value="' . $id . '"' . $selected . '>'
                . $optionLabel . '</option>';
        }

        $html .= '</select></div>';

        $html .= '<div class="quality-selector-group"
            style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;">
            <label for="qualitySelector">Quality:</label>
            <select id="qualitySelector" class="form-control"
                style="padding:0.3rem 0.6rem;width:auto;font-size:0.85rem;"
                onchange="handleQualityChange(this.value)">';

        if ($type === 'youtube') {
            $html .= '<option value="auto" selected>Auto (Adaptive Stream)</option>
                <option value="hd1080">1080p Full HD</option>
                <option value="hd720">720p HD</option>
                <option value="large">480p</option>
                <option value="medium">360p</option>';
        } elseif ($type === 'hls' || $type === 'dash') {
            $html .= '<option value="auto" selected>Auto (Adaptive Stream)</option>';
            $html .= '<option value="source">' . $quality . ' (Source label)</option>';
        } else {
            $html .= '<option value="auto">Auto (Source Default)</option>';
            $html .= '<option value="source" selected>' . $quality . '</option>';
        }

        $html .= '</select></div>';

        $html .= '<div class="speed-selector-group"
            style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;">
            <label for="speedSelector">Speed:</label>
            <select id="speedSelector" class="form-control"
                style="padding:0.3rem 0.6rem;width:auto;font-size:0.85rem;"
                onchange="setPlayerSpeed(this.value)">
                <option value="0.75">0.75x</option>
                <option value="1.0" selected>1.0x (Normal)</option>
                <option value="1.25">1.25x</option>
                <option value="1.5">1.5x</option>
                <option value="2.0">2.0x</option>
            </select>
        </div>';

        $html .= '<div class="rights-badge"
            style="font-size:0.75rem;color:var(--text-dim);">
            <small title="' . $rights . '">' . $rights . '</small>
        </div>';

        $html .= '</div></div>';

        return $html;
    }

    public static function updateWatchProgress(
        int $userId,
        int $movieId,
        int $positionSeconds,
        int $duration = 0
    ): bool {
        $db = Database::getConnection();
        if (!$db) {
            return false;
        }

        try {
            $completed = (
                $duration > 0 &&
                ($positionSeconds / $duration) > 0.9
            ) ? 1 : 0;

            $stmt = $db->prepare('
                INSERT INTO watch_history
                    (user_id, movie_id, last_position, duration, completed, watched_at)
                VALUES
                    (:u, :m, :p, :d, :c, NOW())
                ON DUPLICATE KEY UPDATE
                    last_position = :p2,
                    duration = :d2,
                    completed = :c2,
                    watched_at = NOW()
            ');

            return $stmt->execute([
                ':u' => $userId,
                ':m' => $movieId,
                ':p' => $positionSeconds,
                ':d' => $duration,
                ':c' => $completed,
                ':p2' => $positionSeconds,
                ':d2' => $duration,
                ':c2' => $completed
            ]);
        } catch (PDOException $e) {
            error_log('[HEISVERSE WATCH HISTORY ERROR]: ' . $e->getMessage());
            return false;
        }
    }
}
