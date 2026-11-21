-- =====================================================================
-- HEISVERSE — Migration 003: Global Entertainment, Seasons & Contact
-- =====================================================================

USE heisverse;

-- 1. Enhance movies table for global content types
ALTER TABLE movies 
ADD COLUMN IF NOT EXISTS content_type VARCHAR(30) NOT NULL DEFAULT 'movies',
ADD COLUMN IF NOT EXISTS country VARCHAR(60) NOT NULL DEFAULT 'United States',
ADD COLUMN IF NOT EXISTS original_language VARCHAR(30) NOT NULL DEFAULT 'English',
ADD COLUMN IF NOT EXISTS seasons_count INT NOT NULL DEFAULT 1,
ADD COLUMN IF NOT EXISTS episodes_count INT NOT NULL DEFAULT 1,
ADD COLUMN IF NOT EXISTS is_downloadable TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Create Seasons Table for TV Shows, Anime Series, K-Dramas
CREATE TABLE IF NOT EXISTS seasons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  movie_id INT NOT NULL,
  season_number INT NOT NULL DEFAULT 1,
  title VARCHAR(120) NOT NULL,
  overview TEXT,
  poster_url VARCHAR(500) DEFAULT '',
  air_date VARCHAR(30) DEFAULT '',
  FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
  INDEX idx_seasons_movie (movie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Episodes Table
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
  FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
  FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
  INDEX idx_episodes_season (season_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Password Resets Table
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reset_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Default Contact & Social Information
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('contact_email', 'contact@heisverse.com'),
('support_email', 'support@heisverse.com'),
('phone_number', '+250 788 123 456'),
('whatsapp_number', '+250 788 123 456'),
('country', 'Rwanda'),
('physical_address', 'Kigali Innovation City, KG 7 Ave, Kigali, Rwanda'),
('website_url', 'http://localhost:8080'),
('social_facebook', 'https://facebook.com/heisverse'),
('social_instagram', 'https://instagram.com/heisverse'),
('social_tiktok', 'https://tiktok.com/@heisverse'),
('social_youtube', 'https://youtube.com/@heisverse'),
('social_telegram', 'https://t.me/heisverse'),
('social_x', 'https://x.com/heisverse'),
('business_description', 'HEISVERSE is a premier worldwide cinematic universe and media discovery platform delivering authorized film streaming, anime, TV series, cartoons, and music lounges.'),
('smtp_host', 'smtp.example.com'),
('smtp_port', '587'),
('smtp_user', 'notify@heisverse.com'),
('smtp_pass', ''),
('smtp_encryption', 'tls');

-- 6. Seed Global Content: Anime, TV Series, Cartoons, and K-Drama
INSERT IGNORE INTO movies (id, title, category, content_type, country, original_language, released, runtime, bg, icon, about, is_featured, is_published, is_downloadable) VALUES
(101, 'Attack on Titan', 'Action', 'anime_series', 'Japan', 'Japanese', 2023, '4 Seasons', 'linear-gradient(135deg,#1f1c2c,#928dab)', '⚔️', 'Humanity fights for survival against gigantic humanoids known as Titans within massive walled cities.', 1, 1, 1),
(102, 'Spirited Away', 'Fantasy', 'anime_movies', 'Japan', 'Japanese', 2001, '2h 5m', 'linear-gradient(135deg,#11998e,#38ef7d)', '🐉', 'A young girl enters the world of spirits and must work to free herself and her parents.', 0, 1, 0),
(103, 'Demon Slayer: Mugen Train', 'Animation', 'anime_movies', 'Japan', 'Japanese', 2020, '1h 57m', 'linear-gradient(135deg,#eb3349,#f45c43)', '🔥', 'Tanjiro and the Demon Slayer Corps board the mysterious Infinity Train on a deadly demon hunting mission.', 0, 1, 0),
(104, 'Stranger Things', 'Sci-Fi', 'tv_series', 'United States', 'English', 2024, '4 Seasons', 'linear-gradient(135deg,#000000,#e50914)', '🚲', 'When a young boy vanishes, a small town uncovers a mystery involving secret experiments and terrifying supernatural forces.', 1, 1, 1),
(105, 'Squid Game', 'Thriller', 'kdrama', 'South Korea', 'Korean', 2024, '2 Seasons', 'linear-gradient(135deg,#ff007f,#00f2fe)', '🦑', 'Hundreds of cash-strapped players accept a strange invitation to compete in children games with deadly high stakes.', 1, 1, 0),
(106, 'Avatar: The Last Airbender', 'Adventure', 'cartoons', 'United States', 'English', 2008, '3 Seasons', 'linear-gradient(135deg,#2980b9,#6dd5fa)', '💨', 'In a war-torn world of elemental magic, a young boy reawakens to undertake a dangerous mystic quest.', 0, 1, 1),
(107, 'Spider-Man: Across the Spider-Verse', 'Animation', 'animated_movies', 'United States', 'English', 2023, '2h 20m', 'linear-gradient(135deg,#8e2de2,#4a00e0)', '🕷️', 'Miles Morales catapults across the Multiverse, where he encounters a team of Spider-People charged with protecting its existence.', 0, 1, 0),
(108, 'Planet Earth III', 'Documentary', 'documentaries', 'United Kingdom', 'English', 2023, '1 Season', 'linear-gradient(135deg,#134e5e,#71b280)', '🌍', 'Journey to the far reaches of our planet to discover extraordinary animal behaviors and landscapes.', 0, 1, 0);

-- Seed Seasons for Attack on Titan (101) & Stranger Things (104)
INSERT IGNORE INTO seasons (id, movie_id, season_number, title, overview) VALUES
(1, 101, 1, 'Season 1: The Fall of Shiganshina', 'Eren Yeager discovers his destiny as Titans breach Wall Maria.'),
(2, 101, 2, 'Season 2: Clash of the Titans', 'The Scout Regiment investigates deep betrayals within the military police.'),
(3, 104, 1, 'Season 1: The Upside Down', 'Will Byers goes missing, and a mysterious telekinetic girl arrives.');

-- Seed Episodes for Season 1 of Attack on Titan
INSERT IGNORE INTO episodes (id, season_id, movie_id, episode_number, title, runtime, source_url, quality) VALUES
(1, 1, 101, 1, 'To You, in 2000 Years', '24m', 'https://www.youtube.com/embed/MGRm4IzK1SQ', 'HD 1080p'),
(2, 1, 101, 2, 'That Day: The Fall of Shiganshina', '24m', 'https://www.youtube.com/embed/MGRm4IzK1SQ', 'HD 1080p');
