# PHASE 10.9 — E2E + Mobile Acceptance Hardening

## Scope

Phase 10.9 hardens the existing Symfony/Twig/Stimulus UI for the documented mobile acceptance targets of **360px** and **390px**, while keeping the backend authoritative for all business behavior.

## Implemented

- Explicit responsive acceptance rules for 360px and 390px viewports.
- Prevented common flex/grid children from forcing horizontal overflow with `min-width: 0`.
- Added safe wrapping for long payment references, bank details, webhook values and product/cart text.
- Preserved safe-area spacing and bottom-navigation scroll padding.
- Preserved 48px touch targets and 52px primary checkout action targets on small screens.
- Hardened POS card/header wrapping on narrow screens.
- Hardened statistics/admin table containers for narrow viewports.
- Added a frontend acceptance contract covering viewport breakpoints, overflow prevention, touch targets, navigation semantics and critical checkout security attributes.
- Added a guard that frontend controllers remain presentation/interaction only.

## Acceptance targets

| Target | Requirement |
|---|---|
| 360px portrait | No intentional horizontal overflow; checkout controls remain usable |
| 390px portrait | Same, with slightly more room for card content |
| 360/390px landscape | POS layout remains usable and bottom navigation remains available |
| Touch | Critical controls remain >= 48px; primary checkout >= 52px |
| Accessibility | Existing labels, `aria-current`, `aria-controls`, live regions and focus behavior remain intact |
| Security | Checkout retains CSRF and `Idempotency-Key` contracts |

## Architecture constraints

- Backend/domain/application remains the source of truth.
- No business rule is introduced into Twig or Stimulus.
- No payment, stock, order or persistence mutation is implemented in frontend code.
- `public/assets/controllers/` is not modified by this phase. Source controllers remain under `assets/controllers/`.

## Validation

Static validation expected for this phase:

```bash
php -l tests/Frontend/Phase109MobileAcceptanceHardeningContractTest.php
php -l src/Controller/Order/CheckoutController.php
php -l src/Controller/Order/OrderLifecycleController.php
git diff --check
```

The full Symfony/PHPUnit suite should be run in an environment with Composer dependencies installed.
