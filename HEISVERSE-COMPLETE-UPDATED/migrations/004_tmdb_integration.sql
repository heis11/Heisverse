-- =====================================================================
-- HEISVERSE — Migration 004: TMDB watchlist (SAFE: only adds, never drops)
-- Works on XAMPP MariaDB and MySQL 8.
-- How to run: phpMyAdmin -> select database `heisverse` -> SQL tab -> paste -> Go
-- It is safe to run more than once.
-- =====================================================================

CREATE TABLE IF NOT EXISTS tmdb_watchlist (
  user_id     INT NOT NULL,
  tmdb_id     INT NOT NULL,
  media_type  ENUM('movie','tv') NOT NULL DEFAULT 'movie',
  title       VARCHAR(200) NOT NULL DEFAULT '',
  poster_path VARCHAR(120) NOT NULL DEFAULT '',
  year        VARCHAR(4)   NOT NULL DEFAULT '',
  added_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, tmdb_id, media_type),
  INDEX idx_tmdb_wl_user (user_id, added_at),
  CONSTRAINT fk_tmdb_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
