# PHASE 2.33 — POS Order History & Order Detail Implementation

## CRITICAL SOURCE IMPLEMENTATION PROMPT

### Objective
Implement a read-only POS Order History and Order Detail experience using the existing Order, OrderItem, Customer, Payment and PaymentReference domain/persistence model.

Flow:

```text
POS
  ↓
Order History
  ↓
Search / Filter / Pagination
  ↓
Order Detail
  ↓
Historical sale inspection
```

This phase MUST NOT introduce Order mutation, Payment mutation, Refund, Cancellation, Stock mutation, webhook mutation, or PaymentReference reconciliation.

## 1. FROZEN BOUNDARY

The following systems are frozen and MUST NOT be modified:

- Bank webhook
- Webhook reconciliation
- PaymentReference matching/lifecycle processing
- Bank notification ingestion
- Payment idempotency
- Payment persistence
- Webhook transaction handling

Consume existing persisted data only.

## 2. SOURCE-FIRST

Before implementation inspect the actual repository:

- Order / OrderRepository
- OrderItem
- Product
- Customer
- Payment
- PaymentReference
- Order lifecycle/statuses
- existing controllers/routes/templates
- existing Stimulus controllers
- Voters/permissions
- pagination/search/filter patterns
- money/date formatting
- translations
- existing tests
- `docs/PHASE-2.32-IMPLEMENTATION.md`

Reuse existing architecture and terminology. Do not invent duplicate domain models, permissions, routes, or query systems.

## 3. ORDER HISTORY

Implement/reuse an appropriate route following existing conventions, typically equivalent to:

```text
GET /admin/orders
```

The list should expose, where supported by the actual domain:

- Order number/identifier
- Customer
- Total
- Payment method
- Order status
- Created at
- Completed at

Use backend/server-side querying. Never load the complete Order table and filter in Twig/JavaScript.

## 4. SEARCH

Support search against actual persisted searchable fields, such as:

- Order number/identifier
- Customer name
- Customer phone

Only expose fields supported by the repository/domain.

## 5. FILTERS

Where supported, implement server-side filters for:

- Order status
- Payment method
- Date from/to
- Customer

Do not invent statuses or payment methods.

Preserve filter/search parameters through pagination.

## 6. PAGINATION

Use existing pagination infrastructure if available. Otherwise implement minimal repository-level pagination.

Do not:

- load every Order
- filter in PHP arrays
- slice results in Twig

Default ordering should follow existing conventions; if none exists, use newest Orders first by persisted creation timestamp.

## 7. QUERY PERFORMANCE

Avoid N+1 queries for Customer, Payment, and OrderItem data needed by the list/detail view.

Fetch only required relations/data. Do not blindly eager-load every relation.

Inspect existing indexes before adding migrations. Add schema/index changes only when genuinely required by the implemented query pattern.

## 8. ORDER DETAIL

Implement/reuse an appropriate route, typically equivalent to:

```text
GET /admin/orders/{id}
```

Display, where actually persisted:

- Order ID/number
- Status
- Created/completed timestamps
- Customer
- Items
- Quantity
- Historical unit price
- Line totals
- Subtotal
- Discount
- Tax
- Grand total
- Payment method
- Payment amount/status
- PaymentReference

Missing Order must use the application's normal 404 behavior.

## 9. HISTORICAL DATA INTEGRITY

Order Detail MUST use historical OrderItem values.

Never rebuild historical prices/totals from the current Product price.

Example:

```text
Current Product price: 120,000
Historical OrderItem price: 100,000

Order Detail MUST display: 100,000
```

Reuse canonical persisted totals/calculators already present in the project. Do not create a second financial calculation implementation.

## 10. CUSTOMER

Display persisted customer information when available.

If the application has an established convention for walk-in customers, reuse it. Do not create a Customer merely because an Order has no customer.

## 11. PAYMENT / PAYMENT REFERENCE

Display existing persisted payment information when associated with the Order.

For Bank Transfer, display PaymentReference when the existing relationship provides it.

Never:

- call a bank
- call webhook/reconciliation
- create Payment
- create PaymentReference
- change payment status
- expose raw webhook payloads, signatures, credentials, or secrets

Order History and Detail are read-only.

## 12. AUTHORIZATION

Use the existing security/Voter/permission architecture.

Do not rely on hiding links. Direct Order Detail URLs MUST be authorized server-side.

If an existing Order Voter exists, reuse it. Do not hardcode role checks when the project already uses Voters.

## 13. READ-ONLY GUARANTEE

Opening Order History or Order Detail MUST NOT modify:

- Order
- OrderItem
- Payment
- PaymentReference
- StockMovement

unless the project already has an explicit read-audit requirement.

Do not add functional actions for:

- Cancel
- Refund
- Edit Order
- Delete Order
- Retry Payment
- Regenerate PaymentReference

Those belong to later phases.

## 14. MOBILE-FIRST UI

Target 360px+.

Use the existing POS/admin visual system. Avoid desktop-only wide tables where they make mobile inspection difficult.

Order History should remain usable for:

- search
- filter
- opening detail
- pagination

Touch targets should be approximately 48px minimum.

Order Detail should have clear sections:

```text
Order Header
Customer
Items
Totals
Payment
Metadata
```

## 15. EMPTY / ERROR STATES

Empty result should clearly state that no matching Orders were found.

Use existing application error handling for load failures.

Never expose SQL errors, stack traces, Doctrine internals, credentials, or secrets.

## 16. FORMATTING / TRANSLATIONS

Reuse the project's existing:

- money formatter
- date/time formatter
- timezone configuration
- translation system
- Order/payment terminology

Do not scatter `number_format()` or hardcoded date formatting through templates when project-level formatters already exist.

## 17. STIMULUS

Basic search/filter/pagination/navigation should remain server authoritative.

Stimulus may be used only for lightweight presentation enhancements such as a mobile filter drawer or copy-to-clipboard interaction.

Do not move Order business rules into Twig/Stimulus/JavaScript.

## 18. CONTROLLER / REPOSITORY RESPONSIBILITY

Controllers should remain thin:

```text
request
→ normalize filters
→ authorize
→ query/application service
→ render
```

Repository/query layer owns:

- search
- filters
- pagination
- sorting
- appropriate joins/fetching

Do not duplicate query logic across Controller/Twig/Stimulus.

## 19. TEST REQUIREMENTS

Add/update tests appropriate to the existing test architecture.

At minimum cover:

- Order History loads
- Order Detail loads
- missing Order → 404
- unauthorized access → denied
- search
- status filter
- payment method filter
- date filter
- pagination
- newest-first ordering
- historical OrderItem price display
- Payment/PaymentReference display when applicable
- empty result
- read-only behavior
- no obvious N+1 regression

Critical historical-price test:

```text
Product current price != OrderItem historical price
→ Order Detail displays historical OrderItem price
```

## 20. WEBHOOK REGRESSION

Do not modify webhook/reconciliation code to make Phase 2.33 pass.

Run existing webhook/reconciliation tests and the full PHPUnit suite. Existing payment/webhook behavior must remain unchanged.

## 21. ACCEPTANCE SCENARIO

The following must work:

```text
1. Login
2. Open Order History
3. See newest Orders
4. Search an Order
5. Open Order Detail
6. Inspect customer
7. Inspect items
8. Inspect historical prices
9. Inspect total
10. Inspect payment information
11. Inspect PaymentReference when applicable
12. Return to Order History
13. Apply status filter
14. Apply date filter
15. Navigate pagination
16. Open another Order
17. Verify no business data changed
```

## 22. MOBILE ACCEPTANCE

At 360px width verify:

- search is usable
- filters are usable
- Order list/cards are readable
- View Detail is easy to tap
- pagination is usable
- Order Detail has no unnecessary horizontal scrolling

## 23. IMPLEMENTATION ORDER

```text
1. Inspect repository
2. Inspect Order domain
3. Inspect Payment/PaymentReference relationships
4. Inspect permissions/Voters
5. Inspect existing admin/POS UI patterns
6. Inspect query/pagination patterns
7. Identify existing routes
8. Implement/reuse repository query
9. Implement/reuse Order History controller
10. Implement Order History template
11. Implement search/filter/pagination
12. Implement/reuse Order Detail controller
13. Implement Order Detail template
14. Enforce authorization
15. Implement responsive UI
16. Add tests
17. Run PHPUnit
18. Run frontend/build/lint checks from package.json/composer.json
19. Inspect git diff
20. Verify webhook/reconciliation files are untouched
```

## 24. FILE CHANGE DISCIPLINE

Before finishing:

```bash
git status
git diff --stat
git diff
```

Changes must be limited to Phase 2.33.

Explicitly verify that these remain untouched:

```text
Webhook
Reconciliation
PaymentReference matching
Bank notification processing
Payment idempotency
Bank webhook transaction logic
```

Revert unrelated changes before completion.

## 25. DEFINITION OF DONE

```text
[ ] Order History exists
[ ] Order Detail exists
[ ] Search is server-side
[ ] Filters are server-side
[ ] Pagination is server-side
[ ] Newest Orders appear first
[ ] Historical OrderItem prices are preserved
[ ] Customer information is displayed safely
[ ] Payment information is displayed safely
[ ] PaymentReference is displayed when applicable
[ ] Existing Order status terminology is reused
[ ] Existing date/time formatting is reused
[ ] Existing money formatting is reused
[ ] Authorization is enforced server-side
[ ] Unauthorized direct access is denied
[ ] Missing Order returns normal 404
[ ] Empty state works
[ ] Error handling is safe
[ ] Mobile 360px layout works
[ ] Touch targets are usable
[ ] History/Detail cause no business mutation
[ ] No duplicate query implementation exists
[ ] No obvious N+1 query exists
[ ] Existing Order lifecycle is unchanged
[ ] Webhook/reconciliation is untouched
[ ] Existing webhook tests remain green
[ ] Full PHPUnit suite passes
[ ] Frontend/build checks pass
[ ] Git diff contains only intended Phase 2.33 changes
```

## FINAL RULE

> PHASE 2.33 is a historical read-only Order experience.

Invariant:

```text
PERSISTED ORDER
      ↓
QUERY
      ↓
AUTHORIZE
      ↓
DISPLAY
      ↓
NO BUSINESS MUTATION
```

And explicitly:

```text
WEBHOOK
RECONCILIATION
PAYMENT MATCHING
PAYMENT IDEMPOTENCY
= FROZEN
```
