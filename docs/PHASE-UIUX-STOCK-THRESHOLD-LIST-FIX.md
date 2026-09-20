# Stock list + low threshold fix

- Stock page now lists active products by default; search remains optional.
- Stock list displays current stock, low-stock threshold, and Low stock/OK status.
- Stock detail allows users with PRODUCT_EDIT to update the low-stock threshold.
- Threshold update uses a dedicated application command and transaction.
- Product list/detail also display low-stock threshold and computed stock status.
- Stock quantity adjustments remain a separate STOCK_ADJUST operation with idempotency and movement history.
