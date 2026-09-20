# Product Form Validation Hardening

## Scope

The product create/edit form now validates input before the application command is invoked.

### Browser-side validation

- Product name is required and limited to 255 characters.
- Selling price is required.
- Selling price and optional cost price must be non-negative decimal values with at most two decimal places.
- Because POS money is VND, non-zero fractional digits are rejected.
- Category selection and inline category creation remain mutually exclusive.
- SKU is limited to 100 characters.
- Unit is limited to 50 characters.
- Low-stock threshold is a non-negative integer.

### Server-side validation

The controller performs the same structural validation before constructing `Money`/`Sku` objects or invoking `CreateProductHandler`/`UpdateProductHandler`.

Optional `categoryId` is parsed safely: an empty select value becomes `null`; invalid non-empty values return a validation error instead of Symfony `InputBag` throwing `BadRequestHttpException`.

## Required fields

For product create/update:

- `name`
- `sellingPrice`

The following remain optional:

- `sku` (auto-generated when empty)
- `unit`
- `categoryId`
- `categoryName` (create only)
- `costPrice`
- `lowStockThreshold`
- `note`

## Verification

PHP syntax checks and JavaScript syntax check pass. PHPUnit cannot be executed from this source archive because it does not contain `vendor/`.
