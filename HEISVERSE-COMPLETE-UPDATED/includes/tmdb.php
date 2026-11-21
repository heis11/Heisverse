<?php
/**
 * HEISVERSE — Centralized TMDB Service
 *
 * The ONLY place that talks to The Movie Database (https://developer.themoviedb.org/docs).
 * - The API key is read from the private .env (TMDB_API_KEY) and is used server-side only.
 * - Every response is validated, normalised and file-cached.
 * - Failures never crash a page: they throw TmdbException, which callers catch.
 *
 * Anime classification logic (TMDB has no "anime" flag):
 *   A title is treated as ANIME when it is Animation (genre id 16) AND
 *   (its original language is Japanese OR its origin country is JP).
 *   Japanese live-action shows are therefore NOT anime, and non-Japanese cartoons are NOT anime.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class TmdbException extends RuntimeException {
    public int $httpStatus;
    public function __construct(string $message, int $httpStatus = 0) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}

class Tmdb {
    private const API_BASE   = 'https://api.themoviedb.org/3';
    private const IMAGE_BASE = 'https://image.tmdb.org/t/p/';
    public const GENRE_ANIMATION = 16;

    /** Image sizes officially supported by TMDB's /configuration endpoint. */
    private const POSTER_SIZES   = ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'];
    private const BACKDROP_SIZES = ['w300', 'w780', 'w1280', 'original'];
    private const PROFILE_SIZES  = ['w45', 'w185', 'h632', 'original'];

    // ------------------------------------------------------------------
    // Configuration helpers
    // ------------------------------------------------------------------

    public static function isConfigured(): bool {
        return TMDB_API_KEY !== '' && TMDB_API_KEY !== 'YOUR_TMDB_API_KEY';
    }

    /** Build a valid TMDB image URL, or null when TMDB has no image. */
    public static function image(?string $path, string $kind = 'poster', string $size = 'w342'): ?string {
        if ($path === null || $path === '' || $path[0] !== '/') {
            return null;
        }
        $allowed = match ($kind) {
            'backdrop' => self::BACKDROP_SIZES,
            'profile'  => self::PROFILE_SIZES,
            default    => self::POSTER_SIZES,
        };
        if (!in_array($size, $allowed, true)) {
            $size = $allowed[min(3, count($allowed) - 1)];
        }
        return self::IMAGE_BASE . $size . $path;
    }

    /** srcset string so the browser picks a sensible poster size on phones/desktops. */
    public static function posterSrcset(?string $path): string {
        if (!$path) return '';
        return self::image($path, 'poster', 'w185') . ' 185w, '
             . self::image($path, 'poster', 'w342') . ' 342w, '
             . self::image($path, 'poster', 'w500') . ' 500w';
    }

    public static function backdropSrcset(?string $path): string {
        if (!$path) return '';
        return self::image($path, 'backdrop', 'w780') . ' 780w, '
             . self::image($path, 'backdrop', 'w1280') . ' 1280w, '
             . self::image($path, 'backdrop', 'original') . ' 2000w';
    }

    // ------------------------------------------------------------------
    // Low-level request with cache, timeout and error handling
    // ------------------------------------------------------------------

    /**
     * @param array<string,scalar|null> $params
     * @return array<string,mixed>
     * @throws TmdbException
     */
    public static function request(string $endpoint, array $params = [], ?int $ttl = null): array {
        if (!self::isConfigured()) {
            throw new TmdbException('TMDB_API_KEY is missing. Add it to the private .env file.', 0);
        }

        $endpoint = '/' . ltrim($endpoint, '/');
        if (!preg_match('#^/[a-zA-Z0-9/_\-]+$#', $endpoint)) {
            throw new TmdbException('Invalid TMDB endpoint.', 0);
        }

        $params = array_filter($params, fn($v) => $v !== null && $v !== '');
        $params += ['language' => TMDB_LANGUAGE];
        ksort($params);
        $ttl = $ttl ?? TMDB_CACHE_TTL;

        // Cache key never contains the API key.
        $cacheKey = sha1($endpoint . '?' . http_build_query($params));
        $cacheFile = CACHE_DIR . '/tmdb/' . $cacheKey . '.json';

        if ($ttl > 0 && is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        if (!is_dir(CACHE_DIR . '/tmdb')) { @mkdir(CACHE_DIR . '/tmdb', 0775, true); }

        // Back off briefly if TMDB told us we were rate limited.
        $blockFile = CACHE_DIR . '/tmdb/.ratelimit';
        if (is_file($blockFile) && (int)file_get_contents($blockFile) > time()) {
            if (is_file($cacheFile)) { // serve stale data rather than fail
                $stale = json_decode((string)file_get_contents($cacheFile), true);
                if (is_array($stale)) return $stale;
            }
            throw new TmdbException('TMDB rate limit reached. Please try again in a moment.', 429);
        }

        $url = self::API_BASE . $endpoint . '?' . http_build_query($params + ['api_key' => TMDB_API_KEY]);

        if (!function_exists('curl_init')) {
            throw new TmdbException('The PHP cURL extension is disabled. Enable extension=curl in php.ini.', 0);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => TMDB_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'HEISVERSE/1.0',
        ]);
        // XAMPP on Windows often has no CA bundle configured; use one shipped with the project if present.
        $caBundle = __DIR__ . '/../cacert.pem';
        if (is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errNo  = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);

        if ($body === false || $errNo !== 0) {
            self::log("cURL error {$errNo} on {$endpoint}: {$errMsg}");
            $hint = ($errNo === 60 || $errNo === 77)
                ? 'SSL certificate problem (see README: "cURL error 60").'
                : ($errNo === 28 ? 'TMDB took too long to respond.' : 'Could not reach TMDB.');
            if (($stale = self::stale($cacheFile)) !== null) { return $stale; }
            throw new TmdbException($hint, 0);
        }

        if ($status === 401) {
            self::log("TMDB rejected the API key (401) on {$endpoint}");
            throw new TmdbException('TMDB rejected the API key. Check TMDB_API_KEY in .env.', 401);
        }
        if ($status === 404) {
            throw new TmdbException('Title not found on TMDB.', 404);
        }
        if ($status === 429) {
            @file_put_contents($blockFile, (string)(time() + 10), LOCK_EX);
            if (($stale = self::stale($cacheFile)) !== null) { return $stale; }
            throw new TmdbException('TMDB rate limit reached. Please try again in a moment.', 429);
        }
        if ($status < 200 || $status >= 300) {
            self::log("TMDB HTTP {$status} on {$endpoint}");
            if (($stale = self::stale($cacheFile)) !== null) { return $stale; }
            throw new TmdbException("TMDB returned an error (HTTP {$status}).", $status);
        }

        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            self::log("Invalid JSON from TMDB on {$endpoint}");
            if (($stale = self::stale($cacheFile)) !== null) { return $stale; }
            throw new TmdbException('TMDB returned an unreadable response.', $status);
        }

        if ($ttl > 0) {
            $dir = dirname($cacheFile);
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $data;
    }

    /** Return expired cache data (better than an error page) or null. */
    private static function stale(string $cacheFile): ?array {
        if (!is_file($cacheFile)) return null;
        $data = json_decode((string)file_get_contents($cacheFile), true);
        return is_array($data) ? $data : null;
    }

    private static function log(string $msg): void {
        if (!is_dir(LOG_DIR)) { @mkdir(LOG_DIR, 0775, true); }
        // Messages never include the API key.
        @file_put_contents(LOG_DIR . '/tmdb.log', '[' . date('c') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // ------------------------------------------------------------------
    // Normalisation: every list item becomes the same small array
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $r raw TMDB item
     * @return array<string,mixed>
     */
    public static function normalize(array $r, ?string $forcedType = null): array {
        $type = $forcedType ?? ($r['media_type'] ?? (isset($r['first_air_date']) || isset($r['name']) ? 'tv' : 'movie'));
        $type = $type === 'tv' ? 'tv' : 'movie';
        $date = (string)($r['release_date'] ?? $r['first_air_date'] ?? '');
        $genreIds = array_map('intval', $r['genre_ids'] ?? array_column($r['genres'] ?? [], 'id'));
        $lang = (string)($r['original_language'] ?? '');
        $origin = array_values(array_filter((array)($r['origin_country'] ?? [])));
        $isAnime = in_array(self::GENRE_ANIMATION, $genreIds, true)
            && ($lang === 'ja' || in_array('JP', $origin, true));

        return [
            'id'            => (int)($r['id'] ?? 0),
            'media_type'    => $type,
            'is_anime'      => $isAnime,
            'title'         => (string)($r['title'] ?? $r['name'] ?? $r['original_title'] ?? $r['original_name'] ?? 'Untitled'),
            'overview'      => (string)($r['overview'] ?? ''),
            'date'          => $date,
            'year'          => strlen($date) >= 4 ? substr($date, 0, 4) : '',
            'rating'        => round((float)($r['vote_average'] ?? 0), 1),
            'votes'         => (int)($r['vote_count'] ?? 0),
            'popularity'    => (float)($r['popularity'] ?? 0),
            'genre_ids'     => $genreIds,
            'language'      => $lang,
            'poster_path'   => $r['poster_path'] ?? null,
            'backdrop_path' => $r['backdrop_path'] ?? null,
        ];
    }

    /**
     * Run a list endpoint and return normalised items + paging info.
     * @param array<string,scalar|null> $params
     * @return array{items: array<int,array<string,mixed>>, page:int, total_pages:int, total_results:int}
     */
    public static function listing(string $endpoint, array $params = [], ?string $forcedType = null, ?int $ttl = null): array {
        $data = self::request($endpoint, $params, $ttl);
        $items = [];
        foreach ((array)($data['results'] ?? []) as $row) {
            if (!is_array($row) || empty($row['id'])) continue;
            if (isset($row['media_type']) && !in_array($row['media_type'], ['movie', 'tv'], true)) continue; // skip people
            $items[] = self::normalize($row, $forcedType);
        }
        return [
            'items'         => $items,
            'page'          => (int)($data['page'] ?? 1),
            // TMDB only serves the first 500 pages
            'total_pages'   => min(500, (int)($data['total_pages'] ?? 1)),
            'total_results' => (int)($data['total_results'] ?? count($items)),
        ];
    }

    // ------------------------------------------------------------------
    // Movies
    // ------------------------------------------------------------------
    public static function popularMovies(int $page = 1): array   { return self::listing('movie/popular', ['page' => $page], 'movie'); }
    public static function topRatedMovies(int $page = 1): array  { return self::listing('movie/top_rated', ['page' => $page], 'movie'); }
    public static function upcomingMovies(int $page = 1): array  { return self::listing('movie/upcoming', ['page' => $page], 'movie'); }
    public static function nowPlayingMovies(int $page = 1): array{ return self::listing('movie/now_playing', ['page' => $page], 'movie'); }
    public static function trendingMovies(string $window = 'week', int $page = 1): array {
        return self::listing('trending/movie/' . self::window($window), ['page' => $page], 'movie');
    }
    public static function movieRecommendations(int $id, int $page = 1): array { return self::listing("movie/{$id}/recommendations", ['page' => $page], 'movie'); }
    public static function similarMovies(int $id, int $page = 1): array        { return self::listing("movie/{$id}/similar", ['page' => $page], 'movie'); }

    // ------------------------------------------------------------------
    // TV
    // ------------------------------------------------------------------
    public static function popularTv(int $page = 1): array   { return self::listing('tv/popular', ['page' => $page], 'tv'); }
    public static function topRatedTv(int $page = 1): array  { return self::listing('tv/top_rated', ['page' => $page], 'tv'); }
    public static function airingTodayTv(int $page = 1): array { return self::listing('tv/airing_today', ['page' => $page], 'tv'); }
    public static function trendingTv(string $window = 'week', int $page = 1): array {
        return self::listing('trending/tv/' . self::window($window), ['page' => $page], 'tv');
    }
    public static function tvRecommendations(int $id, int $page = 1): array { return self::listing("tv/{$id}/recommendations", ['page' => $page], 'tv'); }
    public static function similarTv(int $id, int $page = 1): array         { return self::listing("tv/{$id}/similar", ['page' => $page], 'tv'); }

    private static function window(string $w): string { return $w === 'day' ? 'day' : 'week'; }

    // ------------------------------------------------------------------
    // Genres (cached for a day)
    // ------------------------------------------------------------------
    /** @return array<int,string> id => name */
    public static function genres(string $type): array {
        $type = $type === 'tv' ? 'tv' : 'movie';
        try {
            $data = self::request("genre/{$type}/list", [], 86400);
        } catch (TmdbException $e) {
            return [];
        }
        $out = [];
        foreach ((array)($data['genres'] ?? []) as $g) {
            if (isset($g['id'], $g['name'])) $out[(int)$g['id']] = (string)$g['name'];
        }
        return $out;
    }

    /** First genre name for a normalised item. */
    public static function genreLabel(array $item, array $genreMap): string {
        foreach ($item['genre_ids'] as $gid) {
            if (isset($genreMap[$gid])) return $genreMap[$gid];
        }
        return '';
    }

    // ------------------------------------------------------------------
    // Search
    // ------------------------------------------------------------------
    /**
     * @param string $filter all|movie|tv|anime
     */
    public static function search(string $query, string $filter = 'all', int $page = 1): array {
        $query = trim(mb_substr($query, 0, 100));
        if ($query === '') {
            return ['items' => [], 'page' => 1, 'total_pages' => 0, 'total_results' => 0];
        }
        $params = ['query' => $query, 'page' => $page, 'include_adult' => 'false'];

        if ($filter === 'movie') return self::listing('search/movie', $params, 'movie', 600);
        if ($filter === 'tv')    return self::listing('search/tv', $params, 'tv', 600);
        if ($filter === 'anime') {
            // Anime is TV or film. Search TV and movies, keep only items that match the anime rule.
            $tv    = self::listing('search/tv', $params, 'tv', 600);
            $movie = self::listing('search/movie', $params, 'movie', 600);
            $items = array_values(array_filter(
                array_merge($tv['items'], $movie['items']),
                fn($i) => $i['is_anime']
            ));
            usort($items, fn($a, $b) => $b['popularity'] <=> $a['popularity']);
            return [
                'items'         => $items,
                'page'          => $page,
                'total_pages'   => max($tv['total_pages'], $movie['total_pages']),
                'total_results' => count($items),
            ];
        }
        return self::listing('search/multi', $params, null, 600);
    }

    // ------------------------------------------------------------------
    // Discover (real filters sent to TMDB)
    // ------------------------------------------------------------------
    /**
     * @param array{type?:string,genre?:int,year?:int,country?:string,language?:string,min_rating?:float,sort?:string,page?:int,anime?:bool} $f
     */
    public static function discover(array $f): array {
        $type = ($f['type'] ?? 'movie') === 'tv' ? 'tv' : 'movie';
        $anime = !empty($f['anime']);
        $page = max(1, min(500, (int)($f['page'] ?? 1)));

        $sortMap = [
            'popularity.desc'   => 'popularity.desc',
            'popularity.asc'    => 'popularity.asc',
            'rating.desc'       => 'vote_average.desc',
            'rating.asc'        => 'vote_average.asc',
            'latest'            => $type === 'tv' ? 'first_air_date.desc' : 'primary_release_date.desc',
            'oldest'            => $type === 'tv' ? 'first_air_date.asc' : 'primary_release_date.asc',
        ];
        $sortKey = (string)($f['sort'] ?? 'popularity.desc');
        $sort = $sortMap[$sortKey] ?? 'popularity.desc';

        $p = [
            'page'          => $page,
            'sort_by'       => $sort,
            'include_adult' => 'false',
        ];

        // Ratings are only meaningful with enough votes (avoids 10/10 titles with 1 vote).
        $minRating = (float)($f['min_rating'] ?? 0);
        if ($minRating > 0) {
            $p['vote_average.gte'] = $minRating;
        }
        if ($sort === 'vote_average.desc' || $sort === 'vote_average.asc' || $minRating > 0) {
            $p['vote_count.gte'] = 200;
        }
        if ($sortKey === 'latest') {
            // "Latest releases" must not list unreleased titles
            $today = date('Y-m-d');
            if ($type === 'tv') { $p['first_air_date.lte'] = $today; $p['vote_count.gte'] = $p['vote_count.gte'] ?? 5; }
            else { $p['primary_release_date.lte'] = $today; $p['vote_count.gte'] = $p['vote_count.gte'] ?? 5; }
        }

        $genre = (int)($f['genre'] ?? 0);
        $genres = $genre > 0 ? [$genre] : [];
        if ($anime) {
            $genres[] = self::GENRE_ANIMATION;
            $p['with_original_language'] = 'ja';
            if ($type === 'tv') { $p['with_origin_country'] = 'JP'; }
        }
        if ($genres) {
            $p['with_genres'] = implode(',', array_unique($genres)); // comma = AND
        }

        $year = (int)($f['year'] ?? 0);
        if ($year >= 1900 && $year <= (int)date('Y') + 2) {
            if ($type === 'tv') $p['first_air_date_year'] = $year;
            else $p['primary_release_year'] = $year;
        }

        $country = strtoupper(trim((string)($f['country'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $country) && !($anime && $type === 'tv')) {
            $p['with_origin_country'] = $country;
        }

        $lang = strtolower(trim((string)($f['language'] ?? '')));
        if (preg_match('/^[a-z]{2,3}$/', $lang) && !$anime) {
            $p['with_original_language'] = $lang;
        }

        $res = self::listing("discover/{$type}", $p, $type, 1800);
        if ($anime) {
            foreach ($res['items'] as &$it) { $it['is_anime'] = true; }
            unset($it);
        }
        return $res;
    }

    // ------------------------------------------------------------------
    // Details
    // ------------------------------------------------------------------
    /**
     * Full details incl. videos, credits, recommendations and similar in ONE request.
     * @return array<string,mixed>
     */
    public static function details(string $type, int $id): array {
        $type = $type === 'tv' ? 'tv' : 'movie';
        if ($id <= 0) throw new TmdbException('Invalid title.', 404);

        $raw = self::request("{$type}/{$id}", [
            'append_to_response' => 'videos,credits,recommendations,similar,external_ids',
            'include_video_language' => 'en,null',
        ], 3600);

        $base = self::normalize($raw, $type);

        $runtime = null;
        if ($type === 'movie') {
            $runtime = isset($raw['runtime']) ? (int)$raw['runtime'] : null;
        } else {
            $rt = (array)($raw['episode_run_time'] ?? []);
            $runtime = $rt ? (int)$rt[0] : null;
        }

        $cast = [];
        foreach (array_slice((array)($raw['credits']['cast'] ?? []), 0, 12) as $c) {
            $cast[] = [
                'name'         => (string)($c['name'] ?? ''),
                'character'    => (string)($c['character'] ?? ''),
                'profile_path' => $c['profile_path'] ?? null,
            ];
        }
        $directors = [];
        foreach ((array)($raw['credits']['crew'] ?? []) as $c) {
            if (($c['job'] ?? '') === 'Director') $directors[] = (string)$c['name'];
        }
        if ($type === 'tv') {
            foreach ((array)($raw['created_by'] ?? []) as $c) $directors[] = (string)($c['name'] ?? '');
        }

        $recs = [];
        foreach (array_slice((array)($raw['recommendations']['results'] ?? []), 0, 12) as $r) {
            if (!empty($r['id'])) $recs[] = self::normalize($r, $type);
        }
        $similar = [];
        foreach (array_slice((array)($raw['similar']['results'] ?? []), 0, 12) as $r) {
            if (!empty($r['id'])) $similar[] = self::normalize($r, $type);
        }

        return $base + [
            'tagline'        => (string)($raw['tagline'] ?? ''),
            'genres'         => array_map(fn($g) => (string)($g['name'] ?? ''), (array)($raw['genres'] ?? [])),
            'runtime'        => $runtime,
            'status'         => (string)($raw['status'] ?? ''),
            'seasons'        => $type === 'tv' ? (int)($raw['number_of_seasons'] ?? 0) : null,
            'episodes'       => $type === 'tv' ? (int)($raw['number_of_episodes'] ?? 0) : null,
            'countries'      => array_map(fn($c) => (string)($c['name'] ?? ''), (array)($raw['production_countries'] ?? [])),
            'homepage'       => (string)($raw['homepage'] ?? ''),
            'cast'           => $cast,
            'directors'      => array_values(array_unique(array_filter($directors))),
            'trailer'        => self::pickTrailer((array)($raw['videos']['results'] ?? [])),
            'recommendations'=> $recs,
            'similar'        => $similar,
        ];
    }

    /**
     * Pick an official YouTube trailer from TMDB's own video list. Never invents a video.
     * @return array{key:string,name:string}|null
     */
    public static function pickTrailer(array $videos): ?array {
        $best = null;
        $bestScore = -1;
        foreach ($videos as $v) {
            if (($v['site'] ?? '') !== 'YouTube' || empty($v['key'])) continue;
            if (!preg_match('/^[A-Za-z0-9_\-]{6,20}$/', (string)$v['key'])) continue;
            $score = 0;
            if (($v['type'] ?? '') === 'Trailer') $score += 4;
            elseif (($v['type'] ?? '') === 'Teaser') $score += 2;
            else continue;
            if (!empty($v['official'])) $score += 2;
            if (($v['iso_639_1'] ?? '') === 'en') $score += 1;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['key' => (string)$v['key'], 'name' => (string)($v['name'] ?? 'Trailer')];
            }
        }
        return $best;
    }

    /** Lightweight trailer lookup used by the Discover hero. */
    public static function trailerFor(string $type, int $id): ?array {
        $type = $type === 'tv' ? 'tv' : 'movie';
        try {
            $data = self::request("{$type}/{$id}/videos", ['include_video_language' => 'en,null'], 86400);
        } catch (TmdbException $e) {
            return null;
        }
        return self::pickTrailer((array)($data['results'] ?? []));
    }

    // ------------------------------------------------------------------
    // Reference data for filters
    // ------------------------------------------------------------------
    /** @return array<string,string> ISO code => name (TMDB countries) */
    public static function countries(): array {
        try {
            $data = self::request('configuration/countries', [], 604800);
        } catch (TmdbException $e) {
            return [];
        }
        $out = [];
        foreach ($data as $c) {
            if (!empty($c['iso_3166_1']) && !empty($c['english_name'])) {
                $out[(string)$c['iso_3166_1']] = (string)$c['english_name'];
            }
        }
        asort($out);
        return $out;
    }

    /** @return array<string,string> ISO 639-1 code => English name */
    public static function languages(): array {
        try {
            $data = self::request('configuration/languages', [], 604800);
        } catch (TmdbException $e) {
            return [];
        }
        $out = [];
        foreach ($data as $l) {
            if (!empty($l['iso_639_1']) && !empty($l['english_name']) && $l['iso_639_1'] !== 'xx') {
                $out[(string)$l['iso_639_1']] = (string)$l['english_name'];
            }
        }
        asort($out);
        return $out;
    }

    /**
     * Browser-safe card data (no API key anywhere in it): image URLs are public TMDB CDN URLs.
     * @return array<string,mixed>
     */
    public static function toCard(array $item, array $genreMap = []): array {
        $label = $item['is_anime'] ? 'Anime' : ($item['media_type'] === 'tv' ? 'TV' : 'Movie');
        return [
            'id'         => $item['id'],
            'media_type' => $item['media_type'],
            'label'      => $label,
            'title'      => $item['title'],
            'year'       => $item['year'],
            'rating'     => $item['rating'],
            'genre'      => self::genreLabel($item, $genreMap),
            'poster'     => self::image($item['poster_path'], 'poster', 'w342'),
            'poster_srcset' => self::posterSrcset($item['poster_path']),
            'poster_path'=> $item['poster_path'],
            'url'        => self::url($item),
        ];
    }

    /** Link used by the front end for a title. */
    public static function url(array $item): string {
        return 'title.php?type=' . ($item['media_type'] === 'tv' ? 'tv' : 'movie') . '&id=' . (int)$item['id'];
    }
}
