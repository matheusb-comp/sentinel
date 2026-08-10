---
paths:
  - 'tests/**'
---

# Tests

## Two test suites: SQLite by default, Postgres for tenancy
New tests go in `tests/Feature` (or `tests/Unit`), which run on the in-memory `sqlite_testing` connection via `phpunit.xml`. Put a test in `tests/Tenancy` only when it depends on Postgres — tenant isolation via RLS, `timestamptz`, JSON operators, `ILIKE`. That suite uses `pgsql_testing` (database `labtemp_test`) via `phpunit.tenancy.xml`.

`composer test` (and `php artisan test`) runs only the default suite. The tenancy suite is a separate command on purpose:

```
composer test:tenancy
```

It is kept out of `composer test` so the everyday command stays fast and free of infrastructure prerequisites. RLS work will need a dedicated Postgres role with specific grants — something Laravel cannot provision for itself. CI runs both as separate steps, so nothing merges without the tenancy suite passing.

No manual setup is needed for the database itself: `MigrateCommand::createMissingMySqlOrPgsqlDatabase()` creates `labtemp_test` automatically when `migrate` runs with `--force`, which is what `RefreshDatabase` does. This requires the connecting role to have CREATEDB and access to the `postgres` database; the compose `admin` user is the Postgres superuser, so it does.

The two suites must stay in separate processes: `RefreshDatabaseState::$migrated` is a global static, so in one process the first suite to migrate would leave the second running against an unmigrated database. Do not try to switch connections per directory in `tests/Pest.php`.

Both testing connections avoid `env('DB_DATABASE')` on purpose — compose.yaml injects `DB_DATABASE=labtemp` as a real OS env var, and Laravel's env repository is immutable, so neither `force="true"` in phpunit.xml nor `.env.testing` can override it.
