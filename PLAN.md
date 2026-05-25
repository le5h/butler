# Development Plan

## Done

- **Foundation** — router, config, auto-copy from example template
- **Storage** — `FileStorage` (flat file) + `SqliteStorage` (PDO/SQLite), auto-detect, fallback, cleanup, schema migration, timestamp index
- **API** — `?api=new` / `?api=update`, CORS, collection toggles, rate limiting
- **JS tracker** — inline IIFE, `sendBeacon` on leave, config inlined at generation
- **Dashboard** — Chart.js, range switcher, quality bar, paginated table, CSV/JSON export, summary cards
- **Settings** — collection toggles, storage selector, password/TOTP auth, retention, CSRF
- **Auth** — bcrypt passwords, TOTP (RFC 6238), session-based, CSRF on POST
- **Polish** — XSS protection, hot-path split, on-demand lib loading, privacy defaults, geo caching, `?logout`
- **Tooling** — `?test` page with live tracker status and manual API verify
- **Data protection** — `.htaccess` denying direct data access, gitignored runtime files
- **SQLite perf** — timestamp index, conditional VACUUM on cleanup

## Future

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
- `data/` — runtime storage (gitignored, access denied)
