# PHASE 2.8.2 — Order Lifecycle Mutation

Implemented:

- `COMPLETED -> CANCELLED`
- `COMPLETED -> REFUNDED`
- immutable `OrderFinancialReversal` record
- `DebtStatus::REVERSED`
- stock restoration through `SALE_REVERSAL`
- order row locking + deterministic product locking
- idempotency integration using existing infrastructure
- atomic audit + business mutation transaction
- HTTP endpoints for cancel/refund
- integration + HTTP tests

## Financial model decision

The existing `Payment` entity enforces positive immutable payment amounts. It is therefore **not mutated** for cancellation/refund. A dedicated immutable `OrderFinancialReversal` record stores the positive reversed/refunded amount and the semantic type (`CANCEL` or `REFUND`). This preserves the original payment history and avoids introducing signed values into `Payment`.

## Important

The uploaded source snapshot does not include `vendor/`, so PHPUnit/Symfony container validation could not be executed in this environment. PHP syntax lint for all changed PHP files passes.

Run on the project:

```bash
APP_ENV=test php bin/phpunit tests/Integration/Application/Order/Lifecycle/OrderLifecycleTest.php
APP_ENV=test php bin/phpunit tests/Integration/Http/OrderLifecycleControllerTest.php
APP_ENV=test php bin/phpunit
APP_ENV=test php bin/console doctrine:schema:validate
APP_ENV=test php bin/console lint:container
```
