<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/tmdb.php';

$db = Database::getConnection();

if (!$db || !Tmdb::isConfigured()) {
    exit("ERROR: Database or TMDB is not configured.\n");
}

$doImport = in_array('--import', $argv ?? [], true);
$limitPerCategory = 2;

function getItems(array $result): array {
    return $result['items'] ?? [];
}

function chooseItems(array $items, int $limit, callable $filter): array {
    $chosen = [];
    $seen = [];

    foreach ($items as $item) {
        if (empty($item['id']) || empty($item['poster_path'])) {
            continue;
        }

        if (!$filter($item)) {
            continue;
        }

        $id = (int)$item['id'];

        if (isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $chosen[] = $item;

        if (count($chosen) >= $limit) {
            break;
        }
    }

    return $chosen;
}

function runtimeLabel(?int $minutes): string {
    if (!$minutes || $minutes < 1) {
        return 'Unknown';
    }

    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    if ($hours > 0 && $remaining > 0) {
        return $hours . 'h ' . $remaining . 'm';
    }

    if ($hours > 0) {
        return $hours . 'h';
    }

    return $remaining . 'm';
}

$categories = [];

try {
    $popularMovies = getItems(Tmdb::popularMovies(1));
    $popularTv = getItems(Tmdb::popularTv(1));

    $animeMovies = getItems(Tmdb::discover([
        'type' => 'movie',
        'anime' => true,
        'sort' => 'popularity.desc'
    ]));

    $animeTv = getItems(Tmdb::discover([
        'type' => 'tv',
        'anime' => true,
        'sort' => 'popularity.desc'
    ]));

    $animationMovies = getItems(Tmdb::discover([
        'type' => 'movie',
        'genre' => 16,
        'sort' => 'popularity.desc'
    ]));

    $animationTv = getItems(Tmdb::discover([
        'type' => 'tv',
        'genre' => 16,
        'sort' => 'popularity.desc'
    ]));

    $categories = [
        'movies' => [
            'type' => 'movie',
            'items' => chooseItems(
                $popularMovies,
                $limitPerCategory,
                fn($item) => !in_array(16, $item['genre_ids'] ?? [], true)
            )
        ],

        'tv_series' => [
            'type' => 'tv',
            'items' => chooseItems(
                $popularTv,
                $limitPerCategory,
                fn($item) => !in_array(16, $item['genre_ids'] ?? [], true)
            )
        ],

        'anime_movies' => [
            'type' => 'movie',
            'items' => chooseItems(
                $animeMovies,
                $limitPerCategory,
                fn($item) => true
            )
        ],

        'anime_series' => [
            'type' => 'tv',
            'items' => chooseItems(
                $animeTv,
                $limitPerCategory,
                fn($item) => true
            )
        ],

        'animated_movies' => [
            'type' => 'movie',
            'items' => chooseItems(
                $animationMovies,
                $limitPerCategory,
                fn($item) => ($item['language'] ?? '') !== 'ja'
            )
        ],

        'cartoons' => [
            'type' => 'tv',
            'items' => chooseItems(
                $animationTv,
                $limitPerCategory,
                fn($item) => ($item['language'] ?? '') !== 'ja'
            )
        ],
    ];

} catch (Throwable $e) {
    exit("TMDB listing error: " . $e->getMessage() . "\n");
}

echo "\nHEISVERSE TMDB IMPORTER\n";
echo $doImport ? "MODE: IMPORT\n" : "MODE: PREVIEW ONLY\n";
echo "-----------------------------\n";

$totalInserted = 0;
$totalSkipped = 0;
$totalErrors = 0;

$check = $db->prepare(
    'SELECT id FROM movies
     WHERE tmdb_id = :tmdb_id
     LIMIT 1'
);

$insert = $db->prepare(
    'INSERT INTO movies (
        title, category, released, runtime, bg, icon,
        poster_url, backdrop_url, about, director, cast_list,
        tmdb_id, rating_avg, ratings_count, is_featured,
        is_published, content_type, country, original_language,
        seasons_count, episodes_count, is_downloadable
    ) VALUES (
        :title, :category, :released, :runtime, :bg, :icon,
        :poster_url, :backdrop_url, :about, :director, :cast_list,
        :tmdb_id, :rating_avg, :ratings_count, 0,
        1, :content_type, :country, :original_language,
        :seasons_count, :episodes_count, 0
    )'
);

foreach ($categories as $contentType => $group) {
    echo "\n[" . strtoupper($contentType) . "]\n";

    foreach ($group['items'] as $item) {
        $tmdbId = (int)$item['id'];

        try {
            $check->execute([
                ':tmdb_id' => $tmdbId
            ]);

            if ($check->fetchColumn()) {
                echo "SKIP: {$item['title']} (already exists)\n";
                $totalSkipped++;
                continue;
            }

            $details = Tmdb::details($group['type'], $tmdbId);

            $title = trim((string)($details['title'] ?? ''));
            $poster = Tmdb::image($details['poster_path'] ?? null, 'poster', 'w342');

            if ($title === '' || !$poster) {
                echo "SKIP: Missing title or poster for TMDB ID {$tmdbId}\n";
                $totalSkipped++;
                continue;
            }

            $year = (int)($details['year'] ?? 0);

            if ($year < 1800 || $year > 2200) {
                echo "SKIP: Missing valid release year for {$title}\n";
                $totalSkipped++;
                continue;
            }

            $genres = $details['genres'] ?? [];
            $category = $genres[0] ?? 'Other';

            $country = $details['countries'][0] ?? '';
            $language = (string)($details['language'] ?? '');

            $castNames = array_map(
                fn($person) => (string)($person['name'] ?? ''),
                $details['cast'] ?? []
            );

            $directors = $details['directors'] ?? [];

            if ($group['type'] === 'tv' && $language === 'ko'
                && $contentType === 'tv_series') {
                $contentTypeForInsert = 'kdrama';
            } else {
                $contentTypeForInsert = $contentType;
            }

            $runtime = $group['type'] === 'movie'
                ? runtimeLabel(isset($details['runtime']) ? (int)$details['runtime'] : null)
                : runtimeLabel(isset($details['runtime']) ? (int)$details['runtime'] : null);

            if ($group['type'] === 'tv' && !empty($details['seasons'])) {
                $runtime = (int)$details['seasons'] . ' Seasons';
            }

            $values = [
                ':title' => mb_substr($title, 0, 150),
                ':category' => mb_substr((string)$category, 0, 50),
                ':released' => $year,
                ':runtime' => mb_substr($runtime, 0, 20),
                ':bg' => 'linear-gradient(135deg,#101827,#26364d)',
                ':icon' => 'ðŸŽ¬',
                ':poster_url' => $poster,
                ':backdrop_url' => Tmdb::image(
                    $details['backdrop_path'] ?? null,
                    'backdrop',
                    'w1280'
                ) ?? '',
                ':about' => (string)($details['overview'] ?? ''),
                ':director' => mb_substr(implode(', ', $directors), 0, 100),
                ':cast_list' => mb_substr(implode(', ', array_filter($castNames)), 0, 255),
                ':tmdb_id' => $tmdbId,
                ':rating_avg' => min(10, max(0, (float)($details['rating'] ?? 0))),
                ':ratings_count' => max(0, (int)($details['votes'] ?? 0)),
                ':content_type' => $contentTypeForInsert,
                ':country' => mb_substr((string)$country, 0, 60),
                ':original_language' => mb_substr($language, 0, 30),
                ':seasons_count' => max(0, (int)($details['seasons'] ?? 0)),
                ':episodes_count' => max(0, (int)($details['episodes'] ?? 0)),
            ];

            echo "FOUND: {$title} ({$year})";

            if (!$doImport) {
                echo " â€” preview only\n";
                continue;
            }

            $insert->execute($values);

            echo " â€” INSERTED, database ID " . $db->lastInsertId() . "\n";
            $totalInserted++;

        } catch (Throwable $e) {
            echo "ERROR: TMDB ID {$tmdbId}: " . $e->getMessage() . "\n";
            $totalErrors++;
        }
    }
}

echo "\n-----------------------------\n";
echo "IMPORT SUMMARY\n";
echo "Inserted: {$totalInserted}\n";
echo "Skipped: {$totalSkipped}\n";
echo "Errors: {$totalErrors}\n";

if (!$doImport) {
    echo "\nThis was only a preview. No movie records were inserted.\n";
    echo "Review the titles, then run with --import to save them.\n";
}
