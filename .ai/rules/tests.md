---
paths:
  - 'tests/**'
---

# Tests

## Two test suites: SQLite by default, Postgres for what only Postgres proves
New tests go in `tests/Feature` (or `tests/Unit`), which run on the in-memory `sqlite_testing` connection via `phpunit.xml`. Put a test in `tests/Postgres` only when it depends on Postgres — tenant isolation via RLS, partitioned tables, `timestamptz`, JSON operators, `ILIKE`. That suite uses `pgsql_testing` via `phpunit.postgres.xml`, whose database defaults to `DB_DATABASE` plus a `_test` suffix, so it follows the project without the name being written anywhere. Override with `DB_TEST_DATABASE` if needed.

`composer test` (and `php artisan test`) runs only the default suite. The Postgres suite is a separate command on purpose:

```
composer test:postgres
```

It is kept out of `composer test` so the everyday command stays fast and free of infrastructure prerequisites. RLS work will need a dedicated Postgres role with specific grants — something Laravel cannot provision for itself. CI has a step for each, but both workflows are triggered by hand: nothing about them gates a merge.

No manual setup is needed for the database itself: `MigrateCommand::createMissingMySqlOrPgsqlDatabase()` creates it automatically when `migrate` runs, which both `RefreshDatabase` and `DatabaseTruncation` trigger. This requires the connecting role to have CREATEDB and access to the `postgres` database; the compose `admin` user is the Postgres superuser, so it does. CI relies on this too — the workflow's Postgres service does not declare the database.

## The Postgres suite uses DatabaseTruncation, not RefreshDatabase
`PostgresRLSBootstrapper` reconnects as a different Postgres role, which means a different connection. `RefreshDatabase` keeps every test inside an uncommitted transaction on the central connection, so the tenant connection would see an empty database and every isolation test would pass for the wrong reason. Truncation commits, so both connections agree.

Its `beforeEach` in `tests/Pest.php` runs `php artisan tenants:rls`, because `migrate:fresh` drops policies along with the tables.

## tests/Postgres proves isolation once, not per feature
`RowLevelSecurityTest` proves the isolation mechanism: policies exist, they compare the tenant key as bigint rather than casting the column, they scope reads, they reject a write carrying another company's key, and the central connection still sees everything.

A new feature does not get its own isolation test. `TenantScope` stays registered — the configured manager is `BigintTableRLSManager`, a `TableRLSManager` subclass, and no model implements `RLSModel` — so the SQLite suite already exercises application-level scoping. RLS covers structurally what that cannot reach: query builder calls and raw SQL inside tenancy.

The suite also holds tests that only prove something on Postgres, whatever the feature: the connection switch made by `PostgresRLSBootstrapper`, requests served on the RLS connection, a `uuid` column rejecting malformed input. A test belongs there when it would pass on SQLite even with the defect it guards against.

## The SQLite suite strips the RLS bootstrapper
`tests/Pest.php` filters `PostgresRLSBootstrapper` out of `tenancy.bootstrappers` for `tests/Feature`. It issues `SET my.current_tenant = '...'`, which is not valid SQLite. Tenant scoping there comes from `TenantScope` alone, which is real coverage — it is the same layer that runs in production alongside RLS.

The two suites must stay in separate processes: `RefreshDatabaseState::$migrated` is a global static, so in one process the first suite to migrate would leave the second running against an unmigrated database. Do not try to switch connections per directory in `tests/Pest.php`.

Neither testing connection lets `phpunit.xml` set the database name: compose.yaml injects `DB_DATABASE` as a real OS env var, and Laravel's env repository is immutable, so neither `force="true"` nor `.env.testing` can override it. `sqlite_testing` hardcodes `:memory:`; `pgsql_testing` derives from `DB_DATABASE` in PHP. Only `DB_CONNECTION` is switchable from `phpunit.xml`, which is why each suite selects a whole connection.

## Run the Postgres suite only when the change can reach it
The Postgres suite costs about 165 seconds; the default one costs 5. While iterating, run the affected test file only. Run the whole default suite before handing work over.

Run the Postgres suite when the change reaches what it covers: migrations or schema, tests/Pest.php, model traits or connections, the actions it exercises (CreateDevice, SyncDeviceSensors, StoreReadings, partitioning), tenancy config or bootstrappers, and the tenant route chain. Run it once more at the end of a cycle.

In doubt, run the single Postgres file instead of the suite: one file takes seconds.
