# PHASE 2.18.2 — Global UI Foundation + Forms + Dark/Light Theme System

## Objective

Close the presentation layer foundation for the entire Mobile POS. Build one UI language for Application Shell, Dashboard, POS, Products, Customers, Orders, Payments, Stock, Import, Statistics, Users, Guild, Settings and Error pages.

Do not create page-specific visual systems when an existing global primitive is suitable.

## Source-first

Inspect:
- `assets/styles/app.css`
- `assets/styles/phase-uiux-7.css`
- legacy `assets/app.css`
- `templates/base.html.twig`
- authenticated/POS layouts
- all Twig templates
- Stimulus/controllers
- existing Appearance/User Preferences
- Font Size
- Density
- High Contrast
- Reduced Motion

Identify the canonical stylesheet and migrate to it. Prefer the existing `assets/styles/app.css` foundation unless repository inspection proves another structure is required.

Do not create `app-v2.css`, `theme-v2.css`, `forms-v2.css`, or another parallel design system.

## Design tokens

Use semantic tokens for:
- background/surface/raised surface
- primary text/muted text
- borders
- primary/success/warning/danger/info/focus
- radius
- spacing
- control height/touch target

If existing tokens such as `--color-primary` and `--ux-primary` represent the same semantic, consolidate/migrate rather than keeping duplicate token systems.

## Theme system

Support first-class:
- Light
- Dark
- System

Reuse the existing `data-appearance` and User Preference infrastructure. System follows `prefers-color-scheme`.

Do not duplicate the entire stylesheet for dark mode. Use semantic tokens with light/dark values.

Prevent theme flash where the current architecture supports early appearance resolution.

All UI primitives must work in Light/Dark/System:
- buttons
- cards
- inputs
- select
- textarea
- checkbox
- radio
- tables
- badge/status
- alerts/flash
- modal/dialog
- tabs
- navigation
- empty state
- loading state

Also preserve:
- font-size preference
- density
- high contrast
- reduced motion

## Buttons

Canonical semantic variants:
- primary
- secondary
- danger
- ghost

States:
- default
- hover
- active
- focus-visible
- disabled
- loading

Target touch size around 48px. Never remove focus without a visible replacement.

## Form controls

Normalize:
- text/email/password/number/tel/search/date/datetime-local
- select
- textarea

States:
- default
- hover
- focus
- invalid
- disabled
- readonly

Default controls should have approximately 48px minimum height; textarea can be taller.

Do not alter server-side validation, CSRF, authorization, domain validation or 422 response contracts.

## Checkbox / radio

Use native semantics whenever possible.

Checkbox:
- unchecked
- checked
- indeterminate
- disabled
- focus

Radio:
- unchecked
- checked
- disabled
- focus

Clickable area should be about 48px. Preserve `name`, `value`, `checked`, `required`, `disabled` semantics. Use `fieldset`/`legend` for grouped radio controls where appropriate, especially payment methods.

## Form foundation

Standardize:

```text
Form
 ├─ Section
 │   ├─ Label
 │   ├─ Control
 │   ├─ Help
 │   └─ Error
 └─ Actions
```

Labels are visible and associated with controls. Placeholder is supplementary, never a label replacement.

Required/optional semantics must be clear. Errors must be readable, associated with the field, and use `aria-invalid` where appropriate. Do not expose technical exceptions.

Mobile-first forms are one column; desktop may use two columns when semantically appropriate. Financial inputs (amount, price, discount, quantity, payment amount) must remain highly readable without changing business validation.

## Cards

Unify existing:
- `.card`
- `.admin-card`
- `.admin-section`
- `.pos-card`
- `.form-card`
- `.statistics-card`

Do not blindly rename everything. Establish one visual foundation for background, border, radius, shadow, padding, heading and spacing.

Variants:
- default
- interactive
- selected
- danger

## Tables / status / feedback

Tables must have consistent header/body/row/hover/selected/numeric/empty/responsive styles. Numeric/money columns may use tabular numerals. If mobile needs scrolling, only the table wrapper scrolls; the page must not overflow horizontally.

Status/badges:
- neutral
- info
- success
- warning
- danger

Status must contain text; color alone is not business meaning.

Alerts/flash:
- success
- info
- warning
- error

Reuse the existing flash architecture.

Empty state contains title, description and optional action. Loading supports button/section/page states and respects reduced motion.

## Modal / tabs / navigation

Reuse existing Stimulus/Turbo behavior. Do not introduce another modal framework.

Modal must support keyboard/focus/Escape/mobile sizing/scroll/close action.

Tabs must share one visual system for default/active/hover/focus/disabled/mobile overflow. Dashboard 2.18.1 must reuse this system.

Navigation visual states may be standardized, but permission/navigation architecture must not change.

## Typography and spacing

Suggested hierarchy:
- page title: 24–28px
- section title: 18–20px
- primary information: 18–20px semibold/bold
- amount: 22–30px bold
- body: 14–16px
- secondary: 14–15px
- technical metadata: 12–13px

Use a consistent spacing scale, e.g. 4/8/12/16/20/24/32, unless existing tokens already provide a better compatible scale.

## Responsive and accessibility

Validate at 320, 360, 375, 390, 430, 768, 1024+.

Rule:

> Resize → Wrap → Reflow → Never Hide Business Information.

Business names, money amounts, statuses and other important information must not be hidden through ellipsis/overflow tricks.

All primitives must be keyboard accessible, have visible focus, semantic HTML, readable labels, adequate contrast and touch-friendly targets.

Check Light, Dark and High Contrast independently.

## Migration and CSS cleanup

Search all `templates/**/*.twig`, CSS and Stimulus usage. Migrate buttons, links-as-buttons, forms, inputs, selects, textareas, checkboxes, radios, cards, tables, tabs, alerts, badges, modals, empty/loading states across all business modules.

Cleanup order:

```text
Inspect → Migrate → Test → Remove unused
```

Do not delete legacy classes blindly. Keep business-specific classes when they are genuinely business-specific.

## JavaScript / Stimulus

Stimulus handles behavior: tabs, modal, dynamic form rows, loading and interaction.

CSS handles visual state: theme, focus, hover, disabled, checked, invalid.

Do not use client-side JavaScript to calculate business data or replace native form semantics.

## Business preservation

No changes to:
- Checkout
- Payment
- Bank Transfer
- Order state machine
- Stock
- Import
- Idempotency
- Concurrency
- Security
- Permissions
- Transactions
- Database schema unless absolutely required by an already-existing preference contract

This is a presentation/UI phase.

## Testing

Representative pages:
- Login
- Home/Dashboard
- Profile
- Settings
- Products/Product Form
- Customers/Customer Form
- Orders
- Payments
- Stock
- Statistics
- Import
- Users
- POS
- Error pages

Verify render, controls, forms, validation presentation, authorization behavior, themes, responsive behavior and accessibility.

Theme matrix:
- Light
- Dark
- System

Check for white-on-white, black-on-black, invisible borders, low-contrast inputs, invisible checkboxes and broken disabled states.

Do not build a large visual-regression framework if the project does not already have one.

## Acceptance

One UI foundation, one form language, one theme system, one responsive system and one accessibility model.

Future phases must reuse this foundation and must not introduce parallel button/input/card/checkbox/theme styles unless the component is genuinely business-specific.
