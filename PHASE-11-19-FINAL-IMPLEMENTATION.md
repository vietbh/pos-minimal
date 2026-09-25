# Final Phase 11–19 Consolidated Implementation

This release consolidates the remaining post-Phase-10 production work into one bounded implementation pass.

## Scope

- Performance baseline and production cache configuration.
- Symfony Messenger/Doctrine transport remains the async mechanism for secondary work only.
- Product image processing remains asynchronous and retryable.
- Cleanup/requeue commands remain bounded by batch size.
- Error handling and structured production logging remain server-side.
- Production readiness, database backup, worker, cron and deployment runbook are consolidated.
- Final regression contract verifies the critical invariants introduced by the previous phases.
- Final project structure is documented without moving business logic between layers.

## Business-mutation rule

The following remain synchronous and transaction-bound:

- checkout
- payment
- debt payment
- stock mutation/adjustment/reversal
- order cancellation
- debt creation

Messenger is reserved for secondary work such as product-image processing, notifications and email/SMS integrations already routed by the application.

## Cache/performance

Production Doctrine system/result caches use Symfony cache pools. External VietQR bank metadata is cached for 24 hours. No new Redis/RabbitMQ/Kafka/Kubernetes dependency is introduced.

## Worker/cron

Recommended bounded worker:

```bash
APP_ENV=prod php bin/console messenger:consume async --limit=100 --time-limit=3600 --memory-limit=256M --failure-limit=10
```

Recommended maintenance commands are documented in `FINAL-PRODUCTION-RUNBOOK.md`.

## Production environment safety

`.env.prod` is a committed template only. Production secrets and database credentials must be supplied through real environment variables or the deployment secret store. Placeholder values are intentional and must not be used as production credentials.

## Validation limitation

This source package may not contain `vendor/`. Full PHPUnit, Twig lint, migration and Messenger runtime checks must be run after `composer install` in the target environment.
