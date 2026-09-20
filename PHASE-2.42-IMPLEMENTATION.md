# PHASE 2.42 — POS Reporting Scheduling / Background Export & Large Report Processing

## 0. Mission

Implement Phase 2.42 as a production-grade extension of the existing POS reporting/export architecture from Phases 2.39–2.41.

This phase moves genuinely large/slow reports and exports from synchronous HTTP execution to background processing where appropriate. The implementation must preserve the existing reporting/query source of truth, permissions, date/time semantics, export formats, and business invariants.

Do NOT redesign the reporting domain. Do NOT create a second reporting engine. Inspect the current repository first and adapt to the architecture that actually exists.

## 1. Mandatory repository-first workflow

Before changing code:

1. Inspect the current repository structure and existing Phase 2.39/2.40/2.41 implementation.
2. Identify the actual report/query/application services, export services, controllers/routes, security/Voters, Messenger configuration, queues/transports, workers/commands, storage abstraction, and tests.
3. Identify whether CSV/XLSX/PDF exporters already exist and how they are invoked.
4. Identify the existing persistence model and naming conventions for asynchronous jobs/tasks, if any.
5. Inspect `composer.json`, Symfony Messenger configuration, Doctrine configuration, cache/storage configuration, and environment configuration.
6. Do not assume Redis, RabbitMQ, S3, Supervisor, cron, or any external infrastructure exists. Reuse what is already configured; if a new infrastructure dependency is truly required, document it and keep the implementation aligned with the project's existing deployment model.
7. Read the relevant existing tests before implementing behavior.

## 2. Core architectural rule

The existing canonical reporting/query layer remains the only source of report data.

The background job must call the same application/query/export services used by synchronous reporting/export where practical. It must NOT independently recalculate KPIs, totals, stock balances, debt, payment summaries, or date boundaries.

The flow should conceptually be:

HTTP request → authorize → create export job → persist job state → dispatch message → worker processes job → write artifact → update job state → UI/download endpoint retrieves artifact.

For small reports where synchronous export remains safe and already supported, preserve synchronous behavior unless the repository clearly benefits from making it asynchronous.

## 3. Async export job model

If the project has no suitable existing model, introduce a minimal persistent export-job record appropriate to the architecture.

It should contain enough information to safely manage lifecycle and retrieval, such as:

- stable job identifier
- requested report/export type
- serialized validated report filters/query parameters
- requested format
- requesting user/actor reference if the security model supports it
- lifecycle status
- progress information only if meaningful and reliable
- artifact storage reference/path
- filename/content metadata where appropriate
- error state suitable for user-facing status without exposing internals
- created/started/completed/failed/expired timestamps
- retry/attempt metadata if needed
- timestamps for retention/cleanup

Do not persist secrets, raw bank notifications, credentials, or unnecessary sensitive data.

Use explicit lifecycle states rather than loosely interpreted strings. At minimum support the repository's appropriate equivalents of:

`QUEUED → PROCESSING → COMPLETED`

and terminal failure/expiry/cancellation states where required.

Do not invent cancellation semantics unless they can be implemented safely.

## 4. Idempotency and duplicate-submit hardening

Multiple clicks, browser retries, refreshes, duplicate HTTP requests, or Messenger redelivery must not create uncontrolled duplicate work.

Implement appropriate idempotency protection for job creation and worker execution.

Important distinction:

- Two intentionally different export requests may legitimately create two jobs.
- The same logical request retried because of transport/HTTP duplication must be safely deduplicated when the architecture can establish a stable idempotency key.
- Worker messages are assumed at-least-once delivered. Processing must therefore be safe against redelivery.

Use database uniqueness/locking/state transitions where appropriate rather than relying only on application-level checks.

A worker must not overwrite a completed artifact or regress a terminal job back to processing because of a duplicate message.

## 5. Worker / Messenger implementation

If Symfony Messenger is already present, integrate with it rather than inventing a parallel queue abstraction.

Implement a dedicated message/handler for report export processing where appropriate.

The handler must:

1. Load the persistent job.
2. Verify that the job is eligible for processing.
3. Atomically transition it into processing where concurrency matters.
4. Execute the canonical report query.
5. Generate the requested export using the existing exporter layer.
6. Store the resulting artifact through the project's storage abstraction.
7. Persist completion metadata atomically enough to avoid exposing an incomplete artifact.
8. Mark the job completed.
9. On recoverable failure, allow Messenger retry behavior to work correctly.
10. On terminal failure, mark the job failed with a safe user-facing error state.

Do not swallow exceptions merely to make a job appear successful.

Do not acknowledge successful completion before the artifact and job state are durably persisted.

## 6. Large-report processing

Design for large datasets without loading the entire report into PHP memory when avoidable.

Inspect and use the existing query layer's pagination/streaming/iterable capabilities.

For CSV:
- Prefer streaming generation where the existing architecture permits it.
- Avoid constructing one enormous PHP array/string.

For XLSX:
- Use the project's existing spreadsheet implementation if present.
- Use a memory-conscious writer mode when supported.
- Do not add a new spreadsheet library merely for convenience if an existing dependency already handles it.

For PDF:
- Respect the actual capabilities/limits of the existing PDF generator.
- If very large PDFs are unsafe to generate synchronously or in one memory-heavy operation, establish a bounded/appropriate report-size policy instead of pretending PDF generation is infinitely scalable.

Do not fake progress percentages. Only report progress when the implementation can calculate it reliably.

## 7. Artifact storage and download security

Artifacts must not be exposed through predictable public filesystem paths.

Reuse the existing storage abstraction where possible.

Download endpoint requirements:

- Authenticate the requester.
- Authorize access to the specific export job/artifact.
- Do not trust a client-supplied filesystem path.
- Resolve the artifact through the persisted job record/storage abstraction.
- Return the correct content type and safe filename.
- Do not allow path traversal.
- Do not serve another user's export.
- Do not expose internal storage paths.

If the project's authorization model is role/Voter based, enforce it server-side on both job creation and artifact download/status access.

## 8. Job status UI

Add a mobile-first report/export job experience only where the current UI architecture calls for it.

The user should be able to understand:

- report type
- requested period/filters
- format
- queued/processing/completed/failed/expired state
- creation/completion time where useful
- download action when completed
- retry action only if the domain safely supports retry

Do not poll aggressively. If polling is used, make it bounded and resilient to transient failures.

Never show “completed” before the backend has durably marked the job completed and the artifact is available.

Never fabricate progress or completion on the frontend.

Use the existing Twig/Stimulus conventions. Keep business logic in the backend.

## 9. Retention / cleanup

Export artifacts are temporary operational outputs, not permanent business history unless the repository explicitly models them otherwise.

Implement retention/cleanup only if the existing application has a suitable command/scheduler/worker mechanism.

If cleanup is introduced:

- delete only expired artifacts/jobs according to explicit policy
- do not delete business records such as Orders, Payments, StockMovements, or PaymentReferences
- make cleanup idempotent
- handle missing artifacts safely
- make retention configurable where appropriate

Do not silently delete reports users may still be entitled to access.

## 10. Scheduling

This phase is about background processing and scheduling infrastructure, not recurring business reports unless the repository already has the necessary scheduling architecture.

If scheduled report generation is implemented:

- represent the schedule explicitly and persistently
- authorize who may create/change schedules
- prevent duplicate scheduler dispatches
- make scheduled execution idempotent
- preserve the same report/query/export source of truth
- do not introduce a custom scheduler if Symfony Scheduler/Messenger or an existing project mechanism is already available

Do not make scheduled reporting a prerequisite for asynchronous one-off export jobs.

## 11. Transaction boundaries

Creating an export job and dispatching work must not leave an impossible state such as a committed job that can never be processed because the dispatch was lost.

Inspect the repository's Messenger transaction/dispatch configuration.

Use the architecture's appropriate after-commit/transactional dispatch mechanism if available.

Do not hold a long database transaction open while generating a large CSV/XLSX/PDF.

Report generation itself should generally occur outside the request transaction and outside a long-lived Doctrine transaction.

Do not modify business entities merely to generate a report.

## 12. Business-data immutability

Exports are read-only.

The following must never be mutated by report generation/export processing:

- Order historical data
- OrderItem historical unit prices
- Payment history
- PaymentReference lifecycle
- StockMovement history
- Product stock quantity
- Customer debt/balance
- bank reconciliation state

The report must reflect canonical persisted state as defined by existing reporting services.

## 13. Security

Enforce permissions at the backend/application boundary.

Cover:

- report creation
- job status access
- artifact download
- scheduled-report administration if implemented
- cleanup/admin operations if exposed

Do not rely on hiding buttons in Twig.

Respect existing ROLE/Voter/permission architecture.

Do not weaken CSRF protection on state-changing job-management endpoints.

## 14. Failure and retry semantics

Explicitly distinguish:

- validation failure
- authorization failure
- transient infrastructure failure
- exporter failure
- storage failure
- permanent application/data failure

Messenger retryable exceptions should remain retryable. Permanent failures should reach a terminal failed state without infinite retry loops.

If a worker crashes after creating an artifact but before marking the job completed, recovery must not expose corrupt/incomplete output or generate uncontrolled duplicates.

If an artifact already exists for a job, worker logic must determine whether it is a valid completed artifact before regenerating it.

## 15. Observability

Use the project's existing logging/observability conventions.

Log structured operational information such as:

- job id
- report/export type
- format
- lifecycle transition
- duration
- row/item count when available
- retry/attempt count
- failure category

Never log sensitive payment/bank notification content or credentials.

Do not dump full report payloads into logs.

## 16. Tests — mandatory

Add or update tests at the appropriate layers.

### Application/domain tests
- valid job lifecycle
- invalid state transitions
- idempotent duplicate handling
- authorization decisions where applicable
- retention/expiry behavior if implemented

### Integration tests
- job persistence
- message dispatch
- handler processing
- artifact creation
- failure/retry behavior
- duplicate worker delivery
- concurrent worker execution
- storage failure
- exporter failure

### HTTP/acceptance tests
- authorized job creation
- unauthorized creation
- job status access isolation
- completed artifact download
- cross-user artifact access rejection
- invalid/expired job behavior
- correct content type and filename

### Large-data tests
Use realistic enough datasets to detect obvious memory/query regressions without making the test suite unreasonably slow.

Verify that report generation does not introduce N+1 queries where the repository's test tooling can detect this.

### Regression tests
Existing synchronous reporting/export behavior from Phases 2.39–2.41 must continue to work.

## 17. Performance acceptance criteria

The implementation must demonstrate:

- HTTP request remains bounded for asynchronous exports.
- Worker processes large reports without unnecessary full-dataset materialization.
- No obvious N+1 query introduced.
- Duplicate messages do not duplicate completed business artifacts uncontrollably.
- Artifact download does not re-run the expensive report query.
- Dashboard/report read paths remain unaffected by background export load as far as the architecture permits.

Do not invent numeric performance targets without repository evidence or explicit requirements.

## 18. Configuration / deployment

Document all new configuration required for:

- Messenger transport/worker
- storage
- retention
- report-job limits
- environment-specific behavior

Reuse the project's current deployment model.

If a worker command already exists, extend it rather than adding redundant worker infrastructure.

If no worker infrastructure exists, implement the smallest Symfony-compatible path and document how it is intended to run in development/test/production.

Do not claim production readiness for infrastructure that cannot be verified in the repository.

## 19. Explicit non-goals

Do NOT:

- redesign Phase 2.39 reporting queries
- redesign Phase 2.40 dashboard KPIs
- replace Phase 2.41 exporters unnecessarily
- alter Order/Payment/Stock/Customer business rules
- alter checkout/payment flow
- alter bank webhook handling
- alter PaymentReference matching/reconciliation
- add fake progress UI
- add client-side authorization
- introduce a second queue framework
- introduce a second reporting engine
- silently change existing synchronous export semantics for small reports

## 20. FROZEN AREA

The following is explicitly FROZEN and must not be modified as part of Phase 2.42 except for strictly necessary compile/configuration integration that does not alter its business behavior:

- bank webhook/reconciliation
- PaymentReference creation/matching lifecycle
- bank notification parsing/bridge behavior
- payment confirmation business rules

If an apparent dependency requires changing a frozen component, stop and document the dependency instead of silently redesigning it.

## 21. Definition of Done

Phase 2.42 is complete only when:

1. Existing reporting/export architecture has been inspected and reused.
2. Large/slow export requests can be processed asynchronously through the project's queue architecture.
3. Persistent job lifecycle is explicit and safe.
4. Duplicate HTTP requests and duplicate worker delivery are hardened.
5. Large datasets are processed in a memory-conscious manner.
6. Artifacts are stored and downloaded securely.
7. Permissions are enforced server-side.
8. Failure/retry semantics are explicit and tested.
9. Optional cleanup/scheduling is implemented only where justified by the repository architecture.
10. Existing Phase 2.39–2.41 behavior remains intact.
11. Tests cover lifecycle, concurrency/idempotency, security, storage/export failure, HTTP, and regression paths.
12. Webhook/reconciliation/PaymentReference behavior remains untouched.
13. New configuration and worker/deployment requirements are documented.
14. No business logic is moved into Twig/Stimulus/JavaScript.

## Final implementation instruction

Do not merely describe the solution. Inspect the repository, implement the complete Phase 2.42 source changes, update configuration/migrations/templates/controllers/services/messages/handlers/tests as required by the actual architecture, run the relevant checks/tests, and report exactly what was changed and what could not be verified.
