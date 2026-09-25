# PHASE 10.1 — Design System Foundation

## Scope
Shared visual primitives only. No business logic, domain behavior, permissions, routing, checkout, payment, stock, or persistence changes.

## Implemented
- Centralized Phase 10.1 design tokens in `assets/styles/app.css`.
- Legacy `--color-*` variables now alias the foundation tokens so existing UI inherits the new palette without a parallel design system.
- Mobile-first typography and control sizing.
- Minimum 48px touch targets and 52px primary actions.
- Visible keyboard focus states and reduced-motion support.
- Semantic status, alert, loading, empty, error, and success primitives.
- Responsive dialog/bottom-sheet CSS foundation.
- 360px baseline and coarse-pointer hardening.
- Base document theme color updated to the primary design-system color.
- Added `DesignSystemFoundationContractTest`.

## Invariants
- CSS is presentation-only.
- Existing Twig/Stimulus business behavior is unchanged.
- Backend/domain/application layers remain the source of truth.
- Status styles include a visual marker in addition to color.
