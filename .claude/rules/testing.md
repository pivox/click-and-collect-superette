# Testing Rules

## Backend (apps/backend/)

### Test environment

- Database: SQLite in-memory (`DATABASE_URL=sqlite:///:memory:`).
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
