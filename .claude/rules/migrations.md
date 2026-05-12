# Migration Rules

## Protocol for schema changes

1. Every Doctrine entity change must be accompanied by a migration.
2. Generate: `symfony console doctrine:migrations:diff` — review the diff before committing.
3. Validate: `symfony console doctrine:schema:validate` — must pass before merging.
4. Never edit a migration after it has been merged to `main`.
5. Never run `doctrine:migrations:migrate` automatically — always run manually and deliberately.

## Naming convention

Format: `Version{YYYYMMDD}{HHMMSS}.php`
Example: `Version20260512130000.php`

## Migration content rules

- Implement both `up()` and `down()` methods.
- Keep each migration focused on one logical change.
- Include index creation for fields used in WHERE clauses (`shop_id`, `status`, `created_at`, `pickup_slot_id`).
- Do not seed data in migrations unless it is a required singleton (e.g., PlatformTheme with a fixed UUID).

## Rollback

- `down()` must fully reverse `up()`.
- Test `down()` manually in development before merging.
