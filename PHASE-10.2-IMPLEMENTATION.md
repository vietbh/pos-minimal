# PHASE 10.2 — Authenticated Shell + Navigation

## Scope
- Harden the shared authenticated shell and POS shell for mobile-first use.
- Keep authorization and route access server-side through Symfony/Voter checks.
- Keep Stimulus limited to presentation/navigation state.
- Preserve Domain/Application/transaction/payment/stock semantics.

## Implemented
- Added skip-to-main-content link to authenticated and POS layouts.
- Kept `main#main-content` keyboard-focusable.
- Hardened navigation controller with Escape handling, focus restoration, and a Tab loop.
- Kept outside-click close behavior presentation-only.
- Hardened navigation touch targets to at least 48px.
- Added small viewport navigation text overflow protection at <=380px.
- Preserved safe-area and reduced-motion support.
- Added a non-color-only active-page marker to mobile navigation.
- Updated browser theme-color to the Phase 10.1 primary token.
- Added frontend contract coverage for shell accessibility and navigation boundaries.

## Regression boundary
No Domain, Application, Repository, transaction, idempotency, payment, stock, security policy, or business workflow was changed.

## Validation
- PHP syntax check: passed for the new contract test.
- YAML parse: passed for EN/VI translation files.
- Full PHPUnit/Twig/YAML Symfony commands require the project's `vendor/` dependencies and should be run in the local repo.
