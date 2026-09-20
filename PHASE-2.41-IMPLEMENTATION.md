# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.41 — POS REPORTING & EXPORT (CSV / EXCEL / PDF) HARDENING

## 0. OBJECTIVE

Implement a secure, deterministic, read-only POS Reporting and Export layer on top of the existing reporting/dashboard architecture.

This phase extends Phase 2.39 Stock Reporting and Phase 2.40 POS Operational Dashboard. It must provide exportable reports without creating a second reporting engine or changing any business semantics.

Required export targets, only where the repository already has suitable support/dependencies:

- CSV
- Excel-compatible XLSX
- PDF

The implementation must be source-first. Inspect the actual repository before coding and reuse existing entities, repositories, query/application services, permission/Voter rules, money/date formatting, translation conventions, and reporting abstractions.

The backend/domain/application/query layer remains the source of truth.

Exports are READ-ONLY. Generating, previewing, downloading, or retrying an export must never mutate:

- Order
- OrderItem
- Payment
- PaymentReference
- Customer
- Product
- StockMovement
- debt/settlement records
- AuditLog
- any other business record

Webhook / bank reconciliation / PaymentReference matching remains FROZEN.

---

# 1. ABSOLUTE SCOPE BOUNDARY

Do NOT:

- introduce a second sales/order/payment/stock/debt data model;
- duplicate Phase 2.39/2.40 KPI calculations in Twig or JavaScript;
- calculate business totals from browser state;
- recalculate historical Order totals from current Product prices;
- infer payment success from transient polling/UI state;
- infer stock from frontend state;
- use AuditLog as a financial or inventory ledger;
- modify checkout/payment/refund/cancel/stock-adjustment business semantics;
- modify the bank webhook, webhook authentication, notification parsing, reconciliation, PaymentReference lifecycle, or VCB/Casso integration;
- create fake report data to fill an export;
- silently change the meaning of an existing KPI for export convenience.

If an export format is not currently supported by the project dependencies or infrastructure, inspect the repository and implement it only through an appropriate existing dependency/architecture. Do not silently substitute a different format.

If PDF/Excel generation requires a new library and the repository does not already contain an approved dependency mechanism, document the dependency requirement instead of inventing a custom fragile implementation.

---

# 2. REQUIRED SOURCE-FIRST INSPECTION

Before changing code, inspect:

### Reporting/dashboard

- Phase 2.39 Stock Reporting / Inventory Analytics implementation
- Phase 2.40 POS Operational Dashboard / Daily Business Summary implementation
- existing report/query DTOs/value objects
- date-range/filter abstractions
- KPI definitions
- query builders/repositories
- pagination and sorting conventions

### Business sources

- Order / OrderItem
- Order lifecycle/status semantics
- Payment / payment method/status
- PaymentReference
- Customer/debt model
- Product / stockQuantity / lowStockThreshold
- StockMovement and movement types
- cancellation/refund/reversal semantics

### Infrastructure

- composer.json / composer.lock
- existing CSV/export utilities
- existing spreadsheet/PDF libraries
- Symfony Serializer/Filesystem/HttpFoundation usage
- Twig and translation conventions
- response/download conventions
- temporary-file/storage conventions
- cache/queue infrastructure if already present

### Security

- Permission enum
- Voters
- route/controller authorization
- CSRF conventions where applicable
- admin/POS role rules
- existing report permissions

### Tests

- Phase 2.39 tests
- Phase 2.40 tests
- query tests
- HTTP/functional tests
- security/Voter tests
- any existing export tests

Reuse existing abstractions instead of introducing parallel ones.

---

# 3. REPORT SOURCE CONTRACT

Exports must consume the same canonical report/query/application layer used by the UI wherever possible.

Preferred architecture:

```text
UI / Export Request
        ↓
Controller / Entry Point
        ↓
Report Query / Application Service
        ↓
Canonical persisted domain data
        ↓
Report DTO / Row DTO
        ↓
CSV / XLSX / PDF Renderer
        ↓
Download Response
```

Do NOT implement:

```text
Controller
  ↓
Doctrine entity loops
  ↓
business calculations
  ↓
Twig/PHP formatting
```

Business calculations belong in the query/application/report layer.

The renderer should format already-defined report data.

A report metric must have one definition, regardless of whether it is displayed as:

- dashboard KPI
- HTML table
- CSV
- XLSX
- PDF

If Phase 2.40 already exposes a query DTO or report result object, reuse it.

---

# 4. REPORT TYPES

Implement only reports that are actually supported by the existing repository.

At minimum evaluate:

## 4.1 Daily Business Summary

Based on Phase 2.40.

Potential sections:

- reporting period
- order activity
- sales totals
- payment totals
- payment-method breakdown
- debt summary
- stock-health summary
- operational exceptions

Do not include a metric unless its authoritative source and lifecycle semantics are clear.

## 4.2 Sales / Order Report

Potential columns, according to actual domain:

- Order identifier
- order date/time
- customer
- order status
- relevant order total
- payment status
- payment method
- cancellation/refund information where authoritative

Historical values must come from the persisted Order/business records.

Do not join current Product price as a replacement for historical OrderItem price.

## 4.3 Payment Report

Potential columns:

- Payment identifier
- related Order
- amount
- payment method
- status
- created/recorded timestamp
- relevant reference

For bank transfer, use the persisted PaymentReference only where appropriate.

Never export raw webhook payloads, bank credentials, OTPs, secrets, or notification text.

## 4.4 Stock / Inventory Report

Reuse Phase 2.39 semantics.

Potential columns:

- Product
- SKU
- current stock
- threshold/status
- movement date
- movement type
- quantity before
- change
- quantity after
- reason
- actor/order reference where already persisted and safe

Do not reconstruct current stock by replaying movements if Product.stockQuantity is authoritative.

## 4.5 Customer Debt Report

Reuse Phase 2.37 semantics and the canonical debt query.

Potential columns:

- customer
- outstanding balance
- relevant debt/payment information
- reporting date/period where meaningful

Do not invent a debt balance from unrelated payment records.

If a report type cannot be safely supported, omit it rather than creating an approximate report.

---

# 5. FILTER / PERIOD CONTRACT

Exports must accept the same filter semantics as the corresponding report UI.

At minimum, where supported:

- date range
- search
- status
- payment method
- product/category
- customer
- stock status

Do not duplicate filter parsing independently in CSV/XLSX/PDF endpoints.

Use a shared immutable filter/query object.

Date semantics:

- use the application's canonical timezone;
- use explicit inclusive/exclusive boundaries;
- prefer `[start, end)`;
- do not use `23:59:59` hacks;
- handle DST/timezone behavior according to the project's configured timezone;
- validate start <= end;
- reject invalid date ranges deterministically.

An exported report must represent exactly the same filtered population as the corresponding report query.

---

# 6. CSV EXPORT

Implement CSV using a streaming or memory-safe approach where practical.

Requirements:

- UTF-8 output.
- Correct delimiter/escaping.
- Correct quoting when values contain delimiter, quote, or newline.
- Stable column order.
- Stable header names.
- Deterministic date/time formatting.
- Deterministic money formatting.
- No HTML markup.
- No accidental formula injection.

### CSV formula-injection hardening

If a user-controlled string begins with a spreadsheet formula trigger such as:

```text
=
+
-
@
```

do not blindly emit it into a CSV cell in a way that spreadsheet applications may execute it.

Use the project's approved safe-export convention, such as prefixing/escaping according to the chosen CSV security policy.

Do not alter authoritative persisted values; sanitize only at the export representation boundary.

Add tests for malicious-looking customer/product/reference strings.

---

# 7. EXCEL / XLSX EXPORT

If XLSX support exists or an approved spreadsheet dependency is already present, implement a proper XLSX exporter.

Requirements:

- stable worksheet name;
- stable column order;
- correct numeric types for amounts/quantities where appropriate;
- date/time cells represented consistently;
- no business calculations hidden inside spreadsheet formulas;
- no external links;
- no macros;
- no executable content;
- reasonable column widths;
- readable headers;
- large-result handling that does not exhaust PHP memory unnecessarily.

Prefer values already calculated by the report query layer over spreadsheet formulas.

Do not allow a user to alter an exported workbook and treat that modified workbook as authoritative application data.

---

# 8. PDF EXPORT

If PDF support already exists or an approved PDF library is available, provide a printable report.

Requirements:

- readable title;
- reporting period;
- generated timestamp;
- relevant filters;
- clear sections/tables;
- consistent currency/date formatting;
- page headers/footers where supported;
- repeat table headers on page breaks where supported;
- no business logic in Twig;
- no JavaScript dependency for report correctness.

PDF is a presentation of the same report result. It must not calculate different totals.

If a report is too large for practical PDF rendering, provide a deterministic bounded/paginated/summary behavior instead of exhausting memory.

---

# 9. EXPORT RESPONSE / DOWNLOAD SECURITY

Use proper Symfony response semantics.

Requirements:

- correct `Content-Type`;
- safe `Content-Disposition`;
- deterministic filename;
- no path traversal;
- no user-controlled filename injection;
- no arbitrary filesystem path input;
- temporary files cleaned up when used;
- no sensitive information in URLs where avoidable.

Suggested filename pattern:

```text
pos-report-{report-type}-{date-or-range}.{extension}
```

Normalize any dynamic filename component.

Do not expose internal filesystem paths.

---

# 10. AUTHORIZATION

Every export endpoint must enforce the same permission boundary as the underlying report.

Do not rely only on hiding an Export button.

Enforce authorization server-side through the existing Voter/permission architecture.

Verify:

- unauthenticated user rejected;
- unauthorized authenticated user rejected;
- authorized role allowed;
- export endpoint cannot bypass report permissions;
- direct URL access is protected;
- filters cannot broaden access beyond the report permission.

If the repository distinguishes view-report from export-report permissions, reuse that distinction. Do not invent a new permission unless the architecture clearly requires it.

---

# 11. CSRF

GET downloads should remain safe and non-mutating.

If export is implemented through a state-changing POST for architectural reasons, use the existing CSRF convention.

Never use CSRF as a substitute for authorization.

---

# 12. LARGE DATASET / PERFORMANCE HARDENING

Exports can process more rows than the normal paginated UI.

Inspect current query performance.

Requirements:

- avoid N+1 queries;
- use explicit joins/selects where appropriate;
- select only required fields;
- stream rows/chunks where the chosen infrastructure permits;
- avoid loading an entire entity graph into memory;
- avoid rendering thousands of Twig rows before generating CSV/XLSX/PDF;
- avoid repeating identical aggregate queries for each export format;
- preserve stable ordering.

For very large reports, prefer:

```text
database query
    ↓
iterable/report rows
    ↓
streaming renderer
```

over:

```text
database query
    ↓
load all entities
    ↓
map entire dataset into nested arrays
    ↓
render
```

Add query-count/performance regression coverage where the project has infrastructure for it.

---

# 13. CONSISTENCY / SNAPSHOT SEMANTICS

An export must not accidentally combine incompatible reporting periods or query snapshots.

For a single report:

- resolve filters once;
- resolve date boundaries once;
- reuse the same report definition;
- keep ordering deterministic.

If the application already has transaction/snapshot conventions for read reporting, reuse them.

Do not add unnecessary database write transactions around a read-only export.

If concurrent business activity occurs while an export is generated, document/use the repository's normal read consistency semantics rather than inventing a fake global lock.

The export does not need to freeze sales, payments, or stock operations.

---

# 14. MONEY / QUANTITY / DATE FORMATTING

Use existing project conventions.

Money:

- never use floating-point arithmetic for business totals;
- preserve integer minor units / Money value object semantics where applicable;
- use canonical currency;
- use the same rounding rules as the UI/report query.

Quantities:

- preserve the domain's numeric precision;
- do not convert signs without an explicit report definition.

Dates:

- use canonical application timezone;
- stable machine-readable/export-friendly representation for CSV/XLSX;
- human-readable representation for PDF;
- do not mix timezone conventions within one report.

---

# 15. TRANSLATIONS / LABELS

Reuse existing Symfony translation keys where possible.

Do not hard-code duplicate business terminology if the project already has translations.

Column headers should be stable and understandable.

If CSV/XLSX/PDF requires a fixed language, follow the project's current locale/reporting convention. Do not introduce a second locale-selection system without need.

---

# 16. UI INTEGRATION

Extend the existing Phase 2.39/2.40 reporting UI.

Provide Export actions only where the user is authorized.

The UI should:

- preserve active filters;
- show the selected reporting period;
- expose CSV/XLSX/PDF options only when supported;
- avoid auto-downloading on page load;
- remain usable at 360px+;
- use touch targets >= 48px;
- avoid forcing horizontal scrolling for the main controls;
- show clear disabled/loading/error states.

Export buttons are triggers only.

No report calculation belongs in Stimulus/JavaScript.

---

# 17. IDEMPOTENCY / DOUBLE-CLICK

Export is read-only, so it must not create duplicate business records.

Still harden the UX against:

- double-click;
- repeated download requests;
- stale filters;
- multiple simultaneous browser requests.

Do not create an idempotency record unless the existing architecture genuinely requires one.

A repeated export should simply produce the corresponding read-only report again.

---

# 18. AUDIT LOG

Do not automatically write AuditLog entries merely because a user viewed/exported a report.

If the repository already defines export auditing as a security/compliance requirement, reuse that existing convention.

If export auditing is not currently part of the architecture, keep the export read-only and do not invent a new audit policy in this phase.

---

# 19. ERROR HANDLING

Handle:

- invalid filters;
- invalid date range;
- unauthorized access;
- missing report type;
- unsupported export format;
- empty result set;
- renderer/library failure;
- filesystem/temporary-file failure;
- oversized report where applicable.

Errors must not leak:

- SQL;
- stack traces;
- filesystem paths;
- credentials;
- webhook payloads;
- provider secrets.

Use existing application error handling and translation conventions.

For an empty report, return a valid export with headers/structure where that format permits it, rather than fabricating a “No data” business row.

---

# 20. TESTING REQUIREMENTS

Add/extend tests according to existing project structure.

## Query/report tests

Verify:

- KPI/report totals match authoritative data;
- lifecycle/status filters are correct;
- date boundaries are correct;
- timezone handling is correct;
- cancelled/refunded/reversed records follow existing semantics;
- stock movement signs are represented correctly;
- debt uses canonical balance;
- payment totals use persisted payment lifecycle;
- filters produce deterministic populations.

## CSV tests

Verify:

- headers;
- row order;
- escaping;
- quotes/newlines;
- UTF-8;
- money/date formatting;
- formula-injection hardening;
- empty result.

## XLSX tests

Where library support exists:

- workbook opens successfully;
- expected worksheet exists;
- headers and values are correct;
- numeric/date cell semantics are correct;
- no unexpected formulas/macros/external links.

## PDF tests

Where library support exists:

- PDF is generated successfully;
- expected report title/period appears;
- expected totals appear;
- empty report works;
- renderer errors are handled safely.

Do not rely exclusively on byte-for-byte PDF snapshots because PDF metadata/rendering can be nondeterministic. Test meaningful content/structure.

## Security tests

Verify:

- unauthenticated access rejected;
- unauthorized role rejected;
- authorized role allowed;
- direct endpoint access cannot bypass permissions;
- filename/path injection is blocked.

## HTTP tests

Verify:

- correct content type;
- attachment disposition;
- stable filename;
- filter propagation;
- error responses;
- no mutation of business state.

## Regression tests

Run the relevant existing suite.

Confirm this phase does not break:

- checkout;
- payment;
- bank transfer;
- webhook/reconciliation;
- order history;
- cancel/refund;
- product/customer/debt management;
- stock adjustment;
- Phase 2.39 reporting;
- Phase 2.40 dashboard.

---

# 21. FROZEN BANK PAYMENT BOUNDARY

This is mandatory.

Do NOT modify:

- bank webhook controller;
- notification parser;
- webhook authentication;
- Casso/VCB reconciliation;
- PaymentReference matching;
- PaymentReference regeneration semantics;
- webhook idempotency;
- bank-transfer transaction boundaries.

Reporting may READ persisted Payment/PaymentReference information according to existing semantics.

Reporting/export must never become a second payment reconciliation path.

---

# 22. ACCEPTANCE CRITERIA

Phase 2.41 is complete only when:

1. Existing Phase 2.39/2.40 report definitions remain authoritative.
2. Export does not introduce a second business calculation engine.
3. CSV export works for supported reports.
4. XLSX export works if supported by existing/approved project infrastructure.
5. PDF export works if supported by existing/approved project infrastructure.
6. Filters and date ranges are identical between UI and export.
7. Money/date/quantity semantics are deterministic.
8. Large datasets do not cause obvious N+1 or full-entity-memory failures.
9. Authorization is enforced server-side.
10. Export filenames and filesystem handling are hardened.
11. CSV formula injection is handled safely.
12. Exporting never mutates business records.
13. Empty/error states are handled deterministically.
14. Relevant tests pass.
15. Existing critical POS flows remain green.
16. Webhook/reconciliation remains untouched.

---

# 23. IMPLEMENTATION ORDER

Recommended order:

1. Inspect Phase 2.39 and 2.40 report/query architecture.
2. Inventory existing export dependencies/utilities.
3. Define/reuse canonical report result DTOs.
4. Define/reuse shared report filters and period semantics.
5. Implement CSV renderer.
6. Implement XLSX renderer only where supported.
7. Implement PDF renderer only where supported.
8. Add secure download endpoints.
9. Add UI export controls.
10. Add authorization/security tests.
11. Add renderer/query/HTTP tests.
12. Run the relevant full regression suite.
13. Review diff for accidental business-rule or webhook changes.

---

# 24. FINAL SOURCE REVIEW

Before declaring completion, verify:

- no duplicated KPI formulas in Twig/Stimulus;
- no business logic in export controllers;
- no new competing domain model;
- no historical data mutation;
- no stock/payment/order semantic changes;
- no raw bank notification export;
- no secrets in exported files;
- no unbounded entity loading for large exports where avoidable;
- no authorization bypass;
- no unsafe CSV formulas;
- no path traversal;
- no accidental webhook/reconciliation changes.

The final implementation must fit the existing Symfony architecture and remain a thin reporting/export layer over authoritative persisted business data.
