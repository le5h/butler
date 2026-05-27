# Development Rules

## Git commit format

- Title with very short summary or top feature
- Main changes one per line
- Closing line for small changes summary

## Code

- Keep clean, readable, simple.
- Minimize external runtime dependencies.
- Use modern features and current web standards.
- Split and reuse, prevent "spaghetti".
- Short commentary based documentation is allowed.

## Security

- Collect and store only what is necessary.
- Never expose secrets in URLs or tracked files.

## UI / Style

- Zero inline styles, reusable css classes insted of unique.
- Modern, consistent, compact and clean visual langauge.

## For AI agents

- Ask the user with suggested variants when multiple valid approaches exist before proceeding.
- Long list of changes → split into few commits. Short → delay for later commit.
