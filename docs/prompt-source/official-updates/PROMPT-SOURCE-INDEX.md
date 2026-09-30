# Mobile POS — Prompt Source Index / Update Pack

This directory contains the prompt sources that must travel with the codebase so future implementation agents have the same source of truth.

## Official updates included

1. `PHASE-2.18.1-Dashboard-Simplification-Role-Aware-Chart.md`
   - Minimal Home
   - role/permission-aware tabs
   - one simple overview chart
   - real read-model data
   - Active Guild strict rule
   - `role_label` / `is_admin_role` Twig contract

2. `PHASE-2.18.2-Global-UI-Foundation-Forms-Dark-Light-Theme.md`
   - global UI primitives
   - forms
   - buttons/cards/inputs/select/textarea/checkbox/radio
   - tables/status/alerts/tabs/modal
   - Light/Dark/System themes
   - existing preferences
   - responsive/accessibility
   - CSS cleanup

3. `FIX-HomeController-role-label-contract.md`
   - documents the current `role_label` runtime error and the required controller/view-model contract.

## Original uploaded prompt sources

The `original-uploaded/` directory preserves the prompt source files available in this project context at packaging time.

## Future phase rule

Before creating any UI element:

> Inspect and reuse the Global UI Foundation. Do not introduce a parallel visual style.

Before adding authorization logic:

> Reuse the existing Permission/Voter architecture. Do not create a second RBAC/capability engine.
