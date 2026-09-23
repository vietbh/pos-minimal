# PHASE 10.5 — Product / Stock UI

## Scope

Mobile-first Product Catalog and Stock UI integration on top of Phases 10.1–10.4.

## Implemented

- Product list/filter UI accessibility and responsive hardening.
- Product detail status presentation with explicit status markers.
- Product form labels/IDs and accessible help associations.
- Stock list/filter empty states and translated pagination copy.
- Stock detail summary includes a non-color-only stock status marker.
- Stock threshold and stock adjustment forms have explicit labels and help text associations.
- Stock adjustment submit state disables the submit control and exposes `aria-busy`; no business decision is made in JavaScript.
- Stock movement tables have captions and column scopes.
- Existing CSRF, permission, idempotency, transaction and application/domain handlers remain authoritative.
- No product/stock business logic moved into Twig or Stimulus.

## Non-goals

- No changes to product pricing rules.
- No changes to stock mutation semantics or concurrency handling.
- No changes to authorization policy.
- No changes to transaction/idempotency boundaries.

## Validation

Run in the real project environment:

```bash
php bin/phpunit
php bin/console lint:twig templates
php bin/console lint:yaml config
```

The Phase 10.5 contract test additionally checks the product/stock accessibility and backend-boundary invariants.
