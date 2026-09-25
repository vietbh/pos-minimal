# PHASE 10.8 — Critical Business Flow Integration

## Scope

Harden the UI/application boundary around the critical POS business flows without moving business rules into Twig or Stimulus.

## Implemented

- POS checkout keeps the existing server-authoritative flow for checkout, payment-session status, bank-transfer confirmation and order completion.
- Existing CSRF and idempotency contracts remain intact.
- POS retry behavior continues to reuse the logical checkout idempotency key.
- Payment-session polling remains the source of truth for bank-transfer state.
- Manual bank confirmation and paid-order completion remain explicit backend operations.
- POS errors are now mapped to translated user-facing messages; backend `errorCode` values and exception text are not rendered to the cashier.
- Order cancel/refund UI keeps CSRF + `Idempotency-Key` + permission enforcement and now maps backend error codes to translated user-facing messages instead of exposing technical codes.
- Added a frontend contract test covering critical checkout/payment/lifecycle boundaries.
- Added Vietnamese and English translations for the new error mapping.

## Architecture constraints preserved

- Backend/domain/application remains the source of truth.
- No stock, payment, order-state or transaction rules were added to JavaScript.
- No public compiled controller asset was modified.
- Source Stimulus controllers remain under `assets/controllers/` only.
- No technical `errorCode` or backend exception message is shown directly to users.

## Validation

Successful static validation:

- `php -l src/Controller/Order/CheckoutController.php`
- `php -l src/Controller/Order/OrderLifecycleController.php`
- `php -l tests/Frontend/Phase108CriticalBusinessFlowContractTest.php`
- `node --check assets/controllers/pos_checkout_controller.js`
- `node --check assets/controllers/order_lifecycle_controller.js`
- YAML parsing for `translations/messages.vi.yaml`
- YAML parsing for `translations/messages.en.yaml`

Full Symfony/Twig/PHPUnit runtime validation requires the repository `vendor/` dependencies to be installed.
