---
paths:
  - '**/*.php'
---

# General

## Start Eloquent queries with static calls, not Model::query()
Write `Company::where(...)`, `CompanyUser::sole()`, `User::find($id)`, the style of the Laravel documentation. Use `Model::query()` only to hold an empty builder that is filled conditionally (`$query = Model::query(); if (...) { $query->where(...); }`).
Its only gain is IDE typing, which adds little in a framework that is dynamic almost everywhere (model attributes, relations as properties, facades).
Something that is not a query, such as reaching a model's connection, uses an instance: `(new Company)->getConnection()`.
Decided 2026-09-19.
