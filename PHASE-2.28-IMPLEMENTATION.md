# PHASE 2.28 — Manual Bank Confirmation + Delayed Webhook Enrichment

## Scope

This phase hardens the bank-transfer path for the real-world case where the POS/device sees that the customer has transferred money but the webhook is delayed, stuck, or temporarily unavailable.

## Implemented rules

- A separate `PAYMENT_BANK_MANUAL_CONFIRM` permission protects the manual bank-confirmation action.
- `ROLE_ADMIN` and `ROLE_ROOT` receive this permission; ordinary POS users do not.
- Manual confirmation is an explicit cashier assertion and requires CSRF, session identity, payment reference, and amount.
- The backend remains authoritative: the supplied amount must exactly match the persisted session/reference amount.
- Manual confirmation is allowed while the reference is still `PENDING` and not expired; the cashier does not have to wait for the QR countdown to expire.
- Manual confirmation creates the real `BANK_TRANSFER` Payment, links the PaymentReference and Order, marks the session `PAID`, and invokes the existing authoritative `CompleteOrderService` for stock mutation and final sale completion.
- Manual confirmation does **not** fabricate an `ExternalPaymentTransaction`, provider ID, bank notification text, or bank `occurredAt`.
- A later real webhook for an already `MATCHED` reference is treated as enrichment rather than a duplicate rejection.
- The later webhook persists the provider/external transaction metadata exactly once and links it to the existing Payment.
- A matched reference is never rejected merely because its original five-minute reference expiration has passed.
- For a still-pending webhook, expiration is evaluated against bank transaction `occurredAt`, not webhook HTTP arrival time.
- A delayed webhook whose `occurredAt` is before reference expiry remains valid even if the HTTP request arrives later.
- Concurrent manual confirmation and webhook processing are serialized by the existing payment-reference row lock and external transaction unique constraint.
- A second webhook with the same provider/external transaction ID remains idempotent.
- Known payment/order/reference data is never replaced by missing webhook fields because the enrichment path only adds the external transaction record after manual matching.

## HTTP contract

`POST /app/payment-sessions/{id}/manual-confirm`

Body:

```json
{
  "paymentReference": "ABC12345",
  "amount": "100000.00"
}
```

The endpoint requires:

- authenticated active user;
- `PAYMENT_BANK_MANUAL_CONFIRM` permission;
- valid `pos_checkout` CSRF token.

## Audit

Manual confirmation writes `PAYMENT_MANUALLY_CONFIRMED` with the actor, order/payment/reference identifiers, amount, request ID, and an explicit `webhookEnrichmentPending=true` marker.

Later webhook enrichment writes `PAYMENT_RECONCILED_ENRICHED`.

## Frontend

The POS exposes `Confirm bank transfer manually` only when the backend grants the manual-confirmation permission. The action requires an explicit browser confirmation and sends the persisted payment reference plus authoritative session amount to the backend.

Once payment is confirmed by webhook or manual confirmation, competing payment actions are hidden and the final sale path remains backend-authoritative.

## Validation performed in this environment

- PHP syntax check passed for all modified PHP files.
- JavaScript syntax check passed for the modified Stimulus controller.
- PHPUnit / Symfony console execution was not available because the uploaded base does not contain `vendor/` and therefore Symfony dependencies are not installed in the working copy.

Run locally after `composer install`:

```bash
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate --skip-sync
php bin/phpunit
```
