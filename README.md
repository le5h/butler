# local-stats

Self-hosted analytics that respects your visitors. Drop the folder on any PHP server, add one script tag, and get clean visitor stats without selling out your data.

- **Zero dependencies** — no npm, no build step, no database required. Pure PHP.
- **Privacy-first by default** — aggregates only. No cookies, no tracking IDs, no individual user profiles. Every optional field can be toggled off.
- **Two storage backends** — flat file (zero setup) or SQLite (faster queries). Switch in settings.
- **Password + TOTP two-factor auth** — your stats stay yours.
- **Dark theme dashboard** — Chart.js bar chart, paginated visit table, and CSV/JSON export.
- **Adblocker-resistant** — served from your own domain with no third-party scripts. Not on any blocklist.

## Why local-stats?

Google Analytics, Plausible, Fathom — great tools, but they're either a privacy leak, a monthly bill, or a complex self-hosted setup. **local-stats** is a single PHP folder. Upload it, embed a script, done. No accounts, no tracking networks, no JavaScript framework.

## How to use

Upload the folder to your server and embed the tracker on any page:

```html
<script src="https://your-domain.com/stats/?js" async></script>
```

The script auto-detects its own URL via `document.currentScript` — it works from any path.

### Routes

| Route | What it does |
|---|---|
| `?js` | Returns the async JS tracker. Embed on the pages you want to monitor. |
| `?view` | Stats dashboard: bar chart, summary cards, paginated visits, CSV/JSON export. |
| `?settings` | Admin panel: data toggles, storage backend, password, TOTP, retention. |
| `?api=new` | Creates a visit record (POST). Called by the tracker on page load. |
| `?api=update` | Updates a visit (POST). Called by the tracker on page leave. |
| `?test` | Test page to verify stats collection. Includes the tracker and interactive elements. |
| `?logout` | Ends your admin session. |

The first time you visit `?settings`, a `config.php` is created from the example template. Set a password and you're ready.

## How it works

### JS tracker

The snippet from `?js` is a self-executing IIFE with your collection settings baked in at generation time. On page load it POSTs to `?api=new` with enabled fields (page, referrer, language). The server returns a visit ID. On page leave (`beforeunload` / `visibilitychange`) it POSTs the ID with elapsed duration and interaction count via `navigator.sendBeacon`.

### Architecture

```
index.php          — router + inline JS/API handlers (hot path)
lib/storage.php    — Storage interface: FileStorage & SqliteStorage
lib/geo.php        — OS detection + IP geo lookup (ip-api.com, cached)
lib/auth.php       — password + TOTP auth via sessions
lib/view.php       — dashboard: Chart.js, pagination, export
lib/settings.php   — admin settings with CSRF protection
lib/common.php     — shared HTML helpers (head, nav, footer)
style.css          — single shared stylesheet (dark theme)
config.php         — your settings (gitignored)
config.example.php — template without secrets
data/              — visit data (gitignored)
```

### Privacy

Collected data is minimal and configurable:

| Data point | Default | Notes |
|---|---|---|
| Visit duration, interactions | Always on | Needed for basic stats |
| Page URL, referrer, language | On (toggleable) | Disable any in `?settings` |
| Operating system | Always on | From User-Agent header |
| Subnet (e.g. 192.168.1.0/24) | Off (opt-in) | Cannot identify individuals |
| Geo location | Off (opt-in) | Looked up live, IP never stored |

### Security

- Rate-limited to 120 requests/hour per IP on the API.
- CSRF tokens on all state-changing POSTs.
- bcrypt passwords, session-based auth (no secrets in URLs).
- TOTP two-factor (RFC 6238) with 30s verification window.
- `htmlspecialchars` on all rendered user data.

### Storage backends

- **File** — one JSON-lines file per day (`data/YYYY-MM-DD.txt`). No setup, great for low traffic.
- **SQLite** — PDO/SQLite with server-side aggregation. Better for production.

Switch between them in `?settings`. Auto-cleanup of old records is configurable.

---

Built with [OpenCode](https://opencode.ai).
