# PHASE 2.44 — POS Audit / Activity Timeline & Operational Traceability

## 0. Mission

Implement Phase 2.44 as a production-grade, read-oriented audit and activity timeline layer for operational traceability across the existing POS application.

The goal is to let authorized users answer: **who did what, to which business object, when, and what safe contextual information was recorded** — without turning AuditLog into a second source of business truth.

This phase must distinguish **AuditLog / operational traceability** from business-history records such as StockMovement, Payment history, Order history, and PaymentReference lifecycle records. Those domain records remain authoritative for their own business facts.

**WEBHOOK / RECONCILIATION / PAYMENTREFERENCE MATCHING ARE FROZEN. DO NOT MODIFY THEM.**

## 1. Mandatory repository-first workflow

Before changing code:

1. Inspect the current repository and read the implementation artifacts from Phases 2.35 through 2.43, especially the existing permission, stock, reporting, export, alert, and observability work.
2. Search the repository for any existing `AuditLog`, audit/event/activity/history/timeline implementation, Doctrine listeners/subscribers, domain events, Messenger messages, security voters, or logging abstraction.
3. Inspect the actual entities/application services for Product, StockMovement, Order, Payment, PaymentReference, Customer, ExportJob, Alert, User and other relevant aggregates.
4. Inspect existing migrations, repositories, indexes, enum/value-object conventions, and tests before introducing new persistence.
5. Inspect the Permission Matrix and Voter/security architecture.
6. Inspect Twig/Stimulus conventions and authenticated application-shell navigation.
7. Do not assume an audit framework, event store, WebSocket, Redis, Elasticsearch, external logging service, or third-party audit product exists.
8. Reuse existing infrastructure whenever possible. Do not introduce a second audit/event framework.

## 2. Core architectural rules

### 2.1 Domain records remain source of truth

Audit entries are an operational trace/projection. They must never replace or redefine:

- StockMovement for stock history
- Payment records for payment history/state
- PaymentReference for bank-transfer reconciliation lifecycle
- Order history/status/totals
- Customer debt/balance records
- ExportJob lifecycle
- Alert lifecycle

If a screen needs an authoritative business fact, read the corresponding domain/application query rather than reconstructing it from AuditLog.

### 2.2 Audit is append-oriented

Audit records should be immutable after creation whenever possible.

Do not provide generic UI editing/deleting of audit entries.

If retention/cleanup is required by an existing project policy, implement it as an explicit operational concern with authorization and tests; do not silently mutate historical audit records from the normal UI.

### 2.3 No business logic in audit presentation

Twig/Stimulus must not decide whether an action was authorized, whether an order was paid, whether stock changed correctly, or whether a user may see an entity.

The backend/application layer supplies the audit facts and authorization decisions.

## 3. Audit event scope

First inventory existing auditable actions. Prefer real actions already implemented in the repository.

Candidate categories include:

- AUTH / LOGIN / LOGOUT where the existing security architecture exposes a reliable event.
- PRODUCT_CREATE / PRODUCT_UPDATE / PRODUCT_STATUS_CHANGE where such actions exist.
- CUSTOMER_CREATE / CUSTOMER_UPDATE where such actions exist.
- STOCK_ADJUSTMENT initiation/result where an auditable application action exists.
- ORDER_CREATE / ORDER_CANCEL / REFUND where those flows exist.
- PAYMENT_ACTIONS where an actual application action is performed, without duplicating payment history.
- DEBT_SETTLEMENT where the actual settlement flow exists.
- EXPORT_REQUEST / EXPORT_COMPLETED / EXPORT_FAILED where Phase 2.42 exposes reliable lifecycle transitions.
- ALERT_READ / ALERT_DISMISSED only if the existing Alert Center treats these as operationally meaningful.

Do not invent events for functionality absent from the repository.

Do not create an audit entry merely because a read-only page was viewed unless there is a documented compliance/operational reason to do so.

## 4. Audit record model

If an audit model already exists, extend it minimally and preserve compatibility.

If no suitable model exists, introduce the smallest repository-consistent immutable record that can answer:

- stable audit identifier
- event/action type
- occurred timestamp
- actor/user identifier when known
- actor display context only if safe and necessary
- aggregate/entity type
- aggregate/entity identifier
- safe human-readable summary or structured metadata
- request/correlation identifier if the existing observability architecture has one
- outcome/status if the action can succeed/fail and the distinction is meaningful

Prefer typed enums/value objects where consistent with the existing codebase.

Do not store passwords, tokens, OTPs, bank credentials, raw bank notifications, authorization headers, session secrets, or other sensitive payloads.

Avoid storing complete request bodies by default.

## 5. Structured metadata and privacy

Audit metadata must be deliberately bounded and safe.

Prefer structured fields such as:

- changed field names
- old/new values only when the value is non-sensitive and genuinely needed
- reason code
- reference number
- aggregate identifiers
- safe result/status

Do not capture arbitrary serialized entities.

Do not store raw exception stacks, full request payloads, uploaded file contents, or secrets in the normal audit record.

If a sensitive field changes, record that it changed rather than storing the sensitive value.

## 6. Actor and system actions

Support both:

- authenticated human actor
- system/background actor when a Messenger worker, scheduled task, or command performs an auditable action

Do not fabricate a user identity for background work.

Use an explicit system actor representation consistent with the repository if one exists.

Record enough context to distinguish a manual action from a background process without exposing infrastructure secrets.

## 7. Transactional semantics

Audit creation must respect the business transaction boundary.

For critical mutations:

- determine whether the existing architecture supports writing the audit record in the same transaction as the business mutation;
- if so, preserve atomicity;
- if the project uses an established post-commit/domain-event mechanism, reuse it rather than inventing a second mechanism.

An audit failure must not silently produce a false audit entry claiming success.

Conversely, do not make a successful critical business transaction fail merely because a non-critical observability path is unavailable unless the repository explicitly treats the audit as a mandatory compliance record.

Document the chosen consistency semantics in code/tests.

## 8. Idempotency and duplicate protection

Repeated HTTP requests, Messenger redelivery, retries, and concurrent execution must not create uncontrolled duplicate audit records for the same logical action when the underlying action is idempotent.

Where the domain has a stable command/request/idempotency key, preserve it in the audit correlation context.

Do not invent a universal deduplication rule that would incorrectly collapse legitimate repeated actions.

If a business action legitimately occurs twice, the audit must be able to represent two distinct occurrences.

## 9. Activity Timeline query layer

Create or reuse a backend query/application service for timeline retrieval.

Requirements:

- newest-first default ordering
- deterministic secondary ordering
- server-side pagination/bounded result size
- filter by actor where authorized
- filter by event/category where useful
- filter by aggregate/entity where useful
- date/time range filters with explicit timezone semantics
- safe search only over indexed/approved fields
- no N+1 entity loading
- appropriate database indexes

Do not reconstruct the timeline by joining every business table into one giant query if a dedicated audit table/query is more appropriate.

The timeline is a traceability view, not a replacement for domain history.

## 10. Timeline UI

Implement a mobile-first Activity/Audit Timeline integrated into the existing authenticated application shell.

Minimum behavior where appropriate:

- timeline/list view
- timestamp
- actor/system indicator
- action/category
- concise summary
- related entity link when authorized
- filters
- pagination/bounded loading
- empty state
- loading/error state
- detail/expanded metadata only when safe and useful

Target 360px+ widths.

Touch targets >=48px.

Use existing POS typography/color conventions and high-contrast presentation.

Do not create flashy or noisy visualizations.

## 11. Entity-specific activity views

Where useful and supported, provide an activity section for a specific Product, Order, Customer, ExportJob, or other aggregate.

The activity view must:

- enforce entity authorization server-side;
- show only audit records actually related to that aggregate;
- tolerate deleted/archived entities;
- not leak audit records from another tenant/scope/user if the project has such boundaries.

Do not make an entity page depend on AuditLog for its authoritative business state.

## 12. Security / permissions

Enforce audit access server-side using the existing Permission Matrix/Voter architecture.

Possible separation:

- general operational audit permission
- admin audit permission
- own-activity visibility where the current permission model supports it

Do not expose audit data merely because the user knows an audit ID or entity ID.

Protect any audit-related mutation endpoint (if one exists, such as acknowledge/read) with the project's CSRF conventions.

Never trust client-side role claims or filter restrictions.

## 13. Audit vs business history — mandatory distinction

The implementation must explicitly preserve these boundaries:

### Stock

`StockMovement` remains the authoritative stock movement history.

Audit may record that a stock adjustment command/action was performed, but must not become the quantity ledger.

### Payment

Payment records remain authoritative for payment history and state.

Audit may record an operational payment action, but must not duplicate or redefine payment status.

### PaymentReference

PaymentReference remains authoritative for bank-transfer reference lifecycle and reconciliation.

Audit may record safe operational events around the reference if the repository already exposes such actions.

**Do not modify webhook/reconciliation logic.**

### Orders

Order history and domain status remain authoritative.

Audit is supplemental traceability.

### Debt

Debt/settlement records remain authoritative.

Audit is not a debt ledger.

## 14. Operational dashboard integration

Integrate audit information into the Phase 2.40/2.43 operational surfaces only where it adds traceability value.

Examples:

- latest operational actions
- “who last changed this” contextual link
- recent failed operational actions

Do not turn the dashboard into a giant audit feed.

Counts and KPIs must continue to come from their canonical query layers.

## 15. Observability / correlation

If Phase 2.42/2.43 or the existing project already has request IDs, correlation IDs, command IDs, or job IDs, reuse them.

A useful audit record should allow an operator to correlate:

HTTP request → application command → business mutation → background job/event

without exposing infrastructure secrets.

Do not invent a second correlation-ID system if one already exists.

## 16. Performance

Audit tables can grow quickly. Design for growth from the beginning:

- indexes for common filters/orderings
- bounded pagination
- avoid unbounded `SELECT *`
- avoid loading full related entities for every timeline row
- select only fields needed by the UI
- consider archival/retention only if repository requirements support it

Add query-level tests or profiling safeguards where the project has an established pattern.

## 17. Failure semantics

Audit/timeline failures must not corrupt business state.

Examples to test:

- audit write failure during non-critical action
- duplicate command retry
- Messenger redelivery
- concurrent identical command
- missing/deleted related entity
- unauthorized audit access
- malformed filter/date range
- large audit dataset

The implementation must make the chosen consistency/failure policy explicit rather than silently swallowing every failure.

## 18. Testing requirements

Add/update tests appropriate to the actual architecture:

### Domain/Application

- audit event creation semantics
- actor/system context
- safe metadata
- immutable behavior
- event classification
- duplicate/idempotency behavior where applicable

### Integration/Doctrine

- persistence
- unique/index constraints
- pagination/order determinism
- filters
- related aggregate references
- migration correctness

### Security/Voter

- authorized user can view permitted audit data
- unauthorized user cannot view it
- cross-entity/scope access is denied

### HTTP/Acceptance

- timeline loads
- filters work
- pagination works
- entity links enforce authorization
- empty/error states work

### Concurrency

Where the repository has real concurrency tests, cover repeated/concurrent actions so audit records reflect actual logical actions without uncontrolled duplication.

### Regression

Run the relevant existing test suite and verify checkout, payment, stock, debt, export, alert, and login flows remain unchanged.

## 19. Explicit non-goals / forbidden changes

Do NOT:

- modify bank webhook reconciliation;
- modify PaymentReference matching/lifecycle semantics;
- store raw bank notifications;
- change Android bank-notification bridge behavior;
- replace StockMovement with AuditLog;
- replace Payment history with AuditLog;
- replace Order history with AuditLog;
- replace debt ledger/history with AuditLog;
- add a second queue/event bus/audit framework;
- put business authorization in Twig/Stimulus;
- add generic audit CRUD editing/deletion;
- invent business statuses or thresholds merely to populate audit events;
- make dashboard/reporting KPIs depend on audit records;
- expose secrets or raw request payloads.

## 20. Definition of Done

Phase 2.44 is complete only when:

1. Existing audit/activity infrastructure has been reused or a minimal repository-consistent implementation has been added.
2. Audit records are immutable/append-oriented and contain only safe, useful operational context.
3. Actor and system actions are distinguishable.
4. Timeline queries are paginated, deterministic, authorized, and performant.
5. Mobile-first Activity/Audit UI is integrated into the existing application shell.
6. Entity-specific activity views, where implemented, enforce authorization.
7. AuditLog is clearly separated from StockMovement, Payment, PaymentReference, Order, Debt, ExportJob, and Alert business history.
8. Idempotency/concurrency behavior is tested where relevant.
9. Sensitive data is not captured.
10. Relevant application, integration, security, HTTP, concurrency, and regression tests pass.
11. Webhook/reconciliation/PaymentReference matching remains untouched.
12. No new duplicate infrastructure has been introduced.
