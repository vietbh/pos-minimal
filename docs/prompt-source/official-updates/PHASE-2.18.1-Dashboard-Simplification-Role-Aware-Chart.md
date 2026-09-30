# PHASE 2.18.1 — Dashboard Simplification + Role-Aware Navigation + Simple Overview Chart

## Purpose

Implement the Application Home as a minimal, mobile-first, permission-aware dashboard without creating a second authorization or reporting architecture.

Dashboard answers:
1. Hôm nay bán được bao nhiêu?
2. Có việc gì cần chú ý?
3. Tình hình 7 ngày gần đây thế nào?
4. Tôi cần làm gì tiếp theo?

Dashboard is not BI, full analytics, ERP, or POS checkout.

## Source-first

Inspect and reuse the current:
- Home route/controller
- `GetDashboardHandler` / existing dashboard read model
- statistics/report queries
- Twig/layout/navigation
- Permission/Voter system
- User Preferences
- Active Guild context
- existing tests

Do not create `DashboardServiceV2`, `DashboardControllerV2`, `DashboardRepositoryV2`, `DashboardQueryV2` when an existing abstraction is suitable.

## Home information architecture

Overview may contain:
- Today
- one simple overview chart
- Quick Actions
- Attention only when non-empty
- Active Guild only when `activeGuild != null`

No fake data, random values, placeholder Guild, or fallback Guild.

## Today

Use real backend/read-model data for:
- today's revenue
- today's order count
- optional outstanding metrics only when they already have clear business semantics

Twig and Stimulus must not calculate these values.

## Overview chart

One chart only on Overview, preferably a 7-day revenue bar chart.

Rules:
- one metric
- 7 days by default when existing data supports it
- roughly 7–14 points maximum on mobile
- explicit title, time range, unit, and labels
- no multi-series chart
- no pie chart for trend
- no complex tooltip as the only way to understand a value
- no fake points
- chart is backed by an application read model/query
- textual numeric summary is rendered below the chart

Backend returns the series and summary; Twig only renders them.

## Role/permission-aware navigation

Suggested tabs:
- Overview
- Activity
- Insights
- Operations
- Administration

Actual availability must come from the existing Permission/Voter architecture. Do not scatter `ROLE_ADMIN` / `ROLE_MANAGER` branches through Twig.

Preferred flow:

```text
User
 -> existing permissions/voters
 -> DashboardNavigationResolver (only if needed)
 -> allowed tabs/actions
 -> Dashboard ViewModel
 -> Twig
```

If a resolver is needed, it resolves available tabs and default tab only; it must not become a second RBAC engine.

Default-tab priority:

```text
1. Permission
2. Business context
3. Saved user tab preference
4. Role/profile default
5. Overview fallback
```

## Active Guild rule

```text
activeGuild != null -> Guild UI/data may appear
activeGuild == null -> hide the entire Guild section and Guild-dependent actions
```

Never:
- show a fake/default Guild
- show a placeholder Guild
- auto-select another Guild
- mutate Guild state while loading Home

## Twig contract — mandatory

The Home Twig template must receive every required presentation variable from its controller/view model.

For the current role-aware Home implementation, the controller must provide at least the equivalent of:

```php
'role_label' => $roleLabel,
'is_admin_role' => $user->hasRole(UserRole::ADMIN) || $user->hasRole(UserRole::ROOT),
```

The role label must be derived from the existing `UserRole` model, not invented in Twig. Do not hide this contract with `|default(...)`; a missing required variable is an implementation error.

Current expected mapping:

```text
ROOT  -> Root
ADMIN -> Quản trị viên
other -> Nhân viên
```

Use the project's actual role enum/role abstraction if its naming differs.

## Quick Actions

Actions are permission-aware and backend authorization remains authoritative.

Examples:
- New Sale / POS
- Products
- Orders
- Customers
- Stock

Do not expose unauthorized routes merely by hiding visual elements.

## Attention

Render only when actual attention items exist, such as:
- low stock
- pending payment
- failed import
- reconciliation issue

Empty Attention must not render.

## Activity / Insights / Operations / Administration

Activity: bounded recent relevant activity; never load all orders/payments/audit logs.

Insights: reuse existing Statistics/Reports implementation; do not duplicate reporting engines.

Operations: actionable operational items only.

Administration: only when permission allows; backend authorization remains mandatory.

## Performance

Avoid N+1 and unbounded historical queries. Aggregate in backend/database where existing architecture allows. Do not load thousands of orders and sum in Twig/PHP merely to draw a 7-day chart.

Dashboard is read-side only. It must not create Order/Payment, mutate Stock, activate Guild, or use write locks for statistics.

## Responsive/accessibility

Targets: 320, 360, 375, 390, 430, 768, 1024+.

Use:

> Resize → Wrap → Reflow → Never Hide Business Information.

Business names, amounts, statuses, and important text must not be ellipsized away.

Keyboard navigation, visible focus, semantic headings/labels, sufficient contrast, touch targets around 48px, and an accessible textual chart summary are required.

## Testing

Test:
- real Today metrics
- date boundaries/timezone semantics
- chart range/order/values/empty state
- chart summary
- attention present/absent
- Active Guild present/absent
- permission-derived tabs/actions
- saved tab preference valid/invalid
- fallback to Overview
- HTTP rendering/authentication/authorization
- absence of debug output
- absence of `dump()` in Dashboard

## Acceptance

- Home is visibly simpler.
- Today is primary.
- At most one overview chart.
- Chart uses real data.
- Numeric chart summary is real data.
- No fake Guild/data.
- No role-based authorization logic in Twig.
- No duplicate architecture.
- No N+1.
- Existing Authentication, POS, Orders, Payments, Bank Transfer, Stock, Products, Customers, Guild, Import, Security, Preferences and Error Handling remain intact.
