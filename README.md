# local-stats

Self-hosted, drop-in PHP analytics for tracking site visitors with zero external dependencies. Collects **aggregate visit stats only** — not designed to identify individual users.

Drop the folder on your server, embed a script tag, and start collecting visitor stats immediately.

## Routes

| Route | Description |
|---|---|
| `?js` | Returns an async JS tracker. Embed on any page. |
| `?api&method=new` | Creates a new visit record. Called by the JS tracker on page load. |
| `?api&method=update` | Updates a visit (duration, interactions). Called on page leave. |
| `?view` | Stats dashboard with Chart.js bar graph, summary cards, and paginated visit table. Switchable range: day, week, month, all. |
| `?setup` | Configuration page: storage backend (file or SQLite), access password, TOTP auth secret with QR code. |

## How the JS tracker works

The JS snippet returned by `?js` is a self-executing IIFE that:

1. **On page load** — sends a POST to `?api&method=new` with referrer, screen size, language, and page path. The server returns a unique visit ID.
2. **During the session** — counts click and keydown events as interactions.
3. **On page leave** (`beforeunload` / `visibilitychange`) — sends a POST to `?api&method=update` with the visit ID, elapsed duration (seconds), and interaction count. Uses `navigator.sendBeacon` when available.

## Embed example

Include the script on any page you want to track:

```html
<script src="https://your-domain.com/stats/?js" async></script>
```

The script auto-detects its own URL via `document.currentScript`, so it works from any path.

## Architecture

```
index.php          — lean router + inline JS/API handlers (hot path)
lib/storage.php    — FileStorage & SqliteStorage backends
lib/geo.php        — OS detection + IP geo lookup helpers
lib/auth.php       — password auth (loaded on demand)
lib/view.php       — stats dashboard (loaded on demand)
lib/setup.php      — config page (loaded on demand)
config.php         — persistent settings (password, storage, auth_secret)
data/              — runtime data (gitignored)
```

## Privacy

This tool is designed for **aggregate analytics**, not user tracking. Collected data is minimal:

| Data point | Stored by default | Notes |
|---|---|---|
| Page URL, referrer, language, screen size | Yes | No personal data |
| Visit duration, interaction count | Yes | Aggregated only |
| IP address | **No** (opt-in) | Toggle in `?setup` |
| Geo location (from IP) | **No** (opt-in) | Requires IP storage |

IP and geo location are opt-in to avoid storing personal data unless you explicitly need it.

## Storage

- **File** — appends JSON lines per day (`data/YYYY-MM-DD.txt`). Simple, no database needed.
- **SQLite** — uses PDO/SQLite with aggregated queries for faster stats. Recommended for production.

Set the backend in `?setup`.
