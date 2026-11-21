<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();

$db = Database::getConnection();

if (!$db) {
    http_response_code(503);
    exit('Database connection unavailable.');
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
 * Separate review table. This does not modify existing movie records.
 */
$db->exec("
CREATE TABLE IF NOT EXISTS free_movie_candidates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(30) NOT NULL DEFAULT 'archive_org',
    identifier VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    release_year VARCHAR(20) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    details_url VARCHAR(500) NOT NULL,
    poster_url VARCHAR(500) NOT NULL,
    license_url VARCHAR(1000) DEFAULT NULL,
    rights_text TEXT DEFAULT NULL,
    video_files_json LONGTEXT DEFAULT NULL,
    review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_archive_identifier (provider, identifier),
    KEY idx_review_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

function hvArchiveGet(string $url): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'HEISVERSE-MovieDiscovery/1.0'
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status !== 200) {
        return null;
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : null;
}

function hvEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$message = '';
$error = '';
$searchTerm = trim((string)($_GET['q'] ?? ''));
$results = [];

/*
 * Save candidate after re-fetching its metadata from Archive.org.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Refresh the page and try again.';
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9._-]{1,255}$/', $identifier)) {
            $error = 'Invalid Archive.org identifier.';
        } else {
            $metadata = hvArchiveGet(
                'https://archive.org/metadata/' . rawurlencode($identifier)
            );

            if (!$metadata || empty($metadata['metadata'])) {
                $error = 'Could not retrieve this item metadata.';
            } else {
                $m = $metadata['metadata'];

                $title = trim((string)($m['title'] ?? $identifier));
                $description = $m['description'] ?? '';
                if (is_array($description)) {
                    $description = implode("\n", $description);
                }

                $year = $m['year'] ?? null;
                if (is_array($year)) {
                    $year = $year[0] ?? null;
                }

                $license = $m['licenseurl'] ?? null;
                $rights = $m['rights'] ?? null;

                $videoFiles = [];

                foreach (($metadata['files'] ?? []) as $file) {
                    $name = (string)($file['name'] ?? '');

                    if (preg_match('/\.(mp4|webm)$/i', $name)) {
                        $videoFiles[] = [
                            'name' => $name,
                            'format' => (string)($file['format'] ?? ''),
                            'size' => (string)($file['size'] ?? '')
                        ];
                    }
                }

                $stmt = $db->prepare("
                    INSERT INTO free_movie_candidates
                    (
                        provider, identifier, title, release_year,
                        description, details_url, poster_url,
                        license_url, rights_text, video_files_json
                    )
                    VALUES
                    (
                        'archive_org', :identifier, :title, :year,
                        :description, :details, :poster,
                        :license, :rights, :files
                    )
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        release_year = VALUES(release_year),
                        description = VALUES(description),
                        license_url = VALUES(license_url),
                        rights_text = VALUES(rights_text),
                        video_files_json = VALUES(video_files_json)
                ");

                $stmt->execute([
                    ':identifier' => $identifier,
                    ':title' => substr($title, 0, 255),
                    ':year' => $year !== null ? substr((string)$year, 0, 20) : null,
                    ':description' => substr((string)$description, 0, 20000),
                    ':details' => 'https://archive.org/details/' . rawurlencode($identifier),
                    ':poster' => 'https://archive.org/services/img/' . rawurlencode($identifier),
                    ':license' => is_string($license) ? $license : null,
                    ':rights' => is_string($rights) ? $rights : null,
                    ':files' => json_encode($videoFiles, JSON_UNESCAPED_SLASHES)
                ]);

                $message = 'Candidate saved for review. Existing movie records were not changed.';
            }
        }
    }
}

/*
 * Search only the feature_films collection.
 */
if ($searchTerm !== '') {
    $safeTerm = preg_replace('/[^a-zA-Z0-9 \'-]/', '', $searchTerm);
    $safeTerm = trim((string)$safeTerm);

    if (strlen($safeTerm) < 2) {
        $error = 'Enter at least two letters or numbers.';
    } else {
        $query = 'mediatype:movies AND collection:feature_films AND title:"'
            . $safeTerm . '"';

        $url = 'https://archive.org/advancedsearch.php?' . http_build_query([
            'q' => $query,
            'fl[]' => ['identifier', 'title', 'year', 'description'],
            'rows' => 12,
            'page' => 1,
            'output' => 'json'
        ]);

        $data = hvArchiveGet($url);

        if (!$data) {
            $error = 'Archive.org search failed. Please try again later.';
        } else {
            $results = $data['response']['docs'] ?? [];
        }
    }
}

$pending = $db->query("
    SELECT * FROM free_movie_candidates
    WHERE review_status = 'pending'
    ORDER BY created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Free Movie Discovery | HEISVERSE Admin</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#080b12;color:#edf1f8;font-family:Arial,sans-serif}
main{max-width:1250px;margin:auto;padding:28px 20px}
a{color:#8bbcff}
.top{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
h1{font-size:27px;margin:0}
.muted{color:#9ba8ba;font-size:13px;line-height:1.6}
.panel{background:#111722;border:1px solid #263144;border-radius:13px;padding:20px;margin-top:20px}
input{background:#080e19;border:1px solid #34445b;color:white;padding:13px;border-radius:8px;width:100%}
button{background:#2563eb;color:white;border:0;border-radius:8px;padding:12px 17px;cursor:pointer;font-weight:bold}
button:hover{background:#1d4ed8}
.search{display:flex;gap:10px;margin-top:14px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:15px;margin-top:18px}
.movie{background:#0b111d;border:1px solid #283448;border-radius:12px;overflow:hidden}
.poster{width:100%;height:300px;object-fit:cover;background:#182235}
.movie-body{padding:14px}
.movie h3{font-size:16px;margin:0 0 8px}
.small{font-size:12px;color:#a8b5c7;line-height:1.5;overflow-wrap:anywhere}
.movie form{margin-top:13px}
.notice{padding:13px;border-radius:8px;margin-top:15px;background:#172b24;color:#a7f3d0}
.error{background:#351c22;color:#fecaca}
table{width:100%;border-collapse:collapse;margin-top:14px}
td,th{text-align:left;padding:12px;border-bottom:1px solid #293548;font-size:13px;vertical-align:top}
.status{color:#facc15;font-weight:bold}
@media(max-width:600px){.search{flex-direction:column}.poster{height:250px}main{padding:18px 12px}}
</style>
</head>
<body>
<main>
    <div class="top">
        <div>
            <h1>Free Movie Discovery</h1>
            <p class="muted">HEISVERSE Admin / Internet Archive search</p>
        </div>
        <a href="index.php">Back to Admin Dashboard</a>
    </div>

    <div class="panel">
        <h2>Search feature-film collection</h2>
        <p class="muted">
            Search results are candidates, not verified free movies.
            Every item must pass a rights review before publication.
        </p>

        <form method="get" class="search">
            <input name="q" value="<?= hvEscape($searchTerm) ?>"
                   placeholder="Enter a movie title..." required>
            <button type="submit">Search Archive.org</button>
        </form>

        <?php if ($message): ?>
            <div class="notice"><?= hvEscape($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notice error"><?= hvEscape($error) ?></div>
        <?php endif; ?>

        <?php if ($searchTerm !== '' && !$error): ?>
            <h3>Search results (<?= count($results) ?> shown)</h3>
            <div class="grid">
                <?php foreach ($results as $film): ?>
                    <?php
                    $id = (string)($film['identifier'] ?? '');
                    $title = (string)($film['title'] ?? $id);
                    $description = $film['description'] ?? '';
                    if (is_array($description)) $description = implode(' ', $description);
                    ?>
                    <article class="movie">
                        <img class="poster"
                             src="https://archive.org/services/img/<?= rawurlencode($id) ?>"
                             alt="Poster for <?= hvEscape($title) ?>"
                             loading="lazy">
                        <div class="movie-body">
                            <h3><?= hvEscape($title) ?></h3>
                            <p class="small">
                                Year: <?= hvEscape($film['year'] ?? 'Not listed') ?>
                            </p>
                            <p class="small">
                                <?= hvEscape(substr(strip_tags((string)$description), 0, 220)) ?>
                            </p>
                            <p class="small">
                                <a target="_blank" rel="noopener"
                                   href="https://archive.org/details/<?= rawurlencode($id) ?>">
                                    View original Archive.org item
                                </a>
                            </p>
                            <form method="post">
                                <input type="hidden" name="csrf_token"
                                       value="<?= hvEscape($csrf) ?>">
                                <input type="hidden" name="identifier"
                                       value="<?= hvEscape($id) ?>">
                                <button type="submit">Save for Rights Review</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Pending review (<?= count($pending) ?>)</h2>
        <p class="muted">
            These candidates have not been published. Missing licence information
            must be investigated before any playback source is approved.
        </p>

        <?php if (!$pending): ?>
            <p class="muted">No candidates saved yet.</p>
        <?php else: ?>
            <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Rights information</th>
                        <th>Video files</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $item): ?>
                    <?php $files = json_decode((string)$item['video_files_json'], true) ?: []; ?>
                    <tr>
                        <td>
                            <strong><?= hvEscape($item['title']) ?></strong>
                            <p class="small"><?= hvEscape($item['release_year'] ?? 'Year unknown') ?></p>
                            <a target="_blank" rel="noopener"
                               href="<?= hvEscape($item['details_url']) ?>">Open item</a>
                        </td>
                        <td class="small">
                            Licence:
                            <?= $item['license_url']
                                ? '<a target="_blank" rel="noopener" href="' . hvEscape($item['license_url']) . '">View licence</a>'
                                : 'Not provided' ?>
                            <br>
                            Rights: <?= hvEscape($item['rights_text'] ?: 'Not provided') ?>
                        </td>
                        <td class="small">
                            <?php if (!$files): ?>
                                No MP4/WebM detected
                            <?php else: ?>
                                <?php foreach ($files as $file): ?>
                                    <?= hvEscape($file['name'] ?? '') ?><br>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="status">PENDING</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
