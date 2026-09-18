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
