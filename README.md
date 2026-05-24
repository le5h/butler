# local-stats

Self-hosted web statistics (PHP version).

- **0)** Drop-in folder solution for collecting site visitor statistics
- **1)** `?js` – returns async JS snippet that requests `?api`
- **2)** `?api` – methods: get new visit ID, update visit by ID
- **3)** `?view` – statistics page (switchable range: day, week, month, all) with 3 views:
  - Canvas graph (visits count + duration avg bars)
  - Summary (total visits, average visit time)
  - Paginated visit list (id, timestamp, duration, interactions, language, IP/location, OS)
- **4)** `?setup` – storage backend (`date.txt` or SQLite), access password, auth codes via QR

## Rules

1. Short, simple, minimal dependencies, optimally working, human-readable.
2. Git commits: short title with key features, bullet changes, closing summary line.
