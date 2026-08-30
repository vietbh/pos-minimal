# Operations Runbook

## Worker failure

Check `php bin/console messenger:failed:show --env=prod` and worker logs. Fix the underlying cause, then retry only the affected messages.

## Queue backlog

Check worker process, database availability, storage availability and failed transport. Do not increase worker count blindly; protect MySQL and image-processing CPU/memory.

## Cron failure

Run the exact Cron command manually using the same absolute PHP binary, project path and environment. Check exit code and `var/log/cron.log`.

## Disk full

Check backups, product images, logs and failed messages. Remove only technical/expired data according to policy. Never delete financial history to recover disk space.

## Database unavailable

Do not repeatedly restart business workers against an unavailable DB. Restore DB connectivity, then restart/retry according to the failed-message policy.

## Storage unavailable

Image processing should fail safely and remain recoverable. Do not mark an image READY when storage writes failed.

## Backup failure

Treat a failed backup as an operational incident. Check disk, credentials and database connectivity; do not report backup success unless the artifact exists and is non-empty.
