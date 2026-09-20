# PHASE UI/UX 6 — Order History + Order Detail + Customer & Debt Workflow UX

## Scope

Mobile-first UX hardening for:

- Order history/search/filter/pagination
- Order detail information hierarchy
- Customer list/detail/form
- Debt list/detail/payment
- Lifecycle action presentation
- Loading/error/empty-state messaging
- EN/VI translation coverage
- Accessibility and touch-target consistency

## Preserved behavior

The implementation is presentation/interaction focused. It does not intentionally change:

- Checkout business rules
- Payment semantics
- Transaction boundaries
- Idempotency behavior
- Concurrency/locking
- Debt domain calculations
- Order lifecycle authorization
- CSRF enforcement
- Voter/permission enforcement
- Bank webhook semantics

Order lifecycle and debt payment Stimulus controllers retain their existing idempotency and request-ID headers. UI messages that were hard-coded in those controllers are now supplied by translated data attributes.

## Changed files

- `templates/order/index.html.twig`
- `templates/order/show.html.twig`
- `templates/customer/index.html.twig`
- `templates/customer/show.html.twig`
- `templates/customer/form.html.twig`
- `templates/debt/index.html.twig`
- `templates/debt/show.html.twig`
- `assets/controllers/order_lifecycle_controller.js`
- `assets/controllers/debt_payment_controller.js`
- `translations/messages.en.yaml`
- `translations/messages.vi.yaml`
- `tests/Frontend/OrderCustomerDebtUiContractTest.php`
- `PHASE-UIUX-6-IMPLEMENTATION.md`

## Verification performed in the release workspace

- PHP syntax check for the new frontend contract test: PASS
- JavaScript syntax checks for order lifecycle and debt payment controllers: PASS
- YAML parse validation for EN/VI translations: PASS
- Basic template delimiter/structure inspection: PASS

Full PHPUnit/Twig/container validation was not run in the extracted release workspace because Composer dependencies/vendor are not present there. The local repository remains the authoritative environment for the full test suite.
