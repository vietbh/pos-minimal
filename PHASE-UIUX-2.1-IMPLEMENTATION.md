# PHASE UI/UX 2.1 — Login + Authentication UX

Implemented from the UI/UX 2.1 source implementation prompt.

## Included

- Mobile-first login UX using the existing global UI system.
- Symfony Security authentication flow preserved.
- Existing CSRF protection preserved.
- Existing remember-me behavior preserved.
- Accessible password visibility interaction through Stimulus.
- Safe generic authentication error presentation.
- Native form validation with duplicate-submit protection.
- Loading/submitting state.
- Translation keys for all new user-facing login strings.
- English translations as current default UI.
- Vietnamese translations prepared for future locale selection.
- Accessible labels, descriptions, focus states, and touch targets.
- Existing Application/Home redirect remains authoritative.
- Logout/session behavior remains backend-controlled.

## Changed files

- `templates/security/login.html.twig`
- `assets/controllers/login_controller.js`
- `assets/styles/app.css`
- `translations/messages.en.yaml`
- `translations/messages.vi.yaml`
- `PHASE-UIUX-2.1-IMPLEMENTATION.md`

## Scope guard

No Order, Payment, Stock, Debt, Money, transaction, idempotency, concurrency, webhook, or POS business rules were changed.
