# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.38
# STOCK MANAGEMENT UI & STOCK ADJUSTMENT HARDENING

## 0. OBJECTIVE

Implement and harden the Stock Management experience for the existing POS application.

The current repository already contains:

- `StockMovement`
- `StockMovementType`
- `AdjustStockHandler`
- `AdjustStockHandlerEntryPoint`
- stock permissions
- stock admin controller
- stock index/detail templates
- stock adjustment concurrency tests
- idempotency infrastructure
- product-level stock locking

This phase must build on those existing capabilities rather than replacing them.

Target flow:

```text
Admin / Stock
    ↓
Search products
    ↓
Stock overview
    ↓
Product stock detail
    ↓
Current quantity
    ↓
Low-stock status
    ↓
Movement history
    ↓
Stock adjustment
    ↓
Atomic/idempotent mutation
    ↓
StockMovement + AuditLog
```

The backend/domain remains the source of truth.

---

# 1. ABSOLUTE SCOPE BOUNDARY

The following areas are FROZEN:

```text
Bank webhook
Webhook reconciliation
PaymentReference matching
Payment persistence
Bank notification ingestion
Payment idempotency
Webhook transaction boundaries
```

Do not modify them.

This phase must not redesign:

```text
Order checkout
Payment
Bank transfer
Webhook
PaymentReference
```

unless a compilation/test dependency genuinely requires an unrelated correction. Prefer leaving those files untouched.

---

# 2. SOURCE-FIRST REQUIREMENT

Before changing code, inspect the actual repository.

Inspect at minimum:

```text
src/Domain/Product/Product.php
src/Domain/Stock/StockMovement.php
src/Domain/Stock/Enum/StockMovementType.php
src/Domain/Stock/Repository/StockMovementRepositoryInterface.php
src/Infrastructure/Persistence/Doctrine/Repository/StockMovementRepository.php

src/Application/Product/Command/AdjustStock/*
src/Application/Product/Query/SearchProducts/*
src/Application/Security/Permission.php

src/Controller/Admin/StockController.php

templates/admin/stock/index.html.twig
templates/admin/stock/show.html.twig

tests/Integration/Application/Product/StockAdjustmentTest.php
tests/Integration/Application/Product/StockAdjustmentConcurrencyTest.php
tests/Support/StockAdjustmentConcurrencyWorker.php

existing product admin UI
existing pagination/search patterns
existing flash-message patterns
existing Stimulus/Turbo patterns
existing Voter/security implementation
existing money/date formatting
```

Also inspect the most recent:

```text
docs/PHASE-2.35-IMPLEMENTATION.md
docs/PHASE-2.36-IMPLEMENTATION.md
docs/PHASE-2.37-IMPLEMENTATION.md
```

if present.

Do not assume the current source matches a previous prompt.

---

# 3. EXISTING STOCK BUSINESS INVARIANTS

Preserve these invariants.

## Stock can never be negative

```text
stockQuantity >= 0
```

## Stock adjustment cannot be zero

```text
quantityChange != 0
```

## Stock mutation must create StockMovement

An adjustment must produce:

```text
Product stock change
+
StockMovement
+
AuditLog
```

within the existing transaction boundary.

Do not bypass `AdjustStockHandler`.

## Stock movement records are historical

Do not edit or delete historical StockMovement records from the UI.

---

# 4. EXISTING ADJUSTMENT PIPELINE

The repository already has an application command equivalent to:

```text
AdjustStockHandler
```

Reuse it.

Do NOT create a second:

```text
StockAdjustmentService
StockAdjustmentControllerLogic
Product::setStockQuantity()
```

for the web UI.

The controller must call the existing application entry point.

Expected conceptual flow:

```text
HTTP POST
    ↓
CSRF validation
    ↓
Permission STOCK_ADJUST
    ↓
Idempotency key
    ↓
AdjustStockHandler
    ↓
transaction
    ↓
product lock
    ↓
validate resulting stock
    ↓
mutate Product
    ↓
create StockMovement
    ↓
create AuditLog
    ↓
flush
    ↓
complete idempotency
```

Do not move business logic into Twig or JavaScript.

---

# 5. STOCK INDEX

Harden:

```text
/admin/stock
```

using the existing route if possible.

The page should support:

```text
Product search
Current stock
Low-stock visibility
Product navigation
```

Search must be server-side.

Do not load all Products into Twig and filter there.

---

# 6. PRODUCT SEARCH

Search should support the existing Product search contract.

At minimum, if supported by the current repository:

```text
Product name
SKU
```

Reuse:

```text
SearchProductsHandler
SearchProductsInput
```

or the existing Product query mechanism.

Do not implement a second search query in `StockController`.

---

# 7. STOCK LIST DATA

For each product, display where appropriate:

```text
Product name
SKU
Current stock
Low-stock status
Unit
Active/inactive status
```

Only display fields supported by the actual Product model.

Do not invent warehouse/location data.

This project currently models product-level stock, not multi-warehouse stock, unless the source proves otherwise.

---

# 8. LOW-STOCK STATUS

Use the existing:

```text
Product::getLowStockThreshold()
```

and:

```text
Product::getStockQuantity()
```

to determine low-stock state.

Conceptually:

```text
stockQuantity <= lowStockThreshold
```

but verify the project's existing semantics before implementing.

Do not hardcode a threshold.

Do not create a second low-stock field.

Do not persist a derived boolean such as:

```text
isLowStock
```

unless the existing domain already has it.

---

# 9. LOW-STOCK UX

Make low stock immediately visible.

Example:

```text
Current stock
3

Low threshold
5

LOW STOCK
```

For zero stock:

```text
OUT OF STOCK
```

only if that terminology is consistent with the existing UI.

The visual treatment must remain accessible and not depend only on color.

Example:

```text
LOW STOCK
3 units
```

rather than only a red/amber dot.

---

# 10. STOCK DETAIL

The stock detail page should show:

```text
Product
SKU
Current stock
Low-stock threshold
Stock status
```

and:

```text
Stock movement history
```

Use the existing route:

```text
/admin/stock/{productId}
```

unless the source already defines a different route.

---

# 11. MOVEMENT HISTORY

Display historical movements.

At minimum:

```text
Date
Movement type
Quantity before
Quantity change
Quantity after
Reason
```

If appropriate and already supported:

```text
User
Order
```

Do not expose internal IDs unnecessarily.

---

# 12. MOVEMENT TYPE LABELS

The current domain includes:

```text
INITIAL
SALE
SALE_REVERSAL
ADJUSTMENT
```

Do not invent additional movement types.

Render labels using the existing translation terminology.

Do not rename domain enum values merely for UI presentation.

---

# 13. MOVEMENT DIRECTION

Make positive/negative quantity changes visually clear.

For example:

```text
+10
-3
```

but do not rely only on color.

Use accessible text/signs.

Do not alter the persisted `quantityChange`.

---

# 14. MOVEMENT HISTORY MUST BE READ-ONLY

Users must not be able to:

```text
edit movement
delete movement
change quantityBefore
change quantityAfter
change createdAt
```

Historical stock movements are business history.

If an incorrect adjustment was made, the correction must be another adjustment, not editing history.

---

# 15. MOVEMENT PAGINATION

Inspect the existing movement repository and UI.

If the current history loads the entire movement collection:

```text
findByProductId()
```

and this is acceptable only for small data, evaluate whether Phase 2.38 needs repository-level pagination.

For products with many movements, prefer backend pagination.

Do not implement pagination by:

```text
array_slice()
```

after loading all historical movements.

If adding pagination, extend the repository interface cleanly.

Do not break existing callers.

---

# 16. STOCK SEARCH PAGINATION

If Product search already has pagination support, reuse it.

If it does not, inspect existing Product catalog pagination patterns before adding one.

Do not create a different pagination convention only for Stock.

---

# 17. STOCK ADJUSTMENT FORM

The adjustment UI should make the operation explicit.

Fields:

```text
Quantity change
Reason
```

Example:

```text
Stock adjustment

Current stock: 10

Quantity change
[ +5 ]

Reason
[ Physical count correction ]

[ Confirm adjustment ]
```

Do not silently assume:

```text
+1
```

or:

```text
-1
```

if no value is provided.

---

# 18. QUANTITY VALIDATION

The UI may provide basic validation:

```text
quantityChange != 0
integer
```

but backend validation remains authoritative.

Backend must reject:

```text
0
```

and negative resulting stock.

Do not rely on HTML validation alone.

---

# 19. RESULTING STOCK PREVIEW

If implemented, a preview may show:

```text
Current stock: 10
Change: +5
New stock: 15
```

But this is informational only.

Do not treat the preview as authoritative.

Concurrent changes can happen between rendering and submission.

The backend must recalculate:

```text
quantityBefore
quantityAfter
```

under the existing product lock.

---

# 20. CONCURRENCY

This is a critical requirement.

Do not weaken the existing product locking.

Concurrent adjustments must preserve:

```text
no lost update
no negative stock
one valid result per successful mutation
correct StockMovement history
correct AuditLog history
```

Existing tests already cover scenarios such as:

```text
concurrent positive adjustments
concurrent overdraw
concurrent same-key adjustment
```

Keep those guarantees.

---

# 21. SAME-KEY IDEMPOTENCY

The same idempotency key must not produce two stock mutations.

Example:

```text
request A
idempotency key = XYZ

request B
idempotency key = XYZ
```

must not create:

```text
+5
+5
```

twice.

The existing `IdempotencyPort` is authoritative.

Do not implement a frontend-only idempotency mechanism as a replacement.

---

# 22. DIFFERENT-KEY CONCURRENCY

Different idempotency keys represent independent operations.

Example:

```text
A: +5
B: +3
```

Expected:

```text
stock +8
```

without lost updates.

The existing product lock must remain intact.

---

# 23. CONCURRENT OVERDRAW

Example:

```text
Current stock = 2

Request A = -2
Request B = -2
```

Exactly one operation may successfully consume the available stock.

The other must fail without creating a StockMovement.

Do not allow:

```text
stock = -2
```

or:

```text
two successful movements
```

for the same available quantity.

---

# 24. CSRF

Stock adjustment POST must retain CSRF protection.

Do not remove:

```text
csrf_token()
```

or replace it with JavaScript-only validation.

Use the project's existing CSRF conventions.

---

# 25. AUTHORIZATION

Stock viewing:

```text
STOCK_VIEW
```

Stock history:

```text
STOCK_HISTORY_VIEW
```

Stock adjustment:

```text
STOCK_ADJUST
```

must follow the actual permission architecture.

Inspect the existing Voter/security setup.

Do not rely on hiding the adjustment form.

Direct POST access must also be denied without permission.

---

# 26. PERMISSION SEPARATION

If the project already distinguishes:

```text
STOCK_VIEW
STOCK_HISTORY_VIEW
STOCK_ADJUST
```

preserve that separation.

Do not automatically grant:

```text
STOCK_ADJUST
```

to everyone who has:

```text
STOCK_VIEW
```

unless the existing security policy explicitly does so.

---

# 27. ADJUSTMENT RESPONSE

After successful adjustment:

```text
Stock updated.
```

and display the updated state.

Prefer redirect-after-POST.

Do not allow browser refresh to resubmit the adjustment.

Expected:

```text
POST adjustment
    ↓
success
    ↓
redirect GET stock detail
```

---

# 28. PRG

Use Post/Redirect/Get for the normal HTML flow.

This prevents:

```text
refresh
    ↓
repeat POST
```

and provides a clean browser history.

The idempotency key remains a second layer of protection.

---

# 29. IDEMPOTENCY KEY GENERATION

The current UI generates an idempotency key.

Inspect that implementation.

It must be unique per intended submission.

Do not create the same static key for every adjustment.

Do not derive the key solely from:

```text
productId + quantity
```

because two legitimate adjustments may have identical values.

---

# 30. RETRY SAFETY

If a request times out after the server has successfully committed the adjustment, retrying with the same idempotency key must replay the existing result rather than applying the adjustment twice.

The UI must not generate a new key automatically for an uncertain retry if the original operation may have succeeded.

Respect the existing idempotency contract.

---

# 31. FAILURE UX

If adjustment fails:

```text
stock remains unchanged
no false success message
no fake movement
```

Display a safe error.

Do not expose stack traces or SQL errors.

If the backend reports insufficient stock, display the actual domain-safe message.

---

# 32. NO FRONTEND STOCK MUTATION

Stimulus/JavaScript must not directly mutate persisted stock.

Invalid:

```text
product.stockQuantity += 5
```

as a business mutation.

Frontend may calculate a display preview, but backend remains authoritative.

---

# 33. NO DIRECT PRODUCT SETTER

Do not call:

```text
Product::setStockQuantityForAdjustment()
```

from a controller.

The source explicitly indicates that controlled stock workflows must create the corresponding StockMovement in the same transaction.

Use:

```text
AdjustStockHandler
```

---

# 34. AUDIT LOG

Every successful stock adjustment must continue to produce the existing AuditLog.

Do not remove or bypass:

```text
STOCK_ADJUSTED
```

or the existing audit implementation.

Do not create a second audit system for the UI.

---

# 35. STOCK MOVEMENT

Every successful adjustment must create:

```text
StockMovementType::ADJUSTMENT
```

with correct:

```text
quantityBefore
quantityChange
quantityAfter
reason
user
session
product
```

Do not create movements in the controller.

---

# 36. REASON

Normalize reason according to the domain.

An empty reason should follow the existing behavior.

Do not make reason mandatory unless the current business specification requires it.

If introducing a required reason is desired, treat it as a business-rule change and do not silently change the current contract.

---

# 37. LOW STOCK OVERVIEW

If practical with the existing query architecture, provide an obvious way to find:

```text
Low stock products
```

Do not load every product and calculate this in Twig.

Prefer repository/query-level filtering.

If the current Product query layer already supports it, reuse it.

---

# 38. OUT-OF-STOCK PRODUCTS

Provide a clear state for:

```text
stockQuantity = 0
```

Do not confuse:

```text
OUT OF STOCK
```

with:

```text
LOW STOCK
```

unless the project's terminology explicitly combines them.

---

# 39. ACTIVE / INACTIVE PRODUCTS

Stock records may exist for inactive products.

Do not delete or hide historical stock data simply because a Product is inactive.

If filtering inactive products in the stock list, make the behavior explicit and consistent with existing Product management conventions.

---

# 40. PRODUCT DELETE SAFETY

Do not introduce product deletion through Stock UI.

The Stock page must not delete Products.

Existing StockMovement foreign-key history uses restrictive relationships.

Preserve historical integrity.

---

# 41. ORDER STOCK MOVEMENTS

Stock movements may reference Orders.

Do not change:

```text
SALE
SALE_REVERSAL
```

behavior in this phase.

Do not alter checkout stock deduction or refund reversal logic.

This phase only manages administrative adjustment UI and stock visibility.

---

# 42. SALE STOCK INTEGRITY

Opening Stock pages must not trigger:

```text
stock recalculation
stock adjustment
stock movement creation
```

Stock is displayed from persisted Product/StockMovement data.

---

# 43. MOBILE UI

Target:

```text
360px+
```

Use:

```text
large touch targets
clear stock number
clear adjustment action
readable movement history
```

Avoid requiring horizontal scrolling for the basic stock overview.

For detailed movement tables, a responsive card layout may be preferable on narrow screens if consistent with existing UI.

---

# 44. VISUAL PRIORITY

Stock detail priority:

```text
1. Product
2. Current stock
3. Stock status
4. Low-stock threshold
5. Adjust stock
6. Movement history
```

The current quantity should be visually prominent.

Example:

```text
Current stock
18
units
```

---

# 45. ACCESSIBILITY

Adjustment form:

```text
label
input
error
button
```

must be accessible.

Do not use placeholder text as the only label.

Error messages should be associated with fields where possible.

Stock status must not depend only on color.

---

# 46. FLASH MESSAGES

Reuse the existing flash-message conventions.

Success:

```text
Stock updated.
```

Failure:

```text
Unable to adjust stock.
```

or the existing domain-safe message.

Do not leak:

```text
exception class
stack trace
SQL
database details
```

---

# 47. QUERY PERFORMANCE

Avoid N+1 queries.

Stock index should not perform a query per product for:

```text
stock status
category
```

if those values already belong to Product.

Stock detail should avoid loading unrelated relations.

Movement history should query only the required data.

---

# 48. DATABASE INDEXES

Inspect current indexes before adding migrations.

The existing `StockMovement` already has indexes for:

```text
product + created_at
order
user + created_at
type + created_at
```

Do not add duplicate indexes.

If pagination/filtering requires a missing index, add only the minimal migration justified by the actual query.

---

# 49. TEST REQUIREMENTS

Preserve and extend tests.

At minimum:

```text
Stock page loads
Search works
Stock detail loads
Missing product -> 404
Permission denied
Adjustment succeeds
Zero adjustment rejected
Overdraw rejected
StockMovement created
AuditLog created
Resulting stock correct
Reason persisted
Same-key idempotency
Different-key concurrency
Concurrent overdraw
PRG after POST
CSRF rejection
```

---

# 50. STOCK HISTORY TEST

Given:

```text
INITIAL +10
ADJUSTMENT +5
SALE -2
SALE_REVERSAL +2
```

the history should preserve the correct chronological records.

Do not alter existing movement records.

---

# 51. ADJUSTMENT TEST

Given:

```text
stock = 10
```

POST:

```text
quantityChange = +5
reason = "Physical count"
```

expect:

```text
stock = 15
movement.quantityBefore = 10
movement.quantityChange = +5
movement.quantityAfter = 15
movement.type = ADJUSTMENT
reason = "Physical count"
```

and one audit entry.

---

# 52. NEGATIVE ADJUSTMENT TEST

Given:

```text
stock = 10
```

POST:

```text
quantityChange = -3
```

expect:

```text
stock = 7
```

with:

```text
ADJUSTMENT
before = 10
change = -3
after = 7
```

---

# 53. ZERO TEST

POST:

```text
quantityChange = 0
```

must fail.

Verify:

```text
stock unchanged
no StockMovement
no successful AuditLog
```

---

# 54. OVERDRAW TEST

Given:

```text
stock = 3
```

POST:

```text
quantityChange = -4
```

must fail.

Verify:

```text
stock = 3
no StockMovement
```

---

# 55. SAME-KEY TEST

Two requests:

```text
product = X
change = +5
key = SAME
```

must result in exactly one mutation.

Verify:

```text
stock += 5
one movement
one audit
```

---

# 56. DIFFERENT-KEY CONCURRENCY TEST

Two concurrent requests:

```text
+5
+3
```

must result in:

```text
+8
```

with two movements.

---

# 57. CONCURRENT OVERDRAW TEST

Given:

```text
stock = 2
```

two concurrent:

```text
-2
-2
```

must result in:

```text
one success
one failure
stock = 0
one movement
```

---

# 58. AUTHORIZATION TEST

Verify:

```text
STOCK_VIEW
```

can view the page.

Verify a user without:

```text
STOCK_ADJUST
```

cannot perform adjustment even by direct POST.

If `STOCK_HISTORY_VIEW` is separately enforced in the project, verify that too.

---

# 59. CSRF TEST

Invalid CSRF must reject the POST.

Verify:

```text
no stock mutation
no movement
```

---

# 60. PRG TEST

Successful POST must redirect to:

```text
admin_stock_show
```

and refreshing the resulting GET must not repeat the adjustment.

---

# 61. ACCEPTANCE SCENARIO

The complete flow must work:

```text
1. Login
2. Open Stock
3. Search product by name
4. Search product by SKU
5. Open product stock detail
6. See current stock
7. See low-stock status
8. See movement history
9. Enter quantity adjustment
10. Enter reason
11. Submit
12. Backend locks product
13. Backend validates resulting stock
14. Stock changes atomically
15. StockMovement is created
16. AuditLog is created
17. Redirect to stock detail
18. Updated stock is visible
19. New movement appears in history
```

---

# 62. REGRESSION

Do not break:

```text
Product Management
POS Checkout
Order Completion
Cancel
Refund
Stock Sale movement
Stock Reversal movement
```

The stock adjustment UI must not change checkout semantics.

---

# 63. WEBHOOK REGRESSION

Do not modify webhook/reconciliation code.

Run existing webhook/payment tests if present.

They must remain GREEN.

If they fail due to an unrelated pre-existing issue, document the failure rather than modifying frozen payment infrastructure.

---

# 64. IMPLEMENTATION ORDER

Follow:

```text
1. Inspect repository
2. Inspect existing stock command/domain
3. Inspect existing stock UI
4. Inspect permissions
5. Inspect existing tests
6. Identify query/pagination conventions
7. Harden stock query/read model if needed
8. Harden stock index
9. Harden stock detail
10. Harden movement history
11. Harden adjustment form
12. Preserve existing AdjustStockHandler
13. Add/extend integration tests
14. Add frontend/UI contract tests if appropriate
15. Run PHPUnit
16. Run frontend/build/lint checks
17. Inspect git diff
18. Verify webhook/reconciliation files are untouched
```

---

# 65. FILE CHANGE DISCIPLINE

Before finishing:

```bash
git status
git diff --stat
git diff
```

Verify that changes are limited to Phase 2.38.

Do not modify unrelated:

```text
webhook
payment
reconciliation
PaymentReference
bank notification
```

files.

---

# 66. DEFINITION OF DONE

Phase 2.38 is complete only when:

```text
[ ] Stock index works
[ ] Product search works
[ ] Stock detail works
[ ] Current stock is accurate
[ ] Low-stock state is visible
[ ] Out-of-stock state is clear
[ ] Movement history is readable
[ ] Historical movements are read-only
[ ] Adjustment uses existing application handler
[ ] CSRF enforced
[ ] STOCK_ADJUST enforced
[ ] Zero change rejected
[ ] Negative resulting stock rejected
[ ] Same-key idempotency preserved
[ ] Different-key concurrency preserved
[ ] Concurrent overdraw protected
[ ] StockMovement created for successful adjustment
[ ] AuditLog created for successful adjustment
[ ] Failed adjustment creates no movement
[ ] PRG prevents accidental form resubmission
[ ] Retry safety preserved
[ ] Mobile 360px UI works
[ ] Accessibility requirements pass
[ ] No N+1 regression introduced
[ ] Existing indexes reused
[ ] Product historical data remains intact
[ ] Checkout stock behavior unchanged
[ ] Refund/reversal stock behavior unchanged
[ ] Webhook/reconciliation untouched
[ ] Existing stock tests remain GREEN
[ ] Full PHPUnit suite passes
[ ] Frontend/build checks pass
[ ] Git diff contains only intended Phase 2.38 changes
```

---

# FINAL RULE

> PHASE 2.38 is a Stock Management UI + Stock Adjustment hardening phase.

The invariant is:

```text
ADMIN UI
   ↓
AUTHORIZE
   ↓
VALIDATE
   ↓
EXISTING ADJUST STOCK APPLICATION COMMAND
   ↓
PRODUCT LOCK
   ↓
ATOMIC STOCK CHANGE
   ↓
STOCK MOVEMENT
   ↓
AUDIT LOG
   ↓
CLEAN REDIRECT
```

Never bypass the application/domain stock mutation path.

And explicitly:

```text
WEBHOOK
RECONCILIATION
PAYMENT
PAYMENTREFERENCE
= FROZEN
```
