# PHASE 2.18.1 — Dashboard Simplification + Role-Aware Navigation + Simple Overview Chart

Implemented directly on the current Mobile POS base.

## Implemented

- Home reduced to a minimal application dashboard.
- Removed duplicate role-function and quick-access grids from the Overview.
- Today section keeps only current sales/order data and outstanding debt when non-zero.
- Added one backend-driven 7-day revenue bar chart.
- Chart uses the existing Statistics read-side repository and does not calculate business data in Twig/Stimulus.
- Backend returns chart points, bar percentages and textual summary data.
- Added 7-day total and highest-day summary when real data exists.
- Added permission-aware Dashboard tabs:
  - Overview
  - Insights
  - Operations
  - Administration
- Tabs are resolved through `DashboardNavigationResolver` using existing permissions.
- No second RBAC/capability system was introduced.
- Active Guild was not invented because the current base has no existing Active Guild context/read model.
- Activity tab was not fabricated because the current base has no bounded recent-activity query suitable for Dashboard use.
- Quick actions are built server-side from existing permissions.
- Operations only renders when there is an actual stock alert.
- Administration only renders actions backed by existing permissions/routes.
- Existing statistics/reporting remains the destination for detailed analysis.
- Responsive behavior targets mobile widths without horizontal overflow.

## Changed files

- `src/Controller/Application/HomeController.php`
- `src/Application/Dashboard/DashboardNavigationResolver.php`
- `src/Application/Statistics/Query/GetDashboardHandler.php`
- `src/Application/Statistics/Query/StatisticsQueryRepositoryInterface.php`
- `src/Application/Statistics/Query/Model/DashboardResult.php`
- `src/Application/Statistics/Query/Model/RevenueTrend.php`
- `src/Application/Statistics/Query/Model/RevenueTrendPoint.php`
- `src/Infrastructure/Persistence/Doctrine/Query/StatisticsQueryRepository.php`
- `templates/application/home.html.twig`
- `assets/styles/app.css`
- `translations/messages.vi.yaml`
- `translations/messages.en.yaml`

## Validation

PHP syntax checks: PASS for all changed PHP files.

PHPUnit/Twig console validation: not available in this ZIP environment because the base does not include the Composer `vendor/` directory.
