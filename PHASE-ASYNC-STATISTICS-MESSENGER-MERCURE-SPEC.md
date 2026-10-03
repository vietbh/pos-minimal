# PHASE — Asynchronous Statistics + Symfony Messenger + Mercure

## 1. Goal

Implement asynchronous, reliable statistics projections for the Symfony Mobile POS application. Symfony Messenger processes committed business events and updates statistics tables. Mercure notifies authorized browser clients only after the corresponding projection update has committed.

This phase must preserve the existing transactional source of truth and must not weaken checkout, payment, refund, idempotency, or concurrency guarantees.

## 2. Scope

### In scope
- Statistics read models:
  - `daily_sales_statistics`
  - `daily_product_sales_statistics`
  - `daily_customer_sales_statistics`
- Event publication using a transactional outbox.
- Symfony Messenger transport, handlers, retries, failure transport, and worker operation.
- Idempotent projection updates and concurrency-safe database writes.
- Handling completed orders, cancellations, refunds, and returned items according to existing domain rules.
- Mercure updates after projection commit.
- Stimulus/frontend refresh behavior, including reconnect/resync.
- Tests, reconciliation/rebuild strategy, operational metrics, and documentation.

### Out of scope
- Replacing source-of-truth Order, OrderItem, Payment, Refund, or Customer records with projections.
- Treating Mercure as a durable message queue or database.
- Sending sensitive customer/payment data in Mercure payloads.
- Introducing a new broker without checking current deployment and project conventions.

## 3. Architecture

1. A business use case changes the source-of-truth records inside a database transaction.
2. The same transaction writes an outbox event record.
3. An outbox relay dispatches the event to Symfony Messenger.
4. A Messenger handler validates the event and updates the affected statistics projections transactionally.
5. The handler commits the projection update and records event processing/idempotency state atomically.
6. Only after the projection transaction commits, publish a minimal Mercure invalidation/update event.
7. The frontend receives the update and fetches the authorized Dashboard endpoint for the latest snapshot.
8. If notifications are missed or Mercure is unavailable, the frontend can resync; projection data remains durable.

Do not publish Mercure from inside an uncommitted transaction. Do not publish “statistics updated” before the projection commit.

## 4. Data model

Use the existing project naming, ID types, money abstraction, timezone rules, and store/tenant scope. Treat the names below as proposed logical names and adapt them to existing conventions.

### `daily_sales_statistics`
- `business_date`
- `store_id` / scope key when applicable
- `completed_order_count`
- `gross_sales_minor`
- `discount_minor`
- `refund_minor`
- `net_sales_minor`
- `updated_at`
- Unique key over the business date and scope.

### `daily_product_sales_statistics`
- `business_date`
- `store_id` / scope key when applicable
- `product_id`
- Optional product-name snapshot for historical display
- `quantity_sold`
- `refund_quantity`
- `gross_sales_minor`
- `discount_minor`
- `net_sales_minor`
- `completed_order_count`
- `updated_at`
- Unique key over business date, scope, and product.

### `daily_customer_sales_statistics`
- `business_date`
- `store_id` / scope key when applicable
- `customer_id`
- `order_count`
- `gross_spend_minor`
- `discount_minor`
- `refund_minor`
- `net_spend_minor`
- `last_order_at`
- `updated_at`
- Unique key over business date, scope, and customer.

Do not blindly create duplicate tables if equivalent read models already exist. Confirm the current schema and entity mappings first. Use integer minor units or the existing Money convention, never floating-point arithmetic for monetary values. Define currency and business-date semantics explicitly.

## 5. Domain events

Integrate with the existing domain/application events where available. Candidate event types:
- `OrderCompleted`
- `OrderCancelled`
- `PaymentRefunded`
- `OrderItemsReturned`

Events must contain a stable unique `event_id`, aggregate/order identifier, event type/version, occurrence time, and only the identifiers/data required to reconstruct the projection change. Do not trust client-supplied totals.

Use the authoritative committed business data or immutable event facts to calculate the projection. Define precisely which order statuses, payment states, discounts, taxes, cancellations, and returns count toward each metric.

## 6. Reliability and consistency requirements

- Transactional outbox: write the outbox row in the same transaction as the business change.
- At-least-once delivery must be assumed; handlers must be idempotent.
- Enforce event uniqueness in the database; do not rely on an in-memory “already processed” check.
- Apply projection writes and event-processing/idempotency records in a transaction.
- Use atomic upserts/locking or another database-appropriate concurrency strategy to prevent lost updates.
- Handle out-of-order events with event versions, aggregate sequence numbers, recomputation, or another explicitly tested strategy.
- Retries must not double-count sales, quantities, refunds, or order counts.
- Configure a failure transport and document safe replay procedures.
- A poison message must not block unrelated messages indefinitely.
- Add a reconciliation/rebuild command that can recompute projections from authoritative business records/events.
- Keep checkout successful if Messenger or Mercure is temporarily unavailable, provided the durable outbox record is committed.
- Do not perform a direct non-transactional dispatch-only pattern that can lose an event between business commit and dispatch.

## 7. Messenger and worker operations

- Follow the project's existing transport and deployment configuration.
- Use Doctrine transport for a simpler initial deployment or a dedicated broker such as RabbitMQ when operational needs justify it; decide based on existing infrastructure rather than introducing an unneeded dependency.
- Configure bounded retries/backoff, failure transport, message timeouts, and worker memory/time limits as appropriate.
- Document worker startup, restart, graceful stop, monitoring, and failed-message replay.
- Ensure the worker can process duplicate deliveries safely.

## 8. Mercure and frontend behavior

- Publish only after the projection transaction commits.
- Prefer a small invalidation message, for example: event type, authorized store/scope, affected widget group, and projection version or update timestamp.
- Do not include full customer profiles, payment data, or sensitive transaction payloads.
- Use private topics and authorization consistent with the existing security model. Verify a user cannot subscribe to another store's statistics.
- Debounce/coalesce bursts of updates so the Dashboard does not refetch for every individual message.
- Refresh only affected widgets when practical.
- On reconnect or missed updates, fetch the latest snapshot from the authorized HTTP endpoint.
- Provide polling or explicit refresh as a fallback if Mercure is unavailable.
- Show an updated-at indicator or a subtle syncing state if queue lag is user-visible.

## 9. Dashboard query requirements

- Dashboard endpoints read projections, not an unbounded Order/OrderItem entity graph.
- Filter by a bounded date range and authorized store/scope.
- Use deterministic ordering and pagination/limits for top products and customers.
- Avoid N+1 queries.
- Add indexes only for observed query patterns and verify query plans.
- The source-of-truth endpoint remains authoritative for checkout/payment results; asynchronous statistics may be eventually consistent.

## 10. Tests and acceptance criteria

### Unit/domain/application
- Event mapping and business-date/currency calculations.
- Gross, discount, net sales, refund, returned quantity, and order-count semantics.
- Correct behavior for completed, cancelled, refunded, and partially returned orders.
- Projection calculations do not use floating-point money.

### Integration/database
- Outbox record is committed or rolled back with the business transaction.
- Duplicate event delivery changes the projection only once.
- Concurrent events for the same store/date/product/customer do not lose increments.
- Failure and retry do not double-count.
- Out-of-order events are handled according to the documented strategy.
- Rebuild/reconciliation reproduces expected totals.
- Unique constraints and upserts work with the project's actual database engine.

### Mercure/security/HTTP
- No update is published before projection commit.
- Mercure failure does not roll back committed business transactions or corrupt projections.
- Only authorized users can subscribe to their permitted topics.
- Payload contains no sensitive information.
- Dashboard fetch after notification returns the committed projection.
- Reconnect/resync restores current state after missed notifications.

### Operational
- Worker and outbox relay instructions are documented.
- Failed messages can be inspected and safely replayed.
- Queue lag, failure count, projection update latency, and reconciliation discrepancies are observable.
- Existing tests and critical checkout/idempotency/concurrency tests remain green.

## 11. Suggested implementation order

1. Inspect current entities, schema, domain events, transaction boundaries, Messenger config, security, and deployment setup.
2. Agree metric definitions and reuse existing Money, timezone, tenant/store, and naming conventions.
3. Add migrations and projection persistence.
4. Add outbox entity/table and transactional event recording.
5. Add relay/dispatch flow and Messenger transport configuration.
6. Implement idempotent projection handlers and concurrency-safe updates.
7. Add rebuild/reconciliation tooling.
8. Add authorized Dashboard read handlers and bounded queries.
9. Publish Mercure invalidations after projection commit.
10. Integrate Stimulus subscribe, debounce, widget refresh, and reconnect resync.
11. Add tests and operational documentation.
12. Run the complete existing test suite plus focused integration/concurrency tests; report commands and actual results without claiming unrun tests passed.

## 12. Definition of done

- Source-of-truth business transactions remain correct and independent of Mercure availability.
- Events are durably recorded through the transactional outbox.
- Projection processing is idempotent, retry-safe, and concurrency-safe.
- The three statistics read models can be rebuilt and reconciled.
- Mercure is published only after committed projection changes and is protected by authorization.
- Dashboard updates near-real-time without querying the full transactional entity graph.
- Tests, migrations, worker operations, failure replay, and recovery steps are documented.
