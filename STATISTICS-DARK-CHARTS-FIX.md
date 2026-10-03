# Statistics charts + dark theme fix

Patch files are rooted at the Symfony project root.

- Adds data-driven ApexCharts rendering for the Sales, Products, Payments, Customers and Debts tab panels.
- Uses horizontal bars for comparison-oriented tabs and a donut for payment mix.
- Improves dark-theme chart label, axis, legend, grid and tooltip contrast.
- Uses compact axis labels (k / tr / tỷ) without changing underlying values.
- Empty datasets display an explicit empty state; no fake points are added.

After applying, import `assets/styles/statistics-dark-charts.css` from the existing main stylesheet (or append its rules to `assets/styles/app.css`) if the build does not automatically include standalone CSS files. Then run the project's normal asset build.

Note: tab charts visualize fields already returned by the statistics tab endpoint. The patch does not add new business queries or invent missing metrics.
