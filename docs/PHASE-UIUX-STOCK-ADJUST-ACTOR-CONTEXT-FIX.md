# Stock adjustment actor-context fix

Fixed the admin stock adjustment flow so the controller initializes `RuntimeActorContextProvider` from the authenticated active user before invoking `AdjustStockHandler`. The handler requires actor context for idempotency, audit logging, and user/session resolution.

Also added strict controller-side validation for `quantityChange` before constructing `AdjustStockInput`: blank/non-integer values and zero are rejected. The actor context is always cleared in `finally`.

## Verification

- `php -l src/Controller/Admin/StockController.php` passes.
- Stock adjustment route remains `POST /admin/stock/{productId}/adjust`.
- Low-threshold route remains separate.
