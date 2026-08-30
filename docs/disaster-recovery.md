# Disaster Recovery

## Critical data

Orders, payments, debts, debt payments, products/stock, stock movements, audit logs and idempotency records are business data. Product images are supporting business data. Cache and technical logs are rebuildable/replaceable.

## Recovery sequence

1. Stop web traffic and workers.
2. Preserve the incident evidence/logs if possible.
3. Provision/restore the application runtime.
4. Restore the database backup.
5. Restore product-image storage from the matching recovery point.
6. Verify environment variables and PHP CLI version.
7. Clear Symfony cache.
8. Verify migrations/schema against the restored release.
9. Verify Messenger transport tables and failed messages.
10. Reinstall/re-enable Cron and verify its absolute PHP/project paths.
11. Start bounded worker execution.
12. Run `/health` and critical POS smoke tests.
13. Verify a read-only sample of orders, payments, debts, stock movements and audit logs.

## RPO/RTO

MVP operational target: RPO <= 24h and RTO <= 4h.

## Queue recovery

Do not blindly retry all failed messages. Inspect the failed message, determine whether the failure is transient or permanent, fix the cause, retry safely, then verify the resulting business state.

## Backup safety

Keep backups outside the primary database/server when possible. Restrict permissions and never commit backup artifacts to Git.
