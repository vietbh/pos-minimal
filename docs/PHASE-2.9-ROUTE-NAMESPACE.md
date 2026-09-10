# Phase 2.9 — Route Namespace Convention

## Canonical prefixes

- `/auth/*` — authentication
- `/app/*` — user-facing POS/application UI and actions
- `/admin/*` — admin/back-office UI and actions
- `/media/*` — media/resource serving
- `/health` — infrastructure health

Route prefixes are not an authorization mechanism. Security, Voters and permission checks remain authoritative.

## Canonical routes

```text
/auth/login
/auth/logout

/app/pos
/app/orders
/app/orders/{id}
POST /app/checkout
POST /app/orders/{id}/cancel
POST /app/orders/{id}/refund

/admin/products/...
/admin/customers/...
/admin/stock/...

/media/products/{id}/{variant}
/health
```

The route names are intentionally preserved (`login`, `logout`, `pos`, `pos_checkout`, `orders_index`, `orders_show`, `order_cancel`, `order_refund`, etc.) so Twig and controller redirects using `path()` / `redirectToRoute()` do not need a route-name redesign.

## Apply

From the project root:

```bash
python3 scripts/migrate-route-namespaces.py
php bin/console cache:clear
php bin/console debug:router
```

Then verify that the old UI/action paths are gone:

```bash
grep -RInE "(/api/pos/checkout|/pos'|/api/orders|/orders'|/login'|/logout'|/product-images/)" src templates tests config --exclude-dir=vendor
```

Known acceptable matches should only be unrelated filesystem names or documentation examples; no active route attribute should use the old prefixes.

## Validation

```bash
php bin/console lint:container
php bin/phpunit
php bin/console doctrine:schema:validate
php bin/console asset-map:compile
```
