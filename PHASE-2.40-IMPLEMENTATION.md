# CRITICAL SOURCE IMPLEMENTATION PROMPT — PHASE 2.40
## POS Operational Dashboard / Daily Business Summary

You are implementing **PHASE 2.40 — POS Operational Dashboard / Daily Business Summary** in the existing Mobile POS Symfony project.

## 0. NON-NEGOTIABLE RULE

This is a **source implementation task**, not a design-only task.

Before changing anything:

1. Inspect the existing repository and current Phase 2.39 implementation.
2. Reuse the existing architecture, entities, repositories, application services, voters, templates, Stimulus controllers, translation conventions, and test conventions.
3. Do not invent parallel domain models when an existing source-of-truth already exists.
4. Do not move business rules into Twig/Stimulus/JavaScript/controllers.
5. Preserve all transaction, persistence, idempotency, concurrency, security, and historical-data invariants.
6. Do not modify the bank webhook/reconciliation flow, `PaymentReference` matching, or VCB/Casso integration. **Webhook / reconciliation / PaymentReference matching is FROZEN.**
7. Do not change checkout/payment/stock business semantics merely to make dashboard numbers easier to calculate.

The dashboard is **read-only**. Opening, filtering, refreshing, or navigating the dashboard must never mutate Orders, Payments, Stock, Debt, Customer, Product, PaymentReference, StockMovement, or AuditLog records.

---

# 1. OBJECTIVE

Build a **POS Operational Dashboard / Daily Business Summary** for day-to-day store operation.

The dashboard should give an operator/admin a concise, trustworthy view of:

- sales activity
- order activity
- payment activity
- cash/bank-transfer collection where the existing domain supports it
- outstanding customer debt where the existing domain supports it
- stock health / low-stock / out-of-stock indicators
- operational exceptions or items requiring attention, using existing authoritative data

The dashboard must answer operational questions such as:

- How much business was recorded today?
- How many orders were created/completed/cancelled according to the existing Order semantics?
- How were payments collected?
- How much outstanding debt exists according to the existing debt model?
- Which stock conditions require attention?
- What changed versus a previous comparable period, **only where the underlying source data and semantics make that comparison valid**?

Do not manufacture business KPIs that the current domain cannot support reliably.

---

# 2. SOURCE-OF-TRUTH RULE

Every metric must identify and use its canonical source.

Inspect the current implementation first and document the actual source for each metric.

Examples:

- Sales/order totals → existing persisted Order/order-item data and existing order lifecycle semantics.
- Payment totals → existing Payment records and their persisted status/lifecycle.
- Cash/bank-transfer breakdown → existing payment method/status semantics.
- Customer debt → existing debt/balance model and canonical application/domain service/query if present.
- Stock → existing Product stock quantity and StockMovement history, according to Phase 2.38/2.39 semantics.
- Cancellation/refund impact → existing persisted business records and reversal semantics; never infer it from UI state.

Never derive a financial total from UI state, browser-local state, cart state, or transient payment polling state.

If the repository contains multiple candidate sources, resolve the discrepancy by following the existing domain/application architecture. Do not silently choose a convenient source.

If a requested metric is not safely supported, omit it or present a clearly labeled unavailable state rather than inventing a number.

---

# 3. DATE / PERIOD SEMANTICS

Implement a clear reporting period.

Minimum requirement:

- Today / selected business date.

If the existing application already has a reusable date-range/reporting abstraction, reuse it.

Otherwise introduce the smallest appropriate query-layer value object/filter rather than duplicating date logic across controllers and Twig.

Requirements:

- Define timezone semantics explicitly using the application's configured timezone.
- Define the start/end boundary consistently.
- Avoid `23:59:59` hacks where the query architecture can use an exclusive upper bound.
- Use immutable date/time objects where consistent with the existing codebase.
- Handle month/year/day boundaries correctly.
- Do not use browser local time as the authoritative business period unless that is already the application's explicit convention.

If comparison with a previous period is implemented, the comparison period must be explicitly defined and consistently calculated.

---

# 4. CORE DAILY SUMMARY

Implement a dashboard summary with only metrics that can be calculated from authoritative persisted data.

Potential core cards, depending on the actual domain:

### Sales
- Gross sales / order total according to existing semantics.
- Number of relevant orders.

### Payments
- Total successfully recorded payments for the period.
- Cash total if supported.
- Bank-transfer total if supported.
- Other persisted payment methods if supported.

### Orders
- Relevant order counts by lifecycle state.
- Cancellation/refund indicators where persisted business semantics support them.

### Debt
- Current outstanding debt, if supported by the existing debt model.
- New debt created during the selected period, only if this can be calculated unambiguously.
- Settled debt/payment activity during the selected period, only if supported.

### Stock
- Out-of-stock product count.
- Low-stock product count.
- Recent stock adjustments, where supported by StockMovement.

Do not assume all of these metrics exist. Inspect the repository and implement the subset that is actually supported.

---

# 5. METRIC DEFINITIONS MUST BE EXPLICIT

For every displayed KPI, define:

- name
- source entity/table
- source status/lifecycle filters
- date field used
- inclusion/exclusion rules
- treatment of cancellation/refund/reversal
- money aggregation semantics

Put metric calculation in an application/query/reporting layer, not Twig.

Where useful, create dedicated read-only query services such as:

- `PosOperationalDashboardQuery`
- `DailyBusinessSummaryQuery`
- or the repository's existing equivalent.

Do not create unnecessary abstractions if the current architecture already has an appropriate reporting/query service.

---

# 6. MONEY CORRECTNESS

Money must be handled using the existing project's money representation.

Do not:

- use floating-point arithmetic for monetary totals
- sum formatted strings
- calculate totals in JavaScript
- mix currencies silently
- infer payment success from client-side state

Respect existing currency configuration and money value objects/types.

If the current project supports only one currency, use that existing convention rather than introducing multi-currency complexity.

---

# 7. PAYMENT SAFETY

The dashboard is read-only and must not alter payment state.

Especially:

- Never mark a Payment as paid from the dashboard.
- Never create a Payment from the dashboard.
- Never create a PaymentReference from the dashboard.
- Never reconcile a bank notification from the dashboard.
- Never retry or trigger payment settlement as a side effect of loading a report.
- Never treat `PAYMENT_WAITING` UI state as persisted payment success.

Bank transfer figures must use the same persisted payment/reconciliation semantics already established by the application.

**Do not touch webhook/reconciliation code.**

---

# 8. ORDER SEMANTICS

Inspect the actual Order lifecycle before implementing counts or revenue metrics.

Do not assume that `COMPLETED`, `PAID`, `CANCELLED`, `REFUNDED`, etc. have meanings different from the current code.

Preserve the project's existing completion semantics.

If an order is cancelled/refunded through an existing business flow, the dashboard must reflect the persisted business result according to the domain rules rather than trying to reverse numbers heuristically in the UI.

Do not modify historical orders to support reporting.

---

# 9. DEBT / CUSTOMER BALANCE

Use the canonical debt implementation created/hardened in earlier phases.

Do not calculate customer debt from arbitrary order totals if the project already has a persisted debt/payment model.

Do not silently count bank-transfer payment references as debt settlement unless the existing Payment lifecycle says the payment was actually applied.

Dashboard access must remain read-only.

---

# 10. STOCK OPERATIONAL SUMMARY

Reuse Phase 2.38/2.39 stock semantics.

Potential indicators:

- out-of-stock count
- low-stock count
- stock adjustments today
- recent stock movement volume
- products requiring attention

Do not mutate stock from the dashboard.

Do not recompute authoritative stock by replaying movements in PHP if the current `Product` stock quantity is the canonical operational value and the reporting requirement can query it directly.

If historical stock movement analytics are needed, use the existing StockMovement semantics.

Avoid N+1 product/movement queries.

---

# 11. COMPARISON / TREND DATA

A comparison such as “vs yesterday” or “vs previous period” may be implemented only when:

- the current domain has enough data to calculate both periods consistently
- the date boundaries are correct
- cancellation/refund/payment semantics are identical across periods

Do not present misleading percentage changes when the denominator is zero.

For zero/undefined baselines, use an explicit neutral/empty state such as “—” rather than inventing a percentage.

Do not call a metric “growth” unless the underlying comparison genuinely supports that interpretation.

---

# 12. OPERATIONAL EXCEPTIONS

If useful and supported by existing data, provide a small “Needs attention” section, for example:

- out-of-stock products
- low-stock products
- unusually high number of cancellations according to a clearly defined threshold
- unpaid/outstanding customer debt
- payment records requiring existing operational attention

Thresholds must not be invented as business rules without evidence from the existing project.

If no configured threshold exists, prefer explicit counts/lists over arbitrary severity classifications.

Do not introduce red/yellow/green business judgments without a documented domain rule.

---

# 13. QUERY / PERFORMANCE HARDENING

This dashboard is read-heavy and must not create a query explosion.

Inspect generated SQL and query plans where practical.

Requirements:

- avoid N+1 queries
- use aggregate SQL for aggregate metrics
- select only required fields
- avoid loading entire Order/Payment/StockMovement collections into PHP merely to sum values
- avoid repeated identical queries for separate widgets
- reuse one report result where appropriate
- add indexes only when justified by actual query patterns and existing schema conventions
- preserve existing indexes and constraints

If multiple metrics can be obtained safely from one aggregate query, consider doing so without making the query unreadable or semantically dangerous.

Do not prematurely introduce caching if correctness/period freshness would become ambiguous.

If caching already exists, follow its established conventions and ensure dashboard data does not become misleadingly stale.

---

# 14. SECURITY / AUTHORIZATION

Dashboard access must use the existing security architecture.

Inspect the existing Voters/permissions and apply the appropriate permission for operational reporting.

Do not:

- bypass voters because the dashboard is read-only
- trust a role string directly in Twig
- expose admin-only financial/debt information to users without authorization
- create a second authorization system

If the existing permission matrix has a dedicated reporting/admin permission, reuse it.

If no dedicated permission exists, follow the closest existing documented permission boundary without silently broadening access.

---

# 15. ROUTING / CONTROLLER

Use a thin Symfony controller.

Controller responsibilities should be limited to:

- authorization
- request/date/filter parsing
- invoking the read-only application/query service
- rendering the response

Do not calculate business totals inside the controller.

Do not perform persistence from the dashboard controller.

Use existing route naming and controller conventions.

---

# 16. TWIG / STIMULUS UI

Build a mobile-first operational dashboard.

Minimum viewport: **360px**.

Requirements:

- no forced horizontal scrolling for ordinary use
- touch targets >= 48px
- body text around 16px
- clear headings
- high contrast
- concise cards
- simple tables/lists
- accessible labels
- visible loading/empty/error states where applicable
- keyboard-accessible controls
- no flashy gradients/neon styling

Use the project's existing design system/components.

Do not duplicate backend metric logic in Twig or JavaScript.

JavaScript may handle presentation concerns such as date-picker interaction, collapsing sections, or refreshing a read-only report, but the server remains authoritative.

---

# 17. DAILY SUMMARY UX

Recommended information hierarchy:

1. Reporting date / period selector.
2. Primary sales/order summary.
3. Payment collection summary.
4. Debt summary, if authorized/supported.
5. Stock health summary.
6. Operational attention list.
7. Optional detail tables/drill-down links.

Do not overload the first screen with every historical record.

Use links to existing Order History, Payment/Debt Management, and Stock Management screens for drill-down rather than rebuilding their business flows.

Opening a drill-down must remain read-only unless the destination is an existing mutation flow explicitly designed for that purpose.

---

# 18. EMPTY / ERROR STATES

Handle explicitly:

- no orders for selected period
- no payments for selected period
- no stock alerts
- no debt activity
- invalid date range
- unauthorized access
- temporary report/query failure

Never display zero when the query actually failed.

A failed metric/report must not silently masquerade as an empty business result.

Use the existing application's error/flash/translation conventions.

---

# 19. TESTING REQUIREMENTS

Implement tests consistent with the current repository.

## Query/Application tests

Test:

- correct date boundaries
- timezone behavior
- order inclusion/exclusion
- payment status inclusion/exclusion
- cash/bank-transfer aggregation
- cancellation/refund semantics according to actual domain
- debt totals according to actual debt model
- stock alert counts
- empty periods
- zero-denominator comparisons
- multiple customers/products/orders
- no double counting from joins

## Integration tests

Verify:

- dashboard route returns expected data
- unauthorized users are denied
- authorized users can access it
- filters/date selection produce correct results
- no persistence mutation occurs

## HTTP/Acceptance tests

Verify:

- page renders on a realistic mobile viewport where the existing test tooling supports it
- KPI labels/data are visible
- empty state works
- invalid filters are handled
- permission enforcement works

## Regression tests

Run the existing relevant test suite and ensure this phase does not break:

- checkout
- payment
- bank transfer webhook/reconciliation
- order history/detail
- debt/payment management
- stock management

Do not weaken existing tests to make the dashboard pass.

---

# 20. DATA CONSISTENCY / CONCURRENCY

The dashboard is read-only, so it must not introduce locking or transactions that change business behavior merely to generate a report.

Normal database isolation should be respected.

Do not assume that multiple separate aggregate queries represent one perfectly atomic snapshot unless the current transaction/isolation architecture explicitly guarantees it.

If the dashboard displays several independently queried metrics, the implementation should tolerate tiny timing differences between them rather than introducing unsafe application-level locking.

Never mutate or “repair” inconsistent business data while generating a report.

---

# 21. AUDIT / OBSERVABILITY

Do not create AuditLog entries merely because a user viewed the dashboard unless the existing security/audit policy explicitly requires view auditing.

Do not log sensitive customer/payment/bank notification contents.

Application logs should identify report failures with safe contextual information only.

Follow the existing observability/error handling conventions.

---

# 22. IMPLEMENTATION PROCESS

Follow this order:

1. Inspect current Phase 2.39 source.
2. Map existing authoritative entities/services/statuses.
3. Write down metric definitions before coding.
4. Implement read-only application/query layer.
5. Implement authorization.
6. Implement thin controller/routes.
7. Implement Twig UI using existing components.
8. Add only minimal Stimulus behavior if required.
9. Add tests.
10. Run targeted tests.
11. Run the relevant full regression suite.
12. Inspect SQL/query count for obvious N+1 or duplicate aggregate queries.
13. Review for accidental persistence mutations.
14. Review webhook/reconciliation files and confirm they were not modified.

---

# 23. ACCEPTANCE CRITERIA

Phase 2.40 is complete only when:

- [ ] Dashboard exists and is reachable through the correct authorized route.
- [ ] Daily/selected-period summary uses authoritative persisted data.
- [ ] Metric definitions are explicit and consistent with the current domain.
- [ ] Sales/order totals do not double-count.
- [ ] Payment totals use persisted payment semantics.
- [ ] Debt figures use the canonical debt model when available.
- [ ] Stock indicators use Phase 2.38/2.39 semantics.
- [ ] Date/time boundaries are correct.
- [ ] No business mutation occurs from dashboard/report access.
- [ ] No payment or bank reconciliation side effect exists.
- [ ] No PaymentReference is created/modified by reporting.
- [ ] Authorization is enforced through the existing security architecture.
- [ ] UI works from 360px upward.
- [ ] Empty/error states are explicit.
- [ ] No obvious N+1 query pattern exists.
- [ ] Query/application/integration/HTTP tests are implemented as appropriate.
- [ ] Existing regression tests remain green.
- [ ] Webhook/reconciliation/PaymentReference matching remains untouched.

---

# 24. FINAL REVIEW CHECKLIST

Before declaring completion, answer these questions from the actual source:

1. What exact entity/query is the source for each displayed KPI?
2. Which Order statuses are included and why?
3. Which Payment statuses are included and why?
4. How are cancellations/refunds represented in the totals?
5. How is customer debt sourced?
6. How are stock alerts sourced?
7. What timezone defines “today”?
8. Are aggregate joins protected against double counting?
9. Are any metrics computed in Twig/JavaScript? If yes, move them to the backend.
10. Does dashboard access mutate any business record? It must not.
11. Were webhook/reconciliation/PaymentReference files modified? They must not be.
12. Which tests prove the above behavior?

If the existing source does not support a requested metric safely, **do not invent a business rule**. State the gap in the implementation notes and implement only the supported reporting surface.

## FINAL DELIVERABLE

Deliver the implemented Phase 2.40 source with tests, preserving all existing architecture and business invariants.
