# Production Operations Runbook

## Health

- `GET /health` checks database connectivity and product-image storage writability.
- HTTP 200 means the checked dependencies are healthy.
- HTTP 503 means at least one checked dependency failed.

## Readiness check

Run:

```bash
APP_ENV=prod php bin/console app:production:check
```

This is non-destructive. It verifies production debug mode, database connectivity and storage writability.

## Database backup

Run from the deployed release:

```bash
APP_ENV=prod php bin/console app:backup:database daily
```

The command uses `mysqldump --single-transaction --quick` and writes a timestamped SQL file under `PRODUCTION_BACKUP_ROOT`.

The backup file is only considered valid when the command succeeds and the output file is non-empty.

Do not store backups only on the same disk as the production database. Copy them to an independent backup location according to the deployment environment's retention policy.

## Restore drill

Restore only in an isolated environment. Never overwrite production as a restore test.

1. Provision an empty MySQL database.
2. Restore the SQL backup.
3. Configure the application to use the restored database.
4. Restore required product-image storage.
5. Run `php bin/console doctrine:schema:validate`.
6. Run the critical smoke flow: login → product search → checkout → order → debt/payment → stock.
7. Record restore duration and any missing dependencies.

## Worker

Inspect the Messenger worker and failed transport after every deployment. Failed messages must be investigated before retrying; do not blindly retry non-idempotent operations.

## Cron

Technical cleanup jobs are safe candidates for scheduled execution:

- `app:session:cleanup`
- `app:idempotency:cleanup`
- `app:product-images:cleanup`

Never use cleanup jobs to delete financial history: orders, payments, debts, debt payments, stock movements or audit logs.

## Deployment smoke test

1. Login
2. Open POS
3. Search product
4. Create/use customer
5. Checkout
6. Verify order/payment/stock
7. View debt and pay debt when applicable
8. Verify order history
9. Verify admin authorization
10. Verify worker and cron
11. Verify `/health`
