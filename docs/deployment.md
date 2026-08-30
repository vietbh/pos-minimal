# Mobile POS — Deployment Runbook

## Production model

- Symfony 7.4 + PHP CLI/FPM
- MySQL 8
- Doctrine ORM
- Doctrine Messenger transport
- Cron-driven bounded worker when no process manager is available
- Filesystem/object storage for product images
- Daily database and product-image backups

Critical business mutations remain synchronous and transactional: Checkout, Payment, Debt Payment, Stock Adjustment and Order Cancellation.

## Required production environment

Set real server-side values for:

- `APP_SECRET`
- `DATABASE_URL`
- `MESSENGER_TRANSPORT_DSN`
- `MESSENGER_FAILED_TRANSPORT_DSN`
- `PRODUCT_IMAGE_STORAGE_ROOT`
- `IMAGE_MAGICK_BINARY`
- `DEFAULT_URI`

Do not commit production secrets.

## Release procedure

1. Verify disk space and backup destination.
2. Create a database backup.
3. Create a product-image backup.
4. Deploy the release.
5. Run `composer install --no-dev --optimize-autoloader`.
6. Run `php bin/console doctrine:migrations:migrate --no-interaction --env=prod`.
7. Run `php bin/console cache:clear --env=prod`.
8. Recycle/restart workers. For Cron-driven workers, allow the bounded process to exit and start the new release.
9. Run `php bin/console lint:container --env=prod`.
10. Run smoke checks: `/health`, login, product search, Checkout, Debt Payment and Stock view.

Never use `doctrine:schema:update --force` in production.

## Worker

Preferred bounded command:

```bash
./scripts/worker.sh
```

The script uses `flock` and bounds work by message count, time and memory. If a process manager is available, restart the worker after deployment instead of relying on Cron.

Manual inspection/recovery:

```bash
php bin/console messenger:failed:show --env=prod
php bin/console messenger:failed:retry --env=prod
php bin/console messenger:failed:remove --env=prod
```

## Cron

Use CLI Cron, not a public HTTP endpoint. See `docs/cron.example`.

## Backup

Database:

```bash
./scripts/backup-db.sh
```

Product images:

```bash
./scripts/backup-files.sh
```

Backups must be copied to storage separate from the primary database/server when possible.

## Restore

Stop web/worker processes before a coordinated restore. Restore database and files from the same recovery point when possible, then clear cache, run migrations only when appropriate for the restored release, start worker/Cron, and execute the smoke checklist.

Database restore is destructive and requires:

```bash
CONFIRM_RESTORE=YES ./scripts/restore-db.sh /path/to/backup.sql.gz
```

File restore:

```bash
CONFIRM_RESTORE=YES ./scripts/restore-files.sh /path/to/product-images.tar.gz
```

Never restore production data into the wrong environment.

## Rollback

Before migrations: revert code and restart/recycle workers.

After migrations: do not blindly roll back migrations. Evaluate backward compatibility and use backup/restore for destructive changes.

## Operational targets

MVP targets:

- RPO: <= 24 hours
- RTO: <= 4 hours

These are operational targets, not an enterprise SLA.
