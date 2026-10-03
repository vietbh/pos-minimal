# Phase — Async Statistics + Symfony Messenger + Mercure

## What is implemented

- Transactional statistics outbox recording on successful checkout, explicit order completion, cancellation, and full refund.
- Symfony Messenger message `RebuildStatisticsForOrder` routed to the existing `async` transport.
- Idempotent projection handler using `statistics_processed_events` and per-day/scope DB locks.
- Three read models: daily sales, daily product sales, and daily customer sales.
- A separate notification outbox; Mercure publish failures remain pending for retry.
- Private Mercure invalidations and an HttpOnly subscriber authorization cookie for the selected statistics scope.
- Stimulus controller that listens to Mercure and refreshes the current authorized HTTP snapshot.
- Historical backfill command and a feature flag to avoid switching dashboard reads before backfill.

## Business semantics in this phase

- Only orders currently in `COMPLETED` status contribute to the daily projection tables.
- `gross_sales` / `gross_spend` are the sum of order/item subtotals before order discount; `discount_amount` is the order discount; `net_sales` / `net_spend` are order totals after discount.
- Product projection records sold quantity and line-item subtotal. Order-level discounts are not allocated to individual products.
- Fully cancelled/refunded orders are removed from these projections when their event is processed; partial item refunds are not currently represented by the existing full-refund workflow.
- Scope `sales_point_id = 0` means the all-sales-points aggregate. Positive IDs are per-sales-point projections.
- `APP_TIMEZONE` determines the interpretation of naive database timestamps for the projection business date. Set it to the intended business timezone before backfilling if daily boundaries should follow Vietnam local time.

## Setup

1. Set `APP_PUBLIC_URL` to the canonical application origin used by the browser (for example `http://localhost:8000`).
2. Set a strong random `MERCURE_JWT_SECRET`. The app and Mercure Hub must use the exact same secret. Do not use the Compose development fallback in production.
3. For PHP running on the host, set:
   - `MERCURE_PUBLISH_URL=http://localhost:3000/.well-known/mercure`
   - `MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure`
   For PHP inside a Compose service on the same network, use `http://mercure/.well-known/mercure` for the publish URL. The public URL must be reachable by the browser.
4. Start MySQL and Mercure: `docker compose up -d database mercure`.
5. Run `php bin/console doctrine:migrations:migrate --no-interaction`.
6. Stop any Messenger workers and outbox relay schedules, then run `php bin/console app:statistics:rebuild-projections` once to backfill the existing order history.
7. Only after the backfill succeeds, set `STATISTICS_USE_PROJECTIONS=1` and clear cache/restart the PHP process. Keep it `0` if you want to retain the existing direct aggregate queries.
8. Start the Messenger worker: `php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M`.
9. Run these commands every minute using cron/systemd timer/supervisor scheduling:
   - `php bin/console app:statistics:dispatch-outbox`
   - `php bin/console app:statistics:publish-notifications`
   Or schedule `scripts/statistics-relay.sh` every minute under the production environment; it uses `flock` to avoid overlapping relay runs.

## Recovery and operations

- A business event and its outbox row are written on the same DB connection and transaction.
- Duplicate Messenger deliveries are ignored by `event_id`; projection rebuilding is serialized per business date/scope.
- The projection rebuild command is a maintenance operation. Pause workers and relay commands before running it so an event handler cannot race the full-table rebuild.
- Mercure events contain only an invalidation type, event ID, scope ID, and timestamp. The browser reloads the authenticated page; it does not receive financial/customer aggregates in the event payload.
- If Mercure is unavailable, notifications remain in `statistics_notification_outbox` and can be retried by rerunning the publish command. The committed order and projection data do not depend on Mercure.
- Failed Messenger messages can be inspected with `php bin/console messenger:failed:show`; replay with `php bin/console messenger:failed:retry` after fixing the cause.
- Verify deployment configuration and run the project's complete PHPUnit suite after migration.

## Acceptance checks

- Migration succeeds on the project's MySQL 8 schema.
- Checkout/completion/cancel/refund write outbox rows inside their business transaction; rollback removes both the business mutation and event.
- Re-dispatching the same event ID does not double-count.
- Concurrent handlers for the same date/scope serialize on `statistics_projection_locks`.
- Backfill counts match direct aggregate queries for the documented completed-order semantics.
- With `STATISTICS_USE_PROJECTIONS=1`, top product/customer widgets read projection tables.
- Mercure is published only from the notification outbox after projection commit; private subscriber cookie only authorizes the selected statistics topic.

## Validation performed in this coding environment

- PHP syntax lint passed for all newly added/modified PHP files.
- YAML parsing passed for `config/services.yaml`, `config/packages/messenger.yaml`, and `compose.yaml`.
- Shell syntax validation passed for `scripts/statistics-relay.sh` and `scripts/worker.sh`.
- A direct PHP smoke test decoded the generated subscriber JWT and verified its Mercure subscribe claim.
- Full PHPUnit, Symfony container compilation, Doctrine migration execution, and live Mercure/worker tests were not run here because this exported source archive does not contain `vendor/`, and this environment does not provide Composer or Docker/MySQL executables. Run those checks in the actual project environment before enabling projections in production.
