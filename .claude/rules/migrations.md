# Migration Rules

## Protocol for schema changes

1. Every Doctrine entity change must be accompanied by a migration.
2. Generate: `symfony console doctrine:migrations:diff` — review the diff before committing.
   **Warning**: if the local DB is empty or out of sync, the diff generates a full-schema migration
   (recreates all tables). In that case, write the migration manually (e.g. `ALTER TABLE shops ADD
   logo_url VARCHAR(2048) DEFAULT NULL`) and delete the generated file.
3. Validate: `symfony console doctrine:schema:validate` — must pass before merging.
   **Drift cosmétique acceptable** : si `migrations:diff` génère uniquement des `ALTER INDEX ... RENAME TO`,
   c'est un drift de noms d'index (custom → auto Doctrine), non fonctionnel. Supprimer le fichier généré
   et documenter le drift comme connu. Ne pas committer une migration cosmétique inutile.
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
