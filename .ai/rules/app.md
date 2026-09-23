---
paths:
  - 'app/**'
---

# App

## Business orchestration goes in app/Actions, never app/Services
Business orchestration lives in single-purpose classes under `app/Actions/`. There is no `app/Services/` and none should be created.

Reason: Fortify publishes `App\Actions\Fortify\*` and its auth pipeline is a list of actions, so services would mean two conventions in the same `app/` from day one.

Limit: an operation that is a single Eloquent call does not need an action — that is indirection with no gain. Actions earn their place when there is orchestration: a transaction, multiple writes, events, or an external call.

Decided 2026-08-10, see docs/superpowers/specs/2026-08-10-tenancy-foundation-design.md (D11).

## Public uuid, internal numeric keys
Every model referenced from outside (URL or API) uses App\Models\Concerns\HasPublicUuid and hides `id` and every `*_id` column. Responses use `uuid`, never `id`; references use the `_uuid` suffix, inbound and outbound (`sensor_uuid`).
An inbound reference is validated with App\Rules\ExistsByUuid, never Rule::exists: the rule queries through the model, so its global scopes apply. Like any query, it is scoped to a company only while tenancy is initialized.
Never query a `uuid` column with a value that has not passed a format check (Str::isUuid): on Postgres a non-uuid value is an error (500), not an empty result.
See docs/superpowers/specs/2026-09-18-public-uuid-design.md.

## Do not re-check by hand what the tenancy chain already guarantees
Middleware and the tenant scope exist so that application code does not repeat their checks. Never add a defensive "in depth" guard that re-validates the company of a record reached through a tenant route.

An action that only serves an endpoint belongs to that HTTP layer: its context is the middleware chain, so it does not assert the chain again. Do not guard the precondition either — guarding "this does not work without X" would put a guard in half the application, the way nothing checks that the database is connected. An action that must also run outside HTTP takes the company as a parameter instead.

Tests are the guarantee. A new endpoint placed in the tenant route chain works because that chain is tested; if a defect could slip through, add the test to the chain instead of guarding the caller.
