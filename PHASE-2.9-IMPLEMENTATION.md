# PHASE 2.9 — MVP Admin + Product + Customer + Stock UI

Implemented against the supplied Phase 2.9 current source snapshot.

## Added
- Admin Product UI: search, create, edit, detail, price change, activate/deactivate, stock adjustment, image entry point, stock history.
- Customer UI: search, create, edit, detail.
- Stock UI: product search, current stock, adjustment, movement history.
- Permission-aware actions using existing Permission/Voter attributes.
- CSRF-protected mutations.
- Stock adjustment keeps the existing idempotency contract through `Idempotency-Key`.
- Mobile-first shared UI styles and empty/loading-safe form states.
- Frontend and route-registration contract tests.

## Architectural boundaries
- Existing Application handlers and Domain objects remain authoritative.
- No business totals/stock/debt calculation added to Twig/JavaScript.
- No duplicate repositories or fake APIs.
- Product image processing remains delegated to the existing Phase 1.13 upload flow.

## Validation on this patch snapshot
PHP syntax checks for the three new controllers pass. The uploaded snapshot does not contain the project's `bin/console`/vendor runtime, so Symfony/PHPUnit commands cannot be truthfully reported as executed here.

Run on the working tree:

```bash
composer dump-autoload
APP_ENV=test php bin/phpunit
APP_ENV=test php bin/console doctrine:schema:validate
APP_ENV=test php bin/console lint:container
php bin/console lint:twig templates
php bin/console asset-map:compile
```
