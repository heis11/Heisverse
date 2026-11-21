<?php
require_once __DIR__ . '/../includes/database.php';

$db = Database::getConnection();

if (!$db) {
    exit("ERROR: Database connection failed.\n");
}

$db->exec("
    CREATE TABLE IF NOT EXISTS seasons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        movie_id INT NOT NULL,
        season_number INT NOT NULL DEFAULT 1,
        title VARCHAR(120) NOT NULL,
        overview TEXT,
        poster_url VARCHAR(500) DEFAULT '',
        air_date VARCHAR(30) DEFAULT '',
        CONSTRAINT fk_seasons_movie
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
        INDEX idx_seasons_movie (movie_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "CHECKED seasons\n";

$db->exec("
    CREATE TABLE IF NOT EXISTS episodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        season_id INT NOT NULL,
        movie_id INT NOT NULL,
        episode_number INT NOT NULL DEFAULT 1,
        title VARCHAR(150) NOT NULL,
        overview TEXT,
        runtime VARCHAR(20) DEFAULT '24m',
        still_url VARCHAR(500) DEFAULT '',
        source_url VARCHAR(1000) NOT NULL DEFAULT '',
        quality VARCHAR(20) NOT NULL DEFAULT 'HD 1080p',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_episodes_season
            FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
        CONSTRAINT fk_episodes_movie
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
        INDEX idx_episodes_season (season_id),
        INDEX idx_episodes_movie (movie_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "CHECKED episodes\n";

$db->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_password_resets_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_reset_token (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "CHECKED password_resets\n";

echo "REQUIRED TABLE REPAIR COMPLETED\n";