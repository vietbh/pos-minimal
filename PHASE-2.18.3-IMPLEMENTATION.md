# PHASE 2.18.3 — Dashboard Active Tab Preference + Role Default + POS Color System

Implemented against the existing Phase 2.18.1/2.18.2 codebase.

## Implemented

- Added persisted `dashboard_default_tab` user preference with default `auto`.
- Reused existing User preference/settings architecture; no second preference engine.
- Dashboard navigation now resolves in this order:
  1. existing permissions;
  2. saved dashboard preference;
  3. role default when preference is `auto`;
  4. Overview fallback;
  5. first available tab if Overview is unavailable.
- Admin/Root role default is Administration; regular users default to Overview.
- Settings only exposes dashboard tabs currently allowed by `PermissionMatrix`.
- Unauthorized dashboard default values are rejected and not persisted.
- Active tab remains separate from default tab; URL `?tab=` can select a permitted tab without changing the saved preference.
- Added Doctrine migration `Version20260930150000`.
- Added resolver and HTTP tests for default-tab behavior and permission-aware persistence.
- Rebound the canonical UI semantic tokens to the existing Mobile POS teal/green palette for Light/Dark/System.
- Updated Dashboard active tab, primary buttons, POS navigation, KPI/chart surfaces, focus states and settings controls to use the POS palette.
- Preserved existing business logic, Permission Matrix/Voter architecture, authentication flow, POS isolation and user preferences.

## Validation

PHP syntax validation passed for all changed PHP files.

PHPUnit could not be executed in this build environment because `vendor/bin/phpunit` is not present in the source ZIP.
