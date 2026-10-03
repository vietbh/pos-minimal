Statistics tabs fetch/filter patch

Files are stored relative to project root (flat ZIP).

Changes:
- Adds permission-checked GET /app/statistics/data/{tab} endpoint using existing StatisticsQueryRepositoryInterface queries.
- Fetches tab-specific data using the same date preset, date range, sales point and Top N filters.
- Preserves the selected tab in the GET filter form and restores it after applying filters.
- Cancels stale tab requests and only renders responses for the currently selected tab.

After applying: run composer test/lint as used by this repository, then npm run build.
