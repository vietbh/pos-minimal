# PHASE 2.32 — POS Sale Completion & New Sale State Hardening

## Scope

Implemented POS-only sale reset hardening. Bank webhook and payment reconciliation are frozen and were not changed.

## Changes

- Consolidated New Sale and automatic success timeout into `resetSale()`.
- Reset sale-scoped cart, customer, payment, QR, PaymentReference UI, timers, polling, and success state.
- Restored CASH as the default payment method for a fresh sale when available.
- Cleared sale-scoped product/customer search state.
- Added `saleGeneration` guards so stale polling/manual-completion responses cannot mutate a new sale.
- Added generation guard to the 5-second Payment Received auto-reset.
- Fixed Stimulus `disconnect()` cleanup structure.
- Updated the compiled POS controller used by the frontend contract tests.
- Added frontend contract tests for canonical reset and stale async protection.

## Explicitly Frozen

No changes were made to bank webhook, notification ingestion, PaymentReference reconciliation, or webhook transaction/idempotency implementation.
