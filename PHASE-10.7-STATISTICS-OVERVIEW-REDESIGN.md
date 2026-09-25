# Statistics Overview Redesign

Implemented on top of the supplied `mobile-pos-current(9).zip` base.

## UI changes

- Reworked the statistics KPI cards into a clearer hierarchy:
  - Net sales as the primary KPI.
  - Payments collected and AOV as supporting KPIs.
  - Gross sales, cancelled and refunded as secondary status cards.
- Added a dedicated overview chart section.
- Added an ApexCharts financial overview bar chart.
- Added an ApexCharts payment-mix donut chart.
- Kept exact-value HTML tables for accessibility and users who prefer precise data.
- Kept the mobile-first 360px/390px layout and existing design tokens.
- Preserved the existing server-side date/preset/limit authority.

## Architecture

ApexCharts is loaded through Symfony ImportMap from the pinned CDN module:

`apexcharts@7.5.1`

The Stimulus controller only renders server-provided values. It does not calculate sales, debt, stock, payment totals, dates or business rules.

No files under `public/assets/controllers/` were modified.

## Validation

- JavaScript syntax check passed with `node --check`.
- PHP syntax check passed for `importmap.php`.
- Existing frontend contract test was extended to assert the chart integration.
- Full Symfony/Twig/PHPUnit execution still requires the project's Composer `vendor/` directory.
