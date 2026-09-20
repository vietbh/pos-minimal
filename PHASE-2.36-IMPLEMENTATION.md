# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.36 — POS CUSTOMER MANAGEMENT UI & CRUD HARDENING

## 0. OBJECTIVE

Implement the Customer Management UI and CRUD flow using the existing Symfony domain architecture.

Scope:
- Customer list
- Server-side search
- Customer detail
- Create customer
- Edit customer
- Validation and duplicate handling
- Permission/Voter enforcement
- Mobile-first UI
- Tests and regression hardening

This phase must preserve existing Order, Payment, Checkout, Stock, webhook and reconciliation behavior.

---

## 1. ABSOLUTE SCOPE BOUNDARY

Treat these as FROZEN:

- Bank webhook
- Webhook reconciliation
- PaymentReference matching/lifecycle
- Bank notification ingestion
- Payment idempotency/concurrency
- Existing Order completion/payment business flow

Do not refactor or redesign them merely because Customer is referenced by Orders.

Customer CRUD must never rewrite historical Order data.

---

## 2. SOURCE-FIRST REQUIREMENT

Inspect the actual repository before coding.

At minimum inspect:

- Customer entity and repository
- Order ↔ Customer relation
- existing Customer controllers/templates/forms
- Product management patterns from Phase 2.35
- existing admin routes and layout
- Symfony Forms/DTO/validation conventions
- Voters/permissions
- pagination/search patterns
- translations
- money/date formatting conventions
- existing tests

Reuse existing architecture. Do not invent a parallel Customer model or permission system.

---

## 3. FUNCTIONAL SCOPE

Implement, according to what already exists in the repository:

1. Customer List
2. Search
3. Pagination
4. Customer Detail
5. Create Customer
6. Edit Customer
7. Validation
8. Duplicate/uniqueness handling where domain rules require it
9. Authorization
10. Mobile UI
11. Tests

Delete Customer only if the existing domain explicitly supports safe deletion. Otherwise do not introduce deletion in this phase.

---

## 4. CUSTOMER LIST

Use the project's existing admin/application route conventions.

The list should show useful persisted fields, where available:

- Name
- Phone
- Email
- Status/active state if the entity has one
- Created At
- Order count only if efficiently available from existing data/query design

Do not fabricate fields.

Use backend pagination.

Do not load the complete customer table and filter in Twig/PHP memory.

---

## 5. SEARCH

Implement server-side search using actual Customer fields.

Typical searchable fields, only when present:

- name
- phone
- email
- customer code/identifier

Use the existing repository/query conventions.

Do not perform the primary search in Stimulus or Twig.

Preserve search/filter parameters across pagination.

---

## 6. CUSTOMER DETAIL

Display a read-oriented customer summary using persisted data.

Where supported:

- Customer identity
- Contact information
- Status
- Created/updated timestamps
- Order history summary/list

If Order History is already implemented, link to or reuse it rather than duplicating a second Order query architecture.

Do not mutate Orders merely by viewing Customer Detail.

---

## 7. CREATE CUSTOMER

Implement customer creation using the project's existing Symfony Form/DTO/application-service conventions.

Validate all actual domain constraints.

At minimum inspect:

- required name fields
- phone format
- email format
- length constraints
- uniqueness constraints
- active/status semantics

Do not invent business validation rules unsupported by the current domain.

Successful creation should persist exactly one Customer.

Do not create an Order, Payment, PaymentReference, StockMovement, or Checkout session.

---

## 8. EDIT CUSTOMER

Edit must update only the Customer fields supported by the domain.

Do not allow Customer Edit to silently modify historical Orders.

Do not change:

- historical Order totals
- historical OrderItem prices
- Payment records
- PaymentReference records
- Stock records

If Orders reference the Customer entity, changing current customer contact information must follow the project's existing relational semantics; do not copy current Customer values backward into historical records unless that is already the domain model.

---

## 9. DUPLICATE HANDLING

Inspect actual database constraints before implementing duplicate logic.

If phone/email/customer code is unique, rely on the authoritative validation + database constraint pattern already used by the project.

Handle a duplicate race safely:

- do not rely only on frontend validation;
- do not expose SQL/Doctrine exceptions;
- return the project's normal validation/conflict message.

Do not add a unique constraint without confirming that the business domain requires it.

---

## 10. DELETE

Do not add destructive deletion merely for CRUD completeness.

If the Customer is referenced by Orders, determine the current Doctrine/database relationship and existing business rules first.

If deletion is unsafe or unsupported, omit it.

If the project already has an explicit archive/deactivate mechanism, use that instead of physical deletion.

---

## 11. PERMISSION

Reuse the existing Voter/permission architecture.

Enforce authorization server-side for:

- view customer list
- view customer detail
- create customer
- edit customer
- any existing supported mutation

Do not use raw role checks in controllers when the project already uses Voters.

Do not secure only the UI link; direct URL access must also be protected.

---

## 12. CONTROLLER RESPONSIBILITY

Controllers remain thin:

- read/normalize request
- authorize
- invoke repository/application service/form
- render/redirect

Do not put domain business rules or large Doctrine queries in Twig/controllers.

---

## 13. REPOSITORY / QUERY RESPONSIBILITY

Repository/query layer owns:

- search
- filters
- sorting
- pagination
- efficient customer/order-history queries

Avoid duplicate query implementations.

Avoid N+1 queries.

Do not eager-load every relation blindly.

---

## 14. ORDER HISTORY IN CUSTOMER DETAIL

If Customer Detail shows recent Orders:

- use the existing Order History query/service where possible;
- paginate if the list can become large;
- do not load every historical Order into memory;
- do not mutate Orders.

Opening Customer Detail must be read-only from the business perspective.

---

## 15. HISTORICAL DATA SAFETY

Critical invariant:

```text
Customer edit
    ≠
Historical Order edit
```

Never recalculate or rewrite historical Order data because a Customer was edited.

Do not change historical:

- Order customer snapshot fields, if such snapshots exist
- Order totals
- OrderItem prices
- Payment records
- PaymentReference records

unless the current domain explicitly defines those records as live references rather than snapshots. Inspect source first.

---

## 16. FORM UX

Use existing Symfony form patterns.

Mobile-first requirements:

- 360px+
- labels clearly associated with inputs
- input text around 16–18px
- touch targets around 48px
- clear validation errors
- obvious Save/Cancel actions
- no hover-only behavior

Do not put business validation in JavaScript.

JavaScript may enhance presentation only.

---

## 17. MOBILE LIST UX

Avoid a desktop-only wide table.

Prefer responsive cards or the project's existing responsive table pattern.

Example conceptual card:

```text
┌─────────────────────────────┐
│ Nguyen Van A                │
│ 0901234567                  │
│ customer@example.com        │
│                             │
│ VIEW     EDIT               │
└─────────────────────────────┘
```

Use actual project data and terminology.

---

## 18. CUSTOMER DETAIL UX

Organize into clear sections:

- Customer information
- Contact
- Status
- Order history, if available
- Actions allowed by permission

Do not make destructive actions visually dominant.

---

## 19. CREATE/EDIT SUCCESS FLOW

After successful save:

- persist exactly once;
- redirect using PRG where appropriate;
- show the project's standard success feedback;
- prevent browser refresh from resubmitting the form.

Do not rely on JavaScript-only submission for correctness.

---

## 20. DOUBLE SUBMIT PROTECTION

Protect create/edit forms from accidental double submission using the existing application pattern.

The backend must remain authoritative.

A repeated request must not create duplicate Customers when uniqueness/domain constraints should prevent it.

Do not invent a separate idempotency subsystem if the current form operation does not require one.

---

## 21. CUSTOMER SELECTOR IN POS

If the existing POS already supports selecting/searching Customers, inspect and reuse the Customer Management model.

Do not break existing POS customer selection.

Do not make Customer Management changes that alter checkout semantics without explicit evidence in the current source.

If a new Customer is created and the POS has an existing supported "select customer" flow, integrate only if the current architecture already provides a safe path.

Do not introduce an implicit checkout mutation from the admin Customer form.

---

## 22. TRANSLATIONS

Use the existing Symfony translation system and project terminology.

Do not scatter hardcoded UI strings if the application already uses translation keys.

Keep terminology consistent across:

- Customer
- Order
- POS
- Payment
- Status

---

## 23. ACCESSIBILITY

Forms must have:

- associated labels
- readable error messages
- keyboard accessibility
- visible focus states using existing UI conventions
- touch-friendly controls

Do not rely only on color to communicate validation state.

---

## 24. ERROR HANDLING

Do not expose:

- SQL errors
- Doctrine exceptions
- stack traces
- internal IDs unnecessarily

Use the project's existing validation/error handling.

Handle:

- invalid input
- duplicate/unique conflict
- unauthorized access
- not found

according to existing application conventions.

---

## 25. PERFORMANCE

Customer List must remain efficient for a large dataset.

Inspect query plans/indexes where relevant.

Do not add indexes blindly.

Only add a migration when the actual implemented search/sort/filter pattern justifies it and the existing schema lacks the necessary support.

---

## 26. TEST REQUIREMENTS

Add tests following the existing testing architecture.

At minimum cover:

### List
- list loads
- pagination works
- search works
- empty state works

### Detail
- valid Customer loads
- missing Customer returns 404
- unauthorized access is denied

### Create
- valid Customer is persisted
- invalid input is rejected
- duplicate unique value is handled safely
- exactly one Customer is created

### Edit
- valid update persists
- invalid update is rejected
- historical Orders remain unchanged

### Security
- permissions are enforced server-side
- direct URL access cannot bypass authorization

---

## 27. HISTORICAL ORDER REGRESSION TEST

Create a Customer and an Order associated with that Customer.

Record the Order's historical values.

Edit the Customer.

Verify the Order's business/historical values remain unchanged according to the domain model.

This is a critical regression test.

---

## 28. CREATE RACE / UNIQUE CONSTRAINT TEST

If a Customer field is uniquely constrained, test the authoritative database-backed conflict path where practical.

Two concurrent attempts using the same unique value must not result in two Customers.

Handle the conflict using the existing persistence/error architecture.

Do not weaken the database constraint to make the test pass.

---

## 29. ACCEPTANCE SCENARIO

The following must work:

```text
1. Login as authorized user
2. Open Customer Management
3. Search customer
4. Open Customer Detail
5. Inspect customer information
6. Inspect Order history if available
7. Create a Customer
8. Save successfully
9. Edit the Customer
10. Save successfully
11. Search for the updated Customer
12. Open detail again
13. Verify updated current information
14. Verify historical Orders were not rewritten
15. Logout / access as unauthorized role
16. Verify direct access is denied
```

---

## 30. WEBHOOK REGRESSION

Run the existing webhook/reconciliation tests.

They must remain GREEN.

Do not modify webhook code merely to accommodate Customer Management.

If a pre-existing failure exists, document it accurately rather than changing unrelated payment infrastructure.

---

## 31. IMPLEMENTATION ORDER

Follow this order:

1. Inspect repository
2. Inspect Customer domain and relationships
3. Inspect existing Product Management implementation from Phase 2.35
4. Inspect Order History from Phase 2.33
5. Inspect permissions/Voters
6. Identify existing Customer routes/forms
7. Implement/reuse repository queries
8. Implement Customer List
9. Implement search/pagination
10. Implement Customer Detail
11. Implement Create
12. Implement Edit
13. Implement validation/conflict handling
14. Implement authorization
15. Implement responsive UI
16. Add tests
17. Run PHPUnit
18. Run frontend/build/lint checks discovered from package.json/composer.json
19. Inspect git diff
20. Confirm webhook/reconciliation files are untouched

---

## 32. FILE CHANGE DISCIPLINE

Before finishing:

```bash
git status
git diff --stat
git diff
```

Confirm only intended Phase 2.36 files changed.

Especially verify these remain untouched:

- webhook
- reconciliation
- PaymentReference matching
- bank notification processing
- payment idempotency/concurrency
- checkout payment flow

---

## 33. DEFINITION OF DONE

```text
[ ] Customer List exists
[ ] Search works server-side
[ ] Pagination works server-side
[ ] Customer Detail exists
[ ] Create Customer works
[ ] Edit Customer works
[ ] Validation follows actual domain constraints
[ ] Duplicate/unique conflicts are handled safely
[ ] Authorization is enforced server-side
[ ] Direct unauthorized URL access is denied
[ ] 404 works for missing Customers
[ ] Customer changes do not rewrite historical Orders
[ ] Existing POS customer selection remains functional
[ ] Mobile 360px layout works
[ ] Forms are accessible
[ ] Double submission is safe
[ ] No unnecessary Customer deletion is introduced
[ ] No Payment/Order/Stock mutation is created by Customer CRUD
[ ] No obvious N+1 query exists
[ ] Existing webhook/reconciliation remains untouched
[ ] Existing webhook tests remain green
[ ] Full PHPUnit suite passes
[ ] Frontend/build checks pass
[ ] Git diff contains only intended Phase 2.36 changes
```

# FINAL RULE

> PHASE 2.36 is Customer Management, not payment/webhook work.

The invariant is:

```text
Customer CRUD
    ↓
Current Customer State
    ↓
NO historical Order rewrite
    ↓
NO Payment mutation
    ↓
NO Stock mutation
    ↓
NO Webhook mutation
```

Keep the existing payment and reconciliation infrastructure frozen.
