# Development Rules

## Git

### Commit format
Title with short summary, main changes one per line, closing line for total diff stats.

### AI agent rules
- Big change → split into few commits. Small change → delay and combine later.
- Ambiguous task → do not commit until reviewed.

## Code

- Keep clean, readable, simple.
- Use modern features and current web standards.
- Split and reuse — prevent spaghetti.

## Security

- Never expose secrets in URLs, tracked files, or inline styles.
- Exclude secret configs from VCS; provide template without secrets.
- CSRF with safe comparison on all state-changing POSTs.
- Session-based auth. Rate-limit public endpoints.
- Collect and store only what is necessary.
- Pin external resources with integrity hashes.

## UI / Style

- Zero inline styles — single shared stylesheet.
- Structured layout with visual grouping over generic dividers.
- Consistent, light visual rhythm.

## Architecture

- Minimize external runtime dependencies.
- Decouple independent concerns (e.g. collection from feature logic).
- Gate privileged features behind prerequisite checks.
- Route data access through a single storage interface.

## AI

- On ambiguous tasks or when multiple valid approaches exist — ask the user with suggested variants before proceeding.
