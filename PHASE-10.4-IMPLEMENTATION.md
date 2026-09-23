# PHASE 10.4 — Order / Customer / Debt UI

## Scope

This phase integrates the Order, Customer, and Debt screens with the Phase 10.1 design foundation and Phase 10.2 authenticated shell without moving business rules into Twig or JavaScript.

## Implemented

- Mobile-first Order / Customer / Debt presentation foundation.
- Consistent 48px touch targets and 52px primary mutation actions.
- Search forms expose semantic `role="search"` and explicit labels.
- Empty states explain the next useful action without adding client-side business logic.
- Customer create/edit form uses explicit labels, autocomplete hints, CSRF token, and server POST semantics.
- Customer detail preserves server-side permission checks for order/debt history.
- Order detail preserves server-side authorization for cancel/refund actions.
- Debt detail preserves backend-authoritative payment mutation, CSRF, idempotency, and request correlation headers.
- Status indicators use a visual marker in addition to color.
- Mobile 360px hardening and reduced-motion baseline.
- Technical error codes are not surfaced to end users by mutation controllers.
- Existing order/debt financial calculations remain backend authoritative.

## Non-goals

- No Domain/Application/Repository changes.
- No changes to transaction boundaries.
- No changes to stock, payment, debt, order lifecycle, or idempotency semantics.
- No frontend authorization bypass.

## Validation performed in the package environment

- PHP syntax check for the new contract test: pass.
- JavaScript syntax checks for the modified controllers: pass.
- ZIP integrity: verified during packaging.

Full PHPUnit/Twig/YAML application validation must be run in the real project after restoring `vendor/` because the distributable source ZIP intentionally excludes `vendor/`.
