# PHASE 2.45 — POS Data Retention / Archival & Operational Cleanup Hardening

## 0. Mission

Implement **PHASE 2.45 — POS Data Retention / Archival & Operational Cleanup Hardening** directly in the current repository.

The supplied repository is the **authoritative current base**. Do not assume that the previous phase ZIP is identical to this source. Inspect the actual code, migrations, entities, repositories, commands, configuration, tests, storage layout, Messenger setup, and existing cleanup/backup mechanisms before changing anything.

This phase is operational hardening only. The objective is to make retention, cleanup, archival, and operational housekeeping explicit, bounded, observable, safe to rerun, and resistant to accidental deletion of business history.

The implementation must preserve all existing business invariants and must not redesign unrelated domains.

---

## 1. Mandatory repository-first workflow

Before coding:

1. Inspect the repository tree and current architecture.
2. Inspect `composer.json` and installed Symfony/Doctrine/Messenger capabilities.
3. Inspect all existing cleanup/maintenance commands, especially:
   - `CleanupExpiredSessionsCommand`
   - `CleanupIdempotencyRecordsCommand`
   - `CleanupOrphanFilesCommand`
   - backup commands
   - any export-job cleanup/retention code
   - any alert/audit cleanup code
4. Inspect the actual persistence model for:
   - `AuditLog`
   - `UserSession`
   - idempotency records
   - `ExportJob` / generated artifacts if present
   - alerts/notifications if present
   - product images/files
   - PaymentReference
   - Payment
   - Order
   - StockMovement
   - debt/settlement records
   - any other operational tables.
5. Inspect migrations and indexes before introducing new ones.
6. Inspect existing Console command conventions and tests.
7. Inspect Messenger configuration and worker conventions.
8. Inspect backup/storage directories and environment configuration.
9. Run the relevant existing tests before modifying code when the environment permits it.
10. Build on existing abstractions instead of creating duplicate cleanup frameworks.

If a record/type mentioned in this prompt does not exist in the repository, do not invent it merely to satisfy the prompt. Document the gap and implement only what the current architecture supports safely.

---

## 2. Core architectural rule: business history is not disposable operational data

The following records are historical/business source-of-truth records and MUST NOT be deleted merely because they are old:

- Orders and order items.
- Payments/payment history.
- PaymentReference lifecycle/history.
- StockMovement ledger/history.
- Debt/settlement history.
- AuditLog when required for operational traceability/compliance by the current product design.

Do not introduce generic `DELETE old rows` behavior against business history.

If archival is required for a business-history table, it must first be proven safe by the repository's existing architecture and explicit product requirements. In the absence of such an established archival contract, the correct behavior is **retain**, not delete.

---

## 3. Classify data before adding retention rules

Create a clear classification in code/documentation/tests where appropriate:

### 3.1 Operational/ephemeral data

Examples may include:

- terminal idempotency records after their replay-safety window, if existing command semantics already define this.
- expired user sessions.
- temporary/export artifacts after their required download/retention period.
- stale transient files.
- failed/expired operational jobs when their historical record is no longer needed, if the current model explicitly supports cleanup.

These may be cleaned only under explicit, bounded policies.

### 3.2 Business history

Examples:

- Order.
- Payment.
- StockMovement.
- PaymentReference.
- Debt/settlement.
- business audit records where retention is required.

These are retained unless a separate, explicit archival contract exists.

### 3.3 Backups

Database/file backups are operational recovery artifacts, not business records. They may have their own retention policy, but cleanup MUST be conservative:

- never delete the only available backup set accidentally;
- retain at least the configured minimum count/time window;
- use bounded deletion;
- handle partially-written/corrupt-looking files conservatively;
- never delete a file that is currently being created if that can be detected.

---

## 4. Retention policy architecture

Do not scatter hard-coded retention values throughout commands.

Where the repository architecture permits it, centralize operational retention settings in a small configuration/value-object layer, for example:

- idempotency retention days;
- export artifact retention days;
- temporary file age;
- backup retention days and/or minimum backup count;
- session inactivity period;
- alert retention if an actual alert persistence model exists;
- other operational cleanup thresholds that already exist.

Use the project's existing Symfony configuration conventions.

Requirements:

- sensible defaults;
- environment/config override where appropriate;
- strict validation of positive/non-negative values;
- no unsafe zero-value behavior unless explicitly intended;
- test configuration parsing/validation where practical.

Do not expose secrets through configuration or command output.

---

## 5. Unified cleanup strategy without creating unnecessary abstraction

Review the existing cleanup commands first.

If several commands already implement the same bounded-batch pattern, extract only the minimal reusable infrastructure that materially improves correctness/maintainability.

Do NOT build an oversized generic “cleanup engine” merely for abstraction's sake.

Each cleanup operation must remain:

- independently runnable;
- bounded;
- idempotent;
- observable;
- safe to retry;
- safe under partial failure.

A cleanup run that fails halfway must be safely rerunnable without corrupting data.

---

## 6. Existing idempotency cleanup hardening

The repository currently contains `CleanupIdempotencyRecordsCommand`.

Review and harden it rather than blindly replacing it.

Requirements:

- only terminal states eligible for cleanup may be deleted;
- active/in-progress/replay-relevant records must remain;
- retention must preserve the application's intended replay/idempotency safety window;
- deletion must be bounded;
- repeated execution must be safe;
- concurrent execution must not create logical corruption;
- indexes/query shape must remain practical for large tables;
- output must report what was actually removed.

Do not shorten retention merely because a default value is convenient.

---

## 7. Existing session cleanup hardening

The repository currently contains `CleanupExpiredSessionsCommand`.

Review whether its current behavior is correct for the actual session model.

Preserve:

- session lifecycle semantics;
- authentication/security invariants;
- bounded processing;
- safe reruns.

Do not delete users or security history as a side effect of session cleanup.

If session cleanup should remain a state transition (`ACTIVE -> EXPIRED`) rather than physical deletion, preserve that semantic.

---

## 8. Export artifact retention

Phase 2.42 introduced/backgrounded reporting/export processing only if supported by the actual repository. Inspect the current implementation.

If persistent export artifacts/jobs exist:

- define an explicit retention boundary;
- do not delete an artifact while it is still `QUEUED` or `PROCESSING`;
- do not delete a fresh `COMPLETED` artifact before its configured download window;
- failed jobs/artifacts must follow the actual lifecycle semantics;
- cleanup must remove filesystem artifacts safely;
- database/file cleanup ordering must avoid dangling downloadable records where possible;
- never expose arbitrary filesystem paths;
- cleanup must be idempotent.

If an `ExportJob` does not exist in the current repository, do not invent one in this phase merely for completeness.

---

## 9. Product image / file cleanup

The repository currently contains `CleanupOrphanFilesCommand` and product image storage.

Harden it against accidental deletion.

Requirements:

- only files proven to be orphaned may be deleted;
- age threshold must be respected;
- path normalization must remain safe;
- path traversal must be impossible;
- symlinks and unexpected filesystem entries must be handled conservatively;
- deletion must be bounded;
- one deletion failure must not silently report complete success;
- database references must be authoritative;
- newly-created files must receive a grace period before cleanup.

Do not delete referenced product images merely because the file timestamp is old.

Do not delete a file solely because a transient database query failed.

---

## 10. Backup retention and safety

Inspect the actual backup implementation and storage layout.

If backup retention is currently absent or incomplete, implement only the minimum safe hardening required by this phase.

Requirements:

- configurable retention;
- minimum backup safety floor where practical;
- bounded deletion;
- never delete all backups because a retention calculation is wrong;
- preserve recent successful backups;
- do not treat a zero-byte/partial backup as a valid successful backup;
- avoid deleting files currently being written;
- clear console output showing candidates/deletions without leaking credentials.

The cleanup command MUST NOT call `mysqldump` or create a backup implicitly unless the existing architecture explicitly requires it.

Prefer a separate backup-retention command from backup creation.

---

## 11. AuditLog retention — conservative by default

The current repository contains `App\Domain\Audit\AuditLog`.

Do not delete AuditLog merely because it is old.

Before implementing any AuditLog retention:

1. inspect how AuditLog is used by the application;
2. inspect whether it is required for operational traceability/security;
3. inspect Phase 2.44 timeline requirements;
4. determine whether the current product has an explicit retention requirement.

If no explicit safe retention requirement exists, retain AuditLog and document that no destructive retention policy is applied.

If a retention policy is explicitly supported by the repository, implement it as bounded archival/cleanup with clear semantics and tests. Never silently destroy the audit trail.

---

## 12. Alerts / operational notifications

If Phase 2.43 created a persistent alert model, inspect it.

Only clean up alerts when:

- the lifecycle explicitly permits retention cleanup;
- unread/active alerts are protected;
- retention applies to resolved/acknowledged historical alerts only;
- cleanup cannot break unread counts or operational dashboards;
- deletion is bounded and idempotent.

If alerts are not persisted in the current repository, do not create a new alert persistence subsystem solely for this phase.

---

## 13. Archival vs deletion

Use three distinct outcomes:

1. **Retain** — default for business history.
2. **Archive** — move/compact data only when the repository has an explicit archival contract.
3. **Delete** — only ephemeral/operational data with a documented retention rule.

Never use “archive” as a euphemism for deleting records without a recoverable archival representation.

If archival is introduced, define:

- source of truth;
- archive location/schema;
- integrity expectations;
- idempotency;
- recoverability;
- retention of archived data;
- operational failure behavior;
- authorization for restore/access if applicable.

Do not implement a complex archive store unless the current repository genuinely requires it.

---

## 14. Command design

Follow existing Symfony Console conventions.

Commands should support bounded execution and safe operational use.

Where appropriate, support:

- dry-run;
- batch-size/max-items;
- retention override for controlled operational execution;
- deterministic cutoff calculation;
- clear summary counts.

A destructive cleanup command should default to safe behavior and must make it difficult to accidentally pass an invalid retention value.

Do not add dangerous “delete everything” flags.

Use exit codes consistently:

- success when cleanup completes;
- invalid arguments/configuration as `Command::INVALID`;
- operational failure as `Command::FAILURE`.

---

## 15. Scheduling / cron integration

Inspect the repository's existing deployment/cron/worker conventions before adding schedules.

If a scheduler/cron mechanism already exists, integrate cleanup commands there.

Do not introduce a second scheduler framework.

If no scheduler exists, implement commands first and document the intended invocation rather than inventing a new scheduler subsystem.

Cleanup commands should be safe if invoked more than once due to cron overlap or operator retries.

---

## 16. Concurrency and overlapping cleanup runs

Assume two workers/operators may invoke the same cleanup simultaneously.

The implementation must avoid logical corruption such as:

- deleting the same row twice and treating it as an error;
- deleting a record that became ineligible during processing;
- deleting a newly-created/referenced file;
- reporting misleading totals because of overlapping runs.

Use database constraints/query predicates/transactions/locking only where actually necessary.

Do not hold a giant transaction over an entire cleanup job.

Prefer short bounded units of work.

---

## 17. Query/index/performance hardening

Retention operations will eventually run against large datasets.

Review query predicates and existing indexes.

For database cleanup:

- use indexed cutoff/status predicates where possible;
- process bounded batches;
- avoid loading huge entity collections into memory;
- use DBAL/DQL appropriately for bulk operational deletion where safe;
- preserve lifecycle semantics when bulk DQL would bypass domain behavior.

For filesystem cleanup:

- avoid loading an unbounded list of every known storage key into memory if the repository can safely do better;
- if the existing implementation is acceptable for current scale, do not rewrite it unnecessarily;
- protect against symlink/path traversal edge cases.

Add indexes only when justified by actual query patterns and migrations.

---

## 18. Observability

Cleanup must be operationally visible without producing noisy or sensitive logs.

At minimum expose:

- command/job name;
- cutoff/retention used;
- candidate count when practical;
- deleted/archived count;
- skipped count where meaningful;
- failure count;
- duration if existing observability conventions support it.

Never log:

- passwords;
- access tokens;
- OTPs;
- bank credentials;
- raw bank notifications;
- sensitive personal data unnecessarily.

If structured logging already exists, reuse it.

Do not build a second observability system.

---

## 19. Failure semantics

Cleanup is maintenance, not a reason to compromise core business data.

Examples:

- one file deletion fails → report failure; do not pretend the entire run succeeded;
- one batch fails → stop or continue according to the operation's safety semantics, but report accurately;
- database connectivity failure → fail safely without filesystem deletion based on stale/incomplete references;
- configuration invalid → do not execute destructive work;
- backup directory unavailable → do not attempt deletion elsewhere;
- partial archive → never mark it successfully archived.

A failed cleanup must be safely retryable.

---

## 20. Security

Apply existing permissions to operational/admin commands and UI where applicable.

For web-triggered maintenance, if such a surface exists:

- enforce Voter/permission server-side;
- require CSRF for state-changing actions;
- never trust client-supplied retention values without validation;
- never allow arbitrary filesystem paths;
- never allow arbitrary table/entity selection from the client.

Prefer CLI-only maintenance when there is no existing admin UI requirement.

---

## 21. Testing requirements

### 21.1 Command tests

Test:

- valid retention values;
- invalid/zero/negative values;
- batch limits;
- dry-run if implemented;
- correct deletion counts;
- no deletion of ineligible records;
- rerunning cleanup;
- partial failure behavior.

### 21.2 Idempotency cleanup tests

Test:

- terminal records eligible;
- active records preserved;
- cutoff boundary;
- repeated invocation;
- bounded batches;
- concurrent invocation where practical.

### 21.3 Session cleanup tests

Test:

- inactive sessions expire;
- recent active sessions remain active;
- already-expired sessions are not corrupted;
- rerun is safe.

### 21.4 File cleanup tests

Test:

- referenced file preserved;
- orphan old file deleted;
- young orphan preserved;
- path normalization;
- symlink/unexpected-entry safety where practical;
- deletion failure is reported;
- rerun is safe.

### 21.5 Backup retention tests

Test:

- recent backups preserved;
- retention cutoff;
- minimum safety floor;
- partial/invalid backup handling;
- rerun is safe.

### 21.6 Audit/alert retention tests

If cleanup exists for these models, test the exact supported lifecycle and ensure protected records remain.

### 21.7 Integration / Doctrine

Test real schema/index behavior where practical.

### 21.8 Concurrency

Where the current test infrastructure supports it, exercise overlapping cleanup processes and verify no logical corruption.

### 21.9 Regression

Run the relevant existing suite, especially:

- stock adjustment/concurrency;
- payment/order flows;
- idempotency;
- export/reporting;
- audit/timeline;
- security/Voter;
- HTTP acceptance.

Do not weaken existing tests to make Phase 2.45 pass.

---

## 22. Explicit non-goals / forbidden changes

Do NOT:

- delete Orders because they are old;
- delete Payments because they are old;
- delete PaymentReference history;
- delete StockMovement history;
- delete Debt/settlement history;
- rewrite checkout/payment business logic;
- modify bank webhook reconciliation;
- change PaymentReference matching rules;
- modify Android bank-notification ingestion;
- introduce a new payment provider;
- replace AuditLog with another history mechanism;
- introduce a second queue/worker framework;
- introduce a second scheduler framework;
- create a generic cleanup framework with no concrete need;
- add a web “delete data” endpoint for convenience;
- silently change existing retention semantics without documenting the change;
- log sensitive data.

### FROZEN AREAS

The following are explicitly frozen and must not be changed in this phase:

- bank webhook/reconciliation;
- PaymentReference creation/matching lifecycle;
- Android notification bridge;
- checkout/payment atomicity;
- stock adjustment business semantics;
- existing order completion semantics.

---

## 23. Definition of Done

Phase 2.45 is complete only when:

1. The current repository has been inspected before implementation.
2. Existing cleanup commands have been reviewed and hardened rather than duplicated unnecessarily.
3. Operational/ephemeral data is clearly separated from business history.
4. No business-history table is destructively cleaned without an explicit archival/retention contract.
5. Retention values are validated and centralized where appropriate.
6. Cleanup operations are bounded and safely rerunnable.
7. File cleanup cannot delete referenced/new/unsafe files.
8. Backup cleanup cannot accidentally remove the entire recovery set.
9. Concurrent cleanup execution is safe.
10. Query/index performance is acceptable for growing datasets.
11. Observability reports accurate results without sensitive data.
12. Relevant command, integration, security, concurrency, and regression tests pass.
13. Existing business flows remain unchanged.
14. Webhook/reconciliation/PaymentReference and Android notification pipeline remain untouched.
15. The final implementation documents any retention areas intentionally left as **retain-only** because the repository lacks a safe archival contract.

The final implementation must favor **data safety over aggressive cleanup**. When repository evidence is ambiguous, retain the data and document the ambiguity rather than deleting it.
