---
paths:
  - '**'
---

# General

## Start Eloquent queries with static calls, not Model::query()
Write `Company::where(...)`, `CompanyUser::sole()`, `User::find($id)`, the style of the Laravel documentation. Use `Model::query()` only to hold an empty builder that is filled conditionally (`$query = Model::query(); if (...) { $query->where(...); }`).
Its only gain is IDE typing, which adds little in a framework that is dynamic almost everywhere (model attributes, relations as properties, facades).
Something that is not a query, such as reaching a model's connection, uses an instance: `(new Company)->getConnection()`.
Decided 2026-09-19.

## The dev database is rebuilt by its owner, never by an agent

`migrate`, `migrate:fresh`, `migrate:rollback`, `db:seed` and `tenants:rls` are
not run against the dev database by anyone but the person who owns it.
`migrate:fresh` drops every table, and pre-production that is the normal way to
apply an edited migration — which makes it a routine command with a destructive
effect.

Verify a schema change against the `_test` database instead. The Postgres suite
builds it from scratch on every run and applies the policies afterwards, so
`composer test:postgres` already proves the migration applies and `tenants:rls`
succeeds. Read `pg_policies` and `information_schema` through that connection,
or through a test.
