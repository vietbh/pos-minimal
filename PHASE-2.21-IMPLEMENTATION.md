# PHASE 2.21 — Orders / Sales History UX Hardening

## Scope

Harden the existing `/app/orders` and `/app/orders/{id}` application flow without moving transaction/business rules into Twig or Stimulus.

## Implemented

- Server-side order search by order number, customer name and customer phone.
- Status filter with explicit user-facing labels.
- Inclusive `from` / `to` date filtering.
- 100-character search bound.
- 366-day maximum date-range guard.
- Invalid filter values return HTTP 400 rather than HTTP 404.
- Pagination preserves query filters.
- Out-of-range page requests resolve to the last available page.
- Clear-filters action.
- Result count and filtered-state feedback.
- Shared `money_vnd` formatter for order list/detail amounts.
- Order detail preserves list filter/pagination context through the back link.
- Completion/cancellation timestamps are exposed when available.
- Lifecycle actions remain permission-gated and backend-authoritative.
- Added frontend UI contract tests for filter wiring, formatting, permission checks and absence of client-side financial calculations.
- Updated `docs/UI-UX-DESIGN-SYSTEM.md` with Phase 2.21 UX rules.

## Validation

- PHP syntax checks: PASS for changed PHP files.
- Twig lint: PASS (26 templates).
- Container lint: PASS.
- PHPUnit: BLOCKED by the current PHP environment missing `dom`, `mbstring`, and `xmlwriter`.

No claim is made that the PHPUnit suite passes until those PHP extensions are available.
