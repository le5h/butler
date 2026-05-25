# Development Plan

## Phase 1 – Foundation
- [x] Init git repo, README, PLAN
- [x] Create `index.php` with router (`?js`, `?api`, `?view`, `?settings`)
- [x] Create `config.php` – password, storage backend, collection toggles

## Phase 2 – Storage Layer
- [x] `lib/storage.php` – interface with two implementations:
  - `FileStorage` (date.txt append-log per day)
  - `SqliteStorage` (SQLite via PDO with `getAggregatedStats()` GROUP BY)
- [x] Auto-detect backend from config, fallback to file
- [x] `cleanup()` method — auto-delete records older than N days (configurable)
- [x] Schema migration via column existence check (not silent try/catch)

## Phase 3 – API (?api)
- [x] `api/new` – create visit, return ID
- [x] `api/update` – update duration, interactions by ID
- [x] CORS + JSON responses
- [x] Respect collection toggles (page/referrer/lang gated by config)
- [x] Rate limiting — 120 req/h per IP via file-based counter

## Phase 4 – JS Snippet (?js)
- [x] Return inline JS that loads on page
- [x] Calls `api/new` on load, `api/update` on unload / visibility change
- [x] Collection settings inlined as `S` object — only sends enabled fields
- [x] Uses `navigator.sendBeacon` for reliable leave events

## Phase 5 – View (?view)
- [x] HTML page with range switcher (day/week/month/all)
- [x] Chart.js bar chart (visits + avg duration from `getAggregatedStats()`)
- [x] Dual y-axis (visits left, duration right)
- [x] Summary card (total visits, avg visit time)
- [x] Paginated visits table (id, timestamp, duration, interactions, lang, IP, geo, OS, page)
- [x] IP → location at creation time via ip-api.com (opt-in, HTTPS, 3s timeout)
- [x] Export CSV / JSON

## Phase 6 – Settings (?settings)
- [x] Data collection toggles (page URL, referrer, language, IP, geo)
- [x] Storage backend selector (file ↔ sqlite)
- [x] Password set / change
- [x] TOTP secret generation + server-side verification
- [x] Configurable visit retention (auto-cleanup)
- [x] CSRF protection on all POST requests
- [x] Save to `config.php`

## Phase 7 – Polish
- [x] Error handling
- [x] XSS protection (htmlspecialchars on all user data)
- [x] Performance: hot-path split, on-demand lib loading, SQL-level aggregation
- [x] Privacy: minimal data by default, all optional fields opt-in
- [x] Session-based auth (no password in URLs)
- [x] `X-Forwarded-For` support for proxy environments
- [x] Geo lookup caching (per-request, HTTPS, 3s timeout)
- [x] Auth logout (`?logout`)

## Phase 8 – Future
- [ ] TOTP two-factor auth — ~~verify codes on login, implement server-side validation~~ **done**
- [ ] Configurable visit retention / auto-cleanup — **done**
- [ ] Export stats (CSV/JSON) — **done**
- [ ] Email / push notification summaries
- [ ] Real-time dashboard with SSE
- [ ] Plugins / webhook integration

## Architecture
- `index.php` — lean router + inline JS/API handlers (hot path)
- `lib/storage.php` — FileStorage & SqliteStorage with `getAggregatedStats()`
- `lib/geo.php` — OS detection + IP geo-lookup helpers (HTTPS, caching)
- `lib/auth.php` — password + TOTP auth via sessions (loaded only for view/settings)
- `lib/view.php` — stats page with Chart.js + CSV/JSON export (loaded only for ?view)
- `lib/settings.php` — admin settings page with CSRF (loaded only for ?settings)
- `config.php` — persistent settings
- `data/` — runtime storage (gitignored)
