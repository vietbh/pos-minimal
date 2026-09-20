# PHASE UI/UX 8 — Turbo Form Response Fix

## Scope

Ensure Turbo form submissions have an unambiguous HTTP contract:

- successful POST -> redirect
- validation/domain failure -> re-render the form with HTTP 422
- no failed POST is returned as a normal 200 form response

## Updated / verified flows

- Product create
- Product edit
- Product category create
- Product category edit

## Implementation status

The source baseline already contained the required 422 response handling for the four flows. Phase 8 locks that behavior with a frontend contract test rather than introducing unnecessary backend or Turbo infrastructure.

This preserves the existing Symfony/Turbo navigation model and keeps server-side validation/domain failures visible to Turbo as failed form submissions.

## Preserved

- CSRF protection
- authorization / Permission checks
- existing Application handlers
- transaction boundaries
- domain validation
- redirect-after-success behavior
- existing i18n and UI design system

## Verification

The added contract test verifies:

1. Product create/edit set HTTP 422 when POST processing falls back to the form.
2. Product category create/edit set HTTP 422 on failed POST re-render.
3. Successful paths retain redirect behavior.

Full PHPUnit/Twig/container validation must be run in the project environment with Composer dependencies installed.
