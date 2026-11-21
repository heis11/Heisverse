<?php
require_once __DIR__ . '/../includes/database.php';

$db = Database::getConnection();
if (!$db) {
    exit("ERROR: Database connection failed.\n");
}

$columns = [
    'content_type'      => "VARCHAR(30) NOT NULL DEFAULT 'movies'",
    'country'           => "VARCHAR(60) NOT NULL DEFAULT ''",
    'original_language' => "VARCHAR(30) NOT NULL DEFAULT ''",
    'seasons_count'     => "INT NOT NULL DEFAULT 1",
    'episodes_count'    => "INT NOT NULL DEFAULT 1",
    'is_downloadable'   => "TINYINT(1) NOT NULL DEFAULT 0"
];

foreach ($columns as $name => $definition) {
    $check = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'movies' AND COLUMN_NAME = ?"
    );
    $check->execute([$name]);

    if ((int)$check->fetchColumn() === 0) {
        $db->exec("ALTER TABLE movies ADD COLUMN `$name` $definition");
        echo "ADDED movies.$name\n";
    } else {
        echo "EXISTS movies.$name\n";
    }
}

$db->exec("
    CREATE TABLE IF NOT EXISTS tmdb_watchlist (
        user_id INT NOT NULL,
        tmdb_id INT NOT NULL,
        media_type ENUM('movie','tv') NOT NULL DEFAULT 'movie',
        title VARCHAR(200) NOT NULL DEFAULT '',
        poster_path VARCHAR(120) NOT NULL DEFAULT '',
        year VARCHAR(4) NOT NULL DEFAULT '',
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, tmdb_id, media_type),
        INDEX idx_tmdb_wl_user (user_id, added_at),
        CONSTRAINT fk_tmdb_wl_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "CHECKED tmdb_watchlist\n";
echo "SAFE SCHEMA REPAIR COMPLETED\n";
