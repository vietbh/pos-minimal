# PHASE 2.19 — Statistics KPI Goals

Implemented on top of the existing Phase 2.11 Statistics read model.

## Scope

- KPI targets for revenue, units sold, buying customers and outstanding debt.
- Start/end date for every goal.
- `at_least` targets for cumulative metrics.
- `at_most` target for outstanding debt.
- Actual progress comes from existing Statistics query infrastructure.
- Estimated achievement date is calculated from observed pace only when enough elapsed data exists.
- Debt goals capture an initial outstanding balance when created, allowing reduction-rate projection.
- CSRF-protected create/delete actions.
- Permission remains `STATISTICS_VIEW` / existing Voter + PermissionMatrix.

## Important semantics

- Statistics remains read-side; KPI configuration is separate from Order/Debt/Product transaction lifecycles.
- No fake data and no Twig/Stimulus business aggregation.
- Debt uses current committed outstanding balance; its target direction is `at_most`.
- If there is insufficient history or zero pace, the UI explicitly says that an estimate is unavailable.
- Projection is an estimate, not a promise.

## Release checks

Run in the full repository environment:

```bash
php bin/console doctrine:schema:validate
php bin/console lint:twig templates/statistics/index.html.twig
php bin/console lint:yaml translations/messages.vi.yaml translations/messages.en.yaml
APP_ENV=test php bin/phpunit
```
