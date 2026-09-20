# PHASE UI/UX 9 — Manual Bank Transfer Completion

## Objective

Fix the bank-transfer checkout UX when the configured completion policy is `MANUAL`.

The cashier flow must be:

```text
Checkout
  ↓
Bank Transfer
  ↓
Payment Reference / QR
  ↓
Cashier independently verifies the real bank transfer
  ↓
Confirm bank transfer manually
  ↓
POST /app/payment-sessions/{id}/manual-confirm
  ↓
Atomic backend payment confirmation + paid-order completion
  ↓
Committed success state
```

The existing `CompleteOrderService` remains the authoritative final-sale path. The manual confirmation application service records the bank payment, marks the payment reference matched, marks the payment session paid, and invokes `CompleteOrderService` inside the same transaction. Because a newly created Order uses a database-generated ID and `CompleteOrderService` reloads it with `FOR UPDATE`, the manual flow must flush the pending Order/Payment/reference/session changes before invoking the completion service while keeping the transaction open.

## Fix

Before this phase, the POS success screen exposed `completePaidSaleButton` even when the completion policy was `MANUAL`. That button calls the webhook-reconciliation completion endpoint, which correctly rejects an order when the bank webhook has not yet reconciled the payment. The user therefore saw:

```text
ORDER_COMPLETION_FAILED: Ngân hàng chưa xác nhận thanh toán.
```

For `MANUAL` policy the UI now:

- shows `manualBankConfirmButton` when the user has `PAYMENT_BANK_MANUAL_CONFIRM`;
- hides/disables `completePaidSaleButton`;
- sends the explicit manual confirmation request to `/manual-confirm`;
- clears the cart only after the backend returns success;
- shows the committed order/payment result;
- keeps webhook enrichment optional and non-blocking.

For non-`MANUAL` policy, the existing webhook-reconciliation completion flow remains unchanged.

## Backend invariants preserved

- Backend remains payment authority.
- CSRF is required.
- Permission `PAYMENT_BANK_MANUAL_CONFIRM` is required.
- Payment reference must belong to the payment session and be pending/non-expired.
- Confirmed amount must equal the payment session/reference amount.
- Payment reference is marked `MATCHED`.
- Payment session is marked `PAID`.
- `CompleteOrderService` verifies the order is fully paid before completion.
- The transaction context performs an intermediate `flush()` before completion so generated Order/Payment IDs exist and the completion service can safely reload the Order with `FOR UPDATE`; the outer transaction remains open until the final commit.
- Product stock is locked and decremented by the authoritative completion service.
- `ORDER_COMPLETED` audit is written in the transaction.
- No provider transaction metadata is fabricated; later webhook data only enriches the already-confirmed payment.

## Tests

Updated frontend contract coverage verifies that `MANUAL` policy selects cashier confirmation and hides the webhook-completion button.

Existing application coverage verifies that manual confirmation invokes `CompleteOrderService` after creating the bank payment and matching the reference. The regression test also verifies the transaction-context flush occurs before completion.

## Affected files

- `assets/controllers/pos_checkout_controller.js`
- `src/Application/Payment/ManualBankPaymentConfirmationService.php`
- `tests/Frontend/PosCheckoutUiContractTest.php`
- `tests/Application/Payment/ManualBankPaymentConfirmationServiceTest.php`
- `PHASE-UIUX-9-MANUAL-BANK-TRANSFER-COMPLETION.md`

## Acceptance

### Manual policy

```text
Bank Transfer
→ manual confirmation button visible for authorized cashier
→ no ORDER_COMPLETION_FAILED caused by missing webhook
→ confirmation succeeds
→ Payment recorded
→ Order completed
→ stock movement recorded by completion service
→ cart cleared
```

### Automatic/webhook policy

Existing webhook-driven completion/recovery behavior remains intact.

## Source-of-truth update

ADDED
- Manual-policy-specific presentation rule: cashier confirmation is the only primary completion action before webhook reconciliation.

CHANGED
- POS bank-transfer success state now branches on `bankTransferCompletionPolicy`.

FIXED
- Manual bank transfer previously exposed the webhook-completion action and could show `ORDER_COMPLETION_FAILED` before webhook reconciliation.
- Manual confirmation rolled back after locking the cart product because the newly created Order had not been flushed before `CompleteOrderService` required its generated database ID.

DEPRECATED
- None.
