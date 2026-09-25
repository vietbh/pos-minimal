# Sales Point + Change Password Update

## Scope

This update is based on the supplied current project archive.

### Sales point model

The domain uses a reusable `SalesPoint` abstraction instead of making `POS` and `Table` separate order concepts.

- `SalesPoint.code`: stable system identifier (`POS-01`, `TABLE-A1`, ...)
- `SalesPoint.name`: user-facing name (`Quầy chính`, `Bàn A1`, ...)
- `SalesPoint.type`: `POS` or `TABLE`
- `SalesPoint.status`: active/inactive
- `SalesPoint.group`: optional `SalesPointGroup` for floors, counters, areas, etc.

Orders and bank-payment checkout sessions can reference a sales point. The relation is nullable for backward compatibility with existing orders/tests/data.

### POS behavior

The POS screen now exposes an explicit sales-point selector. Selection is stored in the authenticated Symfony session, while checkout remains server-authoritative: the application resolves the selected ID and rejects missing/inactive points.

The selected sales point is included in the checkout idempotency fingerprint so changing the point changes the request identity.

### Admin behavior

`/admin/sales-points` provides:

- create sales-point groups
- create POS/table sales points
- activate/deactivate points
- view grouping and type

Permissions:

- `SALES_POINT_VIEW`
- `SALES_POINT_SELECT`
- `SALES_POINT_MANAGE`

Regular authenticated users can view/select active points. Admin/root users can manage them.

### Change password

Profile now includes a server-side change-password form:

- current password verification
- minimum 8-character new password
- confirmation matching
- new password must differ from current password
- Symfony password hasher used for the new hash
- CSRF protected

Route: `POST /app/profile/password`.

## Persistence

Migration:

`Version20260924121500`

Creates:

- `sales_point_groups`
- `sales_points`

Adds nullable `sales_point_id` to:

- `orders`
- `checkout_payment_sessions`

## Architecture constraints preserved

- Domain/application remains authoritative.
- No business rule is implemented in Twig or Stimulus.
- No files under `public/assets/controllers/` were modified.
- Checkout business mutations remain synchronous and transactional.
- Sales-point selection is presentation/session state; checkout validates the selected point again on the server.
