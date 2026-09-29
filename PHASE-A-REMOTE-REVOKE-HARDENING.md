# PHASE A — Remote Session Revoke Hardening

## Scope

- Enforce `UserSession` terminal states before Symfony Security restores the session token.
- Remote `REVOKED` session is invalidated on the next request.
- Preserve `UserSession` history; no delete and no schema change.
- Invalidate signed Symfony Remember-Me cookies on remote session revoke by including `updatedAt` in `remember_me.signature_properties` and updating the target user's security timestamp.
- Add real HTTP integration coverage for current-session remote revoke and Remember-Me bypass prevention.

## Expected behavior

1. User logs in in Browser B.
2. Admin revokes Browser B's `UserSession` from Browser A.
3. Browser B may remain visually unchanged until it sends another request.
4. On the next request, the revoked Symfony session is invalidated before the firewall restores authentication.
5. Browser B is redirected to `/auth/login`.
6. If Browser B had a Remember-Me cookie, it is also invalidated because `updatedAt` changed and is part of the Remember-Me signature.
7. The `UserSession` row remains `REVOKED` for activity/audit history.

## Database

No migration is introduced. Existing `users.updated_at` and `user_sessions` columns are reused.

## Validation

Static PHP syntax checks pass for the modified implementation and new integration test. Full PHPUnit/container/schema validation requires the project's installed dependencies and reachable test MySQL database.

## Native Symfony switch_user

The main firewall also enables Symfony's native `switch_user` listener with `_switch_user` and `ROLE_ADMIN`.

- `ROLE_ADMIN` can impersonate `ROLE_USER` and `ROLE_ADMIN` accounts.
- `ROLE_ADMIN` cannot impersonate `ROLE_ROOT`; only `ROLE_ROOT` may impersonate `ROLE_ROOT`.
- Symfony's `UserChecker` remains authoritative for inactive targets.
- The physical `UserSession` remains owned by the original browser user; impersonation does not create a fake target session.
- If an impersonated target becomes inactive during impersonation, the next request exits impersonation and preserves the original administrator session.
- Remote revocation of an actual target session does not silently revoke the administrator's physical session, because Symfony impersonation is a token overlay rather than a separate browser session.

Symfony documents `switch_user`, `SwitchUserToken`, and `SwitchUserEvent` as the native impersonation mechanism. urlSymfony impersonation documentationhttps://symfony.com/doc/7.4/security/impersonating_user.html

## User hierarchy + protected ROOT bootstrap

The user-management boundary is now strict and centralized in `UserManagementPolicy`:

- `ROLE_ROOT` is the protected master account. It is never listed or addressable through normal User Management, even by another ROOT account.
- `ROLE_ADMIN` is the next tier. Only ROOT may see/manage/switch to ADMIN accounts.
- ADMIN may see/manage/switch only to ROLE_USER accounts.
- ROLE_USER has no User Management permission and cannot see ADMIN/ROOT through the User Management surface.
- ROOT cannot be created, promoted to, edited, deactivated, password-reset, or session-revoked through normal User Management endpoints.
- A single ROOT account is bootstrapped by `app:bootstrap-root` from runtime secrets. The plaintext password exists only in process memory while it is hashed with Symfony's configured password hasher; only the hash is persisted.
- Native Symfony `switch_user` remains the impersonation mechanism. The UI now exposes a `Switch` action for permitted targets and an `Exit switch user` action while impersonating.
- `ROLE_ADMIN` can never switch to ROOT or another ADMIN; ROOT can switch to ADMIN/USER, but never ROOT.
