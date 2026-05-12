# AGENTS.md — Click & Collect Supérette Tunisie

## Project mission

Build a click & collect application for local supérettes in Tunisia.

The customer scans a shop QR code, opens the shop space, browses products, prepares a **Kadhia**, chooses a pickup slot, submits the order, then picks it up after merchant validation.

## Always read first

Before changing anything, read these files when present:

1. `AI_CONTEXT.md` — product context, MVP scope, business vocabulary, order statuses, reference entities
2. `README.md`
3. `docs/product/`

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

The repository is currently documentation-first. Do not invent successful test results.

When a Symfony application is added, prefer these checks when available:

- `composer validate`
- `composer install`
- `symfony console lint:container`
- `symfony console doctrine:schema:validate`
- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse`
- `vendor/bin/php-cs-fixer fix --dry-run --diff`

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
