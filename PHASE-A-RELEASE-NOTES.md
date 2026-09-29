# PHASE A — Manage User — Release Overlay

This ZIP is a **flat project-root overlay**. Extract it into the existing Symfony project root.

Included:
- User management list/detail/create/edit
- Search/status/role filters and DB-side pagination
- Activate/deactivate
- Admin password reset
- Existing role model (`ROLE_USER`, `ROLE_ADMIN`, `ROLE_ROOT`) reused
- Existing `USER_MANAGE` Permission/Voter reused
- AuditLog integration without plaintext passwords
- Transaction boundaries for mutations
- Pessimistic row locking for concurrent role/status/password mutations
- Mobile-first Twig UI and navigation
- HTTP/integration/unit/concurrency tests

No new Doctrine migration is required: the existing `users` schema already contains the fields used by Phase A.

Validation performed in this environment:
- PHP syntax checks: PASS for all changed PHP files.
- Full PHPUnit / Symfony console gates: NOT RUN because the supplied source ZIP intentionally has no `vendor/` directory and Composer is unavailable in the execution environment.

After extraction on the target project, run:

```bash
php bin/phpunit
APP_ENV=test php bin/console doctrine:schema:validate
APP_ENV=test php bin/console lint:container
php bin/console asset-map:compile
git diff
git status
```

This overlay intentionally does **not** contain `vendor/`, `.env`, database credentials, or the full repository snapshot.


## Phase A hardening — User activity + protected root administrator

- Existing `UserSession` is now used as the activity source of truth; no tracking table was introduced.
- Successful login creates/refreshes a `UserSession` with login time, last activity, IP, user-agent and device.
- Authenticated requests refresh `lastActivityAt` at a bounded 30-second write interval.
- Logout records the session as `LOGGED_OUT`.
- User detail shows recent sessions and their activity/security state.
- Admin/root can explicitly revoke a target user's active session.
- Deactivating a user atomically revokes the user's active sessions, making remote deactivation effective on the next request.
- The existing `ROLE_ROOT` remains the highest role. Root is hidden from non-root user-management lists/details and cannot be deactivated.
- `ROLE_ADMIN` accounts are fully tracked by the same `UserSession` mechanism and can be managed by the root; an admin may manage non-root accounts according to `USER_MANAGE`.
- No new RBAC, UserSession entity, or permission system was introduced; existing `SESSION_VIEW`, `SESSION_REVOKE`, `USER_MANAGE`, `ROLE_ADMIN`, and `ROLE_ROOT` are reused.
