-- =====================================================================
-- HEISVERSE — Migration 004b: make sure the `movies` table has the columns
-- the Home / Anime / Series / Admin pages already query.
-- Needed only if you imported database.sql but never ran migration 003.
-- SAFE: uses information_schema checks, adds only what is missing, works on MariaDB and MySQL 8.
-- =====================================================================

DROP PROCEDURE IF EXISTS heisverse_add_col;
DELIMITER //
CREATE PROCEDURE heisverse_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END//
DELIMITER ;

CALL heisverse_add_col('movies', 'content_type', "VARCHAR(30) NOT NULL DEFAULT 'movies'");
CALL heisverse_add_col('movies', 'country', "VARCHAR(60) NOT NULL DEFAULT 'United States'");
CALL heisverse_add_col('movies', 'original_language', "VARCHAR(30) NOT NULL DEFAULT 'English'");
CALL heisverse_add_col('movies', 'seasons_count', "INT NOT NULL DEFAULT 1");
CALL heisverse_add_col('movies', 'episodes_count', "INT NOT NULL DEFAULT 1");
CALL heisverse_add_col('movies', 'is_downloadable', "TINYINT(1) NOT NULL DEFAULT 0");

DROP PROCEDURE IF EXISTS heisverse_add_col;
