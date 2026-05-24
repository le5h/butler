# Development Plan

## Phase 1 – Foundation
- [x] Init git repo, README, PLAN
- [ ] Create `index.php` with router (`?js`, `?api`, `?view`, `?setup`)
- [ ] Create `config.php` – password, auth codes, storage backend setting

## Phase 2 – Storage Layer
- [ ] `lib/storage.php` – interface with two implementations:
  - `FileStorage` (date.txt append-log per day)
  - `SqliteStorage` (SQLite via PDO)
- [ ] Auto-detect backend from config, fallback to file

## Phase 3 – API (?api)
- [ ] `api/new` – create visit, return ID
- [ ] `api/update` – update duration, interactions by ID
- [ ] CORS + JSON responses
- [ ] Basic auth via `pwd` param or header

## Phase 4 – JS Snippet (?js)
- [ ] Return minified inline JS that loads on page
- [ ] Calls `api/new` on load, `api/update` on unload / visibility change
- [ ] Sends: referrer, user-agent, language, screen size

## Phase 5 – View (?view)
- [ ] HTML page with range switcher (day/week/month/all)
- [ ] Chart.js or pure canvas bar chart (visits + avg duration)
- [ ] Summary card (total visits, avg visit time)
- [ ] Paginated visits table (id, timestamp, duration, interactions, lang, IP/location, OS)
- [ ] IP → location via free geo API or local GeoLite DB

## Phase 6 – Setup (?setup)
- [ ] Storage backend selector (file ↔ sqlite)
- [ ] Password set / change
- [ ] Auth code generation + QR code display (via `chart.js` or similar)
- [ ] Save to `config.php`

## Phase 7 – Polish
- [x] Error handling
- [x] CSP headers, XSS protection
- [x] Final testing, lint, commit

## Architecture (performance-optimized)
- `index.php` — lean router + inline JS/API handlers (hot path)
- `lib/storage.php` — FileStorage & SqliteStorage with `getAggregatedStats()` for SQL-level aggregation
- `lib/geo.php` — OS detection + IP geo-lookup helpers
- `lib/auth.php` — password auth (loaded only for view/setup)
- `lib/view.php` — stats page with Chart.js (loaded only for ?view)
- `lib/setup.php` — config page (loaded only for ?setup)
