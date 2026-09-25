# PHASE 10.7 — Admin / Statistics / Security UI

## Scope

Presentation and UI integration only. Backend/domain/application/security remain the source of truth.

## Implemented

- Hardened statistics page for mobile-first layouts and 360px/390px widths.
- Removed inline statistics CSS and consolidated presentation rules in `assets/styles/phase-uiux-7.css`.
- Added semantic headings, table captions, table scopes, filter help, loading state and live status.
- Statistics filter Stimulus controller is presentation-only; server remains authoritative for presets, date ranges, limits and results.
- Added accessible statistics empty states and responsive table wrappers.
- Hardened admin workspace controls, statuses and destructive/security presentation primitives.
- Hardened 403/security presentation styling without changing authorization behavior.
- Added Vietnamese/English translations for new UI labels.

## Security / authority

- Existing `PermissionVoter` / `PermissionMatrix` enforcement is unchanged.
- No authorization decision was moved to JavaScript.
- No financial/business calculation was moved to Twig or JavaScript.
- No backend/domain/application behavior was changed.

## Asset rule

Starting with Phase 10.7, source Stimulus controllers are changed only under `assets/controllers/`.

`public/assets/controllers/*` is intentionally excluded from this phase and must not be staged or committed.

## Validation

Recommended from the repository root:

```bash
php -l src/Controller/Statistics/StatisticsController.php
php bin/console lint:twig templates
php bin/console asset-map:compile
APP_ENV=test php bin/phpunit tests/Frontend/AdminUiContractTest.php
APP_ENV=test php bin/phpunit tests/Frontend/AdminManagementUiContractTest.php
APP_ENV=test php bin/phpunit tests/Frontend/MobileUxContractTest.php
APP_ENV=test php bin/phpunit tests/Frontend/ResponsiveShellContractTest.php
APP_ENV=test php bin/phpunit tests/Frontend/UiTranslationContractTest.php
APP_ENV=test php bin/phpunit tests/Integration/Http/StatisticsControllerTest.php
APP_ENV=test php bin/phpunit
```

## Statistics UI follow-up

The statistics screen was tightened after visual review: KPI cards now use a responsive 1/2/3-column grid, monetary values use the existing `money_vnd` presentation filter, payment method enums are translated through existing payment labels, and payment counts are shown as secondary context. The backend statistics result and server-side filtering remain unchanged.



## Statistics card title alignment follow-up

- Standardized top-level statistics card title padding and line-height so `Thanh toán`, `Công nợ`, `Tổng quan kho`, `Sản phẩm bán chạy`, and `Khách hàng nổi bật` share the same visual inset.
- Kept KPI `h3` cards unchanged and limited the alignment rule to direct `h2` card titles.
- Mobile <=380px compensates for the card padding so the title remains aligned with the card content.
- No business logic or compiled `public/assets/controllers/` output was modified.
