# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.38 — STOCK MANAGEMENT UI & STOCK ADJUSTMENT HARDENING

## 0. OBJECTIVE

Implement and harden the Stock Management workspace on top of the repository's existing Product stock model, `StockMovement` history, `AdjustStock` application flow, locking, idempotency, permissions and audit conventions.

This is a **source-first implementation phase**. Inspect the current repository before changing anything. Reuse the existing domain/application infrastructure instead of creating parallel stock logic.

The result must provide a reliable mobile-first stock management experience for:

- stock overview/search
- product stock detail
- stock movement history
- legitimate manual stock adjustment
- permission enforcement
- CSRF protection
- idempotency/double-submit protection
- transaction/concurrency safety
- validation and clear error handling
- tests

Do not weaken existing checkout/sale stock deduction or sale-reversal behavior.

---

## 1. ABSOLUTE SCOPE BOUNDARY

Do NOT redesign or replace the existing stock domain.

Do NOT introduce a second stock quantity field, second StockMovement model, second adjustment service, or frontend-only inventory state.

Do NOT change the frozen bank webhook/reconciliation/payment-reference flow.

Do NOT change historical Order totals or Product prices as part of stock management.

Do NOT put stock business rules in Twig, Stimulus, JavaScript, or controllers.

The backend/domain/application layer remains the source of truth.

If an apparent requirement is not supported by the current domain, inspect the repository and preserve existing semantics rather than inventing a new business rule.

---

## 2. SOURCE-FIRST INSPECTION — REQUIRED BEFORE CODING

Inspect at minimum:

### Product / stock

- `Product` entity/domain object
- Product repository/query handlers
- current `stockQuantity`
- `lowStockThreshold`
- stock mutation methods
- Product locking implementation
- Product persistence mapping

### Stock

- `StockMovement`
- `StockMovementType`
- `StockMovementRepositoryInterface`
- Doctrine repository implementation
- all existing stock movement creation paths
- initial stock semantics
- sale deduction semantics
- sale reversal semantics
- adjustment semantics

### Adjustment application flow

- `AdjustStockInput`
- `AdjustStockHandler`
- `AdjustStockHandlerEntryPoint`
- `AdjustStockResult`
- transaction manager/context
- idempotency port/decision model
- product locking
- actor/session resolution
- audit log behavior

### Existing UI/security

- `StockController`
- stock templates
- Product Management UI from Phase 2.35
- existing admin layout/design system
- pagination/search/filter conventions
- `Permission` enum
- Voters/security configuration
- CSRF conventions
- flash/error conventions

### Tests

- `StockAdjustmentTest`
- `StockAdjustmentConcurrencyTest`
- concurrency worker/support code
- relevant Product tests
- relevant Checkout/stock tests
- security/controller acceptance tests
- existing UI/frontend tests

Also inspect migrations/schema/indexes before deciding whether any persistence change is actually necessary.

---

## 3. CURRENT STOCK MODEL IS AUTHORITATIVE

The displayed current stock must come from the persisted Product/domain state.

Conceptually:

```text
Product.stockQuantity
        ↓
Stock Management UI
```

Do not calculate current stock in Twig from movement history.

Do not calculate current stock in JavaScript.

Do not maintain a browser-local inventory counter.

Movement history is historical evidence, not a replacement for the authoritative Product stock quantity.

---

## 4. STOCK MANAGEMENT INDEX

Harden the existing stock index at `/admin/stock` rather than creating a competing route unless the repository clearly requires it.

The screen should support the existing Product search flow and, where the current query infrastructure supports it, expose:

- product name
- SKU
- current stock
- low-stock indication based on the existing Product/domain field/rule
- active/inactive state if relevant to existing Product semantics
- link to stock detail

Use backend-side search/querying.

Do not load the entire product catalog merely to filter it in Twig.

If pagination infrastructure already exists, use it rather than inventing another paginator.

Preserve query/filter state when navigating pages.

---

## 5. LOW-STOCK SEMANTICS

Inspect the existing `lowStockThreshold` semantics before implementing UI behavior.

If the domain already exposes a canonical low-stock rule, use it.

Do not silently redefine the rule.

Do not hard-code a threshold in Twig/Stimulus.

If no canonical boolean exists, derive the presentation from the existing authoritative threshold and stock quantity in an appropriate application/query layer, not in the template.

Clearly distinguish:

- out of stock
- low stock
- normal stock

only if those concepts are actually supported by the repository's current semantics.

Avoid adding a new persisted status merely for presentation.

---

## 6. STOCK DETAIL

Harden `/admin/stock/{productId}`.

The detail screen should clearly show:

- product name
- SKU where present
- current stock
- low-stock threshold where present
- stock status if supported
- adjustment action when permitted
- movement history

The screen must remain read-only for users without stock-adjust permission.

Opening the detail page must never mutate stock.

Do not expose internal database identifiers unnecessarily in the UI.

---

## 7. STOCK MOVEMENT HISTORY

Use persisted `StockMovement` records as the historical source.

Display, where available and appropriate:

- date/time
- movement type
- quantity before
- quantity change
- quantity after
- reason
- actor/user
- related Order when applicable

Preserve historical values exactly as persisted.

For sale/sale-reversal movements, do not fabricate an adjustment reason.

For adjustment movements, show the persisted reason if present.

Do not allow editing or deleting historical stock movements from this phase.

Do not treat AuditLog as a substitute for StockMovement.

`StockMovement` remains the business/history record for inventory changes; `AuditLog` remains audit history according to the existing architecture.

---

## 8. MOVEMENT TYPE PRESENTATION

Use the existing `StockMovementType` enum as the source of truth.

Current known types include:

- `INITIAL`
- `SALE`
- `SALE_REVERSAL`
- `ADJUSTMENT`

Do not invent additional movement types just to make the UI easier.

Presentation labels may be human-friendly, but the underlying value must remain the enum/domain value.

If translations already exist, reuse them.

---

## 9. MANUAL STOCK ADJUSTMENT

The existing `AdjustStockHandler` is the canonical mutation path.

Do not duplicate its logic in `StockController`.

The controller should remain a thin HTTP adapter:

```text
HTTP request
  ↓
permission / CSRF
  ↓
application input
  ↓
AdjustStockHandler
  ↓
transaction + product lock + idempotency
  ↓
Product mutation + StockMovement + AuditLog
```

Reuse the current handler and harden it only where inspection demonstrates a real defect or missing requirement.

---

## 10. ADJUSTMENT VALIDATION

Preserve and verify the existing validation rules.

At minimum:

- product ID must be valid
- quantity change must not be zero
- resulting stock must never be negative
- reason length must respect the domain/storage limit
- idempotency key must be present

Use integer stock quantities.

Do not use floating-point arithmetic for stock quantities.

Do not accept arbitrary decimal quantities unless the existing domain explicitly supports them.

Trim and normalize optional reason input consistently with existing domain behavior.

---

## 11. POSITIVE / NEGATIVE ADJUSTMENT UX

The UI should make the direction of the adjustment obvious.

A practical mobile form may expose:

- Increase stock
- Decrease stock
- quantity
- reason
- current stock
- resulting stock preview only if it can be computed safely from authoritative values
- confirmation action

If a client-side preview is shown, it is only a helper. The backend must recalculate and validate the final result under the transaction/lock.

Never trust a hidden `quantityAfter` field sent by the browser.

Never trust a browser-supplied current stock value.

The canonical adjustment is the signed `quantityChange` handled by the application layer.

---

## 12. IDEMPOTENCY — DO NOT BREAK EXISTING CONTRACT

The current adjustment flow already uses an idempotency port and operation name.

Preserve that mechanism.

Verify behavior for:

1. same user + same idempotency key + same fingerprint → replay, no second movement;
2. same user + same key + different fingerprint → reject according to existing idempotency semantics;
3. same key while the first request is executing → do not execute a second adjustment;
4. retry after completed request → return the stored result;
5. failed operation → preserve the repository's existing failure/idempotency semantics.

Do not create a second idempotency table/framework.

Do not generate an idempotency key only in a way that makes browser refresh accidentally repeat a business mutation.

If the current HTML form uses a generated hidden key, inspect whether the generated value is safe for the project's retry semantics and improve it only within the existing idempotency architecture.

---

## 13. CONCURRENCY HARDENING

Stock adjustment must remain safe under concurrent requests.

The critical section is conceptually:

```text
BEGIN TRANSACTION
  ↓
lock Product row
  ↓
read authoritative stock
  ↓
validate quantityAfter >= 0
  ↓
mutate Product stock
  ↓
create StockMovement with before/change/after
  ↓
create required AuditLog
  ↓
flush
COMMIT
```

Preserve the existing `ProductLockingInterface` approach.

Do not replace row locking with a browser-side check.

Do not read stock before the lock and then assume that value is still current.

Verify that `quantityBefore`, `quantityChange`, and `quantityAfter` stored in `StockMovement` describe the same atomic mutation.

Verify that two different idempotency keys cannot both cause an oversell through a lost-update race.

Do not weaken checkout stock locking while hardening adjustment.

---

## 14. STOCK HISTORY CONSISTENCY

For every successful manual adjustment:

```text
quantityBefore = locked Product.stockQuantity before mutation
quantityAfter  = quantityBefore + quantityChange
Product.stockQuantity = quantityAfter
StockMovement.quantityBefore = quantityBefore
StockMovement.quantityChange = quantityChange
StockMovement.quantityAfter = quantityAfter
```

These values must be internally consistent.

For a failed transaction, none of the following may be partially committed:

- Product stock mutation
- StockMovement
- required AuditLog

Reuse the existing transaction boundary.

---

## 15. AUDIT INTEGRITY

Preserve the existing audit convention.

The current adjustment flow records `STOCK_ADJUSTED` with old/new values.

Do not remove this audit behavior merely because StockMovement already exists.

Do not log secrets or unrelated request data.

If audit behavior is already correct, leave it intact.

---

## 16. PERMISSIONS

Use the existing permission model.

At minimum preserve the distinction between:

- stock view permission
- stock adjustment permission

The exact enum/value names must come from the repository.

Enforce authorization server-side with the existing security/Voter/permission mechanism.

Do not rely only on hiding the adjustment form in Twig.

A direct POST without permission must be rejected.

A user with view-only access must be able to inspect stock but must not mutate it.

---

## 17. CSRF

Preserve Symfony CSRF protection for the browser adjustment form.

The server must validate the token.

Do not accept a mutation merely because an idempotency key is present.

Do not expose a reusable CSRF token in client-side JavaScript unnecessarily.

Reuse the existing project's CSRF naming/conventions where possible.

---

## 18. ERROR HANDLING

Keep the controller/application boundary clean.

Expected business failures should become clear user-facing messages without leaking internal stack traces or infrastructure details.

At minimum distinguish where the existing architecture supports it:

- invalid input
- unauthorized/forbidden action
- product not found
- insufficient stock
- duplicate/in-progress idempotent request
- unexpected server failure

Do not catch every exception and silently report success.

A failed adjustment must never redirect with a success message.

Preserve the repository's existing exception-to-HTTP/message conventions where available.

---

## 19. MOBILE-FIRST UI HARDENING

Use the project's existing UI design system.

Target:

- minimum supported width: 360px
- touch targets >= 48px
- body text around 16px
- form inputs around 17–18px
- buttons around 16–18px
- clear headings around 24px
- stock quantity visually prominent
- high contrast
- no flashy gradients/neon styling

Avoid forcing horizontal scrolling for the normal stock workflow.

If movement history requires a table, make it usable on narrow screens using the project's established responsive pattern.

Do not move business logic into Stimulus just to make the UI interactive.

---

## 20. ACCESSIBILITY

Use:

- real `<label>` elements
- meaningful button text
- visible focus states from the existing design system
- sufficient contrast
- semantic headings
- accessible error messages
- keyboard-operable controls

Do not rely on color alone to communicate low/out-of-stock state.

---

## 21. SEARCH / PAGINATION / QUERY SAFETY

If stock index currently supports search, harden the query rather than replacing it with client-side filtering.

Use repository/query handlers for:

- name
- SKU
- supported filters
- pagination

Do not interpolate raw query strings into SQL.

Preserve existing Doctrine/query conventions.

If the current repository does not have a suitable paginated stock query, add the smallest reusable query/application component consistent with the existing architecture rather than embedding a large Doctrine query in the controller.

---

## 22. ROUTING / CONTROLLER DESIGN

Preserve the existing routes where possible:

- `admin_stock_index`
- `admin_stock_show`
- `admin_stock_adjust`

Do not create duplicate routes for the same action.

Keep controllers thin.

Controllers may:

- read request data
- authorize
- validate CSRF
- construct application input
- call application handlers
- translate result/errors to HTTP/UI response

Controllers must not:

- directly mutate Product stock
- construct StockMovement for business mutation
- implement locking
- implement idempotency
- calculate canonical stock state
- bypass the application service

---

## 23. DO NOT EDIT HISTORICAL SALES

Stock management must not mutate:

- historical Order item quantity
- historical Order item price
- historical Order totals
- Payment records
- PaymentReference reconciliation state

Stock adjustment is inventory correction, not order editing.

If a stock discrepancy is caused by a sale/refund business flow, use the existing sale/sale-reversal domain flow rather than manually editing an old movement.

---

## 24. DO NOT USE AUDIT LOG AS INVENTORY LEDGER

Never reconstruct stock history solely from `AuditLog`.

Never replace `StockMovement` with `AuditLog`.

The canonical responsibilities remain:

```text
Product.stockQuantity
    = current authoritative inventory state

StockMovement
    = inventory business history

AuditLog
    = audit trail
```

Preserve this separation.

---

## 25. TEST MATRIX — REQUIRED

Add or update tests following existing project conventions.

### A. Application/domain happy path

Test:

- positive adjustment
- negative adjustment
- resulting stock
- StockMovement before/change/after
- reason persistence
- audit persistence where applicable

### B. Validation

Test:

- zero quantity rejected
- negative resulting stock rejected
- invalid product rejected
- missing idempotency key rejected
- overlong reason rejected

### C. Idempotency

Test:

- same key + same fingerprint → one movement
- replay returns same result
- same key + different request → rejected
- in-progress same-key request → rejected/not duplicated
- failed operation follows existing idempotency semantics

### D. Concurrency

Test real concurrency using the project's existing concurrency test infrastructure.

At minimum cover:

```text
stock = N

request A: -X
request B: -Y

assert final stock is exactly N-X-Y when both are valid
or exactly one succeeds when only one can succeed

assert no negative stock
assert no lost update
assert movement history is consistent
```

Also test concurrent adjustments against the same product with different idempotency keys.

Do not fake concurrency with two sequential calls and call that a concurrency test.

### E. Authorization

Test:

- view permission allows GET
- missing view permission denied
- adjustment permission allows POST
- view-only user cannot POST adjustment

Use the actual project's roles/Voters/permission matrix.

### F. CSRF

Test invalid/missing CSRF cannot mutate stock.

### G. HTTP acceptance

Where the repository has acceptance tests, cover:

- stock index
- stock detail
- adjustment success
- validation failure
- permission failure
- CSRF failure

### H. Regression

Run existing Product, Checkout, stock, idempotency and security tests that could be affected.

---

## 26. PERFORMANCE

Avoid N+1 queries in stock history.

If movement history displays actor/order data, inspect Doctrine fetch behavior and query accordingly.

Do not load an unbounded movement history list for a product if the project already has pagination conventions or if the dataset can grow significantly.

Prefer a repository query with explicit ordering:

```text
created_at DESC
id DESC
```

where deterministic ordering is required.

Do not add speculative caching to mutable stock quantities.

Current stock should remain strongly consistent.

---

## 27. SECURITY / DATA EXPOSURE

Do not expose:

- webhook payloads
- bank credentials
- provider secrets
- internal exception traces
- sensitive audit metadata

Stock screens may display ordinary business information needed by authorized staff.

Do not weaken authentication or authorization to make the screen easier to use.

---

## 28. IMPLEMENTATION ORDER

Implement in this order:

1. inspect existing stock/product/application/security/test architecture;
2. identify real gaps in the current stock UI and adjustment flow;
3. harden query/read models for stock index/detail/history;
4. harden controller boundaries and permission/CSRF handling;
5. harden adjustment application flow only where required;
6. preserve transaction + product lock + idempotency behavior;
7. harden responsive/mobile UI;
8. add/update tests;
9. run targeted tests;
10. run broader regression tests;
11. inspect git diff for accidental scope creep.

Do not rewrite working infrastructure merely for stylistic reasons.

---

## 29. DEFINITION OF DONE

Phase 2.38 is complete only when all applicable conditions are true:

- stock index is usable on mobile;
- stock search/filtering is backend-driven;
- stock detail is authoritative and read-safe;
- movement history uses persisted StockMovement records;
- movement types are represented from the existing enum;
- authorized staff can perform legitimate adjustments;
- unauthorized users cannot adjust stock;
- CSRF is enforced;
- zero/invalid/negative-result adjustments are rejected;
- Product stock and StockMovement are atomically consistent;
- concurrent different-key adjustments cannot cause lost updates/oversell;
- same-key retries do not create duplicate movements;
- audit behavior is preserved;
- historical Orders/Payments are untouched;
- bank webhook/reconciliation remains untouched;
- tests cover happy path, validation, idempotency, concurrency, security and HTTP behavior;
- existing relevant regression tests pass;
- no business logic has leaked into Twig/Stimulus/JS/controller code.

---

## 30. FINAL IMPLEMENTATION REPORT

After implementation, report:

1. files created/changed;
2. routes added/changed;
3. application/domain changes;
4. UI changes;
5. security/permission changes;
6. transaction/idempotency/concurrency behavior;
7. tests added/changed;
8. exact test commands executed and results;
9. any repository limitation or intentionally deferred item;
10. confirmation that webhook/reconciliation/payment-reference flow was not modified.

Do not claim tests passed unless they were actually executed.

