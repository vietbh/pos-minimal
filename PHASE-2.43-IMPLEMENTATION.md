# PHASE 2.43 — POS Notification / Alert Center & Operational Alerts

## 0. Mission

Implement Phase 2.43 as a production-grade, read-oriented operational notification and alert layer on top of the existing POS domains and reporting/operations architecture.

The Alert Center must surface actionable operational conditions without becoming a second source of business truth and without mutating Order, Payment, PaymentReference, Stock, Customer, or reporting state merely because an alert is displayed, read, dismissed, or refreshed.

This phase is about **operational alerts**, not generic chat/messaging and not bank-notification ingestion. The existing Android bank notification bridge and backend bank webhook/reconciliation flow remain completely outside the scope of this phase.

**WEBHOOK / RECONCILIATION / PAYMENTREFERENCE MATCHING ARE FROZEN. DO NOT MODIFY THEM.**

## 1. Mandatory repository-first workflow

Before changing code:

1. Inspect the current repository and read the implementation artifacts from Phases 2.38, 2.39, 2.40, 2.41 and 2.42.
2. Inspect the actual domain/application models for Product stock, StockMovement, Orders, Payments, PaymentReference, Customer debt/balance, export jobs, Messenger jobs, and existing operational/observability components.
3. Search for any existing Notification, Alert, FlashMessage, Inbox, Event, DomainEvent, Messenger message, scheduler, command, cron, or persistence pattern that can be reused.
4. Inspect Symfony security/Voters/permissions and the current user/role model.
5. Inspect Twig/Stimulus UI conventions and the application shell/navigation.
6. Inspect Doctrine migrations and repositories before introducing new persistence.
7. Inspect tests before implementing behavior.
8. Do not assume Redis, WebSockets, Mercure, email, SMS, push notifications, or a third-party notification service exists. Reuse existing infrastructure only.
9. Do not introduce a second event bus, notification framework, scheduler, or queue abstraction when an existing project mechanism can support the requirement.

## 2. Core architectural rules

### 2.1 Business domains remain source of truth

Alerts must be derived from authoritative domain/application queries. They must never become the source of truth for:

- stock quantity
- Order status/totals
- Payment state
- PaymentReference lifecycle
- customer debt/balance
- export-job lifecycle

An alert is an operational projection/reference to a condition, not the condition itself.

### 2.2 Read-only presentation

Opening the Alert Center, refreshing it, marking an alert read, dismissing an alert, or filtering alerts must not mutate business entities.

The only permitted mutation is the notification/alert projection itself, such as read/dismissed timestamps, if the repository's architecture requires persistent alert state.

### 2.3 No duplicated business logic

Do not reimplement stock, debt, payment, order, export, or reporting calculations inside alert code.

Reuse existing query/application services wherever possible.

## 3. Alert categories

Inspect the repository and implement only categories supported by real existing data. Candidate operational categories include:

- LOW_STOCK — product at/below configured low-stock threshold if such a threshold exists.
- OUT_OF_STOCK — product has zero/negative stock where the domain permits the condition.
- STOCK_ADJUSTMENT_EXCEPTION — only if the existing stock domain exposes a real exceptional condition worth surfacing.
- EXPORT_FAILED — background report/export job failed.
- EXPORT_EXPIRED — export artifact/job expired and is no longer downloadable.
- PAYMENT_EXCEPTION — only for an already modeled payment exception; do not invent a new payment state.
- DEBT_EXCEPTION — only for an existing actionable debt condition represented by the current domain.
- OPERATIONAL_FAILURE — only where an existing operational/observability signal can be safely projected into the Alert Center.

Do not create alerts for every normal business event. Alerts should represent conditions requiring awareness or action.

Do not invent thresholds, payment states, debt rules, or severity rules that are absent from the current repository. If a required threshold/configuration does not exist, document the gap rather than silently inventing one.

## 4. Alert model / persistence

First determine whether an existing notification/alert persistence model already exists.

If none exists and persistence is justified, introduce a minimal model with concepts appropriate to the repository, such as:

- stable alert identifier
- alert category/type
- severity
- title/message key or safe presentation payload
- related aggregate/entity reference where safe
- deduplication/fingerprint key
- current lifecycle/read state
- created timestamp
- first/last observed timestamps if useful
- read timestamp
- dismissed/resolved timestamp only if the lifecycle is actually supported
- optional actor/user scope if alerts are user-specific

Do not persist raw bank notifications, credentials, secrets, access tokens, or unnecessary sensitive data.

Prefer stable typed enums/value objects over arbitrary strings if that matches the project's domain conventions.

## 5. Alert lifecycle and semantics

Define explicit semantics for:

- NEW / UNREAD
- READ
- optionally DISMISSED or RESOLVED only if the project genuinely needs these states.

Distinguish between:

1. **condition state** — e.g. product is currently low stock;
2. **alert record state** — whether the user has seen/read/dismissed the alert.

Reading an alert must not make the underlying business condition disappear.

If the condition remains active, a future refresh must not generate an uncontrolled duplicate alert for the same condition.

When the condition clears and later becomes active again, determine whether a new alert should be created based on an explicit deduplication/fingerprint policy.

## 6. Deduplication / idempotency

Alert generation may be triggered repeatedly by page loads, scheduled checks, workers, or retries. It must therefore be idempotent.

Use a stable fingerprint derived from the actual condition, for example conceptually:

`alert_type + aggregate_type + aggregate_id + condition_version/key`

Do not use random UUID generation alone as the deduplication mechanism.

Concurrent workers must not create duplicate active alerts for the same logical condition.

Use database unique constraints and/or appropriate locking/atomic upsert semantics rather than application-level check-then-insert alone.

Do not let Messenger redelivery or scheduler retries create alert storms.

## 7. Alert detection architecture

Inspect the existing application for suitable mechanisms:

- synchronous query projection
- domain/application events
- Symfony Messenger
- Symfony Scheduler
- existing cron commands
- existing reporting queries

Prefer the smallest architecture that fits the repository.

If alert generation can safely be derived on demand from existing read models without persistence, do not introduce persistence merely for convenience.

If persistence is required for unread state, deduplication, or operational history, generate/update alerts through an idempotent application service.

Do not make alert generation part of critical checkout/payment/stock transactions unless the repository already has an established transactional domain-event pattern that makes this safe.

An operational alert failure must never cause a successful sale, payment, stock adjustment, or other critical business transaction to fail.

## 8. Alert Center UI

Implement a mobile-first Alert Center integrated with the existing authenticated application shell.

Minimum behavior where supported:

- unread count/badge
- list of alerts
- severity/category indication
- concise title/message
- relative/absolute timestamp as appropriate
- read/unread visual distinction
- filtering by category/severity/read state if useful
- pagination or bounded loading for large alert volumes
- alert detail or contextual link when a safe route exists
- mark as read
- mark all as read only if authorization and volume semantics are safe
- dismiss only if a real dismissed lifecycle is implemented

Do not navigate users to unauthorized resources.

Do not put business logic in Twig or Stimulus.

## 9. Dashboard integration

Integrate operational alert summaries into the Phase 2.40 dashboard only where useful and where existing architecture supports it.

Examples:

- unread operational alerts count
- low-stock count
- failed exports count
- other actionable exception counts supported by real domain data

The dashboard summary and Alert Center must derive from the same canonical alert/query layer so counts do not disagree.

Do not add noisy duplicate widgets that simply repeat every alert.

## 10. Security / permissions

Enforce alert access server-side.

Inspect the existing Permission Matrix and Voter architecture.

A user must only see alerts they are authorized to see.

If alerts are global operational alerts, enforce the appropriate operational/admin permission. If alerts are user-scoped, enforce ownership as well.

Never trust:

- user-supplied alert IDs
- entity IDs in redirect URLs
- client-side read/dismiss flags
- client-side role claims

Protect mutating alert endpoints with the project's CSRF conventions where applicable.

## 11. Alert-to-entity navigation

When an alert references a Product, Order, Customer, ExportJob, or other entity:

- resolve the entity server-side
- verify authorization before linking/accessing it
- handle deleted/archived entities safely
- never expose internal filesystem paths or implementation identifiers unnecessarily

An alert must remain harmless if its referenced entity no longer exists.

## 12. Severity and presentation

Use a small explicit severity model appropriate to the repository, for example:

- INFO
- WARNING
- CRITICAL

Do not infer severity from arbitrary wording in templates.

Severity should come from backend/domain/application logic.

Do not use flashing/neon visual treatment. Follow the existing POS design system: high contrast, simple colors, clear typography, touch targets >=48px, body text around 16px, and mobile-first layout for 360px+ screens.

## 13. Notification delivery boundary

This phase may expose alerts in the web/mobile POS UI.

Do not automatically add email, SMS, browser push, Web Push, Firebase, or Android push delivery unless such infrastructure already exists and is explicitly part of the repository architecture.

The Android bank notification bridge is not an alert-delivery provider for backend operational alerts.

Never send bank credentials, OTPs, raw bank notifications, or payment secrets through operational alerts.

## 14. Performance / query hardening

Alert Center queries must be bounded and indexed.

Requirements:

- no N+1 when listing alerts
- server-side pagination for large histories
- appropriate indexes for unread/user/category/created-at queries
- avoid scanning every Product/Order/ExportJob on every page request if the repository has a more appropriate aggregate/query mechanism
- dashboard unread counts must use efficient aggregate queries
- do not load complete entity graphs merely to render alert rows

If a periodic detection job is introduced, make its query incremental/bounded where possible.

## 15. Retention / cleanup

Alert records are operational data, not replacements for business history.

If retention is needed, implement it through the repository's existing scheduler/command infrastructure.

Cleanup must:

- be idempotent
- use explicit retention rules
- never delete Orders, Payments, PaymentReferences, StockMovements, Customers, or Export business data
- safely tolerate already-missing records/artifacts

Do not silently delete active/unread alerts without an explicit retention policy.

## 16. Observability

Use the existing logging/observability conventions.

Useful signals include:

- alert generation success/failure
- duplicate suppression
- alert query failures
- cleanup failures
- notification endpoint authorization failures where appropriate

Logs must not contain raw bank notification payloads, credentials, OTPs, or unnecessary personal/payment data.

Do not log every normal alert read operation at noisy levels.

## 17. Critical business-flow isolation

Alert processing must never alter the correctness of:

- checkout
- payment application
- cash payment
- bank transfer reconciliation
- PaymentReference lifecycle
- stock adjustment
- order cancellation/refund
- customer debt settlement
- report/export generation

If alert generation fails, the underlying business operation must remain successful when the business operation itself succeeded.

## 18. Testing requirements

Implement tests matching the repository's actual test organization.

### Domain/Application

- alert type/severity semantics
- deduplication fingerprint
- condition-to-alert mapping
- read-state behavior
- condition remains independent from alert read state

### Integration

- persistence
- unique deduplication constraint
- concurrent duplicate generation
- query pagination/filtering
- unread counts
- entity references
- retention/cleanup if implemented

### Security

- authorized user can access permitted alerts
- unauthorized role cannot access operational alerts
- cross-user alert access is rejected where alerts are scoped
- CSRF protection for mutating endpoints

### HTTP/Acceptance

- Alert Center renders correctly
- unread badge/count
- filters
- mark-read flow
- safe redirect/entity navigation
- missing/deleted entity behavior
- empty state
- error state

### Regression

Run the relevant existing suites, especially:

- stock adjustment/concurrency
- payment/webhook tests
- order/cancel/refund tests
- debt/settlement tests
- reporting/export tests
- Phase 2.42 async export/idempotency tests

Alert implementation must not modify the frozen payment/webhook behavior.

## 19. Explicit non-goals

Do NOT implement in this phase:

- bank notification ingestion
- Casso webhook changes
- PaymentReference matching changes
- bank account changes
- payment reconciliation changes
- Android notification-listener changes
- new payment statuses merely for alerting
- new stock business rules merely to create alerts
- a generic chat/messaging system
- email/SMS/push infrastructure from scratch
- a second scheduler/queue framework
- a second reporting engine
- automatic business mutations triggered by viewing an alert

## 20. Acceptance criteria

Phase 2.43 is complete only when:

1. The implementation is based on the repository's actual existing architecture.
2. Alert sources are canonical domain/application queries or established domain events.
3. Alerts are idempotent and duplicate-safe under retries/concurrency.
4. Alert state is clearly separated from business condition state.
5. Alert Center is secure and mobile-first.
6. Dashboard alert summaries agree with Alert Center data.
7. No N+1 or unbounded alert queries are introduced.
8. Operational alert failure cannot break critical POS business transactions.
9. Tests cover application, persistence, concurrency/idempotency, security, HTTP, and regression paths relevant to the implementation.
10. No webhook/reconciliation/PaymentReference behavior has been changed.
11. No raw bank notification, credential, OTP, or sensitive secret is persisted/logged/displayed by the alert system.
12. The implementation does not invent unsupported business rules; missing capabilities are documented explicitly.

## 21. Final implementation report

After implementation, report:

- files added/changed
- migrations added
- routes/endpoints added
- alert categories actually implemented
- detection/generation mechanism used
- deduplication strategy
- permissions/Voters used
- retention/cleanup behavior
- tests executed and their results
- any infrastructure/configuration changes
- any intentionally unsupported alert categories and why
- explicit confirmation that webhook/reconciliation/PaymentReference behavior remains untouched
