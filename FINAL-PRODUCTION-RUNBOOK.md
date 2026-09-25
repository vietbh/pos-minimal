# Final Production Runbook

## 1. Pre-release

```bash
git status --short
git diff --check
composer validate --strict
composer install --no-interaction --prefer-dist
php bin/console lint:yaml config translations
php bin/console lint:twig templates
php bin/console asset-map:compile
APP_ENV=test php bin/phpunit
```

## 2. Deploy

```bash
git fetch origin
git checkout <EXACT_SHA>
composer install --no-dev --optimize-autoloader --no-interaction
APP_ENV=prod php bin/console doctrine:migrations:status
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console cache:warmup
php bin/console messenger:stop-workers
```

Reload PHP-FPM only after the new release is ready. Keep the previous release available for rollback.

## 3. Worker

Run a bounded worker under the process supervisor:

```bash
APP_ENV=prod php bin/console messenger:consume async --limit=100 --time-limit=3600 --memory-limit=256M --failure-limit=10
```

Inspect failures:

```bash
APP_ENV=prod php bin/console messenger:failed:show --max=50
```

Do not run unbounded workers without an external supervisor.

## 4. Scheduled maintenance

Recommended schedules:

```text
Every 5 minutes:  app:product-images:requeue 10 100
Every 15 minutes: app:session:cleanup 24 200
Hourly:          app:idempotency:cleanup 30 500
Daily:           app:product-images:cleanup 24 500
Daily:           app:backup:database scheduled
```

Tune retention and batch sizes to the actual production workload.

## 5. Readiness

```bash
APP_ENV=prod php bin/console app:production:check
```

The command must pass before traffic is switched to the release.

## 6. Smoke tests

- Login.
- Open POS.
- Search product.
- Add product to cart.
- Complete cash payment.
- Start bank transfer flow.
- Verify payment state polling/manual confirmation.
- Open order detail.
- Verify authorization boundary.
- Verify statistics.
- Verify product image upload/processing.
- Check application log for unexpected errors.

Never perform a destructive checkout/refund against production merely as a smoke test unless a controlled test order is explicitly approved.

## 7. Rollback

```bash
git checkout <PREVIOUS_EXACT_SHA>
composer install --no-dev --optimize-autoloader --no-interaction
APP_ENV=prod php bin/console cache:clear
php bin/console messenger:stop-workers
```

Do not roll back database migrations blindly. Review migration compatibility before any database rollback.
