# SalesPoint Operations + Current SalesPoint + Checkout Hardening + Statistics Filter + Bank Webhook Token Persistence

Implemented on the supplied `mobile-pos-current(20260924-090954)` base.

## Included

- `SalesPoint` and `SalesPointGroup` persistence.
- `POS` / `TABLE` point types and active/inactive state.
- Admin SalesPoint management under `/admin/sales-points`.
- `SALES_POINT_MANAGE` permission for ADMIN/ROOT.
- Current SalesPoint stored in the authenticated Symfony session.
- POS selector with backend CSRF-protected current-point switching.
- HTTP checkout ignores a browser-supplied point and uses the current server-side session point.
- Checkout idempotency fingerprint includes the SalesPoint.
- Orders persist `sales_point_id`.
- Bank-transfer checkout sessions persist `sales_point_id`, so webhook/manual confirmation-created orders retain the originating point.
- Statistics filter by SalesPoint across sales, payments, debt, top products and top customers.
- Per-bank-account cryptographic webhook token generated and persisted in `payment_bank_accounts`.
- Webhook authentication now resolves `bankAccountId` and validates `X-Webhook-Token` against the persisted account token.
- Token regeneration is available from Payment Settings; the previous token becomes invalid immediately.
- Removed runtime dependency on `BANK_NOTIFICATION_WEBHOOK_TOKEN` and the old token-generation command.
- Migration: `Version20260924120000`.

## Verification

- PHP syntax checks passed for modified PHP files.
- YAML parsing passed for the touched Symfony YAML files.
- `importmap.php` syntax check passed.
- PHPUnit was not executed because the supplied base ZIP does not contain a Composer `vendor/` directory and Composer is not installed in the execution environment.
