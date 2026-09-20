# PHASE 2.22 — Customers / Customer Detail + Debt UX Hardening

## Implemented

- Added a dedicated customer detail application read model:
  - customer identity/contact fields
  - total debt count
  - debt original/paid/remaining summary
  - latest 10 debt records
  - total customer order count
  - latest 10 orders
- Added `GetCustomerInput`, `GetCustomerHandler`, `CustomerDetailResult`, and `CustomerOrderSummaryResult`.
- Extended `CustomerQueryRepositoryInterface` and Doctrine query implementation.
- Customer detail controller now uses the application query handler instead of exposing the customer entity directly to the view.
- Reworked customer detail UI for mobile-first readability and accessibility.
- Added permission-aware debt/order sections.
- Added explicit empty states and links to the full debt/order workspaces.
- All money presentation uses `money_vnd`; no financial calculation is performed in Twig.
- Debt summary uses separate aggregate queries to avoid multiplying original debt amounts when a debt has multiple payments.
- Bounded detail payload to 10 debts and 10 recent orders.
- Added application and HTTP integration coverage.
- Updated `docs/UI-UX-DESIGN-SYSTEM.md`.

## Validation

- PHP syntax: run `php -l` on changed PHP files.
- Twig lint: run `php bin/console lint:twig templates`.
- Container validation: run `php bin/console lint:container`.
- PHPUnit should be run in an environment containing the required `dom`, `mbstring`, and `xmlwriter` PHP extensions.
