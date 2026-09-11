# PHASE 2.11 — Statistics / Reporting / Dashboard

## Scope
Read-only statistics dashboard at `GET /app/statistics`, protected by `STATISTICS_VIEW` through the existing PermissionVoter/PermissionMatrix.

## Date semantics
- `from` and `to` are calendar dates in the configured `APP_TIMEZONE`.
- Query semantics are `[from 00:00:00, to + 1 day 00:00:00)`.
- Maximum range: 366 days.
- Default timezone is `UTC`; deployments may explicitly set `APP_TIMEZONE`.

## Financial semantics
- Gross Sales: total of orders with status `COMPLETED` or `REFUNDED` in the selected order-created period. `CANCELLED` and `DRAFT` orders are excluded.
- Cancelled Amount: total order value of `CANCELLED` orders in the selected period.
- Refunded Amount: `OrderFinancialReversal` rows with type `REFUND` in the selected period.
- Net Sales: Gross Sales minus refunded financial reversals. Cancellation is already excluded from Gross Sales and is therefore not subtracted twice.
- Payments Collected: payments recorded in the selected period minus all financial reversals recorded in the selected period. This represents the net cash movement represented by persisted payment/reversal records.
- AOV: Net Sales divided by qualifying sales orders (`COMPLETED` + `REFUNDED`), with zero returned when the denominator is zero.
- Debt Summary: debts created in the selected period; original amount is the persisted debt amount, collected amount is the sum of all persisted debt payments for those debts, and outstanding is zero for reversed debts otherwise original minus all payments, floored at zero.
- Top Products / Top Customers: completed orders only. This avoids presenting refunded historical sales as current product/customer spend because the MVP has full refunds only and no product-level refund allocation.
- Stock Snapshot is current committed product state, not a historical period snapshot.

These choices follow the existing Phase 2.8 financial reversal model: cancellation/refund creates immutable financial reversal records and reverses debt/stock rather than mutating historical payments.

## Query architecture
The dashboard uses a dedicated DBAL read repository. Each metric is aggregated in SQL; the dashboard does not load historical entities into PHP and does not join multiple one-to-many financial tables in one aggregate query.

No cache, lock, write, authorization, or domain mutation is performed by the read repository.

## Performance
- Top lists are bounded by `limit` (1–100).
- Explicit deterministic ordering is used.
- Existing order/payment/debt/product indexes are reused.
- No `SELECT *` over historical data.

## UI
The Twig dashboard is server-rendered and mobile-first. JavaScript only handles date-preset UX and loading state; financial calculations remain server-side.

## Known limitations
- `APP_TIMEZONE` is an application configuration boundary because the existing domain does not contain a per-user timezone.
- Payment breakdown is gross payment records by method; refunds/cancellations are reported separately because `OrderFinancialReversal` has no payment-method field.
