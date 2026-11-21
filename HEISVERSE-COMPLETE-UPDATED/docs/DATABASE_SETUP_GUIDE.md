# MySQL 8 Database Architecture & Migration Guide

This document explains the database structure, initialization, and safe data migration processes in HEISVERSE.

---

## 1. Automatic Database Initialization

When the MySQL container runs for the very first time:
- It automatically mounts `./database.sql` into `/docker-entrypoint-initdb.d/init.sql`.
- MySQL 8 automatically executes `database.sql`, creating the database `heisverse`, setting up character set `utf8mb4`, creating all 18 tables, and seeding the 8 baseline movies with authorized streaming links.
- The data is stored in the persistent Docker volume named `heisverse_db_data`.

---

## 2. Table Summary

| Table | Purpose |
| :--- | :--- |
| `users` | Accounts, passwords, profile data, roles, language & theme preferences |
| `roles` | Role-based authorization (`admin`, `user`) |
| `movies` | Film catalog, categories, release years, runtimes, descriptions, ratings |
| `movie_sources` | Multi-source playback (YouTube trailers, Internet Archive, direct MP4) |
| `watchlists` | User-saved movies for future viewing |
| `watch_history` | Timestamp tracking for "Resume Watching" and "Recently Watched" |
| `reviews` | User star ratings (1-5) and written critiques |
| `comments` | Threaded discussions and replies on movie pages |
| `movie_likes` | User favorites and like tallies |
| `playlists` | Curated collections of YouTube music and video tracks |
| `playlist_items` | Individual videos stored in playlists |
| `follows` | Social following relationships between users |
| `notifications` | In-app alerts for likes, replies, and system updates |
| `messages` | Direct 1-on-1 private user communication |
| `reports` | Moderation flags for inappropriate comments or profiles |
| `admin_activity_logs` | Audit trail recording administrator actions |
| `system_settings` | Dynamic platform settings (site title, API switches) |

---

## 3. Running Safe Migrations

To apply incremental schema changes without affecting existing data:
```powershell
docker compose exec -T db mysql -u root -pheisverse_root_pass_2026 heisverse < migrations/001_create_initial_schema.sql
docker compose exec -T db mysql -u root -pheisverse_root_pass_2026 heisverse < migrations/002_add_social_and_playlists.sql
```
All migration scripts use `IF NOT EXISTS` so they can be safely executed multiple times without errors.
