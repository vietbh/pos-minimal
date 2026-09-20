# PHASE UI/UX 7 — Admin & Management Workspace

## Scope

Mobile-first UX hardening for Product, Product Category, Stock, Payment Bank Accounts and Statistics.

## Changed

- Product catalog/list/filter/create/edit/detail presentation hardened for mobile.
- Product category list/form hardened with clear permission-gated actions.
- Stock list/detail and adjustment forms use readable cards, summaries and horizontally scrollable history.
- Receiving bank account management uses clear cards and form grouping without exposing additional secrets.
- Statistics receives a consistent management-workspace header/filter presentation while keeping server-side filtering.
- Shared admin workspace CSS adds responsive cards, facts, summaries, tables, forms, status semantics and 360px behavior.
- Added frontend contract coverage for the management workspace.

## Preserved

- Existing routes and controllers.
- Backend permission enforcement.
- CSRF protection.
- Product, stock and payment business semantics.
- Transaction boundaries.
- Idempotency behavior for stock adjustment.
- Server-authoritative statistics queries.
- Existing Turbo/Stimulus architecture.

## Verification

Run in the real repository:

```bash
php bin/phpunit
php bin/console lint:twig templates
php bin/console lint:yaml translations/messages.en.yaml
php bin/console lint:yaml translations/messages.vi.yaml
```

The sandbox baseline does not include Composer dependencies, so the full Symfony PHPUnit/Twig/container suite was not claimed as executed here.

## I18n Hardening — UI/UX 5–7

The UI layer was audited so user-facing presentation strings are routed through Symfony translations instead of being fixed to one language.

### Added / changed

- Added EN/VI translation coverage for Phase UI/UX 7 management screens:
  - Product catalog/create/edit/detail
  - Product categories
  - Stock list/detail/adjustment
  - Receiving bank accounts
  - Statistics
- Extended translation coverage across existing application UI used by UI/UX 5–7:
  - Orders
  - Customers
  - Debt
  - Application/Profile/Settings
  - Product image upload
  - POS interaction/fallback messages
- Added translation-driven Stimulus values for client-owned fallback/status messages in:
  - `pos_checkout_controller.js`
  - `debt_payment_controller.js`
  - `order_lifecycle_controller.js`
  - `product_image_upload_controller.js`
- Existing backend-provided error messages remain authoritative; frontend does not rewrite server business errors.
- Added translation contract test coverage for referenced Twig translation keys.

### Preserved

- Business logic
- Transaction boundaries
- Idempotency
- Concurrency
- Permission/Voter enforcement
- CSRF
- Turbo/Stimulus architecture
- Server-authoritative financial/stock state
