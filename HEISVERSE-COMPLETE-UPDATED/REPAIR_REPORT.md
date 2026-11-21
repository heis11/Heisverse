# HEISVERSE — Repair Report

## 1. Problems found (from reading the actual source)
| # | Problem | Evidence |
|---|---|---|
| 1 | **TMDB was never connected.** The key constant existed but no file called TMDB. | `grep -i tmdb` finds only `config.php` and an unused `tmdb_id` column |
| 2 | **Discover showed no wallpapers/posters.** It only listed rows from the local `movies` table and drew gradients + emoji. `poster_url`/`backdrop_url` were never rendered anywhere. | old `discover.php`, `index.php` |
| 3 | Discover filters were free-text/fixed lists (8 countries, 6 languages) over local rows only. No rating, genre, sort or pagination. | old `discover.php` |
| 4 | **Docker-only defaults**: `DB_HOST` defaulted to `db` and a real-looking DB password was hard-coded in `config.php` / `.env.example`. XAMPP would fail to connect. | `config.php`, `.env.example` |
| 5 | `database.sql` has no `content_type/country/original_language` columns that Home, Anime, Series, Admin and Discover query (they live only in migration 003). Importing only `database.sql` breaks those pages. Also 003 uses `ADD COLUMN IF NOT EXISTS`. | `database.sql` vs `migrations/003` |
| 6 | **Mobile navigation missing**: links were `display:none` under 960px with no menu button. | `style.css` |
| 7 | Search dropdown injected unescaped titles into HTML (XSS) and only searched the local table. Only the home page had a search box. | `main.js`, `api.php` |
| 8 | SQL error text leaked to visitors (login, register, API). `error_reporting(0)` in production hid real errors instead of logging. | `auth.php`, `api.php`, `config.php` |
| 9 | Legacy watchlist toggle had no CSRF check; theme/accent values were stored unvalidated. | `api.php` |
| 10 | Service worker could cache cross-origin images and was not excluding `api.php`. | `service-worker.js` |

## 2. What was changed
**New:** `includes/tmdb.php`, `includes/ui.php`, `title.php`, `search.php`, `scripts/check_setup.php`, `migrations/004_tmdb_integration.sql`, `migrations/004b_ensure_global_columns.sql`, `.htaccess`, `cache/`, `logs/` (web-blocked), updated `.env`, `.env.example`, `.gitignore`, `README.md`.
**Rewritten:** `discover.php` (original library filter view kept on its own tab).
**Edited:** `config.php` (XAMPP defaults, log file, TMDB settings, CSRF/rate-limit helpers), `api.php` (TMDB search + TMDB watchlist, CSRF on watchlist, no error leakage, health no longer prints DB errors), `includes/auth.php` (no SQL error leakage), `assets/js/main.js` (escaped search, debounce, search page, trailers, share, watchlist, image fallbacks, mobile menu), `assets/css/style.css` (additions only, existing rules untouched), `service-worker.js`, `index.php` (shows stored `poster_url`/`backdrop_url` when present; layout unchanged), `watchlist.php` (adds "Saved from Discover" section), search box added to the headers of `anime, cartoons, categories, contact, music, my-pc, series, watchlist`.
**Not touched:** your database content, tables, users, admin dashboard logic, login/register flows, player, My PC, themes, languages, Docker files.

## 3. TMDB service (`includes/tmdb.php`)
Popular / trending / top-rated / upcoming / now-playing movies; popular / trending / top-rated / airing TV; movie + TV details (videos, credits, recommendations, similar in one call); search (multi/movie/tv/anime); genres; countries; languages; discover with real filters; pages (capped at TMDB's 500).
Handles: missing/invalid key (401), HTTP errors, 404, 429 (short back-off, serves stale cache), cURL timeouts (8 s), invalid JSON, missing image paths, empty results. File cache in `cache/tmdb/` (the key is never part of cache names or logs). Image URLs are built only from real `poster_path/backdrop_path/profile_path` with TMDB-supported sizes.

## 4. Discover
Featured trending title with real backdrop, overview, date, rating, genre, **Watch Trailer** (only when TMDB lists an official YouTube video) and **View Details**; rows: Trending, Popular Movies, Popular TV, Recommended, Anime, Anime Films, Recently Released, Top Rated Movies/TV, Action/Comedy/Drama. Filters (type, genre, year, country, original language, minimum rating, sort incl. Latest releases) are sent to TMDB `/discover`; results are paginated.

## 5. Tests — what was and was NOT run
### Actually run (in my sandbox)
- JavaScript syntax check (`node --check`) on `main.js`, `service-worker.js` and the other JS files: **passed**.
- CSS brace balance: **passed**.
- A bracket/parenthesis balance scan of every `.php` file: **passed** (this is only a sanity scan, **not** a PHP syntax check).

### NOT run (my sandbox has no PHP, no MySQL and no internet)
- `php -l` on all files, database connection, TMDB connectivity, popular movies/TV, Discover filters, poster and backdrop loading, search, pagination, trailers, authentication, admin, browser console, mobile layout, and a visual comparison with your original design.
- **I therefore cannot claim any of these work.** The code was written carefully against TMDB's documented API, but it has never been executed.

### How you run them (5 minutes)
1. `C:\xampp\php\php.exe scripts\check_setup.php` — tests PHP, `.env`, database, TMDB (popular, trending, TV, anime, search, filters, details) and prints image links.
2. Run `C:\xampp\php\php.exe -l discover.php` (repeat for `title.php`, `search.php`, `api.php`, `includes\tmdb.php`, `includes\ui.php`) — each must say "No syntax errors".
3. Browser: log in → Discover (backdrop + rows), pick **Anime** + a genre + Apply (results change), go to page 2, open a title, press Watch Trailer, add to Watchlist, open Watchlist, type in the header search, open `search.php?q=naruto` and click the Anime chip. Press F12 → Console/Network and look for red errors.
4. Resize the browser narrow (or use F12 device mode) and check the ☰ menu.
Please send me the output of step 1 and any red message and I will fix it.

## 6. Known limits / honest notes
- `anime.php`, `series.php`, `cartoons.php`, `categories.php` and the Home grids still show **your local library** (database) as before; the worldwide TMDB anime/TV/movie experience is in **Discover, Search and Title details**. Wiring TMDB into those pages is a possible next step.
- Your project has **no** "Free Films" page, Favorites page or profile-edit page; I did not invent them. Watch History exists via Continue Watching.
- Play buttons on TMDB titles only appear when that title exists in your library with an active authorized source (`movies.tmdb_id` + `movie_sources`). Otherwise the page shows a clear "no authorized stream" note and the official trailer.
- Cold first load of Discover makes ~15 TMDB requests (about 2–4 s); afterwards it is served from cache for 1 hour.
- Admin "Add/Edit movie" does not yet import from TMDB.
- The `.env` in this ZIP contains your TMDB key (needed to run locally). The key was shared in chat: **regenerate it** and replace it in `.env`. Never publish this ZIP or `.env`.
- `DB_PASS` is left empty: add your `heisverse_user` password.
