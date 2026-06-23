# AGENTS.md — Click & Collect Supérette Tunisie

## Project mission

Build and maintain a full-stack click & collect application for local supérettes in Tunisia.

The customer scans a shop QR code, opens the shop space, browses products, prepares a **Kadhia**, chooses a pickup slot, submits the order, then picks it up after merchant validation.

The repository now contains backend, frontend, admin, merchant and product documentation. `docs/project/source-of-truth.md` is the project entry point and precedence document. `AI_CONTEXT.md` remains the shared AI context for MVP scope, business vocabulary, order statuses and reference entities.

## Always read first

Before changing anything, read these files when present:

1. `AI_CONTEXT.md` — shared AI context for current project state, MVP scope, business vocabulary, order statuses and reference entities
2. `README.md` — development workflow and repository orientation
3. `docs/project/source-of-truth.md` — project entry point and documentation precedence
4. relevant `docs/product/`, sprint or roadmap documentation for the task

**Codex CLI** — then read: `Codex/instructions.md`, `Codex/workflows.md`, `Codex/checklist.md`

**Claude Code** — `CLAUDE.md` handles agent-specific config via `@imports` (`Claude/instructions.md`, `Claude/workflows.md`).

## Language and tone

- Respond in French by default.
- Keep product documentation clear, concrete and MVP-oriented.
- Use English comments in code when code is added.
- Preserve the business vocabulary defined in `AI_CONTEXT.md`: **Kadhia**, supérette, marchand, client, rendez-vous, retrait.

## Coding rules

- Prefer small, focused changes.
- Do not introduce new production dependencies without explaining why.
- Keep domain logic in services or domain classes, not controllers.
- Keep API resources explicit with serialization groups when needed.
- Use DTOs for write models when entity exposure would create coupling.
- Add migrations for database changes.
- Add tests for meaningful business behavior.
- Keep names explicit and business-oriented.

## Symfony/API Platform guidance

When backend code exists:

- Use API Platform operations for separate read/write routes when the payloads differ.
- Use serialization groups to expose different representations.
- Do not rely on eager loading as a substitute for clear API design.
- Avoid exposing internal persistence details to the client.
- Use validation constraints on input DTOs or entities.
- Use voters/security expressions for merchant/customer/admin access separation.

## Verification commands

Do not invent successful test results.

For this repository, run PHP, Symfony and Composer commands through Docker Compose or the existing `make` targets. Do not use host `php`, `composer` or `symfony` for project checks.

When a Symfony application is added, prefer these checks when available:

- `docker compose exec backend composer validate`
- `docker compose exec backend composer install`
- `docker compose exec backend php bin/console lint:container`
- `docker compose exec backend php bin/console doctrine:schema:validate`
- `docker compose exec backend php bin/phpunit`
- `docker compose exec backend vendor/bin/phpstan analyse`
- `docker compose exec backend vendor/bin/php-cs-fixer fix --dry-run --diff`

Equivalent `make` targets are preferred when available: `make test-backend`, `make lint-backend`, `make validate`, `make phpunit`.

For documentation-only changes:

- check Markdown readability;
- check internal links;
- check that MVP scope remains coherent.

## Required final response format

For every task, summarize:

- what changed;
- files changed;
- verification performed or not performed;
- assumptions;
- risks / next steps.

Never claim that tests passed unless they were actually run.
