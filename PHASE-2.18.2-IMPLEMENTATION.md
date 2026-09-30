# PHASE 2.18.2 — Global UI Foundation + Forms + Dark/Light/System Theme

Implemented directly on the supplied `mobile-pos-current.zip` base.

## Scope

- Kept the existing Symfony/domain/application architecture unchanged.
- Kept the existing persisted appearance preference: `light`, `dark`, `system`.
- Added one canonical semantic UI token layer at the end of `assets/styles/app.css`.
- Unified existing button classes without requiring a template-wide rename.
- Unified text/select/textarea states: default, hover, focus, invalid, disabled, readonly.
- Unified checkbox/radio presentation while preserving native semantics.
- Unified card/surface presentation across existing `.card`, `.admin-card`, `.admin-section`, `.pos-card`, `.form-card`, `.statistics-card`, home/settings surfaces.
- Unified table, badge/status, alert/flash, empty, dialog/modal and loading presentation.
- Added Light/Dark/System semantic theme values using the existing `data-appearance` contract.
- Reused existing density, high-contrast and reduced-motion preferences.
- Preserved mobile touch targets and 320/360/390px hardening.
- Business data is explicitly kept wrap-safe; no new ellipsis/hidden business information rules were introduced.

## Files changed

- `assets/styles/app.css`
- `PHASE-2.18.2-IMPLEMENTATION.md`

`assets/app.css` was intentionally not removed: the current runtime imports `assets/styles/app.css`, while existing frontend contract tests and historical compatibility still reference the legacy file.

## Validation

- CSS brace/static token validation: OK.
- PHP syntax sweep across `src`, `tests`, `config`, `migrations`, `bin`: OK.
- PHPUnit could not execute from the supplied ZIP because `vendor/phpunit/phpunit/phpunit` is not present in the archive.
