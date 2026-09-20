# CRITICAL SOURCE IMPLEMENTATION PROMPT — Mobile POS UI/UX Hardening

## Objective

Use the supplied repository as the **only canonical base** and perform a UI/UX-focused hardening pass. The application already has working business flows; do **not** redesign or rewrite domain/application business logic. The priority is to make the existing Symfony POS feel like a mobile application on 360–430px screens while preserving all existing behavior.

## Mandatory workflow

1. Inspect the repository before editing.
2. Identify the actual Twig layout hierarchy, AssetMapper entrypoint, CSS, Stimulus controllers, and existing responsive rules.
3. Reuse the existing design tokens and components where possible; remove conflicting/duplicated CSS only when behavior is demonstrably preserved.
4. Implement and verify changes, not merely document them.
5. Run the available lint/build/tests that are relevant to the changed files. Do not claim tests that were not run.
6. Keep the source ZIP rooted directly at project root.

## Scope

### P0 — Mobile shell
- Optimize 360px, 375px, 390px and 430px widths.
- Prevent accidental horizontal page overflow.
- Keep primary navigation usable with touch and horizontal scrolling/snap where a full navigation list is unavoidable.
- Compact authenticated header/user area on mobile.
- Preserve all existing route links and permission checks.
- Respect iOS safe-area insets where fixed/sticky controls are used.
- Keep focus-visible states and keyboard accessibility.

### P0 — POS workspace
- Make product search, product results, cart, customer selection and payment controls comfortable for one-handed use.
- Keep the current backend/business flow exactly intact.
- On mobile, stack the POS workspace intentionally rather than merely shrinking the desktop two-column layout.
- Make cart total visually prominent.
- Keep primary payment/completion action large and easy to reach.
- Avoid sticky/fixed elements covering inputs or content.
- Preserve Cash and Bank Transfer UX and all existing Stimulus targets/actions.
- Preserve payment-success modal, QR modal, countdown, polling and reset behavior; change presentation only.
- Use 48px+ touch targets and 16–18px form controls.

### P1 — CRUD and operational screens
Apply consistent mobile presentation to:
- Products / categories
- Customers
- Orders / order detail
- Stock / stock detail
- Debt / settlement screens
- Payment account screens
- Statistics / dashboards
- Profile / settings

Where a desktop table exists, prefer a responsive card/list representation when practical. If the existing table must remain, provide a deliberate compact mobile layout and avoid forced page-wide horizontal scrolling.

### P1 — Forms and actions
- Large labels and controls.
- Correct `inputmode` where already semantically appropriate.
- Primary action visually dominant.
- Secondary/destructive actions clearly separated.
- Full-width actions on narrow screens where this improves reachability.
- Error/help text close to the relevant control.
- Do not alter validation or server-side rules.

### P1 — States
Make loading, empty, error, success, disabled and confirmation states readable and touch-friendly. Do not invent new business states.

### P2 — Visual consistency
Establish a small, coherent UI system using existing colors/tokens:
- typography hierarchy
- spacing rhythm
- button hierarchy
- card/list treatment
- status pills
- focus/active/disabled states
- consistent radius and borders
- restrained shadows
- reduced-motion support
- high-contrast support

Do not introduce flashy gradients, neon effects or unnecessary animation.

## Strict business invariants

Do NOT modify business semantics for:
- checkout
- Order state machine
- Payment / Cash / Bank Transfer
- PaymentReference
- webhook/reconciliation
- stock adjustment / StockMovement
- debt / settlement
- idempotency
- concurrency/locking
- permissions/Voters
- AuditLog
- ExportJob/reporting
- Android notification bridge

Do not move business logic into Twig or client-side JavaScript.

Stimulus changes are allowed only for presentation/interaction concerns such as focus, responsive presentation, modal accessibility, keyboard handling or purely visual state. Existing targets/actions must remain compatible.

## Acceptance criteria

- No page-wide horizontal overflow at 360px.
- Main interactive controls are at least approximately 48px high/tappable.
- Body text remains readable without zooming.
- Inputs are at least 17px where appropriate to avoid mobile browser zoom behavior.
- POS primary actions remain visible/reachable while using the keyboard and while scrolling.
- No modal exceeds the viewport or traps the user behind an inaccessible backdrop.
- Navigation remains usable on touch devices.
- Desktop layouts remain usable at >= 768px.
- No route/permission/business-flow regressions.
- Existing translations/terminology and server-side authorization remain intact.

## Verification

At minimum inspect/render representative screens at:
- 360x800
- 375x812
- 390x844
- 430x932
- desktop >= 1024px

Verify at least Login, Home, POS, Payment, Order list/detail, Product, Customer, Stock, Debt and Statistics where those routes exist.

Use browser/device tooling if available; otherwise use source-level inspection plus build/tests and document any visual verification limitation.

## Deliverable

Return the modified project as a clean ZIP rooted directly at the Symfony project root. Exclude runtime/cache/build artifacts, database backups, generated storage, `.git`, `vendor`, and environment-local secrets unless explicitly required.
