# HEISVERSE — Movies, TV Series & Anime Discovery

PHP + MySQL/MariaDB platform. This version runs directly on **XAMPP (Windows)**; Docker is optional (the old Docker files are still included but not needed).

## What is new in this repair
- Real **TMDB** integration (server-side only, cached): trending, popular, top rated, now playing, details, trailers, search, genres, recommendations, similar titles, discover filters, pagination.
- **Discover** rebuilt with real TMDB backdrops and posters, working filters, sorting, pagination. Your original library filter view is kept on the "HEISVERSE Library" tab.
- New pages: `title.php` (details), `search.php` (global search). A search box is now in every page header.
- Mobile menu (the menu links used to disappear on phones).
- Safe migrations for the new TMDB watchlist.

---

## Installation in XAMPP (step by step)

### 1. Put the project in the right folder
Copy the project folder to:

    C:\xampp\htdocs\HEISVERSE-COMPLETE-UPDATED

If an older copy is already there, copy the new files **over** it. Keep your existing `.env` if you have one.

### 2. Start XAMPP
Open the **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**.

### 3. Fill in the private `.env` file
Open `.env` in Notepad (it is in the project folder; if you only see `.env.example`, copy it and rename the copy to `.env`).

- `DB_PASS=` → the password of your MySQL user `heisverse_user`.
- `TMDB_API_KEY=` → your TMDB key (already filled in if you used the file from this ZIP).

Never upload `.env` to GitHub or send it to anyone (`.gitignore` already blocks it).

### 4. Prepare the database (your data is never deleted)
Open <http://localhost/phpmyadmin>, click the **heisverse** database, open the **SQL** tab, then paste and run the files below **one at a time**. They only add things and are safe to run again.

| Situation | Run these files from the `migrations` folder |
|---|---|
| **Your database already works** | `004_tmdb_integration.sql` |
| Pages like Home/Anime show a "column not found" problem | `004b_ensure_global_columns.sql`, then `004_tmdb_integration.sql` |
| **Brand-new empty database** | `../database.sql`, then `003_global_library_and_contact.sql`, then `004_tmdb_integration.sql` |

### 5. Check everything with the built-in test
Open **Command Prompt** and run:

    cd C:\xampp\htdocs\HEISVERSE-COMPLETE-UPDATED
    C:\xampp\php\php.exe scripts\check_setup.php

Every line should say `[ OK ]`. It tests PHP extensions, `.env`, the database, TMDB (movies, TV, anime, search, filters, details) and prints a real poster and backdrop link you can paste in your browser.

### 6. Open the site
<http://localhost/HEISVERSE-COMPLETE-UPDATED/>  → log in → **Discover**.
Health check: <http://localhost/HEISVERSE-COMPLETE-UPDATED/api.php?action=health>

If you have no account yet, use `register.php` (the first registered user becomes admin) or `setup-admin.php`.

---

## Troubleshooting
| Problem | Fix |
|---|---|
| `Database connection unavailable` | MySQL is not started, or `DB_PASS` in `.env` is wrong. Run step 5. |
| `TMDB_API_KEY is missing` / `rejected the API key` | Check `TMDB_API_KEY` in `.env` (no quotes, no spaces). |
| `cURL error 60` / "SSL certificate problem" | Download <https://curl.se/ca/cacert.pem>, save it as `cacert.pem` in the project folder (next to `config.php`). It is used automatically. |
| "The PHP cURL extension is disabled" | In `C:\xampp\php\php.ini` remove the `;` before `extension=curl`, restart Apache. |
| No images | Run step 5 and open the printed poster link; if it opens, hard-refresh the site (Ctrl+F5). |
| Watchlist button says "table missing" | Run `migrations/004_tmdb_integration.sql`. |
| Old page still shown | Ctrl+F5 (the service worker caches old CSS/JS). |

Errors are written to `logs/php-error.log` and `logs/tmdb.log` (never shown to visitors, never containing your key).

---

## How anime is detected
TMDB has no "anime" flag. HEISVERSE treats a title as **anime** only when it has the **Animation** genre **and** Japanese as original language (or Japan as origin country). Japanese live-action shows are **not** anime, and non-Japanese cartoons are **not** anime. Discover's "Anime" filters send exactly these parameters to TMDB.

## Project map
| File | Purpose |
|---|---|
| `includes/tmdb.php` | The only place that talks to TMDB (cache, timeouts, errors, image URLs) |
| `includes/ui.php` | Shared header, search box, poster cards, rows |
| `discover.php` | Discover (TMDB + HEISVERSE Library tabs) |
| `title.php` | Movie / TV / anime details, trailer, cast, recommendations, similar |
| `search.php` | Global search (All / Movies / TV / Anime) |
| `api.php` | JSON endpoints (search, watchlist, player ...) |
| `cache/tmdb/` | Cached TMDB responses (safe to delete) |
| `scripts/check_setup.php` | Self-test |

## Security notes
- The TMDB key is read only from `.env` on the server; the browser never receives it.
- Because the key was shared in a chat, **regenerate it** at <https://www.themoviedb.org/settings/api> and paste the new one into `.env`.
- Playback: only authorized sources stored in your database (`movie_sources`), official TMDB-listed YouTube trailers, and your own local files (My PC). No scraping, no invented streams.
