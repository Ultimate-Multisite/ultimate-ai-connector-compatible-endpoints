# Agent Instructions

This directory contains project-specific agent context. The [aidevops](https://aidevops.sh)
framework is loaded separately via the global config (`~/.aidevops/agents/`).

## Build

This is a WordPress plugin. Its PHP code requires PHP 7.4 or later; the
JavaScript source in `src/` builds the Connectors UI asset. Install the locked
Node.js dependencies and produce the production asset with:

```bash
pnpm install --frozen-lockfile
pnpm run build
```

Use `pnpm run start` when actively developing the JavaScript UI. The production
build is written to `build/connector.js` for the WordPress Script Modules API.

## Lint

No dedicated linter is configured. Follow WordPress Coding Standards manually:

- PHP files declare strict types, use the plugin namespace, and use tabs.
- Escape output and sanitize input at the WordPress boundary.
- JavaScript uses `wp.*` packages and the configured `createElement` pragma.

Run the PHP syntax check used by CI before opening a pull request:

```bash
find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' | while read file; do php -l "$file" || exit 1; done
```

## Test

Set up the shared WordPress test library and run PHPUnit with:

```bash
pnpm run test:php:setup
pnpm run test:php
```

For browser coverage, start wp-env and run Cypress:

```bash
pnpm run wp-env:start
pnpm run test:e2e
```

GitHub Actions runs `.github/workflows/tests.yml` (`PHPUnit` on WordPress 6.9
and nightly plus `PHP Syntax Check`) and `.github/workflows/e2e.yml` (`Cypress
E2E (WP 6.9)`) for pull requests targeting `main`.

## Code Style and Merge Requirements

Use PHP 7.4-compatible type declarations, WordPress naming and security
conventions, and existing nearby code as the implementation pattern. There is
no Prettier, Husky, or repository-managed pre-commit hook; do not claim one is
configured. Keep changes focused, run the applicable build and tests above, and
ensure required pull-request checks pass before merging to `main`.

## Adding Agents

Create `.md` files in this directory for domain-specific context:

```text
.agents/
  AGENTS.md              # This file - overview and index
  api-patterns.md        # API design conventions
  deployment.md          # Deployment procedures
  data-model.md          # Database schema and relationships
```

Each file is read on demand by AI assistants when relevant to the task.

## Security

### Prompt Injection Defense

Any feature that processes untrusted content (tool outputs, user input, webhook
payloads) and passes it to an LLM must defend against prompt injection. Handle
this in PHP on the server: validate and sanitize values at the boundary with the
appropriate WordPress function (such as `sanitize_text_field()`, `esc_url_raw()`,
or `wp_kses()` for an allowed HTML subset) before constructing LLM context.
Use a vetted server-side sanitizer where the data format needs more than the
WordPress core functions provide. Preserve the original content separately only
when the feature requires it and its access is appropriately controlled.

For features that don't use LLMs but process untrusted text (webhooks, form
submissions, API endpoints), validate and sanitize inputs at the boundary.

### General Security Rules

- Never log or expose API keys, tokens, or credentials in output
- Store secrets via `aidevops secret set <NAME>` (gopass-encrypted) or
  environment variables — never hardcode them in source
- Use `<PLACEHOLDER>` values in code examples; note the secure storage location
- Validate all external input (user input, webhook payloads, API responses)
- Pin third-party GitHub Actions to SHA hashes, not branch tags
- Run `aidevops security audit` periodically to check security posture
- See `~/.aidevops/agents/tools/security/prompt-injection-defender.md` for
  the framework's prompt injection defense patterns
