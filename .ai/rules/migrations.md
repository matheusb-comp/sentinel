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

## Run `php artisan tenants:rls` after every migration

Always after `migrate`, never before: the policies are derived from the schema,
so the tables have to exist. This applies to deployment too, even when `migrate`
fails.

Forgetting it fails loudly rather than leaking. `grantPermissions()` grants
table by table over whatever exists at the time, so a table added by a later
migration has no grant and the RLS role cannot read it at all:
`ERROR: permission denied for table <name>`. The command re-grants on every run,
including when the role already exists.

Never put the numeric tenant key in a URL or a serialized response. The public
identifier is `companies.slug`.

Verified 2026-08-10, see docs/superpowers/specs/2026-08-10-tenancy-foundation-design.md (D2, D13).
