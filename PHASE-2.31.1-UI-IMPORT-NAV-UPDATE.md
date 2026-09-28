# PHASE 2.31.1 — Import UI Navigation Update

Updated on top of `mobile-pos-phase-2.31-flat.zip`.

## UI changes

- Added **Import Excel** to the authenticated Admin/Management navigation when the user has `PRODUCT_CREATE` or `STOCK_ADJUST`.
- Added the same entry to the mobile navigation menu.
- Product list toolbar now has:
  - **Tải template sản phẩm**
  - **Import Excel**
- Stock list toolbar now has:
  - **Tải template tồn kho**
  - **Import Excel**
- Import upload/history/detail screens now extend the existing authenticated admin layout instead of the generic base layout.
- Added template download and import-history shortcuts throughout the import screens.
- Added Vietnamese/English translation keys for the new UI.
- Import history access accepts `PRODUCT_VIEW` or `STOCK_ADJUST`, matching the navigation visibility.
- Reused existing admin CSS classes; no new UI framework was introduced.

## Important

This update changes navigation/UI only. The Phase 2.31 Product/Stock import business flow remains unchanged:

- Product import → existing Product application flow.
- Stock import → existing `AdjustStockHandler`.
- Messenger remains the background execution mechanism.
- Stock delta remains `quantity_change`, not absolute stock.

## Verification performed in this environment

- PHP syntax checks passed for modified PHP files.
- YAML syntax checks passed for `translations/messages.vi.yaml` and `translations/messages.en.yaml`.
- Full Symfony/Twig/PHPUnit runtime verification was not possible because the flat ZIP does not contain `vendor/` and Composer is unavailable in this environment.
