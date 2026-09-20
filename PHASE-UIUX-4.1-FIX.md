# PHASE UI/UX 4.1 — Responsive Shell Visual Fix

## Scope

Presentation-only repair based on the supplied UI/UX base ZIP.

### Desktop / laptop
- Persistent left sidebar.
- Content reserves the full sidebar width.
- Main content uses the available desktop canvas instead of a phone-width column.
- Dashboard KPI grid expands to 4 columns on wide screens.
- Quick-access cards expand to 3 columns on wide screens.

### Mobile / tablet
- Sidebar is hidden at <= 900px.
- Sticky compact top bar is shown.
- Fixed bottom navigation is shown.
- POS remains the primary center action.
- Safe-area insets are respected.
- Main content receives bottom padding so the bottom navigation does not cover content.

## Critical fix
The supplied base contained accidental shell/Python command text inside `assets/styles/app.css` immediately before the responsive shell CSS. That made the desktop shell unreliable and caused the sidebar/content to visually overlap. The accidental block was removed and the responsive shell was rewritten as valid CSS.

## Business safety
No controller, domain, payment, order, stock, debt, permission, Voter, or persistence logic was changed.
