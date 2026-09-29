# PHASE A.2 — User visibility + ROOT-only analytics

## Changes

- Profile shows only the currently authenticated user's role.
- ADMIN user-management list can see all non-ROOT accounts, including peer ADMIN accounts.
- ADMIN can mutate/switch only USER accounts; peer ADMIN accounts are view-only.
- ROOT can manage ADMIN + USER accounts, while the ROOT account itself remains hidden and non-addressable from normal user management.
- Role labels are translated in English/Vietnamese instead of exposing raw `ROLE_*` values in normal UI.
- `UserSession` records exact request count and last request method/path/IP.
- `/root/analytics` is a separate ROOT-only analysis surface.
- ROOT analytics shows non-ROOT request/session volume, latest access IP/device/request and recent audited actions.
- ROOT itself is filtered from analytics presentation.
- No GPS is collected silently. Current "location" is server-observed IP + request path. Exact GPS would require explicit browser permission and a separate privacy decision.

## Database

New migration:

`Version20260929090000`

Adds to `user_sessions`:

- `request_count BIGINT UNSIGNED NOT NULL DEFAULT 0`
- `last_request_method VARCHAR(10) NULL`
- `last_request_path VARCHAR(512) NULL`

Do not use `doctrine:schema:update --force`.

## Security invariants

- USER cannot access `/admin/users`.
- ADMIN sees USER + ADMIN, never ROOT.
- ADMIN cannot edit/deactivate/reset/revoke/switch another ADMIN.
- ROOT can manage ADMIN + USER.
- ROOT remains hidden from `/admin/users/{id}`.
- `/root/analytics` returns 404 to non-ROOT actors so the ROOT-only surface is not discoverable through authorization errors.
- Analytics is not linked for non-ROOT users.

## Native Symfony switch_user

The existing Symfony `switch_user` implementation remains the impersonation mechanism. No custom impersonation table is introduced.
