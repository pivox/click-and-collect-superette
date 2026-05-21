# Testing Rules

## Backend (apps/backend/)

### Test environment

- Database: SQLite fichier par classe de test dans `sys_get_temp_dir()` — schéma recréé via `rebuildSchema()` dans `setUp()`.
- Auth: `HTTP_X_TEST_USER` header bypasses JWT — set to user email in test fixtures.
- Schema: `rebuildSchema()` called before each test class to ensure clean state.

### Test types and location

- Unit tests: `tests/Unit/` — entities, services, voters, processors, mappers.
- Functional (API) tests: `tests/Functional/Api/` — full HTTP request/response cycle.

### Coverage expectations

- Every new business rule (order status transition, price snapshot, slot capacity) needs a unit test.
- Every new API endpoint needs a functional test covering: happy path, unauthorized access, invalid input.
- Do not test framework internals — test your domain logic.

### Run

```bash
cd apps/backend && vendor/bin/phpunit
```

## Naming conventions

- Test class: `{Subject}Test.php`
- Test method: `test{ScenarioInCamelCase}` or descriptive method name

## Pickup slot tests — always use relative dates

`PickupSlotRepository::findAvailableForShop()` filters `startsAt > now()`. Tests with hardcoded
dates silently return 0 results once those dates pass.

```php
// Correct — always in the future
$tomorrow = new \DateTimeImmutable('tomorrow 09:00:00', $timezone);
$slot = $this->createPickupSlot($shop, $tomorrow, $tomorrow->modify('+1 hour'), 4);

// Incorrect — returns 0 results the day after the hardcoded date
$slot = $this->createPickupSlot($shop, new \DateTimeImmutable('2026-05-21 09:00:00', $tz), ...);
```

## Verifying a pre-existing CI failure

```bash
git checkout main
vendor/bin/phpunit tests/Functional/Api/SomeTest.php --filter testSpecificMethod
git checkout -  # return to feature branch
```

If it fails identically on `main`, document it in the PR and handle separately.
