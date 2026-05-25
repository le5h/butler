# Development Plan

## Phase 1 – Foundation
- [x] Init git repo, README, PLAN
- [x] Create `index.php` with router (`?js`, `?api`, `?view`, `?settings`, `?logout`, `?test`)
- [x] Create `config.php` – password, storage backend, collection toggles
- [x] Simplify API routes: `?api=new` / `?api=update` instead of `?api&method=…`

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

## Phase 8 – Tooling
- [x] `?test` page with live tracker status, interactive elements, manual API verify, dashboard link

## Phase 9 – Future
- [x] TOTP two-factor auth — ~~verify codes on login, implement server-side validation~~ **done**
- [x] Configurable visit retention / auto-cleanup — **done**
- [x] Export stats (CSV/JSON) — **done**
- [ ] Email / push notification summaries
- [ ] Plugins / webhook integration
- [ ] **Breakdowns section on `?view`** — inline card with top-N tables. No new routes, no new page types. All rendered on the existing dashboard below the chart.

  | Breakdown | Data source | Presentation |
  |---|---|---|
  | Top pages | `page` (default on) | Ranked table: page URL → visits → avg duration |
  | Top referrers | `referrer` (default on) | Ranked table: source → visits; "Direct" when empty |
  | Bounce rate | `interactions`, `duration` | Summary stat card (% with 0 clicks or <3s) |
  | Top operating systems | `os` (always collected) | Horizontal bar chart or ranked table: OS → visits |
  | Top languages | `lang` (default on) | Ranked table: lang code → visits |
  | Top countries | `geo` (opt-in only) | Ranked table: location → visits (shown only when geo data exists) |
  | Hourly & weekday trends | `timestamp` (always) | Small heatmap-style row or two compact bar charts on the dashboard |

  All breakdowns share a single `getBreakdowns(string $range): array` method on the `Storage` interface — one query per backend, computed server-side, rendered as simple HTML tables. Keeps the view layer trivially simple.

- [ ] **Custom actions** — track named events (form submits, button clicks, purchases). Expose a queue-based `stats()` global so calls work before the async tracker loads:

  ```js
  window.stats = window.stats || function(){ (window._statsq = window._statsq || []).push(arguments); };
  ```

  The tracker processes the queue on load, sends events to `?api=event` (POST, flushed on page leave). Storage: separate event log per backend. Dashboard: events table + top events. Off by default, toggled in `?settings`.

## Architecture
- `index.php` — lean router + inline JS/API handlers (hot path, on-demand lib loading)
- `lib/storage.php` — abstract `Storage` + `FileStorage` & `SqliteStorage`
- `lib/geo.php` — OS detection + IP geo-lookup helpers (HTTPS, per-request cache)
- `lib/auth.php` — password + TOTP auth via sessions (loaded only for view/settings)
- `lib/view.php` — stats dashboard, Chart.js, pagination, CSV/JSON export (loaded only for ?view)
- `lib/settings.php` — admin settings page with CSRF (loaded only for ?settings)
- `lib/test.php` — test page for verifying stats collection (loaded only for ?test)
- `lib/common.php` — shared HTML render helpers (head, nav, footer, Chart.js)
- `style.css` — single shared stylesheet, dark theme, zero inline styles
- `config.php` — persistent settings (gitignored)
- `config.example.php` — template without secrets
- `data/` — runtime storage (gitignored)
