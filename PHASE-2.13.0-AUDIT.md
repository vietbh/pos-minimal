# Phase 2.13.0 — POS Audit

## Scope
Audit of the uploaded `mobile-pos-current.zip` against the existing Mobile POS architecture and the POS UX acceptance criteria.

## Findings

### P0 — POS controller was not registered
`assets/stimulus_bootstrap.js` registered `statistics-filter` but not `pos-checkout`. The POS page declared `data-controller="pos-checkout"`, so the checkout JavaScript was not guaranteed to be started by the application bootstrap.

**Impact:** POS interaction/checkout could render as a static form and fail to execute the intended idempotency, timeout, retry, and success handling.

**Fix in 2.13.1/2.13.2:** explicitly register `pos-checkout`.

### P1 — POS UI was only a raw JSON checkout form
The POS page required operators to manually type `[{"productId":1,"quantity":1}]`. There was no product search, one-tap add, cart editor, customer picker, or mobile sales workflow.

**Impact:** the application-level POS use cases existed, but the operator-facing golden path was not practically usable on a phone.

**Fix:** local cart + product search + customer search + quantity controls + checkout surface.

### P1 — Cart was not persistent across refresh
The previous UI stored the cart only in a textarea. A refresh could lose the operator's in-progress sale.

**Fix:** persist the local cart in browser storage. Checkout still sends only product IDs/quantities and remains server-authoritative.

### P1 — Existing application search handlers were not exposed to the POS UI
Product and customer query handlers already existed, but the POS page had no dedicated read endpoints for them.

**Fix:** add bounded GET endpoints under `/app/pos/products` and `/app/pos/customers`, guarded by POS access plus the corresponding view permission.

### P2 — Navigation duplicated across the POS template
The base layout already renders the authenticated application navigation, while the old POS template rendered another partial navigation row.

**Fix:** POS now relies on the shared base navigation.

### P2 — Shared CSS had accumulated duplicate/competing rules
`assets/app.css` contained compact Phase 2.9 rules followed by a second style block for later pages. This is not a correctness blocker, but it increases cascade ambiguity.

**Decision:** do not perform a broad CSS refactor during this patch; POS styles are scoped with `pos-*` classes.

## Preserved invariants

- Checkout remains the only critical business mutation request.
- Server remains authoritative for price, stock, totals, payment validity, debt and order state.
- CSRF and `Idempotency-Key` remain required for checkout.
- Timeout/network failure preserves the cart and reuses the same idempotency key on retry.
- Cart quantity changes are local only; they do not create a request per tap.
- Search is bounded and debounced; stale search responses are ignored.
- No unsafe HTML rendering is used for product/customer results.
- No new domain entity, repository, transaction manager, cache, queue, or business rule was introduced.

## Validation available from uploaded snapshot

- PHP syntax check: PASS for all `src/` and `tests/` PHP files after the patch.
- JavaScript syntax check with Node: PASS for `pos_checkout_controller.js`.
- Full Symfony/PHPUnit execution: NOT RUN in this environment because the uploaded ZIP intentionally excludes `vendor/` and Composer is not installed in the execution environment.

## Phase result

**2.13.0 AUDIT: PASS WITH ACTIONS**

The critical POS gap was UI/bootstrap integration, not a replacement of the existing checkout architecture. The patch proceeds with the smallest practical shell/core implementation while preserving the established backend authority and concurrency/idempotency boundaries.
