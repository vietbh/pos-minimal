# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.39 — STOCK REPORTING / INVENTORY ANALYTICS & OPERATIONAL DASHBOARD

## 0. OBJECTIVE
Implement a read-only, mobile-first Stock Reporting / Inventory Analytics workspace on top of the repository's existing Product stock, StockMovement, Order and OrderItem infrastructure. The backend/domain/application/query layer remains the source of truth. Provide operational visibility without creating a second inventory engine.

Expected capabilities, only where supported by the current repository:
- inventory snapshot / current stock KPIs
- low-stock and out-of-stock visibility
- stock movement analytics
- adjustment activity
- sales / sale-reversal movement summaries
- date-range filtering
- product-level drill-down
- paginated movement reporting
- permission enforcement
- query/performance hardening
- deterministic tests

Webhook, PaymentReference and bank reconciliation are FROZEN and must not be changed.

## 1. ABSOLUTE SCOPE BOUNDARY
Do NOT introduce a second stock quantity, inventory ledger, StockMovement model, adjustment mechanism, browser-local inventory truth, or report-only mutation command.
Do NOT change checkout stock deduction, sale reversal, Order history, product pricing, Payment, PaymentReference or Customer business semantics.
Do NOT put business calculations in Twig, Stimulus, JavaScript or controllers.
Do NOT use AuditLog as an inventory ledger.
Reports must never mutate Product, StockMovement, Order, Payment or PaymentReference merely by being opened.
If a metric is not supported by authoritative persisted data, omit it or explicitly document the limitation; never invent a fake approximation.

## 2. SOURCE-FIRST INSPECTION — REQUIRED
Before coding inspect:
- Product, stockQuantity, lowStockThreshold, active/status semantics, repositories and query infrastructure
- StockMovement, StockMovementType, repository, quantityBefore/change/after, reason, actor, Order relation, all creation paths
- Order / OrderItem lifecycle and cancellation/refund/reversal semantics
- Phase 2.38 stock controller/templates/adjustment flow
- existing Product, Customer, Order History and Debt/Payment admin UI patterns
- Permission enum, Voters, CSRF and error/translation conventions
- Doctrine mappings, indexes, migrations and existing query builders
- stock, checkout, reversal, security and HTTP tests

Reuse existing abstractions instead of inventing parallel ones.

## 3. AUTHORITATIVE DATA RULES
Current inventory comes from persisted Product.stockQuantity.
Historical inventory activity comes from StockMovement.
Sales-oriented reporting may use Order/OrderItem only when their lifecycle semantics are authoritative; do not count arbitrary Orders as sales.
Do not reconstruct current stock by replaying all movements when Product.stockQuantity is authoritative.
Do not infer sold units from stock deltas.
Do not silently count cancelled/reversed transactions as completed sales.

## 4. DASHBOARD
Extend the existing stock/admin workspace rather than creating competing routes. Inspect the current routing first.
Possible KPI cards, only when precisely supported:
- total products / active products
- total units in stock
- out-of-stock products
- low-stock products
- adjustment count
- positive adjustment units
- negative adjustment units
- net adjustment units
- sale movement count / units sold
- sale-reversal count / units restored

Every displayed metric MUST have an explicit definition in the query/application layer and tests. Do not add metrics merely to fill a dashboard.

## 5. KPI DEFINITION CONTRACT
For every KPI define:
- source entity/table
- source fields
- lifecycle/status filters
- timestamp/date field
- timezone semantics
- inclusion/exclusion rules
- aggregation formula

Example only if repository semantics confirm it: units sold may be the normalized absolute quantity of SALE StockMovements in a period. Preserve actual stored sign semantics and normalize presentation in the query/application layer, not Twig.
For adjustments distinguish positive, negative and net units when the stored movement data supports them.
Never label a metric “received”, “purchased” or “inventory value” unless the domain actually has authoritative receiving/cost semantics.

## 6. DATE-RANGE FILTERS
Use the project's existing date/time conventions. Practical presets may include Today, Yesterday, Last 7 Days, Last 30 Days and Custom Range, but implement only what fits the existing UI.
Normalize date boundaries in the backend. Prefer deterministic half-open intervals `[start, end)` where compatible with the project.
Use the application's canonical timezone; never silently use browser-local time as a business rule.
Test boundary cases and invalid ranges.

## 7. STOCK HEALTH / EXCEPTIONS
Show low-stock and out-of-stock products using the same authoritative semantics established in Phase 2.38.
Do not hard-code a threshold in Twig/Stimulus.
Useful read-only links:
Dashboard → product → existing stock detail
Dashboard → low/out-of-stock list → filtered stock list
Dashboard → adjustment activity → movement history filtered to ADJUSTMENT
Do not introduce arbitrary anomaly-detection/statistical thresholds in this phase.

## 8. STOCK MOVEMENT REPORT
Provide a read-only movement report using persisted StockMovement data.
Filters may include date range, movement type, product/SKU search and actor where supported.
Use server-side filtering and pagination.
Display, where available:
- timestamp
- product / SKU
- movement type
- quantity before
- quantity change
- quantity after
- reason
- actor
- related Order when applicable
Do not edit/delete historical movements.

## 9. PRODUCT DRILL-DOWN
Reuse the existing product stock detail. Show current stock, threshold/status where supported, recent movements and optional authoritative period summaries. A product with no movements is not evidence that current stock is zero.

## 10. QUERY / READ ARCHITECTURE
Controllers orchestrate input, authorization and rendering only.
Use dedicated read/query services or repository methods consistent with existing architecture. Do not load the entire catalog/movement ledger into PHP and aggregate it for operational reports.
Do not implement large reports with `findAll()` + loops.
Twig renders already-computed values. JavaScript is presentation-only.

## 11. AGGREGATION CORRECTNESS
Avoid accidental double-counting from multi-table joins, especially combinations such as Order → OrderItems → Payments → StockMovements.
Use separate aggregate queries when necessary.
Never `SUM()` a rowset whose joins multiply the underlying business rows unless cardinality is proven safe.
Add regression tests that would fail under a naive duplicated join.

## 12. CURRENT STOCK TOTALS
If showing total units, explicitly define the Product population. Do not silently exclude inactive products unless the KPI says so. If both active-only and all-product totals are useful, define them separately rather than mixing semantics.

## 13. INVENTORY VALUE
Do not calculate “inventory value” from retail sale price unless the repository explicitly defines that as its valuation basis. If authoritative cost does not exist, omit the metric and document the limitation.

## 14. ADJUSTMENT ANALYTICS
Use only StockMovementType::ADJUSTMENT (or the repository's actual enum equivalent). Possible metrics: adjustment count, positive units, negative units, net units, frequently adjusted products. Do not count AuditLog records or Product edits as adjustments. Opening a report must not create a movement.

## 15. SALES / REVERSAL ANALYTICS
When supported by actual StockMovement semantics, report sale movements, units sold, reversal movements and units restored. Preserve domain signs and historical records. Do not independently derive stock changes from Order status when authoritative StockMovement records exist.

## 16. PRODUCT RANKINGS
If useful, factual operational lists may include lowest current stock, most units sold in a period, or most adjustment activity. Do not label products “best” or “worst”. Use deterministic tie-breaking such as metric DESC, product.id ASC and stable pagination.

## 17. PERFORMANCE
Inspect actual query predicates before adding indexes. Consider existing indexes for product/type/timestamp/status fields, but add only justified non-redundant indexes.
Avoid N+1 queries. If movement rows display Product name/SKU, fetch efficiently. Inspect generated SQL/query count where needed.

## 18. PAGINATION
All potentially unbounded movement/product lists must be paginated. Preserve page, search, filters, date range and supported sort state across pagination. Reuse the project's existing paginator.

## 19. SECURITY / PERMISSIONS
Use the existing Permission/Voter architecture. Reporting permission must not imply stock-adjustment permission.
Protect all HTML and JSON/report endpoints. Do not expose report data through an unprotected Stimulus endpoint.
GET/report views are read-only. Any mutation remains behind existing CSRF and application authorization.

## 20. MOBILE-FIRST UI
Target 360px+ and preserve the established elderly-friendly POS/admin style:
- touch targets >=48px
- body text ~16px
- inputs ~17–18px
- clear headings and high contrast
- simple status colors, no flashy gradients/neon
- KPI cards stack on narrow screens
- avoid unreadable dense desktop tables
Charts are optional; tables/cards are preferable to adding a large dependency solely for visualization.

## 21. ACCESSIBILITY
Charts must never be the sole representation of a metric. Provide equivalent textual/table data. Use labels, keyboard-accessible controls, focus states, sufficient contrast and non-color-only status indicators.

## 22. CHARTS
If an existing chart library is already present, it may be used. Backend computes aggregates; browser receives only compact data. Never download the full movement history and aggregate it in JavaScript. If no suitable chart library exists, do not add unnecessary complexity; KPI cards + tables are acceptable.

## 23. EMPTY / ERROR / PARTIAL STATES
Handle:
- no products
- no movements in period
- no low-stock products
- invalid range
- unauthorized access
- query failure
A failed query must not be rendered as numeric zero or stale data. Use existing Symfony error/flash/translation conventions.

## 24. TEST MATRIX — REQUIRED
Add/update tests following repository conventions.

### Query correctness
Test current stock totals, active/inactive scope where applicable, low/out-of-stock counts, movement counts, quantity aggregation, positive/negative/net adjustments, sale units and reversal units.

### Date boundaries
Test same-day, start boundary, end boundary, empty range and invalid range.

### Aggregation safety
Test a scenario where a naive join would double-count records.

### Drill-down
Test authoritative current stock, filtered movements and no-movement state.

### Search/filter/pagination
Test product search, SKU search, movement type, date filters, pagination and deterministic ordering.

### Security
Test permitted admin access, unauthorized denial, and that report permission does not imply adjustment permission.

### HTTP acceptance
Test dashboard, report and detail routes for status, key data, filters, empty state and authorization.

### Regression
Run existing checkout stock, stock adjustment, stock concurrency, order cancellation/refund/reversal and security tests. Reporting must not change their behavior.

## 25. NO SIDE EFFECTS
Opening dashboard/report/detail/movement history must not create StockMovement, update Product.stockQuantity, update Order, update Payment, create PaymentReference, or create AuditLog unless access auditing is already an established project convention.

## 26. IMPLEMENTATION ORDER
1. Inspect current architecture and Phase 2.38 source.
2. Define exact metrics and source-of-truth rules.
3. Implement/extend read/query layer.
4. Add query correctness tests, especially date boundaries and join multiplication.
5. Integrate thin controller/application layer.
6. Build dashboard/report UI using existing admin shell.
7. Add drill-down/filter/pagination.
8. Harden permissions and endpoint exposure.
9. Review query count/SQL/indexes.
10. Run full relevant regression suite.

## 27. DEFINITION OF DONE
- [ ] dashboard/report uses existing admin architecture
- [ ] Product.stockQuantity remains authoritative for current stock
- [ ] StockMovement remains authoritative for movement history
- [ ] every KPI has a tested definition
- [ ] date semantics are deterministic
- [ ] filters are server-side
- [ ] unbounded lists are paginated
- [ ] aggregates do not double-count
- [ ] no N+1 reporting queries remain
- [ ] report pages have no business mutation side effects
- [ ] permissions/Voters are enforced
- [ ] JSON/report endpoints are protected
- [ ] UI works from 360px+
- [ ] accessibility does not depend on charts/colors alone
- [ ] empty/error states are explicit
- [ ] existing checkout/adjustment/reversal/concurrency behavior is unchanged
- [ ] webhook/reconciliation/PaymentReference remains untouched
- [ ] relevant tests pass

## 28. FINAL IMPLEMENTATION REPORT
Report:
1. Files changed.
2. Query/read services added or modified.
3. Every implemented KPI and exact definition.
4. Routes added/modified.
5. Permissions/Voters involved.
6. Database indexes/migrations added and why.
7. Tests added/modified.
8. Exact test commands and actual results.
9. Repository limitations that prevented any requested metric.
10. Confirmation that checkout, stock adjustment concurrency, sale reversal and frozen webhook/reconciliation/payment-reference behavior were not altered.

Never claim a test passed unless it actually ran successfully. Disclose skipped tests, missing dependencies, environment failures and known limitations.
