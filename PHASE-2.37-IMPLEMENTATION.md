# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.37 — POS DEBT / PAYMENT MANAGEMENT UI & SETTLEMENT HARDENING

## 0. OBJECTIVE

Implement the POS/Admin Debt and Payment Management experience on top of the existing persisted Customer, Order, Payment and debt-related domain model.

This phase must provide a reliable read/write workflow for customer outstanding balances and legitimate settlement/payment operations already supported by the domain.

The implementation must be source-first: inspect the repository before changing anything and reuse existing entities, application services, permissions, money handling, transaction boundaries and payment rules.

## 1. ABSOLUTE SCOPE BOUNDARY — WEBHOOK IS FROZEN

Do NOT modify:

- bank webhook controller
- webhook authentication/signature handling
- bank notification ingestion
- PaymentReference reconciliation
- PaymentReference matching/lifecycle processing
- webhook idempotency
- bank payment transaction boundaries
- existing successful bank-transfer flow

Webhook/reconciliation is FROZEN.

If a payment already exists, this phase reads the persisted payment/business data. Do not create a second reconciliation path.

## 2. SOURCE-FIRST INSPECTION

Before coding inspect:

- Customer entity/repository
- Order entity/repository and lifecycle
- Payment entity/repository
- PaymentReference relationship
- existing debt/balance fields or value objects
- existing customer payment/settlement services
- existing admin/POS routes/controllers/templates
- existing Voters/permissions
- money/currency formatting
- transaction/application-service conventions
- audit conventions
- existing pagination/search/filter patterns
- Phase 2.36 Customer implementation
- existing tests

Do not invent a second debt model if the repository already contains one.

## 3. FUNCTIONAL SCOPE

Implement, according to the actual domain:

- customer outstanding balance overview
- customer debt/payment history
- search/filter/pagination
- customer balance detail
- settlement/payment entry where the domain already supports manual settlement
- validation
- permission enforcement
- transaction safety
- duplicate-submit protection
- mobile-first UI
- tests

If the current domain does not support a particular mutation, implement the read-only portion and document the gap instead of inventing business behavior.

## 4. DEBT MUST BE DERIVED FROM AUTHORITATIVE DATA

Do not create a frontend-only balance.

Prefer an existing canonical balance calculation/service.

If the domain persists an authoritative balance, use it according to existing rules.

Do not calculate debt in Twig/Stimulus.

Conceptually:

```text
Customer
  ↓
Orders / debt-bearing transactions
  ↓
Payments / settlements
  ↓
Canonical outstanding balance
```

Use the actual project's model and semantics.

## 5. HISTORICAL INTEGRITY

Do not mutate historical Order totals merely to display or settle debt.

A settlement/payment must reference the appropriate customer/account/order according to the existing domain rules.

Never recalculate historical sale prices from current Product data.

## 6. CUSTOMER DEBT LIST

Provide a mobile-friendly list where supported:

- Customer
- phone/contact identifier
- outstanding balance
- last relevant payment/date
- status or debt indicator if an existing domain status exists
- View Detail

Use backend pagination.

Do not load every customer and filter in Twig.

## 7. SEARCH AND FILTER

Search using existing repository/query infrastructure.

Potential fields only if supported:

- customer name
- phone
- customer identifier

Potential filters only if supported:

- outstanding balance > 0
- settled/no outstanding balance
- date range for payment history

Preserve query/filter state across pagination.

## 8. CUSTOMER DEBT DETAIL

Detail should clearly separate:

```text
Customer
Outstanding Balance
Debt-bearing transactions
Payments / Settlements
Remaining Balance
```

Use persisted/canonical values.

Do not make the UI look like an editable Order.

## 9. PAYMENT / SETTLEMENT HISTORY

Where supported, display:

- payment identifier
- amount
- method
- status
- created/recorded time
- related Order/reference
- relevant note/reference

For bank transfer, display existing persisted PaymentReference when appropriate.

Never expose raw webhook payloads or provider secrets.

## 10. MANUAL SETTLEMENT

Only implement manual settlement if the current domain/application layer already supports it or the project specification explicitly defines it.

Do not create a generic "payment" entity directly from a controller merely to reduce debt.

The operation must use the existing application/domain service.

Conceptually:

```text
UI
 ↓
Controller
 ↓
Application command/service
 ↓
Domain validation
 ↓
Transaction
 ↓
Persist settlement/payment
 ↓
Recalculate/read canonical balance
```

## 11. TRANSACTION BOUNDARY

A legitimate settlement mutation must be atomic according to existing application architecture.

At minimum ensure that a successful settlement does not leave:

- payment persisted without the required balance effect;
- balance changed without the required payment record;
- duplicate settlement from a repeated request.

Reuse the existing transaction manager/application service patterns.

Do not modify the frozen bank webhook transaction boundary.

## 12. IDEMPOTENCY / DOUBLE SUBMIT

Protect manual settlement against:

- double click
- browser retry
- duplicate form submission
- repeated request with the same operation identifier if the domain already supports idempotency

Do not invent a second idempotency framework if an existing one applies.

A duplicate request must not create two business payments.

## 13. MONEY VALIDATION

Use the project's canonical money representation.

Validate:

- amount is present where required
- amount is positive
- currency is valid
- precision/scale follows existing rules
- amount cannot exceed allowed settlement amount unless the existing business rule explicitly permits overpayment

Do not use floating-point arithmetic for financial calculations.

Do not scatter `number_format()` or ad-hoc decimal arithmetic across controllers/templates.

## 14. OVERPAYMENT

Inspect the current business rules before deciding behavior.

If overpayment is not supported:

```text
requested settlement > outstanding balance
→ validation error
```

If overpayment is explicitly supported, preserve that domain behavior.

Do not silently invent credit/refund behavior.

## 15. ORDER-LEVEL VS CUSTOMER-LEVEL DEBT

Inspect the current domain carefully.

If debt belongs to:

```text
Customer account
```

settlement must follow that model.

If debt belongs to:

```text
specific Order
```

preserve that model.

Do not merge these semantics just for UI convenience.

## 16. PAYMENT METHODS

Use only payment methods already supported by the project for settlement.

Do not invent new methods.

Bank Transfer settlement must not bypass the existing bank reconciliation flow.

If a bank transfer payment has not been backend-confirmed, the UI must not mark it as paid manually unless the existing domain explicitly permits such an operation.

## 17. BANK TRANSFER RULE

This phase must never provide a shortcut such as:

```text
cashier clicks "Mark Paid"
→ bank transfer becomes PAID
```

for a bank payment that requires webhook/reconciliation.

Existing backend authority remains authoritative.

## 18. PERMISSIONS

Reuse existing Voters/permission matrix.

Separate, where appropriate:

- view customer debt
- view payment history
- create manual settlement

Do not rely on hiding buttons.

Direct URLs/API requests must be authorized server-side.

Do not hardcode role checks if the project uses Voters.

## 19. AUDIT

Inspect the existing audit architecture.

If settlement mutations are already required to be audited, use the existing audit mechanism.

Do not create a new AuditLog implementation solely for this phase.

Do not audit harmless read-only page rendering unless the application explicitly requires it.

## 20. CONTROLLER RESPONSIBILITY

Controllers remain thin:

- parse/validate request
- authorize
- call application service/query
- render/redirect

Do not put financial calculations or transaction logic directly in Twig/controller code.

## 21. REPOSITORY / QUERY RESPONSIBILITY

Repository/query services should handle:

- debt list
- search
- filters
- payment history
- pagination
- sorting

Avoid N+1 queries.

Do not load all payments/orders for a customer if a paginated query is appropriate.

## 22. UI — MOBILE FIRST

Target 360px+.

Use large touch targets (approximately 48px minimum).

Prioritize:

```text
Customer
Outstanding balance
Payment history
Settlement action (if permitted)
```

Do not use a desktop-only wide financial table.

## 23. BALANCE PRESENTATION

Outstanding balance must be visually prominent but not misleading.

Example conceptual layout:

```text
Customer: Nguyen Van A

Outstanding
500,000 VND

[Record Settlement]

Payment History
...
```

Use actual project terminology and translation keys.

## 24. SETTLEMENT FORM

If supported by the domain, provide:

- amount
- payment method
- reference/note if supported
- related Order if required by domain
- confirmation

Validate server-side regardless of frontend validation.

Do not allow stale frontend balance to authorize a settlement.

## 25. CONCURRENCY

Consider two cashiers settling the same outstanding balance concurrently.

The backend/domain must remain authoritative.

Example:

```text
Balance = 500,000

Cashier A settles 500,000
Cashier B simultaneously settles 500,000
```

The result must follow the existing concurrency/business rules and must not silently create an impossible double settlement.

Reuse existing locking/unique/idempotency patterns where applicable.

Do not solve concurrency only in JavaScript.

## 26. STALE BALANCE

The UI may display an old balance.

When submitting settlement, the backend must validate against current authoritative state.

Do not trust a hidden HTML field such as:

```text
outstandingBalance=500000
```

as authority.

## 27. SUCCESS FLOW

After a successful settlement:

```text
settlement persisted
 ↓
redirect/reload
 ↓
canonical balance read
 ↓
updated history
```

Do not fake the new balance in JavaScript.

## 28. FAILURE FLOW

On validation/concurrency/business failure:

- preserve safe user input where appropriate
- show a clear error
- do not claim settlement succeeded
- do not partially reset the page

Do not expose stack traces/Doctrine errors.

## 29. EMPTY STATES

Customer with no debt:

```text
No outstanding balance.
```

Customer with no payment history:

```text
No payments recorded.
```

Do not create placeholder business records.

## 30. PAGINATION

Payment history must be backend paginated when it can become large.

Preserve customer identity and filters while navigating pages.

## 31. DATE/TIME

Use existing application timezone and date formatter.

Do not hardcode timezone/format if the project already has a convention.

## 32. TRANSLATION

Reuse Symfony translation keys and existing terminology.

Do not hardcode a parallel vocabulary for:

- debt
- settlement
- payment
- outstanding balance
- paid
- pending

## 33. ACCESSIBILITY

Forms must have labels.

Errors must be associated with fields.

Buttons must be keyboard accessible.

Do not rely on color alone to communicate debt/payment status.

If a settlement success message is live-announced, do so once rather than repeatedly.

## 34. NO FRONTEND BUSINESS LOGIC

Stimulus/JS may:

- open settlement modal
- validate obvious input for UX
- disable submit while submitting
- show loading state

It must NOT decide:

- debt amount
- payment success
- settlement validity
- Order completion
- bank payment confirmation

Backend remains authoritative.

## 35. TESTS — READ FLOW

Add tests for:

- debt list loads
- search
- pagination
- customer debt detail
- payment history
- no-debt state
- empty payment history
- authorization
- direct unauthorized access

## 36. TESTS — SETTLEMENT

If manual settlement is supported, test:

- valid settlement
- zero amount rejected
- negative amount rejected
- invalid currency rejected
- unsupported method rejected
- overpayment according to actual rule
- duplicate submission
- stale balance
- concurrent settlement
- transaction rollback
- successful balance update
- payment history update

## 37. TEST — READ-ONLY SAFETY

Opening debt/payment pages must not create or modify:

- Order
- Payment
- PaymentReference
- StockMovement

## 38. TEST — BANK SAFETY

Verify this phase does not alter bank webhook/reconciliation behavior.

Existing webhook/reconciliation tests must remain GREEN.

Do not modify webhook code to make these tests pass.

## 39. ACCEPTANCE SCENARIO

```text
1. Login
2. Open Customer/Debt management
3. Search customer
4. Open customer debt detail
5. See canonical outstanding balance
6. See payment history
7. If permitted and supported, open settlement form
8. Enter valid amount
9. Submit once
10. Backend validates current balance
11. Settlement persists atomically
12. Redirect/reload
13. Updated balance is displayed from backend
14. Updated payment history is visible
15. Repeat click/retry does not create duplicate settlement
```

## 40. IMPLEMENTATION ORDER

```text
1. Inspect repository and current Phase 2.36 implementation
2. Identify existing debt/balance semantics
3. Identify existing payment/settlement services
4. Identify permissions/Voters
5. Identify money conventions
6. Implement/reuse read queries
7. Implement debt list
8. Implement customer debt detail
9. Implement payment history
10. Implement settlement command only if supported
11. Apply authorization
12. Apply validation
13. Apply transaction/concurrency safeguards
14. Implement mobile UI
15. Add tests
16. Run PHPUnit
17. Run frontend/build/lint checks
18. Inspect git diff
19. Confirm webhook/reconciliation files are untouched
```

## 41. FILE CHANGE DISCIPLINE

Before finishing:

```bash
git status
git diff --stat
git diff
```

Verify that changes are limited to Phase 2.37.

Especially verify these remain untouched:

- webhook
- reconciliation
- PaymentReference matching
- bank notification processing
- webhook idempotency

## 42. DEFINITION OF DONE

```text
[ ] Customer debt overview exists
[ ] Customer debt detail exists
[ ] Outstanding balance comes from canonical backend/domain data
[ ] Payment history exists
[ ] Search/filter/pagination work server-side
[ ] Settlement exists only if supported by the actual domain
[ ] Settlement uses existing application/domain service
[ ] Money validation is server-side
[ ] Overpayment follows existing business rules
[ ] Double-submit is safe
[ ] Concurrent settlement is safe according to existing domain rules
[ ] Stale frontend balance cannot authorize payment
[ ] Authorization is enforced server-side
[ ] Historical Orders are not rewritten
[ ] Bank transfer cannot be manually faked as reconciled
[ ] Mobile UI works at 360px+
[ ] Accessibility requirements pass
[ ] No obvious N+1 query exists
[ ] Existing webhook/reconciliation remains untouched
[ ] Existing webhook tests remain green
[ ] Full PHPUnit suite passes
[ ] Frontend/build checks pass
[ ] Git diff contains only intended Phase 2.37 changes
```

## FINAL RULE

> PHASE 2.37 manages customer debt and legitimate settlement workflows; it does not replace or bypass the existing payment authority.

```text
CUSTOMER / ORDER DATA
        ↓
CANONICAL BALANCE
        ↓
AUTHORIZED USER
        ↓
EXISTING APPLICATION SERVICE
        ↓
ATOMIC SETTLEMENT (when supported)
        ↓
UPDATED BALANCE + HISTORY
```

And permanently:

```text
WEBHOOK + RECONCILIATION + PAYMENTREFERENCE MATCHING = FROZEN
```
