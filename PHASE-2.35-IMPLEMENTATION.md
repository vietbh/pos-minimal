# CRITICAL SOURCE IMPLEMENTATION PROMPT

# PHASE 2.35 — POS PRODUCT MANAGEMENT UI & CRUD HARDENING

## 0. OBJECTIVE
Implement the Product Management experience for the POS/admin application using the existing domain, persistence, security, UI and file/image infrastructure.

Scope:
- Product list
- Search/filter/sort/pagination
- Product create/edit
- Product active/inactive state
- SKU/name/price/stock/category fields supported by the current domain
- Product image upload/display using the existing upload/image-processing architecture
- Validation and permission enforcement
- Mobile-first UI
- Tests

This phase must not redesign checkout, payment, webhook, reconciliation, Order history, or refund/cancel flows.

## 1. FROZEN SYSTEMS
Do not modify unless an existing contract is genuinely broken:
- bank webhook
- webhook reconciliation
- PaymentReference matching/lifecycle
- payment idempotency/concurrency
- bank notification ingestion
- checkout/payment business flow
- completed Order historical data

## 2. SOURCE-FIRST
Inspect the actual repository before coding. Inspect at minimum:
- Product entity/repository/services
- Category entity/repository
- Product validation
- stock model and StockMovement
- existing admin/product routes/controllers/templates
- existing image upload/file processing
- security voters/permissions
- forms/components
- money/decimal handling
- pagination/search patterns
- translations
- existing tests
- current docs for previous product/upload phases

Reuse existing architecture. Do not create duplicate Product or Category models/services.

## 3. DOMAIN RULES
Product is a real persisted domain entity. Category is a real persisted entity.
Do not use fake Twig-only categories.
Do not put business rules in Twig/Stimulus.
Do not modify historical OrderItem prices when Product price changes.
Product price changes affect future sales only.

Do not delete a Product if the existing domain/history rules prohibit destructive deletion. Prefer the existing active/inactive mechanism where supported.
Do not invent stock mutation semantics. Product CRUD must not silently adjust stock unless the existing Product business operation explicitly requires it.

## 4. PRODUCT LIST
Implement/reuse an appropriate admin route, following existing routing conventions.
Display where supported:
- name
- SKU
- category
- selling price
- stock/current availability
- active/inactive status
- image
- actions

Default to active products if that is the existing product-management convention.
Support:
- search by name/SKU
- category filter
- active/inactive filter if supported
- deterministic sorting
- backend pagination

Never load the complete product table and filter in Twig/PHP arrays.

## 5. SEARCH/FILTER/PAGINATION
Queries belong in repository/query/application layers.
Preserve filters/search through pagination.
Avoid N+1 category/stock queries.
Use existing pagination infrastructure if available.
Use existing query parameter conventions instead of inventing duplicates.

## 6. CREATE PRODUCT
Create only through the application's existing application/domain service/form architecture.
Validate:
- required name
- SKU uniqueness according to existing schema/business rule
- valid price
- valid category when required
- active state
- image constraints if image is supplied

Do not trust client-side validation as the authoritative validation.

## 7. EDIT PRODUCT
Editing must preserve existing entity identity and historical references.
Do not recreate the Product entity merely to change fields.
Do not mutate historical OrderItem snapshots.
If SKU uniqueness is enforced, exclude the current Product from its uniqueness check.

## 8. PRICE/MONEY
Reuse the project's existing money representation and formatter.
Do not use floating point for persisted monetary values if the current domain uses integer/decimal money.
Do not introduce a second price calculation implementation.

## 9. CATEGORY
Use the existing Category entity/repository.
Do not hardcode categories in Twig/JavaScript.
If category creation is outside this phase, provide only selection of persisted categories.
Do not silently create categories from arbitrary client strings.

## 10. ACTIVE/INACTIVE
If the Product domain supports active/inactive:
- expose the existing state
- allow authorized users to change it through the existing application path
- prevent inactive products from being treated as sellable if the current checkout rules already enforce that

Do not implement a new product availability state.

## 11. STOCK
Product Management is not Stock Management.
Display stock only through the existing stock/read model where available.
Do not create StockMovement merely by editing a product.
Do not change quantities from Product CRUD.
Stock adjustment belongs to the dedicated stock phase.

## 12. IMAGE UPLOAD
Reuse the existing file-upload/image-processing infrastructure.
Inspect the actual implementation before changing anything.
Requirements where supported by current architecture:
- validate MIME/type and size server-side
- process image safely
- preserve existing storage abstraction
- avoid storing arbitrary executable files
- show current image on edit
- replace image through the existing upload service
- handle missing/invalid image safely
- do not store raw upload paths in business entities if the current architecture uses a media/file abstraction

Do not duplicate image-processing logic.

## 13. IMAGE UI
Product list should use a small optimized preview where available.
Product form should show current image and allow replacement according to existing rules.
Do not make image upload mandatory unless the existing domain requires it.

## 14. SECURITY
Every create/edit/active-state mutation must be authorized server-side.
Reuse existing Voters/permission matrix.
Do not hardcode ROLE_ADMIN checks when a Voter/permission exists.
Direct URL/API access must be protected even if UI buttons are hidden.

## 15. CONTROLLER RESPONSIBILITY
Controllers should remain thin:
- authorize
- receive/normalize request
- delegate to existing application/service/form layer
- render/redirect

Do not place Doctrine query construction, business rules, stock mutation, or image processing directly in controllers.

## 16. FORMS / VALIDATION
Reuse Symfony Forms or the project's established form mechanism.
Show field-level validation errors clearly.
Do not rely on JavaScript-only validation.
Handle duplicate SKU and invalid category gracefully.

## 17. MOBILE-FIRST UI
Target 360px+.
Use large touch targets (~48px).
Keep forms readable and vertically structured.
Avoid wide desktop-only tables; use responsive cards/table transformation consistent with existing UI.
Primary actions must be obvious.
Do not introduce flashy gradients/neon or unrelated design changes.

## 18. READ/WRITE BOUNDARY
List/detail are read-only.
Create/edit/toggle-active are explicit business mutations.
Opening a Product page must never change stock, orders, payments, or categories.

## 19. CONCURRENCY / INTEGRITY
Respect existing unique constraints and persistence rules.
SKU uniqueness must be enforced by the database where the current schema supports it, with a user-friendly application-level error for conflicts.
Do not rely only on a pre-check query for uniqueness.
Do not weaken optimistic/pessimistic locking already used by the project.

## 20. TESTS
Add/update tests for:
- product list loads
- search by name
- search by SKU
- category filter
- active/inactive filter
- pagination
- create valid product
- required-field validation
- invalid price
- duplicate SKU
- edit product
- SKU unchanged on self-edit
- category selection
- unauthorized create/edit
- inactive product state
- image validation
- image replacement if existing infrastructure supports it
- product CRUD does not create StockMovement
- historical OrderItem prices remain unchanged

## 21. ACCEPTANCE SCENARIO
1. Authorized user opens Product Management.
2. Active products are listed using backend pagination.
3. Search by name/SKU works.
4. Category filter works using persisted categories.
5. User opens a Product.
6. User edits name/price/category/status.
7. Validation works server-side.
8. Product is persisted.
9. Existing Order history remains unchanged.
10. No stock movement is created by ordinary Product editing.
11. Image upload/replacement works through existing infrastructure.
12. Unauthorized user cannot perform mutations by direct URL.
13. Mobile layout remains usable at 360px.

## 22. REGRESSION
Run the full existing test suite and frontend/build/lint commands discovered from composer.json/package.json.
Do not invent commands.
Confirm webhook/reconciliation tests remain green.

## 23. FILE DISCIPLINE
Before finishing:
- git status
- git diff --stat
- git diff

Verify only intended Phase 2.35 files changed.
Do not modify webhook/reconciliation/payment-reference code.

## 24. DEFINITION OF DONE
[ ] Product list implemented
[ ] Backend search/filter/pagination implemented
[ ] Product create implemented
[ ] Product edit implemented
[ ] Existing Category entity reused
[ ] Existing permissions/Voters enforced
[ ] SKU uniqueness protected
[ ] Money handling follows existing domain
[ ] Active/inactive follows existing domain
[ ] Product CRUD does not mutate stock
[ ] Existing image upload/processing reused
[ ] Image validation works
[ ] Mobile UI works at 360px+
[ ] Historical Orders remain unchanged
[ ] Tests added/updated
[ ] Full test suite passes
[ ] Frontend/build checks pass
[ ] Webhook/reconciliation remains untouched
[ ] Git diff contains only intended changes

## FINAL RULE
PHASE 2.35 is Product Management, not Stock Management and not Payment Management.

Keep these boundaries strict:

Product CRUD → Product
Product category → Category
Product image → existing media/upload infrastructure
Stock quantity → Stock domain/Stock Management phase
Payment → Payment domain
Webhook → FROZEN
Reconciliation → FROZEN
Historical Order → immutable historical record
