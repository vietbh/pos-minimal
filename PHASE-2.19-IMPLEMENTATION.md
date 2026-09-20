# PHASE 2.19 — Dashboard Data / Reporting UX Hardening

Implemented on top of the uploaded `mobile-pos-current.zip` baseline.

## Implemented
- Introduced `StatisticsPeriod` read-model value object.
- Introduced `StatisticsPeriodResolver` so reporting presets use the configured application timezone instead of browser timezone.
- Server-side handling for reporting presets:
  - `custom`
  - `today`
  - `yesterday`
  - `week` (Monday through today)
  - `month`
- Existing `StatisticsQueryInput` remains the final guard for non-empty and maximum 366-day ranges and Top-N bounds.
- Statistics page now preserves the resolved preset selection after navigation.
- Existing Dashboard/Home and Statistics query architecture reused; no duplicate repository.
- Added unit coverage for timezone boundaries, presets and invalid dates/presets.

## Intentionally unchanged
- Checkout/payment/stock/debt business rules.
- Existing statistics aggregation semantics.
- Permission enforcement: `STATISTICS_VIEW` remains backend-enforced.
- POS workspace isolation.

## Validation
- PHP syntax checks: expected to pass for changed PHP files.
- PHPUnit execution depends on the environment's PHP extensions; report the actual result rather than assuming success.
