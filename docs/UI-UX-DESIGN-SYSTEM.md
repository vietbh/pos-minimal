# UI/UX Design System

This is the implementation baseline for the current Mobile POS project.

## Visual direction
- Simple, calm, high contrast, mobile-first.
- Designed for easy reading and touch interaction.
- Do not rely on hover.
- Do not use color alone to communicate state.

## Semantic colors
- surface: `#f8fafc`
- surface-raised: `#ffffff`
- text: `#172033`
- text-muted: `#475569`
- border: `#cbd5e1`
- primary: `#1d4ed8`
- primary-strong: `#1e40af`
- success: `#15803d`
- warning: `#a16207`
- danger: `#b91c1c`
- disabled: `#94a3b8`

These are presentation tokens only; they are not business rules.

## Typography
- Body minimum: 16px
- Input: 17–18px
- Button: 16–18px
- Page title: 24px
- Section title: 20px
- Important monetary values: 22–28px
- Avoid thin font weights.

## Touch
- Minimum touch target: 48px
- Primary actions: 52px or larger where practical.
- Avoid ambiguous icon-only actions.

## Accessibility
Status should use text/icon/label in addition to color where appropriate.


## Phase 2.17 — Settings, accessibility and profile navigation
- Persisted user preferences: font size, appearance, UI density, high contrast and reduced motion.
- Authenticated application navigation exposes **Hồ sơ** and **Cài đặt** without changing POS business behavior.
- Profile provides a direct **Cài đặt** action.
- Application Home provides direct **Hồ sơ**, **Cài đặt** and **Mở POS** actions.
- Preference presentation is server-rendered from the authenticated `User`; browser storage is not the source of truth.
- Touch targets remain at least 48px; primary actions target 52px or larger.
- Reduced-motion preference also respects `prefers-reduced-motion`.
- Dark/system presentation uses shared UX tokens so surfaces, text and borders remain readable across authenticated pages.

## Phase 2.21 — Orders / Sales History UX Hardening
- Orders is a read-oriented application workspace; POS remains the transaction workspace.
- Order history supports server-side search by order number/customer name/customer phone, status filtering and inclusive date-range filtering.
- Search input is bounded to 100 characters and date queries are bounded to a 366-day range for predictable query cost.
- Invalid filter values are client-input errors (HTTP 400), not not-found resources.
- Pagination preserves active filters; requests beyond the final page resolve to the final available page.
- Order detail preserves the originating order-list filter/pagination context when navigating back.
- Monetary values use the shared `money_vnd` presentation formatter; Twig does not calculate financial values.
- Order lifecycle mutations remain backend-authoritative and permission-gated by `ORDER_CANCEL` / `ORDER_REFUND`.
- Empty, filtered-empty, focus-visible and mobile touch states are explicit; minimum touch target remains 48px.

## Phase 2.22 — Customers / Customer Detail + Debt UX Hardening
- Customer detail is a read-oriented application workspace for customer identity, contact information, debt summary, debt history and recent orders.
- Customer detail keeps POS as a separate transaction workspace; it does not own cart or checkout state.
- Debt totals are calculated by the backend query/read model and rendered through the shared `money_vnd` formatter; Twig does not calculate financial values.
- Debt history and recent orders are bounded to the most recent 10 records on the customer detail view; explicit links take the user to the full Debt / Orders workspaces when additional records exist.
- Debt and order sections are permission-aware through `DEBT_VIEW` and `ORDER_VIEW`; absence of permission produces an explicit state rather than exposing data.
- Customer detail preserves clear empty states for customers with no debts or no orders and keeps primary interaction targets at least 48px.
- Customer detail does not expose audit internals or infer business state in presentation code.
