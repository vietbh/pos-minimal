# Statistics charts source fix

Updated source files in `assets/` (not only generated `public/assets/`):
- `assets/controllers/statistics_tabs_controller.js`: guards missing form/panel/endpoint, fetches the Symfony tab endpoint, consumes `data.rows`, and renders charts for sales/products/payments/customers/debts.
- `assets/styles/statistics-dark-charts.css`: increases dark-theme chart label contrast.

Built asset output should be regenerated using the project's configured asset build command. The ZIP preserves the original base archive and its existing files.
