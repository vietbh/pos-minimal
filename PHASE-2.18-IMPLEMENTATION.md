# PHASE 2.18 — Dashboard / Home UX

Implemented from `mobile-pos-current.zip` as the source baseline.

## Implemented
- Application Home dashboard at `/`
- Permission-aware quick actions
- Profile and Settings entry points
- Primary `Mở POS` action gated by `POS_ACCESS`
- KPI cards using the existing `GetDashboardHandler`
- Statistics aggregate data loaded only when `STATISTICS_VIEW` is granted
- Low/out-of-stock operational alert
- Top products for today's existing statistics query
- Empty state
- Mobile-first responsive dashboard
- Accessibility-oriented semantic sections and named navigation
- Preference-aware presentation remains provided by Phase 2.17

## Intentionally not invented
The current query contract does not expose recent-order/activity data. This phase therefore does not fabricate a "recent activity" dataset or introduce a duplicate query architecture.

## Validation
- PHP syntax: HomeController and Home-related test: OK
- Twig lint for home template: OK
- Router: `app_home` -> `/`
- PHPUnit: blocked by environment missing `dom`, `mbstring`, `xmlwriter`
