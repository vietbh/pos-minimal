# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.34
# POS ORDER CANCEL / REFUND UX & BUSINESS FLOW

## 0. OBJECTIVE

Implement the next POS business workflow after Order History & Order Detail: controlled Order cancellation and refund handling.

This phase must build on the existing Order lifecycle, Payment model, permissions, stock movement history, and audit architecture already present in the repository.

The implementation must be source-first and must not redesign working payment infrastructure.

---

## 1. ABSOLUTE SCOPE BOUNDARY

The following systems are FROZEN and MUST NOT be redesigned:

- Bank webhook
- Webhook reconciliation
- PaymentReference matching
- Bank notification ingestion
- Payment idempotency
- Payment persistence
- Webhook transaction boundaries
- Android notification bridge

Do not modify these systems merely to support cancellation/refund UX.

If refund/cancellation needs payment information, consume the existing persisted domain state.

---

## 2. SOURCE-FIRST REQUIREMENT

Before coding, inspect the actual repository and identify:

- Order entity and lifecycle
- OrderItem
- Payment entity/status/method
- PaymentReference relationship
- StockMovement / inventory services
- existing Order History and Detail from Phase 2.33
- existing Voters/permissions
- existing transaction/application services
- existing audit mechanism
- existing error handling
- existing Twig/Stimulus UI patterns
- existing tests

Do not invent duplicate domain services when an existing service already owns the behavior.

---

## 3. BUSINESS SEMANTICS

Preserve the current Order lifecycle.

Do not invent new statuses without confirming that the current domain requires them.

Possible existing concepts may include:

```text
PENDING
COMPLETED
CANCELLED
REFUNDED
```

Use the actual enum/constants already present.

Cancellation and refund are distinct operations:

```text
CANCEL
  = invalidate an order according to the existing business rules

REFUND
  = reverse/refund an already paid sale according to the existing business rules
```

Do not treat every cancellation as a refund.

Do not treat every refund as an Order cancellation.

---

## 4. ORDER DETAIL ACTIONS

Extend the existing Order Detail UI from Phase 2.33 with only the actions that are actually supported by the domain.

Potential actions:

```text
Cancel Order
Refund Payment
```

The UI must determine availability from backend-authoritative state and permissions.

Hiding a button is NOT authorization.

Every action must be authorized server-side.

---

## 5. CANCEL ORDER

Implement cancellation only where the current Order state permits it.

Before executing cancellation:

- authorize the user;
- load the Order from persistence;
- validate the current lifecycle state;
- enforce existing business rules;
- execute the domain/application operation atomically;
- persist the resulting state;
- preserve historical records.

Do not allow cancellation of an Order simply because a button is visible.

Do not allow stale UI state to bypass the backend state check.

---

## 6. REFUND

Implement refund only according to the existing payment/business model.

Before refund:

- authorize the user;
- verify the Order/payment state;
- verify that the payment is actually refundable according to current rules;
- prevent duplicate refund operations;
- execute the operation through the appropriate application/domain service;
- preserve historical payment information.

Do not create a fake refund by merely changing a Twig/Stimulus status.

Do not mark a Payment as refunded unless the existing domain explicitly defines that transition.

If the current system does not yet have a real refund domain operation, do NOT fabricate one. Document the missing domain capability and implement only the supported cancellation path.

---

## 7. IDEMPOTENCY / DOUBLE SUBMIT

Cancellation/refund requests must be safe against:

```text
single click
rapid double click
browser retry
network retry
stale browser tab
```

A repeated request for an already-completed cancellation/refund must not create duplicate business effects.

Reuse existing idempotency infrastructure if applicable.

Do not introduce a second unrelated idempotency mechanism.

---

## 8. CONCURRENCY

Protect against concurrent actions:

```text
Cashier A → Cancel Order
Cashier B → Cancel Order
```

and:

```text
Cashier A → Refund
Cashier B → Refund
```

The backend must re-check authoritative state inside the appropriate transaction boundary.

Expected invariant:

```text
one valid business transition
        ↓
no duplicate transition
```

Do not rely on frontend disabling alone.

---

## 9. TRANSACTION BOUNDARY

Cancellation/refund must preserve atomicity of the existing business operation.

Inspect the current application service/transaction conventions.

Where multiple persisted records must change together, ensure they share the appropriate transaction boundary.

Do not partially persist:

```text
Order status
Payment state
Stock state
Audit/history
```

and then return success if the operation is incomplete.

Do not redesign unrelated transaction boundaries.

---

## 10. STOCK BEHAVIOR

Inspect the existing stock domain before implementing cancellation/refund.

If cancellation/refund requires stock restoration according to the current business rules, use the existing stock service/domain mechanism.

Do NOT directly mutate:

```text
Product.stock
```

from the controller or Twig/Stimulus.

Use the existing stock movement/history model.

Do not delete historical stock movements.

Do not rewrite stock history to hide a cancellation/refund.

If the current domain does not support stock restoration for the operation, do not invent a parallel stock mechanism.

---

## 11. PAYMENT HISTORY

Payment records are historical/business records.

Do not delete the original Payment because a refund occurs.

If the existing domain supports refund records or payment reversal records, use that model.

If it does not, inspect the existing architecture before introducing a new entity.

Never hide a refund by deleting the original payment.

---

## 12. AUDIT / HISTORY

Use the existing audit mechanism if the project has one.

Record the business action according to existing conventions:

```text
ORDER_CANCELLED
PAYMENT_REFUNDED
```

or the project's actual audit vocabulary.

Do not create a second AuditLog implementation.

Audit information must be historical and must not replace Order/Payment/Stock business records.

---

## 13. CONFIRMATION UX

Cancellation and refund are destructive/high-impact actions.

Require explicit confirmation.

Example:

```text
Cancel this order?

This action cannot be undone.

[Cancel] [Keep Order]
```

For refund:

```text
Refund this payment?

Amount: 500,000 VND

[Refund Payment] [Keep Payment]
```

Use the project's existing modal/dialog pattern where available.

Do not put business rules in the modal.

---

## 14. MOBILE UX

Target 360px+.

Use large touch targets (~48px minimum).

Primary/secondary actions must be visually distinct.

Do not place destructive actions next to primary navigation in a way that encourages accidental taps.

The Order Detail page should remain readable after an action succeeds.

---

## 15. SUCCESS UX

After a successful cancellation/refund:

- show a clear success message;
- update/reload the Order Detail from backend state;
- remove or disable actions that are no longer valid;
- preserve the historical Order information;
- do not fabricate a new local status without backend confirmation.

Preferred flow:

```text
POST action
  ↓
backend validates + commits
  ↓
redirect/reload
  ↓
render persisted state
```

Prefer server-authoritative navigation over complex client-side mutation.

---

## 16. FAILURE UX

If the backend rejects cancellation/refund:

- do not change the displayed Order into the requested state;
- show the existing safe error format;
- preserve the current persisted state;
- allow the cashier to recover where appropriate.

Do not expose stack traces, SQL errors, provider payloads, or secrets.

---

## 17. STALE UI PROTECTION

Scenario:

```text
Tab A displays COMPLETED
Tab B cancels/refunds the same Order
Tab A clicks Refund
```

Backend must reject or safely handle the stale operation according to current state.

Frontend must not assume that the old Order state is still valid.

---

## 18. ORDER HISTORY INTEGRATION

After cancellation/refund, Order History must reflect the persisted state.

Examples:

```text
COMPLETED → CANCELLED
```

or:

```text
COMPLETED + refunded payment
```

according to the actual domain model.

Search/filter/pagination from Phase 2.33 must continue working.

Do not duplicate Order History query logic.

---

## 19. NO WEBHOOK SIDE EFFECTS

A refund/cancellation initiated from the POS must NOT:

- call the bank webhook;
- simulate a bank notification;
- modify PaymentReference reconciliation;
- replay a notification;
- modify Android notification handling.

The bank webhook remains an inbound payment confirmation mechanism.

---

## 20. CONTROLLER RESPONSIBILITY

Controllers should remain thin:

```text
request
→ authorize
→ validate input
→ call application/domain service
→ redirect/render
```

Do not put cancellation/refund business logic directly into Twig, Stimulus, or controllers when a domain/application service is appropriate.

---

## 21. HTTP SEMANTICS

Use appropriate HTTP methods.

Do not implement destructive actions as GET requests.

Prefer the existing project's conventions, for example:

```text
POST /admin/orders/{id}/cancel
POST /admin/orders/{id}/refund
```

Do not blindly use these exact routes if equivalent routes already exist.

Protect state-changing requests with the existing CSRF mechanism.

---

## 22. CSRF

All browser state-changing actions must use the project's existing CSRF protection.

Do not disable CSRF for convenience.

Do not trust a hidden form field as authorization.

---

## 23. PERMISSIONS

Reuse existing Voters/permissions.

Potential conceptual permissions:

```text
ORDER_CANCEL
ORDER_REFUND
```

Only add new permission attributes if the current security architecture genuinely needs them.

Do not hardcode role checks in controllers if Voters are already used.

Test both allowed and denied cases.

---

## 24. BUSINESS RULE DISCOVERY

Before implementing the action, explicitly determine from source:

```text
Can PENDING orders be cancelled?
Can COMPLETED orders be cancelled?
Can PAID orders be cancelled?
Can a paid order be refunded?
Can a cancelled order be refunded?
Can a refunded order be refunded again?
Does cancellation restore stock?
Does refund restore stock?
Can partial refunds exist?
```

Do not invent answers.

Implement only the rules supported by the existing domain/specification.

If a required business rule is genuinely missing, stop that portion rather than silently inventing behavior.

---

## 25. PARTIAL REFUND

Do NOT implement partial refunds unless the current domain explicitly supports them.

If the existing Payment/Refund model is full-refund only:

```text
refund = full refundable amount
```

Do not add partial-refund UI just because it appears useful.

---

## 26. REFUND AMOUNT

Never trust an amount submitted by the browser.

The backend must derive the refundable amount from persisted business state.

Invalid:

```text
POST refund amount = 1,000,000
```

and trusting it.

Valid conceptual flow:

```text
Order/payment
  ↓
backend determines refundable amount
  ↓
transaction
```

---

## 27. COMPLETED ORDER PROTECTION

Once an Order is cancelled/refunded, opening the detail page must never make it appear editable as an active sale.

Do not expose:

```text
Edit Order
Checkout Again
Change Payment
```

unless explicitly supported by the existing product specification.

---

## 28. TEST MATRIX

Add tests appropriate to the existing test architecture.

Minimum coverage:

```text
Cancel allowed state
Cancel denied state
Refund allowed state
Refund denied state
Unauthorized cancel
Unauthorized refund
CSRF rejection
Double cancel
Double refund
Concurrent cancel
Concurrent refund
Stock restoration if supported
Payment history preservation
Audit/history preservation
Order History reflects result
```

---

## 29. CANCEL TEST

Given an Order in a cancellable state:

```text
cancel request
```

must produce the correct persisted state.

Repeat the same request.

Expected:

```text
no duplicate business effect
```

---

## 30. REFUND TEST

Given a refundable payment:

```text
refund request
```

must produce the correct persisted refund/reversal state.

Repeat the request.

Expected:

```text
no duplicate refund
```

---

## 31. CONCURRENT TEST

Use the project's real concurrency testing approach where available.

Simulate:

```text
Request A → cancel
Request B → cancel
```

Expected:

```text
one valid transition
no duplicate stock reversal
no duplicate refund/cancellation record
```

Repeat for refund where supported.

Do not replace a real concurrency test with a single-thread unit test.

---

## 32. HISTORICAL DATA TEST

After cancellation/refund verify:

```text
original Order remains queryable
original OrderItems remain intact
original Payment history remains intact
stock history remains intact
```

Do not physically delete business history.

---

## 33. ORDER DETAIL TEST

After action completion:

```text
GET Order Detail
```

must render persisted state from the database.

Do not rely on the previous POST response to fabricate the final page.

---

## 34. REGRESSION TEST

Run:

```bash
php bin/phpunit
```

and the project's existing frontend/build/lint checks discovered from package.json/composer.json.

Do not invent commands that are not part of the repository.

Existing webhook/reconciliation tests must remain GREEN.

---

## 35. IMPLEMENTATION ORDER

Follow this order:

```text
1. Inspect repository
2. Inspect Order lifecycle
3. Inspect Payment/refund model
4. Inspect Stock domain
5. Inspect Audit/history
6. Inspect Phase 2.33 Order Detail
7. Inspect Voters/permissions
8. Identify existing cancel/refund services
9. Implement/reuse application service
10. Implement authorization
11. Implement CSRF-protected HTTP action
12. Implement confirmation UX
13. Implement success/error handling
14. Update Order Detail
15. Verify Order History integration
16. Add unit/application/integration/security tests
17. Add concurrency tests where supported
18. Run full test suite
19. Run frontend checks
20. Inspect git diff
21. Verify webhook/reconciliation files are untouched
```

---

## 36. FILE CHANGE DISCIPLINE

Before finishing:

```bash
git status
git diff --stat
git diff
```

Verify changes are limited to Phase 2.34.

Explicitly verify these areas were NOT changed:

```text
Webhook
Reconciliation
PaymentReference matching
Bank notification ingestion
Android notification bridge
```

If unrelated modifications were introduced, revert them.

---

## 37. DEFINITION OF DONE

```text
[ ] Existing Order lifecycle preserved
[ ] Cancel flow implemented only where supported
[ ] Refund flow implemented only where supported
[ ] No invented refund semantics
[ ] No partial refund unless already supported
[ ] Backend derives authoritative refund amount
[ ] Server-side authorization enforced
[ ] CSRF enforced
[ ] Double-submit protected
[ ] Concurrent operations hardened
[ ] Transaction boundary correct
[ ] Stock behavior follows existing domain rules
[ ] Historical business records preserved
[ ] Audit/history follows existing architecture
[ ] Order Detail reflects persisted result
[ ] Order History reflects persisted result
[ ] Mobile UX works at 360px+
[ ] Destructive actions require explicit confirmation
[ ] Failure does not fabricate state
[ ] Webhook/reconciliation remains untouched
[ ] Existing webhook tests remain green
[ ] Full PHPUnit suite passes
[ ] Frontend/build checks pass
[ ] Git diff contains only intended Phase 2.34 changes
```

---

# FINAL RULE

> **PHASE 2.34 introduces controlled Order cancellation/refund business operations on top of the existing domain. It must not redesign the payment ingestion or webhook infrastructure.**

The invariant is:

```text
USER ACTION
   ↓
AUTHORIZATION
   ↓
BACKEND STATE VALIDATION
   ↓
DOMAIN/APPLICATION OPERATION
   ↓
ATOMIC PERSISTENCE
   ↓
HISTORICAL RECORDS PRESERVED
   ↓
READ-BACK FROM DATABASE
   ↓
UPDATED ORDER DETAIL / HISTORY
```

And explicitly:

```text
WEBHOOK
RECONCILIATION
PAYMENTREFERENCE MATCHING
BANK NOTIFICATION INGESTION
= FROZEN
```
