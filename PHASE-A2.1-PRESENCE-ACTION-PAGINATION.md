# PHASE A2.1 — Root Analytics Presence + Action Filtering/Pagination

Base: `mobile-pos-current.zip` supplied by the user.

## Changes

- Distinguish account state (`Active` / `Inactive`) from presence (`Online` / `Idle` / `Offline`).
- Presence is based on the most recent ACTIVE UserSession:
  - Online: activity < 2 minutes.
  - Idle: 2–15 minutes.
  - Offline: >= 15 minutes or no active session.
- Added authenticated POST heartbeat endpoint `/app/activity/heartbeat` with CSRF.
- Added Stimulus `activity-tracker` that sends a heartbeat every 60 seconds only while the page is visible.
- Heartbeats update `lastActivityAt` but do not increment `requestCount`.
- Every authenticated main HTTP request records real request count, method, path and IP.
- Root analytics action list is now DB-filtered and server-side paginated (20/page).
- Action filters: actor user, exact action, free-text search, from date, to date.
- Action options are loaded from all non-root audit records, not just the current page.
- ROOT remains excluded from user activity and audit presentation.
- No GPS is collected; access location remains server-observed IP/route.

## No schema migration

This phase uses the existing `UserSession.requestCount`, `lastActivityAt`, `lastRequestMethod`, `lastRequestPath`, `ipAddress` and existing AuditLog indexes. No new database columns are required.

## Verification

PHP syntax and translation YAML were checked in the provided base. Full Symfony container/PHPUnit execution was not possible in the extracted archive because `vendor/` is not present; run in the project:

```bash
php bin/console lint:container
APP_ENV=test php bin/phpunit tests/Integration/Http/RootAnalyticsControllerTest.php
APP_ENV=test php bin/phpunit tests/Integration/Security/RemoteSessionRevocationTest.php
APP_ENV=test php bin/phpunit tests/Integration/Security/SwitchUserSecurityTest.php
```
