---
paths:
  - 'database/migrations/**'
---

# Migrations

## Tenant key columns are bigint, and the RLS policy casts the session variable

Declare tenant key columns as `$table->foreignId('company_id')`. The tenant key
is `companies.id`, a plain autoincrement bigint — the cheapest possible foreign
key and the cheapest join.

This only works because the project ships a custom RLS policy manager. The stock
`TableRLSManager` generates `company_id::text = current_setting('my.current_tenant')`,
which **casts the column** and makes the predicate non-indexable. Ours inverts it
to `company_id = current_setting('my.current_tenant')::bigint`, casting the
session variable instead.

An index answers questions about the values it stores. `f(column) = constant` can
only use that index when `f` is a no-op relabel. `varchar -> text` is the only
such cast; `bigint`, `uuid` and `char(26) -> text` are all real conversions, so
they are non-indexable regardless of the data. `char(26)` fails even when every
value is a full 26-character ULID with no padding — the planner decides from the
cast's declared semantics in `pg_cast`, not from the rows.

Measured on Postgres 18, 2M rows, 5000 tenants, index present, with
`enable_seqscan = off` to prove the index is unusable rather than merely
unpreferred:

| Column type | Predicate | Index Cond | Rows from index | Buffers |
| --- | --- | --- | --- | --- |
| `bigint` | `company_id = setting::bigint` | yes | 400 | **6** |
| `uuid` | `company_id = setting::uuid` | yes | 400 | 7 |
| `varchar(26)` | `company_id::text = setting` | yes | 400 | 403 |
| `bigint` | `company_id::text = setting` | no | 2,000,000 | 14,408 |
| `uuid` | `company_id::text = setting` | no | 2,000,000 | 16,375 |
| `char(26)` | `company_id::text = setting` | no | 2,000,000 | 18,336 |

Storage for the same schema at 2M rows, table plus index: bigint 113 MB, uuid
128 MB, varchar(26) 143 MB.

**The wrong column type fails silently** — no error, just full scans on every
tenant-scoped query. If the custom policy manager ever stops being applied, it
throws rather than falling back, so this cannot regress quietly.

## After every migration: series tables, their partitions, then `tenants:rls`

Always after a successful `migrate`, never before, and in this order:

1. `migrate:status --pending=1` — refuse to go on while a migration is pending.
   The option takes a value, which is the exit code when something is pending;
   `--pending` alone succeeds either way.
2. `series:setup` — the series tables are declared in `config/series.php`, not in
   a migration.
3. `series:maintain-partitions` — a partitioned table accepts no row until a
   partition covers it.
4. `tenants:rls` — last, because the policies are derived from the tables that
   exist. A partition that exists by then gets a policy of its own; one created
   later by the daily schedule gets no grant and cannot be read directly.

In Docker, `.docker/entrypoint.d/60-post-migration.sh` runs these in every
container that migrates (`AUTORUN_LARAVEL_MIGRATION`), right after the image's
migration step, and stops at the first step that fails.

Forgetting it fails loudly rather than leaking. `grantPermissions()` grants
table by table over whatever exists at the time, so a table added by a later
migration has no grant and the RLS role cannot read it at all:
`ERROR: permission denied for table <name>`. The command re-grants on every run,
including when the role already exists.

Never put the numeric tenant key in a URL or a serialized response. The public
identifier is `companies.slug`.

Verified 2026-08-10, see docs/superpowers/specs/2026-08-10-tenancy-foundation-design.md (D2, D13).
