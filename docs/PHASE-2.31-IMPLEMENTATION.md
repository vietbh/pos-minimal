# PHASE 2.31 — Product + Stock Excel Import

Implemented against the current Mobile POS source.

## Scope

- XLSX product import (create-only; reuses `CreateProductHandler`)
- XLSX stock import (quantity delta; reuses `AdjustStockHandler`)
- Downloadable v1 templates
- Upload + synchronous validation/preview
- Confirm + Messenger `async` worker
- ImportBatch / row state / row error persistence
- ActorContext propagation into worker
- Error CSV
- Mobile-first Twig screens
- Permission reuse: `PRODUCT_CREATE` and `STOCK_ADJUST`
- Doctrine migration

## Dependency

`phpoffice/phpspreadsheet:^5.0` was added to `composer.json`.

The supplied environment did not contain Composer, so `composer.lock` could not be regenerated here. On the development machine run:

```bash
composer update phpoffice/phpspreadsheet --with-dependencies
```

Then run the normal project test suite.

## Templates

- `docs/import-templates/product-import-template-v2.xlsx`
- `docs/import-templates/stock-import-template-v1.xlsx`

## Important semantics

Product import is create-only. Existing SKU rows fail.

Stock import uses `Quantity Change`; it never sets `Product.stockQuantity` directly. Stock mutation goes through the existing `AdjustStockHandler`, preserving locking, `StockMovement`, `AuditLog`, and idempotency behavior.


## Phase 2.31.2 — Product creation UX update

- Product SKU is optional when creating/importing a product.
- If SKU is blank, `CreateProductHandler` generates a stable, human-readable SKU from Category + Product Name.
- The generated SKU is assigned once and is not regenerated when the product name or category changes.
- When the generated base SKU already exists, a short collision suffix is added without using `MAX()+1`.
- Product category input is now a name rather than a numeric Category ID for Excel import.
- Category lookup is case-insensitive after trimming; an existing active category is reused, otherwise a new category is created inside the existing product creation transaction.
- Inactive matching categories are rejected rather than silently reactivated.
- Product import template is now `product-import-template-v2.xlsx`.
