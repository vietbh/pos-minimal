# PHASE UI/UX 3 — Mobile POS UX Hardening + Real-Device Readiness

## Scope

This phase hardens the existing mobile POS interaction layer for real-device browser behavior without changing checkout, payment, authorization, persistence, or business rules.

## Changes

- Hardened `mobile_ux_controller.js` with visual viewport width/height/offset metrics.
- Added orientation state and keyboard-open presentation hint.
- Preserved focus-to-visible behavior for inputs, selects, textareas, and contenteditable controls.
- Added safe-area handling and landscape-specific mobile POS layout adjustments.
- Added coarse-pointer touch-target hardening for POS controls.
- Added mobile POS `enterkeyhint="done"` to numeric/tendered/payment/note inputs.
- Reused Symfony Translation for POS page title and Close action.
- Converted the POS workspace logout to the existing Symfony Security POST logout flow.
- Added regression contract assertions for viewport/orientation/touch readiness.

## Explicit non-goals

No business/payment logic, checkout endpoint, transaction behavior, idempotency, concurrency, authorization, or persistence rules were changed.

## Verification

Static verification should include:

```bash
node --check assets/controllers/mobile_ux_controller.js
php -l tests/Frontend/MobileUxContractTest.php
php bin/phpunit --filter MobileUxContractTest
php bin/console lint:twig
php bin/console lint:yaml
```
